<?php

/*
FlyCubePHP WebSockets based on the code and idea described in morozovsk/websocket.
https://github.com/morozovsk/websocket
Released under the MIT license
*/

namespace FlyCubePHP\WebSockets\Server;

use FlyCubePHP\Core\Logger\Logger;
use FlyCubePHP\Core\Session\Session;

class WSWorker
{
    const SOCKET_BUFFER_SIZE        = 1024;
    const MAX_SOCKET_BUFFER_SIZE    = 10240;
    const MAX_SOCKETS               = 1000;
    const SOCKET_MESSAGE_DELIMITER  = "\n";

    private $_appChannels = array();
    private $_currentClientId = null;
    private $_clients = array();
    private $_clientsInfo = array(); // headers, cookie, params, channel-id
    private $_clientsSubscribers = array(); // { 'ch-name': { client-id, client-id, ...} }
    private $_server = null;
    private $_controlSocket = null;
    private $_read = array();  // read buffers
    private $_write = array(); // write buffers
    private $_pid = -1;
    private $_handshakes = array();
    private $_timerSec = 1;
    private $_mountPath = "";
    private $_isEnabledPerform = true;

    function __construct(string $mountPath,
                         bool $isEnabledPerform,
                         array $appChannels,
                         $server,
                         $controlSocket)
    {
        $this->_mountPath = $mountPath;
        $this->_isEnabledPerform = $isEnabledPerform;
        $this->_appChannels = $appChannels;
        $this->_server = $server;
        $this->_controlSocket = $controlSocket;
        $this->_pid = posix_getpid();
    }

    function __destruct()
    {
        // --- checking that the WSWorker is stopped and not the timer fork ---
        if ($this->_pid == posix_getpid())
            $this->log(Logger::INFO, "WSWorker Stopped.");
    }

    /**
     * Метод запуска обработчика
     * @throws \FlyCubePHP\Core\Error\Error
     */
    public function start() {
        $chSid = posix_getsid($this->_pid);
        if ($chSid < 0) {
            $errMsg = "[". self::class ."] WSWorker Stopped. Invalid SID! PID: " . $this->_pid;
            Logger::error($errMsg);
            fwrite(STDERR, "$errMsg\r\n");
            die();
        }

        $infoMsg = "[". self::class ."] WSWorker Started. PID: " . $this->_pid;
        Logger::info($infoMsg);
        echo "$infoMsg\r\n";

        // --- create timer fork for send 'ping' to client ---
        $timer = $this->createTimer();

        // --- start worker loop ---
        while (true) {
            // --- check SID and exit if not found ---
            if (!file_exists("/proc/$chSid"))
                exit;

            // --- prepare the array of sockets that need to be processed ---
            $read = $this->_clients;
            if ($this->_server)
                $read[] = $this->_server;

            // --- timer ticks ---
            $read[] = $timer;

            // --- control from master ---
            $read[] = $this->_controlSocket;

            // --- check ---
            if (!$read)
                exit;

            $write = array();
            if ($this->_write) {
                foreach ($this->_write as $connectionId => $buffer) {
                    if ($buffer)
                        $write[] = $this->connectionById($connectionId);
                }
            }

            // --- exception array ---
            $except = $read;

            // --- select streams ---
            stream_select($read, $write, $except, null); // update the array of sockets that can be processed

            if ($read) { // data were obtained from the connected clients
                foreach ($read as $client) {
                    if ($timer == $client) {
                        // --- check is timer tick ---
                        $tData = fread($timer, self::SOCKET_BUFFER_SIZE);
                        // --- check if 'STOP' command ---
                        if (strcmp($tData, "STOP") === 0)
                            exit;
                        $this->onTimer();
                    } else if ($this->_server == $client) {
                        // --- check is new incoming connection ---
                        if ((count($this->_clients) < self::MAX_SOCKETS)
                            && ($client = @stream_socket_accept($this->_server, 0))) {
                            stream_set_blocking($client, 0);
                            $clientId = $this->idByConnection($client);
                            $this->_clients[$clientId] = $client;
                            $this->open($clientId);
                        }
                    } else {
                        // --- read new incoming data ---
                        $connectionId = $this->idByConnection($client);
                        if ($this->_controlSocket == $client) {
                            if (!$this->read($connectionId)) { // connection has been closed or the buffer was overflow or master is stopped
                                $this->close($connectionId);
                                exit; // stop worker loop
                            }
                            // --- process incoming master message ---
                            while ($data = $this->readFromBuffer($connectionId))
                                $this->onMasterMessage($data);
                        } else {
                            if (!$this->read($connectionId)) { // connection has been closed or the buffer was overwhelmed
                                $this->close($connectionId);
                                continue;
                            }
                            // --- process incoming message ---
                            $this->processMessage($connectionId);
                        }
                    }
                }
            }

            if ($write) {
                foreach ($write as $client) {
                    if (is_resource($client)) // verify that the connection is not closed during the reading
                        $this->sendBuffer($client);
                }
            }

            if ($except) {
                foreach ($except as $client)
                    $this->onError($this->idByConnection($client));
            }
        }
    }

