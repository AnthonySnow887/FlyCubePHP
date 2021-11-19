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
    private $_controller;
    private $_action;

    function __construct(int $type,
                         string $uri,
                         string $controller,
                         string $action) {
        $this->_type = $type;
        $this->_uri = $uri;
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

    public function controller(): string {
        return $this->_controller;
    }

    public function action(): string {
        return $this->_action;
    }
}