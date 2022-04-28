<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 30.08.21
 * Time: 19:04
 */

namespace FlyCubePHP\Core\Controllers\Helpers;

include_once 'BaseControllerHelper.php';

use FlyCubePHP\Core\AssetPipeline\AssetPipeline;
use FlyCubePHP\Core\Protection\CSPProtection;
use FlyCubePHP\HelperClasses\CoreHelper;

class StylesheetTagHelper extends BaseControllerHelper
{
    function __construct() {
        $this->appendSafeFunction("stylesheet_tag");
        $this->appendSafeFunction("stylesheet_asset_tag");
    }

    /**
     * Добавить тэг скрипта
     * @param string $data
     * @param array $options
     * @return mixed|string
     *
     * ==== Options
     *
     * - nonce  - Enable/Disable nonce tag for script (only true/false) (default: false)
     *
     * NOTE: for use nonce - enable CSP Protection.
     *
     * ==== Examples
     *
     *   stylesheet_tag("html,body { background-color: red !important; }")
     *   * => <script type="text/css">
     *        //<![CDATA[
     *        html,body { background-color: red !important; }
     *        //]]>
     *        </script>
     *
     *   stylesheet_tag("html,body { background-color: red !important; }", {"nonce": true})
     *   * => <script nonce="e8cf820e1e236731eea842ef95b592f7" type="text/css">
     *        //<![CDATA[
     *        html,body { background-color: red !important; }
     *        //]]>
     *        </script>
     *
     * ==== Examples in Twig notations
     *
     *   {% set css_str %}
     *   html,
     *   body {
     *      background-color: red !important;
     *   }
     *   {% endset %}
     *   {{ stylesheet_tag(css_str, {"nonce": true}) }}
     */
    public function stylesheet_tag(string $data, array $options = array())
    {
        if (empty($data) && empty($options))
            return "";
        $data = trim($data);
        $cssData = <<<EOT
            
<script type="text/css">
//<![CDATA[
$data
//]]>
</script>

EOT;
        if (empty($options))
            return $cssData;
        if (array_key_exists("nonce", $options)
            && $options["nonce"] === true
            && CSPProtection::instance()->isContentSecurityPolicy() === true) {
            $val = CSPProtection::instance()->nonceKey();
            $cssData = str_replace("<script", "<script nonce=\"$val\"", $cssData);
        }
        return $cssData;
    }

    /**
     * Добавить тэг скрипта с содержимым файла стилей
     * @param string $name
     * @param array $options
     * @return string
     *
     * ==== Options
     *
     * - nonce  - Enable/Disable nonce tag for script (only true/false) (default: false)
     *
     * NOTE: for use nonce - enable CSP Protection.
     * NOTE: this is overload function for 'stylesheet_tag(...)'.
     * NOTE: all dependent scripts are also built into the body of the block.
     *
     * ==== Examples
     *
     *   stylesheet_asset_tag("application")
     *   * => <script type="text/css">
     *        //<![CDATA[
     *        // Created by FlyCubePHP generator.
     *        //
     *        // This is a manifest file that'll be compiled into application.css, which will include all the files
     *        // listed below.
     *        //
     *        // Any CSS and SCSS file within this directory, lib/assets/stylesheets, or any plugin's
     *        // vendor/assets/stylesheets directory can be referenced here using a relative path.
     *        //
     *        // Supported require_* commands:
     *        //
     *        // require [name]       - load file (search by name without extension)
     *        // require_tree [path]  - load all files from path
     *        //
     *        //]]>
     *        </script>
     */
    public function stylesheet_asset_tag(string $name, array $options = []): string {
        $tmpLst = AssetPipeline::instance()->stylesheetFilePathReal($name);
        if (empty($tmpLst))
            throw new \RuntimeException("[stylesheet_asset_tag] Not found stylesheet in asset pipeline (name: $name)!");

        if (is_array($tmpLst)) {
            $tmpData = "";
            foreach ($tmpLst as $key => $value) {
                $tmpPath = CoreHelper::buildPath(CoreHelper::rootDir(), CoreHelper::splicePathFirst($value));
                $tmpData .= file_get_contents($tmpPath) . "\r\n";
            }
        } else {
            $tmpData = file_get_contents($tmpLst) . "\r\n";
        }
        return $this->stylesheet_tag($tmpData, $options);
    }
}