    /**
     * Добавить запись о подписчике текущего соединения
     * @param string $channel
     * @param string $broadcasting
     */
    public function appendSubscriber(string $channel, string $broadcasting)
    {
        if (is_null($this->_currentClientId)
            || empty($channel)
            || empty($broadcasting))
            return;
        $tmpConnection = $this->connectionById($this->_currentClientId);
        if (is_null($tmpConnection))
            return;
        if (!isset($this->_clientsSubscribers[$broadcasting]))
            $this->_clientsSubscribers[$broadcasting] = [];
        $tmpLst = $this->_clientsSubscribers[$broadcasting];
        if (isset($tmpLst[$this->_currentClientId]))
            return;
        $tmpLst[$this->_currentClientId] = [
            'channel' => $channel,
            'stream_name' => $broadcasting,
            'identifier' => json_encode([ 'channel' => $channel, 'stream_name' => $broadcasting ]),
            'connection' => $tmpConnection
        ];
        $this->_clientsSubscribers[$broadcasting] = $tmpLst;
        $this->log(Logger::INFO, "$channel is streaming from $broadcasting (id:" . $this->_currentClientId . ")");
    }

    /**
     * Удалить запись о подписчике текущего соединения
     * @param string $broadcasting
     */
    public function removeSubscriber(string $broadcasting)
    {
        if (is_null($this->_currentClientId) || empty($broadcasting))
            return;
        if (!isset($this->_clientsSubscribers[$broadcasting]))
            return;
        $tmpLst = $this->_clientsSubscribers[$broadcasting];
        if (isset($tmpLst[$this->_currentClientId])) {
            $channelName = $tmpLst[$this->_currentClientId]['channel'];
            unset($tmpLst[$this->_currentClientId]);
            if (!empty($tmpLst))
                $this->_clientsSubscribers[$broadcasting] = $tmpLst;
            else
                unset($this->_clientsSubscribers[$broadcasting]);

            $this->log(Logger::INFO, "$channelName stopped streaming $broadcasting (id: " . $this->_currentClientId . ")");
        }
    }

    /**
     * Удалить записи о подписчиках всех соединений по названию канала
     * @param string $channel
     */
    public function removeSubscribersByChannel(string $channel)
    {
        if (empty($channel))
            return;
        foreach ($this->_clientsSubscribers as $broadcasting => $broadcastingInfo) {
            $keys = array_keys($broadcastingInfo);
            foreach ($keys as $key) {
                if (strcmp($broadcastingInfo[$key]['channel'], $channel) !== 0)
                    continue;
                unset($broadcastingInfo[$key]);
                $this->log(Logger::INFO, "$channel stopped streaming $broadcasting (id: $key)");
            }
            if (!empty($broadcastingInfo))
                $this->_clientsSubscribers[$broadcasting] = $broadcastingInfo;
            else
                unset($this->_clientsSubscribers[$broadcasting]);
        }
    }

    /**
     * Удалить запись об ИД подписчика
     * @param $connectionId
     */
    public function removeSubscriberId($connectionId)
    {
        foreach ($this->_clientsSubscribers as $key => $value) {
            if (isset($value[$connectionId])) {
                unset($value[$connectionId]);
                if (!empty($value))
                    $this->_clientsSubscribers[$key] = $value;
                else
                    unset($this->_clientsSubscribers[$key]);
            }
        }
    }

    /**
     * Получить список подписчиков
     * @param string $broadcasting
     * @return array
     */
    protected function subscribers(string $broadcasting): array
    {
        if (empty($broadcasting))
            return [];
        if (!isset($this->_clientsSubscribers[$broadcasting]))
            return [];
        return $this->_clientsSubscribers[$broadcasting];
    }

