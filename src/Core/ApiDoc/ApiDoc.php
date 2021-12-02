<?php

namespace FlyCubePHP\Core\ApiDoc;

use Exception;
use FlyCubePHP\Core\Config\Config as Config;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\Error\Error;

include_once 'ApiDocObject.php';
include_once __DIR__.'/../../HelperClasses/CoreHelper.php';
include_once __DIR__.'/../../ComponentsCore/ComponentsManager.php';

class ApiDoc
{
    private static $_instance = null;

    private $_isEnabled = false;
    private $_cacheList = array();
    private $_apiDocDirs = array();

    const SETTINGS_DIR      = "tmp/cache/FlyCubePHP/api_doc/";
    const CACHE_LIST_FILE   = "cache_list.json";

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function instance(): ApiDoc {
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
        $this->_isEnabled = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_API_DOC, false));
        if ($this->_isEnabled === false)
            return;
        $this->loadCacheList();
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
     * Включен ли API-Doc
     * @return bool
     */
    public function isEnabled(): bool {
        return $this->_isEnabled;
    }

    /**
     * Список добавленных каталогов api-doc
     * @param bool $fullPath
     * @return array
     */
    public function apiDocDirs(bool $fullPath = false): array {
        if (!$this->_isEnabled)
            return [];
        if ($fullPath === true)
            return $this->_apiDocDirs;

        $tmpLst = array();
        foreach ($this->_apiDocDirs as $key => $value)
            $tmpLst[$key] = CoreHelper::buildAppPath($value);
        return $tmpLst;
    }

    /**
     * Добавить и просканировать каталог api-doc
     * @param string $dir
     * @throws
     */
    public function appendApiDocDir(string $dir) {
        if (!$this->_isEnabled)
            return;
        $this->loadApiDoc($dir);
    }

    /**
     * Получить объект с разобранным описанием api-doc
     * @param string $name
     * @return ApiDocObject|null
     * @throws
     */
    public function apiDoc(string $name)/*: ApiDocObject|null */ {
        if (!$this->_isEnabled || !isset($this->_cacheList[$name]))
            return null;
        return ApiDocObject::parseApiDoc($this->_cacheList[$name]);
    }

    /**
     * Загрузить список api-doc.json файлов
     * @param string $dir
     * @throws
     */
    private function loadApiDoc(string $dir) {
        if (empty($dir)
            || !is_dir($dir)
            || in_array($dir, $this->_apiDocDirs))
            return;
        $this->_apiDocDirs[] = $dir;
        $tmpApiDocLst = CoreHelper::scanDir($dir, true);
        foreach ($tmpApiDocLst as $doc) {
            $tmpName = CoreHelper::buildAppPath($doc);
            if (!preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.json)$/", $tmpName))
                continue;
            $tmpName = basename($tmpName);
            $tmpName = substr($tmpName, 0, strlen($tmpName) - 5);
            if ($tmpName[0] == DIRECTORY_SEPARATOR)
                $tmpName = ltrim($tmpName, $tmpName[0]);
            if (!class_exists($tmpName))
                throw Error::makeError([
                    'tag' => 'api-doc',
                    'message' => "Not found php class for api-doc file (needed class: $tmpName)! Path: $doc",
                    'class-name' => __CLASS__,
                    'class-method' => __FUNCTION__
                ]);
            if (!array_key_exists($tmpName, $this->_cacheList))
                $this->_cacheList[$tmpName] = $doc;
        }
        $this->updateCacheList();
    }

    /**
     * Загрузить список кэш файлов
     * @throws
     */
    private function loadCacheList() {
        $dirPath = CoreHelper::buildPath(CoreHelper::rootDir(), ApiDoc::SETTINGS_DIR);
        if (!CoreHelper::makeDir($dirPath, 0777, true))
            throw Error::makeError([
                'tag' => 'api-doc',
                'message' => "Make dir for cache settings failed! Path: $dirPath",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        $fPath = CoreHelper::buildPath($dirPath, hash('sha256', ApiDoc::CACHE_LIST_FILE));
        if (!file_exists($fPath)) {
            $this->updateCacheList();
            return;
        }
        $fData = file_get_contents($fPath);
        $this->_cacheList = json_decode($fData, true);
    }

    /**
     * Обновить и сохранить список кэш файлов
     * @throws
     */
    private function updateCacheList() {
        $dirPath = CoreHelper::buildPath(CoreHelper::rootDir(), ApiDoc::SETTINGS_DIR);
        if (!CoreHelper::makeDir($dirPath, 0777, true))
            throw Error::makeError([
                'tag' => 'api-doc',
                'message' => "Make dir for cache settings failed! Path: $dirPath",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        $fPath = CoreHelper::buildPath($dirPath, hash('sha256', ApiDoc::CACHE_LIST_FILE));
        $fData = json_encode($this->_cacheList);
        $tmpFile = tempnam($dirPath, basename($fPath));
        if (false !== @file_put_contents($tmpFile, $fData) && @rename($tmpFile, $fPath)) {
            @chmod($fPath, 0666 & ~umask());
        } else {
            throw Error::makeError([
                'tag' => 'api-doc',
                'message' => "Write file for cache settings failed! Path: $fPath",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);
        }
    }
}