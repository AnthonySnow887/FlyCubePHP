<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 24.08.21
 * Time: 15:00
 */

namespace FlyCubePHP\Core\Error;

include_once __DIR__.'/../../HelperClasses/Enum.php';

class ErrorType extends \FlyCubePHP\HelperClasses\Enum {
    const DEFAULT           = 0;
    const ASSET_PIPELINE    = 1;
    const ACTIVE_RECORD     = 2;
    const CONTROLLER        = 3;
    const DATABASE          = 4;
    const ROUTES            = 5;
    const COOKIE            = 6;
}