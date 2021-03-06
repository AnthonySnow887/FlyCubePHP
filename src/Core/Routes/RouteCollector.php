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
use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\Core\Controllers\BaseController;
use FlyCubePHP\Core\Controllers\BaseActionController;
use FlyCubePHP\Core\Logger\Logger;
use FlyCubePHP\HelperClasses\CoreHelper;

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
     * ???????????????? ???????????? ????????????????
     * @param Route $route
     */
    public function appendRoute(Route &$route) {
        $tmpName = RouteType::intToString($route->type()) . "_" . $route->uri();
        if (array_key_exists($tmpName, $this->_routes)) {
            unset($route);
            return;
        }
        $this->_routes[$tmpName] = $route;

        // --- make route 'as' define ---
        $tmpAs = $route->routeAs();
        define($tmpAs."_url", $route->uri());
    }

    /**
     * ???????????????? ?????????????? ????????????????
     * @param string $method - HTTP ??????????
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
     * ???????????????? ?????????????? ?????????????????? ????????????????
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
     * ???????????????? ???????????? ????????????????
     * @param string $method - HTTP ??????????
     * @param string $uri - URL
     * @return Route|null
     */
    public function routeByMethodUri(string $method, string $uri)/*: Route|null*/ {
        if (empty($method) || empty($uri))
            return null;
        foreach ($this->_routes as $route) {
            if ($route->type() == RouteType::stringToInt($method)
                && $route->isRouteMatch($uri) === true)
                return $route;
        }
        return null;
    }

    /**
     * ???????????????? ???????????? ???????????????? ???? ???????????????? ?????????????????????? ?? ?????? ????????????
     * @param string $controller - ????????????????????
     * @param string $action - ?????? ??????????
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
     * ???????????????? ???????????? ?????????????????? ????????????????
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
     * ???????????????? ?????? ????????????????
     * @param bool $sort - ?????????????????? ???? ???????????????????? ???? URL
     * @return array
     */
    public function allRoutes(bool $sort = false): array {
        if (!$sort)
            return $this->_routes;
        $tmpArray = $this->_routes;
        usort($tmpArray, [ $this, 'compareRoutes' ]);
        return $tmpArray;
    }

    /**
     * ???????????????? ???????????? ???????????????? ?????? ???????????????? ??????????????
     * @return Route|null
     */
    public function currentRoute()/*: Route|null*/ {
        $tmpURI = RouteCollector::currentRouteUri();
        $tmpMethod = RouteCollector::currentRouteMethod();
        return $this->routeByMethodUri($tmpMethod, $tmpURI);
    }

    /**
     * ???????????????? ???? ?????????????? ?????????????? ?????????????????
     * @return bool
     */
    public function currentRouteIsRoot(): bool {
        $route = $this->currentRoute();
        if (is_null($route))
            return false;
        return ($route->type() == RouteType::GET && $route->uri() == "/");
    }

    /**
     * ?????????????????? ?????????????????? ?????????????? ?? ?????????????????? ????????????????
     * @throws \FlyCubePHP\Core\Error\Error
     * @private
     *
     * NOTE: This function is only for use inside the system kernel!
     */
    public function processingRequest() {
        // --- check caller function ---
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $dbt[1]['function'] ?? null;
        if (is_null($caller))
            trigger_error("Not found caller function!", E_USER_ERROR);
        if (strcmp($caller, "FlyCubePHP\\requestProcessing") !== 0)
            trigger_error("Invalid caller function!", E_USER_ERROR);

        // --- log request ---
        $httpM = static::currentRouteMethod();
        $httpUrl = static::currentRouteUri();
        $httpArgs = static::currentRouteArgs();
        $clientIP = static::currentClientIP();
        Logger::info("$httpM $httpUrl (from: $clientIP)");
        if (empty($httpArgs))
            Logger::info("PARAMS: {}");
        else
            Logger::info("PARAMS:", $httpArgs);

        // --- check current route ---
        $tmpCurRoute = $this->currentRoute();
        if (is_null($tmpCurRoute))
            $tmpCurRoute = $this->processingFailed("Not found route: [$httpM] $httpUrl", 404);

        // --- check redirect ---
        if ($tmpCurRoute->hasRedirect()) {
            // --- clear all buffers ---
            while (ob_get_level() !== 0)
                ob_end_clean();
            // --- send redirect ---
            $redirectUri = $tmpCurRoute->redirectUri(self::currentRouteUri());
            if (!preg_match("/^(http:\/\/|https:\/\/).*/", $redirectUri)) {
                $redirectUri = self::makeValidUrl($redirectUri);
                $redirectUri = self::currentHostUri() . $redirectUri;
            }
            $redirectStatus = $tmpCurRoute->redirectStatus();
            http_response_code($redirectStatus);
            header("Location: $redirectUri", true, $redirectStatus);
            Logger::info("REDIRECT TO: [status: $redirectStatus] $redirectUri");
            die();
        }

        // --- processing controller ---
        $tmpClassName = $tmpCurRoute->controller();
        $tmpClassAct = $tmpCurRoute->action();
        $tmpController = new $tmpClassName();
        if (!$tmpController instanceof BaseController) {
            $tmpCurRoute = $this->processingFailed("Controller class is not instance of BaseController!", 500);
            unset($tmpController);
            $tmpController = null;
        } elseif (!method_exists($tmpController, $tmpClassAct)) {
            $tmpCurRoute = $this->processingFailed("Controller class does not contain required action!", 500);
            unset($tmpController);
            $tmpController = null;
        } elseif ($tmpController instanceof BaseActionController
                  && $tmpController->isHelperMethod($tmpClassAct)) {
            $tmpCurRoute = $this->processingFailed("Controller class action is a helper method!", 500);
            unset($tmpController);
            $tmpController = null;
        }
        $renderMS = $this->processingRender($tmpCurRoute, $tmpController);
        Logger::info("RENDER: [$renderMS"."ms] $tmpClassName::$tmpClassAct()");
    }

    /**
     * ?????????????????? ?? ?????????????????? ???????????????????? ?? ??????????????
     * @param string $message
     * @param int $httpCode
     * @return Route|void|null
     * @throws \FlyCubePHP\Core\Error\Error
     */
    private function processingFailed(string $message, int $httpCode = 500)/*: Route|null*/ {
        if (Config::instance()->isDevelopment())
            trigger_error($message, E_USER_ERROR);

        Logger::warning($message);
        if (!$this->containsRoute('GET', "/$httpCode")) {
            http_response_code($httpCode);
            die();
        }
        $tmpCurRoute = $this->routeByMethodUri('GET', "/$httpCode");
        if (!isset($_GET['code']))
            $_GET['code'] = $httpCode;

        return $tmpCurRoute;
    }

    /**
     * ?????????????????? ?????????????????????? ?? ?????? ????????????
     * @param $route
     * @param $controller
     * @return float
     */
    private function processingRender(/*Route|null*/ $route, /*BaseController|null*/ $controller): float {
        if (is_null($route))
            trigger_error("Route object is NULL!", E_USER_ERROR);

        $tmpClassName = $route->controller();
        $tmpClassAct = $route->action();
        if (is_null($controller))
            $tmpController = new $tmpClassName();
        else
            $tmpController = $controller;

        $renderStartMS = microtime(true);
        try {
            $tmpController->renderPrivate($tmpClassAct);
        } catch (\Throwable $ex) {
            $tmpController->evalException($ex);
        }
        $renderMS = round(microtime(true) - $renderStartMS, 3);
        unset($tmpController);
        return $renderMS;
    }

    /**
     * ???????????????? URL ?????????????? ?????? ????????????????????
     * @return string
     */
    static public function applicationUrlPrefix(): string {
        $appPrefix = \FlyCubePHP\configValue(Config::TAG_APP_URL_PREFIX, "");
        return RouteCollector::makeValidUrlPrefix($appPrefix);
    }

    /**
     * ???????????????? URL ???????????????????? (currentHostUri + applicationUrlPrefix)
     * @return string
     */
    static public function applicationUri(): string
    {
        $hostUri = RouteCollector::currentHostUri();
        $appPrefix = RouteCollector::applicationUrlPrefix();
        if (!empty($appPrefix) && $appPrefix !== "/")
            return $hostUri . $appPrefix;
        return $hostUri;
    }

    /**
     * ???????????????? ?????????????? ???????????????? ??????????
     * @return string
     */
    static public function currentHostProtocol(): string {
        $protocol = "http";
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            $protocol = "https";

        return $protocol;
    }

    /**
     * ???????????????? ?????????????? URL ??????????
     * @return string
     */
    static public function currentHostUri(): string {
        $protocol = self::currentHostProtocol();
        return "$protocol://$_SERVER[HTTP_HOST]";
    }

    /**
     * ???????????????? ?????????? ???????????????? ??????????????
     * @return string
     */
    static public function serverHost(): string {
        $tmpHost = explode(':', $_SERVER['HTTP_HOST']);
        if (!empty($tmpHost))
            return $tmpHost[0];
        return "";
    }

    /**
     * ???????????????? ???????? ???????????????? ??????????????
     * @return int
     */
    static public function serverPort(): int {
        $tmpHost = explode(':', $_SERVER['HTTP_HOST']);
        if (count($tmpHost) >= 2) {
            return intval($tmpHost[1]);
        } else if (strcmp(self::currentHostProtocol(), 'http') === 0) {
            return 80;
        } else if (strcmp(self::currentHostProtocol(), 'https') === 0) {
            return 443;
        }
        return -1;
    }

    /**
     * ???????????????? ???????????????????? ?? USER AGENT ???????????????? ??????????????
     * @return string
     */
    static public function currentClientUserAgent(): string {
        return $_SERVER['HTTP_USER_AGENT'];
    }

    /**
     * @brief ???????????????? IP ???????????????? ??????????????
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
     * ???????????????? PORT ???????????????? ??????????????
     * @return int
     */
    static public function currentClientPort(): int {
        return intval($_SERVER['REMOTE_PORT']);
    }

    /**
     * ???????????????? ???????????????????? ?? ???????????????? ???????????????? ??????????????
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
     * ???????????????? ?????????????? URL
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
     * ???????????????? ?????????????? URL ????????????????
     * @param bool $withParams - ?????????????? ???? ???????????????? ?????????????????? ?????? ??????
     * @return string
     */
    static public function currentRouteUri(bool $withParams = false): string {
        $tmpURI = $_SERVER['REQUEST_URI'];
        $appPrefix = RouteCollector::applicationUrlPrefix();
        if (!empty($appPrefix) && $appPrefix !== "/") {
            $pos = strpos($tmpURI, $appPrefix);
            if ($pos !== false)
                $tmpURI = substr_replace($tmpURI, "", $pos, strlen($appPrefix));
        }
        if (!$withParams) {
            $tmpURILst = explode('?', $tmpURI);
            $tmpURI = RouteCollector::spliceUrlLast($tmpURILst[0]);
        }
        if (empty($tmpURI))
            $tmpURI = "/";
        return $tmpURI;
    }

    /**
     * ???????????????? ???????????????? http ???????????? ?????? ???????????????? ??????????????
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
     * ???????????????? ???? ?????????????? ?????????? ?????????????? ?????????????? HTTP-GET?
     * @return bool
     */
    static public function isCurrentRouteMethodGET(): bool {
        return (strcmp(RouteCollector::currentRouteMethod(), "GET") === 0);
    }

    /**
     * ???????????????? ?????????????? ???????????????? ?????? ???????????????? ??????????????
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
     * ???????????????? ???????????? ?????????????? ???????????????????? ?????? ???????????????? ?????????????? (?????????????? ??????????)
     * @return array
     */
    static public function currentRouteArgs(): array {
        return self::currentRouteArgsPr(true);
    }

    /**
     * ???????????????? ???????????? ?????????????? ???????????? ?????? ???????????????? ??????????????
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
     * ???????????????? ???????????? HTTP ?????????????????????? ???????????????? ??????????????
     * @param bool $namesToLower - ?????????????????????????????? ?????????? ???????????????????? ?? ???????????? ??????????????
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
     * ???????????????? ???????????????? HTTP ?????????????????? ???????????????? ??????????????
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
     * ???????????????? ???????????????? url-prefix
     * @param string $uri - ???????????? URL
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
     * ???????????????? ???????????????? ???????? ?????? ???????????????? ?? ??????????????????????
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
     * ???????????????? ???????????????? ???????????? ???????????? (?? App-Url-Prefix, ???????? ??????????)
     * @param string $uri - ???????????? URL
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
     * ???????????????? url ???????????? ?????????????? (?????????????????? '/')
     * @param string $uri - ???????????? URL
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
     * ???????????????? url ???????????? ???????????? (?????????????????? '/')
     * @param string $uri - ???????????? URL
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
     * ???????????????? ?????????????????? ???????????????? (???? ?????????????? ??????????????)
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
     * ???????????????? ???????????? ????????????????
     * @param string $userAgent
     * @param string $vPrefix ?????????????? ???????????? ????????????
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
     * ???????????????? ???????????? ?????????????? ???????????????????? ?????? ???????????????? ?????????????? (?????????????? ??????????)
     * @param bool $full - ?????????????????? ???? ?????????????????????? ????????????????
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

    /**
     * ?????????????? ?????????????????? ?????????????????? ???? ???? URL
     * @param Route $left
     * @param Route $right
     * @return int
     */
    static private function compareRoutes(Route $left, Route $right): int {
        return strcasecmp($left->uri(), $right->uri());
    }
}