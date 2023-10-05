<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 20.07.21
 * Time: 16:53
 */

namespace FlyCubePHP\HelperClasses;

include_once __DIR__."/../Core/Routes/RouteCollector.php";

use FlyCubePHP\Core\Routes\RouteCollector;

class CoreHelper
{
    const UINT_8_MIN  = 0;
    const UINT_16_MIN = self::UINT_8_MIN;
    const UINT_32_MIN = self::UINT_8_MIN;
    const UINT_64_MIN = self::UINT_8_MIN;

    const UINT_8_MAX  = 255;
    const UINT_16_MAX = 65535;
    const UINT_32_MAX = 4294967295;
    const UINT_64_MAX = 18446744073709551615;

    const INT_8_MIN  = -128;
    const INT_16_MIN = -32768;
    const INT_32_MIN = -2147483648;
    const INT_64_MIN = -9223372036854775808;

    const INT_8_MAX  = 127;
    const INT_16_MAX = 32767;
    const INT_32_MAX = 2147483647;
    const INT_64_MAX = 9223372036854775807;

    const TYPE_NUMBER_INVALID = -1;
    const TYPE_NUMBER_UINT_8  = 0;
    const TYPE_NUMBER_UINT_16 = 1;
    const TYPE_NUMBER_UINT_32 = 2;
    const TYPE_NUMBER_UINT_64 = 3;
    const TYPE_NUMBER_INT_8   = 4;
    const TYPE_NUMBER_INT_16  = 5;
    const TYPE_NUMBER_INT_32  = 6;
    const TYPE_NUMBER_INT_64  = 7;

    /**
     * Получить путь корневого каталога приложения
     * @return string
     */
    static public function rootDir(): string {
        $phpSelf = $_SERVER['SCRIPT_FILENAME'];
        if (empty($phpSelf))
            $phpSelf = $_SERVER['PHP_SELF'];
        if (strcmp(basename($phpSelf), "index.php") !== 0)
            return getcwd();
        $rootDir = CoreHelper::dirName($phpSelf);
        if (empty($rootDir) || strcmp($rootDir, ".") === 0) {
            $rootDir = pathinfo($_SERVER["SCRIPT_FILENAME"],  PATHINFO_DIRNAME);
            if (strcmp($rootDir, ".") === 0)
                return getcwd();
        }
        return $rootDir;
    }

    /**
     * Собрать путь до файла/каталога из строк
     * @param mixed ...$segments
     * @return string
     */
    static public function buildPath(...$segments): string {
        if (empty($segments))
            return "";
        if (!is_array($segments))
            return "";
        if (!is_array($segments[0]))
            return implode(DIRECTORY_SEPARATOR, $segments);
        return implode(DIRECTORY_SEPARATOR, $segments[0]);
    }

    /**
     * Собрать путь до файла/каталога из строк исключая путь до корневого каталога приложения
     * @param mixed ...$segments
     * @return string
     */
    static public function buildAppPath(...$segments): string {
        if (empty($segments))
            return "";
        if (!is_array($segments))
            return "";
        $tmpPath = "";
        if (!is_array($segments[0]))
            $tmpPath = CoreHelper::buildPath($segments);
        else
            $tmpPath = CoreHelper::buildPath($segments[0]);
        $tmpPath = str_replace(CoreHelper::rootDir(), "", $tmpPath);
        if (!empty($tmpPath) && $tmpPath[0] == DIRECTORY_SEPARATOR)
            $tmpPath = ltrim($tmpPath, $tmpPath[0]);
        return $tmpPath;
    }

    /**
     * Получить значение переменной окружения
     * @param string $key - ключ
     * @param string $def - базовое значение
     * @return string
     */
    static public function getEnv(string $key, string $def = ""): string {
        if (empty($key))
            return $def;
        $tmpVal = getenv($key);
        if (is_bool($tmpVal) && $tmpVal === false)
            return $def;
        return $tmpVal;
    }

    /**
     * Получить значение локальной переменной окружения (выставлена системой или методом putenv(...) )
     * @param string $key - ключ
     * @param string $def - базовое значение
     * @return string
     */
    static public function getEnvLocal(string $key, string $def = ""): string {
        if (empty($key))
            return $def;
        $tmpVal = getenv($key, true);
        if (is_bool($tmpVal) && $tmpVal === false)
            return $def;
        return $tmpVal;
    }

    /**
     * Преобразование строки в формат pretty name
     * @param string $str
     * @return string
     *
     * TestStringValue -> Test String Value
     */
    static public function prettyName(string $str): string {
        $str = CoreHelper::underscore($str);
        $str = str_replace("_", " ", $str);
        return ucwords($str);
    }

