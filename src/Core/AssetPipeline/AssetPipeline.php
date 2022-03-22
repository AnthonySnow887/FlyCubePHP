<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 27.07.21
 * Time: 14:48
 */

namespace FlyCubePHP\Core\AssetPipeline;

include_once __DIR__.'/../../HelperClasses/CoreHelper.php';
include_once __DIR__.'/../Routes/RouteCollector.php';
include_once __DIR__.'/../Error/ErrorAssetPipeline.php';
include_once __DIR__.'/../Cache/APCu.php';
include_once 'JSBuilder.php';
include_once 'CSSBuilder.php';
include_once 'ImageBuilder.php';

use Exception;
use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\Routes\RouteCollector;
use FlyCubePHP\Core\Error\ErrorAssetPipeline;
use FlyCubePHP\Core\Cache\APCu;

class AssetPipeline
{
    private static $_instance = null;
    private $_jsBuilder = null;
    private $_cssBuilder = null;
    private $_imageBuilder = null;

    private $_viewDirs = array();
    private $_cacheList = array();

    private $_useCompression = false;
    private $_compressionType = "";
    private $_cacheMaxAge = 0;

    const CORE_JS_DIR       = __DIR__."/../../assets/javascripts/";
    const CORE_CSS_DIR      = __DIR__."/../../assets/stylesheets/";
    const CORE_IMAGES_DIR   = __DIR__."/../../assets/images/";
    const SETTINGS_DIR      = "tmp/cache/FlyCubePHP/asset_pipeline/";
    const CACHE_LIST_FILE   = "cache_list.json";

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function instance(): AssetPipeline {
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
        $this->loadCacheList();

        $defVal = !Config::instance()->isProduction();
        $use_rebuildCache = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_REBUILD_CACHE, $defVal));

        $prepareRequireList = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_PREPARE_ASSETS_REQUIRES_LIST, true));

        $defVal = Config::instance()->isProduction();
        $this->_useCompression = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_ASSETS_COMPRESSION, $defVal));
        $this->_compressionType = \FlyCubePHP\configValue(Config::TAG_ASSETS_COMPRESSION_TYPE, "gzip");
        $this->_cacheMaxAge = intval(\FlyCubePHP\configValue(Config::TAG_ASSETS_CACHE_MAX_AGE, 31536000));
        if (strcmp($this->_compressionType, "gzip") !== 0
            && strcmp($this->_compressionType, "deflate") !== 0)
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Invalid assets compression type (value: \"".$this->_compressionType."\")!",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);
        if ($this->_cacheMaxAge <= 0)
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Invalid assets cache control mag age value (value: \"".$this->_cacheMaxAge."\")!",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        // --- create js-builder ---
        $cacheDir = CoreHelper::buildPath(CoreHelper::rootDir(), "tmp", "cache", "FlyCubePHP", "js_builder");
        if (!CoreHelper::makeDir($cacheDir, 0777, true))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Unable to create the cache directory! Dir: $cacheDir.",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        $this->_jsBuilder = new JSBuilder();
        $this->_jsBuilder->setCacheDir($cacheDir);
        $this->_jsBuilder->setRebuildCache($use_rebuildCache);
        $this->_jsBuilder->setPrepareRequireList($prepareRequireList);
        $this->_jsBuilder->loadExtensions();

        // --- create css-builder ---
        $cacheDir = CoreHelper::buildPath(CoreHelper::rootDir(), "tmp", "cache", "FlyCubePHP", "css_builder");
        if (!CoreHelper::makeDir($cacheDir, 0777, true))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Unable to create the cache directory! Dir: $cacheDir.",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        $this->_cssBuilder = new CSSBuilder();
        $this->_cssBuilder->setCacheDir($cacheDir);
        $this->_cssBuilder->setRebuildCache($use_rebuildCache);
        $this->_cssBuilder->setPrepareRequireList($prepareRequireList);
        $this->_cssBuilder->loadExtensions();

        // --- create image-builder ---
        $this->_imageBuilder = new ImageBuilder();
        $this->_imageBuilder->setRebuildCache($use_rebuildCache);

        // --- append FlyCubePHP assets ---
        $this->appendJavascriptDir(AssetPipeline::CORE_JS_DIR);
        $this->appendStylesheetDir(AssetPipeline::CORE_CSS_DIR);
        $this->appendImageDir(AssetPipeline::CORE_IMAGES_DIR);
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
     * Перезапросить и переустановить настройки кэширования
     */
    public function resetCacheSettings() {
        $defVal = !Config::instance()->isProduction();
        $use_rebuildCache = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_REBUILD_CACHE, $defVal));

        if ($this->_jsBuilder)
            $this->_jsBuilder->setRebuildCache($use_rebuildCache);
        if ($this->_cssBuilder)
            $this->_cssBuilder->setRebuildCache($use_rebuildCache);
        if ($this->_imageBuilder)
            $this->_imageBuilder->setRebuildCache($use_rebuildCache);
    }

    /**
     * Список добавленных каталогов views
     * @param bool $fullPath
     * @return array
     */
    public function viewDirs(bool $fullPath = false): array {
        if ($fullPath === true)
            return $this->_viewDirs;

        $tmpLst = array();
        foreach ($this->_viewDirs as $key => $value)
            $tmpLst[$key] = CoreHelper::buildAppPath($value);
        return $tmpLst;
    }

    /**
     * Добавить каталог views
     * @param string $dir
     */
    public function appendViewDir(string $dir) {
        if (empty($dir))
            return;
        if (!is_dir($dir))
            return;
        if (!in_array($dir, $this->_viewDirs))
            $this->_viewDirs[] = $dir;
    }

    /**
     * Список добавленных каталогов javascripts
     * @param bool $fullPath
     * @return array
     */
    public function javascriptDirs(bool $fullPath = false): array {
        if (is_null($this->_jsBuilder))
            return array();
        return $this->_jsBuilder->jsDirs($fullPath);
    }

    /**
     * Список загруженных javascript asset-ов и пути к ним
     * @param bool $fullPath
     * @return array
     */
    public function javascriptList(bool $fullPath = false): array {
        if (is_null($this->_jsBuilder))
            return array();
        return $this->_jsBuilder->jsList($fullPath);
    }

    /**
     * Добавить и просканировать каталог javascripts
     * @param string $dir
     */
    public function appendJavascriptDir(string $dir) {
        if (is_null($this->_jsBuilder))
            return;
        $this->_jsBuilder->loadJS($dir);
    }

    /**
     * Получить путь (список путей) для JS файлов
     * @param string $name
     * @return array|string
     * @throws
     */
    public function javascriptFilePath(string $name)/*: string|array*/ {
        if (is_null($this->_jsBuilder))
            return "";
        $tmpPaths = $this->_jsBuilder->javascriptFilePath($name);
        if (is_array($tmpPaths)) {
            $tmpAssetPaths = array();
            foreach ($tmpPaths as $key => $value)
                $tmpAssetPaths[] = $this->buildAssetPath($value, CoreHelper::dirName($key), $this->_useCompression);

            return $tmpAssetPaths;
        }
        return $this->buildAssetPath($tmpPaths, "", $this->_useCompression);
    }

    /**
     * Список добавленных каталогов stylesheets
     * @param bool $fullPath
     * @return array
     */
    public function stylesheetDirs(bool $fullPath = false): array {
        if (is_null($this->_cssBuilder))
            return array();
        return $this->_cssBuilder->cssDirs($fullPath);
    }

    /**
     * Список загруженных stylesheet asset-ов и пути к ним
     * @param bool $fullPath
     * @return array
     */
    public function stylesheetList(bool $fullPath = false): array {
        if (is_null($this->_cssBuilder))
            return array();
        return $this->_cssBuilder->cssList($fullPath);
    }

    /**
     * Добавить и просканировать каталог stylesheets
     * @param string $dir
     */
    public function appendStylesheetDir(string $dir) {
        if (is_null($this->_cssBuilder))
            return;
        $this->_cssBuilder->loadCSS($dir);
    }

    /**
     * Получить путь (список путей) для CSS файлов
     * @param string $name
     * @return array|string
     * @throws
     */
    public function stylesheetFilePath(string $name)/*: string|array*/ {
        if (is_null($this->_cssBuilder))
            return "";
        $tmpPaths = $this->_cssBuilder->stylesheetFilePath($name);
        if (is_array($tmpPaths)) {
            $tmpAssetPaths = array();
            foreach ($tmpPaths as $key => $value)
                $tmpAssetPaths[] = $this->buildAssetPath($value, CoreHelper::dirName($key), $this->_useCompression);

            return $tmpAssetPaths;
        }
        return $this->buildAssetPath($tmpPaths, "", $this->_useCompression);
    }

    /**
     * Список добавленных каталогов images
     * @param bool $fullPath
     * @return array
     */
    public function imageDirs(bool $fullPath = false): array {
        if (is_null($this->_imageBuilder))
            return array();
        return $this->_imageBuilder->imageDirs($fullPath);
    }

    /**
     * Список загруженных изображений и пути к ним
     * @param bool $fullPath
     * @return array
     */
    public function imageList(bool $fullPath = false): array {
        if (is_null($this->_imageBuilder))
            return array();
        return $this->_imageBuilder->imageList($fullPath);
    }

    /**
     * Поиск пути до image файла по имени
     * @param string $name
     * @return string
     * @throws
     *
     * imagePath("configure.svg") => "app/assets/images/configure.svg"
     */
    public function imageFilePath(string $name): string {
        if (is_null($this->_imageBuilder))
            return "";
        $tmpPath = $this->_imageBuilder->imageFilePath($name);
        return $this->buildAssetPath($tmpPath);
    }

    /**
     * Добавить и просканировать каталог images
     * @param string $dir
     */
    public function appendImageDir(string $dir) {
        if (is_null($this->_imageBuilder))
            return;
        $this->_imageBuilder->loadImages($dir);
    }

    /**
     * Метод обработки запроса на получение asset-а
     *
     * Если asset найден, то он возвращается клиенту
     * и завершает выполнение всех запросов.
     *
     * Если asset найден, но не может быть прочитан, то
     * возвращается код 403 и завершает выполнение всех запросов.
     *
     * Если asset не найден (но запрашивается файл), то
     * возвращается код 404 и завершает выполнение всех запросов.
     *
     * ПРИМЕЧАНИЕ: используется системой! Использование запрещено!
     */
    public function assetProcessing() {
        // --- check caller function ---
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($dbt[1]['function']) ? $dbt[1]['function'] : null;
        if (is_null($caller))
            return;
        if (strcmp($caller, "FlyCubePHP\\requestProcessing") !== 0)
            return;

        // --- processing ---
        $path = RouteCollector::instance()->currentRouteUri();
        $path = RouteCollector::spliceUrlFirst($path);
        if (empty($path))
            return;
        if (strcmp($path[strlen($path) - 1], "/") === 0)
            return;
        if (strpos($path, "assets/") !== 0)
            return;
        $fExt = pathinfo($path, PATHINFO_EXTENSION);
        if (empty(strtolower($fExt)))
            return;
        if (array_key_exists($path, $this->_cacheList)) {
            $realPath = $this->_cacheList[$path]['path'] ?? "";
            $compressedRealPath = $this->_cacheList[$path][$this->_compressionType] ?? "";
            $eTag = $this->_cacheList[$path]['etag'] ?? "";
            if (empty($realPath) || empty($eTag))
                http_response_code(500);
            $this->sendAsset($realPath, $compressedRealPath, $eTag);
        }
        http_response_code(404);
        exit;
    }

    /**
     * Загрузить список кэш файлов
     * @throws
     */
    private function loadCacheList() {
        if (!APCu::isApcuEnabled()) {
            $dirPath = CoreHelper::buildPath(CoreHelper::rootDir(), AssetPipeline::SETTINGS_DIR);
            if (!CoreHelper::makeDir($dirPath, 0777, true))
                throw ErrorAssetPipeline::makeError([
                    'tag' => 'asset-pipeline',
                    'message' => "Make dir for cache settings failed! Path: $dirPath",
                    'class-name' => __CLASS__,
                    'class-method' => __FUNCTION__
                ]);

            $fPath = CoreHelper::buildPath($dirPath, hash('sha256', AssetPipeline::CACHE_LIST_FILE));
            if (!file_exists($fPath)) {
                $this->updateCacheList();
                return;
            }
            $fData = file_get_contents($fPath);
            $this->_cacheList = json_decode($fData, true);
        } else {
            $this->_cacheList = APCu::cacheData('asset-pipeline-cache', []);
        }
    }

    /**
     * Обновить и сохранить список кэш файлов
     * @throws
     */
    private function updateCacheList() {
        if (!APCu::isApcuEnabled()) {
            $dirPath = CoreHelper::buildPath(CoreHelper::rootDir(), AssetPipeline::SETTINGS_DIR);
            if (!CoreHelper::makeDir($dirPath, 0777, true))
                throw ErrorAssetPipeline::makeError([
                    'tag' => 'asset-pipeline',
                    'message' => "Make dir for cache settings failed! Path: $dirPath",
                    'class-name' => __CLASS__,
                    'class-method' => __FUNCTION__
                ]);

            $fPath = CoreHelper::buildPath($dirPath, hash('sha256', AssetPipeline::CACHE_LIST_FILE));
            $fData = json_encode($this->_cacheList);
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
        } else {
            APCu::setCacheData('asset-pipeline-cache', $this->_cacheList);
            APCu::saveEncodedApcuData('asset-pipeline-cache', $this->_cacheList);
        }
    }

    /**
     * "Собрать" путь к asset-у
     * @param string $path - полный путь до файла
     * @param string $childDir - дочерний подкаталог
     * @param bool $useCompression - использовать сжатие
     * @return string
     * @throws
     */
    private function buildAssetPath(string $path,
                                    string $childDir = "",
                                    bool $useCompression = false): string {
        if (empty($path))
            return "";
        $assetPath = "assets/";
        $tmpName = basename($path);
        $tmpCompressDir = "";
        preg_match('/\_[A-Fa-f0-9]{64}\..*$/', $tmpName, $matches, PREG_OFFSET_CAPTURE);
        if (!empty($matches)) {
            $tmpName = str_replace($matches[0][0], "", $tmpName);
            $tmpName = trim($tmpName);
            $tmpCompressDir = $matches[0][0][1].$matches[0][0][2]; // hash first 2 symbols
        }
        $fExt = pathinfo($path, PATHINFO_EXTENSION);
        $lastModified = filemtime($path);
        $tmpName = CoreHelper::fileName($tmpName, true);
        $hash = hash('sha256', $tmpName . $lastModified);
        $tmpName .= "-".$hash.".".$fExt;
        if (empty($tmpCompressDir))
            $tmpCompressDir = $hash[0].$hash[1];

        if (empty($childDir)) {
            $assetPath .= $tmpName;
        } else {
            $childDir = RouteCollector::spliceUrlFirst($childDir);
            $childDir = RouteCollector::spliceUrlLast($childDir);
            $assetPath .= $childDir . "/" . $tmpName;
        }
        if (array_key_exists($assetPath, $this->_cacheList))
            return RouteCollector::makeValidUrl($assetPath);

        // --- use compression ---
        $compressFilePath = "";
        if ($useCompression === true) {
            // --- check ---
            if (!\extension_loaded('zlib'))
                trigger_error("\"ZLib\" extension not installed! Use is not possible!", E_USER_ERROR);

            // --- build ---
            $cacheDir = CoreHelper::splicePathFirst(CoreHelper::splicePathLast(AssetPipeline::SETTINGS_DIR));
            $fDir = CoreHelper::buildPath(CoreHelper::rootDir(), $cacheDir, $tmpCompressDir);
            if (strcmp($this->_compressionType, "gzip") === 0
                && CoreHelper::makeDir($fDir, 0777, true)) {
                // --- build gzip ---
                $compressFilePath = CoreHelper::buildPath($fDir, basename($path.".gz"));
                $compressData = gzencode(file_get_contents($path), 6);
                $tmpFile = tempnam($fDir, basename($compressFilePath));
                if (false !== @file_put_contents($tmpFile, $compressData) && @rename($tmpFile, $compressFilePath))
                    @chmod($compressFilePath, 0666 & ~umask());
            } else if (strcmp($this->_compressionType, "deflate") === 0
                       && CoreHelper::makeDir($fDir, 0777, true)) {
                // --- build deflate ---
                $compressFilePath = CoreHelper::buildPath($fDir, basename($path.".zz"));
                $compressData = gzdeflate(file_get_contents($path), 6);
                $tmpFile = tempnam($fDir, basename($compressFilePath));
                if (false !== @file_put_contents($tmpFile, $compressData) && @rename($tmpFile, $compressFilePath))
                    @chmod($compressFilePath, 0666 & ~umask());
            }
        }

        // --- update cache settings ---
        $this->_cacheList[$assetPath] =  [
            'path' => realpath($path),
            'etag' => hash('sha256', basename($path) . $lastModified),
            $this->_compressionType => $compressFilePath
        ];
        $this->updateCacheList();
        return RouteCollector::makeValidUrl($assetPath);
    }

    /**
     * Отправить файл asset-а клиенту
     * @param string $realPath - реальный путь до файла
     * @param string $compressedRealPath
     * @param string $eTag
     */
    private function sendAsset(string $realPath, string $compressedRealPath, string $eTag) {
        $fExt = pathinfo($realPath, PATHINFO_EXTENSION);
        $fType = $fExt;
        $cType = "text/$fType";
        $supportCompression = false;
        if (strcmp($fType, "js") === 0) {
            $cType = "application/javascript";
            $supportCompression = true;
        }
        if (strcmp($fType, "css") === 0) {
            $cType = "text/css";
            $supportCompression = true;
        }
        if (strcmp($fType, "png") === 0
            || strcmp($fType, "jpg") === 0
            || strcmp($fType, "jpeg") === 0
            || strcmp($fType, "gif") === 0)
            $cType = "image/$fType";
        if (strcmp($fType, "svg") === 0)
            $cType = "image/svg+xml";

        if (file_exists($realPath)) {
            if (!is_readable($realPath)) {
                http_response_code(403);
                exit;
            }
            // сбрасываем буфер вывода PHP, чтобы избежать переполнения памяти выделенной под скрипт
            // если этого не сделать файл будет читаться в память полностью!
            // --- clear all buffers ---
            while (ob_get_level() !== 0)
                ob_end_clean();

            $lastModified = filemtime($realPath);

            // --- check If-None-Match ---
            $ifNoneMatch = CoreHelper::removeQuote(trim(RouteCollector::currentRouteHeader('If-None-Match')));
            $s = strcmp($ifNoneMatch, $eTag);
            if ($s === 0) {
                header($_SERVER["SERVER_PROTOCOL"] . " 304 Not Modified");
                exit;
            }

            // --- check Accept-Encoding ---
            $contentEncodingHeader = "";
            if ($this->_useCompression === true && $supportCompression === true) {
                $acceptEncoding = trim(RouteCollector::currentRouteHeader('Accept-Encoding'));
                if (!empty($acceptEncoding)
                    && strpos($acceptEncoding, $this->_compressionType) !== false
                    && file_exists($compressedRealPath)
                    && is_readable($compressedRealPath)) {
                    $contentEncodingHeader = $this->_compressionType;
                    $realPath = $compressedRealPath;
                }
            }

            // --- send data ---
            header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
            header("Accept-Ranges: bytes");
            header("Cache-Control: public, max-age=" . $this->_cacheMaxAge);
            header("Content-Length: ".filesize($realPath));
            header("Content-Type: $cType");
            header("Date: ".gmdate('D, d M Y H:i:s', time())." GMT");
            header("ETag: \"$eTag\"");
            header("Last-Modified: ".gmdate('D, d M Y H:i:s', $lastModified)." GMT");
            if (!empty($contentEncodingHeader))
                header("Content-Encoding: $contentEncodingHeader");

            readfile($realPath);
            exit;
        } else {
            http_response_code(404);
            exit;
        }
    }
}