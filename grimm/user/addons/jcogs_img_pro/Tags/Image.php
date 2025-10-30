<?php

/**
 * JCOGS Image Pro - EE7 Image Tag Handler
 * ========================================
 * Phase 2 implementation - uses native EE7 pipeline architecture
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

namespace JCOGSDesign\JCOGSImagePro\Tags;

/**
 * EE7 Image Tag Handler Class
 * 
 * Handles {exp:jcogs_img_pro:image} template tags using the native
 * EE7 pipeline architecture for maximum performance and maintainability.
 * 
 * Extends ImageAbstractTag which provides:
 * - Shared service initialization (utilities, settings, performance, validation, filesystem, pipeline)
 * - Common benchmark tracking methods (start_benchmark, end_benchmark, end_benchmark_with_error)
 * - Consistent error handling (handle_tag_error)
 * - Tag context management (set_tag_context)
 * - Debug logging functionality (debug_message)
 * - Abstract process(): string method enforcement for consistent return types
 * 
 * This base class eliminates service instantiation overhead and ensures
 * consistent behavior across all image processing tags in the addon.
 */
class Image extends ImageAbstractTag
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Process the image tag
     * 
     * This is the main entry point for {exp:jcogs_img_pro:image} tags.
     * EE7 automatically routes to this method when the tag is encountered.
     * 
     * Supports both single tags and tag pairs universally:
     * - {exp:jcogs_img_pro:image src="..." width="300"} (single)
     * - {exp:jcogs_img_pro:image src="..." width="300"}{jcogs_img_pro:url}{/exp:jcogs_img_pro:image} (pair)
     * 
     * Uses the native ImageProcessingPipeline for optimal performance.
     * 
     * @return string Processed image HTML output
     */
    public function process(): string 
    {
        // Start performance benchmark - tracks total pipeline time in EE Debug Panel
        $this->start_benchmark('Image_Tag');
        
        try {
            // Get template parameters and data
            $tagparams = ee()->TMPL->tagparams ?? [];
            $tagdata = ee()->TMPL->tagdata ?? null;
            
            // Apply security validation to all tag parameters
            $tagparams = $this->applySecurityValidation($tagparams);
            
            // Process preset parameters and merge with tag parameters
            // This must happen before any other parameter processing
            $tagparams = $this->process_preset_parameters($tagparams);
            
            // Determine if this is a tag pair based on presence of tagdata
            $is_tag_pair = !empty($tagdata);
            
            // Set tag type context for pipeline processing
            $tagparams['_tag_type'] = $is_tag_pair ? 'pair' : 'single';
            
            // Only set _called_by if not already set by another tag (e.g., Palette tag)
            if (!isset($tagparams['_called_by'])) {
                $tagparams['_called_by'] = 'Image_Tag';
            }
            
            // Create a completely fresh copy to prevent any reference sharing
            $isolated_params = array();
            foreach ($tagparams as $key => $value) {
                $isolated_params[$key] = $value;
            }
            
            // Create pipeline with targeted connection warming
            $pipeline_service = $this->create_pipeline_for_connection($isolated_params);
            
            // Process through native pipeline
            $result = $pipeline_service->process($isolated_params, $tagdata);
            
            // Handle pipeline response
            if (isset($result['success']) && $result['success']) {
                // End benchmark and get duration (shows final processing time)
                $this->end_benchmark('Image_Tag');
                
                return (string) $result['output'];
            }
            
            // Handle pipeline errors
            $error_message = $result['error'] ?? 'Unknown pipeline error';
            
            // End benchmark with error context and report elapsed time
            $this->end_benchmark_with_error('Image_Tag', $error_message);
            
            return $this->_generate_fallback_output($isolated_params, $error_message);
            
        } catch (\Throwable $e) {
            return $this->handle_tag_error('Image_Tag', $e);
        }
    }
    
    /**
     * Generate fallback output for errors
     * 
     * @param array $tagparams Template parameters
     * @param string $error_message Error description
     * @return string Fallback output
     */
    private function _generate_fallback_output(array $tagparams, string $error_message): string 
    {
        // In development mode, show detailed error
        if (ee()->config->item('debug') >= 1) {
            return "<!-- JCOGS Image Pro Error: {$error_message} -->";
        }
        
        // In production, attempt to show original source if available
        $src = $tagparams['src'] ?? '';
        if (!empty($src)) {
            // Basic fallback IMG tag
            return "<img src=\"{$src}\" alt=\"Image\" />";
        }
        
        // Final fallback - empty output
        return '';
    }
}
