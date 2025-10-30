<?php

/**
 * JCOGS Image Pro - Load Source Pipeline Stage
 * ============================================
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

use JCOGSDesign\JCOGSImagePro\Contracts\FilesystemInterface;
use Imagine\Gd\Imagine;
use Maestroerror\HeicToJpg;

/**
 * Load Source Pipeline Stage
 * 
 * Second stage of the processing pipeline. Loads and validates the source
 * image, handling various formats and error conditions.
 * 
 * Responsibilities:
 * - Load source image from filesystem or URL
 * - Validate image format and integrity
 * - Handle special cases (SVG, animated GIF, etc.)
 * - Set up image objects for processing
 * - Extract basic image metadata (dimensions, format)
 */
class LoadSourceStage extends AbstractStage 
{
    /**
     * @var Imagine Image library instance
     */
    private Imagine $imagine;
    
    /**
     * @var object Image processing service (cached for performance)  
     */
    private $image_processing_service;
    
    /**
     * @var FallbackSourceService Reusable fallback source service
     */
    private $fallback_source_service;
    
    /**
     * OPTIMIZATION: Static cache for common Box objects to avoid repeated creation
     */
    private static array $box_cache = [];
    
    /**
     * OPTIMIZATION: Static cache for dimension calculations
     */
    private static array $dimension_cache = [];
    
    /**
     * @var FallbackSourceService
     */
    private FallbackSourceService $fallback_service;
    
    /**
     * Constructor
     * 
     * Common services are now automatically available via parent AbstractStage.
     * Only initialize stage-specific services here.
     */
    public function __construct(FilesystemInterface $filesystem_service = null) 
    {
        parent::__construct('load_source');
        $this->imagine = new Imagine();
        
        // Override filesystem service if provided (for dependency injection/testing)
        if ($filesystem_service !== null) {
            $this->filesystem_service = $filesystem_service;
        }
        // Otherwise use the one from parent AbstractStage
        
        // Pre-load stage-specific services to avoid repeated instantiation during processing
        $this->image_processing_service = ee('jcogs_img_pro:ImageProcessingService');
        $this->fallback_service = new FallbackSourceService();
    }
    
    /**
     * Process load source stage
     * 
     * @param Context $context Processing context
     * @throws \Exception If source loading fails
     */
    protected function process(Context $context): void 
    {
        $this->utilities_service->debug_message('debug_load_stage_starting', null, false, 'detailed');
        
        // 1. Handle fallback source resolution (mirrors legacy while loop pattern)
        if (!$this->handle_fallback_source_resolution($context)) {
            throw new \Exception("No valid source or fallback found");
        }
        
        $src = $context->get_param('src');
        
        // 2. Handle special image types that don't need full loading
        if ($this->handle_special_image_types($context)) {
            if (function_exists('log_message')) {
                log_message('debug', '[JCOGS Image Pro - LoadSourceStage] Handled as special image type');
            }
            $this->utilities_service->debug_message('debug_load_special_type_handled', null, false, 'detailed');
            return;
        }
        
        // 3. Load the source image (with fallback support)
        $source_image = $this->load_image_file_with_fallback($context);
        
        // Check if this was an animated GIF (returns true instead of image object)
        if ($source_image === true && $context->get_flag('animated_gif')) {
            $this->utilities_service->debug_message('Animated GIF: skipping image object creation and metadata extraction');
            return; // Skip image storage and metadata for animated GIFs
        }
        
        if (!$source_image) {
            throw new \Exception("Failed to load source image or fallback");
        }
        
        // 4. Store source image in context
        $context->set_source_image($source_image);
        
        // 5. Extract and store image metadata
        $this->extract_image_metadata($context, $source_image);
        
        // 6. Apply auto-adjust logic if enabled (mirrors Legacy ValidationTrait auto-adjust)
        $this->apply_auto_adjust_if_needed($context, $source_image);
        
        $this->utilities_service->debug_message('debug_load_stage_completed', null, false, 'detailed');
    }
    
    /**
     * OPTIMIZATION: Get an optimized Box object from cache to avoid repeated creation
     * 
     * @param int $width Width of the box
     * @param int $height Height of the box
     * @return \Imagine\Image\Box
     */
    private function get_optimized_box(int $width, int $height): \Imagine\Image\Box 
    {
        $key = "{$width}x{$height}";
        
        if (!isset(self::$box_cache[$key])) {
            self::$box_cache[$key] = new \Imagine\Image\Box($width, $height);
        }
        
        return self::$box_cache[$key];
    }
    
