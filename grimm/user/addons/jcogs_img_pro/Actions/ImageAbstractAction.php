<?php

/**
 * JCOGS Image Pro - Abstract Action Base Class
 * =============================================
 * Base class for all Image Pro action handlers with shared service initialization
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

namespace JCOGSDesign\JCOGSImagePro\Actions;

use ExpressionEngine\Service\Addon\Controllers\Action\AbstractRoute;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

/**
 * Abstract Action Class with Shared Services
 * 
 * Extends EE's Action AbstractRoute and provides automatic initialization of common services
 * using the ServiceCache pattern for optimal performance.
 * 
 * All Image Pro action handlers should extend this class to inherit:
 * - Shared service instances (settings, utilities, filesystem, etc.)
 * - Common functionality for Image Pro actions
 * - Automatic service optimization
 * - Performance tracking and error handling helpers
 */
abstract class ImageAbstractAction extends AbstractRoute
{
    /**
     * Shared services available to all Image Pro actions (using ServiceCache for optimal performance)
     */
    protected $settings_service;
    protected $utilities_service;
    protected $filesystem_service;
    protected $validation_service;
    protected $performance_service;
    protected $pipeline_service;
    protected $cache_service;
    
    /**
     * @var int|null Benchmark instance ID for performance tracking
     */
    protected ?int $benchmark_instance_id = null;
    
    /**
     * Constructor - automatically initializes shared services
     * 
     * Called automatically when any action extending this class is instantiated.
     * Provides immediate access to all common services without repeated instantiation.
     */
    public function __construct()
    {
        // Initialize shared services using ServiceCache for optimal performance
        $this->settings_service = ServiceCache::settings();
        $this->utilities_service = ServiceCache::utilities();
        $this->filesystem_service = ServiceCache::filesystem();
        $this->validation_service = ServiceCache::validation();
        $this->performance_service = ServiceCache::performance();
        $this->pipeline_service = ServiceCache::pipeline();
        $this->cache_service = ServiceCache::cache();
        
        // PRE-LOAD cache log index like Legacy does - this should run once per request
        // regardless of cache parameter settings, matching Legacy's constructor behavior
        $this->cache_service->preload_cache_log_index();
    }

    /**
     * End performance benchmark successfully
     * 
     * @param string $action_name Name of the action that was processed
     * @return void
     */
    protected function end_benchmark(string $action_name): void
    {
        if ($this->benchmark_instance_id !== null) {
            $this->performance_service->end_benchmark($this->benchmark_instance_id, $action_name);
            $this->benchmark_instance_id = null;
        }
    }

    /**
     * End performance benchmark with error
     * 
     * @param string $action_name Name of the action that was processed
     * @param string $error_message Error message to log
     * @return void
     */
    protected function end_benchmark_with_error(string $action_name, string $error_message): void
    {
        if ($this->benchmark_instance_id !== null) {
            $this->performance_service->end_benchmark_with_error($this->benchmark_instance_id, $action_name, $error_message);
            $this->benchmark_instance_id = null;
        }
    }

    /**
     * Get current settings (available to all extending actions)
     * 
     * @return array Current settings
     */
    protected function get_current_settings(): array
    {
        return $this->settings_service->all();
    }

    /**
     * Handle action error with consistent logging and fallback
     * 
     * @param string $action_name Name of the action that encountered error
     * @param \Throwable $exception The exception that was caught
     * @return string Empty string for actions that serve binary data
     */
    protected function handle_action_error(string $action_name, \Throwable $exception): string
    {
        // Log the error for debugging
        $error_message = sprintf('%s error: %s', $action_name, $exception->getMessage());
        
        if (function_exists('log_message')) {
            log_message('error', 'JCOGS Image Pro ' . $error_message);
        }
        
        // Debug log for development
        $this->utilities_service->debug_log('JCOGS Action Error: ' . $error_message);
        
        // Return empty string to avoid corrupting binary output
        return '';
    }
    
    /**
     * Common functionality for Image Pro actions
     */
    
    /**
     * Start performance benchmark for action processing
     * 
     * @param string $action_name Name of the action being processed
     * @return void
     */
    protected function start_benchmark(string $action_name): void
    {
        $this->benchmark_instance_id = $this->performance_service->start_benchmark($action_name);
    }
}
