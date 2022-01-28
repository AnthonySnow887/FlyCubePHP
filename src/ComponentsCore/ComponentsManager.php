<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 20.07.21
 * Time: 15:41
 */

namespace FlyCubePHP\ComponentsCore;

include_once __DIR__.'/../HelperClasses/Enum.php';
include_once __DIR__.'/../HelperClasses/CoreHelper.php';
include_once __DIR__.'/../Core/Error/Error.php';
include_once __DIR__.'/../Core/Logger/Logger.php';
include_once 'BaseComponent.php';
include_once 'DependencyTreeElement.php';

use Exception;
use \FlyCubePHP\Core\Config\Config as Config;
use \FlyCubePHP\Core\Error\Error as Error;
use \FlyCubePHP\Core\Logger\Logger as Logger;
use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;
use \FlyCubePHP\Core\AssetPipeline\AssetPipeline as AssetPipeline;
use \FlyCubePHP\Core\ApiDoc\ApiDoc as ApiDoc;

class CMState extends \FlyCubePHP\HelperClasses\Enum {
    const NOT_LOADED    = 0; # не загружен
    const LOADED        = 1; # загружен
    const INITIALIZED   = 2; # проинициализирован
}

class ComponentsManager
{
    private static $_instance = null;

    private $_state = CMState::NOT_LOADED;
    private $_plugins_dir = "";
    private $_plugins = array();
    private $_ignore_list = array();

    const IGNORE_LIST_NAME = "ignore-list.conf";
    const PLUGINS_DIR = "plugins";
    const CONFIG_DIR = "config";
    const CONTROLLERS_DIR = "controllers";
    const CHANNELS_DIR = "channels";
    const MODELS_DIR = "models";
    const PLUGIN_INIT_FILE = "init.php";
    const PLUGIN_ROUTES_FILE = "routes.php";


    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function instance(): ComponentsManager {
        if (static::$_instance === null)
            static::$_instance = new static();
        return static::$_instance;
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     */
    private function __construct() {
    }

    /**
     * prevent the instance from being cloned (which would create a second instance of it)
     */
    private function __clone() {
    }

    /**
     * prevent from being unserialized (which would create a second instance of it)
     * @throws Exception Cannot unserialize singleton
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }

    /**
     * Получить состояние менеджера
     * @return int
     */
    public function state(): int {
        return $this->_state;
    }

    /**
     * Получить массив плагинов
     * @return array
     */
    public function plugins(): array {
        return $this->_plugins;
    }

    /**
     * Получить массив информаций о контроллерах плагинов
     * @return array
     */
    public function pluginsControllers(): array {
        $controllers = array();
        foreach ($this->_plugins as $pl)
            $controllers [] = $pl->controllers();
        return $controllers;
    }

    /**
     * Получить массив информаций о контроллерах всего приложения
     * @return array
     */
    public function applicationAllControllers(): array {
        $controllers = array();
        $app_controllers_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "app", ComponentsManager::CONTROLLERS_DIR);
        $app_controllers = CoreHelper::scanDir($app_controllers_dir);
        foreach ($app_controllers as $controller) {
            if (!preg_match("/^.*Controller\.php$/", $controller))
                continue;
            $ctrlCName = CoreHelper::fileName($controller, true);
            $ctrlName = CoreHelper::fileName($controller);
            $ctrlPath = $controller;
            $controllers[] = array("class_name" => $ctrlCName, "name" => $ctrlName, "path" => $ctrlPath);
        }
        return array_merge($controllers, $this->pluginsControllers());
    }

    /**
     * Получить каталог расположения плагинов
     * @return string
     */
    public function pluginsDir(): string {
        return $this->_plugins_dir;
    }

    /**
     * Получить список игнорируемых плагинов
     * @return array
     */
    public function ignoreList(): array {
        return $this->_ignore_list;
    }

