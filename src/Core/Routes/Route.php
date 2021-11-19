<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 21.07.21
 * Time: 15:04
 */

namespace FlyCubePHP\Core\Routes;

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

class Route
{
    private $_type;
    private $_uri;
    private $_uriArgs = [];
    private $_controller;
    private $_action;

    function __construct(int $type,
                         string $uri,
                         array $uriArgs,
                         string $controller,
                         string $action) {
        $this->_type = $type;
        $this->_uri = $uri;
        if (count(explode('?', $this->_uri)) > 1)
            $this->parseArgs();
        $this->_uriArgs = array_merge($this->_uriArgs, $uriArgs);
        $this->_controller = $controller;
        $this->_action = $action;
    }

    public function type(): int {
        return $this->_type;
    }

    public function uri(): string {
        $tmpURILst = explode('?', $this->_uri);
        $tmpURI = RouteCollector::spliceUrlLast($tmpURILst[0]);
        if (empty($tmpURI))
            $tmpURI = "/";
        return $tmpURI;
    }

    public function uriFull(): string {
        return $this->_uri;
    }

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

    public function hasUriArgs(): bool {
        return (!empty($this->_uriArgs) || preg_match('/\:([a-zA-Z0-9_]*)/i', $this->uri()));
    }

    public function uriArgs(): array {
        return $this->_uriArgs;
    }

    public function controller(): string {
        return $this->_controller;
    }

    public function action(): string {
        return $this->_action;
    }

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