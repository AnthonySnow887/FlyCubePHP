<?php

namespace FlyCubePHP\Core\HelpDoc\Helpers;

class HelpDocHeadingHelper
{
    /**
     * Get id for heading
     * @param array $parts Array of headings in the order they appear in the document
     * @return string
     *
     * NOTE: heading array should consist of strings of the following format: "[Heading Level]:[Heading Name]"
     *
     * ==== Examples in Help-Doc notations
     *
     *   {{ heading_id(["1:This is test H1", "2:This is test H2"]) }}
     *   * => a9b53c340b7554d22f2ac3946841189377f6fe54
     */
    static public function heading_id(array $parts): string
    {
        $tmpID = "";
        foreach ($parts as $part) {
            if (!preg_match('/^(\d{1,6})\:(.*)$/', strval($part), $matches))
                continue;
            $level = $matches[1];
            $heading = $matches[2];
            if (!empty($tmpID))
                $tmpID = sha1("$tmpID:$level:$heading");
            else
                $tmpID = sha1("$level:$heading");
        }
        return $tmpID;
    }

    /**
     * Create a string with a link to the heading
     * @param string $name Link name
     * @param array $parts Array of headings in the order they appear in the document
     * @return string
     *
     * NOTE: heading array should consist of strings of the following format: "[Heading Level]:[Heading Name]"
     *
     * ==== Examples in Help-Doc notations
     *
     *   {{ heading_link('Test Link', ["1:This is test H1", "2:This is test H2"]) }}
     *   * => [Test Link](#a9b53c340b7554d22f2ac3946841189377f6fe54)
     */
    static public function heading_link(string $name, array $parts): string
    {
        // [Heading IDs](#heading-ids)
        return "[$name](#".static::heading_id($parts).")";
    }
}