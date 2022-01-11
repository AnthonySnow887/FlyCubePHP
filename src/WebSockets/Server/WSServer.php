<?php

/*
FlyCubePHP WebSockets based on the code and idea described in morozovsk/websocket.
https://github.com/morozovsk/websocket
Released under the MIT license
*/

namespace FlyCubePHP\WebSockets\Server;

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
    private $_pid;

    function __construct()
    {
        // TODO load config
    }

    public function start(string $host = '127.0.0.1', int $port = 8000)
    {
        echo "App path: " . CoreHelper::rootDir() . "\r\n";


        // TODO load host & port from config

// TODO
//        $pid = @file_get_contents($this->config['pid']);
//        if ($pid) {
//            if (posix_getpgid($pid)) {
//                die("already started\r\n");
//            } else {
//                unlink($this->config['pid']);
//            }
//        }

// TODO
//        if (empty($this->config['websocket']) && empty($this->config['localsocket']) && empty($this->config['master'])) {
//            die("error: config: !websocket && !localsocket && !master\r\n");
//        }

        $server = $service = $master = null;

// TODO
//        if (!empty($this->config['websocket'])) {
//            //open server socket
//            $server = stream_socket_server($this->config['websocket'], $errorNumber, $errorString);
//            stream_set_blocking($server, 0);
//
//            if (!$server) {
//                die("error: stream_socket_server: $errorString ($errorNumber)\r\n");
//            }
//        }

            //open server socket
            $server = stream_socket_server("tcp://$host:$port", $errorNumber, $errorString);
            stream_set_blocking($server, 0);
            if (!$server)
                die("error: stream_socket_server: $errorString ($errorNumber)\r\n"); // TODO write in log file error start



// TODO
//        if (!empty($this->config['localsocket'])) {
//            //create a socket for the processing of messages from scripts
//            $service = stream_socket_server($this->config['localsocket'], $errorNumber, $errorString);
//            stream_set_blocking($service, 0);
//
//            if (!$service) {
//                die("error: stream_socket_server: $errorString ($errorNumber)\r\n");
//            }
//        }
//
//        if (!empty($this->config['master'])) {
//            //create a socket for the processing of messages from slaves
//            $master = stream_socket_client($this->config['master'], $errorNumber, $errorString);
//            stream_set_blocking($master, 0);
//
//            if (!$master) {
//                die("error: stream_socket_client: $errorString ($errorNumber)\r\n");
//            }
//        }
//
//        if (!empty($this->config['eventDriver']) && $this->config['eventDriver'] == 'libevent') {
//            class_alias('morozovsk\websocket\GenericLibevent', 'morozovsk\websocket\Generic');
//        } elseif (!empty($this->config['eventDriver']) && $this->config['eventDriver'] == 'event') {
//            class_alias('morozovsk\websocket\GenericEvent', 'morozovsk\websocket\Generic');
//        } else {
//            class_alias('morozovsk\websocket\GenericSelect', 'morozovsk\websocket\Generic');
//        }
//
//        file_put_contents($this->config['pid'], posix_getpid());



        //list($pid, $master, $workers) = $this->spawnWorkers();//create child processes

        /*if ($pid) {//мастер
            file_put_contents($this->config['pid'], $pid);
            fclose($server);//master will not handle incoming connections on the main socket
            $masterClass = $this->config['master']['class'];
            $master = new $masterClass ($service, $workers);//master will process messages from the script and send them to a worker
            if (!empty($this->config['master']['timer'])) {
                $master->timer = $this->config['worker']['timer'];
            }
            $master->start();
        } else {//воркер*/

// TODO
//        $workerClass = $this->config['class'];
//        $worker = new $workerClass ($server, $service, $master);
//        if (!empty($this->config['timer'])) {
//            $worker->timer = $this->config['timer'];
//        }
//        $worker->start();

        // TODO start reader (ipc or redis) & connect this to workers

        // TODO start N workers (get count from config) (default: 1)

        // --- test start 3 workers ---
//        $numWorkers = 1;
//        for ($i = 0; $i < $numWorkers; $i++) {
//            $pid = pcntl_fork(); //create a fork
//            if ($pid == -1) {
//                // TODO write to log file
//                die("error: pcntl_fork\r\n");
//            } else if ($pid == 0) { // child started
//
//                // create stream_socket_pair for send data to worker & send system commands
//
//                $worker = new WSWorker($server);
//                $worker->start();
//                break;
//            }
//        }

        $worker = new WSWorker($server);
        $worker->start();
        //}
    }

    /*protected function spawnWorkers() {
        $master = null;
        $workers = array();
        for ($i=0; $i<$this->config['master']['workers']; $i++) {
            //create a pair of sockets through which the master and the worker is to be linked
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            $pid = pcntl_fork();//create a fork
            if ($pid == -1) {
                die("error: pcntl_fork\r\n");
            } elseif ($pid) { //master
                fclose($pair[0]);
                $workers[intval($pair[1])] = $pair[1];//one of the pair is in the master
            } else { //worker
                fclose($pair[1]);
                $master = $pair[0];//second of pair is in worker
                break;
            }
        }
        return array($pid, $master, $workers);
    }*/
}