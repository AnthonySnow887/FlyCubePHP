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
    private $_settings = [];
    private $_additionalSettings = [];
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
     * @param array $args - массив параметров создания адаптера
     * @return BaseDatabaseAdapter|null
     *
     * ==== Args
     *
     * - [bool] auto-connect - connect automatically on creation (default: true)
     * - [string] database   - database key name in '*_additional' config (default: '')
     *
     * NOTE: If database name is empty - used primary database.
     */
    public function createDatabaseAdapter(array $args = [ 'auto-connect' => true ])/*: BaseDatabaseAdapter|null */ {
        if (empty($this->_settings) && empty($this->_additionalSettings))
            return null;
        if (!isset($args['database']) || empty($args['database']))
            return $this->createAdapter($this->_settings, $args);
        if (!isset($this->_additionalSettings[$args['database']]))
            return null;
        return $this->createAdapter($this->_additionalSettings[$args['database']], $args);
    }

    /**
     * Имя основного (первичного) адаптера по работе с базой данных
     * @return string
     */
    public function primaryAdapterName(): string {
        return $this->adapterName($this->_settings);
    }

    /**
     * Имя вторичного адаптера по работе с базой данных
     * @param string $database - название базы данных
     * @return string
     */
    public function additionalAdapterName(string $database): string {
        if (empty($this->_additionalSettings) || empty($database))
            return "";
        if (!isset($this->_additionalSettings[$database]))
            return "";
        return $this->adapterName($this->_additionalSettings[$database]);
    }

    /**
     * Список названий ключей к дполнительным базам данных из раздела конфигурации '*_additional'
     * @return array
     */
    public function additionalDatabases(): array {
        $tmpDatabases = [];
        foreach ($this->_additionalSettings as $key => $value)
            $tmpDatabases[] = $key;
        return $tmpDatabases;
    }

    /**
     * Загрузить настройки для работы с базой данных
     */
    public function loadConfig() {
        if (!empty($this->_settings))
            return;
        $path = CoreHelper::buildPath(CoreHelper::rootDir(), ComponentsManager::CONFIG_DIR, DatabaseFactory::DATABASE_CONFIG);
        if (!is_readable($path))
            throw new \RuntimeException("[DatabaseFactory][loadConfig] Not found database configs! Path: $path");
        $configData = file_get_contents($path);
        $configDataJSON = json_decode($configData, true);
        if (!is_array($configDataJSON))
            throw new \RuntimeException("[DatabaseFactory][loadConfig] Invalid database configs (invalid JSON)! Path: $path");

        // --- load primary settings ---
        $dbMode = 'development';
        if (Config::instance()->isProduction())
            $dbMode = 'production';

        $this->_settings = $this->loadDatabaseSettings($dbMode, $configDataJSON, $path);
        if (empty($this->_settings))
            throw new \RuntimeException("[DatabaseFactory][loadConfig] Invalid database configs (invalid JSON)! Path: $path");

        // --- check supported adapters ---
        $this->checkSupportedAdapters($this->_settings, $path);

        // --- load additional settings ---
        if (!array_key_exists($dbMode."_additional", $configDataJSON))
            return;
        $this->_additionalSettings = $this->loadDatabaseAdditionalSettings($dbMode."_additional", $configDataJSON, $path);
        if (!empty($this->_additionalSettings)) {
            // --- check supported adapters ---
            foreach ($this->_additionalSettings as $settingsPath)
                $this->checkSupportedAdapters($settingsPath, $path);
        }
    }

    /**
     * Сбросить настройки конфигурации
     */
    public function resetConfig() {
        // --- reset primary settings ---
        unset($this->_settings);
        $this->_settings = [];

        // --- reset additional settings ---
        unset($this->_additionalSettings);
        $this->_additionalSettings = [];
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

    /**
     * Загрузить настройки по работе с БД
     * @param string $key - ключ раздела настроек в JSON
     * @param array $configDataJSON - данные файла конфигурации в формате JSON
     * @param string $path - путь до файла конфигурации
     * @return array
     */
    private function loadDatabaseSettings(string $key, array $configDataJSON, string $path): array {
        if (!array_key_exists($key, $configDataJSON))
            throw new \RuntimeException("[DatabaseFactory][loadDatabaseSettings] Not found database $key settings! Path: $path");

        $tmpSettings = $configDataJSON[$key];
        if (is_string($tmpSettings))
            return $this->loadDatabaseSettings($tmpSettings, $configDataJSON, $path);
        elseif (is_array($tmpSettings) && !CoreHelper::arrayIsList($tmpSettings))
            return $tmpSettings;
        else
            throw new \RuntimeException("[DatabaseFactory][loadDatabaseSettings] Invalid database $key settings (is not valid array or string)! Path: $path");
    }

    /**
     * Загрузить дополнительные настройки по работе с БД
     * @param string $key - ключ раздела настроек в JSON
     * @param array $configDataJSON - данные файла конфигурации в формате JSON
     * @param string $path - путь до файла конфигурации
     * @return array
     */
    private function loadDatabaseAdditionalSettings(string $key, array $configDataJSON, string $path): array {
        if (!array_key_exists($key, $configDataJSON))
            throw new \RuntimeException("[DatabaseFactory][loadDatabaseAdditionalSettings] Not found database $key settings! Path: $path");

        $tmpSettings = $configDataJSON[$key];
        if (is_string($tmpSettings)) {
            return $this->loadDatabaseSettings($tmpSettings, $configDataJSON, $path);
        } elseif (is_array($tmpSettings) && !CoreHelper::arrayIsList($tmpSettings)) {
            $tmpAdditionalSettings = [];
            foreach ($tmpSettings as $tmpKey => $tmpValue)
                $tmpAdditionalSettings[$tmpKey] = $this->loadDatabaseSettings($tmpValue, $configDataJSON, $path);

            return $tmpAdditionalSettings;
        } else {
            throw new \RuntimeException("[DatabaseFactory][loadDatabaseSettings] Invalid database $key settings (is not valid array or string)! Path: $path");
        }
    }

    /**
     * Метод проверки поддерживаемых адаптеров по работе с БД
     * @param array $settings - настройки по работе с БД
     * @param string $path - путь до файла конфигурации
     */
    private function checkSupportedAdapters(array $settings, string $path) {
        if (!array_key_exists('adapter', $settings))
            throw new \RuntimeException("[DatabaseFactory][checkSupportedAdapters] Not found database adapter! Path: $path");

        $tmpAdapter = $settings['adapter'];
        if (empty($this->selectAdapterClassName($tmpAdapter)))
            throw new \RuntimeException("[DatabaseFactory][checkSupportedAdapters] Unsupported database adapter! Name: $tmpAdapter; Path: $path");
    }

    /**
     * Создать адаптер по работе с БД
     * @param array $settings - настройки подключения
     * @param array|bool[] $args - массив параметров создания адаптера
     * @return BaseDatabaseAdapter|null
     */
    private function createAdapter(array $settings, array $args = [ 'auto-connect' => true ])/*: BaseDatabaseAdapter|null */ {
        if (empty($settings))
            return null;
        if (!array_key_exists('adapter', $settings))
            return null;
        $adapterClassName = $this->selectAdapterClassName($settings['adapter']);
        if (empty($adapterClassName))
            return null;
        $adapter = new $adapterClassName($settings);
        $autoConnect = $args['auto-connect'] ?? true;
        if ($autoConnect === true)
            $adapter->recreatePDO();
        return $adapter;
    }

    /**
     * Имя адаптера по работе с базой данных
     * @param array $settings - настройки подключения
     * @return string
     */
    private function adapterName(array $settings): string {
        if (empty($settings))
            return "";
        if (!array_key_exists('adapter', $settings))
            return "";
        return $settings['adapter'];
    }
}