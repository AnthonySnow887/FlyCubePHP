<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 19.08.21
 * Time: 15:02
 */

namespace FlyCubePHP\Core\Controllers\Helpers;

include_once 'BaseControllerHelper.php';

use FlyCubePHP\HelperClasses\CoreHelper;
use FlyCubePHP\Core\Routes\RouteCollector;
use FlyCubePHP\Core\AssetPipeline\AssetPipeline;

class AssetUrlHelper extends BaseControllerHelper
{
    function __construct() {
    }

    /**
     * Computes the path to an some asset.
     * @param string $name
     * @param array $options
     * @return string
     *
     * ==== Options
     *
     * - skip_asset_pipeline    - Skip search in Asset Pipeline
     * - type                   - Set asset type (js/css/image)
     *
     * ==== Examples in Twig notations
     *
     *   asset_path("test")
     *   * => "/assets/test.js"
     *
     *   asset_path("test", {"type": "css"})
     *   * => "/assets/test.css"
     *
     *   asset_path("test.js")
     *   * => "/assets/test.js"
     *
     *   asset_path("test.css")
     *   * => "/assets/test.css"
     *
     *   asset_path("test", {"skip_asset_pipeline": true})
     *   * => "/assets/test"
     *
     *   asset_path("test/test.js", {"skip_asset_pipeline": true})
     *   * => "/assets/test/test.js"
     *
     *   asset_path("/test/test.js")
     *   * => "/test/test.js"
     *
     *   asset_path("http://www.example.com/test/test.js")
     *   * => "http://www.example.com/test/test.js"
     *
     */
    public function asset_path(string $name, array $options = [])/*: string*/ {
        if (empty($name))
            throw new \RuntimeException("[asset_path] Invalid asset name (empty)!");
        if (strcmp($name[0], "/") === 0)
            return $name;
        if (preg_match("/^(http:\/\/|https:\/\/).*/", $name))
            return $name;
        if (isset($options["skip_asset_pipeline"])
            && $options["skip_asset_pipeline"] === true)
            return "/assets/$name";

        $fPath = $this->prepareAssetPath($name, $options);
        if (empty($fPath))
            throw new \RuntimeException("[asset_path] Not found asset in asset pipeline (name: $name)!");

        return $fPath;
    }

    /**
     * Computes the full URL to a some asset.
     * @param string $name
     * @param array $options
     * @return string
     *
     * ==== Options
     *
     * - skip_asset_pipeline    - Skip search in Asset Pipeline
     * - host                   - Set needed host in URL
     * - protocol               - Set needed HTTP protocol in URL
     * - type                   - Set asset type (js/css/image)
     *
     * ==== Examples in Twig notations
     *
     *   asset_url("test", {"host": "www.example.com"})
     *   * => "http://www.example.com/assets/test.js"
     *
     *   asset_url("test/test_2.js", {"host": "www.example.com", "skip_asset_pipeline": true})
     *   * => "http://www.example.com/assets/test/test_2.js"
     *
     *   asset_url("test")
     *   * => "http://www.my_project.com/assets/test.js"
     *
     *   asset_url("test", {"type": "css"})
     *   * => "http://www.my_project.com/assets/test.css"
     *
     *   asset_url("test.js")
     *   * => "http://www.my_project.com/assets/test.js"
     *
     *   asset_url("test/test_2.js", {"skip_asset_pipeline": true})
     *   * => "http://www.my_project.com/assets/test/test_2.js"
     *
     */
    public function asset_url(string $name, array $options = []): string {
        if (empty($name))
            throw new \RuntimeException("[asset_url] Invalid asset name (empty)!");
        if (isset($options["host"]) && empty($options["host"]))
            throw new \RuntimeException("[asset_url] Invalid host!");
        if (isset($options["protocol"])) {
            if (empty($options["protocol"])
                || (strcmp($options["protocol"], "http") !== 0
                    && strcmp($options["protocol"], "https") !== 0))
                throw new \RuntimeException("[asset_url] Invalid protocol!");
        }

        $host = CoreHelper::spliceUrlLast(RouteCollector::currentHostUri());
        if (isset($options["host"]))
            $host = CoreHelper::spliceUrlLast(strval($options["host"]));
        if (isset($options["protocol"])) {
            $protocol = strval($options["protocol"]);
            $host = "$protocol://$host";
        }

        if (isset($options["skip_asset_pipeline"])
            && $options["skip_asset_pipeline"] === true)
            return "$host/assets/$name";

        $fPath = $this->prepareAssetPath($name, $options);
        if (empty($fPath))
            throw new \RuntimeException("[asset_url] Not found asset in asset pipeline (name: $name)!");

        return $host.$fPath;
    }

