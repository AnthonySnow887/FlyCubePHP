<?php

namespace FlyCubePHP\Core\ActiveRecord\Helpers;

class ActiveRecordPostgreSQLHelper
{
    /**
     * Метод преобразования списка в ARRAY PostgreSQL
     * @param array $list
     * @return string
     */
    static public function psqlArrayToStr(/*array*/ $list) : string
    {
        if (!is_array($list))
            return "{}";
        return "{".implode(',', $list)."}";
    }

    /**
     * Метод преобразования строкового представления ARRAY PostgreSQL в array php
     * @param string $data
     * @return array
     */
    static public function psqlStrToArray(/*string*/ $data) : array
    {
        if (!is_string($data))
            return [];
        $data = str_replace("\n", "", $data);
        preg_match_all("/\{(.*)\}/", $data, $matches);
        if (count($matches) < 2)
            return [];
        $resParts = [ 0 => [] ]; // root part
        $tmpVal = "";
        $openBrackets = 0;
        $inQuotes = false;
        $escaped = false;
        $isVal = false;
        $index = 0;
        $tmpData = $matches[1][0];
        while ($index < strlen($tmpData)) {
            $escaped = false;
            $ch = self::dataSymbol($tmpData, $index, $escaped);
            if (!$escaped && !$inQuotes && $ch == "{") {
                $openBrackets++;
                $resParts[$openBrackets] = [];
            } else if (!$escaped && !$inQuotes && $ch == "}") {
                // append value to local part
                $tmpPart = $resParts[$openBrackets];
                unset($resParts[$openBrackets]);
                if ($isVal) {
                    $tmpPart[] = $tmpVal;
                    $tmpVal = "";
                    $isVal = false;
                }
                $openBrackets--;
                // append value list to parent part
                $tmpParentPart = $resParts[$openBrackets];
                $tmpParentPart[] = $tmpPart;
                $resParts[$openBrackets] = $tmpParentPart;
            } else if (!$escaped && !$inQuotes && $ch =="\"") {
                $inQuotes = true;
            } else if (!$escaped && $inQuotes && $ch =="\"") {
                $inQuotes = false;
                $isVal = true;
            } else if (!$escaped && !$inQuotes && $ch == ",") {
                if ($isVal) {
                    // append value to local part
                    $tmpPart = $resParts[$openBrackets];
                    $tmpPart[] = $tmpVal;
                    $resParts[$openBrackets] = $tmpPart;
                    $tmpVal = "";
                    $isVal = false;
                }
                // else -> skip symbol ','
            } else {
                $tmpVal .= $ch;
                $isVal = true;
            }
        }
        if ($isVal) {
            // append value to local part
            $tmpPart = $resParts[$openBrackets];
            $tmpPart[] = $tmpVal;
            $resParts[$openBrackets] = $tmpPart;
        }
        return $resParts[0];
    }

    /**
     * Получить символ строки по индексу включая экранирование
     * @param string $data
     * @param int $index
     * @param bool $escaped
     * @return string
     */
    static protected function dataSymbol(string $data, int &$index, bool &$escaped): string
    {
        $ch = $data[$index];
        $index++;
        if ($ch == '\\') {
            $escaped = true;
            $ch = $data[$index];
            $index++;
        }
        return $ch;
    }
}