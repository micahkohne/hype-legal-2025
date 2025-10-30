<?php

/**
 * JCOGS Image Pro - Parameter Default Mapping Service
 * ===================================================
 * Bidirectional mapping between CP settings and parameter packages
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 4 CP Integration Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service;

class ParameterDefaultMapping
{
    /**
     * Mapping of CP settings to parameter package information
     * 
     * @var array
     */
    private static $cp_to_package_map = [
        // ==========================================
        // ImageDefaults.php Mappings (Primary Target)
        // ==========================================
        
        // Quality Settings
        
        // JPEG Quality (maps to legacy 'quality' parameter for backward compatibility)
        'img_cp_jpg_default_quality' => [
            'package' => 'TransformationalParameterPackage',
            'parameter' => 'quality',
            'category' => 'transformational',
            'type' => 'slider',
            'min' => 1,
            'max' => 100,
            'step' => 1
        ],
        
        // PNG Quality (uses separate png_quality parameter for PNG compression level 0-9)
        'img_cp_png_default_quality' => [
            'package' => 'TransformationalParameterPackage',
            'parameter' => 'png_quality',
            'category' => 'transformational',
            'type' => 'slider',
            'min' => 0,
            'max' => 9,
            'step' => 1
        ],
        
        // Format Settings - Dynamic format detection
        'img_cp_default_image_format' => [
            'package' => 'ControlParameterPackage',
            'parameter' => 'output_format',
            'category' => 'control',
            'type' => 'dynamic_format_select',
            'dynamic' => true
        ],
        
        // Dimensional Settings
        'img_cp_default_img_width' => [
            'package' => 'DimensionalParameterPackage',
            'parameter' => 'width',
            'category' => 'dimensional',
            'type' => 'text',
            'validation' => 'positive_integer'
        ],
        'img_cp_default_img_height' => [
            'package' => 'DimensionalParameterPackage', 
            'parameter' => 'height',
            'category' => 'dimensional',
            'type' => 'text',
            'validation' => 'positive_integer'
        ],
        
        // Operational Settings
        'img_cp_allow_scale_larger_default' => [
            'package' => 'DimensionalParameterPackage',
            'parameter' => 'allow_scale_larger',
            'category' => 'dimensional',
            'type' => 'yes_no'
        ],
        'img_cp_enable_auto_sharpen' => [
            'package' => 'TransformationalParameterPackage',
            'parameter' => 'auto_sharpen',
            'category' => 'transformational',
            'type' => 'yes_no'
        ],
        
        // Lazy Loading Settings
        'img_cp_enable_lazy_loading' => [
            'package' => 'ControlParameterPackage',
            'parameter' => 'lazy_loading',
            'category' => 'control',
            'type' => 'yes_no'
        ],
        
        // Fallback Settings
        'img_cp_default_fallback_src' => [
            'package' => 'ControlParameterPackage',
            'parameter' => 'fallback_src',
            'category' => 'control',
            'type' => 'text'
        ],
        'img_cp_default_fallback_color' => [
            'package' => 'ControlParameterPackage',
            'parameter' => 'fallback_color',
            'category' => 'control',
            'type' => 'color_picker'
        ],
        
        // ==========================================
        // AdvancedSettings.php Mappings (Secondary Target)
        // ==========================================
        
        // Browser/Server Detection
        'img_cp_enable_browser_check' => [
            'package' => 'ControlParameterPackage',
            'parameter' => 'enable_browser_check',
            'category' => 'control',
            'type' => 'yes_no'
        ],
        
        // File Naming
        'img_cp_default_filename_separator' => [
            'package' => 'ControlParameterPackage',
            'parameter' => 'filename_separator',
            'category' => 'control',
            'type' => 'text',
            'validation' => 'filename_separator'
        ],
        'img_cp_default_max_source_filename_length' => [
            'package' => 'ControlParameterPackage',
            'parameter' => 'max_source_filename_length',
            'category' => 'control',
            'type' => 'text',
            'validation' => 'positive_integer',
            'min' => 1,
            'max' => 175
        ],
        
        // Performance Settings
        'img_cp_default_min_php_ram' => [
            'package' => 'ControlParameterPackage',
            'parameter' => 'min_php_ram',
            'category' => 'control',
            'type' => 'text',
            'validation' => 'memory_value'
        ],
        'img_cp_default_min_php_process_time' => [
            'package' => 'ControlParameterPackage',
            'parameter' => 'min_php_process_time',
            'category' => 'control',
            'type' => 'text',
            'validation' => 'positive_integer'
        ],
        
        // ==========================================
        // Caching.php Mappings (Limited Integration)
        // ==========================================
        
        'img_cp_default_cache_duration' => [
            'package' => 'ControlParameterPackage',
            'parameter' => 'cache_duration',
            'category' => 'control',
            'type' => 'text',
            'validation' => 'cache_duration'
        ]
    ];

    /**
     * Get parameter package information for a CP setting
     * 
     * @param string $setting_key CP setting key
     * @return array|null Package information or null if not mapped
     */
    public function getPackageForCPSetting(string $setting_key): ?array
    {
        return self::$cp_to_package_map[$setting_key] ?? null;
    }

    /**
     * Get CP setting key for a parameter package parameter
     * 
     * @param string $package_class Package class name
     * @param string $parameter Parameter name
     * @return string|null CP setting key or null if not mapped
     */
    public function getCPSettingForPackageParameter(string $package_class, string $parameter): ?string
    {
        foreach (self::$cp_to_package_map as $setting_key => $package_info) {
            if ($package_info['package'] === $package_class && $package_info['parameter'] === $parameter) {
                return $setting_key;
            }
        }
        return null;
    }

    /**
     * Get all CP settings mapped to a specific package category
     * 
     * @param string $category Package category (control, dimensional, transformational)
     * @return array Array of setting keys mapped to this category
     */
    public function getCPSettingsByCategory(string $category): array
    {
        $settings = [];
        foreach (self::$cp_to_package_map as $setting_key => $package_info) {
            if ($package_info['category'] === $category) {
                $settings[] = $setting_key;
            }
        }
        return $settings;
    }

    /**
     * Get all CP settings mapped to a specific package class
     * 
     * @param string $package_class Package class name
     * @return array Array of setting keys mapped to this package
     */
    public function getCPSettingsByPackage(string $package_class): array
    {
        $settings = [];
        foreach (self::$cp_to_package_map as $setting_key => $package_info) {
            if ($package_info['package'] === $package_class) {
                $settings[] = $setting_key;
            }
        }
        return $settings;
    }

    /**
     * Check if a CP setting is mapped to parameter packages
     * 
     * @param string $setting_key CP setting key
     * @return bool True if setting is mapped to packages
     */
    public function isMappedSetting(string $setting_key): bool
    {
        return isset(self::$cp_to_package_map[$setting_key]);
    }

    /**
     * Get all mapped CP settings
     * 
     * @return array All CP setting keys that are mapped to packages
     */
    public function getAllMappedSettings(): array
    {
        return array_keys(self::$cp_to_package_map);
    }

    /**
     * Get full mapping configuration
     * 
     * @return array Complete mapping configuration
     */
    public function getFullMapping(): array
    {
        return self::$cp_to_package_map;
    }
}
