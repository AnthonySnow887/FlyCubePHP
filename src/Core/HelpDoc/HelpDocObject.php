<?php

namespace FlyCubePHP\Core\HelpDoc;

include_once 'Helpers/HelpDocHelper.php';
include_once 'Helpers/HelpDocAssetHelper.php';
include_once 'HelpPart.php';

use FlyCubePHP\Core\Error\Error;
use FlyCubePHP\Core\HelpDoc\Helpers\HelpDocHelper;

class HelpDocObject
{
    private $_parts = [];
    private $_helpers = [];

    function __construct()
    {
        $this->_helpers['image_path'] = new HelpDocHelper('image_path', [ 'FlyCubePHP\Core\HelpDoc\Helpers\HelpDocAssetHelper', 'image_path' ]);
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

        // TODO build unique hash for all parts

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
     * @param bool $buildTableOfContents Генерировать оглавление?
     * @return string
     */
    public function buildMarkdown(bool $buildTableOfContents = false): string
    {
        if ($this->isEmpty())
            return "";
        $md = "";
        if ($buildTableOfContents === true) {
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
                && strcmp($part->heading(), $heading) === 0)
                return $part;
            $tmpPart = $part->findSubPart($level, $heading);
            if (!is_null($tmpPart))
                return $tmpPart;
        }
        $tmpPart = new HelpPart($level, $heading, $data);
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
        $prevLine = "";
        $headingLevel = -1;
        $headingName = "";
        $headingData = "";
        $lineNum = 0;
        while (!feof($file)) {
            $line = fgets($file);
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
                    $headingData = substr($headingData, 0, strlen($headingData) - strlen($prevLine));
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
                    $headingData = substr($headingData, 0, strlen($headingData) - strlen($prevLine));
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
                preg_match_all("/\{([\{\#])\s{0,}([\w]+)\s{0,}\(\s{0,}[\"\']{1}([a-zA-Z0-9_\-\.\s\/]+)[\"\']{1}\s{0,}\)\s{0,}([\}\#])\}/", $line, $matches);
                if (count($matches) >= 5) {
                    $size = count($matches[0]);
                    for ($i = 0; $i < $size; $i++) {
                        $replaceStr = $matches[0][$i];
                        $tagOpen    = $matches[1][$i];
                        $helpFunc   = $matches[2][$i];
                        $assetName = $matches[3][$i];
                        $tagClose   = $matches[4][$i];
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
                                $replaceValue = $this->evalHelpFunction($helpFunc, $assetName);
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
                        $line = str_replace($replaceStr, $replaceValue, $line);
                    }
                }
            }
            if (!empty($headingName))
                $headingData .= $line;

            $prevLine = $line;
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
                    $tmpPart = new HelpPart($level, $heading, $data);
                    $currentParent->appendSubPart($tmpPart);
                }
                $currentParent = $tmpPart;
            } else if ($currentRoot->level() < $level) {
                $tmpPart = $currentRoot->findSubPart($level, $heading);
                if (is_null($tmpPart)) {
                    $tmpPart = new HelpPart($level, $heading, $data);
                    $currentRoot->appendSubPart($tmpPart);
                }
                $currentParent = $tmpPart;
            } else {
                $currentRoot = $currentParent = $this->helpPart($level, $heading, $data);
            }
        }
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
     * @param mixed ...$args Аргументы
     * @return string
     * @throws Error
     */
    private function evalHelpFunction(string $funcName, /*mixed*/ ...$args): string
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
        // TODO add cross-reference (named anchor)
        $md = "";
        if (is_null($helpPart)) {
            foreach ($this->_parts as $part) {
                $md .= "$tab * " . $part->heading() . "\n";
                if ($part->hasSubParts())
                    $md .= $this->makeTableOfContents($part, "$tab  ");
            }
        } else {
            foreach ($helpPart->subParts() as $part) {
                $md .= "$tab * " . $part->heading() . "\n";
                if ($part->hasSubParts())
                    $md .= $this->makeTableOfContents($part, "$tab  ");
            }
        }
        return $md;
    }
}