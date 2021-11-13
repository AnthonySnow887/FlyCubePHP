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

    public static function parseInputData(): array {
        $parser = new static();
        $boundary = $parser->formBoundary();
        if (!strlen($boundary)) {
            $data = [
                'post' => $parser->parseDataBody(),
                'file' => []
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
        if(!isset($_SERVER['CONTENT_TYPE']))
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
        $request = array();
        $requestData = urldecode($this->_input);
        if (!empty($requestData)) {
            $requestKeyValueArray = explode('&', $requestData);
            foreach ($requestKeyValueArray as $keyValue) {
                $keyValueArray = explode('=', $keyValue);
                $keyData = $keyValueArray[0];
                $valueData = str_replace($keyData . "=", "", $keyValue);
                $request[$keyData] = $valueData;
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
            'post' => [],
            'file' => []
        ];

        foreach($dataBlocks as $value) {
            if (empty($value))
                continue;

            $block = $this->parseDataBlock($value);
            if (count($block['post']) > 0)
                array_push($results['post'], $block['post']);
            if (count($block['file']) > 0)
                array_push($results['file'], $block['file']);
        }
        return $this->merge($results);
    }

    /**
     * @function decide
     * @param string $string
     * @returns array
     */
    private function parseDataBlock(string $string): array {
        if (strpos($string, 'application/octet-stream') !== false) {
            return [
                'post' => $this->parseFile($string),
                'file' => []
            ];
        }
        if (strpos($string, 'filename') !== false) {
            return [
                'post' => [],
                'file' => $this->parseFileStream($string)
            ];
        }

        return [
            'post' => $this->parseData($string),
            'file' => []
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
                $match[1] => (!empty($match[2]) ? $match[2] : '')
            ];

        return [];
    }

    /**
     * Рабор потока файла
     * @param string $string
     * @return array
     */
    private function parseFileStream(string $string): array {
        $data = [];

        preg_match('/name=\"([^\"]*)\"; filename=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $string, $match);
        preg_match('/Content-Type: (.*)?/', $match[3], $mime);

        $image = preg_replace('/Content-Type: (.*)[^\n\r]/', '', $match[3]);

        $path = sys_get_temp_dir().'/php'.substr(sha1(rand()), 0, 6);

        $err = file_put_contents($path, ltrim($image));

        if (preg_match('/^(.*)\[\]$/i', $match[1], $tmp)) {
            $index = $tmp[1];
        } else {
            $index = $match[1];
        }

        $data[$index]['name'][] = $match[2];
        $data[$index]['type'][] = $mime[1];
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
        if (preg_match('/^(.*)\[\]$/i', $match[1], $tmp))
            $data[$tmp[1]][] = (!empty($match[2]) ? $match[2] : '');
        else
            $data[$match[1]] = (!empty($match[2]) ? $match[2] : '');

        return $data;
    }

    /**
     * @function merge
     * @param $array array
     *
     * Ugly ugly ugly
     *
     * @returns array
     *
     * TODO WTF??? нахрена? переписать или вообще удалить!
     */
    private function merge($array)
    {
        $results = [
            'post' => [],
            'file' => []
        ];

        if (count($array['post']) > 0) {
            foreach($array['post'] as $key => $value) {
                foreach($value as $k => $v) {
                    if (is_array($v)) {
                        foreach($v as $kk => $vv) {
                            $results['post'][$k][] = $vv;
                        }
                    } else {
                        $results['post'][$k] = $v;
                    }
                }
            }
        }

        if (count($array['file']) > 0) {
            foreach($array['file'] as $key => $value) {
                foreach($value as $k => $v) {
                    if (is_array($v)) {
                        foreach($v as $kk => $vv) {
                            if(is_array($vv) && (count($vv) === 1)) {
                                $results['file'][$k][$kk] = $vv[0];
                            } else {
                                $results['file'][$k][$kk][] = $vv[0];
                            }
                        }
                    } else {
                        $results['file'][$k][$key] = $v;
                    }
                }
            }
        }

        return $results;
    }
}