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

    public function url(): string {
        return $this->_url;
    }

    public function code(): int {
        return $this->_code;
    }

    public function isHttpInformation(): bool {
        return ($this->_code >= 100 && $this->_code < 200);
    }

    public function isHttpSuccess(): bool {
        return ($this->_code >= 200 && $this->_code < 300);
    }

    public function isHttpRedirection(): bool {
        return ($this->_code >= 300 && $this->_code < 400);
    }

    public function isHttpClientError(): bool {
        return ($this->_code >= 400 && $this->_code < 500);
    }

    public function isHttpServerError(): bool {
        return ($this->_code >= 500 && $this->_code < 600);
    }

    public function rawHeader(): string {
        return $this->_rawHeader;
    }

    public function hasHeaders(string $name): bool {
        return !empty($this->_headers);
    }

    public function headers(): array {
        return $this->_headers;
    }

    public function hasHeader(string $name): bool {
        return isset($this->_headers[$name]);
    }

    public function header(string $name)/*: mixed|null */ {
        $name = strtolower($name);
        if (isset($this->_headers[$name]))
            return $this->_headers[$name];

        return null;
    }

    public function hasCookie(): bool {
        return !empty($this->_cookie);
    }

    public function cookie(): array {
        return $this->_cookie;
    }

    public function hasCookieValue(string $key): bool {
        return isset($this->_cookie[$key]);
    }

    public function cookieValue(string $key)/*: mixed|null*/ {
        if (isset($this->_cookie[$key]))
            return $this->_cookie[$key];
        return null;
    }

    public function hasBody(): bool {
        return !empty($this->_body);
    }

    public function body(): string {
        return $this->_body;
    }

    public function contentType(): string {
        return $this->_contentType;
    }

    public function hasError(): bool {
        return !empty($this->_error);
    }

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
        return [ trim($cookieArray[0]) => trim($cookieArray[1]) ];
    }
}