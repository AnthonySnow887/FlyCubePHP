<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 18.08.21
 * Time: 17:19
 */

namespace FlyCubePHP\Core\Controllers\Helpers;

include_once 'BaseControllerHelper.php';
include_once 'Extensions/TagBuilder.php';

use FlyCubePHP\Core\AssetPipeline\AssetPipeline;
use FlyCubePHP\Core\Protection\CSPProtection;
use FlyCubePHP\HelperClasses\CoreHelper;

class JavascriptTagHelper extends BaseControllerHelper
{
    use Extensions\TagBuilder;

    function __construct() {
        $this->appendSafeFunction("javascript_tag");
        $this->appendSafeFunction("javascript_content_tag");
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
     * NOTE: other options will be added as tag attributes.
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
     *   javascript_tag("alert('Hi!');", {"type": "application/javascript", "my_attr": "my_attr_value"})
     *   * => <script type="application/javascript" my_attr="my_attr_value">
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
        if (isset($options["nonce"])) {
            if ($options["nonce"] === true
                && CSPProtection::instance()->isContentSecurityPolicy() === true) {
                $options["nonce"] = CSPProtection::instance()->nonceKey();
            } else {
                unset($options["nonce"]);
            }
        }
        $data = trim($data);
        $jsData = <<<EOT
//<![CDATA[
$data
//]]>
EOT;
        return $this->makeTag('script', $jsData, $options, true);
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
     * NOTE: other options will be added as tag attributes.
     * NOTE: for use nonce - enable CSP Protection.
     * NOTE: this is overload function for 'javascript_tag(...)'.
     * NOTE: all dependent scripts are also built into the body of the block.
     *
     * ==== Examples
     *
     *   javascript_content_tag("application")
     *   * => <script>
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
    public function javascript_content_tag(string $name, array $options = []): string {
        $tmpLst = AssetPipeline::instance()->javascriptFilePathReal($name);
        if (empty($tmpLst))
            throw new \RuntimeException("[javascript_content_tag] Not found javascript in asset pipeline (name: $name)!");

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