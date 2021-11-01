<?php

namespace FlyCubePHP\Core\Migration;

include_once 'BaseMigrator.php';
include_once __DIR__.'/../../HelperClasses/CoreHelper.php';

use FlyCubePHP\Core\Database\BaseDatabaseAdapter;
use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;

class MySQLMigrator extends BaseMigrator
{
    // TODO delete after tests!!!
    function __construct(BaseDatabaseAdapter &$_dbAdapter)
    {
        parent::__construct($_dbAdapter);
//        var_dump($this->tableIndexes('test'));
//        var_dump($this->tableColumns('test'));
//        var_dump($this->tablePrimaryKeys('test'));
//        var_dump($this->tableForeignKeys('child'));
    }

    /**
     * Создать новую базу данных
     * @param string $name - название базы данных
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [string] collation       - Set flag 'COLLATE'
     * [string] charset         - Set flag 'CHARACTER'
     */
    public function createDatabase(string $name, array $props = []) {
        if (empty($name))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> createDatabase: invalid database name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> createDatabase: invalid database connector (NULL)!');
        $strOptions = "";
        if (isset($props['collation']))
            $strOptions = "DEFAULT COLLATE ".$props['collation'];
        else if (isset($props['charset']))
            $strOptions = "DEFAULT CHARACTER SET ".$props['charset'];
        else
            $strOptions = "DEFAULT CHARACTER SET utf8";

        $res = $this->_dbAdapter->query("CREATE DATABASE $name $strOptions;");
        if (is_null($res))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> createDatabase: invalid result (NULL)!');
    }

    /**
     * Удалить базу данных
     * @param string $name - название базы данных
     */
    public function dropDatabase(string $name) {
        if (empty($name))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> dropDatabase: invalid database name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> dropDatabase: invalid database connector (NULL)!');

        $res = $this->_dbAdapter->query("DROP DATABASE IF EXISTS $name;");
        if (is_null($res))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> dropDatabase: invalid result (NULL)!');
    }

    /**
     * Запросить список индексов для таблицы
     * @param string $table - название таблицы
     * @return array
     *
     * Return example:
     *
     * [
     *   'index_1' => [
     *                  'index_name' => 'index_1',
     *                  'unique' => true,
     *                  'table' => 'test_table',
     *                  'columns' => [ 0 => 'column_1', 1 => 'column_2']
     *                ]
     *   'index_2' => [
     *                  'index_name' => 'index_2',
     *                  'unique' => false,
     *                  'table' => 'test_table',
     *                  'columns' => [ 0 => 'column_2' ]
     *                ]
     *   ...
     * ]
     */
    final public function tableIndexes(string $table)
    {
        if (is_null($this->_dbAdapter))
            return []; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> tableIndexes: invalid database connector (NULL)!');

        // --- select table information ---
        $dbName = $this->_dbAdapter->database();
        $res = $this->_dbAdapter->query("SHOW INDEX FROM $table FROM $dbName;");
        if (empty($res))
            return [];
        $tmpIndexes = [];
        foreach ($res as $r) {
            $iName = $r->Key_name;
            $unique = !CoreHelper::toBool($r->Non_unique);
            $iColumns = [];
            if (isset($tmpIndexes[$iName]))
                $iColumns = $tmpIndexes[$iName]['columns'];
            $iColumns[] = $r->Column_name;

            $tmpIndexes[$iName] = [
                'index_name' => $iName,
                'unique' => $unique,
                'table' => $r->Table,
                'columns' => $iColumns
            ];
        }
        return $tmpIndexes;
    }

    /**
     * Запросить список колонок таблицы и их типов
     * @param string $table - название таблицы
     * @return array
     *
     * Return example:
     *
     * [
     *   'column_1' => [ 'table' => 'test_table',
     *                   'column' => 'column_1',
     *                   'type' => 'text',
     *                   'is_pk' => true,
     *                   'is_not_null' => true
     *                   'default' => null
     *                 ]
     *   'column_2' => [ 'table' => 'test_table',
     *                   'column' => 'column_2',
     *                   'type' => 'integer'
     *                   'is_pk' => false
     *                   'is_not_null' => false
     *                   'default' => 999
     *                 ]
     *   ...
     * ]
     */
    final public function tableColumns(string $table) {
        if (is_null($this->_dbAdapter))
            return null; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> tableColumns: invalid database connector (NULL)!');
        // --- select table information ---
        $res = $this->_dbAdapter->query("SHOW COLUMNS FROM $table;");
        if (empty($res))
            return [];
        $tmpColumns = [];
        foreach ($res as $r) {
            $isNotNull = false;
            if (strcmp(strtolower($r->Null), 'no') === 0)
                $isNotNull = true;
            $isPKey = false;
            if (strcmp(strtolower($r->Key), 'pri') === 0)
                $isPKey = true;

            $tmpColumns[$r->Field] = [
                'table' => $table,
                'column' => $r->Field,
                'type' => $r->Type,
                'is_pk' => $isPKey,
                'is_not_null' => $isNotNull,
                'default' => $r->Default
            ];
        }
        return $tmpColumns;
    }

