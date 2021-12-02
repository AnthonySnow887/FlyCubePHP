<?php

namespace FlyCubePHP\Core\ApiDoc;

use FlyCubePHP\HelperClasses\CoreHelper;

class ApiDocExample
{
    private $_name = "";
    private $_description = "";
    private $_request = "";
    private $_response = "";

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
     * Задан ли пример запроса?
     * @return bool
     */
    public function hasRequest(): bool {
        return !empty($this->_request);
    }

    /**
     * Пример запроса
     * @return string
     */
    public function request(): string {
        return $this->_request;
    }

    /**
     * Задан ли пример ответа?
     * @return bool
     */
    public function hasResponse(): bool {
        return !empty($this->_response);
    }

    /**
     * Пример ответа
     * @return string
     */
    public function response(): string {
        return $this->_response;
    }

    /**
     * Метод разбора данных секции
     * @param string $name
     * @param array $data
     * @return ApiDocExample
     */
    static public function parse(string $name, array $data): ApiDocExample {
        $obj = new ApiDocExample();
        $obj->_name = trim($name);
        foreach ($data as $key => $val) {
            if (strcmp($key, 'name') === 0 && !empty(trim(strval($val))))
                $obj->_name = trim(strval($val));
            else if (strcmp($key, 'description') === 0)
                $obj->_description = trim(strval($val));
            else if (strcmp($key, 'request') === 0)
                $obj->_request = self::parseRequestResponse($val);
            else if (strcmp($key, 'response') === 0)
                $obj->_response = self::parseRequestResponse($val);
        }
        return $obj;
    }

    /**
     * Метод разбора примеров
     * @param $data
     * @return string
     */
    static private function parseRequestResponse($data): string {
        $tmp = "";
        if (is_string($data)) {
            $tmp = trim($data);
        } else if (is_array($data)) {
            foreach ($data as $s) {
                if (!is_string($s))
                    continue;
                $tmp .= "$s\r\n";
            }
            $tmp = trim($tmp);
        }
        return $tmp;
    }
}