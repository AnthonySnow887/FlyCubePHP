<?php

class BaseDatabaseResult
{
    private $_sth = null; // PDOStatement

    public function __construct(\PDOStatement &$sth) {
        $this->_sth = $sth;
    }

    public function columnCount(): int {
        return $this->_sth->columnCount();
    }

    public function rowCount(): int {
        return $this->_sth->rowCount();
    }

    public function fetch($mode = PDO::FETCH_BOTH)/*: mixed*/ {
        return $this->_sth->fetch($mode);
    }

    public function fetchAll($mode = PDO::FETCH_BOTH, ...$args)/*: array|false*/ {
        return $this->_sth->fetchAll($mode, ...$args);
    }

    public function fetchColumn(int $column = 0)/*: mixed*/ {
        return $this->_sth->fetchColumn($column);
    }

    public function fetchObject($class = "stdClass", array $constructorArgs = [])/*: mixed*/ {
        return $this->_sth->fetchObject($class, $constructorArgs);
    }
}