<?php

/**
 * JCOGS Image Pro Filter - GD
 * ============================
 * A GD-specific Greyscale filter for the Pro addon.
 * 
 * @return object $image
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

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Gd\Imagine;

/**
 * A GD-specific Greyscale filter for the Pro addon.
 */
class Greyscale implements FilterInterface
{
    /**
     * Constructs Greyscale filter.
     */
    public function __construct()
    {
    }

    /**
     * Apply GD-native greyscale filter
     * 
     * Uses imagefilter with IMG_FILTER_GRAYSCALE for maximum performance.
     * 
     * @param ImageInterface $image Source image (must be GD image)
     * @param array $params Filter parameters (unused for greyscale)
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image, array $params = []): ImageInterface
    {
        // Get the GD resource using ImageUtilities
        $image_utilities = ee('jcogs_img_pro:ImageUtilities');
        $gd_resource = $image_utilities->imagineToGdResource($image);
        
        if (!is_resource($gd_resource) && !is_object($gd_resource)) {
            throw new \RuntimeException('Invalid GD resource for greyscale filter');
        }
        
        // Apply GD greyscale filter directly to the resource
        imagefilter($gd_resource, IMG_FILTER_GRAYSCALE);
        
        // Convert back to Imagine image format using optimized ImageUtilities method
        return $image_utilities->gdResourceToImagine($gd_resource);
    }
}
