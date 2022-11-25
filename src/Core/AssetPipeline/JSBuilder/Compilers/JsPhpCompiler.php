<?php

namespace FlyCubePHP\Core\AssetPipeline\JSBuilder\Compilers;

include_once 'BaseJSCompiler.php';

use FlyCubePHP\Core\Error\ErrorAssetPipeline;

class JsPhpCompiler extends BaseJSCompiler
{
    public function __construct(string $buildDir) {
        parent::__construct($buildDir);
    }

    /**
     * Название компилятора
     * @return string
     */
    static public function compilerName(): string {
        return 'JsPhp';
    }

    /**
     * Метод компиляции JS.PHP файла
     * @param string $filePath Путь до файла
     * @return string Путь до собранного файла
     * @throws
     */
    protected function compile(string $filePath): string
    {
        $isPhpCode = false;
        $tmpPhpCode = "";
        $tmpJS = "";
        if ($file = fopen($filePath, "r")) {
            $currentLine = 0;
            while (!feof($file)) {
                $currentLine += 1;
                $line = fgets($file);
                $tmpLine = trim($line);
                if (empty($tmpLine)) {
                    $tmpJS .= $line; // add in js file
                    continue;
                }
                $pos = strpos($tmpLine, "<?php");
                if ($pos !== false) {
                    $isPhpCode = true;
                    if ($pos > 0) {
                        $posPre = strpos($line, "<?php");
                        $tmpLinePre = substr($line, 0, $posPre);
                        $tmpJS .= $tmpLinePre; // add in js file
                    }
                    $tmpLine = substr($tmpLine, $pos + 5, strlen($tmpLine));
                }
                if ($isPhpCode) {
                    $pos = strpos($tmpLine, "?>");
                    if ($pos !== false) {
                        $isPhpCode = false;
                        $tmpLinePost = "";
                        if ($pos < strlen($tmpLine) - 2) {
                            $pos2 = strpos($line, "?>");
                            $tmpLinePost = substr($line, $pos2 + 2, strlen($line));
                        }
                        $tmpLine = substr($tmpLine, 0, $pos);
                        $tmpPhpCode .= $tmpLine . "\n";

                        // --- evaluate php ---
                        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                            // error was suppressed with the @-operator
                            if (0 === error_reporting())
                                return false;
                            $err = new \FlyCubePHP\Core\Error\Error($errstr);
                            $err->setLine($errline);
                            throw $err;
                        });
                        ob_start();
                        try {
                            eval($tmpPhpCode);
                        } catch(\Exception $e) {
                            $tmpPhpCodeLinesNum = count(preg_split("/((\r?\n)|(\r\n?))/", $tmpPhpCode)) - 1;
                            $tmpDiff = $tmpPhpCodeLinesNum - $e->getLine();
                            if ($tmpDiff < 0)
                                $tmpDiff = 0;
                            $errLine = $currentLine - $tmpDiff;
                            throw ErrorAssetPipeline::makeError([
                                'tag' => 'asset-pipeline',
                                'message' => "[JsPhp] Pre-Build js.php file failed! Error: " . $e->getMessage(),
                                'class-name' => __CLASS__,
                                'class-method' => __FUNCTION__,
                                'asset-name' => $filePath,
                                'file' => $filePath,
                                'line' => $errLine,
                                'has-asset-code' => true
                            ]);
                        } catch (\Error $e) {
                            $tmpPhpCodeLinesNum = count(preg_split("/((\r?\n)|(\r\n?))/", $tmpPhpCode)) - 1;
                            $tmpDiff = $tmpPhpCodeLinesNum - $e->getLine();
                            if ($tmpDiff < 0)
                                $tmpDiff = 0;
                            $errLine = $currentLine - $tmpDiff;
                            throw ErrorAssetPipeline::makeError([
                                'tag' => 'asset-pipeline',
                                'message' => "[JsPhp] Pre-Build js.php file failed! Error: " . $e->getMessage(),
                                'class-name' => __CLASS__,
                                'class-method' => __FUNCTION__,
                                'asset-name' => $filePath,
                                'file' => $filePath,
                                'line' => $errLine,
                                'has-asset-code' => true
                            ]);
                        }
                        restore_error_handler();
                        $tmpResult = trim(ob_get_clean());
                        $tmpPhpCode = ""; // clear
                        if (!empty($tmpResult))
                            $tmpJS .= $tmpResult;
                        if (!empty($tmpLinePost))
                            $tmpJS .= $tmpLinePost;
                        else
                            $tmpJS .= "\r\n";
                        continue;
                    }
                }
                if ($isPhpCode) {
                    $tmpPhpCode .= $tmpLine . "\n";
                    continue;
                }
                $tmpJS .= $line; // add in js file
            }
            fclose($file);
            $tmpJS .= "\r\n";
        }
        return $tmpJS;
    }
}