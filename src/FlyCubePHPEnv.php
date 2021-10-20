<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 20.07.21
 * Time: 18:56
 */

include_once 'FlyCubePHPErrorHandling.php';
include_once __DIR__.'/Core/AutoLoader/AutoLoaderHelper.php';
include_once __DIR__.'/Core/ActiveRecord/ActiveRecord.php';
include_once __DIR__.'/Core/Config/ConfigHelper.php';
include_once __DIR__.'/Core/Routes/RoutesHelper.php';
include_once __DIR__.'/Core/Controllers/BaseController.php';
include_once __DIR__.'/Core/Controllers/Helpers/BaseControllerHelper.php';
include_once __DIR__.'/Core/AssetPipeline/AssetPipeline.php';
include_once __DIR__.'/Core/Database/DatabaseFactory.php';
include_once __DIR__.'/Core/Cookie/Cookie.php';
include_once __DIR__.'/Core/Session/Session.php';
include_once __DIR__.'/HelperClasses/CoreHelper.php';
include_once __DIR__.'/ComponentsCore/ComponentsManager.php';
include_once 'FlyCubePHPEnvLoader.php';

use \FlyCubePHP\Core\Config\Config as Config;
use \FlyCubePHP\Core\Session\Session as Session;
use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;
use \FlyCubePHP\Core\Routes\RouteCollector as RouteCollector;
use \FlyCubePHP\Core\Database\DatabaseFactory as DatabaseFactory;
use \FlyCubePHP\Core\Controllers\FlashMessages as FlashMessages;
use \FlyCubePHP\Core\AssetPipeline\AssetPipeline as AssetPipeline;
use \FlyCubePHP\ComponentsCore\ComponentsManager as ComponentsManager;


// --- include base controllers classes ---
$base_controller = CoreHelper::buildPath(CoreHelper::rootDir(), "app", "controllers", "ApplicationController.php");
$base_controller_api = CoreHelper::buildPath(CoreHelper::rootDir(), "app", "controllers", "ApplicationControllerAPI.php");
if (is_file($base_controller))
    include_once $base_controller;
if (is_file($base_controller_api))
    include_once $base_controller_api;

// --- include env file ---
$env_devel = CoreHelper::buildPath(CoreHelper::rootDir(), "config", "environments", "development.php");
$env_prod = CoreHelper::buildPath(CoreHelper::rootDir(), "config", "environments", "production.php");
if (Config::instance()->isProduction() && is_file($env_prod))
    include_once $env_prod;
elseif (Config::instance()->isDevelopment() && is_file($env_devel))
    include_once $env_devel;

// --- check needed library ---
$twig_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "vendor", "Twig-2.x", "Twig");
if (!is_dir($twig_dir))
    trigger_error("Not found needed library directory! Dir: $twig_dir", E_USER_ERROR);
$scss_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "vendor", "ScssPhp", "ScssPhp");
if (!is_dir($scss_dir))
    trigger_error("Not found needed library directory! Dir: $scss_dir", E_USER_ERROR);
$jshrink_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "vendor", "JShrink", "JShrink");
if (!is_dir($jshrink_dir))
    trigger_error("Not found needed library directory! Dir: $jshrink_dir", E_USER_ERROR);
$psr_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "vendor", "Psr", "Psr");
if (!is_dir($psr_dir))
    trigger_error("Not found needed library directory! Dir: $psr_dir", E_USER_ERROR);
$monolog_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "vendor", "Monolog", "Monolog");
if (!is_dir($monolog_dir))
    trigger_error("Not found needed library directory! Dir: $monolog_dir", E_USER_ERROR);


// --- append autoload dirs ---
\FlyCubePHP\appendAutoLoadDir("vendor/");
\FlyCubePHP\appendAutoLoadDir("vendor/Twig-2.x/");
\FlyCubePHP\appendAutoLoadDir("vendor/JShrink/JShrink/");
\FlyCubePHP\appendAutoLoadDir("vendor/Psr/");
\FlyCubePHP\appendAutoLoadDir("vendor/Monolog/Monolog/");

// --- init database factory ---
DatabaseFactory::instance()->loadExtensions();
DatabaseFactory::instance()->loadConfig();

