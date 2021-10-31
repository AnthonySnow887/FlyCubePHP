<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 17.08.21
 * Time: 17:56
 */

namespace FlyCubePHP\Core\Protection;

include_once __DIR__.'/../../HelperClasses/CoreHelper.php';

use Exception;
use \FlyCubePHP\Core\Config\Config as Config;
use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;

class CSPProtection
{
    const NONCE_KEY_LENGTH = 16;

    private $_isContentSecurityPolicy = false;
    private $_nonceKeys = array();
    private $_settings = array();

    private static $_instance = null;

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function instance(): CSPProtection {
        if (static::$_instance === null)
            static::$_instance = new static();
        return static::$_instance;
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     */
    private function __construct() {
        $this->_isContentSecurityPolicy = CoreHelper::toBool(Config::instance()->arg(Config::TAG_CSP_PROTECT, false));

        // --- set base settings ---
        $this->setDefaultSrc("'self'");
        $this->setScriptSrc("'self'");
        $this->setStyleSrc(["'self'", "data:"]);
        $this->setImgSrc(["'self'", "data:"]);
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
     * Включена ли поддержка CSP Protection
     * @return bool
     */
    public function isContentSecurityPolicy(): bool {
        return $this->_isContentSecurityPolicy;
    }

    /**
     * Получить уникальный ключ
     * @return string
     */
    public function nonceKey(): string {
        try {
            $key = bin2hex(random_bytes(CSPProtection::NONCE_KEY_LENGTH));
        } catch (\Exception $e) {
            return "";
        }
        $this->_nonceKeys[] = $key;
        return $key;
    }

    // --- default-src ---
    public function setDefaultSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        if (is_string($val))
            $this->_settings['default-src'] = [ strval($val) ];
        else
            $this->_settings['default-src'] = $val;
    }

    // --- script-src ---
    public function setScriptSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        if (is_string($val))
            $this->_settings['script-src'] = [ strval($val) ];
        else
            $this->_settings['script-src'] = $val;
    }

    public function appendScriptSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        $tmpArr = $this->_settings['script-src'];
        if (is_string($val) && !in_array(strval($val), $tmpArr))
            $tmpArr[] = strval($val);
        else if (is_array($val))
            $tmpArr = array_unique(array_merge($tmpArr, $val));

