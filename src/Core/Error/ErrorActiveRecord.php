<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 25.08.21
 * Time: 16:31
 */

namespace FlyCubePHP\Core\Error;

include_once 'Error.php';
include_once 'ErrorDatabase.php';

class ErrorActiveRecord extends Error
{
    private $_activeRecordClass = "";
    private $_activeRecordMethod = "";
    private $_errorDatabase = null;

    function __construct(string $activeRecordClass,
                         string $activeRecordMethod,
                         string $message = "",
                         string $tag = "",
                         ErrorDatabase $errorDatabase = null,
                         int $code = 0,
                         \Throwable $previous = null) {
        parent::__construct($message, $tag, $code, $previous);
        $this->_type = ErrorType::ACTIVE_RECORD;
        $this->_activeRecordClass = $activeRecordClass;
        $this->_activeRecordMethod = $activeRecordMethod;
        $this->_errorDatabase = $errorDatabase;
    }

    final public function activeRecordClass(): string {
        return $this->_activeRecordClass;
    }

    final public function activeRecordMethod(): string {
        return $this->_activeRecordMethod;
    }

    final public function hasErrorDatabase(): bool {
        return !is_null($this->_errorDatabase);
    }

    final public function errorDatabase()/*: ErrorDatabase|null */ {
        return $this->_errorDatabase;
    }

    /**
     * Create Error object
     * @param array $options
     * @return ErrorActiveRecord
     *
     * ==== Options
     *
     * - [string]           message             - error message
     * - [string]           tag                 - error tag (default: empty)
     * - [int]              code                - error code (default: 0)
     * - [Throwable]        previous            - previous error object (default: null)
     * - [string]           additional-message  - additional error message (default: empty)
     * - [array]            additional-data     - additional error data (key:value array) (default: empty)
     * - [string]           file                - error file (default: used backtrace point)
     * - [int]              line                - error line (default: used backtrace point)
     * - [int]              backtrace-shift     - error backtrace point (default: 1)
     * - [string]           active-r-class      - active record class name
     * - [string]           active-r-method     - active record class method
     * - [ErrorDatabase]    error-database      - error database object (default: null)
     */
    final static public function makeError(array $options = []) {
        $message = "";
        if (isset($options['message']))
            $message = $options['message'];
        $tag = "";
        if (isset($options['tag']))
            $tag = $options['tag'];
        $activeRClass = "";
        if (isset($options['active-r-class']))
            $activeRClass = $options['active-r-class'];
        $activeRMethod = "";
        if (isset($options['active-r-method']))
            $activeRMethod = $options['active-r-method'];
        $errorDB = "";
        if (isset($options['error-database'])
            && is_subclass_of($options['error-database'], '\FlyCubePHP\Core\Error\ErrorDatabase'))
            $errorDB = $options['error-database'];
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

        $err = new ErrorActiveRecord($activeRClass, $activeRMethod, $message, $tag, $errorDB, $code, $previous);
        $err->setAdditionalMessage($additionalMessage);
        $err->setAdditionalData($additionalData);
        $err->setFile($file);
        $err->setLine($line);
        return $err;
    }
}