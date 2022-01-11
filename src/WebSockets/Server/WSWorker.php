<?php

/*
FlyCubePHP WebSockets based on the code and idea described in morozovsk/websocket.
https://github.com/morozovsk/websocket
Released under the MIT license
*/

namespace FlyCubePHP\WebSockets\Server;

class WSWorker
{
    const SOCKET_BUFFER_SIZE        = 1024;
    const MAX_SOCKET_BUFFER_SIZE    = 10240;
    const MAX_SOCKETS               = 1000;
    const SOCKET_MESSAGE_DELIMITER  = "\n";

    private $_clients = array();
    private $_server = null;
//    protected $_service = null;  // TODO ???
//    protected $_master = null;  // TODO ???
    private $_read = array();  //read buffers
    private $_write = array(); //write buffers
    private $_pid = -1;
    private $_handshakes = array();
    private $_timer = null;
    private $_timerSec = 1;

    function __construct($server)
    {
        $this->_server = $server;
        $this->_pid = posix_getpid();
    }

    public function start() {
        // TODO write in log file 'worker PID started'
        echo "WSWorker started: " . $this->_pid . "\r\n";

        // --- create timer for send 'ping' to client ---
        $this->_timer = $this->_createTimer();

        while (true) {
            // prepare the array of sockets that need to be processed
            $read = $this->_clients /*+ $this->services*/;
            if ($this->_server)
                $read[] = $this->_server;

            // --- timer ticks ---
            $read[] = $this->_timer;

//            if ($this->_service) {
//                $read[] = $this->_service;
//            }
//
//            if ($this->_master) {
//                $read[] = $this->_master;
//            }
//


            if (!$read)
                return;

            $write = array();
            if ($this->_write) {
                foreach ($this->_write as $connectionId => $buffer) {
                    if ($buffer)
                        $write[] = $this->getConnectionById($connectionId);
                }
            }

            // --- exception array ---
            $except = $read;

            // --- select streams ---
            stream_select($read, $write, $except, null); // update the array of sockets that can be processed

//            // --- check is timer tick ---
//            if ($this->_timerSec && in_array($timer, $read)) {
//                unset($read[array_search($timer, $read)]);
//                fread($timer, self::SOCKET_BUFFER_SIZE);
//                $this->onTimer();
//            }

            if ($read) { //data were obtained from the connected clients
                foreach ($read as $client) {
                    if ($this->_timer == $client) {
                        // --- check is timer tick ---
                        fread($this->_timer, self::SOCKET_BUFFER_SIZE);
                        $this->onTimer();
                    } else if ($this->_server == $client) {
                        // --- check is new incoming connection ---
                        if ((count($this->_clients) /*+ count($this->services)*/ < self::MAX_SOCKETS)
                            && ($client = @stream_socket_accept($this->_server, 0))) {
                            stream_set_blocking($client, 0);
                            $clientId = $this->getIdByConnection($client);
                            $this->_clients[$clientId] = $client;
                            $this->_onOpen($clientId);
                        }
                    } /*elseif ($this->_service == $client) { //the local socket got a request from a new client
                        if ((count($this->clients) + count($this->services) < self::MAX_SOCKETS) && ($client = @stream_socket_accept($this->_service, 0))) {
                            stream_set_blocking($client, 0);
                            $clientId = $this->getIdByConnection($client);
                            $this->services[$clientId] = $client;
                            $this->onServiceOpen($clientId);
                        }
                    } */else {
                        // --- read new incoming data ---
                        $connectionId = $this->getIdByConnection($client);
                        /*if (in_array($client, $this->services)) {
                            if (is_null($this->_read($connectionId))) { //connection has been closed or the buffer was overflow
                                $this->close($connectionId);
                                continue;
                            }

                            while ($data = $this->_readFromBuffer($connectionId)) {
                                $this->onServiceMessage($connectionId, $data); //call user handler
                            }
                        } elseif ($this->_master == $client) {
                            if (is_null($this->_read($connectionId))) { //connection has been closed or the buffer was overflow
                                $this->close($connectionId);
                                continue;
                            }

                            while ($data = $this->_readFromBuffer($connectionId)) {
                                $this->onMasterMessage($data); //call user handler
                            }
                        } else {*/
                            if (!$this->_read($connectionId)) { // connection has been closed or the buffer was overwhelmed
                                $this->close($connectionId);
                                continue;
                            }
                            // --- process incoming message ---
                            $this->_onMessage($connectionId);
                        /*}*/
                    }
                }
            }

            if ($write) {
                foreach ($write as $client) {
                    if (is_resource($client)) // verify that the connection is not closed during the reading
                        $this->_sendBuffer($client);
                }
            }

            if ($except) {
                foreach ($except as $client)
                    $this->_onError($this->getIdByConnection($client));
            }
        }
    }

