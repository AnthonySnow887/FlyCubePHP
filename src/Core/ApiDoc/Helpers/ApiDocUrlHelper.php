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

    /**
     * Get current controller action URL.
     * @return string
     *
     * ==== Examples in Api-Doc notations
     *
     *   {{ current_action_url() }}
     *   * => http://127.0.0.1:8080/my-project/login
     */
    public function current_action_url(): string
    {
        $appUrl = RouteCollector::applicationUri();
        return $appUrl . $this->_currentRoute->uri();
    }

    /**
     * Get URL by controller and action.
     * @param string $controller
     * @param string $act
     * @return string
     *
     * ==== Examples in Api-Doc notations
     *
     *   {{ action_url("SessionsController", "new") }}
     *   * => http://127.0.0.1:8080/my-project/login
     */
    public function action_url(string $controller, string $act): string
    {
        $appUrl = RouteCollector::applicationUri();
        $route = RouteCollector::instance()->routeByControllerAct($controller, $act);
        if (is_null($route))
            trigger_error("Not found route by controller action ($controller::$act)!");

        return $appUrl . $route->uri();
    }
}