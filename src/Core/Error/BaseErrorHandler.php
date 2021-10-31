<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 23.08.21
 * Time: 18:23
 */

namespace FlyCubePHP\Core\Error;


abstract class BaseErrorHandler
{
    /**
     * Метод обработки ошибки
     * @param int $errCode - код ошибки
     * @param string $errStr - текст с ошибкой
     * @param string $errFile - файл с ошибкой
     * @param int $errLine - номер строки с ошибкой
     * @param string $errFileContent - часть кода файла с ошибкой
     * @param string $backtrace - стек вызовов
     * @return bool
     */
    abstract public function evalError(int $errCode,
                                       string $errStr,
                                       string $errFile,
                                       int $errLine,
                                       string $errFileContent,
                                       string $backtrace): bool;

    /**
     * Метод обработки исключения
     * @param \Throwable $ex
     * @return mixed
     */
    abstract public function evalException(\Throwable $ex);
}