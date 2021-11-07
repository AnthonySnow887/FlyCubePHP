<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 03.08.21
 * Time: 12:56
 */

namespace FlyCubePHP\Core\Migration;

include_once __DIR__.'/../Database/DatabaseFactory.php';
include_once 'BaseMigrator.php';

use \FlyCubePHP\Core\Database\DatabaseFactory as DatabaseFactory;

abstract class Migration
{
    private $_version = -1;
    private $_dbAdapter = null;
    private $_migrator = null;

    public function __construct(int $version = null) {
        $tmpRef = null;
        try {
            $tmpRef = new \ReflectionClass($this);
        } catch (\Exception $e) {
            echo 'Exception: ',  $e->getMessage(), "\n";
            return;
        }
        $tmpFileName = basename($tmpRef->getFileName());
        unset($tmpRef);
        preg_match('/^([0-9]{14})_.*\.php$/', $tmpFileName, $matches, PREG_OFFSET_CAPTURE);
        if (count($matches) >= 2)
            $this->_version = intval($matches[1][0]);
        elseif (!is_null($version))
            $this->_version = $version;
    }

    public function __destruct() {
        unset($this->_migrator);
        unset($this->_dbAdapter);
    }

    /**
     * Является ли объект миграции корректным
     * @return bool
     */
    final public function isValid(): bool {
        return ($this->_version > 0 && strlen(strval($this->_version)) === 14);
    }

    /**
     * Версия миграции
     * @return int
     */
    final public function version(): int {
        return $this->_version;
    }

    /**
     * Выполнить миграцию
     * @param int $version
     * @param string $migratorClassName
     * @param bool $showOutput
     * @param string $outputDelimiter
     * @return bool
     */
    final public function migrate(int $version,
                                  string $migratorClassName,
                                  bool $showOutput,
                                  string $outputDelimiter): bool {
        // --- check caller function ---
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($dbt[1]['function']) ? $dbt[1]['function'] : null;
        if (is_null($caller))
            return false; // TODO throw new \RuntimeException('Migration: Not found caller function!);
        if (strcmp($caller, "migrate") !== 0
            && strcmp($caller, "rollback") !== 0
            && strcmp($caller, "schemaLoad") !== 0)
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

        if ($version >= $this->_version)
            $this->up();
        else
            $this->down();

        $res = $this->_dbAdapter->commitTransaction();
        unset($this->_migrator);
        unset($this->_dbAdapter);
        return $res;
    }

    // --- protected ---

    /**
     * Внесение изменений миграции
     * @return mixed
     */
    abstract protected function up();

    /**
     * Удалений изменений миграции
     * @return mixed
     */
    abstract protected function down();

    // --- default protected ---

    /**
     * Название адаптера для работы с базой данных
     * @return string
     */
    final protected function adapterName(): string {
        if (!is_null($this->_dbAdapter))
            return $this->_dbAdapter->name();

        return "";
    }

