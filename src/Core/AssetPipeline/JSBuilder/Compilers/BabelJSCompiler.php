<?php

namespace FlyCubePHP\Core\AssetPipeline\JSBuilder\Compilers;

use FlyCubePHP\Core\Error\ErrorAssetPipeline;
use FlyCubePHP\HelperClasses\CoreHelper;

include_once 'BaseJavaScriptCompiler.php';

class BabelJSCompiler extends BaseJavaScriptCompiler
{
    public function __construct(string $buildDir) {
        parent::__construct($buildDir);
    }

    /**
     * Название компилятора
     * @return string
     */
    static public function compilerName(): string {
        return 'BabelJS';
    }

    /**
     * Метод компиляции JS.PHP файла
     * @param string $filePath Путь до файла
     * @return string Путь до собранного файла
     * @throws
     */
    protected function compile(string $filePath): string
    {
        $babelConfig = CoreHelper::buildPath(CoreHelper::rootDir(), 'babel.config.json');
        if (!file_exists($babelConfig))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "[BabelJS] Not found BabelJS config file! Search path: $babelConfig",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $filePath
            ]);

        $output=null;
        $retVal=null;
        exec("npx babel --config-file $babelConfig $filePath 2>&1", $output, $retVal);
        $buildResult = implode("\n", $output);
        if ($retVal !== 0) {
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "[BabelJS] Pre-Build js file failed! Error: $buildResult",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $filePath,
                'file' => $filePath,
                'line' => -1,
                'has-asset-code' => true
            ]);
        }
        return $buildResult;
    }

    /**
     * Подготовить имя для собранного файла
     * @param string $filePath Путь до файла
     */
    protected function prepareFileName(string $filePath): string {
        $tmpName = parent::prepareFileName($filePath);
        return "$tmpName.compiled.js";
    }
}