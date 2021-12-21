<?php
/**
 * Created by PhpStorm.
 * User: anton
 * Date: 28.07.21
 * Time: 11:53
 */

namespace FlyCubePHP\Core\AssetPipeline;

include_once __DIR__.'/../../HelperClasses/CoreHelper.php';

//use Exception;
use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;
use \FlyCubePHP\Core\Error\ErrorAssetPipeline as ErrorAssetPipeline;

class ImageBuilder
{
    private $_imageDirs = array();
    private $_imageList = array();

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::instance() instead
     */
    public function __construct() {
    }

    /**
     * Список добавленных каталогов images
     * @param bool $fullPath
     * @return array
     */
    public function imageDirs(bool $fullPath = false): array {
        if ($fullPath === true)
            return $this->_imageDirs;

        $tmpLst = array();
        foreach ($this->_imageDirs as $key => $value)
            $tmpLst[$key] = CoreHelper::buildAppPath($value);
        return $tmpLst;
    }

    /**
     * Список загруженных изображений и пути к ним
     * @param bool $fullPath
     * @return array
     */
    public function imageList(bool $fullPath = false): array {
        if ($fullPath === true)
            return $this->_imageList;

        $tmpLst = array();
        foreach ($this->_imageList as $key => $value)
            $tmpLst[$key] = CoreHelper::buildAppPath($value);
        return $tmpLst;
    }

    /**
     * Поиск пути до image файла по имени
     * @param string $name
     * @param bool $fullPath
     * @return string
     * @throws
     *
     * imagePath("configure.svg")       => "app/assets/images/configure.svg"
     * imagePath("configure.svg", true) => "/opt/my_app/app/assets/images/configure.svg", where "/opt/my_app/" - your application directory
     */
    public function imageFilePath(string $name, bool $fullPath = false): string {
        if (empty($name))
            return "";
        if (array_key_exists($name, $this->_imageList)) {
            if ($fullPath === true)
                return $this->_imageList[$name];

            return CoreHelper::buildAppPath($this->_imageList[$name]);
        }
        throw ErrorAssetPipeline::makeError([
            'tag' => 'asset-pipeline',
            'message' => "Not found needed image file: $name",
            'class-name' => __CLASS__,
            'class-method' => __FUNCTION__,
            'asset-name' => $name,
            'backtrace-shift' => 2
        ]);
    }

    /**
     * Добавить и просканировать каталог images
     * @param string $dir
     */
    public function loadImages(string $dir) {
        if (empty($dir)
            || !is_dir($dir)
            || in_array($dir, $this->_imageDirs))
            return;
        $this->_imageDirs[] = $dir;
        $tmpImages = CoreHelper::scanDir($dir, true);
        foreach ($tmpImages as $img) {
            $tmpName = CoreHelper::buildAppPath($img);
            if (!preg_match("/([a-zA-Z0-9\s_\\.\-\(\):])+(\.svg|\.png|\.jpg|\.jpeg|\.gif)$/", $tmpName))
                continue;
            $pos = strpos($tmpName, "images/");
            if ($pos === false) {
                $tmpName = basename($tmpName);
            } else {
                $tmpName = substr($tmpName, $pos + 7, strlen($tmpName));
                $tmpName = trim($tmpName);
            }
            if ($tmpName[0] == DIRECTORY_SEPARATOR)
                $tmpName = ltrim($tmpName, $tmpName[0]);
            if (!array_key_exists($tmpName, $this->_imageList))
                $this->_imageList[$tmpName] = $img;
        }
    }
}