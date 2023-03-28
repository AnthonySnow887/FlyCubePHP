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
        // --- make data ---
        $data = json_encode([
            'broadcasting' => $broadcasting,
            'message' => $message
        ]);

        // --- check data ---
        if ($data && strlen($data) >= WSConfig::MAX_SOCKET_BUFFER_SIZE) {
            Logger::error("[". self::class ."] Error: The message exceeds the maximum buffer size (".WSConfig::MAX_SOCKET_BUFFER_SIZE.")! Skip broadcast!");
            return false;
        }

        // --- make socket path ---
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
        $result = $this->write($socket, $data . WSConfig::SOCKET_MESSAGE_DELIMITER);

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
        $written = socket_write($sock, $data, WSConfig::SOCKET_BUFFER_SIZE);
        if ($written === false) {
            Logger::error("[" . self::class . "] Error: Write data failed!");
            return false;
        }
        // --- wait response ---
        if (false === ($bytes = socket_recv($sock, $r_data, WSConfig::SOCKET_BUFFER_SIZE, 0))) {
            Logger::error("[" . self::class . "] Error: Receiving data response failed!");
            return false;
        }
        // --- send next part if needed ---
        $data = substr($data, $written);
        if (!empty($data))
            return $this->write($sock, $data);
        return true;
    }
}