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
use \FlyCubePHP\Core\Controllers\BaseActionController as BaseActionController;
use FlyCubePHP\Core\Logger\Logger;

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
        // --- init args ---
        self::currentRouteArgsPr();
        // --- select files ---
        $files = self::currentRouteFiles();
        foreach ($files as $fInfo) {
            if (isset($fInfo['tmp_name'])
                && !empty($fInfo['tmp_name'])
                && is_file($fInfo['tmp_name'])) {
                unlink($fInfo['tmp_name']);
            } else if (is_array($fInfo)) {
                foreach ($fInfo as $info) {
                    if (isset($info['tmp_name'])
                        && !empty($info['tmp_name'])
                        && is_file($info['tmp_name']))
                        unlink($info['tmp_name']);
                }
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
            if ($route->type() == RouteType::stringToInt($method)
                && $route->isRouteMatch($uri) === true)
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
            if ($route->type() == RouteType::stringToInt($method)
                && $route->isRouteMatch($uri) === true
                /*&& $route->uri() == $uri*/)
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
     * Является ли текущий маршрут корневым?
     * @return bool
     */
    public function currentRouteIsRoot(): bool {
        $route = $this->currentRoute();
        if (is_null($route))
            return false;
        return ($route->type() == RouteType::GET && $route->uri() == "/");
    }

    /**
     * Метод проверки маршрутов на корректность (верно ли заданы контроллеры и их методы)
     * @return bool
     * @throws ErrorRoutes
     * @throws \FlyCubePHP\Core\Error\Error
     *
     * Если найден некорректный маршрут, то он удаляется из списка!
     * Если найден маршрут, ссылающийся на вспомогательный метод класса (helper method), то выбрасывается исключение!
     *
     * Return true if route list is not empty, else - return false.
     */
    public function checkRoutes(): bool {
        $tmpRemove = array();
        foreach ($this->_routes as $key => $value) {
            $tmpClassName = $value->controller();
            $tmpClassAct = $value->action();
            if (!class_exists($tmpClassName, false)) {
                $tmpRemove[$key] = [
                    'route' => $value,
                    'msg' => 'Not found controller class!'
                ];
                continue;
            }
            $tmpController = new $tmpClassName();
            if (!$tmpController instanceof BaseController)
                $tmpRemove[$key] = [
                    'route' => $value,
                    'msg' => 'Controller class is not instance of BaseController!'
                ];
            elseif (!method_exists($tmpController, $tmpClassAct))
                $tmpRemove[$key] = [
                    'route' => $value,
                    'msg' => 'Controller class does not contain required action!'
                ];
            elseif ($tmpController instanceof BaseActionController
                    && $tmpController->isHelperMethod($tmpClassAct))
                $tmpRemove[$key] = [
                    'route' => $value,
                    'msg' => 'Controller class action is a helper method!'
                ];

            // --- make route 'as' define ---
            $tmpAs = $value->routeAs();
            define($tmpAs."_url", $value->uri());

            // --- clear ---
            unset($tmpController);
        }
        foreach ($tmpRemove as $key => $value) {
            $route = $value['route'];
            $msg = $value['msg'];
            if (Config::instance()->isProduction())
                Logger::warning("Route '". $route->uri() ."' removed! $msg");
            else
                throw ErrorRoutes::makeError([
                    'tag' => 'check-routes',
                    'message' => "Invalid route '".$route->uri()."'! $msg",
                    'url' => $route->uri(),
                    'route-type' => $route->type(),
                    'controller' => $route->controller(),
                    'action' => $route->action()
                ]);

            unset($this->_routes[$key]);
        }
        return count($this->_routes) > 0;
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
     * Получить текущий протокол хоста
     * @return string
     */
    static public function currentHostProtocol(): string {
        $protocol = "http";
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            $protocol = "https";

        return $protocol;
    }

    /**
     * Получить текущий URL хоста
     * @return string
     */
    static public function currentHostUri(): string {
        $protocol = self::currentHostProtocol();
        return "$protocol://$_SERVER[HTTP_HOST]";
    }

    /**
     * Получить информацию о USER AGENT текущего клиента
     * @return string
     */
    static public function currentClientUserAgent(): string {
        return $_SERVER['HTTP_USER_AGENT'];
    }

    /**
     * @brief Получить IP текущего клиента
     * @return string
     */
    static public function currentClientIP(): string {
        if (!empty($_SERVER['HTTP_CLIENT_IP']))
            return $_SERVER['HTTP_CLIENT_IP'];
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
            return $_SERVER['HTTP_X_FORWARDED_FOR'];

        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Получить PORT текущего клиента
     * @return int
     */
    static public function currentClientPort(): int {
        return intval($_SERVER['REMOTE_PORT']);
    }

    /**
     * Получить информацию о браузере текущего клиента
     * @return array
     *
     * Array keys:
     * - [string] userAgent - current client user agent string
     * - [string] name      - browser name
     * - [string] platform  - browser platform
     * - [string] version   - browser version (maybe empty)
     * - [bool]   bot       - is it a bot
     */
    static public function currentClientBrowser(): array
    {
        $userAgent = self::currentClientUserAgent();
        $bName = 'Unknown';
        $bPlatform = self::browserPlatform($userAgent);
        $bVersion= "";
        $isBot = false;

        // Follow up to Francesco R's post from 2016.

        // Make case insensitive.
        $t = strtolower($userAgent);

        // If the string *starts* with the string, strpos returns 0 (i.e., FALSE). Do a ghetto hack and start with a space.
        // "[strpos()] may return Boolean FALSE, but may also return a non-Boolean value which evaluates to FALSE."
        //     http://php.net/manual/en/function.strpos.php
        $t = " " . $t;

        // Humans / Regular Users
        if (strpos($t, 'opera') || strpos($t, 'opr/')) {
            $bName = 'Opera';
            $bVersion = self::browserVersion($userAgent, 'Opera');
        } elseif (strpos($t, 'edge')) {
            $bName = 'Edge';
            $bVersion = self::browserVersion($userAgent, 'Edge');
        } elseif (strpos($t, 'chrome')) {
            $bName = 'Chrome';
            $bVersion = self::browserVersion($userAgent, 'Chrome');
        } elseif (strpos($t, 'safari')) {
            $bName = 'Safari';
            $bVersion = self::browserVersion($userAgent, 'Safari');
        } elseif (strpos($t, 'firefox')) {
            $bName = 'Firefox';
            $bVersion = self::browserVersion($userAgent, 'Firefox');
        } elseif (strpos($t, 'msie') || strpos($t, 'trident/7')) {
            $bName = 'Internet Explorer';
            if (strpos($t, 'trident/7'))
                $bVersion = self::browserVersion($userAgent, 'rv');
            else
                $bVersion = self::browserVersion($userAgent, 'MSIE');
        }

        // Search Engines
        elseif (strpos($t, 'google')) {
            $bName = 'Googlebot';
            $isBot = true;
        } elseif (strpos($t, 'bing')) {
            $bName = 'Bingbot';
            $isBot = true;
        } elseif (strpos($t, 'slurp')) {
            $bName = 'Yahoo! Slurp';
            $isBot = true;
        } elseif (strpos($t, 'duckduckgo')) {
            $bName = 'DuckDuckBot';
            $isBot = true;
        } elseif (strpos($t, 'baidu')) {
            $bName = 'Baidu';
            $isBot = true;
        } elseif (strpos($t, 'yandex')) {
            $bName = 'Yandex';
            $isBot = true;
        } elseif (strpos($t, 'sogou')) {
            $bName = 'Sogou';
            $isBot = true;
        } elseif (strpos($t, 'exabot')) {
            $bName = 'Exabot';
            $isBot = true;
        } elseif (strpos($t, 'msn')) {
            $bName = 'MSN';
            $isBot = true;
        }

        // Common Tools and Bots
        elseif (strpos($t, 'mj12bot')) {
            $bName = 'Majestic';
            $isBot = true;
        } elseif (strpos($t, 'ahrefs')) {
            $bName = 'Ahrefs';
            $isBot = true;
        } elseif (strpos($t, 'semrush')) {
            $bName = 'SEMRush';
            $isBot = true;
        } elseif (strpos($t, 'rogerbot') || strpos($t, 'dotbot')) {
            $bName = 'Moz or OpenSiteExplorer';
            $isBot = true;
        } elseif (strpos($t, 'frog') || strpos($t, 'screaming')) {
            $bName = 'Screaming Frog';
            $isBot = true;
        }

        // Miscellaneous
        elseif (strpos($t, 'facebook')) {
            $bName = 'Facebook';
            $isBot = true;
        } elseif (strpos($t, 'pinterest')) {
            $bName = 'Pinterest';
            $isBot = true;
        }

        // Check for strings commonly used in bot user agents
        elseif (strpos($t, 'crawler') || strpos($t, 'api')
            || strpos($t, 'spider') || strpos($t, 'http')
            || strpos($t, 'bot') || strpos($t, 'archive')
            || strpos($t, 'info') || strpos($t, 'data')) {
            $bName = 'Other';
            $isBot = true;
        }

        return [
            'userAgent' => $userAgent,
            'name' => $bName,
            'platform' => $bPlatform,
            'version' => $bVersion,
            'bot' => $isBot
        ];
    }

    /**
     * Получить текущий URL
     * @return string
     */
    static public function currentUri(): string {
        $protocol = "http";
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            $protocol = "https";
        if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['REQUEST_URI']))
            return "$protocol://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        
        return "$protocol://";
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
        $tmpArgs = self::currentRouteArgsPr();
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
     * Получить входной аргумент для текущего запроса
     * @param string $key
     * @param null $def
     * @return mixed|null
     */
    static public function currentRouteArg(string $key, $def = null) {
        if (!isset($_SERVER['REQUEST_METHOD']))
            return $def;
        $tmpArgs = RouteCollector::currentRouteArgs();
        if (isset($tmpArgs[$key]))
            return $tmpArgs[$key];
        return $def;
    }

    /**
     * Получить массив входных аргументов для текущего запроса (включая файлы)
     * @return array
     */
    static public function currentRouteArgs(): array {
        return self::currentRouteArgsPr(true);
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
                $tmpFiles[$key] = [];
                for ($i = 0; $i < count($_FILES[$key]['tmp_name']); $i++) {
                    $fName = $_FILES[$key]['name'][$i];
                    $fType = $_FILES[$key]['type'][$i];
                    $fTmpName = $_FILES[$key]['tmp_name'][$i];
                    $fError = $_FILES[$key]['error'][$i];
                    $fSize = $_FILES[$key]['size'][$i];
                    $tmpKey = "$key" . "[" . $fName . "]";
                    $tmpFiles[$key][$tmpKey] = [
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

    /**
     * Получить платформу браузера (на которой запущен)
     * @param string $userAgent
     * @return string
     */
    static private function browserPlatform(string $userAgent): string
    {
        if (preg_match('/linux/i', $userAgent))
            return 'linux';
        elseif (preg_match('/macintosh|mac os x/i', $userAgent))
            return 'mac';
        elseif (preg_match('/windows|win32/i', $userAgent))
            return 'windows';

        return 'Unknown';
    }

    /**
     * Получить версию браузера
     * @param string $userAgent
     * @param string $vPrefix префикс поиска версии
     * @return string
     */
    static private function browserVersion(string $userAgent, string $vPrefix): string
    {
        // Get the correct version number
        // Added "|:"
        $known = array('Version', $vPrefix, 'other');
        $pattern = '#(?<browser>' . join('|', $known) . ')[/|: ]+(?<version>[0-9.|a-zA-Z.]*)#';
        if (!preg_match_all($pattern, $userAgent, $matches)) {
            // we have no matching number just continue
            return "";
        }

        // see how many we have
        $i = count($matches['browser']);
        if ($i != 1) {
            // we will have two since we are not using 'other' argument yet
            // see if version is before or after the name
            if (strripos($userAgent, "Version") < strripos($userAgent, $vPrefix))
                $version = $matches['version'][0];
            else
                $version = $matches['version'][1];
        } else {
            $version = $matches['version'][0];
        }

        // check if we have a number
        if ($version == null || $version == "")
            $version = "?";

        return $version;
    }

    /**
     * Получить массив входных аргументов для текущего запроса (включая файлы)
     * @param bool $full - Дополнять ли аргументами маршрута
     * @return array
     * @private
     */
    static private function currentRouteArgsPr(bool $full = false): array {
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
                $tmpArgs = array_merge($tmpFiles, $tmpArgs);
        }
        if (!$full)
            return $tmpArgs;

        // --- check & append other route args
        $route = RouteCollector::instance()->currentRoute();
        if (!is_null($route) && $route->hasUriArgs() === true) {
            $tmpArgs = array_merge($tmpArgs, $route->uriArgs());
            $tmpArgs = array_merge($tmpArgs, $route->routeArgsFromUri(self::currentRouteUri()));
        }
        return $tmpArgs;
    }
}