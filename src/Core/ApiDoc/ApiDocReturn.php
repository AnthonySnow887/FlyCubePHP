<?php

namespace FlyCubePHP\Core\ApiDoc;

use FlyCubePHP\Core\Error\Error;

include_once 'ApiDocParameter.php';

class ApiDocReturn
{
    private $_httpCode = -1;
    private $_dataType = "";
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
     * Тип возвращаемых данных
     * @return string
     */
    public function dataType(): string {
        return $this->_dataType;
    }

    /**
     * Описание
     * @return string
     */
    public function description(): string {
        return $this->_description;
    }

    /**
     * Заданы ли возвращаемые параметры?
     * @return bool
     */
    public function hasParameters(): bool {
        return !empty($this->_parameters);
    }

    /**
     * Массив возвращаемых параметров
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
        $md = "";
        if (!empty($this->_description))
            $md .= $this->_description . "\r\n";

        $md .= " * HTTP Status Code: " . $this->_httpCode . "\r\n";
        $md .= " * Type: " . $this->_dataType . "\r\n";

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
     * @param array $data
     * @return ApiDocReturn
     * @throws Error
     */
    static public function parse(array $data): ApiDocReturn {
        $obj = new ApiDocReturn();
        foreach ($data as $key => $val) {
            if (strcmp($key, 'code') === 0) {
                $obj->_httpCode = intval($val);
            } else if (strcmp($key, 'type') === 0) {
                $obj->_dataType = trim(strval($val));
            } else if (strcmp($key, 'description') === 0) {
                        $obj->_description = trim(strval($val));
            } else if (preg_match('/^param-(.*)$/', $key, $matches)) {
                try {
                    $obj->_parameters[] = ApiDocParameter::parse($matches[1], $val);
                } catch (Error $ex) {
                    $additionalData = [
                        'section' => array_merge([ 'return' ], $ex->additionalDataValue('section')),
                        'name' => array_merge([ '' ], $ex->additionalDataValue('name'))
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
                'message' => "Invalid http code in [api-doc] -> [action] -> [return] section!",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'additional-data' => [
                    'section' => [ 'return' ],
                    'name' => [ '' ]
                ]
            ]);
        if (empty($obj->_dataType))
            throw Error::makeError([
                'tag' => 'api-doc',
                'message' => "Invalid data type in [api-doc] -> [action] -> [return] section!",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'additional-data' => [
                    'section' => [ 'return' ],
                    'name' => [ '' ]
                ]
            ]);

        return $obj;
    }
}