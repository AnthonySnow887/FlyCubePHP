<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 18.08.21
 * Time: 16:01
 */

namespace FlyCubePHP\Core\Controllers\Helpers;

include_once 'BaseControllerHelper.php';
include_once 'Extensions/TagBuilder.php';
include_once __DIR__.'/../../../HelperClasses/MimeTypes.php';

use FlyCubePHP\ComponentsCore\ComponentsManager;
use FlyCubePHP\Core\Routes\RouteType;
use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\Routes\RouteCollector;
use FlyCubePHP\Core\AssetPipeline\AssetPipeline;
use FlyCubePHP\HelperClasses\MimeTypes;

class AssetTagHelper extends BaseControllerHelper
{
    use Extensions\TagBuilder;

    function __construct() {
        $this->appendSafeFunction("stylesheet_link_tag");
        $this->appendSafeFunction("javascript_include_tag");
        $this->appendSafeFunction("auto_discovery_link_tag");
        $this->appendSafeFunction("favicon_link_tag");
        $this->appendSafeFunction("preload_link_tag");
        $this->appendSafeFunction("image_tag");
        $this->appendSafeFunction("link_to");

        $this->setFunctionSettings('add_view_javascripts', [
            'safe' => true,
            'need_context' => true
        ]);
        $this->setFunctionSettings('add_view_stylesheets', [
            'safe' => true,
            'need_context' => true
        ]);
        $this->setFunctionSettings('current_plugin_directory', [
            'safe' => false,
            'need_context' => true
        ]);
        $this->setFunctionSettings('plugin_controller_stylesheets_file', [
            'safe' => true,
            'need_context' => true
        ]);
        $this->setFunctionSettings('plugin_controller_javascript_file', [
            'safe' => true,
            'need_context' => true
        ]);
        $this->setFunctionSettings('plugin_controller_action_javascript_file', [
            'safe' => true,
            'need_context' => true
        ]);
    }

    private $_mimeTypes = array();

    /**
     * Добавить тэг подключаемого файла стилей
     * @param string $name
     * @param array $options
     * @return string
     *
     * NOTE: options will be added as tag attributes.
     */
    public function stylesheet_link_tag(string $name, array $options = []): string {
        $tmpLst = AssetPipeline::instance()->stylesheetFilePath($name);
        if (empty($tmpLst))
            throw new \RuntimeException("[stylesheet_link_tag] Not found stylesheet in asset pipeline (name: $name)!");

        if (is_array($tmpLst)) {
            $tmpData = "";
            foreach ($tmpLst as $key => $value) {
                $tmpOptions = [ "rel" => "stylesheet", "href" => $value ];
                $tmpOptions = $this->prepareTagAttributes($tmpOptions, $options);
                $tmpData .= $this->makeTag('link', '', $tmpOptions) . "\r\n";
            }
            return $tmpData;
        }
        $tmpOptions = [ "rel" => "stylesheet", "href" => $tmpLst ];
        $tmpOptions = $this->prepareTagAttributes($tmpOptions, $options);
        return $this->makeTag('link', '', $tmpOptions) . "\r\n";
    }

    /**
     * Добавить тэг подключаемого файла скрипта
     * @param string $name
     * @param array $options
     * @return string
     *
     * NOTE: options will be added as tag attributes.
     */
    public function javascript_include_tag(string $name, array $options = []): string {
        $tmpLst = AssetPipeline::instance()->javascriptFilePath($name);
        if (empty($tmpLst))
            throw new \RuntimeException("[javascript_include_tag] Not found javascript in asset pipeline (name: $name)!");

        if (is_array($tmpLst)) {
            $tmpData = "";
            foreach ($tmpLst as $key => $value) {
                $tmpOptions = [ "src" => $value ];
                $tmpOptions = $this->prepareTagAttributes($tmpOptions, $options);
                $tmpData .= $this->makeTag('script', '', $tmpOptions, true) . "\r\n";
            }
            return $tmpData;
        }
        $tmpOptions = [ "src" => $tmpLst ];
        $tmpOptions = $this->prepareTagAttributes($tmpOptions, $options);
        return $this->makeTag('script', '', $tmpOptions, true) . "\r\n";
    }