    protected function _onError($connectionId) {
        echo "[". $this->_pid . "] An error has occurred: $connectionId\n";
    }

    protected function _close($connectionId) {
        @fclose($this->getConnectionById($connectionId));
    }

    protected function _write($connectionId, $data, $delimiter = '') {
        @$this->_write[$connectionId] .=  $data . $delimiter;
    }

    protected function _sendBuffer($connect) {
        $connectionId = $this->getIdByConnection($connect);
        $written = fwrite($connect, $this->_write[$connectionId], self::SOCKET_BUFFER_SIZE);
        $this->_write[$connectionId] = substr($this->_write[$connectionId], $written);
    }

    protected function _readFromBuffer($connectionId) {
        $data = '';

        if (false !== ($pos = strpos($this->_read[$connectionId], self::SOCKET_MESSAGE_DELIMITER))) {
            $data = substr($this->_read[$connectionId], 0, $pos);
            $this->_read[$connectionId] = substr($this->_read[$connectionId], $pos + strlen(self::SOCKET_MESSAGE_DELIMITER));
        }

        return $data;
    }

    protected function _read($connectionId) {
        $data = fread($this->getConnectionById($connectionId), self::SOCKET_BUFFER_SIZE);

        if (!strlen($data))
            return 0;

        @$this->_read[$connectionId] .= $data;//add the data into the read buffer
        return strlen($this->_read[$connectionId]) < self::MAX_SOCKET_BUFFER_SIZE;
    }

    protected function _createTimer() {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $pid = pcntl_fork(); //create a fork
        if ($pid == -1) {
            // TODO write to log file
            die("error: pcntl_fork\r\n");
        } elseif ($pid) { // parent
            fclose($pair[0]);
            return $pair[1]; // one of the pair will be in the parent
        } else { // child process
            $chPid = posix_getpid();
            $chSid = posix_getsid(posix_getpid());
            echo "Timer PID: $chPid\r\n";
            echo "Timer SID: $chSid\r\n";
            if ($chSid < 0)
                exit;
            fclose($pair[1]);
            $parent = $pair[0]; // second of the pair will be in the child
            while (true) {
                fwrite($parent, '1');
                sleep($this->_timerSec);
                if (!file_exists("/proc/$chSid"))
                    exit;
            }
        }
    }

    protected function _onOpen($connectionId) {
        $this->_handshakes[$connectionId] = ''; // mark the connection that it needs a handshake
    }

    protected function _onMessage($connectionId) {
        if (isset($this->_handshakes[$connectionId])) {
            if ($this->_handshakes[$connectionId]) {//if the client has already made a handshake
                return;//then there does not need to read before sending the response from the server
            }

            if (!$this->_handshake($connectionId)) {
                $this->close($connectionId);
            }
        } else {
            while (($data = $this->_decode($connectionId)) && mb_check_encoding($data['payload'], 'utf-8')) {//decode buffer (there may be multiple messages)
                $this->onMessage($connectionId, $data['payload'], $data['type']);//call user handler
            }
        }
    }

