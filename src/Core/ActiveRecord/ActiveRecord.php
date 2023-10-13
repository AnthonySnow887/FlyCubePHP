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
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\Database\DatabaseFactory;
use FlyCubePHP\Core\Error\ErrorDatabase;
use FlyCubePHP\Core\ActiveRecord\ActiveRecordTransactionHelper;

abstract class ActiveRecord
{
    private $_tableName = "";
    private $_primaryKey = "id";
    private $_newRecord = true;
    private $_data = array();
    private $_dataHash = array();
    private $_columnMappings = array();
    private $_passwordColumn = "password";

    private $_callbacks = [];
    private $_readOnly = false;

    private $_database = "";

    /**
     * ActiveRecord constructor.
     * @throws ErrorActiveRecord
     */
    public function __construct() {
        $this->_tableName = CoreHelper::underscore($this->objectName());
        $this->beforeSave('preparePassword');
    }

    final public function &__get(string $name) {
        $tmpName = CoreHelper::camelcase($name, false);
        return $this->_data[$tmpName];
    }

    final public function __isset(string $name) {
        $tmpName = CoreHelper::camelcase($name, false);
        return isset($this->_data[$tmpName]);
    }

    final public function __set(string $name, $value) {
        $tmpName = CoreHelper::camelcase($name, false);
        // --- call before-set ---
        $value = $this->beforeSet($tmpName, $value);
        // --- save value ---
        $this->_data[$tmpName] = $value;
        // --- save data hash ---
        if (!array_key_exists($tmpName, $this->_dataHash))
            $this->_dataHash[$tmpName] = $value;
    }

    final public function __unset(string $name) {
        unset($this->_data[$name]);
    }

    /**
     * Проверка, является ли объект новым и не сохраненным в базу данных.
     * @return bool
     */
    final public function isNewObject(): bool {
        return $this->_newRecord;
    }

