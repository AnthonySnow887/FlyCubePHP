<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 22.07.21
 * Time: 15:40
 */

namespace FlyCubePHP\Core\Controllers;

include_once 'vendor/Twig-2.x/Twig/Extension/ExtensionInterface.php';
include_once 'vendor/Twig-2.x/Twig/Extension/AbstractExtension.php';

include_once __DIR__.'/../../HelperClasses/CoreHelper.php';
include_once __DIR__.'/../Error/ErrorController.php';
include_once 'Helpers/AssetTagHelper.php';
include_once 'Helpers/AssetUrlHelper.php';
include_once 'Helpers/JavascriptTagHelper.php';
include_once 'Helpers/StylesheetTagHelper.php';
include_once 'Helpers/ProtectionTagHelper.php';
include_once 'Helpers/FormTagHelper.php';

use \FlyCubePHP\Core\Config\Config as Config;
use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;
use \FlyCubePHP\Core\Error\ErrorController as ErrorController;
use \FlyCubePHP\Core\Controllers\Helpers\BaseControllerHelper as BaseControllerHelper;

class ControllerHelperTwigExt extends \Twig\Extension\AbstractExtension
{
    private $_helper = null;
    private $_defaultHelpers = array();

    public function __construct(/*BaseControllerHelper*/ $helper) {
        $this->_helper = $helper;
        $this->_defaultHelpers[] = new Helpers\AssetTagHelper();
        $this->_defaultHelpers[] = new Helpers\AssetUrlHelper();
        $this->_defaultHelpers[] = new Helpers\JavascriptTagHelper();
        $this->_defaultHelpers[] = new Helpers\StylesheetTagHelper();
        $this->_defaultHelpers[] = new Helpers\ProtectionTagHelper();
        $this->_defaultHelpers[] = new Helpers\FormTagHelper();
        $this->loadAppHelper();
        $this->loadExtensions();
    }

    public function __destruct() {
        unset($this->_helper);
        unset($this->_defaultHelpers);
    }