    /**
     * Computes the path to a javascript asset.
     * @param string $name
     * @param array $options
     * @return string
     *
     * ==== Options
     *
     * - skip_asset_pipeline - Skip search in Asset Pipeline
     *
     * ==== Examples in Twig notations
     *
     *   image_path("test")
     *   * => "/assets/test.js"
     *
     *   image_path("test/test.js")
     *   * => "/assets/test.js"
     *
     *   image_path("test", {"skip_asset_pipeline": true})
     *   * => "/assets/test"
     *
     *   image_path("test/test.js", {"skip_asset_pipeline": true})
     *   * => "/assets/test/test.js"
     *
     *   image_path("/test/test.js")
     *   * => "/test/test.js"
     *
     *   image_path("http://www.example.com/test/test.js")
     *   * => "http://www.example.com/test/test.js"
     *
     */
    public function javascript_path(string $name, array $options = []): string {
        if (empty($name))
            throw new \RuntimeException("[javascript_path] Invalid javascript file name (empty)!");
        if (strcmp($name[0], "/") === 0)
            return $name;
        if (preg_match("/^(http:\/\/|https:\/\/).*/", $name))
            return $name;
        if (isset($options["skip_asset_pipeline"])
            && $options["skip_asset_pipeline"] === true)
            return "/assets/$name";

        $name = CoreHelper::fileName($name, true);
        $fPath = AssetPipeline::instance()->javascriptFilePath($name);
        if (empty($fPath))
            throw new \RuntimeException("[javascript_path] Not found javascript file in asset pipeline (name: $name)!");

        return $fPath;
    }

    /**
     * Computes the full URL to an javascript asset.
     * @param string $name
     * @param array $options
     * @return string
     *
     * ==== Options
     *
     * - skip_asset_pipeline    - Skip search in Asset Pipeline
     * - host                   - Set needed host in URL
     * - protocol               - Set needed HTTP protocol in URL
     *
     * ==== Examples in Twig notations
     *
     *   javascript_url("test", {"host": "www.example.com"})
     *   * => "http://www.example.com/assets/test.js"
     *
     *   javascript_url("test/test_2.js", {"host": "www.example.com", "skip_asset_pipeline": true})
     *   * => "http://www.example.com/assets/test/test_2.js"
     *
     *   javascript_url("test")
     *   * => "http://www.my_project.com/assets/test.js"
     *
     *   javascript_url("test.js")
     *   * => "http://www.my_project.com/assets/test.js"
     *
     *   javascript_url("test/test_2.js", {"skip_asset_pipeline": true})
     *   * => "http://www.my_project.com/assets/test/test_2.js"
     *
     */
    public function javascript_url(string $name, array $options = []): string {
        if (empty($name))
            throw new \RuntimeException("[javascript_url] Invalid javascript file name (empty)!");
        if (isset($options["host"]) && empty($options["host"]))
            throw new \RuntimeException("[javascript_url] Invalid host!");
        if (isset($options["protocol"])) {
            if (empty($options["protocol"])
                || (strcmp($options["protocol"], "http") !== 0
                    && strcmp($options["protocol"], "https") !== 0))
                throw new \RuntimeException("[javascript_url] Invalid protocol!");
        }

        $host = CoreHelper::spliceUrlLast(RouteCollector::currentHostUri());
        if (isset($options["host"]))
            $host = CoreHelper::spliceUrlLast(strval($options["host"]));
        if (isset($options["protocol"])) {
            $protocol = strval($options["protocol"]);
            $host = "$protocol://$host";
        }

        if (isset($options["skip_asset_pipeline"])
            && $options["skip_asset_pipeline"] === true)
            return "$host/assets/$name";

        $name = CoreHelper::fileName($name, true);
        $fPath = AssetPipeline::instance()->javascriptFilePath($name);
        if (empty($fPath))
            throw new \RuntimeException("[javascript_url] Not found javascript file in asset pipeline (name: $name)!");

        return $host.$fPath;
    }

    /**
     * Computes the path to a stylesheet asset.
     * @param string $name
     * @param array $options
     * @return string
     *
     * ==== Options
     *
     * - skip_asset_pipeline - Skip search in Asset Pipeline
     *
     * ==== Examples in Twig notations
     *
     *   stylesheet_path("test")
     *   * => "/assets/test.css"
     *
     *   stylesheet_path("test/test.css")
     *   * => "/assets/test.css"
     *
     *   stylesheet_path("test", {"skip_asset_pipeline": true})
     *   * => "/assets/test"
     *
     *   stylesheet_path("test/test.css", {"skip_asset_pipeline": true})
     *   * => "/assets/test/test.css"
     *
     *   stylesheet_path("/test/test.css")
     *   * => "/test/test.css"
     *
     *   stylesheet_path("http://www.example.com/test/test.css")
     *   * => "http://www.example.com/test/test.css"
     *
     */
    public function stylesheet_path(string $name, array $options = []): string {
        if (empty($name))
            throw new \RuntimeException("[stylesheet_path] Invalid stylesheet file name (empty)!");
        if (strcmp($name[0], "/") === 0)
            return $name;
        if (preg_match("/^(http:\/\/|https:\/\/).*/", $name))
            return $name;
        if (isset($options["skip_asset_pipeline"])
            && $options["skip_asset_pipeline"] === true)
            return "/assets/$name";

        $name = CoreHelper::fileName($name, true);
        $fPath = AssetPipeline::instance()->stylesheetFilePath($name);
        if (empty($fPath))
            throw new \RuntimeException("[stylesheet_path] Not found stylesheet file in asset pipeline (name: $name)!");

        return $fPath;
    }

