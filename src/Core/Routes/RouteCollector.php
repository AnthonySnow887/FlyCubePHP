<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 21.07.21
 * Time: 15:12
 */

namespace FlyCubePHP\Core\Routes;

include_once 'Route.php';
include_once 'RouteStreamParser.php';
include_once __DIR__.'/../Config/ConfigHelper.php';
include_once __DIR__ . '/../Controllers/BaseActionController.php';
include_once __DIR__ . '/../Controllers/BaseActionControllerAPI.php';
include_once __DIR__.'/../Error/ErrorRoutes.php';

use Exception;
use \FlyCubePHP\Core\Config\Config as Config;
use \FlyCubePHP\Core\Error\ErrorRoutes as ErrorRoutes;
use \FlyCubePHP\Core\Controllers\BaseController as BaseController;

class RouteCollector
{
    private static $_instance = null;

    private $_routes = array();

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function instance(): RouteCollector {
        if (static::$_instance === null)
            static::$_instance = new static();
        return static::$_instance;
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     */
    private function __construct() {
    }

    function __destruct() {
        $args = RouteCollector::currentRouteArgs();
        if (isset($args['file']) && is_array($args['file'])) {
            foreach ($args['file'] as $fInfo) {
                if (isset($fInfo['tmp_name'])
                    && !empty($fInfo['tmp_name'])
                    && is_file($fInfo['tmp_name']))
                    unlink($fInfo['tmp_name']);
            }
        }
        unset($this->_routes);
    }

    /**
     * prevent the instance from being cloned (which would create a second instance of it)
     */
    private function __clone() {
    }

    /**
     * prevent from being unserialized (which would create a second instance of it)
     * @throws Exception Cannot unserialize singleton
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }

    /**
     * Добавить объект маршрута
     * @param Route $route
     */
    public function appendRoute(Route &$route) {
        if (is_null($route))
            return;
        $tmpName = RouteType::intToString($route->type()) . "_" . $route->uri();
        if (array_key_exists($tmpName, $this->_routes)) {
            unset($route);
            return;
        }
        $this->_routes[$tmpName] = $route;
    }

    /**
     * Проверка наличия маршрута
     * @param string $method - HTTP Метод
     * @param string $uri - URL
     * @return bool
     */
    public function containsRoute(string $method, string $uri): bool {
        if (empty($method) || empty($uri))
            return false;
        foreach ($this->_routes as $route) {
            if ($route->type() == RouteType::stringToInt($method) && $route->uri() == $uri)
                return true;
        }
        return false;
    }

    /**
     * Проверка наличия корневого маршрута
     * @return bool
     */
    public function containsRootRoute(): bool {
        foreach ($this->_routes as $route) {
            if ($route->type() == RouteType::GET && $route->uri() == "/")
                return true;
        }
        return false;
    }

    /**
     * Получить объект маршрута
     * @param string $method - HTTP Метод
     * @param string $uri - URL
     * @return Route|null
     */
    public function routeByMethodUri(string $method, string $uri)/*: Route|null*/ {
        if (empty($method) || empty($uri))
            return null;
        foreach ($this->_routes as $route) {
            if ($route->type() == RouteType::stringToInt($method) && $route->uri() == $uri)
                return $route;
        }
        return null;
    }

    /**
     * Получить объект маршрута по названию контроллера и его экшена
     * @param string $controller - контроллер
     * @param string $action - его экшен
     * @return Route|null
     */
    public function routeByControllerAct(string $controller, string $action)/*: Route|null*/ {
        if (empty($controller) || empty($action))
            return null;
        foreach ($this->_routes as $route) {
            if (strcmp($route->controller(), $controller) === 0
                && strcmp($route->action(), $action) === 0)
                return $route;
        }
        return null;
    }

    /**
     * Получить объект корневого маршрута
     * @return Route|null
     */
    public function rootRoute()/*: Route|null*/ {
        foreach ($this->_routes as $route) {
            if ($route->type() == RouteType::GET && $route->uri() == "/")
                return $route;
        }
        return null;
    }

    /**
     * Получить все маршруты
     * @return array
     */
    public function allRoutes(): array {
        return $this->_routes;
    }

    /**
     * Получить объект маршрута для текущего запроса
     * @return Route|Route
     */
    public function currentRoute()/*: Route|null*/ {
        $tmpURI = RouteCollector::currentRouteUri();
        $tmpMethod = RouteCollector::currentRouteMethod();
        return $this->routeByMethodUri($tmpMethod, $tmpURI);
    }

    /**
     * Метод проверки маршрутов на корректность (верно ли заданы контроллеры и их методы)
     * @return bool
     *
     * Если найден некорректный маршрут, то он удаляется из списка!
     *
     * Return true if route list is not empty, else - return false.
     */
    public function checkRoutes(): bool {
        $tmpRemove = array();
        foreach ($this->_routes as $key => $value) {
            $tmpClassName = $value->controller();
            $tmpClassAct = $value->action();
            if (!class_exists($tmpClassName, false)) {
                $tmpRemove[] = $key;
                continue;
            }
            $tmpController = new $tmpClassName();
            if (!$tmpController instanceof BaseController)
                $tmpRemove[] = $key;
            elseif (!method_exists($tmpController, $tmpClassAct))
                $tmpRemove[] = $key;

            unset($tmpController);
        }
        foreach ($tmpRemove as $key => $value)
            unset($this->_routes[$value]);

        return count($this->_routes) > 0 ? true : false;
    }

    /**
     * Получить URL префикс для приложения
     * @return string
     */
    static public function applicationUrlPrefix(): string {
        $appPrefix = \FlyCubePHP\configValue(Config::TAG_APP_URL_PREFIX, "");
        return RouteCollector::makeValidUrlPrefix($appPrefix);
    }

    /**
     * Получить текущий URL хоста
     * @return string
     */
    static public function currentHostUri(): string {
        $protocol = "http";
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            $protocol = "https";
        return "$protocol://$_SERVER[HTTP_HOST]";
    }

    /**
     * @brief Получить IP текущего клиента
     * @return string
     */
    static public function currentClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP']))
            return $_SERVER['HTTP_CLIENT_IP'];
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
            return $_SERVER['HTTP_X_FORWARDED_FOR'];

        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Получить текущий URL
     * @return string
     */
    static public function currentUri(): string {
        $protocol = "http";
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            $protocol = "https";
        return "$protocol://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }

    /**
     * Получить текущий URL без аргументов
     * @return string
     */
    static public function currentRouteUri(): string {
        $tmpURI = $_SERVER['REQUEST_URI'];
        $appPrefix = RouteCollector::applicationUrlPrefix();
        if (!empty($appPrefix) && $appPrefix !== "/") {
            $pos = strpos($tmpURI, $appPrefix);
            if ($pos !== false)
                $tmpURI = substr_replace($tmpURI, "", $pos, strlen($appPrefix));
        }
        $tmpURILst = explode('?', $tmpURI);
        $tmpURI = RouteCollector::spliceUrlLast($tmpURILst[0]);
        if (empty($tmpURI))
            $tmpURI = "/";
        return $tmpURI;
    }

    /**
     * Получить название http метода для текущего запроса
     * @return string
     */
    static public function currentRouteMethod(): string {
        $tmpArgs = RouteCollector::currentRouteArgs();
        if (array_key_exists("_method", $tmpArgs)) {
            $tmpMethod = strtolower($tmpArgs["_method"]);
            if ($tmpMethod === "get")
                return "GET";
            elseif ($tmpMethod == "post")
                return "POST";
            elseif ($tmpMethod == "put")
                return "PUT";
            elseif ($tmpMethod == "patch")
                return "PATCH";
            elseif ($tmpMethod === "delete")
                return "DELETE";
        }
        if (isset($_SERVER['REQUEST_METHOD']))
            return $_SERVER['REQUEST_METHOD'];
        return "GET";
    }

    /**
     * Является ли текущий метод запроса методом HTTP-GET?
     * @return bool
     */
    static public function isCurrentRouteMethodGET(): bool {
        return (strcmp(RouteCollector::currentRouteMethod(), "GET") === 0);
    }

    /**
     * Получить массив входных аргументов для текущего запроса (включая файлы)
     * @return array
     */
    static public function currentRouteArgs(): array {
        if (!isset($_SERVER['REQUEST_METHOD']))
            return array();

        $tmpArgs = array();
        $tmpMethod = strtolower($_SERVER['REQUEST_METHOD']);
        if ($tmpMethod === 'get') {
            $tmpArgs = $_GET;
        } elseif ($tmpMethod === 'post'
                  || $tmpMethod === 'put'
                  || $tmpMethod === 'patch'
                  || $tmpMethod === 'delete') {
            if (empty($_POST)) {
                $inData = RouteStreamParser::parseInputData();
                $_POST = $inData['args'];
                if (empty($_FILES))
                    $_FILES = $inData['files'];
                else
                    $_FILES = array_merge($_FILES, $inData['files']);
            }
            $tmpArgs = $_POST;
            $tmpFiles = RouteCollector::currentRouteFiles();
            if (!empty($tmpFiles))
                $tmpArgs["file"] = $tmpFiles;
        }
        return $tmpArgs;
    }

    /**
     * Получить массив входных файлов для текущего запроса
     * @return array
     */
    static public function currentRouteFiles(): array {
        $buffKeys = array_keys($_FILES);
        $tmpFiles = [];
        foreach ($buffKeys as $key) {
            if (empty($_FILES[$key]['tmp_name']))
                continue;
            if (is_string($_FILES[$key]['tmp_name'])) {
                $tmpFiles[$key] = $_FILES[$key];
            } else if (is_array($_FILES[$key]['tmp_name'])) {
                for ($i = 0; $i < count($_FILES[$key]['tmp_name']); $i++) {
                    $fName = $_FILES[$key]['name'][$i];
                    $fType = $_FILES[$key]['type'][$i];
                    $fTmpName = $_FILES[$key]['tmp_name'][$i];
                    $fError = $_FILES[$key]['error'][$i];
                    $fSize = $_FILES[$key]['size'][$i];
                    $tmpKey = "$key" . "[" . $fName . "]";
                    $tmpFiles[$tmpKey] = [
                        'name' => $fName,
                        'type' => $fType,
                        'tmp_name' => $fTmpName,
                        'error' => $fError,
                        'size' => $fSize
                    ];
                }
            }
        }
        return $tmpFiles;
    }

    /**
     * Получить массив HTTP заголовоков текущего запроса
     * @param bool $namesToLower - преобразовывать имена заголовков в нижний регистр
     * @return array
     */
    static public function currentRouteHeaders(bool $namesToLower = false): array {
        $headers = array();
        foreach ($_SERVER as $key => $value) {
            if (strcmp(substr($key, 0, 5),'HTTP_') !== 0)
                continue;
            $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
            if ($namesToLower === true)
                $header = strtolower($header);
            $headers[$header] = $value;
        }
        return $headers;
    }

    /**
     * Получить значение HTTP заголовка текущего запроса
     * @param string $name
     * @return mixed|null
     */
    static public function currentRouteHeader(string $name) {
        $name = strtolower($name);
        $headers = RouteCollector::currentRouteHeaders(true);
        if (isset($headers[$name]))
            return $headers[$name];

        return null;
    }

    /**
     * Получить валидный url-prefix
     * @param string $uri - строка URL
     * @return string
     *
     * echo makeValidUrlPrefix("/app1/");
     * => "/app1"
     */
    static public function makeValidUrlPrefix(string $uri): string {
        if (empty($uri))
            return "/";
        if ($uri[strlen($uri) - 1] == "/") {
            if (strlen($uri) > 1) {
                $uri = ltrim($uri, "/");
                $uri = RouteCollector::makeValidUrlPrefix($uri);
            } else {
                $uri = "/";
            }
        }
        return $uri;
    }

    /**
     * Получить валидный путь для маршрута к контроллеру
     * @param string $uri
     * @return string
     */
    static public function makeValidRouteUrl(string $uri): string {
        if (empty($uri))
            return $uri;
        $uri = RouteCollector::spliceUrlFirst($uri);
        $uri = RouteCollector::spliceUrlLast($uri);
        if (empty($uri))
            return $uri;
        return "/".$uri;
    }

    /**
     * Получить валидную строку адреса (с App-Url-Prefix, если задан)
     * @param string $uri - строка URL
     * @return string
     *
     * echo makeValidUrl("/api/test_api");
     *   if url-prefix not set => "/api/test_api"
     *   if url-prefix set ("/app1") => "/app1/api/test_api"
     */
    static public function makeValidUrl(string $uri): string {
        $appPrefix = \FlyCubePHP\configValue(Config::TAG_APP_URL_PREFIX);
        $appPrefix = RouteCollector::makeValidUrlPrefix($appPrefix);
        if (!empty($appPrefix) && strcmp($appPrefix, "/") !== 0) {
            $uri = RouteCollector::spliceUrlFirst($uri);
            $uri = $appPrefix . "/" . $uri;
        }
        return $uri;
    }

    /**
     * Обрезать url строку вначале (исключить '/')
     * @param string $uri - строка URL
     * @return string
     *
     * echo spliceUrlFirst("/app1/");
     *   => "app1/"
     */
    static public function spliceUrlFirst(string $uri): string {
        if (empty($uri))
            return $uri;
        if (strcmp($uri[0], "/") === 0) {
            if (strlen($uri) > 1) {
                $uri = ltrim($uri, "/");
                $uri = RouteCollector::spliceUrlFirst($uri);
            } else {
                $uri = "";
            }
        }
        return $uri;
    }

    /**
     * Обрезать url строку вконце (исключить '/')
     * @param string $uri - строка URL
     * @return string
     *
     * echo spliceUrlLast("/app1/");
     *   => "/app1"
     */
    static public function spliceUrlLast(string $uri): string {
        if (empty($uri))
            return $uri;
        if (strcmp($uri[strlen($uri) - 1], "/") === 0) {
            if (strlen($uri) > 1) {
                $uri = substr($uri, 0, -1);
                $uri = RouteCollector::spliceUrlLast($uri);
            } else {
                $uri = "";
            }
        }
        return $uri;
    }
}