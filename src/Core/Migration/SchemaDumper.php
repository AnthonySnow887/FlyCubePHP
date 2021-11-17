<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 11.08.21
 * Time: 17:00
 */

namespace FlyCubePHP\Core\Migration;

include_once __DIR__.'/../Database/DatabaseFactory.php';
include_once 'BaseMigrator.php';

use \FlyCubePHP\Core\Database\DatabaseFactory as DatabaseFactory;

class SchemaDumper
{
    private $_dbAdapter = null;
    private $_migrator = null;

    public function __destruct() {
        unset($this->_migrator);
        unset($this->_dbAdapter);
    }

    /**
     * Выполнить дамп схемы базы данных
     * @param int $currentVersion
     * @param string $migratorClassName
     * @param bool $showOutput
     * @param string $outputDelimiter
     * @param string $dumpData
     * @return bool
     */
    final public function dump(int $currentVersion,
                               string $migratorClassName,
                               bool $showOutput,
                               string $outputDelimiter,
                               string &$dumpData): bool {
        // --- check caller function ---
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($dbt[1]['function']) ? $dbt[1]['function'] : null;
        if (is_null($caller))
            return false; // TODO throw new \RuntimeException('Migration: Not found caller function!);
        if (strcmp($caller, "schemaDump") !== 0)
            return false;

        // --- processing ---
        if (empty($migratorClassName))
            return false; // TODO throw new \RuntimeException('Migration: invalid database migrator class name!);

        $this->_dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($this->_dbAdapter))
            return false; // TODO throw new \RuntimeException('Migration: invalid database connector (NULL)!);

        $this->_migrator = new $migratorClassName($this->_dbAdapter);
        if (is_null($this->_migrator))
            return false; // TODO throw new \RuntimeException('Migration: invalid database migrator (NULL)!);

        $this->_dbAdapter->setShowOutput($showOutput);
        $this->_dbAdapter->setOutputDelimiter($outputDelimiter);
        $this->_dbAdapter->beginTransaction();

        $dumpData = "";
        $this->dumpHeader($dumpData);
        $this->dumpVersion($currentVersion, $dumpData);
        $this->dumpDataStart($dumpData);

        // --- dump extensions ---
        // TODO extensions for PostgreSQL...

        $schemaLst = [];
        $tables = $this->_dbAdapter->tables();
        // --- dump tables ---
        foreach ($tables as $table) {
            if (strcmp($table, "public.schema_migrations") === 0
                || strcmp($table, "schema_migrations") === 0)
                continue;
            $tableLst = explode('.', $table);
            if (count($tableLst) >= 2 && !in_array($tableLst[0], $schemaLst)) {
                $this->dumpSchema($tableLst[0], $dumpData);
                $schemaLst[] = $tableLst[0];
            }
            $this->dumpTable($table, $dumpData);
        }
        // --- dump tables f-keys ---
        foreach ($tables as $table) {
            if (strcmp($table, "public.schema_migrations") === 0
                || strcmp($table, "schema_migrations") === 0)
                continue;
            $this->dumpTableForeignKeys($table, $dumpData);
        }
        $this->dumpTrailer($dumpData, "    ");
        $this->dumpTrailer($dumpData);