    /**
     * Computes the full URL to an stylesheet asset.
     * @param string $name
     * @param array $options
     * @return string
     *
     * ==== Options
     *
     * - skip_asset_pipeline    - Skip search in Asset Pipeline
     * - host                   - Set needed host in URL
     * - protocol               - Set needed HTTP protocol in URL
     *
     * ==== Examples in Twig notations
     *
     *   stylesheet_url("test", {"host": "www.example.com"})
     *   * => "http://www.example.com/assets/test.css"
     *
     *   stylesheet_url("test/test_2.css", {"host": "www.example.com", "skip_asset_pipeline": true})
     *   * => "http://www.example.com/assets/test/test_2.css"
     *
     *   stylesheet_url("test")
     *   * => "http://www.my_project.com/assets/test.css"
     *
     *   stylesheet_url("test.css")
     *   * => "http://www.my_project.com/assets/test.css"
     *
     *   stylesheet_url("test/test_2.css", {"skip_asset_pipeline": true})
     *   * => "http://www.my_project.com/assets/test/test_2.css"
     *
     */
    public function stylesheet_url(string $name, array $options = []): string {
        if (empty($name))
            throw new \RuntimeException("[stylesheet_url] Invalid stylesheet file name (empty)!");
        if (isset($options["host"]) && empty($options["host"]))
            throw new \RuntimeException("[stylesheet_url] Invalid host!");
        if (isset($options["protocol"])) {
            if (empty($options["protocol"])
                || (strcmp($options["protocol"], "http") !== 0
                    && strcmp($options["protocol"], "https") !== 0))
                throw new \RuntimeException("[stylesheet_url] Invalid protocol!");
        }

        $host = CoreHelper::spliceUrlLast(RouteCollector::currentHostUri());
        if (isset($options["host"]))
            $host = CoreHelper::spliceUrlLast(strval($options["host"]));
        if (isset($options["protocol"])) {
            $protocol = strval($options["protocol"]);
            $host = "$protocol://$host";
        }

        if (isset($options["skip_asset_pipeline"])
            && $options["skip_asset_pipeline"] === true)
            return "$host/assets/$name";

        $name = CoreHelper::fileName($name, true);
        $fPath = AssetPipeline::instance()->stylesheetFilePath($name);
        if (empty($fPath))
            throw new \RuntimeException("[stylesheet_url] Not found stylesheet file in asset pipeline (name: $name)!");

        return $host.$fPath;
    }

    /**
     * Computes the path to an image asset.
     * @param string $name
     * @param array $options
     * @return string
     *
     * ==== Options
     *
     * - skip_asset_pipeline - Skip search in Asset Pipeline
     *
     * ==== Examples in Twig notations
     *
     *   image_path("edit.png")
     *   * => "/assets/edit.png"
     *
     *   image_path("icons/edit_2.png")
     *   * => "/assets/edit_2.png"
     *
     *   image_path("icons/edit_2.png", {"skip_asset_pipeline": true})
     *   * => "/assets/icons/edit_2.png"
     *
     *   image_path("/icons/edit.png")
     *   * => "/icons/edit.png"
     *
     *   image_path("http://www.example.com/img/edit.png")
     *   * => "http://www.example.com/img/edit.png"
     *
     */
    public function image_path(string $name, array $options = []): string {
        if (empty($name))
            throw new \RuntimeException("[image_path] Invalid image name (empty)!");
        if (strcmp($name[0], "/") === 0)
            return $name;
        if (preg_match("/^(http:\/\/|https:\/\/).*/", $name))
            return $name;
        if (isset($options["skip_asset_pipeline"])
            && $options["skip_asset_pipeline"] === true)
            return "/assets/$name";

        $fPath = AssetPipeline::instance()->imageFilePath($name);
        if (empty($fPath))
            throw new \RuntimeException("[image_path] Not found image in asset pipeline (name: $name)!");

        return $fPath;
    }

