<?php

namespace FlyCubePHP\Network;

use FlyCubePHP\Core\Routes\RouteCollector;

class HttpResponse
{
    private $_url = "";
    private $_code = 0;
    private $_rawHeader = "";
    private $_headers = [];
    private $_cookie = [];
    private $_body = "";
    private $_contentType = "";
    private $_error = "";

    function __construct(string $url,
                         int $code,
                         string $rawHeader,
                         string $body,
                         string $contentType,
                         string $error = "") {
        $this->_url = $url;
        $this->_code = $code;
        $this->_rawHeader = $rawHeader;
        $this->_body = $body;
        $pos = strpos($contentType, ';');
        if ($pos !== false)
            $contentType = substr($contentType, 0, $pos);
        $this->_contentType = $contentType;
        $this->_error = $error;
        $this->parseHeaders($rawHeader);
        if (isset($this->_headers['set-cookie'])) {
            $this->_cookie = $this->_headers['set-cookie'];
            unset($this->_headers['set-cookie']);
        }
    }

    /**
     * URL запроса
     * @return string
     */
    public function url(): string {
        return $this->_url;
    }

    /**
     * Код ответа http
     * @return int
     */
    public function code(): int {
        return $this->_code;
    }

    /**
     * Является ли ответ информацией (code >= 100 и code < 200)
     * @return bool
     */
    public function isHttpInformation(): bool {
        return ($this->_code >= 100 && $this->_code < 200);
    }

    /**
     * Является ли ответ успехом (code >= 200 и code < 300)
     * @return bool
     */
    public function isHttpSuccess(): bool {
        return ($this->_code >= 200 && $this->_code < 300);
    }

    /**
     * Является ли ответ перенаправлением (code >= 300 и code < 400)
     * @return bool
     */
    public function isHttpRedirection(): bool {
        return ($this->_code >= 300 && $this->_code < 400);
    }

    /**
     * Является ли ответ ошибкой клиента (code >= 400 и code < 500)
     * @return bool
     */
    public function isHttpClientError(): bool {
        return ($this->_code >= 400 && $this->_code < 500);
    }

    /**
     * Является ли ответ ошибкой сервера (code >= 500 и code <= 526)
     * @return bool
     */
    public function isHttpServerError(): bool {
        return ($this->_code >= 500 && $this->_code <= 526);
    }

    /**
     * Получить сырой заголовок http из ответа
     * @return string
     */
    public function rawHeader(): string {
        return $this->_rawHeader;
    }

    /**
     * Заданы ли заголовки http в ответе
     * @return bool
     */
    public function hasHeaders(): bool {
        return !empty($this->_headers);
    }

    /**
     * Получить заданные в ответе заголовки http
     * @return array
     */
    public function headers(): array {
        return $this->_headers;
    }

    /**
     * Задан ли в ответе требуемый заголовок
     * @param string $name
     * @return bool
     */
    public function hasHeader(string $name): bool {
        return isset($this->_headers[$name]);
    }

    /**
     * Получить значение заданного в ответе требуемого заголовка
     * @param string $name
     * @return mixed|null
     */
    public function header(string $name)/*: mixed|null */ {
        $name = strtolower($name);
        if (isset($this->_headers[$name]))
            return $this->_headers[$name];

        return null;
    }

    /**
     * Заданы ли в ответе cookie
     * @return bool
     */
    public function hasCookie(): bool {
        return !empty($this->_cookie);
    }

    /**
     * Получить заданные в ответе cookie
     * @return array
     */
    public function cookie(): array {
        return $this->_cookie;
    }

    /**
     * Задан ли в ответе требуемый cookie
     * @param string $key
     * @return bool
     */
    public function hasCookieValue(string $key): bool {
        return isset($this->_cookie[$key]);
    }

    /**
     * Получить значение заданного в ответе требуемого cookie
     * @param string $key
     * @return mixed|null
     */
    public function cookieValue(string $key)/*: mixed|null*/ {
        if (isset($this->_cookie[$key]))
            return $this->_cookie[$key];
        return null;
    }

    /**
     * Задано ли в ответе тело сообщения
     * @return bool
     */
    public function hasBody(): bool {
        return !empty($this->_body);
    }

    /**
     * Получить тело сообщения ответа
     * @return string
     */
    public function body(): string {
        return $this->_body;
    }

    /**
     * Получить Content-Type
     * @return string
     */
    public function contentType(): string {
        return $this->_contentType;
    }

    /**
     * Задана ли ошибка cURL?
     * @return bool
     */
    public function hasError(): bool {
        return !empty($this->_error);
    }

    /**
     * Получить ошибку cURL
     * @return string
     */
    public function error(): string {
        return $this->_error;
    }

    private function parseHeaders(string $rawHeaders) {
        $rawHeadersArray = explode(PHP_EOL, $rawHeaders);
        foreach ($rawHeadersArray as $rawHeader) {
            $header = preg_split('/:\s*/', $rawHeader);
            if ($header === false || count($header) < 2)
                continue;
            $headerName = strtolower(trim($header[0]));
            if (strcmp($headerName, 'set-cookie') === 0) {
                $tmpCookie = [];
                if (isset($this->_headers[$headerName]))
                    $tmpCookie = $this->_headers[$headerName];

                $tmpCookie = array_merge($tmpCookie, $this->parseCookie($header[1]));
                $this->_headers[$headerName] = $tmpCookie;
            } else {
                $this->_headers[$headerName] = trim($header[1]);
            }
        }
    }

    private function parseCookie(string $rawCookie): array {
        $rawCookie = trim($rawCookie);
        if (strpos($rawCookie, ";") !== FALSE) {
            $cookieArray = explode(";", $rawCookie);
            if (!isset($cookieArray[0]))
                return [];
            $rawCookie = trim($cookieArray[0]);
        }
        if (strpos($rawCookie, "=") === FALSE)
            return [];
        $cookieArray = explode("=", $rawCookie);
        if (!isset($cookieArray[0]) || !isset($cookieArray[1]))
            return [];
        return [ trim($cookieArray[0]) => urldecode(trim($cookieArray[1])) ];
    }
}