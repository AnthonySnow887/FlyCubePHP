<?php

error_reporting(E_ALL);

include_once 'Server/WSServer.php';

$ws = new FlyCubePHP\WebSockets\Server\WSServer();
$ws->start();

