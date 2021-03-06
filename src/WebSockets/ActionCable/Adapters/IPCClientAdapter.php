<?php

namespace FlyCubePHP\WebSockets\ActionCable\Adapters;

use FlyCubePHP\Core\Logger\Logger;
use FlyCubePHP\WebSockets\Config\WSConfig;

include_once 'BaseClientAdapter.php';
include_once __DIR__.'/../../Config/WSConfig.php';

class IPCClientAdapter implements BaseClientAdapter
{
    const SOCKET_BUFFER_SIZE = 1024;

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
        $result = $this->write($socket, $data);
        if ($result === false)
            Logger::error("[". self::class ."] Error: Write to socket failed!");

        // --- close connection ---
        socket_close($socket);
        return !($result == false);
    }

    /**
     * Запись данных в сокет
     * @param $sock
     * @param string $data
     * @return bool
     */
    protected function write($sock, string $data): bool
    {
        $written = socket_write($sock, $data, self::SOCKET_BUFFER_SIZE);
        if ($written === false)
            return false;
        $data = substr($data, $written);
        if (!empty($data))
            return $this->write($sock, $data);
        return true;
    }
}