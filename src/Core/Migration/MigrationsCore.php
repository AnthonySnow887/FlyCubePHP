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
include_once 'MySQLMigrator.php';

use Exception;
use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\Database\DatabaseFactory;
use FlyCubePHP\ComponentsCore\ComponentsManager;

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
        $this->registerMigrator('mysql', 'FlyCubePHP\Core\Migration\MySQLMigrator');
        $this->registerMigrator('mariadb', 'FlyCubePHP\Core\Migration\MySQLMigrator');

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
    public function currentVersion(array $dbNames): int {
        // --- init database factory ---
        DatabaseFactory::instance()->loadExtensions();
        DatabaseFactory::instance()->loadConfig();
        // --- select version ---
        return $this->currentMigrationVersion($dbNames);
    }

    /**
     * Метод инициализации миграции
     * @param array $dbNames
     * @param int|null $version
     * @param bool $showOutput
     */
    public function migrate(array $dbNames, /* int|null */ $version, bool $showOutput = false) {
        // --- check migrations list ---
        if (empty($this->_migrations)) {
            echo "MigrationsCore: Not found migration files\r\n";
            return; // nothing
        }
        if (empty($dbNames)) {
            echo "MigrationsCore: Database names array is Empty!\r\n";
            return; // nothing
        }

        // --- init database factory ---
        DatabaseFactory::instance()->loadExtensions();
        DatabaseFactory::instance()->loadConfig();

        // --- processing ---
        if (!is_null($version))
            $version = intval($version);
        else
            $version = PHP_INT_MAX;

        if ($version < 0)
            $version = PHP_INT_MAX;

        // --- select current migration version from database ---
        $currentVersion = $this->currentMigrationVersion($dbNames);

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
            // --- configuration current migration ---
            $m->configuration();
            // --- select migration info ---
            $mVersion = $m->version();
            $mClassName = get_class($m);
            $mDatabase = $m->database();
            $mDatabaseTitle = $mDatabase;
            if (empty($mDatabaseTitle))
                $mDatabaseTitle = 'primary';

            // --- select current adapter name ---
            if (empty($mDatabase))
                $adapterName = DatabaseFactory::instance()->primaryAdapterName();
            else
                $adapterName = DatabaseFactory::instance()->secondaryAdapterName($mDatabase);

            if (empty($adapterName)) {
                echo "MigrationsCore: Invalid current database adapter name!\r\n";
                return;
            }

            // --- select current migrator name ---
            $migratorName = $this->selectMigratorClassName($adapterName);
            if (empty($migratorName)) {
                echo "MigrationsCore: Invalid current migrator name for database adapter (name: $adapterName)!\r\n";
                return;
            }

            // --- check database name ---
            if (!in_array($mDatabase, $dbNames)) {
//                echo "[Skip][DB: $mDatabaseTitle] Migration ($mVersion - '$mClassName')\r\n";
                continue;
            }
            if (strcmp($mCommand, 'up') === 0
                && $newVersion == $version) {
                break;
            } else if (strcmp($mCommand, 'down') === 0
                       && $mVersion == $version) {
                $newVersion = $version;
                break;
            }

            // --- select current migration database version ---
            $currentDbVersion = $this->lastMigrationVersion($mDatabase);

            // --- check skip ---
            if (strcmp($mCommand, 'up') === 0
                && $currentVersion >= $mVersion) {
                echo "[Skip][DB: $mDatabaseTitle] Migration ($mVersion - '$mClassName')\r\n";
                continue;
            }
            if (strcmp($mCommand, 'up') === 0
                && $currentDbVersion >= $mVersion) {
                echo "[Skip][DB: $mDatabaseTitle] Migration ($mVersion - '$mClassName')\r\n";
                continue;
            }
            if (strcmp($mCommand, 'down') === 0
                && $currentVersion < $mVersion) {
                echo "[Skip][DB: $mDatabaseTitle] Migration ($mVersion - '$mClassName')\r\n";
                continue;
            }
            if (strcmp($mCommand, 'down') === 0
                && $currentDbVersion < $mVersion) {
                echo "[Skip][DB: $mDatabaseTitle] Migration ($mVersion - '$mClassName')\r\n";
                continue;
            }

            $msgAttr = "to";
            if (strcmp($mCommand, 'down') === 0)
                $msgAttr = "from";

            echo "[". ucwords($mCommand)."][DB: $mDatabaseTitle] Migrate $msgAttr ($mVersion - '$mClassName')\r\n";
            ob_start();
            if ($m->migrate($version, $migratorName, $showOutput, "\r\n") === false)
                break;
            $outPut = ob_get_clean();
            if ($showOutput)
                echo $outPut;
            $newVersion = $m->version();
            if (strcmp($mCommand, 'up') === 0)
                $this->appendMigrationVersion($mDatabase, $newVersion, $showOutput);
            elseif (strcmp($mCommand, 'down') === 0)
                $this->removeMigrationVersion($mDatabase, $newVersion, $showOutput);
        }
        echo "MigrationsCore: Finish migrate\r\n";

        // --- select current migration version from database ---
        $currentVersion = $this->currentMigrationVersion($dbNames);
        echo "MigrationsCore: Current migration version: $currentVersion\r\n";
    }

    /**
     * Запросить состояние миграций
     * @param array $dbNames
     */
    public function migrateStatus(array $dbNames) {
        $curV = $this->currentVersion($dbNames);
        $allV = $this->allInstallMigrationVersions($dbNames);
        $tmpStateLst = [];
        foreach ($allV as $item) {
            $mClassName = "???";
            $stateStr = "File Not Found";
            $databaseTitle = "???";
            if (array_key_exists($item, $this->_migrations)) {
                $tmpM = $this->_migrations[$item];
                // --- configuration current migration ---
                $tmpM->configuration();
                // --- select migration info ---
                $mClassName = get_class($tmpM);
                $mDatabase = $tmpM->database();
                $databaseTitle = $mDatabase;
                if (empty($databaseTitle))
                    $databaseTitle = 'primary';
                // --- check database name ---
                if (!in_array($mDatabase, $dbNames))
                    continue; // skip
                $stateStr = "Not Installed";
                if ($this->lastMigrationVersion($tmpM->database()) >= $item)
                    $stateStr = "Installed";
            }
            $tmpStateLst[$item] = "[$stateStr][DB: $databaseTitle] Migration ($item - '$mClassName')";
        }
        $size = 0;
        foreach ($this->_migrations as $key => $value) {
            // --- configuration current migration ---
            $value->configuration();
            // --- select migration info ---
            $mClassName = get_class($value);
            $mDatabase = $value->database();
            $databaseTitle = $mDatabase;
            if (empty($databaseTitle))
                $databaseTitle = 'primary';
            // --- check database name ---
            if (!in_array($mDatabase, $dbNames))
                continue; // skip
            $stateStr = "Not Installed";
            if ($this->lastMigrationVersion($value->database()) >= $key)
                $stateStr = "Installed";
            $tmpStateLst[$key] = "[$stateStr][DB: $databaseTitle] Migration ($key - '$mClassName')";
            $size = $size + 1;
        }
        ksort($tmpStateLst, SORT_NUMERIC);
        $sizeInstall = count($allV);
        echo "MigrationsCore: Current database version: $curV\r\n";
        echo "MigrationsCore: Found migration files: $size\r\n";
        echo "MigrationsCore: Installed in database: $sizeInstall\r\n";
        foreach ($tmpStateLst as $item)
            echo "$item\r\n";
    }

    /**
     * Метод перустановки миграции
     * @param array $dbNames
     * @param int $step
     * @param bool $showOutput
     */
    public function migrateRedo(array $dbNames, /* int|null */ $step, bool $showOutput = false) {
        $curV = $this->currentVersion($dbNames);
        if ($curV == 0) {
            echo "MigrationsCore: Migrations have not yet been installed!\r\n";
            return; // nothing
        }
        $this->rollback($dbNames, $step, $showOutput);
        echo "\r\n";
        $this->migrate($dbNames, $curV, $showOutput);
    }

    /**
     * Метод отката миграции
     * @param array $dbNames
     * @param int $step
     * @param bool $showOutput
     */
    public function rollback(array $dbNames, /* int|null */ $step, bool $showOutput = false) {
        // --- check migrations list ---
        if (empty($this->_migrations)) {
            echo "MigrationsCore: Not found migration files\r\n";
            return; // nothing
        }
        if (empty($dbNames)) {
            echo "MigrationsCore: Database names array is Empty!\r\n";
            return; // nothing
        }

        // --- init database factory ---
        DatabaseFactory::instance()->loadExtensions();
        DatabaseFactory::instance()->loadConfig();

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
        $currentVersion = $this->currentMigrationVersion($dbNames);

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
            // --- configuration current migration ---
            $m->configuration();
            // --- select migration info ---
            $mVersion = $m->version();
            $mClassName = get_class($m);
            $mDatabase = $m->database();
            $mDatabaseTitle = $mDatabase;
            if (empty($mDatabaseTitle))
                $mDatabaseTitle = 'primary';

            // --- select current adapter name ---
            if (empty($mDatabase))
                $adapterName = DatabaseFactory::instance()->primaryAdapterName();
            else
                $adapterName = DatabaseFactory::instance()->secondaryAdapterName($mDatabase);

            if (empty($adapterName)) {
                echo "MigrationsCore: Invalid current database adapter name!\r\n";
                return;
            }

            // --- select current migrator name ---
            $migratorName = $this->selectMigratorClassName($adapterName);
            if (empty($migratorName)) {
                echo "MigrationsCore: Invalid current migrator name for database adapter (name: $adapterName)!\r\n";
                return;
            }

            // --- select current migration database version ---
            $currentDbVersion = $this->lastMigrationVersion($mDatabase);

            // --- check steps ---
            if ($step == 0) {
                $newVersion = $mVersion;
                break;
            }
            // --- check database name ---
            if (!in_array($mDatabase, $dbNames)) {
//                echo "[Skip][DB: $mDatabaseTitle] Migration ($mVersion - '$mClassName')\r\n";
                continue;
            }
            // --- check version ---
            if ($currentVersion < $mVersion
                || $currentDbVersion < $mVersion) {
                echo "[Skip][DB: $mDatabaseTitle] Migration ($mVersion - '$mClassName')\r\n";
                continue;
            }
            echo "[Down][DB: $mDatabaseTitle] Migrate from ($mVersion - '$mClassName')\r\n";
            ob_start();
            if ($m->migrate(($mVersion - 1), $migratorName, $showOutput, "\r\n") === false)
                break;
            $outPut = ob_get_clean();
            if ($showOutput)
                echo $outPut;
            $newVersion = $m->version();
            $this->removeMigrationVersion($mDatabase, $newVersion, $showOutput);
            $step = $step - 1;
        }
        echo "MigrationsCore: Finish rollback\r\n";

        // --- select current migration version from database ---
        $currentVersion = $this->currentMigrationVersion($dbNames);
        echo "MigrationsCore: Current migration version: $currentVersion\r\n";
    }

    /**
     * Выполнить дамп схемы базы данных
     * @param string $db
     * @param bool $showOutput
     */
    public function schemaDump(string $db, bool $showOutput = false) {
        // --- init database factory ---
        DatabaseFactory::instance()->loadExtensions();
        DatabaseFactory::instance()->loadConfig();
        // --- select adapter name ---
        if (empty($db))
            $adapterName = DatabaseFactory::instance()->primaryAdapterName();
        else
            $adapterName = DatabaseFactory::instance()->secondaryAdapterName($db);

        if (empty($adapterName)) {
            echo "MigrationsCore: Invalid current database adapter name!\r\n";
            return;
        }

        $migratorName = $this->selectMigratorClassName($adapterName);
        if (empty($migratorName)) {
            echo "MigrationsCore: Invalid current migrator name for database adapter (name: $adapterName)!\r\n";
            return;
        }

        // --- processing ---
        $curV = $this->lastMigrationVersion($db);
        $dumpData = "";
        $dumper = new SchemaDumper();
        $dumper->dump($curV, $migratorName, $showOutput, "\r\n", $db, $dumpData);
        unset($dumper);

        // --- write file ---
        $fName = "Schema";
        if (!empty($db))
            $fName = "Schema-$db";

        $fName = CoreHelper::camelcase($fName);
        $resultFilePath = CoreHelper::buildPath("db", "$fName.php");
        if (false !== @file_put_contents($resultFilePath, $dumpData)) {
            @chmod($resultFilePath, 0644 & ~umask());
            echo "[Created] $resultFilePath\r\n";
            return;
        }
    }

    /**
     * Пересоздать базу данных и установить дамп
     * @param string $db
     * @param bool $showOutput
     */
    public function schemaLoad(string $db, bool $showOutput = false) {
        // --- init database factory ---
        DatabaseFactory::instance()->loadExtensions();
        DatabaseFactory::instance()->loadConfig();
        // --- select adapter name ---
        if (empty($db))
            $adapterName = DatabaseFactory::instance()->primaryAdapterName();
        else
            $adapterName = DatabaseFactory::instance()->secondaryAdapterName($db);

        if (empty($adapterName)) {
            echo "MigrationsCore: Invalid current database adapter name!\r\n";
            return;
        }

        $migratorName = $this->selectMigratorClassName($adapterName);
        if (empty($migratorName)) {
            echo "MigrationsCore: Invalid current migrator name for database adapter (name: $adapterName)!\r\n";
            return;
        }

        // --- processing ---
        $fName = "Schema";
        if (!empty($db))
            $fName = "Schema-$db";

        $fName = CoreHelper::camelcase($fName);
        $schemaFilePath = CoreHelper::buildPath("db", "$fName.php");
        if (!is_file($schemaFilePath) || !is_readable($schemaFilePath)) {
            echo "MigrationsCore: Not found schema dump file!\r\n";
            return;
        }
        include_once $schemaFilePath;
        if (!class_exists($fName)) {
            echo "MigrationsCore: Not found schema dump class '$fName'!\r\n";
            return;
        }

        // --- re-create database ---
        $dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter([ 'database' => $db, 'auto-connect' => false ]);
        if (is_null($dbAdapter)) {
            echo "MigrationsCore: Invalid database adapter (NULL)!\r\n";
            return;
        }

        $migrator = new $migratorName($dbAdapter);
        if (is_null($migrator)) {
            echo "MigrationsCore: Invalid database migrator (NULL)!\r\n";
            return;
        }

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

        echo "MigrationsCore: Start load schema dump '$fName'\r\n";
        $tmpSchema = new $fName();
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

        $this->appendMigrationVersion($db, $tmpV, $showOutput);
        echo "MigrationsCore: Finish load schema dump\r\n";

        // --- select current migration version from database ---
        $currentVersion = $this->currentMigrationVersion([ $db ]);
        echo "MigrationsCore: Current migration version: $currentVersion\r\n";
    }

    /**
     * Создать базу данных для миграций
     * @param string $db
     * @param bool $showOutput
     */
    public function dbCreate(string $db, bool $showOutput = false) {
        // --- init database factory ---
        DatabaseFactory::instance()->loadExtensions();
        DatabaseFactory::instance()->loadConfig();
        // --- select adapter name ---
        if (empty($db))
            $adapterName = DatabaseFactory::instance()->primaryAdapterName();
        else
            $adapterName = DatabaseFactory::instance()->secondaryAdapterName($db);

        if (empty($adapterName)) {
            echo "MigrationsCore: Invalid current database adapter name!\r\n";
            return;
        }

        $migratorName = $this->selectMigratorClassName($adapterName);
        if (empty($migratorName)) {
            echo "MigrationsCore: Invalid current migrator name for database adapter (name: $adapterName)!\r\n";
            return;
        }

        // --- processing ---

        // --- create database ---
        $dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter([ 'database' => $db, 'auto-connect' => false ]);
        if (is_null($dbAdapter)) {
            echo "Migration: Invalid database adapter (NULL)!\r\n";
            return;
        }

        $migrator = new $migratorName($dbAdapter);
        if (is_null($migrator)) {
            echo "Migration: Invalid database migrator (NULL)!\r\n";
            return;
        }

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
     * @param string $db
     * @param bool $showOutput
     */
    public function dbDrop(string $db, bool $showOutput = false) {
        // --- init database factory ---
        DatabaseFactory::instance()->loadExtensions();
        DatabaseFactory::instance()->loadConfig();
        // --- select adapter name ---
        if (empty($db))
            $adapterName = DatabaseFactory::instance()->primaryAdapterName();
        else
            $adapterName = DatabaseFactory::instance()->secondaryAdapterName($db);

        if (empty($adapterName)) {
            echo "MigrationsCore: Invalid current database adapter name!\r\n";
            return;
        }

        $migratorName = $this->selectMigratorClassName($adapterName);
        if (empty($migratorName)) {
            echo "MigrationsCore: Invalid current migrator name for database adapter (name: $adapterName)!\r\n";
            return;
        }

        // --- processing ---

        // --- create database ---
        $dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter([ 'database' => $db, 'auto-connect' => false ]);
        if (is_null($dbAdapter)) {
            echo "Migration: Invalid database adapter (NULL)!\r\n";
            return;
        }

        $migrator = new $migratorName($dbAdapter);
        if (is_null($migrator)) {
            echo "Migration: Invalid database migrator (NULL)!\r\n";
            return;
        }

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
     * Проверка таблицы версий миграций\
     * @param array $dbNames
     * @return bool
     *
     * Если таблица не найдена, то она создается.
     */
    private function checkMigrationTables(array $dbNames): bool {
        // --- check ---
        foreach ($dbNames as $dbName) {
            if (!$this->checkMigrationTable($dbName))
                return false;
        }
        return true;
    }

    /**
     * Проверка таблицы версий миграций для конкретной БД
     * @param string $db
     * @return bool
     *
     * Если таблица не найдена, то она создается.
     */
    private function checkMigrationTable(string $db = ''): bool {
        $dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter([ 'database' => $db ]);
        if (is_null($dbAdapter))
            return false;
        $tables = $dbAdapter->tables();
        if (in_array('schema_migrations', $tables)
            || in_array('public.schema_migrations', $tables))
            return true;
        $sql = <<<EOT
        CREATE TABLE schema_migrations (
            version VARCHAR(128) NOT NULL,
            CONSTRAINT schema_migrations_pkey PRIMARY KEY (version)
        )
EOT;
        $res = $dbAdapter->queryTransaction($sql);
        return !is_null($res);
    }

    /**
     * Получить текущую версию установленных миграций
     * @param array $dbNames
     * @param bool $max - максимальная или минимальная
     * @return int
     */
    private function currentMigrationVersion(array $dbNames, bool $max = true): int {
        if (empty($dbNames) || !$this->checkMigrationTables($dbNames))
            return 0;

        // --- select ---
        $tmpVersion = 0;
        if (!$max)
            $tmpVersion = PHP_INT_MAX;

        foreach ($dbNames as $dbName) {
            $tmpVersionS = $this->lastMigrationVersion($dbName);
            if ($max && $tmpVersion < $tmpVersionS)
                $tmpVersion = $tmpVersionS;
            else if (!$max && $tmpVersion > $tmpVersionS)
                $tmpVersion = $tmpVersionS;
        }
        return $tmpVersion;
    }

    /**
     * Получить последнюю версию установленных миграций для конкретной БД
     * @param string $db
     * @return int
     */
    private function lastMigrationVersion(string $db = ''): int {
        if (!$this->checkMigrationTable($db))
            return 0;
        $dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter([ 'database' => $db ]);
        if (is_null($dbAdapter))
            return 0;
        $res = $dbAdapter->queryTransaction("SELECT version FROM schema_migrations ORDER BY version DESC LIMIT 1;");
        if (empty($res))
            return 0;
        return intval($res[0]->version);
    }

    /**
     * Получить все версии установленных миграций
     * @param array $dbNames
     * @return array
     */
    private function allInstallMigrationVersions(array $dbNames): array {
        if (empty($dbNames) || !$this->checkMigrationTables($dbNames))
            return [];

        // --- select ---
        $tmpVersions = [];
        foreach ($dbNames as $dbName)
            $tmpVersions = array_merge($tmpVersions, $this->installMigrationVersions($dbName));

        // --- sort ASC ---
        if (!ksort($tmpVersions, SORT_NUMERIC))
            return [];
        return $tmpVersions;
    }

    /**
     * Получить все версии установленных миграций для конкретной БД
     * @param string $db
     * @return array
     */
    private function installMigrationVersions(string $db = ''): array {
        if (!$this->checkMigrationTable($db))
            return [];
        $dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter([ 'database' => $db ]);
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
     * @param string $db
     * @param int $version
     * @param bool $showOutput
     */
    private function appendMigrationVersion(string $db, int $version, bool $showOutput = false) {
        if (!$this->checkMigrationTable($db))
            return;
        $dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter([ 'database' => $db ]);
        if (is_null($dbAdapter))
            return;
        $dbAdapter->setShowOutput($showOutput);
        $dbAdapter->setOutputDelimiter("\r\n");
        $dbAdapter->queryTransaction("INSERT INTO schema_migrations (version) VALUES ('$version');");
    }

    /**
     * Удалить версию миграции
     * @param string $db
     * @param int $version
     * @param bool $showOutput
     */
    private function removeMigrationVersion(string $db, int $version, bool $showOutput = false) {
        if (!$this->checkMigrationTable($db))
            return;
        $dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter([ 'database' => $db ]);
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
    private function loadPluginsIgnoreList(string $dir): array {
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