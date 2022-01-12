<?php

/*
FlyCubePHP WebSockets based on the code and idea described in morozovsk/websocket.
https://github.com/morozovsk/websocket
Released under the MIT license
*/

namespace FlyCubePHP\WebSockets\Server;

use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\Core\Logger\Logger;
use FlyCubePHP\HelperClasses\CoreHelper;


include_once __DIR__.'/../../FlyCubePHPVersion.php';
include_once __DIR__.'/../../FlyCubePHPAutoLoader.php';
include_once __DIR__.'/../../FlyCubePHPErrorHandling.php';
include_once __DIR__.'/../../FlyCubePHPEnvLoader.php';
include_once __DIR__.'../../Core/Logger/Logger.php';
include_once __DIR__.'/../../HelperClasses/CoreHelper.php';

include_once 'WSWorker.php';

class WSServer
{
    private $_host;
    private $_port;
    private $_workersNum = 1;
    private $_workersControls = array();
    private $_pid;

    function __construct()
    {
        $this->_host = Config::instance()->arg(Config::TAG_WS_SERVER_HOST, "127.0.0.1");
        $this->_port = intval(Config::instance()->arg(Config::TAG_WS_SERVER_PORT, 8000));
        $this->_workersNum = intval(Config::instance()->arg(Config::TAG_WS_SERVER_WORKERS_NUM, 5));
        if ($this->_workersNum <= 0) {
            Logger::error("[". self::class ."] Invalid WS Workers number (num <= 0)!");
            die();
        }
        $this->_pid = posix_getpid();
    }

    public function start()
    {
        Logger::info("[". self::class ."] Start WSServer. PID: " . $this->_pid);
        Logger::info("[". self::class ."] App path: " . CoreHelper::rootDir());

        // --- open server socket ---
        $host = $this->_host;
        $port = $this->_port;
        $server = stream_socket_server("tcp://$host:$port", $errorNumber, $errorString);
        stream_set_blocking($server, 0);
        if (!$server) {
            Logger::error("[". self::class ."] stream_socket_server: $errorString ($errorNumber)");
            die();
        }

        // --- start workers ---
        for ($i = 0; $i < $this->_workersNum; $i++) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            $pid = pcntl_fork(); // create a fork
            if ($pid == -1) {
                Logger::error("[" . self::class . "] Create fork failed (error pcntl_fork)!");
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

        // TODO start reader (ipc or redis) & connect this to workers
        // --- start server loop ---
        while (true) {} // TODO start server loop
    }
}