    /**
     * OPTIMIZATION: Calculate resize dimensions with caching for performance
     * 
     * @param int $orig_width Original width
     * @param int $orig_height Original height
     * @param int $max_dimension Maximum dimension constraint
     * @return array [new_width, new_height, ratio]
     */
    private function calculate_resize_dimensions(int $orig_width, int $orig_height, int $max_dimension): array 
    {
        $cache_key = "{$orig_width}x{$orig_height}_{$max_dimension}";
        
        if (isset(self::$dimension_cache[$cache_key])) {
            return self::$dimension_cache[$cache_key];
        }
        
        $ratio = $max_dimension / max($orig_width, $orig_height);
        $new_width = (int) round($orig_width * $ratio, 0);
        $new_height = (int) round($orig_height * $ratio, 0);
        
        $result = [$new_width, $new_height, $ratio];
        
        // Cache for common dimensions (limit cache size to prevent memory bloat)
        if (count(self::$dimension_cache) < 1000) {
            self::$dimension_cache[$cache_key] = $result;
        }
        
        return $result;
    }

    /**
     * Apply auto-adjust logic if enabled
     * Mirrors Legacy ValidationTrait auto-adjust behavior exactly
     * 
     * @param Context $context Processing context
     * @param object $source_image Source image object
     */
    private function apply_auto_adjust_if_needed(Context $context, $source_image): void 
    {
        // Get settings via cached service call
        $settings = $this->settings_service->get_all();
        
        // Skip auto-adjust for animated GIFs and SVGs (mirrors Legacy conditions)
        if ($context->get_flag('animated_gif') || $context->get_flag('svg')) {
            $this->utilities_service->debug_message('Auto-adjust: Skipping for animated GIF or SVG');
            return;
        }
        
        // Check if auto-adjust is enabled (mirrors Legacy setting check)
        if (!isset($settings['img_cp_enable_auto_adjust']) || 
            substr(strtolower($settings['img_cp_enable_auto_adjust']), 0, 1) !== 'y') {
            $this->utilities_service->debug_message('Auto-adjust: Disabled in settings');
            return;
        }
        
        // Get current image dimensions for pre-check
        $current_size = $source_image->getSize();
        $current_width = $current_size->getWidth();
        $current_height = $current_size->getHeight();
        $original_file_size = $context->get_metadata_value('original_file_size', 0);
        
        // OPTIMIZATION: Quick pre-check to skip unnecessary processing
        $max_dimension = (int)($settings['img_cp_default_max_image_dimension'] ?? 0);
        $max_file_size_mb = (float)($settings['img_cp_default_max_image_size'] ?? 0);
        
        // Quick dimension check
        $dimension_ok = ($max_dimension <= 0 || max($current_width, $current_height) <= $max_dimension);
        
        // Quick size check (rough estimate) 
        $size_ok = ($max_file_size_mb <= 0 || $original_file_size <= ($max_file_size_mb * 1000000));
        
        if ($dimension_ok && $size_ok) {
            // No adjustment needed, skip entirely
            return;
        }
        
        $auto_adjust_time_start = microtime(true);
        $auto_adjust_applied = false;
        $current_image = $source_image;
        
        // Use stored original file size (Legacy pattern) - avoids PNG conversion overhead
        $current_file_size = $original_file_size; // Start with original size
        $current_binary_data = null; // Only calculate when needed
        
        // Determine what adjustments are needed
        $dimension_needs_adjustment = ($max_dimension > 0 && max($current_width, $current_height) > $max_dimension);
        $size_needs_adjustment = (!$dimension_needs_adjustment && $max_file_size_mb > 0 && 
                                  $original_file_size > ($max_file_size_mb * 1000000));
        
        if ($dimension_needs_adjustment) {
            
            try {
                // OPTIMIZATION: Use cached dimension calculation and optimized Box creation
                [$new_width, $new_height, $rescale_ratio] = $this->calculate_resize_dimensions(
                    $current_width, $current_height, $max_dimension
                );
                
                // OPTIMIZATION: Use cached Box object and improved resize filter
                $rescaled_image_box = $this->get_optimized_box($new_width, $new_height);
                $current_image->resize($rescaled_image_box, \Imagine\Image\ImageInterface::FILTER_LANCZOS);
                
                // OPTIMIZATION: Estimate file size based on dimension reduction instead of PNG conversion
                $current_width = $new_width;
                $current_height = $new_height;
                $scale_ratio = ($new_width * $new_height) / ($current_size->getWidth() * $current_size->getHeight());
                $current_file_size = (int)($original_file_size * $scale_ratio);
                
                $auto_adjust_applied = true;
                
            } catch (\Exception $e) {
                $this->utilities_service->debug_message('Auto-adjust dimension rescale failed: ' . $e->getMessage());
                throw new \Exception('Auto-adjust failed to rescale image successfully, probably too large to process');
            }
        }
        
        // Second check: File size limit (mirrors Legacy size check)
        $max_size_mb = (float)($settings['img_cp_default_max_image_size'] ?? 0);
        if ($max_size_mb > 0 && $current_file_size > ($max_size_mb * 1000000)) {
            
            $rescale_ratio = sqrt(($max_size_mb * 1000000) / $current_file_size);
            
            try {
                $new_width = (int) round($current_width * $rescale_ratio, 0);
                $new_height = (int) round($current_height * $rescale_ratio, 0);
                
                // OPTIMIZATION: Use cached Box object and improved resize filter  
                $rescaled_image_box = $this->get_optimized_box($new_width, $new_height);
                $current_image->resize($rescaled_image_box, \Imagine\Image\ImageInterface::FILTER_LANCZOS);
                
                // OPTIMIZATION: Only get binary data now for accurate size after resize
                $current_binary_data = $current_image->get('png');
                $current_file_size = strlen($current_binary_data);
                
                // Update dimensions
                $current_width = $new_width;
                $current_height = $new_height;
                
                $auto_adjust_applied = true;
                
            } catch (\Exception $e) {
                $this->utilities_service->debug_message('Auto-adjust size rescale failed: ' . $e->getMessage());
                throw new \Exception('Auto-adjust failed to rescale image successfully, probably too large to process');
            }
        }
        
        // If auto-adjust was applied, update context with new image and metadata
        if ($auto_adjust_applied) {
            $auto_adjust_elapsed_time = microtime(true) - $auto_adjust_time_start;
            
            // Update source image in context
            $context->set_source_image($current_image);
            
            // Update metadata with new dimensions
            $context->set_metadata('original_width', $current_width);
            $context->set_metadata('original_height', $current_height);
            $context->set_metadata('aspect_ratio', $current_width / $current_height);
            $context->set_metadata('auto_adjust_applied', true);
            $context->set_metadata('auto_adjust_processing_time', $auto_adjust_elapsed_time);
            
            // OPTIMIZATION: Only get binary data once for final success message if not already calculated
            if ($current_binary_data === null) {
                $current_binary_data = $current_image->get('png');
                $current_file_size = strlen($current_binary_data);
            }
            
            // OPTIMIZED: Single comprehensive auto-adjust success message
            $original_dimensions = $context->get_metadata_value('original_width') . 'x' . $context->get_metadata_value('original_height');
            $original_size_mb = $context->get_metadata_value('original_file_size', 0) / 1000000;
            
            $this->utilities_service->debug_message(sprintf(
                "Auto-adjust: %s → %dx%d (%.2f MB → %.2f MB) in %.3fs",
                $original_dimensions,
                $current_width, 
                $current_height,
                $original_size_mb,
                $current_file_size / 1000000,
                $auto_adjust_elapsed_time
            ));
            
            // OPTIMIZATION: Memory cleanup for large operations
            unset($current_binary_data);
            
            // Force garbage collection for large operations (>50MB memory usage)
            if (memory_get_usage() > 50 * 1024 * 1024) {
                gc_collect_cycles();
            }
        }
        // No debug message when no adjustments needed - reduces noise
    }
    
