<?php

/**
 * Image Add-on Setup File
 * =======================
 * Controls the configuration of Add-on.
 * 
 * =====================================================
 *
 * @category   ExpressionEngine Add-on
 * @package    
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.4.16.2
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.0.0
 */

$addonJson = json_decode(file_get_contents(__DIR__ . '/addon.json'));

// Version constant
defined("JCOGS_IMG_VERSION") || define('JCOGS_IMG_VERSION', $addonJson->version);

// Class constant
defined("JCOGS_IMG_CLASS") || define('JCOGS_IMG_CLASS', $addonJson->class);

// Add-on name
defined("JCOGS_IMG_NAME") || define('JCOGS_IMG_NAME', $addonJson->name);

return [
    'author'             => $addonJson->author,
    'author_url'         => $addonJson->author_url,
    'name'               => $addonJson->name,
    'description'        => $addonJson->description,
    'version'            => $addonJson->version,
    'namespace'          => $addonJson->namespace,
    'settings_exist'     => $addonJson->settings_exist,
    'docs_url'           => $addonJson->docs_url,
    'services'           => [
        'Licensing'      => 'Service\Licensing',
        'ImageUtilities' => 'Service\ImageUtilities',
        'Settings'       => 'Service\Settings',
        'Utilities'      => 'Service\Utilities',
    ],
    'requires'       => [
        'php'   => $addonJson->require->php,
        'ee'    => $addonJson->require->expressionengine
    ],
];