    protected function close($connectionId) {
        if (isset($this->_handshakes[$connectionId])) {
            unset($this->_handshakes[$connectionId]);
        } elseif (isset($this->_clients[$connectionId])) {
            $this->onClose($connectionId); // call user handler
        } /*elseif (isset($this->services[$connectionId])) {
            $this->onServiceClose($connectionId);//call user handler
        } elseif ($this->getIdByConnection($this->_master) == $connectionId) {
            $this->onMasterClose($connectionId);//call user handler
        }*/

        $this->_close($connectionId);

        if (isset($this->_clients[$connectionId])) {
            unset($this->_clients[$connectionId]);
        } /*elseif (isset($this->services[$connectionId])) {
            unset($this->services[$connectionId]);
        } */elseif ($this->getIdByConnection($this->_server) == $connectionId) {
            $this->_server = null;
        } /*elseif ($this->getIdByConnection($this->_service) == $connectionId) {
            $this->_service = null;
        } elseif ($this->getIdByConnection($this->_master) == $connectionId) {
            $this->_master = null;
        }*/

        unset($this->_write[$connectionId]);
        unset($this->_read[$connectionId]);
    }

    protected function sendToClient($connectionId, $data, $type = 'text') {
        if (!isset($this->_handshakes[$connectionId])
            && isset($this->_clients[$connectionId]))
            $this->_write($connectionId, $this->_encode($data, $type));
    }

//    protected function sendToMaster($data) {
//        $this->_write($this->getIdByConnection($this->_master), $data, self::SOCKET_MESSAGE_DELIMITER);
//    }

//    protected function sendToService($connectionId, $data) {
//        $this->_write($connectionId, $data, self::SOCKET_MESSAGE_DELIMITER);
//    }

    protected function _handshake($connectionId) {
        //read the headers from the connection
        if (!strpos($this->_read[$connectionId], "\r\n\r\n"))
            return true;

        preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $this->_read[$connectionId], $match);
        if (empty($match[1]))
            return false;

        $headers = explode("\r\n", $this->_read[$connectionId]);
        $info = array();
        foreach ($headers as $header) {
            if (($explode = explode(':', $header)) && isset($explode[1])) {
                $info[trim($explode[0])] = trim($explode[1]);
            } elseif (($explode = explode(' ', $header)) && isset($explode[1])) {
                $info[$explode[0]] = $explode[1];
            }
        }

        /*$source = explode(':', stream_socket_get_name($this->clients[$connectionId], true));
        $info['Ip'] = $source[0];*/

        $this->_read[$connectionId] = '';

        var_dump($info); // TODO ...

        // TODO exec connection handler for check incoming connection
        // TODO if 'reject' -> close connection