        $this->_dbAdapter->rollBackTransaction();
        unset($this->_migrator);
        unset($this->_dbAdapter);
        return true;
    }

    /**
     * Добавить заголовок в дамп-файл
     * @param string $stream
     */
    private function dumpHeader(string &$stream) {
        $tmpData = <<<EOT
<?php
// This file is auto-generated from the current state of the database. Instead
// of editing this file, please use the migrations feature of Active Record to
// incrementally modify your database, and then regenerate this schema definition.
//
// Note that this Schema.php definition is the authoritative source for your
// database schema. If you need to create the application database on another
// system, you should be using 'fly_cube_php --db-schema-load', not running all 
// the migrations from scratch. The latter is a flawed and unsustainable approach 
// (the more migrations you'll amass, the slower it'll run and the greater likelihood 
// for issues).
//
// It's strongly recommended that you check this file into your version control system.
//
class Schema extends \FlyCubePHP\Core\Migration\BaseSchema
{

EOT;
    $stream .= $tmpData;
    }

    /**
     * Добавить версию миграции в дамп-файл
     * @param int $version
     * @param string $stream
     */
    private function dumpVersion(int $version, string &$stream) {
        $tmpData = <<<EOT
    public function __construct() {
        parent::__construct($version);
    }


EOT;
        $stream .= $tmpData;
    }

    /**
     * Добавить метод загрузки дампа в дамп-файл
     * @param string $stream
     */
    private function dumpDataStart(string &$stream) {
        $tmpData = <<<EOT
    final public function up() {

EOT;
        $stream .= $tmpData;
    }

    /**
     * Добавить закрывающий символ в дамп-файл
     * @param string $stream
     * @param string $prefix
     */
    private function dumpTrailer(string &$stream, string $prefix = "") {
        $stream .= "\r\n$prefix}";
    }

    /**
     * Добавить методо создания схемы базы данных в дамп-файл
     * @param string $name
     * @param string $stream
     */
    private function dumpSchema(string $name, string &$stream) {
        if (strcmp($name, "public") === 0)
            return;
        $stream .= "\r\n        \$this->createSchema('$name', [ 'if_not_exists' => true ]);";
    }

    /**
     * Добавить метод создания таблицы базы данных в дамп-файл
     * @param string $name
     * @param string $stream
     */
    private function dumpTable(string $name, string &$stream) {
        $tableLst = explode('.', $name);
        if (count($tableLst) >= 2 && strcmp($tableLst[0], "public") === 0)
            $name = $tableLst[1];

        $tColumns = $this->_migrator->tableColumns($name);
        $tIndexes = $this->_migrator->tableIndexes($name);
        $tPKeys = $this->_migrator->tablePrimaryKeys($name);
        $tmpData = "\r\n        \$this->createTable('$name', [";
        if (!array_key_exists('id', $tColumns))
            $tmpData .= "\r\n            'id' => false,";
        foreach ($tColumns as $column) {
            $cName = $column['column'];
            $cType = $column['type'];
            $cIsNull = $this->boolToStr(!$column['is_not_null']);
            $cIsPk = $this->boolToStr($column['is_pk']);
            $cDefault = $column['default'];

            $tmpData .= "\r\n            '$cName' => [ 'type' => '$cType', 'null' => $cIsNull, 'primary_key' => $cIsPk";
            if (!is_null($cDefault)) {
                if (preg_match("/\'(.*)\'.*/", $cDefault, $matches, PREG_OFFSET_CAPTURE)) {
                    if (count($matches) >= 2)
                        $cDefault = "'" . $matches[1][0] . "'";
                } elseif (preg_match("/(.*)\(.*\)/", $cDefault, $matches, PREG_OFFSET_CAPTURE)) {
                    if (count($matches) >= 2)
                        $cDefault = "'" . $matches[0][0] . "'";
                }
                $tmpData .= ", 'default' => $cDefault ],";
            } else {
                $tmpData .= " ],";
            }
        }
        $tmpData = substr($tmpData, 0, strlen($tmpData) - 1); // remove last symbol ','
        $tmpData .= "\r\n        ]);";

        foreach ($tIndexes as $tIndex) {
            $iName = $tIndex['index_name'];
            if (strpos($iName, "sqlite_autoindex") !== false)
                $iName = "";
            $iColumns = $tIndex['columns'];
            $iUnique = $this->boolToStr($tIndex['unique']);
            $strColumns = "";
            if (array_key_exists($iName, $tPKeys))
                continue;
            foreach ($iColumns as $c) {
                if (empty($strColumns))
                    $strColumns = "'$c'";
                else
                    $strColumns .= ", '$c'";
            }
            if (empty($iName))
                $tmpData .= "\r\n        \$this->addIndex('$name', [ $strColumns ], [ 'unique' => $iUnique ]);";
            else
                $tmpData .= "\r\n        \$this->addIndex('$name', [ $strColumns ], [ 'name' => '$iName', 'unique' => $iUnique ]);";
        }
        $stream .= $tmpData;
    }

    /**
     * Добавить метод создания вторичных ключей таблицы в дамп-файл
     * @param string $name
     * @param string $stream
     */
    private function dumpTableForeignKeys(string $name, string &$stream) {
        $tableLst = explode('.', $name);
        if (count($tableLst) >= 2 && strcmp($tableLst[0], "public") === 0)
            $name = $tableLst[1];

        $tfKeys = $this->_migrator->tableForeignKeys($name);
        $tmpData = "";
        foreach ($tfKeys as $tfKey) {
            $fName = $tfKey['name'];
            $fTable = $tfKey['table'];
            $tableLst = explode('.', $fTable);
            if (count($tableLst) >= 2 && strcmp($tableLst[0], "public") === 0)
                $fTable = $tableLst[1];

            $fColumn = $tfKey['column'];
            $fTableRef = $tfKey['ref_table'];
            $tableLst = explode('.', $fTableRef);
            if (count($tableLst) >= 2 && strcmp($tableLst[0], "public") === 0)
                $fTableRef = $tableLst[1];

            $fColumnRef = $tfKey['ref_column'];
            $fOnUpdate = $tfKey['on_update'];
            $fOnDelete = $tfKey['on_delete'];

            $tmpData .= "\r\n        \$this->addForeignKey('$fTable', [ '$fColumn' ], '$fTableRef', [ '$fColumnRef' ], [";
            $tmpData .= "\r\n            'name' => '$fName',";
            if (strcmp(trim(strtoupper($fOnDelete)), "NO ACTION") === 0)
                $tmpData .= "\r\n            'on_delete' => false,";
            else
                $tmpData .= "\r\n            'on_delete' => true,\r\n            'action' => '$fOnDelete',";

            if (strcmp(trim(strtoupper($fOnUpdate)), "NO ACTION") === 0)
                $tmpData .= "\r\n            'on_update' => false";
            else
                $tmpData .= "\r\n            'on_update' => true,\r\n            'action' => '$fOnDelete'";

            $tmpData .= "\r\n        ]);";
        }
        $stream .= $tmpData;
    }

    /**
     * Метод конвертации bool в строку
     * @param bool $val
     * @return string
     */
    private function boolToStr(bool $val): string {
        return ($val === true) ? "true" : "false";
    }
}