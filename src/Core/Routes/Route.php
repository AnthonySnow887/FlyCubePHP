<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 21.07.21
 * Time: 15:04
 */

namespace FlyCubePHP\Core\Routes;

use FlyCubePHP\HelperClasses\CoreHelper;
use GuiCore\MenuBar\ActionBase;

include_once 'RouteType.php';
include_once 'RouteRedirect.php';

/**
 * Класс маршрута
 */
class Route
{
    private $_type;             /**< тип маршрута (get/post/put/patch/delete) */
    private $_uri;              /**< url маршрута */
    private $_uriArgs = [];     /**< статические аргументы маршрута */
    private $_controller;       /**< название класса контроллера */
    private $_action;           /**< название метода контроллера */
    private $_as;               /**< псевдоним для быстрого доступа к маршруту */
    private $_constraints = []; /**< constraints для проверки параметров в динамической части маршрута (Пример маршрута: /ROUTE/:id) */
    private $_redirect = null;  /**< объект с сописанием перенаправления маршрута */

    function __construct(int $type,
                         string $uri,
                         array $uriArgs,
                         string $controller,
                         string $action,
                         string $as,
                         array $constraints,
                         /*RouteRedirect*/ $redirect = null) {
        $this->_type = $type;
        $this->_uri = $uri;
        if (count(explode('?', $this->_uri)) > 1)
            $this->parseArgs();
        $this->_uriArgs = array_merge($this->_uriArgs, $uriArgs);
        $this->_controller = $controller;
        $this->_action = $action;
        if (empty($as)) {
            $tmpUrl = str_replace('/', ' ', $this->uri());
            $tmpUrl = str_replace(':', ' ', $tmpUrl);
            $tmpUrl = strtolower(RouteType::intToString($type)) . " $tmpUrl";
            $as = CoreHelper::underscore(CoreHelper::camelcase($tmpUrl));
        }
        $this->_as = $as;
        $this->_constraints = $constraints;

        if (!is_null($redirect) && !$redirect instanceof RouteRedirect)
            trigger_error("[Route] Append route redirect failed! Invalid redirect class!", E_USER_ERROR);
        $this->_redirect = $redirect;
    }

    /**
     * Тип маршрута
     * @return int
     */
    public function type(): int {
        return $this->_type;
    }

    /**
     * URL маршрута без аргументов
     * @return string
     */
    public function uri(): string {
        $tmpURILst = explode('?', $this->_uri);
        $tmpURI = RouteCollector::spliceUrlLast($tmpURILst[0]);
        if (empty($tmpURI))
            $tmpURI = "/";
        return $tmpURI;
    }

    /**
     * Полный URL маршрута
     * @return string
     */
    public function uriFull(): string {
        return $this->_uri;
    }

    /**
     * Сравнение маршрута с локальной копией
     * @param string $uri - URL маршрута для сравнения
     * @return bool
     */
    public function isRouteMatch(string $uri): bool {
        $uri = $this->prepareUri($uri);
        $localUri = $this->prepareUri($this->uri());
        if (strcmp($localUri, $uri) === 0)
            return true;
        if (!preg_match('/\:([a-zA-Z0-9_]*)/i', $localUri)
            && !preg_match('/\*([a-zA-Z0-9_]*)/i', $localUri))
            return false;
        // --- check ---
        $localUriLst = explode('/', $localUri);
        $uriLst = explode('/', $uri);
        if (!preg_match('/\*([a-zA-Z0-9_]*)/i', $localUri)) {
            if (count($localUriLst) != count($uriLst))
                return false;
            for ($i = 0; $i < count($localUriLst); $i++) {
                $localPath = $localUriLst[$i];
                $uriPath = $uriLst[$i];
                if (empty($localPath) && empty($uriPath))
                    continue;
                if (strcmp($localPath[0], ':') === 0) {
                    $constraint = $this->prepareConstraint($this->_constraints[substr($localPath, 1, strlen($localPath))] ?? "");
                    if (!empty($constraint) && !preg_match($constraint, $uriPath))
                        return false;
                    continue; // skip
                }
                if (strcmp($localPath, $uriPath) !== 0)
                    return false;
            }
        } else {
            if (count($localUriLst) > count($uriLst))
                return false;
            $uriPathCount = 0;
            for ($i = 0; $i < count($localUriLst); $i++) {
                // --- check uri-path-count ---
                if ($uriPathCount >= count($uriLst))
                    return false;
                // --- check ---
                $localPath = $localUriLst[$i];
                $uriPath = $uriLst[$uriPathCount];
                if (empty($localPath) && empty($uriPath)) {
                    $uriPathCount += 1;
                    continue;
                }
                if (strcmp($localPath[0], ':') === 0) {
                    $constraint = $this->prepareConstraint($this->_constraints[substr($localPath, 1, strlen($localPath))] ?? "");
                    if (!empty($constraint) && !preg_match($constraint, $uriPath))
                        return false;
                    $uriPathCount += 1;
                    continue; // skip
                }
                if (strcmp($localPath[0], '*') === 0) {
                    $isSuccess = false;
                    if ($i + 1 < count($localUriLst)) {
                        $localPathNext = $localUriLst[$i + 1];
                        for ($j = $uriPathCount + 1; $j < count($uriLst); $j++) {
                            $uriPath = $uriLst[$j];
                            if (strcmp($localPathNext, $uriPath) === 0
                                || strcmp($localPathNext[0], ':') === 0 && strlen($localPathNext) > 1) {
                                $uriPathCount = $j - 1;
                                $isSuccess = true;
                                break;
                            }
                        }
                    } else {
                        $uriPathCount = count($uriLst) - 1;
                        $isSuccess = true;
                    }
                    if (!$isSuccess)
                        return false;

                    $uriPathCount += 1;
                    continue; // skip
                }
                if (strcmp($localPath, $uriPath) !== 0)
                    return false;

                $uriPathCount += 1;
            }
            // --- check uri-path-count ---
            if ($uriPathCount < count($uriLst))
                return false;
        }
        return true;
    }

