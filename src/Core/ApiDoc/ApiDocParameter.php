<?php

namespace FlyCubePHP\Core\ApiDoc;

use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\Error\Error;

class ApiDocParameter
{
    private $_name = "";
    private $_description = "";
    private $_dataType = "";
    private $_isOptional = false;
    private $_canBeEmpty = false;
    private $_min = 0;
    private $_max = 0;
    private $_availableValues = [];
    private $_parameters = [];

    /**
     * Название параметра
     * @return string
     */
    public function name(): string {
        return $this->_name;
    }

    /**
     * Описание параметра
     * @return string
     */
    public function description(): string {
        return $this->_description;
    }

    /**
     * Тип данных параметра
     * @return string
     */
    public function dataType(): string {
        return $this->_dataType;
    }

    /**
     * Является ли необязательным (опциональным)?
     * @return bool
     */
    public function isOptional(): bool {
        return $this->_isOptional;
    }

    /**
     * Может ли иметь пустое значение?
     * @return bool
     */
    public function canBeEmpty(): bool {
        return $this->_canBeEmpty;
    }

    /**
     * Минимальное значение (для числовых типов)
     * @return int|double
     */
    public function min()/*: int|double */ {
        return $this->_min;
    }

    /**
     * Максимальное значение (для числовых типов)
     * @return int|double
     */
    public function max()/*: int|double */ {
        return $this->_max;
    }

    /**
     * Задан ли массив возможных значений?
     * @return bool
     */
    public function hasAvailableValues(): bool {
        return !empty($this->_availableValues);
    }

    /**
     * Массив возможных значений
     * @return array
     */
    public function availableValues(): array {
        return $this->_availableValues;
    }

    /**
     * Задан ли массив параметров?
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
     * Метод разбора данных секции
     * @param string $name
     * @param array $data
     * @return ApiDocParameter
     * @throws Error
     */
    static public function parse(string $name, array $data): ApiDocParameter {
        $obj = new ApiDocParameter();
        $obj->_name = trim($name);
        foreach ($data as $key => $val) {
            if (strcmp($key, 'name') === 0 && !empty(trim(strval($val))))
                $obj->_name = trim(strval($val));
            else if (strcmp($key, 'description') === 0)
                $obj->_description = trim(strval($val));
            else if (strcmp($key, 'type') === 0)
                $obj->_dataType = trim(strval($val));
            else if (strcmp($key, 'optional') === 0)
                $obj->_isOptional = CoreHelper::toBool($val);
            else if (strcmp($key, 'empty') === 0)
                $obj->_canBeEmpty = CoreHelper::toBool($val);
            else if (strcmp($key, 'min') === 0)
                $obj->_min = is_double($val) ? doubleval($val) : intval($val);
            else if (strcmp($key, 'max') === 0)
                $obj->_max = is_double($val) ? doubleval($val) : intval($val);
            else if (strcmp($key, 'available-values') === 0)
                $obj->_availableValues = self::parseAvailableValues($val);
            else if (preg_match('/^param-(.*)$/', $key, $matches)) {
                try {
                    $obj->_parameters[] = ApiDocParameter::parse($matches[1], $val);
                } catch (Error $ex) {
                    $additionalData = [
                        'section' => array_merge([ 'param' ], $ex->additionalDataValue('section')),
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
        if (empty($obj->_dataType))
            throw Error::makeError([
                'tag' => 'api-doc',
                'message' => "Invalid data type in [api-doc] -> [action] -> [param] section (param section name: $name)!",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'additional-data' => [
                    'section' =>  [ 'param' ],
                    'name' =>  [ $name ]
                ]
            ]);

        return $obj;
    }

    /**
     * Метод разбора возможных значений
     * @param array $data
     * @return array
     */
    static private function parseAvailableValues(array $data): array {
        $availableValues = [];
        foreach ($data as $val) {
            if (!is_array($val) && !is_object($val))
                $availableValues[] = trim(strval($val));
        }
        return $availableValues;
    }
}