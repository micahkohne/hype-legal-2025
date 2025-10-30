<?php

/**
 * JCOGS Image Pro - Abstract Pipeline Stage
 * =========================================
 * Phase 2: Native EE7 implementation pipeline architecture
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
 * Abstract Pipeline Stage Class
 * 
 * Provides common functionality for all pipeline stages.
 * Concrete stages extend this class and implement the process() method.
 * 
 * All common services are automatically available through shared cache for optimal performance.
 */
abstract class AbstractStage implements StageInterface 
{
    /**
     * @var string Stage name
     */
    protected string $name;
    
    /**
     * @var \JCOGSDesign\JCOGSImagePro\Service\Utilities Utilities service for debug logging
     */
    protected Utilities $utilities_service;
    
    /**
     * Common services available to all pipeline stages (using shared cache for performance)
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
     * @var static Utilities Shared utilities instance across all stages for performance
     * @deprecated Use ServiceCache::utilities() instead for consistency
     */
    private static ?Utilities $shared_utilities = null;
    
    /**
     * Constructor
     * 
     * Automatically initializes all common services using shared cache for optimal performance.
     * Pipeline stages no longer need to manually instantiate these common services.
     * 
     * @param string $name Stage name
     */
    public function __construct(string $name) 
    {
        $this->name = $name;
        
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
        
        // Legacy compatibility - keep shared utilities for backward compatibility
        if (self::$shared_utilities === null) {
            self::$shared_utilities = $this->utilities_service;
        }
    }
    
    /**
     * Execute the stage
     * 
     * Template method that handles common stage execution logic.
     * Calls the concrete process() method implemented by subclasses.
     * 
     * @param Context $context Processing context
     * @throws \Exception If critical error occurs
     */
    public function execute(Context $context): void 
    {
        // Check if stage should be skipped
        if ($this->should_skip($context)) {
            return;
        }
        
        try {
            // Execute the actual processing
            $this->process($context);
            
        } catch (\Throwable $e) {
            // Add error to context
            $context->add_error(
                "Stage '{$this->name}' failed: " . $e->getMessage(), 
                true // Critical error
            );
            
            // Re-throw for pipeline to handle
            throw $e;
        }
    }
    
    /**
     * Process the stage (implemented by concrete stages)
     * 
     * @param Context $context Processing context
     * @throws \Exception If processing fails
     */
    abstract protected function process(Context $context): void;
    
    /**
     * Get stage name
     * 
     * @return string Stage identifier
     */
    public function get_name(): string 
    {
        return $this->name;
    }
    
    /**
     * Check if stage should be skipped
     * 
     * Default implementation - stages can override for custom logic.
     * 
     * @param Context $context Processing context
     * @return bool True if stage should be skipped
     */
    public function should_skip(Context $context): bool 
    {
        return false;
    }

}