// --- init session cookie params ---
Session::initSessionCookieParams();
// --- init flash messages ---
FlashMessages::instance();

// --- check cache dirs ---
$cacheDir = CoreHelper::buildPath(CoreHelper::rootDir(), "tmp", "cache", "FlyCubePHP", "js_builder");
if (!CoreHelper::makeDir($cacheDir, 0777, true))
    trigger_error("Unable to create the cache directory! Dir: $cacheDir", E_USER_ERROR);
$cacheDir = CoreHelper::buildPath(CoreHelper::rootDir(), "tmp", "cache", "FlyCubePHP", "css_builder");
if (!CoreHelper::makeDir($cacheDir, 0777, true))
    trigger_error("Unable to create the cache directory! Dir: $cacheDir", E_USER_ERROR);
$cacheDir = CoreHelper::buildPath(CoreHelper::rootDir(), "tmp", "cache", "Twig");
if (!CoreHelper::makeDir($cacheDir, 0777, true))
    trigger_error("Unable to create the cache directory! Dir: $cacheDir", E_USER_ERROR);

// --- load app js files ---
$app_js_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "app", "assets", "javascripts");
AssetPipeline::instance()->appendJSDir($app_js_dir);

// --- load lib js files ---
$app_js_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "lib", "assets", "javascripts");
AssetPipeline::instance()->appendJSDir($app_js_dir);

// --- load vendor js files ---
$app_js_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "vendor", "assets", "javascripts");
AssetPipeline::instance()->appendJSDir($app_js_dir);

// --- load app css|scss files ---
$app_css_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "app", "assets", "stylesheets");
AssetPipeline::instance()->appendCSSDir($app_css_dir);

// --- load lib css|scss files ---
$app_css_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "lib", "assets", "stylesheets");
AssetPipeline::instance()->appendCSSDir($app_css_dir);

// --- load vendor css|scss files ---
$app_css_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "vendor", "assets", "stylesheets");
AssetPipeline::instance()->appendCSSDir($app_css_dir);

// --- load app images ---
$app_image_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "app", "assets", "images");
AssetPipeline::instance()->appendImageDir($app_image_dir);

// --- load lib images ---
$app_image_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "lib", "assets", "images");
AssetPipeline::instance()->appendImageDir($app_image_dir);

// --- load vendor images ---
$app_image_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "vendor", "assets", "images");
AssetPipeline::instance()->appendImageDir($app_image_dir);

// --- include all app models ---
$app_models_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "app", ComponentsManager::MODELS_DIR);
$app_models = CoreHelper::scanDir($app_models_dir);
foreach ($app_models as $model) {
    $fExt = pathinfo($model, PATHINFO_EXTENSION);
    if (strcmp(strtolower($fExt), "php") !== 0)
        continue;
    include_once $model;
}

// --- include all app controllers ---
$app_controllers_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "app", ComponentsManager::CONTROLLERS_DIR);
$app_controllers = CoreHelper::scanDir($app_controllers_dir);
foreach ($app_controllers as $controller) {
    if (!preg_match("/^.*Controller\.php$/", $controller))
        continue;
    include_once $controller;
}

// --- include app routes ---
$app_routes = CoreHelper::buildPath(CoreHelper::rootDir(), "config", "routes.php");
if (file_exists($app_routes))
    include_once $app_routes;
else
    trigger_error("Not found application routes file!", E_USER_ERROR);

// --- init components core ---
$enablePluginsCore = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_PLUGINS_CORE, true));
if ($enablePluginsCore === true) {
    $pl_dir = CoreHelper::buildPath(CoreHelper::rootDir(), ComponentsManager::PLUGINS_DIR);
    if (!ComponentsManager::instance()->loadPlugins($pl_dir))
        trigger_error("Load plugins failed!", E_USER_ERROR);

    if (!ComponentsManager::instance()->initPlugins())
        trigger_error("Init plugins failed!", E_USER_ERROR);
}

// --- check app routes ---
if (!RouteCollector::instance()->checkRoutes())
    trigger_error("Invalid routes list!", E_USER_ERROR);