<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 30.07.21
 * Time: 16:40
 */

namespace FlyCubePHP\Core\Database;

include_once __DIR__.'/../Config/Config.php';
include_once __DIR__.'/../../HelperClasses/CoreHelper.php';
include_once __DIR__.'/../../ComponentsCore/ComponentsManager.php';
include_once 'SQLiteAdapter.php';
include_once 'PostgreSQLAdapter.php';
include_once 'MySQLAdapter.php';

use Exception;
use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\ComponentsCore\ComponentsManager;

class DatabaseFactory
{
    private static $_instance = null;
    private $_settings = null;
    private $_adapters = [];
    private $_isLoaded = false;

    const DATABASE_CONFIG = "database.json";

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function instance(): DatabaseFactory {
        if (static::$_instance === null)
            static::$_instance = new static();
        return static::$_instance;
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     */
    private function __construct() {
        // --- append default adapters ---
        $this->registerDatabaseAdapter('sqlite', 'FlyCubePHP\Core\Database\SQLiteAdapter');
        $this->registerDatabaseAdapter('sqlite3', 'FlyCubePHP\Core\Database\SQLiteAdapter');
        $this->registerDatabaseAdapter('postgresql', 'FlyCubePHP\Core\Database\PostgreSQLAdapter');
        $this->registerDatabaseAdapter('mysql', 'FlyCubePHP\Core\Database\MySQLAdapter');
        $this->registerDatabaseAdapter('mariadb', 'FlyCubePHP\Core\Database\MySQLAdapter');
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
     * Загрузить расширения
     */
    public function loadExtensions() {
        if (!CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_EXTENSION_SUPPORT, false)))
            return;
        if ($this->_isLoaded === true)
            return;
        $this->_isLoaded = true;

        // --- include other adapters ---
        $extRoot = strval(\FlyCubePHP\configValue(Config::TAG_EXTENSIONS_FOLDER, "extensions"));
        $adaptersFolder = CoreHelper::buildPath(CoreHelper::rootDir(), $extRoot, "db", "adapters");
        if (!is_dir($adaptersFolder))
            return;
        $adaptersLst = CoreHelper::scanDir($adaptersFolder);
        foreach ($adaptersLst as $item) {
            $fExt = pathinfo($item, PATHINFO_EXTENSION);
            if (strcmp(strtolower($fExt), "php") !== 0)
                continue;
            try {
                include_once $item;
            } catch (Exception $e) {
//                error_log("DatabaseFactory: $e->getMessage()!\n");
//                echo 'Выброшено исключение: ',  $e->getMessage(), "\n";
            }
        }
    }

    /**
     * Зарегистрировать адаптер по работе с базой данных
     * @param string $name - название адаптера, используемое в конфигурацинном файле для доступа к БД
     * @param string $className - имя класса адаптера (с namespace; наследник класса BaseDatabaseAdapter)
     */
    public function registerDatabaseAdapter(string $name, string $className) {
        $name = trim($name);
        $className = trim($className);
        if (empty($name) || empty($className))
            return;
        if (array_key_exists($name, $this->_adapters))
            return; // TODO adapter already exist!
        $this->_adapters[$name] = $className;
    }

    /**
     * Создать адаптер по работе с базой данных
     * @param bool $autoConnect - автоматически подключаться при создании
     * @return BaseDatabaseAdapter|null
     */
    public function createDatabaseAdapter(bool $autoConnect = true)/*: BaseDatabaseAdapter|null */ {
        if (is_null($this->_settings))
            return null;
        if (!array_key_exists('adapter', $this->_settings))
            return null;
        $adapterClassName = $this->selectAdapterClassName($this->_settings['adapter']);
        if (empty($adapterClassName))
            return null;
        $adapter = new $adapterClassName($this->_settings);
        if ($autoConnect === true)
            $adapter->recreatePDO();
        return $adapter;
    }