    /**
     * Создать новую схему данных
     * @param string $name - имя
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [bool] if_not_exists - добавить флаг 'IF NOT EXISTS'
     */
    final protected function createSchema(string $name, array $props = []) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> createSchema: invalid database migrator (NULL)!');
        $this->_migrator->createSchema($name, $props);
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
    final protected function dropSchema(string $name, array $props = []) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> dropSchema: invalid database migrator (NULL)!');
        $this->_migrator->dropSchema($name, $props);
    }

    /**
     * Создать таблицу
     * @param string $name - название
     * @param array $args - массив колонок и их спецификация
     *
     * Supported Keys:
     *
     * [bool]     if_not_exists  - добавить флаг 'IF NOT EXISTS' (только для таблицы)
     * [bool]     id             - использовать колонку ID или нет
     * [string]   type           - тип данных колонки (обязательный)
     * [integer]  limit          - размер данных колонки
     * [bool]     null           - может ли быть NULL
     * [string]   default        - базовое значение
     * [bool]     primary_key    - использовать как первичный ключ
     * [bool]     unique         - является уникальным
     * [string]   unique_group   - является уникальной группой (значение: имя группы)
     *
     * createTable('test', [ 'id' => false,
     *                       'if_not_exists' => true,
     *                       'my_id' => [ 'type' => 'integer', 'null' => false, 'primary_key' => true ],
     *                       'my_data' => [ 'type' => 'string', 'limit' => 128, 'default' => '' ]
     * ]);
     *
     */
    final protected function createTable(string $name, array $args) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> createTable: invalid database migrator (NULL)!');
        $this->_migrator->createTable($name, $args);
    }

    /**
     * Переименовать таблицу
     * @param string $name - имя
     * @param string $newName - новое имя
     */
    final protected function renameTable(string $name, string $newName) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> renameTable: invalid database migrator (NULL)!');
        $this->_migrator->renameTable($name, $newName);
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
     *
     */
    final protected function dropTable(string $name, array $props = []) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> dropTable: invalid database migrator (NULL)!');
        $this->_migrator->dropTable($name, $props);
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
    final protected function addColumn(string $table, string $column, array $props = []) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> addColumn: invalid database migrator (NULL)!');
        $this->_migrator->addColumn($table, $column, $props);
    }

    /**
     * Переименовать колонку в таблице
     * @param string $table - название таблицы
     * @param string $column - название колонки
     * @param string $newName - новое название колонки
     */
    final protected function renameColumn(string $table, string $column, string $newName) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> renameColumn: invalid database migrator (NULL)!');
        $this->_migrator->renameColumn($table, $column, $newName);
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
    final protected function changeColumn(string $table, string $column, string $type, array $props = []) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> changeColumn: invalid database migrator (NULL)!');
        $this->_migrator->changeColumn($table, $column, $type, $props);
    }

    /**
     * Изменить/Удалить секцию DEFAULT у колонки
     * @param string $table - название таблицы
     * @param string $column - название колонки
     * @param $default - значение секции DEFAULT (если null -> секция DEFAULT удаляется)
     */
    final protected function changeColumnDefault(string $table, string $column, $default = null) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> changeColumnDefault: invalid database migrator (NULL)!');
        $this->_migrator->changeColumnDefault($table, $column, $default);
    }

    /**
     * Добавить/Удалить секцию NOT NULL у колонки
     * @param string $table - название таблицы
     * @param string $column - название колонки
     * @param bool $notNull - значение секции (если false - секция NOT NULL удаляется)
     */
    final protected function changeColumnNull(string $table, string $column, $notNull = false) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> changeColumnNull: invalid database migrator (NULL)!');
        $this->_migrator->changeColumnNull($table, $column, $notNull);
    }

    /**
     * Удалить колонку из таблицы.
     * @param string $table  - название таблицы
     * @param string $column - название колонки
     */
    final protected function dropColumn(string $table, string $column) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> removeColumn: invalid database migrator (NULL)!');
        $this->_migrator->dropColumn($table, $column);
    }

    /**
     * Добавить индекс для таблицы
     * @param string $table - название таблицы
     * @param array $column - названия колонок
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [string] name - имя индекса (необязательное)
     * [bool]   unique  - является ли уникальным
     */
    final protected function addIndex(string $table, array $column, array $props = []) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> addIndex: invalid database migrator (NULL)!');
        $this->_migrator->addIndex($table, $column, $props);
    }

    /**
     * Переименовать индекс для таблицы
     * @param string $table - название таблицы
     * @param string $oldName - старое название
     * @param string $newName - новое название
     */
    /*public*/final protected function renameIndex(string $table, string $oldName, string $newName) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> renameIndex: invalid database migrator (NULL)!');
        $this->_migrator->renameIndex($table, $oldName, $newName);
    }

    /**
     * Удалить индекс таблицы
     * @param string $table - название таблицы
     * @param array $props - свойства
     *
     * Supported Props:
     *
     * [string] column  - имя колонки
     * [string] name    - имя индекса
     * [bool] if_exists - добавить флаг 'IF EXISTS' (может не поддерживаться)
     * [bool] cascade   - добавить флаг 'CASCADE' (может не поддерживаться)
     *
     * NOTE: Должен быть задан хотябы один!
     * NOTE: Приоритет отдается name!
     */
    final protected function dropIndex(string $table, array $props = []) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> removeIndex: invalid database migrator (NULL)!');
        $this->_migrator->dropIndex($table, $props);
    }

    /**
     * Установить новый первичный ключ таблицы
     * @param string $table - название таблицы
     * @param string $column - название колонки
     */
    /*public*/final protected function setPrimaryKey(string $table, string $column) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> setPrimaryKey: invalid database migrator (NULL)!');
        $this->_migrator->setPrimaryKey($table, $column);
    }

    /**
     * Удалить первичный ключ таблицы
     * @param string $table - название таблицы
     * @param string $column - название колонки
     */
    /*public*/final protected function dropPrimaryKey(string $table, string $column) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> dropPrimaryKey: invalid database migrator (NULL)!');
        $this->_migrator->dropPrimaryKey($table, $column);
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
    final protected function addForeignKey(string $table, array $columns,
                                           string $refTable, array $refColumns,
                                           array $props = []) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> addForeignKey: invalid database migrator (NULL)!');
        $this->_migrator->addForeignKey($table, $columns, $refTable, $refColumns, $props);
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
     *
     */
    final protected function addForeignKeyPKey(string $table, string $column,
                                               string $refTable,
                                               array $props = []) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> addForeignKeyPKey: invalid database migrator (NULL)!');
        $this->_migrator->addForeignKeyPKey($table, $column, $refTable, $props);
    }

    /**
     * Удалить вторичный ключ таблицы
     * @param string $table - название таблицы
     * @param array $columns - названия колонок
     */
    final protected function dropForeignKey(string $table, array $columns) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> dropForeignKey: invalid database migrator (NULL)!');
        $this->_migrator->dropForeignKey($table, $columns);
    }

    /**
     * Удалить вторичный ключ таблицы, ссылающийся на первичный ключ другой таблицы
     * @param string $table - название таблицы
     * @param string $column - название колонки
     */
    final protected function dropForeignKeyPKey(string $table, string $column) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> dropForeignKeyPKey: invalid database migrator (NULL)!');
        $this->_migrator->dropForeignKeyPKey($table, $column);
    }

    /**
     * Выполнить SQL запрос
     * @param string $sql
     */
    final protected function execute(string $sql) {
        if (is_null($this->_migrator))
            return; // TODO throw new \RuntimeException('Migration -> execute: invalid database migrator (NULL)!');
        $this->_migrator->execute($sql);
    }
}