<?php

/**
 * JCOGS Image Pro Filter
 * ======================
 * A Greyscale filter for the Pro addon.
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

namespace JCOGSDesign\JCOGSImagePro\Filters;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

/**
 * A Greyscale filter for the Pro addon.
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
     * Apply greyscale filter to image
     * 
     * Uses GD-specific greyscale for optimal performance when possible,
     * falls back to Imagine's generic greyscale filter.
     * 
     * @param ImageInterface $image Source image
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        switch (true) {
            case ($image instanceof \Imagine\Gd\Image) : 
                $image = (new Gd\Greyscale())->apply($image, []);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
            case ($image instanceof \Imagine\Gmagick\Image):
            default:
                $image = (new \Imagine\Filter\Advanced\Grayscale())->apply($image);
                break;
        }
        return $image;
    }
}
