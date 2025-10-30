<?php

/**
 * JCOGS Image Pro - GD-Specific Pixelate Filter
 * =============================================
 * High-performance pixelate implementation using GD's native imagefilter
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

namespace JCOGSDesign\JCOGSImagePro\Filters\Gd;

use Imagine\Image\ImageInterface;
use Imagine\Filter\FilterInterface;
use Imagine\Gd\Imagine;

/**
 * GD-Specific Pixelate Filter Class
 * 
 * Uses PHP's native imagefilter function with IMG_FILTER_PIXELATE
 * for optimal performance when working with GD image resources.
 */
class Pixelate implements FilterInterface
{
    /**
     * Apply GD-native pixelate filter
     * 
     * Uses imagefilter with IMG_FILTER_PIXELATE for maximum performance.
     * 
     * @param ImageInterface $image Source image (must be GD image)
     * @param array $params Filter parameters from top-level filter
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image, array $params = []): ImageInterface
    {
        // Get processed parameters from top-level filter
        $block_size = $params['block_size'] ?? 0;
        
        // Skip processing if block size is 0 (no pixelation)
        if ($block_size <= 0) {
            return $image;
        }
        
        // Get the GD resource using the same method as legacy
        $gd_resource = imagecreatefromstring($image->__toString());
        
        if (!is_resource($gd_resource) && !is_object($gd_resource)) {
            throw new \RuntimeException('Invalid GD resource for pixelate filter');
        }
        
        // Apply GD pixelate filter
        // Note: GD pixelate takes block_size and advanced_mode (always false for compatibility)
        imagefilter($gd_resource, IMG_FILTER_PIXELATE, $block_size, false);
        
        // Convert back to Imagine image format using optimized ImageUtilities method
        $image_utilities = ee('jcogs_img_pro:ImageUtilities');
        return $image_utilities->gdResourceToImagine($gd_resource);
    }
}
