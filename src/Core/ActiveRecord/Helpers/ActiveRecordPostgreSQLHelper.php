<?php

namespace FlyCubePHP\Core\ActiveRecord\Helpers;

class ActiveRecordPostgreSQLHelper
{
    /**
     * Метод преобразования списка в ARRAY PostgreSQL
     * @param array $list
     * @return string
     */
    static public function psqlArrayToStr(array $list) : string
    {
        return "{".implode(',', $list)."}";
    }

    /**
     * Метод преобразования строкового представления ARRAY PostgreSQL в array php
     * @param string $data
     * @return array
     */
    static public function psqlStrToArray(string $data) : array
    {
        $data = str_replace("\n", "", $data);
        preg_match_all("/\{(.*)\}/", $data, $matches);
        if (count($matches) < 2)
            return [];
        $resParts = [ 0 => [] ]; // root part
        $tmpVal = "";
        $openBrackets = 0;
        $isStr = false;
        $isVal = false;
        $tmpData = $matches[1][0];
        for ($i = 0; $i < strlen($tmpData); $i++) {
            $ch = $tmpData[$i];
            $chPrev = "\0";
            if ($i != 0)
                $chPrev = $tmpData[$i - 1];
            if ($chPrev != "\\" && $ch == "{") {
                $openBrackets++;
                $resParts[$openBrackets] = [];
            } else if ($chPrev != "\\" && $ch == "}") {
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
            } else if ($chPrev != "\\" && $ch =="\"") {
                $isStr = !$isStr;
                $isVal = true;
            } else if ($chPrev != "\\" && $ch == "," && !$isStr) {
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
}