    /**
     * Разобрать аргументы маршрута, если он задан в формате "/ROUTE/:id"
     * @param string $uri
     * @return array
     */
    public function routeArgsFromUri(string $uri): array {
        $uri = $this->prepareUri($uri);
        $localUri = $this->prepareUri($this->uri());
        if (!preg_match('/\:([a-zA-Z0-9_]*)/i', $localUri)
            && !preg_match('/\*([a-zA-Z0-9_]*)/i', $localUri))
            return [];
        // --- select ---
        $tmpArgs = [];
        $localUriLst = explode('/', $localUri);
        $uriLst = explode('/', $uri);
        if (!preg_match('/\*([a-zA-Z0-9_]*)/i', $localUri)) {
            if (count($localUriLst) != count($uriLst))
                return [];
            for ($i = 0; $i < count($localUriLst); $i++) {
                $localPath = $localUriLst[$i];
                $uriPath = $uriLst[$i];
                if (empty($localPath) && empty($uriPath))
                    continue;
                if (strcmp($localPath[0], ':') === 0 && strlen($localPath) > 1)
                    $tmpArgs[ltrim($localPath, ":")] = $uriPath;
            }
        } else {
            if (count($localUriLst) > count($uriLst))
                return [];
            $uriPathCount = 0;
            for ($i = 0; $i < count($localUriLst); $i++) {
                // --- check uri-path-count ---
                if ($uriPathCount >= count($uriLst))
                    return [];
                // --- make args ---
                $localPath = $localUriLst[$i];
                $uriPath = $uriLst[$uriPathCount];
                if (empty($localPath) && empty($uriPath)) {
                    $uriPathCount += 1;
                    continue;
                }
                if (strcmp($localPath[0], ':') === 0 && strlen($localPath) > 1) {
                    $tmpArgs[ltrim($localPath, ":")] = $uriPath;
                } else if (strcmp($localPath[0], '*') === 0) {
                    $tmpUriPath = $uriPath;
                    if ($i + 1 < count($localUriLst)) {
                        $localPathNext = $localUriLst[$i + 1];
                        for ($j = $uriPathCount + 1; $j < count($uriLst); $j++) {
                            $uriPath = $uriLst[$j];
                            if (strcmp($localPathNext, $uriPath) === 0
                                || strcmp($localPathNext[0], ':') === 0 && strlen($localPathNext) > 1) {
                                $uriPathCount = $j - 1;
                                break;
                            } else if (empty($tmpUriPath)) {
                                $tmpUriPath .= $uriPath;
                            } else {
                                $tmpUriPath .= "/$uriPath";
                            }
                        }
                    } else {
                        for ($j = $uriPathCount + 1; $j < count($uriLst); $j++) {
                            $uriPath = $uriLst[$j];
                            $tmpUriPath .= "/$uriPath";
                        }
                        $uriPathCount = count($uriLst) - 1;
                    }
                    $tmpArgs[ltrim($localPath, "*")] = $tmpUriPath;
                }
                $uriPathCount += 1;
            }
            // --- check uri-path-count ---
            if ($uriPathCount < count($uriLst))
                return [];
        }
        return $tmpArgs;
    }

    /**
     * Есть ли у маршрута входные аргументы?
     * @return bool
     */
    public function hasUriArgs(): bool {
        return (!empty($this->_uriArgs)
                || preg_match('/\:([a-zA-Z0-9_]*)/i', $this->uri())
                || preg_match('/\*([a-zA-Z0-9_]*)/i', $this->uri()));
    }

