<?php

namespace FlyCubePHP\WebSockets\ActionCable;

use FlyCubePHP\Core\Error\Error as Error;
use FlyCubePHP\Core\ActiveRecord\ActiveRecord;
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
     *
     * NOTE: if $this->rejectConnection() -> connection refused!
     */
    public function connect() {}

    /**
     * Обработка отключения клиента
     */
    public function disconnect() {}

    /**
     * Обработка подписки на канала
     *
     * NOTE: if $this->rejectSubscription() -> subscription refused!
     */
    public function subscribed() {}

    /**
     * Обработка отписки от канала
     */
    public function unsubscribed() {}

    /**
     * Обработка входящих данных, полученных от клиента
     * @param mixed $data Данные
     */
    public function receive($data) {}

    /**
     * Отправить данные клиентам
     * @param ActiveRecord $model Модель данных
     * @param mixed $message Данные
     * @throws
     */
    final protected function broadcastTo(ActiveRecord $model, $message)
    {
        ActionCable::serverBroadcast($this->broadcastingFor($model), $message);
    }

    /**
     * Возвращает уникальный идентификатор вещания для этой модели в этом канале
     * @param ActiveRecord $model Модель данных
     * @return string
     * @throws
     */
    final protected function broadcastingFor(ActiveRecord $model): string
    {
        $chName = CoreHelper::underscore($this->channelClassName());
        $modelGID = $model->modelGlobalID();
        return (empty($modelGID)) ? $chName : "$chName:$modelGID";
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
     * @throws
     */
    final protected function streamFrom(string $broadcasting)
    {
        if (is_null($this->_wsWorker))
            return;
        $this->_wsWorker->appendSubscriber($this->channelClassName(), $broadcasting);
    }

    /**
     * Начать трансляцию pub-sub очереди для модели в этом канале.
     * @param ActiveRecord $model Модель данных
     */
    final protected function streamFor(ActiveRecord $model)
    {
        $this->streamFrom($this->broadcastingFor($model));
    }

    /**
     * Вызывает "streamFor" с заданной моделью, если она присутствует, чтобы начать трансляцию, в противном случае отклоняет подписку.
     * @param ActiveRecord|null $model Модель данных
     */
    final protected function streamOrRejectFor(/*ActiveRecord|null*/ $model)
    {
        if (!is_null($model))
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
     * Останавливает трансляцию pub-sub в этом канале для модели.
     * @param ActiveRecord $model Модель данных
     */
    final protected function stopStreamFor(ActiveRecord $model)
    {
        $this->stopStreamFrom($this->broadcastingFor($model));
    }

    /**
     * Отменяет подписку на все трансляции pub-sub, связанные с этим каналом.
     * @throws
     */
    final protected function stopAllStreams()
    {
        if (is_null($this->_wsWorker))
            return;
        $this->_wsWorker->removeSubscribersByChannel($this->channelClassName());
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
     * Получить описание метода если он есть в классе
     * @param string $name
     * @return array
     */
    final public function channelMethod(string $name): array
    {
        $tmpMethods = $this->channelMethods();
        if (!isset($tmpMethods[$name]))
            return [];
        return $tmpMethods[$name];
    }

    /**
     * Получить список доступных методов с описанием
     * @return array
     */
    final public function channelMethods(): array
    {
        $tmpRef = null;
        try {
            $tmpRef = new \ReflectionClass($this);
        } catch (\Exception $e) {
            return [];
        }
        $tmpMethods = array();
        $methods = $tmpRef->getMethods(\ReflectionMethod::IS_STATIC | \ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $mName = $method->name;
            $mArgs = array();
            if (strlen($mName) > 2) {
                if ($mName[0] === "_" || $mName[0].$mName[1] === "__")
                    continue;
                if (strcmp($mName, 'connect') === 0
                    || strcmp($mName, 'disconnect') === 0
                    || strcmp($mName, 'subscribed') === 0
                    || strcmp($mName, 'unsubscribed') === 0
                    || strcmp($mName, 'receive') === 0
                    || strcmp($mName, 'broadcastTo') === 0
                    || strcmp($mName, 'broadcastingFor') === 0
                    || strcmp($mName, 'setWSWorker') === 0
                    || strcmp($mName, 'streamFrom') === 0
                    || strcmp($mName, 'streamFor') === 0
                    || strcmp($mName, 'streamOrRejectFor') === 0
                    || strcmp($mName, 'stopStreamFrom') === 0
                    || strcmp($mName, 'stopStreamFor') === 0
                    || strcmp($mName, 'stopAllStreams') === 0
                    || strcmp($mName, 'rejectConnection') === 0
                    || strcmp($mName, 'isRejectConnection') === 0
                    || strcmp($mName, 'rejectSubscription') === 0
                    || strcmp($mName, 'isRejectSubscription') === 0)
                    continue;
            }
            foreach ($method->getParameters() as $arg)
                $mArgs[] = $arg->name;
            $tmpMethods[$mName] = array("name" => $mName, "args" => $mArgs);
        }
        return $tmpMethods;
    }

    /**
     * Имя текущего канала
     * @return string
     * @throws
     */
    final protected function channelName(): string {
        $tmpName = $this->channelClassName();
        if (preg_match("/.*Channel$/", $tmpName))
            $tmpName = substr($tmpName, 0, strlen($tmpName) - 7);
        return $tmpName;
    }

    /**
     * Имя класса текущего канала
     * @return string
     * @throws
     */
    final protected function channelClassName(): string {
        $tmpRef = null;
        try {
            $tmpRef = new \ReflectionClass($this);
        } catch (\Exception $e) {
            throw new Error($e->getMessage(), "channel-base");
        }
        $tmpName = $tmpRef->getShortName();
        unset($tmpRef);
        return $tmpName;
    }
}