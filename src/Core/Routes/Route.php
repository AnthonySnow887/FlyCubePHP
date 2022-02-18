<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 21.07.21
 * Time: 15:04
 */

namespace FlyCubePHP\Core\Routes;

use FlyCubePHP\HelperClasses\CoreHelper;

include_once __DIR__.'/../../HelperClasses/Enum.php';

class RouteType extends \FlyCubePHP\HelperClasses\Enum {
    const GET       = 0;
    const POST      = 1;
    const PUT       = 2;
    const PATCH     = 3;
    const DELETE    = 4;

    static public function intToString(int $val): string {
        switch ($val) {
            case RouteType::GET:
                return "GET";
            case RouteType::POST:
                return "POST";
            case RouteType::PUT:
                return "PUT";
            case RouteType::PATCH:
                return "PATCH";
            case RouteType::DELETE:
                return "DELETE";
            default:
                break;
        }
        return "???";
    }

    static public function stringToInt(string $val): int {
        if (empty($val))
            return -1;
        $tmpVal = strtolower($val);
        if ($tmpVal == "get")
            return RouteType::GET;
        elseif ($tmpVal == "post")
            return RouteType::POST;
        elseif ($tmpVal == "put")
            return RouteType::PUT;
        elseif ($tmpVal == "patch")
            return RouteType::PATCH;
        elseif ($tmpVal == "delete")
            return RouteType::DELETE;
        return -1;
    }
}

/**
 * Класс маршрута
 */
class Route
{
    private $_type;         /**< тип маршрута (get/post/put/patch/delete) */
    private $_uri;          /**< url маршрута */
    private $_uriArgs = []; /**< статические аргументы маршрута */
    private $_controller;   /**< название класса контроллера */
    private $_action;       /**< название метода контроллера */
    private $_as;           /**< псевдоним для быстрого доступа к маршруту */

    function __construct(int $type,
                         string $uri,
                         array $uriArgs,
                         string $controller,
                         string $action,
                         string $as = "") {
        $this->_type = $type;
        $this->_uri = $uri;
        if (count(explode('?', $this->_uri)) > 1)
            $this->parseArgs();
        $this->_uriArgs = array_merge($this->_uriArgs, $uriArgs);
        $this->_controller = $controller;
        $this->_action = $action;
        if (empty($as)) {
            $tmpUrl = str_replace('/', ' ', $this->uri());
            $tmpUrl = str_replace(':', ' ', $tmpUrl);
            $tmpUrl = strtolower(RouteType::intToString($type)) . " $tmpUrl";
            $as = CoreHelper::underscore(CoreHelper::camelcase($tmpUrl));
        }
        $this->_as = $as;
    }

    /**
     * Тип маршрута
     * @return int
     */
    public function type(): int {
        return $this->_type;
    }

    /**
     * URL маршрута без аргументов
     * @return string
     */
    public function uri(): string {
        $tmpURILst = explode('?', $this->_uri);
        $tmpURI = RouteCollector::spliceUrlLast($tmpURILst[0]);
        if (empty($tmpURI))
            $tmpURI = "/";
        return $tmpURI;
    }

    /**
     * Полный URL маршрута
     * @return string
     */
    public function uriFull(): string {
        return $this->_uri;
    }

    /**
     * Сравнение маршрута с локальной копией
     * @param string $uri - URL маршрута для сравнения
     * @return bool
     */
    public function isRouteMatch(string $uri): bool {
        $localUri = $this->uri();
        if (strcmp($localUri, $uri) === 0)
            return true;
        if (!preg_match('/\:([a-zA-Z0-9_]*)/i', $localUri))
            return false;
        // --- check ---
        $localUriLst = explode('/', $localUri);
        $uriLst = explode('/', $uri);
        if (count($localUriLst) != count($uriLst))
            return false;
        for ($i = 0; $i < count($localUriLst); $i++) {
            $localPath = $localUriLst[$i];
            $uriPath = $uriLst[$i];
            if (empty($localPath) && empty($uriPath))
                continue;
            if (strcmp($localPath[0], ':') === 0)
                continue; // skip
            if (strcmp($localPath, $uriPath) !== 0)
                return false;
        }
        return true;
    }

    /**
     * Разобрать аргументы маршрута, если он задан в формате "/ROUTE/:id"
     * @param string $uri
     * @return array
     */
    public function routeArgsFromUri(string $uri): array {
        $localUri = $this->uri();
        if (!preg_match('/\:([a-zA-Z0-9_]*)/i', $localUri))
            return [];
        // --- select ---
        $tmpArgs = [];
        $localUriLst = explode('/', $localUri);
        $uriLst = explode('/', $uri);
        if (count($localUriLst) != count($uriLst))
            return [];
        for ($i = 0; $i < count($localUriLst); $i++) {
            $localPath = $localUriLst[$i];
            $uriPath = $uriLst[$i];
            if (empty($localPath) && empty($uriPath))
                continue;
            if (strcmp($localPath[0], ':') === 0 && strlen($localPath) > 1)
                $tmpArgs[ltrim($localPath, ":")] = $uriPath;
        }
        return $tmpArgs;
    }

    /**
     * Есть ли у маршрута входные аргументы?
     * @return bool
     */
    public function hasUriArgs(): bool {
        return (!empty($this->_uriArgs) || preg_match('/\:([a-zA-Z0-9_]*)/i', $this->uri()));
    }

    /**
     * Статические аргументы маршрута
     * @return array
     */
    public function uriArgs(): array {
        return $this->_uriArgs;
    }

    /**
     * Название контроллера
     * @return string
     */
    public function controller(): string {
        return $this->_controller;
    }

    /**
     * Название метода контроллера
     * @return string
     */
    public function action(): string {
        return $this->_action;
    }

    /**
     * Псевдоним для быстрого доступа к маршруту
     * @return string
     */
    public function routeAs(): string {
        return $this->_as;
    }

    /**
     * Метод разбора аргументов
     */
    private function parseArgs() {
        // NOTE! Не использовать parse_str($postData, $postArray),
        //       т.к. данный метод портит Base64 строки!
        $tmpURILst = explode('?', $this->_uri);
        if (count($tmpURILst) != 2)
            return;
        $requestData = urldecode($tmpURILst[1]);
        if (empty($requestData))
            return;
        $requestKeyValueArray = explode('&', $requestData);
        foreach ($requestKeyValueArray as $keyValue) {
            $keyValueArray = explode('=', $keyValue);
            if (count($keyValueArray) < 2) {
                $this->_uriArgs[] = $keyValue;
            } else {
                $keyData = $keyValueArray[0];
                $valueData = str_replace($keyData . "=", "", $keyValue);
                if (preg_match('/(.*?)\[(.*?)\]/i', $keyData, $tmp)) {
                    if (empty($tmp)) {
                        $this->_uriArgs[$keyData] = $valueData;
                    } else {
                        if (!isset($this->_uriArgs[$tmp[1]]))
                            $this->_uriArgs[$tmp[1]] = [];
                        $this->_uriArgs[$tmp[1]][$tmp[2]] = $valueData;
                    }
                } else {
                    $this->_uriArgs[$keyData] = $valueData;
                }
            }
        }
    }
}