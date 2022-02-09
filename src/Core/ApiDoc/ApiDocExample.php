<?php

namespace FlyCubePHP\Core\ApiDoc;

include_once __DIR__.'/../TemplateCompiler/TemplateCompiler.php';
include_once 'Helpers/ApiDocUrlHelper.php';

use FlyCubePHP\Core\ApiDoc\Helpers\ApiDocUrlHelper;
use FlyCubePHP\Core\Error\Error;
use FlyCubePHP\Core\Routes\Route;
use FlyCubePHP\Core\TemplateCompiler\TCHelperFunction;
use FlyCubePHP\Core\TemplateCompiler\TemplateCompiler;

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
     * Получить секцию api-doc в формате markdown
     * @return string
     */
    public function buildMarkdown(): string {
        $md = $this->_name;
        if (!empty($this->_description))
            $md .= " - " . $this->_description;
        $md .= "\r\n";
        if (!empty($this->_request)) {
            $tmpRequest = trim($this->_request);
            $tmpRequest = str_replace("\r\n", "\r\n   ", $tmpRequest);
            $md .= " * Request:\r\n";
            $md .= "   ```\r\n";
            $md .= "   $tmpRequest\r\n";
            $md .= "   ```\r\n";
        }
        if (!empty($this->_response)) {
            $tmpResponse = trim($this->_response);
            $tmpResponse = str_replace("\r\n", "\r\n   ", $tmpResponse);
            $md .= " * Response:\r\n";
            $md .= "   ```\r\n";
            $md .= "   $tmpResponse\r\n";
            $md .= "   ```\r\n";
        }
        return $md;
    }

    /**
     * Метод разбора данных секции
     * @param Route $route
     * @param string $name
     * @param array $data
     * @return ApiDocExample
     * @throws
     */
    static public function parse(Route &$route, string $name, array $data): ApiDocExample {
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
        // --- check ---
        if (empty($obj->_request) && empty($obj->_response))
            throw Error::makeError([
                'tag' => 'api-doc',
                'message' => "Invalid request/response in [api-doc] -> [action] -> [example] section (example section name: $name)!",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'additional-data' => [
                    'section' => [ 'example' ],
                    'name' => [ $name ]
                ]
            ]);

        // --- prepare request / response data ---
        $helper = new ApiDocUrlHelper($route);
        $templateCompiler = new TemplateCompiler();
        $templateCompiler->appendHelpFunction(new TCHelperFunction('current_action_url', [ $helper, 'current_action_url' ]));
        $templateCompiler->appendHelpFunction(new TCHelperFunction('action_url', [ $helper, 'action_url' ]));
        try {
            if (!empty($obj->_request))
                $obj->_request = $templateCompiler->compile($obj->_request);
            if (!empty($obj->_response))
                $obj->_response = $templateCompiler->compile($obj->_response);
        } catch (Error $ex) {
            throw Error::makeError([
                'tag' => 'api-doc',
                'message' => $ex->getMessage(),
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'additional-data' => [
                    'section' => [ 'example' ],
                    'name' => [ $name ],
                    'error-line-data' => $ex->additionalDataValue('error-line-data')
                ]
            ]);
        } catch (\Throwable $ex) {
            throw Error::makeError([
                'tag' => 'api-doc',
                'message' => $ex->getMessage(),
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__,
                'additional-data' => [
                    'section' => [ 'example' ],
                    'name' => [ $name ]
                ]
            ]);
        }
        unset($templateCompiler);
        unset($helper);

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