<?php

/**
 * JCOGS Image Pro - Monochrome Mask Filter
 * ========================================
 * Pro version of monochrome mask filter for image-based masks
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
 * Monochrome Mask Filter
 * 
 * Converts an image to a bichromal mask (two colors only).
 * Used internally by the Mask filter for image-based masks.
 */
class MonochromeMask implements FilterInterface
{
    /**
     * @var RGB|null Color for mask areas
     */
    private ?RGB $color;

    /**
     * Constructor
     *
     * @param RGB|null $color Color for mask areas
     */
    public function __construct(?RGB $color = null)
    {
        $this->color = $color;
    }

    /**
     * Apply monochrome mask filter to image
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
                $gd_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Gd\MonochromeMask($this->color);
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
