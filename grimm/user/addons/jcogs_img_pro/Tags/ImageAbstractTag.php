<?php

/**
 * JCOGS Image Pro - Abstract Tag Base Class
 * ==========================================
 * Shared service initialization and common functionality for all Image Pro tags
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Service Optimization Phase
 */

namespace JCOGSDesign\JCOGSImagePro\Tags;

use ExpressionEngine\Service\Addon\Controllers\Tag\AbstractRoute;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

/**
 * Abstract base class for all JCOGS Image Pro template tags
 * 
 * Provides shared service initialization and common helper methods
 * for all tag classes, eliminating duplicate service instantiation
 * and ensuring consistent access patterns across all tags.
 * 
 * Performance Optimizations:
 * - Singleton service pattern via ServiceCache
 * - Cache preload executed only once per request (not per tag)
 * - Static flag prevents redundant cache operations in large templates
 */
abstract class ImageAbstractTag extends AbstractRoute
{
    /**
     * @var bool Static flag to ensure cache preload only happens once per request
     */
    private static bool $cache_preloaded = false;

    /**
     * @var \JCOGSDesign\JCOGSImagePro\Service\Utilities
     */
    protected $utilities_service;

    /**
     * @var \JCOGSDesign\JCOGSImagePro\Service\Settings
     */
    protected $settings_service;

    /**
     * @var \JCOGSDesign\JCOGSImagePro\Service\PerformanceService
     */
    protected $performance_service;

    /**
     * @var \JCOGSDesign\JCOGSImagePro\Service\ValidationService
     */
    protected $validation_service;

    /**
     * @var \JCOGSDesign\JCOGSImagePro\Service\FilesystemService
     */
    protected $filesystem_service;

    /**
     * @var \JCOGSDesign\JCOGSImagePro\Service\CacheManagementService
     */
    protected $cache_service;

    /**
     * @var \JCOGSDesign\JCOGSImagePro\Service\PresetResolver
     */
    protected $preset_resolver;

    /**
     * @var \JCOGSDesign\JCOGSImagePro\Service\SecurityValidationService
     */
    protected $security_service;

    /**
     * @var int|null Benchmark instance ID for performance tracking
     */
    protected ?int $benchmark_instance_id = null;

    /**
     * Constructor - Initialize shared services using ServiceCache
     */
    public function __construct()
    {
        // Initialize all commonly used services through ServiceCache
        $this->utilities_service = ServiceCache::utilities();
        $this->settings_service = ServiceCache::settings();
        $this->performance_service = ServiceCache::performance();
        $this->validation_service = ServiceCache::validation();
        $this->filesystem_service = ServiceCache::filesystem();
        // Note: pipeline_service is now created per-process call with specific connection
        $this->cache_service = ServiceCache::cache();
        $this->security_service = ServiceCache::security();
        
        // Initialize preset resolver - may be null if not available
        $this->preset_resolver = ServiceCache::preset_resolver();
        
        // PRE-LOAD cache log index like Legacy does - but only once per request
        // regardless of cache parameter settings, matching Legacy's constructor behavior
        if (!self::$cache_preloaded) {
            $this->cache_service->preload_cache_log_index();
            self::$cache_preloaded = true;
        }
    }

    /**
     * Reset the cache preload flag for new requests or testing
     * 
     * This method should be called at the start of new requests to ensure
     * the cache preload occurs for the first tag processed.
     * 
     * @return void
     */
    public static function reset_cache_preload_flag(): void
    {
        self::$cache_preloaded = false;
    }

    /**
     * Create pipeline service with specific connection for optimal filesystem warming
     * 
     * This method extracts the connection parameter from tag parameters and creates
     * a pipeline instance targeted for that specific connection. The pipeline constructor
     * will warm up the filesystem adapter for that connection immediately.
     * 
     * @param array $tag_params Template tag parameters
     * @return mixed ImageProcessingPipeline instance configured for the connection
     */
    protected function create_pipeline_for_connection(array $tag_params)
    {
        // Extract connection parameter or use null (which will default to the configured default connection)
        $connection_name = $tag_params['connection'] ?? null;
        
        // Create pipeline instance with targeted connection warming
        return ServiceCache::pipeline($connection_name);
    }

    /**
     * Start performance benchmark for tag processing
     * 
     * @param string $tag_name Name of the tag being processed
     * @return void
     */
    protected function start_benchmark(string $tag_name): void
    {
        $this->benchmark_instance_id = $this->performance_service->start_benchmark($tag_name);
    }

    /**
     * End performance benchmark and log results
     * 
     * @param string $tag_name Name of the tag that was processed
     * @return void
     */
    protected function end_benchmark(string $tag_name): void
    {
        if ($this->benchmark_instance_id !== null) {
            $duration = $this->performance_service->end_benchmark($this->benchmark_instance_id);
            if ($duration !== null) {
                $elapsed_time_report = $this->performance_service->get_elapsed_time_report($duration);
                $this->utilities_service->debug_message(sprintf('%s processing completed: %s', $tag_name, $elapsed_time_report));
            }
            $this->benchmark_instance_id = null;
        }
    }

