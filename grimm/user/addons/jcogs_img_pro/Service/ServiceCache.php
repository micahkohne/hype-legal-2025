<?php

/**
 * JCOGS Image Pro - Shared Service Cache
 * =====================================
 * Centralized static service caching for optimal performance across all classes
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

namespace JCOGSDesign\JCOGSImagePro\Service;

use JCOGSDesign\JCOGSImagePro\Service\Utilities;
use JCOGSDesign\JCOGSImagePro\Service\SecurityValidationService;
use Exception;

/**
 * Shared Service Cache
 * 
 * Provides static caching for commonly used services to eliminate repeated
 * service container lookups and improve performance across all addon classes.
 * 
 * This is particularly beneficial for frequently used services like Utilities,
 * Settings, PerformanceService, etc. that are instantiated by multiple classes.
 */
class ServiceCache 
{
    /**
     * Static service cache - stores service instances
     */
    private static ?Utilities $utilities = null;
    private static $settings = null;
    private static $performance = null;
    private static $filesystem = null;
    private static $validation = null;
    private static $colour = null;
    private static $cache_management = null;
    private static $cache_key_generator = null;
    private static $lazy_loading = null;
    private static $face_detection = null;
    private static $preset_resolver = null;
    private static $preset_debug = null;
    private static $preset_service = null;
    private static ?SecurityValidationService $security = null;
    
    /**
     * Get CacheManagementService instance
     * 
     * @return mixed CacheManagementService instance
     */
    public static function cache() 
    {
        if (self::$cache_management === null) {
            self::$cache_management = ee('jcogs_img_pro:CacheManagementService');
        }
        return self::$cache_management;
    }
    
    /**
     * Get CacheKeyGenerator service instance
     * 
     * @return mixed CacheKeyGenerator instance
     */
    public static function cache_key_generator() 
    {
        if (self::$cache_key_generator === null) {
            self::$cache_key_generator = ee('jcogs_img_pro:CacheKeyGenerator');
        }
        return self::$cache_key_generator;
    }
    
    /**
     * Clear all cached services (useful for testing or memory management)
     * 
     * @return void
     */
    public static function clear_cache(): void 
    {
        self::$utilities = null;
        self::$settings = null;
        self::$performance = null;
        self::$filesystem = null;
        self::$validation = null;
        self::$colour = null;
        self::$cache_management = null;
        self::$cache_key_generator = null;
        self::$lazy_loading = null;
        self::$face_detection = null;
        self::$preset_resolver = null;
        self::$preset_debug = null;
        self::$preset_service = null;
        self::$security = null;
    }
    
    /**
     * Get ColourManagementService instance
     * 
     * @return mixed ColourManagementService instance
     */
    public static function colour() 
    {
        if (self::$colour === null) {
            self::$colour = ee('jcogs_img_pro:ColourManagementService');
        }
        return self::$colour;
    }
    
    /**
     * Get FaceDetectionService instance
     * 
     * @return \JCOGSDesign\JCOGSImagePro\Service\FaceDetectionService FaceDetectionService instance
     */
    public static function face_detection() 
    {
        if (self::$face_detection === null) {
            self::$face_detection = new \JCOGSDesign\JCOGSImagePro\Service\FaceDetectionService();
        }
        return self::$face_detection;
    }
    
    /**
     * Get FilesystemService instance
     * 
     * @return mixed FilesystemService instance
     */
    public static function filesystem() 
    {
        if (self::$filesystem === null) {
            self::$filesystem = ee('jcogs_img_pro:FilesystemService');
        }
        return self::$filesystem;
    }
    
    /**
     * Get all cached service instances (for debugging)
     * 
     * @return array Array of cached service instances
     */
    public static function get_cached_services(): array 
    {
        return [
            'utilities' => self::$utilities !== null,
            'settings' => self::$settings !== null,
            'performance' => self::$performance !== null,
            'filesystem' => self::$filesystem !== null,
            'validation' => self::$validation !== null,
            'colour' => self::$colour !== null,
            'pipeline' => 'factory_pattern', // No longer cached - uses factory pattern
            'cache' => self::$cache_management !== null,
            'cache_key_generator' => self::$cache_key_generator !== null,
            'lazy_loading' => self::$lazy_loading !== null,
            'face_detection' => self::$face_detection !== null,
            'security' => self::$security !== null,
        ];
    }
    