    /**
     * Чтение данных из сокета
     * @param $connectionId
     * @return bool|int
     */
    protected function read($connectionId) {
        $data = fread($this->connectionById($connectionId), self::SOCKET_BUFFER_SIZE);
        if (!strlen($data))
            return 0;

        @$this->_read[$connectionId] .= $data; // add the data into the read buffer
        return strlen($this->_read[$connectionId]) < self::MAX_SOCKET_BUFFER_SIZE;
    }

    /**
     * Запись данных в буфер для отправки
     * @param $connectionId
     * @param $data
     * @param string $delimiter
     */
    protected function write($connectionId, $data, string $delimiter = '') {
        @$this->_write[$connectionId] .=  $data . $delimiter;
    }

    /**
     * Обработка нового входящего соединения
     * @param $connectionId
     */
    protected function open($connectionId) {
        $this->_handshakes[$connectionId] = ''; // mark the connection that it needs a handshake
    }

    /**
     * Обработка закрытия соединения
     * @param $connectionId
     */
    protected function close($connectionId) {
        if (isset($this->_handshakes[$connectionId])) {
            unset($this->_handshakes[$connectionId]);
        } elseif (isset($this->_clients[$connectionId])) {
            $this->onClose($connectionId); // call user handler
        } elseif ($this->idByConnection($this->_controlSocket) == $connectionId) {
            $this->onMasterClose($connectionId); // call user handler
        }

        // close connection
        @fclose($this->connectionById($connectionId));

        if (isset($this->_clients[$connectionId])) {
            unset($this->_clients[$connectionId]);
            if (isset($this->_clientsInfo[$connectionId]))
                unset($this->_clientsInfo[$connectionId]);
            $this->removeSubscriberId($connectionId);
        } elseif ($this->idByConnection($this->_server) == $connectionId) {
            $this->_server = null;
        } elseif ($this->idByConnection($this->_controlSocket) == $connectionId) {
            $this->_controlSocket = null;
        }

        unset($this->_write[$connectionId]);
        unset($this->_read[$connectionId]);
    }

    /**
     * Чтение данных из сохраненного буфера
     * @param $connectionId
     * @return false|string
     */
    protected function readFromBuffer($connectionId) {
        $data = '';
        if (false !== ($pos = strpos($this->_read[$connectionId], self::SOCKET_MESSAGE_DELIMITER))) {
            $data = substr($this->_read[$connectionId], 0, $pos);
            $this->_read[$connectionId] = substr($this->_read[$connectionId], $pos + strlen(self::SOCKET_MESSAGE_DELIMITER));
        }
        return $data;
    }

    /**
     * Отправка записаного буфера данных в сокет
     * @param $connect
     */
    protected function sendBuffer($connect) {
        $connectionId = $this->idByConnection($connect);
        $written = fwrite($connect, $this->_write[$connectionId], self::SOCKET_BUFFER_SIZE);
        $this->_write[$connectionId] = substr($this->_write[$connectionId], $written);
    }

    /**
     * Подготовка данных для отправки клиенту
     * @param $connectionId
     * @param $data
     * @param string $type
     */
    protected function sendToClient($connectionId, $data, string $type = 'text') {
        if (!isset($this->_handshakes[$connectionId])
            && isset($this->_clients[$connectionId]))
            $this->write($connectionId, $this->encodeData($data, $type));
    }

    /**
     * Обработка "рукопожатия" Web сокета
     * @param $connectionId
     * @return bool
     */
    protected function handshake($connectionId): bool {
        // read the headers from the connection
        if (!strpos($this->_read[$connectionId], "\r\n\r\n"))
            return true;

        preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $this->_read[$connectionId], $match);
        if (empty($match[1]))
            return false;

        // --- parse input headers ---
        $cookie = [];
        $headers = $this->parseHttpHeaders($this->_read[$connectionId]);
        $additionalInfo = [];
        if (isset($headers['set-cookie'])) {
            $cookie = array_merge($cookie, $headers['set-cookie']);
            unset($headers['set-cookie']);
        }
        if (isset($headers['cookie'])) {
            $cookie = array_merge($cookie, $headers['cookie']);
            unset($headers['cookie']);
        }

        // --- parse input client host & port ---
        $source = explode(':', stream_socket_get_name($this->_clients[$connectionId], true));
        if (count($source) >= 2) {
            $additionalInfo['remote-host'] = $source[0];
            $additionalInfo['remote-port'] = $source[1];
        }