    /**
     * @return array|\Twig\TwigFunction[]
     * @throws ErrorController
     */
    final public function getFunctions() {
        $defVal = !Config::instance()->isProduction();
        $checkDuplicate = CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_CHECK_DUPLICATE_HELPERS, $defVal));
        $tmpFunc = array();

        // --- add default helper functions ---
        foreach ($this->_defaultHelpers as $hlpObject) {
            $funcArray = $this->makeHelperFunctions($hlpObject);
            if ($checkDuplicate === false) {
                $tmpFunc = array_merge($tmpFunc, $funcArray);
            } else {
                foreach ($funcArray as $key => $value) {
                    if (isset($tmpFunc[$key])) {
                        $prevF = $tmpFunc[$key];
                        $prevFClass = get_class($prevF->getCallable()[0]);
                        $prevFFile = $this->helperClassPath($prevFClass);
                        $currF = $value;
                        $currFClass = get_class($currF->getCallable()[0]);
                        $currFFile = $this->helperClassPath($currFClass);
                        $errMsg = "Duplicate helper function (name: $key)!";
                        $errMsg .= " Previous helper: " . $prevFClass . "::" . $key . "();";
                        $errMsg .= " Current helper: " . $currFClass . "::" . $key . "();";
                        $err = new ErrorController("ControllerHelperTwigExt", __FUNCTION__, "", $errMsg, "helper-base");
                        $err->appendAdditionalData("Previous helper error file", $prevFFile);
                        $err->appendAdditionalData("Previous helper error line", $this->helperFunctionLine($prevFFile, $key));
                        $err->appendAdditionalData("Current helper error file", $currFFile);
                        $err->appendAdditionalData("Current helper error line", $this->helperFunctionLine($currFFile, $key));
                        throw $err;
                    } else {
                        $tmpFunc[$key] = $value;
                    }
                }
            }
        }
        if (is_null($this->_helper))
            return array_values($tmpFunc);

        // --- add controller helper functions ---
        $funcArray = $this->makeHelperFunctions($this->_helper);
        if ($checkDuplicate === false) {
            $tmpFunc = array_merge($tmpFunc, $funcArray);
        } else {
            foreach ($funcArray as $key => $value) {
                if (isset($tmpFunc[$key])) {
                    $prevF = $tmpFunc[$key];
                    $prevFClass = get_class($prevF->getCallable()[0]);
                    $prevFFile = $this->helperClassPath($prevFClass);
                    $currF = $value;
                    $currFClass = get_class($currF->getCallable()[0]);
                    $currFFile = $this->helperClassPath($currFClass);
                    $errMsg = "Duplicate helper function (name: $key)!";
                    $errMsg .= " Previous helper: " . $prevFClass . "::" . $key . "();";
                    $errMsg .= " Current helper: " . $currFClass . "::" . $key . "();";
                    $err = new ErrorController("ControllerHelperTwigExt", __FUNCTION__, "", $errMsg, "helper-base");
                    $err->appendAdditionalData("Previous helper error file", $prevFFile);
                    $err->appendAdditionalData("Previous helper error line", $this->helperFunctionLine($prevFFile, $key));
                    $err->appendAdditionalData("Current helper error file", $currFFile);
                    $err->appendAdditionalData("Current helper error line", $this->helperFunctionLine($currFFile, $key));
                    throw $err;
                } else {
                    $tmpFunc[$key] = $value;
                }
            }
        }
        return array_values($tmpFunc);
    }

    /**
     * Создать функции помошника в формате расширения Twig
     * @param BaseControllerHelper $helper
     * @return array
     */
    final private function makeHelperFunctions(BaseControllerHelper &$helper): array {
        $tmpFunc = array();
        $hlpMethods = $helper->helperMethods();
        foreach ($hlpMethods as $method) {
            $settings = [];
            if ($helper->isSafeFunction($method["name"]) === true)
                $settings['is_safe'] = ['html'];
            if ($helper->isNeedsContext($method["name"]) === true)
                $settings['needs_context'] = true;
            if ($helper->isNeedsEnvironment($method["name"]) === true)
                $settings['needs_environment'] = true;

            $tmpFunc[$method["name"]] = new \Twig\TwigFunction($method["name"], [$helper, $method["name"]], $settings);
        }
        return $tmpFunc;
    }

    /**
     * Загрузить забовый helper приложения
     */
    final private function loadAppHelper() {
        $name = "ApplicationHelper";
        $path = CoreHelper::buildAppPath("app", "helpers", "$name.php");
        if (!is_file($path) || !is_readable($path))
            return;
        try {
            include_once $path;
            $this->_defaultHelpers[] = new $name();
        } catch (\Exception $e) {
            // nothing...
        }
    }

    /**
     * Загрузить расширения
     */
    final private function loadExtensions() {
        if (!CoreHelper::toBool(\FlyCubePHP\configValue(Config::TAG_ENABLE_EXTENSION_SUPPORT, false)))
            return;
        // --- include other helpers ---
        $extRoot = strval(\FlyCubePHP\configValue(Config::TAG_EXTENSIONS_FOLDER, "extensions"));
        $extFolder = CoreHelper::buildPath(CoreHelper::rootDir(), $extRoot, "controller", "helpers");
        if (!is_dir($extFolder))
            return;
        $extLst = CoreHelper::scanDir($extFolder);
        foreach ($extLst as $item) {
            $fExt = pathinfo($item, PATHINFO_EXTENSION);
            if (strcmp(strtolower($fExt), "php") !== 0)
                continue;
            try {
                $classes = get_declared_classes();
                include_once $item;
                $diff = array_diff(get_declared_classes(), $classes);
                reset($diff);
                foreach ($diff as $cName) {
                    try {
                        $tmpClass = new $cName();
                        if (is_subclass_of($tmpClass, '\FlyCubePHP\Core\Controllers\Helpers\BaseControllerHelper'))
                            $this->_defaultHelpers[] = $tmpClass;
                        else
                            unset($tmpClass);
                    } catch (\Exception $e) {
                        // nothing...
                    }
                }
            } catch (\Exception $e) {
                // nothing...
            }
        }
    }

    /**
     * Путь до файла хэлпера
     * @param string $className
     * @return string
     * @throws
     */
    final private function helperClassPath(string $className): string {
        $tmpRef = null;
        try {
            $tmpRef = new \ReflectionClass($className);
        } catch (\Exception $e) {
            throw new ErrorController("ControllerHelperTwigExt", __FUNCTION__, "", $e->getMessage(), "helper-base");
        }
        $tmpName = $tmpRef->getFileName();
        unset($tmpRef);
        return $tmpName;
    }

    /**
     * Поиск строки с необходимой функцией
     * @param string $path - путь до файла с исходными кодами
     * @param string $searchFunc - название функции (без скобок и аргументов)
     * @return int
     */
    final private function helperFunctionLine(string $path, string $searchFunc): int {
        if (!is_file($path) || !is_readable($path))
            return 0;
        $lineCount = 1;
        if ($file = fopen($path, "r")) {
            while (!feof($file)) {
                $lineStr = fgets($file);
                $funcName = "";
                preg_match('/.*function\s{1,}([a-zA-Z0-9_]{1,})\s{0,}\(.*/', $lineStr, $matches, PREG_OFFSET_CAPTURE);
                if (count($matches) >= 2)
                    $funcName = trim($matches[1][0]);
                if (strcmp($funcName, $searchFunc) === 0)
                    break;
                $lineCount += 1;
            }
            fclose($file);
        }
        return $lineCount;
    }
}
