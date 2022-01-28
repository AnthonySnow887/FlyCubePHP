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
        $connected = $redis->connect($host, $port);
        if ($connected === false) {
            Logger::error("[". self::class ."] Connect to Redis failed (host: $host:$port)!");
            unset($redis);
            return false;
        }
        if (!empty($password)) {
            $isAuth = $redis->auth($password);
            if ($isAuth === false) {
                Logger::error("[". self::class ."] Redis authorization failed (host: $host:$port)!");
                unset($redis);
                return false;
            }
        }

        // Отправляем запрос
        $data = json_encode([
            'broadcasting' => $broadcasting,
            'message' => $message
        ]);
        $redis->publish($channelName, $data);
        unset($redis);
        return true;
    }
}