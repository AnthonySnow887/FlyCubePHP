<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 11.08.21
 * Time: 12:09
 */

namespace FlyCubePHP\Core\Controllers;

include_once 'BaseController.php';
include_once 'ControllerHelperTwigExt.php';

use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\Error\ErrorController;
use FlyCubePHP\Core\Routes\RouteCollector;
use FlyCubePHP\Core\Protection\CSPProtection;
use FlyCubePHP\Core\AssetPipeline\AssetPipeline;

abstract class BaseActionController extends BaseController
{
    use Extensions\NetworkBase {
        isNetworkUsed as private;
    }

    protected $_helper = null;

    private $_layout = "application.html";
    private $_layoutSupport = true;
    private $_skipRenderActions = [];
    private $_helperMethods = [];

    public function __construct() {
        parent::__construct();
    }

    /**
     * Метод отрисовки
     * @param string $action
     * @param array $options - дополнительные настройки отрисовки
     * @throws
     *
     * ==== Options
     *
     * - [bool]     layout_support  - отрисовывать или нет базовый слой (default: true)
     * - [string]   layout          - задать базовый layout (должен раполагаться в каталоге: app/views/layouts/)
     * - [string]   view            - задать view для отрисовки (view текущего метода контроллера, если он существует, будет проигнорирован)
     * - [array]    args            - задать массив аргументов, который будет передан в Twig при рендеринге
     * - [bool]     skip_render     - пропустить отрисовку страницы
     *
     * NOTE: Key 'render_action' in args array is reserved!
     */
    final public function render(string $action = "", array $options = [ 'layout_support' => true ]) {
        // --- check skip in options ---
        if (isset($options['skip_render'])
            && $options['skip_render'] === true) {
            $this->_isRendered = true;
            return;
        }
        // --- check is already used ---
        if ($this->_isRendered === true)
            return;
        // --- check is Network trait used ---
        if ($this->isNetworkUsed())
            return;
        // --- check in not HTTP GET ---
        if (!RouteCollector::isCurrentRouteMethodGET())
            return;
        // --- check action name & select ---
        if (empty($action)) {
            // --- get caller function ---
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = isset($dbt[1]['function']) ? $dbt[1]['function'] : null;
            if (is_null($caller))
                throw ErrorController::makeError([
                    'tag' => 'render',
                    'message' => "Not found caller function!",
                    'controller' => $this->controllerName(),
                    'method' => __FUNCTION__
                ]);

            $action = $caller;
        }
        // --- check skip in settings ---
        if ($this->hasSkipRenderForAction($action))
            return;

        // --- start render ---
        $this->_isRendered = true;

        // --- create twig ---
        $coreLayoutsDirectory = $this->coreLayoutsDirectory();
        $viewsDirectory = $this->viewsDirectory();
        $viewsDirectoryNamespace = basename($viewsDirectory);
        if (!isset($options['view'])) {
            $viewPath = $this->viewPath($action);
            $viewFileName = CoreHelper::fileName($viewPath);
            if (!is_dir($viewsDirectory))
                throw ErrorController::makeError([
                    'tag' => 'render',
                    'message' => "Views directory not found!",
                    'additional-data' => ["Dir", $viewsDirectory],
                    'controller' => $this->controllerName(),
                    'method' => __FUNCTION__,
                    'action' => $action
                ]);
            if (empty($viewPath))
                throw ErrorController::makeError([
                    'tag' => 'render',
                    'message' => "Views file not found!",
                    'controller' => $this->controllerName(),
                    'method' => __FUNCTION__,
                    'action' => $action
                ]);
            $actionView = "$viewsDirectoryNamespace/$viewFileName";
        } else {
            $actionView = $this->prepareViewPath($options['view']);
        }

        $loader = new \Twig\Loader\FilesystemLoader($coreLayoutsDirectory);

        // --- append core layouts namespaces ---
        $coreLayoutsNS = $this->layoutsDirectoryNamespaces(CoreHelper::buildPath(CoreHelper::rootDir(), "app", "views", "layouts"));
        foreach ($coreLayoutsNS as $key => $value)
            $loader->addPath($value, $key);

        try {
            if (is_dir($viewsDirectory)) {
                $loader->addPath($viewsDirectory, $viewsDirectoryNamespace);

                // --- append controller layouts namespaces ---
                $controllerLayoutsNS = $this->layoutsDirectoryNamespaces(CoreHelper::buildPath(CoreHelper::rootDir(), $viewsDirectory));
                foreach ($controllerLayoutsNS as $key => $value)
                    $loader->addPath($value, "$viewsDirectoryNamespace/$key");
            }

            // --- append other views paths ---
            $tmpViewsLst = AssetPipeline::instance()->viewDirs();
            foreach ($tmpViewsLst as $key => $value) {
                if (strcmp($value, $coreLayoutsDirectory) === 0
                    || strcmp($value, $viewsDirectory) === 0)
                    continue;
                $dirNamespace = basename($value);
                $loader->addPath($value, $dirNamespace);

                // --- append other views namespaces ---
                $otherLayoutsNS = $this->layoutsDirectoryNamespaces(CoreHelper::buildPath(CoreHelper::rootDir(), $value));
                foreach ($otherLayoutsNS as $otherKey => $otherValue)
                    $loader->addPath($otherValue, "$dirNamespace/$otherKey");
            }
        } catch (\Twig\Error\LoaderError $e) {
            throw ErrorController::makeError([
                'tag' => 'render',
                'message' => "Twig add path error!",
                'additional-message' => $e->getMessage(),
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__,
                'action' => $action
            ]);
        }

        $defVal = !Config::instance()->isProduction();
        $twigCacheReload = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_REBUILD_TWIG_CACHE, $defVal));
        $twigStrictVariables = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_TWIG_STRICT_VARIABLES, true));
        $twigDebugExt = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_TWIG_DEBUG_EXTENSION, $defVal));

        $twig = new \Twig\Environment($loader, [
            'debug' => $twigDebugExt,
            'cache' => 'tmp/cache/Twig',
            'auto_reload' => $twigCacheReload,
            'strict_variables' => $twigStrictVariables
        ]);

        // --- append global variables ---
        $twig->addGlobal('params', $this->_params);
        $twig->addGlobal('flash', FlashMessages::instance());
        $twig->addGlobal('router', RouteCollector::instance());

        // --- append helper methods ---
        $ext = new ControllerHelperTwigExt($this->_helper);
        $twig->addExtension($ext);
        if ($twigDebugExt === true)
            $twig->addExtension(new \Twig\Extension\DebugExtension());

        // --- append controller helper methods ---
        foreach ($this->_helperMethods as $key => $value) {
            $settingsTwig = [];
            if ($this->isHelperMethodSafe($key) === true)
                $settingsTwig['is_safe'] = ['html'];
            if ($this->isHelperMethodNeedsContext($key) === true)
                $settingsTwig['need_context'] = true;
            if ($this->isHelperMethodNeedsEnvironment($key) === true)
                $settingsTwig['needs_environment'] = true;

            $twig->addFunction(new \Twig\TwigFunction($key, [$this, $key], $settingsTwig));
        }

        // --- render page by twig ---
        $pageData = "";
        $localLayoutSupport = true;
        if (isset($options['layout_support']))
            $localLayoutSupport = CoreHelper::toBool($options['layout_support']);
        $localLayout = $this->_layout;
        if (isset($options['layout']) && !empty(trim($options['layout'])))
            $localLayout = trim($options['layout']);

        // --- make & check render args ---
        $viewArgs = [];
        if (isset($options['args']) && is_array($options['args']))
            $viewArgs = $options['args'];
        if (isset($viewArgs['render_action']))
            throw ErrorController::makeError([
                'tag' => 'render',
                'message' => "Unsupported argument key! Key 'render_action' is reserved!",
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__,
                'action' => $action
            ]);

        try {
            if ($localLayoutSupport === true && $this->_layoutSupport === true) {
                $viewArgs = array_merge($viewArgs, [ 'render_action' => "@$actionView" ]);
                $pageData = $twig->render($localLayout, $viewArgs);
            } else {
                $pageData = $twig->render("@$actionView", $viewArgs);
            }
        } catch (\Twig\Error\LoaderError $e) {
            $errFile = "";
            $errLine = -1;
            $additionalData = [];
            $additionalData["Backtrace"] = $e->getTraceAsString();
            $source = $e->getSourceContext();
            if (!is_null($source)) {
                $errLine = $e->getTemplateLine();
                $errFile = CoreHelper::buildAppPath($source->getPath());
                $additionalData["Twig source name"] = $source->getName();
            }
            $prevEx = $e->getPrevious();
            if (is_a($prevEx, "\FlyCubePHP\Core\Error\Error")) {
                $additionalData = array_merge($additionalData, $prevEx->additionalData());
                if ($prevEx->type() == \FlyCubePHP\Core\Error\ErrorType::ASSET_PIPELINE) {
                    if ($prevEx->hasAssetCode()) {
                        $additionalData["Asset Pipeline error file"] = $prevEx->getFile();
                        $additionalData["Asset Pipeline error line"] = $prevEx->getLine();
                    }
                    $additionalData["Asset name"] = $prevEx->assetName();
                } else {
                    $additionalData["Additional error file"] = $prevEx->getFile();
                    $additionalData["Additional error line"] = $prevEx->getLine();
                }
            }
            throw ErrorController::makeError([
                'tag' => 'render',
                'message' => "Twig loader error!",
                'additional-message' => $e->getMessage(),
                'additional-data' => $additionalData,
                'file' => $errFile,
                'line' => $errLine,
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__,
                'action' => $action
            ]);
        } catch (\Twig\Error\SyntaxError $e) {
            $errFile = "";
            $errLine = -1;
            $additionalData = [];
            $additionalData["Backtrace"] = $e->getTraceAsString();
            $source = $e->getSourceContext();
            if (!is_null($source)) {
                $errLine = $e->getTemplateLine();
                $errFile = CoreHelper::buildAppPath($source->getPath());
                $additionalData["Twig source name"] = $source->getName();
            }
            $prevEx = $e->getPrevious();
            if (is_a($prevEx, "\FlyCubePHP\Core\Error\Error")) {
                $additionalData = array_merge($additionalData, $prevEx->additionalData());
                if ($prevEx->type() == \FlyCubePHP\Core\Error\ErrorType::ASSET_PIPELINE) {
                    if ($prevEx->hasAssetCode()) {
                        $additionalData["Asset Pipeline error file"] = $prevEx->getFile();
                        $additionalData["Asset Pipeline error line"] = $prevEx->getLine();
                    }
                    $additionalData["Asset name"] = $prevEx->assetName();
                } else {
                    $additionalData["Additional error file"] = $prevEx->getFile();
                    $additionalData["Additional error line"] = $prevEx->getLine();
                }
            }
            throw ErrorController::makeError([
                'tag' => 'render',
                'message' => "Twig syntax error!",
                'additional-message' => $e->getMessage(),
                'additional-data' => $additionalData,
                'file' => $errFile,
                'line' => $errLine,
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__,
                'action' => $action
            ]);
        } catch (\Twig\Error\RuntimeError $e) {
            $errFile = "";
            $errLine = -1;
            $additionalData = [];
            $additionalData["Backtrace"] = $e->getTraceAsString();
            $source = $e->getSourceContext();
            if (!is_null($source)) {
                $errLine = $e->getTemplateLine();
                $errFile = CoreHelper::buildAppPath($source->getPath());
                $additionalData["Twig source name"] = $source->getName();
            }
            $prevEx = $e->getPrevious();
            if (is_a($prevEx, "\FlyCubePHP\Core\Error\Error")) {
                $additionalData = array_merge($additionalData, $prevEx->additionalData());
                if ($prevEx->type() == \FlyCubePHP\Core\Error\ErrorType::ASSET_PIPELINE) {
                    if ($prevEx->hasAssetCode()) {
                        $additionalData["Asset Pipeline error file"] = $prevEx->getFile();
                        $additionalData["Asset Pipeline error line"] = $prevEx->getLine();
                    }
                    $additionalData["Asset name"] = $prevEx->assetName();
                } else {
                    $additionalData["Additional error file"] = $prevEx->getFile();
                    $additionalData["Additional error line"] = $prevEx->getLine();
                }
            }
            throw ErrorController::makeError([
                'tag' => 'render',
                'message' => "Twig runtime error!",
                'additional-message' => $e->getMessage(),
                'additional-data' => $additionalData,
                'file' => $errFile,
                'line' => $errLine,
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__,
                'action' => $action
            ]);
        }
        // --- send csp protection http header ---
        if (CSPProtection::instance()->isContentSecurityPolicy() === true)
            CSPProtection::instance()->processingCSPNonce();

        // --- show page ---
        if ($this->_obLevel != 0
            && $this->_obLevel == ob_get_level()) {
            if ($this->_enableActionOutput === true)
                ob_end_flush();
            else
                ob_end_clean();

            $this->_obLevel = 0;
        }

        // --- check HTTP request (if HEAD - skip body) ---
        $httpM = strtolower(RouteCollector::currentRouteMethod());
        if (strcmp($httpM, 'head') !== 0)
            echo $pageData;

        // --- clear ---
        unset($twig);
        unset($loader);
    }

    /**
     * Базовый метод отрисовки GUI контроллера
     * @param string $action
     * @throws
     *
     * ПРИМЕЧАНИЕ: используется системой! Использование запрещено!
     */
    final public function renderPrivate(string $action) {
        // --- check caller function ---
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $callerClass = $dbt[1]['class'] ?? '';
        $caller = $dbt[1]['function'] ?? '';
        if (empty($caller))
            throw ErrorController::makeError([
                'tag' => 'render',
                'message' => "Not found caller function!",
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__,
                'action' => $action
            ]);
        if (!empty($callerClass))
            $caller = "$callerClass::$caller";

        if (strcmp($caller, "FlyCubePHP\Core\Routes\RouteCollector::processingRender") !== 0
            && strcmp($caller, "assetsPrecompile") !== 0)
            throw ErrorController::makeError([
                'tag' => 'render',
                'message' => "Invalid caller function!",
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__,
                'action' => $action
            ]);
        $ignoreProcessing = false;
        if (strcmp($caller, "assetsPrecompile") === 0)
            $ignoreProcessing = true;

        // --- select settings ---
        $defVal = !Config::instance()->isProduction();
        $this->_enableActionOutput = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_ACTION_OUTPUT, $defVal));
        $this->_params = RouteCollector::currentRouteArgs();
        $this->_params['controller-class'] = $this->controllerClassName();
        $this->_params['controller'] = $this->controllerName();
        $this->_params['action'] = $action;

        // --- before action ---
        if (!$ignoreProcessing) {
            $res = $this->processingBeforeAction($action);
            if ($res === false)
                return;
        }

        // --- create helper ---
        $this->createHelper();

        // --- clear all buffers ---
        while (ob_get_level() !== 0)
            ob_end_clean();

        // --- processing ---
        if (!$ignoreProcessing) {
            ob_start();
            $this->_obLevel = ob_get_level();
            $this->$action();
            if ($this->_obLevel != 0
                && $this->_obLevel == ob_get_level()) {
                if ($this->_enableActionOutput === true)
                    ob_end_flush();
                else
                    ob_end_clean();

                $this->_obLevel = 0;
            }
        }

        // --- render page ---
        $this->render($action);

        // --- after action ---
        if (!$ignoreProcessing)
            $this->processingAfterAction($action);
    }

    /**
     * Является ли метод вспомогательной функцией
     * @param string $name
     * @return bool
     */
    final public function isHelperMethod(string $name): bool {
        return array_key_exists($name, $this->_helperMethods);
    }

    // --- protected ---

    /**
     * Задать базовый layout для контроллера
     * @param string $name - название
     * @throws
     *
     * NOTE: базовый layout должен раполагаться в каталоге: app/views/layouts/
     */
    final protected function setLayout(string $name) {
        if (empty(trim($name)))
            throw ErrorController::makeError([
                'tag' => 'app-controller-base',
                'message' => "Invalid layout name (empty)!",
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__
            ]);

        $lPath = CoreHelper::buildAppPath($this->coreLayoutsDirectory(), $name);
        if (!is_file($lPath))
            throw ErrorController::makeError([
                'tag' => 'app-controller-base',
                'message' => "Layout '$name' not found in path: " . $this->coreLayoutsDirectory(),
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__
            ]);
        if (!is_readable($lPath))
            throw ErrorController::makeError([
                'tag' => 'app-controller-base',
                'message' => "Layout '$name' is not readable!",
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__
            ]);

        $this->_layout = trim($name);
    }

    /**
     * Задать поддержку отрисовки базового layout для контроллера
     * @param bool $val
     */
    final protected function setLayoutSupport(bool $val) {
        $this->_layoutSupport = $val;
    }

    /**
     * Добавить название метода, для которого будет пропущен рендеринг страницы
     * @param string $action - Название метода контроллера
     * @throws ErrorController
     */
    final protected function skipRenderForAction(string $action) {
        if (!method_exists($this, $action))
            throw ErrorController::makeError([
                'tag' => 'app-controller-base',
                'message' => "Not found action function in controller!",
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__,
                'action' => $action
            ]);
        if (!in_array($action, $this->_skipRenderActions))
            $this->_skipRenderActions[] = $action;
    }

    /**
     * Добавить название методов, для которых будет пропущен рендеринг страницы
     * @param array $actions - Названия методов контроллера
     * @throws ErrorController
     */
    final protected function skipRenderForActions(array $actions) {
        foreach ($actions as $act)
            $this->skipRenderForAction($act);
    }

    /**
     * Добавить вспомогательную функцию
     * @param string $name - название публичного (public) метода
     * @param array $settings - свойства
     * @throws ErrorController
     *
     * ПРИМЕЧАНИЕ:
     * Разрешена регистрация только публичных (public) методов!
     * Публичные статические (static public) методы так же разрешены.
     *
     * ПРИМЕЧАНИЕ:
     * Метод контроллера, заданный как вспомогательная функция, не может быть методом отрисовки страницы
     * и будет проигнорирован при формировании списка маршрутов!
     *
     * ==== Settings
     *
     * - safe               - безопасная функция (вывод без экранирования) (default: false)
     * - need_context       - требуется twig context (default: false)
     * - needs_environment  - требуется twig environment (default: false)
     *
     * ==== Examples
     *
     * function __construct() {
     *    $this->appendHelperMethod('my_f_1', ['safe'=>true]);
     *    $this->appendHelperMethod('my_f_1_1', ['safe'=>true]);
     *    $this->appendHelperMethod('my_f_2', ['need_context'=>true]);
     *    $this->appendHelperMethod('my_f_3', ['need_context'=>true, 'needs_environment'=>true]);
     * }
     *
     * public function my_f_1($a, $b) { ... }
     * static public function my_f_1_1($a, $b) { ... }
     * public function my_f_2($context, $a, $b) { ... }
     * public function my_f_3(\Twig\Environment $env, $context, $string) { ... }
     *
     */
    final protected function appendHelperMethod(string $name, array $settings = []) {
        if (!method_exists($this, $name))
            throw ErrorController::makeError([
                'tag' => 'app-controller-base',
                'message' => "Not found method in controller for create helper function!",
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__,
                'action' => $name
            ]);

        // --- check is public ---
        $reflection = new \ReflectionMethod($this, $name);
        if (!$reflection->isPublic())
            throw ErrorController::makeError([
                'tag' => 'app-controller-base',
                'message' => "Controller helper method is not public!",
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__,
                'action' => $name
            ]);

        if (!array_key_exists($name, $this->_helperMethods))
            $this->_helperMethods[$name] = $settings;
    }

    // --- private ---

    /**
     * Является ли вспомогательнуя функция безопасной
     * @param string $name
     * @return bool
     */
    final public function isHelperMethodSafe(string $name): bool {
        if (isset($this->_helperMethods[$name])
            && isset($this->_helperMethods[$name]["safe"]))
            return $this->_helperMethods[$name]["safe"];
        return false;
    }

    /**
     * Требуется ли вспомогательной функции twig контекст
     * @param string $name
     * @return bool
     */
    final public function isHelperMethodNeedsContext(string $name): bool {
        if (isset($this->_helperMethods[$name])
            && isset($this->_helperMethods[$name]["need_context"]))
            return $this->_helperMethods[$name]["need_context"];
        return false;
    }

    /**
     * Требуется ли вспомогательной функции twig environment
     * @param string $name
     * @return bool
     */
    final public function isHelperMethodNeedsEnvironment(string $name): bool {
        if (isset($this->_helperMethods[$name])
            && isset($this->_helperMethods[$name]["needs_environment"]))
            return $this->_helperMethods[$name]["needs_environment"];
        return false;
    }

    /**
     * Задан ли шаблон страницы
     * @param string $action
     * @return bool
     */
    final public function hasView(string $action): bool {
        return !empty($this->viewPath($action));
    }

    /**
     * Пропускать ли рендеринг страницы для метода контроллера
     * @param string $action - Название метода контроллера
     * @return bool
     */
    final private function hasSkipRenderForAction(string $action): bool {
        return in_array($action, $this->_skipRenderActions);
    }

    /**
     * Каталог базовых layouts приложения
     * @return string
     */
    final private function coreLayoutsDirectory(): string {
        return CoreHelper::buildAppPath("app", "views", "layouts");
    }

    /**
     * Список подкаталогов и их namespace-ов для layouts
     * @param string $dir
     * @return array
     */
    final private function layoutsDirectoryNamespaces(string $dir): array {
        $tmpPaths = CoreHelper::scanDir($dir, [ 'recursive' => true, 'append-dirs' => true, 'only-dirs' => true ]);
        $tmpNS = [];
        foreach ($tmpPaths as $path) {
            $tmpNSName = CoreHelper::splicePathFirst(str_replace($dir, "", $path));
            $tmpNS[$tmpNSName] = CoreHelper::buildAppPath($path);
        }
        return $tmpNS;
    }

    /**
     * Каталог views текущего контроллера
     * @return string
     * @throws ErrorController
     */
    final private function viewsDirectory(): string {
        $shortName = $this->controllerName();
        return CoreHelper::buildAppPath($this->controllerDirectory(), "views", CoreHelper::underscore($shortName));
    }

    /**
     * Путь до view для экшена
     * @param string $action
     * @return string
     * @throws
     */
    final private function viewPath(string $action): string {
        $viewsDirectory = $this->viewsDirectory();
        $viewPath = "$viewsDirectory/$action.html";
        $viewPathTwig = "$viewsDirectory/$action.html.twig";
        if (is_file($viewPath))
            return $viewPath;
        if (is_file($viewPathTwig))
            return $viewPathTwig;
        return "";
    }

    /**
     * Каталог helpers текущего контроллера
     * @return string
     * @throws
     */
    final private function helpersDirectory(): string {
        return CoreHelper::buildAppPath($this->controllerDirectory(), "helpers");
    }

    /**
     * Имя текущего хелпера
     * @return string
     */
    final private function helperName(): string {
        $shortName = $this->controllerName();
        return $shortName . "Helper";
    }

    /**
     * Полный путь до текущего хелпера
     * @return string
     * @throws
     */
    final private function helperPath(): string {
        return CoreHelper::buildAppPath($this->helpersDirectory(), $this->helperName().".php");
    }

    /**
     * Создать хелпер для текущего контроллера
     * @throws
     */
    final private function createHelper() {
        if (!is_null($this->_helper))
            return;
        if (!file_exists($this->helperPath()))
            return;
        if (!class_exists($this->helperName(), false))
            include_once $this->helperPath();
        $hlpName = $this->helperName();
        $this->_helper = new $hlpName();
    }

    /**
     * Подготовить путь для view
     * @param string $path - путь
     * @return string
     *
     * echo prepareViewPath("@@/tmp/app1.html");
     *   => "tmp/app1.html"
     */
    final private function prepareViewPath(string $path): string {
        if (empty($path))
            return $path;
        if (strcmp($path[0], "@") === 0) {
            if (strlen($path) > 1) {
                $path = ltrim($path, "@");
                $path = $this->prepareViewPath($path);
            } else {
                $path = "";
            }
        }
        return CoreHelper::splicePathFirst($path);
    }
}