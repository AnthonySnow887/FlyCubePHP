<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 26.08.21
 * Time: 12:20
 */

namespace FlyCubePHP\Core\Error;

use FlyCubePHP\Core\Routes\RouteType;

include_once 'Error.php';
include_once __DIR__.'/../Routes/Route.php';

class ErrorRoutes extends Error
{
    private $_uri = "";
    private $_controller = "";
    private $_action = "";
    private $_routeType = -1;

    function __construct(string $uri,
                         string $controller,
                         string $action,
                         int $routeType,
                         string $message = "",
                         string $tag = "",
                         int $code = 0,
                         \Throwable $previous = null) {
        parent::__construct($message, $tag, $code, $previous);
        $this->_type = ErrorType::ROUTES;
        $this->_uri = $uri;
        $this->_controller = $controller;
        $this->_action = $action;
        $this->_routeType = $routeType;
    }

    final public function uri(): string {
        return $this->_uri;
    }

    final public function controller(): string {
        return $this->_controller;
    }

    final public function action(): string {
        return $this->_action;
    }

    final public function routeType(): int {
        return $this->_routeType;
    }

    final public function routeTypeStr(): string {
        return RouteType::intToString($this->_routeType);
    }

    /**
     * Create Error object
     * @param array $options
     * @return ErrorRoutes
     *
     * ==== Options
     *
     * - [string]       message             - error message
     * - [string]       tag                 - error tag (default: empty)
     * - [int]          code                - error code (default: 0)
     * - [Throwable]    previous            - previous error object (default: null)
     * - [string]       additional-message  - additional error message (default: empty)
     * - [array]        additional-data     - additional error data (key:value array) (default: empty)
     * - [string]       file                - error file (default: used backtrace point)
     * - [int]          line                - error line (default: used backtrace point)
     * - [int]          backtrace-shift     - error backtrace point (default: 1)
     * - [string]       url                 - set URL
     * - [string]       controller          - set controller class name
     * - [string]       action              - set controller action name
     * - [int]          route-type          - set route type
     */
    final static public function makeError(array $options = []) {
        $message = "";
        if (isset($options['message']))
            $message = $options['message'];
        $tag = "";
        if (isset($options['tag']))
            $tag = $options['tag'];
        $controllerName = "";
        if (isset($options['controller']))
            $controllerName = $options['controller'];
        $controllerAct = "";
        if (isset($options['action']))
            $controllerAct = $options['action'];
        $url = "";
        if (isset($options['url']))
            $url = $options['url'];
        $routeType = -1;
        if (isset($options['route-type']))
            $routeType = $options['route-type'];
        $code = 0;
        if (isset($options['code']))
            $code = $options['code'];
        $previous = null;
        if (isset($options['previous'])
            && is_subclass_of($options['previous'], 'Throwable'))
            $previous = $options['previous'];
        $additionalMessage = "";
        if (isset($options['additional-message']))
            $additionalMessage = $options['additional-message'];
        $additionalData = [];
        if (isset($options['additional-data'])
            && is_array($options['additional-data']))
            $additionalData = $options['additional-data'];
        $file = "";
        if (isset($options['file']))
            $file = $options['file'];
        $line = 0;
        if (isset($options['line']))
            $line = $options['line'];

        $backtraceShift = 1;
        if (isset($options['backtrace-shift']))
            $backtraceShift = $options['backtrace-shift'];
        if (empty($file) || $line <= 0) {
            $backtraceArr = debug_backtrace();
            if ($backtraceShift <= 0)
                $backtraceShift = 1;
            for ($i = 0; $i < $backtraceShift; $i++)
                array_shift($backtraceArr); // remove method backtrace
            $lastUsage = array_shift($backtraceArr);
            $file = "";
            if (isset($lastUsage['file']))
                $file = $lastUsage['file'];
            $line = 0;
            if (isset($lastUsage['line']))
                $line = intval($lastUsage['line']);
        }

        $err = new ErrorRoutes($url, $controllerName, $controllerAct, $routeType, $message, $tag, $code, $previous);
        $err->setAdditionalMessage($additionalMessage);
        $err->setAdditionalData($additionalData);
        $err->setFile($file);
        $err->setLine($line);
        return $err;
    }
}