<?php

namespace FlyCubePHP\WebSockets\Server\Adapters;

use FlyCubePHP\Core\Logger\Logger;
use FlyCubePHP\WebSockets\Config\WSConfig;
use FlyCubePHP\WebSockets\Server\WSWorker;

include_once 'BaseServerAdapter.php';

class IPCServerAdapter extends BaseServerAdapter
{
    const SOCKET_BUFFER_SIZE        = 1024;
    const MAX_SOCKET_BUFFER_SIZE    = 10240;

    private $_sockPath;
    private $_server = null;
    private $_clients = array();
    private $_read = array();  // read buffers

    function __construct(array $workersControls)
    {
        parent::__construct($workersControls);
        $this->_sockPath = WSConfig::instance()->currentSettingsValue(WSConfig::TAG_IPC_SOCK_PATH, WSConfig::DEFAULT_IPC_SOCK_PATH);
        if (empty($this->_sockPath)) {
            $errMsg = "[". self::class ."] Invalid IPC socket path!";
            $this->log(Logger::ERROR, $errMsg);
            fwrite(STDERR, "$errMsg\r\n");
            die();
        }
        if (file_exists($this->_sockPath)
            && !unlink($this->_sockPath)) {
            $errMsg = "[". self::class ."] Remove old IPC socket failed! Abort!";
            $this->log(Logger::ERROR, $errMsg);
            fwrite(STDERR, "$errMsg\r\n");
            die();
        }
    }

    /**
     * Метод запуска обработчика событий от контроллеров для отправки клиентам
     */
    public function run()
    {
        $sockPath = $this->_sockPath;
        $this->_server = stream_socket_server("unix://$sockPath", $errorNumber, $errorString);
        stream_set_blocking($this->_server, true);
        if (!$this->_server) {
            $errMsg = "[". self::class ."] stream_socket_server: $errorString ($errorNumber)";
            $this->log(Logger::ERROR, $errMsg);
            fwrite(STDERR, "$errMsg\r\n");
            die();
        }
        $infoMsg = "[". self::class ."] Server adapter listen on: unix://$sockPath";
        Logger::info($infoMsg);
        echo "$infoMsg\r\n";

        // --- start worker loop ---
        while (true) {
            // --- prepare the array of sockets that need to be processed ---
            $read = $this->_clients;
            $read[] = $this->_server;

            // --- write array ---
            $write = array();

            // --- exception array ---
            $except = $read;

            // --- select streams ---
            stream_select($read, $write, $except, null); // update the array of sockets that can be processed
            if ($read) { // data were obtained from the connected clients
                foreach ($read as $client) {
                    if ($this->_server == $client) {
                        // --- check is new incoming connection ---
                        if ($client = @stream_socket_accept($this->_server, 0)) {
                            $clientId = $this->idByConnection($client);
                            $this->_clients[$clientId] = $client;
                        }
                    } else {
                        // --- read new incoming data ---
                        $connectionId = $this->idByConnection($client);
                        if (!$this->read($connectionId)) { // connection has been closed or the buffer was overwhelmed
                            $this->close($connectionId);
                            continue;
                        }
                        // --- process incoming message ---
                        $this->sendToWorkers($connectionId);
                    }
                }
            }

            if ($except) {
                foreach ($except as $client)
                    $this->error($this->idByConnection($client));
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
     * Чтение данных из сокета
     * @param $connectionId
     * @return bool|int
     */
    protected function read($connectionId) {
        $data = fread($this->connectionById($connectionId), self::SOCKET_BUFFER_SIZE);
        if (!strlen($data)) {
            return 0;
        }

        @$this->_read[$connectionId] .= $data; // add the data into the read buffer
        return strlen($this->_read[$connectionId]) < self::MAX_SOCKET_BUFFER_SIZE;
    }

    /**
     * Запись данных в сокет
     * @param $sock
     * @param $data
     */
    protected function write($sock, $data)
    {
        $written = fwrite($sock, $data, self::SOCKET_BUFFER_SIZE);
        $data = substr($data, $written);
        if (!empty($data))
            $this->write($sock, $data);
    }

    /**
     * Обработка закрытия соединения
     * @param $connectionId
     */
    protected function close($connectionId) {
        @fclose($this->connectionById($connectionId));
        if (isset($this->_clients[$connectionId])) {
            unset($this->_clients[$connectionId]);
        } elseif ($this->idByConnection($this->_server) == $connectionId) {
            $this->_server = null;
        }
        unset($this->_read[$connectionId]);
    }

    /**
     * Обработка ошибки
     * @param $connectionId
     */
    protected function error($connectionId) {
        $this->log(Logger::ERROR, "An error has occurred: $connectionId");
    }

    /**
     * Отправка данных дочерним потокам
     * @param $connectionId
     */
    protected function sendToWorkers($connectionId)
    {
        $data = $this->_read[$connectionId];
        $this->_read[$connectionId] = "";
        foreach ($this->_workersControls as $control)
            $this->write($control, $data . WSWorker::SOCKET_MESSAGE_DELIMITER);
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
            Logger::log($level, "[". self::class ."] $message", $context);
        } catch (\Exception $e) {
        }
    }
}