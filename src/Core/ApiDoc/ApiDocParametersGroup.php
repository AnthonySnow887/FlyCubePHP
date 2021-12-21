<?php

namespace FlyCubePHP\Core\ApiDoc;

use FlyCubePHP\Core\Error\Error;

include_once 'ApiDocParameter.php';

class ApiDocParametersGroup
{
    private $_name = "";
    private $_description = "";
    private $_parameters = [];

    /**
     * Название группы параметров
     * @return string
     */
    public function name(): string {
        return $this->_name;
    }

    /**
     * Описание группы параметров
     * @return string
     */
    public function description(): string {
        return $this->_description;
    }

    /**
     * Пустая ли группа?
     * @return bool
     *
     * NOTE: this is alias for hasParameters().
     */
    public function isEmpty(): bool {
        return !$this->hasParameters();
    }

    /**
     * Заданы ли параметры
     * @return bool
     */
    public function hasParameters(): bool {
        return !empty($this->_parameters);
    }

    /**
     * Массив параметров
     * @return array
     */
    public function parameters(): array {
        return $this->_parameters;
    }

    /**
     * Получить секцию api-doc в формате markdown
     * @return string
     */
    public function buildMarkdown(): string {
        if (empty($this->_parameters))
            return "";

        $tmpName = trim($this->_name . " - " . $this->_description);
        $md = "PARAMETERS GROUP: **$tmpName**\r\n";
        foreach ($this->_parameters as $param) {
            $tmpMd = $param->buildMarkdown();
            $tmpMd = trim(str_replace("\r\n", "\r\n  ", $tmpMd));
            $md .= " * $tmpMd\r\n";
        }
        return $md;
    }

    /**
     * Метод разбора данных секции
     * @param string $name
     * @param array $data
     * @return ApiDocParametersGroup
     * @throws Error
     */
    static public function parse(string $name, array $data): ApiDocParametersGroup {
        $obj = new ApiDocParametersGroup();
        $obj->_name = trim($name);
        foreach ($data as $key => $val) {
            if (strcmp($key, 'name') === 0 && !empty(trim(strval($val))))
                $obj->_name = trim(strval($val));
            else if (strcmp($key, 'description') === 0)
                $obj->_description = trim(strval($val));
            else if (preg_match('/^param-(.*)$/', $key, $matches)) {
                try {
                    $obj->_parameters[] = ApiDocParameter::parse($matches[1], $val);
                } catch (Error $ex) {
                    $additionalData = [
                        'section' => array_merge([ 'param-group' ], $ex->additionalDataValue('section')),
                        'name' => array_merge([ $name ], $ex->additionalDataValue('name'))
                    ];
                    throw Error::makeError([
                        'tag' => 'api-doc',
                        'message' => $ex->getMessage(),
                        'class-name' => __CLASS__,
                        'class-method' => __FUNCTION__,
                        'additional-data' => $additionalData
                    ]);
                }
            }
        }
        return $obj;
    }
}