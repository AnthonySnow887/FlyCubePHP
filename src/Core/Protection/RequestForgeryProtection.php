<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 16.08.21
 * Time: 16:52
 */

namespace FlyCubePHP\Core\Protection;

include_once __DIR__.'/../../HelperClasses/CoreHelper.php';
include_once __DIR__.'/../Routes/RouteCollector.php';
include_once __DIR__.'/../Session/Session.php';
include_once __DIR__.'/../Error/Error.php';

use Exception;
use \FlyCubePHP\Core\Config\Config as Config;
use FlyCubePHP\Core\Logger\Logger;
use \FlyCubePHP\Core\Session\Session as Session;
use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;
use \FlyCubePHP\Core\Routes\RouteCollector as RouteCollector;


class RequestForgeryProtection
{
    const SECRET_TOKEN_LENGTH = 64;
    const AUTHENTICITY_TOKEN_LENGTH = 32;
    const GLOBAL_CSRF_TOKEN_IDENTIFIER = "!real_csrf_token";

    private $_protectFromForgery = true;

    private static $_instance = null;

    /**
     * Сгенерировать секретный ключ
     * @param null|int $length
     * @return string
     * @throws Exception
     */
    public static function makeSecretKey($length = null): string {
        if (is_null($length) || intval($length) <= 0)
            $length = RequestForgeryProtection::SECRET_TOKEN_LENGTH;
        return bin2hex(random_bytes($length));
    }

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function instance(): RequestForgeryProtection {
        if (static::$_instance === null)
            static::$_instance = new static();
        return static::$_instance;
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     */
    private function __construct() {
        $this->_protectFromForgery = CoreHelper::toBool(Config::instance()->arg(Config::TAG_CSRF_PROTECT, true));
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
     * Включена ли поддержка CSRF Protection
     * @return bool
     */
    public function isProtectFromForgery(): bool {
        return $this->_protectFromForgery;
    }

    /**
     * Сгенерировать ключ аутентификации
     * @return string
     * @throws Exception
     */
    public function formAuthenticityToken(): string {
        return urlencode($this->maskedAuthenticityToken());
    }

    /**
     * Проверка входящего запроса
     * @return bool
     * @throws Exception
     */
    public function isVerifiedRequest(): bool {
        return !$this->isProtectFromForgery()
            || RouteCollector::isCurrentRouteMethodGET()
            || $this->isValidAuthenticityToken($this->formAuthenticityParam())
            || $this->isValidAuthenticityToken(RouteCollector::currentRouteHeader('X-CSRF-Token'));
    }

    /**
     * Запросить параметр токена аутентификации
     * @return string
     */
    private function formAuthenticityParam(): string {
        $params = RouteCollector::currentRouteArgs();
        if (isset($params['authenticity_token']))
            return $params['authenticity_token'];
        return "";
    }

    /**
     * Проверка ключа аутентификации на корректность
     * @param string $encodedMaskedToken
     * @return bool
     * @throws Exception
     */
    private function isValidAuthenticityToken($encodedMaskedToken): bool {
        if (empty($encodedMaskedToken)
            || !is_string($encodedMaskedToken))
            return false;
        $maskedToken = base64_decode(urldecode($encodedMaskedToken), true);
        if ($maskedToken === false)
            return false;
        if (strlen($maskedToken) === RequestForgeryProtection::AUTHENTICITY_TOKEN_LENGTH) {
            return $this->compareWithRealToken($maskedToken);
        } elseif (strlen($maskedToken) === RequestForgeryProtection::AUTHENTICITY_TOKEN_LENGTH * 2) {
            $csrfToken = $this->unmaskToken($maskedToken);
            return ($this->compareWithGlobalToken($csrfToken) || $this->compareWithRealToken($csrfToken));
        }
        return false;
    }

    /**
     * Сгенерировать маскированный токен аутентификации
     * @return string
     * @throws Exception
     */
    private function maskedAuthenticityToken(): string {
        return $this->maskToken($this->globalCSRFToken());
    }

    /**
     * Сгенерировать оригинальный токен аутентификации
     * @return string
     * @throws Exception
     */
    private function realCSRFToken(): string {
        if (isset($_SERVER['SERVER_ADDR'])
            && !Session::instance()->isInit())
            throw new \FlyCubePHP\Core\Error\Error("Init php-session failed! Save real CSRF token failed!", "verify-authenticity-token");
        if (!Session::instance()->containsKey('_csrf_token'))
            Session::instance()->setValue('_csrf_token', base64_encode(random_bytes(RequestForgeryProtection::AUTHENTICITY_TOKEN_LENGTH)));

        return base64_decode(Session::instance()->value('_csrf_token'), true);
    }

    /**
     * Сгенерировать глобальный токен аутентификации
     * @return string
     * @throws Exception
     */
    private function globalCSRFToken(): string {
        if (Config::instance()->isDevelopment())
            $identifier = RequestForgeryProtection::GLOBAL_CSRF_TOKEN_IDENTIFIER;
        else
            $identifier = Config::instance()->secretKey();

        return $this->csrfTokenHmac($identifier);
    }

    /**
     * Сгенерировать хэш токена аутентификации
     * @param string $identifier
     * @return string
     * @throws Exception
     */
    private function csrfTokenHmac(string $identifier): string {
        return hash_hmac('sha256', $this->realCSRFToken(), $identifier, true);
    }

    /**
     * XOR битовых строк
     * @param string $s1
     * @param string $s2
     * @return string
     * @throws
     */
    private function xorByteString(string $s1, string $s2): string {
        if (strlen($s1) != strlen($s2))
            throw new \FlyCubePHP\Core\Error\Error("Invalid string size [strlen(s1) != strlen(s2)]!", "verify-authenticity-token");
        $s1Bytes = unpack('C*', $s1);
        $s2Bytes = unpack('C*', $s2);
        for ($i = 1; $i <= count($s1Bytes); $i++)
            $s2Bytes[$i] = $s2Bytes[$i] ^ $s1Bytes[$i];

        return implode(array_map("chr", $s2Bytes));
    }

    /**
     * Размаскировать токен
     * @param string $maskedToken
     * @return string
     * @throws
     */
    private function unmaskToken(string $maskedToken): string {
        // Split the token into the one-time pad and the encrypted
        // value and decrypt it.
        $oneTimePad = substr($maskedToken, 0, RequestForgeryProtection::AUTHENTICITY_TOKEN_LENGTH);
        $encryptedCsrfToken = substr($maskedToken, RequestForgeryProtection::AUTHENTICITY_TOKEN_LENGTH, strlen($maskedToken));
        return $this->xorByteString($oneTimePad, $encryptedCsrfToken);
    }

    /**
     * Маскировать токен
     * @param string $rawToken
     * @return string
     * @throws Exception
     */
    private function maskToken(string $rawToken): string {
        $oneTimePad = random_bytes(RequestForgeryProtection::AUTHENTICITY_TOKEN_LENGTH);
        $encryptedCsrfToken = $this->xorByteString($oneTimePad, $rawToken);
        $maskedToken = $oneTimePad . $encryptedCsrfToken;
        return base64_encode($maskedToken);
    }

    /**
     * Сравнение с реальным токеном
     * @param string $token
     * @return bool
     * @throws Exception
     */
    private function compareWithRealToken(string $token): bool {
        return hash_equals($token, $this->realCSRFToken());
    }

    /**
     * Сравнение с глобальным токеном
     * @param string $token
     * @return bool
     * @throws Exception
     */
    private function compareWithGlobalToken(string $token): bool {
        return hash_equals($token, $this->globalCSRFToken());
    }
}