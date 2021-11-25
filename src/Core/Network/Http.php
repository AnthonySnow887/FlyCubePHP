<?php

namespace FlyCubePHP\Core\Network;

class Http
{
    /**
     * Send a POST request using cURL
     * @param string $url to request
     * @param array $post values to send
     * @param array $options for cURL
     * @return array
     *
     * Response array keys:
     * - [string] url
     * - [int] code
     * - [string] header
     * - [string] body
     * - [string] content_type
     * - [bool] has_error
     * - [string] error_message
     */
    static public function curlPost(string $url, array $post = [], array $options = []): array {
        $defaults = array(
            CURLOPT_POST => true,
            CURLOPT_HEADER => 1,
            CURLOPT_URL => $url,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_POSTFIELDS => http_build_query($post),
            CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36'
        );

        $ch = curl_init();
        curl_setopt_array($ch, ($options + $defaults));
        if(!$result = curl_exec($ch)) {
            curl_close($ch);
            return [
                'url' => $url,
                'code' => 0,
                'header' => '',
                'body' => '',
                'content_type' => '',
                'has_error' => true,
                'error_message' => curl_error($ch)
            ];
        }
        $info = curl_getinfo($ch);
        $header = substr($result, 0, $info['header_size']);
        $body = substr($result, $info['header_size']);
        curl_close($ch);
        return [
            'url' => $info['url'],
            'code' => $info['http_code'],
            'header' => $header,
            'body' => $body,
            'content_type' => $info['content_type'],
            'has_error' => false,
            'error_message' => ''
        ];
    }

    /**
     * Send a GET request using cURL
     * @param string $url to request
     * @param array $get values to send
     * @param array $options for cURL
     * @return array
     *
     * Response array keys:
     * - [string] url
     * - [int] code
     * - [string] header
     * - [string] body
     * - [string] content_type
     * - [bool] has_error
     * - [string] error_message
     */
    static public function curlGet(string $url, array $get = [], array $options = []): array {
        $defaults = array(
            CURLOPT_URL => $url. (strpos($url, '?') === FALSE ? '?' : ''). http_build_query($get),
            CURLOPT_HEADER => 1,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/72.0.3626.121 Safari/537.36'
        );

        $ch = curl_init();
        curl_setopt_array($ch, ($options + $defaults));
        if (!$result = curl_exec($ch)) {
            curl_close($ch);
            return [
                'url' => $url,
                'code' => 0,
                'header' => '',
                'body' => '',
                'content_type' => '',
                'has_error' => true,
                'error_message' => curl_error($ch)
            ];
        }
        $info = curl_getinfo($ch);
        $header = substr($result, 0, $info['header_size']);
        $body = substr($result, $info['header_size']);
        curl_close($ch);
        return [
            'url' => $info['url'],
            'code' => $info['http_code'],
            'header' => $header,
            'body' => $body,
            'content_type' => $info['content_type'],
            'has_error' => false,
            'error_message' => ''
        ];
    }
}