    /**
     * Computes the full URL to an image asset.
     * @param string $name
     * @param array $options
     * @return string
     *
     * ==== Options
     *
     * - skip_asset_pipeline    - Skip search in Asset Pipeline
     * - host                   - Set needed host in URL
     * - protocol               - Set needed HTTP protocol in URL
     *
     * ==== Examples in Twig notations
     *
     *   image_url("edit.png", {"host": "www.example.com"})
     *   * => "http://www.example.com/assets/edit.png"
     *
     *   image_url("icons/edit_2.png", {"host": "www.example.com", "skip_asset_pipeline": true})
     *   * => "http://www.example.com/assets/icons/edit_2.png"
     *
     *   image_url("edit.png")
     *   * => "http://www.my_project.com/assets/edit.png"
     *
     *   image_url("icons/edit_2.png", {"skip_asset_pipeline": true})
     *   * => "http://www.my_project.com/assets/icons/edit_2.png"
     *
     */
    public function image_url(string $name, array $options = []): string {
        if (empty($name))
            throw new \RuntimeException("[image_url] Invalid image name (empty)!");
        if (isset($options["host"]) && empty($options["host"]))
            throw new \RuntimeException("[image_url] Invalid host!");
        if (isset($options["protocol"])) {
            if (empty($options["protocol"])
                || (strcmp($options["protocol"], "http") !== 0
                    && strcmp($options["protocol"], "https") !== 0))
                throw new \RuntimeException("[image_url] Invalid protocol!");
        }

        $host = CoreHelper::spliceUrlLast(RouteCollector::currentHostUri());
        if (isset($options["host"]))
            $host = CoreHelper::spliceUrlLast(strval($options["host"]));
        if (isset($options["protocol"])) {
            $protocol = strval($options["protocol"]);
            $host = "$protocol://$host";
        }

        if (isset($options["skip_asset_pipeline"])
            && $options["skip_asset_pipeline"] === true)
            return "$host/assets/$name";

        $fPath = AssetPipeline::instance()->imageFilePath($name);
        if (empty($fPath))
            throw new \RuntimeException("[image_url] Not found image in asset pipeline (name: $name)!");

        return $host.$fPath;
    }

    /**
     * Returns a string suitable for an HTML image tag alt attribute.
     * @param string $name
     * @return string
     *
     * ==== Examples in Twig notations
     *
     *   image_alt('rails.png')
     *   * => Rails
     *
     *   image_alt('hyphenated-file-name.png')
     *   * => Hyphenated file name
     *
     *   image_alt('underscored_file_name.png')
     *   * => Underscored file name
     *
     */
    public function image_alt(string $name): string {
        $name = CoreHelper::fileName($name, true);
        $name = str_replace("-", " ", $name);
        $name = str_replace("_", " ", $name);
        $name = strtolower($name);
        return ucfirst($name);
    }

    /**
     * Получить валидную строку адреса (с App-Url-Prefix, если задан)
     * @param string $url - строка URL
     * @return string
     *
     * ==== Examples in Twig notations
     *
     *    make_valid_url("/api/test_api");
     *    * if url-prefix not set => "/api/test_api"
     *    * if url-prefix set ("/app1") => "/app1/api/test_api"
     *
     */
    public function make_valid_url(string $url): string {
        return CoreHelper::makeValidUrl($url);
    }

    // --- private ---

    private function prepareAssetPath(string $name, array $options): string {
        if (isset($options["type"]) && strcmp($options["type"], "image") === 0) {
            $fPath = AssetPipeline::instance()->imageFilePath($name);
        } else if (isset($options["type"]) && strcmp($options["type"], "js") === 0) {
            $name = CoreHelper::fileName($name, true);
            $fPath = AssetPipeline::instance()->javascriptFilePath($name);
        } else if (isset($options["type"]) && strcmp($options["type"], "css") === 0) {
            $name = CoreHelper::fileName($name, true);
            $fPath = AssetPipeline::instance()->stylesheetFilePath($name);
        } else if (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.svg|\.png|\.jpg|\.jpeg|\.gif)$/", $name)) {
            $fPath = AssetPipeline::instance()->imageFilePath($name);
        } else if (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.js)$/", $name)) {
            $name = CoreHelper::fileName($name, true);
            $fPath = AssetPipeline::instance()->javascriptFilePath($name);
        } else if (preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.css|\.scss)$/", $name)) {
            $name = CoreHelper::fileName($name, true);
            $fPath = AssetPipeline::instance()->stylesheetFilePath($name);
        } else {
            $fPath = AssetPipeline::instance()->imageFilePath($name);
            if (empty($fPath)) {
                $name = CoreHelper::fileName($name, true);
                $fPath = AssetPipeline::instance()->javascriptFilePath($name);
                if (empty($fPath))
                    $fPath = AssetPipeline::instance()->stylesheetFilePath($name);
            }
        }
        return $fPath;
    }
}