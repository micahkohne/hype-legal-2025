<?php

/**
 * JCOGS Image Pro - Image Processing Pipeline
 * 
 * @package    JCOGS Image Pro
 * @category   ExpressionEngine
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 2 Native Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service;

use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;
use JCOGSDesign\JCOGSImagePro\Service\Pipeline\Context;
use JCOGSDesign\JCOGSImagePro\Service\Pipeline\StageInterface;
use JCOGSDesign\JCOGSImagePro\Service\Pipeline\OutputGenerationService;

/**
 * Image Processing Pipeline
 * 
 * Modern pipeline architecture for processing images through multiple stages.
 * Provides separation of concerns, error handling, and performance monitoring.
 * 
 * Pipeline Stages (Optimized Cache-First Architecture):
 * - Initialize: Parameter validation and setup
 * - Fast Cache Check: Pre-pipeline cache lookup (fast path - replicates Legacy cache-first approach)
 * - Load Source: Image loading and initial validation (skipped if cache hit)
 * - Process Image: Resizing, cropping, quality adjustments (skipped if cache hit)  
 * - Handle Cache: Cache storage and retrieval (skipped if cache hit)
 * - Generate Output: Final output generation (always runs)
 * 
 * PERFORMANCE OPTIMIZATION: EarlyCacheCheckStage has been removed to eliminate redundant
 * cache operations. All cache checking is now consolidated in the fast cache path for
 * better performance, reducing cache lookups from 7.4 per tag to 1-2 maximum.
 * 
 * @package    JCOGS Image Pro
 * @subpackage Service
 * @since      2.0.0-alpha1
 */
class ImageProcessingPipeline 
{
    /**
     * @var int Static instance counter for all tag types in current template processing
     */
    private static int $instance_counter = 0;
    
    /**
     * @var object Static cache for cache management service (shared across all instances)
     */
    private static $global_cache_service = null;
    
    /**
     * @var object Static cache for filesystem service (shared across all instances)
     */
    private static $global_filesystem_service = null;
    
    /**
     * @var array Static cache for settings (shared across all instances)
     */
    private static array $global_settings_cache = [];
    
    /**
     * @var array Static cache for cache keys (memoization for repeated lookups)
     */
    private static array $cache_key_memo = [];
    
    /**
     * @var array Static processing locks to prevent duplicate processing of identical images
     */
    private static array $processing_locks = [];
    
    /**
     * @var array Static cache for filesystem adapters (shared across all instances)
     */
    private static array $global_filesystem_cache = [];
    
    /**
     * @var array Static cache for adapter URLs (shared across all instances)
     */
    private static array $global_adapter_urls = [];
    
    /**
     * @var array Static cache for pipeline stages (shared across all instances for optimal performance)
     */
    private static array $global_stages_cache = [];
    
    /**
     * @var array Pipeline stage classes (for lazy loading)
     */
    private array $stage_classes = [];
    
    /**
     * @var array Pipeline stages
     */
    private array $stages = [];
    
    /**
     * @var object Utilities instance for debugging
     */
    private $utilities_service;
    
    /**
     * @var bool Enable performance monitoring
     */
    private bool $performance_monitoring = false;
    
    /**
     * @var object Cached cache management service instance
     */
    private $cache_service = null;
    
    /**
     * Shared services for optimal performance - following ServiceCache pattern
     */
    private $settings_service;
    private $filesystem_service;
    // Note: lazy_loading_service accessed via helper method to prevent circular dependency
    
    /**
     * Constructor
     * 
     * @param string|null $connection_name Specific connection to use for this pipeline instance
     * @param object $utilities Utilities instance for debugging
     */
    public function __construct(?string $connection_name = null, $utilities_service = null) 
    {
        $this->utilities_service = $utilities_service;
        
        // Initialize shared services for optimal performance
        $this->settings_service = ServiceCache::settings();
        $this->filesystem_service = ServiceCache::filesystem();
        // Note: lazy_loading_service accessed via helper method to prevent circular dependency
        
        $this->_initialize_stages();
        
        // Warm up services with the specific connection for this pipeline instance
        // This ensures the filesystem adapter for the requested connection is cached immediately
        $target_connection = $connection_name ?? $this->_getDefaultConnectionForPipeline();
        $this->_warm_up_services($target_connection);
    }
    
    /**
     * Add custom processing stage
     * 
     * Allows extension of the pipeline with custom stages for specialized processing.
     * 
     * @param string $name Stage name
     * @param StageInterface $stage Stage implementation
     * @param string|null $after Insert after this stage (null = append)
     */
    public function add_stage(string $name, StageInterface $stage, ?string $after = null): void 
    {
        if ($after === null) {
            $this->stages[$name] = $stage;
        } else {
            $new_stages = [];
            foreach ($this->stages as $stage_name => $existing_stage) {
                $new_stages[$stage_name] = $existing_stage;
                if ($stage_name === $after) {
                    $new_stages[$name] = $stage;
                }
            }
            $this->stages = $new_stages;
        }
    }

    /**
     * Get lazy loading service (lazy initialization to prevent circular dependency)
     * 
     * @return mixed LazyLoadingService instance
     */
    private function getLazyLoadingService()
    {
        return ServiceCache::lazy_loading();
    }
    
    /**
     * Clear all cached filesystem adapters
     * 
     * Useful for cache management operations that need to ensure
     * all adapters are fresh.
     */
    public static function clear_filesystem_cache(): void 
    {
        self::$global_filesystem_cache = [];
        self::$global_adapter_urls = [];
    }
    
    /**
     * Clear all static caches and reset state (useful for testing or new requests)
     */
    public static function clear_static_caches(): void 
    {
        self::$instance_counter = 0;
        self::$global_cache_service = null;
        self::$global_filesystem_service = null;
        self::$global_filesystem_service = null;
        self::$global_settings_cache = [];
        self::$cache_key_memo = [];
        self::$processing_locks = [];
        self::$global_filesystem_cache = [];
        self::$global_adapter_urls = [];
        self::$global_stages_cache = [];
    }
    
