<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 20.07.21
 * Time: 13:32
 */

namespace FlyCubePHP\ComponentsCore;

include_once 'BaseTypes.php';
include_once 'ComponentDependency.php';


/**
 * Class BaseComponent
 */
abstract class BaseComponent
{
    protected $_name = "";                  # название плагина
    protected $_version = "";               # версия плагина
    protected $_compat_version = "";        # версия совместимости плагина
    protected $_description = "";           # описание плагина
    protected $_dependence = array();       # хэш зависимостей
    protected $_state = BCState::NO_STATE;  # состояние плагина
    protected $_warnings = array();         # массив сообщений о предупреждениях
    protected $_errors = array();           # массив сообщений об ошибках
    protected $_directory = "";             # каталог плагина
    protected $_controllers = array();      # список файлов контроллеров

    /**
     * BaseComponent constructor.
     * @param string $name
     * @param string $version
     * @param string $compat_version
     * @param string $description
     */
    function __construct(string $name,
                         string $version,
                         string $compat_version,
                         string $description = "") {
        $this->_name = $name;
        $this->_version = $version;
        $this->_compat_version = $compat_version;
        $this->_description = $description;
    }

    /**
     * @brief BaseComponent destructor
     */
    function __destruct() {
        unset ($this->_dependence);
        $this->_dependence = array();
        unset ($this->_warnings);
        $this->_warnings = array();
        unset ($this->_errors);
        $this->_errors = array();
        unset ($this->_controllers);
        $this->_controllers = array();
    }

    /**
     * Имя плагина
     * @return string
     */
    public function name(): string {
        return $this->_name;
    }

    /**
     * Версия плагина
     * @return string
     */
    public function version(): string {
        return $this->_version;
    }

    /**
     * Версия совместимости плагина
     * @return string
     */
    public function compatVersion(): string {
        return $this->_compat_version;
    }

    /**
     * Описание плагина
     * @return string
     */
    public function description(): string {
        return $this->_description;
    }

    /**
     * Список обязательных зависимостей плагина
     * @return array
     */
    public function dependence(): array {
        $spec_list = array();
        foreach ($this->_dependence as $value) {
            if ($value->type() == CDType::REQUIRED)
                $spec_list[] = $value;
        }
        return$spec_list;
    }

    /**
     * Список необязательных зависимостей плагина
     * @return array
     */
    public function optionalDependence(): array {
        $spec_list = array();
        foreach ($this->_dependence as $value) {
            if ($value->type() == CDType::OPTIONAL)
                $spec_list[] = $value;
        }
        return$spec_list;
    }

    /**
     * Текущее состояние плагина
     * @return int
     */
    public function state(): int {
        return $this->_state;
    }

    /**
     * Задать текущее состояние плагина
     * @param int $state
     */
    public function setState(int $state) {
        $this->_state = $state;
    }

    /**
     * Есть ли предупреждения от плагина
     * @return bool
     */
    public function hasWarnings(): bool {
        return (count($this->_warnings) > 0);
    }

    /**
     * Все предупреждения плагина
     * @return array
     */
    public function warnings(): array {
        return $this->_warnings;
    }

    /**
     * Последнее предупреждение плагина
     * @return string
     */
    public function lastWarning(): string {
        if (count($this->_warnings) > 0)
            return $this->_warnings[count($this->_warnings) - 1];
        return "";
    }

    /**
     * Добавить новое предупреждение плагина. Автоматически добавляется вконец списка предупреждений.
     * @param string $warning
     */
    public function setLastWarning(string $warning) {
        if (empty($warning))
            return;
        $this->_warnings[] = $warning;
    }

    /**
     * Есть ли ошибки от плагина
     * @return bool
     */
    public function hasErrors(): bool {
        return (count($this->_errors) > 0);
    }

    /**
     * Все ошибки плагина
     * @return array
     */
    public function errors(): array {
        return $this->_errors;
    }

    /**
     * Последняя ошибка плагина
     * @return string
     */
    public function lastError(): string {
        if (count($this->_errors) > 0)
            return $this->_errors[count($this->_errors) - 1];
        return "";
    }

    /**
     * Добавить новое сообщение об ошибке. Автоматически добавляется вконец списка ошибок.
     * @param string $error
     */
    public function setLastError(string $error) {
        if (empty($error))
            return;
        $this->_errors[] = $error;
    }

    /**
     * Каталог плагина
     * @return string
     */
    public function directory(): string {
        return $this->_directory;
    }

    /**
     * Задать каталог плагина
     * @param string $directory
     */
    public function setDirectory(string $directory) {
        if (empty($directory))
            return;
        $this->_directory = $directory;
    }

    /**
     * Содержит ли плагин контроллеры
     * @return bool
     */
    public function hasControllers(): bool {
        return (count($this->_controllers) > 0);
    }

    /**
     * Список контроллеров плагина
     * @return array
     */
    public function controllers(): array {
        return $this->_controllers;
    }

    /**
     * Добавить контроллер плагина
     * @param $controller
     */
    public function addController($controller) {
        if (is_null($controller))
            return;
        $this->_controllers[] = $controller;
    }

    /**
     * Задать новый список контроллеров плагина
     * @param array $controllers
     */
    public function setControllers(array $controllers) {
        if (!is_array($controllers))
            return;
        unset ($this->_controllers);
        $this->_controllers = $controllers;
    }

    /**
     * Добавить обязательную зависимость плагина
     * @param string $name - название требуемого плагина
     * @param string $version - версия требуемого плагина
     */
    protected function addDependence(string $name, string $version) {
        if (array_key_exists($name, $this->_dependence))
            return;
        $this->_dependence[$name] = new ComponentDependency($name, $version);
    }

    /**
     * Добавить необязательную зависимость плагина
     * @param string $name - название требуемого плагина
     * @param string $version - версия требуемого плагина
     */
    protected function addOptionalDependence(string $name, string $version) {
        if (array_key_exists($name, $this->_dependence))
            return;
        $this->_dependence[$name] = new ComponentDependency($name, $version, CDType::OPTIONAL);
    }

    /**
     * @brief Метод инициализации плагина
     * @return bool
     *
     * Именно в данном методе должна происходить проверка и формирование данных и объектов плагина.
     */
    abstract public function init(): bool;
}