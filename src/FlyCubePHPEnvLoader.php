<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 29.07.21
 * Time: 16:52
 */

include_once __DIR__.'/Core/Config/ConfigHelper.php';
include_once __DIR__.'/HelperClasses/CoreHelper.php';
include_once __DIR__.'/ComponentsCore/ComponentsManager.php';

use \FlyCubePHP\Core\Config\Config as Config;
use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;
use \FlyCubePHP\ComponentsCore\ComponentsManager as ComponentsManager;


// --- load env values ---
$env_file = CoreHelper::buildPath(CoreHelper::rootDir(), ComponentsManager::CONFIG_DIR, Config::ENV_FILE_NAME);
$key_file = CoreHelper::buildPath(CoreHelper::rootDir(), ComponentsManager::CONFIG_DIR, "secret.key");
Config::instance()->loadEnv($env_file);
if (Config::instance()->isProduction() === true) {
    if (!is_file($key_file) || !is_readable($key_file))
        Config::instance()->setArg(Config::TAG_ENV_TYPE, 'development');
    else
        Config::instance()->loadSecretKey($key_file);
}