<?php

/**
 * JCOGS Image Pro - EE7 Bulk Tag Handler
 * ======================================
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
 * EE7 Bulk Tag Handler Class
 * 
 * Delegates to the universal Image tag for processing, maintaining the legacy
 * concept that all image processing uses the same pipeline regardless of 
 * the tag entry point.
 * 
 * The bulk tag traditionally handles multiple images within a single tag call,
 * often parsing <img> tags within the template data.
 * 
 * Extends ImageAbstractTag which provides:
 * - Shared service initialization (utilities, settings, performance, validation, filesystem, cache)
 * - Pipeline service creation per-connection (via create_pipeline_for_connection)
 * - Common benchmark tracking methods (start_benchmark, end_benchmark, end_benchmark_with_error)
 * - Consistent error handling (handle_tag_error)
 * - Tag context management (set_tag_context)
 * - Debug logging functionality (debug_message)
 * - Abstract process(): string method enforcement for consistent return types
 * 
 * This base class eliminates service instantiation overhead and ensures
 * consistent behavior across all image processing tags in the addon.
 */
class Bulk extends ImageAbstractTag 
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Process the bulk tag
     * 
     * Replicates legacy bulk processing workflow:
     * 1. Extract <img> tags from tagdata
     * 2. Apply exclude_regex filtering
     * 3. Process each image through pipeline with merged parameters
     * 4. Replace images in tagdata with processed results
     * 
     * @return string Processed image HTML output for multiple images
     */
    public function process(): string 
    {
        try {
            // Add debug message to confirm bulk tag is being called
            $this->utilities_service->debug_message('JCOGS Image Pro: Bulk tag processing started');

            // Get template parameters and data
            $tagparams = ee()->TMPL->tagparams ?? [];
            $tagdata = ee()->TMPL->tagdata ?? '';
            
            // Apply security validation to all tag parameters
            $tagparams = $this->applySecurityValidation($tagparams);
            
            if (empty($tagdata)) {
                return '';
            }
            
            // Extract img tags from tagdata (same regex as legacy)
            preg_match_all('/(:?<img\s(.*?)>)/s', $tagdata, $img_tags, PREG_SET_ORDER);
            
            if (empty($img_tags)) {
                $this->utilities_service->debug_message('JCOGS Image Pro: No img tags found in tagdata');
                return $tagdata; // No images found, return original tagdata
            }

            $this->utilities_service->debug_message('JCOGS Image Pro: Found ' . count($img_tags) . ' img tags to process');

            // Apply exclude_regex filter if provided (same logic as legacy)
            $exclude_regex = $tagparams['exclude_regex'] ?? '';
            if (!empty($exclude_regex)) {
                $img_tags = $this->_exclude_images_by_regex($img_tags, $exclude_regex);
            }            if (empty($img_tags)) {
                return $tagdata; // All images excluded, return original tagdata
            }
            
            // Process each image individually like legacy
            $vars = []; // EE template variables for final replacement
            $processed_tagdata = $tagdata;
            
            foreach ($img_tags as $i => $img_tag) {
                // Create temporary variable name for this image
                $temp_var = 'jcogs_img_pro_bulk_' . $i;
                
                // Replace the original img tag with placeholder in tagdata
                $processed_tagdata = str_replace($img_tag[0], '{' . $temp_var . '}', $processed_tagdata);
                
                // Set default value to original tag (fallback if processing fails)
                $vars[0][$temp_var] = $img_tag[0];
                
                // Extract and merge parameters (like legacy _process_image_attributes)
                $merged_params = $this->_merge_bulk_and_image_params($tagparams, $img_tag);
                
                // Create pipeline with targeted connection warming for this image
                $pipeline_service = $this->create_pipeline_for_connection($merged_params);
                
                // Process through Pro pipeline
                try {
                    $result = $pipeline_service->process($merged_params, null); // No tagdata for individual images
                    
                    if (isset($result['success']) && $result['success']) {
                        $vars[0][$temp_var] = $result['output'];
                        $this->utilities_service->debug_message('JCOGS Image Pro: Successfully processed image ' . ($i + 1));
                    } else {
                        // Keep original tag on error (like legacy)
                        $vars[0][$temp_var] = $img_tag[0];
                        $error_msg = $result['error'] ?? 'Unknown error';
                        $this->utilities_service->debug_message('JCOGS Image Pro: Failed to process image ' . ($i + 1) . ': ' . $error_msg);
                    }
                } catch (\Throwable $e) {
                    // Keep original tag on pipeline error
                    $vars[0][$temp_var] = $img_tag[0];
                    $this->utilities_service->debug_message('JCOGS Image Pro: Pipeline error for image ' . ($i + 1) . ': ' . $e->getMessage());
                }
            }
            
            // Use EE's parse_variables like legacy to replace placeholders
            return ee()->TMPL->parse_variables($processed_tagdata, $vars);
            
        } catch (\Throwable $e) {
            // Fallback to original tagdata on any error
            if (ee()->config->item('debug') >= 1) {
                return "<!-- JCOGS Image Pro Bulk Error: {$e->getMessage()} -->" . $tagdata;
            }
            return $tagdata;
        }
    }
    
    /**
     * Filter images based on exclude_regex parameter
     * 
     * @param array $img_tags Array of img tag matches
     * @param string $exclude_regex Regex pattern to exclude images
     * @return array Filtered image tags
     */
    private function _exclude_images_by_regex(array $img_tags, string $exclude_regex): array
    {
        $regexs = explode('@', $exclude_regex);
        
        foreach ($regexs as $regex) {
            foreach ($img_tags as $i => $img_tag) {
                if (preg_match('/' . $regex . '/', $img_tag[0])) {
                    // Use debug method available in Pro
                    $this->utilities_service->debug_message('Excluding image due to exclude_regex tag: ' . $img_tag[0]);
                    unset($img_tags[$i]);
                }
            }
        }
        
        return array_values($img_tags);
    }
    
    /**
     * Merge bulk tag parameters with individual img tag attributes
     * Replicates legacy _process_image_attributes merging logic
     * 
     * @param array $bulk_params Bulk tag parameters
     * @param array $img_tag Individual img tag data
     * @return array Merged parameters for pipeline processing
     */
    private function _merge_bulk_and_image_params(array $bulk_params, array $img_tag): array
    {
        $merged_params = $bulk_params; // Start with bulk parameters
        $img_attributes = $img_tag[2] ?? ''; // The attributes portion of the img tag
        
        // Extract src attribute (required)
        if (preg_match('/(?:src=(?:\"|\')(.*?)(?:\"|\'))/', $img_attributes, $src_matches)) {
            $src = $src_matches[1];
            
            // Apply security validation to extracted src attribute
            if ($this->security_service->containsMaliciousContent($src)) {
                $this->utilities_service->debug_message('Bulk tag: Malicious content detected in img src attribute, skipping: ' . $src);
                return $merged_params; // Return original params, skip this image
            }
            
            // Handle lazy-load src swapping (like legacy)
            if (stripos(strtolower($src), '_lqip_')) {
                $src = str_replace('lqip_', '', $src);
                $this->utilities_service->debug_message('Found lazy-load LQIP image, swapping to: ' . $src);
            } elseif (stripos(strtolower($src), '_dominant_color_')) {
                $src = str_replace('dominant_color_', '', $src);
                $this->utilities_service->debug_message('Found dominant color image, swapping to: ' . $src);
            }
            
            $merged_params['src'] = $src;
        }
        
        // Extract width (individual img tag width overrides bulk width only if bulk width not set)
        if (preg_match('/(?:width=(?:\"|\')(.*?)(?:\"|\'))/', $img_attributes, $width_matches)) {
            $width_value = $width_matches[1];
            
            // Apply security validation to width attribute
            if (!$this->security_service->containsMaliciousContent($width_value)) {
                // Only use img tag width if no bulk width parameter was set (legacy logic)
                if (!isset($bulk_params['width']) || empty($bulk_params['width'])) {
                    $merged_params['width'] = $width_value;
                }
            } else {
                $this->utilities_service->debug_message('Bulk tag: Malicious content detected in img width attribute, ignoring: ' . $width_value);
            }
        }
        
        // Extract height (individual img tag height overrides bulk height only if bulk height not set)
        if (preg_match('/(?:height=(?:\"|\')(.*?)(?:\"|\'))/', $img_attributes, $height_matches)) {
            $height_value = $height_matches[1];
            
            // Apply security validation to height attribute
            if (!$this->security_service->containsMaliciousContent($height_value)) {
                // Only use img tag height if no bulk height parameter was set (legacy logic)
                if (!isset($bulk_params['height']) || empty($bulk_params['height'])) {
                    $merged_params['height'] = $height_value;
                }
            } else {
                $this->utilities_service->debug_message('Bulk tag: Malicious content detected in img height attribute, ignoring: ' . $height_value);
            }
        }
        
        // Extract remaining attributes for attributes parameter
        $remaining_attrs = $img_attributes;
        $remaining_attrs = preg_replace('/(?:src=(?:\"|\').*?(?:\"|\'))/', '', $remaining_attrs);
        $remaining_attrs = preg_replace('/(?:width=(?:\"|\').*?(?:\"|\'))/', '', $remaining_attrs);
        $remaining_attrs = preg_replace('/(?:height=(?:\"|\').*?(?:\"|\'))/', '', $remaining_attrs);
        $remaining_attrs = trim($remaining_attrs);
        
        if (!empty($remaining_attrs)) {
            // Apply security validation to remaining attributes
            if (!$this->security_service->containsMaliciousContent($remaining_attrs)) {
                // Merge with existing attributes parameter if present
                if (isset($merged_params['attributes']) && !empty($merged_params['attributes'])) {
                    $merged_params['attributes'] = trim($merged_params['attributes'] . ' ' . $remaining_attrs);
                } else {
                    $merged_params['attributes'] = $remaining_attrs;
                }
            } else {
                $this->utilities_service->debug_message('Bulk tag: Malicious content detected in img remaining attributes, ignoring: ' . $remaining_attrs);
            }
        }
        
        // Set bulk_tag flag like legacy
        $merged_params['bulk_tag'] = 'y';
        
        return $merged_params;
    }
}
