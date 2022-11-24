<?php

namespace FlyCubePHP\Core\AssetPipeline\CSSBuilder;

use FlyCubePHP\Core\Error\ErrorAssetPipeline;

class CSSMinifier
{
    /**
     * Минимизировать данные файла CSS
     * @param string $data - данные файла CSS
     * @return string
     *
     * Данный метод удаляет коментарии, лишние пробелы, пустые селекторы и т.д. и возвращает содержимое сжатого css файла.
     */
    public function minifyData(string $data): string {
        if (empty($data))
            return "";

        $cssData = "";
        $isComment = false;
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $data) as $line) {
            $line = trim($line);
            $this->minifyLine($line, $cssData, $isComment);
        }
        return trim($cssData);
    }

    /**
     * Минимизировать файл CSS
     * @param string $path - путь до файла
     * @return string
     * @throws
     *
     * Данный метод удаляет коментарии, лишние пробелы, пустые селекторы и т.д. и возвращает содержимое сжатого css файла.
     */
    public function minifyFile(string $path): string {
        $file = fopen($path, "r");
        if ($file === false)
            throw ErrorAssetPipeline::makeError([
                'tag' => 'asset-pipeline',
                'message' => "Minify css file failed! Open file failed! File path: $path",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        $cssData = "";
        $isComment = false;
        while (!feof($file)) {
            $line = trim(fgets($file));
            $this->minifyLine($line, $cssData, $isComment);
        }
        fclose($file);
        return trim($cssData);
    }

    // --- private functions ---

    /**
     * Обработать строку файла
     * @param string $line - строка
     * @param string $outData - выходные данные
     * @param bool $isComment - является ли коментарием?
     */
    private function minifyLine(string $line, string &$outData, bool &$isComment) {
        if (empty($line))
            return; // ignore empty line

        // --- check multi line comments ---
        $line = $this->multiLineComment($line, $isComment);

        // --- check one line comment ---
        if (!$isComment)
            $line = $this->oneLineComment($line);

        // --- change white-space(s) ---
        if (!empty($line) && preg_match('/(\s{2,})/', $line, $matches))
            $line = str_replace($matches[0], " ", $line);

        // --- change white-space(s) for ';' ---
        if (!empty($line) && preg_match('/(\s*;\s*)/', $line, $matches))
            $line = str_replace($matches[0], ";", $line);

        // --- change open selector ---
        if (!empty($line) && preg_match('/(\s*\{\s*)/', $line, $matches))
            $line = str_replace($matches[0], "{", $line);

        // --- change close selector ---
        if (!empty($line) && preg_match('/(\s*\}\s*)/', $line, $matches))
            $line = str_replace($matches[0], "}", $line);

        // --- change key-value splitter ---
        if (!empty($line) && preg_match('/(\s*\:\s*)/', $line, $matches))
            $line = str_replace($matches[0], ":", $line);

        // --- change '0(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)' to '0' ---
        if (!empty($line) && preg_match('/\:((0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)[ ]*){1,4}(?=[;\}]|\!important)/', $line, $matches))
            $line = str_replace($matches[0], ":0", $line);

        // --- change ': 0 0 0 0' to ': 0' or ':0 0 0 0' to ':0' ---
        if (!empty($line) && preg_match('/(?<=[\s:])(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)/', $line, $matches))
            $line = str_replace($matches[0], "0", $line);

        // --- change '0.6' to '.6' ---
        if (!empty($line) && preg_match('/(?<=[\s:,\-])0+\.(\d+)/', $line, $matches))
            $line = str_replace($matches[0], ".".$matches[1], $line);

        // --- change HEX color (example: '#ffffff' to '#fff') ---
        if (!empty($line) && preg_match('/(?<=[\s:,\-]\#)([a-f0-6]+)\1([a-f0-6]+)\2([a-f0-6]+)\3/', $line, $matches))
            $line = str_replace($matches[0], $matches[1].$matches[2].$matches[3], $line);

        // --- change '(border|outline):none' to '(border|outline):0' ---
        if (!empty($line) && preg_match('/(?<=[\{;\s])*(border|outline)\s*\:\s*none(?=[;\}\!])/', $line, $matches))
            $line = str_replace($matches[0], $matches[1].":0", $line);

        // --- build css ---
        if (!empty($line)) {
            $outData .= $line;

            // --- remove empty selector ---
            if (preg_match('/(?:[^\{\}]+)\{\}/', $outData, $matches))
                $outData = str_replace($matches[0], "", $outData);
        }
    }

    /**
     * Найти и удалить однострочный коментарий
     * @param string $line
     * @return string
     */
    private function oneLineComment(string $line): string {
        if (empty($line))
            return $line;

        $pos = strpos($line, "//");
        if ($pos === false)
            return $line;
        $quote = false;
        $dblQuote = false;
        $prevChar = null;
        for ($i = 0; $i < $pos; $i++) {
            $char = $line[$i];
            if ($i > 0)
                $prevChar = $line[$i - 1];
            if (strcmp($char, "'") === 0 && !$dblQuote
                && !is_null($prevChar) && strcmp($prevChar, "\\") !== 0)
                $quote = !$quote;
            else if (strcmp($char, "\"") === 0 && !$quote
                && !is_null($prevChar) && strcmp($prevChar, "\\") !== 0)
                $dblQuote = !$dblQuote;
        }
        if ($quote === false && $dblQuote === false)
            $line = substr($line, 0, $pos);

        return trim($line);
    }

    /**
     * Найти и удалить многострочный коментарий
     * @param string $line
     * @param bool $isComment
     * @return string
     */
    private function multiLineComment(string $line, bool &$isComment): string {
        if (empty($line))
            return $line;

        $checkArray = [ "/*", "*/" ];
        if ($isComment)
            array_shift($checkArray);

        $mlStart = -1;
        $mlEnd =  -1;
        foreach ($checkArray as $chk) {
            $pos = strpos($line, $chk);
            if ($pos === false)
                continue;
            $quote = false;
            $dblQuote = false;
            $prevChar = null;
            for ($i = 0; $i < $pos; $i++) {
                $char = $line[$i];
                if ($i > 0)
                    $prevChar = $line[$i - 1];
                if (strcmp($char, "'") === 0 && !$dblQuote
                    && !is_null($prevChar) && strcmp($prevChar, "\\") !== 0)
                    $quote = !$quote;
                else if (strcmp($char, "\"") === 0 && !$quote
                    && !is_null($prevChar) && strcmp($prevChar, "\\") !== 0)
                    $dblQuote = !$dblQuote;
            }
            if ($quote === false && $dblQuote === false) {
                switch ($chk) {
                    case "/*":
                        $mlStart = $pos;
                        break;
                    case "*/":
                        $mlEnd = $pos + 2;
                        break;
                    default:
                        break;
                }
            }
        }
        $tmpLineStart = "";
        $tmpLineEnd = "";
        if ($mlStart !== -1) {
            $isComment = true;
            $tmpLineStart = substr($line, 0, $mlStart);
        }
        if ($mlEnd !== -1) {
            $isComment = false;
            $tmpLineEnd = substr($line, $mlEnd, strlen($line));
        }
        if ($mlStart !== -1 && $mlEnd !== -1)
            $line = $this->multiLineComment($tmpLineStart . $tmpLineEnd, $isComment);
        else if ($mlEnd !== -1)
            $line = $this->multiLineComment($tmpLineEnd, $isComment);
        else if ($mlStart !== -1)
            $line = $tmpLineStart;
        else if ($isComment)
            $line = "";

        return trim($line);
    }
}