        // --- clear read buffer ---
        $this->_read[$connectionId] = '';

        // --- show input connection ---
        $info = [
            'HTTP HEADERS' => $headers,
            'COOKIE' => $cookie,
            'ADDITIONAL INFO' => $additionalInfo
        ];
        $this->log(Logger::INFO, "Incoming connection (id: $connectionId)", $info);

        // --- check url ---
        $reqMethod = "???";
        if (isset($headers['request-method']['type']))
            $reqMethod = $headers['request-method']['type'];

        $httpConnection = "???";
        if (isset($headers['connection']))
            $httpConnection = $headers['connection'];

        $httpUpgrade = "???";
        if (isset($headers['upgrade']))
            $httpUpgrade = $headers['upgrade'];

        if (!isset($headers['request-method']['url']))
            $this->log(Logger::WARNING, "Failed to upgrade to WebSocket (REQUEST_METHOD: $reqMethod, HTTP_CONNECTION: $httpConnection, HTTP_UPGRADE: $httpUpgrade)! Not found 'request-method::url' (id: $connectionId)! Close connection!");
        if (strcmp($headers['request-method']['url'], $this->_mountPath) !== 0)
            $this->log(Logger::WARNING, "Failed to upgrade to WebSocket (REQUEST_METHOD: $reqMethod, HTTP_CONNECTION: $httpConnection, HTTP_UPGRADE: $httpUpgrade)! Invalid input URL (id: $connectionId; url: " . $headers['request-method']['url'] . ")! Close connection!");

        // --- make connection info ---
        $connectionInfo = $this->makeConnectionInfo($headers, $cookie, $additionalInfo, $connectionId);

        // --- check input connection ---
        if (class_exists('\ApplicationCable\Channel')) {
            // --- init client settings ---
            $this->initClientSettings($connectionInfo);

            // --- create base channel ---
            $tmpChannel = new \ApplicationCable\Channel();
            if (is_subclass_of($tmpChannel, '\FlyCubePHP\WebSockets\ActionCable\BaseChannel')) {
                $tmpChannel->connect();
                $isReject = $tmpChannel->isRejectConnection();
                unset($tmpChannel);

                // --- clear client settings ---
                $this->clearClientSettings();

                // --- check result ---
                if ($isReject === true) {
                    $this->log(Logger::WARNING, "Failed to upgrade to WebSocket (REQUEST_METHOD: $reqMethod, HTTP_CONNECTION: $httpConnection, HTTP_UPGRADE: $httpUpgrade)! Channel denied connection! Close connection!");
                    return false;
                }
            }
        }

        // --- save connection info ---
        $this->setConnectionInfo($connectionId, $connectionInfo);