    /**
     * Evict cached filesystem adapter for connection changes
     * 
     * This method implements cache eviction when named connections are updated
     * to ensure stale adapters don't persist in static cache.
     * 
     * @param string $connection_name Connection name to evict from cache
     */
    public static function evict_cached_filesystem(string $connection_name): void 
    {
        unset(self::$global_filesystem_cache[$connection_name]);
        unset(self::$global_adapter_urls[$connection_name]);
    }
    
    /**
     * Get cached filesystem adapter (replicates Legacy static filesystem caching)
     * Updated for named connections but maintains same cache key structure for performance
     * 
     * @param string $connection_name Connection name (named connection or legacy adapter name)
     * @return \League\Flysystem\Filesystem|null Cached filesystem or null if not cached
     */
    public static function get_cached_filesystem(string $connection_name): ?\League\Flysystem\Filesystem 
    {
        return self::$global_filesystem_cache[$connection_name] ?? null;
    }
    
    /**
     * Get the current instance counter value
     */
    public static function get_instance_counter(): int 
    {
        return self::$instance_counter;
    }
    
    /**
     * Get current pipeline stages
     * 
     * @return array
     */
    public function get_stages(): array 
    {
        return array_keys($this->stage_classes);
    }
    
    /**
     * Process image through the pipeline
     * 
     * Main entry point for image processing. Executes all pipeline stages
     * in sequence, with comprehensive error handling and performance monitoring.
     * 
     * @param array $tag_params Template tag parameters
     * @param string|null $tag_data Template tag data (for pair tags)
     * @return array Processing result with output and metadata
     * @throws \Exception If critical processing error occurs
     */
    public function process(array $tag_params, ?string $tag_data = null, bool $palette_mode = false): array 
    {
        $start_time = microtime(true);
        
        // Increment static instance counter for this processing event
        self::$instance_counter++;
        $current_instance = self::$instance_counter;
        
        // Set debug mode on utilities service based on tag parameters
        if ($this->utilities_service) {
            $this->utilities_service->set_debug_mode($tag_params);
            // Add opening debug message with proper instance numbering
            $this->utilities_service->debug_message(sprintf('Image processing starting for image #%d', $current_instance));
        }
        
        // Fast path for cache hits - bypass full pipeline if possible
        $fast_cache_result = $this->_try_fast_cache_path($tag_params, $tag_data, $current_instance, $start_time);
        if ($fast_cache_result !== null) {
            return $fast_cache_result;
        }
        
        $this->utilities_service->debug_log('debug_pipeline_starting');
        
        // Create processing context
        $context = new Context($tag_params, $tag_data, ServiceCache::cache_key_generator());
        
        // ENHANCEMENT: Resolve and store connection name in metadata for consistent pipeline access
        $resolved_connection_name = $context->get_save_to_connection() ?? $this->_getDefaultConnectionForPipeline();
        $context->set_metadata('resolved_connection_name', $resolved_connection_name);
        
        $current_stage = 'initialization'; // Initialize for error reporting
        
        try {
            // Execute pipeline stages sequentially
            foreach (array_keys($this->stage_classes) as $stage_name) {
                $current_stage = $stage_name;
                $stage_start = microtime(true);
                
                $this->utilities_service->debug_log('debug_pipeline_stage_executing', $stage_name);
                
                // Get stage instance (lazy loaded)
                $stage = $this->_get_stage($stage_name);
                
                // Execute stage
                $stage->execute($context);
                
                // Always track performance for cache hit analysis
                $stage_time = microtime(true) - $stage_start;
                $context->add_performance_metric($stage_name, $stage_time);
                
                // Debug stage timing for performance analysis (detailed level only)
                if ($this->utilities_service) {
                    $this->utilities_service->debug_message(sprintf('Pipeline stage %s completed in %s seconds', $stage_name, number_format($stage_time, 4)), [], false, 'detailed');
                }
                
                // Check for critical errors
                if ($context->has_critical_error()) {
                    throw new \Exception($context->get_error_message());
                }
                
                // Check for palette mode early exit after loading source
                if ($palette_mode && $stage_name === 'load_source' && $context->get_source_image()) {
                    // For palette mode, stop after loading source image
                    $this->utilities_service->debug_log('debug_pipeline_palette_mode_exit');
                    return [
                        'success' => true,
                        'source_image' => $context->get_source_image(),
                        'pipeline_time' => microtime(true) - $start_time
                    ];
                }
                
                // Check for early exit conditions (e.g., cache hit)
                if ($context->should_exit_early()) {
                    $exit_reason = $context->get_metadata_value('exit_reason', 'unknown');
                    $this->utilities_service->debug_log('debug_pipeline_stage_skipped', $exit_reason);
                    
                    // For cache hits, skip to output generation stage instead of exiting completely
                    if ($exit_reason === 'early_cache_hit') {
                        // Skip all remaining stages except output generation
                        break;
                    } else {
                        // For other early exit reasons, exit completely
                        break;
                    }
                }
                
                // Check if context indicates we should stop
                if ($context->has_errors()) {
                    if ($this->utilities_service) {
                        $this->utilities_service->debug_message(
                            lang('jcogs_img_pro_pipeline_error'),
                            [$context->get_error_message()]
                        );
                    }
                    break;
                }
                
            }
            
            // For early cache hits, always execute output generation stage
            if ($context->should_exit_early() && $context->get_metadata_value('exit_reason') === 'early_cache_hit') {
                // Execute output stage specifically for cache hits
                $output_stage = $this->_get_stage('output');
                $stage_start = microtime(true);
                
                $this->utilities_service->debug_log('debug_pipeline_stage_executing', 'output');
                
                $output_stage->execute($context);
                    
                if ($this->performance_monitoring) {
                    $stage_time = microtime(true) - $stage_start;
                    $context->add_performance_metric('output', $stage_time);
                }
            }
            
            // Finalize processing
            $total_time = microtime(true) - $start_time;
            $context->add_performance_metric('total_processing', $total_time);
            
            // Detailed debug message for pipeline timing (avoiding duplicate with Tag layer timing)
            $this->utilities_service->debug_message(sprintf('Generation completed for image #%d in %s seconds', $current_instance, number_format($total_time, 3)), [], false, 'detailed');
            
            $this->utilities_service->debug_log('debug_pipeline_completed');
            
            return $this->_build_success_response($context);
            
        } catch (\Throwable $e) {
            $this->utilities_service->debug_log('debug_pipeline_error', $current_stage, $e->getMessage());
            
            // Handle processing errors gracefully
            return $this->_build_error_response($e, $context);
        }
    }
    
