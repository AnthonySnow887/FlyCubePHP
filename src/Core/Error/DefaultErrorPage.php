<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 23.08.21
 * Time: 18:40
 */

namespace FlyCubePHP\Core\Error;

use FlyCubePHP\HelperClasses\CoreHelper;

include_once 'BaseErrorHandler.php';

class DefaultErrorPage extends BaseErrorHandler
{
    private $_isRendered = false;

    /**
     * Метод обработки ошибки
     * @param int $errCode - код ошибки
     * @param string $errStr - текст с ошибкой
     * @param string $errFile - файл с ошибкой
     * @param int $errLine - номер строки с ошибкой
     * @param string $errFileContent - часть кода файла с ошибкой
     * @param string $backtrace - стек вызовов
     * @return bool
     */
    final public function evalError(int $errCode,
                                    string $errStr,
                                    string $errFile,
                                    int $errLine,
                                    string $errFileContent,
                                    string $backtrace): bool {
        $message = $errStr;
        $message = str_replace("\n", "<br>", $message);
        $file = CoreHelper::buildAppPath($errFile);
        $line = $errLine;
        $type = $this->errorTypeByValue($errCode);

        $errorTitle = "FlyCubePHP Error";
        $errorTitle2 = "in " . basename($errFile);
        $errorBody = <<<EOT
<i style="font-size: 14pt">$message</i>
<br>
<br><i style="font-size: 10pt">File: $file (line: $line)</i>
<br><i style="font-size: 10pt">Type: $type</i>
EOT;
        $errorBody .= "<br><i style=\"font-size: 10pt\">FlyCubePHP " . FLY_CUBE_PHP_VERSION . "</i>";
        $errorBody .= "<br><i style=\"font-size: 10pt\">PHP " . PHP_VERSION . " (" . PHP_OS . ")</i><br>";
        $errorBody .= $this->makeTrace('Code:', htmlentities($errFileContent), $errLine, false);
        $errorBody .= $this->makeTrace('Backtrace:', $backtrace);
        $this->renderError($errorTitle, $errorTitle2, $errorBody);
        die();
    }