    /**
     * End performance benchmark with error context
     * 
     * @param string $tag_name Name of the tag that encountered an error
     * @param string $error_message Error message to log
     * @return void
     */
    protected function end_benchmark_with_error(string $tag_name, string $error_message): void
    {
        if ($this->benchmark_instance_id !== null) {
            $duration = $this->performance_service->end_benchmark_with_error($this->benchmark_instance_id, 'error');
            if ($duration !== null) {
                $elapsed_time_report = $this->performance_service->get_elapsed_time_report($duration);
                if ($error_message) {
                $this->utilities_service->debug_message(sprintf('%s error: %s - %s', $tag_name, $elapsed_time_report, $error_message));
            }
            }
            $this->benchmark_instance_id = null;
        }
    }

    /**
     * Get tag parameters with fallbacks
     * 
     * @param string $param_name Parameter name
     * @param mixed $default Default value if parameter not found
     * @return mixed Parameter value or default
     */
    protected function get_tag_param(string $param_name, $default = null)
    {
        return ee()->TMPL->fetch_param($param_name, $default);
    }

    /**
     * Set tag context for tracking
     * 
     * @param string $tag_name Name of the calling tag
     * @return void
     */
    protected function set_tag_context(string $tag_name): void
    {
        if (!isset(ee()->TMPL->tagparams)) {
            ee()->TMPL->tagparams = [];
        }
        ee()->TMPL->tagparams['_called_by'] = $tag_name;
    }

    /**
     * Handle tag error with consistent error reporting
     * 
     * @param string $tag_name Name of the tag that encountered error
     * @param \Throwable $e Exception that was thrown
     * @return string Error message for template output
     */
    protected function handle_tag_error(string $tag_name, \Throwable $e): string
    {
        $error_message = $e->getMessage();
        
        // End benchmark with error context
        $this->end_benchmark_with_error($tag_name, $error_message);
        
        // Log the error
        if (function_exists('log_message')) {
            log_message('error', "JCOGS Image Pro {$tag_name} error: {$error_message}");
        }
        
        // Return error message for development or empty string for production
        if (ee()->config->item('debug') >= 1) {
            return "<!-- JCOGS Image Pro {$tag_name} Error: {$error_message} -->";
        }
        
        return '';
    }

    /**
     * Process preset parameters and merge with tag parameters
     * 
     * This method detects preset parameters in tag parameters and resolves them
     * through the PresetResolver service. It merges preset parameters with tag
     * parameters using the priority rule: tag parameters override preset parameters.
     * 
     * @param array $tagparams Original tag parameters from template
     * @return array Merged parameters ready for pipeline processing
     */
    protected function process_preset_parameters(array $tagparams): array
    {
        try {
            // Check if PresetResolver is available
            if ($this->preset_resolver === null) {
                // PresetResolver not available - return original parameters
                $this->utilities_service->debug_message(
                    "PresetResolver not available - preset processing disabled",
                    'ImageAbstractTag'
                );
                return $tagparams;
            }
            
            // Use PresetResolver to handle preset resolution and parameter merging
            $resolved_parameters = $this->preset_resolver->resolveParameters($tagparams);
            
            // Log preset processing if debug mode is enabled
            if (isset($tagparams['preset']) && !empty($tagparams['preset'])) {
                $preset_name = $tagparams['preset'];
                $original_count = count($tagparams);
                $resolved_count = count($resolved_parameters);
                
                $this->utilities_service->debug_message(
                    "Preset '{$preset_name}' processed: {$original_count} tag params + preset params = {$resolved_count} total params",
                    'ImageAbstractTag'
                );
            }
            
            return $resolved_parameters;
            
        } catch (\Exception $e) {
            // Log preset processing error
            $this->utilities_service->debug_message(
                "Preset processing error: " . $e->getMessage(),
                'ImageAbstractTag'
            );
            
            // Fall back to original parameters
            return $tagparams;
        }
    }

    /**
     * Apply security validation to tag parameters
     * 
     * Validates all tag parameters for malicious content and sanitizes them
     * to prevent XSS, path traversal, command injection and other attacks.
     * 
     * @param array $tagparams Raw tag parameters from template
     * @return array Validated and sanitized parameters
     */
    protected function applySecurityValidation(array $tagparams): array
    {
        try {
            // Use the already-cached security validation service
            $sanitized_params = $this->security_service->validateAndSanitizeParameters($tagparams);
            
            // Log if any parameters were filtered for security
            $original_count = count($tagparams);
            $sanitized_count = count($sanitized_params);
            
            if ($original_count !== $sanitized_count) {
                $filtered_count = $original_count - $sanitized_count;
                $this->utilities_service->debug_message(
                    "Security validation filtered {$filtered_count} potentially malicious parameter(s)",
                    ['original_count' => $original_count, 'sanitized_count' => $sanitized_count]
                );
            }
            
            return $sanitized_params;
            
        } catch (\Exception $e) {
            // Log security validation error but don't block processing
            $this->utilities_service->debug_message(
                "Security validation error: " . $e->getMessage(),
                'ImageAbstractTag'
            );
            
            // Fall back to original parameters (less secure but functional)
            return $tagparams;
        }
    }

    /**
     * Abstract method that all tags must implement
     * 
     * @return string Processed template output
     */
    abstract public function process(): string;
}
