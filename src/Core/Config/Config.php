<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 21.07.21
 * Time: 18:01
 */

namespace FlyCubePHP\Core\Config;

use Exception;

class Config
{
    const ENV_FILE_NAME                     = "application_env.conf";

    const TAG_APP_URL_PREFIX                = "APP_URL_PREFIX";
    const TAG_ENV_TYPE                      = "FLY_CUBE_PHP_ENV";
    const TAG_REBUILD_CACHE                 = "FLY_CUBE_PHP_REBUILD_CACHE";
    const TAG_REBUILD_TWIG_CACHE            = "FLY_CUBE_PHP_REBUILD_TWIG_CACHE";
    const TAG_ENABLE_ACTION_OUTPUT          = "FLY_CUBE_PHP_ENABLE_ACTION_OUTPUT";
    const TAG_ENABLE_PLUGINS_CORE           = "FLY_CUBE_PHP_ENABLE_PLUGINS_CORE";
    const TAG_CHECK_PLUGINS_COUNT           = "FLY_CUBE_PHP_CHECK_PLUGINS_COUNT";
    const TAG_CSRF_PROTECT                  = "FLY_CUBE_PHP_CSRF_PROTECT";
    const TAG_CSP_PROTECT                   = "FLY_CUBE_PHP_CSP_PROTECT";
    const TAG_COOKIE_SIGNED_SALT            = "FLY_CUBE_PHP_COOKIE_SIGNED_SALT";
    const TAG_COOKIE_ENCRYPTED_SALT         = "FLY_CUBE_PHP_COOKIE_ENCRYPTED_SALT";
    const TAG_ENABLE_TWIG_STRICT_VARIABLES  = "FLY_CUBE_PHP_ENABLE_TWIG_STRICT_VARIABLES";
    const TAG_ENABLE_TWIG_DEBUG_EXTENSION   = "FLY_CUBE_PHP_ENABLE_TWIG_DEBUG_EXTENSION";
    const TAG_CHECK_DUPLICATE_HELPERS       = "FLY_CUBE_PHP_CHECK_DUPLICATE_HELPERS";
    const TAG_ENABLE_EXTENSION_SUPPORT      = "FLY_CUBE_PHP_ENABLE_EXTENSION_SUPPORT";
    const TAG_EXTENSIONS_FOLDER             = "FLY_CUBE_PHP_EXTENSIONS_FOLDER";
    const TAG_ENABLE_LOG                    = "FLY_CUBE_PHP_ENABLE_LOG";
    const TAG_ENABLE_ROTATE_LOG             = "FLY_CUBE_PHP_ENABLE_ROTATE_LOG";
    const TAG_LOG_ROTATE_MAX_FILES          = "FLY_CUBE_PHP_LOG_ROTATE_MAX_FILES";
    const TAG_LOG_ROTATE_FILE_DATE_FORMAT   = "FLY_CUBE_PHP_LOG_ROTATE_FILE_DATE_FORMAT";
    const TAG_LOG_ROTATE_FILE_NAME_FORMAT   = "FLY_CUBE_PHP_LOG_ROTATE_FILE_NAME_FORMAT";
    const TAG_LOG_LEVEL                     = "FLY_CUBE_PHP_LOG_LEVEL";
    const TAG_LOG_FOLDER                    = "FLY_CUBE_PHP_LOG_FOLDER";
    const TAG_LOG_DATE_TIME_FORMAT          = "FLY_CUBE_PHP_LOG_DATE_TIME_FORMAT";
    const TAG_ENABLE_API_DOC                = "FLY_CUBE_PHP_ENABLE_API_DOC";
    const TAG_ENABLE_ASSETS_COMPRESSION     = "FLY_CUBE_PHP_ENABLE_ASSETS_COMPRESSION";
    const TAG_ASSETS_COMPRESSION_TYPE       = "FLY_CUBE_PHP_ASSETS_COMPRESSION_TYPE";
    const TAG_ACTION_CABLE_MOUNT_PATH       = "FLY_CUBE_PHP_ACTION_CABLE_MOUNT_PATH";
    const TAG_ACTION_CABLE_ENABLE_PERFORM   = "FLY_CUBE_PHP_ACTION_CABLE_ENABLE_PERFORM";

