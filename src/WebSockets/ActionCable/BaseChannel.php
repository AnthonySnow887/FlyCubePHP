<?php

namespace FlyCubePHP\WebSockets\ActionCable;

use FlyCubePHP\Core\Error\Error as Error;

include_once 'ActionCable.php';

abstract class BaseChannel
{
    /**
     * Обработка входящего соединения
     * @param array $params Массив параметров от клиента
     * @param array $cookie Массив cookie от клиента
     * @return bool
     *
     * NOTE: if return 'false' -> connection refused!
     */
    public function connect(array $params, array $cookie): bool
    {
        return true;
    }

    public function subscribed() // TODO add params & cookie
    {
    }

    public function unsubscribed() // TODO add params & cookie
    {
    }

    public function receive($data)
    {
    }

    /**
     * Отправить данные клиентам
     * @param mixed $message Данные
     * @throws Error
     * @throws \FlyCubePHP\Core\Error\Error
     */
    static public function broadcast($message)
    {
        ActionCable::serverBroadcast(self::channelName(), $message);
    }

    /**
     * Имя класса текущего канала
     * @return string
     * @throws
     */
    final static protected function channelName(): string {
        $tmpRef = null;
        try {
            $tmpRef = new \ReflectionClass(self::class);
        } catch (\Exception $e) {
            throw new Error($e->getMessage(), "channel-base");
        }
        $tmpName = $tmpRef->getName();
        unset($tmpRef);
        return $tmpName;
    }
}