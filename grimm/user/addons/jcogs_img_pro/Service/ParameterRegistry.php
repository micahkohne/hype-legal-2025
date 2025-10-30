<?php

/**
 * JCOGS Image Pro - Parameter Registry Service
 * =============================================
 * Central registry for all parameter categorization and management
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Parameter Registry Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service;

/**
 * Parameter Registry Service
 * 
 * Single source of truth for parameter categorization in JCOGS Image Pro.
 * Uses Legacy's proven categorization system (control/dimensional/transformational)
 * to eliminate maintenance issues with duplicate parameter lists.
 * 
 * This registry replaces standalone parameter lists in:
 * - CacheKeyGenerator::_initialize_transformational_params()
 * - Future ValidationService parameter categorization
 * - Parameter Package directory organization
 * 
 * Based on Legacy ImageUtilities.php parameter categorization with Pro extensions.
 */
class ParameterRegistry 
{
    /**
     * Parameter definitions with Legacy categorization
     * 
     * Format: 'parameter_name' => ['category' => 'type', 'package' => 'PackageClass']
     * 
     * Categories (from Legacy ImageUtilities.php):
     * - 'control': Output parameters, cache settings, file paths
     * - 'dimensional': Size and dimension parameters  
     * - 'transformational': Image modification parameters
     * 
     * @var array
     */
    private static array $parameter_definitions = [
        // CONTROL PARAMETERS (Legacy 'control' type)
        // File and output control parameters
        'src' => ['category' => 'control', 'package' => 'SrcParameter'],
        'filename' => ['category' => 'control', 'package' => 'FilenameParameter'],
        'filename_prefix' => ['category' => 'control', 'package' => 'FilenameParameter'],
        'filename_suffix' => ['category' => 'control', 'package' => 'FilenameParameter'],
        'hash_filename' => ['category' => 'control', 'package' => 'ControlParameterPackage'],
        'cache' => ['category' => 'control', 'package' => 'ControlParameterPackage'],
        'cache_dir' => ['category' => 'control', 'package' => 'ControlParameterPackage'],
        'overwrite_cache' => ['category' => 'control', 'package' => 'ControlParameterPackage'],
        'connection' => ['category' => 'control', 'package' => 'ControlParameterPackage'],
        'debug' => ['category' => 'control', 'package' => 'ControlParameterPackage'],
        'output' => ['category' => 'control', 'package' => 'OutputParameter'],
        'save_type' => ['category' => 'control', 'package' => 'OutputParameter'],
        'url_only' => ['category' => 'control', 'package' => 'OutputParameter'],
        'lazy' => ['category' => 'control', 'package' => 'OutputParameter'],
        'sizes' => ['category' => 'control', 'package' => 'OutputParameter'],
        'srcset' => ['category' => 'control', 'package' => 'OutputParameter'],
        'exclude_style' => ['category' => 'control', 'package' => 'OutputParameter'],
        'exclude_regex' => ['category' => 'control', 'package' => 'OutputParameter'],
        'image_path_prefix' => ['category' => 'control', 'package' => 'PathParameter'],
        'use_image_path_prefix' => ['category' => 'control', 'package' => 'PathParameter'],
        'svg_passthrough' => ['category' => 'control', 'package' => 'FormatParameter'],
        'palette_size' => ['category' => 'control', 'package' => 'FormatParameter'],
        'add_dims' => [
            'category' => 'control', 
            'package' => 'OutputParameter',
            'doc_url' => 'https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-add-dims'
        ],
        'attributes' => [
            'category' => 'control', 
            'package' => 'OutputParameter',
            'doc_url' => 'https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-attributes'
        ],
        'add_dimensions' => [
            'category' => 'control', 
            'package' => 'OutputParameter',
            'doc_url' => 'https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-add-dimensions'
        ],
        'consolidate_class_style' => [
            'category' => 'control', 
            'package' => 'OutputParameter',
            'doc_url' => 'https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-consolidate-class-style'
        ],
        'create_tag' => [
            'category' => 'control', 
            'package' => 'OutputParameter',
            'doc_url' => 'https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-create-tag'
        ],
        'disable_browser_checks' => [
            'category' => 'control', 
            'package' => 'OutputParameter',
            'doc_url' => 'https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-disable-browser-checks'
        ],
        'exclude_class' => [
            'category' => 'control', 
            'package' => 'OutputParameter',
            'doc_url' => 'https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-exclude-class'
        ],
        
        // DIMENSIONAL PARAMETERS (Legacy 'dimensional' type)
        // Size and dimension parameters only
        'width' => ['category' => 'dimensional', 'package' => 'DimensionParameter'],
        'height' => ['category' => 'dimensional', 'package' => 'DimensionParameter'],
        'max' => ['category' => 'dimensional', 'package' => 'DimensionParameter'],
        'max_width' => ['category' => 'dimensional', 'package' => 'DimensionParameter'],
        'max_height' => ['category' => 'dimensional', 'package' => 'DimensionParameter'],
        'min' => ['category' => 'dimensional', 'package' => 'DimensionParameter'],
        'min_width' => ['category' => 'dimensional', 'package' => 'DimensionParameter'],
        'min_height' => ['category' => 'dimensional', 'package' => 'DimensionParameter'],
        
        // TRANSFORMATIONAL PARAMETERS (Legacy 'transformational' type)
        // All image modification and enhancement parameters
        
        // Core transformation parameters from Legacy
        'allow_scale_larger' => [
            'category' => 'transformational', 
            'package' => 'BehaviorParameter',
            'doc_url' => 'https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-allow-scale-larger'
        ],
        'aspect_ratio' => ['category' => 'transformational', 'package' => 'AspectRatioParameter'],
        'auto_sharpen' => ['category' => 'transformational', 'package' => 'SharpenParameter'],
        'bg_color' => ['category' => 'transformational', 'package' => 'BackgroundParameter'],
        'border' => [
            'category' => 'transformational', 
            'package' => 'BorderParameter',
            'doc_url' => 'https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-border'
        ],
        'crop' => [
            'category' => 'transformational', 
            'package' => 'CropParameter',
            'doc_url' => 'https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-crop'
        ],
        'face_crop_margin' => ['category' => 'transformational', 'package' => 'FaceDetectionParameter'],
        'face_detect_sensitivity' => ['category' => 'transformational', 'package' => 'FaceDetectionParameter'],
        'fallback_src' => ['category' => 'transformational', 'package' => 'FallbackParameter'],
        'filter' => [
            'category' => 'transformational', 
            'package' => 'FilterParameter',
            'doc_url' => 'https://jcogs.net/add-ons/image/documentation#filter'
        ],
        'fit' => ['category' => 'transformational', 'package' => 'ResizeParameter'],
        'flip' => ['category' => 'transformational', 'package' => 'TransformParameter'],
        'interlace' => ['category' => 'transformational', 'package' => 'OptimizationParameter'],
        'png_quality' => ['category' => 'transformational', 'package' => 'QualityParameter'],
        'preload' => ['category' => 'transformational', 'package' => 'OptimizationParameter'],
        'quality' => ['category' => 'transformational', 'package' => 'QualityParameter'],
        'reflection' => [
            'category' => 'transformational', 
            'package' => 'ReflectionParameter',
            'doc_url' => 'https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-reflection'
        ],
        'rotate' => ['category' => 'transformational', 'package' => 'TransformParameter'],
        'rounded_corners' => [
            'category' => 'transformational', 
            'package' => 'RoundedCornersParameter',
            'doc_url' => 'https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-rounded-corners'
        ],
                'text' => [
            'category' => 'transformational', 
            'package' => 'TextParameter',
            'doc_url' => 'https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-text'
        ],
        'watermark' => [
            'category' => 'transformational', 
            'package' => 'WatermarkParameter',
            'doc_url' => 'https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-watermark'
        ],
        
        // Pro-specific face detection (not in Legacy)
        'face_detect_crop_focus' => ['category' => 'transformational', 'package' => 'FaceDetectionParameter'],
        
        // Pro-specific enhancement parameters (not in Legacy)
        'sharpen' => ['category' => 'transformational', 'package' => 'SharpenParameter'],
        'contrast' => ['category' => 'transformational', 'package' => 'ContrastParameter'],
        'brightness' => ['category' => 'transformational', 'package' => 'BrightnessParameter'],
        'hue' => ['category' => 'transformational', 'package' => 'ColorParameter'],
        'saturation' => ['category' => 'transformational', 'package' => 'ColorParameter'],
        'lightness' => ['category' => 'transformational', 'package' => 'ColorParameter'],
        'blur' => ['category' => 'transformational', 'package' => 'BlurParameter'],
        'pixelate' => ['category' => 'transformational', 'package' => 'EffectParameter'],
        'emboss' => ['category' => 'transformational', 'package' => 'EffectParameter'],
        'edge_enhance' => ['category' => 'transformational', 'package' => 'EffectParameter'],
        'find_edges' => ['category' => 'transformational', 'package' => 'EffectParameter'],
        
        // Pro-specific advanced transformation parameters
        'perspective' => ['category' => 'transformational', 'package' => 'AdvancedTransformParameter'],
        'distort' => ['category' => 'transformational', 'package' => 'AdvancedTransformParameter'],
        'skew' => ['category' => 'transformational', 'package' => 'AdvancedTransformParameter'],
        
        // Pro-specific optimization parameters
        'progressive' => ['category' => 'transformational', 'package' => 'OptimizationParameter'],
        'strip_meta' => ['category' => 'transformational', 'package' => 'OptimizationParameter'],
        'optimize' => ['category' => 'transformational', 'package' => 'OptimizationParameter'],
    ];
    
