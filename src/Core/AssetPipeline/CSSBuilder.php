<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 26.07.21
 * Time: 14:48
 */

namespace FlyCubePHP\Core\AssetPipeline;

include_once __DIR__.'/../../HelperClasses/CoreHelper.php';

use \FlyCubePHP\Core\Config\Config as Config;
use FlyCubePHP\Core\Error\ErrorAssetPipeline;
use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;
use ScssPhp\ScssPhp\OutputStyle;

class CSSBuilder
{
    private $_isLoaded = false;
    private $_cssDirs = array();
    private $_cssList = array();
    private $_cacheList = array();
    private $_cacheDir = "";
    private $_rebuildCache = false;

    const PRE_BUILD_DIR = "pre_build";
    const SETTINGS_DIR = "tmp/cache/FlyCubePHP/css_builder/";
    const CACHE_LIST_FILE = "cache_list.json";

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     */
    public function __construct() {
        $this->loadCacheList();
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
     * Список добавленных каталогов stylesheets
     * @param bool $fullPath
     * @return array
     */
    public function cssDirs(bool $fullPath = false): array {
        if ($fullPath === true)
            return $this->_cssDirs;

        $tmpLst = array();
        foreach ($this->_cssDirs as $key => $value)
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
        $migratorsFolder = CoreHelper::buildPath(CoreHelper::rootDir(), $extRoot, "asset_pipeline", "css_builder");
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
     * Загрузить список CSS/SCSS файлов
     * @param string $dir
     */
    public function loadCSS(string $dir) {
        if (empty($dir)
            || !is_dir($dir)
            || in_array($dir, $this->_cssDirs))
            return;
        $this->_cssDirs[] = $dir;
        $tmpCss = CoreHelper::scanDir($dir, true);
        foreach ($tmpCss as $css) {
            $tmpName = CoreHelper::buildAppPath($css);
            if (!preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.css|\.scss)$/", $tmpName))
                continue;
            $pos = strpos($tmpName, "stylesheets/");
            if ($pos === false) {
                $tmpName = basename($tmpName);
            } else {
                $tmpName = substr($tmpName, $pos + 12, strlen($tmpName));
                $tmpName = trim($tmpName);
            }
            if (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.css)$/", $tmpName) === 1)
                $tmpName = substr($tmpName, 0, strlen($tmpName) - 4);
            elseif (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.scss)$/", $tmpName) === 1)
                $tmpName = substr($tmpName, 0, strlen($tmpName) - 5);

            if ($tmpName[0] == DIRECTORY_SEPARATOR)
                $tmpName = ltrim($tmpName, $tmpName[0]);

            if (!array_key_exists($tmpName, $this->_cssList))
                $this->_cssList[$tmpName] = $css;
        }
    }

    /**
     * Получить путь (список путей) для CSS файлов
     * @param string $name
     * @return array|string
     * @throws
     */
    public function stylesheetFilePath(string $name)/*: string|array*/ {
        if (empty($name))
            return "";
        if (Config::instance()->isDevelopment()) {
            $fPath = $this->searchFilePath($name);
            if (empty($fPath))
                throw ErrorAssetPipeline::makeError([
                    'tag' => 'asset-pipeline',
                    'message' => "Not found needed css/scss file: $name",
                    'class-name' => __CLASS__,
                    'class-method' => __FUNCTION__,
                    'asset-name' => $name,
                    'backtrace-shift' => 2
                ]);

            $tmpFList = $this->parseRequireList($fPath);
            if (empty($tmpFList) || count($tmpFList) === 1) {
                $fExt = pathinfo($fPath, PATHINFO_EXTENSION);
                if (strtolower($fExt) === "scss")
                    $fPath = $this->preBuildFile($fPath);
                return CoreHelper::buildAppPath($fPath);
            }
            $tmpCSSLst = array();
            foreach ($tmpFList as $key => $item) {
                $fExt = pathinfo($item, PATHINFO_EXTENSION);
                if (strtolower($fExt) === "scss")
                    $item = $this->preBuildFile($item);
                $tmpCSSLst[$key] = CoreHelper::buildAppPath($item);
            }
            return $tmpCSSLst;
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
     * Поиск пути до CSS/SCSS файла по имени
     * @param string $name
     * @return string
     */
    private function searchFilePath(string $name): string {
        if (empty($name))
            return "";
        if (array_key_exists($name, $this->_cssList))
            return $this->_cssList[$name];
        return "";
    }

    /**
     * Поиск пути до CSS/SCSS файла по имени в каталоге кэша
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
     * Метод разбора списка зависимостей CSS/SCSS файла
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
        if (in_array($path, $readFiles))
            return array();
        $isMLineComment = false;
        $tmpChild = array();
        if ($file = fopen($path, "r")) {
            $readFiles[] = $path;
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
                    $tmpCSS = CoreHelper::scanDir($tmpPath, true);
                    foreach ($tmpCSS as $css) {
                        if (!preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.css|\.scss)$/", $css))
                            continue;
                        $tmpChild = array_unique(array_merge($tmpChild, $this->parseRequireList($css, $readFiles)));
                    }
                    continue; // ignore require_tree folder
                } elseif (substr($line, 0, 8) == "require ") {
                    $line = substr($line, 8, strlen($line));
                    $line = trim($line);
                    if (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.css)$/", $line) === 1)
                        $line = substr($line, 0, strlen($line) - 4);
                    else if (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.scss)$/", $line) === 1)
                        $line = substr($line, 0, strlen($line) - 5);

                    $tmpPath = $this->makeFilePath(dirname($path), $line);
                } else {
                    continue;
                }
                // --- parse child file ---
                if (!empty($tmpPath))
                    $tmpChild = array_unique(array_merge($tmpChild, $this->parseRequireList($tmpPath, $readFiles)));
                else
                    throw ErrorAssetPipeline::makeError([
                        'tag' => 'asset-pipeline',
                        'message' => "Not found needed css/scss file: $line",
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
        $pos = strpos($tmpChildKey, "stylesheets/");
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
     * Метод разбора списка зависимостей CSS/SCSS файла и формирование единого файла
     * @param string $path
     * @param int $lastModified
     * @param array $readFiles
     * @return string
     * @throws
     */
    private function parseAndMakeRequireList(string $path,
                                             int &$lastModified = -1,
                                             array &$readFiles = array()): string {
        if (empty($path))
            return "";
        if (is_dir($path))
            return "";
        if (!file_exists($path))
            return "";
        $neededPath = $this->makeFilePathWithoutExt($path);
        if (in_array($neededPath, $readFiles))
            return "";

        // --- get last modified and check ---
        $fLastModified = filemtime($path);
        if ($fLastModified === false)
            $fLastModified = time();
        if ($lastModified < $fLastModified)
            $lastModified = $fLastModified;

        // --- check file extension ---
        $scssReqData = "";
        $fExt = pathinfo($path, PATHINFO_EXTENSION);
        if (strtolower($fExt) === "scss") {
            $tmpReqList = $this->parseRequireList($path);
            foreach ($tmpReqList as $key => $item) {
                if (strcmp(basename($key), basename($path)) === 0)
                    continue; // skip current file
                $scssReqData .= $this->parseAndMakeRequireList($item, $lastModified, $readFiles);
            }
            $path = $this->preBuildFile($path);
        }

        $isMLineComment = false;
        $tmpCSS = "";
        if ($file = fopen($path, "r")) {
            $readFiles[] = $neededPath;
            $readFiles[] = $this->makeFilePathWithoutExt($path); // if used pre-build
            $currentLine = 0;
            while (!feof($file)) {
                $currentLine += 1;
                $line = fgets($file);
                $tmpLine = trim($line);
                if (empty($tmpLine))
                    continue; // ignore empty line
                $isMLineCommentEnd = false;
                if (substr($tmpLine, 0, 2) === "/*")
                    $isMLineComment = true;
                if (substr($tmpLine, strlen($tmpLine) - 2, 2) === "*/") {
                    $isMLineComment = false;
                    $isMLineCommentEnd = true;
                }
                $isComment = false;
                if (substr($tmpLine, 0, 2) === "//")
                    $isComment = true;
                if (!$isComment && !$isMLineComment && !$isMLineCommentEnd) {
                    $tmpCSS .= $line; // add in css file
                    continue;
                }
                $pos = strpos($tmpLine, "=");
                if ($pos === false)
                    continue; // ignore comments
                $tmpLine = substr($tmpLine, $pos + 1, strlen($tmpLine));
                $tmpLine = trim($tmpLine);
                $tmpPath = "";
                if (substr($tmpLine, 0, 13) == "require_tree ") {
                    $tmpLine = substr($tmpLine, 13, strlen($tmpLine));
                    $tmpLine = trim($tmpLine);
                    $tmpPath = $this->makeDirPath(dirname($path), $tmpLine);
                    $tmpCssLst = CoreHelper::scanDir($tmpPath, true);
                    foreach ($tmpCssLst as $css) {
                        if (!preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.css|\.scss)$/", $css))
                            continue;
                        $tmpCSS .= $this->parseAndMakeRequireList($css, $lastModified, $readFiles);
                    }
                    continue; // ignore require_tree folder
                } elseif (substr($tmpLine, 0, 8) == "require ") {
                    $tmpLine = substr($tmpLine, 8, strlen($tmpLine));
                    $tmpLine = trim($tmpLine);
                    if (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.css)$/", $tmpLine) === 1)
                        $tmpLine = substr($tmpLine, 0, strlen($tmpLine) - 4);
                    else if (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.scss)$/", $tmpLine) === 1)
                        $tmpLine = substr($tmpLine, 0, strlen($tmpLine) - 5);

                    $tmpPath = $this->makeFilePath(dirname($path), $tmpLine);
                } else {
                    continue; // ignore comments
                }
                // --- parse child file ---
                if (!empty($tmpPath))
                    $tmpCSS .= $this->parseAndMakeRequireList($tmpPath, $lastModified, $readFiles);
                else
                    throw ErrorAssetPipeline::makeError([
                        'tag' => 'asset-pipeline',
                        'message' => "Not found needed css/scss file: $tmpLine",
                        'class-name' => __CLASS__,
                        'class-method' => __FUNCTION__,
                        'asset-name' => $path,
                        'file' => $path,
                        'line' => $currentLine,
                        'has-asset-code' => true
                    ]);
            }
            fclose($file);
            $tmpCSS .= "\r\n";
        }
        if (!empty($scssReqData))
            $tmpCSS = $scssReqData . $tmpCSS;
        return trim($tmpCSS);
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
        if (file_exists($basePath . $str . ".css"))
            return $basePath . $str . ".css";
        elseif (file_exists($basePath . $str . ".scss"))
            return $basePath . $str . ".scss";

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
     * @return array
     */
    private function generateCacheSettings(string $name, int $lastModified = -1): array {
        if (empty($name))
            return array("f-dir" => "", "f-path" => "");
        if ($lastModified <= 0)
            $lastModified = time();
        $hash = hash('sha256', basename($name) . strval($lastModified));
        $fExt = ".css";
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
                'message' => "Not found needed css/scss file!",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $name,
                'backtrace-shift' => 3
            ]);

        $fLastModified = -1;
        $tmpFileData = $this->parseAndMakeRequireList($fPath, $fLastModified);
        $cacheSettings = $this->generateCacheSettings($name, $fLastModified);
        if (empty($cacheSettings["f-dir"]) || empty($cacheSettings["f-path"]))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Invalid cache settings for css/scss file!",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $name,
                'backtrace-shift' => 3
            ]);

        if (!$this->writeCacheFile($cacheSettings["f-dir"], $cacheSettings["f-path"], $tmpFileData))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Write cache css/scss file failed!",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $name,
                'backtrace-shift' => 3
            ]);