        //send a header according to the protocol websocket
        $SecWebSocketAccept = base64_encode(pack('H*', sha1($match[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: {$SecWebSocketAccept}\r\n" .
            "Sec-WebSocket-Protocol: actioncable-v1-json\r\n\r\n";

        $this->_write($connectionId, $upgrade);
        unset($this->_handshakes[$connectionId]);

        $this->onOpen($connectionId, $info);
        return true;
    }

    protected function _encode($payload, $type = 'text'): string
    {
        $frameHead = array();
        $payloadLength = strlen($payload);

        switch ($type) {
            case 'text':
                // first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 129;
                break;

            case 'close':
                // first byte indicates FIN, Close Frame(10001000):
                $frameHead[0] = 136;
                break;

            case 'ping':
                // first byte indicates FIN, Ping frame (10001001):
                $frameHead[0] = 137;
                break;

            case 'pong':
                // first byte indicates FIN, Pong frame (10001010):
                $frameHead[0] = 138;
                break;
        }

        if ($payloadLength > 65535) {
            $ext = pack('NN', 0, $payloadLength);
            $secondByte = 127;
        } elseif ($payloadLength > 125) {
            $ext = pack('n', $payloadLength);
            $secondByte = 126;
        } else {
            $ext = '';
            $secondByte = $payloadLength;
        }

        return (chr($frameHead[0]) . chr($secondByte) . $ext . $payload);
    }

    protected function _decode($connectionId)/*: array:bool */
    {
        $data = $this->_read[$connectionId];
        if (strlen($data) < 2)
            return false;

        $unmaskedPayload = '';
        $decodedData = array();

        // estimate frame type:
        $firstByteBinary = sprintf('%08b', ord($data[0]));
        $secondByteBinary = sprintf('%08b', ord($data[1]));
        $opcode = bindec(substr($firstByteBinary, 4, 4));
        $isMasked = $secondByteBinary[0] == '1';
        $payloadLength = ord($data[1]) & 127;

        switch ($opcode) {
            // text frame:
            case 1:
                $decodedData['type'] = 'text';
                break;
            case 2:
                $decodedData['type'] = 'binary';
                break;
            // connection close frame:
            case 8:
                $decodedData['type'] = 'close';
                break;
            // ping frame:
            case 9:
                $decodedData['type'] = 'ping';
                break;
            // pong frame:
            case 10:
                $decodedData['type'] = 'pong';
                break;
            default:
                $decodedData['type'] = '';
        }

        if ($payloadLength === 126) {
            if (strlen($data) < 4)
                return false;
            $payloadOffset = 8;
            $dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
        } else if ($payloadLength === 127) {
            if (strlen($data) < 10)
                return false;
            $payloadOffset = 14;
            for ($tmp = '', $i = 0; $i < 8; $i++)
                $tmp .= sprintf('%08b', ord($data[$i + 2]));
            $dataLength = bindec($tmp) + $payloadOffset;
        } else {
            $payloadOffset = 6;
            $dataLength = $payloadLength + $payloadOffset;
        }

        if (strlen($data) < $dataLength)
            return false;
        else
            $this->_read[$connectionId] = substr($data, $dataLength);

        if ($isMasked) {
            if ($payloadLength === 126)
                $mask = substr($data, 4, 4);
            else if ($payloadLength === 127)
                $mask = substr($data, 10, 4);
            else
                $mask = substr($data, 2, 4);

            for ($i = $payloadOffset; $i < $dataLength; $i++) {
                $j = $i - $payloadOffset;
                if (isset($data[$i]))
                    $unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
            }
            $decodedData['payload'] = $unmaskedPayload;
        } else {
            $payloadOffset = $payloadOffset - 4;
            $decodedData['payload'] = substr($data, $payloadOffset, $dataLength - $payloadOffset);
        }
        return $decodedData;
    }

    protected function getConnectionById($connectionId) {
        if (isset($this->_clients[$connectionId])) {
            return $this->_clients[$connectionId];
        } /*elseif (isset($this->services[$connectionId])) {
            return $this->services[$connectionId];
        } */elseif ($this->getIdByConnection($this->_server) == $connectionId) {
            return $this->_server;
        } /*elseif ($this->getIdByConnection($this->_service) == $connectionId) {
            return $this->_service;
        } elseif ($this->getIdByConnection($this->_master) == $connectionId) {
            return $this->_master;
        }*/
    }

    protected function getIdByConnection($connection) {
        return intval($connection);
    }

    protected function onOpen($connectionId, $info) {
        $this->sendToClient($connectionId, "{\"type\":\"welcome\"}");
    }
    protected function onClose($connectionId) {
        echo "[". $this->_pid . "] Connection closed: $connectionId\r\n";
    }
    protected function onMessage($connectionId, $packet, $type) {
        var_dump($connectionId);
        var_dump($packet);
        var_dump($type);
        $data = json_decode($packet, true);
        if (isset($data['command']) && $data['command'] == 'subscribe') {
            // TODO exec channel handler and send 'confirm_subscription' or 'reject_subscription'
            // TODO if 'reject_subscription' -> close connection
            $sData = [
                'identifier' => $data['identifier'],
                'type' => 'confirm_subscription'
            ];
            $this->sendToClient($connectionId, json_encode($sData));
        }
    }

    protected function onServiceMessage($connectionId, $data) {}
    protected function onServiceOpen($connectionId) {}
    protected function onServiceClose($connectionId) {}

    protected function onMasterMessage($data) {}
    protected function onMasterClose($connectionId) {}

    protected function onTimer() {
        $sData = [
            'type' => 'ping',
            'message' => time()
        ];
        foreach ($this->_clients as $clientId => $client) {
            echo "[". $this->_pid . "] Send to: $clientId\r\n";
            $this->sendToClient($clientId, json_encode($sData));
        }
    }
}