    /**
     * Returns a link tag that browsers and feed readers can use to auto-detect an RSS, Atom, or JSON feed.
     * @param string $type
     * @param array $options
     * @return string
     *
     * ==== Options
     *
     * - rel        - Specify the relation of this link, defaults to "alternate"
     * - title      - Specify the title of the link, defaults to the +type+
     * - href       - Specify the link URL
     * - controller - Specify the application controller class name
     * - action     - Specify the application controller action
     *
     * NOTE: 'href' and 'controller + action' are mutually exclusive arguments!
     * NOTE: other options will be added as tag attributes.
     *
     * ==== Examples in Twig notations
     *
     *   auto_discovery_link_tag()
     *   * => <link rel="alternate" type="application/rss+xml" title="RSS" href="http://www.currenthost.com/controller/action" />
     *
     *   auto_discovery_link_tag("atom")
     *   * => <link rel="alternate" type="application/atom+xml" title="ATOM" href="http://www.currenthost.com/controller/action" />
     *
     *   auto_discovery_link_tag("json")
     *   * => <link rel="alternate" type="application/json" title="JSON" href="http://www.currenthost.com/controller/action" />
     *
     *   auto_discovery_link_tag("rss", {"controller": "App", "action": "feed"})
     *   * => <link rel="alternate" type="application/rss+xml" title="RSS" href="http://www.currenthost.com/app/feed" />
     *
     *   auto_discovery_link_tag("rss", {"controller": "App", "action": "feed", "title": "My RSS"})
     *   * => <link rel="alternate" type="application/rss+xml" title="My RSS" href="http://www.currenthost.com/app/feed" />
     *
     *   auto_discovery_link_tag("rss", {"controller": "news", "action": "feed"})
     *   * => <link rel="alternate" type="application/rss+xml" title="RSS" href="http://www.currenthost.com/news/feed" />
     *
     *   auto_discovery_link_tag("rss", {"title": "Example RSS", "href": "http://www.example.com/feed.rss"})
     *   * => <link rel="alternate" type="application/rss+xml" title="Example RSS" href="http://www.example.com/feed.rss" />
     *
     */
    public function auto_discovery_link_tag(string $type = "rss", array $options = []): string {
        if (empty($type)
            || (strcmp($type, "rss") !== 0
                && strcmp($type, "atom") !== 0
                && strcmp($type, "json") !== 0))
            throw new \RuntimeException("[auto_discovery_link_tag] Invalid type (value: $type)!");

        $attrRel = "alternate";
        if (isset($options['rel']))
            $attrRel = strval($options['rel']);

        $attrType = "";
        if (strcmp($type, "rss") === 0)
            $attrType = "application/rss+xml";
        else if (strcmp($type, "atom") === 0)
            $attrType = "application/atom+xml";
        else if (strcmp($type, "json") === 0)
            $attrType = "application/json";

        $attrTitle = strtoupper($type);
        if (isset($options['title']))
            $attrTitle = strval($options['title']);

        $attrHref = "";
        if (array_key_exists("href", $options)
            && !array_key_exists("controller", $options)
            && !array_key_exists("action", $options)) {
            $val = strval($options["href"]);
            $attrHref = CoreHelper::makeValidUrl($val);
        } else if (!array_key_exists("href", $options)
            && array_key_exists("controller", $options)
            && array_key_exists("action", $options)) {
            $controller = strval($options["controller"]) . "Controller";
            $action = strval($options["action"]);
            $route = RouteCollector::instance()->routeByControllerAct($controller, $action);
            if (is_null($route))
                throw new \RuntimeException("[auto_discovery_link_tag] Not found needed controller with action: " . $controller . "::" . $action . "()");

            $val = $route->uri();
            $attrHref = CoreHelper::makeValidUrl($val);
        }
        $tmpOptions = [ "rel" => $attrRel, "type" => $attrType, "title" => $attrTitle, "href" => $attrHref ];
        $tmpOptions = $this->prepareTagAttributes($tmpOptions, $options);
        return $this->makeTag('link', '', $tmpOptions);
    }

