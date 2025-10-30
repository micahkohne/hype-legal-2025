<?php

/**
 * JCOGS Image Pro - Cache Pipeline Stage
 * ======================================
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

use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use JCOGSDesign\JCOGSImagePro\Service\Pipeline\ResponsiveImageService;

/**
 * Cache Pipeline Stage
 * 
 * Fourth stage of the processing pipeline. Handles cache operations
 * including checking for existing cached images and saving new ones.
 * 
 * Responsibilities:
 * - Check for existing cached images
 * - Load cached images when available
 * - Save processed images to cache
 * - Update cache metadata and logs
 * - Handle cache cleanup and optimization
 */
class CacheStage extends AbstractStage 
{
    /**
     * @var mixed Image utilities service
     */
    private $image_utilities;
    
    /**
     * @var mixed Context copy
     */
    private $context;

    private $cache_connection_name;
    
    /**
     * Constructor
     * 
     * All services are now automatically available via parent AbstractStage.
     * No need to manually instantiate services.
     */
    public function __construct() 
    {
        parent::__construct('cache');
        // Setup image utilities
        $this->image_utilities = ee('jcogs_img_pro:ImageUtilities');
        // Setup cache connection name
        $this->cache_connection_name = $this->context ? $this->context->get_metadata_value('resolved_connection_name') : null;
        $this->cache_connection_name = $this->cache_connection_name ?? $this->getDefaultConnectionForCache();
    }

    /**
     * Get lazy loading service (lazy initialization to prevent circular dependency)
     * 
     * @return mixed LazyLoadingService instance
     */
    private function getLazyLoadingService()
    {
        return \JCOGSDesign\JCOGSImagePro\Service\ServiceCache::lazy_loading();
    }
    
    /**
     * Get default connection name for cache operations
     * Provides explicit connection resolution instead of relying on deprecated getCurrentConnectionName
     * 
     * @return string Default connection name from settings
     */
    protected function getDefaultConnectionForCache(): string
    {
        $default_connection = $this->settings_service->get_default_connection_name();
        return !empty($default_connection) ? $default_connection : 'legacy_local';
    }
    
    /**
     * Process cache stage
     * 
     * @throws \Exception If cache operations fail
     */
    protected function process(Context $context): void 
    {
        // Cache context for this stage
        $this->context = $context;
        
        try {
            $this->utilities_service->debug_message('debug_cache_stage_starting');
            
            // Get connection name for named connections (supports connection parameter)
            $save_to_connection = $this->context->get_save_to_connection();
            $cache_connection = $save_to_connection ?? $this->getDefaultConnectionForCache();
        
        $cache_key = $this->context->get_cache_key();
        
        // Check if caching is explicitly disabled (cache="0") 
        $cache_param = $this->context->get_param('cache', '');
        $is_caching_disabled = ($cache_param == '0' || $cache_param === 0);
        
        if ($is_caching_disabled) {
            $this->utilities_service->debug_message('debug_cache_disabled_ignoring_existing', null, false, 'detailed');
        } else {
            // 1. Check if we already have a cached version (only if caching not disabled)
            if ($this->_check_cache_exists($cache_key)) {
                $this->utilities_service->debug_message('debug_cache_hit', [$cache_key]);
                
                // Load cached image and metadata
                if ($this->_load_from_cache($cache_key)) {
                    $this->context->set_flag('using_cache_copy', true);
                    
                    // Don't exit early - let OutputStage handle output generation
                    // This ensures add_dims logic and other output parameters are processed
                    $this->utilities_service->debug_message('debug_cache_hit_continuing_to_output', null, false, 'detailed');
                    return;
                }
            }
        }
        
        // 2. No cache hit (or cache disabled) - save processed image to cache
        // This saves the processed image regardless of cache="0" setting
        if (!$this->context->should_exit_early()) {
            $this->_save_to_cache($cache_key);
        }
        
        $this->utilities_service->debug_message('debug_cache_stage_completed');
        
        // Debug: CacheStage completed normally
        $this->utilities_service->debug_message('CacheStage: Completed normally, no early exit', null, false, 'detailed');
        } finally {
            // Clean up context reference
            $this->context = null;
        }
    }

    /**
     * Check if stage should be skipped
     * 
     * @param Context $context
     * @return bool
     */
    public function should_skip(Context $context): bool 
    {
        return $context->has_critical_error();
    }
    
    /**
     * Apply background color for non-transparent formats
     * Composites transparent areas with bg_color parameter for formats like JPG
     * that don't support transparency
     * 
     * @param ImageInterface $image Image to process
     * @param string $save_as Output format
     * @return ImageInterface Processed image with background color applied
     */
    private function _apply_background_color_for_format(ImageInterface $image, string $save_as): ImageInterface 
    {
        // Only apply background color for non-transparent formats
        $non_transparent_formats = ['jpg', 'jpeg'];
        if (!in_array(strtolower($save_as), $non_transparent_formats)) {
            return $image; // PNG, GIF, WebP, AVIF support transparency
        }
        
        // Get background color from context parameters
        $bg_color = $this->context->get_param('bg_color', '');
        if (empty($bg_color)) {
            return $image; // No background color specified
        }
        
        // Ensure bg_color has # prefix
        if (!str_starts_with($bg_color, '#')) {
            $bg_color = '#' . $bg_color;
        }
        
        try {
            // Create a background canvas with the specified color
            $size = $image->getSize();
            $palette = new \Imagine\Image\Palette\RGB();
            $bg_rgb_color = $palette->color($bg_color);
            
            // Create background image
            $imagine = new \Imagine\Gd\Imagine();
            $background = $imagine->create($size, $bg_rgb_color);
            
            // Composite the original image on top of the background
            // This fills transparent areas with the background color
            $background->paste($image, new \Imagine\Image\Point(0, 0));
            
            $this->utilities_service->debug_message("Applied background color {$bg_color} for {$save_as} format");
            
            return $background;
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Error applying background color: " . $e->getMessage());
            return $image; // Return original image if background application fails
        }
    }
    
