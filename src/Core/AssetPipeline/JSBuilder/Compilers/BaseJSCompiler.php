<?php

namespace FlyCubePHP\Core\AssetPipeline\JSBuilder\Compilers;

include_once __DIR__.'/../../../Error/ErrorAssetPipeline.php';
include_once __DIR__.'/../../../../HelperClasses/CoreHelper.php';

use FlyCubePHP\Core\Error\ErrorAssetPipeline;
use FlyCubePHP\HelperClasses\CoreHelper;

abstract class BaseJSCompiler
{
    private $_buildDir = "";

    public function __construct(string $buildDir) {
        if (!is_dir($buildDir))
            return;
        $this->_buildDir = $buildDir;
    }

    /**
     * Путь до каталога для сборки
     * @return string
     */
    final protected function buildDir(): string {
        return $this->_buildDir;
    }

    /**
     * Метод компиляции JS файла
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
        $compilerName = $this->compilerName();
        $sqlStartMS = microtime(true);
        $tmpJS = $this->compile($filePath);
        $sqlMS = round(microtime(true) - $sqlStartMS, 3);

        // --- append build date-time ---
        $buildDate = date('d.m.y');
        $buildTime = date('H:i');
        $buildPrefix = <<<EOT
/*
File compiled with FlyCubePHP-$compilerName.
      Date: $buildDate
      Time: $buildTime
Build time: $sqlMS sec
*/
EOT;
        $tmpJS = $buildPrefix . "\n" . $tmpJS;

        // --- write file ---
        $fName = $this->prepareFileName($filePath);
        $fDir = $this->buildDir();
        $fPath = $fDir.DIRECTORY_SEPARATOR.basename($fName);
        if (!CoreHelper::makeDir($fDir, 0777, true))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "[$compilerName] Make dir for js file failed! Dir: $fDir",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $filePath
            ]);

        $tmpFile = tempnam($fDir, basename($fName));
        if (false !== @file_put_contents($tmpFile, $tmpJS) && @rename($tmpFile, $fPath)) {
            @chmod($fPath, 0666 & ~umask());
            return $fPath;
        }
        return "";
    }

    /**
     * Подготовить имя для собранного файла
     * @param string $filePath Путь до файла
     *
     * NOTE: override this method for correct implementation.
     */
    protected function prepareFileName(string $filePath) {
        $fExt = pathinfo($filePath, PATHINFO_EXTENSION);
        $fName = basename($filePath);
        return substr($fName, 0, strlen($fName) - (strlen($fExt) + 1)); // example: .php => strlen: 4
    }

    /**
     * Название компилятора
     * @return string
     */
    abstract protected function compilerName(): string;

    /**
     * Метод компиляции JS файла
     * @param string $filePath Путь до файла
     * @return string Путь до собранного файла
     */
    abstract protected function compile(string $filePath): string;
}