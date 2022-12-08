<?php

namespace FlyCubePHP\Core\Cache;

include_once __DIR__.'/../Config/ConfigHelper.php';
include_once __DIR__.'/../../HelperClasses/CoreHelper.php';

use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\HelperClasses\CoreHelper;

class APCu
{
    const APCU_PRELOAD_KEY  = "FlyCubePHP_APCu_preload";
    const APCU_CACHE_DIR    = "tmp/cache/FlyCubePHP/apcu/";

    /**
     * Проверка, включена ли поддержка APCu
     * @return bool
     */
    static public function isApcuEnabled(): bool {
        $defVal = Config::instance()->isProduction();
        return function_exists('apcu_enabled') && apcu_enabled() && CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_APCU_CACHE, $defVal));
    }

    /**
     * Проверка, включен ли режим предварительной загрузки кэша APCu
     * @return bool
     */
    static public function isApcuPreloadEnabled(): bool {
        $defVal = Config::instance()->isProduction();
        return self::isApcuEnabled() && CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_APCU_CACHE_PRELOAD, $defVal));
    }

    /**
     * Получить кэшированные данные
     * @param string $key
     * @param null $def
     * @return false|mixed|null
     */
    static public function cacheData(string $key, $def = null) {
        if (!self::isApcuEnabled())
            return $def;
        $success = false;
        $tmpCache = apcu_fetch($key, $success);
        return ($success === false) ? $def : $tmpCache;

    }

    /**
     * Добавить данные в кэш
     * @param string $key
     * @param $value
     */
    static public function setCacheData(string $key, $value) {
        if (empty($key) || !self::isApcuEnabled())
            return;
        if (apcu_store($key, $value) !== true)
            trigger_error("Save cache data failed (APCu)! Key: $key", E_USER_ERROR);
    }

    /**
     * Сохранить файл кэша для APCu
     * @param string $key
     * @param $data
     */
    static public function saveEncodedApcuData(string $key, $data) {
        if (empty($key) || !self::isApcuEnabled())
            return;
        $encodedApcuData = serialize($data);

        $dirPath = CoreHelper::buildPath(CoreHelper::rootDir(), self::APCU_CACHE_DIR);
        if (!CoreHelper::makeDir($dirPath, 0777, true))
            trigger_error("Make dir for cache data failed! Path: $dirPath", E_USER_ERROR);

        $fPath = CoreHelper::buildPath($dirPath, "$key.data");
        $tmpFile = tempnam($dirPath, basename($fPath));
        if (false !== @file_put_contents($tmpFile, $encodedApcuData) && @rename($tmpFile, $fPath))
            @chmod($fPath, 0666 & ~umask());
        else
            trigger_error("Write file for cache data failed! Path: $dirPath", E_USER_ERROR);
    }

    /**
     * Метод предварительной загрузки кэша APCu
     */
    static public function preLoadCache() {
        if (!self::isApcuPreloadEnabled())
            return;
        if (self::cacheData(self::APCU_PRELOAD_KEY, false))
            return;
        $dirPath = CoreHelper::buildPath(CoreHelper::rootDir(), self::APCU_CACHE_DIR);
        $apcuDataFiles = CoreHelper::scanDir($dirPath, [ 'recursive' => false ]);
        foreach ($apcuDataFiles as $f) {
            if (!preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.data)$/", $f))
                continue;
            $fData = file_get_contents($f);
            if ($fData === false)
                trigger_error("Read cache data file failed! Path: $f", E_USER_ERROR);

            $decodedApcuData = unserialize($fData);
            $tmpKey = basename($f);
            $tmpKey = substr($tmpKey, 0, strlen($tmpKey) - 5);
            self::setCacheData($tmpKey, $decodedApcuData);
        }
        self::setCacheData(self::APCU_PRELOAD_KEY, true);
    }
}