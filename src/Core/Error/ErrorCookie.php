<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 26.08.21
 * Time: 11:37
 */

namespace FlyCubePHP\Core\Error;

include_once 'Error.php';

class ErrorCookie extends Error
{
    private $_className = "";
    private $_method = "";
    private $_cookieKey = "";
    private $_cookieOptions = [];

    function __construct(string $className,
                         string $method,
                         string $cookieKey,
                         array $cookieOptions,
                         string $message = "",
                         string $tag = "",
                         int $code = 0,
                         \Throwable $previous = null) {
        parent::__construct($message, $tag, $code, $previous);
        $this->_type = ErrorType::COOKIE;
        $this->_className = $className;
        $this->_method = $method;
        $this->_cookieKey = $cookieKey;
        $this->_cookieOptions = $cookieOptions;
    }

    final public function className(): string {
        return $this->_className;
    }

    final public function method(): string {
        return $this->_method;
    }

    final public function cookieKey(): string {
        return $this->_cookieKey;
    }

    final public function cookieOptions(): array {
        return $this->_cookieOptions;
    }

    final public function cookieOptionsStr(string $delimiter = ", "): string {
        $str = "";
        foreach ($this->_cookieOptions as $key => $value) {
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

    final public function cookieKeyWithOptions(): string {
        if (!empty($this->_cookieOptions))
            return "Cookie: '$this->_cookieKey'\r\n\r\n==> Where options: [" . $this->cookieOptionsStr() . "]";

        return "Cookie: '$this->_cookieKey'";
    }

    /**
     * Create Error object
     * @param array $options
     * @return ErrorCookie
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
     * - [string]       class-name          - set class name
     * - [string]       class-method        - set class method
     * - [string]       cookie-key          - set cookie key
     * - [array]        cookie-options      - set cookie options
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
        $cookieKey = "";
        if (isset($options['cookie-key']))
            $cookieKey = $options['cookie-key'];
        $cookieOpt = [];
        if (isset($options['cookie-options'])
            && is_array($options['cookie-options']))
            $cookieOpt = $options['cookie-options'];
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

        $err = new ErrorCookie($className, $classMethod, $cookieKey, $cookieOpt, $message, $tag, $code, $previous);
        $err->setAdditionalMessage($additionalMessage);
        $err->setAdditionalData($additionalData);
        $err->setFile($file);
        $err->setLine($line);
        return $err;
    }
}