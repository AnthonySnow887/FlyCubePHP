<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 25.08.21
 * Time: 16:42
 */

namespace FlyCubePHP\Core\Error;

include_once 'Error.php';

class ErrorDatabase extends Error
{
    private $_adapterClass = "";
    private $_adapterMethod = "";
    private $_adapterName = "";
    private $_sqlQuery = "";
    private $_sqlParams = [];

    function __construct(string $adapterClass,
                         string $adapterMethod,
                         string $adapterName,
                         string $sqlQuery,
                         array $sqlParams,
                         string $message = "",
                         string $tag = "",
                         int $code = 0,
                         \Throwable $previous = null) {
        parent::__construct($message, $tag, $code, $previous);
        $this->_type = ErrorType::DATABASE;
        $this->_adapterClass = $adapterClass;
        $this->_adapterMethod = $adapterMethod;
        $this->_adapterName = $adapterName;
        $this->_sqlQuery = $sqlQuery;
        $this->_sqlParams = $sqlParams;
    }

    final public function adapterClass(): string {
        return $this->_adapterClass;
    }

    final public function adapterMethod(): string {
        return $this->_adapterMethod;
    }

    final public function adapterName(): string {
        return $this->_adapterName;
    }

    final public function sqlQuery(): string {
        return $this->_sqlQuery;
    }

    final public function sqlParams(): array {
        return $this->_sqlParams;
    }

    final public function sqlParamsStr(string $delimiter = ", "): string {
        $str = "";
        foreach ($this->_sqlParams as $key => $value) {
            if (!empty($str))
                $str .= $delimiter;
            if (is_bool($value)) {
                if ($value === true)
                    $value = "true";
                else
                    $value = "false";
            } else if (is_string($value)) {
                $value = "'$value'";
            }
            $str .= "$key: $value";
        }
        return $str;
    }

    final public function sqlQueryWithParams(): string {
        if (!empty($this->_sqlParams))
            return $this->_sqlQuery . "\r\n\r\n==> Where params: [" . $this->sqlParamsStr() . "]";

        return $this->_sqlQuery;
    }

    /**
     * Create Error object
     * @param array $options
     * @return ErrorDatabase
     *
     * ==== Options
     *
     * - [string]       message             - error message
     * - [string]       tag                 - error tag (default: empty)
     * - [int]          code                - error code (default: 0)
     * - [Throwable]    previous            - previous error object (default: null)
     * - [string]       additional-message  - additional error message (default: empty)
     * - [array]        additional-data     - additional error data (key:value array) (default: empty)
     * - [string]       file                - error file (default: used backtrace point)
     * - [int]          line                - error line (default: used backtrace point)
     * - [int]          backtrace-shift     - error backtrace point (default: 1)
     * - [string]       adapter-class       - set adapter class name
     * - [string]       adapter-method      - set adapter class method
     * - [string]       adapter-name        - set adapter name
     * - [string]       sql-query           - set sql query
     * - [array]        sql-params          - set sql params
     */
    final static public function makeError(array $options = []) {
        $message = "";
        if (isset($options['message']))
            $message = $options['message'];
        $tag = "";
        if (isset($options['tag']))
            $tag = $options['tag'];
        $className = "";
        if (isset($options['class-name']))
            $className = $options['class-name'];
        $classMethod = "";
        if (isset($options['class-method']))
            $classMethod = $options['class-method'];
        $adapterName = "";
        if (isset($options['adapter-name']))
            $adapterName = $options['adapter-name'];
        $sqlQuery = "";
        if (isset($options['sql-query']))
            $sqlQuery = $options['sql-query'];
        $sqlParams = [];
        if (isset($options['sql-params'])
            && is_array($options['sql-params']))
            $sqlParams = $options['sql-params'];
        $code = 0;
        if (isset($options['code']))
            $code = $options['code'];
        $previous = null;
        if (isset($options['previous'])
            && is_subclass_of($options['previous'], 'Throwable'))
            $previous = $options['previous'];
        $additionalMessage = "";
        if (isset($options['additional-message']))
            $additionalMessage = $options['additional-message'];
        $additionalData = [];
        if (isset($options['additional-data'])
            && is_array($options['additional-data']))
            $additionalData = $options['additional-data'];
        $file = "";
        if (isset($options['file']))
            $file = $options['file'];
        $line = 0;
        if (isset($options['line']))
            $line = $options['line'];

        $backtraceShift = 1;
        if (isset($options['backtrace-shift']))
            $backtraceShift = $options['backtrace-shift'];
        if (empty($file) || $line <= 0) {
            $backtraceArr = debug_backtrace();
            if ($backtraceShift <= 0)
                $backtraceShift = 1;
            for ($i = 0; $i < $backtraceShift; $i++)
                array_shift($backtraceArr); // remove method backtrace
            $lastUsage = array_shift($backtraceArr);
            $file = "";
            if (isset($lastUsage['file']))
                $file = $lastUsage['file'];
            $line = 0;
            if (isset($lastUsage['line']))
                $line = intval($lastUsage['line']);
        }

        $err = new ErrorDatabase($className, $classMethod, $adapterName, $sqlQuery, $sqlParams, $message, $tag, $code, $previous);
        $err->setAdditionalMessage($additionalMessage);
        $err->setAdditionalData($additionalData);
        $err->setFile($file);
        $err->setLine($line);
        return $err;
    }
}