<?php

namespace FlyCubePHP\WebSockets\Server\Adapters;

use FlyCubePHP\Core\Logger\Logger;
use FlyCubePHP\WebSockets\Config\WSConfig;
use FlyCubePHP\WebSockets\Server\WSWorker;

include_once 'BaseServerAdapter.php';

class RedisServerAdapter extends BaseServerAdapter
{
    private $_host;
    private $_port;
    private $_password;
    private $_channelName;

    function __construct(array $workersControls)
    {
        parent::__construct($workersControls);
        $this->_host = WSConfig::instance()->currentSettingsValue(WSConfig::TAG_REDIS_HOST, "");
        $this->_port = WSConfig::instance()->currentSettingsValue(WSConfig::TAG_REDIS_PORT, 6379);
        $this->_password = WSConfig::instance()->currentSettingsValue(WSConfig::TAG_REDIS_PASSWORD, "");
        $this->_channelName = WSConfig::instance()->currentSettingsValue(WSConfig::TAG_REDIS_CHANNEL, "");
        if (empty($this->_host)
            || empty($this->_channelName)
            || $this->_port <= 0) {
            $errMsg = "[". self::class ."] Invalid Redis settings!";
            $this->log(Logger::ERROR, $errMsg);
            fwrite(STDERR, "$errMsg\r\n");
            die();
        }
    }

    /**
     * Метод запуска обработчика событий от контроллеров для отправки клиентам
     */
    public function run()
    {
        // --- set unlimited socket io timeout ---
        ini_set("default_socket_timeout", -1);

        $redis = new \Redis();
        $connected = $redis->connect($this->_host, $this->_port);
        if ($connected === false) {
            $errMsg = "[". self::class ."] Connect to Redis failed (host: ".$this->_host.":".$this->_port.")!";
            $this->log(Logger::ERROR, $errMsg);
            fwrite(STDERR, "$errMsg\r\n");
            die();
        }
        if (!empty($this->_password)) {
            $isAuth = $redis->auth($this->_password);
            if ($isAuth === false) {
                $errMsg = "[". self::class ."] Redis authorization failed (host: ".$this->_host.":".$this->_port.")!";
                $this->log(Logger::ERROR, $errMsg);
                fwrite(STDERR, "$errMsg\r\n");
                die();
            }
        }

        $infoMsg = "[". self::class ."] Server adapter connected to Redis: ".$this->_host.":".$this->_port;
        Logger::info($infoMsg);
        echo "$infoMsg\r\n";

        // --- start worker loop ---
        $redis->subscribe([ $this->_channelName ], [ $this, 'processMessage' ]);
    }

    /**
     * Обработка входящих событий
     * @param \Redis $redis
     * @param string $channel
     * @param string $message
     */
    public function processMessage(\Redis $redis, string $channel, string $message)
    {
        $this->sendToWorkers($message);
    }

    /**
     * Запись данных в сокет
     * @param $sock
     * @param $data
     */
    protected function write($sock, $data)
    {
        $written = fwrite($sock, $data, WSConfig::SOCKET_BUFFER_SIZE);
        $data = substr($data, $written);
        if (!empty($data))
            $this->write($sock, $data);
    }

    /**
     * Отправка данных дочерним потокам
     * @param $data
     */
    protected function sendToWorkers($data)
    {
        foreach ($this->_workersControls as $control)
            $this->write($control, $data);
    }

    /**
     * Отправить сообщение в лог
     * @param $level
     * @param $message
     * @param array $context
     */
    protected function log($level, $message, array $context = array())
    {
        try {
            Logger::log($level, "[". self::class ."] $message", $context);
        } catch (\Exception $e) {
        }
    }
}