    /**
     * Returns a link tag for a favicon managed by the asset pipeline.
     * @param string $source
     * @param array $options
     * @return string
     *
     * ==== Options
     *
     * - rel    - Specify the relation of this link, defaults to "shortcut icon"
     * - type   - Specify the type of this icon, defaults to "image/x-icon"
     *
     * NOTE: other options will be added as tag attributes.
     *
     * ==== Examples in Twig notations
     *
     *   favicon_link_tag()
     *   * => <link href="/assets/favicon.ico" rel="shortcut icon" type="image/x-icon" />
     *
     *   favicon_link_tag("myicon.ico")
     *   * => <link href="/assets/myicon.ico" rel="shortcut icon" type="image/x-icon" />
     *
     * Mobile Safari looks for a different link tag, pointing to an image that
     * will be used if you add the page to the home screen of an iOS device.
     * The following call would generate such a tag:
     *
     *   favicon_link_tag("mb-icon.png", {"rel": "apple-touch-icon", "type": "image/png"})
     *   * => <link href="/assets/mb-icon.png" rel="apple-touch-icon" type="image/png" />
     *
     */
    public function favicon_link_tag(string $source = "favicon.ico", array $options = []): string {
        if (empty($source))
            throw new \RuntimeException("[favicon_link_tag] Invalid source (value: $source)!");
        $sPath = AssetPipeline::instance()->imageFilePath($source);
        if (empty($sPath))
            throw new \RuntimeException("[favicon_link_tag] Not found source in asset pipeline (name: $source)!");

        $attrRel = "shortcut icon";
        if (isset($options['rel']))
            $attrRel = strval($options['rel']);

        $attrType = "image/x-icon";
        if (isset($options['type']))
            $attrType = strval($options['type']);

        $tmpOptions = [ "href" => $sPath, "rel" => $attrRel, "type" => $attrType ];
        $tmpOptions = $this->prepareTagAttributes($tmpOptions, $options);
        return $this->makeTag('link', '', $tmpOptions);
    }

    /**
     * Returns a link tag that browsers can use to preload the +source+.
     * The +source+ can be the path of a resource managed by asset pipeline, a full path, or an URI.
     * @param string $source
     * @param array $options
     * @return string
     *
     * ==== Options
     *
     * - type           - Override the auto-generated mime type, defaults to the mime type for +source+ extension.
     * - as             - Override the auto-generated value for as attribute, calculated using +source+ extension and mime type.
     * - crossorigin    - Specify the crossorigin attribute, required to load cross-origin resources.
     *
     * NOTE: other options will be added as tag attributes.
     *
     * ==== Examples in Twig notations
     *
     *   preload_link_tag("custom_theme.css")
     *   * => <link rel="preload" href="/assets/custom_theme.css" as="style" type="text/css" />
     *
     *   preload_link_tag("worker.js", {"as": "worker"})
     *   * => <link rel="preload" href="/assets/worker.js" as="worker" type="text/javascript" />
     *
     *   preload_link_tag("//example.com/font.woff2")
     *   * => <link rel="preload" href="//example.com/font.woff2" as="font" type="font/woff2" crossorigin="anonymous"/>
     *
     *   preload_link_tag("//example.com/font.woff2", {"crossorigin": "use-credentials"})
     *   * => <link rel="preload" href="//example.com/font.woff2" as="font" type="font/woff2" crossorigin="use-credentials" />
     *
     */
    public function preload_link_tag(string $source, array $options = []): string {
        if (empty($source))
            throw new \RuntimeException("[preload_link_tag] Invalid source (value: $source)!");

        $sPath = $this->assetPath($source, true);
        if (empty($sPath))
            $sPath = $source;

        $sourceExt = pathinfo($sPath, PATHINFO_EXTENSION);
        $sourceMimeType = MimeTypes::mimeType($sourceExt) ?? "";
        $attrType = $sourceMimeType;
        if (isset($options['type']))
            $attrType = strval($options['type']);

        $attrAs = $this->resolveLinkAs($sourceExt, $sourceMimeType);
        if (isset($options['as']))
            $attrAs = strval($options['as']);

        $attrCrossorigin = "";
        if (strcmp($attrAs, "font") === 0)
            $attrCrossorigin = "anonymous";
        if (isset($options['crossorigin']))
            $attrCrossorigin = strval($options['crossorigin']);

        $props = [
            "rel" => "preload",
            "href" => $sPath,
            "type" => $attrType
        ];
        if (!empty($attrAs))
            $props[ "as" ] = $attrAs;
        if (!empty($attrCrossorigin))
            $props[ "crossorigin" ] = $attrCrossorigin;

        $props = $this->prepareTagAttributes($props, $options);
        return $this->makeTag("link", "", $props);
    }

