#!/usr/bin/env php
<?php

/**
 * NOTE: Put this file in your project root, where the index.php is located.
 *
 * NOTE: This is a test file, and it will be removed!
 */

//error_reporting(E_ALL);

chdir(__DIR__);

// TODO comment this line
include_once 'Server/WSServiceApplication.php';

// TODO uncomment this line
//include_once 'vendor/FlyCubePHP/FlyCubePHP/WebSockets/Server/WSServiceApplication.php';

$app = $argv[0];
$arguments = [];
for ($i = 1; $i < $argc; $i++)
    $arguments[] = $argv[$i];

$ws = new FlyCubePHP\WebSockets\Server\WSServiceApplication();
if (in_array('start', $arguments) === true)
    $ws->start();
else if (in_array('stop', $arguments) === true)
    $ws->stop();
else if (in_array('restart', $arguments) === true)
    $ws->restart();