    /**
     * Reset the static instance counter (useful for testing or new template processing)
     */
    public static function reset_instance_counter(): void 
    {
        self::$instance_counter = 0;
    }
    
    /**
     * Set cached filesystem adapter (replicates Legacy static filesystem caching)
     * Updated for named connections but maintains same cache key structure for performance
     * 
     * @param string $connection_name Connection name (named connection or legacy adapter name)
     * @param \League\Flysystem\Filesystem $filesystem Filesystem instance to cache
     * @param string|null $adapter_url URL for the adapter (optional)
     */
    public static function set_cached_filesystem(string $connection_name, \League\Flysystem\Filesystem $filesystem, ?string $adapter_url = null): void 
    {
        if(!isset(self::$global_filesystem_cache[$connection_name])) {
            self::$global_filesystem_cache[$connection_name] = $filesystem;
        }
        if ($adapter_url !== null) {
            self::$global_adapter_urls[$connection_name] = $adapter_url;
        }
    }
    
    /**
     * Enable/disable performance monitoring
     * 
     * @param bool $enabled
     */
    public function set_performance_monitoring(bool $enabled): void 
    {
        $this->performance_monitoring = $enabled;
    }
    
    /**
     * Check if all responsive variants are cached
     * 
     * Verifies that all required responsive image variants exist in cache
     * before allowing fast path to proceed.
     * 
     * @param array $tag_params Tag parameters
     * @param string $main_cache_path Main image cache path
     * @return bool True if all variants are cached
     */
    private function _all_responsive_variants_cached(array $tag_params, string $main_cache_path): bool
    {
        try {
            // Get responsive service to generate variant info
            $responsive_service = ee('jcogs_img_pro:ResponsiveImageService');
            
            // Create minimal context for variant generation
            $context = new Context($tag_params, $tag_params['tagdata'] ?? null, ServiceCache::cache_key_generator());
            
            // Get expected dimensions (estimate from parameters or cache)
            $base_width = (int)($tag_params['width'] ?? 0);
            $max_width = $base_width; // Conservative estimate
            
            // If we can't determine dimensions, assume variants might be missing
            if ($base_width <= 0) {
                return false;
            }
            
            // Generate variant information
            $variants = $responsive_service->generate_variant_info(
                $context,
                $base_width,
                $max_width,
                false // Don't allow scaling larger than original
            );
            
            // If no variants needed, responsive caching is complete
            if (empty($variants)) {
                return true;
            }
            
            // Check each variant exists in cache
            $cache_service = self::$global_cache_service ?? ServiceCache::cache();
            $main_cache_dir = dirname($main_cache_path) . '/';
            $main_filename_base = pathinfo($main_cache_path, PATHINFO_FILENAME);
            $main_extension = pathinfo($main_cache_path, PATHINFO_EXTENSION);
            
            foreach ($variants as $variant) {
                $variant_width = $variant['width'];
                $variant_filename = $main_filename_base . '_' . $variant_width . 'w.' . $main_extension;
                $variant_path = $main_cache_dir . $variant_filename;
                
                // Check if this variant is cached and valid
                if (!$cache_service->is_image_in_cache($variant_path)) {
                    return false; // Missing or expired variant
                }
            }
            
            // All variants are cached and valid
            return true;
            
        } catch (\Exception $e) {
            // If variant checking fails, be conservative and skip fast path
            return false;
        }
    }
    
