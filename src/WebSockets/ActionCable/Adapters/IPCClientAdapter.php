<?php

namespace FlyCubePHP\WebSockets\ActionCable\Adapters;

use FlyCubePHP\Core\Logger\Logger;
use FlyCubePHP\WebSockets\Config\WSConfig;

include_once 'BaseClientAdapter.php';
include_once __DIR__.'/../../Config/WSConfig.php';

class IPCClientAdapter implements BaseClientAdapter
{
    /**
     * Отправить данные клиентам
     * @param string $broadcasting Название канала вещания
     * @param mixed $message Данные
     * @return bool
     * @throws
     */
    public function broadcast(string $broadcasting, $message): bool
    {
        $sockPath = WSConfig::instance()->currentSettingsValue(WSConfig::TAG_IPC_SOCK_PATH, WSConfig::DEFAULT_IPC_SOCK_PATH);

        // Создаём  UNIX сокет
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if ($socket === false) {
            Logger::error("[". self::class ."] Error: " . socket_strerror(socket_last_error()));
            return false;
        }

        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, [ 'sec' => 30, 'usec' => 0 ]); // 30 sec
        socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);

        // Подключаемся к UNIX сокету
        $result = socket_connect($socket, $sockPath);
        if ($result === false) {
            Logger::error("[". self::class ."] Error: " . socket_strerror(socket_last_error($socket)));
            return false;
        }

        // Отправляем запрос
        $data = json_encode([
            'broadcasting' => $broadcasting,
            'message' => $message
        ]);
        $result = socket_write($socket, $data, strlen($data));

        // --- wait confirm ---
        $recvBytes = socket_recv($socket, $r_data, 2, MSG_WAITALL);

        // --- close connection ---
        socket_close($socket);
        return !($result == false || $recvBytes == false);
    }
}