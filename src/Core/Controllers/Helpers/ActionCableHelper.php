<?php

namespace FlyCubePHP\Core\Controllers\Helpers;

use FlyCubePHP\Core\Config\Config;
use FlyCubePHP\HelperClasses\CoreHelper;

include_once 'BaseControllerHelper.php';
include_once 'Extensions/TagBuilder.php';
include_once __DIR__.'/../../Config/Config.php';

class ActionCableHelper extends BaseControllerHelper
{
    use Extensions\TagBuilder;

    function __construct() {
        $this->appendSafeFunction("action_cable_meta_tag");
    }

    /**
     * Добавить HTML-мета-тэг для ActionCable
     * @return string
     *
     * ==== Examples in Twig notations
     *
     *    action_cable_meta_tag()
     *    * =>  <meta name="action-cable-url" content="/my_app/cable">
     */
    public function action_cable_meta_tag(): string {
        $mountPath = Config::instance()->arg(Config::TAG_ACTION_CABLE_MOUNT_PATH, "/cable");
        if (empty($mountPath))
            throw new \RuntimeException("[action_cable_meta_tag] Invalid action cable mount path!");

        $mountPath = CoreHelper::makeValidUrl($mountPath);
        return $this->makeTag('meta', '', [ 'name' => 'action-cable-url', 'content' => $mountPath ]);
    }
}