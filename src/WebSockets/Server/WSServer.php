<?php

/*
FlyCubePHP WebSockets based on the code and idea described in morozovsk/websocket.
https://github.com/morozovsk/websocket
Released under the MIT license
*/

namespace FlyCubePHP\WebSockets\Server;

use FlyCubePHP\Core\Logger\Logger;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\WebSockets\Config\WSConfig;
use FlyCubePHP\WebSockets\Server\Adapters\IPCAdapter;


include_once __DIR__.'/../../FlyCubePHPVersion.php';
include_once __DIR__.'/../../FlyCubePHPAutoLoader.php';
include_once __DIR__.'/../../FlyCubePHPErrorHandling.php';
include_once __DIR__.'/../../FlyCubePHPEnvLoader.php';
include_once __DIR__.'../../Core/Logger/Logger.php';
include_once __DIR__.'/../../HelperClasses/CoreHelper.php';
include_once __DIR__.'/../Config/WSConfig.php';

include_once 'WSWorker.php';
include_once 'Adapters/IPCAdapter.php';

class WSServer
{
    private $_host;
    private $_port;
    private $_workersNum = 1;
    private $_workersControls = array();
    private $_pid;

    function __construct()
    {
        $this->_host = WSConfig::instance()->currentSettingsValue(WSConfig::TAG_WS_SERVER_HOST, "127.0.0.1");
        $this->_port = intval(WSConfig::instance()->currentSettingsValue(WSConfig::TAG_WS_SERVER_PORT, 8000));
        $this->_workersNum = intval(WSConfig::instance()->currentSettingsValue(WSConfig::TAG_WS_SERVER_WORKERS_NUM, 1));
        if ($this->_workersNum <= 0) {
            $errMsg = "[". self::class ."] Invalid WS Workers number (num <= 0)!";
            Logger::error($errMsg);
            fwrite(STDERR, "$errMsg\r\n");
            die();
        }
        $this->_pid = posix_getpid();
    }

    public function start()
    {
        $infoMsg = "[". self::class ."] Start WSServer. PID: " . $this->_pid;
        Logger::info($infoMsg);
        echo "$infoMsg\r\n";

        $infoMsg = "[". self::class ."] App path: " . CoreHelper::rootDir();
        Logger::info($infoMsg);
        echo "$infoMsg\r\n";


        // --- open server socket ---
        $host = $this->_host;
        $port = $this->_port;
        $server = stream_socket_server("tcp://$host:$port", $errorNumber, $errorString);
        stream_set_blocking($server, 0);
        if (!$server) {
            $errMsg = "[". self::class ."] stream_socket_server: $errorString ($errorNumber)";
            Logger::error($errMsg);
            fwrite(STDERR, "$errMsg\r\n");
            die();
        }
        $infoMsg = "[". self::class ."] Listen on: tcp://$host:$port";
        Logger::info($infoMsg);
        echo "$infoMsg\r\n";

        // --- start workers ---
        for ($i = 0; $i < $this->_workersNum; $i++) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            $pid = pcntl_fork(); // create a fork
            if ($pid == -1) {
                $errMsg = "[" . self::class . "] Create fork failed (error pcntl_fork)!";
                Logger::error($errMsg);
                fwrite(STDERR, "$errMsg\r\n");
                die();
            } else if ($pid) { // parent process
                fclose($pair[0]);
                $this->_workersControls[] = $pair[1];
            } else if ($pid == 0) { // child process
                fclose($pair[1]);
                $worker = new WSWorker($server, $pair[0]);
                $worker->start();
                break;
            }
        }

        $adapter = null;
        $adapterName = WSConfig::instance()->currentAdapterName();
        if (strcmp(trim(strtolower($adapterName)), 'ipc') === 0)
            $adapter = new IPCAdapter($this->_workersControls);
        else if (strcmp(trim(strtolower($adapterName)), 'redis') === 0)
            $adapter = null;//new RedisAdapter($this->_workersControls);

        if (is_null($adapter)) {
            $errMsg = "[" . self::class . "] Not found adapter with name \"$adapterName\"!";
            Logger::error($errMsg);
            fwrite(STDERR, "$errMsg\r\n");
            die();
        }
        $adapter->run();
    }
}