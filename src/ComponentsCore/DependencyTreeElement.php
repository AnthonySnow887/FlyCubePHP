<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 20.07.21
 * Time: 17:36
 */

namespace FlyCubePHP\ComponentsCore;


class DependencyTreeElement
{
    private $_plugin_name = "";     # имя плагина
    private $_plugin_version = "";  # версия плагина
    private $_node_level = 0;       # уровень узла
    private $_child = array();      # дочерние узлы
    private $_is_cyclic = false;    # является циклической зависимостью
    private $_is_optional = false;  # является обязательной или необязательной зависимостью
    private $_error = "";           # сообщение об ошибке

    function __construct(string $plugin_name,
                         string $plugin_version,
                         int $node_level = 0) {
        $this->_plugin_name = $plugin_name;
        $this->_plugin_version = $plugin_version;
        $this->_node_level = $node_level;
    }

    function __destruct() {
        unset($this->_child);
    }

    public function pluginName(): string {
        return $this->_plugin_name;
    }

    public function pluginVersion(): string {
        return $this->_plugin_version;
    }

    public function nodeLevel(): int {
        return $this->_node_level;
    }

    public function child(): array {
        return $this->_child;
    }

    public function isCyclic(): bool {
        return $this->_is_cyclic;
    }
    public function setIsCyclic(bool $val) {
        $this->_is_cyclic = $val;
    }

    public function isOptional(): bool {
        return $this->_is_optional;
    }
    public function setIsOptional(bool $val) {
        $this->_is_optional = $val;
    }

    public function error(): string {
        return $this->_error;
    }
    public function setError(string $val) {
        $this->_error = $val;
    }

    public function appendChild(DependencyTreeElement &$child) {
        $this->_child[] = $child;
    }

    public function searchChild(string $name)/*: DependencyTreeElement|null*/ {
        if (empty($name))
            return null;
        if ($name == $this->_plugin_name)
            return $this;
        if (count($this->_child) == 0)
            return null;
        foreach ($this->_child as &$ch) {
            if ($ch->pluginName() == $name)
                return $ch;
            $tmpCh = $ch->searchChild($name);
            if (!is_null($tmpCh))
                return $tmpCh;
        }
        return null;
    }
}