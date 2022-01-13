<?php

namespace FlyCubePHP\WebSockets\Server\Adapters;

abstract class BaseAdapter
{
    protected $_workersControls = array();

    function __construct(array $workersControls)
    {
        $this->_workersControls = $workersControls;
    }

    abstract public function run();
}