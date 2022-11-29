<?php

namespace FlyCubePHP\Core\AssetPipeline\CSSBuilder\Compilers;

use FlyCubePHP\Core\Error\Error;
use FlyCubePHP\Core\Logger\Logger;
use \ScssPhp\ScssPhp\Logger\LoggerInterface;

class SCSSLogger implements LoggerInterface
{
    private $_filePath;

    function __construct(string $filePath) {
        $this->_filePath = basename($filePath);
    }

    /**
     * Emits a warning with the given message.
     *
     * If $deprecation is true, it indicates that this is a deprecation
     * warning. Implementations should surface all this information to
     * the end user.
     *
     * @param string $message
     * @param bool  $deprecation
     *
     * @return void
     */
    final public function warn($message, $deprecation = false) {
        try {
            $deprecationMsg = "";
            if ($deprecation)
                $deprecationMsg = "[DEPRECATED]";
            $message = $this->prepareMessage($message);
            foreach (preg_split("/((\r?\n)|(\r\n?))/", $message) as $line)
                Logger::warning("[SCSS]$deprecationMsg $line");
        } catch (Error $err) {
        }
    }

    /**
     * Emits a debugging message.
     *
     * @param string $message
     *
     * @return void
     */
    final public function debug($message) {
        try {
            $message = $this->prepareMessage($message);
            foreach (preg_split("/((\r?\n)|(\r\n?))/", $message) as $line)
                Logger::debug("[SCSS] $line");
        } catch (Error $err) {
        }
    }

    /**
     * Подготовить сообщение к выводу
     * @param $message
     * @return string
     */
    private function prepareMessage($message): string {
        return str_replace("(unknown file)", "(".$this->_filePath.")", $message);
    }
}