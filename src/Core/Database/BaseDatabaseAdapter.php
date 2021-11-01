<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 30.07.21
 * Time: 16:41
 */

namespace FlyCubePHP\Core\Database;

include_once __DIR__.'/../Error/ErrorDatabase.php';
include_once __DIR__.'/../Logger/Logger.php';

use \FlyCubePHP\Core\Logger\Logger as Logger;
use \FlyCubePHP\Core\Error\ErrorDatabase as ErrorDatabase;

abstract class BaseDatabaseAdapter
{
    private $_settings = null;
    private $_pdoObject = null;
    private $_outputDelimeter = "<br/>";
    private $_showOutput = false;

    public function __construct(array $settings) {
        $this->_settings = $settings;
    }

    /**
     * Пересоздать соединение с базой данных
     * @param array $settings
     * @throws
     */
    final public function recreatePDO(array $settings = []) {
        unset($this->_pdoObject);
        $this->_pdoObject = null;
        if (!empty($settings))
            $this->_settings = $settings;
        try {
            $dsn = $this->makeDSN($this->_settings);
            if (empty($dsn))
                throw ErrorDatabase::makeError([
                    'tag' => 'database',
                    'message' => "Invalid DSN!",
                    'class-name' => $this->objectName(),
                    'class-method' => __FUNCTION__,
                    'adapter-name' => $this->name()
                ]);

            if ($this->authSettingsTransferToPDO())
                $this->_pdoObject = new \PDO($dsn, $this->_settings['username'], $this->_settings['password']);
            else
                $this->_pdoObject = new \PDO($dsn);

            $this->_pdoObject->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            throw ErrorDatabase::makeError([
                'tag' => 'database',
                'message' => $e->getMessage(),
                'class-name' => $this->objectName(),
                'class-method' => __FUNCTION__,
                'adapter-name' => $this->name()
            ]);
        }
    }

    /**
     * Проверка на валидность (был ли создан объект по работе с базой данных)
     * @return bool
     */
    final public function isValid(): bool {
        return !is_null($this->_pdoObject);
    }

    /**
     * Установить флаг вывода в консоль
     * @param bool $value
     */
    final public function setShowOutput(bool $value) {
        $this->_showOutput = $value;
    }

    /**
     * Задать разделитель строк
     * @param string $value
     */
    final public function setOutputDelimiter(string $value) {
        $this->_outputDelimeter = $value;
    }

    /**
     * Получить массив с настройками
     * @return array|null
     */
    final public function settings()/*: array:null*/ {
        return $this->_settings;
    }

    /**
     * Получить имя текущей базы данных из настроек
     * @return string
     */
    final public function database(): string {
        if (isset($this->_settings['database']))
            return $this->_settings['database'];
        return "";
    }

    /**
     * Метод выполнения запроса к базе данных
     * @param string $sql - SQL запрос
     * @param array $params - параметры запроса
     * @param string $className - Имя класса, для создания объектов результатов
     * @return array|null
     * @throws
     */
    final public function query(string $sql, array $params = [], string $className = 'stdClass')/*: array|null */ {
        if ($this->_showOutput)
            echo "=> SQL: $sql".$this->_outputDelimeter;

        if (!$this->isValid())
            throw ErrorDatabase::makeError([
                'tag' => 'database',
                'message' => 'Database adapter is not valid!',
                'class-name' => $this->objectName(),
                'class-method' => __FUNCTION__,
                'adapter-name' => $this->name(),
                'sql-query' => $sql,
                'sql-params' => $params
            ]);
        try {
            $sth = $this->_pdoObject->prepare($sql);
            $sqlStartMS = microtime(true);
            $result = $sth->execute($params);
            $sqlMS = round(microtime(true) - $sqlStartMS, 3);
            Logger::info("SQL: [$sqlMS"."ms] $sql", $params);
        } catch (\Exception $e) {
            throw ErrorDatabase::makeError([
                'tag' => 'database',
                'message' => $e->getMessage(),
                'class-name' => $this->objectName(),
                'class-method' => __FUNCTION__,
                'adapter-name' => $this->name(),
                'sql-query' => $sql,
                'sql-params' => $params
            ]);
        }
        if (false === $result)
            return null;
        if ($sth->columnCount() === 0)
            return [];
        return $sth->fetchAll(\PDO::FETCH_CLASS, $className);
    }

