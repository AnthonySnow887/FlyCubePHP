<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 22.07.21
 * Time: 14:17
 */

namespace FlyCubePHP\Core\Controllers\Helpers;


use FlyCubePHP\HelperClasses\CoreHelper;

abstract class BaseControllerHelper
{
    private $_functionSettings = array();

    /**
     * Список доступных методов хелпера
     * @return array
     */
    final public function helperMethods(): array {
        $tmpRef = null;
        try {
            $tmpRef = new \ReflectionClass($this);
        } catch (\Exception $e) {
            echo 'Exception: ',  $e->getMessage(), "\n";
        }
        $tmpMethods = array();
        $methods = $tmpRef->getMethods(\ReflectionMethod::IS_STATIC | \ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            $mName = $method->name;
            $mArgs = array();
            if (strlen($mName) > 2) {
                if ($mName[0] === "_" || $mName[0].$mName[1] === "__")
                    continue;
                if (strcmp($mName, 'helperMethods') === 0
                    || strcmp($mName, 'functionSettings') === 0
                    || strcmp($mName, 'isSafeFunction') === 0
                    || strcmp($mName, 'isNeedsContext') === 0
                    || strcmp($mName, 'isNeedsEnvironment') === 0
                    || strcmp($mName, 'setFunctionSettings') === 0
                    || strcmp($mName, 'appendSafeFunction') === 0
                    || strcmp($mName, 'appendNeedContext') === 0
                    || strcmp($mName, 'appendNeedEnvironment') === 0)
                    continue;
            }
            foreach ($method->getParameters() as $arg)
                $mArgs[] = $arg->name;
            $tmpMethods[] = array("name" => $mName, "args" => $mArgs);
        }
        return $tmpMethods;
    }

    /**
     * Получить список настроек функций
     * @return array
     */
    final public function functionSettings(): array {
        return $this->_functionSettings;
    }

    /**
     * Является ли функция безопасной
     * @param string $name
     * @return bool
     */
    final public function isSafeFunction(string $name): bool {
        if (isset($this->_functionSettings[$name])
            && isset($this->_functionSettings[$name]["safe"]))
            return $this->_functionSettings[$name]["safe"];
        return false;
    }

    /**
     * Требуется ли функции twig контекст
     * @param string $name
     * @return bool
     */
    final public function isNeedsContext(string $name): bool {
        if (isset($this->_functionSettings[$name])
            && isset($this->_functionSettings[$name]["need_context"]))
            return $this->_functionSettings[$name]["need_context"];
        return false;
    }

    /**
     * Требуется ли функции twig environment
     * @param string $name
     * @return bool
     */
    final public function isNeedsEnvironment(string $name): bool {
        if (isset($this->_functionSettings[$name])
            && isset($this->_functionSettings[$name]["needs_environment"]))
            return $this->_functionSettings[$name]["needs_environment"];
        return false;
    }

    /**
     * Задать настройки вспомогательной функции
     * @param string $name - название
     * @param array $settings - свойства
     *
     * ==== Settings
     *
     * - safe               - безопасная функция (вывод без экранирования) (default: false)
     * - need_context       - требуется twig context (default: false)
     * - needs_environment  - требуется twig environment (default: false)
     *
     * ==== Examples
     *
     * function __construct() {
     *    $this->setFunctionSettings('my_f_1', ['safe'=>true]);
     *    $this->setFunctionSettings('my_f_2', ['need_context'=>true]);
     *    $this->setFunctionSettings('my_f_3', ['need_context'=>true, 'needs_environment'=>true]);
     * }
     *
     * public function my_f_1($a, $b) { ... }
     * public function my_f_2($context, $a, $b) { ... }
     * public function my_f_3(\Twig\Environment $env, $context, $string) { ... }
     *
     */
    final protected function setFunctionSettings(string $name, array $settings = []) {
        if (empty($name) || empty($settings))
            return;
        if (!method_exists($this, $name))
            return;
        $tmpSettings = [];
        if (isset($this->_functionSettings[$name]))
            $tmpSettings = $this->_functionSettings[$name];
        if (isset($settings['safe']))
            $tmpSettings['safe'] = CoreHelper::toBool($settings['safe']);
        if (isset($settings['need_context']))
            $tmpSettings['need_context'] = CoreHelper::toBool($settings['need_context']);
        if (isset($settings['needs_environment']))
            $tmpSettings['needs_environment'] = CoreHelper::toBool($settings['needs_environment']);

        $this->_functionSettings[$name] = $tmpSettings;
    }

    /**
     * Задать, что функция является безопасной
     * @param string $name
     * @param bool $val
     */
    final protected function appendSafeFunction(string $name, bool $val = true) {
        $this->setFunctionSettings($name, [ 'safe' => $val ]);
    }

    /**
     * Задать, что функции требуется twig context
     * @param string $name
     * @param bool $val
     */
    final protected function appendNeedContext(string $name, bool $val = true) {
        $this->setFunctionSettings($name, [ 'need_context' => $val ]);
    }

    /**
     * Задать, что функции требуется twig environment
     * @param string $name
     * @param bool $val
     */
    final protected function appendNeedEnvironment(string $name, bool $val = true) {
        $this->setFunctionSettings($name, [ 'needs_environment' => $val ]);
    }
}