<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 03.09.21
 * Time: 14:06
 */

namespace FlyCubePHP\Core\Controllers\Extensions;

include_once __DIR__.'/../../Error/ErrorController.php';
include_once __DIR__.'/../../Routes/RouteCollector.php';

use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;
use \FlyCubePHP\Core\Routes\RouteCollector as RouteCollector;
use \FlyCubePHP\Core\Error\ErrorController as ErrorController;

trait NetworkBase
{
    private $_used = false;

    /**
     * Отправить клиенту перенаправление на другой URL
     * @param array $options
     * @throws
     *
     * ==== Options
     *
     * - [int]      status      - Set redirect HTTP status code (default: 303)
     * - [string]   url         - Set redirect URL
     * - [string]   controller  - Set redirect application controller class name
     * - [string]   action      - Set redirect application controller action
     * - [bool]     exit        - Use exit() after send response (default: false)
     *
     * NOTE: 'url' and 'controller + action' are mutually exclusive arguments!
     */
    protected function redirect_to(array $options = []) {
        if ($this->_used === true)
            return;
        $this->_used = true;

        $status = 303;
        if (isset($options['status'])
            && intval($options['status']) >= 301
            && intval($options['status']) <= 303)
            $status = intval($options['status']);

        $useExit = false;
        if (isset($options['exit']) && $options['exit'] === true)
            $useExit = true;

        $url = "";
        if (isset($options['url'])
            && !isset($options['controller'])
            && !isset($options['action'])) {
            $url = trim($options['url']);
        } else if (!isset($options['url'])
            && isset($options['controller'])
            && isset($options['action'])) {
            $controller = strval($options["controller"]) . "Controller";
            $action = strval($options["action"]);
            $route = RouteCollector::instance()->routeByControllerAct($controller, $action);
            if (is_null($route))
                throw new ErrorController(__CLASS__, __FUNCTION__, "", "Not found needed controller with action: $controller::$action()", "controller-base");

            $url = $route->uri();
        }
        if (empty($url))
            throw new ErrorController(__CLASS__, __FUNCTION__, "", "Invalid redirect URL (empty)!", "controller-base");

        if (!preg_match("/^(http:\/\/|https:\/\/).*/", $url)) {
            $url = CoreHelper::makeValidUrl($url);
            $url = RouteCollector::currentHostUri() . $url;
        }

        if (ob_get_level())
            ob_end_clean();

        http_response_code($status);
        header("Location: $url", true, $status);
        if ($useExit === true)
            exit();
    }

    /**
     * Отправить клиенту перенаправление на предыдущий URL
     * @param array $options
     * @throws
     *
     * ==== Options
     *
     * - [int]      status     - Set redirect HTTP status code (default: 303)
     * - [string]   url        - Set redirect fallback URL
     * - [string]   controller - Set redirect fallback application controller class name
     * - [string]   action     - Set redirect fallback application controller action
     * - [bool]     exit       - Use exit() after send response (default: false)
     *
     * NOTE: 'url' and 'controller + action' are mutually exclusive arguments!
     */
    protected function redirect_back(array $options = []) {
        if ($this->_used === true)
            return;

        $url = "";
        $refererVal = RouteCollector::currentRouteHeader('Referer');
        if (!is_null($refererVal)) {
            $url = $refererVal;
        } else if (isset($options['url'])
            && !isset($options['controller'])
            && !isset($options['action'])) {
            $url = trim($options['url']);
        } else if (!isset($options['url'])
            && isset($options['controller'])
            && isset($options['action'])) {
            $controller = strval($options["controller"]) . "Controller";
            $action = strval($options["action"]);
            $route = RouteCollector::instance()->routeByControllerAct($controller, $action);
            if (is_null($route))
                throw new ErrorController(__CLASS__, __FUNCTION__, "", "Not found needed controller with action: $controller::$action()", "controller-base");

            $url = $route->uri();
        }

        if (isset($options['url']))
            unset($options['url']);
        if (isset($options['controller']))
            unset($options['controller']);
        if (isset($options['action']))
            unset($options['action']);

        if (!preg_match("/^(http:\/\/|https:\/\/).*/", $url)) {
            $url = CoreHelper::makeValidUrl($url);
            $url = RouteCollector::currentHostUri() . $url;
        }

        $options['url'] = $url;
        $this->redirect_to($options);
    }

