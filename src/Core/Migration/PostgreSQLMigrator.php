<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 03.08.21
 * Time: 16:30
 */

namespace FlyCubePHP\Core\Migration;

use FlyCubePHP\HelperClasses\CoreHelper;

include_once 'BaseMigrator.php';

class PostgreSQLMigrator extends BaseMigrator
{
    /**
     * Создать новую базу данных
     * @param string $name - название базы данных
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [string] encoding        - Set flag 'ENCODING' (default: utf8)
     * [string] owner           - Set flag 'OWNER'
     * [string] template        - Set flag 'TEMPLATE'
     * [string] collation       - Set flag 'LC_COLLATE'
     * [string] ctype           - Set flag 'LC_CTYPE'
     * [string] tablespace      - Set flag 'TABLESPACE'
     * [int] connection_limit   - Set flag 'CONNECTION LIMIT'
     */
    public function createDatabase(string $name, array $props = []) {
        if (empty($name))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> createDatabase: invalid database name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> createDatabase: invalid database connector (NULL)!');
        if (!isset($props['encoding']))
            $props['encoding'] = "utf8";

        $strOptions = "";
        if (isset($props['owner']) && !empty($props['owner'])) {
            $val = $props['owner'];
            $strOptions .= " OWNER = \"$val\"";
        }
        if (isset($props['template']) && !empty($props['template'])) {
            $val = $props['template'];
            $strOptions .= " TEMPLATE = \"$val\"";
        }
        if (isset($props['encoding']) && !empty($props['encoding'])) {
            $val = $props['encoding'];
            $strOptions .= " ENCODING = '$val'";
        }
        if (isset($props['collation']) && !empty($props['collation'])) {
            $val = $props['collation'];
            $strOptions .= " LC_COLLATE = '$val'";
        }
        if (isset($props['ctype']) && !empty($props['ctype'])) {
            $val = $props['ctype'];
            $strOptions .= " LC_CTYPE = '$val'";
        }
        if (isset($props['tablespace']) && !empty($props['tablespace'])) {
            $val = $props['tablespace'];
            $strOptions .= " TABLESPACE = \"$val\"";
        }
        if (isset($props['connection_limit'])) {
            $val = intval($props['connection_limit']);
            $strOptions .= " CONNECTION LIMIT = $val";
        }
        $res = $this->_dbAdapter->query("CREATE DATABASE \"$name\" $strOptions;");
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

        $res = $this->_dbAdapter->query("DROP DATABASE IF EXISTS \"$name\";");
        if (is_null($res))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> dropDatabase: invalid result (NULL)!');
    }

    /**
     * Подключить расширение базы данных
     * @param string $name - имя
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [bool] if_not_exists - добавить флаг 'IF NOT EXISTS'
     *
     * NOTE: override this method for correct implementation.
     */
    public function createExtension(string $name, array $props = []) {
        if (empty($name))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> createExtension: invalid extension name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> createExtension: invalid database connector (NULL)!');
        $ifNotExists = "";
        if (isset($props['if_not_exists']) && $props['if_not_exists'] === true)
            $ifNotExists = "IF NOT EXISTS";

        $this->_dbAdapter->query("CREATE EXTENSION $ifNotExists \"$name\";");
    }

    /**
     * Удалить расширение базы данных
     * @param string $name - имя
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [bool] if_exists - добавить флаг 'IF EXISTS'
     *
     * NOTE: override this method for correct implementation.
     */
    public function dropExtension(string $name, array $props = []) {
        if (empty($name))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> dropExtension: invalid extension name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> dropExtension: invalid database connector (NULL)!');
        $if_exists = "";
        if (isset($props['if_exists']) && $props['if_exists'] === true)
            $if_exists .= "IF EXISTS";
        $this->_dbAdapter->query("DROP EXTENSION $if_exists \"$name\" CASCADE;");
    }

