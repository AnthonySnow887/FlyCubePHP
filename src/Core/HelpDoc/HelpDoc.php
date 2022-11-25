<?php

namespace FlyCubePHP\Core\HelpDoc;

include_once 'HelpDocObject.php';
include_once __DIR__.'/../Cache/APCu.php';

use \Exception;
use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\Core\Error\Error;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\Cache\APCu;

class HelpDoc
{
    private static $_instance = null;

    private $_isEnabled = false;
    private $_rebuildCache = false;
    private $_helpDocList = array();
    private $_cacheList = array();
    private $_helpDocDirs = array();

    const SETTINGS_DIR      = "tmp/cache/FlyCubePHP/help_doc/";
    const CACHE_LIST_FILE   = "cache_list.json";

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function instance(): HelpDoc {
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
        $this->_isEnabled = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_HELP_DOC, false));
        if ($this->_isEnabled === false)
            return;
        $defVal = !Config::instance()->isProduction();
        $this->_rebuildCache = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_REBUILD_CACHE, $defVal));
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
     * Включен ли Help-Doc
     * @return bool
     */
    public function isEnabled(): bool {
        return $this->_isEnabled;
    }

    /**
     * Список загруженных help-doc файлов
     * @return array
     */
    public function helpDocFiles(): array {
        if (!$this->_isEnabled)
            return [];
        return array_keys($this->_helpDocList);
    }

    /**
     * Список добавленных каталогов help-doc
     * @param bool $fullPath
     * @return array
     */
    public function helpDocDirs(bool $fullPath = false): array {
        if (!$this->_isEnabled)
            return [];
        if ($fullPath === true)
            return $this->_helpDocDirs;

        $tmpLst = array();
        foreach ($this->_helpDocDirs as $key => $value)
            $tmpLst[$key] = CoreHelper::buildAppPath($value);
        return $tmpLst;
    }

    /**
     * Добавить и просканировать каталог help-doc
     * @param string $dir
     * @throws
     */
    public function appendHelpDocDir(string $dir) {
        if (!$this->_isEnabled)
            return;
        $this->loadHelpDoc($dir);
    }

    /**
     * Получить объект с разобранным описанием help-doc
     * @param string $heading Заголовок требуемого раздела (если пустой, то возвращается весь HelpDoc)
     * @param int $level Уровень раздела (если <= 0, то игнорируется при поиске)
     * @return HelpDocObject|null
     * @throws
     */
    public function helpDoc(string $heading = "", int $level = -1)/*: HelpDocObject|null */ {
        if (!$this->_isEnabled || empty($this->_helpDocList))
            return null;
        return HelpDocObject::parseHelpDoc(array_values($this->_helpDocList), $heading, $level);
    }

    /**
     * Получить help-doc в формате markdown
     * @param string $heading Заголовок требуемого раздела (если пустой, то возвращается весь HelpDoc)
     * @param int $level Уровень раздела (если <= 0, то игнорируется при поиске)
     * @return string
     * @throws
     */
    public function helpDocMarkdown(string $heading = "", int $level = -1): string {
        if (!$this->_isEnabled)
            return "";
        $tmpName = $this->buildCacheFileName($heading, $level);
        if ($this->_rebuildCache === false) {
            if (isset($this->_cacheList[$tmpName]))
                return file_get_contents($this->_cacheList[$tmpName]);

            return file_get_contents($this->buildCacheFile($heading, $level));
        }
        return file_get_contents($this->buildCacheFile($heading, $level));
    }

    /**
     * Загрузить список help-doc.md файлов
     * @param string $dir
     * @throws
     */
    private function loadHelpDoc(string $dir) {
        if (empty($dir)
            || !is_dir($dir)
            || in_array($dir, $this->_helpDocDirs))
            return;
        $this->_helpDocDirs[] = $dir;
        if (Config::instance()->isProduction() && !$this->_rebuildCache)
            return;
        $tmpHelpDocLst = CoreHelper::scanDir($dir, [ 'recursive' => true ]);
        foreach ($tmpHelpDocLst as $doc) {
            $doc = CoreHelper::buildAppPath($doc);
            $tmpName = $doc;
            if (!preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.md)$/", $tmpName))
                continue;
            $tmpName = basename($tmpName);
            $tmpName = substr($tmpName, 0, strlen($tmpName) - 3);
            if ($tmpName[0] == DIRECTORY_SEPARATOR)
                $tmpName = ltrim($tmpName, $tmpName[0]);
            if (!array_key_exists($tmpName, $this->_helpDocList))
                $this->_helpDocList[$tmpName] = $doc;
        }
        $this->updateCacheList();
    }

    /**
     * Загрузить список кэш файлов
     * @throws
     */
    private function loadCacheList() {
        if (!APCu::isApcuEnabled()) {
            $dirPath = CoreHelper::buildPath(CoreHelper::rootDir(), HelpDoc::SETTINGS_DIR);
            if (!CoreHelper::makeDir($dirPath, 0777, true))
                throw Error::makeError([
                    'tag' => 'help-doc',
                    'message' => "Make dir for cache settings failed! Path: $dirPath",
                    'class-name' => __CLASS__,
                    'class-method' => __FUNCTION__
                ]);

            $fPath = CoreHelper::buildPath($dirPath, hash('sha256', HelpDoc::CACHE_LIST_FILE));
            if (!file_exists($fPath)) {
                $this->updateCacheList();
                return;
            }
            $fData = file_get_contents($fPath);
            $cacheList = json_decode($fData, true);
        } else {
            $cacheList = APCu::cacheData('help-doc-cache', [ 'cache-files' => [], 'hlp-files' => [] ]);
        }
        $this->_cacheList = $cacheList['cache-files'];
        $this->_helpDocList = $cacheList['hlp-files'];
    }

    /**
     * Обновить и сохранить список кэш файлов
     * @throws
     */
    private function updateCacheList() {
        if (!APCu::isApcuEnabled()) {
            $dirPath = CoreHelper::buildPath(CoreHelper::rootDir(), HelpDoc::SETTINGS_DIR);
            if (!CoreHelper::makeDir($dirPath, 0777, true))
                throw Error::makeError([
                    'tag' => 'help-doc',
                    'message' => "Make dir for cache settings failed! Path: $dirPath",
                    'class-name' => __CLASS__,
                    'class-method' => __FUNCTION__
                ]);

            $fPath = CoreHelper::buildPath($dirPath, hash('sha256', HelpDoc::CACHE_LIST_FILE));
            $fData = json_encode(['cache-files' => $this->_cacheList, 'hlp-files' => $this->_helpDocList]);
            $tmpFile = tempnam($dirPath, basename($fPath));
            if (false !== @file_put_contents($tmpFile, $fData) && @rename($tmpFile, $fPath)) {
                @chmod($fPath, 0666 & ~umask());
            } else {
                throw Error::makeError([
                    'tag' => 'help-doc',
                    'message' => "Write file for cache settings failed! Path: $fPath",
                    'class-name' => __CLASS__,
                    'class-method' => __FUNCTION__
                ]);
            }
        } else {
            APCu::setCacheData('help-doc-cache', ['cache-files' => $this->_cacheList, 'hlp-files' => $this->_helpDocList]);
            APCu::saveEncodedApcuData('help-doc-cache', ['cache-files' => $this->_cacheList, 'hlp-files' => $this->_helpDocList]);
        }
    }

    /**
     * Создать имя для файла
     * @param string $heading
     * @param int $level
     * @return string
     */
    private function buildCacheFileName(string $heading = "", int $level = -1): string {
        if ($level <= 0)
            $level = 0;
        return CoreHelper::camelcase("$level-$heading");
    }

    /**
     * Создать кэш файл и вернуть путь до него
     * @param string $heading
     * @param int $level
     * @return string
     * @throws
     */
    private function buildCacheFile(string $heading, int $level): string {
        $obj = $this->helpDoc($heading, $level);
        if (is_null($obj))
            throw Error::makeError([
                'tag' => 'help-doc',
                'message' => "Not found needed help-doc object! Level: $level; Heading: $heading",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);
        if ($obj->isEmpty())
            throw Error::makeError([
                'tag' => 'help-doc',
                'message' => "Help-doc object is Empty! Level: $level; Heading: $heading",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        $fLastModified = -1;
        $tmpName = $this->buildCacheFileName($heading, $level);
        $cacheSettings = $this->generateCacheSettings($tmpName, $fLastModified);
        if (empty($cacheSettings["f-dir"]) || empty($cacheSettings["f-path"]))
            throw Error::makeError([
                'tag' => 'help-doc',
                'message' => "Invalid cache settings for help-doc file! Name: $tmpName",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        if (!$this->writeCacheFile($cacheSettings["f-dir"], $cacheSettings["f-path"], $obj->buildMarkdown()))
            throw Error::makeError([
                'tag' => 'help-doc',
                'message' => "Write cache help-doc file failed! Name: $tmpName",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        // --- update cache settings ---
        $this->_cacheList[$tmpName] = $cacheSettings["f-path"];
        $this->updateCacheList();
        return $cacheSettings["f-path"];
    }

    /**
     * Сгенерировать настройки для кэширования файла
     * @param string $name
     * @param int $lastModified
     * @return array
     */
    private function generateCacheSettings(string $name, int $lastModified = -1): array {
        if (strlen($name) === 0)
            return array("f-dir" => "", "f-path" => "");
        if ($lastModified <= 0)
            $lastModified = time();
        $hash = hash('sha256', basename($name) . $lastModified);
        $fDir = CoreHelper::buildPath(HelpDoc::SETTINGS_DIR, $hash[0].$hash[1]);
        $fPath = CoreHelper::buildPath($fDir, basename($name)."_$hash.md");
        return array("f-dir" => $fDir, "f-path" => $fPath);
    }

    /**
     * Записать кэш файл
     * @param string $dirPath - каталог файла
     * @param string $filePath - полный путь до файла с его именем
     * @param string $fileData - данные
     * @return bool
     * @throws
     */
    private function writeCacheFile(string $dirPath,
                                    string $filePath,
                                    string $fileData): bool {
        if (empty($dirPath) || empty($filePath))
            return false;
        if (!CoreHelper::makeDir($dirPath, 0777, true))
            throw Error::makeError([
                'tag' => 'help-doc',
                'message' => "Make dir for cache help-doc file failed! Path: $dirPath",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        $tmpFile = tempnam($dirPath, basename($filePath));
        if (false !== @file_put_contents($tmpFile, $fileData) && @rename($tmpFile, $filePath)) {
            @chmod($filePath, 0666 & ~umask());
            return true;
        }
        return false;
    }
}