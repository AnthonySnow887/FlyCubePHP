<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 29.07.21
 * Time: 16:27
 */

namespace FlyCubePHP;

include_once 'FlyCubePHPVersion.php';
include_once 'FlyCubePHPAutoLoader.php';
include_once 'FlyCubePHPErrorHandling.php';
include_once 'FlyCubePHPEnvLoader.php';
include_once __DIR__.'/Core/Logger/Logger.php';
include_once __DIR__.'/Core/AssetPipeline/AssetPipeline.php';

use \FlyCubePHP\Core\Config\Config as Config;
use FlyCubePHP\Core\Logger\Logger;
use \FlyCubePHP\Core\Routes\RouteCollector as RouteCollector;
use \FlyCubePHP\Core\AssetPipeline\AssetPipeline as AssetPipeline;

function requestProcessing() {
    // --- check if load assets ---
    AssetPipeline::instance()->assetProcessing();

    // --- process current route ---
    include_once 'FlyCubePHPEnv.php';

    // --- log request ---
    $httpM = RouteCollector::currentRouteMethod();
    $httpUrl = RouteCollector::currentRouteUri();
    $httpArgs = RouteCollector::currentRouteArgs();
    $clientIP = RouteCollector::currentClientIP();
    Core\Logger\Logger::info("$httpM $httpUrl (from: $clientIP)");
    if (empty($httpArgs))
        Core\Logger\Logger::info("PARAMS: {}");
    else
        Core\Logger\Logger::info("PARAMS:", $httpArgs);

    // --- check current route ---
    $tmpCurRoute = RouteCollector::instance()->currentRoute();
    if (is_null($tmpCurRoute)) {
        if (Config::instance()->isDevelopment()) {
            trigger_error("Not found route: [$httpM] $httpUrl", E_USER_ERROR);
        } else {
            Core\Logger\Logger::warning("Not found current route (404)! URL: [$httpM] $httpUrl");
            if (!RouteCollector::instance()->containsRoute('GET', '/404')) {
                http_response_code(404);
                die();
            }
            $tmpCurRoute = RouteCollector::instance()->routeByMethodUri('GET', '/404');
            if (!isset($_GET['code']))
                $_GET['code'] = 404;
        }
    }

    // --- processing controller ---
    $tmpClassName = $tmpCurRoute->controller();
    $tmpClassAct = $tmpCurRoute->action();
    $tmpController = new $tmpClassName();
    $renderStartMS = microtime(true);
    try {
        $tmpController->renderPrivate($tmpClassAct);
    } catch (\Throwable $ex) {
        $tmpController->evalException($ex);
    }
    $renderMS = round(microtime(true) - $renderStartMS, 3);
    unset($tmpController);
    Core\Logger\Logger::info("RENDER: [$renderMS"."ms] $tmpClassName::$tmpClassAct()");
}
