<?php

/**
 * JCOGS Image Pro - Initialize Pipeline Stage
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
 * Initialize Pipeline Stage
 * 
 * First stage of the processing pipeline. Validates input parameters,
 * sets up processing context, and determines basic processing flags.
 * 
 * Responsibilities:
 * - Validate required parameters (src, etc.)
 * - Generate cache key for the request
 * - Set basic processing flags
 * - Validate image source exists and is accessible
 * - Set up processing metadata
 */
class InitializeStage extends AbstractStage 
{
    /**
     * Constructor
     */
    public function __construct() 
    {
        parent::__construct('initialize');
    }
    
    /**
     * Process initialization stage
     * 
     * @param Context $context Processing context
     * @throws \Exception If initialization fails
     */
    protected function process(Context $context): void 
    {
        // Note: Opening message is already shown in Tags/Image.php with proper instance numbering
        $this->utilities_service->debug_log('debug_init_stage_starting');
        
        // Early declaration: Show which connection will be used for processing
        $connection_name = $context->get_metadata_value('resolved_connection_name', 'unknown');
        $this->utilities_service->debug_message("Will save processed image to named connection: {$connection_name}");
        
        // 1. Process EE file directives in src and fallback_src parameters
        $this->process_ee_file_directives($context);
        
        // 2. Validate required parameters
        $this->validate_required_parameters($context);
        
        // 2. Generate cache key for this request
        $cache_key = $context->get_cache_key();
        $this->utilities_service->debug_log('debug_init_cache_key_generated', $cache_key);
        
        // User-friendly debug message - show source image being worked with
        $src = $context->get_param('src');
        $src_display = is_array($src) ? implode(', ', $src) : (string)$src;
        $this->utilities_service->debug_message('jcogs_img_debug_working_with_source', [$src_display], false, 'detailed');
        
        // 3. Set basic processing flags based on parameters
        $this->set_processing_flags($context);
        
        // 4. Set tag-specific context based on parameters
        $this->set_tag_context($context);
        
        // 5. Set up processing metadata
        $this->setup_metadata($context);
        
        $this->utilities_service->debug_log('debug_init_stage_completed');
        $this->utilities_service->debug_message(lang('jcogs_img_debug_init_complete'), null, false, 'detailed');
    }
    
    /**
     * Process save_type parameter to determine final save_as format
     * Replicates legacy _get_save_as() logic with browser/server validation
     * 
     * @param Context $context
     */
    private function process_save_type(Context $context): void 
    {
        $src = $context->get_param('src', '');
        $save_type = $context->get_param('save_type', '');
        
        // Parse source URL to get file extension like legacy
        $parsed_url = parse_url($src);
        $file_info = $parsed_url && array_key_exists('path', $parsed_url) ? pathinfo($parsed_url['path']) : [];
        $extension = array_key_exists('extension', $file_info) ? $file_info['extension'] : 'jpg'; // Default fallback
        
        // If no save_type parameter specified, use system default setting
        if (empty($save_type)) {
            $default_format = $this->settings_service->get('img_cp_default_image_format', 'source');
            $save_type = $default_format;
        }
        
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
        
        // Validate browser and server capabilities for save_as format
        // Following legacy fallback logic from ImageProcessingTrait->_get_save_format_as()
        $do_browser_checks = $context->get_param('disable_browser_checks', 'n') !== 'y';
        
        if ($do_browser_checks) {
            // Check if format is supported by server and browser
            if (!$this->validation_service->validate_server_image_format($save_as) || 
                !$this->validation_service->validate_browser_image_format($save_as)) {
                
                // Format not supported - apply fallback logic
                // If original extension supports transparency (webp, png, gif), use png
                // Otherwise use jpg (like legacy implementation)
                if (isset($extension) && in_array($extension, ['webp', 'png', 'gif'])) {
                    $save_as = 'png';
                    $this->utilities_service->debug_log("Save format fallback: Using PNG for transparency preservation (original: {$extension})");
                } else {
                    $save_as = 'jpg';
                    $this->utilities_service->debug_log("Save format fallback: Using JPG as default (original: {$extension})");
                }
            }
        }
        
        // Handle special cases from legacy
        // SVG files must stay as SVG
        if ($extension === 'svg') {
            $save_as = 'svg';
        }
        
        // Note: Animated GIF handling will be done in LoadSourceStage when we can detect animation
        // Legacy logic: if extension === 'gif' && setting 'img_cp_ignore_save_type_for_animated_gifs' == 'y'
        // then save_as should be 'gif' to preserve animation
        
        // Store the processed save_as format
        $context->set_param('save_as', $save_as);
        
        // Keep save_type in sync for consistency like legacy
        $context->set_param('save_type', $save_as);
        
        // Debug log with proper null handling for PHP 8.3
        $debug_save_type = $context->get_param('save_type', 'empty');
        $debug_default_format = isset($default_format) ? $default_format : 'n/a';
        $debug_extension = $extension ?? 'none';
        $debug_save_as = $save_as ?? 'none';
        
        $this->utilities_service->debug_log(sprintf(
            'Save type processing: save_type_param=%s, default_format=%s, source_ext=%s, final_save_as=%s',
            $debug_save_type,
            $debug_default_format,
            $debug_extension,
            $debug_save_as
        ));
    }
    
