<?php

namespace FlyCubePHP\WebSockets\ActionCable;

use FlyCubePHP\Core\Error\Error as Error;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\WebSockets\Server\WSWorker;

include_once 'ActionCable.php';

abstract class BaseChannel
{
    private $_rejectConnection = false;
    private $_rejectSubscription = false;
    private $_wsWorker = null;
    private $_friendClasses = [
        'FlyCubePHP\WebSockets\Server\WSWorker'
    ];

    /**
     * Обработка входящего соединения
     * @param array $params Массив параметров от клиента
     * @param array $cookie Массив cookie от клиента
     *
     * NOTE: if $this->rejectConnection() -> connection refused!
     */
    public function connect(array $params, array $cookie) {}

    public function disconnect(array $params, array $cookie) {}

    public function subscribed(array $params, array $cookie) {}

    public function unsubscribed(array $params, array $cookie) {}

    public function receive(array $params, array $cookie, $data) {}

    /**
     * Отправить данные клиентам
     * @param string $model Модель канала
     * @param mixed $message Данные
     * @throws
     */
    final static public function broadcastTo(string $model, $message)
    {
        ActionCable::serverBroadcast(self::broadcastingFor($model), $message);
    }

    /**
     * Возвращает уникальный идентификатор вещания для этой модели в этом канале
     * @param string $model Модель канала
     * @return string
     * @throws
     */
    final static public function broadcastingFor(string $model): string
    {
        $chName = CoreHelper::underscore(self::channelName());
        $model = CoreHelper::underscore($model);
        return (empty($model)) ? $chName : "$chName:$model";
    }

    /**
     * Задать обработчик-родитель web sockets
     * @param WSWorker $worker
     *
     * NOTE: Only for 'FlyCubePHP\WebSockets\Server\WSWorker' class!
     */
    final public function setWSWorker(WSWorker &$worker)
    {
        $trace = debug_backtrace();
        if (isset($trace[1]['class']) && in_array($trace[1]['class'], $this->_friendClasses)) {
            $this->_wsWorker = $worker;
            return;
        }
        trigger_error('Cannot access private property ' . __CLASS__ . '::$_wsWorker', E_USER_ERROR);
    }

    /**
     * Начать трансляцию pub-sub для названной очереди (broadcasting).
     * @param string $broadcasting Уникальный идентификатор вещания
     */
    final protected function streamFrom(string $broadcasting)
    {
        if (is_null($this->_wsWorker))
            return;
        $this->_wsWorker->appendSubscriber(self::channelName(), $broadcasting);
    }

    /**
     * Начать потоковую передачу очереди pub-sub для модели в этом канале.
     * @param string $model Модель канала
     */
    final protected function streamFor(string $model)
    {
        $this->streamFrom(self::broadcastingFor($model));
    }

    /**
     * Вызывает stream_for с заданной моделью, если она присутствует, чтобы начать потоковую передачу, в противном случае отклоняет подписку.
     * @param string $model
     */
    final protected function streamOrRejectFor(string $model)
    {
        if (!empty($model))
            $this->streamFor($model);
        else
            $this->rejectSubscription();
    }

    /**
     * Останавливает трансляцию pub-sub для названной очереди (broadcasting).
     * @param string $broadcasting
     */
    final protected function stopStreamFrom(string $broadcasting)
    {
        if (is_null($this->_wsWorker))
            return;
        $this->_wsWorker->removeSubscriber($broadcasting);
    }

    /**
     * Останавливает трансляцию pub-sub в этом канала для модели.
     * @param string $model
     */
    final protected function stopStreamFor(string $model)
    {
        $this->stopStreamFrom(self::broadcastingFor($model));
    }

    /**
     * Отменяет подписку на все трансляции pub-sub, связанные с этим каналом.
     */
    final protected function stopAllStreams()
    {
        if (is_null($this->_wsWorker))
            return;
        $this->_wsWorker->removeSubscribersByChannel(self::channelName());
    }

    /**
     * Отклонить подключение
     */
    final protected function rejectConnection()
    {
        $this->_rejectConnection = true;
    }

    /**
     * Было ли отклонено подключение
     * @return bool
     */
    final public function isRejectConnection(): bool
    {
        return $this->_rejectConnection;
    }

    /**
     * Отклонить подписку
     */
    final protected function rejectSubscription()
    {
        $this->_rejectSubscription = true;
    }

    /**
     * Была ли отклонена подписка
     * @return bool
     */
    final public function isRejectSubscription(): bool
    {
        return $this->_rejectSubscription;
    }

    /**
     * Имя текущего канала
     * @return string
     * @throws
     */
    final static protected function channelName(): string {
        $tmpName = self::channelClassName();
        if (preg_match("/.*Channel$/", $tmpName))
            $tmpName = substr($tmpName, 0, strlen($tmpName) - 7);
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
        $tmpName = $tmpRef->getShortName();
        unset($tmpRef);
        return $tmpName;
    }
}