    /**
     * Добавить плагин в менеджер
     * @param BaseComponent $plugin - объект плагина
     * @throws
     */
    public function appendPlugin(BaseComponent &$plugin) {
        if (empty($plugin->name()))
            trigger_error("[ComponentsManager] Append plugin failed! Invalid plugin name (empty)!", E_USER_ERROR);
        if (array_key_exists($plugin->name(), $this->_plugins)) {
            $plName = $plugin->name();
            trigger_error("[ComponentsManager] Append plugin failed! Plugin with name \"$plName\" already added!", E_USER_ERROR);
        }

        $tmpRef = new \ReflectionClass($plugin);
        $tmpPathStr = $tmpRef->getFilename();
        unset($tmpRef);
        $tmpPathStr = str_replace($this->_plugins_dir, "", $tmpPathStr);
        if ($tmpPathStr[0] == DIRECTORY_SEPARATOR)
            $tmpPathStr = ltrim($tmpPathStr, $tmpPathStr[0]);
        $tmpPathLst = explode(DIRECTORY_SEPARATOR, $tmpPathStr);
        if (count($tmpPathLst) > 0)
            array_pop($tmpPathLst);

        // --- get plugin dir and load plugin models and controllers ---
        $plDir = CoreHelper::buildPath($tmpPathLst);
        $plDirFull = CoreHelper::buildPath(CoreHelper::rootDir(), ComponentsManager::PLUGINS_DIR, $plDir);
        $plDirModels = CoreHelper::buildPath($plDirFull, "app", ComponentsManager::MODELS_DIR);
        $plDirControllers = CoreHelper::buildPath($plDirFull, "app", ComponentsManager::CONTROLLERS_DIR);

        // --- load models ---
        $plModelFiles = CoreHelper::scanDir($plDirModels);
        foreach ($plModelFiles as $model) {
            $fExt = pathinfo($model, PATHINFO_EXTENSION);
            if (strcmp(strtolower($fExt), "php") !== 0)
                continue;
            try {
                include_once $model;
            } catch (\Exception $e) {
                throw Error::makeError([
                    'tag' => 'components-manager',
                    'message' => $e->getMessage(),
                    'previous' => $e
                ]);
            }
        }

        // --- load controllers ---
        $plControllers = array();
        $plControllerFiles = CoreHelper::scanDir($plDirControllers);
        foreach ($plControllerFiles as $controller) {
            if (!preg_match("/^.*Controller\.php$/", $controller))
                continue;
            $ctrlCName = CoreHelper::fileName($controller, true);
            $ctrlName = substr($ctrlCName, 0, strlen($ctrlCName) - 10);
            $ctrlPath = $controller;
            $plControllers[] = array("class_name" => $ctrlCName, "name" => $ctrlName, "path" => $ctrlPath);
            include_once $controller;
        }
        $plugin->setDirectory($plDir);
        $plugin->setControllers($plControllers);
        $this->_plugins[$plugin->name()] = $plugin;
    }

    /**
     * Поиск плагина по названию класса
     * @param string $class_name
     * @return BaseComponent|null
     */
    public function pluginByClassName(string $class_name)/*: BaseComponent|null*/ {
        $plugin = $this->searchPluginByClassName($class_name);
        return $this->getSuccessPlugin($plugin);
    }

    /**
     * Поиск плагина по названию его контроллера
     * @param string $controller_name
     * @return BaseComponent|null
     */
    public function pluginByControllerName(string $controller_name)/*: BaseComponent|null*/ {
        $plugin = $this->searchPluginByControllerName($controller_name);
        return $this->getSuccessPlugin($plugin);
    }