        // --- send a header according to the protocol websocket ---
        $SecWebSocketAccept = base64_encode(pack('H*', sha1($match[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: {$SecWebSocketAccept}\r\n" .
            "Sec-WebSocket-Protocol: actioncable-v1-json\r\n\r\n";

        $this->write($connectionId, $upgrade);
        unset($this->_handshakes[$connectionId]);

        $this->onOpen($connectionId, $connectionInfo);
        return true;
    }

    /**
     * Метод упаковки данных
     * @param $payload
     * @param string $type
     * @return string
     */
    protected function encodeData($payload, string $type = 'text'): string
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

    /**
     * Метод распаковки данных
     * @param $connectionId
     * @return array|false
     */
    protected function decodeData($connectionId)/*: array:bool */
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

    /**
     * Метод обработки входящего сообщения
     * @param $connectionId
     */
    protected function processMessage($connectionId) {
        if (isset($this->_handshakes[$connectionId])) {
            if ($this->_handshakes[$connectionId]) { // if the client has already made a handshake
                return; // then there does not need to read before sending the response from the server
            }

            if (!$this->handshake($connectionId)) {
                $this->close($connectionId);
            }
        } else {
            while (($data = $this->decodeData($connectionId)) && mb_check_encoding($data['payload'], 'utf-8')) { // decode buffer (there may be multiple messages)
                $this->onMessage($connectionId, $data['payload'], $data['type']); // call user handler
            }
        }
    }

    /**
     * Получить дескриптор соединения по его ИД
     * @param $connectionId
     * @return mixed|null
     */
    protected function connectionById($connectionId) {
        if (isset($this->_clients[$connectionId])) {
            return $this->_clients[$connectionId];
        } else if ($this->idByConnection($this->_server) == $connectionId) {
            return $this->_server;
        } else if ($this->idByConnection($this->_controlSocket) == $connectionId) {
            return $this->_controlSocket;
        }
        return null;
    }

    /**
     * Получить ИД по дескриптору входящего соединения
     * @param $connection
     * @return int
     */
    protected function idByConnection($connection): int {
        return intval($connection);
    }

    /**
     * Создать массив с информацией подключения клиента
     * @param array $headers
     * @param array $cookie
     * @param array $additionalInfo
     * @param string $channelId
     * @return array
     */
    protected function makeConnectionInfo(array $headers,
                                          array $cookie,
                                          array $additionalInfo,
                                          string $channelId = ""): array {
        return [
            'headers' => $headers,
            'cookie' => $cookie,
            'additional-info' => $additionalInfo,
            'channel-id' => $channelId
        ];
    }

    /**
     * Задать информацию подключения клиента
     * @param $connectionId
     * @param array $connectionInfo
     */
    protected function setConnectionInfo($connectionId, array $connectionInfo) {
        $this->_clientsInfo[$connectionId] = $connectionInfo;
    }

    /**
     * Задать ИД канала в информации подключения клиента
     * @param $connectionId
     * @param string $channelId
     */
    protected function setConnectionInfoChannelId($connectionId, string $channelId = "") {
        $this->_clientsInfo[$connectionId]['channel-id'] = $channelId;
    }

    /**
     * Получить информацию подключения клиента
     * @param $connectionId
     * @return array
     */
    protected function connectionInfo($connectionId): array {
        if (isset($this->_clientsInfo[$connectionId]))
            return $this->_clientsInfo[$connectionId];
        return $this->makeConnectionInfo([], [], [], '');
    }

    /**
     * Инициализировать глобальные объекты с данными клиента
     * @param array $connectionInfo Информация подключения клиента
     */
    protected function initClientSettings(array $connectionInfo, array $params = [])
    {
        // --- set http headers ---
        foreach ($connectionInfo['headers'] as $header => $value) {
            if (strcmp($header, 'request-method') === 0)
                continue;
            $header = str_replace('-', '_', $header);
            $_SERVER["HTTP_" . strtoupper($header)] = $value;
        }

        // --- set other client information ---
        $_SERVER['HTTP_CLIENT_IP'] = $connectionInfo['additional-info']['remote-host'];
        $_SERVER['REMOTE_ADDR'] = $connectionInfo['additional-info']['remote-host'];
        $_SERVER['REMOTE_PORT'] = $connectionInfo['additional-info']['remote-port'];
        $_SERVER['REQUEST_URI'] = $connectionInfo['headers']['request-method']['url-full'];
        $_SERVER['REQUEST_METHOD'] = $connectionInfo['headers']['request-method']['type'];

        // --- set $_GET / $_POST ---
        if (strcmp($_SERVER['REQUEST_METHOD'], 'GET') === 0)
            $_GET = array_merge($connectionInfo['headers']['request-method']['url-args'], $params);
        else
            $_POST = array_merge($connectionInfo['headers']['request-method']['url-args'], $params);

        // --- set $_COOKIE ---
        $_COOKIE = $connectionInfo['cookie'];

        // --- init session ---
        Session::instance()->init(true);
    }

    /**
     * Очитстить глобальные объекты с данными клиента
     */
    protected function clearClientSettings()
    {
        // --- close session ---
        Session::instance()->destroy();

        // --- clear http headers ---
        foreach ($_SERVER as $key => $value) {
            if (strcmp(substr($key, 0, 5),'HTTP_') !== 0)
                continue;
            $_SERVER[$key] = "";
        }
        // --- clear other client information ---
        $_SERVER['REMOTE_ADDR'] = "";
        $_SERVER['REMOTE_PORT'] = "";
        $_SERVER['REQUEST_URI'] = "";
        $_SERVER['REQUEST_METHOD'] = "";

        // --- clear global arrays ---
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
    }

    /**
     * Получить имя класса канала
     * @param string $channel
     * @return string
     */
    protected function channelClassName(string $channel): string {
        if (isset($this->_appChannels[$channel]))
            return $this->_appChannels[$channel];
        return "";
    }

    /**
     * Метод создания потока таймера
     * @return mixed|void
     */
    protected function createTimer() {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $pid = pcntl_fork(); // create a fork
        if ($pid == -1) {
            $errMsg = "[" . self::class . "] Create timer fork failed (error pcntl_fork)!";
            $this->log(Logger::ERROR, $errMsg);
            fwrite(STDERR, "$errMsg\r\n");
            die();
        } elseif ($pid) { // parent
            fclose($pair[0]);
            return $pair[1]; // one of the pair will be in the parent
        } else { // child process
            $chSid = posix_getsid(posix_getpid());
            if ($chSid < 0)
                exit;
            fclose($pair[1]);
            $parent = $pair[0]; // second of the pair will be in the child
            while (true) {
                fwrite($parent, '1');
                sleep($this->_timerSec);
                if (!file_exists("/proc/$chSid")) {
                    fwrite($parent, 'STOP');
                    exit;
                }
            }
        }
    }

    /**
     * Метод разбора HTTP заголовков
     * @param string $rawHeaders
     * @return array
     */
    protected function parseHttpHeaders(string $rawHeaders): array
    {
        $headers = [];
        $rawHeadersArray = explode(PHP_EOL, $rawHeaders);
        foreach ($rawHeadersArray as $rawHeader) {
            if (preg_match("/^(GET|POST)\s(.*)\sHTTP\/(.*)/", trim($rawHeader), $matches)) {
                $tmpUri = $tmpUriFull = trim($matches[2]);
                $tmpUriArgs = [];
                $tmpUriLst = explode('?', $tmpUriFull);
                if (count($tmpUriLst) == 2) {
                    $tmpUri = trim($tmpUriLst[0]);
                    $tmpUriArgs = $this->parseUrlArgs($tmpUriLst[1]);
                }
                $headers['request-method'] = [
                    'type' => $matches[1],
                    'url' => $tmpUri,
                    'url-args' => $tmpUriArgs,
                    'url-full' => $tmpUriFull,
                    'http' => trim($matches[3])
                ];
                continue;
            }
            $header = preg_split('/:\s*/', $rawHeader);
            if ($header === false || count($header) < 2)
                continue;
            $headerName = strtolower(trim($header[0]));
            if (strcmp($headerName, 'set-cookie') === 0) {
                $tmpCookie = [];
                if (isset($this->_headers[$headerName]))
                    $tmpCookie = $this->_headers[$headerName];

                $tmpCookie = array_merge($tmpCookie, $this->parseCookie($header[1]));
                $headers[$headerName] = $tmpCookie;
            } if (strcmp($headerName, 'cookie') === 0) {
                $tmpCookie = [];
                if (isset($this->_headers[$headerName]))
                    $tmpCookie = $this->_headers[$headerName];

                $tmpCookie = array_merge($tmpCookie, $this->parseCookieOneLine($header[1]));
                $headers[$headerName] = $tmpCookie;
            } else {
                $headers[$headerName] = trim($header[1]);
            }
        }
        return $headers;
    }

    /**
     * Метод разбора HTTP заголовка 'Set-Cookie'
     * @param string $rawCookie
     * @return array
     */
    protected function parseCookie(string $rawCookie): array
    {
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

    /**
     * Метод разбора HTTP заголовка 'Cookie'
     * @param string $rawCookie
     * @return array
     */
    protected function parseCookieOneLine(string $rawCookie): array
    {
        $tmpCookie = [];
        $rawCookie = trim($rawCookie);
        if (strpos($rawCookie, ";") !== FALSE) {
            $cookieArray = explode(";", $rawCookie);
            foreach ($cookieArray as $cookie)
                $tmpCookie = array_merge($tmpCookie, $this->parseCookie(trim($cookie)));
        }
        return $tmpCookie;
    }

    /**
     * Метод разбора аргументов
     * @param string $uriArgs
     * @return array
     */
    protected function parseUrlArgs(string $uriArgs): array
    {
        // NOTE! Не использовать parse_str($postData, $postArray),
        //       т.к. данный метод портит Base64 строки!
        $requestData = urldecode($uriArgs);
        if (empty($requestData))
            return [];
        $uriArgs = [];
        $requestKeyValueArray = explode('&', $requestData);
        foreach ($requestKeyValueArray as $keyValue) {
            $keyValueArray = explode('=', $keyValue);
            if (count($keyValueArray) < 2) {
                $uriArgs[] = $keyValue;
            } else {
                $keyData = $keyValueArray[0];
                $valueData = str_replace($keyData . "=", "", $keyValue);
                if (preg_match('/(.*?)\[(.*?)\]/i', $keyData, $tmp)) {
                    if (empty($tmp)) {
                        $uriArgs[$keyData] = $valueData;
                    } else {
                        if (!isset($uriArgs[$tmp[1]]))
                            $uriArgs[$tmp[1]] = [];
                        $uriArgs[$tmp[1]][$tmp[2]] = $valueData;
                    }
                } else {
                    $uriArgs[$keyData] = $valueData;
                }
            }
        }
        return $uriArgs;
    }

    /**
     * Отправить сообщение в лог
     * @param $level
     * @param $message
     * @param array $context
     */
    protected function log($level, $message, array $context = array())
    {
        try {
            Logger::log($level, "[". self::class ."][". $this->_pid ."] $message", $context);
        } catch (\Exception $e) {
        }
    }

    // --- Функции-Обработчики ---

    /**
     * Обработчик нового входящего соединения
     * @param $connectionId
     * @param array $connectionInfo
     */
    protected function onOpen($connectionId, array $connectionInfo) {
        $reqMethod = "???";
        if (isset($connectionInfo['headers']['request-method']['type']))
            $reqMethod = $connectionInfo['headers']['request-method']['type'];

        $httpConnection = "???";
        if (isset($connectionInfo['headers']['connection']))
            $httpConnection = $connectionInfo['headers']['connection'];

        $httpUpgrade = "???";
        if (isset($connectionInfo['headers']['upgrade']))
            $httpUpgrade = $connectionInfo['headers']['upgrade'];

        $this->log(Logger::INFO, "Successfully upgraded to WebSocket (REQUEST_METHOD: $reqMethod, HTTP_CONNECTION: $httpConnection, HTTP_UPGRADE: $httpUpgrade)");
        $this->sendToClient($connectionId, "{\"type\":\"welcome\"}");
    }

    /**
     * Обработчик закрытия соединения
     * @param $connectionId
     */
    protected function onClose($connectionId) {
        if (class_exists('\ApplicationCable\Channel')) {
            $connectionInfo = $this->connectionInfo($connectionId);

            // --- init client settings ---
            $this->initClientSettings($connectionInfo);

            // --- create base channel ---
            $tmpChannel = new \ApplicationCable\Channel();
            if (is_subclass_of($tmpChannel, '\FlyCubePHP\WebSockets\ActionCable\BaseChannel')) {
                $tmpChannel->disconnect();
                unset($tmpChannel);
            }
            // --- clear client settings ---
            $this->clearClientSettings();
        }
        $this->log(Logger::INFO, "Connection closed (id: $connectionId)");
    }

    /**
     * Обработчик ошибки
     * @param $connectionId
     */
    protected function onError($connectionId) {
        $this->log(Logger::ERROR, "An error has occurred: $connectionId");
    }

    /**
     * Обработчик входящего сообщения
     * @param $connectionId
     * @param $packet
     * @param $type
     */
    protected function onMessage($connectionId, $packet, $type) {
        // check is close message
        if (strcmp($type, 'close') === 0) {
            $this->close($connectionId);
            return;
        }
        // check is text message
        if (strcmp($type, 'text') !== 0) {
            $this->log(Logger::ERROR, "Invalid incoming message type (id: $connectionId, type: $type)!");
            return;
        }
        $data = json_decode($packet, true);
        $identifier = [];
        if (isset($data['identifier']))
            $identifier = json_decode($data['identifier'], true);
        if (!isset($identifier['channel'])) {
            $this->log(Logger::ERROR, "Not found channel name: $connectionId");
            return;
        }
        $channelName = $identifier['channel'];
        if (empty($channelName)) {
            $this->log(Logger::ERROR, "Empty channel name: $connectionId");
            return;
        }
        $channelClassName = $this->channelClassName($channelName);
        if (empty($channelClassName)) {
            $this->log(Logger::ERROR, "Subscription class not found (id: $connectionId, channel: \"$channelName\")");
            return;
        }
        $this->_currentClientId = $connectionId;
        $connectionInfo = $this->connectionInfo($connectionId);

        // --- init client settings ---
        $this->initClientSettings($connectionInfo, $identifier);

        // --- create channel class ---
        $channel = new $channelClassName();
        $channel->setWSWorker($this);

        // --- process ---
        if ((isset($data['command']) && $data['command'] == 'subscribe')
            || (isset($data['command']) && $data['command'] == 'unsubscribe')) {
            // --- subscribe / unsubscribe ---
            if ($data['command'] == 'subscribe') {
                $channel->subscribed();
                $isReject = $channel->isRejectSubscription();

                $stateMessage = "confirm_subscription";
                if ($isReject === true) {
                    $stateMessage = "reject_subscription";
                    $this->log(Logger::ERROR, "Subscription class reject subscribe (id: $connectionId, channel: \"$channelName\")! Close connection!");
                } else {
                    $this->log(Logger::INFO, "$channelName is transmitting the subscription confirmation (id: $connectionId)");
                    $this->setConnectionInfoChannelId($connectionId, $data['identifier']);
                }

                // --- send response ---
                $sData = [
                    'identifier' => $data['identifier'],
                    'type' => $stateMessage
                ];
                $this->sendToClient($connectionId, json_encode($sData));
                if ($isReject === true)
                    $this->close($connectionId);
            } else {
                $channel->unsubscribed();
                $this->log(Logger::INFO, "Unsubscribing from channel: " . $data['identifier'] . " (id: $connectionId)");
            }
        } else if (isset($data['command']) && $data['command'] == 'message') {
            // --- incoming data ---
            if (!isset($data['data'])) {
                $this->log(Logger::ERROR, "Not found message data (id: $connectionId)!");
            } else {
                $message = json_decode($data['data'], true);
                if (!isset($message['action'])) {
                    $this->log(Logger::INFO, "$channelName::receive(" . $data['data'] . ")");
                    $channel->receive($message);
                } else {
                    $actName = $message['action'];
                    unset($message['action']);
                    if ($this->_isEnabledPerform === false) {
                        $this->log(Logger::WARNING, "Action Cable perform disabled! Method $channelName::$actName(" . $data['data'] . ") refused to run!");
                    } else {
                        $actInfo = $channel->channelMethod($actName);
                        if (empty($actInfo)) {
                            $this->log(Logger::ERROR, "Not found method \"$actName\" in class $channelName! Action Cable perform failed!");
                        } else if (count($actInfo['args']) > 1) {
                            $this->log(Logger::ERROR, "Method $channelName::$actName(" . implode(', ', $actInfo['args']) . ") has more than one argument! Action Cable perform failed!");
                        } else {
                            try {
                                $this->log(Logger::INFO, "$channelName::$actName(" . $data['data'] . ")");
                                $channel->$actName($message);
                            } catch (\Throwable $e) {
                                $this->log(Logger::ERROR, $e->getMessage());
                            }
                        }
                    }
                }
            }
        } else {
            $this->log(Logger::ERROR, "Unsupported incoming command (id: $connectionId)!", $data);
        }
        unset($channel);
        $this->_currentClientId = null;

        // --- clear client settings ---
        $this->clearClientSettings();
    }

    /**
     * Обработчик входящего сообщения от управляющего потока
     * @param $data
     */
    protected function onMasterMessage($data) {
        $data = json_decode($data, true);
        if (!isset($data['broadcasting']) || !isset($data['message'])) {
            $this->log(Logger::ERROR, "Not found broadcasting or message path in master-message!");
            return;
        }
        $subscribers = $this->subscribers($data['broadcasting']);
        foreach ($subscribers as $connectionId => $connectionSettings) {
            $identifier = $connectionSettings['identifier'];
            $channelName = $connectionSettings['channel'];
            $streamName = $connectionSettings['stream_name'];
            if ($connectionId <= 0) {
                $this->log(Logger::WARNING, "Not found client connection id! Send skip!");
                continue;
            }
            if (empty($identifier)) {
                $this->log(Logger::WARNING, "Not found client channel identifier (id: $connectionId)! Send skip!");
                continue;
            }
            $sData = [
                'identifier' => $identifier,
                'message' => $data['message']
            ];
            $jsonData = json_encode($sData);
            $this->sendToClient($connectionId, $jsonData);
            $this->log(Logger::INFO, "$channelName transmitting \"".json_encode($data['message'])."\" (via streamed from $streamName)");
        }
    }

    /**
     * Обработчик закрытия соединения с управляющим потоком
     * @param $connectionId
     */
    protected function onMasterClose($connectionId) {
        $this->log(Logger::INFO, "Master connection closed (id: $connectionId)");
    }

    /**
     * Обработчик сообщений от потока таймера
     */
    protected function onTimer() {
        $sData = [
            'type' => 'ping',
            'message' => time()
        ];
        $jsonData = json_encode($sData);
        foreach ($this->_clients as $clientId => $client)
            $this->sendToClient($clientId, $jsonData);
    }
}