    /**
     * Calculate display dimensions for animated GIFs based on width/height parameters
     * Replicates legacy behavior from _get_new_image_dimensions() for animated GIFs
     * 
     * @param Context $context Processing context
     * @param array $original_dimensions Original GIF dimensions
     */
    private function calculate_animated_gif_display_dimensions(Context $context, array $original_dimensions): void
    {
        $original_width = $original_dimensions['width'];
        $original_height = $original_dimensions['height'];
        $aspect_ratio = $original_height / $original_width;
        
        // Get width and height parameters from context
        $target_width = $context->get_param('width', null);
        $target_height = $context->get_param('height', null);
        
        // Convert parameters to integers if they exist
        $new_width = null;
        $new_height = null;
        
        if ($target_width) {
            $new_width = (int)$target_width;
        }
        if ($target_height) {
            $new_height = (int)$target_height;
        }
        
        // Calculate missing dimension using aspect ratio (legacy behavior)
        $width_is_set = $new_width && $new_width > 0;
        $height_is_set = $new_height && $new_height > 0;
        
        if ($width_is_set && !$height_is_set) {
            // Width is set, Height is not: Calculate Height
            $new_height = round($new_width * $aspect_ratio, 0);
        } elseif (!$width_is_set && $height_is_set) {
            // Height is set, Width is not: Calculate Width  
            $new_width = round($new_height / $aspect_ratio, 0);
        } elseif (!$width_is_set && !$height_is_set) {
            // Neither Width nor Height is set: Use original dimensions
            $new_width = $original_width;
            $new_height = $original_height;
        }
        // If both are set, use them as-is (distort mode)
        
        // Ensure dimensions are positive integers and at least 1px
        $final_width = max(1, (int)round($new_width));
        $final_height = max(1, (int)round($new_height));
        
        // Store the calculated dimensions in metadata for use in OutputStage
        $context->set_metadata('final_width', $final_width);
        $context->set_metadata('final_height', $final_height);
        $context->set_metadata('target_width', $final_width);
        $context->set_metadata('target_height', $final_height);
        
        $this->utilities_service->debug_message("Animated GIF display dimensions calculated: {$final_width}x{$final_height} (from {$original_width}x{$original_height})");
    }
    