    /**
     * Отправить клиенту только HTTP заголовки
     * @param array $options
     * @throws
     *
     * ==== Options
     *
     * - [int]      status     - Set HTTP status code (default: 200)
     * - [array]    headers    - Set additional response HTTP headers
     * - [bool]     exit       - Use exit() after send response (default: false)
     */
    protected function send_head(array $options = []) {
        if ($this->_used === true)
            return;
        $this->_used = true;

        $status = 200;
        if (isset($options['status'])
            && intval($options['status']) >= 100
            && intval($options['status']) <= 526)
            $status = intval($options['status']);

        $headers = [];
        if (isset($options['headers']) && is_array($options['headers']))
            $headers = $options['headers'];

        $useExit = false;
        if (isset($options['exit']) && $options['exit'] === true)
            $useExit = true;

        // --- send ---
        if (ob_get_level())
            ob_end_clean();

        // --- send ---
//        header($_SERVER["SERVER_PROTOCOL"] . " " . \FlyCubePHP\HelperClasses\HttpCodes::title($status), true, $status);
        http_response_code($status);
        foreach ($headers as $key => $value)
            header("$key: $value");

        if ($useExit === true)
            exit();
    }

    /**
     * Отправить ответ клиенту
     * @param array $options
     *
     * ==== Options
     *
     * - [int]      status         - Set response HTTP status code (default: 200)
     * - [string]   content-type   - Set response HTTP content type (default: text/html)
     * - [string]   encoding       - Set response HTTP encoding (default: utf-8)
     * - [array]    headers        - Set additional response HTTP headers
     * - [string]   body           - Set response body
     *
     * ==== Examples
     */
    protected function send_response(array $options = []) {
        if ($this->_used === true)
            return;
        $this->_used = true;

        $status = 200;
        if (isset($options['status'])
            && intval($options['status']) >= 100
            && intval($options['status']) <= 526)
            $status = intval($options['status']);

        $contentType = "text/html";
        if (isset($options['content-type']) && !empty(trim($options['content-type'])))
            $contentType = trim($options['content-type']);

        $encoding = "utf-8";
        if (isset($options['encoding']) && !empty(trim($options['encoding'])))
            $encoding = trim($options['encoding']);

        $headers = [];
        if (isset($options['headers']) && is_array($options['headers']))
            $headers = $options['headers'];

        $body = "";
        if (isset($options['body']))
            $body = strval($options['body']);

        // --- send ---
//        header($_SERVER["SERVER_PROTOCOL"] . " " . \FlyCubePHP\HelperClasses\HttpCodes::title($status));
        http_response_code($status);
        $cLength = false;
        foreach ($headers as $key => $value) {
            if (strcmp("content-type", strtolower(trim($key))) === 0)
                continue;
            if (strcmp("content-length", strtolower(trim($key))) === 0)
                $cLength = true;
            header("$key: $value");
        }
        if ($cLength === false)
            header("Content-Length: ".mb_strlen($body));

        header("Content-Type: $contentType; charset=".strtoupper($encoding));

        if (ob_get_level())
            ob_end_clean();

        echo $body;
    }

    /**
     * Отправить данные клиенту
     * @param array $options
     *
     * ==== Options
     *
     * - [int]      status         - Set response HTTP status code (default: 200)
     * - [string]   content-type   - Set response HTTP content type (default: get from mime-types)
     * - [string]   encoding       - Set response HTTP encoding (default: utf-8)
     * - [array]    headers        - Set additional response HTTP headers
     * - [string]   data           - Set response file data
     * - [string]   filename       - Set response file name
     *
     * ==== Examples
     */
    protected function send_response_data(array $options = []) {
        if ($this->_used === true)
            return;
        $this->_used = true;

        $status = 200;
        if (isset($options['status'])
            && intval($options['status']) >= 100
            && intval($options['status']) <= 526)
            $status = intval($options['status']);

        $filename = "";
        if (isset($options['filename']) && !empty(trim($options['filename'])))
            $filename = trim($options['filename']);

        $fileExt = pathinfo($filename, PATHINFO_EXTENSION);
        $contentType = \FlyCubePHP\HelperClasses\MimeTypes::mimeType($fileExt);
        if (isset($options['content-type']) && !empty(trim($options['content-type'])))
            $contentType = trim($options['content-type']);

//        $encoding = "utf-8";
//        if (isset($options['encoding']) && !empty(trim($options['encoding'])))
//            $encoding = trim($options['encoding']);

        $headers = [];
        if (isset($options['headers']) && is_array($options['headers']))
            $headers = $options['headers'];

        $data = "";
        if (isset($options['data']))
            $data = strval($options['data']);

        // --- send ---
//        header($_SERVER["SERVER_PROTOCOL"] . " " . \FlyCubePHP\HelperClasses\HttpCodes::title($status), true, $status);
        http_response_code($status);
        $cLength = false;
        foreach ($headers as $key => $value) {
            if (strcmp("content-type", strtolower(trim($key))) === 0
                || strcmp("content-transfer-encoding", strtolower(trim($key))) === 0
                || strcmp("accept-ranges", strtolower(trim($key))) === 0)
                continue;
            if (strcmp("content-length", strtolower(trim($key))) === 0)
                $cLength = true;
            header("$key: $value");
        }
        header("Accept-Ranges: bytes");

        if ($cLength === false)
            header("Content-Length: ".mb_strlen($data));

        header("Content-Type: $contentType");
        header("Content-Transfer-Encoding: binary");
        if (!empty($filename))
            header("Content-Disposition: attachment; filename=\"" . urlencode($filename) . "\"");

        if (ob_get_level())
            ob_end_clean();

        echo $data;
    }

