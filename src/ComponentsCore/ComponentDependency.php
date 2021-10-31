<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 20.07.21
 * Time: 15:29
 */

namespace FlyCubePHP\ComponentsCore;

include_once __DIR__.'/../HelperClasses/Enum.php';

/**
 * Class CDType - тип зависимости
 * @package WebComponentsCore
 */
class CDType extends \FlyCubePHP\HelperClasses\Enum {
    const REQUIRED  = 0;
    const OPTIONAL  = 1;
}

class ComponentDependency
{
    protected $_name = "";                  # название плагина
    protected $_version = "";               # версия плагина
    protected $_type = CDType::REQUIRED;    # тип зависимости

    function __construct(string $name,
                         string $version,
                         int $type = CDType::REQUIRED) {
        $this->_name = $name;
        $this->_version = $version;
        $this->_type = $type;
    }

    public function name(): string {
        return $this->_name;
    }

    public function version(): string {
        return $this->_version;
    }

    public function type(): int {
        return $this->_type;
    }
}