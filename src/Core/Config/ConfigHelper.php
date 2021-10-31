<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 22.07.21
 * Time: 12:18
 */

namespace FlyCubePHP;

include_once 'Config.php';
use \FlyCubePHP\Core\Config\Config as Config;

/**
 * Задать значение аргумента настроек
 * @param string $key - ключ
 * @param $val - значение
 */
function setConfigValue(string $key, $val) {
    Config::instance()->setArg($key, $val);
}

/**
 * Получить значение аргумента настроек
 * @param string $key - ключ
 * @param mixed $def - базовое значение
 * @return mixed|null
 */
function configValue(string $key, $def = null) {
    return Config::instance()->arg($key, $def);
}

/**
 * Check is ENV Production
 * @return bool
 */
function isProduction(): bool {
    return Config::instance()->isProduction();
}

/**
 * Check is ENV Development
 * @return bool
 */
function isDevelopment(): bool {
    return Config::instance()->isDevelopment();
}