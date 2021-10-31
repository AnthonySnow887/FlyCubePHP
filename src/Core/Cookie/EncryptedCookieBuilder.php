<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 20.08.21
 * Time: 14:29
 */

namespace FlyCubePHP\Core\Cookie;

include_once __DIR__.'/../../HelperClasses/CoreHelper.php';

use \FlyCubePHP\Core\Config\Config as Config;

class EncryptedCookieBuilder
{
    private $_salt = "authenticated encrypted cookie";
    private $_cipher = "aes-256-gcm";

    function __construct(string $salt) {
        if (version_compare(PHP_VERSION, '7.1') < 0)
            $this->_cipher = "aes-256-cbc";
        if (!empty($salt))
            $this->_salt = $salt;
        if (!in_array($this->_cipher, openssl_get_cipher_methods()))
            throw new \RuntimeException("[EncryptedCookieBuilder] Not found OpenSSL cipher (name: '$this->_cipher')!");
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
        $ivlen = openssl_cipher_iv_length($this->_cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $tag = "";
        if (version_compare(PHP_VERSION, '7.1') < 0)
            $ciphertext = openssl_encrypt($cookieValue, $this->_cipher, $tmpKey, $options = 0, $iv);
        else
            $ciphertext = openssl_encrypt($cookieValue, $this->_cipher, $tmpKey, $options = 0, $iv, $tag);

        if ($ciphertext === false)
            return null;

        // --- build output data ---
        $outData = base64_encode($ciphertext);
        $outIv = base64_encode($iv);
        $outAuthTag = base64_encode($tag);
        return urlencode("$outData--$outIv--$outAuthTag");
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
        $valLst = explode('--', urldecode($val));
        if (count($valLst) !== 3)
            return $def;
        $data = base64_decode($valLst[0]);
        $iv = base64_decode($valLst[1]);
        $authTag = base64_decode($valLst[2]);

        $tmpSecret = Config::instance()->secretKey();
        $tmpKey = crypt($tmpSecret, $this->_salt);
        if (version_compare(PHP_VERSION, '7.1') < 0)
            $cookieValue = openssl_decrypt($data, $this->_cipher, $tmpKey, 0, $iv);
        else
            $cookieValue = openssl_decrypt($data, $this->_cipher, $tmpKey, 0, $iv, $authTag);

        if ($cookieValue === false)
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