    /**
     * Имя текущего адаптера по работе с базой данных
     * @return string
     */
    public function currentAdapterName(): string {
        if (is_null($this->_settings))
            return "";
        if (!array_key_exists('adapter', $this->_settings))
            return "";
        return $this->_settings['adapter'];
    }

    /**
     * Загрузить настройки для работы с базой данных
     */
    public function loadConfig() {
        if (!is_null($this->_settings))
            return;
        $path = CoreHelper::buildPath(CoreHelper::rootDir(), ComponentsManager::CONFIG_DIR, DatabaseFactory::DATABASE_CONFIG);
        if (!is_readable($path))
            throw new \RuntimeException("[DatabaseFactory][loadConfig] Not found database configs! Path: $path");
        $configData = file_get_contents($path);
        $configDataJSON = json_decode($configData, true);
        if (!is_array($configDataJSON))
            throw new \RuntimeException("[DatabaseFactory][loadConfig] Invalid database configs (invalid JSON)! Path: $path");
        if (Config::instance()->isProduction()) {
            if (array_key_exists('production', $configDataJSON)) {
                $tmpSettings = $configDataJSON['production'];
                if (is_string($tmpSettings)) {
                    if (!array_key_exists($tmpSettings, $configDataJSON))
                        throw new \RuntimeException("[DatabaseFactory][loadConfig] Not found database production settings ($tmpSettings)! Path: $path");
                    $tmpSettingsArr = $configDataJSON[$tmpSettings];
                    if (!is_array($tmpSettingsArr))
                        throw new \RuntimeException("[DatabaseFactory][loadConfig] Invalid database production settings (is nor array)! Key: $tmpSettings; Path: $path");
                    $this->_settings = $tmpSettingsArr;
                } elseif (is_array($tmpSettings)) {
                    $this->_settings = $tmpSettings;
                } else {
                    throw new \RuntimeException("[DatabaseFactory][loadConfig] Invalid database production settings (is nor array or string)! Path: $path");
                }
            }
        } elseif (Config::instance()->isDevelopment()) {
            if (array_key_exists('development', $configDataJSON)) {
                $tmpSettings = $configDataJSON['development'];
                if (is_string($tmpSettings)) {
                    if (!array_key_exists($tmpSettings, $configDataJSON))
                        throw new \RuntimeException("[DatabaseFactory][loadConfig] Not found database development settings ($tmpSettings)! Path: $path");
                    $tmpSettingsArr = $configDataJSON[$tmpSettings];
                    if (!is_array($tmpSettingsArr))
                        throw new \RuntimeException("[DatabaseFactory][loadConfig] Invalid database development settings (is nor array)! Key: $tmpSettings; Path: $path");
                    $this->_settings = $tmpSettingsArr;
                } elseif (is_array($tmpSettings)) {
                    $this->_settings = $tmpSettings;
                } else {
                    throw new \RuntimeException("[DatabaseFactory][loadConfig] Invalid database development settings (is nor array or string)! Path: $path");
                }
            }
        }
        if (is_null($this->_settings))
            throw new \RuntimeException("[DatabaseFactory][loadConfig] Invalid database configs (invalid JSON)! Path: $path");

        // --- check supported adapters ---
        if (!array_key_exists('adapter', $this->_settings))
            throw new \RuntimeException("[DatabaseFactory][loadConfig] Not found database adapter! Path: $path");

        $tmpAdapter = $this->_settings['adapter'];
        if (empty($this->selectAdapterClassName($tmpAdapter)))
            throw new \RuntimeException("[DatabaseFactory][loadConfig] Unsupported database adapter! Name: $tmpAdapter; Path: $path");
    }

    /**
     * Сбросить настройки конфигурации
     */
    public function resetConfig() {
        unset($this->_settings);
        $this->_settings = null;
    }

    /**
     * Запросить имя класса адаптера по работе с базой данных
     * @param string $name - название адаптера
     * @return string
     */
    private function selectAdapterClassName(string $name): string {
        $name = trim($name);
        if (array_key_exists($name, $this->_adapters))
            return $this->_adapters[$name];
        return "";
    }
}