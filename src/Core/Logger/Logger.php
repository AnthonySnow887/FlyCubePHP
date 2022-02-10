<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 08.09.21
 * Time: 13:01
 */

namespace FlyCubePHP\Core\Logger;

include_once __DIR__.'/../Error/Error.php';
include_once __DIR__.'/../Routes/RouteCollector.php';
include_once __DIR__.'/../../HelperClasses/CoreHelper.php';

use \FlyCubePHP\Core\Config\Config as Config;
use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;
use Psr\Log\LoggerInterface;
use Exception;

class Logger
{
    /**
     * Detailed debug information
     */
    const DEBUG = 100;

    /**
     * Interesting events
     *
     * Examples: User logs in, SQL logs.
     */
    const INFO = 200;

    /**
     * Uncommon events
     */
    const NOTICE = 250;

    /**
     * Exceptional occurrences that are not errors
     *
     * Examples: Use of deprecated APIs, poor use of an API,
     * undesirable things that are not necessarily wrong.
     */
    const WARNING = 300;

    /**
     * Runtime errors
     */
    const ERROR = 400;

    /**
     * Critical conditions
     *
     * Example: Application component unavailable, unexpected exception.
     */
    const CRITICAL = 500;

    /**
     * Action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     * This should trigger the SMS alerts and wake you up.
     */
    const ALERT = 550;

    /**
     * Urgent alert.
     */
    const EMERGENCY = 600;


    private static $_instance = null;

    private $_isEnabled = false;
    private $_logFolder = 'log';
    private $_logger = null;
    private $_level = Logger::DEBUG; // enable all logs
    private $_errorMessage = "";

    /**
     * gets the instance via lazy initialization (created on first usage)
     * @throws
     */
    public static function instance(): Logger {
        if (static::$_instance === null) {
            static::$_instance = new static();
            if (static::$_instance->hasErrorMessage())
                throw \FlyCubePHP\Core\Error\Error::makeError([
                    'tag' => 'core',
                    'message' => static::$_instance->errorMessage()
                ]);
        }
        return static::$_instance;
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     *
     * @throws
     */
    private function __construct() {
        // --- get default logger settings ---
        $defVal = Config::instance()->isProduction();
        $useRotate = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_ROTATE_LOG, $defVal));
        $rotateMaxFiles = intval(\FlyCubePHP\configValue(Config::TAG_LOG_ROTATE_MAX_FILES, 10));
        if ($rotateMaxFiles < 0)
            $rotateMaxFiles = 10;

