<?php

/**
 * JCOGS Image Pro - Output Pipeline Stage
 * =======================================
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
use Imagine\Gd\Imagine;
use JCOGSDesign\JCOGSImagePro\Service\Pipeline\ResponsiveImageService;
use JCOGSDesign\JCOGSImagePro\Service\Pipeline\OutputGenerationService;

/**
 * Output Pipeline Stage
 * 
 * Final stage of the processing pipeline. Generates the final HTML output
 * for the template, including IMG tags, variables, and metadata.
 * 
 * Responsibilities:
 * - Generate HTML IMG tags with lazy loading support
 * - Create responsive image attributes (srcset/sizes)
 * - Create template variables for pair tags
 * - Format output based on tag type
 * - Handle custom attributes and performance optimizations
 * - Generate final output string
 */
class OutputStage extends AbstractStage 
{
    /**
     * @var ResponsiveImageService
     */
    private ResponsiveImageService $responsive_service;
    
    /**
     * @var OutputGenerationService
     */
    private OutputGenerationService $output_generation_service;

    private ?Context $context = null;

    /**
     * @var mixed Image utilities service
     */
    private $image_utilities;
    
    /**
     * Constructor
     * 
     * Common services are now automatically available via parent AbstractStage.
     * Only initialize stage-specific services here.
     */
    public function __construct() 
    {
        parent::__construct('output');
        
        // Initialize stage-specific services that are not in common service cache
        $this->responsive_service = ee('jcogs_img_pro:ResponsiveImageService');
        $this->output_generation_service = ee('jcogs_img_pro:OutputGenerationService');
        $this->image_utilities = ee('jcogs_img_pro:ImageUtilities');
        
        // Common services like utilities, filesystem_service, etc. are available via parent
        // Note: lazy_loading_service accessed via helper method to prevent circular dependency
    }
    
    /**
     * Process output generation stage
     * 
     * @param Context $context Processing context
     * @throws \Exception If output generation fails
     */
    protected function process(Context $context): void 
    {
        // Cache context for this stage
        $this->context = $context;
        
        try {
            $output_start_time = microtime(true);
            
            $this->utilities_service->debug_message('debug_output_stage_starting', null, false, 'detailed');
            
            // User-friendly debug message (skip for fast cache hits to avoid duplication)
            if (!$context->get_flag('early_cache_hit')) {
                $this->utilities_service->debug_message(lang('jcogs_img_debug_post_processing_start'));
            }
            
            // 1. Check if output was already generated (e.g., SVG passthrough)
            if (!empty($context->get_output())) {
                $this->utilities_service->debug_message('debug_output_already_generated', null, false, 'detailed');
                return;
            }
        
        // 2. Determine output type based on context
        $output_type = $this->_determine_output_type();
        
        // 3. Generate appropriate output
        switch ($output_type) {
            case 'act_image_serve':
                $this->_serve_act_image();
                return; // Exit immediately after serving image data
                
            case 'img_tag':
                $output = $this->_generate_img_tag();
                break;
                
            case 'pair_variables':
                $output = $this->_generate_pair_variables();
                break;
                
            case 'palette_variables':
                $output = $this->_generate_palette_variables();
                break;
                
            case 'url_only':
                $output = $this->_generate_url_only();
                break;
                
            case 'no_output':
                // create_tag='n' - return empty output (Legacy line 963: "do nothing")
                $output = '';
                break;
                
            default:
                $output = $this->_generate_img_tag();
        }
        
        // 4. Set final output
        $context->set_output($output);
        
        $this->utilities_service->debug_message('debug_output_stage_completed', null, false, 'detailed');
        
        // User-friendly debug messages matching legacy format
        $post_process_time = microtime(true) - $output_start_time;
        $this->utilities_service->debug_message(lang('jcogs_img_debug_post_processing_complete'), [number_format($post_process_time, 3)], false, 'detailed');
        
        $this->utilities_service->debug_message(lang('jcogs_img_debug_generating_outputs'), null, false, 'detailed');
        
        $generation_start_time = microtime(true);
        // Add minimal time for generation (legacy shows this as ~0.0000 seconds)
        $generation_time = microtime(true) - $generation_start_time;
        $this->utilities_service->debug_message(lang('jcogs_img_debug_generation_complete'), [number_format($generation_time, 3)], false, 'detailed');
        } finally {
            // Clean up context reference
            $this->context = null;
        }
    }
    
    /**
     * Apply image path prefix to URL (Sprint 3 Phase 2)
     * 
     * Adds the image_path_prefix parameter to the URL construction,
     * mirroring legacy path handling behavior from the original addon.
     * 
     * @param string $base_url Original image URL
     * @return string URL with prefix applied
     */
    private function _apply_image_path_prefix(string $base_url): string
    {
        $path_prefix = $this->context->get_param('image_path_prefix', '');
        
        if (!empty($path_prefix)) {
            // Ensure prefix has proper leading/trailing slashes
            $path_prefix = '/' . trim($path_prefix, '/') . '/';
            
            // Insert prefix into URL construction
            $parsed_url = parse_url($base_url);
            $path = $parsed_url['path'] ?? '';
            $parsed_url['path'] = $path_prefix . ltrim($path, '/');
            
            return $this->_rebuild_url($parsed_url);
        }
        
        return $base_url;
    }
    
