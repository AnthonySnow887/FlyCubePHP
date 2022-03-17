<?php

namespace FlyCubePHP\Core\ApiDoc;

use FlyCubePHP\Core\Routes\RouteCollector;
use FlyCubePHP\Core\Routes\RouteType;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\Error\Error;

include_once 'ApiDocAction.php';

class ApiDocObject
{
    private $_name = "";
    private $_blockName = "";
    private $_blockDescription = "";
    private $_actions = [];
    private $_sourceFilePath = "";

    /**
     * Название API по имени файла
     * @return string
     */
    public function name(): string {
        return $this->_name;
    }

    /**
     * Название блока API
     * @return string
     */
    public function blockName(): string {
        return $this->_blockName;
    }

    /**
     * Описание блока API
     * @return string
     */
    public function blockDescription(): string {
        return $this->_blockDescription;
    }

    /**
     * Список методов блока API
     * @return array[ApiDocAction]
     */
    public function actions(): array {
        return $this->_actions;
    }

    /**
     * Путь до файла источника данных api
     * @return string
     */
    public function sourceFilePath(): string {
        return $this->_sourceFilePath;
    }

    /**
     * Является ли пустым?
     * @return bool
     */
    public function isEmpty(): bool {
        return empty($this->_actions);
    }

    /**
     * Получить api-doc в формате markdown
     * @return string
     */
    public function buildMarkdown(): string {
        $md = "# " . $this->_blockName . "\r\n";
        $md .= "\r\n";
        if (!empty($this->_blockDescription)) {
            $md .= $this->_blockDescription . "\r\n";
            $md .= "\r\n";
        }
        $md .= "### Actions:\r\n";
        $md .= "\r\n";
        foreach ($this->_actions as $act) {
            $actType = RouteType::intToString($act->httpMethod());
            $actUrl = $act->url();
            $actName = $act->name();
            $md .= " * $actType $actUrl - $actName\r\n";
        }
        $md .= "\r\n";
        foreach ($this->_actions as $act)
            $md .= $act->buildMarkdown();

        $md .= "\r\n";
        return $md;
    }

    /**
     * Разобрать блок данных
     * @param string $path - путь до файла
     * @return ApiDocObject
     * @throws Error
     */
    static public function parseApiDoc(string $path): ApiDocObject {
        if (!is_file($path) || !is_readable($path))
            throw Error::makeError([
                'tag' => 'api-doc',
                'message' => "Not found api-doc file or file is not readable! Path: $path",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        $tmpName = CoreHelper::fileName($path, true);
        if (!class_exists($tmpName))
            throw Error::makeError([
                'tag' => 'api-doc',
                'message' => "Not found php class for api-doc file (needed class: $tmpName)! Path: $path",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        // --- parse ---
        $apiData = json_decode(file_get_contents($path), true);
        $obj = new ApiDocObject();
        $obj->_name = CoreHelper::fileName($path, true);
        $obj->_sourceFilePath = $path;
        $obj->_blockName = $tmpName;
        if (preg_match("/.*Controller$/", $tmpName))
            $obj->_blockName = substr($tmpName, 0, strlen($tmpName) - 10);
        if (isset($apiData['api-block-name'])) {
            if (!empty(trim(strval($apiData['api-block-name']))))
                $obj->_blockName = trim(strval($apiData['api-block-name']));
            unset($apiData['api-block-name']);
        }
        if (isset($apiData['api-block-description'])) {
            $obj->_blockDescription = trim(strval($apiData['api-block-description']));
            unset($apiData['api-block-description']);
        }
        $methods = get_class_methods($tmpName);
        foreach ($apiData as $key => $val) {
            if (!in_array($key, $methods))
                continue;
            $route = RouteCollector::instance()->routeByControllerAct($tmpName, $key);
            if (is_null($route))
                continue;
            try {
                $obj->_actions[] = ApiDocAction::parse($route, $key, $val);
            } catch (Error $ex) {
                throw Error::makeError([
                    'tag' => 'api-doc',
                    'message' => "Parse API-Doc file failed! " . $ex->getMessage(),
                    'class-name' => __CLASS__,
                    'class-method' => __FUNCTION__,
                    'file' => $path,
                    'line' => self::searchErrorLine(
                        $path,
                        $key,
                        $ex->additionalDataValue('section'),
                        $ex->additionalDataValue('name'),
                        $ex->additionalDataValue('error-line-data')
                    )
                ]);
            }
        }
        return $obj;
    }

    /**
     * Поиск строки с ошибкой
     * @param string $filePath - путь до файла
     * @param string $action - название метода блока API
     * @param array $section - последовательность прочитанных секций
     * @param array $sectionName - последовательность имен прочитанных секций
     * @param string|null $errorLineData - содержимое строки с ошибкой
     * @return int
     */
    static private function searchErrorLine(string $filePath,
                                            string $action,
                                            array $section,
                                            array $sectionName,
                                            /*string|null*/ $errorLineData): int {
        if (!is_file($filePath)
            || !is_readable($filePath)
            || count($section) != count($sectionName))
            return 0;
        $regExp = ".*\"$action\".*";
        $found = false;
        $lineCount = 0;
        $firstFoundLine = 0;
        if ($file = fopen($filePath, "r")) {
            while (!feof($file)) {
                $lineCount += 1;
                $lineStr = fgets($file);
                // --- search needed action section ---
                if (preg_match("/$regExp/", $lineStr) && $found === false) {
                    $found = true;
                    $firstFoundLine = $lineCount;
                } else if ($found === false) {
                    continue;
                }
                // --- make regexp ---
                if (empty($section)) {
                    if (!empty($errorLineData)) {
                        if (strpos(stripcslashes($lineStr), $errorLineData) !== false)
                            break;
                        continue;
                    }
                    break;
                }
                $tmpSection = $section[0];
                $tmpSectionName = $sectionName[0];
                $regExpSection = ".*\"$tmpSection\".*";
                if (!empty($tmpSectionName))
                    $regExpSection = ".*\"$tmpSection-". self::quoteSectionName($tmpSectionName) . "\".*";

                if (preg_match("/$regExpSection/", $lineStr)) {
                    array_shift($section);
                    array_shift($sectionName);
                    if (empty($section)) {
                        $firstFoundLine = $lineCount;
                        if (!empty($errorLineData))
                            continue;
                        break;
                    }
                }
            }
            if (feof($file))
                $lineCount = $firstFoundLine;
            fclose($file);
        }
        return $lineCount;
    }

    /**
     * Экранирование имени секции
     * @param string $str
     * @return string
     */
    static private function quoteSectionName(string $str): string {
        if (empty($str))
            return $str;
        $str = str_replace("[", "\[", $str);
        $str = str_replace("]", "\]", $str);
        $str = str_replace("{", "\{", $str);
        $str = str_replace("}", "\}", $str);
        $str = str_replace("(", "\(", $str);
        $str = str_replace(")", "\)", $str);
        $str = str_replace("|", "\|", $str);
        $str = str_replace(":", "\:", $str);
        $str = str_replace("?", "\?", $str);
        $str = str_replace(".", "\.", $str);
        $str = str_replace(",", "\,", $str);
        $str = str_replace("*", "\*", $str);
        $str = str_replace("+", "\+", $str);
        $str = str_replace("-", "\-", $str);
        $str = str_replace("^", "\^", $str);
        $str = str_replace("$", "\\$", $str);
        return $str;
    }
}