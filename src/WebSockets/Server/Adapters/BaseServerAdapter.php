<?php

namespace FlyCubePHP\WebSockets\Server\Adapters;

abstract class BaseServerAdapter
{
    protected $_workersControls = array();

    function __construct(array $workersControls)
    {
        $this->_workersControls = $workersControls;
    }

    /**
     * Метод запуска обработчика событий от контроллеров для отправки клиентам
     */
    abstract public function run();
}