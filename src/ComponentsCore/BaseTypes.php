<?php

namespace FlyCubePHP\ComponentsCore;

include_once __DIR__.'/../HelperClasses/Enum.php';

/**
 * Class CMState - типы состояний менеджера компонентов
 */
class CMState extends \FlyCubePHP\HelperClasses\Enum {
    const NOT_LOADED    = 0; # не загружен
    const LOADED        = 1; # загружен
    const INITIALIZED   = 2; # проинициализирован
}

/**
 * Class BCState - типы состояний инициализации компонентов
 */
class BCState extends \FlyCubePHP\HelperClasses\Enum {
    const NO_STATE      = 0;
    const INIT_SUCCESS  = 1;
    const INIT_FAILED   = 2;
}

/**
 * Class CDType - типы зависимости компонента
 */
class CDType extends \FlyCubePHP\HelperClasses\Enum {
    const REQUIRED  = 0;
    const OPTIONAL  = 1;
}