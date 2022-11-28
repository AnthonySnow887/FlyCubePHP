<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 22.07.21
 * Time: 17:21
 */

namespace FlyCubePHP\Core\AssetPipeline\JSBuilder;

include_once __DIR__.'/../../../HelperClasses/CoreHelper.php';
include_once __DIR__.'/../../Cache/APCu.php';

include_once 'Compilers/JsPhpCompiler.php';
include_once 'Compilers/BabelJSCompiler.php';

use FlyCubePHP\Core\AssetPipeline\JSBuilder\Compilers\BaseJSCompiler;
use FlyCubePHP\Core\AssetPipeline\JSBuilder\Compilers\JsPhpCompiler;
use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\Error\ErrorAssetPipeline;
use FlyCubePHP\Core\Cache\APCu;

class JSBuilder
{
    private $_isLoaded = false;
    private $_jsDirs = array();
    private $_jsList = array();
    private $_cacheList = array();
    private $_compilers = array();
    private $_defCompilerNames = array();
    private $_cacheDir = "";
    private $_rebuildCache = false;
    private $_prepareRequireList = false;

    const PRE_BUILD_DIR = "pre_build";
    const SETTINGS_DIR = "tmp/cache/FlyCubePHP/js_builder/";
    const CACHE_LIST_FILE = "cache_list.json";

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     */
    public function __construct() {
        $this->loadCacheList();

        // get compiler for JS from config file
        $jsCompilerName = Config::instance()->arg(Config::TAG_JS_COMPILER, "");

        // set default compiler names
        $this->setDefCompilerName('php', JsPhpCompiler::compilerName());
        $this->setDefCompilerName('js', $jsCompilerName);

        // append compilers
        $this->appendCompiler('php', 'FlyCubePHP\Core\AssetPipeline\JSBuilder\Compilers\JsPhpCompiler');
        $this->appendCompiler('js', 'FlyCubePHP\Core\AssetPipeline\JSBuilder\Compilers\BabelJSCompiler');
    }

    /**
     * Задать каталог для кэширования файлов
     * @param string $dir
     */
    public function setCacheDir(string $dir) {
        if (!is_dir($dir))
            return;
        $this->_cacheDir = $dir;
        if ($this->_cacheDir[strlen($this->_cacheDir) - 1] !== DIRECTORY_SEPARATOR)
            $this->_cacheDir .= DIRECTORY_SEPARATOR;
    }

    /**
     * Каталог для кэширования файлов
     * @return string
     */
    public function cacheDir(): string {
        return $this->_cacheDir;
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
     * Выставить флаг предподготовки списка зависимостей скриптов
     * @param bool $value
     *
     * NOTE: This flag allows you to "insert" at the beginning of the list
     *       of dependencies libraries located in lib and vendor.
     */
    public function setPrepareRequireList(bool $value) {
        $this->_prepareRequireList = $value;
    }

    /**
     * Флаг предподготовки списка зависимостей скриптов
     * @return bool
     */
    public function hasPrepareRequireList(): bool {
        return $this->_prepareRequireList;
    }

    /**
     * Список добавленных каталогов javascripts
     * @param bool $fullPath
     * @return array
     */
    public function jsDirs(bool $fullPath = false): array {
        if ($fullPath === true)
            return $this->_jsDirs;

        $tmpLst = array();
        foreach ($this->_jsDirs as $key => $value)
            $tmpLst[$key] = CoreHelper::buildAppPath($value);
        return $tmpLst;
    }

    /**
     * Список загруженных javascript asset-ов и пути к ним
     * @param bool $fullPath
     * @return array
     */
    public function jsList(bool $fullPath = false): array {
        if ($fullPath === true)
            return $this->_jsList;

        $tmpLst = array();
        foreach ($this->_jsList as $key => $value)
            $tmpLst[$key] = CoreHelper::buildAppPath($value);
        return $tmpLst;
    }

    /**
     * Загрузить расширения
     */
    public function loadExtensions() {
        if (!CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_EXTENSION_SUPPORT, false)))
            return;
        if ($this->_isLoaded === true)
            return;
        $this->_isLoaded = true;

        // TODO add functions for extensions!