        // --- update cache settings ---
        $this->_cacheList[$name] = $cacheSettings["f-path"];
        $this->updateCacheList();
        return $cacheSettings["f-path"];
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
                'message' => "Make dir for cache css/scss file failed! Path: $dirPath",
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
     * "Сборка" *.scss файла в *.css
     * @param string $path
     * @return string
     * @throws
     */
    private function preBuildFile(string $path): string {
        if (empty($path))
            return "";
        if (is_dir($path))
            return "";
        if (!file_exists($path))
            return "";
        $tmpCss = "";
        if ($file = fopen($path, "r")) {
            $readFiles[] = $path;
            while (!feof($file)) {
                $line = fgets($file);
                $tmpCss .= $line;
            }
            fclose($file);
        }

        // --- compile scss ---
        $compiler = new \ScssPhp\ScssPhp\Compiler();
        foreach ($this->_cssDirs as $dir)
            $compiler->setImportPaths(CoreHelper::buildAppPath($dir));

        // --- append helper functions ---
        $this->appendHelperFunctions($compiler);

        try {
            if (Config::instance()->isProduction() === true)
                $compiler->setOutputStyle(OutputStyle::COMPRESSED);

            $tmpCss = $compiler->compileString($tmpCss)->getCss();
        } catch (\ScssPhp\ScssPhp\Exception\SassException $e) {
            unset($compiler);
            $errFile = $path;
            $errLine = -1;
            preg_match('/.*line: ([0-9]{1,}).*/', $e->getMessage(), $matches, PREG_OFFSET_CAPTURE);
            if (count($matches) >= 2)
                $errLine = intval(trim($matches[1][0]));

            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Pre-Build scss file failed! Error: ".  $e->getMessage(),
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $path,
                'file' => $errFile,
                'line' => $errLine,
                'has-asset-code' => true
            ]);
        }
        unset($compiler);

        // --- write file ---
        $fName = basename($path);
        $fName = substr($fName, 0, strlen($fName) - 5) . ".css"; // delete .scss and add .css
        $fDir = $this->_cacheDir.CSSBuilder::PRE_BUILD_DIR;
        $fPath = $fDir.DIRECTORY_SEPARATOR.basename($fName);
        if (!CoreHelper::makeDir($fDir, 0777, true))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Make dir for css file failed! Dir: $fDir",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $path
            ]);

        $tmpFile = tempnam($fDir, basename($fName));
        if (false !== @file_put_contents($tmpFile, $tmpCss) && @rename($tmpFile, $fPath)) {
            @chmod($fPath, 0666 & ~umask());
            return $fPath;
        }
        return "";
    }

    /**
     * @param \ScssPhp\ScssPhp\Compiler $compiler
     * @throws \FlyCubePHP\Core\Error\ErrorAssetPipeline
     */
    private function appendHelperFunctions(\ScssPhp\ScssPhp\Compiler &$compiler) {
        if (is_null($compiler))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Append helper functions failed! Compiler is NULL!",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        // --- asset_path ---
        $compiler->registerFunction(
            'asset_path',
            function($args) use ($compiler) {
                $pathArray = $compiler->assertString($args[0], 'path');
                if (count($pathArray) !== 3
                    || !is_array($pathArray[2])
                    || empty($pathArray[2]))
                    throw $compiler->error('%s Invalid arguments!', '[asset_path]');

                $path = $pathArray[2][0];
                try {
                    $fPath = AssetPipeline::instance()->imageFilePath($path);
                } catch (ErrorAssetPipeline $ex) {
                    throw $compiler->error('%s Not found needed asset file: %s!', '[asset_path]', $path);
                }
                if (empty($fPath))
                    throw $compiler->error('%s Not found needed asset file: %s!', '[asset_path]', $path);

                // NOTE: use for convert from php value to sass value
                // return \ScssPhp\ScssPhp\ValueConverter::fromPhp($fPath);
                return [\ScssPhp\ScssPhp\Type::T_STRING, '"', [$fPath]];
            },
            ['path']
        );

        // --- asset_url ---
        $compiler->registerFunction(
            'asset_url',
            function($args) use ($compiler) {
                $pathArray = $compiler->assertString($args[0], 'path');
                if (count($pathArray) !== 3
                    || !is_array($pathArray[2])
                    || empty($pathArray[2]))
                    throw $compiler->error('%s Invalid arguments!', '[asset_url]');

                $path = $pathArray[2][0];
                try {
                    $fPath = AssetPipeline::instance()->imageFilePath($path);
                } catch (ErrorAssetPipeline $ex) {
                    throw $compiler->error('%s Not found needed asset file: %s!', '[asset_url]', $path);
                }
                if (empty($fPath))
                    throw $compiler->error('%s Not found needed asset file: %s!', '[asset_url]', $path);

                // NOTE: use for convert from php value to sass value
                // return \ScssPhp\ScssPhp\ValueConverter::fromPhp("url($fPath)");
                return [\ScssPhp\ScssPhp\Type::T_STRING, '', ["url($fPath)"]];
            },
            ['path']
        );
    }

    /**
     * Загрузить список кэш файлов
     * @throws
     */
    private function loadCacheList() {
        $dirPath = CoreHelper::buildPath(CoreHelper::rootDir(), CSSBuilder::SETTINGS_DIR);
        if (!CoreHelper::makeDir($dirPath, 0777, true))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Make dir for cache settings failed! Path: $dirPath",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        $fPath = CoreHelper::buildPath($dirPath, $hash = hash('sha256', CSSBuilder::CACHE_LIST_FILE));
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
        $dirPath = CoreHelper::buildPath(CoreHelper::rootDir(), CSSBuilder::SETTINGS_DIR);
        if (!CoreHelper::makeDir($dirPath, 0777, true))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Make dir for cache settings failed! Path: $dirPath",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        $fPath = CoreHelper::buildPath($dirPath, $hash = hash('sha256', CSSBuilder::CACHE_LIST_FILE));
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
     * Получить путь до файла без расширения
     * @param string $path - путь до файла
     * @return string
     */
    private function makeFilePathWithoutExt(string $path): string {
        if (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.css)$/", $path) === 1)
            $path = substr($path, 0, strlen($path) - 4);
        elseif (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.scss)$/", $path) === 1)
            $path = substr($path, 0, strlen($path) - 5);
        return $path;
    }
}