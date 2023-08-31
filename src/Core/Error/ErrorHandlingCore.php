<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 23.08.21
 * Time: 18:21
 */

namespace FlyCubePHP\Core\Error;

include_once 'BaseErrorHandler.php';
include_once 'DefaultErrorPage.php';
include_once __DIR__.'/../../HelperClasses/CoreHelper.php';
include_once __DIR__.'/../Logger/Logger.php';

use Exception;
use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\Core\Logger\Logger;
use FlyCubePHP\HelperClasses\CoreHelper;

class ErrorHandlingCore
{
    private static $_instance = null;
    private $_errHandler = null;
    private $_isLoaded = false;
    private $_freezeErrHandler = false;

    /**
     * Обработчик ошибок
     * @param int $errno Код ошибки
     * @param string $errstr Текст ошибки
     * @param string $errfile Файл с ошибкой
     * @param int $errline Строка с ошибкой
     * @return bool
     * @throws
     */
    static public function evalErrorHandler($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            // Этот код ошибки не включен в error_reporting,
            // так что пусть обрабатываются стандартным обработчиком ошибок PHP
            return false;
        }
        // --- clear all buffers ---
        while (ob_get_level() !== 0)
            ob_end_clean();

        $errCode = ErrorHandlingCore::fileCodeTrace($errfile, $errline);
        $backtraceArr = debug_backtrace();
        array_shift($backtraceArr); // remove current method backtrace
        $backtrace = ErrorHandlingCore::debugBacktrace($backtraceArr);
        ErrorHandlingCore::loggingError($errno, $errstr, $errfile, $errline, $errCode, $backtrace);
        return ErrorHandlingCore::instance()->evalError($errno, $errstr, $errfile, $errline, $errCode, $backtrace);
    }

    /**
     * Обработчик ошибок
     */
    static public function evalFatalErrorHandler() {
        $error = error_get_last();
        if (is_null($error))
            return;
        ErrorHandlingCore::evalErrorHandler($error["type"], $error["message"], $error["file"], $error["line"]);
    }

    /**
     * Обработчик исключений
     * @param \Throwable $ex
     * @throws
     */
    static public function evalExceptionHandler(\Throwable $ex) {
        // --- clear all buffers ---
        while (ob_get_level() !== 0)
            ob_end_clean();
        ErrorHandlingCore::loggingException($ex);
        ErrorHandlingCore::instance()->evalException($ex);
    }

    /**
     * Сохранить ошибку в журнал
     * @param int $errno Код ошибки
     * @param string $errstr Текст ошибки
     * @param string $errfile Файл с ошибкой
     * @param int $errline Строка с ошибкой
     * @throws
     */
    static public function evalLoggingError(int $errno, string $errstr, string $errfile, int $errline) {
        $errCode = ErrorHandlingCore::fileCodeTrace($errfile, $errline);
        $backtraceArr = debug_backtrace();
        array_shift($backtraceArr); // remove current method backtrace
        $backtrace = ErrorHandlingCore::debugBacktrace($backtraceArr);
        ErrorHandlingCore::loggingError($errno, $errstr, $errfile, $errline, $errCode, $backtrace);
    }

    /**
     * Сохранить исключение в журнал
     * @param \Throwable $ex
     * @throws
     */
    static public function evalLoggingException(\Throwable $ex) {
        ErrorHandlingCore::loggingException($ex);
    }

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    static public function instance(): ErrorHandlingCore {
        if (static::$_instance === null)
            static::$_instance = new static();
        return static::$_instance;
    }

    /**
     * Получить часть кода файла
     * @param string $filePath - путь до файла
     * @param int $line - строка с ошибкой (если -1, то зачитывается все содержимое файла)
     * @param int $visibleLines - количество видимых строк до и после строки с ошибкой
     * @return string
     */
    static public function fileCodeTrace(string $filePath, int $line = -1, int $visibleLines = 10): string {
        if (!is_file($filePath) || !is_readable($filePath))
            return "";
        $lines = "";
        $lineCount = 1;
        if ($file = fopen($filePath, "r")) {
            while (!feof($file)) {
                $lineStr = fgets($file);
                if ($line === -1)
                    $lines .= "#$lineCount " . $lineStr;
                else if (($lineCount >= $line - $visibleLines) && ($lineCount <= $line + $visibleLines))
                    $lines .= "#$lineCount " . $lineStr;
                else if ($lineCount > $line + $visibleLines)
                    break;

                $lineCount += 1;
            }
            fclose($file);
        }
        return $lines;
    }

    /**
     * Получить строку со списков вызовов
     * @param array $backtrace - результат вызова метода debug_backtrace()
     * @return string
     */
    static public function debugBacktrace(array $backtrace): string {
        $lines = "";
        foreach ($backtrace as $key => $value) {
            if (!isset($value['class'])
                || !isset($value['type'])
                || !isset($value['function'])
                || !isset($value['args']))
                continue;
            $file = "";
            if (isset($value['file']))
                $file = $value['file'];
            $line = "";
            if (isset($value['line']))
                $line = $value['line'];
            $class = $value['class'];
            $opType = $value['type'];
            $function = $value['function'];
            $fArgs = "";
            foreach ($value['args'] as $v) {
                if (!is_array($v)) {
                    if (!empty($fArgs))
                        $fArgs .= ", ";
                    if (is_null($v)) {
                        $v = "null";
                    } else if (is_string($v)) {
                        if (strlen($v) > 16)
                            $v = substr($v, 0, 16) . "...";
                        $v = "'$v'";
                    }
                    if (ErrorHandlingCore::canConvertToStr($v))
                        $fArgs .= $v;
                }
            }
            if (!empty($file) && !empty($line))
                $lines .= "\r\n#$key $file($line): $class" . $opType . $function . "($fArgs)";
            else
                $lines .= "\r\n#$key $class" . $opType . $function . "($fArgs)";
        }
        return $lines;
    }

    /**
     * Получить название ошибки PHP по ее коду
     * @param $type
     * @return string
     */
    static public function errorTypeByValue($type): string {
        $constants  = get_defined_constants(true);
        foreach ($constants['Core'] as $key => $value) { // Each Core constant
            if (preg_match('/^E_/', $key)) {    // Check error constants
                if ($type == $value)
                    return $key;
            }
        }
        return "???";
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     */
    private function __construct() {
        $this->_errHandler = new DefaultErrorPage();
    }

    /**
     * prevent the instance from being cloned (which would create a second instance of it)
     */
    private function __clone() {
    }

    /**
     * prevent from being unserialized (which would create a second instance of it)
     * @throws Exception Cannot unserialize singleton
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }

    /**
     * Загрузить расширения
     */
    public function loadExtensions() {
        if (!CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_EXTENSION_SUPPORT, false)))
            return;
        if ($this->_isLoaded === true)
            return;
        $this->_isLoaded = true;

        // --- include other extensions ---
        $extRoot = strval(\FlyCubePHP\configValue(Config::TAG_EXTENSIONS_FOLDER, "extensions"));
        $migratorsFolder = CoreHelper::buildPath(CoreHelper::rootDir(), $extRoot, "error_handling");
        if (is_dir($migratorsFolder)) {
            $migratorsLst = CoreHelper::scanDir($migratorsFolder);
            foreach ($migratorsLst as $item) {
                $fExt = pathinfo($item, PATHINFO_EXTENSION);
                if (strcmp(strtolower($fExt), "php") !== 0)
                    continue;
                try {
                    include_once $item;
                } catch (\Exception $e) {
//                    error_log("MigrationsCore: $e->getMessage()!\n");
//                    echo 'Выброшено исключение: ',  $e->getMessage(), "\n";
                }
            }
        }
    }

    /**
     * Задать обработчик ошибок
     * @param BaseErrorHandler $handler
     */
    public function setErrorHandler(BaseErrorHandler $handler) {
        if ($this->isErrorHandlerFreeze() === true)
            return;
        if (!is_null($this->_errHandler))
            unset($this->_errHandler);
        $this->_errHandler = $handler;
    }

    /**
     * Заблокировать установку обработчика ошибок
     */
    public function freezeErrorHandler() {
        $this->_freezeErrHandler = true;
    }

    /**
     * Заблокирована ли установка обработчика ошибок
     * @return bool
     */
    public function isErrorHandlerFreeze(): bool {
        return $this->_freezeErrHandler;
    }

    /**
     * Обработчик ошибок и вызов хэндлера
     * @param int $errno - код ошибки
     * @param string $errstr - текст с ошибкой
     * @param string $errfile - файл с ошибкой
     * @param int $errline - номер строки с ошибкой
     * @param string $errcode - часть кода файла с ошибкой
     * @param string $backtrace - стек вызовов
     * @return bool
     */
    private function evalError(int $errno,
                               string $errstr,
                               string $errfile,
                               int $errline,
                               string $errcode,
                               string $backtrace): bool {
        if (!is_null($this->_errHandler))
            return $this->_errHandler->evalError($errno, $errstr, $errfile, $errline, $errcode, $backtrace);

        return false;
    }

    /**
     * Обработка исключений и вызов хэндлера
     * @param \Throwable $ex
     */
    private function evalException(\Throwable $ex) {
        if (!is_null($this->_errHandler))
            $this->_errHandler->evalException($ex);
    }

    /**
     * Проверка, может ли объект быть преобразован в строку
     * @param $object
     * @return bool
     */
    static private function canConvertToStr($object): bool {
        if ((!is_array($object))
            && ((!is_object($object) && settype($object, 'string' ) !== false)
                || (is_object($object) && method_exists($object, '__toString'))))
            return true;

        return false;
    }

    /**
     * Логирование ошибки в журнал
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @param string $errcode
     * @param string $backtrace
     * @throws Error
     */
    static private function loggingError(int $errno,
                                         string $errstr,
                                         string $errfile,
                                         int $errline,
                                         string $errcode,
                                         string $backtrace) {
        $outName = str_replace(dirname(__DIR__), "", $errfile);
        $errorBody = "";
        $errorBody .= ErrorHandlingCore::makeTrace("Code ($outName):", $errcode, $errline, false);
        $errorBody .= ErrorHandlingCore::makeTrace('Backtrace:', $backtrace);
        Logger::error("=== FlyCubePHP: ERROR  =========");
        Logger::error("FlyCubePHP " . FLY_CUBE_PHP_VERSION);
        Logger::error("PHP " . PHP_VERSION . " (" . PHP_OS . ")");
        Logger::error("In file: " . $errfile);
        Logger::error("In line: " . $errline);
        Logger::error("Error type: " . ErrorHandlingCore::errorTypeByValue($errno));
        Logger::error("Error: " . $errstr);
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $errorBody) as $line)
            Logger::error($line);
        Logger::error("=== FlyCubePHP: END ERROR =========");
        Logger::error("");
    }

    /**
     * Логирование исключения в журнал
     * @param \Throwable $ex
     * @throws Error
     */
    static private function loggingException(\Throwable $ex) {
        $outName = str_replace(dirname(__DIR__), "", $ex->getFile());
        $errorBody = "";
        $errorBody .= ErrorHandlingCore::makeTrace("Code ($outName):", ErrorHandlingCore::fileCodeTrace($ex->getFile(), $ex->getLine()), $ex->getLine(), false);
        $errorBody .= ErrorHandlingCore::makeTrace('Backtrace:', $ex->getTraceAsString());
        Logger::error("=== FlyCubePHP: ERROR  =========");
        Logger::error("FlyCubePHP " . FLY_CUBE_PHP_VERSION);
        Logger::error("PHP " . PHP_VERSION . " (" . PHP_OS . ")");
        Logger::error("In file: " . $ex->getFile());
        Logger::error("In line: " . $ex->getLine());
        Logger::error("Error type: Throwable");
        Logger::error("Error: " . $ex->getMessage());
        if (is_subclass_of($ex, '\FlyCubePHP\Core\Error\Error')
            && $ex->hasAdditionalMessage() === true)
            Logger::error("Error: " . $ex->additionalMessage());
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $errorBody) as $line)
            Logger::error($line);
        Logger::error("=== FlyCubePHP: END ERROR =========");
        Logger::error("");
    }

    /**
     * Создание текстового представления trace
     * @param string $title
     * @param string $trace
     * @param int $lineError
     * @param bool $buildAppPath
     * @return string
     */
    static private function makeTrace(string $title,
                                      string $trace,
                                      int $lineError = -1,
                                      bool $buildAppPath = true): string {
        $tmpTraceCount = [];
        $tmpTraceData = [];
        $tmpTraceCountMaxLength = 0;
        $traceLst = [];
        preg_match_all('/.*#([0-9]{1,})\s.*/', $trace, $matches, PREG_OFFSET_CAPTURE);
        if (count($matches) >= 2) {
            $mCount = count($matches[0]);
            for ($i = 0; $i < $mCount; $i++) {
                $str = $matches[0][$i][0];
                $pos = strpos($str, "#");
                if ($pos !== false)
                    $traceLst[] = substr($str, $pos + 1, strlen($str));
            }
        }
        foreach ($traceLst as $item) {
            if (empty($item))
                continue;
            $pos = strpos($item, " ");
            if ($pos === false)
                continue;
            $num = trim(substr($item, 0, $pos + 1));
            $item = substr($item, $pos + 1, strlen($item));
            if ($buildAppPath === true)
                $item = str_replace(dirname(__DIR__), "", $item);
            $item = trim($item, "\n\r\0\x0B");
            $tmpTraceCount[] = strval($num);
            $tmpTraceData[] = strval($item);
            if (strlen(strval($num)) > $tmpTraceCountMaxLength)
                $tmpTraceCountMaxLength = strlen(strval($num));
        }
        $tmpTraceCountMaxLength += 1;
        if ($lineError !== -1)
            $tmpTraceCountMaxLength += 4;

        $tmpTrace = "";
        for ($i = 0; $i < count($tmpTraceData); $i++) {
            $str = CoreHelper::makeEvenLength($tmpTraceCount[$i], $tmpTraceCountMaxLength, 'before');
            if (intval($tmpTraceCount[$i]) == $lineError) {
                $str = CoreHelper::makeEvenLength($tmpTraceCount[$i], $tmpTraceCountMaxLength - 5, 'before');
                $str = " ==> $str";
            }
            $strVal = $tmpTraceData[$i];
            $tmpTrace .= "$str | $strVal\r\n";
        }
        $title = trim($title);
        $html = "\r\n$title\r\n";
        $html .= "-------------------------\r\n";
        $html .= "$tmpTrace\r\n";
        $html .= "-------------------------\r\n";
        return $html;
    }
}