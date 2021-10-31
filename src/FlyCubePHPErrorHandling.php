<?php

include_once __DIR__.'/Core/Error/ErrorHandlingCore.php';

// --- init error handling core ---
error_reporting(E_ALL);
set_exception_handler("\FlyCubePHP\Core\Error\ErrorHandlingCore::evalExceptionHandler");
set_error_handler("\FlyCubePHP\Core\Error\ErrorHandlingCore::evalErrorHandler");
register_shutdown_function("\FlyCubePHP\Core\Error\ErrorHandlingCore::evalFatalErrorHandler");
\FlyCubePHP\Core\Error\ErrorHandlingCore::instance()->loadExtensions();