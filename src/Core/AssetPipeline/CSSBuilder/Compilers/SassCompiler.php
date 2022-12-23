<?php

namespace FlyCubePHP\Core\AssetPipeline\CSSBuilder\Compilers;

include_once 'BaseStylesheetCompiler.php';
include_once 'SCSSLogger.php';

use FlyCubePHP\Core\AssetPipeline\AssetPipeline;
use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\Core\Error\ErrorAssetPipeline;
use FlyCubePHP\HelperClasses\CoreHelper;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Exception\SassException;
use ScssPhp\ScssPhp\OutputStyle;


class SassCompiler extends BaseStylesheetCompiler
{
    const version = "1.0.0";

    private $_enableScssLogging = false;

    public function __construct(string $buildDir, array $cssDirs) {
        parent::__construct($buildDir, $cssDirs);

        $defVal = !Config::instance()->isProduction();
        $this->_enableScssLogging = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_SCSS_LOGGING, $defVal));
    }

    /**
     * Название компилятора
     * @return string
     */
    static public function compilerName(): string {
        return 'Sass';
    }

    /**
     * Версия компилятора
     * @return string
     */
    static public function compilerVersion(): string {
        $v = \ScssPhp\ScssPhp\Version::VERSION;
        return self::version . " [ScssPhp ver. $v]";
    }

    /**
     * Расширение файла для компиляции
     * @return string
     */
    static public function fileExtension(): string {
        return "scss";
    }

    /**
     * Метод компиляции JS.PHP файла
     * @param string $filePath Путь до файла
     * @return string Путь до собранного файла
     * @throws
     */
    protected function compile(string $filePath): string
    {
        // --- load file data ---
        $tmpCss = file_get_contents($filePath);

        // --- compile scss ---
        $compiler = new Compiler();
        if ($this->_enableScssLogging === true)
            $compiler->setLogger(new SCSSLogger($filePath));
        $cssDirs = $this->cssDirs();
        foreach ($cssDirs as $dir)
            $compiler->addImportPath(CoreHelper::buildAppPath($dir));

        // --- append helper functions ---
        $this->appendHelperFunctions($compiler);

        try {
            if (Config::instance()->isProduction() === true)
                $compiler->setOutputStyle(OutputStyle::COMPRESSED);

            $tmpCss = $compiler->compileString($tmpCss)->getCss();
            unset($compiler);
        } catch (SassException $e) {
            unset($compiler);
            $errMessage = str_replace("(unknown file)", "(".basename($filePath).")", $e->getMessage());
            $errFile = $filePath;
            $errLine = -1;
            preg_match('/.*line: ([0-9]{1,}).*/', $e->getMessage(), $matches, PREG_OFFSET_CAPTURE);
            if (count($matches) >= 2)
                $errLine = intval(trim($matches[1][0]));

            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Pre-Build scss file failed! Error: $errMessage",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'asset-name' => $filePath,
                'file' => $errFile,
                'line' => $errLine,
                'has-asset-code' => true
            ]);
        }
        return $tmpCss;
    }

    /**
     * Подготовить имя для собранного файла
     * @param string $filePath Путь до файла
     */
    protected function prepareFileName(string $filePath): string {
        $tmpName = parent::prepareFileName($filePath);
        return "$tmpName.css";
    }

    /**
     * @param Compiler $compiler
     * @throws ErrorAssetPipeline
     */
    private function appendHelperFunctions(Compiler &$compiler) {
        if (is_null($compiler))
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "[Sass] Append helper functions failed! Compiler is NULL!",
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
}