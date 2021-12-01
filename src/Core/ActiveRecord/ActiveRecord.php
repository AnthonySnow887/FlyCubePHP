<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 02.08.21
 * Time: 14:35
 */

namespace FlyCubePHP\Core\ActiveRecord;

include_once __DIR__.'/../Database/DatabaseFactory.php';
include_once __DIR__.'/../../HelperClasses/CoreHelper.php';
include_once __DIR__.'/../Error/ErrorActiveRecord.php';

use FlyCubePHP\Core\Database\BaseDatabaseAdapter;
use FlyCubePHP\Core\Error\ErrorActiveRecord;
use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;
use \FlyCubePHP\Core\Database\DatabaseFactory as DatabaseFactory;
use \FlyCubePHP\Core\Error\ErrorDatabase as ErrorDatabase;

abstract class ActiveRecord
{
    private $_tableName = "";
    private $_primaryKey = "id";
    private $_newRecord = true;
    private $_dataHash = array();
    private $_columnMappings = array();
    private $_passwordColumn = "password";

    private $_callbacks = [];
    private $_readOnly = false;

    /**
     * ActiveRecord constructor.
     * @throws ErrorActiveRecord
     */
    public function __construct() {
        $this->_tableName = CoreHelper::underscore($this->objectName());
        $this->beforeSave('preparePassword');
    }

    final public function __set(string $name, $value) {
        $tmpName = CoreHelper::camelcase($name, false);

        // --- check caller && call before-set ---
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        if (isset($trace[1]['class'])
            && strcmp($trace[1]['class'], 'FlyCubePHP\Core\ActiveRecord\ActiveRecord') === 0) {
            $value = $this->beforeSet($name, $value);
        }
        $this->$tmpName = $value;
        if (!array_key_exists($tmpName, $this->_dataHash))
            $this->_dataHash[$tmpName] = $value;
    }

    /**
     * Имя текущей таблицы
     * @return string
     */
    final protected function tableName(): string {
        return $this->_tableName;
    }

    /**
     * Задать имя текущей таблицы
     * @param string $name
     */
    final protected function setTableName(string $name) {
        $this->_tableName = $name;
    }

    /**
     * Имя колонки первичного ключа
     * @return string
     */
    final protected function primaryKey(): string {
        return $this->_primaryKey;
    }

    /**
     * Задать имя колонки первичного ключа
     * @param string $name
     */
    final protected function setPrimaryKey(string $name) {
        $this->_primaryKey = $name;
    }

    /**
     * Задано ли сопоставление параметра класса реальному названию колонки в таблице
     * @param string $classParam
     * @return bool
     */
    final protected function hasColumnMapping(string $classParam): bool {
        return array_key_exists($classParam, $this->_columnMappings);
    }

    /**
     * Сопоставление параметра класса реальному названию колонки в таблице
     * @param string $classParam
     * @return string
     */
    final protected function columnMapping(string $classParam): string {
        if (array_key_exists($classParam, $this->_columnMappings))
            return $this->_columnMappings[$classParam];
        return "";
    }

    /**
     * Массив сопоставлений параметров класса реальным названиям колонок в таблице
     * @return array
     */
    final protected function columnMappings():array {
        return $this->_columnMappings;
    }

    /**
     * Задать сопоставление параметра класса реальному названию колонки в таблице
     * @param string $classParam
     * @param string $columnName
     */
    final protected function setColumnMapping(string $classParam, string $columnName) {
        if (empty($classParam) || empty($columnName))
            return;
        $this->_columnMappings[$classParam] = $columnName;
    }

    /**
     * Задать сопоставление параметров класса реальным названиям колонок в таблице
     * @param array $columnMappings
     *
     * ПРИМЕЧАНИЕ:
     * $columnMappings должен иметь следующий вид:
     *   * key    - название параметра класса
     *   * value  - название колнки в таблице
     *
     * Example:
     * setColumnMappings([ 'name' => 'name_', 'password' => 'password_' ])
     */
    final protected function setColumnMappings(array $columnMappings) {
        if (empty($columnMappings))
            return;
        $this->_columnMappings = $columnMappings;
    }

