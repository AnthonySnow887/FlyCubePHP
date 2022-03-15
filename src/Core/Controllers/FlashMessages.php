<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 23.08.21
 * Time: 12:32
 */

namespace FlyCubePHP\Core\Controllers;

include_once __DIR__.'/../Session/Session.php';

use Exception;
use FlyCubePHP\Core\Session\Session;

class FlashMessages
{
    private static $_instance = null;

    private $_flashHash = array();

    private $_selected = false;
    private $_flashKeysOld = array();

    const KEY = "action_dispatch.request.flash_hash";

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function instance(): FlashMessages {
        if (static::$_instance === null)
            static::$_instance = new static();
        return static::$_instance;
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     */
    private function __construct() {
        $this->_flashHash = Session::instance()->value(FlashMessages::KEY, array());
        Session::instance()->removeValue(FlashMessages::KEY);
    }

    function __destruct() {
        $this->_flashHash = $this->prepareHash();
        if (!is_null($this->_flashHash)
            && !empty($this->_flashHash))
            Session::instance()->setValue(FlashMessages::KEY, $this->_flashHash);
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
     * Получить сообщение типа 'alert'
     * @return string
     */
    public static function alert(): string {
        return FlashMessages::instance()->value('alert', "");
    }

    /**
     * Задать сообщение типа 'alert'
     * @param string $val
     */
    public static function setAlert(string $val) {
        FlashMessages::instance()->setValue('alert', $val);
    }

    /**
     * Получить сообщение типа 'notice'
     * @return string
     */
    public static function notice(): string {
        return FlashMessages::instance()->value('notice', "");
    }

    /**
     * Задать сообщение типа 'notice'
     * @param string $val
     */
    public static function setNotice(string $val) {
        FlashMessages::instance()->setValue('notice', $val);
    }

    /**
     * Содержится ли в массиве ключ
     * @param string $key - ключ
     * @return bool
     */
    public function containsKey(string $key): bool {
        return isset($this->_flashHash[$key]);
    }

    /**
     * Получить список ключей массива
     * @return array
     */
    public function keys(): array {
        return array_keys($this->_flashHash);
    }

    /**
     * Получить все значения массива
     * @return array
     */
    public function allValues(): array {
        $this->prepareOldKeys();
        return $this->_flashHash;
    }

    /**
     * Получить значение ключа
     * @param string $key - ключ
     * @param mixed $def - базовое значение
     * @return mixed|null
     */
    public function value(string $key, $def = null) {
        $this->prepareOldKeys();
        if (isset($this->_flashHash[$key]))
            return $this->_flashHash[$key];
        return $def;
    }

    /**
     * Задать значение ключа
     * @param string $key - ключ
     * @param mixed $value - значение
     */
    public function setValue(string $key, $value) {
        $this->unsetOldKey($key);
        $this->_flashHash[$key] = $value;
    }

    /**
     * Удалить ключ и значение
     * @param string $key - ключ
     */
    public function removeValue(string $key) {
        if (isset($this->_flashHash[$key]))
            unset($this->_flashHash[$key]);
    }

    /**
     * Очистить всю сессию
     */
    public function clearAll() {
        $this->_flashHash = array();
    }

    /**
     * Подготовка списка старых ключей
     */
    private function prepareOldKeys() {
        if ($this->_selected === true)
            return;
        $this->_flashKeysOld = $this->keys();
        $this->_selected = true;
    }

    /**
     * Удаление ключа из списка старых ключей, т.к. выставлено новое значение
     * @param string $key
     */
    private function unsetOldKey(string $key) {
        $this->prepareOldKeys();
        if (isset($this->_flashKeysOld[$key]))
            unset($this->_flashKeysOld[$key]);
    }

    /**
     * Подготовка нового хэша
     * @return array
     *
     * NOTE: prepareOldKeys не выполняется, т.к. очистка должна происходить только если были запрошены/заданы данные.
     */
    private function prepareHash(): array {
        foreach ($this->_flashKeysOld as $item) {
            if (isset($this->_flashHash[$item]))
                unset($this->_flashHash[$item]);
        }
        return $this->_flashHash;
    }
}