    /**
     * Get the local connection name for source image loading
     * Source images should always be loaded from the local filesystem
     * 
     * @return string Local connection name (e.g., 'legacy_local')
     */
    private function getLocalConnectionName(): string
    {
        // Get all named connections from settings
        $connections = $this->settings_service->getAllNamedConnections();
        
        // Look for the default connection first
        $default_connection = $this->settings_service->get_default_connection_name();
        if (!empty($default_connection)) {
            return $default_connection;
        }
        
        // Fallback to first local connection found
        foreach ($connections as $name => $connection) {
            if ($connection['type'] === 'local') {
                return $name;
            }
        }
        
        // Ultimate fallback to legacy naming
        return 'legacy_local';
    }
    
    /**
     * Convert URL to relative path for FilesystemService
     * 
     * @param string $url URL or path to convert
     * @return string|null Relative path or null if external URL
     */
    private function convert_url_to_relative_path(string $url): ?string
    {
        // If it's already a relative path, return as-is
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return ltrim($url, '/');
        }
        
        // Parse the URL
        $parsed_url = parse_url($url);
        if (!$parsed_url || !isset($parsed_url['host']) || !isset($parsed_url['path'])) {
            return null;
        }
        
        // Get the site URL to compare domains
        $site_url = ee()->config->item('site_url');
        $site_parsed = parse_url($site_url);
        
        // Check if this is a local URL (same domain)
        if (isset($site_parsed['host']) && $parsed_url['host'] === $site_parsed['host']) {
            // This is a local URL, extract the path
            return ltrim($parsed_url['path'], '/');
        }
        
