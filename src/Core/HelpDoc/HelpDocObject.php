<?php

namespace FlyCubePHP\Core\HelpDoc;

include_once 'Helpers/HelpDocHelper.php';
include_once 'Helpers/HelpDocAssetHelper.php';
include_once 'Helpers/HelpDocHeadingHelper.php';
include_once 'HelpPart.php';

use FlyCubePHP\Core\Config\Config as Config;
use FlyCubePHP\Core\Error\Error;
use FlyCubePHP\Core\HelpDoc\Helpers\HelpDocHelper;
use FlyCubePHP\HelperClasses\CoreHelper;

class HelpDocObject
{
    private $_parts = [];
    private $_helpers = [];
    private $_isEnabledTOC = false;
    private $_enableHeadingLinks = false;
    private $_enableAppendData = false;

    function __construct()
    {
        $this->_isEnabledTOC = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_HELP_DOC_TOC, false));
        $this->_enableHeadingLinks = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_HELP_DOC_HEADING_LINKS, false));
        $this->_enableAppendData = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_HELP_DOC_APPEND_DATA, false));

        // --- append helpers ---
        $this->_helpers['image_path'] = new HelpDocHelper('image_path', [ 'FlyCubePHP\Core\HelpDoc\Helpers\HelpDocAssetHelper', 'image_path' ]);
        $this->_helpers['heading_id'] = new HelpDocHelper('heading_id', [ 'FlyCubePHP\Core\HelpDoc\Helpers\HelpDocHeadingHelper', 'heading_id' ]);
        $this->_helpers['heading_link'] = new HelpDocHelper('heading_link', [ 'FlyCubePHP\Core\HelpDoc\Helpers\HelpDocHeadingHelper', 'heading_link' ]);
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
     * Получить help-doc в формате markdown
     * @return string
     */
    public function buildMarkdown(): string
    {
        if ($this->isEmpty())
            return "";
        $md = "";
        if ($this->_isEnabledTOC === true) {
            $md .= "# Table of Contents\n\n";
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
                preg_match_all("/\{([\{\#])\s{0,}([\w]+)\s{0,}\(\s{0,}([a-zA-Z0-9_\-\.\,\'\"\:\{\}\[\]\s\/]+)\s{0,}\)\s{0,}([\}\#])\}/", $line, $matches);
                if (count($matches) >= 5) {
                    $size = count($matches[0]);
                    for ($i = 0; $i < $size; $i++) {
                        $replaceStr   = $matches[0][$i];
                        $tagOpen      = $matches[1][$i];
                        $helpFunc     = $matches[2][$i];
                        $helpFuncArgs = $this->parseHelpFunctionArgs($matches[3][$i]);
                        $tagClose     = $matches[4][$i];
                        // --- check ---
                        if (strcmp($tagOpen, '{') === 0 && strcmp($tagClose, '}') !== 0)
                            throw Error::makeError([
                                'tag' => 'help-doc',
                                'message' => 'Invalid closed symbol (not \'}\')!',
                                'class-name' => __CLASS__,
                                'class-method' => __FUNCTION__,
                                'file' => $filePath,
                                'line' => $lineNum
                            ]);
                        else if (strcmp($tagOpen, '#') === 0 && strcmp($tagClose, '#') !== 0)
                            throw Error::makeError([
                                'tag' => 'help-doc',
                                'message' => 'Invalid closed symbol (not \'#\')!',
                                'class-name' => __CLASS__,
                                'class-method' => __FUNCTION__,
                                'file' => $filePath,
                                'line' => $lineNum
                            ]);
                        else if (!$this->hasSupportedHelpFunction($helpFunc))
                            throw Error::makeError([
                                'tag' => 'help-doc',
                                'message' => "Unsupported help function (name: $helpFunc)!",
                                'class-name' => __CLASS__,
                                'class-method' => __FUNCTION__,
                                'file' => $filePath,
                                'line' => $lineNum
                            ]);

                        // --- skip help function ---
                        if (strcmp($tagOpen, '#') === 0) {
                            $replaceValue = "";
                        } else {
                            // --- eval help function ---
                            try {
                                $replaceValue = $this->evalHelpFunction($helpFunc, $helpFuncArgs);
                            } catch (\Throwable $ex) {
                                throw Error::makeError([
                                    'tag' => 'help-doc',
                                    'message' => $ex->getMessage(),
                                    'class-name' => __CLASS__,
                                    'class-method' => __FUNCTION__,
                                    'previous' => $ex,
                                    'file' => $filePath,
                                    'line' => $lineNum
                                ]);
                            }
                        }
                        $rawLine = str_replace($replaceStr, $replaceValue, $rawLine);
                    }
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
                    $tmpID = sha1($currentParent->id().":$level:$heading");
                    $tmpPart = new HelpPart($tmpID, $level, $heading, $data, $this->_enableHeadingLinks);
                    $currentParent->appendSubPart($tmpPart);
                } else if ($this->_enableAppendData === true) {
                    $tmpPart->appendData($data);
                }
                $currentParent = $tmpPart;
            } else if ($currentRoot->level() < $level) {
                $tmpPart = $currentRoot->findSubPart($level, $heading);
                if (is_null($tmpPart)) {
                    $tmpID = sha1($currentParent->id().":$level:$heading");
                    $tmpPart = new HelpPart($tmpID, $level, $heading, $data, $this->_enableHeadingLinks);
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
     * Метод разбора аргументов функции
     * @param string $str
     * @param string $delimiter
     * @return array
     */
    private function parseHelpFunctionArgs(string $str, string $delimiter = ","): array
    {
        $str = trim($str);
        if (strlen($str) === 0)
            return [];
        $tmpArgs = [];
        $currentArg = "";
        $quote = false;
        $dblQuote = false;
        $array = false;
        $hash = false;
        $prevChar = null;
        for ($i = 0; $i < strlen($str); $i++) {
            $char = $str[$i];
            if ($i > 0)
                $prevChar = $str[$i - 1];

            // --- check quotes ---
            if (strcmp($char, "'") === 0 && !$dblQuote
                && (is_null($prevChar) || strcmp($prevChar, "\\") !== 0)) {
                $quote = !$quote;
            } else if (strcmp($char, "\"") === 0 && !$quote
                && (is_null($prevChar) || strcmp($prevChar, "\\") !== 0)) {
                $dblQuote = !$dblQuote;
            }
            // --- check array ---
            else if (strcmp($char, "[") === 0 && !$array
                && (is_null($prevChar) || strcmp($prevChar, "\\") !== 0)) {
                $array = true;
            } else if (strcmp($char, "]") === 0 && $array
                && (is_null($prevChar) || strcmp($prevChar, "\\") !== 0)) {
                $array = false;
            }
            // --- check hash ---
            else if (strcmp($char, "{") === 0 && !$hash
                && (is_null($prevChar) || strcmp($prevChar, "\\") !== 0)) {
                $hash = true;
            } else if (strcmp($char, "}") === 0 && $hash
                && (is_null($prevChar) || strcmp($prevChar, "\\") !== 0)) {
                $hash = false;
            }

            // --- check delimiter ---
            if (strcmp($char, $delimiter) === 0
                && !$quote
                && !$dblQuote
                && !$array
                && !$hash) {
                $tmpArgs[] = $this->prepareFunctionArg($currentArg);
                $currentArg = "";
                continue;
            }
            $currentArg .= $char;
        }
        $tmpArgs[] = $this->prepareFunctionArg($currentArg);
        return $tmpArgs;
    }

    /**
     * Метод преобразования значения аргумента функции
     * @param string $str
     * @return float|int|mixed|string|null
     */
    private function prepareFunctionArg(string $str)
    {
        $str = trim($str);
        if (strlen($str) === 0)
            return null;
        if (preg_match('/^[\'\"](.*)[\'\"]$/', $str, $matches))
            return strval($matches[1]);
        else if (preg_match('/^([+-]?([0-9]*))$/', $str, $matches))
            return intval($matches[1]);
        else if (preg_match('/^([+-]?([0-9]*[.])?[0-9]+)$/', $str, $matches))
            return floatval($matches[1]);
        else if (preg_match('/^\{(.*)\}$/', $str, $matches))
            return json_decode($str, true);
        else if (preg_match('/^\[(.*)\]$/', $str, $matches))
            return json_decode($str, true);
        return null;
    }

    /**
     * Проверить наличие требуемой вспомогательной функции
     * @param string $funcName Название
     * @return bool
     */
    private function hasSupportedHelpFunction(string $funcName): bool
    {
        return isset($this->_helpers[$funcName]);
    }

    /**
     * Выполнить требуемую вспомогательную функцию
     * @param string $funcName Название
     * @param array $args Аргументы
     * @return string
     * @throws Error
     */
    private function evalHelpFunction(string $funcName, array $args): string
    {
        if (!$this->hasSupportedHelpFunction($funcName))
            throw Error::makeError([
                'tag' => 'help-doc',
                'message' => "Eval unsupported help function (name: \'$funcName\')!",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);
        return $this->_helpers[$funcName]->evalFunction($args);
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