    /**
     * Добавить тэг изображения
     * @param string $name
     * @param array $options
     * @return string
     *
     * ==== Options
     *
     * - size       - Supplied as "{Width}x{Height}" or "{Number}", so "30x45" becomes
     *                width="30" and height="45", and "50" becomes width="50" and height="50".
     * - alt        - Set alternative text for image.
     * - height     - Set image height.
     * - width      - Set image width.
     * - class      - Set image class.
     *
     * NOTE: other options will be added as tag attributes.
     *
     * ==== Examples in Twig notations
     *
     *   image_tag("icon.png")
     *   * => <img src="/assets/images/icon.png" />
     *
     *   image_tag("icon.png", {"size": "16x10", "alt": "Edit Entry"})
     *   * => <img src="/assets/images/icon.png" width="16" height="10" alt="Edit Entry" />
     *
     *   image_tag("icons/icon.gif", {"size": "16"})
     *   * => <img src="/assets/images/icons/icon.gif" width="16" height="16" />
     *
     *   image_tag("icons/icon.gif", {"height": "32", "width": "32"})
     *   * => <img src="/assets/images/icons/icon.gif" width="32" height="32" />
     *
     *   image_tag("icons/icon.gif", {"class": "menu_icon"})
     *   * => <img class="menu_icon" src="/assets/images/icons/icon.gif" />
     *
     */
    public function image_tag(string $name, array $options = array()): string {
        $fPath = AssetPipeline::instance()->imageFilePath($name);
        if (empty($fPath))
            throw new \RuntimeException("[image_tag] Not found image in asset pipeline (name: $name)!");

        if (empty($options))
            return $this->makeTag("img", "", [ "src" => $fPath ]);

        $props = [ "src" => $fPath ];

        if (array_key_exists("class", $options)) {
            $val = strval($options["class"]);
            $props[ "class" ] = $val;
        }

        if (array_key_exists("width", $options)) {
            $val = strval($options["width"]);
            $props[ "width" ] = $val;
        }
        if (array_key_exists("height", $options)) {
            $val = strval($options["height"]);
            $props[ "height" ] = $val;
        }
        if (array_key_exists("size", $options)
            && !array_key_exists("width", $options)
            && !array_key_exists("height", $options)) {
            $tmpSize = explode('x', strval($options["size"]));
            if (count($tmpSize) === 2) {
                $tmpW = $tmpSize[0];
                $tmpH = $tmpSize[1];
                $props[ "width" ] = $tmpW;
                $props[ "height" ] = $tmpH;
            } elseif (count($tmpSize) === 1) {
                $tmpS = $tmpSize[0];
                $props[ "width" ] = $tmpS;
                $props[ "height" ] = $tmpS;
            }
        }
        if (array_key_exists("alt", $options)) {
            $val = strval($options["alt"]);
            $props[ "alt" ] = $val;
        }
        $props = $this->prepareTagAttributes($props, $options);
        return $this->makeTag("img", "", $props);
    }

