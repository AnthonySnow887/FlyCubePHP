<?php

namespace FlyCubePHP\Core\HelpDoc;

class HelpPart
{
    private $_id;
    private $_level;
    private $_heading;
    private $_data;
    private $_subParts = [];
    private $_enableHeadingLinks = false;
    private $_parentPart = null;

    /**
     * @param string $id ИД раздела
     * @param int $level Уровень раздела
     * @param string $heading Название раздела
     * @param string $data Данные раздела
     * @param bool $enableHeadingLinks
     * @param HelpPart|null $parentPart
     */
    function __construct(string $id,
                         int $level,
                         string $heading,
                         string $data,
                         bool $enableHeadingLinks = false,
                         /*HelpPart|null*/ $parentPart = null)
    {
        $this->_id = $id;
        $this->_level = $level;
        $this->_heading = trim($heading);
        $this->_data = rtrim(ltrim($data, "\n\r"));
        $this->_enableHeadingLinks = $enableHeadingLinks;
        $this->_parentPart = $parentPart;
    }

    /**
     * ИД раздела
     * @return string
     */
    public function id(): string
    {
        return $this->_id;
    }

    /**
     * Уровень раздела
     * @return int
     */
    public function level(): int
    {
        return $this->_level;
    }

    /**
     * Название раздела
     * @return string
     */
    public function heading(): string
    {
        return $this->_heading;
    }

    /**
     * Данные раздела
     * @return string
     */
    public function data(): string
    {
        return $this->_data;
    }

    /**
     * Получить родительский раздел
     * @return HelpPart|null
     */
    public function parentPart() /*:HelpPart|null*/
    {
        return $this->_parentPart;
    }

    /**
     * Добавить данные в раздел
     * @param string $data
     */
    public function appendData(string $data)
    {
        $tmpData = rtrim(ltrim($data, "\n\r"));
        if (strlen($tmpData) === 0)
            return;
        $this->_data .= "\n$tmpData";
    }

    /**
     * Содержит ли подразделы?
     * @return bool
     */
    public function hasSubParts(): bool
    {
        return !empty($this->_subParts);
    }

    /**
     * Получить список подразделов
     * @return array
     */
    public function subParts(): array
    {
        return $this->_subParts;
    }

    /**
     * Получить последний добавленный подраздел
     * @return HelpPart|null
     */
    public function lastSubPart() /*: HelpPart|null*/
    {
        if (empty($this->_subParts))
            return null;
        return end($this->_subParts);
    }

    /**
     * Поиск подраздела
     * @param int $level Уровень раздела (если <= 0, то игнорируется при поиске)
     * @param string $heading Название раздела
     * @return HelpPart|null
     */
    public function findSubPart(int $level, string $heading) /*: HelpPart|null*/
    {
        if (!$this->hasSubParts())
            return null;
        foreach ($this->_subParts as $part) {
            if (strcmp($part->heading(), $heading) === 0) {
                if ($level <= 0)
                    return $part;
                else if ($part->level() == $level)
                    return $part;
            }
            $tmpPart = $part->findSubPart($level, $heading);
            if (!is_null($tmpPart))
                return $tmpPart;
        }
        return null;
    }

    /**
     * Добавить подраздел
     * @param HelpPart $part
     */
    public function appendSubPart(HelpPart $part)
    {
        if (isset($this->_subParts[$part->heading()]))
            return;
        $this->_subParts[$part->heading()] = $part;
    }

    /**
     * Получить help-doc в формате markdown
     * @return string
     */
    public function buildMarkdown(): string
    {
        $headingID = "";
        if ($this->_enableHeadingLinks === true)
            $headingID = " {#". $this->_id ."}";

        $md  = str_repeat("#", $this->_level) . " " . $this->_heading . "$headingID\n\n";
        $md .= $this->_data . "\n";
        foreach ($this->_subParts as $part)
            $md .= "\n" . trim($part->buildMarkdown()) . "\n";
        $md .= "\n";
        return $md;
    }
}