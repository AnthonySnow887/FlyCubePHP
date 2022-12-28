<?php

namespace FlyCubePHP\Core\Migration;

include_once 'BaseMigrator.php';
include_once __DIR__.'/../../HelperClasses/CoreHelper.php';

use FlyCubePHP\HelperClasses\CoreHelper;

class MySQLMigrator extends BaseMigrator
{
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
            throw new \RuntimeException('Migration::MySQLMigrator -> createDatabase: invalid database name!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::MySQLMigrator -> createDatabase: invalid database adapter (NULL)!');
        $strOptions = "";
        if (isset($props['collation']))
            $strOptions = "DEFAULT COLLATE ".$props['collation'];
        else if (isset($props['charset']))
            $strOptions = "DEFAULT CHARACTER SET ".$props['charset'];
        else
            $strOptions = "DEFAULT CHARACTER SET utf8";

        $this->_dbAdapter->query("CREATE DATABASE ".$this->_dbAdapter->quoteTableName($name)." $strOptions;");
    }

    /**
     * Удалить базу данных
     * @param string $name - название базы данных
     */
    public function dropDatabase(string $name) {
        if (empty($name))
            throw new \RuntimeException('Migration::MySQLMigrator -> dropDatabase: invalid database name!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::MySQLMigrator -> dropDatabase: invalid database adapter (NULL)!');

        $this->_dbAdapter->query("DROP DATABASE IF EXISTS ".$this->_dbAdapter->quoteTableName($name).";");
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
            return [];

        // --- select table information ---
        $dbName = $this->_dbAdapter->database();
        $res = $this->_dbAdapter->query("SHOW INDEX FROM ".$this->_dbAdapter->quoteTableName($table)." FROM ".$this->_dbAdapter->quoteTableName($dbName).";");
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
            return [];
        // --- select table information ---
        $res = $this->_dbAdapter->query("SHOW COLUMNS FROM ".$this->_dbAdapter->quoteTableName($table).";");
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
            return [];
        // --- select table sql ---
        $res = $this->_dbAdapter->query("SHOW KEYS FROM ".$this->_dbAdapter->quoteTableName($table)." WHERE Key_name = 'PRIMARY';");
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
            return [];
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

    /**
     * Переименовать колонку в таблице
     * @param string $table - название таблицы
     * @param string $column - название колонки
     * @param string $newName - новое название колонки
     */
    public function renameColumn(string $table, string $column, string $newName) {
        if (empty($table) || empty($column) || empty($newName))
            throw new \RuntimeException('Migration::MySQLMigrator -> renameColumn: invalid table name or column name or new column name!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::MySQLMigrator -> renameColumn: invalid database adapter (NULL)!');
        $tmpIdexes = $this->tableIndexes($table);
        $tmpColumns = $this->tableColumns($table);
        if (!isset($tmpColumns[$column]))
            throw new \RuntimeException("Migration::MySQLMigrator -> renameColumn: not found column \"$column\" in table \"$table\"!");
        $tmpTable = $this->_dbAdapter->quoteTableName($table);
        $tmpColumn = $this->_dbAdapter->quoteTableName($column);
        $tmpNewName = $this->_dbAdapter->quoteTableName($newName);
        $colType = $tmpColumns[$column]['type'];
        $colIsNotNull = "";
        if ($tmpColumns[$column]['is_not_null'])
            $colIsNotNull = "NOT NULL";
        $colDefault = "";
        if (!empty($tmpColumns[$column]['default']))
            $colDefault = $this->makeDefaultValue($tmpColumns[$column]['default'], $colType);

        $this->_dbAdapter->query("ALTER TABLE $tmpTable CHANGE $tmpColumn $tmpNewName $colType $colIsNotNull $colDefault;");
        foreach ($tmpIdexes as $info) {
            if (!in_array($column, $info['columns']))
                continue;
            $indexNewName = $table . "_" . $newName . "_index";
            $this->renameIndex($table, $info['index_name'], $indexNewName);
        }
    }

    /**
     * Изменить тип колонки и ее дополнительные параметры, если они заданы
     * @param string $table - название таблицы
     * @param string $column - название колонки
     * @param string $type - новый тип данных
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [integer]  limit          - размер данных колонки
     * [bool]     null           - может ли быть NULL
     * [string]   default        - базовое значение
     */
    public function changeColumn(string $table, string $column, string $type, array $props = []) {
        if (empty($table) || empty($column) || empty($type))
            throw new \RuntimeException('Migration::MySQLMigrator -> changeColumn: invalid table name or column name or column type!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::MySQLMigrator -> changeColumn: invalid database adapter (NULL)!');

        $table = $this->_dbAdapter->quoteTableName($table);

        // --- drop default ---
        $sql = "ALTER TABLE $table ALTER COLUMN ".$this->_dbAdapter->quoteTableName($column)." DROP DEFAULT;";
        $this->_dbAdapter->query($sql);

        // --- change type ---
        $tmpLimit = null;
        if (isset($props['limit']))
            $tmpLimit = intval($props['limit']);
        $tmpType = $this->toDatabaseType($type, $tmpLimit);
        $tmpTypeUsing = $tmpType;

        $colIsNotNull = "";
        if (isset($props['null']) && $props['null'] === false)
            $colIsNotNull = "NOT NULL";
        $colDefault = "";
        if (isset($props['default']) && !empty($props['default']))
            $colDefault = $this->makeDefaultValue($props['default'], $tmpType);

        $sql = "ALTER TABLE $table MODIFY ".$this->_dbAdapter->quoteTableName($column)." $tmpType $colIsNotNull $colDefault;";
        $this->_dbAdapter->query($sql);
    }

    /**
     * Добавить/Удалить секцию NOT NULL у колонки
     * @param string $table - название таблицы
     * @param string $column - название колонки
     * @param bool $notNull - значение секции (если false - секция NOT NULL удаляется)
     */
    public function changeColumnNull(string $table, string $column, $notNull = false) {
        if (empty($table) || empty($column))
            throw new \RuntimeException('Migration::MySQLMigrator -> changeColumnNull: invalid table name or column name!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::MySQLMigrator -> changeColumnNull: invalid database adapter (NULL)!');
        $tmpColumns = $this->tableColumns($table);
        if (!isset($tmpColumns[$column]))
            throw new \RuntimeException("Migration::MySQLMigrator -> changeColumnNull: not found column \"$column\" in table \"$table\"!");
        $tmpTable = $this->_dbAdapter->quoteTableName($table);
        $tmpColumn = $this->_dbAdapter->quoteTableName($column);
        $colType = $tmpColumns[$column]['type'];
        $colIsNotNull = "";
        if ($notNull === true)
            $colIsNotNull = "NOT NULL";

        $colDefault = "";
        if (!empty($tmpColumns[$column]['default']))
            $colDefault = $this->makeDefaultValue($tmpColumns[$column]['default'], $colType);

        $this->_dbAdapter->query("ALTER TABLE $tmpTable MODIFY $tmpColumn $colType $colIsNotNull $colDefault;");
    }

    /**
     * Переименовать индекс для таблицы
     * @param string $table - название таблицы
     * @param string $oldName - старое название
     * @param string $newName - новое название
     */
    public function renameIndex(string $table, string $oldName, string $newName) {
        if (empty($table) || empty($oldName) || empty($newName))
            throw new \RuntimeException('Migration::MySQLMigrator -> renameIndex: invalid table name or old name or new name!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::MySQLMigrator -> renameIndex: invalid database connector (NULL)!');
        if (strcmp($oldName, $newName) === 0)
            throw new \RuntimeException('Migration::MySQLMigrator -> renameIndex: old name is the same as new name!');
        $tIndexes = $this->tableIndexes($table);
        if (empty($tIndexes) || !isset($tIndexes[$oldName]))
            throw new \RuntimeException("Migration::MySQLMigrator -> renameIndex: not found old index name \"$oldName\" for table \"$table\"!");
        $this->dropIndex($table, [ 'name' => $oldName ]);
        $this->addIndex($table, $tIndexes[$oldName]['columns'], [ 'name' => $newName, 'unique' => $tIndexes[$oldName]['unique']]);
    }

    /**
     * Удалить индекс у таблицы
     * @param array $args
     */
    protected function dropIndexProtected(array $args) {
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::MySQLMigrator -> dropIndexProtected: invalid database adapter (NULL)!');
        if (empty($args))
            throw new \RuntimeException('Migration::MySQLMigrator -> dropIndexProtected: invalid args (Empty)!');
        if (!isset($args['table']))
            throw new \RuntimeException('Migration::MySQLMigrator -> dropIndexProtected: not found \'table\' in args!');
        if (!isset($args['columns']) && !isset($args['name']))
            throw new \RuntimeException('Migration::MySQLMigrator -> dropIndexProtected: not found \'columns\' and \'name\' in args!');
        $table = $args['table'];
        $tmpName = "";
        if (isset($args['columns'])) {
            $columns = array_filter($args['columns'], 'strlen');
            if (empty($columns))
                throw new \RuntimeException('Migration::MySQLMigrator -> dropIndexProtected: empty \'columns\' in args!');
            $tableLst = explode('.', $table);
            $tmpName = $tableLst[count($tableLst) - 1] . "_" . implode('_', $columns) . "_index";
        }
        if (isset($args['name']))
            $tmpName = $args['name'];

        if (isset($args['if_exists']) && $args['if_exists'] === true) {
            $tIndexes = $this->tableIndexes($table);
            if (!isset($tIndexes[$tmpName]))
                return; // not found -> ok -> exit
        }
        $table = $this->_dbAdapter->quoteTableName($table);
        $tmpName = $this->_dbAdapter->quoteTableName($tmpName);
        $this->_dbAdapter->query("DROP INDEX $tmpName ON $table;");
    }

    /**
     * Удалить вторичный ключ таблицы
     * @param string $table - название таблицы
     * @param array $columns - названия колонок
     */
    public function dropForeignKey(string $table, array $columns) {
        if (empty($table) || empty($columns))
            throw new \RuntimeException('Migration::MySQLMigrator -> dropForeignKey: invalid input arguments!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::MySQLMigrator -> dropForeignKey: invalid database adapter (NULL)!');
        $columns = array_filter($columns,'strlen');
        if (empty($columns))
            throw new \RuntimeException('Migration::MySQLMigrator -> dropForeignKey: invalid columns (Empty)!');
        // --- select f-keys list ---
        $tmpForeignKeysLst = $this->tableForeignKeys($table);
        $columnNames = implode(', ', $columns);
        $tmpName = "";
        foreach ($tmpForeignKeysLst as $fk) {
            if (strcmp($fk['column'], $columnNames) === 0) {
                $tmpName = $fk['name'];
                break;
            }
        }
        if (empty($tmpName))
            throw new \RuntimeException("Migration::MySQLMigrator -> dropForeignKey: not found foreign key name for table \"$table\" and columns (\"$columnNames\")!");
        $tmpTable = $this->_dbAdapter->quoteTableName($table);
        $this->_dbAdapter->query("ALTER TABLE $tmpTable DROP CONSTRAINT ".$this->_dbAdapter->quoteTableName($tmpName).";");
        $this->dropIndex($table, [ 'name' => $tmpName, 'if_exists' => true ]);
    }
}