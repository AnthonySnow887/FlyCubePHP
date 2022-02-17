<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 22.07.21
 * Time: 13:05
 */

namespace FlyCubePHP;

include_once 'AutoLoader.php';

/**
 * Добавить каталог для автоматической загрузки файлов из него
 * @param string $dirPath
 */
function appendAutoLoadDir(string $dirPath) {
    Core\AutoLoader\AutoLoader::instance()->appendAutoLoadDir($dirPath);
}

/**
 * Добавить библиотеку и ее каталог для автоматической загрузки файлов из него
 * @param string $libRootNamespace Корневой префикс namespace библиотеки
 * @param string $libRootDir Корневой каталог библиотеки
 *
 * ==== Example
 *
 *  appendAutoLoadLib('cebe\\markdown', 'vendor/Markdown');
 */
function appendAutoLoadLib(string $libRootNamespace, string $libRootDir) {
    Core\AutoLoader\AutoLoader::instance()->appendAutoLoadLib($libRootNamespace, $libRootDir);
}