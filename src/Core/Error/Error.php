<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 24.08.21
 * Time: 14:53
 */

namespace FlyCubePHP\Core\Error;

include_once 'ErrorType.php';


class Error extends \Exception
{
    protected $_type = ErrorType::DEFAULT;

    private $_tag = "";
    private $_additionalMessage = "";
    private $_additionalData = array();

    public function __construct(string $message = "",
                                string $tag = "",
                                int $code = 0,
                                \Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->_tag = $tag;
    }

    final public function type(): int {
        return $this->_type;
    }

    final public function tag(): string {
        return $this->_tag;
    }

    final public function hasAdditionalMessage(): bool {
        return !empty($this->_additionalMessage);
    }

    final public function additionalMessage(): string {
        return $this->_additionalMessage;
    }

    final public function setAdditionalMessage(string $msg) {
        $this->_additionalMessage = $msg;
    }

    final public function hasAdditionalData(): bool {
        return !empty($this->_additionalData);
    }

    final public function appendAdditionalData(string $key, $value) {
        $this->_additionalData[$key] = $value;
    }

    final public function setAdditionalData(array $data) {
        unset($this->_additionalData);
        $this->_additionalData = $data;
    }

    final public function additionalData(): array {
        return $this->_additionalData;
    }

    final public function hasAdditionalDataKey(string $key): bool {
        return isset($this->_additionalData[$key]);
    }

    final public function additionalDataValue(string $key) {
        if (isset($this->_additionalData[$key]))
            return $this->_additionalData[$key];

        return null;
    }

    final public function setFile(string $file) {
        if (!empty($file))
            $this->file = $file;
    }

    final public function setLine(int $line) {
        if ($line > 0)
            $this->line = $line;
    }

    /**
     * Create Error object
     * @param array $options
     * @return Error
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
     *
     */
    static public function makeError(array $options = []) {
        $message = "";
        if (isset($options['message']))
            $message = $options['message'];
        $tag = "";
        if (isset($options['tag']))
            $tag = $options['tag'];
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

        $err = new Error($message, $tag, $code, $previous);
        $err->setAdditionalMessage($additionalMessage);
        $err->setAdditionalData($additionalData);
        $err->setFile($file);
        $err->setLine($line);
        return $err;
    }
}