    /**
     * Set processing flags based on parameters
     * 
     * @param Context $context
     */
    private function set_processing_flags(Context $context): void 
    {
        $src = $context->get_param('src');
        
        // Only process src-dependent flags if we have a src parameter (not bulk tags)
        if (!empty($src)) {
            // Detect SVG images
            if (str_ends_with(strtolower($src), '.svg')) {
                $context->set_flag('svg', true);
            }
            
            // Detect GIF images (check if animated later during load stage)
            if (str_ends_with(strtolower($src), '.gif')) {
                $context->set_flag('potential_gif', true);
            }
        }
        
        // Parse crop parameter following legacy logic
        $crop = $context->get_param('crop');
        
        // Legacy default: 'n|center,center|0,0|y|3'
        // Format: crop_enabled|position|offset|smart_scale|face_sensitivity
        $crop_parts = explode('|', $crop ?? 'n|center,center|0,0|y|3');
        
        // Ensure we have all parts with defaults
        $crop_enabled = isset($crop_parts[0]) ? $crop_parts[0] : 'n';
        $crop_position = isset($crop_parts[1]) ? $crop_parts[1] : 'center,center';
        $crop_offset = isset($crop_parts[2]) ? $crop_parts[2] : '0,0';
        $smart_scale = isset($crop_parts[3]) ? $crop_parts[3] : 'y';
        $face_sensitivity = isset($crop_parts[4]) ? $crop_parts[4] : '3';
        
        // Determine if this is a crop operation (matching legacy logic exactly)
        $crop_is_enabled = substr(strtolower($crop_enabled), 0, 1) != 'n';
        $smart_scale_enabled = substr(strtolower($smart_scale), 0, 1) != 'n';
        
        // Legacy sets its_a_crop based ONLY on crop_enabled, not smart_scale
        // Smart-scale is handled INSIDE the crop logic, not as a separate path
        $its_a_crop = $crop_is_enabled;
        $context->set_flag('its_a_crop', $its_a_crop);
        $context->set_flag('smart_scale_enabled', $smart_scale_enabled);
        
        // Store crop components for use in processing
        $context->set_param('crop_position', $crop_position);
        $context->set_param('crop_offset', $crop_offset);
        $context->set_param('face_sensitivity', $face_sensitivity);
        
        // Handle additional face detection parameters
        $face_crop_margin = $context->get_param('face_crop_margin') ?? 0;
        $face_detect_sensitivity = $context->get_param('face_detect_sensitivity') ?? $face_sensitivity;
        $face_detect_crop_focus = $context->get_param('face_detect_crop_focus') ?? 
            ($this->settings_service->get('img_cp_default_face_detect_crop_focus') ?? 'first_face');
        $context->set_param('face_crop_margin', $face_crop_margin);
        $context->set_param('face_detect_sensitivity', $face_detect_sensitivity);
        $context->set_param('face_detect_crop_focus', $face_detect_crop_focus);
        
        // Don't check for color fill mode here - it should only be set by FallbackSourceService
        // when img_cp_enable_default_fallback_image is specifically set to 'yc' 
        // and no valid source/fallback source is available
        
        // Process save_type parameter like legacy (_get_save_as logic)
        $this->process_save_type($context);
    }
    
