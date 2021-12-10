<?php

namespace FlyCubePHP\Core\AssetPipeline;

class CSSMinifier
{
    static public function minify(string $path): string {
        $tmpCSS = "";
        if ($file = fopen($path, "r")) {
            $currentLine = 0;
            $isMultiLine = false;
            while (!feof($file)) {
                $currentLine += 1;
                $line = fgets($file);
                $tmpLine = trim($line);
                if (empty($tmpLine))
                    continue; // ignore empty line

                // --- check multi line comments ---
                $mlComment = self::mlComment($tmpLine);
                $tmpLineStart = "";
                $tmpLineEnd = "";
                if ($mlComment['start'] != -1) {
                    $isMultiLine = true;
                    $tmpLineStart = substr($tmpLine, 0, $mlComment['start']);
                }
                if ($mlComment['end'] != -1) {
                    $isMultiLine = false;
                    $tmpLineEnd = substr($tmpLine, $mlComment['end'], strlen($tmpLine));
                }
                if ($mlComment['start'] != -1
                    || $mlComment['end'] != -1
                    || $isMultiLine === true)
                    $tmpLine = $tmpLineStart.$tmpLineEnd;

                if (!$isMultiLine) {
                    // --- check one line comment ---
                    $olComment = self::comment($tmpLine);
                    $tmpLineStart = "";
                    $tmpLineEnd = "";
                    if ($olComment['start'] != -1)
                        $tmpLineStart = substr($tmpLine, 0, $olComment['start']);
                    if ($olComment['end'] != -1)
                        $tmpLineEnd = substr($tmpLine, $olComment['end'], strlen($tmpLine));
                    if ($olComment['start'] != -1 || $olComment['end'] != -1)
                        $tmpLine = $tmpLineStart . $tmpLineEnd;
                }

                // --- change '0(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)' to '0' ---
                if (!empty($tmpLine) && preg_match('/(?<=[\s:])(0)(cm|em|ex|in|mm|pc|pt|px|vh|vw|%)/', $tmpLine, $matches))
                    $tmpLine = str_replace($matches[0], "0", $tmpLine);

                // --- change ': 0 0 0 0' to ': 0' or ':0 0 0 0' to ':0' ---
                if (!empty($tmpLine) && preg_match('/(?<=[\s:])(0\s+0|0\s+0\s+0\s+0)(?=[;\}]|\!important)/', $tmpLine, $matches))
                    $tmpLine = str_replace($matches[0], "0", $tmpLine);

                // change '0.6' to '.6'
                if (!empty($tmpLine) && preg_match('/(?<=[\s:,\-])0+\.(\d+)/', $tmpLine, $matches))
                    $tmpLine = str_replace($matches[0], ".".$matches[1], $tmpLine);

                // change HEX color (example: '#ffffff' to '#fff')
                if (!empty($tmpLine) && preg_match('/(?<=[\s:,\-]\#)([a-f0-6]+)\1([a-f0-6]+)\2([a-f0-6]+)\3/', $tmpLine, $matches))
                    $tmpLine = str_replace($matches[0], $matches[1].$matches[2].$matches[3], $tmpLine);

                // change '(border|outline):none' to '(border|outline):0'
                if (!empty($tmpLine) && preg_match('/(?<=[\{;\s])*(border|outline)\s*\:\s*none(?=[;\}\!])/', $tmpLine, $matches))
                    $tmpLine = str_replace($matches[0], $matches[1].":0", $tmpLine);

                if (!empty($tmpLine)) {
                    $tmpCSS .= $tmpLine;

                    // --- remove empty selector ---
                    if (preg_match('/(?:[^\{\}]+)\{\}/', $tmpCSS, $matches))
                        $tmpCSS = str_replace($matches[0], "", $tmpCSS);
                }
            }
            fclose($file);
        }
        $tmpCSS = str_replace(" {", "{", $tmpCSS);
        $tmpCSS = str_replace("{ ", "{", $tmpCSS);
        $tmpCSS = str_replace("} ", "}", $tmpCSS);
        $tmpCSS = str_replace(" }", "}", $tmpCSS);
        $tmpCSS = str_replace(": ", ":", $tmpCSS);
        return trim($tmpCSS);
    }

    static private function comment(string $line) {
        $mlStart = -1;
        $mlEnd = -1;
        $pos = strpos($line, "//");
        if ($pos !== false) {
            $quote = false;
            $dblQuote = false;
            for ($i = 0; $i < $pos; $i++) {
                if (strcmp($line[$i], "'") === 0)
                    $quote = !$quote;
                if (strcmp($line[$i], "\"") === 0)
                    $dblQuote = !$dblQuote;
            }
            if ($quote === false && $dblQuote === false) {
                $mlStart = $pos;
                $mlEnd = strlen($line);
            }
        }
        return [ 'start' => $mlStart, 'end' => $mlEnd ];
    }

    static private function mlComment(string $line) {
        $mlStart = -1;
        $mlEnd =  -1;
        $pos = strpos($line, "/*");
        if ($pos !== false) {
            $quote = false;
            $dblQuote = false;
            for ($i = 0; $i < $pos; $i++) {
                if (strcmp($line[$i], "'") === 0)
                    $quote = !$quote;
                if (strcmp($line[$i], "\"") === 0)
                    $dblQuote = !$dblQuote;
            }
            if ($quote === false && $dblQuote === false)
                $mlStart = $pos;
        }
        $pos = strpos($line, "*/");
        if ($pos !== false) {
            $quote = false;
            $dblQuote = false;
            for ($i = 0; $i < $pos; $i++) {
                if (strcmp($line[$i], "'") === 0)
                    $quote = !$quote;
                if (strcmp($line[$i], "\"") === 0)
                    $dblQuote = !$dblQuote;
            }
            if ($quote === false && $dblQuote === false)
                $mlEnd = $pos + 2;
        }
        return [ 'start' => $mlStart, 'end' => $mlEnd ];
    }
}