    /**
     * Метод обработки исключения
     * @param \Throwable $ex
     * @return mixed
     */
    final public function evalException(\Throwable $ex) {
        $isAddedCode = false;
        $isAddedBacktrace = false;

        $message = $this->splitStr($ex->getMessage(), "/", 100);
        $message = str_replace("\n", "<br>", $message);
        $file = CoreHelper::buildAppPath($ex->getFile());
        $line = $ex->getLine();

        $errorTitle = "FlyCubePHP Exception";
        $errorTitle2 = "in " . basename($ex->getFile());
        $errorBody = "<i style=\"font-size: 14pt\">$message</i>";

        if (is_subclass_of($ex, "\FlyCubePHP\Core\Error\Error")
            && $ex->hasAdditionalMessage() === true) {
            $additionalMessage = $this->splitStr($ex->additionalMessage(), "/", 100);
            $additionalMessage = str_replace("\n", "<br>", $additionalMessage);
            $errorBody .= "<br><br><i style=\"font-size: 12pt\">$additionalMessage</i>";
        }
        $errorBody .= "<br><br><i style=\"font-size: 10pt\">File: $file (line: $line)</i>";
        $errorBody .= "<br><i style=\"font-size: 10pt\">FlyCubePHP " . FLY_CUBE_PHP_VERSION . "</i>";
        $errorBody .= "<br><i style=\"font-size: 10pt\">PHP " . PHP_VERSION . " (" . PHP_OS . ")</i>";

        if (is_subclass_of($ex, "\FlyCubePHP\Core\Error\Error")) {
            $tag = $ex->tag();
            $errorBody .= "<br><br><i style=\"font-size: 10pt\">Exception tag: \"$tag\"</i>";

            switch ($ex->type()) {
                case ErrorType::DEFAULT: {
                    $errorTitle = "FlyCubePHP: Default Exception";
                    break;
                }
                case ErrorType::ASSET_PIPELINE: {
                    $className = $ex->className();
                    $method = $ex->method();
                    $assetName = CoreHelper::buildAppPath($ex->assetName());

                    $errorTitle = "FlyCubePHP: Asset Pipeline Exception";
                    $errorBody .= <<<EOT
<br><i style="font-size: 10pt">Asset Pipeline class: $className</i>
<br><i style="font-size: 10pt">Method: $method</i>
<br><i style="font-size: 10pt">Asset name: $assetName</i>
EOT;
                    break;
                }
                case ErrorType::ACTIVE_RECORD: {
                    $activeRecordClass = $ex->activeRecordClass();
                    $activeRecordMethod = $ex->activeRecordMethod();

                    $errorTitle = "FlyCubePHP: Active Record Exception";
                    $errorTitle2 = "in $activeRecordClass#$activeRecordMethod";
                    $errorBody .= <<<EOT
<br><i style="font-size: 10pt">Active Record class: $activeRecordClass</i>
<br><i style="font-size: 10pt">Method: $activeRecordMethod</i>
EOT;
                    if ($ex->hasErrorDatabase()) {
                        $adapterClass = $ex->errorDatabase()->adapterClass();
                        $adapterName = $ex->errorDatabase()->adapterName();
                        $errorBody .= <<<EOT
<br><i style="font-size: 10pt">Database adapter name: $adapterName</i>
<br><i style="font-size: 10pt">Database adapter class: $adapterClass</i>
EOT;
                        if (!empty($ex->errorDatabase()->sqlQueryWithParams())) {
                            $sqlQuery = $ex->errorDatabase()->sqlQueryWithParams();
                            $errorBody .= "<br>";
                            $errorBody .= $this->makeDiv('SQL Query:', htmlentities($sqlQuery), "8px 8px 4px 8px", true);
                        }
                    }
                    break;
                }
                case ErrorType::CONTROLLER: {
                    $controller = $ex->controller();
                    $action = $ex->action();

                    $errorTitle = "FlyCubePHP: Controller Exception";
                    $errorTitle2 = "in $controller#$action";
                    $errorBody .= <<<EOT
<br><i style="font-size: 10pt">Controller: $controller</i>
<br><i style="font-size: 10pt">Action: $action</i>
EOT;
                    break;
                }
                case ErrorType::DATABASE: {
                    $adapterClass = $ex->adapterClass();
                    $adapterMethod = $ex->adapterMethod();
                    $adapterName = $ex->adapterName();
                    $sqlQuery = $ex->sqlQueryWithParams();

                    $errorTitle = "FlyCubePHP: Database Exception";
                    $errorTitle2 = "in $adapterClass#$adapterMethod";
                    $errorBody .= <<<EOT
<br><i style="font-size: 10pt">Adapter name: $adapterName</i>
<br><i style="font-size: 10pt">Adapter class: $adapterClass</i>
<br><i style="font-size: 10pt">Method: $adapterMethod</i>
EOT;
                    if (!empty($sqlQuery)) {
                        $errorBody .= "<br>";
                        $errorBody .= $this->makeDiv('SQL Query:', htmlentities($sqlQuery), "8px 8px 4px 8px", true);
                    }
                    break;
                }
                case ErrorType::ROUTES: {
                    $uri = $ex->uri();
                    $controller = $ex->controller();
                    $action = $ex->action();
                    $routeType = $ex->routeTypeStr();

                    $errorTitle = "FlyCubePHP: Routes Exception";
                    $errorBody .= <<<EOT
<br><i style="font-size: 10pt">URL: $uri</i>
<br><i style="font-size: 10pt">HTTP Method: $routeType</i>
<br><i style="font-size: 10pt">Controller: $controller</i>
<br><i style="font-size: 10pt">Action: $action</i>
EOT;
                    break;
                }
                case ErrorType::COOKIE: {
                    $cookieClass = $ex->className();
                    $cookieMethod = $ex->method();
                    $cookieData = $ex->cookieKeyWithOptions();

                    $errorTitle = "FlyCubePHP: Cookie Exception";
                    $errorBody .= <<<EOT
<br><i style="font-size: 10pt">Cookie class: $cookieClass</i>
<br><i style="font-size: 10pt">Method: $cookieMethod</i>
EOT;
                    if (!empty($cookieData)) {
                        $errorBody .= "<br>";
                        $errorBody .= $this->makeDiv('Cookie data:', htmlentities($cookieData), "8px 8px 4px 8px", true);
                    }
                    break;
                }
                default:
                    break;
            }
            // --- add additional data ---
            if ($ex->hasAdditionalData() === true) {
                $additionalCode = [];
                foreach ($ex->additionalData() as $key => $value) {
                    if (strcmp($key, "Backtrace") === 0)
                        continue;
                    $pos = strpos($key, " error file");
                    if ($pos !== false) {
                        $tmpName = substr($key, 0, $pos);
                        $additionalCode[] = $tmpName;
                        continue;
                    }
                    if (strpos($key, " error line") !== false)
                        continue;
                    $value = CoreHelper::buildAppPath($value);
                    if (strlen($value) > 100)
                        $value = $this->splitStr($value, "/", 100);
                    $errorBody .= "<br><i style=\"font-size: 10pt\">$key: $value</i>";
                }

                $errorBody .= "<br>";
                if (!empty($additionalCode)) {
                    foreach ($additionalCode as $code) {
                        $addFile = $ex->additionalDataValue("$code error file");
                        $addFileName = CoreHelper::buildAppPath($addFile);
                        $addLine = $ex->additionalDataValue("$code error line");
                        if (!is_null($addFile) && !is_null($addLine))
                            $errorBody .= $this->makeTrace("Code ($code: $addFileName):", htmlentities(ErrorHandlingCore::fileCodeTrace($addFile, $addLine)), $addLine, false);
                    }
                }
                $outName = CoreHelper::buildAppPath($file);
                $errorBody .= $this->makeTrace("Code ($outName):", htmlentities(ErrorHandlingCore::fileCodeTrace($file, $line)), $line, false);
                if ($ex->hasAdditionalDataKey("Backtrace"))
                    $errorBody .= $this->makeTrace('Backtrace:', $ex->additionalDataValue("Backtrace"));
                else
                    $errorBody .= $this->makeTrace('Backtrace:', $ex->getTraceAsString());

                $isAddedCode = true;
                $isAddedBacktrace = true;
            }
        }
        if ($isAddedCode !== true || $isAddedBacktrace !== true)
            $errorBody .= "<br>";
        if ($isAddedCode !== true) {
            $outName = CoreHelper::buildAppPath($file);
            $errorBody .= $this->makeTrace("Code ($outName):", htmlentities(ErrorHandlingCore::fileCodeTrace($file, $line)), $line, false);
        }
        if ($isAddedBacktrace !== true)
            $errorBody .= $this->makeTrace('Backtrace:', $ex->getTraceAsString());

        $this->renderError($errorTitle, $errorTitle2, $errorBody);
        die();
    }

