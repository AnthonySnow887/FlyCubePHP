<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 24.08.21
 * Time: 15:15
 */

namespace FlyCubePHP\Core\Error;

include_once 'Error.php';

class ErrorController extends Error
{
    private $_controller = "";
    private $_method = "";
    private $_action = "";

    function __construct(string $controller,
                         string $method,
                         string $action,
                         string $message = "",
                         string $tag = "",
                         int $code = 0,
                         \Throwable $previous = null) {
        parent::__construct($message, $tag, $code, $previous);
        $this->_type = ErrorType::CONTROLLER;
        $this->_controller = $controller;
        $this->_method = $method;
        $this->_action = $action;
    }

    final public function controller(): string {
        return $this->_controller;
    }

    final public function method(): string {
        return $this->_method;
    }

    final public function action(): string {
        return $this->_action;
    }

    /**
     * Create Error object
     * @param array $options
     * @return ErrorController
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
     * - [string]       controller          - set controller name
     * - [string]       method              - set controller method
     * - [string]       action              - set controller action
     */
    final static public function makeError(array $options = []) {
        $message = "";
        if (isset($options['message']))
            $message = $options['message'];
        $tag = "";
        if (isset($options['tag']))
            $tag = $options['tag'];
        $controllerName = "";
        if (isset($options['controller']))
            $controllerName = $options['controller'];
        $controllerMethod = "";
        if (isset($options['method']))
            $controllerMethod = $options['method'];
        $controllerAct = "";
        if (isset($options['action']))
            $controllerAct = $options['action'];
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

        $err = new ErrorController($controllerName, $controllerMethod, $controllerAct, $message, $tag, $code, $previous);
        $err->setAdditionalMessage($additionalMessage);
        $err->setAdditionalData($additionalData);
        $err->setFile($file);
        $err->setLine($line);
        return $err;
    }
}