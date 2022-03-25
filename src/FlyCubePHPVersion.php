<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 30.07.21
 * Time: 14:16
 */

namespace FlyCubePHP;

const VERSION_MAJ = 1;
const VERSION_MIN = 7;
const VERSION_PATCH = 0;

function VERSION_STR(): string {
    $v_maj = \FlyCubePHP\VERSION_MAJ;
    $v_min = \FlyCubePHP\VERSION_MIN;
    $v_patch = \FlyCubePHP\VERSION_PATCH;
    return "$v_maj.$v_min.$v_patch";
}

define ('FLY_CUBE_PHP_MAJOR_VERSION', \FlyCubePHP\VERSION_MAJ);
define ('FLY_CUBE_PHP_MINOR_VERSION', \FlyCubePHP\VERSION_MIN);
define ('FLY_CUBE_PHP_RELEASE_VERSION', \FlyCubePHP\VERSION_PATCH);
define ('FLY_CUBE_PHP_VERSION', \FlyCubePHP\VERSION_STR());