<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 22.07.21
 * Time: 13:02
 */

namespace FlyCubePHP\Core\AutoLoader;

use Exception;

class AutoLoader
{
    private static $_instance = null;

    private $_dirs = array();

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function instance(): AutoLoader {
        if (static::$_instance === null)
            static::$_instance = new static();
        return static::$_instance;
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     */
    private function __construct() {
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
     * Добавить каталог для автоматической загрузки файлов из него
     * @param string $dirPath
     */
    public function appendAutoLoadDir(string $dirPath) {
        if (empty($dirPath) || !is_dir($dirPath))
            return;
        $this->_dirs[] = $dirPath;
    }

    /**
     * Метод поиска и автоматической подгрузки файлов php
     * @param $classname
     */
    public function searchAndLoad($classname) {
        foreach ($this->_dirs as $dir) {
            $filename = $dir . str_replace('\\', '/', $classname) .'.php';
            if (file_exists($filename)) {
                require_once $filename;
                break;
            }
        }
    }
}

// --- register autoload function ---
spl_autoload_register(function ($classname) {
    \FlyCubePHP\Core\AutoLoader\AutoLoader::instance()->searchAndLoad($classname);
});