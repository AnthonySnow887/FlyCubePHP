<?php

/**
 * Examples of possible User Agent values:
 * curl/7.60.0
 * Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36
 */

namespace FlyCubePHP\Network;

include_once 'HttpResponse.php';

class HttpClient
{
    /**
     * Send a GET request using cURL
     * @param string $url to request
     * @param array $data - Values to send
     * @param int $timeoutSec - Time out in seconds
     * @param array $httpHeaders - Additional HTTP headers
     * @param array $cookie - Additional Cookie
     * @param array $curlOptions - Additional options for cURL
     * @return HttpResponse
     *
     * ==== Example
     *
     * $headers = [
     *    'Cache-Control' => 'no-cache',
     *    'X-CSRF-Token' => '6NDgVeXlsIWlY/0R341nqfvtB9JAriJmFbO22ECbwdcyVnODXHHA8XEnqtNBE/DFZG0/AiHNI1Rtm+1v6ecXQA=='
     * ];
     * $cookie = [
     *    'PHPSESSID' => '5bkcqrv8gtslqmdiui4aghg47q'
     * ];
     * $data = [
     *    'id' => 123,
     *    'name' => 'test'
     * ];
     * $res = \FlyCubePHP\Network\HttpClient::curlGet('http://127.0.0.1:8080/test_get', $data, 5, $headers, $cookie);
     * var_dump($res);
     */
    static public function curlGet(string $url,
                                   array $data = [],
                                   int $timeoutSec = 5,
                                   array $httpHeaders = [],
                                   array $cookie = [],
                                   array $curlOptions = []): HttpResponse {

        if (strpos($url, '?') === false && !empty($data))
            $url .= "?". http_build_query($data);
        else if (strpos($url, '?') !== false && !empty($data))
            $url .= http_build_query($data);

        $tmpCookie = self::prepareCookie($cookie);
        if (!empty($tmpCookie))
            $httpHeaders = array_merge($httpHeaders, [ 'Cookie' => $tmpCookie ]);

        $defaults = array(
            CURLOPT_URL => $url,
            CURLOPT_HEADER => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_HTTPHEADER => self::prepareHeaders($httpHeaders),
            CURLOPT_USERAGENT => "fly_cube_php/".FLY_CUBE_PHP_VERSION
        );
        return self::execRequest($url, ($curlOptions + $defaults));
    }

    /**
     * Send a POST request using cURL
     * @param string $url - URL to request
     * @param array $data - Values to send
     * @param int $timeoutSec - Time out in seconds
     * @param array $httpHeaders - Additional HTTP headers
     * @param array $cookie - Additional Cookie
     * @param array $curlOptions - Additional options for cURL
     * @return HttpResponse
     *
     * ==== Example
     *
     * $headers = [
     *    'Cache-Control' => 'no-cache',
     *    'X-CSRF-Token' => '6NDgVeXlsIWlY/0R341nqfvtB9JAriJmFbO22ECbwdcyVnODXHHA8XEnqtNBE/DFZG0/AiHNI1Rtm+1v6ecXQA=='
     * ];
     * $cookie = [
     *    'PHPSESSID' => '5bkcqrv8gtslqmdiui4aghg47q'
     * ];
     * $data = [
     *    'id' => 123,
     *    'name' => 'test'
     * ];
     * $res = \FlyCubePHP\Network\HttpClient::curlPost('http://127.0.0.1:8080/test_post', $data, 5, $headers, $cookie);
     * var_dump($res);
     */
    static public function curlPost(string $url,
                                    array $data = [],
                                    int $timeoutSec = 5,
                                    array $httpHeaders = [],
                                    array $cookie = [],
                                    array $curlOptions = []): HttpResponse {
        $tmpCookie = self::prepareCookie($cookie);
        if (!empty($tmpCookie))
            $httpHeaders = array_merge($httpHeaders, [ 'Cookie' => $tmpCookie ]);

        $defaults = array(
            CURLOPT_POST => true,
            CURLOPT_HEADER => 1,
            CURLOPT_URL => $url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_HTTPHEADER => self::prepareHeaders($httpHeaders),
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_USERAGENT => "fly_cube_php/".FLY_CUBE_PHP_VERSION
        );
        return self::execRequest($url, ($curlOptions + $defaults));
    }

    /**
     * Send a PUT request using cURL
     * @param string $url - URL to request
     * @param array $data - Values to send
     * @param int $timeoutSec - Time out in seconds
     * @param array $httpHeaders - Additional HTTP headers (key - value array)
     * @param array $cookie - Additional Cookie
     * @param array $curlOptions - Additional options for cURL
     * @return HttpResponse
     *
     * ==== Example
     *
     * $headers = [
     *    'Cache-Control' => 'no-cache',
     *    'X-CSRF-Token' => '6NDgVeXlsIWlY/0R341nqfvtB9JAriJmFbO22ECbwdcyVnODXHHA8XEnqtNBE/DFZG0/AiHNI1Rtm+1v6ecXQA=='
     * ];
     * $cookie = [
     *    'PHPSESSID' => '5bkcqrv8gtslqmdiui4aghg47q'
     * ];
     * $data = [
     *    'id' => 123,
     *    'name' => 'test'
     * ];
     * $res = \FlyCubePHP\Network\HttpClient::curlPut('http://127.0.0.1:8080/test_put', $data, 5, $headers, $cookie);
     * var_dump($res);
     */
    static public function curlPut(string $url,
                                   array $data = [],
                                   int $timeoutSec = 5,
                                   array $httpHeaders = [],
                                   array $cookie = [],
                                   array $curlOptions = []): HttpResponse {
        $tmpCookie = self::prepareCookie($cookie);
        if (!empty($tmpCookie))
            $httpHeaders = array_merge($httpHeaders, [ 'Cookie' => $tmpCookie ]);

        $defaults = array(
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_HEADER => 1,
            CURLOPT_URL => $url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_HTTPHEADER => self::prepareHeaders($httpHeaders),
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_USERAGENT => "fly_cube_php/".FLY_CUBE_PHP_VERSION
        );
        return self::execRequest($url, ($curlOptions + $defaults));
    }

