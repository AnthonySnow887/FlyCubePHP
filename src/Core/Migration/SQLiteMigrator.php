<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 03.08.21
 * Time: 16:17
 */

namespace FlyCubePHP\Core\Migration;

include_once 'BaseMigrator.php';
include_once __DIR__.'/../../HelperClasses/CoreHelper.php';

use FlyCubePHP\HelperClasses\CoreHelper;

class SQLiteMigrator extends BaseMigrator
{
    /**
     * Создать новую базу данных
     * @param string $name - название базы данных
     * @param array $props - свойства
     */
    public function createDatabase(string $name, array $props = []) {
        if (empty($name))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> createDatabase: invalid database name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> createDatabase: invalid database connector (NULL)!');
        $settings = $this->_dbAdapter->settings();
        $settings['database'] = $name;
        $this->_dbAdapter->recreatePDO($settings);
        return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> createDatabase: not supported!');
    }

    /**
     * Удалить базу данных
     * @param string $name - название базы данных
     */
    public function dropDatabase(string $name) {
        if (empty($name))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> createDatabase: invalid database name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> createDatabase: invalid database connector (NULL)!');
        if (!is_file($name))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> dropDatabase: not found database file '$name'!');
        unlink($name);
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
    final public function tableIndexes(string $table) {
        if (is_null($this->_dbAdapter))
            return []; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> tableIndexes: invalid database connector (NULL)!');
        // --- select table information ---
        $res = $this->_dbAdapter->query("PRAGMA INDEX_LIST(\"$table\");");
        if (empty($res))
            return [];
        $tmpIndexes = [];
        foreach ($res as $r) {
            $iName = $r->name;
            $iRes = $this->_dbAdapter->query("PRAGMA index_info(\"$iName\");");
            if (empty($iRes))
                continue;
            $iColumns = [];
            foreach ($iRes as $info)
                $iColumns[] = $info->name;

            $tmpIndexes[$iName] = [
                'index_name' => $iName,
                'unique' => CoreHelper::toBool($r->unique),
                'table' => $table,
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
        $res = $this->_dbAdapter->query("PRAGMA table_info(\"$table\");");
        if (empty($res))
            return [];
        $tmpColumns = [];
        foreach ($res as $r) {
            $tmpColumns[$r->name] = [
                'table' => $table,
                'column' => $r->name,
                'type' => $r->type,
                'is_pk' => CoreHelper::toBool($r->pk),
                'is_not_null' => CoreHelper::toBool($r->notnull),
                'default' => $r->dflt_value
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
        $res = $this->_dbAdapter->query("SELECT sql FROM sqlite_master WHERE name = \"$table\";");
        if (empty($res))
            return [];
        $tSql = $res[0]->sql;
        // --- select table information ---
        $res = $this->_dbAdapter->query("PRAGMA table_info(\"$table\");");
        if (empty($res))
            return [];
        $tmpColumns = [];
        foreach ($res as $r) {
            if (CoreHelper::toBool($r->pk) === false)
                continue;
            $tmpName = "";
            $columnName = $r->name;
            $reg = "\bCONSTRAINT \"?([A-Za-z0-9_]+\_pkey)\"? PRIMARY KEY \($columnName\)";
            preg_match("/$reg/", $tSql, $matches, PREG_OFFSET_CAPTURE);
            if (count($matches) >= 2)
                $tmpName = trim($matches[1][0]);
            if (empty($tmpName))
                $tmpName = $table."_pkey";

            $tmpColumns[$tmpName] = [
                'name' => $tmpName,
                'table' => $table,
                'column' => $columnName,
                'type' => $r->type
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
        $res = $this->_dbAdapter->query("SELECT sql FROM sqlite_master WHERE name = \"$table\";");
        if (empty($res))
            return [];
        $tSql = $res[0]->sql;
        // --- select table information ---
        $res = $this->_dbAdapter->query("PRAGMA foreign_key_list(\"$table\");");
        if (empty($res))
            return [];
        $tmpList = [];
        foreach ($res as $r) {
            $tmpColumnsLst = explode(',', $r->from);
            $tmpColumns = [];
            foreach ($tmpColumnsLst as $item)
                $tmpColumns[] = trim($item);

            $tmpName = "";
            $columnNames = $r->from;
            $refTableName = $r->table;
            $refColumnNames = $r->to;
            $reg = "\bCONSTRAINT \"?([A-Za-z0-9_]+)\"? FOREIGN KEY \($columnNames\) REFERENCES \"$refTableName\"\($refColumnNames\)";
            preg_match("/$reg/", $tSql, $matches, PREG_OFFSET_CAPTURE);
            if (count($matches) >= 2)
                $tmpName = trim($matches[1][0]);
            if (empty($tmpName))
                $tmpName = "fk_" . $table . "_" . implode('_', $tmpColumns);

            $tmpList[$tmpName] = [
                'name' => $tmpName,
                'table' => $table,
                'column' => $columnNames,
                'ref_table' => $refTableName,
                'ref_column' => $refColumnNames,
                'on_update' => $r->on_update,
                'on_delete' => $r->on_delete
            ];
        }
        return $tmpList;
    }

    /**
     * Переименовать индекс для таблицы
     * @param string $table - название таблицы
     * @param string $oldName - старое название
     * @param string $newName - новое название
     */
    final public function renameIndex(string $table, string $oldName, string $newName) {
        if (empty($table) || empty($oldName) || empty($newName))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> renameIndex: invalid table name or old name or new name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> renameIndex: invalid database connector (NULL)!');
        $tmpIndexes = $this->tableIndexes($table);
        if (empty($tmpIndexes))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> renameIndex: table not contains indexes!');
        $iIndexInfo = [];
        foreach ($tmpIndexes as $info) {
            if (strcmp($info['index_name'], $oldName) === 0) {
                $iIndexInfo = $info;
                break;
            }
        }
        if (empty($iIndexInfo))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> renameIndex: not found old index (name: $oldName)!');

        // --- drop old index ---
        $this->dropIndex($table, [ 'name' => $oldName ]);

        // --- add new index ---
        $this->addIndex($table, $iIndexInfo['columns'], [ 'name' => $newName, 'unique' => $iIndexInfo['unique'] ]);
    }

    /**
     * Добавить колонку в таблицу
     * @param string $table - название таблицы
     * @param string $column - название колонки
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [bool]     if_not_exists  - добавить флаг 'IF NOT EXISTS'
     * [string]   type           - тип данных колонки (обязательный)
     * [integer]  limit          - размер данных колонки
     * [bool]     null           - может ли быть NULL
     * [string]   default        - базовое значение
     */
    final public function addColumn(string $table, string $column, array $props = []) {
        if (empty($table) || empty($column))
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> addColumn: invalid table name or column name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::BaseMigrator -> addColumn: invalid database connector (NULL)!');
        // --- select table information ---
        $res = $this->_dbAdapter->query("SELECT sql FROM sqlite_master WHERE name = \"$table\";");
        if (empty($res))
            return;
        $sql = $res[0]->sql;
        $pos = strpos($sql, "\"$column\"");
        if ($pos !== false)
            return;
        $tmpPKey = "";
        $tmpUnique = [];
        $sql = $this->prepareCreateColumn($column, $props, $tmpPKey, $tmpUnique);
        if (empty($sql))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> addColumn: invalid SQL!');
        $this->_dbAdapter->query("ALTER TABLE \"$table\" ADD $sql;");
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
    final public function changeColumn(string $table, string $column, string $type, array $props = []) {
        if (empty($table) || empty($column) || empty($type))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> changeColumn: invalid table name or column name or column type!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> changeColumn: invalid database connector (NULL)!');
        // --- select indexes ---
        $tmpIdexes = $this->tableIndexes($table);
        // --- select table information ---
        $res = $this->_dbAdapter->query("SELECT sql FROM sqlite_master WHERE name = \"$table\";");
        if (empty($res))
            return;
        $sql = $res[0]->sql;
        $pos = strpos($sql, "\"$column\"");
        if ($pos === false)
            return;
        $newSql = "";
        $newSqlPath = "";
        $useSkip = false;
        for ($i = 0; $i < strlen($sql); $i++) {
            $tmpChar = $sql[$i];
            if (strcmp($tmpChar, ',') === 0) {
                if ($useSkip === false) {
                    $newSqlPath = trim($newSqlPath);
                    if (empty($newSql) && !empty($newSqlPath))
                        $newSql = $newSqlPath;
                    elseif (!empty($newSql) && !empty($newSqlPath))
                        $newSql .= ",\n$newSqlPath";
                } else {
                    $tmpArgs = [ 'type' => $type ];
                    $tmpArgs = array_unique(array_merge($tmpArgs, $props), SORT_REGULAR);
                    $tmpSql = $this->prepareCreateColumn($column, $tmpArgs);
                    if (empty($newSql) && !empty($tmpSql))
                        $newSql = $tmpSql;
                    elseif (!empty($newSql) && !empty($tmpSql))
                        $newSql .= ",\n$tmpSql";
                    $useSkip = false;
                }
                $newSqlPath = "";
                continue;
            }
            $newSqlPath .= $tmpChar;
            if ($i === $pos)
                $useSkip = true;
        }
        if ($useSkip === false) {
            $newSqlPath = trim($newSqlPath);
            if (empty($newSql) && !empty($newSqlPath))
                $newSql = $newSqlPath;
            elseif (!empty($newSql) && !empty($newSqlPath))
                $newSql .= ",\n$newSqlPath";
        }
        // --- remove last ';' ---
        $newSql = trim($newSql);
        if (strcmp($newSql[strlen($newSql) - 1], ';') === 0) {
            $newSql = substr($newSql, 0, strlen($newSql) - 1);
            $newSql = trim($newSql);
        }
        // --- add last ')' ---
        if (strcmp($newSql[strlen($newSql) - 1], ')') !== 0)
            $newSql .= " )";

        $res = $this->_dbAdapter->query("PRAGMA table_info(\"$table\");");
        if (empty($res))
            return;
        $tmpColumns = "";
        foreach ($res as $r) {
            $cName = $r->name;
            if (!empty($tmpColumns))
                $tmpColumns .= ",";
            $tmpColumns .= " $cName";
        }
        $tmpColumns = trim($tmpColumns);

        // --- rename old table ---
        $newTName = $table . "_old";
        $this->_dbAdapter->query("ALTER TABLE \"$table\" RENAME TO \"$newTName\";");

        // --- create new table ---
        $this->_dbAdapter->query($newSql);

        // --- insert new data ---
        if (!empty($tmpColumns))
            $this->_dbAdapter->query("INSERT INTO \"$table\" SELECT $tmpColumns FROM \"$newTName\";");

        // --- drop old table ---
        $this->_dbAdapter->query("DROP TABLE \"$newTName\";");

        // --- append indexes ---
        $tmpIdexesUpd = $this->tableIndexes($table);
        foreach ($tmpIdexes as $index) {
            if (isset($tmpIdexesUpd[$index['index_name']]))
                continue;
            $this->addIndex($table, $index['columns'], ['unique' => $index['unique']]);
        }
    }

    /**
     * Изменить/Удалить секцию DEFAULT у колонки
     * @param string $table - название таблицы
     * @param string $column - название колонки
     * @param $default - значение секции DEFAULT (если null -> секция DEFAULT удаляется)
     */
    final public function changeColumnDefault(string $table, string $column, $default = null) {
        if (empty($table) || empty($column))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> changeColumnDefault: invalid table name or column name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> changeColumnDefault: invalid database connector (NULL)!');
        // --- select indexes ---
        $tmpIdexes = $this->tableIndexes($table);
        // --- select columns ---
        $tmpColumns = $this->tableColumns($table);
        if (!isset($tmpColumns[$column]))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> changeColumnDefault: not found column '$column' in table '$table'!');
        // --- select table information ---
        $res = $this->_dbAdapter->query("SELECT sql FROM sqlite_master WHERE name = \"$table\";");
        if (empty($res))
            return;
        $sql = $res[0]->sql;
        $pos = strpos($sql, "\"$column\"");
        if ($pos === false)
            return;
        $newSql = "";
        $newSqlPath = "";
        $useSkip = false;
        for ($i = 0; $i < strlen($sql); $i++) {
            $tmpChar = $sql[$i];
            if (strcmp($tmpChar, ',') === 0) {
                if ($useSkip === false) {
                    $newSqlPath = trim($newSqlPath);
                    if (empty($newSql) && !empty($newSqlPath))
                        $newSql = $newSqlPath;
                    elseif (!empty($newSql) && !empty($newSqlPath))
                        $newSql .= ",\n$newSqlPath";
                } else {
                    $tmpArgs = [
                        'type' => $tmpColumns[$column]['type'],
                        'default' => $this->prepareDefault($default),
                        'null' => !$tmpColumns[$column]['is_not_null']
                    ];
                    $tmpSql = $this->prepareCreateColumn($column, $tmpArgs);
                    if (empty($newSql) && !empty($tmpSql))
                        $newSql = $tmpSql;
                    elseif (!empty($newSql) && !empty($tmpSql))
                        $newSql .= ",\n$tmpSql";
                    $useSkip = false;
                }
                $newSqlPath = "";
                continue;
            }
            $newSqlPath .= $tmpChar;
            if ($i === $pos)
                $useSkip = true;
        }
        if ($useSkip === false) {
            $newSqlPath = trim($newSqlPath);
            if (empty($newSql) && !empty($newSqlPath))
                $newSql = $newSqlPath;
            elseif (!empty($newSql) && !empty($newSqlPath))
                $newSql .= ",\n$newSqlPath";
        }
        // --- remove last ';' ---
        $newSql = trim($newSql);
        if (strcmp($newSql[strlen($newSql) - 1], ';') === 0) {
            $newSql = substr($newSql, 0, strlen($newSql) - 1);
            $newSql = trim($newSql);
        }
        // --- add last ')' ---
        if (strcmp($newSql[strlen($newSql) - 1], ')') !== 0)
            $newSql .= " )";

        $tmpColumns = implode(', ', array_keys($tmpColumns));

        // --- rename old table ---
        $newTName = $table . "_old";
        $this->_dbAdapter->query("ALTER TABLE \"$table\" RENAME TO \"$newTName\";");

        // --- create new table ---
        $this->_dbAdapter->query($newSql);

        // --- insert new data ---
        if (!empty($tmpColumns))
            $this->_dbAdapter->query("INSERT INTO \"$table\" SELECT $tmpColumns FROM \"$newTName\";");

        // --- drop old table ---
        $this->_dbAdapter->query("DROP TABLE \"$newTName\";");

        // --- append indexes ---
        $tmpIdexesUpd = $this->tableIndexes($table);
        foreach ($tmpIdexes as $index) {
            if (isset($tmpIdexesUpd[$index['index_name']]))
                continue;
            $this->addIndex($table, $index['columns'], ['unique' => $index['unique']]);
        }
    }

    /**
     * Добавить/Удалить секцию NOT NULL у колонки
     * @param string $table - название таблицы
     * @param string $column - название колонки
     * @param bool $notNull - значение секции (если false - секция NOT NULL удаляется)
     */
    public function changeColumnNull(string $table, string $column, $notNull = false) {
        if (empty($table) || empty($column))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> changeColumnDefault: invalid table name or column name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> changeColumnDefault: invalid database connector (NULL)!');
        // --- select indexes ---
        $tmpIdexes = $this->tableIndexes($table);
        // --- select columns ---
        $tmpColumns = $this->tableColumns($table);
        if (!isset($tmpColumns[$column]))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> changeColumnDefault: not found column '$column' in table '$table'!');
        // --- select table information ---
        $res = $this->_dbAdapter->query("SELECT sql FROM sqlite_master WHERE name = \"$table\";");
        if (empty($res))
            return;
        $sql = $res[0]->sql;
        $pos = strpos($sql, "\"$column\"");
        if ($pos === false)
            return;
        $newSql = "";
        $newSqlPath = "";
        $useSkip = false;
        for ($i = 0; $i < strlen($sql); $i++) {
            $tmpChar = $sql[$i];
            if (strcmp($tmpChar, ',') === 0) {
                if ($useSkip === false) {
                    $newSqlPath = trim($newSqlPath);
                    if (empty($newSql) && !empty($newSqlPath))
                        $newSql = $newSqlPath;
                    elseif (!empty($newSql) && !empty($newSqlPath))
                        $newSql .= ",\n$newSqlPath";
                } else {
                    $tmpArgs = [
                        'type' => $tmpColumns[$column]['type'],
                        'default' => $this->prepareDefault($tmpColumns[$column]['default']),
                        'null' => !$notNull
                    ];
                    $tmpSql = $this->prepareCreateColumn($column, $tmpArgs);
                    if (empty($newSql) && !empty($tmpSql))
                        $newSql = $tmpSql;
                    elseif (!empty($newSql) && !empty($tmpSql))
                        $newSql .= ",\n$tmpSql";
                    $useSkip = false;
                }
                $newSqlPath = "";
                continue;
            }
            $newSqlPath .= $tmpChar;
            if ($i === $pos)
                $useSkip = true;
        }
        if ($useSkip === false) {
            $newSqlPath = trim($newSqlPath);
            if (empty($newSql) && !empty($newSqlPath))
                $newSql = $newSqlPath;
            elseif (!empty($newSql) && !empty($newSqlPath))
                $newSql .= ",\n$newSqlPath";
        }
        // --- remove last ';' ---
        $newSql = trim($newSql);
        if (strcmp($newSql[strlen($newSql) - 1], ';') === 0) {
            $newSql = substr($newSql, 0, strlen($newSql) - 1);
            $newSql = trim($newSql);
        }
        // --- add last ')' ---
        if (strcmp($newSql[strlen($newSql) - 1], ')') !== 0)
            $newSql .= " )";

        $tmpColumns = implode(', ', array_keys($tmpColumns));

        // --- rename old table ---
        $newTName = $table . "_old";
        $this->_dbAdapter->query("ALTER TABLE \"$table\" RENAME TO \"$newTName\";");

        // --- create new table ---
        $this->_dbAdapter->query($newSql);

        // --- insert new data ---
        if (!empty($tmpColumns))
            $this->_dbAdapter->query("INSERT INTO \"$table\" SELECT $tmpColumns FROM \"$newTName\";");

        // --- drop old table ---
        $this->_dbAdapter->query("DROP TABLE \"$newTName\";");

        // --- append indexes ---
        $tmpIdexesUpd = $this->tableIndexes($table);
        foreach ($tmpIdexes as $index) {
            if (isset($tmpIdexesUpd[$index['index_name']]))
                continue;
            $this->addIndex($table, $index['columns'], ['unique' => $index['unique']]);
        }
    }

    /**
     * Удалить колонку из таблицы
     * @param string $table - название таблицы
     * @param string $column - название колонки
     */
    final public function dropColumn(string $table, string $column) {
        if (empty($table) || empty($column))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> dropColumn: invalid table name or column name!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> dropColumn: invalid database connector (NULL)!');
        // --- select table information ---
        $res = $this->_dbAdapter->query("SELECT sql FROM sqlite_master WHERE name = \"$table\";");
        if (empty($res))
            return;
        $sql = $res[0]->sql;
        $pos = strpos($sql, "\"$column\"");
        if ($pos === false)
            return;
        $newSql = "";
        $newSqlPath = "";
        $useSkip = false;
        for ($i = 0; $i < strlen($sql); $i++) {
            $tmpChar = $sql[$i];
            if (strcmp($tmpChar, ',') === 0) {
                if ($useSkip === false) {
                    $newSqlPath = trim($newSqlPath);
                    if (empty($newSql) && !empty($newSqlPath))
                        $newSql = $newSqlPath;
                    elseif (!empty($newSql) && !empty($newSqlPath))
                        $newSql .= ",\n$newSqlPath";
                } else {
                    $useSkip = false;
                }
                $newSqlPath = "";
                continue;
            }
            $newSqlPath .= $tmpChar;
            if ($i === $pos)
                $useSkip = true;
        }
        if ($useSkip === false) {
            $newSqlPath = trim($newSqlPath);
            if (empty($newSql) && !empty($newSqlPath))
                $newSql = $newSqlPath;
            elseif (!empty($newSql) && !empty($newSqlPath))
                $newSql .= ",\n$newSqlPath";
        }
        // --- remove last ';' ---
        $newSql = trim($newSql);
        if (strcmp($newSql[strlen($newSql) - 1], ';') === 0) {
            $newSql = substr($newSql, 0, strlen($newSql) - 1);
            $newSql = trim($newSql);
        }
        // --- add last ')' ---
        if (strcmp($newSql[strlen($newSql) - 1], ')') !== 0)
            $newSql .= " )";

        $res = $this->_dbAdapter->query("PRAGMA table_info(\"$table\");");
        if (empty($res))
            return;
        $tmpColumns = "";
        foreach ($res as $r) {
            $cName = $r->name;
            if (strcmp($cName, $column) === 0)
                continue;
            if (!empty($tmpColumns))
                $tmpColumns .= ",";
            $tmpColumns .= " $cName";
        }
        $tmpColumns = trim($tmpColumns);

        // --- rename old table ---
        $newTName = $table . "_old";
        $this->_dbAdapter->query("ALTER TABLE \"$table\" RENAME TO \"$newTName\";");

        // --- create new table ---
        $this->_dbAdapter->query($newSql);

        // --- insert new data ---
        if (!empty($tmpColumns))
            $this->_dbAdapter->query("INSERT INTO \"$table\" SELECT $tmpColumns FROM \"$newTName\";");

        // --- drop old table ---
        $this->_dbAdapter->query("DROP TABLE \"$newTName\";");
    }

    /**
     * Установить новый первичный ключ таблицы
     * @param string $table - название таблицы
     * @param string $column - название колонки
     */
    final public function setPrimaryKey(string $table, string $column) {
        if (empty($table) || empty($column))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> setPrimaryKey: invalid input arguments!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> addForeignKey: invalid database connector (NULL)!');
        // --- drop old primary keys ---
        $tmpPKeys = $this->tablePrimaryKeys($table);
        foreach ($tmpPKeys as $info)
            $this->dropPrimaryKey($table, $info['column']);
        // --- select foreign_keys ---
        $res = $this->_dbAdapter->query("PRAGMA foreign_keys;");
        if (empty($res))
            return;
        $foreignKeys = $res[0]->foreign_keys;
        // --- select defer_foreign_keys ---
        $res = $this->_dbAdapter->query("PRAGMA defer_foreign_keys;");
        if (empty($res))
            return;
        $deferForeignKeys = $res[0]->defer_foreign_keys;
        // --- set new states ---
        $this->_dbAdapter->query("PRAGMA defer_foreign_keys = ON;");
        $this->_dbAdapter->query("PRAGMA foreign_keys = OFF;");
        // --- select indexes ---
        $tmpIdexes = $this->tableIndexes($table);
        // --- select columns ---
        $tmpColumns = $this->tableColumns($table);
        // --- select table information ---
        $res = $this->_dbAdapter->query("SELECT sql FROM sqlite_master WHERE name = \"$table\";");
        if (empty($res))
            return;
        $sql = $res[0]->sql;
        $newSql = "";
        $newSqlPath = "";
        for ($i = 0; $i < strlen($sql); $i++) {
            $tmpChar = $sql[$i];
            if (strcmp($tmpChar, ',') === 0) {
                $newSqlPath = trim($newSqlPath);
                if (empty($newSql) && !empty($newSqlPath))
                    $newSql = $newSqlPath;
                elseif (!empty($newSql) && !empty($newSqlPath))
                    $newSql .= ",\n$newSqlPath";

                $newSqlPath = "";
                continue;
            }
            $newSqlPath .= $tmpChar;
        }
        // --- add end of sql ---
        $newSqlPath = trim($newSqlPath);
        if (empty($newSql) && !empty($newSqlPath))
            $newSql = $newSqlPath;
        elseif (!empty($newSql) && !empty($newSqlPath))
            $newSql .= ",\n$newSqlPath";
        // --- remove last ')' ---
        $newSql = trim($newSql);
        if (strcmp($newSql[strlen($newSql) - 1], ';') === 0) {
            $newSql = substr($newSql, 0, strlen($newSql) - 1);
            $newSql = trim($newSql);
        }
        if (strcmp($newSql[strlen($newSql) - 1], ')') === 0) {
            $newSql = substr($newSql, 0, strlen($newSql) - 1);
            $newSql = trim($newSql);
        }
        // --- add primary key ---
        $tmpTable = explode('.', $table);
        $tmpPKeyName = $tmpTable[count($tmpTable) - 1] . "_pkey";
        $fSQL = "CONSTRAINT $tmpPKeyName PRIMARY KEY ($column)";
        if (!empty($newSql))
            $fSQL = ", \n$fSQL";
        $newSql .= $fSQL;
        // --- add last ')' ---
        $newSql .= " )";

        $tmpColumns = implode(', ', array_keys($tmpColumns));

        // --- rename old table ---
        $newTName = $table . "_old";
        $this->_dbAdapter->query("ALTER TABLE \"$table\" RENAME TO \"$newTName\";");

        // --- create new table ---
        $this->_dbAdapter->query($newSql);

        // --- insert new data ---
        if (!empty($tmpColumns))
            $this->_dbAdapter->query("INSERT INTO \"$table\" SELECT $tmpColumns FROM \"$newTName\";");

        // --- drop old table ---
        $this->_dbAdapter->query("DROP TABLE \"$newTName\";");

        // --- append indexes ---
        $tmpIdexesUpd = $this->tableIndexes($table);
        foreach ($tmpIdexes as $index) {
            if (isset($tmpIdexesUpd[$index['index_name']]))
                continue;
            $this->addIndex($table, $index['columns'], ['unique' => $index['unique']]);
        }

        // --- set old states ---
        $this->_dbAdapter->query("PRAGMA defer_foreign_keys = $deferForeignKeys;");
        $this->_dbAdapter->query("PRAGMA foreign_keys = $foreignKeys;");
    }

    /**
     * Удалить первичный ключ таблицы
     * @param string $table - название таблицы
     * @param string $column - название колонки
     */
    final public function dropPrimaryKey(string $table, string $column) {
        if (empty($table) || empty($column))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> dropPrimaryKey: invalid input arguments!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> dropPrimaryKey: invalid database connector (NULL)!');
        $tmpPKeyName = "";
        $tmpPKeys = $this->tablePrimaryKeys($table);
        foreach ($tmpPKeys as $info) {
            if (strcmp($info['column'], $column) === 0) {
                $tmpPKeyName = $info['name'];
                break;
            }
        }
        if (empty($tmpPKeyName))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> dropPrimaryKey: not found pkey name for table $table and column $column!');
        // --- select foreign_keys ---
        $res = $this->_dbAdapter->query("PRAGMA foreign_keys;");
        if (empty($res))
            return;
        $foreignKeys = $res[0]->foreign_keys;
        // --- select defer_foreign_keys ---
        $res = $this->_dbAdapter->query("PRAGMA defer_foreign_keys;");
        if (empty($res))
            return;
        $deferForeignKeys = $res[0]->defer_foreign_keys;
        // --- set new states ---
        $this->_dbAdapter->query("PRAGMA defer_foreign_keys = ON;");
        $this->_dbAdapter->query("PRAGMA foreign_keys = OFF;");
        // --- select indexes ---
        $tmpIdexes = $this->tableIndexes($table);
        // --- select columns ---
        $tmpColumns = $this->tableColumns($table);
        // --- select table information ---
        $res = $this->_dbAdapter->query("SELECT sql FROM sqlite_master WHERE name = \"$table\";");
        if (empty($res))
            return;
        $sql = $res[0]->sql;
        $pos = strpos($sql, "CONSTRAINT $tmpPKeyName PRIMARY KEY");
        if ($pos === false)
            return;
        $newSql = "";
        $newSqlPath = "";
        $useSkip = false;
        for ($i = 0; $i < strlen($sql); $i++) {
            $tmpChar = $sql[$i];
            if (strcmp($tmpChar, ',') === 0) {
                if ($useSkip === false) {
                    $newSqlPath = trim($newSqlPath);
                    if (empty($newSql) && !empty($newSqlPath))
                        $newSql = $newSqlPath;
                    elseif (!empty($newSql) && !empty($newSqlPath))
                        $newSql .= ",\n$newSqlPath";
                } else {
                    $useSkip = false;
                }
                $newSqlPath = "";
                continue;
            }
            $newSqlPath .= $tmpChar;
            if ($i === $pos)
                $useSkip = true;
        }
        if ($useSkip === false) {
            $newSqlPath = trim($newSqlPath);
            if (empty($newSql) && !empty($newSqlPath))
                $newSql = $newSqlPath;
            elseif (!empty($newSql) && !empty($newSqlPath))
                $newSql .= ",\n$newSqlPath";
        }
        // --- remove last ';' ---
        $newSql = trim($newSql);
        if (strcmp($newSql[strlen($newSql) - 1], ';') === 0) {
            $newSql = substr($newSql, 0, strlen($newSql) - 1);
            $newSql = trim($newSql);
        }
        // --- add last ')' ---
        if (strcmp($newSql[strlen($newSql) - 1], ')') !== 0)
            $newSql .= " )";

        $tmpColumns = implode(', ', array_keys($tmpColumns));

        // --- rename old table ---
        $newTName = $table . "_old";
        $this->_dbAdapter->query("ALTER TABLE \"$table\" RENAME TO \"$newTName\";");

        // --- create new table ---
        $this->_dbAdapter->query($newSql);

        // --- insert new data ---
        if (!empty($tmpColumns))
            $this->_dbAdapter->query("INSERT INTO \"$table\" SELECT $tmpColumns FROM \"$newTName\";");

        // --- drop old table ---
        $this->_dbAdapter->query("DROP TABLE \"$newTName\";");

        // --- append indexes ---
        $tmpIdexesUpd = $this->tableIndexes($table);
        foreach ($tmpIdexes as $index) {
            if (isset($tmpIdexesUpd[$index['index_name']]))
                continue;
            $this->addIndex($table, $index['columns'], ['unique' => $index['unique']]);
        }

        // --- set old states ---
        $this->_dbAdapter->query("PRAGMA defer_foreign_keys = $deferForeignKeys;");
        $this->_dbAdapter->query("PRAGMA foreign_keys = $foreignKeys;");
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
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> addForeignKey: invalid input arguments!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> addForeignKey: invalid database connector (NULL)!');
        $columns = array_filter($columns,'strlen');
        if (empty($columns))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> addForeignKey: invalid columns (Empty)!');
        $refColumns = array_filter($refColumns,'strlen');
        if (empty($refColumns))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> addForeignKey: invalid refColumns (Empty)!');
        // --- select foreign_keys ---
        $res = $this->_dbAdapter->query("PRAGMA foreign_keys;");
        if (empty($res))
            return;
        $foreignKeys = $res[0]->foreign_keys;
        // --- select defer_foreign_keys ---
        $res = $this->_dbAdapter->query("PRAGMA defer_foreign_keys;");
        if (empty($res))
            return;
        $deferForeignKeys = $res[0]->defer_foreign_keys;
        // --- set new states ---
        $this->_dbAdapter->query("PRAGMA defer_foreign_keys = ON;");
        $this->_dbAdapter->query("PRAGMA foreign_keys = OFF;");
        // --- select indexes ---
        $tmpIdexes = $this->tableIndexes($table);
        // --- select columns ---
        $tmpColumns = $this->tableColumns($table);
        // --- select table information ---
        $res = $this->_dbAdapter->query("SELECT sql FROM sqlite_master WHERE name = \"$table\";");
        if (empty($res))
            return;
        $sql = $res[0]->sql;
        $newSql = "";
        $newSqlPath = "";
        for ($i = 0; $i < strlen($sql); $i++) {
            $tmpChar = $sql[$i];
            if (strcmp($tmpChar, ',') === 0) {
                $newSqlPath = trim($newSqlPath);
                if (empty($newSql) && !empty($newSqlPath))
                    $newSql = $newSqlPath;
                elseif (!empty($newSql) && !empty($newSqlPath))
                    $newSql .= ",\n$newSqlPath";

                $newSqlPath = "";
                continue;
            }
            $newSqlPath .= $tmpChar;
        }
        // --- add end of sql ---
        $newSqlPath = trim($newSqlPath);
        if (empty($newSql) && !empty($newSqlPath))
            $newSql = $newSqlPath;
        elseif (!empty($newSql) && !empty($newSqlPath))
            $newSql .= ",\n$newSqlPath";
        // --- remove last ')' ---
        $newSql = trim($newSql);
        if (strcmp($newSql[strlen($newSql) - 1], ';') === 0) {
            $newSql = substr($newSql, 0, strlen($newSql) - 1);
            $newSql = trim($newSql);
        }
        if (strcmp($newSql[strlen($newSql) - 1], ')') === 0) {
            $newSql = substr($newSql, 0, strlen($newSql) - 1);
            $newSql = trim($newSql);
        }
        // --- add foreign key ---
        $columnNames = implode(', ', $columns);
        $refColumnNames = implode(', ', $refColumns);
        $tmpName = "fk_" . $table . "_" . implode('_', $columns);
        if (isset($props['name']) && !empty($props['name']))
            $tmpName = CoreHelper::underscore($props['name']);
        $fSQL = "CONSTRAINT \"$tmpName\" FOREIGN KEY ($columnNames) REFERENCES \"$refTable\"($refColumnNames)";
        $addNext = false;
        if (isset($props['on_update']) && $props['on_update'] === true) {
            $fSQL .= " ON UPDATE";
            $addNext = true;
        } elseif (isset($props['on_delete']) && $props['on_delete'] === true) {
            $fSQL .= " ON DELETE";
            $addNext = true;
        }
        if (isset($props['action']) && $addNext === true) {
            $tmpAct = $this->makeReferenceAction($props['action']);
            $fSQL .= " $tmpAct";
        } elseif ($addNext === true) {
            $fSQL .= " NO ACTION";
        }
        if (!empty($newSql))
            $fSQL = ", \n$fSQL";
        $newSql .= $fSQL;
        // --- add last ')' ---
        $newSql .= " )";

        $tmpColumns = implode(', ', array_keys($tmpColumns));

        // --- rename old table ---
        $newTName = $table . "_old";
        $this->_dbAdapter->query("ALTER TABLE \"$table\" RENAME TO \"$newTName\";");

        // --- create new table ---
        $this->_dbAdapter->query($newSql);

        // --- insert new data ---
        if (!empty($tmpColumns))
            $this->_dbAdapter->query("INSERT INTO \"$table\" SELECT $tmpColumns FROM \"$newTName\";");

        // --- drop old table ---
        $this->_dbAdapter->query("DROP TABLE \"$newTName\";");

        // --- append indexes ---
        $tmpIdexesUpd = $this->tableIndexes($table);
        foreach ($tmpIdexes as $index) {
            if (isset($tmpIdexesUpd[$index['index_name']]))
                continue;
            $this->addIndex($table, $index['columns'], ['unique' => $index['unique']]);
        }

        // --- set old states ---
        $this->_dbAdapter->query("PRAGMA defer_foreign_keys = $deferForeignKeys;");
        $this->_dbAdapter->query("PRAGMA foreign_keys = $foreignKeys;");
    }

    /**
     * Удалить вторичный ключ таблицы
     * @param string $table - название таблицы
     * @param array $columns - названия колонок
     */
    final public function dropForeignKey(string $table, array $columns) {
        if (empty($table) || empty($columns))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> dropForeignKey: invalid input arguments!');
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> dropForeignKey: invalid database connector (NULL)!');
        $columns = array_filter($columns,'strlen');
        if (empty($columns))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> dropForeignKey: invalid columns (Empty)!');
        // --- select foreign_keys ---
        $res = $this->_dbAdapter->query("PRAGMA foreign_keys;");
        if (empty($res))
            return;
        $foreignKeys = $res[0]->foreign_keys;
        // --- select defer_foreign_keys ---
        $res = $this->_dbAdapter->query("PRAGMA defer_foreign_keys;");
        if (empty($res))
            return;
        $deferForeignKeys = $res[0]->defer_foreign_keys;
        // --- set new states ---
        $this->_dbAdapter->query("PRAGMA defer_foreign_keys = ON;");
        $this->_dbAdapter->query("PRAGMA foreign_keys = OFF;");
        // --- select indexes ---
        $tmpIdexes = $this->tableIndexes($table);
        // --- select columns ---
        $tmpColumns = $this->tableColumns($table);
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
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> dropForeignKey: not found fkey name for table $table and columns ($columnNames)!');

        // --- select table information ---
        $res = $this->_dbAdapter->query("SELECT sql FROM sqlite_master WHERE name = \"$table\";");
        if (empty($res))
            return;
        $sql = $res[0]->sql;

        $pos = strpos($sql, "CONSTRAINT \"$tmpName\" FOREIGN KEY");
        if ($pos === false)
            return;
        $newSql = "";
        $newSqlPath = "";
        $useSkip = false;
        for ($i = 0; $i < strlen($sql); $i++) {
            $tmpChar = $sql[$i];
            if (strcmp($tmpChar, ',') === 0) {
                if ($useSkip === false) {
                    $newSqlPath = trim($newSqlPath);
                    if (empty($newSql) && !empty($newSqlPath))
                        $newSql = $newSqlPath;
                    elseif (!empty($newSql) && !empty($newSqlPath))
                        $newSql .= ",\n$newSqlPath";
                } else {
                    $useSkip = false;
                }
                $newSqlPath = "";
                continue;
            }
            $newSqlPath .= $tmpChar;
            if ($i === $pos)
                $useSkip = true;
        }
        if ($useSkip === false) {
            $newSqlPath = trim($newSqlPath);
            if (empty($newSql) && !empty($newSqlPath))
                $newSql = $newSqlPath;
            elseif (!empty($newSql) && !empty($newSqlPath))
                $newSql .= ",\n$newSqlPath";
        }
        // --- remove last ';' ---
        $newSql = trim($newSql);
        if (strcmp($newSql[strlen($newSql) - 1], ';') === 0) {
            $newSql = substr($newSql, 0, strlen($newSql) - 1);
            $newSql = trim($newSql);
        }
        // --- add last ')' ---
//        if (strcmp($newSql[strlen($newSql) - 1], ')') !== 0)
            $newSql .= " )";

        $tmpColumns = implode(', ', array_keys($tmpColumns));

        // --- rename old table ---
        $newTName = $table . "_old";
        $this->_dbAdapter->query("ALTER TABLE \"$table\" RENAME TO \"$newTName\";");

        // --- create new table ---
        $this->_dbAdapter->query($newSql);

        // --- insert new data ---
        if (!empty($tmpColumns))
            $this->_dbAdapter->query("INSERT INTO \"$table\" SELECT $tmpColumns FROM \"$newTName\";");

        // --- drop old table ---
        $this->_dbAdapter->query("DROP TABLE \"$newTName\";");

        // --- append indexes ---
        $tmpIdexesUpd = $this->tableIndexes($table);
        foreach ($tmpIdexes as $index) {
            if (isset($tmpIdexesUpd[$index['index_name']]))
                continue;
            $this->addIndex($table, $index['columns'], ['unique' => $index['unique']]);
        }

        // --- set old states ---
        $this->_dbAdapter->query("PRAGMA defer_foreign_keys = $deferForeignKeys;");
        $this->_dbAdapter->query("PRAGMA foreign_keys = $foreignKeys;");
    }

    // --- protected ---

    /**
     * Создать SQL подстроку с базовым значением
     * @param string $default - базовое значение
     * @param string $type - тип данных колонки
     * @return string
     */
    final protected function makeDefaultValue(string $default, string $type): string {
        if (strpos($type, "text") !== false
            || strpos($type, "varchar") !== false
            || strpos($type, "character varying") !== false)
            return "DEFAULT \"$default\"";

        return "DEFAULT $default";
    }

    /**
     * Удалить индекс у таблицы
     * @param array $args
     */
    final protected function dropIndexProtected(array $args) {
        if (is_null($this->_dbAdapter))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> dropIndexProtected: invalid database connector (NULL)!');
        if (empty($args))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> dropIndexProtected: invalid args!');
        if (!isset($args['table']))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> dropIndexProtected: invalid args values!');
        if (!isset($args['columns']) && !isset($args['name']))
            return; // TODO throw new \RuntimeException('Migration::SQLiteMigrator -> dropIndexProtected: invalid args values!');
        $table = $args['table'];
        $tmpName = "";
        if (isset($args['columns'])) {
            $columns = array_filter($args['columns'],'strlen');
            if (empty($columns))
                return;
            $tmpName = $table . "_" . implode('_', $columns) . "_index";
        }
        if (isset($args['name']))
            $tmpName = $args['name'];

        $this->_dbAdapter->query("DROP INDEX \"$tmpName\";");
    }

    /**
     * Получить значение секции default без кавычек
     * @param null|string $val
     * @return null|string
     */
    final protected function prepareDefault($val)/*: null|string*/ {
        if (empty($val))
            return $val;
        if (strcmp($val[0], "\"") === 0) {
            if (strlen($val) > 1) {
                $val = ltrim($val, "\"");
                $val = $this->prepareDefault($val);
            } else {
                $val = "";
            }
        } else if (strcmp($val[strlen($val) - 1], "\"") === 0) {
            if (strlen($val) > 1) {
                $val = substr($val, 0, -1);
                $val = $this->prepareDefault($val);
            } else {
                $val = "";
            }
        }
        return $val;
    }
}