    /**
     * Get parameter category using Legacy categorization
     * 
     * @param string $parameter Parameter name
     * @return string Category ('control', 'dimensional', 'transformational')
     */
    public static function getParameterCategory(string $parameter): string
    {
        return self::$parameter_definitions[$parameter]['category'] ?? 'control';
    }
    
    /**
     * Get all parameters in a specific category
     * 
     * Used by CacheKeyGenerator to get transformational parameters,
     * ValidationService for category-specific validation, and 
     * Parameter Packages for directory organization.
     * 
     * @param string $category Category name ('control', 'dimensional', 'transformational')
     * @return array Array of parameter names in the category
     */
    public static function getParametersByCategory(string $category): array
    {
        return array_keys(array_filter(
            self::$parameter_definitions, 
            fn($definition) => $definition['category'] === $category
        ));
    }
    
    /**
     * Get parameter package class name
     * 
     * @param string $parameter Parameter name
     * @return string|null Package class name or null if not found
     */
    public static function getParameterPackageClass(string $parameter): ?string
    {
        return self::$parameter_definitions[$parameter]['package'] ?? null;
    }
    
    /**
     * Get parameter documentation URL
     * 
     * @param string $parameter Parameter name
     * @return string|null Documentation URL or null if not available
     */
    public static function getParameterDocumentationUrl(string $parameter): ?string
    {
        return self::$parameter_definitions[$parameter]['doc_url'] ?? null;
    }
    
