<?php

namespace FlyCubePHP\WebSockets\Server;

use FlyCubePHP\Core\Error\BaseErrorHandler;

include_once __DIR__.'/../../Core/Error/BaseErrorHandler.php';

class WSServerErrorHandler extends BaseErrorHandler
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
    public function evalError(int $errCode,
                              string $errStr,
                              string $errFile,
                              int $errLine,
                              string $errFileContent,
                              string $backtrace): bool
    {
        fwrite(STDERR, "WS Server error:\r\n$errStr\r\n");
        fwrite(STDERR, "WS Server was crashed! See log-file!\r\n");
        die();
    }

    /**
     * Метод обработки исключения
     * @param \Throwable $ex
     * @return mixed
     */
    public function evalException(\Throwable $ex)
    {
        $errMsg = $ex->getMessage();
        fwrite(STDERR, "WS Server exception:\r\n$errMsg\r\n");
        fwrite(STDERR, "WS Server was crashed! See log-file!\r\n");
        die();
    }
}