    /**
     * Добавить тэг ссылки
     * @param string $name
     * @param array $options
     * @return string
     *
     * ==== Options
     *
     * - controller     - Set controller name
     * - action         - Set controller action name
     * - href           - Set link URL
     * - method         - Set link HTTP method
     * - class          - Set link class
     * - id             - Set link ID
     * - target         - Set link target
     * - rel            - Set link rel
     * - params         - Set additional URL params
     *
     * NOTE: 'href' and 'controller + action' are mutually exclusive arguments!
     * NOTE: other options will be added as tag attributes.
     *
     * ==== Examples in Twig notations
     *
     *   link_to("Test Link", {"controller": "AppCore", "action": "test"}), where AppCore::test -> GET
     *   * => <a href="/test">Test Link</a>
     *
     *   link_to("Test Link", {"controller": "AppCore", "action": "test_with_id", "params": {"id":123} }), where AppCore::test_with_id -> GET (url with params: /test/:id)
     *   * => <a href="/test/123">Test Link</a>
     *
     *   link_to("Test Link", {"controller": "AppCore", "action": "test"}), where AppCore::test -> POST/PUT/PATCH/DELETE
     *   * => <a href="/test" data-method="post/put/patch/delete">Test Link</a>
     *
     *   link_to("Test Link", {"href": "/test"})
     *   * => <a href="/test">Test Link</a>
     *
     *   link_to("Test Link", {"href": "/test", "method": "post"})
     *   * => <a href="/test" data-method="post">Test Link</a>
     *
     *   link_to("Test Link", {"href": "/test", "class": "my-class"})
     *   * => <a href="/test" class="my-class">Test Link</a>
     *
     *   link_to("Test Link", {"href": "/test", "class": "my-class", "id": "123"})
     *   * => <a href="/test" class="my-class" id="123">Test Link</a>
     *
     *   link_to("Test Link", {"href": "/test", "class": "my-class", "id": "123", "target": "_blank"})
     *   * => <a href="/test" class="my-class" id="123" target="_blank">Test Link</a>
     *
     *   link_to("Test Link", {"href": "/test", "class": "my-class", "id": "123", "rel": "nofollow"})
     *   * => <a href="/test" class="my-class" id="123" rel="nofollow">Test Link</a>
     *
     */
    public function link_to(string $name, array $options = array()): string {
        // <a href="/articles" class="article" id="news">Articles</a>
        if (empty($name))
            throw new \RuntimeException("[link_to] Invalid name (empty)!");
        if (empty($options))
            return $this->makeTag('a', $name, [ 'href' => '' ]);//"<a href=\"\">$name</a>";

        $urlParams = [];
        if (array_key_exists("params", $options))
            $urlParams = $options["params"];
        if (!is_array($urlParams))
            throw new \RuntimeException("[link_to] Invalid params argument (not array)!");

        $tmpOptions = [];
        if (array_key_exists("href", $options)
            && !array_key_exists("controller", $options)
            && !array_key_exists("action", $options)) {
            $tmpOptions["href"] = CoreHelper::makeValidUrl(strval($options["href"]));
        }
        if (!array_key_exists("href", $options)
            && array_key_exists("controller", $options)
            && array_key_exists("action", $options)) {
            $controller = strval($options["controller"]) . "Controller";
            $action = strval($options["action"]);
            $route = RouteCollector::instance()->routeByControllerAct($controller, $action);
            if (is_null($route))
                throw new \RuntimeException("[link_to] Not found needed controller with action: " . $controller . "::" . $action . "()");

            $val = $this->makeHrefWithParams($route->uri(), $urlParams);
            $val = CoreHelper::makeValidUrl($val);
            $method = "";
            if ($route->type() !== RouteType::GET)
                $method = strtolower(RouteType::intToString($route->type()));

            $tmpOptions["href"] = $val;
            if (!empty($method))
                $tmpOptions["data-method"] = $method;
        }
        if (array_key_exists("method", $options)
            && !array_key_exists("controller", $options)
            && !array_key_exists("action", $options)) {
            $val = strtolower(strval($options["method"]));
            if (strcmp($val, "post") === 0
                || strcmp($val, "put") === 0
                || strcmp($val, "patch") === 0
                || strcmp($val, "delete") === 0) {
                $tmpOptions["data-method"] = $val;
            }
        }
        if (array_key_exists("class", $options))
            $tmpOptions["class"] = strval($options["class"]);
        if (array_key_exists("id", $options))
            $tmpOptions["id"] = strval($options["id"]);
        if (array_key_exists("target", $options)) {
            $val = strval($options["target"]);
            if (strcmp($val, "_blank") === 0
                || strcmp($val, "_self") === 0
                || strcmp($val, "_parent") === 0
                || strcmp($val, "_top") === 0) {
                $tmpOptions["target"] = $val;
            }
        }
        if (array_key_exists("rel", $options))
            $tmpOptions["rel"] = strval($options["rel"]);

        $tmpOptions = $this->prepareTagAttributes($tmpOptions, $options);
        return $this->makeTag('a', $name, $tmpOptions);//"<a $tmpOptions>$name</a>";
    }