    /**
     * Apply lazy loading filter for fast path
     * 
     * @param \Imagine\Image\ImageInterface $image Source image
     * @param string $filter_name Filter name
     * @return \Imagine\Image\ImageInterface|null Filtered image
     */
    private function _apply_fast_lazy_filter(\Imagine\Image\ImageInterface $image, string $filter_name): ?\Imagine\Image\ImageInterface
    {
        try {
            switch ($filter_name) {
                case 'lqip':
                    $filter = new \JCOGSDesign\JCOGSImagePro\Filters\Lqip();
                    return $filter->apply($image, []);
                    
                case 'dominant_color':
                    $filter = new \JCOGSDesign\JCOGSImagePro\Filters\DominantColor();
                    return $filter->apply($image, []);
                    
                default:
                    return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Build error processing response
     * 
     * @param \Throwable $e Exception that occurred
     * @param Context $context Processing context
     * @return array Error response
     */
    private function _build_error_response(\Throwable $e, Context $context): array 
    {
        // Log error for debugging using standardized approach
        if ($this->utilities_service) {
            $this->utilities_service->debug_log('JCOGS Image Pro pipeline error: %s', $e->getMessage());
        }
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'output' => '', // Return empty output for template
            'performance' => $context->get_performance_metrics(),
            'metadata' => $context->get_metadata()
        ];
    }
    
    /**
     * Build successful processing response
     * 
     * @param Context $context Processing context
     * @return array Success response
     */
    private function _build_success_response(Context $context): array 
    {
        return [
            'success' => true,
            'output' => $context->get_output(),
            'cache_key' => $context->get_cache_key(),
            'performance' => $context->get_performance_metrics(),
            'metadata' => $context->get_metadata()
        ];
    }
    
    /**
     * Create a minimal Context object for fast output generation
     * 
     * @param string $cache_path Cache file path
     * @param array $tag_params Tag parameters
     * @return Context Minimal context object
     */
    private function _create_fast_context(string $cache_path, array $tag_params): Context
    {
        // Create a new Context instance
        $context = new Context($tag_params, $tag_params['tagdata'] ?? null, ServiceCache::cache_key_generator());
        
        // ENHANCEMENT: Resolve and store connection name in metadata for consistency
        $resolved_connection_name = $context->get_save_to_connection() ?? $this->_getDefaultConnectionForPipeline();
        $context->set_metadata('resolved_connection_name', $resolved_connection_name);
        
        // Set essential metadata for output generation
        // Ensure cache_url has proper leading slash for web URLs
        $cache_url = '/' . ltrim($cache_path, '/');
        $context->set_metadata('cache_url', $cache_url);
        $context->set_metadata('using_cache_copy', true);
        $context->set_flag('using_cache_copy', true);
        $context->set_flag('early_cache_hit', true);
        
        // Try to get image dimensions from cache log or estimate from parameters
        $dimensions = $this->_get_cached_image_dimensions($cache_path, $tag_params);
        if ($dimensions) {
            $context->set_metadata('final_width', $dimensions['width']);
            $context->set_metadata('final_height', $dimensions['height']);
            $context->set_metadata('original_width', $dimensions['original_width'] ?? $dimensions['width']);
            $context->set_metadata('original_height', $dimensions['original_height'] ?? $dimensions['height']);
        }
        
        // Generate cache key for consistency
        $cache_key = $this->_extract_cache_key_from_path($cache_path);
        $context->set_cache_key($cache_key);
        
        return $context;
    }
    
    /**
     * Determine save format using same logic as InitializeStage
     * 
     * Replicates the save_type processing logic to ensure fast path uses
     * the same format determination as the full pipeline.
     * 
     * @param string $src Source image URL
     * @param array $tag_params Template parameters
     * @return string Processed save format
     */
    private function _determine_save_format(string $src, array $tag_params): string 
    {
        $save_type = $tag_params['save_type'] ?? self::$global_settings_cache['img_cp_default_image_format'] ?? 'source';
        
        // Parse source URL to get file extension like InitializeStage
        $parsed_url = parse_url($src);
        $file_info = $parsed_url && array_key_exists('path', $parsed_url) ? pathinfo($parsed_url['path']) : [];
        $extension = array_key_exists('extension', $file_info) ? $file_info['extension'] : 'jpg';
        
        // Process save_type to determine final format
        if ($save_type === 'source') {
            // Use source file extension
            $save_as = $extension;
        } else {
            // Use specified format
            $save_as = $save_type;
        }
        
        // If save_as is still empty, default to jpg
        if (empty($save_as)) {
            $save_as = 'jpg';
        }
        
        // Handle special cases from legacy - preserve format for special file types
        if ($extension === 'svg') {
            $save_as = 'svg';
        }
        
        // Animated GIF files must stay as GIF to preserve animation
        if ($extension === 'gif' && self::$global_settings_cache['img_cp_ignore_save_type_for_animated_gifs'] == 'y') {
            $save_as = 'gif';
        }
        
        return $save_as;
    }
    
    /**
     * Extract cache key from cache file path
     * 
     * @param string $cache_path Cache file path
     * @return string Cache key
     */
    private function _extract_cache_key_from_path(string $cache_path): string
    {
        $filename = basename($cache_path);
        return pathinfo($filename, PATHINFO_FILENAME);
    }
    
    /**
     * Generate basic fallback output when enhanced generation fails
     * 
     * @param string $cache_path Cache file path
     * @param array $tag_params Tag parameters
     * @return string Basic IMG tag HTML
     */
    private function _generate_basic_fallback_output(string $cache_path, array $tag_params): string
    {
        // Get basic parameters for output generation
        $width = $tag_params['width'] ?? '';
        $height = $tag_params['height'] ?? '';
        $alt = $tag_params['alt'] ?? '';
        $class = $tag_params['class'] ?? '';
        $id = $tag_params['id'] ?? '';
        
        // Use the cache path as the src URL (convert from local path to URL)
        $processed_url = $cache_path;
        
        // Build basic IMG tag attributes
        $attributes = ['src' => $processed_url];
        
        if (!empty($width)) $attributes['width'] = $width;
        if (!empty($height)) $attributes['height'] = $height;
        if (!empty($alt)) $attributes['alt'] = $alt;
        if (!empty($class)) $attributes['class'] = $class;
        if (!empty($id)) $attributes['id'] = $id;
        
        // Build HTML attributes string
        $attr_string = '';
        foreach ($attributes as $name => $value) {
            $attr_string .= sprintf(' %s="%s"', $name, htmlspecialchars($value, ENT_QUOTES));
        }
        
        // Return simple IMG tag
        return sprintf('<img%s />', $attr_string);
    }
    
    /**
     * Generate fast output for cache hits with full feature support
     * 
     * Enhanced version that includes lazy loading, responsive images, and proper attributes
     * to match the quality of OutputStage processing while maintaining fast performance.
     * 
     * @param string $cache_path Cache file path
     * @param array $tag_params Template parameters
     * @return string Generated HTML output
     */
    private function _generate_fast_output(string $cache_path, array $tag_params): string 
    {
        try {
            // Create a minimal Context for OutputGenerationService compatibility
            $context = $this->_create_fast_context($cache_path, $tag_params);
            
            // Check if this is a pair tag (has tagdata)
            $tag_data = $tag_params['tagdata'] ?? null;
            if (!empty($tag_data)) {
                // Handle pair tag output using OutputGenerationService
                return $this->_generate_fast_pair_output($context, $tag_data);
            }
            
            // Handle single tag (IMG) output using OutputStage for consistency
            $output_stage = $this->_get_stage('output');
            $output_stage->execute($context);
            return $context->get_output();
            
        } catch (\Exception $e) {
            // Fallback to basic output if enhanced generation fails
            $this->utilities_service->debug_message("Fast output generation failed, using fallback: " . $e->getMessage());
            return $this->_generate_basic_fallback_output($cache_path, $tag_params);
        }
    }
    
    /**
     * Generate fast pair tag output with template variables
     * 
     * @param Context $context Processing context
     * @param string $tag_data Template content
     * @return string Processed template content
     */
    private function _generate_fast_pair_output(Context $context, string $tag_data): string
    {
        // Generate template variables like OutputStage does
        $cache_url = $context->get_metadata_value('cache_url', '');
        $cache_local_path = $cache_url;
        $cache_full_url = ee()->config->item('base_url') . ltrim($cache_url, '/');
        
        $variables = [
            'made' => $cache_local_path,
            'made_url' => $cache_full_url,
            'url' => $cache_local_path,
            'src' => $cache_local_path,
            'width' => $context->get_metadata_value('final_width', ''),
            'height' => $context->get_metadata_value('final_height', ''),
            'original_width' => $context->get_metadata_value('original_width', ''),
            'original_height' => $context->get_metadata_value('original_height', ''),
            'cache_key' => $context->get_cache_key(),
            'using_cache' => 'yes' // Fast path always uses cache
        ];
        
        // Add lazy loading variables if enabled
        if ($this->getLazyLoadingService()->is_lazy_loading_enabled($context)) {
            $mode = $this->getLazyLoadingService()->get_lazy_loading_mode($context);
            $variables['lazy_mode'] = $mode ?? 'html5';
            $variables['is_lazy'] = 'yes';
            $variables['placeholder_url'] = $this->_generate_fast_placeholder_url($context);
        } else {
            $variables['is_lazy'] = 'no';
        }
        
        // Parse template content with variables
        $output = $tag_data;
        foreach ($variables as $key => $value) {
            $output = str_replace('{' . $key . '}', $value, $output);
        }
        
        return $output;
    }
    
    /**
     * Generate placeholder URL for fast path lazy loading
     * 
     * Follows cache-first pattern: check cache_log first, then filesystem, then generate
     * 
     * @param Context $context Processing context
     * @return string Placeholder URL
     */
    private function _generate_fast_placeholder_url(Context $context): string
    {
        $mode = $this->getLazyLoadingService()->get_lazy_loading_mode($context);
        $cache_url = $context->get_metadata_value('cache_url', '');
        
        if (empty($cache_url) || empty($mode)) {
            return '';
        }
        
        // Build expected placeholder URL based on cache URL
        $cache_info = pathinfo($cache_url);
        $filter_name = str_replace('js_', '', $mode);
        $placeholder_filename = $cache_info['filename'] . '_' . $filter_name . '.' . $cache_info['extension'];
        $placeholder_url = $cache_info['dirname'] . '/' . $placeholder_filename;
        $placeholder_path = ltrim($placeholder_url, '/');
        
        // Follow cache-first pattern: check cache_log first, then filesystem fallback
        $cache_service = self::$global_cache_service ?? ServiceCache::cache();
        
        // First: Check cache_log for placeholder existence (fastest method)
        $placeholder_cache_data = $cache_service->load_cached_image_data($placeholder_path);
        if ($placeholder_cache_data && !empty($placeholder_cache_data['cache_url'])) {
            // Found in cache_log, return URL
            return $placeholder_cache_data['cache_url'];
        }
        
        // Second: Check filesystem as fallback (project rules compliance)
        if ($this->filesystem_service->exists($placeholder_path)) {
            // Exists on filesystem but not in cache_log, return URL
            return $placeholder_url;
        }
        
        // Third: Generate placeholder on-demand if not found
        try {
            $main_cache_path = ltrim($cache_url, '/');
            
            // Check main cache exists (cache-first approach)
            $main_cache_data = $cache_service->load_cached_image_data($main_cache_path);
            if (!$main_cache_data && !$this->filesystem_service->exists($main_cache_path)) {
                // Main cache doesn't exist, return fallback
                return $this->_get_fallback_data_url($filter_name);
            }
            
            // Generate placeholder from main cached image
            $imagine = new \Imagine\Gd\Imagine();
            $cached_image = $imagine->open($main_cache_path);
            
            // Apply appropriate filter
            $placeholder_image = $this->_apply_fast_lazy_filter($cached_image, $filter_name);
            
            if ($placeholder_image) {
                $quality = $filter_name === 'lqip' ? 50 : 100;
                $this->filesystem_service->writeImage($placeholder_image, $placeholder_path, ['quality' => $quality]);
                return $placeholder_url;
            }
        } catch (\Exception $e) {
            // If generation fails, return fallback data URL
            return $this->_get_fallback_data_url($filter_name);
        }
        
        // Fallback if all else fails
        return $this->_get_fallback_data_url($filter_name);
    }
    
    /**
     * Get cached image dimensions from cache log with intelligent fallback
     * 
     * @param string $cache_path Cache file path
     * @param array $tag_params Tag parameters
     * @return array|null Dimensions array or null
     */
    private function _get_cached_image_dimensions(string $cache_path, array $tag_params): ?array
    {
        try {
            // Try to get dimensions from cache log first
            $cache_service = self::$global_cache_service ?? ServiceCache::cache();
            $cache_vars = $cache_service->get_variables_from_cache_log($cache_path);
            
            if ($cache_vars) {
                // First try the final processed dimensions from template variables (stored in 'values' column)
                if (isset($cache_vars['width'], $cache_vars['height']) && 
                    (int)$cache_vars['width'] > 0 && (int)$cache_vars['height'] > 0) {
                    return [
                        'width' => (int)$cache_vars['width'],
                        'height' => (int)$cache_vars['height'],
                        'original_width' => (int)($cache_vars['original_width'] ?? $cache_vars['width']),
                        'original_height' => (int)($cache_vars['original_height'] ?? $cache_vars['height'])
                    ];
                }
                
                // Fallback: try target dimensions from stats if available
                if (isset($cache_vars['target_width'], $cache_vars['target_height']) && 
                    (int)$cache_vars['target_width'] > 0 && (int)$cache_vars['target_height'] > 0) {
                    return [
                        'width' => (int)$cache_vars['target_width'],
                        'height' => (int)$cache_vars['target_height'],
                        'original_width' => (int)($cache_vars['original_width'] ?? $cache_vars['target_width']),
                        'original_height' => (int)($cache_vars['original_height'] ?? $cache_vars['target_height'])
                    ];
                }
            }
        } catch (\Exception $e) {
            // Continue to fallback
        }
        
        // Fallback: Try to read dimensions from the actual cached file
        try {
            $filesystem_service = self::$global_filesystem_service ?? ServiceCache::filesystem();
            $image_size = $filesystem_service->getimagesize($cache_path);
            
            if ($image_size !== false && isset($image_size[0], $image_size[1])) {
                return [
                    'width' => (int)$image_size[0],
                    'height' => (int)$image_size[1],
                    'original_width' => (int)$image_size[0],
                    'original_height' => (int)$image_size[1]
                ];
            }
        } catch (\Exception $e) {
            // Continue to final fallback
        }
        
        // Final fallback: For cached images, avoid using tag parameters as they may not reflect 
        // the actual processed dimensions. Return null to let normal dimension detection work.
        return null;
    }
    
    /**
     * Get the cache path for a named connection
     * 
     * Uses the same logic as the Caching page to determine cache paths for different
     * connection types. This ensures consistency between cache path resolution.
     * 
     * @param array $connection Connection configuration
     * @return string Cache path
     */
    private function _get_connection_cache_path(array $connection): string
    {
        $config = $connection['config'] ?? [];
        $type = $connection['type'] ?? 'unknown';
        
        switch ($type) {
            case 'local':
                return $config['cache_directory'] ?? 'images/jcogs_img_pro/cache';
            case 's3':
                $bucket = $config['bucket'] ?? 'bucket';
                $path = $config['server_path'] ?? '';
                return $bucket . (!empty($path) ? '/' . trim($path, '/') : '');
            case 'r2':
                $bucket = $config['bucket'] ?? 'bucket';
                $path = $config['server_path'] ?? '';
                return $bucket . (!empty($path) ? '/' . trim($path, '/') : '');
            case 'dospaces':
                $space = $config['space'] ?? 'space';
                $path = $config['server_path'] ?? '';
                return $space . (!empty($path) ? '/' . trim($path, '/') : '');
            default:
                return 'images/jcogs_img_pro/cache';
        }
    }
    
    /**
     * Generate enhanced responsive data for fast path
     * 
     * Creates proper srcset and sizes attributes by reading cached variant files
     * 
     * @param Context $context Processing context
     * @return array Responsive data with srcset and sizes
     */
    
    /**
     * Get fallback data URL for lazy loading
     * 
     * @param string $filter_name Filter name
     * @return string Fallback data URL
     */
    private function _get_fallback_data_url(string $filter_name): string
    {
        switch ($filter_name) {
            case 'lqip':
                return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
                
            case 'dominant_color':
                return 'data:image/svg+xml;base64,' . base64_encode(
                    '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><rect width="100%" height="100%" fill="#f0f0f0"/></svg>'
                );
                
            default:
                return '';
        }
    }
    
    /**
     * Get stage instance with lazy loading and reuse
     * 
     * Creates stage instances only when needed and reuses them for
     * subsequent calls, dramatically improving performance.
     * 
     * @param string $stage_name Stage identifier
     * @return StageInterface Stage instance
     * @throws \Exception If stage not found
     */
    private function _get_stage(string $stage_name): StageInterface 
    {
        // Check if stage class exists
        if (!isset($this->stage_classes[$stage_name])) {
            throw new \Exception("Unknown pipeline stage: {$stage_name}");
        }
        
        // Check static cache first (shared across all pipeline instances)
        if (!isset(self::$global_stages_cache[$stage_name])) {
            $stage_class = $this->stage_classes[$stage_name];
            
            // Instantiate stage with performance optimization
            switch ($stage_name) {
                case 'load_source':
                    // LoadSourceStage can accept optional filesystem service
                    self::$global_stages_cache[$stage_name] = new $stage_class();
                    break;
                    
                default:
                    // Most stages use default constructor
                    self::$global_stages_cache[$stage_name] = new $stage_class();
                    break;
            }
        }
        
        return self::$global_stages_cache[$stage_name];
    }
    
    /**
     * Get default connection name for pipeline operations (explicit architecture)
     * 
     * @return string Default connection name
     */
    private function _getDefaultConnectionForPipeline(): string
    {
        try {
            // Try to get default named connection first
            $default_connection = $this->settings_service->getDefaultConnection(true); // with decryption
            
            if ($default_connection && !empty($default_connection['name'])) {
                return $default_connection['name'];
            }
        } catch (\Exception $e) {
            // Log and continue with fallback
        }
        
        // Fall back to legacy adapter settings
        $legacy_adapter = $this->settings_service->get('img_cp_flysystem_adapter', 'local');
        
        // Ensure we have a valid adapter name
        if (empty($legacy_adapter)) {
            $legacy_adapter = 'local';
        }
        
        return 'legacy_' . $legacy_adapter;
    }
    
    /**
     * Initialize pipeline stages with lazy loading for optimal performance
     * 
     * Sets up stage classes but doesn't instantiate them until needed,
     * reducing memory usage and initialization overhead.
     */
    private function _initialize_stages(): void 
    {
        // Define stage classes in processing order
        // OPTIMIZATION: Removed EarlyCacheCheckStage to eliminate redundant cache checking
        // All cache logic is now consolidated in the fast cache path for better performance
        $this->stage_classes = [
            'initialize' => \JCOGSDesign\JCOGSImagePro\Service\Pipeline\InitializeStage::class,
            'load_source' => \JCOGSDesign\JCOGSImagePro\Service\Pipeline\LoadSourceStage::class,
            'process_image' => \JCOGSDesign\JCOGSImagePro\Service\Pipeline\ProcessImageStage::class,
            'cache' => \JCOGSDesign\JCOGSImagePro\Service\Pipeline\CacheStage::class,
            'output' => \JCOGSDesign\JCOGSImagePro\Service\Pipeline\OutputStage::class,
        ];
    }
    
    /**
     * Check if responsive images are enabled (fast path version with tag params)
     * 
     * @param array $tag_params Tag parameters
     * @return bool True if responsive images are enabled
     */
    private function _is_responsive_enabled_fast(array $tag_params): bool
    {
        $srcset_param = $tag_params['srcset'] ?? '';
        return !empty($srcset_param);
    }
    
    /**
     * Resolve cache directory for fast path processing using named connections
     * 
     * Updated to work with the named connections system. Respects the connection
     * parameter and uses the appropriate cache path from the named connection config.
     * 
     * @param array $tag_params Tag parameters
     * @return string Resolved cache directory path with trailing slash
     */
    private function _resolve_cache_directory_for_fast_path(array $tag_params): string
    {
        // Check for explicit cache_dir parameter first
        $explicit_cache_dir = $tag_params['cache_dir'] ?? '';
        if (!empty($explicit_cache_dir)) {
            return trim($explicit_cache_dir, '/') . '/';
        }
        
        // Determine which connection to use - either from 'connection' parameter or default
        $connection_name = $tag_params['connection'] ?? null;
        
        // If no connection specified, get the default connection
        if (empty($connection_name)) {
            try {
                $connection_name = $this->settings_service->get_default_connection_name();
            } catch (\Exception $e) {
                // Fallback to legacy naming if named connections not available yet
                $legacy_adapter = $this->settings_service->get('img_cp_flysystem_adapter', 'local');
                $connection_name = 'legacy_' . $legacy_adapter;
            }
        }
        
        // Get the named connection configuration
        try {
            $connection = $this->settings_service->getNamedConnection($connection_name);
            
            if ($connection) {
                // Use the same logic as Caching page to get cache path
                $cache_path = $this->_get_connection_cache_path($connection);
                return trim($cache_path, '/') . '/';
            }
        } catch (\Exception $e) {
            // Named connection lookup failed, log and return fallback
            if ($this->utilities_service) {
                $this->utilities_service->debug_log('Named connection lookup failed: %s', $e->getMessage());
            }
        }
        
        // If we reach here, named connection not found - return default cache path
        return 'images/jcogs_img_pro/cache/';
    }
    
    /**
     * Resolve source with fallback chain for fast path processing
     * 
     * Mimics the LoadSourceStage fallback logic but lightweight for cache checking.
     * Returns the resolved source URL that should be used for cache key generation.
     * 
     * @param array $tag_params Tag parameters
     * @return string|null Resolved source URL or null if no valid source
     */
    private function _resolve_source_for_fast_path(array $tag_params): ?string
    {
        // Try primary source first
        $primary_src = $tag_params['src'] ?? '';
        if (!empty($primary_src)) {
            return $primary_src;
        }
        
        // Primary src is empty, try fallback_src parameter
        $fallback_src = $tag_params['fallback_src'] ?? '';
        if (!empty($fallback_src)) {
            return $fallback_src;
        }
        
        // Try system default fallback based on settings
        $settings = $this->settings_service->get_all();
        $fallback_setting = $settings['img_cp_enable_default_fallback_image'] ?? 'n';
        
        switch ($fallback_setting) {
            case 'yc':
                // Color fill mode - fast path not applicable (needs full pipeline)
                return null;
                
            case 'yl':
                // Local image fallback
                $local_fallback = $settings['img_cp_default_fallback_image_local'] ?? '';
                return !empty($local_fallback) ? $local_fallback : null;
                
            case 'yr':
                // Remote image fallback  
                $remote_fallback = $settings['img_cp_default_fallback_image_remote'] ?? '';
                return !empty($remote_fallback) ? $remote_fallback : null;
                
            default:
                // No system fallback enabled
                return null;
        }
    }
    
    /**
     * Try fast cache path for cache hits
     * 
     * Attempts to quickly resolve cache hits without full pipeline overhead.
     * Returns null if fast path not possible, otherwise returns complete result.
     * 
     * @param array $tag_params Template parameters
     * @param string|null $tag_data Template data
     * @param int $current_instance Instance number
     * @param float $start_time Start time
     * @return array|null Result or null if fast path not possible
     */
    private function _try_fast_cache_path(array $tag_params, ?string $tag_data, int $current_instance, float $start_time): ?array 
    {
        // Skip fast path if caching is disabled
        $cache_param = $tag_params['cache'] ?? '';
        if ($cache_param == '0' || $cache_param === 0) {
            return null;
        }
        
        try {
            // Resolve source with fallback chain BEFORE cache checking
            // This ensures fallback sources can benefit from fast cache path
            $src = $this->_resolve_source_for_fast_path($tag_params);
            if (empty($src)) {
                // No valid source found (including fallbacks), skip fast path
                return null;
            }
            
            // Process save_type to determine actual save_as format (same logic as InitializeStage)
            $actual_save_as = $this->_determine_save_format($src, $tag_params);
            
            // Update tag_params with the actual save_as for cache key generation
            $tag_params_with_save_as = $tag_params;
            $tag_params_with_save_as['save_as'] = $actual_save_as;
            $tag_params_with_save_as['save_type'] = $actual_save_as;
            $tag_params_with_save_as['src'] = $src; // Use resolved source for cache key
            
            // Create cache key with memoization for repeated lookups (using correct Tiger hash)
            // The CacheKeyGenerator automatically filters out non-transformational parameters
            // including ACT-specific parameters like 'act_what', 'act_path', 'act_packet', etc.
            $memo_key = hash('tiger160,3', $src . serialize($tag_params_with_save_as));
            if (!isset(self::$cache_key_memo[$memo_key])) {
                self::$cache_key_memo[$memo_key] = ServiceCache::cache_key_generator()->generate_cache_key($src, $tag_params_with_save_as);
            }
            $cache_key = self::$cache_key_memo[$memo_key];
            
            // Build cache path respecting filesystem adapter and default cache directory settings
            $cache_dir = $this->_resolve_cache_directory_for_fast_path($tag_params);
            $cache_path = ltrim($cache_dir . $cache_key . '.' . $actual_save_as, '/');
            
            // Get cache management service (ensure global service is available for optimal performance)
            if (self::$global_cache_service === null) {
                self::$global_cache_service = ServiceCache::cache();
            }
            $cache_service = self::$global_cache_service;
            
            // Determine which connection will be used for saving (same logic as full pipeline)
            $connection_name = $tag_params['connection'] ?? $this->_getDefaultConnectionForPipeline();
            
            // CRITICAL: Ensure cache preload strategy is initialized before fast cache checks
            // This restores the Legacy behavior where static cache is populated on first use
            $cache_service->preload_cache_log_index($connection_name);
            
            // Check if cache exists and is valid (cache duration automatically extracted from filename)
            if ($cache_service->is_image_in_cache($cache_path, null, $connection_name)) {
                // For responsive images, also verify all variants are cached
                if ($this->_is_responsive_enabled_fast($tag_params)) {
                    if (!$this->_all_responsive_variants_cached($tag_params, $cache_path)) {
                        // Main image cached but responsive variants missing - use full pipeline
                        if ($this->utilities_service) {
                            $this->utilities_service->debug_message('Main image cached but responsive variants missing - full pipeline required');
                        }
                        return null;
                    }
                }
                
                // Cache hit! Generate output directly with minimal overhead
                // (All logging consolidated into completion message for cleaner output)
                
                // Lightweight output generation - bypass full OutputStage overhead
                $output = $this->_generate_fast_output($cache_path, $tag_params);
                
                // Calculate timing and log comprehensive success message
                $total_time = microtime(true) - $start_time;
                
                if ($this->utilities_service) {
                    $cache_filename = basename($cache_path);
                    $variant_info = $this->_is_responsive_enabled_fast($tag_params) ? ' (responsive variants verified)' : '';
                    $this->utilities_service->debug_message(sprintf('Cache hit: Found %s%s - lookup for took %s seconds', 
                        $cache_filename, $variant_info, number_format($total_time, 4)));
                }
                
                return [
                    'success' => true,
                    'output' => $output,
                    'cache_hit' => true,
                    'fast_path' => true,
                    'processing_time' => $total_time
                ];
            } else {
                // Check if cache entry exists (regardless of expiration) to provide accurate message
                $cache_data = $cache_service->load_cached_image_data($cache_path);
                if ($cache_data) {
                    // Cache entry exists but failed is_image_in_cache() check, so it's expired
                    if ($this->utilities_service) {
                        $this->utilities_service->debug_message('Cached image found but expired - reprocessing required');
                    }
                } else {
                    // No cache entry found at all
                    if ($this->utilities_service) {
                        $this->utilities_service->debug_message('No cached version found - processing required');
                    }
                }
            }
            
        } catch (\Throwable $e) {
            // Fast path failed, fall back to full pipeline
            if ($this->utilities_service) {
                $this->utilities_service->debug_message("Fast path failed with exception: " . $e->getMessage() . " - falling back to full pipeline");
            }
            return null;
        }
        
        // No cache hit or fast path not possible
        return null;
    }
    
    /**
     * Warm up services to eliminate first-call overhead
     * 
     * Pre-loads commonly used services and settings into static caches
     * to optimize performance for subsequent calls. Now targets the specific
     * connection that will be used by this pipeline instance.
     * 
     * @param string $connection_name Specific connection to warm up
     */
    private function _warm_up_services(string $connection_name): void 
    {
        try {
            // Warm up cache management service
            if (self::$global_cache_service === null) {
                self::$global_cache_service = ServiceCache::cache();
            }
            
            // Warm up settings cache using Pro Settings
            if (empty(self::$global_settings_cache)) {
                self::$global_settings_cache = [
                    'img_cp_default_image_format' => $this->settings_service->get('img_cp_default_image_format', 'source'),
                    'img_cp_ignore_save_type_for_animated_gifs' => $this->settings_service->get('img_cp_ignore_save_type_for_animated_gifs', 'n')
                ];
            }
            
            // Warm up filesystem adapter cache for the specific connection
            if (!isset(self::$global_filesystem_cache[$connection_name])) {
                try {
                    // Debug: Log warming attempt
                    if ($this->utilities_service) {
                        $this->utilities_service->debug_log('Warming filesystem adapter cache for connection: %s', $connection_name);
                    }
                    
                    // Pre-initialize the filesystem adapter for this specific connection
                    self::set_cached_filesystem(
                        $connection_name,
                        $this->filesystem_service->createFilesystemAdapter($connection_name)
                    );
                    
                    // Cache adapter URL for connection
                    $adapter_url = $this->filesystem_service->getAdapterUrl($connection_name);
                    if ($adapter_url) {
                        self::$global_adapter_urls[$connection_name] = rtrim($adapter_url, '/') . '/';
                    }
                    
                    // Debug: Log successful warming
                    if ($this->utilities_service) {
                        $this->utilities_service->debug_log('Filesystem adapter cached successfully: %s', $connection_name);
                    }
                } catch (\Exception $e) {
                    // Filesystem warming failed, but don't block processing
                    if ($this->utilities_service) {
                        $this->utilities_service->debug_log('Filesystem warming failed: %s', $e->getMessage());
                    }
                }
            } else {
                // Debug: Log that adapter is already cached
                if ($this->utilities_service) {
                    $this->utilities_service->debug_log('Filesystem adapter already cached for connection: %s', $connection_name);
                }
            }
            
            // Service warming completed for this connection
            
        } catch (\Exception $e) {
            // Service warming is optimization, don't fail if it doesn't work
            // Just continue without warming
        }
    }
    

}