    /**
     * Запросить список первичных ключей таблицы
     * @param string $table
     * @return array|null
     *
     * Return example:
     *
     * [
     *   'test_pkey' => [ 'name' => 'test_pkey'
     *                    'table' => 'public.test',
     *                    'column' => 'my_id',
     *                    'type' => 'integer'
     *                  ]
     * ]
     */
    final public function tablePrimaryKeys(string $table) {
        if (is_null($this->_dbAdapter))
            return null; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> tableColumns: invalid database connector (NULL)!');
        // --- select table sql ---
        $res = $this->_dbAdapter->query("SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY';");
        if (empty($res))
            return [];
        $tmpColumns = [];
        foreach ($res as $r) {
//            if (CoreHelper::toBool($r->pk) === false)
//                continue;
//            $tmpName = "";
//            $columnName = $r->name;
//            $reg = "\bCONSTRAINT \"?([A-Za-z0-9_]+\_pkey)\"? PRIMARY KEY \($columnName\)";
//            preg_match("/$reg/", $tSql, $matches, PREG_OFFSET_CAPTURE);
//            if (count($matches) >= 2)
//                $tmpName = trim($matches[1][0]);
//            if (empty($tmpName))
//                $tmpName = $table."_pkey";

            $tColumns = $this->tableColumns($table);
            if (!isset($tColumns[$r->Column_name]))
                continue;
            $tmpColumns[$r->Key_name] = [
                'name' => $r->Key_name,
                'table' => $table,
                'column' => $r->Column_name,
                'type' => $tColumns[$r->Column_name]['type']
            ];
        }
        return $tmpColumns;
    }

    /**
     * Запросить список вторичных ключей для таблицы
     * @param string $table - название таблицы
     * @return array|null
     *
     * Return example:
     *
     * [
     *   'fk_test_2_test_id' => [ 'name' => 'fk_test_2_test_id',
     *                            'table' => 'public.test_2',
     *                            'column' => 'test_id',
     *                            'ref_table' => 'public.test',
     *                            'ref_column' => 'my_id'
     *                            'on_update' => 'NO ACTION',
     *                            'on_delete' => 'NO ACTION'
     *                          ]
     *   ...
     * ]
     */
    final public function tableForeignKeys(string $table) {
        if (is_null($this->_dbAdapter))
            return null; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> tableColumns: invalid database connector (NULL)!');
        // --- select table sql ---
        $dbName = $this->_dbAdapter->database();
        $sql = <<<EOT
        SELECT fk.referenced_table_name AS 'to_table',
               fk.referenced_column_name AS 'primary_key',
               fk.column_name AS 'column',
               fk.constraint_name AS 'name',
               rc.update_rule AS 'on_update',
               rc.delete_rule AS 'on_delete'
        FROM information_schema.referential_constraints rc
        JOIN information_schema.key_column_usage fk
        USING (constraint_schema, constraint_name)
        WHERE fk.referenced_column_name IS NOT NULL
        AND fk.table_schema = '$dbName'
        AND fk.table_name = '$table'
        AND rc.constraint_schema = '$dbName'
        AND rc.table_name = '$table'
EOT;
        // --- select table information ---
        $res = $this->_dbAdapter->query($sql);
        if (empty($res))
            return [];
        $tmpList = [];
        foreach ($res as $r) {
            $tmpList[$r->name] = [
                'name' => $r->name,
                'table' => $table,
                'column' => $r->column,
                'ref_table' => $r->to_table,
                'ref_column' => $r->primary_key,
                'on_update' => $r->on_update,
                'on_delete' => $r->on_delete
            ];
        }
        return $tmpList;
    }
}