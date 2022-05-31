<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 31.08.21
 * Time: 16:49
 */

namespace FlyCubePHP\HelperClasses;


class MimeTypes
{
    private $_mimeTypes = array();

    /**
     * Поиск mime-type по расширению файла
     * @param string $ext
     * @return mixed|null
     */
    static public function mimeType(string $ext) {
        $m = new MimeTypes();
        $mType = $m->resolveMimeType($ext);
        unset($m);
        return $mType;
    }

    /**
     * Поиск mime-type по расширению файла
     * @param string $ext
     * @return mixed|null
     */
    public function resolveMimeType(string $ext)/*: string*/ {
        if (empty($this->_mimeTypes))
            $this->_mimeTypes = $this->systemExtensionMimeTypes();

        $ext = strtolower(trim($ext));
        return $this->_mimeTypes[$ext] ?? null;
    }

    /**
     * Загрузка списка mime-type
     * @return array
     */
    private function systemExtensionMimeTypes(): array {
        # Returns the system MIME type mapping of extensions to MIME types, as defined in /etc/mime.types.
        $out = array();
        $file = fopen('/etc/mime.types', 'r');
        while (($line = fgets($file)) !== false) {
            $line = trim(preg_replace('/#.*/', '', $line));
            if (!$line)
                continue;
            $parts = preg_split('/\s+/', $line);
            if (count($parts) == 1)
                continue;
            $type = array_shift($parts);
            foreach ($parts as $part)
                $out[$part] = $type;
        }
        fclose($file);
        return $out;
    }
}