    /**
     * Добавить для загрузки необходимый js скрипт в раздел html->head
     * @param $context
     * @param string $name
     * @param array $options
     * @return string
     *
     * NOTE: Use only in twig block 'head'!
     * NOTE: options will be added as tag attributes.
     *
     * ==== Examples in Twig notations
     *
     * {% block head %}
     * {{ add_view_javascripts("application") }}
     * {% endblock %}
     *
     */
    public function add_view_javascripts($context, string $name, array $options = []): string {
        if (!isset($context['display_head'])
            || CoreHelper::toBool($context['display_head']) !== true)
            return "";

        return $this->javascript_include_tag($name, $options);
    }

    /**
     * Добавить для загрузки необходимый css/scss скрипт в раздел html->head
     * @param $context
     * @param string $name
     * @param array $options
     * @return string
     *
     * NOTE: Use only in twig block 'head'!
     * NOTE: options will be added as tag attributes.
     *
     * ==== Examples in Twig notations
     *
     * {% block head %}
     * {{ add_view_stylesheets("application") }}
     * {% endblock %}
     *
     */
    public function add_view_stylesheets($context, string $name, array $options = []): string {
        if (!isset($context['display_head'])
            || CoreHelper::toBool($context['display_head']) !== true)
            return "";

        return $this->stylesheet_link_tag($name, $options);
    }

    /**
     * Получить название каталога текущего плагина
     * @param $context
     * @return string
     *
     * ==== Examples in Twig notations
     *
     *   current_plugin_directory()
     *   * => "" // if not found
     *   * => "plugins/TestPlugin" // if found
     *
     */
    public function current_plugin_directory($context): string {
        if (!isset($context['params']))
            return "";
        $controller = $context['params']['controller'];
        $pl = ComponentsManager::instance()->pluginByControllerName($controller);
        if (is_null($pl))
            return "";

        return $pl->directory();
    }

    /**
     * Получить название CSS файла контроллера плагина
     * @param $context
     * @return string
     *
     * ==== Examples in Twig notations
     *
     *   plugin_controller_stylesheets_file()
     *   * => "" // if not found
     *   * => "test_worker" // if found and controller name "TestWorker" (real path: plugins/TestPlugin/app/assets/stylesheets/test_worker.[css/scss])
     *
     */
    public function plugin_controller_stylesheets_file($context): string {
        if (!isset($context['params']))
            return "";
        $fileBaseName = CoreHelper::underscore($context['params']['controller']);
        $plDir = $this->current_plugin_directory($context);
        $cssFile = CoreHelper::buildPath(
            CoreHelper::rootDir(),
            ComponentsManager::PLUGINS_DIR,
            $plDir,
            'app',
            'assets',
            'stylesheets',
            "$fileBaseName.css"
        );
        $scssFile = CoreHelper::buildPath(
            CoreHelper::rootDir(),
            ComponentsManager::PLUGINS_DIR,
            $plDir,
            'app',
            'assets',
            'stylesheets',
            "$fileBaseName.scss"
        );
        if (is_file($cssFile) && is_file($scssFile))
            trigger_error("There are two files (*.css and *.scss)! The system does not know which one to take! Invalid files: $cssFile; $scssFile", E_USER_ERROR);
        if (is_file($cssFile) || is_file($scssFile))
            return $fileBaseName;
        return "";
    }

