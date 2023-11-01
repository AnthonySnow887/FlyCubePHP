<?php

namespace FlyCubePHP\Core\AssetPipeline\JSBuilder\Compilers;

use FlyCubePHP\Core\Error\ErrorAssetPipeline;
use FlyCubePHP\HelperClasses\CoreHelper;

include_once 'BaseJavaScriptCompiler.php';

class BabelJSCompiler extends BaseJavaScriptCompiler
{
    const version = "1.0.0";

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
     * Версия компилятора
     * @return string
     */
    static public function compilerVersion(): string {
        $output = "";
        $stdErr = "";
        $retVal = CoreHelper::execCmd("npx babel -V", $output, $stdErr, true);
        if ($retVal !== 0) {
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "[BabelJS] Get BabelJS version failed! Error: $stdErr",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);
        }
        $output = trim($output);
        return self::version . " [BabelJS ver. $output]";
    }

    /**
     * Расширение файла для компиляции
     * @return string
     */
    static public function fileExtension(): string {
        return 'js';
    }

    /**
     * Метод компиляции JS.PHP файла
     * @param string $filePath Путь до файла
     * @return string Путь до собранного файла
     * @throws
     */
    protected function compile(string $filePath): string
    {
        $babelConfig = CoreHelper::buildPath(CoreHelper::rootDir(), '.babelrc');
        $babelConfig7 = CoreHelper::buildPath(CoreHelper::rootDir(), '.babelrc.json');
        if (!file_exists($babelConfig)
            && !file_exists($babelConfig7))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "[BabelJS] Not found BabelJS config file! Search path-1: $babelConfig; Search path-2: $babelConfig7",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $filePath
            ]);

        $output = "";
        $stdErr = "";
        $retVal = CoreHelper::execCmd("npx babel $filePath", $output, $stdErr, true);
        if ($retVal !== 0) {
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "[BabelJS] Pre-Build js file failed! Error: $stdErr",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $filePath,
                'file' => $filePath,
                'line' => -1,
                'has-asset-code' => true
            ]);
        }

        //
        // check & replace:
        // v1: var _excluded = [...];
        // v2: const _excluded = [...];
        //
        $excludedUid = "";
        $outputChecked = "";
        $outputLst = explode("\n", $output);
        foreach ($outputLst as $row) {
            preg_match_all("/^(var|const)\s+_excluded\s*(=)/", $row, $matches);
            if (count($matches) < 3
                || count($matches[0]) <= 0) {
                preg_match_all("/.*((|,)\s*_excluded\s*(,|))/", $row, $matchesNext);
                if (count($matchesNext) < 4
                    || count($matchesNext[0]) <= 0) {
                    $outputChecked .= "$row\n";
                } else {
                    $tmpRow = str_replace("_excluded", "_excluded_$excludedUid", $row);
                    $outputChecked .= "$tmpRow\n";
                }
            } else {
                $excludedUid = uniqid();
                $tmpRow = str_replace("_excluded", "_excluded_$excludedUid", $row);
                $outputChecked .= "$tmpRow\n";
            }
        }
        return $outputChecked;
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