    /**
     * Метод выполнения запроса к базе данных в рамках одной транзакции
     * @param string $sql - SQL запрос
     * @param array $params - параметры запроса
     * @param string $className - Имя класса, для создания объектов результатов
     * @return array|null
     * @throws
     */
    final public function queryTransaction(string $sql, array $params = [], string $className = 'stdClass')/*: array|null */ {
        if ($this->_showOutput)
            echo "=> SQL: $sql".$this->_outputDelimeter;

        if (!$this->isValid())
            throw ErrorDatabase::makeError([
                'tag' => 'database',
                'message' => 'Database adapter is not valid!',
                'class-name' => $this->objectName(),
                'class-method' => __FUNCTION__,
                'adapter-name' => $this->name(),
                'sql-query' => $sql,
                'sql-params' => $params
            ]);
        try {
            $this->beginTransaction();
            $sth = $this->_pdoObject->prepare($sql);
            $sqlStartMS = microtime(true);
            $result = $sth->execute($params);
            $this->commitTransaction();
            $sqlMS = round(microtime(true) - $sqlStartMS, 3);
            Logger::info("SQL: [$sqlMS"."ms] $sql", $params);
        } catch (\Exception $e) {
            $this->rollBackTransaction();
            throw ErrorDatabase::makeError([
                'tag' => 'database',
                'message' => $e->getMessage(),
                'class-name' => $this->objectName(),
                'class-method' => __FUNCTION__,
                'adapter-name' => $this->name(),
                'sql-query' => $sql,
                'sql-params' => $params
            ]);
        }
        if (false === $result)
            return null;
        if ($sth->columnCount() === 0)
            return [];
        return $sth->fetchAll(\PDO::FETCH_CLASS, $className);
    }

    /**
     * Открыть транзакцию
     * @return bool
     */
    final public function beginTransaction(): bool {
        if (!$this->isValid())
            return false;
        if ($this->_pdoObject->inTransaction() === true)
            return true;
        return $this->_pdoObject->beginTransaction();
    }

    /**
     * Применить транзакцию
     * @return bool
     */
    final public function commitTransaction(): bool {
        if (!$this->isValid())
            return false;
        if ($this->_pdoObject->inTransaction() !== true)
            return false;
        return $this->_pdoObject->commit();
    }

    /**
     * Отменить транзакцию
     * @return bool
     */
    final public function rollBackTransaction(): bool {
        if (!$this->isValid())
            return false;
        if ($this->_pdoObject->inTransaction() !== true)
            return false;
        return $this->_pdoObject->rollBack();
    }

    /**
     * Открыта ли транзакция
     * @return bool
     */
    final public function inTransaction(): bool {
        if (!$this->isValid())
            return false;
        return $this->_pdoObject->inTransaction();
    }

    /**
     * Метод запроса списка таблиц базы данных
     * @return array|null
     */
    abstract public function tables()/*: array|null */;

    /**
     * Имя адаптера
     * @return string
     */
    abstract public function name(): string;

    /**
     * Метод создания строки с настройками подключения
     * @param array $settings - массив настроек
     * @return string
     */
    abstract protected function makeDSN(array $settings): string;

    /**
     * Передавать логин и пароль в PDO при его создании как аргументы
     * @return bool
     */
    protected function authSettingsTransferToPDO(): bool {
        return false;
    }

    /**
     * Имя объекта класса
     * @return string
     * @throws
     */
    final protected function objectName() {
        $tmpRef = null;
        try {
            $tmpRef = new \ReflectionClass($this);
        } catch (\Exception $e) {
            throw ErrorDatabase::makeError([
                'tag' => 'database',
                'message' => $e->getMessage(),
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'adapter-name' => $this->name()
            ]);
        }
        $tmpName = $tmpRef->getName();
        unset($tmpRef);
        return $tmpName;
    }
}