    /**
     * Получить название JS файла контроллера плагина
     * @param $context
     * @return string
     *
     * ==== Examples in Twig notations
     *
     *   plugin_controller_javascript_file()
     *   * => "" // if not found
     *   * => "test_worker" // if found and controller name "TestWorker" (real path: plugins/TestPlugin/app/assets/javascripts/test_worker.[js/js.php])
     *
     */
    public function plugin_controller_javascript_file($context): string {
        if (!isset($context['params']))
            return "";
        $fileBaseName = CoreHelper::underscore($context['params']['controller']);
        $plDir = $this->current_plugin_directory($context);
        $jsFile = CoreHelper::buildPath(
            CoreHelper::rootDir(),
            ComponentsManager::PLUGINS_DIR,
            $plDir,
            'app',
            'assets',
            'javascripts',
            "$fileBaseName.js"
        );
        $jsPhpFile = $jsFile . ".php";
        if (is_file($jsFile) && is_file($jsPhpFile))
            trigger_error("There are two files (*.js and *.js.php)! The system does not know which one to take! Invalid files: $jsFile; $jsPhpFile", E_USER_ERROR);
        if (is_file($jsFile) || is_file($jsPhpFile))
            return $fileBaseName;
        return "";
    }

    /**
     * Получить название JS файла метода контроллера плагина
     * @param $context
     * @return string
     *
     * ==== Examples in Twig notations
     *
     *   plugin_controller_action_javascript_file()
     *   * => "" // if not found
     *   * => "test_worker_act" // if found file for action "act" and controller name "TestWorker" (real path: plugins/TestPlugin/app/assets/javascripts/test_worker_act.[js/js.php])
     *
     */
    public function plugin_controller_action_javascript_file($context): string {
        if (!isset($context['params']))
            return "";
        $jsBaseName = $context['params']['controller'] . "_" . $context['params']['action'];
        $fileBaseName = CoreHelper::underscore($jsBaseName);
        $plDir = $this->current_plugin_directory($context);
        $jsFile = CoreHelper::buildPath(
            CoreHelper::rootDir(),
            ComponentsManager::PLUGINS_DIR,
            $plDir,
            'app',
            'assets',
            'javascripts',
            "$fileBaseName.js"
        );
        $jsPhpFile = $jsFile . ".php";
        if (is_file($jsFile) && is_file($jsPhpFile))
            trigger_error("There are two files (*.js and *.js.php)! The system does not know which one to take! Invalid files: $jsFile; $jsPhpFile", E_USER_ERROR);
        if (is_file($jsFile) || is_file($jsPhpFile))
            return $fileBaseName;
        return "";
    }

    // --- private ---

    private function resolveLinkAs($extname, $mimeType): string {
        $extname = strtolower(trim($extname));
        if (strcmp($extname, "js") === 0)
            return "script";
        else if (strcmp($extname, "css") === 0)
            return "style";
        else if (strcmp($extname, "vtt") === 0)
            return "track";

        $type = explode("/", $mimeType)[0];
        if (in_array($type, [ "audio", "video", "font" ]))
            return $type;

        return "";
    }

    private function assetPath(string $name, bool $skipError = false): string {
        $fPath = AssetPipeline::instance()->javascriptFilePath($name);
        if (!empty($fPath))
            return $fPath;
        $fPath = AssetPipeline::instance()->stylesheetFilePath($name);
        if (!empty($fPath))
            return $fPath;
        $fPath = AssetPipeline::instance()->imageFilePath($name);
        if (empty($fPath) && $skipError !== true)
            throw new \RuntimeException("[assetPath] Not found source in asset pipeline (name: $name)!");

        return $fPath;
    }

    private function makeHrefWithParams(string $url, array $params): string {
        if (!preg_match('/\:([a-zA-Z0-9_]*)/i', $url))
            return $url;
        foreach ($params as $key => $val)
            $url = str_replace(":$key", $val, $url);
        return $url;
    }
}