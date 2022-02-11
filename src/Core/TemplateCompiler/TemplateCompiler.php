<?php

namespace FlyCubePHP\Core\TemplateCompiler;

include_once 'TCHelperFunction.php';

use FlyCubePHP\Core\Error\Error;

class TemplateCompiler
{
    private $_helpers = [];

    /**
     * Добавить вспомогательную функцию
     * @param TCHelperFunction $function
     */
    public function appendHelpFunction(TCHelperFunction $function)
    {
        if (empty($function->name()))
            trigger_error("Invalid help function name (Empty)!", E_USER_ERROR);
        if (isset($this->_helpers[$function->name()]))
            trigger_error("Help function already added (name: '".$function->name()."')!", E_USER_ERROR);
        // --- add ---
        $this->_helpers[$function->name()] = $function;
    }

    /**
     * "Собрать" данные, проверяя вхождение вспомогательных функций
     * @param string $data
     * @return string
     * @throws Error
     */
    public function compile(string $data): string
    {
        $tmpData = "";
        $lineNum = 0;
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $data) as $line) {
            $tmpData .= $this->compileLine($line, $lineNum) . "\r\n";
            $lineNum += 1;
        }
        return rtrim($tmpData);
    }

    /**
     * "Собрать" данные одной строки, проверяя вхождение вспомогательных функций
     * @param string $data
     * @param int $lineNum
     * @return string
     * @throws Error
     */
    public function compileLine(string $data, int $lineNum): string
    {
        if (strlen(trim($data)) === 0)
            return $data;
        preg_match_all("/\{([\{\#])\s{0,}([\w]+)\s{0,}\(\s{0,}(.*)\s{0,}\)\s{0,}([\}\#])\}/", $data, $matches);
        if (count($matches) < 5)
            return $data;
        $size = count($matches[0]);
        for ($i = 0; $i < $size; $i++) {
            $replaceStr   = $matches[0][$i];
            $tagOpen      = $matches[1][$i];
            $helpFunc     = $matches[2][$i];
            $helpFuncArgs = $this->parseHelpFunctionArgs($matches[3][$i]);
            $tagClose     = $matches[4][$i];
            // --- check ---
            if (strcmp($tagOpen, '{') === 0 && strcmp($tagClose, '}') !== 0)
                throw Error::makeError([
                    'tag' => 'template-compiler',
                    'message' => 'Invalid closed symbol (not \'}\')!',
                    'class-name' => __CLASS__,
                    'class-method' => __FUNCTION__,
                    'line' => $lineNum,
                    'additional-data' => [ 'error-line-data' => $data ]
                ]);
            else if (strcmp($tagOpen, '#') === 0 && strcmp($tagClose, '#') !== 0)
                throw Error::makeError([
                    'tag' => 'template-compiler',
                    'message' => 'Invalid closed symbol (not \'#\')!',
                    'class-name' => __CLASS__,
                    'class-method' => __FUNCTION__,
                    'line' => $lineNum,
                    'additional-data' => [ 'error-line-data' => $data ]
                ]);
            else if (!$this->hasSupportedHelpFunction($helpFunc))
                throw Error::makeError([
                    'tag' => 'template-compiler',
                    'message' => "Unsupported help function (name: '$helpFunc')!",
                    'class-name' => __CLASS__,
                    'class-method' => __FUNCTION__,
                    'line' => $lineNum,
                    'additional-data' => [ 'error-line-data' => $data ]
                ]);

            // --- skip help function ---
            if (strcmp($tagOpen, '#') === 0) {
                $replaceValue = "";
            } else {
                // --- eval help function ---
                try {
                    $replaceValue = $this->evalHelpFunction($helpFunc, $helpFuncArgs);
                } catch (\Throwable $ex) {
                    throw Error::makeError([
                        'tag' => 'template-compiler',
                        'message' => $ex->getMessage(),
                        'class-name' => __CLASS__,
                        'class-method' => __FUNCTION__,
                        'previous' => $ex,
                        'line' => $lineNum,
                        'additional-data' => [ 'error-line-data' => $data ]
                    ]);
                }
            }
            $data = str_replace($replaceStr, $replaceValue, $data);
        }
        return $data;
    }

    /**
     * Метод разбора аргументов функции
     * @param string $str
     * @param string $delimiter
     * @return array
     */
    private function parseHelpFunctionArgs(string $str, string $delimiter = ","): array
    {
        $str = trim($str);
        if (strlen($str) === 0)
            return [];
        $tmpArgs = [];
        $currentArg = "";
        $quote = false;
        $dblQuote = false;
        $array = false;
        $hash = false;
        $prevChar = null;
        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];
            if ($i > 0)
                $prevChar = $str[$i - 1];

            // --- check quotes ---
            if (strcmp($char, "'") === 0 && !$dblQuote
                && (is_null($prevChar) || strcmp($prevChar, "\\") !== 0)) {
                $quote = !$quote;
            } else if (strcmp($char, "\"") === 0 && !$quote
                && (is_null($prevChar) || strcmp($prevChar, "\\") !== 0)) {
                $dblQuote = !$dblQuote;
            }
            // --- check array ---
            else if (strcmp($char, "[") === 0 && !$array
                && (is_null($prevChar) || strcmp($prevChar, "\\") !== 0)) {
                $array = true;
            } else if (strcmp($char, "]") === 0 && $array
                && (is_null($prevChar) || strcmp($prevChar, "\\") !== 0)) {
                $array = false;
            }
            // --- check hash ---
            else if (strcmp($char, "{") === 0 && !$hash
                && (is_null($prevChar) || strcmp($prevChar, "\\") !== 0)) {
                $hash = true;
            } else if (strcmp($char, "}") === 0 && $hash
                && (is_null($prevChar) || strcmp($prevChar, "\\") !== 0)) {
                $hash = false;
            }

            // --- check delimiter ---
            if (strcmp($char, $delimiter) === 0
                && !$quote
                && !$dblQuote
                && !$array
                && !$hash) {
                $tmpArgs[] = $this->prepareFunctionArg($currentArg);
                $currentArg = "";
                continue;
            }
            $currentArg .= $char;
        }
        $tmpArgs[] = $this->prepareFunctionArg($currentArg);
        return $tmpArgs;
    }

    /**
     * Метод преобразования значения аргумента функции
     * @param string $str
     * @return float|int|mixed|string|null
     */
    private function prepareFunctionArg(string $str)
    {
        $str = trim($str);
        if (strlen($str) === 0)
            return null;
        if (preg_match('/^[\'\"](.*)[\'\"]$/', $str, $matches))
            return strval($matches[1]);
        else if (preg_match('/^([+-]?([0-9]*))$/', $str, $matches))
            return intval($matches[1]);
        else if (preg_match('/^([+-]?([0-9]*[.])?[0-9]+)$/', $str, $matches))
            return floatval($matches[1]);
        else if (preg_match('/^\{(.*)\}$/', $str, $matches))
            return json_decode($str, true);
        else if (preg_match('/^\[(.*)\]$/', $str, $matches))
            return json_decode($str, true);
        return null;
    }

    /**
     * Проверить наличие требуемой вспомогательной функции
     * @param string $funcName Название
     * @return bool
     */
    private function hasSupportedHelpFunction(string $funcName): bool
    {
        return isset($this->_helpers[$funcName]);
    }

    /**
     * Выполнить требуемую вспомогательную функцию
     * @param string $funcName Название
     * @param array $args Аргументы
     * @return string
     * @throws Error
     */
    private function evalHelpFunction(string $funcName, array $args): string
    {
        if (!$this->hasSupportedHelpFunction($funcName))
            throw Error::makeError([
                'tag' => 'template-compiler',
                'message' => "Eval unsupported help function (name: '$funcName')!",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);
        return $this->_helpers[$funcName]->evalFunction($args);
    }
}