    private function renderError(string $title,
                                 string $title2,
                                 string $errorBody) {
        if ($this->_isRendered)
            return;
        $this->_isRendered = true;

        $html = <<<EOT
<!DOCTYPE html>
<html>
<head>
<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>
<title>$title</title>
<style type='text/css'>   
html, 
body {
    height: 100%; 
    margin: 0;
}   
div.content {
    width: 100%; 
    height: 100%
}  
div.top-info {
    width: 100%;
    height: 70px;
    background-color: #b5180c;
    font-family: Verdana, Arial, Helvetica, sans-serif;
    color: white;
}
div.top-info > span.title {
    font-size: 16pt;
    font-weight: bold;
    position: absolute;
    display: inline-block;
    margin-top: 10px;
    margin-left: 15px;
}
div.top-info > span.title-2 {
    font-size: 12pt;
    font-weight: bold;
    position: absolute;
    display: inline-block;
    margin-top: 20px;
    margin-left: 15px;
}
div.error-data {
    padding: 20px 20px 0px 20px;
}
div.error-data > span.error-title {
    font-size: 14pt;
    font-weight: 600;
    padding: 4px 8px 4px 8px;
}
div.error-data > div.error-information-block {
    background-color: #f0f0f0;
    border: 1px solid #e5e5e5;
    -moz-border-radius: 4px;
    -webkit-border-radius: 4px;
    border-radius: 4px;
    padding: 8px 8px 4px 0px;
    margin-top: 4px;
    margin-bottom: 4px;
    overflow: auto;
}
table.error-information-table {   
    border-collapse: separate;
    width: 100%;
    table-layout: fixed;
}
table.error-information-table td {
  padding: 4px 8px 4px 8px;
  background-clip: padding-box;
  border-bottom: 1px solid #d7d7d7;
}
table.error-information-table tr {
  border-bottom: 1px solid #d7d7d7;
  padding: 4px 8px 4px 8px;
}
table.error-information-table tr:last-child > td {
  border-bottom: 1px solid transparent;
}
table.error-trace td.trace-line-numbers {
  position: sticky;
  left: 0;
  background-color: #f0f0f0;
}
pre {
  font-size: 11px;
  white-space: pre-wrap;
  border: none;
  display: block;
  font-family: monospace;
  overflow: auto;
}
.error-trace-line-numbers {
  color: #AAA;
  padding: 1em .5em;
  border-right: 1px solid #DDD;
  text-align: right;
}
.trace-line {
  padding-left: 10px;
  white-space: pre;
}
.trace-line-error {
  background-color: #ffcccc;
}
</style>
</head>
<body>
<div class='content'> 
    <div class='top-info'>
        <span class="title">$title</span>
        <br><span class="title-2">$title2</span>
    </div>
    <div class='error-data'>
        $errorBody
    </div>
</div>
</body>
</html>
EOT;
        http_response_code(500);
        echo $html;
    }