        $this->_isEnabled = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_LOG, true));
        if ($this->_isEnabled === false)
            return;

        $this->_logFolder = CoreHelper::buildPath(CoreHelper::rootDir(), \FlyCubePHP\configValue(Config::TAG_LOG_FOLDER, "log"));
        if (!CoreHelper::makeDir($this->_logFolder, 0777, true)) {
            $this->_isEnabled = false;
            $this->_errorMessage = "Unable to create the log directory! Dir: $this->_logFolder.";
            return;
        }

        $defLevel = "debug";
        if (Config::instance()->isProduction())
            $defLevel = "warning";
        $this->_level = Logger::levelToInt(\FlyCubePHP\configValue(Config::TAG_LOG_LEVEL, $defLevel));

        // --- check needed classes ---
        if (!class_exists('\Monolog\Formatter\LineFormatter', true)
            || !class_exists('\Monolog\Handler\StreamHandler', true)) {
            $this->_isEnabled = false;
//            $this->_errorMessage = "Not found Monolog library!"; // TODO remove...
            return;
        }

        // --- create log formatter ---
        $dateFormat = \FlyCubePHP\configValue(Config::TAG_LOG_DATE_TIME_FORMAT, "d.m.Y H:i:s");
        $output = "[%datetime%][%level_name%] %message% %context% %extra%\r\n";
        $formatter = new \Monolog\Formatter\LineFormatter($output, $dateFormat);
        $formatter->ignoreEmptyContextAndExtra(true);

        // --- create handler ---
        $logFileName = "development.log";
        if (Config::instance()->isProduction())
            $logFileName = "production.log";

        if ($useRotate === true) {
            $fDateFormat = trim(\FlyCubePHP\configValue(Config::TAG_LOG_ROTATE_FILE_DATE_FORMAT, "Y_m_d"));
            if (empty($fDateFormat))
                $fDateFormat = "Y_m_d";
            $fNameFormat = trim(\FlyCubePHP\configValue(Config::TAG_LOG_ROTATE_FILE_NAME_FORMAT, "{date}_{filename}"));
            if (empty($fNameFormat))
                $fNameFormat = "{date}_{filename}";

            $stream = new \Monolog\Handler\RotatingFileHandler(CoreHelper::buildPath($this->_logFolder, $logFileName), $rotateMaxFiles, $this->_level);
            $stream->setFilenameFormat($fNameFormat, $fDateFormat);
            $stream->setFormatter($formatter);
        } else {
            $stream = new \Monolog\Handler\StreamHandler(CoreHelper::buildPath($this->_logFolder, $logFileName), $this->_level);
            $stream->setFormatter($formatter);
        }

        // --- create log channel ---
        $this->_logger = new \Monolog\Logger('FlyCubePHP');
        $this->_logger->pushHandler($stream);
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
     * Set default logger
     * @param LoggerInterface $logger
     * @throws \FlyCubePHP\Core\Error\Error
     */
    public function setLogger(LoggerInterface $logger) {
        if (!$this->isEnabled())
            return;
        if (is_null($logger))
            throw \FlyCubePHP\Core\Error\Error::makeError([
                'tag' => 'core',
                'message' => 'Invalid logger object!'
            ]);
        if (!is_null($this->_logger))
            unset($this->_logger);
        $this->_logger = $logger;
    }

    /**
     * Set log level
     * @param int $level
     */
    public function setLogLevel(int $level) {
        if (!$this->isEnabled())
            return;
        $this->_level = $level;
    }

    /**
     * Get current log level
     * @return int
     */
    public function logLevel(): int {
        return $this->_level;
    }

    /**
     * Check is logger valid (not null)
     * @return bool
     */
    public function isValid(): bool {
        return !is_null($this->_logger);
    }

    /**
     * Check is logger enabled
     * @return bool
     */
    public function isEnabled(): bool {
        return $this->_isEnabled;
    }

    /**
     * Get log folder full path
     * @return string
     */
    public function logFolder(): string {
        return $this->_logFolder;
    }

    /**
     * Задана ли ошибка инициализации
     * @return bool
     */
    private function hasErrorMessage(): bool {
        return !empty($this->_errorMessage);
    }

    /**
     * Получить текст ошибки
     * @return string
     */
    private function errorMessage(): string {
        return $this->_errorMessage;
    }

    /**
     * System is unusable.
     *
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     * @throws \FlyCubePHP\Core\Error\Error
     */
    static public function emergency($message, array $context = array()) {
        if (!Logger::instance()->isEnabled())
            return;
        if (!Logger::instance()->isValid())
            throw \FlyCubePHP\Core\Error\Error::makeError([
                'message' => 'Invalid logger object!',
                'tag' => 'core'
            ]);
        if (Logger::EMERGENCY < Logger::instance()->_level)
            return;
        Logger::instance()->_logger->emergency($message, Logger::prepareContext($context));
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     * @throws \FlyCubePHP\Core\Error\Error
     */
    static public function alert($message, array $context = array()) {
        if (!Logger::instance()->isEnabled())
            return;
        if (!Logger::instance()->isValid())
            throw \FlyCubePHP\Core\Error\Error::makeError([
                'message' => 'Invalid logger object!',
                'tag' => 'core'
            ]);
        if (Logger::ALERT < Logger::instance()->_level)
            return;
        Logger::instance()->_logger->alert($message, Logger::prepareContext($context));
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     * @throws \FlyCubePHP\Core\Error\Error
     */
    static public function critical($message, array $context = array()) {
        if (!Logger::instance()->isEnabled())
            return;
        if (!Logger::instance()->isValid())
            throw \FlyCubePHP\Core\Error\Error::makeError([
                'message' => 'Invalid logger object!',
                'tag' => 'core'
            ]);
        if (Logger::CRITICAL < Logger::instance()->_level)
            return;
        Logger::instance()->_logger->critical($message, Logger::prepareContext($context));
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     * @throws \FlyCubePHP\Core\Error\Error
     */
    static public function error($message, array $context = array()) {
        if (!Logger::instance()->isEnabled())
            return;
        if (!Logger::instance()->isValid())
            throw \FlyCubePHP\Core\Error\Error::makeError([
                'message' => 'Invalid logger object!',
                'tag' => 'core'
            ]);
        if (Logger::ERROR < Logger::instance()->_level)
            return;
        Logger::instance()->_logger->error($message, Logger::prepareContext($context));
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     * @throws \FlyCubePHP\Core\Error\Error
     */
    static public function warning($message, array $context = array()) {
        if (!Logger::instance()->isEnabled())
            return;
        if (!Logger::instance()->isValid())
            throw \FlyCubePHP\Core\Error\Error::makeError([
                'message' => 'Invalid logger object!',
                'tag' => 'core'
            ]);
        if (Logger::WARNING < Logger::instance()->_level)
            return;
        Logger::instance()->_logger->warning($message, Logger::prepareContext($context));
    }

    /**
     * Normal but significant events.
     *
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     * @throws \FlyCubePHP\Core\Error\Error
     */
    static public function notice($message, array $context = array()) {
        if (!Logger::instance()->isEnabled())
            return;
        if (!Logger::instance()->isValid())
            throw \FlyCubePHP\Core\Error\Error::makeError([
                'message' => 'Invalid logger object!',
                'tag' => 'core'
            ]);
        if (Logger::NOTICE < Logger::instance()->_level)
            return;
        Logger::instance()->_logger->notice($message, Logger::prepareContext($context));
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     * @throws \FlyCubePHP\Core\Error\Error
     */
    static public function info($message, array $context = array()) {
        if (!Logger::instance()->isEnabled())
            return;
        if (!Logger::instance()->isValid())
            throw \FlyCubePHP\Core\Error\Error::makeError([
                'message' => 'Invalid logger object!',
                'tag' => 'core'
            ]);
        if (Logger::INFO < Logger::instance()->_level)
            return;
        Logger::instance()->_logger->info($message, Logger::prepareContext($context));
    }

    /**
     * Detailed debug information.
     *
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     * @throws \FlyCubePHP\Core\Error\Error
     */
    static public function debug($message, array $context = array()) {
        if (!Logger::instance()->isEnabled())
            return;
        if (!Logger::instance()->isValid())
            throw \FlyCubePHP\Core\Error\Error::makeError([
                'message' => 'Invalid logger object!',
                'tag' => 'core'
            ]);
        if (Logger::DEBUG < Logger::instance()->_level)
            return;
        Logger::instance()->_logger->debug($message, Logger::prepareContext($context));
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed   $level
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     *
     * @throws \Psr\Log\InvalidArgumentException
     * @throws \FlyCubePHP\Core\Error\Error
     */
    static public function log($level, $message, array $context = array()) {
        if (!Logger::instance()->isEnabled())
            return;
        if (!Logger::instance()->isValid())
            throw \FlyCubePHP\Core\Error\Error::makeError([
                'message' => 'Invalid logger object!',
                'tag' => 'core'
            ]);
        if ($level < Logger::instance()->_level)
            return;
        Logger::instance()->_logger->log($level, $message, Logger::prepareContext($context));
    }

    /**
     * Convert string level to int
     * @param string $level
     * @return int
     */
    static private function levelToInt(string $level): int {
        $level = strtolower(trim($level));
        if (strcmp($level, 'debug') === 0)
            return Logger::DEBUG;
        if (strcmp($level, 'info') === 0)
            return Logger::INFO;
        if (strcmp($level, 'warning') === 0)
            return Logger::WARNING;
        if (strcmp($level, 'error') === 0)
            return Logger::ERROR;

        return Logger::DEBUG;
    }

    /**
     * Метод подготовки контекса к логированию
     * @param array $context
     * @return array
     */
    static private function prepareContext(array &$context): array {
        foreach ($context as $key => $val) {
            if (!is_array($val)
                && preg_match('/.*(password|secret|private).*/', strtolower(strval($key))) === 1)
                $context[$key] = "*****";
            elseif (is_array($val))
                $context[$key] = self::prepareContext($val);
        }
        return $context;
    }
}