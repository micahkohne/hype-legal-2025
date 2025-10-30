<?php

/**
 * JCOGS Image Pro - Edgedetect Filter
 * ===================================
 * Top-level edge detection filter with multi-library support
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
 * Edgedetect Filter Class
 * 
 * Top-level edge detection filter that provides library detection and delegation.
 * Edgedetect is a parameter-less filter that highlights edges in the image.
 */
class Edgedetect implements FilterInterface
{
    /**
     * Apply edge detection filter to image
     * 
     * No parameters needed for edge detection filter.
     * 
     * @param ImageInterface $image Source image
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Delegate to appropriate library implementation
        switch (true) {
            case $image instanceof \Imagine\Gd\Image:
                $gd_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Gd\Edgedetect();
                return $gd_filter->apply($image);
                
            case $image instanceof \Imagine\Imagick\Image:
                // Future: Imagick implementation
                throw new \RuntimeException('Imagick edgedetect implementation not yet available');
                
            case $image instanceof \Imagine\Gmagick\Image:
                // Future: Gmagick implementation
                throw new \RuntimeException('Gmagick edgedetect implementation not yet available');
                
            default:
                throw new \RuntimeException('Unsupported image library for edgedetect filter');
        }
    }
}
