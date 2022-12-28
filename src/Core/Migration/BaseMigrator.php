<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 03.08.21
 * Time: 15:50
 */

namespace FlyCubePHP\Core\Migration;

include_once __DIR__.'/../Database/DatabaseFactory.php';
include_once __DIR__.'/../../HelperClasses/CoreHelper.php';

use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\Database\BaseDatabaseAdapter;

abstract class BaseMigrator
{
    protected $_dbAdapter = null;

    public function __construct(BaseDatabaseAdapter &$_dbAdapter) {
        $this->_dbAdapter = $_dbAdapter;
    }

    public function __destruct() {
        unset($this->_dbAdapter);
    }

    /**
     * Создать новую базу данных
     * @param string $name - название базы данных
     * @param array $props - свойства
     *
     * NOTE: override this method for correct implementation.
     */
    public function createDatabase(string $name, array $props = []) {
        return;
    }

    /**
     * Удалить базу данных
     * @param string $name - название базы данных
     *
     * NOTE: override this method for correct implementation.
     */
    public function dropDatabase(string $name) {
        return;
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
        return;
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
        return;
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
    public function createSchema(string $name, array $props = []) {
        return;
    }

    /**
     * Удалить схему данных
     * @param string $name - имя
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [bool] if_exists - добавить флаг 'IF EXISTS'
     *
     * NOTE: override this method for correct implementation.
     */
    public function dropSchema(string $name, array $props = []) {
        return;
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
     *
     * NOTE: override this method for correct implementation.
     */
    public function tableIndexes(string $table) {
        return [];
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
     *
     * NOTE: override this method for correct implementation.
     */
    public function tableColumns(string $table) {
        return [];
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
     *
     * NOTE: override this method for correct implementation.
     */
    public function tablePrimaryKeys(string $table) {
        return [];
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
     *
     * NOTE: override this method for correct implementation.
     */
    public function tableForeignKeys(string $table) {
        return [];
    }

    /**
     * Создать таблицу
     * @param string $name - название
     * @param array $args - массив колонок и их спецификация
     *
     * Supported Keys:
     *
     * [bool]     if_not_exists  - добавить флаг 'IF NOT EXISTS' (только для таблицы)
     * [bool]     id             - использовать колонку ID или нет (будет задана как первичный ключ)
     * [string]   type           - тип данных колонки (обязательный)
     * [integer]  limit          - размер данных колонки
     * [bool]     null           - может ли быть NULL
     * [string]   default        - базовое значение
     * [bool]     primary_key    - использовать как первичный ключ
     * [bool]     unique         - является уникальным
     * [string]   unique_group   - является уникальной группой (значение: имя группы)
     *
     * NOTE:
     * id - serial not NULL (for MySQL: bigint unsigned)
     *
     * createTable('test', [ 'id' => false,
     *                       'if_not_exists' => true,
     *                       'my_id' => [ 'type' => 'integer', 'null' => false, 'primary_key' => true ],
     *                       'my_data' => [ 'type' => 'string', 'limit' => 128, 'default' => '' ]
     * ]);
     *
     */
    public function createTable(string $name, array $args) {
        if (empty($name) || empty($args))
            throw new \RuntimeException('Migration::BaseMigrator -> createTable: invalid table name or args!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> createTable: invalid database adapter (NULL)!');
        $ifNotExists = "";
        if (isset($args['if_not_exists']) && $args['if_not_exists'] === true) {
            $ifNotExists = "IF NOT EXISTS";
            unset($args['if_not_exists']);
        }
        $tmpName = $this->_dbAdapter->quoteTableName($name);
        $sql = "CREATE TABLE $ifNotExists $tmpName (\n";
        $sql .= $this->prepareCreateTable($name, $args);
        $sql .= "\n);";
        $this->_dbAdapter->query($sql);
    }

    /**
     * Переименовать таблицу
     * @param string $name - имя
     * @param string $newName - новое имя
     */
    public function renameTable(string $name, string $newName) {
        if (empty($name) || empty($newName))
            throw new \RuntimeException('Migration::BaseMigrator -> renameTable: invalid table name or new name!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> renameTable: invalid database adapter (NULL)!');
        $tmpIdexes = $this->tableIndexes($name);
        $tmpName = $this->_dbAdapter->quoteTableName($name);
        $tmpNewName = $this->_dbAdapter->quoteTableName($newName);
        $this->_dbAdapter->query("ALTER TABLE $tmpName RENAME TO $tmpNewName;");
        foreach ($tmpIdexes as $info) {
            $indexNewName = str_replace($name, $newName, $info['index_name']);
            if (strcmp($indexNewName, $info['index_name']) === 0)
                continue;
            $this->renameIndex($newName, $info['index_name'], $indexNewName);
        }
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
    public function dropTable(string $name, array $props = []) {
        if (empty($name))
            throw new \RuntimeException('Migration::BaseMigrator -> dropTable: invalid table name!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> dropTable: invalid database adapter (NULL)!');
        $name = $this->_dbAdapter->quoteTableName($name);
        $sql = "DROP TABLE $name";
        if (isset($props['if_exists']) && $props['if_exists'] === true)
            $sql .= " IF EXISTS";
        if (isset($props['cascade']) && $props['cascade'] === true)
            $sql .= " CASCADE";
        $this->_dbAdapter->query("$sql;");
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
    public function addColumn(string $table, string $column, array $props = []) {
        if (empty($table) || empty($column))
            throw new \RuntimeException('Migration::BaseMigrator -> addColumn: invalid table name or column name!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> addColumn: invalid database adapter (NULL)!');
        $ifNotExists = "";
        if (isset($props['if_not_exists']) && $props['if_not_exists'] === true) {
            $ifNotExists = "IF NOT EXISTS";
            unset($props['if_not_exists']);
        }
        $tmpPKey = "";
        $tmpUnique = [];
        $sql = $this->prepareCreateColumn($column, $props, $tmpPKey, $tmpUnique);
        if (empty($sql))
            throw new \RuntimeException('Migration::BaseMigrator -> addColumn: prepare create column return empty result!');
        $table = $this->_dbAdapter->quoteTableName($table);
        $this->_dbAdapter->query("ALTER TABLE $table ADD COLUMN $ifNotExists $sql;");
    }

    /**
     * Переименовать колонку в таблице
     * @param string $table - название таблицы
     * @param string $column - название колонки
     * @param string $newName - новое название колонки
     */
    public function renameColumn(string $table, string $column, string $newName) {
        if (empty($table) || empty($column) || empty($newName))
            throw new \RuntimeException('Migration::BaseMigrator -> renameColumn: invalid table name or column name or new column name!');
        if (strcmp($column, $newName) === 0)
            throw new \RuntimeException('Migration::BaseMigrator -> renameColumn: column name is the same as new column name!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> renameColumn: invalid database adapter (NULL)!');
        $tmpIdexes = $this->tableIndexes($table);
        $tmpTable = $this->_dbAdapter->quoteTableName($table);
        $tmpColumn = $this->_dbAdapter->quoteTableName($column);
        $tmpNewName = $this->_dbAdapter->quoteTableName($newName);
        $this->_dbAdapter->query("ALTER TABLE $tmpTable RENAME COLUMN $tmpColumn TO $tmpNewName;");
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
            throw new \RuntimeException('Migration::BaseMigrator -> changeColumn: invalid table name or column name or column type!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> changeColumn: invalid database adapter (NULL)!');

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
        $tPos = strpos($tmpType, "(");
        if ($tPos !== false)
            $tmpTypeUsing = trim(substr($tmpTypeUsing, 0, $tPos));
        $sql = "ALTER TABLE $table ALTER COLUMN ".$this->_dbAdapter->quoteTableName($column)." TYPE $tmpType USING ($column::$tmpTypeUsing);";
        $this->_dbAdapter->query($sql);

        // --- change default ---
        if (isset($props['default'])) {
            $tmpDefault = $this->makeDefaultValue($props['default'], $tmpType);
            $sql = "ALTER TABLE $table ALTER COLUMN ".$this->_dbAdapter->quoteTableName($column)." SET $tmpDefault;";
            $this->_dbAdapter->query($sql);
        }

        // --- change not null ---
        if (isset($props['null'])) {
            if ($props['null'] === false)
                $sql = "ALTER TABLE $table ALTER COLUMN ".$this->_dbAdapter->quoteTableName($column)." SET NOT NULL;";
            else
                $sql = "ALTER TABLE $table ALTER COLUMN ".$this->_dbAdapter->quoteTableName($column)." DROP NOT NULL;";

            $this->_dbAdapter->query($sql);
        }
    }

    /**
     * Изменить/Удалить секцию DEFAULT у колонки
     * @param string $table - название таблицы
     * @param string $column - название колонки
     * @param $default - значение секции DEFAULT (если null -> секция DEFAULT удаляется)
     */
    public function changeColumnDefault(string $table, string $column, $default = null) {
        if (empty($table) || empty($column))
            throw new \RuntimeException('Migration::BaseMigrator -> changeColumnDefault: invalid table name or column name!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> changeColumnDefault: invalid database adapter (NULL)!');

        if (!isset($default)) {
            // --- drop default ---
            $table = $this->_dbAdapter->quoteTableName($table);
            $sql = "ALTER TABLE $table ALTER COLUMN ".$this->_dbAdapter->quoteTableName($column)." DROP DEFAULT;";
            $this->_dbAdapter->query($sql);
        } else {
            // --- replace default ---
            $tmpColumns = $this->tableColumns($table);
            if (!isset($tmpColumns[$column]))
                throw new \RuntimeException("Migration::BaseMigrator -> changeColumnDefault: not found column \"$column\" in table columns list!");
            $tmpType = $tmpColumns[$column]['type'];
            $tmpDefault = $this->makeDefaultValue($default, $tmpType);
            $table = $this->_dbAdapter->quoteTableName($table);
            $sql = "ALTER TABLE $table ALTER COLUMN ".$this->_dbAdapter->quoteTableName($column)." SET $tmpDefault;";
            $this->_dbAdapter->query($sql);
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
            throw new \RuntimeException('Migration::BaseMigrator -> changeColumnNull: invalid table name or column name!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> changeColumnNull: invalid database adapter (NULL)!');
        $table = $this->_dbAdapter->quoteTableName($table);
        if (!isset($notNull) || $notNull === false) {
            $this->_dbAdapter->query("ALTER TABLE $table ALTER COLUMN ".$this->_dbAdapter->quoteTableName($column)." DROP NOT NULL;");
        } elseif (isset($notNull) || $notNull === true) {
            $this->_dbAdapter->query("ALTER TABLE $table ALTER COLUMN ".$this->_dbAdapter->quoteTableName($column)." SET NOT NULL;");
        }
    }

    /**
     * Удалить колонку из таблицы
     * @param string $table - название таблицы
     * @param string $column - название колонки
     *
     * NOTE: not supported in SQLite -> override this method for correct implementation.
     */
    public function dropColumn(string $table, string $column) {
        if (empty($table) || empty($column))
            throw new \RuntimeException('Migration::BaseMigrator -> dropColumn: invalid table name or column name!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> dropColumn: invalid database adapter (NULL)!');
        $table = $this->_dbAdapter->quoteTableName($table);
        $this->_dbAdapter->query("ALTER TABLE $table DROP COLUMN ".$this->_dbAdapter->quoteTableName($column).";");
    }

    /**
     * Добавить индекс для таблицы
     * @param string $table - название таблицы
     * @param array $columns - названия колонок
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [string] name    - имя индекса (необязательное)
     * [bool]   unique  - является ли уникальным
     */
    public function addIndex(string $table, array $columns, array $props = []) {
        if (empty($table) || empty($columns))
            throw new \RuntimeException('Migration::BaseMigrator -> addIndex: invalid table name or column names!');
        if (isset($props['table']))
            unset($props['table']);
        if (isset($props['columns']))
            unset($props['columns']);
        $tmpProps = ['table' => $table, 'columns' => $columns];
        $tmpProps = array_unique(array_merge($tmpProps, $props), SORT_REGULAR);
        $this->addIndexProtected($tmpProps);
    }

    /**
     * Переименовать индекс для таблицы
     * @param string $table - название таблицы
     * @param string $oldName - старое название
     * @param string $newName - новое название
     */
    public function renameIndex(string $table, string $oldName, string $newName) {
        if (empty($table) || empty($oldName) || empty($newName))
            throw new \RuntimeException('Migration::BaseMigrator -> renameIndex: invalid table name or old name or new name!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> renameIndex: invalid database adapter (NULL)!');
        if (strcmp($oldName, $newName) === 0)
            throw new \RuntimeException('Migration::BaseMigrator -> renameIndex: old name is the same as new name!');
        $tmpOldName = $this->_dbAdapter->quoteTableName($oldName);
        $tmpNewName = $this->_dbAdapter->quoteTableName($newName);
        $this->_dbAdapter->query("ALTER INDEX $tmpOldName RENAME TO $tmpNewName;");
    }

    /**
     * Удалить индекс таблицы
     * @param string $table - название таблицы
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [array] columns  - имена колонок
     * [string] name    - имя индекса
     * [bool] if_exists - добавить флаг 'IF EXISTS' (может не поддерживаться)
     * [bool] cascade   - добавить флаг 'CASCADE' (может не поддерживаться)
     *
     * NOTE: Должен быть задан хотябы один!
     * NOTE: Приоритет отдается name!
     */
    public function dropIndex(string $table, array $props = []) {
        if (empty($table))
            throw new \RuntimeException('Migration::BaseMigrator -> dropIndex: invalid table name!');
        if (isset($props['table']))
            unset($props['table']);
        $tmpProps = ['table' => $table];
        $tmpProps = array_unique(array_merge($tmpProps, $props), SORT_REGULAR);
        $this->dropIndexProtected($tmpProps);
    }

    /**
     * Установить новый первичный ключ таблицы
     * @param string $table - название таблицы
     * @param string $column - название колонки
     */
    public function setPrimaryKey(string $table, string $column) {
        if (empty($table) || empty($column))
            throw new \RuntimeException('Migration::BaseMigrator -> setPrimaryKey: invalid table name or column name!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> setPrimaryKey: invalid database adapter (NULL)!');
        // --- drop old primary keys ---
        $tmpPKeys = $this->tablePrimaryKeys($table);
        foreach ($tmpPKeys as $info)
            $this->dropPrimaryKey($table, $info['column']);
        // --- set new primary key ---
        $tmpTable = explode('.', $table);
        $tmpName = $this->_dbAdapter->quoteTableName($tmpTable[count($tmpTable) - 1] . "_pkey");
        $table = $this->_dbAdapter->quoteTableName($table);
        $this->_dbAdapter->query("ALTER TABLE $table ADD CONSTRAINT $tmpName PRIMARY KEY ($column);");
    }

    /**
     * Удалить первичный ключ таблицы
     * @param string $table - название таблицы
     * @param string $column - название колонки
     */
    public function dropPrimaryKey(string $table, string $column) {
        if (empty($table) || empty($column))
            throw new \RuntimeException('Migration::BaseMigrator -> dropPrimaryKey: invalid table name or column name!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> dropPrimaryKey: invalid database adapter (NULL)!');
        $tmpPKeyName = "";
        $tmpPKeys = $this->tablePrimaryKeys($table);
        foreach ($tmpPKeys as $info) {
            if (strcmp($info['column'], $column) === 0) {
                $tmpPKeyName = $info['name'];
                break;
            }
        }
        if (empty($tmpPKeyName))
            throw new \RuntimeException("Migration::BaseMigrator -> dropPrimaryKey: not found primary key name for table \"$table\"!");
        $table = $this->_dbAdapter->quoteTableName($table);
        $this->_dbAdapter->query("ALTER TABLE $table DROP CONSTRAINT ".$this->_dbAdapter->quoteTableName($tmpPKeyName).";");
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
    public function addForeignKey(string $table, array $columns,
                                  string $refTable, array $refColumns,
                                  array $props = []) {
        if (empty($table) || empty($columns)
            || empty($refTable) || empty($refColumns))
            throw new \RuntimeException('Migration::BaseMigrator -> addForeignKey: invalid table name or columns or ref-table name or ref-columns!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> addForeignKey: invalid database adapter (NULL)!');
        $columns = array_filter($columns,'strlen');
        if (empty($columns))
            throw new \RuntimeException('Migration::BaseMigrator -> addForeignKey: invalid columns (Empty)!');
        $refColumns = array_filter($refColumns,'strlen');
        if (empty($refColumns))
            throw new \RuntimeException('Migration::BaseMigrator -> addForeignKey: invalid refColumns (Empty)!');
        $columnNames = implode(', ', $columns);
        $refColumnNames = implode(', ', $refColumns);
        $tmpName = "fk_" . $table . "_" . implode('_', $columns);
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

    /**
     * Добавить вторичный ключ для таблицы, ссылающийся на первичный ключ другой таблицы
     * @param string $table - название таблицы
     * @param string $column - название колонки
     * @param string $refTable - название таблицы на котороу ссылаемся
     * @param array $props - свойства
     *
     * NOTE: данный метод создает вторичный ключ, который будет ссылаться на первичный ключ таблицы $refTable.
     *
     * Supported Props:
     *
     * [bool] on_update - добавить флаг 'ON UPDATE' (может не поддерживаться)
     * [bool] on_delete - добавить флаг 'ON DELETE' (может не поддерживаться)
     * [string] action  - добавить флаг поведения 'NO ACTION / CASCADE / RESTRICT / SET DEFAULT / SET NULL' (может не поддерживаться)
     * [string] name    - задать имя вторичного ключа
     */
    public function addForeignKeyPKey(string $table, string $column,
                                      string $refTable,
                                      array $props = []) {
        $refPKeyColumn = "";
        $tmpColumns = $this->tableColumns($refTable);
        foreach ($tmpColumns as $info) {
            if ($info['is_pk'] === true) {
                $refPKeyColumn = $info['column'];
                break;
            }
        }
        if (empty($refPKeyColumn))
            throw new \RuntimeException("Migration::BaseMigrator -> addForeignKeyPKey: not found primary key for table \"$refTable\"!");
        $this->addForeignKey($table, [ $column ], $refTable, [ $refPKeyColumn ], $props);
    }

    /**
     * Удалить вторичный ключ таблицы
     * @param string $table - название таблицы
     * @param array $columns - названия колонок
     */
    public function dropForeignKey(string $table, array $columns) {
        if (empty($table) || empty($columns))
            throw new \RuntimeException('Migration::BaseMigrator -> dropForeignKey: invalid table name or columns!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> dropForeignKey: invalid database adapter (NULL)!');
        $columns = array_filter($columns,'strlen');
        if (empty($columns))
            throw new \RuntimeException('Migration::BaseMigrator -> dropForeignKey: invalid columns (Empty)!');
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
            throw new \RuntimeException("Migration::BaseMigrator -> dropForeignKey: not found foreign key name for table \"$table\" and columns (\"$columnNames\")!");
        $table = $this->_dbAdapter->quoteTableName($table);
        $this->_dbAdapter->query("ALTER TABLE $table DROP CONSTRAINT ".$this->_dbAdapter->quoteTableName($tmpName).";");
    }

    /**
     * Удалить вторичный ключ таблицы, ссылающийся на первичный ключ другой таблицы
     * @param string $table - название таблицы
     * @param string $column - название колонки
     */
    public function dropForeignKeyPKey(string $table, string $column) {
        if (empty($table) || empty($column))
            throw new \RuntimeException('Migration::BaseMigrator -> dropForeignKeyPKey: invalid table name or column name!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> dropForeignKeyPKey: invalid database adapter (NULL)!');
        $this->dropForeignKey($table, [ $column ]);
    }

    /**
     * Выполнить SQL запрос
     * @param string $sql
     */
    public function execute(string $sql) {
        if (empty($sql))
            throw new \RuntimeException('Migration::BaseMigrator -> execute: invalid SQL (Empty)!');
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> execute: invalid database adapter (NULL)!');
        $this->_dbAdapter->query($sql);
    }

    // --- protected ---

    /**
     * Преобразование типа колонки в тип базы данных
     * @param string $name - имя типа
     * @param int|null $limit - размер данных
     * @return string
     *
     * NOTE: override this method for correct implementation.
     */
    protected function toDatabaseType(string $name, int $limit = null): string {
        if (strcmp($name, 'string') === 0) {
            if (isset($limit))
                return "varchar ($limit)";
            else
                return "text";
        }
        if (isset($limit))
            return "$name ($limit)";
        return $name;
    }

    /**
     * Создать SQL подстроку с базовым значением
     * @param string $default - базовое значение
     * @param string $type - тип данных колонки
     * @return string
     *
     * NOTE: override this method for correct implementation.
     */
    protected function makeDefaultValue(string $default, string $type): string {
        if (strpos($type, "text") !== false
            || strpos($type, "varchar") !== false
            || strpos($type, "character varying") !== false)
            return "DEFAULT ('$default')";

        return "DEFAULT ($default)";
    }

    /**
     * Создать корректный флаг ACTION для reference
     * @param string $action - значение ('NO ACTION / CASCADE / RESTRICT / SET DEFAULT / SET NULL')
     * @return string
     */
    protected function makeReferenceAction(string $action): string {
        $action = strtolower(trim($action));
        if (strcmp($action, 'no action') === 0)
            return "NO ACTION";
        elseif (strcmp($action, 'cascade') === 0)
            return "CASCADE";
        elseif (strcmp($action, 'restrict') === 0)
            return "RESTRICT";
        elseif (strcmp($action, 'set default') === 0)
            return "SET DEFAULT";
        elseif (strcmp($action, 'set null') === 0)
            return "SET NULL";

        return "NO ACTION";
    }

    /**
     * Подготовить SQL для метода CREATE TABLE
     * @param string $table - название таблицы
     * @param array $args - аргументы
     * @return string
     */
    protected function prepareCreateTable(string $table, array $args): string {
        $tmpSql = "";
        $tmpPKey = "";
        $tmpUnique = [];
        $tmpUniqueGroups = [];
        if (!isset($args['id'])
            || (isset($args['id']) && $args['id'] === true)) {
            $tmpPKey = "id";
            $tmpSql .= "id serial not NULL";
        }
        foreach ($args as $key => $value) {
            if (strcmp($key, "id") === 0)
                continue;
            if (!empty($tmpSql))
                $tmpSql .= ",\n";
            if (is_array($value)) {
                // column config array
                $tmpPKeyColumn = "";
                $tmpSql .= $this->prepareCreateColumn($key, $value, $tmpPKeyColumn, $tmpUnique, $tmpUniqueGroups);
                if (empty($tmpPKey))
                    $tmpPKey = $tmpPKeyColumn;
            } else {
                // use as column type
                $tmpType = $this->toDatabaseType($value);
                $tmpSql .= $this->_dbAdapter->quoteTableName($key) . " $tmpType";
            }
        }
        if (!empty($tmpPKey)) {
            $tmpTable = explode('.', $table);
            $tmpName = $tmpTable[count($tmpTable) - 1] . "_pkey";
            $tmpName = $this->_dbAdapter->quoteTableName($tmpName);
            $tmpSql .= ",\nCONSTRAINT $tmpName PRIMARY KEY ($tmpPKey)";
        }
        if (!empty($tmpUnique)) {
            foreach ($tmpUnique as $unique) {
                if (empty($unique))
                    continue;
                $tmpSql .= ",\nUNIQUE ($unique)";
            }
        }
        if (!empty($tmpUniqueGroups)) {
            foreach ($tmpUniqueGroups as $unique) {
                $unique = array_filter($unique,'strlen');
                if (empty($unique))
                    continue;
                $tmpSql .= ",\nUNIQUE (" . implode(', ', $unique) . ")";
            }
        }
        return $tmpSql;
    }

    /**
     * Подготовить SQL для создания колонки
     * @param string $name - название колонки
     * @param array $args - аргументы
     * @param string $pkey - имя первичного ключа
     * @param array $unique - массив уникальных колонок
     * @param array $uniqueGroups - массив уникальных групп колонок
     * @return string
     */
    protected function prepareCreateColumn(string $name,
                                           array $args,
                                           string &$pkey = "",
                                           array &$unique = [],
                                           array &$uniqueGroups = []): string {
        // --- check ---
        if (!isset($args['type']))
            return ""; // TODO throw new \RuntimeException('Migration::BaseMigrator -> prepareCreateColumn: invalid create table args (required column keys: type)!);

        // --- create ---
        $tmpColumnConf = $this->_dbAdapter->quoteTableName($name);
        $tmpLimit = null;
        if (isset($args['limit']))
            $tmpLimit = intval($args['limit']);
        $tmpType = $this->toDatabaseType($args['type'], $tmpLimit);
        $tmpColumnConf .= " $tmpType";
        if (isset($args['null']) && $args['null'] === false)
            $tmpColumnConf .= " NOT NULL";
        if (isset($args['default']))
            $tmpColumnConf .= " ".$this->makeDefaultValue($args['default'], $tmpType);
        if (isset($args['primary_key']) && $args['primary_key'] === true)
            $pkey = $name;
        if (isset($args['unique']) && $args['unique'] === true)
            $unique[] = $name;
        elseif (isset($args['unique_group'])) {
            $tmpArray = [];
            if (array_key_exists($args['unique_group'], $uniqueGroups))
                $tmpArray = $uniqueGroups[$args['unique_group']];
            $tmpArray[] = $name;
            $uniqueGroups[$args['unique_group']] = $tmpArray;
        }
        return $tmpColumnConf;
    }

    /**
     * Добавить индекс для таблицы
     * @param array $args
     */
    protected function addIndexProtected(array $args) {
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> addIndexProtected: invalid database adapter (NULL)!');
        if (empty($args))
            throw new \RuntimeException('Migration::BaseMigrator -> addIndexProtected: invalid args!');
        if (!isset($args['table']) || !isset($args['columns']))
            throw new \RuntimeException('Migration::BaseMigrator -> addIndexProtected: invalid args values (not found \'table\' or \'columns\')!');
        $table = $args['table'];
        $columns = $args['columns'];
        if (is_null($columns) || empty($columns))
            throw new \RuntimeException('Migration::BaseMigrator -> addIndexProtected: invalid columns (Empty)!');
        $columns = array_filter($columns,'strlen');
        if (empty($columns))
            throw new \RuntimeException('Migration::BaseMigrator -> addIndexProtected: invalid columns (Empty)!');
        $columnNames = implode(', ', $columns);
        $tableLst = explode('.', $table);
        $tmpName = $tableLst[count($tableLst) - 1] . "_" . implode('_', $columns) . "_index";
        if (isset($args['name']))
            $tmpName = $args['name'];
        $isUnique = "";
        if (isset($args['unique']) && $args['unique'] === true)
            $isUnique = "UNIQUE";

        $table = $this->_dbAdapter->quoteTableName($table);
        $this->_dbAdapter->query("CREATE $isUnique INDEX ".$this->_dbAdapter->quoteTableName($tmpName)." ON $table ($columnNames);");
    }

    /**
     * Удалить индекс у таблицы
     * @param array $args
     */
    protected function dropIndexProtected(array $args)
    {
        if (is_null($this->_dbAdapter))
            throw new \RuntimeException('Migration::BaseMigrator -> dropIndexProtected: invalid database adapter (NULL)!');
        if (empty($args))
            throw new \RuntimeException('Migration::BaseMigrator -> dropIndexProtected: invalid args!');
        if (!isset($args['table']))
            throw new \RuntimeException('Migration::BaseMigrator -> dropIndexProtected: not found \'table\' in args!');
        if (!isset($args['columns']) && !isset($args['name']))
            throw new \RuntimeException('Migration::BaseMigrator -> dropIndexProtected: not found \'columns\' and \'name\' in args!');
        $table = $args['table'];
        $tmpName = "";
        if (isset($args['columns'])) {
            $columns = array_filter($args['columns'], 'strlen');
            if (empty($columns))
                throw new \RuntimeException('Migration::BaseMigrator -> dropIndexProtected: invalid columns (Empty)!');
            $tableLst = explode('.', $table);
            $tmpName = $tableLst[count($tableLst) - 1] . "_" . implode('_', $columns) . "_index";
        }
        if (isset($args['name']))
            $tmpName = $args['name'];

        $sql = "DROP INDEX";
        if (isset($args['if_exists']) && $args['if_exists'] === true)
            $sql .= " IF EXISTS";
        $sql .= " $tmpName";
        if (isset($args['cascade']) && $args['cascade'] === true)
            $sql .= " CASCADE";

        $this->_dbAdapter->query("$sql;");
    }
}