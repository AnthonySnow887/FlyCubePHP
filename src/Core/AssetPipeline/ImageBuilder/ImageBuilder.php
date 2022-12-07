<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 28.07.21
 * Time: 11:53
 */

namespace FlyCubePHP\Core\AssetPipeline\ImageBuilder;

include_once __DIR__.'/../../../HelperClasses/CoreHelper.php';
include_once __DIR__.'/../../Cache/APCu.php';

use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\Error\ErrorAssetPipeline;
use FlyCubePHP\Core\Cache\APCu;

class ImageBuilder
{
    private $_imageDirs = array();
    private $_imageList = array();

    private $_rebuildCache = false;

    const SETTINGS_DIR = "tmp/cache/FlyCubePHP/image_builder/";
    const CACHE_LIST_FILE = "cache_list.json";

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     */
    public function __construct() {
        $this->loadCacheList();
    }

    /**
     * Выставить флаг пересборки кэша
     * @param bool $value
     */
    public function setRebuildCache(bool $value) {
        $this->_rebuildCache = $value;
    }

    /**
     * Флаг пересборки кэша
     * @return bool
     */
    public function hasRebuildCache(): bool {
        return $this->_rebuildCache;
    }

    /**
     * Список добавленных каталогов images
     * @param bool $fullPath
     * @return array
     */
    public function imageDirs(bool $fullPath = false): array {
        if ($fullPath === true)
            return $this->_imageDirs;

        $tmpLst = array();
        foreach ($this->_imageDirs as $key => $value)
            $tmpLst[$key] = CoreHelper::buildAppPath($value);
        return $tmpLst;
    }

    /**
     * Список загруженных изображений и пути к ним
     * @param bool $fullPath
     * @return array
     */
    public function imageList(bool $fullPath = false): array {
        if ($fullPath === true)
            return $this->_imageList;

        $tmpLst = array();
        foreach ($this->_imageList as $key => $value)
            $tmpLst[$key] = CoreHelper::buildAppPath($value);
        return $tmpLst;
    }

    /**
     * Поиск пути до image файла по имени
     * @param string $name
     * @param bool $fullPath
     * @return string
     * @throws
     *
     * imagePath("configure.svg")       => "app/assets/images/configure.svg"
     * imagePath("configure.svg", true) => "/opt/my_app/app/assets/images/configure.svg", where "/opt/my_app/" - your application directory
     */
    public function imageFilePath(string $name, bool $fullPath = false): string {
        if (empty($name))
            return "";
        if (array_key_exists($name, $this->_imageList)) {
            if ($fullPath === true)
                return $this->_imageList[$name];

            return CoreHelper::buildAppPath($this->_imageList[$name]);
        }
        throw ErrorAssetPipeline::makeError([
            'tag' => 'asset-pipeline',
            'message' => "Not found needed image file: $name",
            'class-name' => __CLASS__,
            'class-method' => __FUNCTION__,
            'asset-name' => $name,
            'backtrace-shift' => 2
        ]);
    }

    /**
     * Добавить и просканировать каталог images
     * @param string $dir
     * @throws ErrorAssetPipeline
     */
    public function loadImages(string $dir) {
        if (empty($dir)
            || !is_dir($dir)
            || in_array($dir, $this->_imageDirs))
            return;
        $this->_imageDirs[] = $dir;
        if (Config::instance()->isProduction() && !$this->_rebuildCache)
            return;
        $tmpImages = CoreHelper::scanDir($dir, [ 'recursive' => true ]);
        foreach ($tmpImages as $img) {
            $img = CoreHelper::buildAppPath($img);
            $tmpName = $img;
            if (!preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.svg|\.png|\.jpg|\.jpeg|\.gif|\.ico)$/", $tmpName))
                continue;
            $pos = strpos($tmpName, "images/");
            if ($pos === false) {
                $tmpName = basename($tmpName);
            } else {
                $tmpName = substr($tmpName, $pos + 7, strlen($tmpName));
                $tmpName = trim($tmpName);
            }
            if ($tmpName[0] == DIRECTORY_SEPARATOR)
                $tmpName = ltrim($tmpName, $tmpName[0]);
            if (!array_key_exists($tmpName, $this->_imageList))
                $this->_imageList[$tmpName] = $img;
        }
        $this->updateCacheList();
    }

    /**
     * Загрузить список кэш файлов
     * @throws
     */
    private function loadCacheList() {
        if (!APCu::isApcuEnabled()) {
            $dirPath = CoreHelper::buildPath(CoreHelper::rootDir(), ImageBuilder::SETTINGS_DIR);
            if (!CoreHelper::makeDir($dirPath, 0777, true))
                throw ErrorAssetPipeline::makeError([
                    'tag' => 'asset-pipeline',
                    'message' => "Make dir for cache settings failed! Path: $dirPath",
                    'class-name' => __CLASS__,
                    'class-method' => __FUNCTION__
                ]);

            $fPath = CoreHelper::buildPath($dirPath, $hash = hash('sha256', ImageBuilder::CACHE_LIST_FILE));
            if (!file_exists($fPath)) {
                $this->updateCacheList();
                return;
            }
            $fData = file_get_contents($fPath);
            $cacheList = json_decode($fData, true);
        } else {
            $cacheList = APCu::cacheData('image-builder-cache', [ 'cache-files' => [] ]);
        }
        $this->_imageList = $cacheList['cache-files'];
    }

    /**
     * Обновить и сохранить список кэш файлов
     * @throws
     */
    private function updateCacheList() {
        // save cache
        $dirPath = CoreHelper::buildPath(CoreHelper::rootDir(), ImageBuilder::SETTINGS_DIR);
        if (!CoreHelper::makeDir($dirPath, 0777, true))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Make dir for cache settings failed! Path: $dirPath",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        $fPath = CoreHelper::buildPath($dirPath, $hash = hash('sha256', ImageBuilder::CACHE_LIST_FILE));
        $fData = json_encode(['cache-files' => $this->_imageList]);
        $tmpFile = tempnam($dirPath, basename($fPath));
        if (false !== @file_put_contents($tmpFile, $fData) && @rename($tmpFile, $fPath)) {
            @chmod($fPath, 0666 & ~umask());
        } else {
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Write file for cache settings failed! Path: $fPath",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);
        }

        // save APCu cache if enabled
        if (APCu::isApcuEnabled()) {
            APCu::setCacheData('image-builder-cache', ['cache-files' => $this->_imageList]);
            APCu::saveEncodedApcuData('image-builder-cache', ['cache-files' => $this->_imageList]);
        }
    }
}