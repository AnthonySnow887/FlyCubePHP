<?php

namespace FlyCubePHP\WebSockets\Server;

include_once 'WSWorker.php';

/*
 * JS Test:
 *
 * function wsStart() {
                ws = new WebSocket("ws://127.0.0.1:8000/");
                ws.onopen = function() { console.log("system: connection is open"); };
                ws.onclose = function() { console.log("system: the connection is closed, I try to reconnect"); setTimeout(wsStart, 1000); };
                ws.onmessage = function(evt) { console.log("In DATA: " + evt.data); };
            };
 *
 * wsStart();
 */

class WSServer
{
    function __construct()
    {
    }

    public function start(string $host = '127.0.0.1', int $port = 8000)
    {
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
                die("error: stream_socket_server: $errorString ($errorNumber)\r\n");

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

    public function stop() {
// TODO
//        $pid = @file_get_contents($this->config['pid']);
//        if ($pid) {
//            posix_kill($pid, SIGTERM);
//            for ($i=0;$i=10;$i++) {
//                sleep(1);
//
//                if (!posix_getpgid($pid)) {
//                    unlink($this->config['pid']);
//                    return;
//                }
//            }
//
//            die("don't stopped\r\n");
//        } else {
//            die("already stopped\r\n");
//        }
    }

    public function restart() {
// TODO
//        $pid = @file_get_contents($this->config['pid']);
//        if ($pid) {
//            $this->stop();
//        }
//
//        $this->start();
    }
}