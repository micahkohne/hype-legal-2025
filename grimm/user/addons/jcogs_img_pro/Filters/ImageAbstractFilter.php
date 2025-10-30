<?php

/**
 * JCOGS Image Pro - Abstract Filter Base Class
 * =============================================
 * Base class for all Image Pro filters with shared service initialization
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 2 Service Optimization
 */

namespace JCOGSDesign\JCOGSImagePro\Filters;

use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;
use Imagine\Filter\FilterInterface;

/**
 * Abstract Filter Class with Shared Services
 * 
 * Provides automatic initialization of common services using the ServiceCache pattern
 * for optimal performance across all Image Pro filters.
 * 
 * All Image Pro filters should extend this class to inherit:
 * - Shared service instances (settings, utilities, filesystem, etc.)
 * - Common functionality for Image Pro filters
 * - Automatic service optimization
 * - FilterInterface compliance for Imagine compatibility
 */
abstract class ImageAbstractFilter implements FilterInterface
{
    /**
     * Shared services available to all Image Pro filters (using ServiceCache for optimal performance)
     */
    protected $settings_service;
    protected $utilities_service;
    protected $filesystem_service;
    protected $validation_service;
    protected $colour_service;
    protected $image_processing_service;
    protected $image_utilities_service;
    
    /**
     * Constructor - automatically initializes shared services
     * 
     * Called automatically when any filter extending this class is instantiated.
     * Provides immediate access to all common services without repeated instantiation.
     */
    public function __construct()
    {
        // Initialize shared services using ServiceCache for optimal performance
        $this->settings_service = ServiceCache::settings();
        $this->utilities_service = ServiceCache::utilities();
        $this->filesystem_service = ServiceCache::filesystem();
        $this->validation_service = ServiceCache::validation();
        $this->colour_service = ServiceCache::colour();
        $this->image_utilities_service = ee('jcogs_img_pro:ImageUtilities');
        
        // Note: ImageProcessingService is not in ServiceCache as it's specialized per filter
        // Filters can add it manually if needed: $this->image_processing_service = ee('jcogs_img_pro:ImageProcessingService');
    }
    
    /**
     * Common functionality for Image Pro filters
     */
    
    /**
     * Debug log using shared utilities service (available to all extending filters)
     * 
     * @param string $message Log message
     * @param mixed ...$args Additional arguments for sprintf formatting
     * @return void
     */
    protected function debug_log(string $message, ...$args): void
    {
        $this->utilities_service->debug_log($message, ...$args);
    }
    
    /**
     * Debug message using shared utilities service (available to all extending filters)
     * 
     * @param string $message Debug message
     * @param mixed $data Additional data to log
     * @param bool $include_level Include debug level in message
     * @param string $level Debug level (basic, detailed, verbose)
     * @return void
     */
    protected function debug_message(string $message, $data = null, bool $include_level = true, string $level = 'basic'): void
    {
        $this->utilities_service->debug_message($message, $data, $include_level, $level);
    }
    
    /**
     * Validate color value using shared colour service (available to all extending filters)
     * 
     * @param string $color Color value to validate
     * @return bool True if valid color
     */
    protected function is_valid_color(string $color): bool
    {
        return $this->colour_service->is_valid_color($color);
    }
    
    /**
     * Parse color value using shared colour service (available to all extending filters)
     * 
     * @param string $color Color value to parse
     * @return array|null Parsed color array or null if invalid
     */
    protected function parse_color(string $color): ?array
    {
        return $this->colour_service->parse_color($color);
    }
    
    /**
     * Validate dimension using shared validation service (available to all extending filters)
     * 
     * @param mixed $value Dimension value to validate
     * @param int $max_value Maximum allowed value
     * @return int|null Validated dimension or null if invalid
     */
    protected function validate_dimension($value, int $max_value): ?int
    {
        return $this->validation_service->validate_dimension($value, $max_value);
    }
    
    /**
     * Get setting value using shared settings service (available to all extending filters)
     * 
     * @param string $key Setting key
     * @param mixed $default Default value if setting not found
     * @return mixed Setting value
     */
    protected function get_setting(string $key, $default = null)
    {
        return $this->settings_service->get($key, $default);
    }
}
