<?php

namespace FlyCubePHP\Core\ActiveRecord;

include_once __DIR__.'/../Database/DatabaseFactory.php';
include_once __DIR__.'/../../HelperClasses/CoreHelper.php';
include_once __DIR__.'/../Error/ErrorActiveRecord.php';

use FlyCubePHP\Core\Database\BaseDatabaseAdapter;
use FlyCubePHP\Core\Error\ErrorActiveRecord;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\Database\DatabaseFactory;

class ActiveRecordTransactionHelper
{
    private static $_helpers = [];

    private $_uid = "";
    private $_dbName = "";
    private $_dbAdapter = null;
    private $_modelClasses = array();

    /**
     * Добавить transaction helper
     * @param ActiveRecordTransactionHelper $helper
     */
    static private function appendHelper(ActiveRecordTransactionHelper &$helper)
    {
        ActiveRecordTransactionHelper::$_helpers[$helper->uid()] = $helper;
    }

    /**
     * Удалить transaction helper
     * @param ActiveRecordTransactionHelper $helper
     */
    static private function removeHelper(ActiveRecordTransactionHelper &$helper)
    {
        unset(ActiveRecordTransactionHelper::$_helpers[$helper->uid()]);
    }

    /**
     * Запросить transaction helper для класса модели
     * @param string $className Имя класса модели ActiveRecord
     * @return ActiveRecordTransactionHelper|null
     */
    static public function helper(string $className) /*: ActiveRecordTransactionHelper|null*/
    {
        if (empty(array_keys(ActiveRecordTransactionHelper::$_helpers)))
            return null;
        $tmpArray = array_reverse(ActiveRecordTransactionHelper::$_helpers);
        foreach ($tmpArray as $helper) {
            if ($helper->containsModelClass($className))
                return $helper;
        }
        return null;
    }

    /**
     * ActiveRecordTransactionHelper constructor.
     */
    public function __construct()
    {
        $this->_uid = CoreHelper::uuid();
        ActiveRecordTransactionHelper::appendHelper($this);
    }

    public function __destruct()
    {
        ActiveRecordTransactionHelper::removeHelper($this);
        if (!is_null($this->_dbAdapter)
            && $this->_dbAdapter->inTransaction())
            $this->_dbAdapter->rollBackTransaction();
    }

    /**
     * Уникальный ID объекта
     * @return string
     */
    public function uid(): string
    {
        return $this->_uid;
    }

    /**
     * Получить объект адаптера по работе с базой данных
     * @return BaseDatabaseAdapter
     * @throws ErrorActiveRecord
     */
    public function databaseAdapter() : BaseDatabaseAdapter
    {
        if (is_null($this->_dbAdapter)) {
            $this->_dbAdapter = DatabaseFactory::instance()->createDatabaseAdapter([ 'database' => $this->_dbName ]);
            if (is_null($this->_dbAdapter))
                throw ErrorActiveRecord::makeError([
                    'tag' => 'active-record',
                    'message' => 'Database adapter is NULL!',
                    'active-r-class' => 'ActiveRecordTransactionHelper',
                    'active-r-method' => __FUNCTION__
                ]);
        }
        return $this->_dbAdapter;
    }

    /**
     * Задан ли класс модели ActiveRecord для работы в рамках одной транзакции
     * @param string $className Имя класса модели ActiveRecord
     * @return bool
     */
    public function containsModelClass(string $className): bool
    {
        return array_key_exists($className, $this->_modelClasses);
    }

    /**
     * Задать класс модели ActiveRecord для работы в рамках одной транзакции
     * @param string $className Имя класса модели ActiveRecord
     * @throws ErrorActiveRecord
     */
    public function appendModelClass(string $className)
    {
        $tmpModel = new $className();
        if (!is_subclass_of($tmpModel, "\FlyCubePHP\Core\ActiveRecord\ActiveRecord"))
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => "Append model class $className failed! This class is not a subclass of class \"\FlyCubePHP\Core\ActiveRecord\ActiveRecord\"!",
                'active-r-class' => "ActiveRecordTransactionHelper",
                'active-r-method' => __FUNCTION__,
                'backtrace-shift' => 2
            ]);
        if (empty($this->_dbName))
            $this->_dbName = $tmpModel->database();
        if (strcmp($this->_dbName, $tmpModel->database()) !== 0)
            throw ErrorActiveRecord::makeError([
                'tag' => 'active-record',
                'message' => "Append model class $className failed! The database of the model is different from those specified in other models!",
                'active-r-class' => "ActiveRecordTransactionHelper",
                'active-r-method' => __FUNCTION__,
                'backtrace-shift' => 2
            ]);
        $this->_modelClasses[$className] = $className;
    }

    /**
     * Удалить класс модели ActiveRecord для работы в рамках одной транзакции
     * @param string $className Имя класса модели ActiveRecord
     */
    public function removeModelClass(string $className)
    {
        if ($this->containsModelClass($className))
            unset($this->_modelClasses[$className]);
    }

    /**
     * Открыть транзакцию
     * @return bool
     * @throws ErrorActiveRecord
     */
    public function beginTransaction(): bool
    {
        return $this->databaseAdapter()->beginTransaction();
    }

    /**
     * Применить транзакцию
     * @return bool
     * @throws ErrorActiveRecord
     */
    public function commitTransaction(): bool
    {
        return $this->databaseAdapter()->commitTransaction();
    }

    /**
     * Отменить транзакцию
     * @return bool
     * @throws ErrorActiveRecord
     */
    public function rollBackTransaction(): bool
    {
        return $this->databaseAdapter()->rollBackTransaction();
    }

    /**
     * Открыта ли транзакция
     * @return bool
     * @throws ErrorActiveRecord
     */
    public function inTransaction(): bool
    {
        return $this->databaseAdapter()->inTransaction();
    }
}