<?php

namespace FlyCubePHP\Core\ApiDoc;

use FlyCubePHP\Core\Routes\RouteType;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\Error\Error;

include_once 'ApiDocReturn.php';
include_once 'ApiDocParameter.php';
include_once 'ApiDocParametersGroup.php';
include_once 'ApiDocError.php';
include_once 'ApiDocExample.php';

class ApiDocAction
{
    private $_httpMethod = -1;
    private $_url = "";
    private $_urlFull = "";
    private $_name = "";
    private $_description = "";
    private $_version = "";
    private $_outputFormats = [];
    private $_isDeprecated = false;
    private $_parameters = [];
    private $_parameterGroups = [];
    private $_httpHeaders = [];
    private $_return = null;
    private $_errors = [];
    private $_examples = [];

    /**
     * Тип HTTP метода (get/post/put/patch/delete) (смотри RouteType::...)
     * @return int
     */
    public function httpMethod(): int {
        return $this->_httpMethod;
    }

    /**
     * URL доступа
     * @return string
     */
    public function url(): string {
        return $this->_url;
    }

    /**
     * URL доступа с аргументами
     * @return string
     */
    public function urlFull(): string {
        return $this->_urlFull;
    }

    /**
     * Название API метода
     * @return string
     */
    public function name(): string {
        return $this->_name;
    }

    /**
     * Описание API метода
     * @return string
     */
    public function description(): string {
        return $this->_description;
    }

    /**
     * Версия API метода
     * @return string
     */
    public function version(): string {
        return $this->_version;
    }

    /**
     * Перечень форматов возвращаемых данных
     * @return array
     */
    public function outputFormats(): array {
        return $this->_outputFormats;
    }

    /**
     * Является ли устаревшим?
     * @return bool
     */
    public function isDeprecated(): bool {
        return $this->_isDeprecated;
    }

    /**
     * Задан ли массив входных параметров?
     * @return bool
     */
    public function hasParameters(): bool {
        return !empty($this->_parameters);
    }

    /**
     * Массив входных параметров
     * @return array[ApiDocParameter]
     */
    public function parameters(): array {
        return $this->_parameters;
    }

    /**
     * Заданы ли группы входных параметров?
     * @return bool
     */
    public function hasParameterGroups(): bool {
        return !empty($this->_parameterGroups);
    }

    /**
     * Группы входных параметров
     * @return array[ApiDocParametersGroup]
     */
    public function parameterGroups(): array {
        return $this->_parameterGroups;
    }

    /**
     * Заданы ли требуемые HTTP заголовки?
     * @return bool
     */
    public function hasHttpHeaders(): bool {
        return !empty($this->_httpHeaders);
    }

    /**
     * Массив требуемых HTTP заголовков
     * @return array
     *
     * array [ string => string ]
     * - key:   HTTP Header name
     * - value: Description
     */
    public function httpHeaders(): array {
        return $this->_httpHeaders;
    }

    /**
     * Обьект описания ответного сообщения
     * @return null|ApiDocReturn
     */
    public function returnData() {
        return $this->_return;
    }

    /**
     * Заданы ли возможные ошибки?
     * @return bool
     */
    public function hasErrors(): bool {
        return !empty($this->_errors);
    }

    /**
     * Массив возможных ошибок
     * @return array[ApiDocError]
     */
    public function errors(): array {
        return $this->_errors;
    }

    /**
     * Заданы ли примеры?
     * @return bool
     */
    public function hasExamples(): bool {
        return !empty($this->_examples);
    }

    /**
     * Массив с примерами
     * @return array[ApiDocExample]
     */
    public function examples(): array {
        return $this->_examples;
    }