        // --- include other extensions ---
        $extRoot = strval(\FlyCubePHP\configValue(Config::TAG_EXTENSIONS_FOLDER, "extensions"));
        $migratorsFolder = CoreHelper::buildPath(CoreHelper::rootDir(), $extRoot, "asset_pipeline", "js_builder");
        if (!is_dir($migratorsFolder))
            return;
        $migratorsLst = CoreHelper::scanDir($migratorsFolder);
        foreach ($migratorsLst as $item) {
            $fExt = pathinfo($item, PATHINFO_EXTENSION);
            if (strcmp(strtolower($fExt), "php") !== 0)
                continue;
            try {
                include_once $item;
            } catch (\Exception $e) {
                // nothing...
            }
        }
    }

    /**
     * Загрузить список JS/JS.PHP файлов
     * @param string $dir
     */
    public function loadJS(string $dir) {
        if (empty($dir)
            || !is_dir($dir)
            || in_array($dir, $this->_jsDirs))
            return;
        $this->_jsDirs[] = $dir;
        if (Config::instance()->isProduction() && !$this->_rebuildCache)
            return;
        $tmpJS = CoreHelper::scanDir($dir, [ 'recursive' => true ]);
        foreach ($tmpJS as $js) {
            $js = CoreHelper::buildAppPath($js);
            $tmpName = $js;
            if (!preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.js|\.js\.php)$/", $tmpName))
                continue;
            $pos = strpos($tmpName, "javascripts/");
            if ($pos === false) {
                $tmpName = basename($tmpName);
            } else {
                $tmpName = substr($tmpName, $pos + 12, strlen($tmpName));
                $tmpName = trim($tmpName);
            }
            if (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.js\.php)$/", $tmpName) === 1)
                $tmpName = substr($tmpName, 0, strlen($tmpName) - 7);
            elseif (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.js)$/", $tmpName) === 1)
                $tmpName = substr($tmpName, 0, strlen($tmpName) - 3);

            if ($tmpName[0] == DIRECTORY_SEPARATOR)
                $tmpName = ltrim($tmpName, $tmpName[0]);

            if (!array_key_exists($tmpName, $this->_jsList))
                $this->_jsList[$tmpName] = $js;
        }
        $this->updateCacheList();
    }

    /**
     * Получить путь (список путей) для JS файлов
     * @param string $name
     * @return array|string
     * @throws
     */
    public function javascriptFilePath(string $name)/*: string|array*/ {
        if (empty($name))
            return "";
        if (Config::instance()->isDevelopment()) {
            $fPath = $this->searchFilePath($name);
            if (empty($fPath))
                throw ErrorAssetPipeline::makeError([
                    'tag' => 'asset-pipeline',
                    'message' => "Not found needed js file: $name",
                    'class-name' => __CLASS__,
                    'class-method' => __FUNCTION__,
                    'asset-name' => $name,
                    'backtrace-shift' => 2
                ]);

            $tmpFList = $this->prepareRequireList($this->parseRequireList($fPath));
            if (empty($tmpFList) || count($tmpFList) === 1) {
                $fPath = $this->preBuildFile($fPath);
                return CoreHelper::buildAppPath($fPath);
            }
            $tmpJSLst = array();
            foreach ($tmpFList as $key => $item) {
                $item = $this->preBuildFile($item);
                $tmpJSLst[$key] = CoreHelper::buildAppPath($item);
            }
            return $tmpJSLst;
        } elseif (Config::instance()->isProduction()) {
            $fPath = $this->searchFilePathInCache($name);
            if (empty($fPath))
                return CoreHelper::buildAppPath($this->buildCacheFile($name));
            if (!$this->_rebuildCache)
                return CoreHelper::buildAppPath($fPath);

            return CoreHelper::buildAppPath($this->buildCacheFile($name));
        }
        return "";
    }

    /**
     * Поиск пути до JS файла по имени
     * @param string $name
     * @return string
     */
    private function searchFilePath(string $name): string {
        if (empty($name))
            return "";
        if (array_key_exists($name, $this->_jsList))
            return $this->_jsList[$name];
        return "";
    }

    /**
     * Поиск пути до JS файла по имени в каталоге кэша
     * @param string $name
     * @return string
     */
    private function searchFilePathInCache(string $name): string {
        if (empty($name))
            return "";
        if (array_key_exists($name, $this->_cacheList))
            return $this->_cacheList[$name];
        return "";
    }

    /**
     * Метод разбора списка зависимостей JS файла
     * @param string $path
     * @param array $readFiles
     * @return array
     * @throws
     */
    private function parseRequireList(string $path, array &$readFiles = array()): array {
        if (empty($path))
            return array();
        if (is_dir($path))
            return array();
        if (!file_exists($path))
            return array();
        $neededPath = $this->makeFilePathWithoutExt($path);
        if (in_array($neededPath, $readFiles))
            return array();
        $isMLineComment = false;
        $tmpChild = array();
        if ($file = fopen($path, "r")) {
            $readFiles[] = $neededPath;
            $readFiles[] = $this->makeFilePathWithoutExt($path); // if used pre-build
            $currentLine = 0;
            while (!feof($file)) {
                $currentLine += 1;
                $line = trim(fgets($file));
                if (empty($line))
                    continue; // ignore empty line
                if (substr($line, 0, 2) === "/*")
                    $isMLineComment = true;
                if (substr($line, strlen($line) - 2, 2) === "*/")
                    $isMLineComment = false;
                $isComment = false;
                if (substr($line, 0, 2) === "//")
                    $isComment = true;
                if (!$isComment && !$isMLineComment)
                    continue;
                $pos = strpos($line, "=");
                if ($pos === false)
                    continue;
                $line = substr($line, $pos + 1, strlen($line));
                $line = trim($line);
                $tmpPath = "";
                if (substr($line, 0, 13) == "require_tree ") {
                    $line = substr($line, 13, strlen($line));
                    $line = trim($line);
                    $tmpPath = $this->makeDirPath(dirname($path), $line);
                    $tmpJS = CoreHelper::scanDir($tmpPath, [ 'recursive' => true ]);
                    foreach ($tmpJS as $js) {
                        if (!preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.js|\.js\.php)$/", $js))
                            continue;
                        $tmpChild = array_merge($tmpChild, $this->parseRequireList($js, $readFiles));
                    }
                    continue; // ignore require_tree folder
                } elseif (substr($line, 0, 8) == "require ") {
                    $line = substr($line, 8, strlen($line));
                    $line = trim($line);
                    if (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.js\.php)$/", $line) === 1)
                        $line = substr($line, 0, strlen($line) - 7);
                    else if (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.js)$/", $line) === 1)
                        $line = substr($line, 0, strlen($line) - 3);

                    $tmpPath = $this->makeFilePath(dirname($path), $line);
                } else {
                    continue;
                }
                // --- parse child file ---
                if (!empty($tmpPath))
                    $tmpChild = array_merge($tmpChild, $this->parseRequireList($tmpPath, $readFiles));
                else
                    throw ErrorAssetPipeline::makeError([
                        'tag' => 'asset-pipeline',
                        'message' => "Not found needed js file: $line",
                        'class-name' => __CLASS__,
                        'class-method' => __FUNCTION__,
                        'asset-name' => $path,
                        'file' => $path,
                        'line' => $currentLine,
                        'has-asset-code' => true
                    ]);
            }
            fclose($file);
        }
        $tmpChildKey = CoreHelper::buildAppPath($path);
        $pos = strpos($tmpChildKey, "javascripts/");
        if ($pos === false) {
            $tmpChildKey = basename($tmpChildKey);
        } else {
            $tmpChildKey = substr($tmpChildKey, $pos + 12, strlen($tmpChildKey));
            $tmpChildKey = trim($tmpChildKey);
        }
        $tmpChild[$tmpChildKey] = $path;
        return $tmpChild;
    }

    /**
     * "Сборать" путь до файла
     * @param $basePath - текущий путь
     * @param $str - путь из секции require
     * @return string
     */
    private function makeFilePath($basePath, $str): string {
        if (empty($basePath) || empty($str))
            return "";
        if (substr($str, 0, 2) == "./") {
            $str = substr($str, 2, strlen($str));
            return $this->makeFilePath($basePath, $str);
        } elseif (substr($str, 0, 3) == "../") {
            $str = substr($str, 3, strlen($str));
            $basePath = dirname($basePath . "../");
            return $this->makeFilePath($basePath, $str);
        }

        if ($basePath[strlen($basePath) - 1] !== DIRECTORY_SEPARATOR)
            $basePath .= DIRECTORY_SEPARATOR;

        $tmpPath = $this->searchFilePath($str);
        if (!empty($tmpPath))
            return $tmpPath;
        if (file_exists($basePath . $str . ".js"))
            return $basePath . $str . ".js";
        elseif (file_exists($basePath . $str . ".js.php"))
            return $basePath . $str . ".js.php";

        return "";
    }

    /**
     * "Сборать" путь до каталога
     * @param $basePath - текущий путь
     * @param $str - путь из секции require_tree
     * @return string
     */
    private function makeDirPath($basePath, $str): string {
        if (empty($basePath))
            return "";
        if (substr($str, 0, 3) === "../") {
            $str = substr($str, 3, strlen($str));
            $basePath = dirname($basePath . "../");
            return $this->makeDirPath($basePath, $str);
        } elseif (substr($str, 0, 2) === "./") {
            $str = substr($str, 2, strlen($str));
            return $this->makeDirPath($basePath, $str);
        } elseif (substr($str, 0, 1) === "." && strlen($str) === 1) {
            $str = substr($str, 1, strlen($str));
            return $this->makeDirPath($basePath, $str);
        }

        if ($basePath[strlen($basePath) - 1] !== DIRECTORY_SEPARATOR)
            $basePath .= DIRECTORY_SEPARATOR;
        return $basePath . $str;
    }

    /**
     * Сгенерировать настройки для кэширования файла
     * @param string $name
     * @param int $lastModified
     * @param bool $isMinJS
     * @return array
     */
    private function generateCacheSettings(string $name,
                                           int $lastModified = -1,
                                           bool $isMinJS = true): array {
        if (empty($name))
            return array("f-dir" => "", "f-path" => "");
        if ($lastModified <= 0)
            $lastModified = time();
        $hash = hash('sha256', basename($name) . strval($lastModified));
        $fExt = ".js";
        if ($isMinJS)
            $fExt = ".min.js";
        $fDir = $this->_cacheDir.$hash[0].$hash[1];
        $fPath = $fDir.DIRECTORY_SEPARATOR.basename($name)."_".$hash.$fExt;
        return array("f-dir" => $fDir, "f-path" => $fPath);
    }

    /**
     * Создать кэш файл и вернуть путь до него
     * @param string $name
     * @return string
     * @throws
     */
    private function buildCacheFile(string $name): string {
        $fPath = $this->searchFilePath($name);
        if (empty($fPath))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Not found needed js file: $name",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $name,
                'backtrace-shift' => 3
            ]);

        $tmpFileData = "";
        $lastModified = -1;
        $tmpFList = $this->prepareRequireList($this->parseRequireList($fPath));
        foreach ($tmpFList as $item) {
            $item = $this->preBuildFile($item);

            // --- get last modified and check ---
            $fLastModified = filemtime($item);
            if ($fLastModified === false)
                $fLastModified = time();
            if ($lastModified < $fLastModified)
                $lastModified = $fLastModified;

            // --- append javascript data ---
            $tmpFileData = $this->appendJsContent($tmpFileData, file_get_contents($item));
        }

        // --- build min.js ---
        try {
            $tmpFileData = \JShrink\Minifier::minify(trim($tmpFileData), [ 'flaggedComments' => false ]);
        } catch (\Exception $e) {
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Build min.js file failed! Error: " . $e->getMessage(),
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $name
            ]);
        }

        $cacheSettings = $this->generateCacheSettings($name, $lastModified);
        if (empty($cacheSettings["f-dir"]) || empty($cacheSettings["f-path"]))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Invalid cache settings for js file! Name: $name",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $name,
                'backtrace-shift' => 3
            ]);

        if (!$this->writeCacheFile($cacheSettings["f-dir"], $cacheSettings["f-path"], $tmpFileData))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Write cache js file failed! Name: $name",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $name,
                'backtrace-shift' => 3
            ]);

        // --- update cache settings ---
        $fPathApp = CoreHelper::buildAppPath($cacheSettings["f-path"]);
        $this->_cacheList[$name] = $fPathApp;
        $this->updateCacheList();
        return $fPathApp;
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
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Make dir for cache js file failed! Path: $dirPath",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $filePath
            ]);

        $tmpFile = tempnam($dirPath, basename($filePath));
        if (false !== @file_put_contents($tmpFile, $fileData) && @rename($tmpFile, $filePath)) {
            @chmod($filePath, 0666 & ~umask());
            return true;
        }
        return false;
    }

    /**
     * "Сборка" js файла
     * @param string $path
     * @return string
     * @throws
     */
    private function preBuildFile(string $path): string {
        // skip:
        // - all min.js files
        // - all *.js files from vendor/FlyCubePHP/
        // - all *.js files from vendor/assets/javascripts/
        $FLCPrefix = CoreHelper::buildPath('vendor', 'FlyCubePHP');
        $vendorPrefix = CoreHelper::buildPath('vendor', 'assets', 'javascripts');
        if (preg_match("/^.*\.min\.js$/", $path) === 1
            || (preg_match("/^.*\.js$/", $path) === 1
                && strpos($path, $FLCPrefix) === 0)
            || (preg_match("/^.*\.js$/", $path) === 1
                && strpos($path, $vendorPrefix) === 0))
            return $path;
        // get compiler
        $fExt = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $jsCompiler = $this->findCompiler($fExt);
        if (is_null($jsCompiler))
            return $path;
        elseif ($fExt === "js")
            return $jsCompiler->compileFile($path);
        return $this->preBuildFile($jsCompiler->compileFile($path));
    }

    /**
     * Добавить данные файла с учетом проверки уже имеющихся глобальных функций
     * @param string $data
     * @param string $appendedData
     * @return string
     *
     * NOTE: The global function must be written on one line without line breaks.
     */
    private function appendJsContent(string $data, string $appendedData): string {
        $appendedDataLst = explode("\n", $appendedData);
        foreach ($appendedDataLst as $row) {
            if (preg_match("/^function\s+([A-Za-z0-9_]+\s*\([A-Za-z0-9_,.\s]*\)\s*\{.*\})$/", $row) !== 1)
                $data .= "$row\n";
            elseif (strpos($data, $row) === false)
                $data .= "$row\n";
        }
        return $data;
    }

    /**
     * Добавить компилятор JS файлов и его описание
     * @param string $fileExt Расширение файла
     * @param string $className Имя класса (с namespace; наследник класса BaseJSCompiler)
     */
    private function appendCompiler(string $fileExt, string $className) {
        $fileExt = strtolower(trim($fileExt));
        $compilerName = strtolower(trim($className::compilerName()));
        $className = trim($className);
        if (empty($fileExt)
            || empty($compilerName)
            || empty($className))
            return;
        if (isset($this->_compilers[$fileExt][$compilerName]))
            return;
        $this->_compilers[$fileExt] = [
            $compilerName => [
                'className' => $className,
                'classObject' => null
            ]
        ];
    }

    /**
     * Поиск компилятора JS файлов по расширению
     * @param string $fileExt
     * @return BaseJSCompiler|null
     */
    private function findCompiler(string $fileExt) /*: BaseJSCompiler|null */{
        $fileExt = trim($fileExt);
        $compilerName = $this->defCompilerName($fileExt);
        if (isset($this->_compilers[$fileExt][$compilerName])) {
            $className = $this->_compilers[$fileExt][$compilerName]['className'];
            $classObject = $this->_compilers[$fileExt][$compilerName]['classObject'];
            if (is_null($classObject)) {
                $classObject = new $className($this->_cacheDir.JSBuilder::PRE_BUILD_DIR);
                $this->_compilers[$fileExt][$compilerName]['classObject'] = $classObject;
            }
            return $classObject;
        }
        return null;
    }

    /**
     * Задать имя базового компилятора для файлов в определенным расширением
     * @param string $fileExt
     * @param string $compilerName
     */
    private function setDefCompilerName(string $fileExt, string $compilerName) {
        $fileExt = strtolower(trim($fileExt));
        $compilerName = strtolower(trim($compilerName));
        $this->_defCompilerNames[$fileExt] = $compilerName;
    }

    /**
     * Получить имя базового компилятора для файлов в определенным расширением
     * @param string $fileExt
     * @return string
     */
    private function defCompilerName(string $fileExt): string {
        $fileExt = strtolower(trim($fileExt));
        if (array_key_exists($fileExt, $this->_defCompilerNames))
            return $this->_defCompilerNames[$fileExt];
        return "";
    }

    /**
     * Загрузить список кэш файлов
     * @throws
     */
    private function loadCacheList() {
        if (!APCu::isApcuEnabled()) {
            $dirPath = CoreHelper::buildPath(CoreHelper::rootDir(), JSBuilder::SETTINGS_DIR);
            if (!CoreHelper::makeDir($dirPath, 0777, true))
                throw ErrorAssetPipeline::makeError([
                    'tag' => 'asset-pipeline',
                    'message' => "Make dir for cache settings failed! Path: $dirPath",
                    'class-name' => __CLASS__,
                    'class-method' => __FUNCTION__
                ]);

            $fPath = CoreHelper::buildPath($dirPath, $hash = hash('sha256', JSBuilder::CACHE_LIST_FILE));
            if (!file_exists($fPath)) {
                $this->updateCacheList();
                return;
            }
            $fData = file_get_contents($fPath);
            $cacheList = json_decode($fData, true);
        } else {
            $cacheList = APCu::cacheData('js-builder-cache', [ 'cache-files' => [], 'js-files' => [] ]);
        }
        $this->_cacheList = $cacheList['cache-files'];
        $this->_jsList = $cacheList['js-files'];
    }

    /**
     * Обновить и сохранить список кэш файлов
     * @throws
     */
    private function updateCacheList() {
        if (!APCu::isApcuEnabled()) {
            $dirPath = CoreHelper::buildPath(CoreHelper::rootDir(), JSBuilder::SETTINGS_DIR);
            if (!CoreHelper::makeDir($dirPath, 0777, true))
                throw ErrorAssetPipeline::makeError([
                    'tag' => 'asset-pipeline',
                    'message' => "Make dir for cache settings failed! Path: $dirPath",
                    'class-name' => __CLASS__,
                    'class-method' => __FUNCTION__
                ]);

            $fPath = CoreHelper::buildPath($dirPath, $hash = hash('sha256', JSBuilder::CACHE_LIST_FILE));
            $fData = json_encode(['cache-files' => $this->_cacheList, 'js-files' => $this->_jsList]);
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
            APCu::setCacheData('js-builder-cache', ['cache-files' => $this->_cacheList, 'js-files' => $this->_jsList]);
            APCu::saveEncodedApcuData('js-builder-cache', ['cache-files' => $this->_cacheList, 'js-files' => $this->_jsList]);
        }
    }

    /**
     * Получить путь до файла без расширения
     * @param string $path - путь до файла
     * @return string
     */
    private function makeFilePathWithoutExt(string $path): string {
        if (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.js\.php)$/", $path) === 1)
            $path = substr($path, 0, strlen($path) - 7);
        elseif (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.js)$/", $path) === 1)
            $path = substr($path, 0, strlen($path) - 3);
        return $path;
    }

    /**
     * "Пересборка" списка зависмостей и их очерёдности загрузки.
     * @param array $requireList
     * @return array
     */
    private function prepareRequireList(array $requireList): array {
        if (!$this->_prepareRequireList)
            return $requireList;
        $FLCPrefix = CoreHelper::buildPath('vendor', 'FlyCubePHP');
        $vendorPrefix = CoreHelper::buildPath('vendor', 'assets', 'javascripts');
        $libPrefix = CoreHelper::buildPath('lib', 'assets', 'javascripts');
        $tmpArray = [];
        $pos = 0;
        foreach ($requireList as $key => $value) {
            if (strpos($value, $FLCPrefix) === 0
                || strpos($value, $vendorPrefix) === 0
                || strpos($value, $libPrefix) === 0) {
                if ($pos <= 0) {
                    $tmpArray = [$key => $value] + $tmpArray;
                } else {
                    $a = array_slice($tmpArray, 0, $pos);
                    $b = array_slice($tmpArray, $pos, count($tmpArray) - 1);
                    $tmpArray = $a + [$key => $value] + $b;
                }

                $pos += 1;
            } else {
                $tmpArray[$key] = $value;
            }
        }
        return $tmpArray;
    }
}