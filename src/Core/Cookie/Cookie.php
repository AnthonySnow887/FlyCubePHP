<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 20.08.21
 * Time: 12:22
 */

namespace FlyCubePHP\Core\Cookie;

include_once __DIR__.'/../../HelperClasses/CoreHelper.php';
include_once __DIR__.'/../Error/ErrorCookie.php';
include_once 'SignedCookieBuilder.php';
include_once 'EncryptedCookieBuilder.php';

use Exception;
use \FlyCubePHP\Core\Config\Config as Config;
use \FlyCubePHP\Core\Error\ErrorCookie as ErrorCookie;
use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;

class Cookie
{
    const ONE_HOUR_SEC      = 3600;
    const ONE_DAY_SEC       = 86400;
    const ONE_WEEK_SEC      = 604800;

    private static $_instance = null;

    private $_signedBuilder = null;
    private $_encryptedBuilder = null;

    /**
     * gets the instance via lazy initialization (created on first usage)
     * @throws
     */
    public static function instance(): Cookie {
        if (static::$_instance === null)
            static::$_instance = new static();
        return static::$_instance;
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     * @throws
     */
    private function __construct() {
        $this->_signedBuilder = new SignedCookieBuilder(Config::instance()->arg(Config::TAG_COOKIE_SIGNED_SALT, ""));
        try {
            $this->_encryptedBuilder = new EncryptedCookieBuilder(Config::instance()->arg(Config::TAG_COOKIE_ENCRYPTED_SALT, ""));
        } catch (\Exception $ex) {
            throw ErrorCookie::makeError([
                'tag' => 'cookie',
                'message' => $ex->getMessage(),
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'backtrace-shift' => 2
            ]);
        }
    }

    function __destruct() {
        unset($this->_signedBuilder);
        unset($this->_encryptedBuilder);
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
     * Содержится ли cookie
     * @param string $key - ключ
     * @return bool
     */
    public function containsCookie(string $key): bool {
        return isset($_COOKIE[$key]);
    }

    /**
     * Получить значение cookie
     * @param string $key - ключ
     * @param null|mixed $def - базовое значение
     * @return mixed|null
     */
    public function cookie(string $key, $def = null) {
        if (isset($_COOKIE[$key]))
            return $_COOKIE[$key];
        return $def;
    }

    /**
     * Задать значение cookie
     * @param string $key - ключ
     * @param array $options - параметры
     * @return bool
     * @throws
     *
     * ==== Options
     *
     * - value      - Set cookie value
     * - expires    - Set cookie expires (default: 1 day)
     * - path       - Set cookie path (default: "")
     * - domain     - Set cookie domain (default: "")
     * - secure     - Set cookie HTTPS use only (default: false)
     * - httponly   - Set cookie HTTP-Only flag (default: false)
     *
     */
    public function setCookie(string $key, array $options = []): bool {
        if (empty($key) || !isset($options["value"]))
            throw ErrorCookie::makeError([
                'tag' => 'cookie',
                'message' => "Invalid key or value (empty / not set)!",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'cookie-key' => $key,
                'cookie-options' => $options
            ]);

        $expires = time() + Cookie::ONE_DAY_SEC;
        if (isset($options["expires"]))
            $expires = intval($options["expires"]);

        $path = "";
        if (isset($options["path"]))
            $path = strval($options["path"]);

        $domain = "";
        if (isset($options["domain"]))
            $domain = strval($options["domain"]);

        $secure = false;
        if (isset($options["secure"]))
            $secure = CoreHelper::toBool($options["secure"]);

        $httponly = false;
        if (isset($options["httponly"]))
            $httponly = CoreHelper::toBool($options["httponly"]);

        if (!isset($_SERVER['SERVER_ADDR']))
            return false;
        return setcookie($key, $options["value"], $expires, $path, $domain, $secure, $httponly);
    }

    /**
     * Получить Signed Cookie
     * @param string $key - ключ
     * @param null|mixed $def - базовое значение
     * @return mixed
     */
    public function signedCookie(string $key, $def = null) {
        return $this->_signedBuilder->parseCookie($this->cookie($key, $def), $def);
    }

    /**
     * Задать Signed Cookie
     * @param string $key - ключ
     * @param array $options - параметры
     * @return bool
     * @throws
     *
     * ==== Options
     *
     * - value      - Set cookie value
     * - expires    - Set cookie expires (default: 1 day)
     * - path       - Set cookie path (default: "")
     * - domain     - Set cookie domain (default: "")
     * - secure     - Set cookie HTTPS use only (default: false)
     * - httponly   - Set cookie HTTP-Only flag (default: false)
     *
     */
    public function setSignedCookie(string $key, array $options = []): bool {
        if (empty($key) || !isset($options["value"]))
            throw ErrorCookie::makeError([
                'tag' => 'cookie',
                'message' => "Invalid key or value (empty / not set)!",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'cookie-key' => $key,
                'cookie-options' => $options
            ]);

        if (!isset($options["expires"]))
            $options["expires"] = time() + Cookie::ONE_DAY_SEC;

        $tmpValue = $this->_signedBuilder->buildCookie($key, $options);
        $options["value"] = $tmpValue;
        return $this->setCookie($key, $options);
    }

    /**
     * Получить Encrypted Cookie
     * @param string $key - ключ
     * @param null|mixed $def - базовое значение
     * @return mixed
     */
    public function encryptedCookie(string $key, $def = null) {
        return $this->_encryptedBuilder->parseCookie($this->cookie($key, $def), $def);
    }

    /**
     * Задать Encrypted Cookie
     * @param string $key - ключ
     * @param array $options - параметры
     * @return bool
     * @throws
     *
     * ==== Options
     *
     * - value      - Set cookie value
     * - expires    - Set cookie expires (default: 1 day)
     * - path       - Set cookie path (default: "")
     * - domain     - Set cookie domain (default: "")
     * - secure     - Set cookie HTTPS use only (default: false)
     * - httponly   - Set cookie HTTP-Only flag (default: false)
     *
     */
    public function setEncryptedCookie(string $key, array $options = []): bool {
        if (empty($key) || !isset($options["value"]))
            throw ErrorCookie::makeError([
                'tag' => 'cookie',
                'message' => "Invalid key or value (empty / not set)!",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'cookie-key' => $key,
                'cookie-options' => $options
            ]);

        if (!isset($options["expires"]))
            $options["expires"] = time() + Cookie::ONE_DAY_SEC;

        $tmpValue = $this->_encryptedBuilder->buildCookie($key, $options);
        $options["value"] = $tmpValue;
        return $this->setCookie($key, $options);
    }

    /**
     * Удалить cookie
     * @param string $key - ключ
     */
    public function removeCookie(string $key) {
        if (isset($_COOKIE[$key])) {
            unset($_COOKIE[$key]);
            setcookie($key, null, -1);
            setcookie($key, null, -1, '/');
        }
    }

    /**
     * Очистить все cookie
     */
    public function clearAll() {
        if (!isset($_SERVER['HTTP_COOKIE']))
            return;
        $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
        foreach($cookies as $cookie) {
            $parts = explode('=', $cookie);
            $key = trim($parts[0]);
            $this->removeCookie($key);
        }
    }
}