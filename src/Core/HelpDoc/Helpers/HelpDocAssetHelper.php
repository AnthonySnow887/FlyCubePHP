<?php

namespace FlyCubePHP\Core\HelpDoc\Helpers;

use FlyCubePHP\Core\AssetPipeline\AssetPipeline;

class HelpDocAssetHelper
{
    /**
     * Computes the path to an image asset.
     * @param string $name
     * @param array $options
     * @return string
     *
     * ==== Examples in Help-Doc notations
     *
     *   {{ image_path("edit.png") }}
     *   * => /assets/edit.png
     *
     *   {{ image_path("icons/edit_2.png") }}
     *   * => /assets/edit_2.png
     *
     *   {{ image_path("/icons/edit.png") }}
     *   * => /icons/edit.png
     *
     *   {{ image_path("http://www.example.com/img/edit.png") }}
     *   * => http://www.example.com/img/edit.png
     *
     * ==== Not yet supported ====
     *
     *   {{ image_path("icons/edit_2.png", {"skip_asset_pipeline": true}) }}
     *   * => /assets/icons/edit_2.png
     *
     */
    static public function image_path(string $name, array $options = []): string
    {
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
}