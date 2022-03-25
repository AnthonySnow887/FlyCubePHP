<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 20.07.21
 * Time: 18:56
 */

include_once 'FlyCubePHPAutoLoader.php';
include_once 'FlyCubePHPErrorHandling.php';
include_once __DIR__.'/Core/ActiveRecord/ActiveRecord.php';
include_once __DIR__.'/Core/Config/ConfigHelper.php';
include_once __DIR__.'/Core/Routes/RoutesHelper.php';
include_once __DIR__.'/Core/Controllers/BaseController.php';
include_once __DIR__.'/Core/Controllers/Helpers/BaseControllerHelper.php';
include_once __DIR__.'/Core/AssetPipeline/AssetPipeline.php';
include_once __DIR__.'/Core/Database/DatabaseFactory.php';
include_once __DIR__.'/Core/Cookie/Cookie.php';
include_once __DIR__.'/Core/Session/Session.php';
include_once __DIR__.'/Core/ApiDoc/ApiDoc.php';
include_once __DIR__.'/Core/HelpDoc/HelpDoc.php';
include_once __DIR__.'/HelperClasses/CoreHelper.php';
include_once __DIR__.'/ComponentsCore/ComponentsManager.php';
include_once 'FlyCubePHPEnvLoader.php';

use FlyCubePHP\Core\ApiDoc\ApiDoc;
use FlyCubePHP\Core\HelpDoc\HelpDoc;
use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\Core\Session\Session;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\AutoLoader\AutoLoader;
use FlyCubePHP\Core\Database\DatabaseFactory;
use FlyCubePHP\Core\Controllers\FlashMessages;
use FlyCubePHP\Core\AssetPipeline\AssetPipeline;
use FlyCubePHP\ComponentsCore\ComponentsManager;


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

// --- init database factory ---
DatabaseFactory::instance()->loadExtensions();
DatabaseFactory::instance()->loadConfig();

// --- init session cookie params ---
Session::initSessionCookieParams();
// --- init session ---
Session::instance()->init();
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
AssetPipeline::instance()->appendJavascriptDir($app_js_dir);

// --- load lib js files ---
$app_js_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "lib", "assets", "javascripts");
AssetPipeline::instance()->appendJavascriptDir($app_js_dir);

// --- load vendor js files ---
$app_js_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "vendor", "assets", "javascripts");
AssetPipeline::instance()->appendJavascriptDir($app_js_dir);

// --- load app css|scss files ---
$app_css_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "app", "assets", "stylesheets");
AssetPipeline::instance()->appendStylesheetDir($app_css_dir);

// --- load lib css|scss files ---
$app_css_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "lib", "assets", "stylesheets");
AssetPipeline::instance()->appendStylesheetDir($app_css_dir);

// --- load vendor css|scss files ---
$app_css_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "vendor", "assets", "stylesheets");
AssetPipeline::instance()->appendStylesheetDir($app_css_dir);

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
AutoLoader::instance()->appendAutoLoadDir($app_models_dir);

// --- include all app controllers ---
$app_controllers_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "app", ComponentsManager::CONTROLLERS_DIR);
AutoLoader::instance()->appendAutoLoadDir($app_controllers_dir);

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

    ComponentsManager::instance()->saveCache();
}

// --- load api-doc ---
if (ApiDoc::instance()->isEnabled() === true) {
    $app_api_doc_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "doc", "api");
    ApiDoc::instance()->appendApiDocDir($app_api_doc_dir);
}

// --- load help-doc ---
if (HelpDoc::instance()->isEnabled() === true) {
    $app_help_doc_img_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "doc", "help", "images");
    AssetPipeline::instance()->appendImageDir($app_help_doc_img_dir);
    $app_help_doc_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "doc", "help");
    HelpDoc::instance()->appendHelpDocDir($app_help_doc_dir);
}

// --- load initializers ---
$initializers_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "config", "initializers");
$app_initializers = CoreHelper::scanDir($initializers_dir);
foreach ($app_initializers as $initializer) {
    if (!preg_match("/^.*\.php$/", $initializer))
        continue;
    include_once $initializer;
}
