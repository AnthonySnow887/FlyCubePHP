<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 22.07.21
 * Time: 13:05
 */

namespace FlyCubePHP;

include_once 'AutoLoader.php';

function appendAutoLoadDir(string $dirPath) {
    Core\AutoLoader\AutoLoader::instance()->appendAutoLoadDir($dirPath);
}