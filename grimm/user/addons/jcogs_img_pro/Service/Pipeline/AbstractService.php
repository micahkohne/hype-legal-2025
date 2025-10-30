<?php

/**
 * JCOGS Image Pro - Abstract Service Base Class
 * =============================================
 * Phase 2: Native EE7 implementation service architecture
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 2 Native Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service\Pipeline;

use JCOGSDesign\JCOGSImagePro\Service\Utilities;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

/**
 * Abstract Service Class
 * 
 * Provides common functionality for all pipeline services.
 * Concrete services extend this class to get automatic access to shared services.
 * 
 * This mirrors the AbstractStage pattern but for services, ensuring consistency
 * in how services access shared dependencies and improving maintainability.
 * 
 * All common services are automatically available through shared cache for optimal performance.
 */
abstract class AbstractService 
{
    /**
     * @var string Service name for debugging and identification
     */
    protected string $service_name;
    
    /**
     * @var \JCOGSDesign\JCOGSImagePro\Service\Utilities Utilities service for debug logging
     */
    protected Utilities $utilities_service;
    
    /**
     * Common services available to all pipeline services (using shared cache for performance)
     */
    protected $settings_service;
    protected $filesystem_service;
    protected $performance_service;
    protected $validation_service;
    protected $colour_service;
    protected $pipeline_service;
    protected $cache_service;
    // Note: lazy_loading_service property removed to prevent circular dependency
    
    /**
     * Constructor
     * 
     * Automatically initializes all common services using shared cache for optimal performance.
     * Pipeline services no longer need to manually instantiate these common services.
     * 
     * @param string $service_name Service name for identification
     */
    public function __construct(string $service_name) 
    {
        $this->service_name = $service_name;
        
        // Initialize all common services using shared cache for performance
        $this->utilities_service = ServiceCache::utilities();
        $this->settings_service = ServiceCache::settings();
        $this->filesystem_service = ServiceCache::filesystem();
        $this->performance_service = ServiceCache::performance();
        $this->validation_service = ServiceCache::validation();
        $this->colour_service = ServiceCache::colour();
        $this->pipeline_service = ServiceCache::pipeline();
        $this->cache_service = ServiceCache::cache();
        // Note: lazy_loading_service not initialized here to prevent circular dependency
        // LazyLoadingService itself should not depend on LazyLoadingService
    }
    
    /**
     * Get service name
     * 
     * @return string Service identifier
     */
    public function get_service_name(): string 
    {
        return $this->service_name;
    }
    
    /**
     * Debug log method for consistent service logging
     * 
     * @param string $message Debug message
     * @param mixed $data Optional data to log
     * @param string $level Debug level (detailed, standard, minimal)
     */
    protected function debug_log(string $message, mixed $data = null, string $level = 'standard'): void 
    {
        $formatted_message = "[{$this->service_name}] {$message}";
        $this->utilities_service->debug_message($formatted_message, $data, false, $level);
    }
    
    /**
     * Get all available services as array for easy access
     * 
     * @return array Associative array of all services
     */
    protected function get_all_services(): array 
    {
        return [
            'utilities' => $this->utilities_service,
            'settings' => $this->settings_service,
            'filesystem' => $this->filesystem_service,
            'performance' => $this->performance_service,
            'validation' => $this->validation_service,
            'colour' => $this->colour_service,
            'pipeline' => $this->pipeline_service,
            'cache' => $this->cache_service,
        ];
    }
}