    private function makeTrace(string $title,
                               string $trace,
                               int $lineError = -1,
                               bool $buildAppPath = true): string {
        $tmpTraceCount = "";
        $tmpTraceData = "";
        $traceLst = [];
        preg_match_all('/.*#([0-9]{1,})\s.*/', $trace, $matches, PREG_OFFSET_CAPTURE);
        if (count($matches) >= 2) {
            $mCount = count($matches[0]);
            for ($i = 0; $i < $mCount; $i++) {
                $str = $matches[0][$i][0];
                $pos = strpos($str, "#");
                if ($pos !== false)
                    $traceLst[] = substr($str, $pos + 1, strlen($str));
            }
        }
        foreach ($traceLst as $item) {
            if (empty($item))
                continue;
            $pos = strpos($item, " ");
            if ($pos === false)
                continue;
            $num = trim(substr($item, 0, $pos + 1));
            $item = substr($item, $pos + 1, strlen($item));
            if ($buildAppPath === true)
                $item = CoreHelper::buildAppPath($item);
            $item = trim($item, "\n\r\0\x0B");
            $tmpTraceCount .= "<div>$num</div>";

            $lineErrorClass = "";
            if ($num == $lineError)
                $lineErrorClass = "trace-line-error";

            $tmpTraceData .= "<div class='trace-line $lineErrorClass'>$item </div>";
        }

        return <<<EOT
<br>
<span class='error-title'>$title</span>
<div class='error-information-block'>
<table class='error-trace' cellspacing="0" cellpadding="0">
<tr>
<td class='trace-line-numbers'><pre class="error-trace-line-numbers">$tmpTraceCount</pre></td>
<td style="width: 100%"><pre style="width: 100%; display: block">$tmpTraceData</pre></td>
</tr>
</table>
</div>
EOT;
    }

    private function makeDiv(string $title,
                             string $data,
                             string $padding = "",
                             bool $usePre = false): string {
        $html = "<br><span class='error-title'>$title</span>";
        if (!empty($padding))
            $html .= "<div class='error-information-block' style='padding: $padding'>";
        else
            $html .= "<div class='error-information-block'>";
        if ($usePre === true)
            $html .= "<pre style='width: 100%'>$data</pre></div>";
        else
            $html .= "$data</div>";
        return $html;
    }

    /**
     * Вставить в строку пробелы после разделителя по максимальной длине
     * @param string $str - строка
     * @param string $splitter - разделитель для поиска
     * @param int $maxlen - максимальная длина
     * @return string
     */
    private function splitStr(string $str, string $splitter, int $maxlen): string {
        $pos = -1;
        while (true) {
            $lastPos = $pos;
            $pos = strpos($str, $splitter, $pos + 1);
            if ($pos === false)
                break;
            if ($pos >= $maxlen) {
                $pos = $lastPos;
                break;
            }
        }
        if ($pos === false || $pos <= 0)
            return $str;
        $strStart = substr($str, 0, $pos);
        $strEnd = substr($str, $pos + strlen($splitter), strlen($str));
        return $strStart . "$splitter " . $strEnd;
    }

    /**
     * Получить название ошибки PHP по ее коду
     * @param $type
     * @return string
     */
    private function errorTypeByValue($type): string {
        $constants  = get_defined_constants(true);
        foreach ($constants['Core'] as $key => $value) { // Each Core constant
            if (preg_match('/^E_/', $key)) {    // Check error constants
                if ($type == $value)
                    return $key;
            }
        }
        return "???";
    }
}