    /**
     * Send a PATCH request using cURL
     * @param string $url - URL to request
     * @param array $data - Values to send
     * @param int $timeoutSec - Time out in seconds
     * @param array $httpHeaders - Additional HTTP headers
     * @param array $cookie - Additional Cookie
     * @param array $curlOptions - Additional options for cURL
     * @return HttpResponse
     *
     * ==== Example
     *
     * $headers = [
     *    'Cache-Control' => 'no-cache',
     *    'X-CSRF-Token' => '6NDgVeXlsIWlY/0R341nqfvtB9JAriJmFbO22ECbwdcyVnODXHHA8XEnqtNBE/DFZG0/AiHNI1Rtm+1v6ecXQA=='
     * ];
     * $cookie = [
     *    'PHPSESSID' => '5bkcqrv8gtslqmdiui4aghg47q'
     * ];
     * $data = [
     *    'id' => 123,
     *    'name' => 'test'
     * ];
     * $res = \FlyCubePHP\Network\HttpClient::curlPatch('http://127.0.0.1:8080/test_patch', $data, 5, $headers, $cookie);
     * var_dump($res);
     */
    static public function curlPatch(string $url,
                                     array $data = [],
                                     int $timeoutSec = 5,
                                     array $httpHeaders = [],
                                     array $cookie = [],
                                     array $curlOptions = []): HttpResponse {
        $tmpCookie = self::prepareCookie($cookie);
        if (!empty($tmpCookie))
            $httpHeaders = array_merge($httpHeaders, [ 'Cookie' => $tmpCookie ]);

        $defaults = array(
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_HEADER => 1,
            CURLOPT_URL => $url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_HTTPHEADER => self::prepareHeaders($httpHeaders),
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_USERAGENT => "fly_cube_php/".FLY_CUBE_PHP_VERSION
        );
        return self::execRequest($url, ($curlOptions + $defaults));
    }

    /**
     * Send a PATCH request using cURL
     * @param string $url - URL to request
     * @param array $data - Values to send
     * @param int $timeoutSec - Time out in seconds
     * @param array $httpHeaders - Additional HTTP headers
     * @param array $cookie - Additional Cookie
     * @param array $curlOptions - Additional options for cURL
     * @return HttpResponse
     *
     * ==== Example
     *
     * $headers = [
     *    'Cache-Control' => 'no-cache',
     *    'X-CSRF-Token' => '6NDgVeXlsIWlY/0R341nqfvtB9JAriJmFbO22ECbwdcyVnODXHHA8XEnqtNBE/DFZG0/AiHNI1Rtm+1v6ecXQA=='
     * ];
     * $cookie = [
     *    'PHPSESSID' => '5bkcqrv8gtslqmdiui4aghg47q'
     * ];
     * $data = [
     *    'id' => 123,
     * ];
     * $res = \FlyCubePHP\Network\HttpClient::curlDelete('http://127.0.0.1:8080/test_delete', $data, 5, $headers, $cookie);
     * var_dump($res);
     */
    static public function curlDelete(string $url,
                                      array $data = [],
                                      int $timeoutSec = 5,
                                      array $httpHeaders = [],
                                      array $cookie = [],
                                      array $curlOptions = []): HttpResponse {
        $tmpCookie = self::prepareCookie($cookie);
        if (!empty($tmpCookie))
            $httpHeaders = array_merge($httpHeaders, [ 'Cookie' => $tmpCookie ]);

        $defaults = array(
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HEADER => 1,
            CURLOPT_URL => $url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_HTTPHEADER => self::prepareHeaders($httpHeaders),
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_USERAGENT => "fly_cube_php/".FLY_CUBE_PHP_VERSION
        );
        return self::execRequest($url, ($curlOptions + $defaults));
    }

    static private function prepareHeaders(array $httpHeaders): array {
        $headers = [];
        foreach ($httpHeaders as $key => $value)
            $headers[] = trim($key) . ": " . trim($value);
        return $headers;
    }

    static private function prepareCookie(array $cookie): string {
        $cookieOut = "";
        foreach ($cookie as $key => $value) {
            if (empty($cookieOut))
                $cookieOut = trim($key) . "=" . trim($value);
            else
                $cookieOut .= "; " . trim($key) . "=" . trim($value);
        }
        return $cookieOut;
    }

    /**
     * Execute request using cURL
     * @param string $url - URL to request
     * @param array $options - Options for cURL
     * @return HttpResponse
     */
    static private function execRequest(string $url, array $options): HttpResponse {
        $ch = curl_init();
        if ($ch === false)
            return new HttpResponse($url, 0, "", "", "", "Init cURL failed!");

        curl_setopt_array($ch, $options);
        if(!$result = curl_exec($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return new HttpResponse($url, 0, "", "", "", $error);
        }
        $info = curl_getinfo($ch);
        $header = substr($result, 0, $info['header_size']);
        $body = substr($result, $info['header_size']);
        curl_close($ch);
        $contentType = $info['content_type'] == null ? "" : $info['content_type'];
        return new HttpResponse($info['url'], $info['http_code'], $header, $body, $contentType);
    }
}