    /**
     * Имя текущей таблицы
     * @return string
     */
    final public function tableName(): string {
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
    final public function primaryKey(): string {
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
     *   * value  - название колонки в таблице
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
    final public function passwordColumn(): string {
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
    final public function isReadOnly(): bool {
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
     * Название ключа базы данных, указанного в разделе конфигурационного файла «*_secondary», с которой будет работать модель
     * @return string
     *
     * NOTE: If database name is empty - used primary database.
     */
    final public function database(): string {
        return $this->_database;
    }

    /**
     * Задать название ключа базы данных, указанного в разделе конфигурационного файла «*_secondary», с которой будет работать модель
     * @param string $database - имя базы данных для подключения
     *
     * NOTE: If database name is empty - used primary database.
     */
    final protected function setDatabase(string $database) {
        $this->_database = $database;
    }

    /**
     * Получить массив со список параметров модели
     * @return array
     *
     * === Example:
     * ActiveRecord.dataParamKeys();
     *   [ 'id', 'name', 'description' ] // where table columns: id, name, description
     *
     * ActiveRecord.id
     * ActiveRecord.name
     * ActiveRecord.description
     */
    final public function dataParamKeys(): array {
        return array_keys($this->_data);
    }

    /**
     * Содержит ли модель требуемый параметр
     * @param string $key название параметра
     * @return bool
     */
    final public function hasDataParamKey(string $key): bool {
        return array_key_exists($key, $this->_data);
    }

    /**
     * Получить массив со списком параметров модели и их значениями
     * @return array
     *
     * === Example:
     * ActiveRecord.dataParamVars();
     *   [ 'id' => '1', 'name' => 'test', 'description' => 'test description' ] // where table columns: id, name, description
     */
    final public function dataParamVars(): array {
        return $this->_data;
    }

    /**
     * Получить массив со списком параметров модели и их значениями
     * @return array
     *
     * === Example:
     * ActiveRecord.objectVars();
     *   [ 'id' => '1', 'name' => 'test', 'description' => 'test description' ] // where table columns: id, name, description
     *
     * NOTE: This alias function for 'ActiveRecord::dataParamVars()'.
     */
    final public function objectVars(): array {
        return $this->dataParamVars();
    }

    /**
     * Был ли изменен объект модели
     * @return bool
     *
     * === Example:
     * ActiveRecord.isObjectChanged();
     *   false // if object not changed
     *
     * ActiveRecord.id = 123; // old value: 1
     * ActiveRecord.isObjectChanged();
     *   true // object changed
     *
     * ActiveRecord.id = 123; // old value: 1
     * ActiveRecord.save();
     * ActiveRecord.isObjectChanged();
     *   false // object already saved and not changed
     */
    final public function isObjectChanged(): bool {
        $objectProps = $this->dataParamVars();
        if ($this->isNewObject() === true)
            return !empty($objectProps);
        foreach ($objectProps as $key => $value) {
            if (strcmp($this->_passwordColumn, $key) !== 0
                && array_key_exists($key, $this->_dataHash)
                && $this->_dataHash[$key] !== $value)
                return true;
        }
        return false;
    }

    /**
     * Получить массив со списком измененных параметров модели и их новыми значениями
     * @return array
     *
     * === Example:
     * ActiveRecord.objectChangedParams();
     *   [] // if object not changed
     *
     * ActiveRecord.id = 123; // old value: 1
     * ActiveRecord.objectChangedParams();
     *   [ 'id' => '123' ] // object changed
     */
    final public function objectChangedParams(): array {
        $objectProps = $this->dataParamVars();
        if ($this->isNewObject() === true)
            return $objectProps;
        foreach ($objectProps as $key => $value) {
            if (strcmp($this->_passwordColumn, $key) !== 0
                && array_key_exists($key, $this->_dataHash)
                && $this->_dataHash[$key] === $value)
                unset($objectProps[$key]);
        }
        return $objectProps;
    }

    /**
     * Получить массив со списком измененных параметров модели и их новыми значениями
     * @return array
     *
     * === Example:
     * ActiveRecord.objectChangedVars();
     *   [] // if object not changed
     *
     * ActiveRecord.id = 123; // old value: 1
     * ActiveRecord.objectChangedVars();
     *   [ 'id' => '123' ] // object changed
     *
     * NOTE: This alias function for 'ActiveRecord::objectChangedParams()'.
     */
    final public function objectChangedVars(): array {
        return $this->objectChangedParams();
    }

    /**
     * Получить текстовое представление объекта модели
     * @param integer $maxParamValueStrLen маскимальный размер строкового представления значения параметра (если меньше или равно 0, то выводится вся строка)
     * @param bool $includeChanged включить вывод изменений параметров
     * @param string $changedDelimiter размедлитель между старым и новым значением параметра (поддерживается, если $includeChanged == true)
     * @return string
     * @throws ErrorActiveRecord
     *
     * === Example:
     * // - 1 set values -
     * ActiveRecord.id = 123;
     * ActiveRecord.name = "test name";
     * ActiveRecord.description = "test description";
     *
     * // - 2 show object -
     * ActiveRecord.objectToStr();
     *   "ActiveRecord {
     *      id: 123,
     *      name: "test name",
     *      description: "test description"
     *    }"
     *
     * // - 3 show object with $maxParamValueStrLen -
     * ActiveRecord.objectToStr(5);
     *   "ActiveRecord {
     *      id: 123,
     *      name: "test ...",
     *      description: "test ..."
     *    }"
     *
     * === Example:
     * // - 1 update values -
     * ActiveRecord.name = "test new name"; // old value "test name"
     * ActiveRecord.description = "test new description"; // old value "test description"
     *
     * // - 2 show object -
     * ActiveRecord.objectToStr();
     *   "ActiveRecord {
     *      id: 123,
     *      name: "test name" -> "test new name",
     *      description: "test description" -> "test new description"
     *    }"
     *
     * === Example:
     * // - 1 update values -
     * ActiveRecord.name = "test new name"; // old value "test name"
     * ActiveRecord.description = "test new description"; // old value "test description"
     * ActiveRecord.save(); // old values hash cleared after update
     *
     * // - 2 show object -
     * ActiveRecord.objectToStr(30, false);
     *   "ActiveRecord {
     *      id: 123,
     *      name: "test new name",
     *      description: "test new description"
     *    }"
     */
    final public function objectToStr(int $maxParamValueStrLen = 30,
                                      bool $includeChanged = true,
                                      string $changedDelimiter = "->"): string {
        $objectName = $this->objectName();
        $objectProps = $this->dataParamVars();
        $tmpStr = "";
        foreach ($objectProps as $key => $value) {
            if (strcmp($this->_passwordColumn, $key) === 0)
                continue;
            if (!empty($tmpStr))
                $tmpStr .= ",";

            $tmpValueStr = CoreHelper::objectToStr($value);
            $tmpValueStr = str_replace("\n", "", $tmpValueStr);
            $tmpValueStr = str_replace("\r", "", $tmpValueStr);
            if ($maxParamValueStrLen > 0
                && strlen($tmpValueStr) > $maxParamValueStrLen)
                $tmpValueStr = mb_substr($tmpValueStr, 0, $maxParamValueStrLen) . "...";

            $valueStr = $tmpValueStr;
            if ($includeChanged
                && array_key_exists($key, $this->_dataHash)
                && $this->_dataHash[$key] != $value) {
                $tmpHashValueStr = CoreHelper::objectToStr($this->_dataHash[$key]);
                $tmpHashValueStr = str_replace("\n", "", $tmpHashValueStr);
                $tmpHashValueStr = str_replace("\r", "", $tmpHashValueStr);
                if ($maxParamValueStrLen > 0
                    && strlen($tmpHashValueStr) > $maxParamValueStrLen)
                    $tmpHashValueStr = mb_substr($tmpHashValueStr, 0, $maxParamValueStrLen) . "...";
                $valueStr = "$tmpHashValueStr $changedDelimiter $tmpValueStr";
            }
            $tmpStr .= "\n  $key: $valueStr";
        }
        return "$objectName { $tmpStr \n}";
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
        if ($this->isNewObject() === true)
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
        if ($this->isNewObject() === true)
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

    /**
     * Генерировать глобальный идентификатор объекта модели данных
     * @return string
     */
    final public function modelGlobalID(): string {
        $modelName = self::class;
        $pkName = $this->primaryKey();
        $pkValue = $this->preparePKeyValue();
        return sha1("$modelName:$pkName:$pkValue");
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
     * @param string $name название параметра
     * @param mixed $value значение параметра
     * @return mixed
     */
    protected function beforeSet(string $name, $value) {
        return $value;
    }

    /**
     * Метод предобработки добавляемых в БД данных (обратное преобразование, выполняемое методом beforeSet(...) )
     * @param string $name название параметра
     * @param mixed $value значение параметра
     * @return mixed
     */
    protected function prepareValueForDB(string $name, $value) {
        return $value;
    }

    // --- static ---

    /**
     * Запрос всех объектов из таблицы
     * @return array
     * @throws
     */
    final public static function all(): array {
        $aClassName = static::class;
        $aRec = new $aClassName();
        $tName = $aRec->tableName();
        $dbName = $aRec->database();
        unset($aRec);
        // --- get adapter ---
        $db = ActiveRecord::databaseAdapter($aClassName, $dbName);
        // --- exec query ---
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
        $aClassName = static::class;
        $aRec = new $aClassName();
        $tName = $aRec->tableName();
        $dbName = $aRec->database();
        unset($aRec);
        // --- get adapter ---
        $db = ActiveRecord::databaseAdapter($aClassName, $dbName);
        // --- exec query ---
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
        $aClassName = static::class;
        $aRec = new $aClassName();
        $tName = $aRec->tableName();
        $dbName = $aRec->database();
        unset($aRec);
        // --- get adapter ---
        $db = ActiveRecord::databaseAdapter($aClassName, $dbName);
        // --- exec query ---
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
        $aClassName = static::class;
        $aRec = new $aClassName();
        $tName = $aRec->tableName();
        $dbName = $aRec->database();
        unset($aRec);
        // --- get adapter ---
        $db = ActiveRecord::databaseAdapter($aClassName, $dbName);
        // --- exec query ---
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
        // --- exec query ---
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
        $aClassName = static::class;
        $aRec = new $aClassName();
        $dbName = $aRec->database();
        unset($aRec);
        // --- get adapter ---
        $db = ActiveRecord::databaseAdapter($aClassName, $dbName);
        // --- exec query ---
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
        $aClassName = static::class;
        $aRec = new $aClassName();
        $tName = $aRec->tableName();
        $dbName = $aRec->database();
        $columnMappings = $aRec->columnMappings();
        unset($aRec);
        // --- get adapter ---
        $db = ActiveRecord::databaseAdapter($aClassName, $dbName);
        // --- exec query ---
        $tmpWhere = "";
        $tmpWhereVal = array();
        foreach ($args as $key => $val) {
            $tmpKey = CoreHelper::underscore($key);
            if (!empty($tmpWhere))
                $tmpWhere .= " AND";
            $tmpColumn = $key;
            if (array_key_exists($key, $columnMappings))
                $tmpColumn = $columnMappings[$key];
            if ($prepareNames === true)
                $tmpColumn = CoreHelper::underscore($tmpColumn);
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
        $aClassName = static::class;
        $aRec = new $aClassName();
        $tName = $aRec->tableName();
        $tPK = $aRec->primaryKey();
        $dbName = $aRec->database();
        unset($aRec);
        // --- get adapter ---
        $db = ActiveRecord::databaseAdapter($aClassName, $dbName);
        // --- exec query ---
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
        $aClassName = static::class;
        $aRec = new $aClassName();
        $tName = $aRec->tableName();
        $dbName = $aRec->database();
        $columnMappings = $aRec->columnMappings();
        unset($aRec);
        // --- get adapter ---
        $db = ActiveRecord::databaseAdapter($aClassName, $dbName);
        // --- exec query ---
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
        if (array_key_exists($column, $columnMappings))
            $tmpColumn = $columnMappings[$column];
        if ($prepareNames === true)
            $tmpColumn = CoreHelper::underscore($tmpColumn);
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
        $aClassName = static::class;
        $aRec = new $aClassName();
        $tName = $aRec->tableName();
        $dbName = $aRec->database();
        $columnMappings = $aRec->columnMappings();
        unset($aRec);
        // --- get adapter ---
        $db = ActiveRecord::databaseAdapter($aClassName, $dbName);
        // --- exec query ---
        $tmpColumns = "";
        foreach ($columns as $key => $val) {
            if (!empty($tmpColumns))
                $tmpColumns .= ",";
            $tmpVal = $val;
            if (array_key_exists($val, $columnMappings))
                $tmpVal = $columnMappings[$val];
            if ($prepareNames === true)
                $tmpVal = CoreHelper::underscore($tmpVal);
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
     * Получить объект адаптера по работе с базой данных
     * @param string $className
     * @param string $dbName
     * @return BaseDatabaseAdapter
     * @throws ErrorActiveRecord
     */
    private static function databaseAdapter(string $className, string $dbName) : BaseDatabaseAdapter
    {
        $helper = ActiveRecordTransactionHelper::helper($className);
        if (!is_null($helper))
            return $helper->databaseAdapter();

        $db = DatabaseFactory::instance()->createDatabaseAdapter([ 'database' => $dbName ]);
        if (is_null($db))
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Database adapter is NULL!',
                'active-r-class' => $className,
                'active-r-method' => __FUNCTION__
            ]);
        return $db;
    }

    /**
     * Имя объекта класса
     * @return string
     * @throws
     */
    private function objectName(): string {
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
     * Выполнение запроса SQL INSERT
     * @throws
     */
    private function insert() {
        $this->processingCallbacks('before-insert');
        $db = ActiveRecord::databaseAdapter($this->objectName(), $this->database());

        $dataColumns = array();
        $dataValues = array();
        $this->prepareData($db, $dataColumns, $dataValues);
        if (empty($dataValues))
            return; // no changed
        $dataValuesKeys = array_keys($dataValues);
        $tName = $this->tableName();
        try {
            $res = $db->query("INSERT INTO ".$db->quoteTableName($tName)." (" . implode(', ', $dataColumns) . ") VALUES (" . implode(', ', $dataValuesKeys) . ") RETURNING *;", $dataValues);
            if (count($res) === 1) {
                $this->_newRecord = false;
                $this->_data = [];
                $this->_dataHash = [];
                foreach ($res[0] as $rKey => $rVal)
                    $this->__set($rKey, $rVal);
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
    private function update() {
        $this->processingCallbacks('before-update');
        $db = ActiveRecord::databaseAdapter($this->objectName(), $this->database());

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
            $res = $db->query("UPDATE ".$db->quoteTableName($tName)." SET " . implode(',', $dataValues4Upd) . " WHERE ".$db->quoteTableName("$tName.$tPK")." = :pk_value RETURNING *;", $dataValues);
            if (count($res) === 1) {
                $this->_data = [];
                $this->_dataHash = [];
                foreach ($res[0] as $rKey => $rVal)
                    $this->__set($rKey, $rVal);
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
        $this->processingCallbacks('after-update');
    }

    /**
     * Выполнение запроса SQL DELETE
     * @throws
     */
    private function delete() {
        $db = ActiveRecord::databaseAdapter($this->objectName(), $this->database());

        $tName = $this->tableName();
        $tPK = $this->primaryKey();
        $dataValues = array();
        $dataValues[":pk_value"] = $this->preparePKeyValue();
        try {
            $res = $db->query("DELETE FROM ".$db->quoteTableName($tName)." WHERE ".$db->quoteTableName("$tName.$tPK")." = :pk_value RETURNING *;", $dataValues);
            if (count($res) === 1) {
                $this->_newRecord = true;
                $this->_data = [];
                $this->_dataHash = [];
                foreach ($res[0] as $rKey => $rVal)
                    $this->__set($rKey, $rVal);
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
    }

    /**
     * Метод подготовки данных для запросов
     * @param BaseDatabaseAdapter $db - адапрет доступа к БД
     * @param array $dataColumns - названия колонок
     * @param array $dataValues - массив связки ключей и их значений
     * @param array $dataValues4Upd - названия ключей и значений для UPDATE
     * @throws
     */
    private function prepareData(BaseDatabaseAdapter &$db,
                                 array &$dataColumns,
                                 array &$dataValues,
                                 array &$dataValues4Upd = []) {
        $objectProps = $this->dataParamVars();
        foreach ($objectProps as $key => $value) {
            if ($this->_newRecord === false
                && array_key_exists($key, $this->_dataHash)
                && $this->_dataHash[$key] === $value)
                continue; // skip not changed value

            // --- prepare ---
            $tmpKey = CoreHelper::underscore($key);
            $tmpName = ":" . $tmpKey . "_value";

            $tmpColumnName = $tmpKey;
            if ($this->hasColumnMapping($key) === true)
                $tmpColumnName = $this->columnMapping($key);

            $dataColumns[] = $tmpColumnName;
            $dataValues4Upd[] = $db->quoteTableName($tmpColumnName)." = $tmpName";
            $dataValues[$tmpName] = $this->prepareValueForDB($key, $value);
        }
    }

    /**
     * Метод подготовки значений первичного ключа
     * @return mixed
     */
    private function preparePKeyValue() {
        $tPK = $this->primaryKey();
        $tPKParam = CoreHelper::camelcase($tPK, false);
        if (array_key_exists($tPKParam, $this->_dataHash))
            return $this->_dataHash[$tPKParam];
        return $this->$tPKParam;
    }

    /**
     * Метод подготовки пароля к сохранению в БД
     */
    private function preparePassword() {
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
    private function encryptPassword(string $val): string {
        return password_hash($val, PASSWORD_DEFAULT);
    }

    /**
     * Добавить колбэк функцию обратоки
     * @param string $callbackType
     * @param string $method
     * @throws ErrorActiveRecord
     */
    private function appendCallback(string $callbackType, string $method) {
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
    private function processingCallbacks(string $callbackType) {
        if (!isset($this->_callbacks[$callbackType]))
            return;
        foreach ($this->_callbacks[$callbackType] as $method)
            $this->$method();
    }
}