    /**
     * Создать новую схему данных
     * @param string $name - имя
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [bool] if_not_exists - добавить флаг 'IF NOT EXISTS'
     *
     * NOTE: override this method for correct implementation.
     */
    final public function createSchema(string $name, array $props = []) {
        if (empty($name))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> createSchema: invalid scheme name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> createSchema: invalid database connector (NULL)!');
        $ifNotExists = "";
        if (isset($props['if_not_exists']) && $props['if_not_exists'] === true)
            $ifNotExists = "IF NOT EXISTS";

        $this->_dbAdapter->query("CREATE SCHEMA $ifNotExists \"$name\";");
    }

    /**
     * Удалить схему данных
     * @param string $name - имя
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [bool] if_exists - добавить флаг 'IF EXISTS'
     */
    final public function dropSchema(string $name, array $props = []) {
        if (empty($name))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> dropSchema: invalid scheme name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> dropSchema: invalid database connector (NULL)!');
        $if_exists = "";
        if (isset($props['if_exists']) && $props['if_exists'] === true)
            $if_exists .= "IF EXISTS";
        $this->_dbAdapter->query("DROP SCHEMA $if_exists \"$name\" CASCADE;");
    }

    /**
     * Удалить таблицу
     * @param string $name - название
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [bool] if_exists - добавить флаг 'IF EXISTS'
     * [bool] cascade   - добавить флаг 'CASCADE'
     */
    final public function dropTable(string $name, array $props = []) {
        if (empty($name))
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> dropTable: invalid table name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> dropTable: invalid database connector (NULL)!');
        $name = $this->_dbAdapter->quoteTableName($name);
        $if_exists = "";
        if (isset($props['if_exists']) && $props['if_exists'] === true)
            $if_exists .= "IF EXISTS";
        $cascade = "";
        if (isset($props['cascade']) && $props['cascade'] === true)
            $cascade = "CASCADE";
        $this->_dbAdapter->query("DROP TABLE $if_exists $name $cascade;");
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
        $tableLst = explode('.', $table);
        $sql = "";
        if (count($tableLst) === 1)
            $sql = "SELECT indexname, indexdef FROM pg_indexes WHERE tablename = '$table';";
        else if (count($tableLst) === 2)
            $sql = "SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = '$tableLst[0]' AND tablename = '$tableLst[1]';";

        $res = $this->_dbAdapter->query($sql);
        if (empty($res))
            return [];
        $tmpIndexes = [];
        foreach ($res as $r) {
            $info = $this->parseTableIndex($table, $r->indexname, $r->indexdef);
            if (!is_null($info))
                $tmpIndexes[$info['index_name']] = $info;
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
            return null; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> tableColumns: invalid database connector (NULL)!');
        // --- select table information ---
        $tableLst = explode('.', $table);
        $sql = "";
        if (count($tableLst) === 1) {
            $sql = <<<EOT
            SELECT table_schema, table_name, column_name, column_default, is_nullable, data_type, character_maximum_length 
            FROM information_schema.columns 
            WHERE table_name = '$table';
EOT;
        } elseif (count($tableLst) === 2) {
            $sql = <<<EOT
            SELECT table_schema, table_name, column_name, column_default, is_nullable, data_type, character_maximum_length 
            FROM information_schema.columns 
            WHERE table_schema = '$tableLst[0]' AND table_name = '$tableLst[1]';
EOT;
        } else {
            return null; // invalid name!
        }
        $tmpPKeys = $this->tablePrimaryKeys($table);
        $res = $this->_dbAdapter->query($sql);
        if (empty($res))
            return [];
        $tmpColumns = [];
        foreach ($res as $r) {
            $is_pk = $this->columnIsPKey($r->column_name, $tmpPKeys);
            $is_notnull = false;
            if (strcmp(strtolower($r->is_nullable), 'no') === 0)
                $is_notnull = true;
            $tmpColumns[$r->column_name] = [
                'table' => "$r->table_schema.$r->table_name",
                'column' => $r->column_name,
                'type' => $this->toDatabaseType($r->data_type, $r->character_maximum_length),
                'is_pk' => $is_pk,
                'is_not_null' => $is_notnull,
                'default' => $r->column_default
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
            return null; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> tablePrimaryKeys: invalid database connector (NULL)!');
        // --- select table information ---
        $tableLst = explode('.', $table);
        $sql = "";
        if (count($tableLst) === 1) {
// TODO: Old sql -> delete...
//            $sql = <<<EOT
//            SELECT
//              pg_namespace.nspname,
//              pg_class.relname,
//              pg_attribute.attname,
//              format_type(pg_attribute.atttypid, pg_attribute.atttypmod) AS data_type
//            FROM pg_index, pg_class, pg_attribute, pg_namespace
//            WHERE
//              pg_class.oid = '$table'::regclass AND
//              indrelid = pg_class.oid AND
//              pg_class.relnamespace = pg_namespace.oid AND
//              pg_attribute.attrelid = pg_class.oid AND
//              pg_attribute.attnum = any(pg_index.indkey)
//            AND indisprimary
//            EOT;
            $sql = <<<EOT
            SELECT
              pg_namespace.nspname,      
              pg_class.relname,
              pg_attribute.attname, 
              format_type(pg_attribute.atttypid, pg_attribute.atttypmod) AS data_type,
              tc.constraint_name
            FROM pg_index, pg_class, pg_attribute, pg_namespace
            JOIN information_schema.table_constraints AS tc 
            ON tc.table_schema = pg_namespace.nspname
            AND tc.table_name = '$table'
            AND tc.constraint_type = 'PRIMARY KEY'
            WHERE pg_class.oid = '$table'::regclass
            AND indrelid = pg_class.oid 
            AND pg_class.relnamespace = pg_namespace.oid
            AND pg_attribute.attrelid = pg_class.oid 
            AND pg_attribute.attnum = any(pg_index.indkey)
            AND indisprimary
EOT;

        } elseif (count($tableLst) === 2) {
// TODO: Old sql -> delete...
//            $sql = <<<EOT
//            SELECT
//              pg_namespace.nspname,
//              pg_class.relname,
//              pg_attribute.attname,
//              format_type(pg_attribute.atttypid, pg_attribute.atttypmod) AS data_type
//            FROM pg_index, pg_class, pg_attribute, pg_namespace
//            WHERE
//              pg_class.oid = '$tableLst[1]'::regclass AND
//              indrelid = pg_class.oid AND
//              nspname = '$tableLst[0]' AND
//              pg_class.relnamespace = pg_namespace.oid AND
//              pg_attribute.attrelid = pg_class.oid AND
//              pg_attribute.attnum = any(pg_index.indkey)
//            AND indisprimary
//EOT;
            $sql = <<<EOT
            SELECT
              pg_namespace.nspname,      
              pg_class.relname,
              pg_attribute.attname, 
              format_type(pg_attribute.atttypid, pg_attribute.atttypmod) AS data_type,
              tc.constraint_name
            FROM pg_index, pg_class, pg_attribute, pg_namespace
            JOIN information_schema.table_constraints AS tc 
            ON tc.table_schema = pg_namespace.nspname
            AND tc.table_schema = '$tableLst[0]'
            AND tc.table_name = '$tableLst[1]'
            AND tc.constraint_type = 'PRIMARY KEY'
            WHERE pg_class.oid = '$tableLst[0].$tableLst[1]'::regclass
            AND indrelid = pg_class.oid 
            AND pg_class.relnamespace = pg_namespace.oid
            AND pg_attribute.attrelid = pg_class.oid 
            AND pg_attribute.attnum = any(pg_index.indkey)
            AND indisprimary
EOT;
        } else {
            return []; // invalid name!
        }

        $res = $this->_dbAdapter->query($sql);
        if (empty($res))
            return [];
        $tmpList = [];
        foreach ($res as $r)
            $tmpList[$r->constraint_name] = [
                'name' => $r->constraint_name,
                'table' => "$r->nspname.$r->relname",
                'column' => $r->attname,
                'type' => $r->data_type
            ];
        return $tmpList;
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
            return null; // TODO throw new \RuntimeException('Migration::PostgreSQLMigrator -> tableForeignKeys: invalid database connector (NULL)!');
        // --- select table information ---
        $tableLst = explode('.', $table);
        $sql = "";
        if (count($tableLst) === 1) {
            $sql = <<<EOT
            SELECT
              tc.table_schema, 
              tc.constraint_name, 
              tc.table_name, 
              kcu.column_name, 
              ccu.table_schema AS foreign_table_schema,
              ccu.table_name AS foreign_table_name,
              ccu.column_name AS foreign_column_name,
              rc.update_rule AS on_update,
              rc.delete_rule AS on_delete
            FROM 
              information_schema.table_constraints AS tc 
            JOIN information_schema.key_column_usage AS kcu
            ON tc.constraint_name = kcu.constraint_name
            AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage AS ccu
            ON ccu.constraint_name = tc.constraint_name
            AND ccu.table_schema = tc.table_schema
            JOIN information_schema.referential_constraints rc
            ON tc.constraint_catalog = rc.constraint_catalog
            AND tc.constraint_schema = rc.constraint_schema
            AND tc.constraint_name = rc.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_name = '$table';
EOT;
        } elseif (count($tableLst) === 2) {
            $sql = <<<EOT
            SELECT
              tc.table_schema, 
              tc.constraint_name, 
              tc.table_name, 
              kcu.column_name, 
              ccu.table_schema AS foreign_table_schema,
              ccu.table_name AS foreign_table_name,
              ccu.column_name AS foreign_column_name,
              rc.update_rule AS on_update,
              rc.delete_rule AS on_delete
            FROM 
              information_schema.table_constraints AS tc 
            JOIN information_schema.key_column_usage AS kcu
            ON tc.constraint_name = kcu.constraint_name
            AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage AS ccu
            ON ccu.constraint_name = tc.constraint_name
            AND ccu.table_schema = tc.table_schema
            JOIN information_schema.referential_constraints rc
            ON tc.constraint_catalog = rc.constraint_catalog
            AND tc.constraint_schema = rc.constraint_schema
            AND tc.constraint_name = rc.constraint_name
            WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_schema = '$tableLst[0]' AND tc.table_name = '$tableLst[1]';
EOT;
        } else {
            return []; // invalid name!
        }
        $res = $this->_dbAdapter->query($sql);
        if (empty($res))
            return [];
        $tmpList = [];
        foreach ($res as $r)
            $tmpList[$r->constraint_name] = [
                'name' => $r->constraint_name,
                'table' => "$r->table_schema.$r->table_name",
                'column' => $r->column_name,
                'ref_table' => "$r->foreign_table_schema.$r->foreign_table_name",
                'ref_column' => $r->foreign_column_name,
                'on_update' => $r->on_update,
                'on_delete' => $r->on_delete
            ];
        return $tmpList;
    }

    /**
     * Преобразование типа колонки в тип базы данных
     * @param string $name - имя типа
     * @param int|null $limit - размер данных
     * @return string
     *
     * NOTE: override this method for correct implementation.
     */
    final protected function toDatabaseType(string $name, int $limit = null): string {
        $name = str_replace("unsigned", "", $name);
        $name = str_replace("UNSIGNED", "", $name);
        $name = trim($name);
        if (strcmp($name, 'string') === 0) {
            if (isset($limit))
                return "varchar ($limit)";
            else
                return "text";
        }
        if (isset($limit) && strcmp($name, 'text') !== 0)
            return "$name ($limit)";
        return $name;
    }

    /**
     * Переименовать таблицу
     * @param string $name - имя
     * @param string $newName - новое имя
     */
    final public function renameTable(string $name, string $newName) {
        if (empty($name) || empty($newName))
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> renameTable: invalid table name or new name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> renameTable: invalid database connector (NULL)!');
        $tmpIdexes = $this->tableIndexes($name);
        $tmpName = $this->_dbAdapter->quoteTableName($name);
        // for postgresql without scheme name
        $tmpNewName = "\"".$this->nameWithoutSchemeName($newName)."\"";
        // exec query
        $this->_dbAdapter->query("ALTER TABLE $tmpName RENAME TO $tmpNewName;");
        foreach ($tmpIdexes as $info) {
            $indexNewName = str_replace($name, $newName, $info['index_name']);
            if (strcmp($indexNewName, $info['index_name']) === 0)
                continue;
            $this->renameIndex($newName, $info['index_name'], $indexNewName);
        }
    }

    /**
     * Переименовать индекс для таблицы
     * @param string $table - название таблицы
     * @param string $oldName - старое название
     * @param string $newName - новое название
     */
    final public function renameIndex(string $table, string $oldName, string $newName) {
        if (empty($table) || empty($oldName) || empty($newName))
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> renameIndex: invalid table name or old name or new name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> renameIndex: invalid database connector (NULL)!');
        if (strcmp($oldName, $newName) === 0)
            return; // skip
        // for postgresql without scheme name
        $tableSchemeName = $this->schemeName($table);
        $oldNameSchemeName = $this->schemeName($oldName, $tableSchemeName);
        if (strcmp($tableSchemeName, $oldNameSchemeName) !== 0)
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> renameIndex: Scheme name in old name is not equal table scheme name!');
        $tmpOldName = $this->_dbAdapter->quoteTableName($this->nameWithSchemeName($oldName, $oldNameSchemeName));
        $tmpNewName = "\"".$this->nameWithoutSchemeName($newName)."\"";
        $this->_dbAdapter->query("ALTER INDEX $tmpOldName RENAME TO $tmpNewName;");
    }

    /**
     * Добавить вторичный ключ для таблицы
     * @param string $table - название таблицы
     * @param array $columns - названия колонок
     * @param string $refTable - название таблицы на котороу ссылаемся
     * @param array $refColumns - названия колонок на которые ссылаемся
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [bool] on_update - добавить флаг 'ON UPDATE' (может не поддерживаться)
     * [bool] on_delete - добавить флаг 'ON DELETE' (может не поддерживаться)
     * [string] action  - добавить флаг поведения 'NO ACTION / CASCADE / RESTRICT / SET DEFAULT / SET NULL' (может не поддерживаться)
     * [string] name    - задать имя вторичного ключа
     */
    final public function addForeignKey(string $table, array $columns,
                                        string $refTable, array $refColumns,
                                        array $props = []) {
        if (empty($table) || empty($columns)
            || empty($refTable) || empty($refColumns))
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> addForeignKey: invalid input arguments!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> addForeignKey: invalid database connector (NULL)!');
        $columns = array_filter($columns,'strlen');
        if (empty($columns))
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> addForeignKey: invalid columns (Empty)!');
        $refColumns = array_filter($refColumns,'strlen');
        if (empty($refColumns))
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> addForeignKey: invalid refColumns (Empty)!');
        $columnNames = implode(', ', $columns);
        $refColumnNames = implode(', ', $refColumns);
        $tmpName = "fk_" . $this->nameWithoutSchemeName($table) . "_" . implode('_', $columns);
        if (isset($props['name']) && !empty($props['name']))
            $tmpName = CoreHelper::underscore($props['name']);

        $table = $this->_dbAdapter->quoteTableName($table);
        $refTable = $this->_dbAdapter->quoteTableName($refTable);
        $sql = "ALTER TABLE $table ADD CONSTRAINT ".$this->_dbAdapter->quoteTableName($tmpName)." FOREIGN KEY ($columnNames) REFERENCES $refTable ($refColumnNames)";
        $addNext = false;
        if (isset($props['on_update']) && $props['on_update'] === true) {
            $sql .= " ON UPDATE";
            $addNext = true;
        } elseif (isset($props['on_delete']) && $props['on_delete'] === true) {
            $sql .= " ON DELETE";
            $addNext = true;
        }
        if (isset($props['action']) && $addNext === true) {
            $tmpAct = $this->makeReferenceAction($props['action']);
            $sql .= " $tmpAct";
        } elseif ($addNext === true) {
            $sql .= " NO ACTION";
        }
        $this->_dbAdapter->query("$sql;");
    }

    // --- protected ---

    /**
     * Удалить индекс у таблицы
     * @param array $args
     */
    final protected function dropIndexProtected(array $args)
    {
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> dropIndexProtected: invalid database connector (NULL)!');
        if (empty($args))
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> dropIndexProtected: invalid args!');
        if (!isset($args['table']))
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> dropIndexProtected: invalid args values!');
        if (!isset($args['columns']) && !isset($args['name']))
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> dropIndexProtected: invalid args values!');
        $table = $args['table'];
        $tmpName = "";
        if (isset($args['columns'])) {
            $columns = array_filter($args['columns'], 'strlen');
            if (empty($columns))
                return;
            $tmpName = $table . "_" . implode('_', $columns) . "_index";
        }
        if (isset($args['name']))
            $tmpName = $args['name'];

        $tableSchemeName = $this->schemeName($table);
        $tmpNameSchemeName = $this->schemeName($tmpName, $tableSchemeName);
        if (strcmp($tableSchemeName, $tmpNameSchemeName) !== 0)
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> dropIndexProtected: Scheme name in index name is not equal table scheme name!');
        $tmpName = $this->_dbAdapter->quoteTableName($this->nameWithSchemeName($tmpName, $tmpNameSchemeName));

        $sql = "DROP INDEX";
        if (isset($args['if_exists']) && $args['if_exists'] === true)
            $sql .= " IF EXISTS";
        $sql .= " $tmpName";
        if (isset($args['cascade']) && $args['cascade'] === true)
            $sql .= " CASCADE";

        $this->_dbAdapter->query("$sql;");
    }

    // --- private ---

    /**
     * Разобрать строку SQL по созданию индекса для получения аргументов
     * @param string $table - название таблицы
     * @param string|null $name - название индекса
     * @param string|null $row - строка SQL
     * @return array|null
     */
    private function parseTableIndex(string $table, $name, $row) {
        if (empty($name) || empty($row))
            return null;
        $isUnique = false;
        if (strpos($row, " UNIQUE ") !== false)
            $isUnique = true;
        $pos = strpos($row, "(");
        if ($pos === false)
            return null;
        $row = substr($row, $pos + 1, strlen($row));
        $row = trim($row);
        $row = str_replace(')', '', $row);
        $rowLst = explode(',', $row);
        $tmpColumns = [];
        foreach ($rowLst as $r)
            $tmpColumns[] = trim($r);
        return [ 'index_name' => $name, 'unique' => $isUnique, 'table' => $table, 'columns' => $tmpColumns ];
    }

    /**
     * Проверка, является ли колонка первичным ключом
     * @param string $column - название колонки
     * @param array $pkeys - массив первичных ключей
     * @return bool
     */
    private function columnIsPKey(string $column, array $pkeys): bool {
        foreach ($pkeys as $pkey) {
            if (strcmp($pkey['column'], $column) === 0)
                return true;
        }
        return false;
    }

    private function schemeName(string $name, string $schemeName = ''): string {
        $nameLst = explode('.', $name);
        if (count($nameLst) == 1) {
            if (strlen($schemeName) === 0)
                return 'public';
            return $schemeName;
        }
        return $nameLst[0];
    }

    private function nameWithSchemeName(string $name, string $schemeName = ''): string {
        $nameLst = explode('.', $name);
        if (count($nameLst) == 1) {
            if (strlen($schemeName) === 0)
                return $name;
            return "$schemeName.$name";
        }
        return implode('.', $nameLst);
    }

    private function nameWithoutSchemeName(string $name): string {
        $nameLst = explode('.', $name);
        if (count($nameLst) == 1)
            return $name;
        array_shift($nameLst);
        return implode('.', $nameLst);
    }
}