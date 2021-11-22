<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 21.07.21
 * Time: 15:25
 */

namespace FlyCubePHP;

include_once 'RouteCollector.php';

use \FlyCubePHP\Core\Routes\Route as Route;
use \FlyCubePHP\Core\Routes\RouteType as RouteType;
use \FlyCubePHP\Core\Routes\RouteCollector as RouteCollector;

/**
 * Задать root url
 * @param string $controller
 * @param string $action
 */
function root(string $controller, string $action) {
    if (empty($controller) || empty($action))
        trigger_error("Make ROOT route failed! Empty controller name or action name!", E_USER_ERROR);

    $tmpController = $controller."Controller";
    $route = new Route(RouteType::GET, "/", [], $tmpController, $action, 'root');
    RouteCollector::instance()->appendRoute($route);
}

/**
 * Задать HTTP GET url
 * @param string $uri
 * @param array $args - массив аргументов
 *
 * ==== Args
 *
 * - [string] to            - The name of the controller and the action separated '#' (Test#show)
 * - [string] controller    - The name of the controller
 * - [string] action        - The name of the controller action
 * - [string] as            - Alias for quick access to the route
 *
 * Other arguments will be transferred as input parameters.
 *
 * ==== Examples
 *
 * get('/test', [ 'to' => 'Test#show' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *
 * get('/test', [ 'controller' => 'Test', 'action' => 'show' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *
 * get('/test', [ 'to' => 'Test#show', 'as' => 'test' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *      - test  - Alias for quick access to url (use define 'test_url')
 */
function get(string $uri, array $args = []) {
    try {
        $args['type'] = RouteType::GET;
        make_route($uri, $args);
    } catch (\ReflectionException $e) {
        trigger_error("Make GET route failed! Error: " . $e->getMessage(), E_USER_ERROR);
    }
}

/**
 * Задать HTTP POST url
 * @param string $uri
 * @param array $args - массив аргументов
 *
 * ==== Args
 *
 * - [string] to            - The name of the controller and the action separated '#' (Test#show)
 * - [string] controller    - The name of the controller
 * - [string] action        - The name of the controller action
 * - [string] as            - Alias for quick access to the route
 *
 * Other arguments will be transferred as input parameters.
 *
 * ==== Examples
 *
 * post('/test', [ 'to' => 'Test#show' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *
 * post('/test', [ 'controller' => 'Test', 'action' => 'show' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *
 * post('/test', [ 'to' => 'Test#show', 'as' => 'test' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *      - test  - Alias for quick access to url (use define 'test_url')
 */
function post(string $uri, array $args = []) {
    try {
        $args['type'] = RouteType::POST;
        make_route($uri, $args);
    } catch (\ReflectionException $e) {
        trigger_error("Make POST route failed! Error: " . $e->getMessage(), E_USER_ERROR);
    }
}

/**
 * Задать HTTP PUT url
 * @param string $uri
 * @param array $args - массив аргументов
 *
 * ==== Args
 *
 * - [string] to            - The name of the controller and the action separated '#' (Test#show)
 * - [string] controller    - The name of the controller
 * - [string] action        - The name of the controller action
 * - [string] as            - Alias for quick access to the route
 *
 * Other arguments will be transferred as input parameters.
 *
 * ==== Examples
 *
 * put('/test', [ 'to' => 'Test#show' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *
 * put('/test', [ 'controller' => 'Test', 'action' => 'show' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *
 * put('/test', [ 'to' => 'Test#show', 'as' => 'test' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *      - test  - Alias for quick access to url (use define 'test_url')
 */
function put(string $uri, array $args = []) {
    try {
        $args['type'] = RouteType::PUT;
        make_route($uri, $args);
    } catch (\ReflectionException $e) {
        trigger_error("Make PUT route failed! Error: " . $e->getMessage(), E_USER_ERROR);
    }
}

/**
 * Задать HTTP PATCH url
 * @param string $uri
 * @param array $args - массив аргументов
 *
 * ==== Args
 *
 * - [string] to            - The name of the controller and the action separated '#' (Test#show)
 * - [string] controller    - The name of the controller
 * - [string] action        - The name of the controller action
 * - [string] as            - Alias for quick access to the route
 *
 * Other arguments will be transferred as input parameters.
 *
 * ==== Examples
 *
 * patch('/test', [ 'to' => 'Test#show' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *
 * patch('/test', [ 'controller' => 'Test', 'action' => 'show' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *
 * patch('/test', [ 'to' => 'Test#show', 'as' => 'test' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *      - test  - Alias for quick access to url (use define 'test_url')
 */