    /**
     * Set tag-specific context based on parameters
     * 
     * @param Context $context
     */
    private function set_tag_context(Context $context): void 
    {
        $params = $context->get_tag_params();
        $tagdata = $context->get_tag_data();
        
        // Determine tag type - check for explicit override first
        $tag_type = $params['_tag_type'] ?? null;
        
        if (!$tag_type) {
            // Auto-detect based on tagdata presence
            $tag_type = !empty($tagdata) ? 'pair' : 'single';
        }
        
        // Set called_by from parameter or default
        $called_by = $params['_called_by'] ?? 'Image_Tag';
        
        // Store in context
        $context->set_flag('tag_type', $tag_type);
        $context->set_flag('called_by', $called_by);
        $context->set_flag('is_tag_pair', $tag_type === 'pair');
        
        // Debug logging
        $this->utilities_service->debug_log(sprintf(
            'Tag context set: type=%s, called_by=%s, has_tagdata=%s',
            $tag_type,
            $called_by,
            !empty($tagdata) ? 'true' : 'false'
        ));
        
        // Remove internal parameters from processing
        if (isset($params['_tag_type'])) {
            unset($params['_tag_type']);
        }
        if (isset($params['_called_by'])) {
            unset($params['_called_by']);
        }
        
        // Update context with cleaned parameters
        foreach ($params as $key => $value) {
            $context->set_param($key, $value);
        }
    }
    
    /**
     * Set up processing metadata
     * 
     * @param Context $context
     */
    private function setup_metadata(Context $context): void 
    {
        $context->set_metadata('start_time', microtime(true));
        $context->set_metadata('src_path', $context->get_param('src'));
        $context->set_metadata('cache_key', $context->get_cache_key());
        $context->set_metadata('processing_flags', $context->get_flags());
        
        // Store original parameters for debugging
        $context->set_metadata('original_params', $context->get_tag_params());
    }
    
    /**
     * Validate required parameters
     * 
     * @param Context $context
     * @throws \Exception If required parameters missing
     */
    private function validate_required_parameters(Context $context): void 
    {
        $params = $context->get_tag_params();
        $tagdata = $context->get_tag_data();
        
        // Check if this is a bulk tag (has tagdata but no src parameter)
        $is_bulk_tag = !empty($tagdata) && empty($params['src']);
        
        if ($is_bulk_tag) {
            // Bulk processing is now handled directly in Tags/Bulk.php
            // The pipeline should not receive bulk processing requests anymore
            throw new \Exception('Bulk processing should be handled by Tags/Bulk.php, not the pipeline');
        }
        
        // For non-bulk tags, src parameter can be empty - LoadSourceStage will handle fallbacks
        // Don't validate src here as it prevents fallback processing when src is missing/empty
        
        // Validate image dimensions if provided
        $width = $context->get_param('width');
        $height = $context->get_param('height');
        
        if ($width !== null && (!is_numeric($width) || $width < 0)) {
            throw new \Exception('Width parameter must be a positive number');
        }
        
        if ($height !== null && (!is_numeric($height) || $height < 0)) {
            throw new \Exception('Height parameter must be a positive number');
        }
    }

    /**
     * Process EE file directives in src and fallback_src parameters
     * Converts {file:135:url} and {filedir_2}filename.jpg syntax to actual paths
     * Mirrors Legacy ValidationTrait->_validate_src() behavior
     * 
     * @param Context $context Processing context
     */
    private function process_ee_file_directives(Context $context): void 
    {
        $image_utilities = ee('jcogs_img_pro:ImageUtilities');
        
        // Process src parameter
        $src = $context->get_param('src', '');
        if (!empty($src)) {
            try {
                $parsed_src = $image_utilities->parseFiledir($src);
                if ($parsed_src !== $src && !empty($parsed_src)) {
                    $this->utilities_service->debug_log('EE file directive resolved', $src . ' -> ' . $parsed_src);
                    $context->set_param('src', $parsed_src);
                }
            } catch (\Exception $e) {
                // If parseFiledir fails, log but continue with original value (mirrors Legacy behavior)
                $this->utilities_service->debug_log('EE file directive parsing failed', $src . ' - ' . $e->getMessage());
            }
        }
        
        // Process fallback_src parameter  
        $fallback_src = $context->get_param('fallback_src', '');
        if (!empty($fallback_src)) {
            try {
                $parsed_fallback = $image_utilities->parseFiledir($fallback_src);
                if ($parsed_fallback !== $fallback_src && !empty($parsed_fallback)) {
                    $this->utilities_service->debug_log('EE file directive resolved (fallback)', $fallback_src . ' -> ' . $parsed_fallback);
                    $context->set_param('fallback_src', $parsed_fallback);
                }
            } catch (\Exception $e) {
                // If parseFiledir fails, log but continue with original value (mirrors Legacy behavior)
                $this->utilities_service->debug_log('EE file directive parsing failed (fallback)', $fallback_src . ' - ' . $e->getMessage());
            }
        }
    }

}
