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
include_once __DIR__.'/Core/AssetPipeline/AssetPipeline.php';

use FlyCubePHP\Core\Routes\RouteCollector;
use FlyCubePHP\Core\AssetPipeline\AssetPipeline;

function requestProcessing() {
    // --- check if load assets ---
    AssetPipeline::instance()->assetProcessing();

    // --- process current route ---
    include_once 'FlyCubePHPEnv.php';
    RouteCollector::instance()->processingRequest();
}