    /**
     * Apply the appropriate lazy loading filter to the image
     * 
     * @param ImageInterface $image The image to filter
     * @param string $filter_name The filter to apply (lqip, dominant_color)
     * @return ImageInterface|null The filtered image or null on failure
     */
    private function _apply_lazy_filter(ImageInterface $image, string $filter_name, ): ?ImageInterface
    {
        try {
            switch ($filter_name) {
                case 'lqip':
                    $filter = new \JCOGSDesign\JCOGSImagePro\Filters\Lqip();
                    return $filter->apply($image); // LQIP takes no parameters
                    
                case 'dominant_color':
                    $filter = new \JCOGSDesign\JCOGSImagePro\Filters\DominantColor();
                    return $filter->apply($image); // Extract mode with no parameters
                    
                default:
                    $this->utilities_service->debug_message("Unknown lazy filter: {$filter_name}");
                    return null;
            }
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Error applying lazy filter {$filter_name}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Build comprehensive Legacy-compatible template variables for cache logging
     * 
     * @param string $cache_local_path Local cache path
     * @param string $cache_full_url Full cache URL
     * @return array Complete set of template variables
     */
    private function _build_legacy_compatible_variables(string $cache_local_path, string $cache_full_url): array
    {
        // Get source and processed image info
        $src_path = $this->context->get_param('src', '');
        $original_width = (int) $this->context->get_metadata_value('original_width', 0);
        $original_height = (int) $this->context->get_metadata_value('original_height', 0);
        
        // For final dimensions, prefer image_width/image_height if available (just set in save_to_cache)
        // Otherwise fall back to final_width/final_height or target dimensions
        $final_width = (int) ($this->context->get_metadata_value('image_width') ?: 
                             $this->context->get_metadata_value('final_width') ?: 
                             $this->context->get_metadata_value('target_width', 0));
        $final_height = (int) ($this->context->get_metadata_value('image_height') ?: 
                              $this->context->get_metadata_value('final_height') ?: 
                              $this->context->get_metadata_value('target_height', 0));
        
        // Calculate aspect ratios
        $aspect_ratio_orig = $original_width > 0 ? ($original_height / $original_width) : 0;
        $aspect_ratio = $final_width > 0 ? ($final_height / $final_width) : 0;
        
        // Get file extensions
        $extension_orig = pathinfo($src_path, PATHINFO_EXTENSION);
        $extension = pathinfo($cache_local_path, PATHINFO_EXTENSION);
        
        // Get file names
        $name_orig = pathinfo($src_path, PATHINFO_FILENAME);
        $name = pathinfo($cache_local_path, PATHINFO_FILENAME);
        
        // Get filesizes efficiently (cache-first approach like Legacy)
        // Try to get filesizes efficiently without expensive string conversion
        $processed_filesize_bytes = 0;
        $processed_filesize = '';
        $original_filesize_bytes = 0;
        $original_filesize = '';
        
        // First try: Get from context metadata (fastest - already calculated)
        $processed_filesize_bytes = $this->context->get_metadata_value('cache_size_bytes', 0);
        $original_filesize_bytes = $this->context->get_metadata_value('original_filesize_bytes', 0);
        
        // Second try: Use filesystem service for actual file sizes (Legacy approach)
        if ($processed_filesize_bytes === 0) {
            try {
                $processed_filesize_bytes = $this->filesystem_service->filesize($cache_local_path);
                if ($processed_filesize_bytes === false) {
                    $processed_filesize_bytes = 0;
                }
            } catch (\Exception $e) {
                // Could not get processed file size from filesystem
            }
        }
        
        if ($original_filesize_bytes === 0) {
            try {
                $source_path = $this->context->get_metadata_value('source_path', '');
                if ($source_path) {
                    $original_filesize_bytes = $this->filesystem_service->filesize($source_path);
                    if ($original_filesize_bytes === false) {
                        $original_filesize_bytes = 0;
                    }
                }
            } catch (\Exception $e) {
                // Could not get original file size from filesystem
            }
        }
        
        // Format human-readable sizes
        $processed_filesize = $processed_filesize_bytes > 0 ? $this->image_utilities->format_file_size($processed_filesize_bytes) : '';
        $original_filesize = $original_filesize_bytes > 0 ? $this->image_utilities->format_file_size($original_filesize_bytes) : '';
                
        // Get MIME type
        $mime_type = '';
        switch (strtolower($extension)) {
            case 'jpg':
            case 'jpeg':
                $mime_type = 'image/jpeg';
                break;
            case 'png':
                $mime_type = 'image/png';
                break;
            case 'webp':
                $mime_type = 'image/webp';
                break;
            case 'avif':
                $mime_type = 'image/avif';
                break;
            case 'gif':
                $mime_type = 'image/gif';
                break;
        }
        
        // Build attributes from parameters
        $attributes = [];
        
        // Collect attributes from tag parameters
        $tag_params = $this->context->get_tag_params();
        $attribute_params = ['class', 'style', 'alt', 'title', 'id'];
        foreach ($attribute_params as $attr) {
            if (isset($tag_params[$attr])) {
                $attributes[] = $attr . '="' . htmlspecialchars($tag_params[$attr]) . '"';
            }
        }
        
        // Handle data-* attributes
        foreach ($tag_params as $param_name => $param_value) {
            if (strpos($param_name, 'data-') === 0) {
                $attributes[] = $param_name . '="' . htmlspecialchars($param_value) . '"';
            }
        }
        
        $attributes_string = implode(' ', $attributes);
        
        // Get EE File Manager fields if source is a managed file
        $file_manager_fields = $this->_get_file_manager_fields($src_path);
        
        // Get lazy loading variables (conditional like Legacy - only when needed)
        $lazy_image_url = '';
        
        // Only generate lazy placeholder if actually needed in template (Legacy approach)
        $lazy_needed = false;
        if ($this->getLazyLoadingService()->is_lazy_loading_enabled($this->context)) {
            // Check if lazy variables are actually used in tagdata before generating expensive placeholders
            $var_prefix = $this->context->get_param('var_prefix', '');
            $tagdata = $this->context->get_metadata_value('tagdata', '');
            $output = $this->context->get_param('output', '');
            $haystack = $tagdata . $output;
            
            // Check for actual lazy variable usage (like Legacy does)
            if (stripos($haystack, '{' . $var_prefix . 'lazy_image}') !== false ||
                stripos($haystack, '{' . $var_prefix . 'lqip}') !== false ||
                stripos($haystack, '{' . $var_prefix . 'dominant_color}') !== false) {
                $lazy_needed = true;
            }
        }
        
        if ($lazy_needed && $this->getLazyLoadingService()->is_lazy_loading_enabled($this->context)) {
            $mode = $this->getLazyLoadingService()->get_lazy_loading_mode($this->context);
            if ($mode && $this->getLazyLoadingService()->requires_placeholder($mode)) {
                // Get placeholder URL from context metadata if available
                if ($mode === 'lqip') {
                    $lazy_image_url = $this->context->get_metadata_value('lqip_url', '');
                } elseif ($mode === 'dominant_color') {
                    $lazy_image_url = $this->context->get_metadata_value('dominant_color_url', '');
                }
            }
        }
        
        // Get palette data if available
        $palette_data = $this->context->get_metadata_value('palette_data', []);
        $dominant_color = $palette_data['dominant_color'] ?? '';
        
        // Extract basename and filename_orig from source
        $basename = basename($src_path);
        $filename_orig = pathinfo($src_path, PATHINFO_FILENAME);
        
        // Get absolute filesystem path for Legacy compatibility (but only for local files)
        $absolute_cache_path = FCPATH . ltrim($cache_local_path ?? '', '/');
        
        // For source path, only create absolute path if it's a local file (not a URL)
        $absolute_source_path = '';
        if (!filter_var($src_path, FILTER_VALIDATE_URL)) {
            // Local file path - create absolute path
            $absolute_source_path = FCPATH . ltrim($src_path ?? '', '/');
        } else {
            // URL - use as is
            $absolute_source_path = $src_path;
        }
        
        // Ensure paths have leading slash for Legacy format consistency
        $made_path = '/' . ltrim($cache_local_path ?? '', '/');
        $made_with_prefix_path = $made_path; // For now, same as made
        
        // For orig path, only add leading slash if it's a local file (not a URL)
        if (!filter_var($src_path, FILTER_VALIDATE_URL)) {
            $orig_path = '/' . ltrim($src_path ?? '', '/');
        } else {
            $orig_path = $src_path; // URLs stay as-is
        }
        
        // Build comprehensive variable set matching Legacy
        $template_variables = [
            // Core processed image variables (Legacy format with leading slash)
            'made' => $made_path,
            'made_url' => $cache_full_url,
            'made_with_prefix' => $made_with_prefix_path,
            'url' => $cache_local_path,              // Keep relative for URL usage
            'src' => $cache_local_path,              // Keep relative for URL usage
            
            // Dimensions - processed
            'width' => $final_width,
            'height' => $final_height,
            'aspect_ratio' => round($aspect_ratio, 6),
            
            // Dimensions - original
            'width_orig' => $original_width,
            'height_orig' => $original_height,
            'aspect_ratio_orig' => round($aspect_ratio_orig, 6),
            
            // File information - processed
            'extension' => $extension,
            'name' => $name,
            'path' => $absolute_cache_path,           // Absolute path like Legacy
            'type' => $extension,
            'filesize' => $processed_filesize,
            'filesize_bytes' => $processed_filesize_bytes,
            'mime_type' => $mime_type,
            
            // File information - original
            'extension_orig' => $extension_orig,
            'name_orig' => $name_orig,
            'path_orig' => $absolute_source_path,     // Absolute path like Legacy
            'orig' => $orig_path,                     // Relative path with leading slash
            'orig_url' => $src_path,                  // Original source URL/path
            'type_orig' => $extension_orig,
            'filesize_orig' => $original_filesize,
            'filesize_bytes_orig' => $original_filesize_bytes,
            
            // Legacy-specific variables
            'basename' => $basename,
            'filename_orig' => $filename_orig,
            
            // Template attributes and metadata
            'attributes' => $attributes_string,
            'lazy_image' => $lazy_image_url,
            
            // Lazy loading and placeholder variables (Legacy compatibility)
            'dominant_color' => $dominant_color,          // Populated from palette data
            'lqip' => '',                                // Will be populated by LQIP processing
            'base64' => '',                              // Legacy placeholder for base64 data
            'preload' => '',                             // Legacy preload attribute
            
            // EE File Manager fields (placeholder support)
            'img_credit' => $file_manager_fields['credit'] ?? '',
            'img_description' => $file_manager_fields['description'] ?? '',
            'img_location' => $file_manager_fields['location'] ?? '',
            'img_title' => $file_manager_fields['title'] ?? '',
            
            // Cache and processing metadata
            'cache_key' => $this->context->get_cache_key(),
            'using_cache' => $this->context->get_flag('using_cache_copy') ? 'yes' : 'no'
        ];
        
        return $template_variables;
    }
    
    /**
     * Check if cached image exists
     * 
     * @param string $cache_key
     * @return bool True if cache exists
     */
    private function _check_cache_exists(string $cache_key): bool
    {
        // Use the migrated cache management service for sophisticated cache checking
        // This includes database optimization, validation, and proper error handling
        $cache_path = $this->_get_cache_path($cache_key);
        
        // Move technical check to debug_log, keep user-friendly message
        $this->utilities_service->debug_log("Checking cache existence for: {$cache_key}");
        
        // Generate cache key for result caching to avoid redundant cache operations
        $cache_result_key = md5($cache_path);
        
        // Check if we've already cached the result of this cache check
        $cached_result = $this->context->get_cached_cache_result($cache_result_key);
        if ($cached_result !== null) {
            $exists = $cached_result;
        } else {
            // Use migrated is_image_in_cache method which includes:
            // - Input validation and profiling
            // - Caching disabled checks  
            // - Database cache log preloading (performance optimization)
            // - Proper cache validity checking through exp_jcogs_img_pro_cache_log table
            // - Cache freshness checking using cache duration extracted from filename
            $exists = $this->cache_service->is_image_in_cache($cache_path);
            // Cache the result to avoid redundant operations
            $this->context->set_cached_cache_result($cache_result_key, $exists);
        }
        
        if ($exists) {
            $this->utilities_service->debug_log('Cache file found and is fresh: ' . $cache_path);
        } else {
            $this->utilities_service->debug_log('Cache file not found or has expired: ' . $cache_path);
        }
        
        return $exists;
    }
    
    /**
     * Create JPG version for JavaScript lazy loading noscript fallback
     * 
     * When using JavaScript lazy loading modes (js_lqip, js_dominant_color), we need
     * a JPG version of the main processed image for noscript fallback compatibility
     * with older browsers that may not support modern image formats.
     * 
     * @param ImageInterface $processed_image The main processed image
     * @param string $cache_path Main cache path
     * @return void
     */
    private function _create_js_lazy_loading_jpg_fallback(ImageInterface $processed_image, string $cache_path): void
    {
        $lazy_param = strtolower($this->context->get_param('lazy', ''));
        $save_as = strtolower($this->context->get_param('save_as', 'jpg'));
        
        // Only create JPG fallback for JavaScript lazy loading modes and non-JPG formats
        if (!str_starts_with($lazy_param, 'js_') || $save_as === 'jpg') {
            return;
        }
        
        // Generate JPG version path
        $path_parts = pathinfo($cache_path);
        $cache_dir = $path_parts['dirname'] . '/';
        $base_filename = $path_parts['filename']; // Without extension
        $jpg_cache_path = $cache_dir . $base_filename . '.jpg';
        
        // Check if JPG version already exists in cache
        $filesystem = $this->filesystem_service;
        if ($filesystem->exists($jpg_cache_path)) {
            $this->utilities_service->debug_message("JPG fallback already exists: {$jpg_cache_path}");
            return;
        }
        
        $this->utilities_service->debug_message("Creating JPG fallback for noscript: {$jpg_cache_path}");
        
        try {
            // Get background color for composition
            $bg_color_param = $this->context->get_param('bg_color', '#ffffff');
            $colour_service = ee('jcogs_img_pro:ColourManagementService');
            $bg_color = $colour_service->validate_colour_string($bg_color_param);
            
            // Create a new image with background color (for transparency handling)
            $image_size = $processed_image->getSize();
            $jpg_image = (new \Imagine\Gd\Imagine())->create(
                new \Imagine\Image\Box($image_size->getWidth(), $image_size->getHeight()),
                $bg_color
            );
            
            // Paste the processed image onto the background
            $jpg_image->paste($processed_image, new \Imagine\Image\PointSigned(0, 0));
            
            // Set JPG quality
            $quality = max(75, min(95, (int)$this->context->get_param('quality', 85))); // Use good quality for fallback
            $jpg_options = ['quality' => $quality];
            
            // Save JPG version
            $success = $filesystem->writeImage($jpg_image, $jpg_cache_path, $jpg_options);
            
            if ($success) {
                $this->utilities_service->debug_message("Successfully created JPG fallback: {$jpg_cache_path}");
                
                // Update cache log for JPG version
                $cache_management = $this->cache_service;
                $processing_time = $this->context->get_metadata_value('processing_time', 0.0);
                $source_path = $this->context->get_metadata_value('source_path', '');
                
                // Get template variables and modify for JPG version
                $template_variables = $this->context->get_metadata_value('computed_template_variables', []);
                if ($template_variables) {
                    // Update variables for JPG version
                    $jpg_template_variables = $template_variables;
                    $var_prefix = $this->context->get_param('var_prefix', '');
                    
                    if (isset($jpg_template_variables[$var_prefix . 'extension'])) {
                        $jpg_template_variables[$var_prefix . 'extension'] = 'jpg';
                    }
                    if (isset($jpg_template_variables[$var_prefix . 'type'])) {
                        $jpg_template_variables[$var_prefix . 'type'] = 'jpg';
                    }
                    if (isset($jpg_template_variables[$var_prefix . 'mime_type'])) {
                        $jpg_template_variables[$var_prefix . 'mime_type'] = 'image/jpeg';
                    }
                    
                    $cache_management->update_cache_log(
                        image_path: $jpg_cache_path,
                        processing_time: $processing_time,
                        cache_dir: $this->context->get_param('cache_dir', 'cache'),
                        vars: [$jpg_template_variables],
                        source_path: $source_path,
                        force_update: true,
                        using_cache_copy: false,
                        connection_name: $this->context->get_save_to_connection() ?? $this->getDefaultConnectionForCache()
                    );
                }
                
            } else {
                $this->utilities_service->debug_message("Failed to save JPG fallback: {$jpg_cache_path}");
            }
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Error creating JPG fallback: " . $e->getMessage());
        }
    }
    
    /**
     * Create lazy placeholder image using filter-based approach (like Legacy)
     * 
     * This unified method creates lazy loading placeholder images by applying
     * the appropriate filter (lqip, dominant_color, etc.) to a copy of the processed image.
     * This approach maintains the same dimensions as the original, preventing layout shifts.
     * 
     * @param ImageInterface $processed_image The main processed image
     * @param string $main_cache_path Path to the main cached image file
     * @return void
     */
    private function _create_lazy_placeholder_image(ImageInterface $processed_image, string $main_cache_path): void
    {
        // Check if lazy loading is enabled
        $lazy_param = strtolower($this->context->get_param('lazy', ''));
        if (empty($lazy_param) || $lazy_param === 'no' || $lazy_param === 'false') {
            return; // No lazy loading requested
        }
        
        // Remove 'js_' prefix if present (like Legacy does)
        $filter_name = str_replace('js_', '', $lazy_param);
        
        // Only handle filters we support for lazy loading
        if (!in_array($filter_name, ['lqip', 'dominant_color'])) {
            $this->utilities_service->debug_message("Unsupported lazy loading type: {$filter_name}");
            return;
        }
        
        $this->utilities_service->debug_message("Creating lazy placeholder image using filter: {$filter_name}");
        
        try {
            // Create a copy of the processed image (same dimensions as Legacy approach)
            $placeholder_image = $processed_image->copy();
            
            // Apply the appropriate filter based on lazy type
            $placeholder_image = $this->_apply_lazy_filter($placeholder_image, $filter_name);
            
            if (!$placeholder_image) {
                $this->utilities_service->debug_message("Failed to apply lazy filter: {$filter_name}");
                return;
            }
            
            // Generate placeholder filename and path
            $path_parts = pathinfo($main_cache_path);
            $cache_dir = $path_parts['dirname'] . '/';
            $base_filename = $path_parts['filename']; // Without extension
            $extension = $path_parts['extension'];
            
            $placeholder_filename = $base_filename . '_' . $filter_name . '.' . $extension;
            $placeholder_path = $cache_dir . $placeholder_filename;
            
            // Save placeholder image with appropriate quality settings
            $filesystem = $this->filesystem_service;
            $quality = $this->_get_lazy_image_quality($filter_name, $extension);
            
            // CRITICAL: Use the same connection as the main image for lazy placeholder
            $save_to_connection = $this->context->get_save_to_connection();
            $cache_connection = $save_to_connection ?? $this->getDefaultConnectionForCache();
            
            $success = $filesystem->writeImage($placeholder_image, $placeholder_path, [
                'quality' => $quality
            ], $cache_connection);
            
            if ($success) {
                $this->utilities_service->debug_message("Successfully generated lazy placeholder: {$placeholder_filename}", null, false, 'detailed');
                
                // Store placeholder URL in context for OutputStage (using same metadata keys as before)
                $placeholder_url = str_replace($cache_dir, $this->context->get_param('cache_dir_url', '/images/jcogs_img_pro/cache/'), $placeholder_path);
                
                // Set metadata using appropriate key based on filter type
                if ($filter_name === 'lqip') {
                    $this->context->set_metadata('lqip_url', $placeholder_url);
                } elseif ($filter_name === 'dominant_color') {
                    $this->context->set_metadata('dominant_color_url', $placeholder_url);
                }
                
                // Update cache log for placeholder image
                $cache_management_service = $this->cache_service;
                $placeholder_relative_path = str_replace($cache_dir, '', $placeholder_path);
                $normalized_cache_dir = $this->context->get_metadata_value('cache_directory_normalized', '.');
                
                // Build Legacy-compatible template variables for placeholder cache logging
                // FIXED: Use correct connection URL for lazy placeholder images
                $save_to_connection = $this->context->get_save_to_connection();
                $cache_connection = $save_to_connection ?? $this->getDefaultConnectionForCache();
                $placeholder_cache_full_url = $this->filesystem_service->getPublicUrl($placeholder_relative_path, $cache_connection);
                $placeholder_template_variables = $this->_build_legacy_compatible_variables($placeholder_relative_path, $placeholder_cache_full_url);
                
                $cache_management_service->update_cache_log(
                    image_path: $placeholder_relative_path,
                    processing_time: 0.01, // Placeholder generation is very fast
                    vars: $placeholder_template_variables,
                    cache_dir: $normalized_cache_dir,
                    source_path: $this->context->get_metadata_value('source_path', ''),
                    force_update: true,
                    using_cache_copy: false,
                    connection_name: $this->context->get_save_to_connection() ?? $this->getDefaultConnectionForCache()
                );
                
                // Also save JPG version like Legacy does for maximum compatibility
                if ($extension !== 'jpg') {
                    $this->_save_jpg_version($placeholder_image, $cache_dir, $base_filename, $filter_name);
                }
                
            } else {
                $this->utilities_service->debug_message("Failed to save lazy placeholder: {$placeholder_filename}");
            }
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Error creating lazy placeholder image: " . $e->getMessage());
        }
    }
    
    /**
     * Create a variant image at the specified width
     * 
     * @param ImageInterface $source_image Source image to resize
     * @param int $target_width Target width for the variant
     * @return ImageInterface|null Resized variant image
     */
    private function _create_variant_image(ImageInterface $source_image, int $target_width, ): ?ImageInterface
    {
        try {
            $source_size = $source_image->getSize();
            $source_width = $source_size->getWidth();
            $source_height = $source_size->getHeight();
            
            // Calculate proportional height
            $aspect_ratio = $source_height / $source_width;
            $target_height = (int) round($target_width * $aspect_ratio);
            
            // Create target box
            $target_box = new Box($target_width, $target_height);
            
            // Resize the image maintaining aspect ratio
            $variant_image = $source_image->copy()->resize($target_box);
            
            return $variant_image;
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Error creating variant image: " . $e->getMessage());
            return null;
        }
    }
    
    
    /**
     * Generate responsive image variants for srcset
     * 
     * This method creates physical variant images at different sizes
     * for responsive image support when srcset parameter is provided.
     * 
     * @param ImageInterface $processed_image The main processed image
     * @param string $main_cache_path Path to the main cached image
     * @return void
     */
    private function _generate_responsive_variants(ImageInterface $processed_image, string $main_cache_path): void
    {
        // Check if srcset is enabled
        $srcset_param = $this->context->get_param('srcset', '');
        if (empty($srcset_param)) {
            return; // No srcset requested
        }
        
        $this->utilities_service->debug_message("Starting responsive variant generation for srcset: {$srcset_param}", null, false, 'detailed');
        
        // Get responsive service
        $responsive_service = new ResponsiveImageService();
        
        // Get dimensions
        $base_width = (int) $this->context->get_metadata_value('final_width', 0);
        $max_width = (int) $this->context->get_metadata_value('original_width', $base_width);
        
        // Generate variant information
        $variants = $responsive_service->generate_variant_info(
            $this->context, 
            $base_width, 
            $max_width,
            false // Don't allow scaling larger than original
        );
        
        if (empty($variants)) {
            $this->utilities_service->debug_message("No valid variants generated for srcset");
            return;
        }
        
        $this->utilities_service->debug_message(sprintf("Generated %d responsive variants", count($variants)), null, false, 'detailed');
        
        // Extract path components from main cache path
        $path_parts = pathinfo($main_cache_path);
        $cache_dir = $path_parts['dirname'] . '/';
        $base_filename = $path_parts['filename']; // Without extension
        $extension = $path_parts['extension'];
        
        // Generate and save each variant
        foreach ($variants as $variant) {
            $variant_start_time = microtime(true);
            $variant_width = $variant['width'];
            $variant_suffix = $variant['cache_suffix']; // e.g., "_250w"
            
            try {
                // Create variant by resizing the processed image
                $variant_image = $this->_create_variant_image($processed_image, $variant_width);
                
                if (!$variant_image) {
                    $this->utilities_service->debug_message("Failed to create variant image for width: {$variant_width}");
                    continue;
                }
                
                // Generate variant filename and path
                $variant_filename = $base_filename . $variant_suffix . '.' . $extension;
                $variant_path = $cache_dir . $variant_filename;
                
                // Save variant image using the same process as the main image
                $filesystem = $this->filesystem_service;
                $save_as = $this->context->get_param('save_as', 'jpg');
                $quality_options = $this->_get_quality_options($save_as);
                $success = $filesystem->writeImage($variant_image, $variant_path, $quality_options);
                
                if ($success) {
                    $this->utilities_service->debug_message("Generated responsive variant: {$variant_filename} ({$variant_width}px)", null, false, 'detailed');
                    
                    // Update cache log for the variant image - critical for performance!
                    $variant_processing_time = microtime(true) - $variant_start_time;
                    
                    // Use relative path for cache logging - define before using
                    $relative_variant_path = str_replace($cache_dir, '', $variant_path);
                    
                    // Build Legacy-compatible template variables for variant cache logging
                    // FIXED: Use correct connection URL for responsive variants
                    $save_to_connection = $this->context->get_save_to_connection();
                    $cache_connection = $save_to_connection ?? $this->getDefaultConnectionForCache();
                    $variant_cache_full_url = $this->filesystem_service->getPublicUrl($relative_variant_path, $cache_connection);
                    $variant_template_variables = $this->_build_legacy_compatible_variables($relative_variant_path, $variant_cache_full_url);
                    
                    $source_path = $this->context->get_metadata_value('source_path', '');
                    $normalized_cache_dir = $this->context->get_metadata_value('cache_directory_normalized', '.');
                    
                    $variant_cache_log_success = $this->cache_service->update_cache_log(
                        image_path: $relative_variant_path,
                        processing_time: $variant_processing_time,
                        vars: $variant_template_variables,
                        cache_dir: $normalized_cache_dir,
                        source_path: $source_path,
                        force_update: true,
                        using_cache_copy: false,
                        connection_name: $this->context->get_save_to_connection() ?? $this->getDefaultConnectionForCache()
                    );
                    
                    if ($variant_cache_log_success) {
                        $this->utilities_service->debug_log("Cache log updated for variant: {$variant_filename}");
                    } else {
                        $this->utilities_service->debug_message("Warning: Failed to update cache log for variant: {$variant_filename}", null, false, 'detailed');
                    }
                } else {
                    $this->utilities_service->debug_message("Failed to save variant: {$variant_filename}");
                }
                
            } catch (\Exception $e) {
                $this->utilities_service->debug_message("Error generating variant {$variant_width}px: " . $e->getMessage());
                continue;
            }
        }
        
        // Store variant info in context for OutputStage
        $this->context->set_metadata('responsive_variants', $variants);
    }
    
    /**
     * Get cache file path for a cache key
     * 
     * IMPORTANT: Returns relative path from webroot for filesystem adapter compatibility
     * Filesystem adapters (local/S3/DO) expect relative paths and will prefix with their root
     * 
     * @param string $cache_key
     * @return string Cache file path (relative from webroot)
     */
    private function _get_cache_path(string $cache_key): string 
    {
        // Get cache directory from parameter or use connection-based default
        $cache_dir_param = $this->context ? $this->context->get_param('cache_dir', '') : '';
        
        if (!empty($cache_dir_param)) {
            // Use specified cache_dir parameter - keep as relative path
            $cache_dir = trim($cache_dir_param, '/') . '/';
        } else {
            // Use connection-based cache directory resolution (same as fast cache check)
            $cache_dir = $this->_resolve_cache_directory_from_connection($this->context);
        }
        
        // Get file extension from save_as parameter (processed from save_type) like legacy
        $save_as = $this->context ? $this->context->get_param('save_as', 'jpg') : 'jpg';
        $extension = $save_as === 'jpeg' ? 'jpg' : $save_as;

        if (!$this->filesystem_service->directoryExists($cache_dir, $this->cache_connection_name)) {
            $this->filesystem_service->createDirectory($cache_dir, $this->cache_connection_name);
        }
        
        // Return relative path for filesystem adapter compatibility
        return $cache_dir . $cache_key . '.' . $extension;
    }
    
    /**
     * Get the cache path for a named connection
     * 
     * Uses the same logic as the pipeline to determine cache paths for different
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
     * Get cache URL for a cache key
     * 
     * @param string $cache_key
     * @return string Cache URL (local path, not full URL)
     */
    private function _get_cache_url(string $cache_key): string
    {
        // Get cache directory from parameter or use default
        $cache_dir_param = $this->context ? $this->context->get_param('cache_dir', '') : '';
        
        if (!empty($cache_dir_param)) {
            // Use specified cache_dir parameter - convert to URL path
            $cache_url_path = '/' . trim($cache_dir_param, '/') . '/';
        } else {
            // Use connection-based cache directory resolution instead of hardcoded Pro default
            try {
                $connection_name = $this->context ? ($this->context->get_metadata_value('resolved_connection_name') ?? $this->settings_service->get_default_connection_name()) : $this->settings_service->get_default_connection_name();
                $connection = $this->settings_service->getNamedConnection($connection_name);
                
                if ($connection) {
                    $cache_path = $this->_get_connection_cache_path($connection);
                    $cache_url_path = '/' . trim($cache_path, '/') . '/';
                } else {
                    // Fallback to Legacy IMG default, not Pro default
                    $cache_url_path = '/images/jcogs_img/cache/';
                }
            } catch (\Exception $e) {
                // Last resort fallback to Legacy IMG default
                $cache_url_path = '/images/jcogs_img/cache/';
            }
        }
        
        // Get file extension from save_as parameter (processed from save_type) like legacy
        $save_as = $this->context ? $this->context->get_param('save_as', 'jpg') : 'jpg';
        $extension = $save_as === 'jpeg' ? 'jpg' : $save_as;
        
        // Return LOCAL PATH like legacy, not full URL
        return $cache_url_path . $cache_key . '.' . $extension;
    }
    
    /**
     * Get EE File Manager fields for source image
     * 
     * @param string $src_path Source image path
     * @return array File manager fields
     */
    private function _get_file_manager_fields(string $src_path): array
    {
        // Extract filename from path
        $file_basename = basename($src_path);
        
        // If no filename, return empty fields
        if (empty($file_basename)) {
            return [];
        }
        
        try {
            // Use static caching to prevent duplicate EE File model queries (following Legacy pattern)
            static $file_cache = [];
            if (!isset($file_cache[$file_basename])) {
                $file_cache[$file_basename] = ee('Model')->get('File')->filter('file_name', $file_basename)->first();
            }
            $file = $file_cache[$file_basename];
            
            if ($file) {
                return [
                    'credit' => $file->credit ?? '',
                    'description' => $file->description ?? '',
                    'location' => $file->location ?? '',
                    'title' => $file->title ?? ''
                ];
            }
        } catch (\Exception $e) {
            // Error accessing EE File model - return empty fields
        }
        
        return [];
    }
    
    /**
     * Get appropriate quality setting for lazy loading images
     * 
     * @param string $filter_name The filter being applied
     * @param string $extension File extension
     * @return int Quality setting (0-100)
     */
    private function _get_lazy_image_quality(string $filter_name, string $extension): int
    {
        // Use lower quality for LQIP to reduce file size
        if ($filter_name === 'lqip') {
            return $extension === 'jpg' ? 50 : 85;
        }
        
        // Use high quality for dominant color (it's a simple 1x1 or solid color image)
        if ($filter_name === 'dominant_color') {
            return 100;
        }
        
        // Default quality
        return 85;
    }
    
    /**
     * Get quality options for image saving
     * 
     * Handles both numeric quality values and special options like 'lossless'
     * 
     * @param string $save_as Save format
     * @return array Quality options for Imagine library
     */
    private function _get_quality_options(string $save_as): array
    {
        // Get PNG quality from settings instead of hardcoded default
        $png_default_quality = $this->settings_service->get('img_cp_png_default_quality', '6');
        
        // Get JPEG quality default from settings to match legacy behavior
        $jpg_default_quality = $this->settings_service->get('img_cp_jpg_default_quality', '90');
        
        $quality_param = $save_as === 'png' ? 
            $this->context->get_param('png_quality', $png_default_quality) : 
            $this->context->get_param('quality', $jpg_default_quality);

        // Handle lossless quality for supported formats
        if ($quality_param === 'lossless') {
            switch (strtolower($save_as)) {
                case 'webp':
                    return ['webp_lossless' => true, 'quality' => 100];
                    
                case 'avif':
                    return ['avif_lossless' => true, 'quality' => 100];
                    
                case 'png':
                    // PNG is inherently lossless, use maximum quality
                    return ['quality' => 100];
                    
                case 'jpg':
                case 'jpeg':
                    // JPEG cannot be truly lossless, use maximum quality
                    $this->utilities_service->debug_message("JPEG cannot be lossless, using quality=100 instead");
                    return ['quality' => 100];
                    
                default:
                    $this->utilities_service->debug_message("Lossless not supported for {$save_as}, using quality=100 instead");
                    return ['quality' => 100];
            }
        }

        // Handle PNG compression levels (0-9) vs quality percentages
        if (strtolower($save_as) === 'png') {
            $compression_level = is_numeric($quality_param) ? (int)$quality_param : 6;
            
            // Ensure compression level is within valid range (0-9)
            $compression_level = max(0, min(9, $compression_level));
            
            // For PNG, use png_compression_level directly instead of quality
            return ['png_compression_level' => $compression_level];
        }

        // Handle numeric quality values for other formats
        $quality = is_numeric($quality_param) ? (int)$quality_param : 85;
        return ['quality' => $quality];
    }
    
    /**
     * Load image and metadata from cache
     * 
     * @param string $cache_key
     * @return bool True if successfully loaded from cache
     */
    private function _load_from_cache(string $cache_key): bool
    {
        try {
            $cache_path = $this->_get_cache_path($cache_key);
            
            // Use the single unified cache loading method
            $cache_data = $this->cache_service->load_cached_image_data($cache_path);
            
            if (!$cache_data) {
                $this->utilities_service->debug_log("No cache data found for: " . $cache_path);
                return false;
            }
            
            // Extract data from unified response
            $template_variables = $cache_data['template_variables'];
            $file_info = $cache_data['file_info'];
            
            // Store cache info in context
            $this->context->set_metadata('cache_path', $file_info['path']);
            $this->context->set_metadata('cache_url', $file_info['url']);
            $this->context->set_metadata('cache_size', $file_info['size']);
            
            // Store template variables for OutputStage
            $this->context->set_metadata('computed_template_variables', $template_variables);
            
            $this->utilities_service->debug_log('Successfully loaded from cache with ' . count($template_variables) . ' variables');
            $this->utilities_service->debug_log("JCOGS_IMG_PRO DEBUG: Restored width=" . ($template_variables['width'] ?? 'MISSING') . " height=" . ($template_variables['height'] ?? 'MISSING'));
            
            return true;
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message('debug_cache_loaded_failed', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Resolve cache directory from named connection configuration
     * 
     * Uses the same logic as the fast cache path to ensure consistency.
     * Respects the connection parameter and uses the appropriate cache path.
     * 
     * @return string Cache directory path with trailing slash
     */
    private function _resolve_cache_directory_from_connection(): string
    {
        // Determine which connection to use - either from 'connection' parameter or default
        $connection_name = $this->context ? $this->context->get_param('connection', '') : '';
        
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
                // Use the same logic as pipeline to get cache path
                $cache_path = $this->_get_connection_cache_path($connection);
                return trim($cache_path, '/') . '/';
            }
        } catch (\Exception $e) {
            // Named connection lookup failed, fall back to default
        }
        
        // Fallback to Pro cache directory
        return 'images/jcogs_img_pro/cache/';
    }
    
    /**
     * Save animated GIF to cache using direct copy (no processing)
     * Mirrors Legacy behavior for animated GIF preservation
     * 
     * @param string $cache_key Cache key
     */
    private function _save_animated_gif_to_cache(string $cache_key): void
    {
        $save_start_time = microtime(true);
        
        try {
            // Get raw image data stored from LoadSourceStage
            $image_raw_data = $this->context->get_metadata_value('image_source_raw', '');
            if (empty($image_raw_data)) {
                throw new \Exception('No raw image data available for animated GIF');
            }
            
            // Override save_as to 'gif' to ensure proper extension
            $original_save_as = $this->context->get_param('save_as', 'jpg');
            $this->context->set_param('save_as', 'gif');
            
            // Build cache path with .gif extension (preserve original format)
            $cache_path = $this->_get_cache_path($cache_key);
            
            // Get filesystem service
            $filesystem = $this->filesystem_service;
            
            // Ensure cache directory exists
            $cache_dir = dirname($cache_path);
            if (!$this->filesystem_service->directoryExists($cache_dir, $this->cache_connection_name)) {
                $this->filesystem_service->createDirectory($cache_dir, $this->cache_connection_name);
            }
            
            // Write raw GIF data directly (no processing)
            $success = $this->filesystem_service->write($cache_path, $image_raw_data, $this->cache_connection_name);
            
            if (!$success) {
                throw new \Exception("Failed to save animated GIF to cache: {$cache_path}");
            }
            
            $this->utilities_service->debug_message('Animated GIF copied directly to cache: ' . $cache_path);
            
            // Update cache log with animated GIF info
            $processing_time = $this->context->get_metadata_value('processing_time', 0.0);
            $tag_params = $this->context->get_tag_params();
            $source_path = $this->context->get_metadata_value('source_path', '');
            
            // Prepare metadata for cache log
            // Use calculated final dimensions if available, otherwise fall back to original
            $final_width = $this->context->get_metadata_value('final_width', null);
            $final_height = $this->context->get_metadata_value('final_height', null);
            
            if ($final_width === null || $final_height === null) {
                // Fall back to original dimensions if final dimensions not calculated
                $final_width = $this->context->get_metadata_value('original_width', 0);
                $final_height = $this->context->get_metadata_value('original_height', 0);
            }
            
            $gif_metadata = [
                'width' => $final_width,
                'height' => $final_height,
                'save_as' => 'gif',
                'is_animated_gif' => true,
                'filesize' => strlen($image_raw_data),
                'orig_filesize' => strlen($image_raw_data)
            ];
            
            // Build Legacy-compatible template variables for GIF cache logging
            // FIXED: Use correct adapter URL for animated GIFs too
            $this->cache_connection_name = $this->context->get_save_to_connection() ?? $this->getDefaultConnectionForCache();
            $gif_cache_full_url = $this->filesystem_service->getPublicUrl($cache_path, $this->cache_connection_name);
            $base_template_variables = $this->_build_legacy_compatible_variables($cache_path, $gif_cache_full_url);
            
            // Merge with GIF-specific metadata for cache log
            $cache_log_vars = array_merge($base_template_variables, $gif_metadata);
            
            // Update cache log
            $cache_log_success = $this->cache_service->update_cache_log(
                image_path: $cache_path,
                processing_time: $processing_time,
                vars: $cache_log_vars,
                cache_dir: dirname($cache_path),
                source_path: $source_path,
                force_update: true,
                using_cache_copy: false,
                connection_name: $this->context->get_save_to_connection() ?? $this->getDefaultConnectionForCache()
            );
            
            if ($cache_log_success) {
                $this->utilities_service->debug_log('Cache log updated for animated GIF: ' . $cache_path);
            } else {
                $this->utilities_service->debug_message('WARNING: Cache log update failed for animated GIF: ' . $cache_path);
            }
            
            // Store cache info in context
            $cache_url_local = $this->_get_cache_url($cache_key);
            
            // Use the correct adapter for URL generation
            $this->cache_connection_name = $this->context->get_save_to_connection() ?? $this->getDefaultConnectionForCache();
            $cache_url = $this->filesystem_service->getPublicUrl($cache_url_local, $this->cache_connection_name);
            
            // For cache_path metadata, use adapter-aware paths
            if ($this->cache_connection_name === 'local') {
                $base_path = ee()->config->item('base_path') ?? FCPATH;
                $full_cache_path = rtrim($base_path, '/') . '/' . $cache_path;
            } else {
                // For cloud adapters (S3, etc.), use the public URL as the "path"
                $full_cache_path = $cache_url;
            }
            
            $this->context->set_metadata('cache_path', $full_cache_path);
            $this->context->set_metadata('cache_url', $cache_url);
            $this->context->set_metadata('cache_size', strlen($image_raw_data));
            
            // Set final dimensions for output (use the same calculated values)
            $this->context->set_metadata('final_width', $final_width);
            $this->context->set_metadata('final_height', $final_height);
            
            // Restore original save_as parameter 
            $this->context->set_param('save_as', $original_save_as);
            
            // Show save timing
            $save_time = microtime(true) - $save_start_time;
            $this->utilities_service->debug_message('Animated GIF saved to cache in ' . number_format($save_time, 3) . ' seconds');
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message('Error saving animated GIF to cache: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Save JPG version of placeholder image (like Legacy does)
     * 
     * @param ImageInterface $placeholder_image The placeholder image
     * @param string $cache_dir Cache directory path
     * @param string $base_filename Base filename without extension
     * @param string $filter_name Filter name for suffix
    * @return void
     */
    private function _save_jpg_version(ImageInterface $placeholder_image, string $cache_dir, string $base_filename, string $filter_name, ): void
    {
        try {
            $jpg_filename = $base_filename . '_' . $filter_name . '.jpg';
            $jpg_path = $cache_dir . $jpg_filename;
            
            $filesystem = $this->filesystem_service;
            $jpg_quality = $this->_get_lazy_image_quality($filter_name, 'jpg');
            
            $success = $filesystem->writeImage($placeholder_image, $jpg_path, [
                'quality' => $jpg_quality
            ]);
            
            if ($success) {
                $this->utilities_service->debug_message("Also saved JPG version: {$jpg_filename}", null, false, 'detailed');
                
                // Update cache log for JPG version too
                $cache_management_service = $this->cache_service;
                $jpg_relative_path = str_replace($cache_dir, '', $jpg_path);
                $normalized_cache_dir = $this->context->get_metadata_value('cache_directory_normalized', '.');
                
                // Build Legacy-compatible template variables for JPG cache logging
                // FIXED: Use correct adapter URL for JPG versions
                $this->cache_connection_name = $this->context->get_save_to_connection() ?? $this->getDefaultConnectionForCache();
                $jpg_cache_full_url = $this->filesystem_service->getPublicUrl($jpg_relative_path, $this->cache_connection_name);
                $jpg_template_variables = $this->_build_legacy_compatible_variables($jpg_relative_path, $jpg_cache_full_url);
                
                $cache_management_service->update_cache_log(
                    image_path: $jpg_relative_path,
                    processing_time: 0.01,
                    vars: $jpg_template_variables,
                    cache_dir: $normalized_cache_dir,
                    source_path: $this->context->get_metadata_value('source_path', ''),
                    force_update: true,
                    using_cache_copy: false,
                    connection_name: $this->context->get_save_to_connection() ?? $this->getDefaultConnectionForCache()
                );
            }
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Error saving JPG version: " . $e->getMessage());
        }
    }
    
    /**
     * Save processed image to cache
     * 
     * @param string $cache_key
     */
    private function _save_to_cache(string $cache_key): void
    {
        $save_start_time = microtime(true);
        $timing_data = [];
        
        try {
            // Handle animated GIF passthrough (copy raw data directly)
            if ($this->context->get_flag('animated_gif')) {
                $this->_save_animated_gif_to_cache($cache_key);
                return;
            }
            
            
            $timing_checkpoint = microtime(true);
            $processed_image = $this->context->get_processed_image();
            if (!$processed_image) {
                $this->utilities_service->debug_message('debug_cache_no_image_to_save');
                $this->utilities_service->debug_log('ERROR: No processed image available for cache saving');
                
                // CRITICAL: Without a processed image, there's no useful work for OutputStage
                // Throw exception to indicate pipeline failure
                throw new \Exception('No processed image available for cache saving - pipeline cannot continue');
            }
            $timing_data['get_processed_image'] = microtime(true) - $timing_checkpoint;

            $timing_checkpoint = microtime(true);
            $cache_path = $this->_get_cache_path($cache_key);
            
            // Debug: Log cache save details
            if ($this->utilities_service) {
                $this->utilities_service->debug_message("Cache: saving processed image to {$cache_path}");
            }
            
            // Ensure cache directory exists using the cache adapter
            $cache_dir = dirname($cache_path);
            if (!$this->filesystem_service->directoryExists($cache_dir, $this->cache_connection_name)) {
                $this->filesystem_service->createDirectory($cache_dir, $this->cache_connection_name);
            }
            $timing_data['filesystem_setup'] = microtime(true) - $timing_checkpoint;
            
            $timing_checkpoint = microtime(true);
            // Get output format from save_as parameter
            $save_as = $this->context->get_param('save_as', 'jpg');
            $quality_options = $this->_get_quality_options($save_as);
            
            // Store quality options in context for later use (e.g., base64 generation)
            $this->context->set_metadata('quality_options', $quality_options);
            $timing_data['quality_options'] = microtime(true) - $timing_checkpoint;
            
            $timing_checkpoint = microtime(true);
            // Debug message for save format and quality
            $format_quality_info = '';
            if (strtolower($save_as) === 'png' && isset($quality_options['png_compression_level'])) {
                $format_quality_info = "PNG (compression: {$quality_options['png_compression_level']})";
            } else {
                $quality_display = isset($quality_options['webp_lossless']) && $quality_options['webp_lossless'] ? 'lossless' : 
                    (isset($quality_options['avif_lossless']) && $quality_options['avif_lossless'] ? 'lossless' : 
                    $quality_options['quality']);
                $format_quality_info = strtoupper($save_as) . " (quality: {$quality_display})";
            }
            
            // Get image dimensions once (avoid duplicate calculation)
            $image_size = $processed_image->getSize();
            $this->utilities_service->debug_log("JCOGS_IMG_PRO DEBUG CacheStage: Processing file dimensions: " . 
                     $image_size->getWidth() . "x" . $image_size->getHeight());
            
            // Store image dimensions in context metadata for cache logging
            $this->context->set_metadata('image_width', $image_size->getWidth());
            $this->context->set_metadata('image_height', $image_size->getHeight());
            $timing_data['image_metadata'] = microtime(true) - $timing_checkpoint;
            
            $timing_checkpoint = microtime(true);
            // Apply background color for non-transparent formats (JPG, etc.)
            // This ensures transparent areas are filled with bg_color like Legacy version
            $processed_image = $this->_apply_background_color_for_format($processed_image, $save_as);
            $timing_data['background_color'] = microtime(true) - $timing_checkpoint;
            
            $timing_checkpoint = microtime(true);
            // Get file size efficiently BEFORE writing to disk (avoids regenerating image content later)
            $image_content = $processed_image->get($save_as, $quality_options);
            $cache_file_size = strlen($image_content);
            $this->context->set_metadata('cache_size', $cache_file_size);
            
            // Normalize path upfront for efficiency - use relative path for cache logging
            // CacheManagementService expects relative paths from webroot for database storage
            $normalized_cache_dir = dirname($cache_path);
            
            // Save the image using the cache adapter (S3, etc.)
            $success = $this->filesystem_service->writeImage($processed_image, $cache_path, $quality_options, $this->cache_connection_name);
            
            if (!$success) {
                throw new \Exception("Failed to save image to cache: {$cache_path}");
            }
            $timing_data['filesystem_write'] = microtime(true) - $timing_checkpoint;
            
            $timing_checkpoint = microtime(true);
            // CRITICAL: Update cache log with processing information
            $processing_time = $this->context->get_metadata_value('processing_time', 0.0);
            
            // Generate lazy placeholder image BEFORE building template variables 
            // so that lazy_image URL can be included in the cache log
            // Only create if lazy loading is actually requested
            $lazy_param = strtolower($this->context->get_param('lazy', ''));
            if (!empty($lazy_param) && $lazy_param !== 'no' && $lazy_param !== 'false') {
                $this->_create_lazy_placeholder_image($processed_image, $cache_path);
            }
            $timing_data['lazy_placeholder'] = microtime(true) - $timing_checkpoint;
            
            $timing_checkpoint = microtime(true);
            // Create JPG fallback for JavaScript lazy loading noscript support
            $this->_create_js_lazy_loading_jpg_fallback($processed_image, $cache_path);
            $timing_data['jpg_fallback'] = microtime(true) - $timing_checkpoint;
            
            $timing_checkpoint = microtime(true);
            // Build Legacy-compatible template variables for cache logging
            $cache_full_url = $this->filesystem_service->getPublicUrl($cache_path, $this->cache_connection_name);
            $template_variables = $this->_build_legacy_compatible_variables($cache_path, $cache_full_url);
            $timing_data['template_variables'] = microtime(true) - $timing_checkpoint;
            
            $timing_checkpoint = microtime(true);
            // Store computed template variables in context for OutputStage and other consumers
            $this->context->set_metadata('computed_template_variables', $template_variables);
            
            $source_path = $this->context->get_metadata_value('source_path', '');
            
            // Use relative path for cache logging (cache_path is already relative)
            $cache_log_success = $this->cache_service->update_cache_log(
                image_path: $cache_path,
                processing_time: $processing_time,
                vars: $template_variables,
                cache_dir: $normalized_cache_dir,
                source_path: $source_path,
                force_update: true,
                using_cache_copy: false,
                connection_name: $this->cache_connection_name
            );
            $timing_data['cache_log_update'] = microtime(true) - $timing_checkpoint;
            
            if ($cache_log_success) {
                // Move detailed success message to debug_log instead of debug_message 
                $this->utilities_service->debug_log('Cache log updated successfully for: ' . $cache_path);
            } else {
                $this->utilities_service->debug_message('WARNING: Cache log update failed for: ' . $cache_path, null, false, 'detailed');
            }
            
            $timing_checkpoint = microtime(true);
            // Store cache info in context - use adapter-aware paths
            $cache_url_local = $this->_get_cache_url($cache_key);
            $cache_url = $this->filesystem_service->getPublicUrl($cache_url_local, $this->cache_connection_name);
            
            // For cache_path metadata, use the full URL when using cloud adapters, 
            // or local filesystem path when using local adapter
            if ($this->cache_connection_name === 'local') {
                $base_path = ee()->config->item('base_path') ?? FCPATH;
                $full_cache_path = rtrim($base_path, '/') . '/' . $cache_path;
            } else {
                // For cloud adapters (S3, etc.), use the public URL as the "path"
                $full_cache_path = $cache_url;
            }
            
            $this->context->set_metadata('cache_path', $full_cache_path);
            $this->context->set_metadata('cache_url', $cache_url);
            
            // File size already set efficiently above during image generation
            // No need to regenerate image content - cache_size is already in metadata
            $timing_data['metadata_finalization'] = microtime(true) - $timing_checkpoint;
            
            $this->utilities_service->debug_log('Successfully saved to cache: ' . $cache_path);
            
            // Calculate save timing
            $save_time = microtime(true) - $save_start_time;
            
            // OPTIMIZED: Single comprehensive cache save message with all key info
            $image_dimensions = $image_size->getWidth() . 'x' . $image_size->getHeight();
            $cache_size_kb = round($cache_file_size / 1024, 1);
            
            $this->utilities_service->debug_log(
                "Cache saved: {$format_quality_info} {$image_dimensions} ({$cache_size_kb} KB) in " . 
                number_format($save_time, 3) . "s"
            );
            
            // Show detailed timing breakdown
            $this->utilities_service->debug_log("Cache save timing breakdown (total: " . number_format($save_time * 1000, 2) . "ms):");
            foreach ($timing_data as $operation => $duration) {
                $this->utilities_service->debug_log("  {$operation}: " . number_format($duration * 1000, 2) . "ms");
            }
            
            $timing_checkpoint = microtime(true);
            // Generate responsive image variants if srcset is enabled
            $this->_generate_responsive_variants($processed_image, $cache_path);
            $timing_data['responsive_variants'] = microtime(true) - $timing_checkpoint;
            
            // Show responsive variants timing if any work was done
            if ($timing_data['responsive_variants'] > 0.001) {
                $this->utilities_service->debug_log("  responsive_variants: " . number_format($timing_data['responsive_variants'] * 1000, 2) . "ms");
            }
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message('debug_cache_saved_failed', $e->getMessage());
            
            // CRITICAL: If cache save fails completely, OutputStage cannot generate valid output
            // Re-throw the exception to indicate pipeline failure
            throw new \Exception('Cache save failed: ' . $e->getMessage(), 0, $e);
        }
    }
    
}