    /**
     * Отправить файл клиенту
     * @param string $path - путь до файла
     * @param array $options
     * @throws
     *
     * ==== Options
     *
     * - [int]      status         - Set response HTTP status code (default: 200)
     * - [array]    headers        - Set additional response HTTP headers
     *
     * ==== Examples
     */
    protected function send_response_file(string $path, array $options = []) {
        if ($this->_used === true)
            return;
        $this->_used = true;

        if (!file_exists($path))
            throw new ErrorController(__CLASS__, __FUNCTION__, "", "File not found! Path: $path", "controller-base");
        if (!is_readable($path))
            throw new ErrorController(__CLASS__, __FUNCTION__, "", "File is not readable! Path: $path", "controller-base");

        $status = 200;
        if (isset($options['status'])
            && intval($options['status']) >= 100
            && intval($options['status']) <= 526)
            $status = intval($options['status']);

        $filename = basename($path);
        $fileExt = pathinfo($filename, PATHINFO_EXTENSION);
        $contentType = \FlyCubePHP\HelperClasses\MimeTypes::mimeType($fileExt);
        $headers = [];
        if (isset($options['headers']) && is_array($options['headers']))
            $headers = $options['headers'];

        // --- send ---
//        header($_SERVER["SERVER_PROTOCOL"] . " " . \FlyCubePHP\HelperClasses\HttpCodes::title($status), true, $status);
        http_response_code($status);
        foreach ($headers as $key => $value) {
            if (strcmp("content-type", strtolower(trim($key))) === 0
                || strcmp("content-transfer-encoding", strtolower(trim($key))) === 0
                || strcmp("accept-ranges", strtolower(trim($key))) === 0
                || strcmp("content-length", strtolower(trim($key))) === 0)
                continue;
            header("$key: $value");
        }
        header("Accept-Ranges: bytes");
        header("Content-Length: ".filesize($path));
        header("Content-Type: $contentType");
        header("Content-Transfer-Encoding: binary");
        header("Content-Disposition: attachment; filename=\"" . urlencode($filename) . "\"");

        if (ob_get_level())
            ob_end_clean();

        echo readfile($path);
    }

    /**
     * Отправить данные файла клиенту
     * @param string $path - путь до файла
     * @param array $options
     * @throws
     *
     * ==== Options
     *
     * - [int]      status         - Set response HTTP status code (default: 200)
     * - [array]    headers        - Set additional response HTTP headers
     *
     * ==== Examples
     */
    protected function send_response_file_data(string $path, array $options = []) {
        if ($this->_used === true)
            return;

        if (!file_exists($path))
            throw new ErrorController(__CLASS__, __FUNCTION__, "", "File not found! Path: $path", "controller-base");
        if (!is_readable($path))
            throw new ErrorController(__CLASS__, __FUNCTION__, "", "File is not readable! Path: $path", "controller-base");

        $status = 200;
        if (isset($options['status'])
            && intval($options['status']) >= 100
            && intval($options['status']) <= 526)
            $status = intval($options['status']);

        $filename = basename($path);
        $fileExt = pathinfo($filename, PATHINFO_EXTENSION);
        $contentType = \FlyCubePHP\HelperClasses\MimeTypes::mimeType($fileExt);
        $headers = [];
        if (isset($options['headers']) && is_array($options['headers']))
            $headers = $options['headers'];

        $data = file_get_contents($path);
        if ($data === false)
            throw new ErrorController(__CLASS__, __FUNCTION__, "", "Read file data failed! Path: $path", "controller-base");

        // --- send ---
        $this->send_response_data([
            'status' => $status,
            'content-type' => $contentType,
            'headers' => $headers,
            'data' => $data
        ]);
    }
}