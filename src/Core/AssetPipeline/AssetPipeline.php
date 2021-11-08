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
include_once 'JSBuilder.php';
include_once 'CSSBuilder.php';
include_once 'ImageBuilder.php';

use Exception;
use \FlyCubePHP\Core\Config\Config as Config;
use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;
use \FlyCubePHP\Core\Routes\RouteCollector as RouteCollector;
use \FlyCubePHP\Core\Error\ErrorAssetPipeline as ErrorAssetPipeline;

class AssetPipeline
{
    private static $_instance = null;
    private $_jsBuilder = null;
    private $_cssBuilder = null;
    private $_imageBuilder = null;

    private $_viewDirs = array();
    private $_cacheList = array();

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
        $this->_cssBuilder->loadExtensions();

        // --- create image-builder ---
        $this->_imageBuilder = new ImageBuilder();

        // --- append FlyCubePHP assets ---
        $this->appendJSDir(AssetPipeline::CORE_JS_DIR);
        $this->appendCSSDir(AssetPipeline::CORE_CSS_DIR);
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
    public function jsDirs(bool $fullPath = false): array {
        if (is_null($this->_jsBuilder))
            return array();
        return $this->_jsBuilder->jsDirs($fullPath);
    }

    /**
     * Добавить и просканировать каталог javascripts
     * @param string $dir
     */
    public function appendJSDir(string $dir) {
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
                $tmpAssetPaths[] = $this->buildAssetPath($value, CoreHelper::dirName($key));

            return $tmpAssetPaths;
        }
        return $this->buildAssetPath($tmpPaths);
    }

    /**
     * Список добавленных каталогов stylesheets
     * @param bool $fullPath
     * @return array
     */
    public function cssDirs(bool $fullPath = false): array {
        if (is_null($this->_cssBuilder))
            return array();
        return $this->_cssBuilder->cssDirs($fullPath);
    }

    /**
     * Добавить и просканировать каталог stylesheets
     * @param string $dir
     */
    public function appendCSSDir(string $dir) {
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
                $tmpAssetPaths[] = $this->buildAssetPath($value, CoreHelper::dirName($key));

            return $tmpAssetPaths;
        }
        return $this->buildAssetPath($tmpPaths);
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
        $fExt = pathinfo($path, PATHINFO_EXTENSION);
        if (empty(strtolower($fExt)))
            return;
        if (array_key_exists($path, $this->_cacheList)) {
            $realPath = $this->_cacheList[$path];
            $realPath = CoreHelper::buildPath(CoreHelper::rootDir(), $realPath);
            $this->sendAsset($path, $realPath);
        }
        http_response_code(404);
        exit;
    }

    /**
     * Загрузить список кэш файлов
     * @throws
     */
    private function loadCacheList() {
        $dirPath = CoreHelper::buildPath(CoreHelper::rootDir(), AssetPipeline::SETTINGS_DIR);
        if (!CoreHelper::makeDir($dirPath, 0777, true))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Make dir for cache settings failed! Path: $dirPath",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        $fPath = CoreHelper::buildPath($dirPath, $hash = hash('sha256', AssetPipeline::CACHE_LIST_FILE));
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
        $dirPath = CoreHelper::buildPath(CoreHelper::rootDir(), AssetPipeline::SETTINGS_DIR);
        if (!CoreHelper::makeDir($dirPath, 0777, true))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Make dir for cache settings failed! Path: $dirPath",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        $fPath = CoreHelper::buildPath($dirPath, $hash = hash('sha256', AssetPipeline::CACHE_LIST_FILE));
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
    }

    /**
     * "Собрать" путь к asset-у
     * @param string $path - полный путь до файла
     * @param string $childDir - дочерний подкаталог
     * @return string
     * @throws
     */
    private function buildAssetPath(string $path,
                                    string $childDir = ""): string {
        if (empty($path))
            return "";
        $assetPath = "assets/";
        $tmpName = basename($path);
        preg_match('/\_[A-Fa-f0-9]{64}\..*$/', $tmpName, $matches, PREG_OFFSET_CAPTURE);
        if (!empty($matches)) {
            $tmpName = str_replace($matches[0][0], "", $tmpName);
            $tmpName = trim($tmpName);
        }
        $fExt = pathinfo($path, PATHINFO_EXTENSION);
        $lastModified = filemtime($path);
        $tmpName = CoreHelper::fileName($tmpName, true);
        $hash = hash('sha256', $tmpName . strval($lastModified));
        $tmpName .= "-".$hash.".".$fExt;

        if (empty($childDir)) {
            $assetPath .= $tmpName;
        } else {
            $childDir = RouteCollector::spliceUrlFirst($childDir);
            $childDir = RouteCollector::spliceUrlLast($childDir);
            $assetPath .= $childDir . "/" . $tmpName;
        }
        if (array_key_exists($assetPath, $this->_cacheList))
            return RouteCollector::makeValidUrl($assetPath);

        // --- update cache settings ---
        $this->_cacheList[$assetPath] = $path;
        $this->updateCacheList();
        return RouteCollector::makeValidUrl($assetPath);
    }

    /**
     * Отправить файл asset-а клиенту
     * @param string $path - запрашиваемый путь
     * @param string $realPath - реальный путь до файла
     */
    private function sendAsset(string $path, string $realPath) {
        $fExt = pathinfo($realPath, PATHINFO_EXTENSION);
        $fType = $fExt;
        if (strcmp($fType, "js") === 0)
            $fType = "javascript";

        $cType = "text/$fType";
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
            if (ob_get_level())
                ob_end_clean();

            $lastModified = filemtime($realPath);
            $hash = hash('sha256', basename($path) . strval($lastModified));
            header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
            header("Accept-Ranges: bytes");
            header("Content-Length: ".filesize($realPath));
            header("Content-Type: $cType");
            header("Date: ".gmdate('D, d M Y H:i:s', time())." GMT");
            header("ETag: $hash");
            header("Last-Modified: ".gmdate('D, d M Y H:i:s', $lastModified)." GMT");
            readfile($realPath);

//            header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
//            header("Cache-Control: public"); // needed for internet explorer
//            header("Content-Type: application/$fExt");
//            header("Content-Transfer-Encoding: Binary");
//            header("Content-Length:".filesize($path));
//            header("Content-Disposition: attachment; filename=$name");
//            readfile($path);
            exit;
        } else {
            http_response_code(404);
            exit;
        }
    }
}