<?php

/**
 * JCOGS Image Pro - EE7 Palette Tag Handler
 * =========================================
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
 * EE7 Palette Tag Handler Class
 * 
 * Delegates to the universal Image tag for processing, maintaining the legacy
 * concept that all image processing uses the same pipeline regardless of 
 * the tag entry point.
 * 
 * The palette tag traditionally extracts color palettes from images using
 * ColorThief or similar libraries for dominant color analysis.
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
class Palette extends ImageAbstractTag 
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Process the palette tag
     * 
     * Uses a dedicated palette processing approach similar to Legacy:
     * 1. Get the source image (processed or cached)
     * 2. Run ColorThief palette extraction directly
     * 3. Format and return the template variables
     * 
     * @return string Processed color palette HTML output
     */
    public function process(): string 
    {
        try {
            // Get template parameters and data
            $tagparams = ee()->TMPL->tagparams ?? [];
            $tagdata = ee()->TMPL->tagdata ?? null;
            
            // Apply security validation to all tag parameters
            $tagparams = $this->applySecurityValidation($tagparams);
            
            // Create pipeline with targeted connection warming
            $pipeline_service = $this->create_pipeline_for_connection($tagparams);
            
            // Get the ImageProcessingPipeline service to load the source image
            $result = $pipeline_service->process($tagparams, $tagdata, true); // true = palette mode
            
            if (!isset($result['success']) || !$result['success']) {
                // Failed to get source image
                return ee()->TMPL->no_results();
            }
            
            // Get the source image from the pipeline result
            $source_image = $result['source_image'] ?? null;
            if (!$source_image) {
                return ee()->TMPL->no_results();
            }
            
            // Extract palette using ColorThief (following legacy approach)
            $palette_size = (int) ($tagparams['palette_size'] ?? 5);
            $palette_size = max(1, min(20, $palette_size)); // Limit to reasonable range
            
            // Get GD resource for ColorThief
            $gd_resource = $source_image->getGdResource();
            if (!$gd_resource) {
                return ee()->TMPL->no_results();
            }
            
            // Use ColorThief to extract palette and dominant color (legacy-compatible)
            $palette = \ColorThief\ColorThief::getPalette($gd_resource, $palette_size, 10);
            $dominant_color = \ColorThief\ColorThief::getColor($gd_resource, 10);
            
            // Format colors exactly like Legacy
            $output = [];
            $i = 0;
            foreach ($palette as $color) {
                // Legacy format: rgb(r,g,b) string, not array 
                $rgb_string = sprintf('rgb(%d, %d, %d)', $color[0], $color[1], $color[2]);
                $output[0]['colors'][$i] = ['color' => $rgb_string, 'rank' => $i + 1];
                $i++;
            }
            
            // Format dominant color like Legacy: rgb(r,g,b) string
            $dominant_rgb = sprintf('rgb(%d, %d, %d)', $dominant_color[0], $dominant_color[1], $dominant_color[2]);
            $output[0]['dominant_color'] = $dominant_rgb;
            
            // Parse output back to template (legacy approach)
            if (empty($palette)) {
                return ee()->TMPL->no_results();
            }
            
            return ee()->TMPL->parse_variables($tagdata, $output);
            
        } catch (\Throwable $e) {
            // Error handling - log and provide fallback
            if (function_exists('log_message')) {
                log_message('error', 'JCOGS Image Pro palette tag error: ' . $e->getMessage());
            }
            
            // Fallback to basic error message in development
            if (ee()->config->item('debug') >= 1) {
                return "<!-- JCOGS Image Pro Palette Error: {$e->getMessage()} -->";
            }
            
            return ee()->TMPL->no_results();
        }
    }
}
