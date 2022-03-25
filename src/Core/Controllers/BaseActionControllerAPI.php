<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 11.08.21
 * Time: 12:26
 */

namespace FlyCubePHP\Core\Controllers;

include_once 'BaseController.php';
include_once __DIR__.'/../Error/ErrorController.php';

use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\Routes\RouteCollector;
use FlyCubePHP\Core\Error\ErrorController;

class BaseActionControllerAPI extends BaseController
{
    use Extensions\NetworkBase {
        isNetworkUsed as private;
    }

    public function __construct() {
        parent::__construct();
    }

    /**
     * Базовый метод обработки контроллера
     * @param string $action
     * @throws
     *
     * ПРИМЕЧАНИЕ: используется системой! Использование запрещено!
     */
    public function renderPrivate(string $action) {
        // --- check caller function ---
        $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $callerClass = $dbt[1]['class'] ?? '';
        $caller = $dbt[1]['function'] ?? '';
        if (empty($caller))
            throw ErrorController::makeError([
                'tag' => 'render',
                'message' => "Not found caller function!",
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__,
                'action' => $action
            ]);
        if (!empty($callerClass))
            $caller = "$callerClass::$caller";

        if (strcmp($caller, "FlyCubePHP\Core\Routes\RouteCollector::processingRender") !== 0
            && strcmp($caller, "assetsPrecompile") !== 0)
            throw ErrorController::makeError([
                'tag' => 'render',
                'message' => "Invalid caller function!",
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__,
                'action' => $action
            ]);

        // --- select settings ---
        $defVal = !Config::instance()->isProduction();
        $this->_enableActionOutput = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_ACTION_OUTPUT, $defVal));
        $this->_params = RouteCollector::currentRouteArgs();
        $this->_params['controller-class'] = $this->controllerClassName();
        $this->_params['controller'] = $this->controllerName();
        $this->_params['action'] = $action;

        // --- before action ---
        $res = $this->processingBeforeAction($action);
        if ($res === false)
            return;

        // --- clear all buffers ---
        while (ob_get_level() !== 0)
            ob_end_clean();

        // --- processing ---
        ob_start();
        $this->_obLevel = ob_get_level();
        $this->$action();
        if ($this->_obLevel != 0
            && $this->_obLevel == ob_get_level()) {
            if ($this->_enableActionOutput === true)
                ob_end_flush();
            else
                ob_end_clean();

            $this->_obLevel = 0;
        }

        // --- after action ---
        $this->processingAfterAction($action);
    }
}