    /**
     * Apply lazy loading filter to cached image (simplified version for cache use)
     * 
     * @param \Imagine\Image\ImageInterface $image Source image
     * @param string $filter_name Filter to apply
     * @return \Imagine\Image\ImageInterface|null Filtered image
     */
    private function _apply_lazy_filter_for_cache(\Imagine\Image\ImageInterface $image, string $filter_name): ?\Imagine\Image\ImageInterface
    {
        try {
            switch ($filter_name) {
                case 'lqip':
                    $filter = new \JCOGSDesign\JCOGSImagePro\Filters\Lqip();
                    return $filter->apply($image);
                    
                case 'dominant_color':
                    $filter = new \JCOGSDesign\JCOGSImagePro\Filters\DominantColor();
                    return $filter->apply($image);
                    
                default:
                    return null;
            }
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Error applying lazy filter for cache: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Build comprehensive Legacy-compatible template variables
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
        $final_width = (int) $this->context->get_metadata_value('final_width', 0);
        $final_height = (int) $this->context->get_metadata_value('final_height', 0);
        
        // Calculate aspect ratios
        $aspect_ratio_orig = $original_width > 0 ? ($original_height / $original_width) : 0;
        $aspect_ratio = $final_width > 0 ? ($final_height / $final_width) : 0;
        
        // Get file extensions
        $extension_orig = pathinfo($src_path, PATHINFO_EXTENSION);
        $extension = pathinfo($cache_local_path, PATHINFO_EXTENSION);
        
        // Get file names
        $name_orig = pathinfo($src_path, PATHINFO_FILENAME);
        $name = pathinfo($cache_local_path, PATHINFO_FILENAME);
        
        // Get filesizes using fast memory-based methods (avoiding filesystem access)
        
        // Try to get processed image filesize from memory object first (fastest method)
        $processed_filesize_bytes = 0;
        $processed_filesize = '';
        
        // First try: Get from processed image object in memory (most efficient)
        $processed_image = $this->context->get_processed_image();
        if ($processed_image && is_object($processed_image) && method_exists($processed_image, 'get')) {
            try {
                $save_as = $this->context->get_param('save_as', 'jpg');

                // Use quality options computed and stored during CacheStage
                $quality_options = $this->context->get_metadata_value('quality_options', ['quality' => 85]);

                // Debug message for save format and quality
                $quality_display = isset($quality_options['webp_lossless']) && $quality_options['webp_lossless'] ? 'lossless' : 
                    (isset($quality_options['avif_lossless']) && $quality_options['avif_lossless'] ? 'lossless' : 
                    $quality_options['quality']);
                $this->utilities_service->debug_message("Saving image as {$save_as} with quality: {$quality_display}");
                
                $image_content = $processed_image->get($save_as, $quality_options);
                $processed_filesize_bytes = strlen($image_content);
                $processed_filesize = $this->image_utilities->format_file_size($processed_filesize_bytes);
            } catch (\Exception $e) {
                // Could not get processed file size from memory object
            }
        }
        
        // Second try: Get from cache metadata if available (also fast)
        if ($processed_filesize_bytes === 0) {
            $processed_filesize_bytes = $this->context->get_metadata_value('cache_size_bytes', 0);
            $processed_filesize = $processed_filesize_bytes > 0 ? $this->image_utilities->format_file_size($processed_filesize_bytes) : '';
        }
        
        // Get original image filesize from metadata (should already be stored)
        $original_filesize_bytes = $this->context->get_metadata_value('original_filesize_bytes', 0);
        $original_filesize = $original_filesize_bytes > 0 ? $this->image_utilities->format_file_size($original_filesize_bytes) : '';
        
        // If we still don't have original filesize, try from raw image data in memory
        if ($original_filesize_bytes <= 0) {
            $source_image_raw = $this->context->get_metadata_value('image_source_raw', '');
            if ($source_image_raw) {
                $original_filesize_bytes = strlen($source_image_raw);
                $original_filesize = $this->image_utilities->format_file_size($original_filesize_bytes);
            }
        }
        
        // Get lazy loading placeholder URL if applicable
        $lazy_image_url = '';
        if ($this->_get_lazy_loading_service()->is_lazy_loading_enabled($this->context)) {
            $mode = $this->_get_lazy_loading_service()->get_lazy_loading_mode($this->context);
            if ($mode && $this->_get_lazy_loading_service()->requires_placeholder($mode)) {
                $lazy_image_url = $this->_generate_placeholder_url($mode);
            }
        }
        
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
        
        // Build comprehensive variable set matching Legacy
        return [
            // Core processed image variables
            'made' => $cache_local_path,
            'made_url' => $cache_full_url,
            'made_with_prefix' => $cache_local_path, // For prefix support (future enhancement)
            'url' => $cache_local_path,              // Legacy compatibility
            'src' => $cache_local_path,              // Legacy compatibility
            
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
            'path' => $cache_local_path,
            'type' => $extension,
            'filesize' => $processed_filesize,
            'filesize_bytes' => $processed_filesize_bytes,
            'mime_type' => $mime_type,
            
            // File information - original
            'extension_orig' => $extension_orig,
            'name_orig' => $name_orig,
            'path_orig' => $src_path,
            'orig' => $src_path,
            'orig_url' => $src_path, // May need URL conversion in future
            'type_orig' => $extension_orig,
            'filesize_orig' => $original_filesize,
            'filesize_bytes_orig' => $original_filesize_bytes,
            
            // Template attributes and metadata
            'attributes' => $attributes_string,
            'lazy_image' => $lazy_image_url,
            
            // EE File Manager fields (placeholder support)
            'img_credit' => $file_manager_fields['credit'] ?? '',
            'img_description' => $file_manager_fields['description'] ?? '',
            'img_location' => $file_manager_fields['location'] ?? '',
            'img_title' => $file_manager_fields['title'] ?? '',
            
            // Cache and processing metadata
            'cache_key' => $this->context->get_cache_key(),
            'using_cache' => $this->context->get_flag('using_cache_copy') ? 'yes' : 'no'
        ];
    }
    
    /**
     * Convert cache URL to filesystem path
     * 
     * @param string $cache_url Cache URL (local path)
     * @return string Filesystem path for the cache file
     */
    private function _convert_url_to_cache_path(string $cache_url): string
    {
        // Use shared utility method for proper URL to path conversion
        return Utilities::convert_url_to_relative_path($cache_url);
    }
    
    /**
     * Determine what type of output to generate
     * 
     * @return string Output type
     */
    private function _determine_output_type(): string 
    {
        $tag_data = $this->context->get_tag_data();
        
        // Check if this is an ACT request first (highest priority)
        $validation_service = \JCOGSDesign\JCOGSImagePro\Service\ServiceCache::validation();
        if ($validation_service->is_act_processing()) {
            return 'act_image_serve';
        }
        
        // Check if this is a palette tag
        $called_by = $this->context->get_flag('called_by') ?: '';
        if ($called_by === 'Palette_Tag') {
            return 'palette_variables';
        }
        
        // Check for url_only parameter
        $url_only = $this->context->get_param('url_only', '');
        if (strtolower($url_only) === 'yes' || strtolower($url_only) === 'y') {
            return 'url_only';
        }
        
        // If there's tag data, it's a pair tag
        if (!empty($tag_data)) {
            // Check create_tag parameter for pair tags (Legacy line 957: create_tag != 'y')
            $create_tag = $this->context->get_param('create_tag', '');
            if (substr(strtolower($create_tag), 0, 1) === 'n') {
                return 'pair_variables'; // Parse tagdata only, no IMG tag
            }
            return 'pair_variables';
        }
        
        // Check if single tag has output parameter
        // Single tag with output param should parse variables like pair tag
        $output = $this->context->get_param('output', '');
        if (!empty($output) && $output !== 'tag') {
            return 'pair_variables'; // Use pair_variables to parse the output content
        }
        
        // Check create_tag parameter for single tags
        $create_tag = $this->context->get_param('create_tag', '');
        if (substr(strtolower($create_tag), 0, 1) === 'n') {
            // create_tag = 'n' so return no output
            return 'no_output';
        }
        
        // Default to IMG tag (Legacy line 966: create_tag == 'y' or single tag with no create_tag)
        return 'img_tag';
    }
    
    /**
     * Ensure lazy placeholder exists, generate if missing (Legacy pattern)
     * 
     * This method replicates Legacy JcogsImage::_generate_lazy_placeholder_image() behavior:
     * - Build expected placeholder filename and path
     * - Check if placeholder exists in cache
     * - If not, load main cached image and generate placeholder using filters
     * - Return placeholder URL
     * 
     * @param string $filter_name Filter name (lqip, dominant_color)
     * @return string Placeholder URL or fallback data URL
     */
    private function _ensure_lazy_placeholder_exists(string $filter_name): string
    {
        try {
            // Get main image cache info
            $cache_url = $this->context->get_metadata_value('cache_url', '');
            if (empty($cache_url)) {
                // No main cache URL available, use fallback
                return $this->_get_fallback_placeholder_url($filter_name);
            }
            
            // Build expected placeholder path and URL
            $cache_path_info = pathinfo($cache_url);
            $placeholder_filename = $cache_path_info['filename'] . '_' . $filter_name . '.' . $cache_path_info['extension'];
            $placeholder_url = $cache_path_info['dirname'] . '/' . $placeholder_filename;
            
            // First check cache_log for placeholder existence (following project rules)
            $cache_management_service = $this->cache_service;
            $placeholder_cache_path = $this->_convert_url_to_cache_path($placeholder_url);
            
            // Try to load placeholder from cache_log first
            $placeholder_cache_data = $cache_management_service->load_cached_image_data($placeholder_cache_path);
            
            if ($placeholder_cache_data && !empty($placeholder_cache_data['cache_url'])) {
                // Found in cache_log, return URL
                $this->utilities_service->debug_message("Found lazy placeholder in cache_log: {$placeholder_filename}", null, false, 'detailed');
                return $placeholder_cache_data['cache_url'];
            }
            
            // Not in cache_log, check filesystem as fallback (project rules compliance)
            $filesystem = $this->filesystem_service;
            if ($filesystem->exists($placeholder_cache_path)) {
                // Placeholder exists on disk but not in cache_log, return URL
                $this->utilities_service->debug_message("Found lazy placeholder on filesystem (not in cache_log): {$placeholder_filename}", null, false, 'detailed');
                return $placeholder_url;
            }
            
            // Placeholder doesn't exist, generate it on-demand
            $this->utilities_service->debug_message("Generating missing lazy placeholder for cached image: {$placeholder_filename}", null, false, 'detailed');
            
            // Load the main cached image - check cache_log first, then filesystem
            $main_cache_path = $this->_convert_url_to_cache_path($cache_url);
            $main_cache_data = $cache_management_service->load_cached_image_data($main_cache_path);
            
            if (!$main_cache_data && !$filesystem->exists($main_cache_path)) {
                // Main image doesn't exist in cache_log or filesystem, use fallback
                $this->utilities_service->debug_message("Main cached image not found in cache_log or filesystem: {$main_cache_path}");
                return $this->_get_fallback_placeholder_url($filter_name);
            }
            
            // Load the cached image using Imagine
            $imagine = new Imagine();
            $cached_image = $imagine->open($main_cache_path);
            
            // Apply the appropriate filter to generate placeholder
            $placeholder_image = $this->_apply_lazy_filter_for_cache($cached_image, $filter_name);
            
            if (!$placeholder_image) {
                // Filter failed, use fallback
                return $this->_get_fallback_placeholder_url($filter_name);
            }
            
            // Save the placeholder image
            $quality = $this->_get_lazy_image_quality($filter_name, $cache_path_info['extension']);
            $success = $filesystem->writeImage($placeholder_image, $placeholder_cache_path, [
                'quality' => $quality
            ]);
            
            if ($success) {
                $this->utilities_service->debug_message("Successfully generated on-demand lazy placeholder: {$placeholder_filename}", null, false, 'detailed');
                
                // Update cache log for the placeholder
                $this->_update_placeholder_cache_log($placeholder_cache_path, $filter_name);
                
                return $placeholder_url;
            } else {
                $this->utilities_service->debug_message("Failed to save on-demand lazy placeholder: {$placeholder_filename}");
                return $this->_get_fallback_placeholder_url($filter_name);
            }
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Error ensuring lazy placeholder exists: " . $e->getMessage());
            return $this->_get_fallback_placeholder_url($filter_name);
        }
    }

    /**
     * Generate action link URL if action_link parameter is enabled
     * 
     * Migrated from Legacy JcogsImage::_generate_action_link()
     * Creates EE Action URLs that serve images directly via act_originated_image method
     * 
     * @param string $image_url Current image URL
     * @param string $what Type indicator (e.g., 'img', 'url')
     * @return string Action URL or original URL if action links disabled
     */
    private function _generate_action_link(string $image_url, string $what = 'img'): string
    {
        // Check if action links are enabled via parameter or global setting
        $action_link_param = $this->context->get_param('action_link', 'auto');
        $global_action_links = $this->settings_service->get('img_cp_action_links', 'n');
        
        // Logic: 
        // - If action_link="y" → force enable
        // - If action_link="n" → force disable  
        // - If action_link not set (auto) → use global setting
        $action_links_enabled = false;
        if (strtolower(substr($action_link_param, 0, 1)) === 'y') {
            $action_links_enabled = true;
        } elseif (strtolower(substr($action_link_param, 0, 1)) === 'n') {
            $action_links_enabled = false;
        } else {
            // Use global setting when parameter is not explicitly set
            $action_links_enabled = (strtolower(substr($global_action_links, 0, 1)) === 'y');
        }
        
        if (!$action_links_enabled) {
            $this->utilities_service->debug_message('Action links disabled');
            return $image_url;
        }
        
        // Debug: Log action link generation when enabled
        $this->utilities_service->debug_message('Generating action link for: ' . $image_url . ' (param: ' . $action_link_param . ', global: ' . $global_action_links . ')');
        
        // Get ORIGINAL context parameters for ACT packet (not current parameters which may have been modified during processing)
        // This ensures consistent cache keys between regular tag processing and ACT regeneration
        $all_params = $this->context->get_original_tag_params();

        // Set specific ACT parameters
        $all_params['action_link'] = 'no'; // Prevent recursive action link generation
        $all_params['act_what'] = $what;
        $all_params['act_path'] = $image_url;
        $all_params['url_only'] = 'yes'; // Force URL only mode for ACT processing
        
        // Ensure color parameters are properly formatted
        if (isset($all_params['bg_color'])) {
            // Use validation service to ensure proper color format
            $all_params['bg_color'] = $this->validation_service->validate_color_string($all_params['bg_color']);
        }
        
        // Build ACT packet
        $act_packet = base64_encode(json_encode($all_params));
        
        if (empty($act_packet)) {
            $this->utilities_service->debug_message('Action link generation failed: JSON encoding error');
            return $image_url;
        }
        
        // Get action ID for act_originated_image
        $act_id = $this->utilities_service->get_action_id('act_originated_image');
        if (!$act_id) {
            $this->utilities_service->debug_message('Action link generation failed: No action ID found for act_originated_image');
            return $image_url;
        }
        
        // Debug: Log successful action ID lookup
        $this->utilities_service->debug_message('Action ID found: ' . $act_id);
        
        // Build action URL
        $append_path = $this->settings_service->get('img_cp_append_path_to_action_links', 'n');
        if (strtolower(substr($append_path, 0, 1)) === 'y') {
            $action_url = sprintf(
                '%s?ACT=%s&act_packet=%s&path=%s',
                ee()->config->item('site_url'),
                $act_id,
                $act_packet,
                urlencode($image_url)
            );
        } else {
            $action_url = sprintf(
                '%s?ACT=%s&act_packet=%s',
                ee()->config->item('site_url'),
                $act_id,
                $act_packet
            );
        }
        
        $this->utilities_service->debug_message('Generated action link: ' . $action_url);
        return $action_url;
    }
    
    /**
     * Generate base64 data from processed image
     * 
     * @return string|null Base64 data URI or null if not available
     */
    private function _generate_base64_from_processed_image(): ?string
    {
        // Try to get from cache first if using cached copy
        if ($this->context->get_flag('using_cache_copy')) {
            $cache_path = $this->context->get_metadata_value('cache_path', '');
            if ($cache_path) {
                try {
                    $filesystem = $this->filesystem_service;
                    $image_content = $filesystem->read(ltrim($cache_path, '/'));
                    if ($image_content) {
                        $save_as = $this->context->get_param('save_as', 'jpg');
                        $mime_type = $this->_get_mime_type($save_as);
                        return 'data:' . $mime_type . ';base64,' . base64_encode($image_content);
                    }
                } catch (\Exception $e) {
                    $this->utilities_service->debug_log('Could not read cached image for base64: ' . $e->getMessage());
                }
            }
        }
        
        // Try to get from processed image object
        $processed_image = $this->context->get_processed_image();
        if ($processed_image && is_object($processed_image) && method_exists($processed_image, 'get')) {
            try {
                $save_as = $this->context->get_param('save_as', 'jpg');

                // Use quality options computed and stored during CacheStage
                $quality_options = $this->context->get_metadata_value('quality_options', ['quality' => 85]);

                $image_content = $processed_image->get($save_as, $quality_options);
                $mime_type = $this->_get_mime_type($save_as);
                return 'data:' . $mime_type . ';base64,' . base64_encode($image_content);
            } catch (\Exception $e) {
                $this->utilities_service->debug_log('Could not generate base64 from processed image: ' . $e->getMessage());
            }
        }
        
        return null;
    }
    
    /**
     * Generate base64 data from source image
     * 
     * @return string|null Base64 data URI or null if not available
     */
    private function _generate_base64_from_source_image(): ?string
    {
        // Only generate for non-cached images (like legacy)
        if ($this->context->get_flag('using_cache_copy')) {
            return null;
        }
        
        // Try to get from raw source image data
        $source_image_raw = $this->context->get_metadata_value('image_source_raw', '');
        if ($source_image_raw) {
            try {
                // Detect MIME type from raw data or use original extension
                $src_path = $this->context->get_param('src', '');
                $extension = pathinfo($src_path, PATHINFO_EXTENSION);
                $mime_type = $this->_get_mime_type($extension);
                
                return 'data:' . $mime_type . ';base64,' . base64_encode($source_image_raw);
            } catch (\Exception $e) {
                $this->utilities_service->debug_log('Could not generate base64_orig from source image: ' . $e->getMessage());
            }
        }
        
        return null;
    }
    
    /**
     * Generate base64 variables on-demand (replicates legacy line 635)
     * 
     * @param string $template_content
     * @param array $variables Variables array to modify by reference
     */
    private function _generate_base64_variables_on_demand(string $template_content, array &$variables): void
    {
        // Check if base64 is needed in the template content
        if (stripos($template_content, '{base64}') !== false && !isset($variables['base64'])) {
            $base64_data = $this->_generate_base64_from_processed_image();
            if ($base64_data) {
                $variables['base64'] = $base64_data;
                $this->utilities_service->debug_message('Generated base64 data for processed image');
            }
        }
        
        // Check if base64_orig is needed in the template content
        if (stripos($template_content, '{base64_orig}') !== false && !isset($variables['base64_orig'])) {
            $base64_orig_data = $this->_generate_base64_from_source_image();
            if ($base64_orig_data) {
                $variables['base64_orig'] = $base64_orig_data;
                $this->utilities_service->debug_message('Generated base64_orig data for source image');
            }
        }
    }
    
    /**
     * Generate HTML IMG tag output
     * 
     * @return string IMG tag HTML
     */
    private function _generate_img_tag(): string 
    {
        $cache_url = $this->context->get_metadata_value('cache_url', '');
        if (empty($cache_url)) {
            // DEBUG: Log when we fall back to original source
            $this->utilities_service->debug_log('WARNING: No cache_url in metadata, falling back to original source');
            
            // Fallback to original source for special cases
            $cache_url = $this->context->get_param('src', '');

            // Additional debugging
            if (empty($cache_url)) {
                $this->utilities_service->debug_log('ERROR: No src parameter available either');
                $cache_url = '/path/to/missing/image.jpg'; // Placeholder to prevent broken HTML
            }
        }

        // Apply image path prefix if specified (Sprint 3 Phase 2)
        $cache_url = $this->_apply_image_path_prefix($cache_url);

        // NOTE: Action link generation moved to after lazy loading processing

        // Get image dimensions from metadata
        $image_dimensions = [
            'width' => $this->context->get_metadata_value('final_width', 0),
            'height' => $this->context->get_metadata_value('final_height', 0)
        ];

        // Generate responsive image data if enabled
        $responsive_data = [];
        if ($this->responsive_service->is_responsive_enabled($this->context)) {
            $base_width = (int) $this->context->get_metadata_value('final_width', 0);
            $max_width = (int) $this->context->get_metadata_value('original_width', $base_width);

            // Generate variant information for responsive images
            $variants = $this->responsive_service->generate_variant_info(
                $this->context, 
                $base_width, 
                $max_width,
                false // Don't allow scaling larger than original
            );
            
            // Generate srcset and sizes attributes
            if (!empty($variants)) {
                // Extract base URL and construct srcset
                $base_url = dirname($cache_url) . '/';
                
                // Apply image path prefix to responsive base URL as well (Sprint 3 Phase 2)
                $base_url = $this->_apply_image_path_prefix($base_url);

                $responsive_data['srcset'] = $this->responsive_service->generate_srcset_attribute(
                    $variants,
                    $base_url,
                    $cache_url,
                    $base_width
                );
                $responsive_data['sizes'] = $this->responsive_service->generate_sizes_attribute(
                    $this->context,
                    $variants,
                    $base_width
                );
            }
            
            $this->utilities_service->debug_message('debug_responsive_images_generated', [count($variants)], false, 'detailed');
        }

        // Handle lazy loading placeholder if needed
        $placeholder_url = '';
        if ($this->_get_lazy_loading_service()->is_lazy_loading_enabled($this->context)) {
            $mode = $this->_get_lazy_loading_service()->get_lazy_loading_mode($this->context);

            if ($mode && $this->_get_lazy_loading_service()->requires_placeholder($mode)) {
                // Generate placeholder URL based on mode
                $placeholder_url = $this->_generate_placeholder_url($mode);
            }
            
            $this->utilities_service->debug_message('debug_lazy_loading_enabled', $mode ?? 'html5', false, 'detailed');
        }

        // Use OutputGenerationService to create the complete HTML output
        $img_tag = $this->output_generation_service->generate_html_output(
            $this->context,
            $cache_url,
            $image_dimensions,
            $responsive_data,
            $placeholder_url
        );
        
        $this->utilities_service->debug_message('debug_output_img_tag_generated', null, false, 'detailed');
        
        return $img_tag;
    }
    
    /**
     * Generate pair tag variables output
     * 
     * @return string Variables output for pair tags
     */
    private function _generate_pair_variables(): string
    {
        // Get tag data - either from actual pair tag or from output parameter (legacy line 958)
        $tag_data = $this->context->get_tag_data();
        if (empty($tag_data)) {
            // Check if this is a single tag with output parameter
            $tag_data = $this->context->get_param('output', '');
        }
        
        if (empty($tag_data)) {
            return '';
        }
        
        // Get cache URL and build template variables
        $cache_url = $this->context->get_metadata_value('cache_url', '');
        if (empty($cache_url)) {
            $cache_url = $this->context->get_param('src', '');
        }
        
        // Build template variables following legacy pattern
        $cache_local_path = $cache_url; // Already a local path like /images/jcogs_img/cache/filename.jpg
        
        // Get base URL and ensure it's properly formatted
        $base_url = ee()->config->item('base_url') ?? '';
        // Fix malformed URLs missing a slash after the protocol
        if (preg_match('/^https?:\/[^\/]/', $base_url)) {
            $base_url = preg_replace('/^(https?):\/([^\/])/', '$1://$2', $base_url);
            $this->utilities_service->debug_message('Fixed malformed base_url: ' . $base_url);
        }
        
        $cache_full_url = $base_url . ltrim($cache_url, '/'); // Convert to full URL
        
        // Get or build comprehensive template variables following Legacy pattern FIRST
        // Check if they were already built by CacheStage (most common case)
        $variables = $this->context->get_metadata_value('computed_template_variables', null);
        $this->utilities_service->debug_log("JCOGS_IMG_PRO DEBUG OutputStage: Retrieved from context: " . json_encode($variables ? array_keys($variables) : 'NULL'));
        
        if ($variables === null) {
            // Not built yet - build them now (fallback for edge cases)
            $this->utilities_service->debug_log("JCOGS_IMG_PRO DEBUG OutputStage: Building variables as fallback");
            $variables = $this->_build_legacy_compatible_variables($cache_local_path, $cache_full_url);

            // Store in context for any other consumers
            $this->context->set_metadata('computed_template_variables', $variables);
        } else {
            $this->utilities_service->debug_log("JCOGS_IMG_PRO DEBUG OutputStage: Using cached variables - width=" . ($variables['width'] ?? 'MISSING'));
        }
        
        // Generate responsive image variables if enabled - use loaded template variables
        $responsive_variables = [];
        if ($this->responsive_service->is_responsive_enabled($this->context)) {
            // Use loaded template variables for dimensions
            $base_width = (int) ($variables['width'] ?? 0);
            $max_width = (int) ($variables['width_orig'] ?? $base_width);
            
            $variants = $this->responsive_service->generate_variant_info(
                $this->context, 
                $base_width, 
                $max_width,
                false
            );
            
            if (!empty($variants)) {
                $base_url = dirname($cache_url) . '/';
                $responsive_variables['srcset'] = $this->responsive_service->generate_srcset_attribute(
                    $variants,
                    $base_url,
                    $cache_url,
                    $base_width
                );
                $responsive_variables['sizes'] = $this->responsive_service->generate_sizes_attribute(
                    $this->context,
                    $variants,
                    $base_width
                );
            }
        }
        
        // Generate lazy loading variables if enabled
        $lazy_variables = [];
        if ($this->_get_lazy_loading_service()->is_lazy_loading_enabled($this->context)) {
            $mode = $this->_get_lazy_loading_service()->get_lazy_loading_mode($this->context);
            $lazy_variables['lazy_mode'] = $mode ?? 'html5';
            $lazy_variables['is_lazy'] = 'yes';
            
            if ($mode && $this->_get_lazy_loading_service()->requires_placeholder($mode)) {
                $lazy_variables['placeholder_url'] = $this->_generate_placeholder_url($mode);
            }
        } else {
            $lazy_variables['lazy_mode'] = 'none';
            $lazy_variables['is_lazy'] = 'no';
        }
        
        // Merge in responsive image variables
        $variables = array_merge($variables, $responsive_variables);
        
        // Merge in lazy loading variables
        $variables = array_merge($variables, $lazy_variables);
        
        // Generate base64 variables on-demand (like legacy line 635)
        $this->_generate_base64_variables_on_demand($tag_data, $variables);

        // Parse template content with variables using EE's template parser (Legacy pattern)
        // This mirrors Legacy line 950-951: ee()->TMPL->parse_variables($this->params->output, $this->vars)
        // and line 955-956: ee()->TMPL->parse_variables($this->tagdata, $this->vars)
        
        // Format variables for EE template parser - it expects a nested array structure
        $template_vars = [$variables]; // EE expects array of variable sets
        
        // Use EE's template parser for proper variable processing (supports conditionals, modifiers, etc)
        if (!empty(ee()->TMPL)) {
            // Standard template processing
            $output = ee()->TMPL->parse_variables($tag_data, $template_vars);
        } else {
            // ACT/direct processing fallback - use template service
            $output = ee('Template')->parse_variables($tag_data, $template_vars);
        }
        
        $this->utilities_service->debug_log('debug_output_pair_variables_generated', strlen($output), false, 'detailed');
        
        return $output;
    }
    
    /**
     * Generate palette tag variables output
     * 
    * @return string Variables output for palette tags
     */
    private function _generate_palette_variables(): string 
    {
        $tag_data = $this->context->get_tag_data();
        if (empty($tag_data)) {
            return '';
        }
        
        // Get palette data from context metadata (set by ProcessImageStage)
        $palette_data = $this->context->get_metadata_value('palette_data', []);
        if (empty($palette_data)) {
            $this->utilities_service->debug_log('No palette data found in context metadata');
            return $tag_data; // Return unparsed template
        }
        
        // Build template variables for palette
        $variables = [
            'dominant_color' => $palette_data['dominant_color'] ?? 'rgb(128,128,128)',
        ];
        
        // Parse template content with variables
        $output = $tag_data;
        
        // Replace simple variables first
        foreach ($variables as $key => $value) {
            $output = str_replace('{' . $key . '}', $value, $output);
        }
        
        // Handle {colors} loop if present
        if (isset($palette_data['colors']) && is_array($palette_data['colors'])) {
            $colors_pattern = '/\{colors\}(.*?)\{\/colors\}/s';
            if (preg_match($colors_pattern, $output, $matches)) {
                $colors_template = $matches[1];
                $colors_output = '';
                
                foreach ($palette_data['colors'] as $color_data) {
                    $color_row = $colors_template;
                    $color_row = str_replace('{color}', $color_data['color'], $color_row);
                    $color_row = str_replace('{rank}', $color_data['rank'], $color_row);
                    $colors_output .= $color_row;
                }
                
                $output = str_replace($matches[0], $colors_output, $output);
            }
        }
        
        $this->utilities_service->debug_log('debug_output_palette_variables_generated', strlen($output), false, 'detailed');
        
        return $output;
    }
    
    /**
     * Generate placeholder URL for lazy loading, ensuring placeholder exists
     * 
     * Replicates Legacy _generate_lazy_placeholder_image behavior:
     * 1. Check if lazy placeholder exists in cache
     * 2. If not, generate it on-demand
     * 3. Return the placeholder URL
     * 
     * This is critical for cached images where lazy placeholders weren't generated
     * during the current processing cycle.
     * 
     * @param string $mode Lazy loading mode (lqip, dominant_color, etc.)
     * @return string Placeholder URL
     */
    private function _generate_placeholder_url(string $mode): string
    {
        // Remove 'js_' prefix if present (like Legacy does)
        $filter_name = str_replace('js_', '', strtolower($mode));
        
        switch ($filter_name) {
            case 'lqip':
                // First check metadata (in case placeholder was generated during this processing)
                $metadata = $this->context->get_metadata();
                if (!empty($metadata['lqip_url'])) {
                    return $metadata['lqip_url'];
                }
                
                // Check if LQIP placeholder exists in cache and generate if needed
                return $this->_ensure_lazy_placeholder_exists('lqip');

            case 'dominant_color':
                // First check metadata (in case placeholder was generated during this processing)
                $metadata = $this->context->get_metadata();
                if (!empty($metadata['dominant_color_url'])) {
                    return $metadata['dominant_color_url'];
                }
                
                // Check if dominant color placeholder exists in cache and generate if needed
                return $this->_ensure_lazy_placeholder_exists('dominant_color');

            default:
                return '';
        }
    }
    
    /**
     * Generate URL-only output (for url_only="yes" parameter)
     * 
     * @return string Image URL (with action link if enabled)
     */
    private function _generate_url_only(): string 
    {
        $cache_url = $this->context->get_metadata_value('cache_url', '');
        if (empty($cache_url)) {
            $cache_url = $this->context->get_param('src', '');
        }
        
        // Apply image path prefix if specified
        $cache_url = $this->_apply_image_path_prefix($cache_url);
        
        // Apply action link generation if enabled
        $final_url = $this->_generate_action_link($cache_url, 'url');

        $this->utilities_service->debug_log('debug_output_url_only_generated', $final_url, false, 'detailed');
        
        return $final_url;
    }
    
    /**
     * Get fallback placeholder URL for when generation fails
     * 
     * @param string $filter_name Filter name
     * @return string Fallback data URL
     */
    private function _get_fallback_placeholder_url(string $filter_name): string
    {
        switch ($filter_name) {
            case 'lqip':
                // Fallback to simple 1x1 transparent pixel
                return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
                
            case 'dominant_color':
                // Fallback to simple colored placeholder
                return 'data:image/svg+xml;base64,' . base64_encode(
                    '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><rect width="100%" height="100%" fill="#f0f0f0"/></svg>'
                );
                
            default:
                return '';
        }
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
                    'title' => $file->title ?? '',
                    'description' => $file->description ?? '',
                    'credit' => $file->credit ?? '',
                    'location' => $file->location ?? ''
                ];
            }
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Error retrieving file manager fields: " . $e->getMessage());
        }
        
        return [];
    }
    
    /**
     * Get quality setting for lazy images
     * 
     * @param string $filter_name Filter name
     * @param string $extension File extension
     * @return int Quality setting
     */
    private function _get_lazy_image_quality(string $filter_name, string $extension): int
    {
        // Use lower quality for LQIP to reduce file size
        if ($filter_name === 'lqip') {
            return $extension === 'jpg' ? 50 : 85;
        }
        
        // Use high quality for dominant color (simple image)
        if ($filter_name === 'dominant_color') {
            return 100;
        }
        
        return 85;
    }
    
    /**
     * Get lazy loading service (lazy initialization to prevent circular dependency)
     * 
     * @return mixed LazyLoadingService instance
     */
    private function _get_lazy_loading_service()
    {
        return \JCOGSDesign\JCOGSImagePro\Service\ServiceCache::lazy_loading();
    }
    
    /**
     * Get MIME type for file extension
     * 
     * @param string $extension
     * @return string MIME type
     */
    private function _get_mime_type(string $extension): string
    {
        switch (strtolower($extension)) {
            case 'jpg':
            case 'jpeg':
                return 'image/jpeg';
            case 'png':
                return 'image/png';
            case 'webp':
                return 'image/webp';
            case 'avif':
                return 'image/avif';
            case 'gif':
                return 'image/gif';
            case 'svg':
                return 'image/svg+xml';
            default:
                return 'image/jpeg'; // Default fallback
        }
    }
    
    /**
     * Rebuild URL from parsed components
     * 
     * @param array $parsed_url Parsed URL components
     * @return string Rebuilt URL
     */
    private function _rebuild_url(array $parsed_url): string
    {
        $url = '';
        
        if (!empty($parsed_url['scheme'])) {
            $url .= $parsed_url['scheme'] . '://';
        }
        
        if (!empty($parsed_url['host'])) {
            if (!empty($parsed_url['user'])) {
                $url .= $parsed_url['user'];
                if (!empty($parsed_url['pass'])) {
                    $url .= ':' . $parsed_url['pass'];
                }
                $url .= '@';
            }
            $url .= $parsed_url['host'];
            
            if (!empty($parsed_url['port'])) {
                $url .= ':' . $parsed_url['port'];
            }
        }
        
        if (!empty($parsed_url['path'])) {
            $url .= $parsed_url['path'];
        }
        
        if (!empty($parsed_url['query'])) {
            $url .= '?' . $parsed_url['query'];
        }
        
        if (!empty($parsed_url['fragment'])) {
            $url .= '#' . $parsed_url['fragment'];
        }
        
        return $url;
    }
    
    /**
     * Serve raw image data for ACT requests (mirrors Legacy _send_act_link_image)
     * 
     * This method serves the processed image directly with appropriate headers
     * and terminates execution. Called when ACT processing flag is detected.
     * 
    * @return void Execution terminates after serving image
     */
    private function _serve_act_image(): void
    {
        $this->utilities_service->debug_message('ACT: OutputStage serving raw image data');
        $this->utilities_service->debug_log('JCOGS ACT: OutputStage serving raw image data');
        
        // Get the processed image path from context metadata
        $cache_url = $this->context->get_metadata_value('cache_url', '');
        if (empty($cache_url)) {
            $this->utilities_service->debug_message('ACT: No cache URL available for image serving');
            $this->utilities_service->debug_log('JCOGS ACT: No cache URL in context metadata');
            header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
            echo "Image not found";
            exit;
        }
        
        // Convert cache URL to filesystem path for Flysystem reading
        $cache_path = $this->_convert_url_to_cache_path($cache_url);
        
        $this->utilities_service->debug_message('ACT: Converting cache URL to path - URL: ' . $cache_url . ' -> Path: ' . $cache_path);
        
        // Try to read the image data using filesystem service with the converted path
        $image_raw = $this->filesystem_service->read($cache_path);
        
        if (empty($image_raw)) {
            $this->utilities_service->debug_message('ACT: Failed to read image data from path: ' . $cache_path . ' (URL: ' . $cache_url . ')');
            $this->utilities_service->debug_log('JCOGS ACT: Failed to read image from path: ' . $cache_path);
            header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
            echo "Image file not accessible";
            exit;
        }
        
        $this->utilities_service->debug_message('ACT: Successfully read image data, size: ' . strlen($image_raw) . ' bytes');
        $this->utilities_service->debug_log('JCOGS ACT: Image data read successfully, size: ' . strlen($image_raw));
        
        // Get image size
        $image_size = strlen($image_raw);
        
        // Set Content-Type header based on file extension (mirrors Legacy)
        $this->_set_act_content_type_header($cache_url);
        
        // Clear validation service parameters (mirrors Legacy clear_params)
        $validation_service = \JCOGSDesign\JCOGSImagePro\Service\ServiceCache::validation();
        $validation_service->clear_params();
        
        // Send Content-Length header and image data (mirrors Legacy)
        header('Content-Length: ' . $image_size);
        echo $image_raw;
        exit();
    }
    
    /**
     * Set Content-Type header for ACT image serving (mirrors Legacy switch statement)
     * 
     * @param string $image_path Path to the image file
     * @return void
     */
    private function _set_act_content_type_header(string $image_path): void
    {
        $extension = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
        
        $this->utilities_service->debug_message('ACT: Setting Content-Type for extension: ' . $extension);
        
        switch ($extension) {
            case 'avif':
                header('Content-Type: image/avif');
                break;
            case 'bmp':
                header('Content-Type: image/bmp');
                break;
            case 'gif':
                header('Content-Type: image/gif');
                break;
            case 'jpg':
            case 'jpeg':
                header('Content-Type: image/jpeg');
                break;
            case 'png':
                header('Content-Type: image/png');
                break;
            case 'svg':
                header('Content-Type: image/svg+xml');
                break;
            case 'tiff':
                header('Content-Type: image/tiff');
                break;
            case 'webp':
                header('Content-Type: image/webp');
                break;
            default:
                header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                echo "Unsupported file type.";
                exit;
        }
    }
    
    /**
     * Check if stage should be skipped
     * 
     * @return bool
     */
    public function should_skip(Context $context): bool 
    {
        return $context->has_critical_error();
    }
    
    /**
     * Update cache log for generated placeholder
     * 
    * @param string $placeholder_path Placeholder file path
     * @param string $filter_name Filter name
     * @return void
     */
    private function _update_placeholder_cache_log(string $placeholder_path, string $filter_name): void
    {
        try {
            $cache_management_service = $this->cache_service;
            $normalized_cache_dir = $this->context->get_metadata_value('cache_directory_normalized', '.');
            
            // Use computed template variables if available (Legacy-compatible),
            // otherwise fallback to tag parameters for backward compatibility
            $template_variables = $this->context->get_metadata_value('computed_template_variables', null);
            $vars_for_cache = $template_variables ?: $this->context->get_tag_params();
            
            $cache_management_service->update_cache_log(
                image_path: $placeholder_path,
                processing_time: 0.01, // On-demand generation is very fast
                vars: $vars_for_cache,
                cache_dir: $normalized_cache_dir,
                source_path: $this->context->get_metadata_value('source_path', ''),
                force_update: true,
                using_cache_copy: false
            );
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Failed to update cache log for placeholder: " . $e->getMessage());
        }
    }
}
