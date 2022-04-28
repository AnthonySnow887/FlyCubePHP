<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 18.08.21
 * Time: 17:19
 */

namespace FlyCubePHP\Core\Controllers\Helpers;

include_once 'BaseControllerHelper.php';

use FlyCubePHP\Core\AssetPipeline\AssetPipeline;
use FlyCubePHP\Core\Protection\CSPProtection;
use FlyCubePHP\HelperClasses\CoreHelper;

class JavascriptTagHelper extends BaseControllerHelper
{
    function __construct() {
        $this->appendSafeFunction("javascript_tag");
        $this->appendSafeFunction("javascript_asset_tag");
    }

    /**
     * Добавить тэг скрипта
     * @param string $data
     * @param array $options
     * @return mixed|string
     *
     * ==== Options
     *
     * - type   - Set script type
     * - nonce  - Enable/Disable nonce tag for script (only true/false) (default: false)
     *
     * NOTE: for use nonce - enable CSP Protection.
     *
     * ==== Examples
     *
     *   javascript_tag("alert('Hi!');")
     *   * => <script>
     *        //<![CDATA[
     *        alert('Hi!');
     *        //]]>
     *        </script>
     *
     *   javascript_tag("alert('Hi!');", {"type": "application/javascript"})
     *   * => <script type="application/javascript">
     *        //<![CDATA[
     *        alert('Hi!');
     *        //]]>
     *        </script>
     *
     *   javascript_tag("alert('Hi!');", {"type": "application/javascript", "nonce": true})
     *   * => <script nonce="e8cf820e1e236731eea842ef95b592f7" type="application/javascript">
     *        //<![CDATA[
     *        alert('Hi!');
     *        //]]>
     *        </script>
     *
     * ==== Examples in Twig notations
     *
     *   {% set js_str %}
     *   console.log("Hi! I am test console!");
     *   console.log("Hi! I am test console-2!");
     *   console.log("Hi! I am test console-3!");
     *   {% endset %}
     *   {{ javascript_tag(js_str, {"nonce": true}) }}
     */
    public function javascript_tag(string $data, array $options = array())
    {
        if (empty($data) && empty($options))
            return "";
        $data = trim($data);
        $jsData = <<<EOT
            
<script>
//<![CDATA[
$data
//]]>
</script>

EOT;
        if (empty($options))
            return $jsData;
        if (array_key_exists("type", $options)) {
            $val = strval($options["type"]);
            $jsData = str_replace("<script", "<script type=\"$val\"", $jsData);
        }
        if (array_key_exists("nonce", $options)
            && $options["nonce"] === true
            && CSPProtection::instance()->isContentSecurityPolicy() === true) {
            $val = CSPProtection::instance()->nonceKey();
            $jsData = str_replace("<script", "<script nonce=\"$val\"", $jsData);
        }
        return $jsData;
    }

    /**
     * Добавить тэг скрипта с содержимым файла
     * @param string $name
     * @param array $options
     * @return string
     *
     * ==== Options
     *
     * - type   - Set script type
     * - nonce  - Enable/Disable nonce tag for script (only true/false) (default: false)
     *
     * NOTE: for use nonce - enable CSP Protection.
     * NOTE: this is overload function for 'javascript_tag(...)'.
     * NOTE: all dependent scripts are also built into the body of the block.
     *
     * ==== Examples
     *
     *   javascript_asset_tag("application")
     *   * => <script type="text/css">
     *        //<![CDATA[
     *        //
     *        // Created by FlyCubePHP generator.
     *        //
     *        //
     *        // This is a manifest file that'll be compiled into application.js, which will include all the files
     *        // listed below.
     *        //
     *        // Any JavaScript/JavaScript.PHP file within this directory, lib/assets/javascripts, or any plugin's
     *        // vendor/assets/javascripts directory can be referenced here using a relative path.
     *        //
     *        // Supported require_* commands:
     *        // require [name]       - load file (search by name without extension)
     *        // require_tree [path]  - load all files from path
     *        //
     *        //]]>
     *        </script>
     */
    public function javascript_asset_tag(string $name, array $options = []): string {
        $tmpLst = AssetPipeline::instance()->javascriptFilePathReal($name);
        if (empty($tmpLst))
            throw new \RuntimeException("[javascript_asset_tag] Not found javascript in asset pipeline (name: $name)!");

        if (is_array($tmpLst)) {
            $tmpData = "";
            foreach ($tmpLst as $key => $value) {
                $tmpPath = CoreHelper::buildPath(CoreHelper::rootDir(), CoreHelper::splicePathFirst($value));
                $tmpData .= file_get_contents($tmpPath) . "\r\n";
            }
        } else {
            $tmpData = file_get_contents($tmpLst) . "\r\n";
        }
        return $this->javascript_tag($tmpData, $options);
    }
}