    /**
     * Get LazyLoadingService instance
     * 
     * @return mixed LazyLoadingService instance
     */
    public static function lazy_loading() 
    {
        if (self::$lazy_loading === null) {
            self::$lazy_loading = ee('jcogs_img_pro:LazyLoadingService');
        }
        return self::$lazy_loading;
    }
    
    /**
     * Get PerformanceService instance
     * 
     * @return mixed PerformanceService instance
     */
    public static function performance() 
    {
        if (self::$performance === null) {
            self::$performance = ee('jcogs_img_pro:PerformanceService');
        }
        return self::$performance;
    }
    
    /**
     * Get PresetResolver instance
     * 
     * @return mixed PresetResolver instance or null if not available
     */
    public static function preset_resolver() 
    {
        if (self::$preset_resolver === null) {
            try {
                // Check if PresetResolver class exists before instantiating
                if (class_exists('\JCOGSDesign\JCOGSImagePro\Service\PresetResolver')) {
                    self::$preset_resolver = new \JCOGSDesign\JCOGSImagePro\Service\PresetResolver();
                } else {
                    // Class not found - return null and log if possible
                    if (function_exists('error_log')) {
                        error_log('[JCOGS Image Pro] PresetResolver class not found - preset functionality disabled');
                    }
                    return null;
                }
            } catch (Exception $e) {
                // Failed to instantiate - return null and log error
                if (function_exists('error_log')) {
                    error_log('[JCOGS Image Pro] Failed to instantiate PresetResolver: ' . $e->getMessage());
                }
                return null;
            }
        }
        return self::$preset_resolver;
    }
    
    /**
     * Create ImageProcessingPipeline instance for specific connection
     * 
     * Changed from singleton to factory pattern to support per-call instantiation
     * with specific connection targeting for optimal filesystem warming.
     * 
     * @param string|null $connection_name Specific connection for this pipeline instance
     * @return mixed ImageProcessingPipeline instance configured for the connection
     */
    public static function pipeline(?string $connection_name = null) 
    {
        // Create a new pipeline instance for each call with the specific connection
        // This enables targeted filesystem warming in the constructor
        $utilities_service = self::utilities();
        
        return new \JCOGSDesign\JCOGSImagePro\Service\ImageProcessingPipeline(
            $connection_name, 
            $utilities_service
        );
    }
    
    /**
     * Get Settings service instance
     * 
     * @return mixed Settings service instance
     */
    public static function settings() 
    {
        if (self::$settings === null) {
            self::$settings = ee('jcogs_img_pro:Settings');
        }
        return self::$settings;
    }
    
    /**
     * Get Utilities service instance
     * 
     * @return Utilities
     */
    public static function utilities(): Utilities 
    {
        if (self::$utilities === null) {
            self::$utilities = ee('jcogs_img_pro:Utilities');
        }
        return self::$utilities;
    }
    
    /**
     * Get ValidationService instance
     * 
     * @return mixed ValidationService instance
     */
    public static function validation() 
    {
        if (self::$validation === null) {
            self::$validation = ee('jcogs_img_pro:ValidationService');
        }
        return self::$validation;
    }
    
    /**
     * Get PresetDebugService instance
     * 
     * @return PresetDebugService|null Debug service instance or null if not available
     */
    public static function preset_debug() 
    {
        if (self::$preset_debug === null) {
            try {
                // Check if PresetDebugService class exists before instantiating
                if (class_exists('\JCOGSDesign\JCOGSImagePro\Service\PresetDebugService')) {
                    self::$preset_debug = new \JCOGSDesign\JCOGSImagePro\Service\PresetDebugService();
                } else {
                    // Class not found - return null
                    return null;
                }
            } catch (Exception $e) {
                // Failed to instantiate - return null
                return null;
            }
        }
        return self::$preset_debug;
    }
    
    /**
     * Get PresetService instance
     * 
     * @return PresetService PresetService instance
     */
    public static function preset_service() 
    {
        if (self::$preset_service === null) {
            self::$preset_service = new \JCOGSDesign\JCOGSImagePro\Service\PresetService();
        }
        return self::$preset_service;
    }
    
    /**
     * Get SecurityValidationService instance
     * 
     * @return SecurityValidationService Security validation service instance
     */
    public static function security(): SecurityValidationService
    {
        if (self::$security === null) {
            self::$security = new SecurityValidationService();
        }
        return self::$security;
    }
}