        // External URL - can't convert to relative path
        return null;
    }
    
    /**
     * Detect HEIC format from binary data
     * Based on HEIC magic number detection
     * 
     * @param string $image_data Binary image data
     * @return bool True if HEIC format detected
     */
    private function detect_heic(string $image_data): bool 
    {
        if (strlen($image_data) < 20) {
            return false;
        }
        
        // HEIC files have "ftyp" at bytes 4-7 and brand identifier at bytes 8-11
        $ftyp_marker = substr($image_data, 4, 4);
        if ($ftyp_marker !== 'ftyp') {
            return false;
        }
        
        // HEIC magic numbers - check at byte positions 8-11 for brand
        $heic_magic_numbers = [
            'heic', // official
            'heix', // variant
            'heim', // variant
            'heis', // variant
            'hevm', // variant
            'hevs', // variant
            'mif1', // variant
            'msf1'  // variant
        ];
        
        $magic_number = substr($image_data, 8, 4);
        return in_array($magic_number, $heic_magic_numbers);
    }
    
    /**
     * Extract dimensions from GIF header for animated GIFs
     * 
     * @param string $gif_data Raw GIF data
     * @return array|null Dimensions array with width/height or null
     */
    private function extract_gif_dimensions(string $gif_data): ?array
    {
        // GIF dimensions are stored at bytes 6-9 (width) and 8-11 (height)
        // as little-endian 16-bit integers
        if (strlen($gif_data) < 10) {
            return null;
        }
        
        // Check for valid GIF header
        if (substr($gif_data, 0, 6) !== 'GIF87a' && substr($gif_data, 0, 6) !== 'GIF89a') {
            return null;
        }
        
        // Extract width and height (little-endian)
        $width = unpack('v', substr($gif_data, 6, 2))[1];
        $height = unpack('v', substr($gif_data, 8, 2))[1];
        
        return [
            'width' => $width,
            'height' => $height
        ];
    }
    
    /**
     * Extract basic metadata from loaded image
     * 
     * @param Context $context
     * @param mixed $image Image object
     */
    private function extract_image_metadata(Context $context, $image): void 
    {
        try {
            $size = $image->getSize();
            
            $context->set_metadata('original_width', $size->getWidth());
            $context->set_metadata('original_height', $size->getHeight());
            $context->set_metadata('aspect_ratio', $size->getWidth() / $size->getHeight());
            $context->set_metadata('image_type', 'raster');
            
            $this->utilities_service->debug_log(sprintf(
                'Image metadata: %dx%d, aspect ratio: %.2f',
                $size->getWidth(),
                $size->getHeight(),
                $size->getWidth() / $size->getHeight()
            ));
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Failed to extract image metadata: " . $e->getMessage());
            // Set default values
            $context->set_metadata('original_width', 0);
            $context->set_metadata('original_height', 0);
            $context->set_metadata('aspect_ratio', 1.0);
            $context->set_metadata('image_type', 'unknown');
        }
    }
    
    /**
     * Handle fallback source resolution using Legacy while loop pattern
     * Mirrors legacy JcogsImage::initialise() fallback loop logic exactly
     * 
     * @param Context $context Processing context
     * @return bool True if valid source resolved, false otherwise
     */
    private function handle_fallback_source_resolution(Context $context): bool
    {
        // Check for color fill mode first (mirrors legacy Stage 3)
        if ($this->fallback_service->setup_color_fill_mode($context)) {
            return true; // Early return for color fill
        }
        
        // Get the primary source
        $source_to_process = $context->get_param('src', '');
        $is_fallback_attempt = false;
        
        // Mirror the Legacy while(true) loop - try primary first, fallback on failure
        while (true) {
            // If source is empty, try fallbacks
            if (empty($source_to_process)) {
                if (!$is_fallback_attempt) {
                    // Try fallback_src parameter
                    $fallback_src = $context->get_param('fallback_src', '');
                    if (!empty($fallback_src)) {
                        $this->utilities_service->debug_message('Primary source empty/failed, trying fallback_src', $fallback_src, false, 'detailed');
                        $source_to_process = $fallback_src;
                        $is_fallback_attempt = true;
                        continue; // Try again with fallback
                    }
                }
                
                // No fallback available
                $this->utilities_service->debug_message('No valid source or fallback_src found', null, false, 'detailed');
                return false;
            }
            
            // We have a source to try - update context and return success
            $context->set_param('src', $source_to_process);
            $context->set_metadata('is_fallback_source', $is_fallback_attempt);
            $context->set_metadata('fallback_type', $is_fallback_attempt ? 'parameter' : 'primary');
            
            // Update filename from source
            $this->update_filename_from_source($context, $source_to_process, $is_fallback_attempt);
            
            $this->utilities_service->debug_message('Source resolved for loading', $source_to_process, false, 'detailed');
            return true; // Let the load_image_file method handle the actual validation
        }
    }
    
    /**
     * Handle special image types (SVG, color fills, etc.)
     * 
     * @param Context $context
     * @return bool True if handled as special case
     */
    private function handle_special_image_types(Context $context): bool 
    {
        // Handle SVG images
        if ($context->get_flag('svg')) {
            $this->utilities_service->debug_message('Detected SVG image - using passthrough mode');
            $context->set_metadata('image_type', 'svg');
            return true;
        }
        
        // Handle color fill mode
        if ($context->get_flag('use_colour_fill')) {
            $this->utilities_service->debug_message('Color fill mode - no source image needed');
            $context->set_metadata('image_type', 'color_fill');
            return true;
        }
        
        return false;
    }
    
    /**
     * Update filename metadata based on resolved source
     * Mirrors legacy filename extraction logic
     * 
     * @param Context $context Processing context
     * @param string $source Resolved source path/URL
     * @param bool $is_fallback Whether this is a fallback source
     */
    private function update_filename_from_source(Context $context, string $source, bool $is_fallback): void
    {
        $parsed_url = parse_url($source);
        
        if (isset($parsed_url['path']) && !empty($parsed_url['path'])) {
            $filename = pathinfo($parsed_url['path'], PATHINFO_FILENAME);
        } else {
            // Generate fallback filename with hash for uniqueness
            $fallback_prefix = $is_fallback ? 'fallback_' : 'no_path_';
            $filename = $fallback_prefix . hash('tiger160,3', time() . rand(0, 1000));
        }
        
        $context->set_metadata('orig_filename', $filename);
        
        $this->utilities_service->debug_message('Filename extracted from source', $filename, false, 'detailed');
    }
    
    /**
     * Load image file from filesystem or URL
     * Uses existing jcogs_img logic for handling local vs remote files
     * 
     * @param string $src Image source path
     * @param Context $context Processing context for metadata storage
     * @return mixed|null Image object or null if loading fails
     */
    private function load_image_file(string $src, Context $context) 
    {
        try {
            // Try the original path first - parse URL to get relative path for Pro FilesystemService
            $this->utilities_service->debug_log("Attempting to load image from original path: {$src}");
            
            $image_data = null;
            $relative_path = $this->convert_url_to_relative_path($src);
            
            if ($relative_path) {
                // Use Pro FilesystemService for local files - get the correct local connection name
                try {
                    $local_connection = $this->getLocalConnectionName();
                    $image_content = $this->filesystem_service->getImageContent($relative_path, $local_connection);
                    $image_data = ['image_source' => $image_content];
                    $this->utilities_service->debug_log("Pro FilesystemService loaded image successfully using connection '{$local_connection}': {$relative_path}");
                } catch (\Exception $e) {
                    $this->utilities_service->debug_log("Pro FilesystemService failed with connection '{$local_connection}': " . $e->getMessage());
                    $image_data = null;
                }
            } else {
                // For external URLs, we need to handle them differently
                $this->utilities_service->debug_log("External URL detected, attempting remote fetch: {$src}");
                try {
                    $remote_content = $this->filesystem_service->getFileFromRemote($src);
                    if ($remote_content) {
                        $image_data = ['image_source' => $remote_content];
                        $this->utilities_service->debug_log("Remote fetch successful");
                    }
                } catch (\Exception $e) {
                    $this->utilities_service->debug_log("Remote fetch failed: " . $e->getMessage());
                    $image_data = null;
                }
            }
            
            // If that fails and the path contains URL encoding, try the decoded version
            if ((!$image_data || !isset($image_data['image_source'])) && preg_match('/%[0-9A-Fa-f]{2}/', $src)) {
                $decoded_src = urldecode($src);
                $this->utilities_service->debug_log("Original path failed, trying URL decoded path: {$decoded_src}");
                
                $decoded_relative_path = $this->convert_url_to_relative_path($decoded_src);
                if ($decoded_relative_path) {
                    try {
                        $local_connection = $this->getLocalConnectionName();
                        $image_content = $this->filesystem_service->getImageContent($decoded_relative_path, $local_connection);
                        $image_data = ['image_source' => $image_content];
                        $this->utilities_service->debug_log("Decoded URL loaded successfully using connection '{$local_connection}': {$decoded_relative_path}");
                    } catch (\Exception $e) {
                        $this->utilities_service->debug_log("Decoded URL failed with connection '{$local_connection}': " . $e->getMessage());
                    }
                }
            }
            
            if (!$image_data || !isset($image_data['image_source'])) {
                $this->utilities_service->debug_message('debug_load_source_not_found', $src, false, 'detailed');
                return null;
            }
            
            $this->utilities_service->debug_log("Successfully retrieved image data, size: " . strlen($image_data['image_source']) . " bytes");
            
            // Load image from the content we retrieved
            $image_source = $image_data['image_source'];
            
            // Check for animated GIF BEFORE processing (use simple file extension check first)
            $parsed_url = parse_url($src);
            $is_potential_gif = false;
            if (isset($parsed_url['path'])) {
                $file_extension = strtolower(pathinfo($parsed_url['path'], PATHINFO_EXTENSION));
                $is_potential_gif = ($file_extension === 'gif');
                $this->utilities_service->debug_log("GIF Detection: extension=$file_extension, is_potential_gif=" . ($is_potential_gif ? 'yes' : 'no'));
                
                // Use the standard log_message function to ensure our debug messages appear
                if (function_exists('log_message')) {
                    log_message('debug', '[JCOGS Image Pro] GIF Detection Debug: src=' . $src . ', extension=' . $file_extension);
                }
            }
            
            if ($is_potential_gif) {
                $image_processing_service = $this->image_processing_service;
                $is_animated = $image_processing_service->is_animated_gif($image_source);
                $this->utilities_service->debug_log("GIF Animation Check: is_animated=" . ($is_animated ? 'yes' : 'no'));
                
                if ($is_animated) {
                    $this->utilities_service->debug_log('Detected animated GIF - enabling passthrough mode');
                    $context->set_flag('animated_gif', true);
                    $context->set_metadata('image_type', 'animated_gif');
                    
                    // Store raw image data for direct copy
                    $context->set_metadata('image_source_raw', $image_source);
                    
                    // Extract dimensions from GIF header for output
                    $dimensions = $this->extract_gif_dimensions($image_source);
                    $this->utilities_service->debug_log("GIF dimensions: " . ($dimensions ? $dimensions['width'] . 'x' . $dimensions['height'] : 'extraction failed'));
                    if ($dimensions) {
                        $context->set_metadata('original_width', $dimensions['width']);
                        $context->set_metadata('original_height', $dimensions['height']);
                        $context->set_metadata('aspect_ratio', $dimensions['height'] / $dimensions['width']);
                        
                        // Calculate display dimensions for animated GIFs based on width/height parameters
                        // This replicates legacy behavior where animated GIFs get properly scaled dimensions
                        $this->calculate_animated_gif_display_dimensions($context, $dimensions);
                    }
                    
                    // Do NOT create an Imagine image object for animated GIFs
                    // Legacy approach: store raw data only, never load with Imagine
                    // This preserves the animation and avoids the getSize() error
                    $this->utilities_service->debug_message('Animated GIF: skipping Imagine image object creation (preserves animation)');
                    
                    // Return early - no need for further processing for animated GIFs
                    return true; // Signal that loading completed (special case)
                } else {
                    $this->utilities_service->debug_message('GIF detected but not animated - processing normally');
                    // Continue with normal processing for non-animated GIFs
                }
            }
            
            // Check for HEIC format and convert if necessary BEFORE trying Imagine
            $was_heic_converted = false;
            if ($this->detect_heic($image_source)) {
                $this->utilities_service->debug_message('HEIC format detected - converting to JPEG');
                
                try {
                    // PERFORMANCE OPTIMIZATION: Use original file path directly (like Legacy)
                    // This eliminates temporary file creation overhead for better performance
                    $relative_path = $this->convert_url_to_relative_path($src);
                    
                    if ($relative_path) {
                        // Try direct conversion from original path first (most efficient)
                        $file_result = $this->filesystem_service->get_a_local_copy_of_image($relative_path);
                        if ($file_result && isset($file_result['local_path'])) {
                            $converted_data = HeicToJpg::convert($file_result['local_path'])->get();
                            
                            if ($converted_data) {
                                $image_source = $converted_data;
                                $was_heic_converted = true;
                                $this->utilities_service->debug_message('HEIC conversion successful (direct path method)');
                            }
                        }
                    }
                    
                    // Fallback to temp file method only if direct path fails
                    if (!$was_heic_converted) {
                        $temp_file = tempnam(sys_get_temp_dir(), 'heic_');
                        file_put_contents($temp_file, $image_source);
                        $converted_data = HeicToJpg::convert($temp_file)->get();
                        unlink($temp_file);
                        
                        if ($converted_data) {
                            $image_source = $converted_data;
                            $was_heic_converted = true;
                            $this->utilities_service->debug_message('HEIC conversion successful (fallback temp file method)');
                        }
                    }
                } catch (\Exception $e) {
                    $this->utilities_service->debug_message('Primary HEIC conversion failed: ' . $e->getMessage());
                    
                    try {
                        // Try Mac-specific conversion as fallback (use same optimized approach)
                        $relative_path = $this->convert_url_to_relative_path($src);
                        
                        if ($relative_path && !$was_heic_converted) {
                            $file_result = $this->filesystem_service->get_a_local_copy_of_image($relative_path);
                            if ($file_result && isset($file_result['local_path'])) {
                                $converted_data = HeicToJpg::convertOnMac($file_result['local_path'], "arm64")->get();
                                
                                if ($converted_data) {
                                    $image_source = $converted_data;
                                    $was_heic_converted = true;
                                    $this->utilities_service->debug_message('HEIC conversion successful (Mac method - direct path)');
                                }
                            }
                        }
                        
                        // Final fallback to temp file for Mac method
                        if (!$was_heic_converted) {
                            $temp_file = tempnam(sys_get_temp_dir(), 'heic_');
                            file_put_contents($temp_file, $image_source);
                            $converted_data = HeicToJpg::convertOnMac($temp_file, "arm64")->get();
                            unlink($temp_file);
                            
                            if ($converted_data) {
                                $image_source = $converted_data;
                                $was_heic_converted = true;
                                $this->utilities_service->debug_message('HEIC conversion successful (Mac method - temp file fallback)');
                            }
                        }
                    } catch (\Exception $e_mac) {
                        $this->utilities_service->debug_message('Mac HEIC conversion also failed: ' . $e_mac->getMessage());
                        throw new \Exception('HEIC conversion failed: ' . $e_mac->getMessage());
                    }
                }
            }
            
            $this->utilities_service->debug_log("Attempting to load image with Imagine, data size: " . strlen($image_source) . " bytes");
            $image = $this->imagine->load($image_source);
            
            // Store original file size for efficient auto-adjust (avoids PNG conversion)
            $context->set_metadata('original_file_size', strlen($image_source));
            
            // Store HEIC conversion status for later use
            if ($was_heic_converted) {
                $context->set_metadata('was_heic_converted', true);
                $context->set_metadata('original_format', 'heic');
            }
            
            $this->utilities_service->debug_message('debug_load_source_loaded', 
                $image->getSize()->getWidth(), 
                $image->getSize()->getHeight()
            );
            
            return $image;
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Failed to load image {$src}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Load image file from filesystem or URL with fallback support
     * Mirrors the Legacy while loop pattern for loading with fallbacks
     * 
     * @param Context $context Processing context
     * @return mixed|null Image object or null if loading fails
     */
    private function load_image_file_with_fallback(Context $context) 
    {
        $source_to_process = $context->get_param('src', '');
        $is_fallback_attempt = false;
        $tried_system_fallback = false;
        
        $this->utilities_service->debug_message('Starting load_image_file_with_fallback', 
            "Initial source: {$source_to_process}, fallback_src: " . $context->get_param('fallback_src', 'none'), false, 'detailed');
        
        while (true) {
            $this->utilities_service->debug_message('Load loop iteration', 
                "source_to_process: " . ($source_to_process ?: 'empty') . 
                ", is_fallback_attempt: " . ($is_fallback_attempt ? 'yes' : 'no') . 
                ", tried_system_fallback: " . ($tried_system_fallback ? 'yes' : 'no'), false, 'detailed');
            
            // If source is empty, try fallbacks
            if (empty($source_to_process)) {
                if (!$is_fallback_attempt) {
                    // Try fallback_src parameter first
                    $fallback_src = $context->get_param('fallback_src', '');
                    if (!empty($fallback_src)) {
                        $this->utilities_service->debug_message('Primary failed, attempting fallback_src', $fallback_src, false, 'detailed');
                        $source_to_process = $fallback_src;
                        $is_fallback_attempt = true;
                        
                        // Update context for fallback
                        $context->set_param('src', $source_to_process);
                        $context->set_metadata('is_fallback_source', true);
                        $context->set_metadata('fallback_type', 'parameter');
                        $this->update_filename_from_source($context, $source_to_process, true);
                        continue; // Try loading with fallback
                    } else {
                        // No fallback_src parameter, but mark as fallback attempt to try system fallback
                        $this->utilities_service->debug_message('No fallback_src parameter, proceeding to system fallback', null, false, 'detailed');
                        $is_fallback_attempt = true;
                        continue; // Continue to system fallback logic
                    }
                } elseif (!$tried_system_fallback) {
                    $this->utilities_service->debug_message('Checking system default fallback', null, false, 'detailed');
                    // Try system default fallback (mirrors Legacy _evaluate_default_image_options)
                    $system_fallback = $this->fallback_service->resolve_system_default_fallback($context);
                    if ($system_fallback && $system_fallback['fallback_type'] !== 'color_fill') {
                        $this->utilities_service->debug_message('Parameter fallback failed, attempting system default', $system_fallback['source'], false, 'detailed');
                        $source_to_process = $system_fallback['source'];
                        $tried_system_fallback = true;
                        
                        // Update context for system fallback
                        $context->set_param('src', $source_to_process);
                        $context->set_metadata('is_fallback_source', true);
                        $context->set_metadata('fallback_type', $system_fallback['fallback_type']);
                        $this->update_filename_from_source($context, $source_to_process, true);
                        continue; // Try loading with system fallback
                    } else {
                        $this->utilities_service->debug_message('System fallback not available or color fill', null, false, 'detailed');
                    }
                }
                
                // No more options
                $this->utilities_service->debug_message('No valid source, fallback_src, or system fallback available', null, false, 'detailed');
                return null;
            }
            
            // Try to load the current source
            $this->utilities_service->debug_message('Attempting to load source', $source_to_process, false, 'detailed');
            $image = $this->load_image_file($source_to_process, $context);
            if ($image) {
                // Success!
                $this->utilities_service->debug_message('Image loaded successfully', $source_to_process, false, 'detailed');
                return $image;
            } else {
                // Failed - set source to empty to trigger fallback attempt
                $this->utilities_service->debug_message('Failed to load source, will try fallback', $source_to_process, false, 'detailed');
                $source_to_process = null;
            }
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
        // Skip if we already have a critical error
        return $context->has_critical_error();
    }
    
    /**
     * Validate if a source is loadable (mirrors Legacy image loading validation)
     * 
     * @param string $source Source path or URL to validate
     * @return bool True if source can be loaded
     */
}
