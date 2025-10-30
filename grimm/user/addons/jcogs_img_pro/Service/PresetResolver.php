<?php

/**
 * JCOGS Image Pro - Preset Resolver Service
 * ==========================================
 * Resolves preset parameters and merges with tag parameters
 * 
 * This service handles the core preset resolution logic, loading preset
 * parameters from the database and merging them with tag parameters using
 * the array_merge($preset_params, $tag_params) pattern where tag parameters
 * override preset parameters.
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Presets Feature Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service;

use JCOGSDesign\JCOGSImagePro\Service\PresetService;
use JCOGSDesign\JCOGSImagePro\Service\Utilities;
use JCOGSDesign\JCOGSImagePro\Service\ParameterPackageDiscovery;
use JCOGSDesign\JCOGSImagePro\Service\PresetDebugService;
use Exception;

class PresetResolver
{
    /**
     * PresetService instance for database operations
     * @var PresetService
     */
    private $presetService;

    /**
     * Utilities service for logging
     * @var Utilities
     */
    private $utilities;

    /**
     * Parameter package discovery for validation
     * @var ParameterPackageDiscovery
     */
    private $packageDiscovery;

    /**
     * Debug service for preset debugging and monitoring
     * @var PresetDebugService
     */
    private $debugService;

    /**
     * Parameter resolution cache
     * @var array
     */
    private static $resolution_cache = [];

    /**
     * Performance tracking
     * @var array
     */
    private static $performance_stats = [
        'resolutions' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'total_time' => 0
    ];

    /**
     * Constructor
     * 
     * @param PresetService $presetService Preset service instance
     * @param Utilities $utilities Utilities service for logging
     * @param ParameterPackageDiscovery $packageDiscovery Parameter validation service
     */
    public function __construct(PresetService $presetService = null, Utilities $utilities = null, ParameterPackageDiscovery $packageDiscovery = null)
    {
        $this->presetService = $presetService ?: new PresetService();
        $this->utilities = $utilities ?: ServiceCache::utilities();
        $this->packageDiscovery = $packageDiscovery ?: new ParameterPackageDiscovery();
        $this->debugService = new PresetDebugService();
    }

    /**
     * Resolve preset parameters and merge with tag parameters
     * 
     * This is the main method that implements the preset resolution logic:
     * 1. Extract preset name from tag parameters
     * 2. Load preset parameters from database (with caching)
     * 3. Merge using array_merge($preset_params, $tag_params)
     * 4. Validate merged parameters using parameter packages
     * 5. Return merged parameters for pipeline processing
     * 
     * @param array $tag_parameters Original tag parameters
     * @return array Merged parameters ready for pipeline processing
     */
    public function resolveParameters(array $tag_parameters): array
    {
        $start_time = microtime(true);
        self::$performance_stats['resolutions']++;

        // Check if preset parameter is specified
        if (!isset($tag_parameters['preset']) || empty($tag_parameters['preset'])) {
            // No preset specified, return original parameters unchanged
            $this->updatePerformanceStats($start_time);
            return $tag_parameters;
        }

        $preset_name = $tag_parameters['preset'];
        
        // Start debug session for comprehensive tracking
        $debug_session = $this->debugService->startPresetResolution($preset_name, $tag_parameters);

        // Try to get preset parameters (with caching)
        $this->debugService->logResolutionStep($debug_session, 'preset_lookup_start');
        $preset_parameters = $this->getPresetParameters($preset_name);

        if ($preset_parameters === null) {
            // Preset not found - log warning and return original parameters
            $this->utilities->debug_log('preset_not_found', $preset_name);
            $this->debugService->logPresetError('preset_not_found', $preset_name, 'Preset not found in database');
            
            // Note: Can't track error for non-existent preset since we need a valid preset ID
            // This error will be logged in debug output instead
            
            $this->debugService->completePresetResolution($debug_session, false);
            $this->updatePerformanceStats($start_time);
            return $tag_parameters;
        }
        
        $this->debugService->logResolutionStep($debug_session, 'preset_loaded', ['param_count' => count($preset_parameters)]);

        // Remove the 'preset' parameter from tag parameters before merging
        $tag_params_without_preset = $tag_parameters;
        unset($tag_params_without_preset['preset']);

        // Merge parameters: preset parameters first, then tag parameters override
        $merged_parameters = array_merge($preset_parameters, $tag_params_without_preset);
        
        // Log parameter merge details for debugging
        $this->debugService->logParameterMerge($debug_session, $preset_parameters, $tag_params_without_preset, $merged_parameters);

        // Validate merged parameters if validation is enabled
        $this->debugService->logResolutionStep($debug_session, 'validation_start');
        $validation_errors = $this->validateMergedParameters($merged_parameters);
        $validation_passed = empty($validation_errors);
        
        $this->debugService->logValidationResult($debug_session, $validation_errors, $validation_passed);
        
        if (!$validation_passed) {
            // Log validation errors and return original tag parameters as fallback
            $this->utilities->debug_log('preset_validation_failed', $preset_name, $validation_errors);
            $this->debugService->logPresetError('validation_failed', $preset_name, 'Parameter validation failed', $validation_errors);
            
            // Track preset error for analytics
            try {
                $preset_service = ServiceCache::preset_service();
                
                // Get the full preset data to extract the ID
                $preset_data = $preset_service->getPreset($preset_name);
                if ($preset_data && isset($preset_data['id'])) {
                    $error_message = 'Parameter validation failed: ' . implode(', ', array_keys($validation_errors));
                    $preset_service->trackPresetError($preset_data['id'], $error_message);
                }
            } catch (\Exception $e) {
                // Don't let analytics tracking break the main flow
                $this->utilities->debug_log('analytics_tracking_error', 'Error tracking failed: ' . $e->getMessage());
            }
            
            $this->debugService->completePresetResolution($debug_session, false);
            $this->updatePerformanceStats($start_time);
            return $tag_parameters; // Fallback to original parameters if validation fails
        }

        // Add preset metadata for cache key generation
        $merged_parameters['_preset_applied'] = true;
        $merged_parameters['_preset_name'] = $preset_name;
        
        $this->debugService->logResolutionStep($debug_session, 'metadata_added', [
            'preset_applied' => true,
            'preset_name' => $preset_name
        ]);

        // Track successful preset usage for analytics
        try {
            $preset_service = ServiceCache::preset_service();
            $performance_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
            
            // Get the full preset data to extract the ID
            $preset_data = $preset_service->getPreset($preset_name);
            if ($preset_data && isset($preset_data['id'])) {
                // Track usage with performance timing (this handles all analytics data)
                $preset_service->trackPresetUsage($preset_data['id'], $performance_time);
            }
            
        } catch (\Exception $e) {
            // Don't let analytics tracking break the main flow
            $this->utilities->debug_log('analytics_tracking_error', 'Usage tracking failed: ' . $e->getMessage());
        }

        // Add debug information if debug mode is enabled
        if (ee()->config->item('debug') >= 1) {
            $this->utilities->debug_log('preset_resolved_success', $preset_name, count($preset_parameters));
        }

        // Complete debug session successfully
        $this->debugService->completePresetResolution($debug_session, true, $merged_parameters);
        
        $this->updatePerformanceStats($start_time);
        return $merged_parameters;
    }

    /**
     * Get preset parameters with caching
     * 
     * @param string $preset_name Preset name
     * @return array|null Preset parameters or null if not found
     */
    private function getPresetParameters(string $preset_name): ?array
    {
        // Check cache first
        $cache_key = $this->getCacheKey($preset_name);
        
        if (isset(self::$resolution_cache[$cache_key])) {
            self::$performance_stats['cache_hits']++;
            return self::$resolution_cache[$cache_key];
        }

        self::$performance_stats['cache_misses']++;

        // Load from database
        $preset = $this->presetService->getPreset($preset_name);
        
        if ($preset === null) {
            // Cache the null result to avoid repeated database queries
            self::$resolution_cache[$cache_key] = null;
            return null;
        }

        // Extract parameters from the database result
        $parameters = $preset['parameters'] ?? [];
        
        // Cache the parameters for future use
        self::$resolution_cache[$cache_key] = $parameters;
        
        return $parameters;
    }

    /**
     * Validate merged parameters using parameter packages
     * 
     * @param array $merged_parameters Merged preset + tag parameters
     * @return array Validation errors (empty if valid)
     */
    private function validateMergedParameters(array $merged_parameters): array
    {
        try {
            // Use parameter package discovery to validate all parameters
            return $this->packageDiscovery->validateAllParameters($merged_parameters);
        } catch (Exception $e) {
            // If validation service fails, log error and return empty (allowing processing to continue)
            $this->utilities->debug_log('validation_service_error', $e->getMessage());
            return [];
        }
    }

    /**
     * Generate cache key for preset
     * 
     * @param string $preset_name Preset name
     * @return string Cache key
     */
    private function getCacheKey(string $preset_name): string
    {
        return 'preset_' . md5($preset_name);
    }

    /**
     * Clear preset resolution cache
     * 
     * @param string|null $preset_name Optional specific preset to clear, or null for all
     * @return void
     */
    public static function clearCache(string $preset_name = null): void
    {
        if ($preset_name === null) {
            self::$resolution_cache = [];
        } else {
            $cache_key = 'preset_' . md5($preset_name);
            unset(self::$resolution_cache[$cache_key]);
        }
    }

    /**
     * Get resolution statistics
     * 
     * @return array Performance and cache statistics
     */
    public static function getPerformanceStats(): array
    {
        $stats = self::$performance_stats;
        
        // Calculate derived metrics
        if ($stats['resolutions'] > 0) {
            $stats['average_time'] = $stats['total_time'] / $stats['resolutions'];
            $stats['cache_hit_rate'] = ($stats['cache_hits'] + $stats['cache_misses']) > 0
                ? $stats['cache_hits'] / ($stats['cache_hits'] + $stats['cache_misses'])
                : 0.0;
        } else {
            $stats['average_time'] = 0;
            $stats['cache_hit_rate'] = 0;
        }

        return $stats;
    }

    /**
     * Reset performance statistics
     * 
     * @return void
     */
    public static function resetPerformanceStats(): void
    {
        self::$performance_stats = [
            'resolutions' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'total_time' => 0
        ];
    }

    /**
     * Update performance statistics
     * 
     * @param float $start_time Start time from microtime(true)
     * @return void
     */
    private function updatePerformanceStats(float $start_time): void
    {
        $execution_time = microtime(true) - $start_time;
        self::$performance_stats['total_time'] += $execution_time;
        
        // Log performance metrics periodically to debug service
        if (self::$performance_stats['resolutions'] % 10 === 0) {
            $this->debugService->logPerformanceMetrics(self::getPerformanceStats());
        }
    }
    
    /**
     * Get debug service instance for external access
     * 
     * @return PresetDebugService Debug service instance
     */
    public function getDebugService(): PresetDebugService
    {
        return $this->debugService;
    }

    /**
     * Get debug information for a specific preset resolution
     * 
     * @param string $preset_name Preset name
     * @param array $tag_parameters Original tag parameters
     * @return array Debug information
     */
    public function getDebugInfo(string $preset_name, array $tag_parameters): array
    {
        $debug_info = [
            'preset_name' => $preset_name,
            'preset_exists' => false,
            'preset_parameters' => [],
            'tag_parameters' => $tag_parameters,
            'merged_parameters' => [],
            'overridden_parameters' => [],
            'cache_status' => 'miss'
        ];

        // Check cache status
        $cache_key = $this->getCacheKey($preset_name);
        if (isset(self::$resolution_cache[$cache_key])) {
            $debug_info['cache_status'] = 'hit';
        }

        // Get preset parameters
        $preset_parameters = $this->getPresetParameters($preset_name);
        if ($preset_parameters !== null) {
            $debug_info['preset_exists'] = true;
            $debug_info['preset_parameters'] = $preset_parameters;

            // Simulate merge to show what would be overridden
            $tag_params_without_preset = $tag_parameters;
            unset($tag_params_without_preset['preset']);

            $debug_info['merged_parameters'] = array_merge($preset_parameters, $tag_params_without_preset);

            // Find overridden parameters
            foreach ($preset_parameters as $key => $value) {
                if (isset($tag_params_without_preset[$key])) {
                    $debug_info['overridden_parameters'][$key] = [
                        'preset_value' => $value,
                        'tag_value' => $tag_params_without_preset[$key]
                    ];
                }
            }
        }

        return $debug_info;
    }

    /**
     * Get current cache contents (for debugging)
     * 
     * @return array Current cache state
     */
    public static function getCacheContents(): array
    {
        return self::$resolution_cache;
    }

    /**
     * Get cache size information
     * 
     * @return array Cache size statistics
     */
    public static function getCacheInfo(): array
    {
        return [
            'entries' => count(self::$resolution_cache),
            'memory_usage' => memory_get_usage(),
            'keys' => array_keys(self::$resolution_cache)
        ];
    }
}
