<?php

namespace FlyCubePHP\Core\TemplateCompiler;

use FlyCubePHP\Core\Error\Error;

class TCHelperFunction
{
    private $_name;
    private $_callable;

    /**
     * Конструктор класса вспомогательной функции
     * @param string $name Название
     * @param callable $callable Функция обратного вызова для метода call_user_func_array(...)
     */
    function __construct(string $name, callable $callable)
    {
        $this->_name = $name;
        $this->_callable = $callable;
    }

    /**
     * Название вспомогательной функции
     * @return string
     */
    public function name(): string
    {
        return $this->_name;
    }

    /**
     * Функция обратного вызова для метода call_user_func_array(...)
     * @return callable
     */
    public function callable(): callable
    {
        return $this->_callable;
    }

    /**
     * Метод вызова вспомогательной функции
     * @param array $args
     * @return string
     * @throws Error
     */
    public function evalFunction(array $args): string
    {
        if (is_null($this->_callable))
            throw Error::makeError([
                'tag' => 'template-compiler',
                'message' => "Invalid callable (NULL)! Function: \"".$this->_name."\"",
                'class-name' => __CLASS__,
                'class-method' => __FUNCTION__
            ]);
        return strval(call_user_func_array($this->_callable, $args));
    }
}