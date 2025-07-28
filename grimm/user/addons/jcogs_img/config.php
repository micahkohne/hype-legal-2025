<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Config
 * ======
 * Full function image processing add-on for EE.
 * Designed to work with similar parameter and variable definitions
 * to CE-Image, to support drop-in replacement.
 * =====================================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.4.16.2
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.0.0
 */


ee()->lang->loadfile('jcogs_img');

$addonJson = json_decode(file_get_contents(__DIR__ . '/addon.json'));

// Version constant
defined("JCOGS_IMG_VERSION") || define('JCOGS_IMG_VERSION', $addonJson->version);

// Class constant
defined("JCOGS_IMG_CLASS") || define('JCOGS_IMG_CLASS', $addonJson->class);

// Add-on name
defined("JCOGS_IMG_NAME") || define('JCOGS_IMG_NAME', $addonJson->name);

// Polyfill for php 8 missing function str_contains
// Necessary for php 7.4 compatibility for HEIC utility
if (!function_exists('str_contains')) {
    function str_contains (string $haystack, string $needle)
    {
        return empty($needle) || strpos($haystack, $needle) !== false;
    }
}