        $this->_settings['script-src'] = $tmpArr;
    }

    // --- style-src ---
    public function setStyleSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        if (is_string($val))
            $this->_settings['style-src'] = [ strval($val) ];
        else
            $this->_settings['style-src'] = $val;
    }

    public function appendStyleSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        $tmpArr = $this->_settings['style-src'];
        if (is_string($val) && !in_array(strval($val), $tmpArr))
            $tmpArr[] = strval($val);
        else if (is_array($val))
            $tmpArr = array_unique(array_merge($tmpArr, $val));

        $this->_settings['style-src'] = $tmpArr;
    }

    // --- img-src ---
    public function setImgSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        if (is_string($val))
            $this->_settings['img-src'] = [ strval($val) ];
        else
            $this->_settings['img-src'] = $val;
    }

    public function appendImgSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        $tmpArr = $this->_settings['img-src'];
        if (is_string($val) && !in_array(strval($val), $tmpArr))
            $tmpArr[] = strval($val);
        else if (is_array($val))
            $tmpArr = array_unique(array_merge($tmpArr, $val));

        $this->_settings['img-src'] = $tmpArr;
    }

    // --- frame-src ---
    public function setFrameSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        if (is_string($val))
            $this->_settings['frame-src'] = [ strval($val) ];
        else
            $this->_settings['frame-src'] = $val;
    }

    public function appendFrameSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        $tmpArr = $this->_settings['frame-src'];
        if (is_string($val) && !in_array(strval($val), $tmpArr))
            $tmpArr[] = strval($val);
        else if (is_array($val))
            $tmpArr = array_unique(array_merge($tmpArr, $val));

        $this->_settings['frame-src'] = $tmpArr;
    }

    // --- connect-src ---
    public function setConnectSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        if (is_string($val))
            $this->_settings['connect-src'] = [ strval($val) ];
        else
            $this->_settings['connect-src'] = $val;
    }

    public function appendConnectSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        $tmpArr = $this->_settings['connect-src'];
        if (is_string($val) && !in_array(strval($val), $tmpArr))
            $tmpArr[] = strval($val);
        else if (is_array($val))
            $tmpArr = array_unique(array_merge($tmpArr, $val));

        $this->_settings['connect-src'] = $tmpArr;
    }

    // --- font-src ---
    public function setFontSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        if (is_string($val))
            $this->_settings['font-src'] = [ strval($val) ];
        else
            $this->_settings['font-src'] = $val;
    }

    public function appendFontSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        $tmpArr = $this->_settings['font-src'];
        if (is_string($val) && !in_array(strval($val), $tmpArr))
            $tmpArr[] = strval($val);
        else if (is_array($val))
            $tmpArr = array_unique(array_merge($tmpArr, $val));

        $this->_settings['font-src'] = $tmpArr;
    }

    // --- object-src ---
    public function setObjectSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        if (is_string($val))
            $this->_settings['object-src'] = [ strval($val) ];
        else
            $this->_settings['object-src'] = $val;
    }

    public function appendObjectSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        $tmpArr = $this->_settings['object-src'];
        if (is_string($val) && !in_array(strval($val), $tmpArr))
            $tmpArr[] = strval($val);
        else if (is_array($val))
            $tmpArr = array_unique(array_merge($tmpArr, $val));

        $this->_settings['object-src'] = $tmpArr;
    }

    // --- media-src ---
    public function setMediaSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        if (is_string($val))
            $this->_settings['media-src'] = [ strval($val) ];
        else
            $this->_settings['media-src'] = $val;
    }

    public function appendMediaSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        $tmpArr = $this->_settings['media-src'];
        if (is_string($val) && !in_array(strval($val), $tmpArr))
            $tmpArr[] = strval($val);
        else if (is_array($val))
            $tmpArr = array_unique(array_merge($tmpArr, $val));

        $this->_settings['media-src'] = $tmpArr;
    }

    // --- child-src ---
    public function setChildSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        if (is_string($val))
            $this->_settings['child-src'] = [ strval($val) ];
        else
            $this->_settings['child-src'] = $val;
    }

    public function appendChildSrc($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        $tmpArr = $this->_settings['child-src'];
        if (is_string($val) && !in_array(strval($val), $tmpArr))
            $tmpArr[] = strval($val);
        else if (is_array($val))
            $tmpArr = array_unique(array_merge($tmpArr, $val));

        $this->_settings['child-src'] = $tmpArr;
    }

    // --- form-action ---
    public function setFormAction($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        if (is_string($val))
            $this->_settings['form-action'] = [ strval($val) ];
        else
            $this->_settings['form-action'] = $val;
    }

    public function appendFormAction($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        $tmpArr = $this->_settings['form-action'];
        if (is_string($val) && !in_array(strval($val), $tmpArr))
            $tmpArr[] = strval($val);
        else if (is_array($val))
            $tmpArr = array_unique(array_merge($tmpArr, $val));

        $this->_settings['form-action'] = $tmpArr;
    }

    // --- frame-ancestors ---
    public function setFrameAncestors($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        if (is_string($val))
            $this->_settings['frame-ancestors'] = [ strval($val) ];
        else
            $this->_settings['frame-ancestors'] = $val;
    }

    public function appendFrameAncestors($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        $tmpArr = $this->_settings['frame-ancestors'];
        if (is_string($val) && !in_array(strval($val), $tmpArr))
            $tmpArr[] = strval($val);
        else if (is_array($val))
            $tmpArr = array_unique(array_merge($tmpArr, $val));

        $this->_settings['frame-ancestors'] = $tmpArr;
    }

    // --- upgrade-insecure-requests ---
    public function setUpgradeInsecureRequests($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        if (is_string($val))
            $this->_settings['upgrade-insecure-requests'] = [ strval($val) ];
        else
            $this->_settings['upgrade-insecure-requests'] = $val;
    }

    public function appendUpgradeInsecureRequests($val) {
        if (!is_string($val) && !is_array($val))
            return;
        if (empty($val))
            return;
        $tmpArr = $this->_settings['upgrade-insecure-requests'];
        if (is_string($val) && !in_array(strval($val), $tmpArr))
            $tmpArr[] = strval($val);
        else if (is_array($val))
            $tmpArr = array_unique(array_merge($tmpArr, $val));

        $this->_settings['upgrade-insecure-requests'] = $tmpArr;
    }

    /**
     * Отправка HTTP заголовка Content-Security-Policy
     */
    public function processingCSPNonce() {
        if (!$this->isContentSecurityPolicy())
            return;
        foreach ($this->_nonceKeys as $item)
            $this->appendScriptSrc("'nonce-$item'");

        $tmpHeaderData = "";
        foreach ($this->_settings as $key => $value) {
            if (empty($value))
                continue;
            $valueStr = join(" ", $value);
            if (strpos($valueStr, " data:") !== false) {
                $valueStr = str_replace(" data:", "", $valueStr);
                $valueStr .= " data:"; // append to end
            }
            if (empty($tmpHeaderData))
                $tmpHeaderData = "$key $valueStr ;";
            else
                $tmpHeaderData .= " $key $valueStr ;";
        }
        if (empty($tmpHeaderData))
            return;

        header("Content-Security-Policy: $tmpHeaderData");
    }
}