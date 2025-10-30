<?php

/**
 * JCOGS Image Pro - EE7 Pair Tag Handler
 * =======================================
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

use ExpressionEngine\Service\Addon\Controllers\Tag\AbstractRoute;

/**
 * EE7 Pair Tag Handler Class
 * 
 * Delegates to the universal Image tag for processing, maintaining the legacy
 * concept that all image processing uses the same pipeline regardless of 
 * the tag entry point.
 * 
 * Note: All tags support pair behavior when they have closing tags.
 * This explicit :pair tag is maintained for legacy compatibility.
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
class Pair extends ImageAbstractTag 
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Process the pair tag
     * 
     * Delegates to the universal Image tag with pair context.
     * Note: All tags support pair behavior when they have closing tags.
     * This explicit :pair tag is maintained for legacy compatibility.
     * 
     * @return string Processed image HTML output
     */
    public function process(): string 
    {
        // Set tag context before delegation
        if (!isset(ee()->TMPL->tagparams)) {
            ee()->TMPL->tagparams = [];
        }
        ee()->TMPL->tagparams['_called_by'] = 'Pair_Tag';
        
        // Delegate to the universal Image tag
        // The pipeline will handle pair-specific logic based on context
        $image_tag = new Image();
        return $image_tag->process();
    }
}
