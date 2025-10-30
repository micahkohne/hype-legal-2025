<?php

/**
 * JCOGS Image Pro - EE7 Single Tag Handler
 * =========================================
 * Legacy compatibility tag for backward compatibility
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Legacy Compatibility Phase
 */

namespace JCOGSDesign\JCOGSImagePro\Tags;

/**
 * EE7 Single Tag Handler Class
 * 
 * Handles {exp:jcogs_img_pro:single} template tags for backward compatibility
 * with legacy implementations. This tag provides identical functionality to
 * the main Image tag but maintains the legacy naming convention.
 * 
 * Extends ImageAbstractTag which provides:
 * - Shared service initialization (utilities, settings, performance, validation, filesystem, pipeline)
 * - Common benchmark tracking methods (start_benchmark, end_benchmark, end_benchmark_with_error)
 * - Consistent error handling (handle_tag_error)
 * - Tag context management (set_tag_context)
 * - Debug logging functionality (debug_message)
 * - Abstract process(): string method enforcement for consistent return types
 * 
 * Implementation delegates to the Image tag to ensure identical behavior
 * while maintaining separate performance tracking for analytics.
 */
class Single extends ImageAbstractTag
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Process the single tag
     * 
     * This is the main entry point for {exp:jcogs_img_pro:single} tags.
     * Provides backward compatibility with legacy implementations while
     * delegating to the main Image tag for actual processing.
     * 
     * Supports both single tags and tag pairs universally:
     * - {exp:jcogs_img_pro:single src="..." width="300"} (single)
     * - {exp:jcogs_img_pro:single src="..." width="300"}{jcogs_img_pro:url}{/exp:jcogs_img_pro:single} (pair)
     * 
     * @return string Processed image HTML output
     */
    public function process(): string
    {
        // Start performance benchmark for Single tag
        $this->start_benchmark('Single_Tag');
        
        try {
            // Set tag context before delegation to track which tag was originally called
            $this->set_tag_context('Single_Tag');
            
            // Single tag is identical to Image tag - just delegate to Image.php
            $image_tag = new Image();
            $result = $image_tag->process();
            
            // End benchmark and log results
            $this->end_benchmark('Single_Tag');
            
            return $result;
            
        } catch (\Throwable $e) {
            return $this->handle_tag_error('Single_Tag', $e);
        }
    }
}
