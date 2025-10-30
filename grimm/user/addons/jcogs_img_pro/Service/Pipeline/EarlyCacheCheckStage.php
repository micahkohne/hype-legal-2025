<?php

/**
 * JCOGS Image Pro - Early Cache Check Pipeline Stage
 * =================================================
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

/**
 * Early Cache Check Pipeline Stage
 * 
 * This stage implements the cache-first approach similar to Legacy addon.
 * It performs cache checking very early in the pipeline and can trigger
 * an early exit to the output generation stage if a valid cached image is found.
 * 
 * Workflow:
 * 1. Assemble information needed for processed image filename
 * 2. Work out the processed image filename (including extension)
 * 3. If cache enabled (cache parameter > 0), check cache_log table for image
 * 4. If found and fresh, verify filesystem presence
 * 5. If verified, set early exit flag and skip to output generation
 * 6. Otherwise, continue with normal processing pipeline
 * 
 * This replicates the streamlined approach from Legacy JcogsImage::initialise()
 */
class EarlyCacheCheckStage extends AbstractStage 
{
    /**
     * Constructor
     * 
     * All services are now automatically available via parent AbstractStage.
     * No need to manually instantiate common services.
     */
    public function __construct() 
    {
        parent::__construct('early_cache_check');
        // $this->cache_service is now available via parent
        // $this->filesystem_service is now available via parent
        // $this->utilities is now available via parent
    }
    
    /**
     * Process early cache check stage
     * 
     * Implements cache-first approach: check cache before any heavy processing.
     * If cached image found and valid, sets early exit flag to skip to output generation.
     * 
     * @param Context $context Processing context
     * @throws \Exception If cache operations fail
     */
    protected function process(Context $context): void 
    {
        // Check if caching is explicitly disabled (cache="0") - fast path
        $cache_param = $context->get_param('cache', '');
        if ($cache_param == '0' || $cache_param === 0) {
            return; // Continue to normal processing
        }
        
        // Get cache key - this includes filename generation
        $cache_key = $context->get_cache_key();
        
        // Get the full cache path to pass to cache management service
        $cache_path = $this->get_cache_file_path($cache_key, $context);
        
        // CRITICAL: Ensure cache preload strategy is determined before any cache lookups
        // This restores the Legacy behavior where static cache is populated on first use
        // Must happen before checking cached results to prevent stale false negatives
        $this->cache_service->preload_cache_log_index();
        
        // Generate cache key for result caching to avoid redundant cache operations
        $cache_result_key = md5($cache_path);
        
        // Check if we've already cached the result of this cache check
        $cached_result = $context->get_cached_cache_result($cache_result_key);
        if ($cached_result !== null) {
            $cache_exists = $cached_result;
        } else {
            // Check cache_log table for image metadata (cache duration automatically extracted from filename)
            $cache_exists = $this->cache_service->is_image_in_cache($cache_path);
            // Cache the result to avoid redundant operations
            $context->set_cached_cache_result($cache_result_key, $cache_exists);
        }
        
        if ($cache_exists) {
            
            // Load cached metadata and image information
            if ($this->load_cached_metadata($cache_key, $context)) {
                
                // Set flags for early exit to output generation
                $context->set_flag('using_cache_copy', true);
                $context->set_flag('early_cache_hit', true);
                $context->set_metadata('exit_reason', 'early_cache_hit');
                $context->set_exit_early(true, 'early_cache_hit');
                
                // User-friendly debug message matching legacy format
                $this->utilities_service->debug_message('Using cached copy of image so skipping processing.');
                return;
            }
        }
        
        // No cache hit - continue with normal processing
        $context->set_flag('early_cache_hit', false);
    }
    
    /**
     * Get cache file path from cache key and context
     * 
     * @param string $cache_key
     * @param Context $context
     * @return string
     */
    private function get_cache_file_path(string $cache_key, Context $context): string 
    {
        // Get cache_dir from context parameter, but use connection-based resolution as fallback
        $cache_dir = $context->get_param('cache_dir', null);
        
        if ($cache_dir === null) {
            // Use connection-based cache directory resolution instead of hardcoded fallback
            try {
                $connection_name = $this->settings_service->get_default_connection_name();
                $connection_config = $this->settings_service->getNamedFilesystemAdapters()[$connection_name] ?? null;
                
                if ($connection_config && isset($connection_config['cache_directory'])) {
                    $cache_dir = $connection_config['cache_directory'];
                } else {
                    // Fallback to Legacy IMG default, not Pro default
                    $cache_dir = '/images/jcogs_img/cache/';
                }
            } catch (\Exception $e) {
                // Last resort fallback to Legacy IMG default
                $cache_dir = '/images/jcogs_img/cache/';
            }
        }
        
        $save_as = $context->get_param('save_as', 'jpg');
        
        // Ensure cache_dir starts and ends with /
        $cache_dir = '/' . trim($cache_dir, '/') . '/';
        
        return $cache_dir . $cache_key . '.' . $save_as;
    }
    

    
    /**
     * Load cached metadata into context
     * 
     * For now, this is a simplified implementation that sets basic flags.
     * Will be enhanced when we migrate more cache management functionality.
     * 
     * @param string $cache_key
     * @param Context $context
     * @return bool
     */
    private function load_cached_metadata(string $cache_key, Context $context): bool 
    {
        try {
            // Get the cache file path for basic validation
            $cache_path = $this->get_cache_file_path($cache_key, $context);
            
            // Basic file existence check should have been done already, but double-check
            if (!$this->filesystem_service->exists($cache_path)) {
                $this->utilities_service->debug_message('debug_early_cache_metadata_file_missing', $cache_path, false, 'detailed');
                return false;
            }
            
            // Set basic metadata to indicate cache hit
            $context->set_metadata('cache_file_path', $cache_path);
            $context->set_metadata('cache_url', $cache_path); // OutputStage expects this
            $context->set_metadata('cache_source', 'early_check');
            
            // Get cached metadata from cache log
            $cache_management_service = $this->cache_service;
            
            // Use the single unified cache loading method
            $cache_data = $cache_management_service->load_cached_image_data($cache_path);
            
            if ($cache_data && !empty($cache_data['template_variables'])) {
                // Restore all cached metadata to context (includes filesize)
                $cached_metadata = $cache_data['template_variables'];
                foreach ($cached_metadata as $key => $value) {
                    $context->set_metadata($key, $value);
                }
                
                $this->utilities_service->debug_message('debug_early_cache_metadata_restored', [
                    'cache_path' => $cache_path,
                    'metadata_keys' => array_keys($cached_metadata),
                    'filesize_from_cache' => $cached_metadata['filesize'] ?? 'missing'
                ], false, 'detailed');
            } else {
                $this->utilities_service->debug_message('debug_early_cache_metadata_empty', $cache_path, false, 'detailed');
                
                // Last resort: get basic file metadata only if no cache log entry found
                try {
                    $file_size = $this->filesystem_service->getSize($cache_path);
                    $context->set_metadata('cached_filesize', $file_size);
                    $this->utilities_service->debug_log('Last resort: got cached file size from disk: %d bytes', $file_size);
                } catch (\Exception $e) {
                    // Not critical if we can't get file size
                    $this->utilities_service->debug_log('Could not get cached file size: %s', $e->getMessage());
                }
            }
            
            $this->utilities_service->debug_message('debug_early_cache_metadata_basic_loaded', [$cache_key], false, 'detailed');
            return true;
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_log('Early cache metadata load error: %s', $e->getMessage());
            return false;
        }
    }

}