    /**
     * Преобразование строки в формат underscore
     * @param string $str
     * @return string
     *
     * TestStringValue -> test_string_value
     */
    static public function underscore(string $str): string {
        $tmpStr = "";
        for ($i = 0; $i < strlen($str); $i++) {
            $tmpChar = $str[$i];
            if (ctype_upper($tmpChar)) {
                if ($i > 0)
                    $tmpStr .= "_" . strtolower($tmpChar);
                else
                    $tmpStr .= strtolower($tmpChar);
            } else {
                $tmpStr .= $tmpChar;
            }
        }
        return $tmpStr;
    }

    /**
     * Преобразование строки в формат camelcase
     * @param string $str - строка
     * @param bool $incFirst - включать первое слово
     * @return string
     *
     * echo camelcase('test-string-value')
     * * => TestStringValue
     *
     * echo camelcase('test_string_value')
     * * => TestStringValue
     *
     * echo camelcase('test string value')
     * * => TestStringValue
     *
     * echo camelcase('test-string-value', false)
     * * => testStringValue
     *
     * echo camelcase('test_string_value', false)
     * * => testStringValue
     *
     * echo camelcase('test string value', false)
     * * => testStringValue
     */
    static public function camelcase(string $str, bool $incFirst = true): string {
        $tmpStr = str_replace("-", " ", $str);
        $tmpStr = str_replace("_", " ", $tmpStr);
        $tmpStr = ucwords($tmpStr);
        $tmpStr = str_replace(" ", "", $tmpStr);
        if ($incFirst === false)
            $tmpStr = lcfirst($tmpStr);
        return $tmpStr;
    }

    /**
     * Создать строку заданого размера
     * @param string $val - значение строки
     * @param int $maxLength - необходимый максимальный размер
     * @param string $type - тип добавления (before/after)
     * @return string
     *
     * echo makeEvenLength('test', 10)
     * => 'test      '
     *
     * echo makeEvenLength('test', 10, 'before')
     * => '      test'
     *
     * echo makeEvenLength('test')
     * => 'test'
     *
     * echo makeEvenLength('test', 1)
     * => 'test'
     */
    static public function makeEvenLength(string $val, int $maxLength = -1, string $type = "after"): string {
        $valLen = strlen($val);
        if ($maxLength <= 0 || $maxLength < $valLen)
            $maxLength = $valLen;
        $appendLen = $maxLength - $valLen;
        $appendStr = str_repeat(" ", $appendLen);
        $tmpStr = $val.$appendStr;
        if (strcmp(strtolower(trim($type)), "before") === 0)
            $tmpStr = $appendStr.$val;
        return $tmpStr;
    }

