<?php

namespace FlyCubePHP\WebSockets\ActionCable\Adapters;

interface BaseClientAdapter
{
    /**
     * Отправить данные клиентам
     * @param string $broadcasting Название канала вещания
     * @param mixed $message
     * @return bool
     */
    public function broadcast(string $broadcasting, $message): bool;
}