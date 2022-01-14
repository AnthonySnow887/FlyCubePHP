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
            Logger::error($errMsg);
            fwrite(STDERR, "$errMsg\r\n");
            die();
        }
        if (file_exists($this->_sockPath)
            && !unlink($this->_sockPath)) {
            $errMsg = "[". self::class ."] Remove old IPC socket failed! Abort!";
            Logger::error($errMsg);
            fwrite(STDERR, "$errMsg\r\n");
            die();
        }
    }

    public function run()
    {
        $sockPath = $this->_sockPath;
        $this->_server = stream_socket_server("unix://$sockPath", $errorNumber, $errorString);
        stream_set_blocking($this->_server, 0);
        if (!$this->_server) {
            $errMsg = "[". self::class ."] stream_socket_server: $errorString ($errorNumber)";
            Logger::error($errMsg);
            fwrite(STDERR, "$errMsg\r\n");
            die();
        }
        $infoMsg = "[". self::class ."] Adapter listen on: unix://$sockPath";
        Logger::info($infoMsg);
        echo "$infoMsg\r\n";

        // --- start worker loop ---
        while (true) {
            // --- prepare the array of sockets that need to be processed ---
            $read = [];
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
                        echo "-> IN Connect\r\n";
                        // --- check is new incoming connection ---
                        if ($client = @stream_socket_accept($this->_server, 0)) {
                            stream_set_blocking($client, 0);
                            $clientId = $this->idByConnection($client);
                            $this->_clients[$clientId] = $client;
                        }
                        // --- read new incoming data ---
                        $connectionId = $this->idByConnection($client);
                        echo "-> Can READ...\r\n";
                        if (!$this->read($connectionId)) { // connection has been closed or the buffer was overwhelmed
                            echo "-> READ failed! Close!\r\n";
                            $this->close($connectionId);
                            continue;
                        }
                        echo "-> READ Success. Send to workers...\r\n";
                        // --- process incoming message ---
                        $this->sendToWorkers($connectionId);
                    } else {
                        // --- read new incoming data ---
                        echo "-> Can READ 2...\r\n";
                        $connectionId = $this->idByConnection($client);
                        if (!$this->read($connectionId)) { // connection has been closed or the buffer was overwhelmed
                            echo "-> READ 2 failed! Close!\r\n";
                            $this->close($connectionId);
                            continue;
                        }
                        echo "-> READ 2 Success. Send to workers...\r\n";
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

    protected function connectionById($connectionId) {
        if (isset($this->_clients[$connectionId])) {
            return $this->_clients[$connectionId];
        } else if ($this->idByConnection($this->_server) == $connectionId) {
            return $this->_server;
        }
        return null;
    }

    protected function idByConnection($connection) {
        return intval($connection);
    }

    protected function read($connectionId) {
        $data = fread($this->connectionById($connectionId), self::SOCKET_BUFFER_SIZE);
        if (!strlen($data))
            return 0;

        @$this->_read[$connectionId] .= $data; // add the data into the read buffer
        return strlen($this->_read[$connectionId]) < self::MAX_SOCKET_BUFFER_SIZE;
    }

    protected function write($sock, $data)
    {
        echo "-> Write: $data\r\n";
        $written = fwrite($sock, $data, self::SOCKET_BUFFER_SIZE);
        echo "-> Write size: $written\r\n";
        $data = substr($data, $written);
        if (!empty($data))
            $this->write($sock, $data);
    }

    protected function close($connectionId) {
        @fclose($this->connectionById($connectionId));
        if (isset($this->_clients[$connectionId])) {
            unset($this->_clients[$connectionId]);
        } elseif ($this->idByConnection($this->_server) == $connectionId) {
            $this->_server = null;
        }
        unset($this->_read[$connectionId]);
    }

    protected function error($connectionId) {
        Logger::error("[". self::class ."] An error has occurred: $connectionId");
    }

    protected function sendToWorkers($connectionId)
    {
        $data = $this->_read[$connectionId];
        $this->_read[$connectionId] = "";
        foreach ($this->_workersControls as $control)
            $this->write($control, $data . WSWorker::SOCKET_MESSAGE_DELIMITER);
    }
}