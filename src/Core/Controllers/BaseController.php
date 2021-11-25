<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 21.07.21
 * Time: 17:33
 */

namespace FlyCubePHP\Core\Controllers;

include_once 'FlashMessages.php';
include_once 'Extensions/NetworkBase.php';
include_once __DIR__.'/../Protection/RequestForgeryProtection.php';
include_once __DIR__.'/../Protection/CSPProtection.php';
include_once __DIR__.'/../Error/ErrorController.php';
include_once __DIR__.'/../Network/HttpCodes.php';
include_once __DIR__.'/../../HelperClasses/MimeTypes.php';

use FlyCubePHP\Core\Routes\RouteCollector;
use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;
use \FlyCubePHP\Core\Error\ErrorController as ErrorController;
use \FlyCubePHP\Core\Protection\RequestForgeryProtection as RequestForgeryProtection;

abstract class BaseController
{
    protected $_params = [];

    private $_isRendered = false;
    private $_obLevel = 0;
    private $_enableActionOutput = false;

    private $_friendClasses = [
        'FlyCubePHP\Core\Controllers\BaseActionController',
        'FlyCubePHP\Core\Controllers\BaseActionControllerAPI'
    ];

    private $_beforeActions = array();
    private $_skipBeforeActions = array();

    private $_afterActions = array();
    private $_skipAfterActions = array();

    /**
     * BaseController constructor.
     * @throws
     */
    public function __construct() {
        if (RequestForgeryProtection::instance()->isProtectFromForgery())
            $this->appendBeforeAction("verifyAuthenticityToken");
    }

    final public function __get($name) {
        $trace = debug_backtrace();
        if (isset($trace[1]['class'])
            && in_array($trace[1]['class'], $this->_friendClasses))
            return $this->$name;

        trigger_error('Cannot access private property ' . __CLASS__ . '::$' . $name, E_USER_ERROR);
    }

    final public function __set($name, $value) {
        $trace = debug_backtrace();
        if (isset($trace[1]['class'])
            && in_array($trace[1]['class'], $this->_friendClasses))
            return $this->$name = $value;

        trigger_error('Cannot access private property ' . __CLASS__ . '::$' . $name, E_USER_ERROR);
    }

    /**
     * Базовый метод обработки контроллера
     * @param string $action
     * @throws
     *
     * ПРИМЕЧАНИЕ: используется системой! Использование запрещено!
     */
    abstract public function renderPrivate(string $action);

    /**
     * Добавить обработчик перед вызовом основного метода контроллера
     * @param string $checkMethod - название метода проверки
     * @throws
     */
    final protected function appendBeforeAction(string $checkMethod) {
        if (empty($checkMethod) || !method_exists($this, $checkMethod))
            throw new ErrorController($this->controllerName(), __FUNCTION__, "", "Not found check method (name: $checkMethod)!", "before-action");
        if (!in_array($checkMethod, $this->_beforeActions))
            $this->_beforeActions[] = $checkMethod;
    }

    /**
     * Добавить обработчик в начало очереди перед вызовом основного метода контроллера
     * @param string $checkMethod - название метода проверки
     * @throws
     */
    final protected function prependBeforeAction(string $checkMethod) {
        if (empty($checkMethod) || !method_exists($this, $checkMethod))
            throw new ErrorController($this->controllerName(), __FUNCTION__, "", "Not found check method (name: $checkMethod)!", "before-action");
        if (!in_array($checkMethod, $this->_beforeActions))
            array_unshift($this->_beforeActions, $checkMethod);
    }

    /**
     * Исключить обработчик перед вызовом основного метода контроллера
     * @param string $checkMethod - название метода проверки
     * @param string|array $action - метод (список методов), который будет игнорироваться обработчиком
     * @throws
     */
    final protected function skipBeforeAction(string $checkMethod, /*string|array*/ $action) {
        if (empty($checkMethod) || !method_exists($this, $checkMethod))
            throw new ErrorController($this->controllerName(), __FUNCTION__, "", "Not found check method (name: $checkMethod)!", "before-action");
        if (!is_string($action) && !is_array($action))
            throw new ErrorController($this->controllerName(), __FUNCTION__, "", "Invalid action type (only string or array[string])!", "before-action");
        if (is_string($action)) {
            $this->skipBeforeActionPr($checkMethod, $action);
        } else {
            foreach ($action as $act)
                $this->skipBeforeActionPr($checkMethod, strval($act));
        }
    }

    /**
     * Добавить обработчик после вызова основного метода контроллера
     * @param string $checkMethod - название метода проверки
     * @throws
     */
    final protected function appendAfterAction(string $checkMethod) {
        if (empty($checkMethod) || !method_exists($this, $checkMethod))
            throw new ErrorController($this->controllerName(), __FUNCTION__, "", "Not found check method (name: $checkMethod)!", "after-action");
        if (!in_array($checkMethod, $this->_afterActions))
            $this->_afterActions[] = $checkMethod;
    }

    /**
     * Добавить обработчик в начало очереди после вызова основного метода контроллера
     * @param string $checkMethod - название метода проверки
     * @throws
     */
    final protected function prependAfterAction(string $checkMethod) {
        if (empty($checkMethod) || !method_exists($this, $checkMethod))
            throw new ErrorController($this->controllerName(), __FUNCTION__, "", "Not found check method (name: $checkMethod)!", "after-action");
        if (!in_array($checkMethod, $this->_afterActions))
            array_unshift($this->_afterActions, $checkMethod);
    }

