<?php

namespace FlyCubePHP\WebSockets\ActionCable;

use FlyCubePHP\Core\Logger\Logger;
use FlyCubePHP\WebSockets\ActionCable\Adapters\IPCClientAdapter;
use FlyCubePHP\WebSockets\Config\WSConfig;

include_once __DIR__.'/../Config/WSConfig.php';
include_once 'Adapters/IPCClientAdapter.php';

class ActionCable
{
    /**
     * Отправить данные клиентам
     * @param string $broadcasting Название канала вещания
     * @param mixed $message Данные
     * @throws \FlyCubePHP\Core\Error\Error
     */
    static public function serverBroadcast(string $broadcasting, $message)
    {
        if (empty($broadcasting) || empty($message)) {
            Logger::warning("[ActionCable][serverBroadcast] Empty broadcasting channel name or message! Skip broadcast!");
            return;
        }
        $adapter = null;
        $adapterName = WSConfig::instance()->currentAdapterName();
        if (strcmp(trim(strtolower($adapterName)), 'ipc') === 0)
            $adapter = new IPCClientAdapter();
        else if (strcmp(trim(strtolower($adapterName)), 'redis') === 0)
            $adapter = null;//new RedisClientAdapter();

        if (is_null($adapter))
            throw new \RuntimeException("[ActionCable][serverBroadcast] Not found adapter with name \"$adapterName\"!");

        $adapter->broadcast($broadcasting, $message);
        unset($adapter);
    }
}