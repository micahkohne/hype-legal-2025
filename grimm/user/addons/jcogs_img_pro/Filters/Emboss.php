<?php

/**
 * JCOGS Image Pro - Emboss Filter
 * ===============================
 * Top-level emboss filter with multi-library support
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
 * Emboss Filter Class
 * 
 * Top-level emboss filter that provides library detection and delegation.
 * Emboss is a parameter-less filter that creates a raised/carved effect.
 */
class Emboss implements FilterInterface
{
    /**
     * Apply emboss filter to image
     * 
     * No parameters needed for emboss filter.
     * 
     * @param ImageInterface $image Source image
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Delegate to appropriate library implementation
        switch (true) {
            case $image instanceof \Imagine\Gd\Image:
                $gd_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Gd\Emboss();
                return $gd_filter->apply($image);
                
            case $image instanceof \Imagine\Imagick\Image:
                // Future: Imagick implementation
                throw new \RuntimeException('Imagick emboss implementation not yet available');
                
            case $image instanceof \Imagine\Gmagick\Image:
                // Future: Gmagick implementation
                throw new \RuntimeException('Gmagick emboss implementation not yet available');
                
            default:
                throw new \RuntimeException('Unsupported image library for emboss filter');
        }
    }
}