function patch(string $uri, array $args = []) {
    try {
        $args['type'] = RouteType::PATCH;
        make_route($uri, $args);
    } catch (\ReflectionException $e) {
        trigger_error("Make PATCH route failed! Error: " . $e->getMessage(), E_USER_ERROR);
    }
}

/**
 * Задать HTTP DELETE url
 * @param string $uri
 * @param array $args - массив аргументов
 *
 * ==== Args
 *
 * - [string] to            - The name of the controller and the action separated '#' (Test#show)
 * - [string] controller    - The name of the controller
 * - [string] action        - The name of the controller action
 * - [string] as            - Alias for quick access to the route
 *
 * Other arguments will be transferred as input parameters.
 *
 * ==== Examples
 *
 * delete('/test', [ 'to' => 'Test#show' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *
 * delete('/test', [ 'controller' => 'Test', 'action' => 'show' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *
 * delete('/test', [ 'to' => 'Test#show', 'as' => 'test' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *      - test  - Alias for quick access to url (use define 'test_url')
 */
function delete(string $uri, array $args = []) {
    try {
        $args['type'] = RouteType::DELETE;
        make_route($uri, $args);
    } catch (\ReflectionException $e) {
        trigger_error("Make DELETE route failed! Error: " . $e->getMessage(), E_USER_ERROR);
    }
}

/**
 * Задать HTTP url
 * @param string $uri
 * @param array $args - массив аргументов
 * @throws \ReflectionException
 *
 * ==== Args
 *
 * - [int]    type          - Route type (RouteType::...)
 * - [string] to            - The name of the controller and the action separated '#' (Test#show)
 * - [string] controller    - The name of the controller
 * - [string] action        - The name of the controller action
 * - [string] as            - Alias for quick access to the route
 *
 * Other arguments will be transferred as input parameters.
 *
 * ==== Examples
 *
 * make_route('/test', [ 'type' => RouteType::DELETE, 'to' => 'Test#show' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *
 * make_route('/test', [ 'type' => RouteType::DELETE, 'controller' => 'Test', 'action' => 'show' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *
 * make_route('/test', [ 'type' => RouteType::DELETE, 'to' => 'Test#show', 'as' => 'test' ])
 *    where
 *      - Test  - The name of the controller class without expansion controller
 *      - show  - The name of the controller action
 *      - test  - Alias for quick access to url (use define 'test_url')
 */
function make_route(string $uri, array $args = []) {
    // --- check type ---
    if (!isset($args['type']))
        trigger_error("Make route failed! Not specified Type of route!", E_USER_ERROR);

    $tmpType = $args['type'];
    $tmpTypeStr = RouteType::intToString($tmpType);
    unset($args['type']);
    if (!RouteType::isValidValue('FlyCubePHP\Core\Routes\RouteType', $tmpType))
        trigger_error("Make route failed! Invalid route type value ($tmpType)!", E_USER_ERROR);

    // --- check uri ---
    if (empty($uri))
        trigger_error("Make $tmpTypeStr route failed! Empty uri!", E_USER_ERROR);

    $tmpUri = RouteCollector::makeValidRouteUrl($uri);
    if (empty($tmpUri))
        trigger_error("Make $tmpTypeStr route failed! Invalid URI!", E_USER_ERROR);

    // --- check args ---
    if (isset($args['to']) && !empty($args['to'])) {
        $tmpTo = explode('#', $args['to']);
        if (count($tmpTo) != 2)
            trigger_error("Make $tmpTypeStr route failed! Invalid argument 'to'!", E_USER_ERROR);

        $tmpController = $tmpTo[0];
        $tmpAct = $tmpTo[1];
    } else if (isset($args['controller']) && !empty($args['controller'])
        && isset($args['action']) && !empty($args['action'])) {
        $tmpController = $args['controller'];
        $tmpAct = $args['action'];
    } else {
        trigger_error("Make $tmpTypeStr route failed! Invalid input arguments!", E_USER_ERROR);
    }

    // --- save as ---
    $tmpAs = "";
    if (isset($args['as']))
        $tmpAs = $args['as'];

    // --- clear ---
    if (isset($args['to']))
        unset($args['to']);
    if (isset($args['controller']))
        unset($args['controller']);
    if (isset($args['action']))
        unset($args['action']);
    if (isset($args['as']))
        unset($args['as']);

    // --- create & add ---
    $tmpController .= "Controller";
    $route = new Route($tmpType, $tmpUri, $args, $tmpController, $tmpAct, $tmpAs);
    RouteCollector::instance()->appendRoute($route);
}