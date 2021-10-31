<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 06.08.21
 * Time: 15:26
 */

namespace FlyCubePHP\Core\Migration;

include_once __DIR__.'/../../ComponentsCore/ComponentsManager.php';
include_once __DIR__.'/../../HelperClasses/CoreHelper.php';
include_once __DIR__.'/../Database/DatabaseFactory.php';
include_once __DIR__.'/../ActiveRecord/ActiveRecord.php';
include_once 'Migration.php';
include_once 'BaseSchema.php';
include_once 'SchemaDumper.php';
include_once 'SQLiteMigrator.php';
include_once 'PostgreSQLMigrator.php';

use Exception;
use \FlyCubePHP\Core\Config\Config as Config;
use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;
use \FlyCubePHP\Core\Database\DatabaseFactory as DatabaseFactory;
use \FlyCubePHP\ComponentsCore\ComponentsManager as ComponentsManager;

class MigrationsCore
{
    private static $_instance = null;
    private $_migrators = [];
    private $_migrations = [];
    private $_isLoaded = false;

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function instance(): MigrationsCore {
        if (static::$_instance === null)
            static::$_instance = new static();
        return static::$_instance;
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     */
    private function __construct() {
        // --- append default migrators ---
        $this->registerMigrator('sqlite', 'FlyCubePHP\Core\Migration\SQLiteMigrator');
        $this->registerMigrator('sqlite3', 'FlyCubePHP\Core\Migration\SQLiteMigrator');
        $this->registerMigrator('postgresql', 'FlyCubePHP\Core\Migration\PostgreSQLMigrator');

        // --- load models ---
        $modelsFolder = CoreHelper::buildPath(CoreHelper::rootDir(), "app", ComponentsManager::MODELS_DIR);
        $this->loadModels($modelsFolder);

        // --- load migrations ---
        $migrationsFolder = CoreHelper::buildPath(CoreHelper::rootDir(), "db", "migrate");
        $this->loadMigrations($migrationsFolder);

        // --- load plugins models and migrations ---
        if (!CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_PLUGINS_CORE, true)))
            return;
        $ignoreLst = $this->loadPluginsIgnoreList(CoreHelper::buildPath(CoreHelper::rootDir(), "config"));

        $plDir = CoreHelper::buildPath(CoreHelper::rootDir(), ComponentsManager::PLUGINS_DIR);
        $dirLst = scandir($plDir);
        foreach ($dirLst as $chDir) {
            if (in_array($chDir,array(".","..")))
                continue;
            if (in_array($chDir, $ignoreLst))
                continue;
            if (!is_dir(CoreHelper::buildPath($plDir, $chDir)))
                continue;
            // --- load plugins models ---
            $modelsFolder = CoreHelper::buildPath($plDir, $chDir, "app", ComponentsManager::MODELS_DIR);
            $this->loadModels($modelsFolder);

            // --- load plugins migrations ---
            $migrationsFolder = CoreHelper::buildPath($plDir, $chDir, "db", "migrate");
            $this->loadMigrations($migrationsFolder);
        }
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