    /**
     * Статические аргументы маршрута
     * @return array
     */
    public function uriArgs(): array {
        return $this->_uriArgs;
    }

    /**
     * Название контроллера
     * @return string
     */
    public function controller(): string {
        return $this->_controller;
    }

    /**
     * Название метода контроллера
     * @return string
     */
    public function action(): string {
        return $this->_action;
    }

    /**
     * Псевдоним для быстрого доступа к маршруту
     * @return string
     */
    public function routeAs(): string {
        return $this->_as;
    }

    /**
     * Constraints для проверки параметров в динамической части маршрута (Пример маршрута: /ROUTE/:id)
     * @return array
     */
    public function constraints(): array {
        return $this->_constraints;
    }

    /**
     * Задано ли перенаправление маршрута?
     * @return bool
     */
    public function hasRedirect(): bool {
        return !is_null($this->_redirect);
    }

    /**
     * Маршрут перенаправления
     * @param string $currentUri - текущий маршрут с параметрами
     * @return string
     */
    public function redirectUri(string $currentUri): string {
        if (is_null($this->_redirect))
            return "";
        return $this->makeRedirectUri($currentUri);
    }

    /**
     * HTTP статус перенаправления
     * @return int
     */
    public function redirectStatus(): int {
        if (is_null($this->_redirect))
            return -1;
        return $this->_redirect->status();
    }

    /**
     * Метод разбора аргументов
     */
    private function parseArgs() {
        // NOTE! Не использовать parse_str($postData, $postArray),
        //       т.к. данный метод портит Base64 строки!
        $tmpURILst = explode('?', $this->_uri);
        if (count($tmpURILst) != 2)
            return;
        $requestData = urldecode($tmpURILst[1]);
        if (empty($requestData))
            return;
        $requestKeyValueArray = explode('&', $requestData);
        foreach ($requestKeyValueArray as $keyValue) {
            $keyValueArray = explode('=', $keyValue);
            if (count($keyValueArray) < 2) {
                $this->_uriArgs[] = $keyValue;
            } else {
                $keyData = $keyValueArray[0];
                $valueData = str_replace($keyData . "=", "", $keyValue);
                if (preg_match('/(.*?)\[(.*?)\]/i', $keyData, $tmp)) {
                    if (empty($tmp)) {
                        $this->_uriArgs[$keyData] = $valueData;
                    } else {
                        if (!isset($this->_uriArgs[$tmp[1]]))
                            $this->_uriArgs[$tmp[1]] = [];
                        $this->_uriArgs[$tmp[1]][$tmp[2]] = $valueData;
                    }
                } else {
                    $this->_uriArgs[$keyData] = $valueData;
                }
            }
        }
    }

    /**
     * Подготовить корректный constraint
     * @param string $constraint
     * @return string
     */
    private function prepareConstraint(string $constraint): string {
        if (empty($constraint))
            return '';

        $tmpConstraintStart = "";
        $tmpConstraint = trim($constraint);
        $tmpConstraintEnd = "";

        preg_match('/(\/?)(.*)(\/\w*)/', $tmpConstraint, $matches, PREG_OFFSET_CAPTURE);
        if (!empty($matches)) {
            $tmpConstraintStart = $matches[1][0];
            $tmpConstraint = $matches[2][0];
            $tmpConstraintEnd = $matches[3][0];
        }
        if (empty($tmpConstraintStart))
            $tmpConstraintStart = "/";
        if (empty($tmpConstraintEnd))
            $tmpConstraintEnd = "/";

        if (strcmp($tmpConstraint[0], '^') !== 0)
            $tmpConstraint = '^' . $tmpConstraint;
        if (strcmp($tmpConstraint[strlen($tmpConstraint) - 1], '$') !== 0)
            $tmpConstraint = $tmpConstraint . '$';
        return $tmpConstraintStart . $tmpConstraint . $tmpConstraintEnd;
    }

    /**
     * Создать корректный маршрут перенаправления
     * @param string $currentUri
     * @return string
     */
    private function makeRedirectUri(string $currentUri): string {
        if (is_null($this->_redirect))
            return "";
        $tmpUri = $this->_redirect->uri();
        if ($this->_redirect->hasUriArgs()) {
            $tmpArgs = $this->routeArgsFromUri($currentUri);
            foreach ($tmpArgs as $key => $value) {
                $argName = "%{".$key."}";
                if (strpos($tmpUri, $argName) !== false)
                    $tmpUri = str_replace($argName, $value, $tmpUri);
            }
        }
        return $tmpUri;
    }

    /**
     * Подготовить URI
     * @param string $uri
     * @return string
     */
    private function prepareUri(string $uri): string {
        if (empty($uri))
            return "";
        if (strcmp($uri[0], '/') === 0)
            $uri = substr($uri, 1, strlen($uri) - 1);
        return $uri;
    }
}