    /**
     * Исключить обработчик после вызова основного метода контроллера
     * @param string $checkMethod - название метода проверки
     * @param string|array $action - метод (список методов), который будет игнорироваться обработчиком
     * @throws
     */
    final protected function skipAfterAction(string $checkMethod, /*string|array*/ $action) {
        if (empty($checkMethod) || !method_exists($this, $checkMethod))
            throw new ErrorController($this->controllerName(), __FUNCTION__, "", "Not found check method (name: $checkMethod)!", "after-action");
        if (!is_string($action) && !is_array($action))
            throw new ErrorController($this->controllerName(), __FUNCTION__, "", "Invalid action type (only string or array[string])!", "after-action");
        if (is_string($action)) {
            $this->skipAfterActionPr($checkMethod, $action);
        } else {
            foreach ($action as $act)
                $this->skipAfterActionPr($checkMethod, strval($act));
        }
    }

    /**
     * Вызов обработчиков проверок перед основным методом контроллера
     * @param string $action - название текущего экшена контроллера
     */
    final protected function processingBeforeAction(string $action) {
        $tmpSkip = array();
        if (isset($this->_skipBeforeActions[$action]))
            $tmpSkip = $this->_skipBeforeActions[$action];

        foreach ($this->_beforeActions as $item) {
            if (in_array($item, $tmpSkip))
                continue;
            $this->$item();
        }
    }

    /**
     * Вызов обработчиков проверок после основного метода контроллера
     * @param string $action - название текущего экшена контроллера
     */
    final protected function processingAfterAction(string $action) {
        $tmpSkip = array();
        if (isset($this->_skipAfterActions[$action]))
            $tmpSkip = $this->_skipAfterActions[$action];

        foreach ($this->_afterActions as $item) {
            if (in_array($item, $tmpSkip))
                continue;
            $this->$item();
        }
    }

    /**
     * Имя текущего контроллера
     * @return string
     * @throws
     */
    final protected function controllerName(): string {
        $tmpName = $this->controllerClassName();
        if (preg_match("/.*Controller$/", $tmpName))
            $tmpName = substr($tmpName, 0, strlen($tmpName) - 10);
        return $tmpName;
    }

    /**
     * Имя класса текущего контроллера
     * @return string
     * @throws
     */
    final protected function controllerClassName(): string {
        $tmpRef = null;
        try {
            $tmpRef = new \ReflectionClass($this);
        } catch (\Exception $e) {
            throw new ErrorController("BaseController", __FUNCTION__, "", $e->getMessage(), "controller-base");
        }
        $tmpName = $tmpRef->getName();
        unset($tmpRef);
        return $tmpName;
    }

    /**
     * Каталог текущего контроллера
     * @return string
     * @throws
     */
    final protected function controllerDirectory(): string {
        $tmpRef = null;
        try {
            $tmpRef = new \ReflectionClass($this);
        } catch (\Exception $e) {
            throw new ErrorController("BaseController", __FUNCTION__, "", $e->getMessage(), "controller-base");
        }
        $tmpPathLst = explode(DIRECTORY_SEPARATOR, $tmpRef->getFilename());
        unset($tmpRef);
        if (count($tmpPathLst) >= 2) {
            array_pop($tmpPathLst); // remove file-name
            array_pop($tmpPathLst); // remove controllers-dir-name
        }
        return CoreHelper::buildAppPath($tmpPathLst);
    }

    /**
     * Метод верификации токена аутентификации
     * @throws
     * @private
     */
    final private function verifyAuthenticityToken() {
        try {
            $isSuccess = RequestForgeryProtection::instance()->isVerifiedRequest();
        } catch (\Exception $e) {
            throw new ErrorController($this->controllerName(), __FUNCTION__, "", $e->getMessage(), "verify-authenticity-token");
        }
        if (!$isSuccess) {
            $curRoute = RouteCollector::instance()->currentRoute();
            throw new ErrorController($this->controllerName(), __FUNCTION__, $curRoute->action(), "Invalid Authenticity Token!", "verify-authenticity-token");
        }
    }

    /**
     * Исключить обработчик перед вызовом основного метода контроллера
     * @param string $checkMethod - название метода проверки
     * @param string $action - метод, который будет игнорироваться обработчиком
     * @throws
     * @private
     */
    final private function skipBeforeActionPr(string $checkMethod, string $action) {
        if (empty($action) || !method_exists($this, $action))
            throw ErrorController::makeError([
                'tag' => "before-action",
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__,
                'action' => "",
                'message' => "Not found action (name: $action)!",
                'backtrace-shift' => 2
            ]);
        $tmpSkip = array();
        if (isset($this->_skipBeforeActions[$action]))
            $tmpSkip = $this->_skipBeforeActions[$action];
        if (!in_array($checkMethod, $tmpSkip))
            $tmpSkip[] = $checkMethod;
        $this->_skipBeforeActions[$action] = $tmpSkip;
    }

    /**
     * Исключить обработчик после вызова основного метода контроллера
     * @param string $checkMethod - название метода проверки
     * @param string $action - метод, который будет игнорироваться обработчиком
     * @throws
     * @private
     */
    final private function skipAfterActionPr(string $checkMethod, string $action) {
        if (empty($action) || !method_exists($this, $action))
            throw ErrorController::makeError([
                'tag' => "after-action",
                'controller' => $this->controllerName(),
                'method' => __FUNCTION__,
                'action' => "",
                'message' => "Not found action (name: $action)!",
                'backtrace-shift' => 2
            ]);

        $tmpSkip = array();
        if (isset($this->_skipAfterActions[$action]))
            $tmpSkip = $this->_skipAfterActions[$action];
        if (!in_array($checkMethod, $tmpSkip))
            $tmpSkip[] = $checkMethod;
        $this->_skipAfterActions[$action] = $tmpSkip;
    }
}