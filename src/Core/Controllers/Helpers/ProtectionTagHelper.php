<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 18.08.21
 * Time: 17:58
 */

namespace FlyCubePHP\Core\Controllers\Helpers;

include_once __DIR__.'/../../Protection/RequestForgeryProtection.php';
include_once __DIR__.'/../../Protection/CSPProtection.php';
include_once 'BaseControllerHelper.php';

use FlyCubePHP\Core\Protection\CSPProtection;
use FlyCubePHP\Core\Protection\RequestForgeryProtection;

class ProtectionTagHelper extends BaseControllerHelper
{
    function __construct() {
        $this->appendSafeFunction("csrf_meta_tags");
        $this->appendSafeFunction("csp_meta_tag");
    }

    /**
     * Добавить HTML-мета-тэги CSRF Protection
     * @return string
     * @throws \Exception
     *
     * ==== Examples in Twig notations
     *
     *    csrf_meta_tags()
     *    * =>  <meta name="csrf-param" content="authenticity_token" />
     *          <meta name="csrf-token" content="y/ISRjCq7t/FjB0ZWuACd9TblQiR5rjRu/n/SvL1cPU7DGOxGxWLmyRmB1AGr+X2wlTU3mpRONWPnrUxyTWv1A==" />
     */
    public function csrf_meta_tags() {
        if (!RequestForgeryProtection::instance()->isProtectFromForgery())
            return "";

        $token = RequestForgeryProtection::instance()->formAuthenticityToken();
        $str = <<<EOT

    <meta name="csrf-param" content="authenticity_token" />
    <meta name="csrf-token" content="$token" />

EOT;
        return $str;
    }

    /**
     * Добавить HTML-мета-тэг CSP Protection
     * @return string
     *
     * ==== Examples in Twig notations
     *
     *    csp_meta_tag()
     *    * =>  <meta name="csp-nonce" content="760230a0ce05ba6aa546549d362eb55f" />
     */
    public function csp_meta_tag() {
        if (!CSPProtection::instance()->isContentSecurityPolicy())
            return "";
        // --- make html meta-tag ---
        $token = CSPProtection::instance()->nonceKey();
        return "<meta name=\"csp-nonce\" content=\"$token\" />";
    }
}