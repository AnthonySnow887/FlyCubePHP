<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 17.08.21
 * Time: 12:13
 */

namespace FlyCubePHP\Core\Session;

include_once __DIR__.'/../Routes/RouteCollector.php';

use Exception;
use \FlyCubePHP\Core\Routes\RouteCollector as RouteCollector;

class Session
{
    private static $_instance = null;
    private $_isInit = false;

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function instance(): Session {
        if (static::$_instance === null)
            static::$_instance = new static();
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
        if (!isset($_SERVER['SERVER_ADDR']))
            return;
        $this->_isInit = session_start();
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
     * Check is php session start success
     * @return bool
     */
    public function isInit(): bool {
        return $this->_isInit;
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
}