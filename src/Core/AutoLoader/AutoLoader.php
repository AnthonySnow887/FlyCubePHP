<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 22.07.21
 * Time: 13:02
 */

namespace FlyCubePHP\Core\AutoLoader;

use Exception;
use FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;

class AutoLoader
{
    private static $_instance = null;

    const AUTOLOAD_CONFIG = "autoload.json";

    private $_dirs = array();
    private $_libs = array();

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
        $this->loadConfig();
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
     * Добавить библиотеку и ее каталог для автоматической загрузки файлов из него
     * @param string $libRootNamespace Корневой префикс namespace библиотеки
     * @param string $libRootDir Корневой каталог библиотеки
     *
     * ==== Example
     *
     *  appendAutoLoadLib('cebe\\markdown', 'vendor/Markdown');
     */
    public function appendAutoLoadLib(string $libRootNamespace, string $libRootDir) {
        if (empty($libRootNamespace)
            || empty($libRootDir)
            || !is_dir($libRootDir)
            || isset($this->_libs[$libRootNamespace]))
            return;
        $this->_libs[$libRootNamespace] = $libRootDir;
    }

    /**
     * Метод поиска и автоматической подгрузки файлов php
     * @param $classname
     */
    public function searchAndLoad($classname) {
        // --- search in dirs ---
        foreach ($this->_dirs as $dir) {
            $filename = $dir . str_replace('\\', '/', $classname) .'.php';
            if (file_exists($filename)) {
                require_once $filename;
                break;
            }
        }
        // --- search in libs ---
        $classNamespace = $this->classNamespace($classname);
        foreach ($this->_libs as $key => $value) {
            if (strcmp($key, $classNamespace) !== 0
                && strpos($classNamespace, $key) !== 0)
                continue;
            $filename = str_replace('\\', '/', $classname) .'.php';
            $filename = str_replace(str_replace('\\', '/', $key), $value, $filename);
            if (file_exists($filename)) {
                require_once $filename;
                return;
            }
        }
    }

    /**
     * Получить namespace для класса
     * @param string $classname
     * @return string
     */
    private function classNamespace(string $classname): string {
        return join(array_slice(explode('\\', $classname), 0, -1), '\\');
    }

    /**
     * Получить имя класса без namespace
     * @param string $classname
     * @return string
     */
    private function classBasename(string $classname): string {
        $tmpArr = explode('\\', $classname);
        return end($tmpArr);
    }

    /**
     * Загрузить конфигурационный файл
     */
    private function loadConfig() {
        $path = CoreHelper::buildPath(CoreHelper::rootDir(), 'config', static::AUTOLOAD_CONFIG);
        if (!is_readable($path))
            return;
        $configDataJSON = json_decode(file_get_contents($path), true);
        if (!is_array($configDataJSON))
            throw new \RuntimeException("[AutoLoader][loadConfig] Invalid autoload configs (invalid JSON)! Path: $path");
        if (isset($configDataJSON['dirs'])
            && is_array($configDataJSON['dirs'])) {
            $this->_dirs = array_merge($this->_dirs, array_values($configDataJSON['dirs']));
        }
        if (isset($configDataJSON['libs'])
            && is_array($configDataJSON['libs'])) {
            $this->_libs = array_merge($this->_libs, $configDataJSON['libs']);
        }
    }
}

// --- register autoload function ---
spl_autoload_register(function ($classname) {
    \FlyCubePHP\Core\AutoLoader\AutoLoader::instance()->searchAndLoad($classname);
});