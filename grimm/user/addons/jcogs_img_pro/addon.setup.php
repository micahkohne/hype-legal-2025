<?php

/**
 * JCOGS Image Pro - Add-on Setup File
 * ====================================
 * v2.0.0 Alpha 1 - Legacy-independent release with native EE7 integration
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 3 Legacy Independence
 */

// Load Composer autoloader
require_once PATH_THIRD . "jcogs_img_pro/vendor/autoload.php";

$addonJson = json_decode(file_get_contents(__DIR__ . '/addon.json'));

// Version constant
defined("JCOGS_IMG_PRO_VERSION") || define('JCOGS_IMG_PRO_VERSION', $addonJson->version);

// Class constant
defined("JCOGS_IMG_PRO_CLASS") || define('JCOGS_IMG_PRO_CLASS', $addonJson->class);

// Add-on name
defined("JCOGS_IMG_PRO_NAME") || define('JCOGS_IMG_PRO_NAME', $addonJson->name);


return [
    'author'             => $addonJson->author,
    'author_url'         => $addonJson->author_url,
    'name'               => $addonJson->name,
    'description'        => $addonJson->description,
    'version'            => $addonJson->version,
    'namespace'          => $addonJson->namespace,
    'settings_exist'     => $addonJson->settings_exist,
    'docs_url'           => $addonJson->docs_url,
    
    // Phase 2 services - Core native EE7 services
    'services'           => [
        'Settings' => function($ee) {
            return new \JCOGSDesign\JCOGSImagePro\Service\Settings();
        },
        'ColourManagementService' => function($ee) {
            $settings = $ee->make('jcogs_img_pro:Settings');
            return new \JCOGSDesign\JCOGSImagePro\Service\ColourManagementService($settings);
        },
        'CacheKeyGenerator' => function($ee) {
            $settings = $ee->make('jcogs_img_pro:Settings');
            return new \JCOGSDesign\JCOGSImagePro\Service\CacheKeyGenerator($settings);
        },
        'Utilities' => function($ee) {
            return new \JCOGSDesign\JCOGSImagePro\Service\Utilities();
        },
        'ImageProcessingPipeline' => function($ee) {
            $cache_key_generator = $ee->make('jcogs_img_pro:CacheKeyGenerator');
            
            // Get Pro utilities service
            $utilities = $ee->make('jcogs_img_pro:Utilities');
            
            return new \JCOGSDesign\JCOGSImagePro\Service\ImageProcessingPipeline($cache_key_generator, $utilities);
        },
        'ImageProcessingService' => function($ee) {
            return new \JCOGSDesign\JCOGSImagePro\Service\ImageProcessingService();
        },
        'FilesystemService' => function($ee) {
            $settings = $ee->make('jcogs_img_pro:Settings');
            $utilities = $ee->make('jcogs_img_pro:Utilities');
            return new \JCOGSDesign\JCOGSImagePro\Service\FilesystemService($settings, $utilities);
        },
        'ImageUtilities' => function($ee) {
            $filesystem_service = $ee->make('jcogs_img_pro:FilesystemService');
            return new \JCOGSDesign\JCOGSImagePro\Service\ImageUtilities($filesystem_service);
        },
        'CacheManagementService' => function($ee) {
            return new \JCOGSDesign\JCOGSImagePro\Service\CacheManagementService();
        },
        'PerformanceService' => function($ee) {
            return new \JCOGSDesign\JCOGSImagePro\Service\PerformanceService();
        },
        'ValidationService' => function($ee) {
            return new \JCOGSDesign\JCOGSImagePro\Service\ValidationService();
        },
        
        // Sprint 2 services - Advanced features
        'LazyLoadingService' => function($ee) {
            return new \JCOGSDesign\JCOGSImagePro\Service\Pipeline\LazyLoadingService(
                $ee->make('jcogs_img_pro:Settings'),
                $ee->make('jcogs_img_pro:Utilities')
            );
        },
        'ResponsiveImageService' => function($ee) {
            return new \JCOGSDesign\JCOGSImagePro\Service\Pipeline\ResponsiveImageService();
        },
        'OutputGenerationService' => function($ee) {
            return new \JCOGSDesign\JCOGSImagePro\Service\Pipeline\OutputGenerationService();
        }
    ],
    
    'models' => [
        'Preset' => 'Models\Preset',
    ],
    
    // Phase 6: Variable Modifiers
    'modifiers' => [
        'jcogs_img'
    ],
    
    'requires'       => [
        'php'   => $addonJson->require->php,
        'ee'    => $addonJson->require->expressionengine
    ],
];

