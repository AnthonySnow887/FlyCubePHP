<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 26.08.21
 * Time: 16:32
 */

namespace FlyCubePHP\Core\Error;

include_once 'Error.php';

class ErrorAssetPipeline extends Error
{
    private $_className = "";
    private $_method = "";
    private $_assetName = "";
    private $_hasAssetCode = false;

    function __construct(string $className,
                         string $method,
                         string $assetName,
                         string $message = "",
                         string $tag = "",
                         int $code = 0,
                         \Throwable $previous = null) {
        parent::__construct($message, $tag, $code, $previous);
        $this->_type = ErrorType::ASSET_PIPELINE;
        $this->_className = $className;
        $this->_method = $method;
        $this->_assetName = $assetName;
    }

    final public function className(): string {
        return $this->_className;
    }

    final public function method(): string {
        return $this->_method;
    }

    final public function assetName(): string {
        return $this->_assetName;
    }

    final public function hasAssetCode(): bool {
        return $this->_hasAssetCode;
    }

    final public function setHasAssetCode(bool $val) {
        $this->_hasAssetCode = $val;
    }

    /**
     * Create Error object
     * @param array $options
     * @return ErrorAssetPipeline
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
     * - [string]       class-name          - asset pipeline class name
     * - [string]       class-method        - asset pipeline class method
     * - [string]       asset-name          - set asset name
     * - [bool]         has-asset-code      - set has asset file code data (default: false)
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
        $assetName = "";
        if (isset($options['asset-name']))
            $assetName = $options['asset-name'];
        $hasAssetCode = false;
        if (isset($options['has-asset-code']))
            $hasAssetCode = $options['has-asset-code'];
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

        $err = new ErrorAssetPipeline($className, $classMethod, $assetName, $message, $tag, $code, $previous);
        $err->setAdditionalMessage($additionalMessage);
        $err->setAdditionalData($additionalData);
        $err->setFile($file);
        $err->setLine($line);
        $err->setHasAssetCode($hasAssetCode);
        return $err;
    }
}