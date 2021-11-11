<?php

include_once __DIR__.'/HelperClasses/CoreHelper.php';
include_once __DIR__.'/Core/AutoLoader/AutoLoaderHelper.php';

use \FlyCubePHP\HelperClasses\CoreHelper as CoreHelper;

// --- check needed library ---
$twig_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "vendor", "Twig-2.x", "Twig");
if (!is_dir($twig_dir))
    trigger_error("Not found needed library directory! Dir: $twig_dir", E_USER_ERROR);
$scss_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "vendor", "ScssPhp", "ScssPhp");
if (!is_dir($scss_dir))
    trigger_error("Not found needed library directory! Dir: $scss_dir", E_USER_ERROR);
$jshrink_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "vendor", "JShrink", "JShrink");
if (!is_dir($jshrink_dir))
    trigger_error("Not found needed library directory! Dir: $jshrink_dir", E_USER_ERROR);
$psr_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "vendor", "Psr", "Psr");
if (!is_dir($psr_dir))
    trigger_error("Not found needed library directory! Dir: $psr_dir", E_USER_ERROR);
$monolog_dir = CoreHelper::buildPath(CoreHelper::rootDir(), "vendor", "Monolog", "Monolog");
if (!is_dir($monolog_dir))
    trigger_error("Not found needed library directory! Dir: $monolog_dir", E_USER_ERROR);

// --- append autoload dirs ---
\FlyCubePHP\appendAutoLoadDir("vendor/");
\FlyCubePHP\appendAutoLoadDir("vendor/Twig-2.x/");
\FlyCubePHP\appendAutoLoadDir("vendor/JShrink/JShrink/");
\FlyCubePHP\appendAutoLoadDir("vendor/Psr/");
\FlyCubePHP\appendAutoLoadDir("vendor/Monolog/Monolog/");