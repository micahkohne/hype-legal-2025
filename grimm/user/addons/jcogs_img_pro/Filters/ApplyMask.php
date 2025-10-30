<?php

/**
 * JCOGS Image Pro - Apply Mask Filter
 * ===================================
 * Pro version of apply mask filter for compositing masks with images
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
use Imagine\Image\Palette\Color\RGB;

/**
 * Apply Mask Filter
 * 
 * Applies a mask image to another image, creating alpha transparency.
 * Used internally by the Mask filter for image-based masks.
 */
class ApplyMask implements FilterInterface
{
    /**
     * @var ImageInterface Mask image to apply
     */
    private ImageInterface $mask;

    /**
     * @var RGB|null Color for mask processing
     */
    private ?RGB $color;

    /**
     * Constructor
     *
     * @param ImageInterface $mask Mask image to apply
     * @param RGB|null $color Color for mask processing (legacy compatibility)
     */
    public function __construct(ImageInterface $mask, ?RGB $color = null)
    {
        $this->mask = $mask;
        $this->color = $color;
    }

    /**
     * Apply mask filter to image
     * 
     * @param ImageInterface $image Source image
     * @param array $params Not used for this filter
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image, array $params = []): ImageInterface
    {
        // Delegate to appropriate library implementation
        switch (true) {
            case $image instanceof \Imagine\Gd\Image:
                $gd_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Gd\ApplyMask($this->mask, $this->color);
                return $gd_filter->apply($image);
                
            case $image instanceof \Imagine\Imagick\Image:
            case $image instanceof \Imagine\Gmagick\Image:
            default:
                // For non-GD implementations, return image unchanged for now
                // Future: Add Imagick/Gmagick implementations
                return $image;
        }
    }
}
