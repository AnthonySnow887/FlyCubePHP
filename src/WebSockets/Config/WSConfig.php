<?php

namespace FlyCubePHP\WebSockets\Config;

use Exception;
use FlyCubePHP\ComponentsCore\ComponentsManager;
use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\HelperClasses\CoreHelper;

class WSConfig
{
    private static $_instance = null;
    private $_settings = null;

    const WS_CONFIG = "cable.json";

    const TAG_WS_SERVER_HOST        = "server_host";
    const TAG_WS_SERVER_PORT        = "server_port";
    const TAG_WS_SERVER_WORKERS_NUM = "server_workers";
    const TAG_IPC_SOCK_PATH         = "adapter_socket";
    const TAG_IPC_SOCK_MODE         = "adapter_socket_mode";
    const TAG_REDIS_HOST            = "redis_host";
    const TAG_REDIS_PORT            = "redis_port";
    const TAG_REDIS_PASSWORD        = "redis_password";
    const TAG_REDIS_CHANNEL         = "redis_channel";

    const DEFAULT_IPC_SOCK_PATH     = "/tmp/fly_cube_php.soc";
    const DEFAULT_IPC_SOCK_MODE     = "0755";

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function instance(): WSConfig {
        if (static::$_instance === null)
            static::$_instance = new static();
        return static::$_instance;
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     */
    private function __construct() {
        $this->loadConfig();
    }

    /**
     * prevent the instance from being cloned (which would create a second instance of it)
     */
    private function __clone() {
    }

    /**
     * prevent from being unserialized (which would create a second instance of it)
     * @throws Exception Cannot unserialize singleton
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }

    /**
     * Имя текущего адаптера по работе с web sockets
     * @return string
     */
    public function currentAdapterName(): string {
        if (is_null($this->_settings))
            return "";
        if (!array_key_exists('adapter', $this->_settings))
            return "";
        return $this->_settings['adapter'];
    }

    /**
     * Получить массив текущих настроек по работе с web sockets
     * @return array
     */
    public function currentSettings(): array {
        if (is_null($this->_settings))
            return [];
        return $this->_settings;
    }

    /**
     * Получить значение ключа текущих настроек по работе с web sockets
     * @param mixed $def
     * @return mixed
     */
    public function currentSettingsValue(string $key, $def) {
        if (is_null($this->_settings)
            || !array_key_exists($key, $this->_settings))
            return $def;
        return $this->_settings[$key];
    }

    /**
     * Загрузить настройки для работы с web sockets
     */
    private function loadConfig() {
        if (!is_null($this->_settings))
            return;
        $path = CoreHelper::buildPath(CoreHelper::rootDir(), ComponentsManager::CONFIG_DIR, self::WS_CONFIG);
        if (!is_readable($path))
            throw new \RuntimeException("[WSConfig][loadConfig] Not found database configs! Path: $path");
        $configData = file_get_contents($path);
        $configDataJSON = json_decode($configData, true);
        if (!is_array($configDataJSON))
            throw new \RuntimeException("[WSConfig][loadConfig] Invalid web sockets configs (invalid JSON)! Path: $path");
        if (Config::instance()->isProduction()) {
            if (array_key_exists('production', $configDataJSON)) {
                $tmpSettings = $configDataJSON['production'];
                if (is_string($tmpSettings)) {
                    if (!array_key_exists($tmpSettings, $configDataJSON))
                        throw new \RuntimeException("[WSConfig][loadConfig] Not found web sockets production settings ($tmpSettings)! Path: $path");
                    $tmpSettingsArr = $configDataJSON[$tmpSettings];
                    if (!is_array($tmpSettingsArr))
                        throw new \RuntimeException("[WSConfig][loadConfig] Invalid web sockets production settings (is nor array)! Key: $tmpSettings; Path: $path");
                    $this->_settings = $tmpSettingsArr;
                } elseif (is_array($tmpSettings)) {
                    $this->_settings = $tmpSettings;
                } else {
                    throw new \RuntimeException("[WSConfig][loadConfig] Invalid web sockets production settings (is nor array or string)! Path: $path");
                }
            }
        } elseif (Config::instance()->isDevelopment()) {
            if (array_key_exists('development', $configDataJSON)) {
                $tmpSettings = $configDataJSON['development'];
                if (is_string($tmpSettings)) {
                    if (!array_key_exists($tmpSettings, $configDataJSON))
                        throw new \RuntimeException("[WSConfig][loadConfig] Not found web sockets development settings ($tmpSettings)! Path: $path");
                    $tmpSettingsArr = $configDataJSON[$tmpSettings];
                    if (!is_array($tmpSettingsArr))
                        throw new \RuntimeException("[WSConfig][loadConfig] Invalid web sockets development settings (is nor array)! Key: $tmpSettings; Path: $path");
                    $this->_settings = $tmpSettingsArr;
                } elseif (is_array($tmpSettings)) {
                    $this->_settings = $tmpSettings;
                } else {
                    throw new \RuntimeException("[WSConfig][loadConfig] Invalid web sockets development settings (is nor array or string)! Path: $path");
                }
            }
        }
        if (is_null($this->_settings))
            throw new \RuntimeException("[WSConfig][loadConfig] Invalid web sockets configs (invalid JSON)! Path: $path");

        // --- check supported adapters ---
        if (!array_key_exists('adapter', $this->_settings))
            throw new \RuntimeException("[WSConfig][loadConfig] Not found web sockets adapter! Path: $path");
    }
}