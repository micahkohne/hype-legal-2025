<?php

/**
 * JCOGS Image Pro - GD Monochrome Mask Filter
 * ============================================
 * GD implementation of monochrome mask filter for image-based masks
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
use Imagine\Gd\Image;
use Imagine\Image\Palette\Color\RGB;

/**
 * GD-specific Monochrome Mask Filter
 * 
 * Converts an image to a bichromal mask (two colors only) using GD library.
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
     * @param RGB|null $color Color for mask areas (null for default)
     */
    public function __construct(?RGB $color = null)
    {
        $this->color = $color;
    }

    /**
     * Apply monochrome mask filter to GD image
     * 
     * @param ImageInterface $image Source image (must be GD image)
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        if (!$image instanceof Image) {
            throw new \InvalidArgumentException('GD MonochromeMask filter requires a GD image');
        }

        // Get the GDImage object
        $gd_image = imagecreatefromstring($image->__toString());

        // Force image to be monochromatic
        imagetruecolortopalette($gd_image, false, 2);

        // Force palette to either black or chosen colour
        $color = $this->color;
        if (!$color) {
            // Default to cyan if no color provided
            $palette = new \Imagine\Image\Palette\RGB();
            $color = $palette->color([0, 255, 255], 100);
        }
        
        if (imagecolorsforindex($gd_image, 0)['red'] < 127) {
            // Index 0 is black so replace with chosen colour
            imagecolorset($gd_image, 0, $color->getRed(), $color->getGreen(), $color->getBlue(), 0);
            // Index 1 is white so replace with magic pink 
            imagecolorset($gd_image, 1, 255, 0, 255, 0);
        } else {
            // Index 0 is white so replace with magic pink
            imagecolorset($gd_image, 0, 255, 0, 255, 0);
            // Index 1 is black so replace with chosen colour 
            imagecolorset($gd_image, 1, $color->getRed(), $color->getGreen(), $color->getBlue(), 0);
        }

        // Convert back to truecolor image
        imagepalettetotruecolor($gd_image);

        // Convert back to Imagine image
        $result_image = new Image($gd_image, $image->palette(), $image->metadata());

        return $result_image;
    }
}
