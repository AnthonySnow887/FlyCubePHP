<?php

namespace FlyCubePHP\WebSockets\ActionCable\Adapters;

interface BaseClientAdapter
{
    /**
     * Отправить данные клиентам
     * @param string $channel
     * @param mixed $message
     */
    public function broadcast(string $channel, $message);
}