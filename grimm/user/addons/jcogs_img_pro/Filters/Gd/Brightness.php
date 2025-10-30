<?php

/**
 * JCOGS Image Pro - GD Brightness Filter
 * =======================================
 * GD-optimized brightness filter implementation
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      GD Filter Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Filters\Gd;

use Imagine\Image\ImageInterface;

/**
 * GD Brightness Filter
 * 
 * Adjusts image brightness using GD's native IMG_FILTER_BRIGHTNESS.
 * Supports both brightening and darkening operations with legacy-compatible
 * parameter processing.
 */
class Brightness
{
    /**
     * Constructs Brightness filter
     * 
     * GD-specific brightness implementation with no constructor parameters
     */
    public function __construct()
    {
        // GD-specific brightness implementation
    }

    /**
     * Apply brightness filter using GD
     * 
     * @param ImageInterface $image The image to adjust brightness for
     * @param array $params Brightness parameters [amount]
     * @return ImageInterface The brightness-adjusted image
     */
    public function apply(ImageInterface $image, array $params = []): ImageInterface
    {
        // Get the already-processed brightness amount from the top-level filter
        $amount = $params[0] ?? 0;
        
        // Get the GDImage object using the same method as legacy
        $gd_resource = imagecreatefromstring($image->__toString());
        
        if ($gd_resource === false) {
            // Fallback if GD resource creation failed
            return $image;
        }

        // Apply brightness filter using native GD (same as legacy)
        if (imagefilter($gd_resource, IMG_FILTER_BRIGHTNESS, $amount)) {
            // Success - convert back to Imagine image using optimized ImageUtilities method
            $image_utilities = ee('jcogs_img_pro:ImageUtilities');
            return $image_utilities->gdResourceToImagine($gd_resource);
        } else {
            // Filter failed - return unchanged
            imagedestroy($gd_resource);
            return $image;
        }
    }
}
