<?php

namespace FlyCubePHP\Core\Routes;

//
// Raw data example:
// "------WebKitFormBoundarycpuJ7AJCw5FmD2Aj\r\nContent-Disposition: form-data; name":"\"output\"\r\n\r\njson\r\n\"name\"\r\n\r\nтестовое имя\r\n------WebKitFormBoundarycpuJ7AJCw5FmD2Aj--\r\n"
//

class RouteStreamParser
{
    private $_input;    //!< php raw input stream

    public function __construct() {
        $this->_input = file_get_contents('php://input');
    }

    /**
     * Метод разбора входных данных
     * @return array|array[]
     */
    public static function parseInputData(): array {
        $parser = new static();
        if (empty($parser->_input))
            return [ 'args'=>[], 'files'=>[] ];

        $boundary = $parser->formBoundary();
        if (empty($boundary)) {
            $data = [
                'args' => $parser->parseDataBody(),
                'files' => []
            ];
        } else {
            $blocks = $parser->selectDataBlocks($boundary);
            $data = $parser->parseDataBlocks($blocks);
        }
        return $data;
    }

    /**
     * Получить WebKitFormBoundary
     * @returns string
     */
    private function formBoundary(): string {
        if (!isset($_SERVER['CONTENT_TYPE']))
            return "";
        preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);
        if (!empty($matches))
            return $matches[1];
        return "";
    }

    /**
     * Разбор данных тела запроса
     * @returns array
     */
    private function parseDataBody(): array {
        if (!isset($_SERVER['CONTENT_TYPE']))
            return [];
        if (strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
            return json_decode(urldecode($this->_input), true);

        // NOTE! Не использовать parse_str($postData, $postArray),
        //       т.к. данный метод портит Base64 строки!

        $request = [];
        $requestData = urldecode($this->_input);
        if (!empty($requestData)) {
            $requestKeyValueArray = explode('&', $requestData);
            foreach ($requestKeyValueArray as $keyValue) {
                $keyValueArray = explode('=', $keyValue);
                if (count($keyValueArray) < 2) {
                    $request[] = $keyValue;
                } else {
                    $keyData = $keyValueArray[0];
                    $valueData = str_replace($keyData . "=", "", $keyValue);
                    if (preg_match('/(.*?)\[(.*?)\]/i', $keyData, $tmp)) {
                        if (empty($tmp)) {
                            $request[$keyData] = $valueData;
                        } else {
                            if (!isset($request[$tmp[1]]))
                                $request[$tmp[1]] = [];
                            $request[$tmp[1]][$tmp[2]] = $valueData;
                        }
                    } else {
                        $request[$keyData] = $valueData;
                    }
                }
            }
        }
        return $request;
    }

    /**
     * Получить тело запроса с данными
     * @param string $formBoundary
     * @return array
     */
    private function selectDataBlocks(string $formBoundary): array {
        $result = preg_split("/-+$formBoundary/", $this->_input);
        array_pop($result);
        return $result;
    }

    /**
     * Разобрать тело запроса
     * @param array $dataBlocks
     * @return array|array[]
     */
    private function parseDataBlocks(array $dataBlocks): array {
        $results = [
            'args' => [],
            'files' => []
        ];

        foreach ($dataBlocks as $value) {
            if (empty($value))
                continue;
            $block = $this->parseDataBlock($value);
            if (count($block['args']) > 0)
                array_push($results['args'], $block['args']);
            if (count($block['files']) > 0)
                array_push($results['files'], $block['files']);
        }
        return $this->resultToSimpleForm($results);
    }

    /**
     * Разобрать тело блока
     * @param string $string
     * @return array
     */
    private function parseDataBlock(string $string): array {
        if (strpos($string, 'application/octet-stream') !== false) {
            return [
                'args' => $this->parseFile($string),
                'files' => []
            ];
        }
        if (strpos($string, 'filename') !== false) {
            return [
                'args' => [],
                'files' => $this->parseFileStream($string)
            ];
        }
        return [
            'args' => $this->parseData($string),
            'files' => []
        ];
    }

    /**
     * Разбор тела файла
     * @param string $string
     * @return array
     */
    private function parseFile(string $string): array {
        preg_match('/name=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s', $string, $match);
        if (!empty($match))
            return [
                $match[1] => (isset($match[2]) && !empty($match[2]) ? $match[2] : '')
            ];

        return [];
    }

    /**
     * Рабор потока файла
     * @param string $string
     * @return array
     */
    private function parseFileStream(string $string): array {
        preg_match('/name=\"([^\"]*)\"; filename=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $string, $match);
        if (count($match) < 4)
            return [];
        preg_match('/Content-Type: (.*)?/', $match[3], $mime);
        if (count($mime) < 2)
            return [];
        $image = preg_replace('/Content-Type: (.*)[^\n\r]/', '', $match[3]);
        $path = sys_get_temp_dir().'/php'.substr(sha1(rand()), 0, 6);
        $err = file_put_contents($path, ltrim($image));
        if (preg_match('/^(.*)\[\]$/i', $match[1], $tmp))
            $index = $tmp[1];
        else
            $index = $match[1];

        $data = [];
        $data[$index]['name'][] = trim($match[2]);
        $data[$index]['type'][] = trim($mime[1]);
        $data[$index]['tmp_name'][] = $path;
        $data[$index]['error'][] = ($err === FALSE) ? $err : 0;
        $data[$index]['size'][] = filesize($path);
        return $data;
    }

    /**
     * Разбор данных тела одного блока
     * @param string $blockData
     * @return array
     */
    private function parseData(string $blockData): array {
        $data = [];
        preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $blockData, $match);
        if (empty($match))
            return [];
        if (preg_match('/(.*?)\[(.*?)\]/i', $match[1], $tmp)) {
            if (empty($tmp)) {
                $data[$match[1]] = (isset($match[2]) && !empty($match[2]) ? $match[2] : '');
            } else {
                $data[$tmp[1]] = [];
                $data[$tmp[1]][$tmp[2]] = (isset($match[2]) && !empty($match[2]) ? $match[2] : '');
            }
        } else {
            $data[$match[1]] = (isset($match[2]) && !empty($match[2]) ? $match[2] : '');
        }

//        if (preg_match('/^(.*)\[\]$/i', $match[1], $tmp))
//            $data[$tmp[1]][] = (isset($match[2]) && !empty($match[2]) ? $match[2] : '');
//        else
//            $data[$match[1]] = (isset($match[2]) && !empty($match[2]) ? $match[2] : '');

        return $data;
    }

    /**
     * Метод преобразования результата в корректный вид
     * @param array $data
     * @return array
     */
    private function resultToSimpleForm(array &$data): array {
        $results = [
            'args' => [],
            'files' => []
        ];

        foreach ($data['args'] as $dValue) {
            foreach ($dValue as $key => $val) {
                if (is_array($val)) {
                    if (!isset($results['args'][$key]))
                        $results['args'][$key] = [];
                    foreach ($val as $kk => $vv)
                        $results['args'][$key][$kk] = $vv;
                } else {
                    $results['args'][$key] = $val;
                }
            }
        }
        foreach ($data['files'] as $dKey => $dValue) {
            foreach ($dValue as $key => $val) {
                if (is_array($val)) {
                    foreach ($val as $kk => $vv) {
                        if (is_array($vv) && (count($vv) === 1))
                            $results['files'][$key][$kk] = $vv[0];
                        else
                            $results['files'][$key][$kk][] = $vv[0];
                    }
                } else {
                    $results['files'][$key][$dKey] = $val;
                }
            }
        }
        return $results;
    }
}