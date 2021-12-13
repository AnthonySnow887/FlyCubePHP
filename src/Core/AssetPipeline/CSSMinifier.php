<?php

namespace FlyCubePHP\Core\AssetPipeline;

class CSSMinifier
{
    static public function minify(string $path): string {
        $tmpCSS = "";
        if ($file = fopen($path, "r")) {
            $isComment = false;
            while (!feof($file)) {
                $tmpLine = trim(fgets($file));
                if (empty($tmpLine))
                    continue; // ignore empty line

                // --- check multi line comments ---
                $tmpLine = self::multiLineComment($tmpLine, $isComment);

                // --- check one line comment ---
                if (!$isComment)
                    $tmpLine = self::oneLineComment($tmpLine);

                // --- change white-space(s) ---
                if (!empty($tmpLine) && preg_match('/(\s{2,})/', $tmpLine, $matches))
                    $tmpLine = str_replace($matches[0], " ", $tmpLine);

                // --- change open selector ---
                if (!empty($tmpLine) && preg_match('/(\s*\{\s*)/', $tmpLine, $matches))
                    $tmpLine = str_replace($matches[0], "{", $tmpLine);

                // --- change close selector ---
                if (!empty($tmpLine) && preg_match('/(\s*\}\s*)/', $tmpLine, $matches))
                    $tmpLine = str_replace($matches[0], "}", $tmpLine);

                // --- change key-value splitter ---
                if (!empty($tmpLine) && preg_match('/(\s*\:\s*)/', $tmpLine, $matches))
                    $tmpLine = str_replace($matches[0], ":", $tmpLine);

                // --- change '0(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)' to '0' ---
                if (!empty($tmpLine) && preg_match('/(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)/', $tmpLine, $matches))
                    $tmpLine = str_replace($matches[0], "0", $tmpLine);

                // --- change ': 0 0 0 0' to ': 0' or ':0 0 0 0' to ':0' ---
                if (!empty($tmpLine) && preg_match('/(?<=[\s:])(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)/', $tmpLine, $matches))
                    $tmpLine = str_replace($matches[0], "0", $tmpLine);

                // --- change '0.6' to '.6' ---
                if (!empty($tmpLine) && preg_match('/(?<=[\s:,\-])0+\.(\d+)/', $tmpLine, $matches))
                    $tmpLine = str_replace($matches[0], ".".$matches[1], $tmpLine);

                // --- change HEX color (example: '#ffffff' to '#fff') ---
                if (!empty($tmpLine) && preg_match('/(?<=[\s:,\-]\#)([a-f0-6]+)\1([a-f0-6]+)\2([a-f0-6]+)\3/', $tmpLine, $matches))
                    $tmpLine = str_replace($matches[0], $matches[1].$matches[2].$matches[3], $tmpLine);

                // --- change '(border|outline):none' to '(border|outline):0' ---
                if (!empty($tmpLine) && preg_match('/(?<=[\{;\s])*(border|outline)\s*\:\s*none(?=[;\}\!])/', $tmpLine, $matches))
                    $tmpLine = str_replace($matches[0], $matches[1].":0", $tmpLine);

                // --- build css ---
                if (!empty($tmpLine)) {
                    $tmpCSS .= $tmpLine;

                    // --- remove empty selector ---
                    if (preg_match('/(?:[^\{\}]+)\{\}/', $tmpCSS, $matches))
                        $tmpCSS = str_replace($matches[0], "", $tmpCSS);
                }
            }
            fclose($file);
        }
        return trim($tmpCSS);
    }

    // --- private functions ---

    static private function oneLineComment(string $line): string {
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

    static private function multiLineComment(string $line, bool &$isComment): string {
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
            $line = self::multiLineComment($tmpLineStart . $tmpLineEnd, $isComment);
        else if ($mlEnd !== -1)
            $line = self::multiLineComment($tmpLineEnd, $isComment);
        else if ($mlStart !== -1)
            $line = $tmpLineStart;
        else if ($isComment)
            $line = "";

        return trim($line);
    }
}