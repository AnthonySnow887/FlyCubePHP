#!/usr/bin/env php
<?php

/**
 * NOTE: Put this file in your project root, where the index.php is located.
 *
 * NOTE: This is a test file, and it will be removed!
 */

error_reporting(E_ALL);

// TODO comment this line
include_once 'Server/WSServer.php';

// TODO uncomment this line
//include_once 'vendor/FlyCubePHP/FlyCubePHP/WebSockets/Server/WSServer.php';

$ws = new FlyCubePHP\WebSockets\Server\WSServer();
$ws->start();