    private static $_instance = null;

    private $_args = array();
    private $_secretKey = "";

    /**
     * gets the instance via lazy initialization (created on first usage)
     */
    public static function instance(): Config {
        if (static::$_instance === null)
            static::$_instance = new static();
        return static::$_instance;
    }

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     */
    private function __construct() {
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
     * Загрузить файл с переменными окружения
     * @param string $filePath
     */
    public function loadEnv(string $filePath) {
        if (!file_exists($filePath))
            return;
        $tmp_env = $this->parseEnv($filePath);
        foreach ($tmp_env as $key => $value) {
            putenv("$key=$value");
            $this->setArg($key, $value);
        }
    }

    /**
     * Загрузить секретный ключ приложения
     * @param string $filePath
     */
    public function loadSecretKey(string $filePath) {
        if (!is_file($filePath) || !is_readable($filePath))
            throw new \RuntimeException('[Config] Not found server secret key!');

        $keyData = file_get_contents($filePath);
        $this->_secretKey = trim($keyData);
        if (empty($this->_secretKey))
            throw new \RuntimeException('[Config] Invalid server secret key!');
    }

    /**
     * Получить секретный ключ приложения
     * @return string
     */
    public function secretKey(): string {
        return $this->_secretKey;
    }

    /**
     * Получить список ключей для загруженных аргументов
     * @return array
     */
    public function keys(): array {
        return array_keys($this->_args);
    }

    /**
     * Получить массив загруженных аргументов
     * @return array
     */
    public function args(): array {
        return $this->_args;
    }

    /**
     * Получить значение аргумента настроек
     * @param string $key - ключ
     * @param mixed $def - базовое значение
     * @return mixed|null
     */
    public function arg(string $key, $def = null) {
        if (!array_key_exists($key, $this->_args))
            return $def;
        return $this->_args[$key];
    }

    /**
     * Задать значение аргумента настроек
     * @param string $key - ключ
     * @param $val - значение
     */
    public function setArg(string $key, $val) {
        $this->_args[$key] = $val;
    }

    /**
     * Check is ENV Production
     * @return bool
     */
    public function isProduction(): bool {
        if (strcasecmp($this->arg(Config::TAG_ENV_TYPE),"production") === 0)
            return true;
        return false;
    }

    /**
     * Check is ENV Development
     * @return bool
     */
    public function isDevelopment(): bool {
        if (strcasecmp($this->arg(Config::TAG_ENV_TYPE), "development") === 0)
            return true;
        return !$this->isProduction();
    }

    /**
     * Метод разбора файла переменных окружения
     * @param string $filePath - полный путь до файла
     * @return array
     */
    private function parseEnv(string $filePath): array {
        if (!file_exists($filePath))
            return array();
        $tmpEnv = array();
        if ($file = fopen($filePath, "r")) {
            while (!feof($file)) {
                $line = trim(fgets($file));
                if (empty($line))
                    continue;
                if (substr($line, 0, 1) == "#")
                    continue;
                $tmpKeyVal = explode(':', $line);
                if (count($tmpKeyVal) <= 1)
                    continue;
                $tmpKey = trim($tmpKeyVal[0]);
                if (empty($tmpKey))
                    continue;
                unset($tmpKeyVal[0]);
                $tmpVal = trim(join(':', $tmpKeyVal));
                if (!empty($tmpVal)) {
                    if (strcmp($tmpVal[0], '"') === 0)
                        $tmpVal = substr($tmpVal, 1, strlen($tmpVal));
                    if (strcmp($tmpVal[strlen($tmpVal) - 1], '"') === 0)
                        $tmpVal = substr($tmpVal, 0, strlen($tmpVal) - 1);
                }
                $tmpEnv[$tmpKey] = $tmpVal;
            }
            fclose($file);
        }
        return $tmpEnv;
    }
}