<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 21.07.21
 * Time: 15:25
 */

namespace FlyCubePHP;

include_once 'RouteCollector.php';

use FlyCubePHP\Core\Error\ErrorRoutes;
use \FlyCubePHP\Core\Routes\Route as Route;
use \FlyCubePHP\Core\Routes\RouteType as RouteType;
use \FlyCubePHP\Core\Routes\RouteCollector as RouteCollector;

/**
 * Задать root url
 * @param string $controller
 * @param string $action
 * @throws
 */
function root(string $controller, string $action) {
    if (empty($controller) || empty($action))
        throw ErrorRoutes::makeError([
            'tag' => 'route',
            'message' => "Empty controller name or action name!",
            'controller' => $controller,
            'action' => $action,
            'route-type' => RouteType::GET,
            'url' => '/'
        ]);
    $tmpController = $controller."Controller";
    $route = new Route(RouteType::GET, "/", [], $tmpController, $action);
    RouteCollector::instance()->appendRoute($route);
}

/**
 * Задать HTTP GET url
 * @param string $uri
 * @param string $controller
 * @param string $action
 * @param array $args - key:value array with additional arguments
 * @throws
 */
function get(string $uri, string $controller, string $action, array $args = []) {
    if (empty($uri) || empty($controller) || empty($action))
        throw ErrorRoutes::makeError([
            'tag' => 'route',
            'message' => "Empty uri, or controller name, or action name!",
            'controller' => $controller,
            'action' => $action,
            'route-type' => RouteType::GET,
            'url' => $uri
        ]);
    $tmpUri = RouteCollector::makeValidRouteUrl($uri);
    if (empty($tmpUri))
        throw ErrorRoutes::makeError([
            'tag' => 'route',
            'message' => "Invalid URI! Use root(...)!",
            'controller' => $controller,
            'action' => $action,
            'route-type' => RouteType::GET,
            'url' => $uri
        ]);
    $tmpController = $controller."Controller";
    $route = new Route(RouteType::GET, $tmpUri, $args, $tmpController, $action);
    RouteCollector::instance()->appendRoute($route);
}

/**
 * Задать HTTP POST url
 * @param string $uri
 * @param string $controller
 * @param string $action
 * @param array $args - key:value array with additional arguments
 * @throws
 */
function post(string $uri, string $controller, string $action, array $args = []) {
    if (empty($uri) || empty($controller) || empty($action))
        throw ErrorRoutes::makeError([
            'tag' => 'route',
            'message' => "Empty uri, or controller name, or action name!",
            'controller' => $controller,
            'action' => $action,
            'route-type' => RouteType::POST,
            'url' => $uri
        ]);
    $tmpUri = RouteCollector::makeValidRouteUrl($uri);
    if (empty($tmpUri))
        throw ErrorRoutes::makeError([
            'tag' => 'route',
            'message' => "Invalid URI!",
            'controller' => $controller,
            'action' => $action,
            'route-type' => RouteType::POST,
            'url' => $uri
        ]);
    $tmpController = $controller."Controller";
    $route = new Route(RouteType::POST, $tmpUri, $args, $tmpController, $action);
    RouteCollector::instance()->appendRoute($route);
}

/**
 * Задать HTTP PUT url
 * @param string $uri
 * @param string $controller
 * @param string $action
 * @param array $args - key:value array with additional arguments
 * @throws
 */
function put(string $uri, string $controller, string $action, array $args = []) {
    if (empty($uri) || empty($controller) || empty($action))
        throw ErrorRoutes::makeError([
            'tag' => 'route',
            'message' => "Empty uri, or controller name, or action name!",
            'controller' => $controller,
            'action' => $action,
            'route-type' => RouteType::PUT,
            'url' => $uri
        ]);
    $tmpUri = RouteCollector::makeValidRouteUrl($uri);
    if (empty($tmpUri))
        throw ErrorRoutes::makeError([
            'tag' => 'route',
            'message' => "Invalid URI!",
            'controller' => $controller,
            'action' => $action,
            'route-type' => RouteType::PUT,
            'url' => $uri
        ]);
    $tmpController = $controller."Controller";
    $route = new Route(RouteType::PUT, $tmpUri, $args, $tmpController, $action);
    RouteCollector::instance()->appendRoute($route);
}

/**
 * Задать HTTP PATCH url
 * @param string $uri
 * @param string $controller
 * @param string $action
 * @param array $args - key:value array with additional arguments
 * @throws
 */
function patch(string $uri, string $controller, string $action, array $args = []) {
    if (empty($uri) || empty($controller) || empty($action))
        throw ErrorRoutes::makeError([
            'tag' => 'route',
            'message' => "Empty uri, or controller name, or action name!",
            'controller' => $controller,
            'action' => $action,
            'route-type' => RouteType::PATCH,
            'url' => $uri
        ]);
    $tmpUri = RouteCollector::makeValidRouteUrl($uri);
    if (empty($tmpUri))
        throw ErrorRoutes::makeError([
            'tag' => 'route',
            'message' => "Invalid URI!",
            'controller' => $controller,
            'action' => $action,
            'route-type' => RouteType::PATCH,
            'url' => $uri
        ]);
    $tmpController = $controller."Controller";
    $route = new Route(RouteType::PATCH, $tmpUri, $args, $tmpController, $action);
    RouteCollector::instance()->appendRoute($route);
}

/**
 * Задать HTTP DELETE url
 * @param string $uri
 * @param string $controller
 * @param string $action
 * @param array $args - key:value array with additional arguments
 * @throws
 */
function delete(string $uri, string $controller, string $action, array $args = []) {
    if (empty($uri) || empty($controller) || empty($action))
        throw ErrorRoutes::makeError([
            'tag' => 'route',
            'message' => "Empty uri, or controller name, or action name!",
            'controller' => $controller,
            'action' => $action,
            'route-type' => RouteType::DELETE,
            'url' => $uri
        ]);
    $tmpUri = RouteCollector::makeValidRouteUrl($uri);
    if (empty($tmpUri))
        throw ErrorRoutes::makeError([
            'tag' => 'route',
            'message' => "Invalid URI!",
            'controller' => $controller,
            'action' => $action,
            'route-type' => RouteType::DELETE,
            'url' => $uri
        ]);
    $tmpController = $controller."Controller";
    $route = new Route(RouteType::DELETE, $tmpUri, $args, $tmpController, $action);
    RouteCollector::instance()->appendRoute($route);
}