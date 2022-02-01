<?php

namespace FlyCubePHP\WebSockets\ActionCable\Adapters;

use FlyCubePHP\Core\Logger\Logger;
use FlyCubePHP\WebSockets\Config\WSConfig;

include_once 'BaseClientAdapter.php';
include_once __DIR__.'/../../Config/WSConfig.php';

class RedisClientAdapter implements BaseClientAdapter
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
        $host = WSConfig::instance()->currentSettingsValue(WSConfig::TAG_REDIS_HOST, "");
        $port = WSConfig::instance()->currentSettingsValue(WSConfig::TAG_REDIS_PORT, 6379);
        $password = WSConfig::instance()->currentSettingsValue(WSConfig::TAG_REDIS_PASSWORD, "");
        $channelName = WSConfig::instance()->currentSettingsValue(WSConfig::TAG_REDIS_CHANNEL, "");
        if (empty($host)
            || empty($channelName)
            || $port <= 0) {
            Logger::error("[". self::class ."] Invalid Redis settings!");
            return false;
        }

        $redis = new \Redis();
        try {
            $connected = $redis->connect($host, $port, 30); // timeout 30 sec
        } catch (\Throwable $e) {
            Logger::error("[". self::class ."] Connect to Redis failed (host: $host:$port)! Error: " . $e->getMessage());
            unset($redis);
            return false;
        }
        if ($connected === false) {
            Logger::error("[". self::class ."] Connect to Redis failed (host: $host:$port)!");
            unset($redis);
            return false;
        }
        if (!empty($password)) {
            $isAuth = $redis->auth($password);
            if ($isAuth === false) {
                Logger::error("[". self::class ."] Redis authorization failed (host: $host:$port)!");
                $redis->close();
                unset($redis);
                return false;
            }
        }

        // Отправляем запрос
        $data = json_encode([
            'broadcasting' => $broadcasting,
            'message' => $message
        ]);
        try {
            $redis->publish($channelName, $data);
        } catch (\Throwable $e) {
            Logger::error("[". self::class ."] Publishing data to Redis failed (host: $host:$port)! Error: " . $e->getMessage());
            $redis->close();
            unset($redis);
            return false;
        }
        $redis->close();
        unset($redis);
        return true;
    }
}