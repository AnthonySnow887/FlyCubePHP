<?php

namespace FlyCubePHP\Core\AssetPipeline\JSBuilder\Compilers;

use FlyCubePHP\Core\Error\ErrorAssetPipeline;
use FlyCubePHP\HelperClasses\CoreHelper;

include_once 'BaseJSCompiler.php';

class BabelJSCompiler extends BaseJSCompiler
{
    public function __construct(string $buildDir) {
        parent::__construct($buildDir);
    }

    /**
     * Название компилятора
     * @return string
     */
    protected function compilerName(): string {
        return 'BabelJSCompiler';
    }

    /**
     * Метод компиляции JS.PHP файла
     * @param string $filePath Путь до файла
     * @return string Путь до собранного файла
     * @throws
     */
    protected function compile(string $filePath): string
    {
        $compilerName = $this->compilerName();
        $babelConfig = CoreHelper::buildPath(CoreHelper::rootDir(), 'babel.config.json');
        if (!file_exists($babelConfig))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "[$compilerName] Not found BabelJS config file! Search path: $babelConfig",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $filePath
            ]);

        $buildResult = shell_exec("npx babel --config-file $babelConfig $filePath 2>&1");
        preg_match('/\{\s+Error\:\s+([\d\s\w\n\t\r\'\@\/\-\.\(\)\:\<\>\#]*)\s+\}/', $buildResult, $matches, PREG_OFFSET_CAPTURE);
        if (!empty($matches)) {
            $error = $matches[1][0];
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "[BabelJSCompiler] Pre-Build js file failed! Error: $error",
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
    protected function prepareFileName(string $filePath) {
        return basename($filePath);
    }
}