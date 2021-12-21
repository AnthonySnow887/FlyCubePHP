<?php

namespace FlyCubePHP\Core\ApiDoc;

include_once 'ApiDocParameter.php';

use FlyCubePHP\Core\Error\Error;

class ApiDocError
{
    private $_httpCode = -1;
    private $_name = "";
    private $_description = "";
    private $_parameters = [];

    /**
     * Код ответа HTTP
     * @return int
     */
    public function httpCode(): int {
        return $this->_httpCode;
    }

    /**
     * Название
     * @return string
     */
    public function name(): string {
        return $this->_name;
    }

    /**
     * Описание
     * @return string
     */
    public function description(): string {
        return $this->_description;
    }

    /**
     * Задан ли массив вовращаемых параметров
     * @return bool
     */
    public function hasParameters(): bool {
        return !empty($this->_parameters);
    }

    /**
     * Массив вовращаемых параметров
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
        $md = strval($this->_httpCode);
        if (!empty($this->_description))
            $md .= ": " . $this->_description;
        $md .= "\r\n";
        if (!empty($this->_parameters)) {
            $md .= " * Parameters:\r\n";
            foreach ($this->_parameters as $param) {
                $tmpMd = $param->buildMarkdown();
                $tmpMd = trim(str_replace("\r\n", "\r\n    ", $tmpMd));
                $md .= "   * $tmpMd\r\n";
            }
        }
        return $md;
    }

    /**
     * Метод разбора данных секции
     * @param string $name
     * @param array $data
     * @return ApiDocError
     * @throws Error
     */
    static public function parse(string $name, array $data): ApiDocError {
        $obj = new ApiDocError();
        $obj->_name = trim($name);
        foreach ($data as $key => $val) {
            if (strcmp($key, 'name') === 0 && !empty(trim(strval($val))))
                $obj->_name = trim(strval($val));
            else if (strcmp($key, 'code') === 0)
                $obj->_httpCode = intval($val);
            else if (strcmp($key, 'description') === 0)
                $obj->_description = trim(strval($val));
            else if (preg_match('/^param-(.*)$/', $key, $matches)) {
                try {
                    $obj->_parameters[] = ApiDocParameter::parse($matches[1], $val);
                } catch (Error $ex) {
                    $additionalData = [
                        'section' => array_merge([ 'error' ], $ex->additionalDataValue('section')),
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
        // --- check ---
        if ($obj->_httpCode < 100 || $obj->_httpCode > 526)
            throw Error::makeError([
                'tag' => 'api-doc',
                'message' => "Invalid http code in [api-doc] -> [action] -> [error] section (error section name: $name)!",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'additional-data' => [
                    'section' => 'error',
                    'name' => $name
                ]
            ]);

        return $obj;
    }
}