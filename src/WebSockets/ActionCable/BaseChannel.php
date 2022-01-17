<?php

namespace FlyCubePHP\WebSockets\ActionCable;

use FlyCubePHP\Core\Error\Error as Error;
use FlyCubePHP\HelperClasses\CoreHelper;

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

    public function disconnect(array $params, array $cookie)
    {
    }

    public function subscribed(array $params, array $cookie): bool
    {
        return true;
    }

    public function unsubscribed(array $params, array $cookie)
    {
    }

    public function receive(array $params, array $cookie, $data)
    {
    }

    /**
     * Отправить данные клиентам
     * @param string $model Модель канала
     * @param mixed $message Данные
     * @throws
     */
    static public function broadcastTo(string $model, $message)
    {
        ActionCable::serverBroadcast(self::broadcastingFor($model), $message);
    }

    /**
     * Возвращает уникальный идентификатор вещания для этой модели в этом канале
     * @param string $model Модель канала
     * @return string
     * @throws
     */
    static public function broadcastingFor(string $model): string
    {
        $chName = CoreHelper::underscore(self::channelName());
        $model = CoreHelper::underscore($model);
        return (empty($model)) ? $chName : "$chName:$model";
    }

    /**
     * Имя текущего канала
     * @return string
     * @throws
     */
    final static protected function channelName(): string {
        $tmpName = self::channelClassName();
        if (preg_match("/.*Channel$/", $tmpName))
            $tmpName = substr($tmpName, 0, strlen($tmpName) - 10);
        return $tmpName;
    }

    /**
     * Имя класса текущего канала
     * @return string
     * @throws
     */
    final static protected function channelClassName(): string {
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