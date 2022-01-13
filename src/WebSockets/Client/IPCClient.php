<?php

namespace FlyCubePHP\WebSockets\Client;

use FlyCubePHP\Core\Logger\Logger;
use FlyCubePHP\WebSockets\Config\WSConfig;

include_once __DIR__.'/../Config/WSConfig.php';

class IPCClient
{
    static public function send(string $data)
    {
        $sockPath = WSConfig::instance()->currentSettingsValue(WSConfig::TAG_IPC_SOCK_PATH, WSConfig::DEFAULT_IPC_SOCK_PATH);

        // Создаём  UNIX сокет
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if ($socket === false) {
            Logger::error("[". self::class ."] Error: " . socket_strerror(socket_last_error()));
            return;
        }

        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, [ 'sec' => 30, 'usec' => 0 ]); // 30 sec
        socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);

        // Подключаемся к UNIX сокету
        $result = socket_connect($socket, $sockPath);
        if ($result === false) {
            Logger::error("[". self::class ."] Error: " . socket_strerror(socket_last_error($socket)));
            return;
        }

        // Отправляем запрос
        $result = socket_write($socket, $data, strlen($data));
        if ($result === false)
            return;

        socket_close($socket);
    }
}