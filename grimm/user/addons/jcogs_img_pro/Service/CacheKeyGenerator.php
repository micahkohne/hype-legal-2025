<?php

/**
 * JCOGS Image Pro - Cache Key Generator Service
 * ============================================
 * Phase 2 implementation - Exact replication of legacy cache key generation
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

use JCOGSDesign\JCOGSImagePro\Contracts\SettingsInterface;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;
use Exception;

/**
 * Cache Key Generator Service
 * 
 * This service generates cache keys using optimized Pro algorithms while maintaining
 * compatibility with legacy cache key format. Pro now uses its own settings and
 * parameter lists for complete independence from Legacy addon.
 * 
 * Uses ParameterRegistry for centralized parameter categorization to eliminate
 * maintenance issues with duplicate parameter lists across multiple services.
 * 
 * Cache Key Format:
 * {cleaned_filename}{separator}{cache_hex}{separator}{transformation_hash}
 * 
 * Example:
 * photo_jpg_-_e10_-_7e984441ee5340542a503089e76921033ecae352
 */
class CacheKeyGenerator 
{
    /**
     * @var SettingsInterface Pro Settings service
     */
    private SettingsInterface $settings_service;
    
    /**
     * @var array Transformational parameters from ParameterRegistry
     */
    private array $transformational_params;
    
    /**
     * @var ColourManagementService Color validation service
     */
    private ColourManagementService $colour_service;
    
    /**
     * Constructor
     * 
     * Initialize the cache key generator with Pro settings and parameters
     * 
     * @param SettingsInterface|null $settings_provider Optional settings provider for dependency injection
     */
    public function __construct(?SettingsInterface $settings_provider = null) 
    {
        // Initialize Pro settings service
        if ($settings_provider === null && function_exists('ee') && !defined('JCOGS_IMG_PRO_TESTING')) {
            try {
                $this->settings_service = ServiceCache::settings();
            } catch (Exception $e) {
                // Service not available - use fallback
                $this->settings_service = new Settings();
            }
        } else {
            // Use provided settings or create new Settings instance
            $this->settings_service = $settings_provider ?? new Settings();
        }
        
        $this->transformational_params = ParameterRegistry::getParametersByCategory('transformational');
        
        // Initialize colour service with Pro settings
        $this->colour_service = new ColourManagementService($this->settings_service);
    }
    
    /**
     * Clean filename using exact legacy algorithm
     * 
     * Replicates the filename cleaning logic from _build_filename method
     * 
     * @param string $filename Original filename (could be full URL)
     * @param array $params Tag parameters
     * @return string Cleaned filename
     */
    private function clean_filename(string $filename, array $params): string 
    {
        // CRITICAL FIX: Extract just filename from URL or path 
        // Legacy gets actual filename, not full URL/path, so we need to extract it
        if (!empty($filename)) {
            if (strpos($filename, 'http://') === 0 || strpos($filename, 'https://') === 0) {
                // Extract filename from full URL path and remove extension for cache key
                $path_parts = pathinfo(parse_url($filename, PHP_URL_PATH));
                $filename = $path_parts['filename'] ?? '';
            } elseif (strpos($filename, '/') !== false) {
                // Extract filename from relative path (e.g., "/media/images/joe_and_morgan.jpg" -> "joe_and_morgan")
                $filename = pathinfo($filename, PATHINFO_FILENAME);
            }
            // If filename has no slashes, it's already just a filename, use as-is
        }
        
        // Handle empty filename case (exact legacy logic)
        if (empty($filename) && isset($params['src'])) {
            // Use hash of src as filename (exact legacy algorithm)
            $filename = hash('tiger160,3', str_replace('%', 'pct', urlencode($params['src'])));
        }
        
        if (empty($filename)) {
            // Create fallback filename (exact legacy algorithm)
            $filename = 'no_filename' . strval(random_int(1, 999));
        }
        
        // Apply filename prefix/suffix/override (exact legacy logic)
        if (isset($params['filename']) && $params['filename']) {
            $filename = $params['filename'];
        }
        if (isset($params['filename_prefix']) && $params['filename_prefix']) {
            $filename = $params['filename_prefix'] . $filename;
        }
        if (isset($params['filename_suffix']) && $params['filename_suffix']) {
            $filename = $filename . $params['filename_suffix'];
        }
        
        // URL decode (exact legacy logic)
        $filename = urldecode($filename);
        
        // Remove special characters (exact legacy character list)
        $special_chars = [
            '\'', '<', '>', '&', '/', '\\', '?', '%', '*', ':', '|', '"', 
            '<', '>', '!', '@', '#', '$', '^', '(', ')', '[', ']', '{', '}', 
            ';', ':', ',', '.', '`', '~', '+', '=', ' ', ' '
        ];
        $filename = str_replace($special_chars, '_', $filename);
        
        // Remove % characters (exact legacy logic)
        $filename = str_replace('%', '_', $filename);
        
        // Lowercase (exact legacy logic)
        $filename = strtolower($filename);
        
        // Truncate if too long (exact legacy logic)
        $max_length = $this->settings_service->get('img_cp_default_max_source_filename_length', 150);
        if (strlen($filename) >= $max_length) {
            try {
                $filename = trim(substr($filename, 0, $max_length) . strval(random_int(1, 999)));
            } catch (Exception $e) {
                $filename = trim(substr($filename, 0, $max_length) . strval(mt_rand(1, 999)));
            }
        }
        
        // Handle hash_filename parameter (exact legacy logic)
        if (isset($params['hash_filename']) && strtolower(substr($params['hash_filename'], 0, 1)) == 'y') {
            $filename = hash('tiger160,3', serialize($filename));
        }
        
        return $filename;
    }
    