    /**
     * Имя колонки с паролем
     * @return string
     */
    final protected function passwordColumn(): string {
        return $this->_passwordColumn;
    }

    /**
     * Задать имя колонки с паролем
     * @param string $name
     */
    final protected function setPasswordColumn(string $name) {
        $this->_passwordColumn = $name;
    }

    /**
     * Находится ли объект класса в режиме "Только чтение"
     * @return bool
     *
     * NOTE: Call to the save/destroy functions with the specified "read-only" flag will trigger an error!
     */
    final protected function isReadOnly(): bool {
        return $this->_readOnly;
    }

    /**
     * Задать режим "Только чтение" для объекта класса
     * @param bool $value
     *
     * NOTE: Call to the save/destroy functions with the specified "read-only" flag will trigger an error!
     */
    final protected function setReadOnly(bool $value) {
        $this->_readOnly = $value;
    }

    /**
     * Сохранить/Обновить объект в БД
     * @throws
     *
     * ПРИМЕЧАНИЕ:
     * Колонка с паролем автоматически преобразуется в хэш перед сохранением / обновлением в БД.
     */
    final public function save() {
        if ($this->isReadOnly())
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Trying to save a read-only object!',
                'active-r-class' => $this->objectName(),
                'active-r-method' => __FUNCTION__
            ]);
        $this->processingCallbacks('before-save');
        if ($this->_newRecord === true)
            $this->insert();
        else
            $this->update();
        $this->processingCallbacks('after-save');
    }

    /**
     * Удалить объект из БД
     * @throws
     */
    final public function destroy() {
        if ($this->isReadOnly())
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Trying to destroy a read-only object!',
                'active-r-class' => $this->objectName(),
                'active-r-method' => __FUNCTION__
            ]);
        if ($this->_newRecord === true)
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Trying to destroy an object not present in the database!',
                'active-r-class' => $this->objectName(),
                'active-r-method' => __FUNCTION__
            ]);
        $this->processingCallbacks('before-destroy');
        $this->delete();
        $this->processingCallbacks('after-destroy');
    }

    /**
     * Проверка авторизации
     * @param string $plainPassword - нешифрованый пароль
     * @return bool
     *
     * ПРИМЕЧАНИЕ:
     * Проверка выполняется, если в классе присутствует параметр с паролем.
     * Иначе - false.
     */
    final public function isAuthenticated(string $plainPassword): bool {
        $tmpName = CoreHelper::camelcase($this->_passwordColumn, false);
        if (!isset($this->$tmpName))
            return false;
        return password_verify($plainPassword, $this->$tmpName);
    }

    // --- callbacks ---

    /**
     * Добавить before-save callback
     * @param string $method
     * @throws ErrorActiveRecord
     */
    final protected function beforeSave(string $method) {
        $this->appendCallback('before-save', $method);
    }

    /**
     * Добавить after-save callback
     * @param string $method
     * @throws ErrorActiveRecord
     */
    final protected function afterSave(string $method) {
        $this->appendCallback('after-save', $method);
    }

    /**
     * Добавить before-insert callback
     * @param string $method
     * @throws ErrorActiveRecord
     */
    final protected function beforeInsert(string $method) {
        $this->appendCallback('before-insert', $method);
    }

    /**
     * Добавить after-insert callback
     * @param string $method
     * @throws ErrorActiveRecord
     */
    final protected function afterInsert(string $method) {
        $this->appendCallback('after-insert', $method);
    }

    /**
     * Добавить before-update callback
     * @param string $method
     * @throws ErrorActiveRecord
     */
    final protected function beforeUpdate(string $method) {
        $this->appendCallback('before-update', $method);
    }

    /**
     * Добавить after-update callback
     * @param string $method
     * @throws ErrorActiveRecord
     */
    final protected function afterUpdate(string $method) {
        $this->appendCallback('after-update', $method);
    }

    /**
     * Добавить before-destroy callback
     * @param string $method
     * @throws ErrorActiveRecord
     *
     * NOTE: destroy from the database, not destroy a class object!
     */
    final protected function beforeDestroy(string $method) {
        $this->appendCallback('before-destroy', $method);
    }

    /**
     * Добавить after-destroy callback
     * @param string $method
     * @throws ErrorActiveRecord
     *
     * NOTE: destroy from the database, not destroy a class object!
     */
    final protected function afterDestroy(string $method) {
        $this->appendCallback('after-destroy', $method);
    }

    // --- specific functions ---

    /**
     * Метод предобработки добавляемых данных (aka __set with return)
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    protected function beforeSet(string $name, $value) {
        return $value;
    }

    // --- static ---

    /**
     * Запрос всех объектов из таблицы
     * @return array
     * @throws
     */
    final public static function all(): array {
        $db = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($db))
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Database connector is NULL!',
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__
            ]);

        $aClassName = static::class;
        $aRec = new $aClassName();
        $tName = $aRec->tableName();
        unset($aRec);
        try {
            $res = $db->query("SELECT * FROM ".$db->quoteTableName($tName).";", [], static::class);
        } catch (ErrorDatabase $ex) {
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => $ex->getMessage(),
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__,
                'error-database' => $ex
            ]);
        }
        foreach ($res as &$r)
            $r->_newRecord = false;
        return $res;
    }

    /**
     * Удалить все объекты из таблицы
     * @throws
     */
    final public static function destroyAll() {
        $db = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($db))
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Database connector is NULL!',
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__
            ]);

        $aClassName = static::class;
        $aRec = new $aClassName();
        $tName = $aRec->tableName();
        unset($aRec);
        try {
            $db->query("DELETE FROM ".$db->quoteTableName($tName).";");
        } catch (ErrorDatabase $ex) {
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => $ex->getMessage(),
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__,
                'error-database' => $ex
            ]);
        }
    }

    /**
     * Запрос первого объекта из таблицы
     * @return ActiveRecord|null
     * @throws
     */
    final public static function first()/*: ActiveRecord|null*/ {
        try {
            $res = self::limit(1);
        } catch (ErrorActiveRecord $ex) {
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => $ex->getMessage(),
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__,
                'error-database' => $ex->errorDatabase()
            ]);
        }
        if (!empty($res))
            return $res[0];
        return null;
    }

    /**
     * Запросить определенное количество объектов из БД
     * @param int $num
     * @return array|null
     * @throws
     */
    final public static function limit(int $num)/*: array|null*/ {
        $db = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($db))
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Database connector is NULL!',
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__
            ]);

        $aClassName = static::class;
        $aRec = new $aClassName();
        $tName = $aRec->tableName();
        unset($aRec);
        try {
            $res = $db->query("SELECT * FROM ".$db->quoteTableName($tName)." LIMIT $num", [], static::class);
        } catch (ErrorDatabase $ex) {
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => $ex->getMessage(),
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__,
                'error-database' => $ex
            ]);
        }
        foreach ($res as &$r)
            $r->_newRecord = false;
        return $res;
    }

    /**
     * Запросить количество объектов в БД
     * @return int
     * @throws
     */
    final public static function count(): int {
        $db = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($db))
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Database connector is NULL!',
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__
            ]);

        $aClassName = static::class;
        $aRec = new $aClassName();
        $tName = $aRec->tableName();
        unset($aRec);
        try {
            $res = $db->query("SELECT COUNT(*) AS count FROM ".$db->quoteTableName($tName).";");
        } catch (ErrorDatabase $ex) {
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => $ex->getMessage(),
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__,
                'error-database' => $ex
            ]);
        }
        if (!empty($res))
            return intval($res[0]->count);
        return 0;
    }

    /**
     * Поиск объекта в БД по значению первичного ключа
     * @param $pkVal
     * @return ActiveRecord|null
     * @throws
     */
    final public static function find($pkVal)/*: ActiveRecord|null*/ {
        $aClassName = static::class;
        $aRec = new $aClassName();
        $tPK = $aRec->primaryKey();
        unset($aRec);
        try {
            $res = self::where([ $tPK => $pkVal ]);
        } catch (ErrorActiveRecord $ex) {
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => $ex->getMessage(),
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__,
                'error-database' => $ex->errorDatabase()
            ]);
        }
        if (!empty($res))
            return $res[0];
        return null;
    }

    /**
     * Поиск объекта в БД по значению произвольной колонки
     * @param string $column
     * @param $val
     * @param bool $prepareNames - автоматический перевод имен колонок в underscore
     * @return array|null
     * @throws
     */
    final public static function findBy(string $column, $val, bool $prepareNames = true) {
        try {
            $res = self::where([ $column => $val ], $prepareNames);
        } catch (ErrorActiveRecord $ex) {
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => $ex->getMessage(),
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__,
                'error-database' => $ex->errorDatabase()
            ]);
        }
        return $res;
    }

    /**
     * Поиск объекта в БД используя произвольный SQL
     * @param string $sql - SQL запрос
     * @param array $params - массив параметров запроса с их значениями
     * @return array|null
     * @throws
     */
    final public static function findBySql(string $sql, array $params = []) {
        $db = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($db))
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Database connector is NULL!',
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__
            ]);

        try {
            $res = $db->query($sql, $params, static::class);
        } catch (ErrorDatabase $ex) {
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => $ex->getMessage(),
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__,
                'error-database' => $ex
            ]);
        }
        foreach ($res as &$r)
            $r->_newRecord = false;
        return $res;
    }

    /**
     * Запрос объектов с фильтрацией
     * @param array $args
     * @param bool $prepareNames - автоматический перевод имен в underscore
     * @return array|null
     * @throws
     *
     * ActiveRecord::where([ 'user_name' => 'test', 'user_type' => '12345' ])
     *
     */
    final public static function where(array $args, bool $prepareNames = true) {
        $db = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($db))
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Database connector is NULL!',
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__
            ]);

        $aClassName = static::class;
        $aRec = new $aClassName();
        $tName = $aRec->tableName();
        unset($aRec);
        $tmpWhere = "";
        $tmpWhereVal = array();
        foreach ($args as $key => $val) {
            $tmpKey = CoreHelper::underscore($key);
            if (!empty($tmpWhere))
                $tmpWhere .= " AND";
            $tmpColumn = $key;
            if ($prepareNames === true)
                $tmpColumn = CoreHelper::underscore($key);
            $tmpWhere .= " ".$db->quoteTableName("$tName.$tmpColumn")." = :key_$tmpKey";
            $tmpWhereVal[":key_".$tmpKey] = $val;
        }
        $tmpWhere = trim($tmpWhere);
        try {
            $res = $db->query("SELECT * FROM ".$db->quoteTableName($tName)." WHERE $tmpWhere;", $tmpWhereVal, static::class);
        } catch (ErrorDatabase $ex) {
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => $ex->getMessage(),
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__,
                'error-database' => $ex
            ]);
        }
        foreach ($res as &$r)
            $r->_newRecord = false;
        return $res;
    }

    /**
     * Проверка наличия объекта в БД по первичному ключу
     * @param $pkVal
     * @return bool
     * @throws
     */
    final public static function exists($pkVal): bool {
        $db = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($db))
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Database connector is NULL!',
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__
            ]);

        $aClassName = static::class;
        $aRec = new $aClassName();
        $tName = $aRec->tableName();
        $tPK = $aRec->primaryKey();
        unset($aRec);
        try {
            $res = $db->query("SELECT 1 AS one FROM ".$db->quoteTableName($tName)." WHERE ".$db->quoteTableName("$tName.$tPK")." = :f_value LIMIT 1;", [ ":f_value" => $pkVal ]);
        } catch (ErrorDatabase $ex) {
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => $ex->getMessage(),
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__,
                'error-database' => $ex
            ]);
        }
        if (!empty($res))
            return CoreHelper::toBool($res[0]->one);
        return false;
    }

    /**
     * Проверка наличия объекта в БД по произвольной колонке и ее значений
     * @param string $column
     * @param array $values
     * @param bool $prepareNames - автоматический перевод имен в underscore
     * @return bool
     * @throws
     *
     * Вернет true если из набора значений в БД есть хотя бы одно.
     */
    final public static function existsSome(string $column, array $values, bool $prepareNames = true): bool {
        $db = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($db))
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Database connector is NULL!',
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__
            ]);

        $aClassName = static::class;
        $aRec = new $aClassName();
        $tName = $aRec->tableName();
        unset($aRec);
        $tmpWhere = "";
        $tmpWhereVal = array();
        foreach ($values as $key => $val) {
            $tmpKey = CoreHelper::underscore($key);
            if (!empty($tmpWhere))
                $tmpWhere .= ",";
            $tmpWhere .= " :key_$tmpKey";
            $tmpWhereVal[":key_$tmpKey"] = $val;
        }
        $tmpWhere = trim($tmpWhere);
        $tmpColumn = $column;
        if ($prepareNames === true)
            $tmpColumn = CoreHelper::underscore($column);
        try {
            $res = $db->query("SELECT 1 AS one FROM ".$db->quoteTableName($tName)." WHERE ".$db->quoteTableName("$tName.$tmpColumn")." IN ($tmpWhere) LIMIT 1;", $tmpWhereVal);
        } catch (ErrorDatabase $ex) {
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => $ex->getMessage(),
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__,
                'error-database' => $ex
            ]);
        }
        if (!empty($res))
            return CoreHelper::toBool($res[0]->one);
        return false;
    }

    /**
     * Запрос произвольного набора колонок для объекта
     * @param array $columns - имена необходимых колонок
     * @param bool $prepareNames - автоматический перевод имен в underscore
     * @return array|null
     * @throws
     */
    final public static function selectSome(array $columns, bool $prepareNames = true) {
        $db = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($db))
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Database connector is NULL!',
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__
            ]);

        $aClassName = static::class;
        $aRec = new $aClassName();
        $tName = $aRec->tableName();
        unset($aRec);
        $tmpColumns = "";
        foreach ($columns as $key => $val) {
            if (!empty($tmpColumns))
                $tmpColumns .= ",";
            $tmpVal = $val;
            if ($prepareNames === true)
                $tmpVal = CoreHelper::underscore($val);
            $tmpColumns .= " ".$db->quoteTableName($tmpVal);
        }
        $tmpColumns = trim($tmpColumns);
        try {
            $res = $db->query("SELECT $tmpColumns FROM ".$db->quoteTableName($tName).";", [], static::class);
        } catch (ErrorDatabase $ex) {
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => $ex->getMessage(),
                'active-r-class' => static::class,
                'active-r-method' => __FUNCTION__,
                'error-database' => $ex
            ]);
        }
        foreach ($res as &$r)
            $r->_newRecord = false;
        return $res;
    }

    // --- private ---

    /**
     * Имя объекта класса
     * @return string
     * @throws
     */
    final private function objectName(): string {
        $tmpRef = null;
        try {
            $tmpRef = new \ReflectionClass($this);
        } catch (\Exception $e) {
            throw new ErrorActiveRecord(__CLASS__, __FUNCTION__, $e->getMessage());
        }
        $tmpName = $tmpRef->getName();
        unset($tmpRef);
        return $tmpName;
    }

    /**
     * Свойства объекта класса
     * @return array
     * @throws
     */
    final private function objectProperties(): array {
        $tmpRef = null;
        try {
            $tmpRef = new \ReflectionClass($this);
        } catch (\Exception $e) {
            throw new ErrorActiveRecord(__CLASS__, __FUNCTION__, $e->getMessage());
        }
        $properties = $tmpRef->getProperties(\ReflectionProperty::IS_PUBLIC);
        unset($tmpRef);
        $tmpProperties = array();
        foreach ($properties as $prop) {
            $propName = $prop->getName();
            $tmpProperties[$propName] = $this->$propName;
        }
        return $tmpProperties;
    }

    /**
     * Выполнение запроса SQL INSERT
     * @throws
     */
    final private function insert() {
        $this->processingCallbacks('before-insert');
        $db = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($db))
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Database connector is NULL!',
                'active-r-class' => $this->objectName(),
                'active-r-method' => __FUNCTION__
            ]);

        $dataColumns = array();
        $dataValues = array();
        $this->prepareData($db, $dataColumns, $dataValues);
        if (empty($dataValues))
            return; // no changed
        $dataValuesKeys = array_keys($dataValues);
        $tName = $this->tableName();
        try {
            $res = $db->query("INSERT INTO ".$db->quoteTableName($tName)." (" . implode(', ', $dataColumns) . ") VALUES (" . implode(', ', $dataValuesKeys) . ") RETURNING ".$db->quoteTableName($this->_primaryKey).";", $dataValues);
            if (count($res) === 1) {
                $tmpName = $this->_primaryKey;
                $this->__set($this->_primaryKey, $res[0]->$tmpName);
            }
        } catch (ErrorDatabase $ex) {
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => $ex->getMessage(),
                'active-r-class' => $this->objectName(),
                'active-r-method' => __FUNCTION__,
                'error-database' => $ex
            ]);
        }
        $this->processingCallbacks('after-insert');
    }

    /**
     * Выполнение запроса SQL UPDATE
     * @throws
     */
    final private function update() {
        $this->processingCallbacks('before-update');
        $db = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($db))
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Database connector is NULL!',
                'active-r-class' => $this->objectName(),
                'active-r-method' => __FUNCTION__
            ]);

        $dataColumns = array();
        $dataValues = array();
        $dataValues4Upd = array();
        $this->prepareData($db, $dataColumns, $dataValues, $dataValues4Upd);
        if (empty($dataValues4Upd))
            return; // no changed
        $tName = $this->tableName();
        $tPK = $this->primaryKey();
        $dataValues[":pk_value"] = $this->preparePKeyValue();
        try {
            $db->query("UPDATE ".$db->quoteTableName($tName)." SET " . implode(',', $dataValues4Upd) . " WHERE ".$db->quoteTableName("$tName.$tPK")." = :pk_value;", $dataValues);
        } catch (ErrorDatabase $ex) {
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => $ex->getMessage(),
                'active-r-class' => $this->objectName(),
                'active-r-method' => __FUNCTION__,
                'error-database' => $ex
            ]);
        }
        $this->processingCallbacks('after-update');
    }

    /**
     * Выполнение запроса SQL DELETE
     * @throws
     */
    final private function delete() {
        $db = DatabaseFactory::instance()->createDatabaseAdapter();
        if (is_null($db))
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Database connector is NULL!',
                'active-r-class' => $this->objectName(),
                'active-r-method' => __FUNCTION__
            ]);

        $tName = $this->tableName();
        $tPK = $this->primaryKey();
        $dataValues = array();
        $dataValues[":pk_value"] = $this->preparePKeyValue();
        try {
            $db->query("DELETE FROM ".$db->quoteTableName($tName)." WHERE ".$db->quoteTableName("$tName.$tPK")." = :pk_value;", $dataValues);
        } catch (ErrorDatabase $ex) {
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => $ex->getMessage(),
                'active-r-class' => $this->objectName(),
                'active-r-method' => __FUNCTION__,
                'error-database' => $ex
            ]);
        }
        $this->_newRecord = true;
    }

    /**
     * Метод подготовки данных для запросов
     * @param BaseDatabaseAdapter $db - адапрет доступа к БД
     * @param array $dataColumns - названия колонок
     * @param array $dataValues - массив связки ключей и их значений
     * @param array $dataValues4Upd - названия ключей и значений для UPDATE
     * @throws
     */
    final private function prepareData(BaseDatabaseAdapter &$db,
                                       array &$dataColumns,
                                       array &$dataValues,
                                       array &$dataValues4Upd = []) {
        $tmpPassName = CoreHelper::camelcase($this->_passwordColumn, false);
        foreach ($this->_dataHash as $key => $value) {
            $tmpValue = $this->$key;
            if ($this->_newRecord === false
                && $value === $tmpValue)
                continue; // skip not changed value

            // --- prepare ---
            $tmpKey = CoreHelper::underscore($key);
            $tmpName = ":" . $tmpKey . "_value";

            $tmpColumnName = $tmpKey;
            if ($this->hasColumnMapping($key) === true)
                $tmpColumnName = $this->columnMapping($key);

            $dataColumns[] = $tmpColumnName;
            $dataValues4Upd[] = $db->quoteTableName($tmpColumnName)." = $tmpName";
            $dataValues[$tmpName] = $tmpValue;
        }
        $objectProps = $this->objectProperties();
        foreach ($objectProps as $key => $value) {
            $tmpValue = $this->$key;
            if ($this->_newRecord === false
                && array_key_exists($key, $this->_dataHash)
                && $this->_dataHash[$key] === $tmpValue)
                continue; // skip not changed value

            // --- prepare ---
            $tmpKey = CoreHelper::underscore($key);
            $tmpName = ":" . $tmpKey . "_value";

            $tmpColumnName = $tmpKey;
            if ($this->hasColumnMapping($key) === true)
                $tmpColumnName = $this->columnMapping($key);

            $dataColumns[] = $tmpColumnName;
            $dataValues4Upd[] = $db->quoteTableName($tmpColumnName)." = $tmpName";
            $dataValues[$tmpName] = $tmpValue;
        }
    }

    /**
     * Метод подготовки значений первичного ключа
     * @return mixed
     */
    final private function preparePKeyValue() {
        $tPK = $this->primaryKey();
        $tPKParam = CoreHelper::camelcase($tPK, false);
        if (array_key_exists($tPKParam, $this->_dataHash))
            return $this->_dataHash[$tPKParam];
        return $this->$tPKParam;
    }

    /**
     * Метод подготовки пароля к сохранению в БД
     */
    final private function preparePassword() {
        $tmpPassName = CoreHelper::camelcase($this->_passwordColumn, false);
        if (!isset($this->$tmpPassName))
            return;
        $tmpValue = $this->$tmpPassName;
        if ($this->_newRecord === false
            && array_key_exists($tmpPassName, $this->_dataHash)
            && $this->_dataHash[$tmpPassName] === $tmpValue)
            return; // skip not changed value
        $this->$tmpPassName = $this->encryptPassword($tmpValue);
    }

    /**
     * Выполнить преобразование пароля
     * @param string $val
     * @return string
     */
    final private function encryptPassword(string $val): string {
        return password_hash($val, PASSWORD_DEFAULT);
    }

    /**
     * Добавить колбэк функцию обратоки
     * @param string $callbackType
     * @param string $method
     * @throws ErrorActiveRecord
     */
    final private function appendCallback(string $callbackType, string $method) {
        if (empty($method) || !method_exists($this, $method))
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => "Append $callbackType failed! Not found callback method (name: $method)!",
                'active-r-class' => $this->objectName(),
                'active-r-method' => __FUNCTION__,
                'backtrace-shift' => 2
            ]);
        $callbackArr = [ $method ];
        if (!in_array($callbackType, $this->_callbacks))
            $this->_callbacks[$callbackType] = $callbackArr;
        else
            $this->_callbacks[$callbackType] = array_merge($this->_callbacks[$callbackType], $callbackArr);
    }

    /**
     * Вызвать колбэк функции обратоки
     * @param string $callbackType
     */
    final private function processingCallbacks(string $callbackType) {
        if (!isset($this->_callbacks[$callbackType]))
            return;
        foreach ($this->_callbacks[$callbackType] as $method)
            $this->$method();
    }
}