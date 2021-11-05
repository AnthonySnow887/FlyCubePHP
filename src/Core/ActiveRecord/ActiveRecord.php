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
use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;
use \FlyCubePHP\Core\Database\DatabaseFactory as DatabaseFactory;
use \FlyCubePHP\Core\Error\ErrorDatabase as ErrorDatabase;
use \FlyCubePHP\Core\Error\ErrorActiveRecord as ErrorActiveRecord;

abstract class ActiveRecord
{
    private $_tableName = "";
    private $_primaryKey = "id";
    private $_newRecord = true;
    private $_dataHash = array();

    /**
     * ActiveRecord constructor.
     * @throws ErrorActiveRecord
     */
    public function __construct() {
        $this->_tableName = CoreHelper::underscore($this->objectName());
    }

    final public function __set(string $name, $value) {
        $tmpName = CoreHelper::camelcase($name, false);
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
     * Сохранить/Обновить объект в БД
     * @throws
     */
    final public function save() {
        if ($this->_newRecord === true)
            $this->insert();
        else
            $this->update();
    }

    /**
     * Удалить объект из БД
     * @throws
     */
    final public function destroy() {
        if ($this->_newRecord === true)
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => 'Attempt to delete an uncreated object!',
                'active-r-class' => $this->objectName(),
                'active-r-method' => __FUNCTION__
            ]);
        $this->delete();
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
     * @return Object|null
     * @throws
     */
    final public static function first() {
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
    final public static function limit(int $num) {
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
    final public static function count() {
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
     * @return Object|null
     * @throws
     */
    final public static function find($pkVal) {
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
            $tmpWhere .= " ".$db->quoteTableName("$tName.$tmpColumn")." = :$tmpKey";
            $tmpWhereVal[$tmpKey] = $val;
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
    final public static function exists($pkVal) {
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
    final public static function existsSome(string $column, array $values, bool $prepareNames = true) {
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
            $tmpWhere .= " :$tmpKey";
            $tmpWhereVal[$tmpKey] = $val;
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
    private function objectName() {
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
    private function objectProperties(): array {
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
    private function insert() {
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
            $db->query("INSERT INTO ".$db->quoteTableName($tName)." (" . implode(', ', $dataColumns) . ") VALUES (" . implode(', ', $dataValuesKeys) . ");", $dataValues);
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
     * Выполнение запроса SQL UPDATE
     * @throws
     */
    private function update() {
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
    }

    /**
     * Выполнение запроса SQL DELETE
     * @throws
     */
    private function delete() {
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
    private function prepareData(BaseDatabaseAdapter &$db,
                                 array &$dataColumns,
                                 array &$dataValues,
                                 array &$dataValues4Upd = []) {
        foreach ($this->_dataHash as $key => $value) {
            $tmpValue = $this->$key;
            if ($this->_newRecord === false
                && $value === $tmpValue)
                continue; // skip not changed value
            $tmpKey = CoreHelper::underscore($key);
            $tmpName = ":" . $tmpKey . "_value";
            $dataColumns[] = $tmpKey;
            $dataValues4Upd[] = $db->quoteTableName($tmpKey)." = $tmpName";
            $dataValues[$tmpName] = $tmpValue;
        }
        $objectProps = $this->objectProperties();
        foreach ($objectProps as $key => $value) {
            $tmpValue = $this->$key;
            if ($this->_newRecord === false
                && array_key_exists($key, $this->_dataHash)
                && $this->_dataHash[$key] === $tmpValue)
                continue; // skip not changed value
            $tmpKey = CoreHelper::underscore($key);
            $tmpName = ":" . $tmpKey . "_value";
            $dataColumns[] = $tmpKey;
            $dataValues4Upd[] = $db->quoteTableName($tmpKey)." = $tmpName";
            $dataValues[$tmpName] = $tmpValue;
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
}