    /**
     * Generate cache key using EXACT legacy algorithm
     * 
     * This method replicates the _build_filename method from JcogsImage exactly,
     * ensuring 100% cache key compatibility with the legacy implementation.
     * 
     * @param string $filename Original filename (without extension)
     * @param array $params EE7 tag parameters
     * @param bool $using_fallback Whether using fallback image
     * @return string Generated cache key
     */
    public function generate_cache_key(string $filename, array $params, bool $using_fallback = false): string 
    {
        // Step 1: Clean and prepare filename (exact legacy algorithm)
        $cleaned_filename = $this->clean_filename($filename, $params);
        
        // Step 2: Generate cache duration hex tag (exact legacy algorithm)
        $cache_tag = $this->_generate_cache_hex_tag($params);
        
        // Step 3: Generate transformation hash (exact legacy algorithm)  
        $transformation_hash = $this->_generate_transformation_hash($params, $using_fallback);
        
        // Step 4: Combine using exact legacy format
        $separator = $this->settings_service->get('img_cp_default_filename_separator', '_-_');
        
        $cache_key = $cleaned_filename 
            . $separator 
            . $cache_tag 
            . $separator 
            . $transformation_hash;
        
        // Log cache key generation for preset debugging if preset was applied
        if (isset($params['_preset_applied']) && $params['_preset_applied']) {
            try {
                $debug_service = ServiceCache::preset_debug();
                if ($debug_service !== null) {
                    $debug_service->logCacheKeyGeneration(
                        $params['_preset_name'] ?? 'unknown',
                        $cache_key,
                        $params
                    );
                }
            } catch (Exception $e) {
                // Silently continue if debug service not available
            }
        }
        
        return $cache_key;
    }
    
    /**
     * Generate cache hex tag using exact legacy algorithm
     * 
     * Replicates the cache duration to hex conversion from _build_filename
     * 
     * @param array $params Tag parameters
     * @return string Hex representation of cache duration
     */
    private function _generate_cache_hex_tag(array $params): string 
    {
        $cache_duration = $params['cache'] ?? 0;
        
        // Exact legacy algorithm
        return is_numeric($cache_duration) && $cache_duration > -1 
            ? dechex($cache_duration) 
            : 'abcdef';
    }
    
    /**
     * Generate transformation hash using exact legacy algorithm
     * 
     * Replicates the options array building and hashing from _build_filename
     * 
     * @param array $params Tag parameters
     * @param bool $using_fallback Whether using fallback image
     * @return string Transformation hash
     */
    private function _generate_transformation_hash(array $params, bool $using_fallback = false): string 
    {
        $options = [];
        
        // Step 1: Start with preset parameters if preset was applied
        $effective_params = $params;
        if (isset($params['_preset_applied']) && $params['_preset_applied']) {
            // Preset parameters should already be merged in $params by PresetResolver
            // but we need to ensure proper precedence and add preset identifier
            $preset_name = $params['_preset_name'] ?? 'unknown';
            
            // Add preset identifier to distinguish preset vs non-preset cache entries
            // This goes directly to options since it's metadata, not a parameter
            $options['_preset'] = $preset_name;
        }
        
        // Step 2: Filter for only transformational parameters from the effective params
        // This ensures only transformation-related params (from preset or tag) make it to hash
        foreach ($effective_params as $param => $value) {
            if (in_array($param, $this->transformational_params)) {
                $options[$param] = $value;
            }
        }
        
        // Add bg_color without Imagine Palette object (exact legacy logic)
        if (isset($effective_params['bg_color'])) {
            $bg_color = $effective_params['bg_color'];
            
            // If it's a string, validate it like legacy does
            if (is_string($bg_color)) {
                $bg_color = $this->colour_service->validate_colour_string($bg_color);
            }
            
            // Extract RGBA values like legacy (assuming Imagine Color object or similar)
            if (is_object($bg_color) && method_exists($bg_color, 'getRed')) {
                $options['bg_color'] = $bg_color->getRed() . $bg_color->getGreen() . $bg_color->getBlue() . $bg_color->getAlpha();
            } else {
                $options['bg_color'] = $bg_color;
            }
        }
        
        // Add source path if option enabled (exact legacy logic)
        if (strtolower(substr($this->settings_service->get('img_cp_include_source_in_filename_hash', 'n'), 0, 1)) == 'y') {
            $options['src'] = $effective_params['src'] ?? '';
        }
        
        // Add license mode differentiation (exact legacy logic)
        $options['license_mode'] = $this->settings_service->get('jcogs_license_mode', 'standard');
        
        // Add fallback marker if using fallback (exact legacy logic)
        if ($using_fallback) {
            $options['fallback'] = 'true';
        }
        
        // Create options string and hash (exact legacy algorithm)
        $options_string = implode($options);
        return hash('tiger160,3', $options_string);
    }
}