    /**
     * Получить секцию api-doc в формате markdown
     * @return string
     */
    public function buildMarkdown(): string {
        $actType = RouteType::intToString($this->_httpMethod);
        $actUrl = $this->_url;
        $md = "## $actType $actUrl\r\n";
        $md .= "\r\n";
        $md .= $this->_name . "\r\n";
        if (!empty($this->_description))
            $md .= $this->_description . "\r\n";
        if (!empty($this->_version))
            $md .= "\r\nVersion: " . $this->_version . "\r\n";
        if ($this->_isDeprecated === true) {
            $md .= "\r\n---\r\n";
            $md .= "**NOTE**\r\n";
            $md .= "\r\n";
            $md .= "This is deprecated method! In the following versions it will be deleted!\r\n";
            $md .= "\r\n";
            $md .= "---\r\n";
        }
        $md .= "\r\n";

        $md .= "### Output formats:\r\n";
        foreach ($this->_outputFormats as $val)
            $md .= " * $val\r\n";
        $md .= "\r\n";

        if (!empty($this->_httpHeaders)) {
            $md .= "### Required HTTP headers:\r\n";
            foreach ($this->_httpHeaders as $key => $val)
                $md .= " * $key - $val\r\n";
            $md .= "\r\n";
        }

        if (!empty($this->_parameters) || !empty($this->_parameterGroups)) {
            $md .= "### Input params:\r\n";
            foreach ($this->_parameters as $param) {
                $tmpMd = $param->buildMarkdown();
                $tmpMd = trim(str_replace("\r\n", "\r\n  ", $tmpMd));
                $md .= " * $tmpMd\r\n";
            }
            foreach ($this->_parameterGroups as $param) {
                $tmpMd = $param->buildMarkdown();
                $tmpMd = trim(str_replace("\r\n", "\r\n  ", $tmpMd));
                $md .= " * $tmpMd\r\n";
            }
            $md = trim($md);
            $md .= "\r\n\r\n";
        }

        $md .= "### Response:\r\n";
        $md .= $this->_return->buildMarkdown();
        $md = trim($md);
        $md .= "\r\n\r\n";

        if (!empty($this->_errors)) {
            $md .= "### Errors:\r\n";
            foreach ($this->_errors as $err) {
                $tmpMd = $err->buildMarkdown();
                $tmpMd = trim(str_replace("\r\n", "\r\n  ", $tmpMd));
                $md .= " * $tmpMd\r\n";
            }
            $md = trim($md);
            $md .= "\r\n\r\n";
        }

        if (!empty($this->_examples)) {
            $md .= "### Examples:\r\n";
            foreach ($this->_examples as $ex) {
                $tmpMd = $ex->buildMarkdown();
                $tmpMd = trim(str_replace("\r\n", "\r\n  ", $tmpMd));
                $md .= " * $tmpMd\r\n";
            }
            $md = trim($md);
            $md .= "\r\n\r\n";
        }
        $md .= "\r\n";
        return $md;
    }

    /**
     * Метод разбора данных секции
     * @param int $httpMethod
     * @param string $url
     * @param string $urlFull
     * @param string $name
     * @param array $data
     * @return ApiDocAction
     * @throws Error
     */
    static public function parse(int $httpMethod, string $url, string $urlFull, string $name, array $data): ApiDocAction {
        $obj = new ApiDocAction();
        $obj->_httpMethod = $httpMethod;
        $obj->_url = $url;
        $obj->_urlFull = $urlFull;
        $obj->_name = trim($name);
        foreach ($data as $key => $val) {
            if (strcmp($key, 'description') === 0)
                $obj->_description = trim(strval($val));
            else if (strcmp($key, 'version') === 0)
                $obj->_version = trim(strval($val));
            else if (strcmp($key, 'deprecated') === 0)
                $obj->_isDeprecated = CoreHelper::toBool($val);
            else if (strcmp($key, 'output-formats') === 0)
                $obj->_outputFormats = self::parseOutputFormats($val);
            else if (strcmp($key, 'headers') === 0)
                $obj->_httpHeaders = self::parseHeaders($val);
            else if (strcmp($key, 'return') === 0)
                $obj->_return = ApiDocReturn::parse($val);
            else if (preg_match('/^param-group-(.*)$/', $key, $matches))
                $obj->_parameterGroups[] = ApiDocParametersGroup::parse($matches[1], $val);
            else if (preg_match('/^param-(.*)$/', $key, $matches))
                $obj->_parameters[] = ApiDocParameter::parse($matches[1], $val);
            else if (preg_match('/^error-(.*)$/', $key, $matches))
                $obj->_errors[] = ApiDocError::parse($matches[1], $val);
            else if (preg_match('/^example-(.*)$/', $key, $matches))
                $obj->_examples[] = ApiDocExample::parse($matches[1], $val);
        }
        // --- check ---
        if (empty($obj->_outputFormats))
            throw Error::makeError([
                'tag' => 'api-doc',
                'message' => "Not found api-doc output formats! Method section: $name",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'additional-data' => [
                    'section' => [ $name ],
                    'name' => [ '' ]
                ]
            ]);
        if (is_null($obj->_return))
            throw Error::makeError([
                'tag' => 'api-doc',
                'message' => "Not found api-doc return section! Method section: $name",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'additional-data' => [
                    'section' => [ $name ],
                    'name' => [ '' ]
                ]
            ]);

        return $obj;
    }

    /**
     * Метод разбора форматов возвращаемых данных
     * @param array $data
     * @return array
     */
    static private function parseOutputFormats(array $data): array {
        $lst = [];
        foreach ($data as $val) {
            if (!is_array($val) && !is_object($val))
                $lst[] = trim(strval($val));
        }
        return $lst;
    }

    /**
     * Метод разбора HTTP заголовков
     * @param array $data
     * @return array
     */
    static private function parseHeaders(array $data): array {
        $headers = [];
        foreach ($data as $key => $val) {
            if (is_string($key) && is_string($val))
                $headers[trim($key)] = trim($val);
        }
        return $headers;
    }
}