    /**
     * Метод загрузки плагинов
     * @param string $dir - путь до каталога с плагинами
     * @throws
     * @return bool
     *
     * ПРИМЕЧАНИЕ: используется системой! Использование запрещено!
     */
    public function loadPlugins(string $dir): bool {
        if (!is_dir($dir))
            return false;
        if ($this->_state != CMState::NOT_LOADED)
            return false;

        $this->loadIgnoreList(CoreHelper::buildPath(CoreHelper::rootDir(), "config"));

        $this->_plugins_dir = $dir;
        $dirLst = scandir($dir);
        foreach ($dirLst as $chDir) {
            if (in_array($chDir,array(".","..")))
                continue;
            if (in_array($chDir, $this->_ignore_list))
                continue;
            if (is_dir(CoreHelper::buildPath($dir, $chDir))
                && file_exists(CoreHelper::buildPath($dir, $chDir, ComponentsManager::PLUGIN_INIT_FILE))) {
                try {
                    include_once CoreHelper::buildPath($dir, $chDir, ComponentsManager::PLUGIN_INIT_FILE);
                } catch (\Exception $e) {
                    throw Error::makeError([
                        'tag' => 'components-manager',
                        'message' => $e->getMessage(),
                        'previous' => $e
                    ]);
                }
            }
        }
        $this->_state = CMState::LOADED;
        $checkPlCount = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_CHECK_PLUGINS_COUNT, true));
        if ($checkPlCount === false)
            return true;
        return (count($this->_plugins) > 0);
    }

    /**
     * Метод инициализации загруженных плагинов.
     * @return bool
     *
     * ПРИМЕЧАНИЕ: используется системой! Использование запрещено!
     */
    public function initPlugins(): bool {
        if ($this->_state != CMState::LOADED)
            return false;
        $checkPlCount = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_CHECK_PLUGINS_COUNT, true));
        if (count($this->_plugins) == 0)
            return !$checkPlCount;
        $init_success = 0;
        foreach ($this->_plugins as &$pl) {
            if ($pl->state() == BCState::INIT_SUCCESS) {
                $init_success += 1;
                continue;
            }
            if ($this->initPlugin($pl)) {
                $init_success += 1;
            }
        }
        $this->_state = CMState::INITIALIZED;
        return ($init_success > 0);
    }



    //--- private ---

    /**
     * Загрузить список игнорируемых плагинов
     * @param string $dir - каталог с файлом игнор-листа
     */
    private function loadIgnoreList(string $dir) {
        if (!is_dir($dir))
            return;
        if (!file_exists(CoreHelper::buildPath($dir, ComponentsManager::IGNORE_LIST_NAME)))
            return;
        if ($file = fopen(CoreHelper::buildPath($dir, ComponentsManager::IGNORE_LIST_NAME), "r")) {
            while (!feof($file)) {
                $line = trim(fgets($file));
                if (empty($line))
                    continue;
                if (substr($line, 0, 1) == "#")
                    continue;
                $this->_ignore_list[] = $line;
            }
            fclose($file);
        }
    }

    /**
     * Метод инициализации плагина
     * @param BaseComponent $plugin - объект плагина
     * @return bool
     * @throws
     */
    private function initPlugin(BaseComponent &$plugin): bool {
        if (empty($plugin->name()))
            return false;
        if ($plugin->state() == BCState::INIT_FAILED)
            return false;
        if ($plugin->state() == BCState::INIT_SUCCESS)
            return true;

        // load deps tree and check
        $tree = new DependencyTreeElement($plugin->name(), $plugin->version());
        if (!$this->loadPluginDependencyTree($plugin, $tree)) {
            $plugin->setState(BCState::INIT_FAILED);
            $plugin->setLastError($tree->error());

            // show error
            $plName = $plugin->name();
            $plVers = $plugin->version();
            $this->logMessage(Logger::ERROR, "[ComponentsManager] Init plugin $plName (ver. $plVers) FAILED!\n" . $plugin->lastError());

            // check cyclic
            if ($tree->isCyclic()) {
                $tree_str = $this->dependencyTreeToString($tree);
                $this->logMessage(Logger::ERROR, "[ComponentsManager] Cyclic tree:\n$tree_str");
            }
            unset($tree);
            return false;
        }
        unset($tree);

        // Required dependency
        foreach ($plugin->dependence() as $spec) {
            if ($spec->type() != CDType::REQUIRED)
                continue;
            $dep_plugin = $this->searchPluginByName($spec->name());
            if (is_null($dep_plugin)) {
                $d_name = $spec->name();
                $d_vers = $spec->version();
                $plugin->setState(BCState::INIT_FAILED);
                $plugin->setLastError("Not found dependence! Dependence plugin: $d_name (ver. $d_vers)");

                // show error
                $this->logMessage(Logger::ERROR, "[ComponentsManager] " . $plugin->lastError());
                return false;
            }
            if ($plugin->name() == $dep_plugin->name()) {
                $plugin->setState(BCState::INIT_FAILED);
                $plugin->setLastError("Cyclic dependence!");

                // show error
                $this->logMessage(Logger::ERROR, "[ComponentsManager] " . $plugin->lastError());
                return false;
            }
            // check version
            if ($this->versionCompare($dep_plugin->compatVersion(), $spec->version()) > 0) {
                $d_name = $spec->name();
                $d_vers = $spec->version();
                $d_compat = $dep_plugin->compatVersion();
                $plugin->setState(BCState::INIT_FAILED);
                $plugin->setLastError("Invalid dependence plugin version! Dependence plugin: $d_name (compat ver. $d_compat; needed ver. $d_vers)");

                // show error
                $this->logMessage(Logger::ERROR, "[ComponentsManager] " . $plugin->lastError());
                return false;
            }

            if (!$this->initPlugin($dep_plugin)) {
                $d_name = $spec->name();
                $d_vers = $spec->version();
                $plugin->setState(BCState::INIT_FAILED);
                $plugin->setLastError("Init dependence plugin failed! Dependence plugin: $d_name (ver. $d_vers)");

                // show error
                $this->logMessage(Logger::ERROR, "[ComponentsManager] " . $plugin->lastError());
                return false;
            }
        }

        // Optional dependency
        foreach ($plugin->optionalDependence() as $spec) {
            if ($spec->type() != CDType::OPTIONAL)
                continue;
            $dep_plugin = $this->searchPluginByName($spec->name());
            if (is_null($dep_plugin)) {
                $d_name = $spec->name();
                $d_vers = $spec->version();
                $plugin->setLastWarning("Not found optional dependence! Dependence plugin: $d_name (ver. $d_vers)");

                // show warning
                $plName = $plugin->name();
                $plVers = $plugin->version();
                $this->logMessage(Logger::WARNING, "[ComponentsManager] Plugin $plName (ver. $plVers) warning:\n" . $plugin->lastWarning());
                continue;
            }
            if ($plugin->name() == $dep_plugin->name()) {
                $plugin->setLastWarning("Cyclic optional dependence!");

                // show warning
                $this->logMessage(Logger::WARNING, "[ComponentsManager] " . $plugin->lastWarning());
                continue;
            }
            // check version
            if ($this->versionCompare($dep_plugin->compatVersion(), $spec->version()) > 0) {
                $d_name = $spec->name();
                $d_vers = $spec->version();
                $d_compat = $dep_plugin->compatVersion();
                $plugin->setLastWarning("Invalid optional dependence plugin version! Dependence plugin: $d_name (compat ver. $d_compat; needed ver. $d_vers)");

                // show warning
                $plName = $plugin->name();
                $plVers = $plugin->version();
                $this->logMessage(Logger::WARNING, "[ComponentsManager] Plugin $plName (ver. $plVers) warning:\n" . $plugin->lastWarning());
                continue;
            }

            if (!$this->initPlugin($dep_plugin)) {
                $d_name = $spec->name();
                $d_vers = $spec->version();
                $plugin->setLastWarning("Init optional dependence plugin failed! Dependence plugin: $d_name (ver. $d_vers)");

                // show warning
                $plName = $plugin->name();
                $plVers = $plugin->version();
                $this->logMessage(Logger::WARNING, "[ComponentsManager] Plugin $plName (ver. $plVers) warning:\n" . $plugin->lastWarning());
                continue;
            }
        }

        try {
            if (!$plugin->init()) {
                $plugin->setState(BCState::INIT_FAILED);
                $plugin->setLastError("Init failed!");

                // show error
                $plName = $plugin->name();
                $plVers = $plugin->version();
                Logger::error("[ComponentsManager] Init plugin $plName (ver. $plVers) FAILED!");
            } else {
                $plugin->setState(BCState::INIT_SUCCESS);

                // --- load plugin routes ---
                $pl_routes = CoreHelper::buildPath(CoreHelper::rootDir(),
                                                    ComponentsManager::PLUGINS_DIR,
                                                    $plugin->directory(),
                                                    ComponentsManager::CONFIG_DIR,
                                                    ComponentsManager::PLUGIN_ROUTES_FILE);
                if (file_exists($pl_routes))
                    include_once $pl_routes;

                // --- load plugin js files ---
                $pl_js_dir = CoreHelper::buildPath(CoreHelper::rootDir(),
                                                    ComponentsManager::PLUGINS_DIR,
                                                    $plugin->directory(),
                                                    "app", "assets", "javascripts");
                AssetPipeline::instance()->appendJSDir($pl_js_dir);

                // --- load plugin css|scss files ---
                $pl_css_dir = CoreHelper::buildPath(CoreHelper::rootDir(),
                                                    ComponentsManager::PLUGINS_DIR,
                                                    $plugin->directory(),
                                                    "app", "assets", "stylesheets");
                AssetPipeline::instance()->appendCSSDir($pl_css_dir);

                // --- load plugin images ---
                $pl_image_dir = CoreHelper::buildPath(CoreHelper::rootDir(),
                                                    ComponentsManager::PLUGINS_DIR,
                                                    $plugin->directory(),
                                                    "app", "assets", "images");
                AssetPipeline::instance()->appendImageDir($pl_image_dir);

                // --- load plugin views ---
                $pl_views_dir = CoreHelper::buildPath(CoreHelper::rootDir(),
                                                    ComponentsManager::PLUGINS_DIR,
                                                    $plugin->directory(),
                                                    "app", "views");
                $pl_views_dir_lst = CoreHelper::scanDir($pl_views_dir, false, true);
                foreach ($pl_views_dir_lst as $vDir) {
                    if (!is_dir($vDir))
                        continue;
                    AssetPipeline::instance()->appendViewDir($vDir);
                }

                // --- load plugin lib/js files ---
                $pl_js_dir = CoreHelper::buildPath(CoreHelper::rootDir(),
                                                    ComponentsManager::PLUGINS_DIR,
                                                    $plugin->directory(),
                                                    "lib", "assets", "javascripts");
                AssetPipeline::instance()->appendJSDir($pl_js_dir);

                // --- load plugin lib/css|scss files ---
                $pl_css_dir = CoreHelper::buildPath(CoreHelper::rootDir(),
                                                    ComponentsManager::PLUGINS_DIR,
                                                    $plugin->directory(),
                                                    "lib", "assets", "stylesheets");
                AssetPipeline::instance()->appendCSSDir($pl_css_dir);

                // --- load plugin lib/images ---
                $pl_image_dir = CoreHelper::buildPath(CoreHelper::rootDir(),
                                                    ComponentsManager::PLUGINS_DIR,
                                                    $plugin->directory(),
                                                    "lib", "assets", "images");
                AssetPipeline::instance()->appendImageDir($pl_image_dir);

                // --- load plugin api-doc files ---
                $pl_api_doc_dir = CoreHelper::buildPath(CoreHelper::rootDir(),
                                                        ComponentsManager::PLUGINS_DIR,
                                                        $plugin->directory(),
                                                        "doc", "api");
                ApiDoc::instance()->appendApiDocDir($pl_api_doc_dir);

                // TODO load plugin help files...
            }
        } catch (\Exception $e) {
            $err_str = $e->getMessage();
            $plugin->setState(BCState::INIT_FAILED);
            $plugin->setLastError("Init failed! Error message: $err_str");

            // show error
            $plName = $plugin->name();
            $plVers = $plugin->version();
            $this->logMessage(Logger::ERROR, "Init plugin $plName (ver. $plVers) FAILED!\n" . $plugin->lastError());
        }
        return ($plugin->state() == BCState::INIT_SUCCESS);
    }

    /**
     * Сформировать дерево зависимостей плагина
     * @param BaseComponent $plugin
     * @param DependencyTreeElement $tree
     * @return bool
     */
    private function loadPluginDependencyTree(BaseComponent &$plugin, DependencyTreeElement &$tree): bool {
        if (empty($plugin->name())) {
            $tree->setError("Plugin is NULL!");
            return false;
        }
        if ($plugin->state() == BCState::INIT_FAILED) {
            $tree->setError("Invalid plugin object state! Plugin: " . $plugin->name() . " (ver. " . $plugin->version() . ")");
            return false;
        }
        $current_plugin_node = $tree->searchChild($plugin->name());
        if (is_null($current_plugin_node)) {
            $tree->setError("Not found plugin object in tree [Plugin: " . $plugin->name() . " (ver. " . $plugin->version() . ")]!");
            return false;
        }
        // Required dependency
        foreach ($plugin->dependence() as $spec) {
            if ($spec->type() != CDType::REQUIRED)
                continue;
            $dep_plugin = $this->searchPluginByName($spec->name());
            if (is_null($dep_plugin)) {
                $tree->setError("Not found dependence plugin [Plugin: " . $plugin->name() . " (ver. " . $plugin->version()
                    . "); Dep.Plugin: " . $spec->name() . "]!");
                return false;
            }
            if ($dep_plugin->state() == BCState::INIT_FAILED) {
                $tree->setError("Invalid dependence plugin state [Plugin: " . $plugin->name() . " (ver. " . $plugin->version()
                    . "); Dep.Plugin: " . $spec->name() . " (ver. " . $spec->version() . ")]!");
                return false;
            }
            $current_dep_node = $tree->searchChild($dep_plugin->name());
            if (!is_null($current_dep_node)) {
                $self_node = $current_dep_node->searchChild($plugin->name());
                if (!is_null($self_node)) {
                    $tree->setIsCyclic(true);
                    $tree->setError("Found cyclic dependence! [". $plugin->name() ." (ver. " . $plugin->version()
                        . ")] --> [" . $dep_plugin->name() . " (ver. " . $dep_plugin->version() . ")]");

                    // for out
                    $err_child = new DependencyTreeElement($dep_plugin->name(), $dep_plugin->version(), $current_plugin_node->nodeLevel() + 1);
                    $err_child->setIsCyclic(true);
                    $current_dep_node->appendChild($err_child);
                    return false;
                }
            }
            $tmp_child = new DependencyTreeElement($dep_plugin->name(), $dep_plugin->version(), $current_plugin_node->nodeLevel() + 1);
            if (is_null($current_dep_node))
                $tree->appendChild($tmp_child);
            else
                $current_dep_node->appendChild($tmp_child);

            if (!$this->loadPluginDependencyTree($dep_plugin, $tree))
                return false;
        }

        // Optional dependency
        foreach ($plugin->optionalDependence() as $spec) {
            if ($spec->type() != CDType::OPTIONAL)
                continue;
            $dep_plugin = $this->searchPluginByName($spec->name());
            if (is_null($dep_plugin))
                continue;
            if ($dep_plugin->state() == BCState::INIT_FAILED)
                continue;
            $current_dep_node = $tree->searchChild($dep_plugin->name());
            if (!is_null($current_dep_node)) {
                $self_node = $current_dep_node->searchChild($plugin->name());
                if (!is_null($self_node)) {
                    $tree->setIsCyclic(true);
                    $tree->setError("Found cyclic dependence! [". $plugin->name() ." (ver. " . $plugin->version()
                        . ")] --> [" . $dep_plugin->name() . " (ver. " . $dep_plugin->version() . ")]");

                    // for out
                    $err_child = new DependencyTreeElement($dep_plugin->name(), $dep_plugin->version(), $current_plugin_node->nodeLevel() + 1);
                    $err_child->setIsCyclic(true);
                    $err_child->setIsOptional(true);
                    $current_dep_node->appendChild($err_child);
                    return false;
                }
            }
            $tmp_child = new DependencyTreeElement($dep_plugin->name(), $dep_plugin->version(), $current_plugin_node->nodeLevel() + 1);
            $tmp_child->setIsOptional(true);
            if (is_null($current_dep_node))
                $tree->appendChild($tmp_child);
            else
                $current_dep_node->appendChild($tmp_child);

            if (!$this->loadPluginDependencyTree($dep_plugin, $tree))
                return false;
        }
        return true;
    }

    /**
     * Преобразовать дерево зависимостей плагина в строку
     * @param DependencyTreeElement $tree
     * @param int $level
     * @return string
     */
    private function dependencyTreeToString(DependencyTreeElement &$tree, int $level = 0): string {
        $prefix = " ";
        $prefix = str_pad($prefix, ($level * 4));
        if ($level == 0)
            $prefix = "$prefix|--";
        else
            $prefix = "$prefix|-->";
        $type_prefix = "";
        if (!$tree->isOptional() && $level > 0)
            $type_prefix = "[Req]";
        if ($tree->isOptional() && $level > 0)
            $type_prefix = "[Opt]";
        $cyclic_warn = "";
        if ($tree->isCyclic())
            $cyclic_warn = "[CYCLIC!]";

        $pl_name = $tree->pluginName();
        $pl_vers = $tree->pluginVersion();
        $str = "$prefix $type_prefix $pl_name ($pl_vers) $cyclic_warn";
        foreach ($tree->child() as &$ch) {
            $ch_str = $this->dependencyTreeToString($ch, $ch->nodeLevel());
            $str = $str . "\n$ch_str";
        }
        return $str;
    }

    /**
     * Поиск плагина по имени его класса
     * @param string $class_name
     * @throws
     * @return BaseComponent|null
     */
    private function searchPluginByClassName(string $class_name)/*: BaseComponent|null*/ {
        foreach ($this->_plugins as &$pl) {
            $tmpRef = new \ReflectionClass($pl);
            $shortName = $tmpRef->getShortName();
            unset($tmpRef);
            if ($shortName == $class_name)
                return $pl;
        }
        return null;
    }

    /**
     * Поиск плагина по названию его контроллера
     * @param string $controller_name
     * @throws
     * @return BaseComponent|null
     */
    private function searchPluginByControllerName(string $controller_name)/*: BaseComponent|null*/ {
        foreach ($this->_plugins as &$pl) {
            $plControllers = $pl->controllers();
            foreach ($plControllers as $controller) {
                if (strcmp($controller['name'], $controller_name) === 0)
                    return $pl;
            }
        }
        return null;
    }

    /**
     * Поиск плагина по имени
     * @param string $name
     * @throws
     * @return BaseComponent|null
     */
    private function searchPluginByName(string $name)/*: BaseComponent|null*/ {
        foreach ($this->_plugins as &$pl) {
            if ($pl->name() == $name)
                return $pl;
        }
        return null;
    }

    /**
     * Получить объект плагина только если он успешно инициализирован
     * @param BaseComponent $plugin
     * @return BaseComponent|null
     */
    private function getSuccessPlugin(/*BaseComponent*/ &$plugin)/*: BaseComponent|null*/ {
        if (!is_null($plugin) && $plugin->state() == BCState::INIT_SUCCESS)
            return $plugin;
        return null;
    }

    /**
     * Проверка версий
     * @param string $v1
     * @param string $v2
     * @return int
     *
     * ПРИМЕР:
     * version1 < version2 --> -1
     * version1 > version2 --> 1
     * version1 == version2 --> 0
     * не корректные версии --> 2
     */
    private function versionCompare(string $v1, string $v2): int {
        if (!preg_match("/([0-9]+)(?:[.]([0-9]+))?(?:[.]([0-9]+))/", $v1))
            return 2;
        if (!preg_match("/([0-9]+)(?:[.]([0-9]+))?(?:[.]([0-9]+))/", $v2))
            return 2;
        $v1_lst = explode('.', $v1);
        $v2_lst = explode('.', $v2);
        if (count($v1_lst) != count($v2_lst))
            return 2;
        for ($i = 0; $i < count($v1_lst); $i++) {
            $v1_num = intval($v1_lst[$i]);
            $v2_num = intval($v2_lst[$i]);
            if ($v1_num < $v2_num)
                return -1;
            if ($v1_num > $v2_num)
                return 1;
        }
        return 0;
    }

    /**
     * Отправить сообщение в log
     * @param int $logLevel
     * @param string $message
     * @throws Error
     */
    private function logMessage(int $logLevel, string $message) {
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $message) as $line)
            Logger::log($logLevel, $line);
    }
}