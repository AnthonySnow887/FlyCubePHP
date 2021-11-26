<?php

namespace FlyCubePHP\Network;

include_once 'HttpResponse.php';

class HttpClient
{
    /**
     * Send a POST request using cURL
     * @param string $url - URL to request
     * @param array $data - Values to send
     * @param int $timeoutSec - Time out in seconds
     * @param array $httpHeaders - Additional HTTP headers
     * @param array $curlOptions - Additional options for cURL
     * @return HttpResponse
     */
    static public function curlPost(string $url,
                                    array $data = [],
                                    int $timeoutSec = 5,
                                    array $httpHeaders = [],
                                    array $curlOptions = []): HttpResponse {
        $defaults = array(
            CURLOPT_POST => true,
            CURLOPT_HEADER => 1,
            CURLOPT_URL => $url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_HTTPHEADER => $httpHeaders,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_USERAGENT => "fly_cube_php/".FLY_CUBE_PHP_VERSION //'curl/7.60.0' //'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36'
        );

        $ch = curl_init();
        if ($ch === false)
            return new HttpResponse($url, 0, "", "", "", "Init cURL failed!");

        curl_setopt_array($ch, ($curlOptions + $defaults));
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

    /**
     * Send a GET request using cURL
     * @param string $url to request
     * @param array $data - Values to send
     * @param int $timeoutSec - Time out in seconds
     * @param array $httpHeaders - Additional HTTP headers
     * @param array $curlOptions - Additional options for cURL
     * @return HttpResponse
     */
    static public function curlGet(string $url,
                                   array $data = [],
                                   int $timeoutSec = 5,
                                   array $httpHeaders = [],
                                   array $curlOptions = []): HttpResponse {

        if (strpos($url, '?') === false && !empty($data))
            $url .= "?". http_build_query($data);
        else if (strpos($url, '?') !== false && !empty($data))
            $url .= http_build_query($data);

        $defaults = array(
            CURLOPT_URL => $url,
            CURLOPT_HEADER => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_HTTPHEADER => $httpHeaders,
            CURLOPT_USERAGENT => "fly_cube_php/".FLY_CUBE_PHP_VERSION
        );

        $ch = curl_init();
        if ($ch === false)
            return new HttpResponse($url, 0, "", "", "", "Init cURL failed!");

        curl_setopt_array($ch, ($curlOptions + $defaults));
        if (!$result = curl_exec($ch)) {
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