<?php

namespace FlyCubePHP\Core\ApiDoc\Helpers;

use FlyCubePHP\Core\Routes\Route;
use FlyCubePHP\Core\Routes\RouteCollector;

class ApiDocUrlHelper
{
    private $_currentRoute;

    function __construct(Route $currentRoute)
    {
        $this->_currentRoute = $currentRoute;
    }

    public function current_action_url(): string
    {
        $hostUrl = RouteCollector::currentHostUri();
        return $hostUrl . $this->_currentRoute->uri();
    }

    public function action_url(string $controller, string $act): string
    {
        $hostUrl = RouteCollector::currentHostUri();
        $route = RouteCollector::instance()->routeByControllerAct($controller, $act);
        if (is_null($route))
            trigger_error("Not found route by controller action ($controller::$act)!");

        return $hostUrl . $route->uri();
    }
}