        // --- include other migrators ---
        $extRoot = strval(\FlyCubePHP\configValue(Config::TAG_EXTENSIONS_FOLDER, "extensions"));
        $migratorsFolder = CoreHelper::buildPath(CoreHelper::rootDir(), $extRoot, "db", "migrators");
        if (!is_dir($migratorsFolder))
            return;
        $migratorsLst = CoreHelper::scanDir($migratorsFolder);
        foreach ($migratorsLst as $item) {
            $fExt = pathinfo($item, PATHINFO_EXTENSION);
            if (strcmp(strtolower($fExt), "php") !== 0)
                continue;
            try {
                include_once $item;
            } catch (\Exception $e) {
//                    error_log("MigrationsCore: $e->getMessage()!\n");
//                    echo 'Выброшено исключение: ',  $e->getMessage(), "\n";
            }
        }
    }

    /**
     * Зарегистрировать адаптер миграции
     * @param string $name - название адаптера, используемое в конфигурацинном файле для доступа к БД
     * @param string $className - имя класса мигратора (с namespace; наследник класса BaseMigrator)
     */
    public function registerMigrator(string $name, string $className) {
        $name = trim($name);
        $className = trim($className);
        if (empty($name) || empty($className))
            return;
        if (array_key_exists($name, $this->_migrators))
            return; // TODO adapter already exist!
        $this->_migrators[$name] = $className;
    }

    /**
     * Получить текущую версию установленных миграций
     * @return int
     */
    public function currentVersion(): int {
        // --- init database factory ---
        DatabaseFactory::instance()->loadExtensions();
        DatabaseFactory::instance()->loadConfig();
        // --- select version ---
        return $this->currentMigrationVersion();
    }

    /**
     * Метод инициализации миграции
     * @param int $version
     * @param bool $showOutput
     */
    public function migrate(/* int|null */ $version, bool $showOutput = false) {
        // --- check migrations list ---
        if (empty($this->_migrations)) {
            echo "MigrationsCore: Not found migration files\r\n";
            return; // nothing
        }

        // --- init database factory ---
        DatabaseFactory::instance()->loadExtensions();
        DatabaseFactory::instance()->loadConfig();
        // --- select current adapter name ---
        $adapterName = DatabaseFactory::instance()->currentAdapterName();
        if (empty($adapterName))
            return; // TODO throw new \RuntimeException('MigrationsCore: invalid current database adapter name!);

        $migratorName = $this->selectMigratorClassName($adapterName);
        if (empty($migratorName))
            return; // TODO throw new \RuntimeException('MigrationsCore: invalid current migrator name for database adapter (name: $adapterName)!);

        // --- processing ---
        if (!is_null($version))
            $version = intval($version);
        else
            $version = PHP_INT_MAX;

        if ($version < 0)
            $version = PHP_INT_MAX;

        // --- select current migration version from database ---
        $currentVersion = $this->currentMigrationVersion();

        // --- check ---
        if ($currentVersion == $version) {
            echo "MigrationsCore: Current migration version already installed\r\n";
            return; // nothing
        }

        // --- sort ---
        $isOk = false;
        $mCommand = 'up';
        if ($currentVersion < $version) {
            $isOk = ksort($this->_migrations, SORT_NUMERIC);
        } else {
            $isOk = krsort($this->_migrations, SORT_NUMERIC);
            $mCommand = 'down';
        }
        if ($isOk === false)
            return; // TODO throw new \RuntimeException('MigrationsCore: sort migrations failed!);

        echo "MigrationsCore: Start migrate from $currentVersion\r\n";
        $newVersion = -1;
        foreach ($this->_migrations as $m) {
            $mVersion = $m->version();
            $mClassName = get_class($m);
            if (strcmp($mCommand, 'up') === 0
                && $newVersion == $version) {
                break;
            } else if (strcmp($mCommand, 'down') === 0
                       && $mVersion == $version) {
                $newVersion = $version;
                break;
            }
            if (strcmp($mCommand, 'up') === 0
                && $currentVersion >= $mVersion) {
                echo "[Skip] Migration ($mVersion - '$mClassName')\r\n";
                continue;
            }
            if (strcmp($mCommand, 'down') === 0
                && $currentVersion < $mVersion) {
                echo "[Skip] Migration ($mVersion - '$mClassName')\r\n";
                continue;
            }

            $msgAttr = "to";
            if (strcmp($mCommand, 'down') === 0)
                $msgAttr = "from";

            echo "[". ucwords($mCommand)."] Migrate $msgAttr ($mVersion - '$mClassName')\r\n";
            ob_start();
            if ($m->migrate($version, $migratorName, $showOutput, "\r\n") === false)
                break;
            $outPut = ob_get_clean();
            if ($showOutput)
                echo $outPut;
            $newVersion = $m->version();
            if (strcmp($mCommand, 'up') === 0)
                $this->appendMigrationVersion($newVersion, $showOutput);
            elseif (strcmp($mCommand, 'down') === 0)
                $this->removeMigrationVersion($newVersion, $showOutput);
        }
        echo "MigrationsCore: Finish migrate\r\n";

        // --- select current migration version from database ---
        $currentVersion = $this->currentMigrationVersion();
        echo "MigrationsCore: Current migration version: $currentVersion\r\n";
    }

    /**
     * Запросить состояние миграций
     */
    public function migrateStatus() {
        $curV = $this->currentVersion();
        $allV = $this->allInstallMigrationVersions();
        $tmpStateLst = [];
        foreach ($allV as $item) {
            $mClassName = "???";
            $stateStr = "File Not Found";
            if (array_key_exists($item, $this->_migrations)) {
                $tmpM = $this->_migrations[$item];
                $mClassName = get_class($tmpM);
                $stateStr = "Not Installed";
                if ($curV >= $item)
                    $stateStr = "Installed";
            }
            $tmpStateLst[$item] = "[$stateStr] Migration ($item - '$mClassName')";
        }
        foreach ($this->_migrations as $key => $value) {
            $mClassName = get_class($value);
            $stateStr = "Not Installed";
            if ($curV >= $key)
                $stateStr = "Installed";
            $tmpStateLst[$key] = "[$stateStr] Migration ($key - '$mClassName')";
        }
        ksort($tmpStateLst, SORT_NUMERIC);
        $size = count($this->_migrations);
        $sizeInstall = count($allV);
        echo "MigrationsCore: Current database version: $curV\r\n";
        echo "MigrationsCore: Found migration files: $size\r\n";
        echo "MigrationsCore: Installed in database: $sizeInstall\r\n";
        foreach ($tmpStateLst as $item)
            echo "$item\r\n";
    }

    /**
     * Метод перустановки миграции
     * @param int $step
     * @param bool $showOutput
     */
    public function migrateRedo(/* int|null */ $step, bool $showOutput = false) {
        $curV = $this->currentVersion();
        if ($curV == 0) {
            echo "MigrationsCore: Migrations have not yet been installed!\r\n";
            return; // nothing
        }
        $this->rollback($step, $showOutput);
        echo "\r\n";
        $this->migrate($curV, $showOutput);
    }

    /**
     * Метод отката миграции
     * @param int $step
     * @param bool $showOutput
     */
    public function rollback(/* int|null */ $step, bool $showOutput = false) {
        // --- check migrations list ---
        if (empty($this->_migrations)) {
            echo "MigrationsCore: Not found migration files\r\n";
            return; // nothing
        }

        // --- init database factory ---
        DatabaseFactory::instance()->loadExtensions();
        DatabaseFactory::instance()->loadConfig();
        // --- select current adapter name ---
        $adapterName = DatabaseFactory::instance()->currentAdapterName();
        if (empty($adapterName))
            return; // TODO throw new \RuntimeException('MigrationsCore: invalid current database adapter name!);

        $migratorName = $this->selectMigratorClassName($adapterName);
        if (empty($migratorName))
            return; // TODO throw new \RuntimeException('MigrationsCore: invalid current migrator name for database adapter (name: $adapterName)!);

        // --- processing ---
        if (!is_null($step))
            $step = intval($step);
        else
            $step = 1;

        if ($step == 0) {
            echo "MigrationsCore: Not specified the number of steps to change. Stop.\r\n";
            return; // nothing
        }
        if ($step < 0 || $step > count($this->_migrations))
            $step = count($this->_migrations);

        // --- select current migration version from database ---
        $currentVersion = $this->currentMigrationVersion();

        // --- check ---
        if ($currentVersion == 0) {
            echo "MigrationsCore: Migrations have not yet been installed!\r\n";
            return; // nothing
        }

        // --- sort ---
        $isOk = krsort($this->_migrations, SORT_NUMERIC);
        if ($isOk === false)
            return; // TODO throw new \RuntimeException('MigrationsCore: sort migrations failed!);

        echo "MigrationsCore: Start rollback from $currentVersion\r\n";
        $newVersion = -1;
        foreach ($this->_migrations as $m) {
            $mVersion = $m->version();
            $mClassName = get_class($m);
            // --- check steps ---
            if ($step == 0) {
                $newVersion = $mVersion;
                break;
            }
            // --- check version ---
            if ($currentVersion < $mVersion) {
                echo "[Skip] Migration ($mVersion - '$mClassName')\r\n";
                continue;
            }
            echo "[Down] Migrate from ($mVersion - '$mClassName')\r\n";
            ob_start();
            if ($m->migrate(($mVersion - 1), $migratorName, $showOutput, "\r\n") === false)
                break;
            $outPut = ob_get_clean();
            if ($showOutput)
                echo $outPut;
            $newVersion = $m->version();
            $this->removeMigrationVersion($newVersion, $showOutput);
            $step = $step - 1;
        }
        echo "MigrationsCore: Finish rollback\r\n";

        // --- select current migration version from database ---
        $currentVersion = $this->currentMigrationVersion();
        echo "MigrationsCore: Current migration version: $currentVersion\r\n";
    }

    /**
     * Выполнить дамп схемы базы данных
     * @param bool $showOutput
     */
    public function schemaDump(bool $showOutput = false) {
        // --- init database factory ---
        DatabaseFactory::instance()->loadExtensions();
        DatabaseFactory::instance()->loadConfig();
        // --- select current adapter name ---
        $adapterName = DatabaseFactory::instance()->currentAdapterName();
        if (empty($adapterName))
            return; // TODO throw new \RuntimeException('MigrationsCore: invalid current database adapter name!);

        $migratorName = $this->selectMigratorClassName($adapterName);
        if (empty($migratorName))
            return; // TODO throw new \RuntimeException('MigrationsCore: invalid current migrator name for database adapter (name: $adapterName)!);

        // --- processing ---
        $curV = $this->currentMigrationVersion();
        $dumpData = "";
        $dumper = new SchemaDumper();
        $dumper->dump($curV, $migratorName, $showOutput, "\r\n", $dumpData);
        unset($dumper);

        // --- write file ---
        $resultFilePath = CoreHelper::buildPath("db", "Schema.php");
        if (false !== @file_put_contents($resultFilePath, $dumpData)) {
            @chmod($resultFilePath, 0644 & ~umask());
            echo "[Created] $resultFilePath\r\n";
            return;
        }
    }

    /**
     * Пересоздать базу данных и установить дамп
     * @param bool $showOutput
     */
    public function schemaLoad(bool $showOutput = false) {
        // --- init database factory ---
        DatabaseFactory::instance()->loadExtensions();
        DatabaseFactory::instance()->loadConfig();
        // --- select current adapter name ---
        $adapterName = DatabaseFactory::instance()->currentAdapterName();
        if (empty($adapterName))
            return; // TODO throw new \RuntimeException('MigrationsCore: invalid current database adapter name!);

        $migratorName = $this->selectMigratorClassName($adapterName);
        if (empty($migratorName))
            return; // TODO throw new \RuntimeException('MigrationsCore: invalid current migrator name for database adapter (name: $adapterName)!);

        // --- processing ---
        $schemaFilePath = CoreHelper::buildPath("db", "Schema.php");
        if (!is_file($schemaFilePath) || !is_readable($schemaFilePath)) {
            echo "MigrationsCore: Not found schema dump file!\r\n";
            return;
        }
        include_once $schemaFilePath;
        if (!class_exists('Schema')) {
            echo "MigrationsCore: Not found schema dump class!\r\n";
            return;
        }

        // --- re-create database ---
        $dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter(false);
        if (is_null($dbAdapter))
            return; // TODO throw new \RuntimeException('Migration: invalid database connector (NULL)!);

        $migrator = new $migratorName($dbAdapter);
        if (is_null($migrator))
            return; // TODO throw new \RuntimeException('Migration: invalid database migrator (NULL)!);

        $dbAdapter->setShowOutput($showOutput);
        $dbAdapter->setOutputDelimiter("\r\n");
        $dbAdapterSettings = $dbAdapter->settings();
        if (is_null($dbAdapterSettings) && !isset($dbAdapterSettings['database'])) {
            unset($migrator);
            unset($dbAdapter);
            echo "MigrationsCore: Not found database adapter settings!\r\n";
            return;
        }
        $dbName = $dbAdapterSettings['database'];
        unset($dbAdapterSettings['database']);
        $dbAdapter->recreatePDO($dbAdapterSettings);
        $migrator->dropDatabase($dbName);
        $migrator->createDatabase($dbName);
        unset($migrator);
        unset($dbAdapter);

        echo "MigrationsCore: Start load schema dump\r\n";
        $tmpSchema = new \Schema();
        $tmpV = $tmpSchema->version();
        if ($tmpV <= 0) {
            echo "MigrationsCore: Invalid schema dump version (version: $tmpV)!\r\n";
            return;
        }
        ob_start();
        if ($tmpSchema->migrate($tmpV + 1, $migratorName, $showOutput, "\r\n") === false) {
            echo "MigrationsCore: Load schema dump failed!\r\n";
            return;
        }
        $outPut = ob_get_clean();
        if ($showOutput)
            echo $outPut;

        $this->appendMigrationVersion($tmpV, $showOutput);
        echo "MigrationsCore: Finish load schema dump\r\n";

        // --- select current migration version from database ---
        $currentVersion = $this->currentMigrationVersion();
        echo "MigrationsCore: Current migration version: $currentVersion\r\n";
    }

    /**
     * Создать базу данных для миграций
     * @param bool $showOutput
     */
    public function dbCreate(bool $showOutput = false) {
        // --- init database factory ---
        DatabaseFactory::instance()->loadExtensions();
        DatabaseFactory::instance()->loadConfig();
        // --- select current adapter name ---
        $adapterName = DatabaseFactory::instance()->currentAdapterName();
        if (empty($adapterName))
            return; // TODO throw new \RuntimeException('MigrationsCore: invalid current database adapter name!);

        $migratorName = $this->selectMigratorClassName($adapterName);
        if (empty($migratorName))
            return; // TODO throw new \RuntimeException('MigrationsCore: invalid current migrator name for database adapter (name: $adapterName)!);

        // --- processing ---

        // --- create database ---
        $dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter(false);
        if (is_null($dbAdapter))
            return; // TODO throw new \RuntimeException('Migration: invalid database connector (NULL)!);

        $migrator = new $migratorName($dbAdapter);
        if (is_null($migrator))
            return; // TODO throw new \RuntimeException('Migration: invalid database migrator (NULL)!);

        $dbAdapter->setShowOutput($showOutput);
        $dbAdapter->setOutputDelimiter("\r\n");
        $dbAdapterSettings = $dbAdapter->settings();
        if (is_null($dbAdapterSettings) && !isset($dbAdapterSettings['database'])) {
            unset($migrator);
            unset($dbAdapter);
            echo "MigrationsCore: Not found database adapter settings!\r\n";
            return;
        }
        $dbName = $dbAdapterSettings['database'];
        echo "MigrationsCore: Start create database (name: $dbName)\r\n";
        unset($dbAdapterSettings['database']);
        $dbAdapter->recreatePDO($dbAdapterSettings);
        $migrator->createDatabase($dbName);
        unset($migrator);
        unset($dbAdapter);
        echo "MigrationsCore: Finish create database\r\n";
    }

    /**
     * Удалить базу данных для миграций
     * @param bool $showOutput
     */
    public function dbDrop(bool $showOutput = false) {
        // --- init database factory ---
        DatabaseFactory::instance()->loadExtensions();
        DatabaseFactory::instance()->loadConfig();
        // --- select current adapter name ---
        $adapterName = DatabaseFactory::instance()->currentAdapterName();
        if (empty($adapterName))
            return; // TODO throw new \RuntimeException('MigrationsCore: invalid current database adapter name!);

        $migratorName = $this->selectMigratorClassName($adapterName);
        if (empty($migratorName))
            return; // TODO throw new \RuntimeException('MigrationsCore: invalid current migrator name for database adapter (name: $adapterName)!);

        // --- processing ---

        // --- create database ---
        $dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter(false);
        if (is_null($dbAdapter))
            return; // TODO throw new \RuntimeException('Migration: invalid database connector (NULL)!);

        $migrator = new $migratorName($dbAdapter);
        if (is_null($migrator))
            return; // TODO throw new \RuntimeException('Migration: invalid database migrator (NULL)!);

        $dbAdapter->setShowOutput($showOutput);
        $dbAdapter->setOutputDelimiter("\r\n");
        $dbAdapterSettings = $dbAdapter->settings();
        if (is_null($dbAdapterSettings) && !isset($dbAdapterSettings['database'])) {
            unset($migrator);
            unset($dbAdapter);
            echo "MigrationsCore: Not found database adapter settings!\r\n";
            return;
        }
        $dbName = $dbAdapterSettings['database'];
        echo "MigrationsCore: Start drop database (name: $dbName)\r\n";
        unset($dbAdapterSettings['database']);
        $dbAdapter->recreatePDO($dbAdapterSettings);
        $migrator->dropDatabase($dbName);
        unset($migrator);
        unset($dbAdapter);
        echo "MigrationsCore: Finish drop database\r\n";
    }

    /**
     * Выполнить запуск файда db/Seed.php
     */
    public function dbSeed() {
        // --- init database factory ---
        DatabaseFactory::instance()->loadExtensions();
        DatabaseFactory::instance()->loadConfig();
        // --- processing ---
        $seedFilePath = CoreHelper::buildPath("db", "Seed.php");
        if (!is_file($seedFilePath) || !is_readable($seedFilePath)) {
            echo "MigrationsCore: Not found Seed.php file!\r\n";
            return;
        }
        include_once $seedFilePath;
    }

    // --- private ---

    /**
     * Запросить имя класса адаптера миграции
     * @param string $name - название адаптера
     * @return string
     */
    private function selectMigratorClassName(string $name): string {
        $name = trim($name);
        if (array_key_exists($name, $this->_migrators))
            return $this->_migrators[$name];
        return "";
    }

    /**
     * Проверка таблицы версий миграций
     * @return bool
     *
     * Есчли таблица не найдена, то она создается.
     */
    private function checkMigrationTable() {
        $dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($dbAdapter))
            return false;
        $tables = $dbAdapter->tables();
        if (in_array('schema_migrations', $tables)
            || in_array('public.schema_migrations', $tables))
            return true;
        $sql = <<<EOT
        CREATE TABLE schema_migrations (
            version character varying NOT NULL,
            CONSTRAINT schema_migrations_pkey PRIMARY KEY (version)
        )
