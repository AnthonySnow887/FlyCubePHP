<?php

namespace FlyCubePHP\Core\HelpDoc;

include_once __DIR__.'/../TemplateCompiler/TemplateCompiler.php';
include_once 'Helpers/HelpDocAssetHelper.php';
include_once 'Helpers/HelpDocHeadingHelper.php';
include_once 'HelpPart.php';

use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\Core\Error\Error;
use FlyCubePHP\Core\TemplateCompiler\TCHelperFunction;
use FlyCubePHP\Core\TemplateCompiler\TemplateCompiler;
use FlyCubePHP\HelperClasses\CoreHelper;

class HelpDocObject
{
    private $_parts = [];
    private $_templateCompiler = null;
    private $_isEnabledTOC = false;
    private $_titleTOC = "Table of Contents";
    private $_isEnabledTOCSort = false;
    private $_TOCSortMaxLevel = -1;
    private $_enableHeadingLinks = false;
    private $_enableAppendData = false;

    function __construct()
    {
        $this->_isEnabledTOC = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_HELP_DOC_TOC, false));
        $this->_titleTOC = trim(strval(\FlyCubePHP\configValue(Config::TAG_HELP_DOC_TOC_TITLE, "Table of Contents")));
        $this->_isEnabledTOCSort = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_HELP_DOC_TOC_SORT, false));;
        $this->_TOCSortMaxLevel = intval(\FlyCubePHP\configValue(Config::TAG_HELP_DOC_TOC_SORT_MAX_LEVEL, -1));
        $this->_enableHeadingLinks = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_HELP_DOC_HEADING_LINKS, false));
        $this->_enableAppendData = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_HELP_DOC_APPEND_DATA, false));

        // --- append helpers ---
        $this->_templateCompiler = new TemplateCompiler();
        $this->_templateCompiler->appendHelpFunction(new TCHelperFunction('image_path', [ 'FlyCubePHP\Core\HelpDoc\Helpers\HelpDocAssetHelper', 'image_path' ]));
        $this->_templateCompiler->appendHelpFunction(new TCHelperFunction('heading_id', [ 'FlyCubePHP\Core\HelpDoc\Helpers\HelpDocHeadingHelper', 'heading_id' ]));
        $this->_templateCompiler->appendHelpFunction(new TCHelperFunction('heading_link', [ 'FlyCubePHP\Core\HelpDoc\Helpers\HelpDocHeadingHelper', 'heading_link' ]));
    }

    /**
     * Метод разбора help-doc файлов
     * @param array $files Список файлов справки
     * @param string $heading Заголовок требуемого раздела (если пустой, то возвращается весь HelpDoc)
     * @param int $level Уровень раздела (если <= 0, то игнорируется при поиске)
     * @return HelpDocObject
     * @throws Error
     */
    static public function parseHelpDoc(array $files, string $heading = "", int $level = -1): HelpDocObject
    {
        $hlp = new HelpDocObject();
        foreach ($files as $file)
            $hlp->parseHeadings($file);

        if (!empty($heading)) {
            $tmpPart = $hlp->findHelpPart($level, $heading);
            if (is_null($tmpPart))
                $hlp->clearRootPart();
            else
                $hlp->setRootPart($tmpPart);
        }
        if ($hlp->isEnabledTOCSort())
            $hlp->sortParts($hlp->TOCSortMaxLevel());
        return $hlp;
    }

    /**
     * Включена ли генерация содержимого при создании markdown
     * @return bool
     */
    public function isEnabledTOC(): bool {
        return $this->_isEnabledTOC;
    }

    /**
     * Включена ли генерация ИД для заголовков и ссылки на них в оглавлении
     * @return bool
     */
    public function isEnabledHeadingLinks(): bool {
        return $this->_enableHeadingLinks;
    }

    /**
     * Включена ли поддержка добавления данных в разделы с одинаковыми названиями
     * @return bool
     */
    public function isEnabledAppendData(): bool {
        return $this->_enableAppendData;
    }

    /**
     * Заголовок для оглавления
     * @return string
     */
    public function titleTOC(): string {
        return $this->_titleTOC;
    }

    /**
     * Включена ли сортировка подразделов
     * @return bool
     */
    public function isEnabledTOCSort(): bool {
        return $this->_isEnabledTOCSort;
    }

    /**
     * Максимальный уровень подраздела для сортировки (если -1, то сортируются все)
     * @return int
     */
    public function TOCSortMaxLevel(): int {
        return $this->_TOCSortMaxLevel;
    }

    /**
     * Является ли объект справки пустым (без разделов)?
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->_parts);
    }

    /**
     * Разделы объекта справки
     * @return array
     */
    public function parts(): array
    {
        return $this->_parts;
    }

    /**
     * Отсортировать разделы
     * @param int $maxLevel Максимальный уровень раздела для сортировки
     */
    public function sortParts(int $maxLevel = -1)
    {
        if ($this->isEmpty())
            return;
        $firstPartArr = array_slice($this->_parts, 0, 1);
        $firstPart = array_shift($firstPartArr);
        if (!$firstPart)
            return;
        if ($maxLevel != -1 && $firstPart->level() > $maxLevel)
            return;
        usort($this->_parts, function ($item1, $item2) {
            return $item1->heading() <=> $item2->heading();
        });
        foreach ($this->_parts as $sPart)
            $sPart->sortSubParts($maxLevel);
    }

    /**
     * Получить help-doc в формате markdown
     * @return string
     */
    public function buildMarkdown(): string
    {
        if ($this->isEmpty())
            return "";
        $md = "";
        if ($this->_isEnabledTOC === true) {
            $md .= "# ".$this->_titleTOC."\n\n";
            $md .= $this->makeTableOfContents() . "\n\n";
        }
        foreach ($this->_parts as $part)
            $md .= "\n" . trim($part->buildMarkdown()) . "\n";
        return trim($md);
    }

    // --- private functions ---

    /**
     * Очистить корневые разделы справки
     */
    private function clearRootPart()
    {
        $this->_parts = [];
    }

    /**
     * Задать корневой раздел справки
     * @param HelpPart $part
     */
    private function setRootPart(HelpPart &$part)
    {
        $this->_parts = [ $part ];
    }

    /**
     * Найти или создать раздел справки
     * @param int $level
     * @param string $heading
     * @param string $data
     * @return HelpPart|mixed
     */
    private function helpPart(int $level, string $heading, string $data)
    {
        foreach ($this->_parts as $part) {
            if ($part->level() == $level
                && strcmp($part->heading(), $heading) === 0) {
                if ($this->_enableAppendData === true)
                    $part->appendData($data);
                return $part;
            }
            $tmpPart = $part->findSubPart($level, $heading);
            if (!is_null($tmpPart)) {
                if ($this->_enableAppendData === true)
                    $tmpPart->appendData($data);
                return $tmpPart;
            }
        }
        $tmpID = sha1("$level:$heading");
        $tmpPart = new HelpPart($tmpID, $level, $heading, $data, $this->_enableHeadingLinks);
        $this->_parts[] = $tmpPart;
        return $tmpPart;
    }

    /**
     * Поиск раздела справки
     * @param int $level Уровень раздела (если <= 0, то игнорируется при поиске)
     * @param string $heading
     * @return mixed|null
     */
    private function findHelpPart(int $level, string $heading)
    {
        foreach ($this->_parts as $part) {
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
     * Метод разбора разделов справки
     * @param string $filePath
     * @throws Error
     */
    private function parseHeadings(string $filePath)
    {
        if (!file_exists($filePath))
            return;
        $file = fopen($filePath, "r");
        if ($file === false)
            throw Error::makeError([
                'tag' => 'help-doc',
                'message' => "Read help file failed! Path: $filePath",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);

        $curRoot = null;
        $curParent = null;
        $prevRawLine = "";
        $headingLevel = -1;
        $headingName = "";
        $headingData = "";
        $lineNum = 0;
        while (!feof($file)) {
            $rawLine = fgets($file);
            $line = trim($rawLine);
            $prevLine = trim($prevRawLine);
            $lineNum += 1;
            if (!empty($line) && preg_match('/^(\#{1,6})\s(.*)$/', $line, $matches)) {
                // --- save prev ---
                if (!empty($headingName))
                    $this->makePart($curRoot, $curParent, $headingLevel, $headingName, $headingData);

                $headingLevel = strlen(trim($matches[1]));
                $headingName = trim($matches[2]);
                $headingData = "";
                continue;
            } else if (!empty($prevLine)
                       && !empty($line)
                       && preg_match('/^(\=+)$/', $line)
                       && strlen($prevLine) == strlen($line)) {
                // --- save prev ---
                if (!empty($headingName)) {
                    // --- remove prev line ---
                    $headingData = substr($headingData, 0, strlen($headingData) - strlen($prevRawLine));
                    // --- make part ---
                    $this->makePart($curRoot, $curParent, $headingLevel, $headingName, $headingData);
                }

                $headingLevel = 1;
                $headingName = trim($prevLine);
                $headingData = "";
                continue;
            } else if (!empty($prevLine)
                       && !empty($line)
                       && preg_match('/^(\-+)$/', $line)
                       && strlen($prevLine) == strlen($line)) {
                // --- save prev ---
                if (!empty($headingName)) {
                    // --- remove prev line ---
                    $headingData = substr($headingData, 0, strlen($headingData) - strlen($prevRawLine));
                    // --- make part ---
                    $this->makePart($curRoot, $curParent, $headingLevel, $headingName, $headingData);
                }

                $headingLevel = 2;
                $headingName = trim($prevLine);
                $headingData = "";
                continue;
            }
            // --- prepare line data ---
            if (!empty($line)) {
                try {
                    $rawLine = $this->_templateCompiler->compileLine($rawLine, $lineNum);
                } catch (\Throwable $ex) {
                    throw Error::makeError([
                        'tag' => 'help-doc',
                        'message' => $ex->getMessage(),
                        'class-name' => __CLASS__,
                        'class-method' => __FUNCTION__,
                        'file' => $filePath,
                        'line' => $lineNum
                    ]);
                }
            }
            if (!empty($headingName))
                $headingData .= $rawLine;

            $prevRawLine = $rawLine;
        }
        // --- save prev ---
        if (!empty($headingName))
            $this->makePart($curRoot, $curParent, $headingLevel, $headingName, $headingData);

        fclose($file);
    }

    /**
     * Создать новый раздел справки
     * @param HelpPart|null $currentRoot Текущий корневой раздел
     * @param HelpPart|null $currentParent Текущий родительский раздел
     * @param int $level
     * @param string $heading
     * @param string $data
     */
    private function makePart(/*HelpPart|null*/ &$currentRoot,
                              /*HelpPart|null*/ &$currentParent,
                              int $level,
                              string $heading,
                              string $data)
    {
        if (empty($heading))
            return;
        if (is_null($currentParent)) {
            $currentRoot = $currentParent = $this->helpPart($level, $heading, $data);
        } else {
            if ($currentParent->level() < $level) {
                $tmpPart = $currentParent->findSubPart($level, $heading);
                if (is_null($tmpPart)) {
                    $tmpID = sha1($currentParent->id() . ":$level:$heading");
                    $tmpPart = new HelpPart($tmpID, $level, $heading, $data, $this->_enableHeadingLinks, $currentParent);
                    $currentParent->appendSubPart($tmpPart);
                } else if ($this->_enableAppendData === true) {
                    $tmpPart->appendData($data);
                }
                $currentParent = $tmpPart;
            } else if ($currentParent->level() === $level
                       && !is_null($currentParent->parentPart())) {
                $tmpPart = $currentParent->parentPart()->findSubPart($level, $heading);
                if (is_null($tmpPart)) {
                    $tmpID = sha1($currentParent->id() . ":$level:$heading");
                    $tmpPart = new HelpPart($tmpID, $level, $heading, $data, $this->_enableHeadingLinks, $currentParent->parentPart());
                    $currentParent->parentPart()->appendSubPart($tmpPart);
                } else if ($this->_enableAppendData === true) {
                    $tmpPart->appendData($data);
                }
                $currentParent = $tmpPart;
            } else if ($currentParent->level() > $level
                        && !is_null($currentParent->parentPart())) {
                $currentParent = $currentParent->parentPart();
                $this->makePart($currentRoot, $currentParent, $level, $heading, $data);
            } else if ($currentRoot->level() < $level) {
                $tmpPart = $currentRoot->findSubPart($level, $heading);
                if (is_null($tmpPart)) {
                    $tmpID = sha1($currentParent->id().":$level:$heading");
                    $tmpPart = new HelpPart($tmpID, $level, $heading, $data, $this->_enableHeadingLinks, $currentRoot);
                    $currentRoot->appendSubPart($tmpPart);
                } else if ($this->_enableAppendData === true) {
                    $tmpPart->appendData($data);
                }
                $currentParent = $tmpPart;
            } else {
                $currentRoot = $currentParent = $this->helpPart($level, $heading, $data);
            }
        }
    }

    /**
     * Создать оглавление справки в формате markdown
     * @param HelpPart|null $helpPart Раздел, с которого начнется генерация (если не задан, то с корневых)
     * @param string $tab Смещение объектов
     * @return string
     */
    private function makeTableOfContents(/*HelpPart|null*/ &$helpPart = null, string $tab = ""): string
    {
        $md = "";
        if (is_null($helpPart)) {
            foreach ($this->_parts as $part) {
                if ($this->_enableHeadingLinks === true)
                    $md .= "$tab * [" . $part->heading() . "](#".$part->id().")\n";
                else
                    $md .= "$tab * " . $part->heading() . "\n";

                if ($part->hasSubParts())
                    $md .= $this->makeTableOfContents($part, "$tab  ");
            }
        } else {
            foreach ($helpPart->subParts() as $part) {
                if ($this->_enableHeadingLinks === true)
                    $md .= "$tab * [" . $part->heading() . "](#".$part->id().")\n";
                else
                    $md .= "$tab * " . $part->heading() . "\n";

                if ($part->hasSubParts())
                    $md .= $this->makeTableOfContents($part, "$tab  ");
            }
        }
        return $md;
    }
}