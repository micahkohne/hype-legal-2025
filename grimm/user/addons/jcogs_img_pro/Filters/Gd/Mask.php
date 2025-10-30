<?php

/**
 * JCOGS Image Pro - GD Mask Filter Implementation
 * ===============================================
 * GD-specific implementation of mask filter (disabled - handled at main level)
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

namespace JCOGSDesign\JCOGSImagePro\Filters\Gd;

/**
 * GD Mask Filter Implementation
 * 
 * Note: The Mask filter is now handled at the main Filters level using Imagine
 * to avoid unnecessary format conversions and follow legacy processing patterns.
 */
class Mask
{
    /**
     * Apply mask filter using GD
     *
     * @param mixed $image_data The image data (string, GD resource, or Imagine object)
     * @param array $parameters Processed parameters
     * @return string The processed image data as PNG string
     */
    public function apply($image_data, array $parameters): string
    {
        if (function_exists('log_message')) {
            log_message('debug', '[JCOGS Image Pro - GD Mask Filter] GD Mask filter called - this should NOT happen. Parameters: ' . print_r($parameters, true));
        }
        throw new \Exception('Mask filter should be handled at the main Filters level, not in the GD-specific implementation. This avoids unnecessary format conversions.');
    }
}
