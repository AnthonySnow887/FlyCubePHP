<?php

namespace FlyCubePHP\Core\AssetPipeline\CSSBuilder\Compilers;

include_once __DIR__.'/../../../Error/ErrorAssetPipeline.php';
include_once __DIR__.'/../../../../HelperClasses/CoreHelper.php';

use FlyCubePHP\Core\Error\ErrorAssetPipeline;
use FlyCubePHP\HelperClasses\CoreHelper;

abstract class BaseStylesheetCompiler
{
    private $_buildDir = "";
    private $_cssDirs = array();

    public function __construct(string $buildDir, array $cssDirs) {
        $this->_buildDir = $buildDir;
        $this->_cssDirs = $cssDirs;
    }

    /**
     * Путь до каталога для сборки
     * @return string
     */
    final protected function buildDir(): string {
        return $this->_buildDir;
    }

    /**
     * Массив директорий со стилями
     * @return array
     */
    final protected function cssDirs(): array {
        return $this->_cssDirs;
    }

    /**
     * Метод компиляции CSS файла
     * @param string $filePath Путь до файла
     * @return string Путь до собранного файла
     * @throws
     */
    final public function compileFile(string $filePath): string {
        if (empty($filePath))
            return "";
        if (is_dir($filePath))
            return "";
        if (!file_exists($filePath))
            return "";
        // get file last modified
        $fileLastModified = filemtime($filePath);
        if ($fileLastModified === false)
            $fileLastModified = time();
        // get build file last modified
        $fPath = $this->filePathForSave($filePath);
        if (!file_exists($fPath))
            $buildLastModified = -1;
        else
            $buildLastModified = filemtime($fPath);
        if ($buildLastModified === false)
            $buildLastModified = -1;
        // check last modified
        if ($buildLastModified > $fileLastModified)
            return $fPath;
        // compile...
        $compilerName = $this->compilerName();
        $sqlStartMS = microtime(true);
        $tmpCSS = $this->compile($filePath);
        $sqlMS = round(microtime(true) - $sqlStartMS, 3);

        // --- append build date-time ---
        $buildDate = date('d.m.y');
        $buildTime = date('H:i');
        $buildPrefix = <<<EOT
/*
 * File compiled with FlyCubePHP-$compilerName.
 *       Date: $buildDate
 *       Time: $buildTime
 * Build time: $sqlMS sec
 */
EOT;
        $tmpCSS = $buildPrefix . "\n" . $tmpCSS;

        // --- write file ---
        $fDir = $this->buildDir();
        if (!CoreHelper::makeDir($fDir, 0777, true))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "[$compilerName] Make dir for stylesheet file failed! Dir: $fDir",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $filePath
            ]);

        $tmpFile = tempnam($fDir, basename($fPath));
        if (false !== @file_put_contents($tmpFile, $tmpCSS) && @rename($tmpFile, $fPath)) {
            @chmod($fPath, 0666 & ~umask());
            return $fPath;
        }
        return "";
    }

    /**
     * Путь для сохранения собранного файла
     * @param string $filePath
     * @return string
     */
    final protected function filePathForSave(string $filePath): string {
        $fName = $this->prepareFileName($filePath);
        return CoreHelper::buildPath($this->buildDir(), basename($fName));
    }

    /**
     * Подготовить имя для собранного файла
     * @param string $filePath Путь до файла
     *
     * NOTE: override this method for correct implementation.
     */
    protected function prepareFileName(string $filePath): string {
        $fExt = pathinfo($filePath, PATHINFO_EXTENSION);
        $fName = basename($filePath);
        return substr($fName, 0, strlen($fName) - (strlen($fExt) + 1)); // example: .css => strlen: 4
    }

    /**
     * Название компилятора
     * @return string
     */
    abstract static public function compilerName(): string;

    /**
     * Метод компиляции JS файла
     * @param string $filePath Путь до файла
     * @return string Путь до собранного файла
     */
    abstract protected function compile(string $filePath): string;
}