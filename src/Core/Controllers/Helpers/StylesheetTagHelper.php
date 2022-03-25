<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 30.08.21
 * Time: 19:04
 */

namespace FlyCubePHP\Core\Controllers\Helpers;

include_once 'BaseControllerHelper.php';

use FlyCubePHP\Core\Protection\CSPProtection;

class StylesheetTagHelper extends BaseControllerHelper
{
    function __construct() {
        $this->appendSafeFunction("stylesheet_tag");
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
}