EOT;
        $res = $dbAdapter->queryTransaction($sql);
        return !is_null($res);
    }

    /**
     * Получить текущую версию установленных миграций
     * @return int
     */
    private function currentMigrationVersion(): int {
        if (!$this->checkMigrationTable())
            return 0;
        $dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($dbAdapter))
            return 0;
        $res = $dbAdapter->queryTransaction("SELECT version FROM schema_migrations ORDER BY version DESC LIMIT 1;");
        if (empty($res))
            return 0;
        return intval($res[0]->version);
    }

    /**
     * Получить все версии установленных миграций
     * @return array
     */
    private function allInstallMigrationVersions(): array {
        if (!$this->checkMigrationTable())
            return [];
        $dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($dbAdapter))
            return [];
        $res = $dbAdapter->queryTransaction("SELECT version FROM schema_migrations ORDER BY version ASC;");
        if (empty($res))
            return [];
        $tmpLst = [];
        foreach ($res as $r)
            $tmpLst[intval($r->version)] = intval($r->version);
        return $tmpLst;
    }

    /**
     * Добавить версию миграции
     * @param int $version
     * @param bool $showOutput
     */
    private function appendMigrationVersion(int $version, bool $showOutput = false) {
        if (!$this->checkMigrationTable())
            return;
        $dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($dbAdapter))
            return;
        $dbAdapter->setShowOutput($showOutput);
        $dbAdapter->setOutputDelimiter("\r\n");
        $dbAdapter->queryTransaction("INSERT INTO schema_migrations (version) VALUES ('$version');");
    }

    /**
     * Удалить версию миграции
     * @param int $version
     * @param bool $showOutput
     */
    private function removeMigrationVersion(int $version, bool $showOutput = false) {
        if (!$this->checkMigrationTable())
            return;
        $dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($dbAdapter))
            return;
        $dbAdapter->setShowOutput($showOutput);
        $dbAdapter->setOutputDelimiter("\r\n");
        $dbAdapter->queryTransaction("DELETE FROM schema_migrations WHERE version = '$version';");
    }

    /**
     * Загрузить модели данных
     * @param string $path
     */
    private function loadModels(string $path) {
        if (!is_dir($path))
            return;
        $modelsLst = CoreHelper::scanDir($path);
        foreach ($modelsLst as $item) {
            $fExt = pathinfo($item, PATHINFO_EXTENSION);
            if (strcmp(strtolower($fExt), "php") !== 0)
                continue;
            try {
                include_once $item;
            } catch (\Exception $e) {
//                    error_log("MigrationsCore: $e->getMessage()!\n");
//                    echo 'Выброшено исключение: ',  $e->getMessage(), "\n";
            }
        }
    }

    /**
     * Загрузить миграции
     * @param string $path
     */
    private function loadMigrations(string $path) {
        if (!is_dir($path))
            return;
        $migrationsLst = CoreHelper::scanDir($path);
        foreach ($migrationsLst as $item) {
            $fExt = pathinfo($item, PATHINFO_EXTENSION);
            if (strcmp(strtolower($fExt), "php") !== 0)
                continue;
            preg_match('/^([0-9]{14})_.*\.php$/', basename($item), $matches, PREG_OFFSET_CAPTURE);
            if (count($matches) < 2)
                continue;
            $tmpClassName = substr(basename($item), 15, strlen(basename($item)) - 19);
            try {
                include_once $item;
                if (class_exists($tmpClassName)) {
                    $tmpMigration = new $tmpClassName();
                    if (!$tmpMigration->isValid()) {
                        unset($tmpMigration);
                    } else {
                        $this->_migrations[$tmpMigration->version()] = $tmpMigration;
                    }
                }
            } catch (\Exception $e) {
//                    error_log("MigrationsCore: $e->getMessage()!\n");
//                    echo 'Выброшено исключение: ',  $e->getMessage(), "\n";
            }
        }
    }

    /**
     * Загрузить список игнорируемых плагинов
     * @param string $dir - каталог с файлом игнор-листа
     * @return array
     */
    private function loadPluginsIgnoreList(string $dir) {
        if (!is_dir($dir))
            return [];
        if (!file_exists(CoreHelper::buildPath($dir, ComponentsManager::IGNORE_LIST_NAME)))
            return [];
        $ignoreLst = [];
        if ($file = fopen(CoreHelper::buildPath($dir, ComponentsManager::IGNORE_LIST_NAME), "r")) {
            while (!feof($file)) {
                $line = trim(fgets($file));
                if (empty($line))
                    continue;
                if (substr($line, 0, 1) == "#")
                    continue;
                $ignoreLst[] = $line;
            }
            fclose($file);
        }
        return $ignoreLst;
    }
}