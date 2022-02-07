<?php

namespace FlyCubePHP\Core\HelpDoc;

class HelpPart
{
    private $_level;
    private $_heading;
    private $_data;
    private $_subParts = [];

    function __construct(int $level, string $heading, string $data)
    {
        $this->_level = $level;
        $this->_heading = trim($heading);
        $this->_data = $data;
    }

    public function level(): int
    {
        return $this->_level;
    }

    public function heading(): string
    {
        return $this->_heading;
    }

    public function data(): string
    {
        return $this->_data;
    }

    public function hasSubParts(): bool
    {
        return !empty($this->_subParts);
    }

    public function subParts(): array
    {
        return $this->_subParts;
    }

    /**
     * @param string $heading
     * @return HelpPart|null
     */
    public function findSubPart(string $heading) /*: HelpPart|null*/
    {
        if (!$this->hasSubParts())
            return null;
        if (isset($this->_subParts[$heading]))
            return $this->_subParts[$heading];
        foreach ($this->_subParts as $part) {
            $tmpPart = $part->findSubPart($heading);
            if (!is_null($tmpPart))
                return $tmpPart;
        }
        return null;
    }

    public function appendSubPart(HelpPart $part)
    {
        if (isset($this->_subParts[$part->heading()]))
            return;
        $this->_subParts[$part->heading()] = $part;
    }
}