    /**
     * Get all defined parameters
     * 
     * @return array Array of all parameter names
     */
    public static function getAllParameters(): array
    {
        return array_keys(self::$parameter_definitions);
    }
    
    /**
     * Check if parameter exists in registry
     * 
     * @param string $parameter Parameter name
     * @return bool True if parameter is registered
     */
    public static function parameterExists(string $parameter): bool
    {
        return array_key_exists($parameter, self::$parameter_definitions);
    }
    
    /**
     * Get parameter definition
     * 
     * @param string $parameter Parameter name
     * @return array|null Parameter definition array or null if not found
     */
    public static function getParameterDefinition(string $parameter): ?array
    {
        return self::$parameter_definitions[$parameter] ?? null;
    }
    
    /**
     * Get all categories
     * 
     * @return array Array of available categories
     */
    public static function getCategories(): array
    {
        return ['control', 'dimensional', 'transformational'];
    }
    
    /**
     * Add parameter to registry (for extensions)
     * 
     * Allows third-party extensions to register new parameters
     * 
     * @param string $parameter Parameter name
     * @param string $category Category ('control', 'dimensional', 'transformational')
     * @param string $package_class Package class name
     * @return void
     */
    public static function registerParameter(string $parameter, string $category, string $package_class): void
    {
        self::$parameter_definitions[$parameter] = [
            'category' => $category,
            'package' => $package_class
        ];
    }
}