    /**
     * Сканирование каталога
     * @param string $dir
     * @param array $args - массив параметров сканирования
     * @return array
     *
     * ==== Args
     *
     * - [bool] recursive       - use recursive scan (default: false)
     * - [bool] append-dirs     - add subdirectories (default: false)
     * - [bool] only-dirs       - search only directories (default: false)
     */
    static public function scanDir(string $dir, array $args = []): array {
        if (!is_dir($dir))
            return array();
        $recursive = $args['recursive'] ?? false;
        $appendDirs = $args['append-dirs'] ?? false;
        $onlyDirs = $args['only-dirs'] ?? false;

        $files = scandir($dir);
        $results = array();
        foreach ($files as $key => $value) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if (!is_dir($path)) {
                if ($onlyDirs !== true)
                    $results[] = $path;
            } elseif ($value != "." && $value != "..") {
                if ($appendDirs === true)
                    $results[] = $path;
                if ($recursive === true)
                    $results = array_merge($results, CoreHelper::scanDir($path, $args));
            }
        }
        return $results;
    }

    /**
     * Создать каталог
     * @param string $path
     * @param int $mode
     * @param bool $recursive
     * @return bool
     */
    static public function makeDir(string $path, $mode = 0777, $recursive = false): bool {
        if (!is_dir($path)) {
            if (false === @mkdir($path, $mode, $recursive)) {
                if (!is_dir($path))
                    return false;
            }
        } elseif (!is_writable($path)) {
            return false;
        }
        return true;
    }

    /**
     * Получить путь до файла без его имени
     * @param string $path
     * @return string
     *
     * echo dirName('assets/application_modules/test.js');
     * => 'assets/application_modules'
     */
    static public function dirName(string $path): string {
        if (empty($path))
            return "";
        $pathLst = explode(DIRECTORY_SEPARATOR, $path);
        if (empty($pathLst))
            return $path;
        unset($pathLst[count($pathLst) - 1]);
        return CoreHelper::buildPath($pathLst);
    }

    /**
     * Получить имя файла
     * @param string $path - путь до файла
     * @param bool $removeExt - удалять ли расширение файла
     * @return string
     */
    static public function fileName(string $path, bool $removeExt = false): string {
        $path = basename($path);
        if ($removeExt === true) {
            $pathLst = explode('.', $path);
            if (count($pathLst) > 1)
                unset($pathLst[count($pathLst) - 1]);
            return implode('.', $pathLst);
        }
        return $path;
    }

    /**
     * Получить время последнего изменения файла
     * @param string $path - путь до файла
     * @return int
     *
     * NOTE: If file not found or select last modified failed - return -1.
     */
    static public function fileLastModified(string $path): int {
        if (empty($path) || !file_exists($path))
            return -1;
        // get file last modified
        $fLastModified = filemtime($path);
        if ($fLastModified === false)
            $fLastModified = -1;
        return $fLastModified;
    }

    /**
     * Обрезать символ вначале
     * @param string $str - строка
     * @param string $symbol - удаляемый символ
     * @return string
     *
     * echo spliceSymbolFirst("/tmp/app1/", "/");
     *   => "tmp/app1/"
     */
    static public function spliceSymbolFirst(string $str, string $symbol): string {
        if (empty($str) || empty($symbol))
            return $str;
        if (strlen($symbol) > 1)
            $symbol = $symbol[0];
        if (strcmp($str[0], $symbol) === 0) {
            if (strlen($str) > 1) {
                $str = ltrim($str, $symbol);
                $str = CoreHelper::spliceSymbolFirst($str, $symbol);
            } else {
                $str = "";
            }
        }
        return $str;
    }

    /**
     * Обрезать символ вконце
     * @param string $str - строка
     * @param string $symbol - удаляемый символ
     * @return string
     *
     * echo spliceSymbolLast("/tmp/app1/", "/");
     *   => "/tmp/app1"
     */
    static public function spliceSymbolLast(string $str, string $symbol): string {
        if (empty($str) || empty($symbol))
            return $str;
        if (strlen($symbol) > 1)
            $symbol = $symbol[0];
        if (strcmp($str[strlen($str) - 1], $symbol) === 0) {
            if (strlen($str) > 1) {
                $str = substr($str, 0, -1);
                $str = CoreHelper::spliceSymbolLast($str, $symbol);
            } else {
                $str = "";
            }
        }
        return $str;
    }

    /**
     * Обрезать путь вначале (исключить '/')
     * @param string $path - путь
     * @return string
     *
     * echo splicePathFirst("/tmp/app1/");
     *   => "tmp/app1/"
     */
    static public function splicePathFirst(string $path): string {
        return self::spliceSymbolFirst($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Обрезать путь вконце (исключить '/')
     * @param string $path - путь
     * @return string
     *
     * echo splice_url_first("/tmp/app1/");
     *   => "/tmp/app1"
     */
    static public function splicePathLast(string $path): string {
        return self::spliceSymbolLast($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Удалить экранирование текста
     * @param string $str
     * @param string $quote
     * @return string
     */
    static public function removeQuote(string $str, string $quote = "\""): string {
        if (empty($str))
            return $str;
        if (strcmp($str[0], $quote) === 0) {
            if (strlen($str) > 1) {
                $str = ltrim($str, $quote);
                $str = CoreHelper::removeQuote($str, $quote);
            } else {
                $str = "";
            }
        } else if (strcmp($str[strlen($str) - 1], $quote) === 0) {
            if (strlen($str) > 1) {
                $str = substr($str, 0, -1);
                $str = CoreHelper::removeQuote($str, $quote);
            } else {
                $str = "";
            }
        }
        return $str;
    }

    /**
     * Получить валидную строку адреса (с App-Url-Prefix, если задан)
     * @param string $uri - строка URL
     * @return string
     *
     * echo make_valid_url("/api/test_api");
     *   if url-prefix not set => "/api/test_api"
     *   if url-prefix set ("/app1") => "/app1/api/test_api"
     */
    static public function makeValidUrl(string $uri): string {
        return RouteCollector::makeValidUrl($uri);
    }

    /**
     * Обрезать url строку вначале (исключить '/')
     * @param string $uri - строка URL
     * @return string
     *
     * echo splice_url_first("/app1/");
     *   => "app1/"
     */
    static public function spliceUrlFirst(string $uri): string {
        return self::spliceSymbolFirst($uri, "/");
    }

    /**
     * Обрезать url строку вконце (исключить '/')
     * @param string $uri - строка URL
     * @return string
     *
     * echo splice_url_first("/app1/");
     *   => "/app1"
     */
    static public function spliceUrlLast(string $uri): string {
        return self::spliceSymbolLast($uri, "/");
    }

    /**
     * Пребразовать значение в bool
     * @param bool|int|string $val
     * @return bool
     */
    static public function toBool($val): bool {
        if (is_bool($val)) {
            return $val;
        } elseif (is_numeric($val)) {
            return ($val == 1);
        } elseif (is_string($val)) {
            $val = trim(strtolower($val));
            if (strcmp($val, "true") === 0
                || strcmp($val, "1") === 0)
                return true;
            return false;
        }
        return false;
    }

    /**
     * Преобразовать значение bool в строку
     * @param bool|int|string $val
     * @return string
     */
    static public function boolToStr($val): string {
        return CoreHelper::toBool($val) ? "true" : "false";
    }

    /**
     * Сгенерировать UUID
     * @return string
     */
    static public function uuid(): string {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Метод проверки является ли массив списком
     * @param array $arr
     * @return bool
     */
    static public function arrayIsList(array $arr): bool {
        return $arr === [] || (array_keys($arr) === range(0, count($arr) - 1));
    }

    /**
     * Метод конвертации в Base64 для дальнейшего корректного экранирования методом urlencode
     * @param string $string
     * @return string
     */
    static public function base64_url_encode(string $string): string {
        return strtr(base64_encode($string), '+/=', '._-');
    }

    /**
     * Метод конвертации из Base64 после распаковки методом urldecode
     * @param string $string
     * @param bool $strict
     * @return string|false
     */
    static public function base64_url_decode(string $string, bool $strict = false)/*: string|false*/ {
        return base64_decode(strtr($string, '._-', '+/='), $strict);
    }

    /**
     * Выполнение команды консоли
     * @param string $cmd
     * @param $stdOut
     * @param string $stdErr
     * @param bool $selectStdErr
     * @return false|int
     */
    static public function execCmd(string $cmd,
                                   /*string*/ &$stdOut,
                                   /*string*/ &$stdErr = "",
                                   bool $selectStdErr = true) {
        // clear in/out args
        $stdOut = "";
        $stdErr = "";
        // check
        if (empty($cmd))
            return false;

        $descriptorSpec = [
            0 => [ "pipe", "r" ], // STDIN
            1 => [ "pipe", "w" ]  // STDOUT
        ];
        if ($selectStdErr)
            $descriptorSpec[2] = [ "pipe", "w" ]; // STDERR

        $cwd = getcwd();
        $env = null;
        $proc = proc_open($cmd, $descriptorSpec, $pipes, $cwd, $env);
        if (!is_resource($proc))
            return false;

        // select output
        $stdOut = stream_get_contents($pipes[1]);
        if ($selectStdErr)
            $stdErr = stream_get_contents($pipes[2]);

        return proc_close($proc);
    }

    /**
     * Проверка, укладывается ли число в размер uint8
     * @param mixed $value
     * @return bool
     */
    static public function isUInt8($value): bool {
        return (floatval($value) >= self::UINT_8_MIN
                && floatval($value) <= self::UINT_8_MAX);
    }

    /**
     * Проверка, укладывается ли число в размер uint16
     * @param mixed $value
     * @return bool
     */
    static public function isUInt16($value): bool {
        return (!self::isUInt8($value)
                && floatval($value) >= self::UINT_16_MIN
                && floatval($value) <= self::UINT_16_MAX);
    }

    /**
     * Проверка, укладывается ли число в размер uint32
     * @param mixed $value
     * @return bool
     */
    static public function isUInt32($value): bool {
        return (!self::isUInt8($value)
                && !self::isUInt16($value)
                && floatval($value) >= self::UINT_32_MIN
                && floatval($value) <= self::UINT_32_MAX);
    }

    /**
     * Проверка, укладывается ли число в размер uint64
     * @param mixed $value
     * @return bool
     */
    static public function isUInt64($value): bool {
        return (!self::isUInt8($value)
                && !self::isUInt16($value)
                && !self::isUInt32($value)
                && floatval($value) >= self::UINT_64_MIN
                && floatval($value) <= self::UINT_64_MAX);
    }

    /**
     * Проверка, укладывается ли число в размер bigUInt (uint64)
     * @param mixed $value
     * @return bool
     */
    static public function isBigUInt($value): bool {
        return self::isUInt64($value);
    }

    /**
     * Проверка, укладывается ли число в размер int8
     * @param mixed $value
     * @return bool
     */
    static public function isInt8($value): bool {
        return (floatval($value) >= self::INT_8_MIN
                && floatval($value) <= self::INT_8_MAX);
    }

    /**
     * Проверка, укладывается ли число в размер int16
     * @param mixed $value
     * @return bool
     */
    static public function isInt16($value): bool {
        return (!self::isInt8($value)
                && floatval($value) >= self::INT_16_MIN
                && floatval($value) <= self::INT_16_MAX);
    }

    /**
     * Проверка, укладывается ли число в размер int32
     * @param mixed $value
     * @return bool
     */
    static public function isInt32($value): bool {
        return (!self::isInt8($value)
                && !self::isInt16($value)
                && floatval($value) >= self::INT_32_MIN
                && floatval($value) <= self::INT_32_MAX);
    }

    /**
     * Проверка, укладывается ли число в размер int64
     * @param mixed $value
     * @return bool
     */
    static public function isInt64($value): bool {
        return (!self::isInt8($value)
                && !self::isInt16($value)
                && !self::isInt32($value)
                && floatval($value) >= self::INT_64_MIN
                && floatval($value) <= self::INT_64_MAX);
    }

    /**
     * Проверка, укладывается ли число в размер bigInt (int64)
     * @param mixed $value
     * @return bool
     */
    static public function isBigInt($value): bool {
        return self::isInt64($value);
    }

    /**
     * Проверка типа числа
     * @param mixed $value
     * @return int
     */
    static public function numberType($value): int {
        if (self::isInt8($value))
            return self::TYPE_NUMBER_INT_8;
        else if (self::isUInt8($value))
            return self::TYPE_NUMBER_UINT_8;
        else if (self::isInt16($value))
            return self::TYPE_NUMBER_INT_16;
        else if (self::isUInt16($value))
            return self::TYPE_NUMBER_UINT_16;
        else if (self::isInt32($value))
            return self::TYPE_NUMBER_INT_32;
        else if (self::isUInt32($value))
            return self::TYPE_NUMBER_UINT_32;
        else if (self::isInt64($value))
            return self::TYPE_NUMBER_INT_64;
        else if (self::isUInt64($value))
            return self::TYPE_NUMBER_UINT_64;

        return self::TYPE_NUMBER_INVALID;
    }

    /**
     * Перевод типа числа в строку
     * @param int $type
     * @return string
     */
    static public function numberTypeToStr(int $type): string {
        switch ($type) {
            case self::TYPE_NUMBER_UINT_8:
                return "UInt8";
            case self::TYPE_NUMBER_UINT_16;
                return "UInt16";
            case self::TYPE_NUMBER_UINT_32;
                return "UInt32";
            case self::TYPE_NUMBER_UINT_64;
                return "UInt64";
            case self::TYPE_NUMBER_INT_8;
                return "Int8";
            case self::TYPE_NUMBER_INT_16;
                return "Int16";
            case self::TYPE_NUMBER_INT_32;
                return "Int32";
            case self::TYPE_NUMBER_INT_64;
                return "Int64";
            default:
                break;
        }
        return "Invalid";
    }

    /**
     * Перевод типа числа из строки в число
     * @param string $str
     * @return int
     */
    static public function numberTypeFromStr(string $str): int {
        $str = strtolower(trim($str));
        if (strcmp($str, 'uint8') == 0)
            return self::TYPE_NUMBER_UINT_8;
        else if (strcmp($str, 'uint16') == 0)
            return self::TYPE_NUMBER_UINT_16;
        else if (strcmp($str, 'uint32') == 0)
            return self::TYPE_NUMBER_UINT_32;
        else if (strcmp($str, 'uint64') == 0)
            return self::TYPE_NUMBER_UINT_64;
        else if (strcmp($str, 'int8') == 0)
            return self::TYPE_NUMBER_INT_8;
        else if (strcmp($str, 'int16') == 0)
            return self::TYPE_NUMBER_INT_16;
        else if (strcmp($str, 'int32') == 0)
            return self::TYPE_NUMBER_INT_32;
        else if (strcmp($str, 'int64') == 0)
            return self::TYPE_NUMBER_INT_64;

        return self::TYPE_NUMBER_INVALID;
    }
}