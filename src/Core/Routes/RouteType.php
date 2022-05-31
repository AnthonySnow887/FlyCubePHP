<?php

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
        if (strcmp($tmpVal, "get") === 0 || strcmp($tmpVal, "head") === 0)
            return RouteType::GET;
        elseif (strcmp($tmpVal, "post") === 0)
            return RouteType::POST;
        elseif (strcmp($tmpVal, "put") === 0)
            return RouteType::PUT;
        elseif (strcmp($tmpVal, "patch") === 0)
            return RouteType::PATCH;
        elseif (strcmp($tmpVal, "delete") === 0)
            return RouteType::DELETE;
        return -1;
    }
}
