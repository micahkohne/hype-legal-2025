<?php

/**
 * JCOGS Image Pro - Mean Removal Filter
 * =====================================
 * Top-level mean removal filter with multi-library support
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

namespace JCOGSDesign\JCOGSImagePro\Filters;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

/**
 * Mean Removal Filter Class
 * 
 * Top-level mean removal filter that provides library detection and delegation.
 * Mean removal is a parameter-less filter that creates a "sketchy" effect.
 */
class MeanRemoval implements FilterInterface
{
    /**
     * Apply mean removal filter to image
     * 
     * No parameters needed for mean removal filter.
     * 
     * @param ImageInterface $image Source image
     * @param array $params Filter parameters (unused for mean_removal)
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Delegate to appropriate library implementation
        switch (true) {
            case $image instanceof \Imagine\Gd\Image:
                $gd_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Gd\MeanRemoval();
                return $gd_filter->apply($image);
                
            case $image instanceof \Imagine\Imagick\Image:
                // Future: Imagick implementation
                throw new \RuntimeException('Imagick mean_removal implementation not yet available');
                
            case $image instanceof \Imagine\Gmagick\Image:
                // Future: Gmagick implementation
                throw new \RuntimeException('Gmagick mean_removal implementation not yet available');
                
            default:
                throw new \RuntimeException('Unsupported image library for mean_removal filter');
        }
    }
}
