<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 09.08.21
 * Time: 15:58
 */

namespace FlyCubePHP\TemplateBuilder;


class TemplateBuilder
{
    static public function buildFromTemplate(string $tmplPath,
                                             string $resultFilePath,
                                             array $params = [],
                                             bool $showOut = false): bool {
        if (!is_file($tmplPath)
            || !is_readable($tmplPath)
            || is_file($resultFilePath))
            return false;
        // --- build template ---
        $tmpData = TemplateBuilder::parseTemplate($tmplPath, $params);
        // --- write file ---
        if (false !== @file_put_contents($resultFilePath, $tmpData)) {
            @chmod($resultFilePath, 0644 & ~umask());
            if ($showOut)
                echo "[Created] $resultFilePath\r\n";
            return true;
        }
        return false;
    }

    static public function parseTemplate(string $path, array $params): string {
        if (!is_file($path) || !is_readable($path))
            return "";
        $tmpData = "";
        if ($file = fopen($path, "r")) {
            while (!feof($file)) {
                $rawLine = fgets($file);
                $line = trim($rawLine);
                if (empty($line)) {
                    $tmpData .= $rawLine;
                    continue;
                }
                preg_match_all("/\{\{\s{0,}([a-zA-Z0-9_]+)\s{0,}\}\}/", $rawLine, $matches);
                if (count($matches) >= 2) {
                    $size = count($matches[0]);
                    for ($i = 0; $i < $size; $i++) {
                        $replaceStr = $matches[0][$i];
                        $replaceKey = $matches[1][$i];
                        if (!array_key_exists($replaceKey, $params))
                            continue;
                        $replaceValue = $params[$replaceKey];
                        $rawLine = str_replace($replaceStr, $replaceValue, $rawLine);
                    }
                }
                $tmpData .= $rawLine;
            }
            fclose($file);
        }
        return $tmpData;
    }
}