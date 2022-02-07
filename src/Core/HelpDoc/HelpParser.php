<?php

namespace FlyCubePHP\Core\HelpDoc;

include_once 'HelpPart.php';

use FlyCubePHP\Core\Error\Error;

class HelpParser
{
    private $_parts = [];

    /**
     * @param array $files
     * @return string
     * @throws Error
     */
    static public function parse(array $files): string
    {
        $hlp = new HelpParser();
        foreach ($files as $file)
            $hlp->parseHeadings($file);

        // TODO build unique hash for all parts
        // TODO build table of contents
        // TODO build content
        // TODO return result markdown

        return $hlp->makeTableOfContents();
    }

    /**
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
        $headingLevel = -1;
        $headingName = "";
        $headingData = "";
        while (!feof($file)) {
            $line = fgets($file);
            if (!empty($line) && preg_match('/^(\#{1,6})\s(.*)$/', $line, $matches)) {
                // --- save prev ---
                if (!empty($headingName))
                    $this->makePart($curRoot, $curParent, $headingLevel, $headingName, $headingData);

                $headingLevel = strlen(trim($matches[1]));
                $headingName = trim($matches[2]);
                $headingData = "";
                continue;
            }
            if (!empty($headingName))
                $headingData .= $line;
        }
        // --- save prev ---
        if (!empty($headingName))
            $this->makePart($curRoot, $curParent, $headingLevel, $headingName, $headingData);

        fclose($file);
    }

    private function makePart(&$currentRoot,
                              &$currentParent,
                              int $headingLevel,
                              string $headingName,
                              string $headingData)
    {
        if (empty($headingName))
            return;
        if (is_null($currentParent)) {
            $currentRoot = $currentParent = new HelpPart($headingLevel, $headingName, $headingData);
            $this->_parts[] = $currentRoot;
        } else {
            if ($currentParent->level() < $headingLevel) {
                $tmp = $currentParent->findSubPart($headingName);
                if (is_null($tmp)) {
                    $tmp = new HelpPart($headingLevel, $headingName, $headingData);
                    $currentParent->appendSubPart($tmp);
                }
                $currentParent = $tmp;
            } else if ($currentRoot->level() < $headingLevel) {
                $tmp = $currentRoot->findSubPart($headingName);
                if (is_null($tmp)) {
                    $tmp = new HelpPart($headingLevel, $headingName, $headingData);
                    $currentRoot->appendSubPart($tmp);
                }
                $currentParent = $tmp;
            } else {
                $currentRoot = $currentParent = new HelpPart($headingLevel, $headingName, $headingData);
                $this->_parts[] = $currentRoot;
            }
        }
    }

    private function makeTableOfContents(&$helpPart = null, string $tab = ""): string
    {
        // TODO add cross-reference (named anchor)
        $tmpMD = "";
        if (is_null($helpPart)) {
            foreach ($this->_parts as $part) {
                $tmpMD .= "$tab * " . $part->heading() . "\n";
                if ($part->hasSubParts())
                    $tmpMD .= $this->makeTableOfContents($part, "$tab  ");
            }
        } else {
            foreach ($helpPart->subParts() as $part) {
                $tmpMD .= "$tab * " . $part->heading() . "\n";
                if ($part->hasSubParts())
                    $tmpMD .= $this->makeTableOfContents($part, "$tab  ");
            }
        }
        return $tmpMD;
    }
}