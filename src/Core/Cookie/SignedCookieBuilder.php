<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 20.08.21
 * Time: 12:55
 */

namespace FlyCubePHP\Core\Cookie;

include_once __DIR__.'/../../HelperClasses/CoreHelper.php';

use FlyCubePHP\Core\Config\Config;

class SignedCookieBuilder
{
    private $_salt = "signed cookie";

    function __construct(string $salt) {
        if (!empty($salt))
            $this->_salt = $salt;
    }

    /**
     * Собрать значение cookie
     * @param string $key - ключ
     * @param array $options - параметры
     * @return string
     *
     * ==== Options
     *
     * - value      - Cookie value
     * - expires    - Cookie expires
     *
     */
    public function buildCookie(string $key, array $options = []): string {
        $message = base64_encode($options["value"]);
        $expires = time() + Cookie::ONE_DAY_SEC;
        if (isset($options["expires"]))
            $expires = intval($options["expires"]);

        $cookiePayload = [
            "_fly_cube_php" => [
                "message" => $message,
                "exp" => $expires,
                "pur" => "cookie.$key"
            ]
        ];
        $cookieValue = base64_encode(json_encode($cookiePayload));
        $tmpSecret = Config::instance()->secretKey();
        $tmpKey = crypt($tmpSecret, $this->_salt);
        $tmpDigest = hash_hmac('sha1', $cookieValue, $tmpKey);
        return urlencode($cookieValue) . "--" . $tmpDigest;
    }

    /**
     * Разобрать значение cookie
     * @param null|string $val - "сырое" значение cookie
     * @param mixed $def - базовое значение
     * @return mixed
     */
    public function parseCookie($val, $def = null) {
        if (!isset($val) || is_null($val))
            return $def;
        if (!preg_match('/.*\-\-[A-Fa-f0-9]{40}$/', $val))
            return $def;
        $valLst = explode('--', $val);
        if (count($valLst) !== 2)
            return $def;
        $cookieValue = urldecode($valLst[0]);
        $tmpSecret = Config::instance()->secretKey();
        $tmpKey = crypt($tmpSecret, $this->_salt);
        $tmpDigest = hash_hmac('sha1', $cookieValue, $tmpKey);
        if (strcmp($valLst[1], $tmpDigest) !== 0)
            return $def;
        $cookiePayload = json_decode(base64_decode($cookieValue), true);
        if (isset($cookiePayload['_fly_cube_php'])
            && isset($cookiePayload['_fly_cube_php']['message'])) {
            $message = $cookiePayload['_fly_cube_php']['message'];
            $tmpVal = base64_decode($message);
            if ($tmpVal !== false)
                return $tmpVal;
        }
        return $def;
    }
}