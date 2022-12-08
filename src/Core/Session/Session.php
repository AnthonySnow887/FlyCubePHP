<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 17.08.21
 * Time: 12:13
 *
 * FlyCubePHP Session encode/decode based on the code and idea described in https://github.com/psr7-sessions/session-encode-decode.
 * Released under the MIT license
 */

namespace FlyCubePHP\Core\Session;

include_once __DIR__.'/../Routes/RouteCollector.php';

use Exception;
use FlyCubePHP\Core\Logger\Logger;
use FlyCubePHP\Core\Routes\RouteCollector;
use FlyCubePHP\HelperClasses\CoreHelper;

class Session
{
    const SESSION_FILE_PREFIX = "sess_";

    private static $_instance = null;
    private $_isInit = false;
    private $_isReadOnly = false;

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function instance(): Session {
        if (static::$_instance === null)
            static::$_instance = new static();
        // --- init if not done ---
        if (!static::$_instance->isInit()) {
            $readOnly = false;
            $trace = debug_backtrace();
            if (isset($trace[1]['class'])
                && strcmp($trace[1]['class'], 'FlyCubePHP\WebSockets\Server\WSWorker') === 0)
                $readOnly = true;
            static::$_instance->init($readOnly);
        }
        return static::$_instance;
    }

    /**
     * Инициализировать настройки cookie для сессии
     */
    public static function initSessionCookieParams() {
        if (!isset($_SERVER['SERVER_ADDR']))
            return;
        if (session_status() === PHP_SESSION_ACTIVE)
            return;
        $s_cookie_params = session_get_cookie_params();
        $s_cookie_lifetime = $s_cookie_params["lifetime"];
        $s_cookie_path = RouteCollector::applicationUrlPrefix();
        $s_cookie_domain = $s_cookie_params["domain"];
        $s_cookie_secure = $s_cookie_params["secure"];
        $s_cookie_http_only = $s_cookie_params["httponly"];
        session_set_cookie_params($s_cookie_lifetime, $s_cookie_path, $s_cookie_domain, $s_cookie_secure, $s_cookie_http_only);
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     */
    private function __construct() {
    }

    function __destruct() {
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
     * Проверка, запустилась php сессия
     * @return bool
     */
    public function isInit(): bool {
        return $this->_isInit;
    }

    /**
     * Проверка, открыта ли сессия только на чтение
     * @return bool
     */
    public function isReadOnly(): bool {
        return $this->_isReadOnly;
    }

    /**
     * Задать режим открытия сессии только на чтение
     * @return bool
     *
     * NOTE: Если сессия уже открыта, то она будет переоткрыта в режиме только на чтение.
     */
    public function setReadOnly(): bool {
        if (!$this->_isReadOnly)
            $this->close();
        $init = $this->init(true);
        return $init && $this->isReadOnly();
    }

    /**
     * Содержится ли в сессии ключ
     * @param string $key - ключ
     * @return bool
     */
    public function containsKey(string $key): bool {
        return isset($_SESSION[$key]);
    }

    /**
     * Получить список ключей сессии
     * @return array
     */
    public function keys(): array {
        return array_keys($_SESSION);
    }

    /**
     * Получить значение ключа
     * @param string $key - ключ
     * @param mixed $def - базовое значение
     * @return mixed|null
     */
    public function value(string $key, $def = null) {
        if (isset($_SESSION[$key]))
            return $_SESSION[$key];
        return $def;
    }

    /**
     * Задать значение ключа
     * @param string $key - ключ
     * @param mixed $value - значение
     */
    public function setValue(string $key, $value) {
        $_SESSION[$key] = $value;
    }

    /**
     * Удалить ключ и значение
     * @param string $key - ключ
     */
    public function removeValue(string $key) {
        if (isset($_SESSION[$key]))
            unset($_SESSION[$key]);
    }

    /**
     * Очистить всю сессию
     */
    public function clearAll() {
        $_SESSION = array();
    }

    /**
     * Закрыть php сессию
     * @return bool
     */
    public function close(): bool {
        if ($this->_isInit === false)
            return false;
        // --- is read-only ---
        if ($this->_isReadOnly === true) {
            $this->_isInit = false;
            $this->_isReadOnly = false;
            $_SESSION = array();
            return true;
        }
        // --- is open ---
        $isOk = session_write_close();
        if ($isOk === true) {
            $this->_isInit = false;
            $this->_isReadOnly = false;
            $_SESSION = array();
        }
        return $isOk;
    }

    /**
     * Закрыть и удалить сессию
     * @return bool
     */
    public function destroy(): bool {
        if ($this->_isInit === false)
            return false;
        // --- is read-only ---
        if ($this->_isReadOnly === true) {
            $this->_isInit = false;
            $this->_isReadOnly = false;
            $_SESSION = array();
            return true;
        }
        // --- is open ---
        $isOk = session_destroy();
        if ($isOk === true) {
            $this->_isInit = false;
            $this->_isReadOnly = false;
            $_SESSION = array();
        }
        return $isOk;
    }

    /**
     * Инициализировать сессию
     * @param bool $readOnly - открыть сессию в режиме "только чтение"
     * @return bool
     */
    private function init(bool $readOnly = false): bool {
        if (!isset($_SERVER['SERVER_ADDR']))
            return false;
        if ($this->_isInit === true)
            return true;
        if ($readOnly !== true)
            $this->_isInit = session_start();
        // --- init read-only ---
        if ($this->_isInit === false
            && isset($_COOKIE[session_name()])
            && file_exists(CoreHelper::buildPath(session_save_path(), self::SESSION_FILE_PREFIX . $_COOKIE[session_name()]))) {
            $sData = file_get_contents(CoreHelper::buildPath(session_save_path(), self::SESSION_FILE_PREFIX . $_COOKIE[session_name()]));
            if ($sData !== false) {
                $_SESSION = $this->decode($sData);
                $this->_isInit = true;
                $this->_isReadOnly = true;
            }
        }
        return $this->_isInit;
    }

    /**
     * Упаковать данные сессии
     * @return string
     */
    private function encode(): string {
        if (empty($_SESSION)) {
            return '';
        }
        $encodedData = '';
        foreach ($_SESSION as $key => $value)
            $encodedData .= $key . '|' . serialize($value);

        return $encodedData;
    }

    /**
     * Распаковать данные сесии
     * @param string $encodedData
     * @return array
     */
    private function decode(string $encodedData): array {
        if ('' === $encodedData)
            return [];

        preg_match_all('/(^|;|\})([\w\-\.\,\:]+)\|/i', $encodedData, $match, PREG_OFFSET_CAPTURE);
        $decodedData = [];
        $lastOffset = null;
        $currentKey = '';
        foreach ($match[2] as $value) {
            $offset = $value[1];
            if (null !== $lastOffset) {
                $valueText = substr($encodedData, $lastOffset, $offset - $lastOffset);
                $decodedData[$currentKey] = unserialize($valueText);
            }
            $currentKey = $value[0];
            $lastOffset = $offset + strlen($currentKey) + 1;
        }
        $valueText = substr($encodedData, $lastOffset);
        $decodedData[$currentKey] = unserialize($valueText);
        return $decodedData;
    }
}