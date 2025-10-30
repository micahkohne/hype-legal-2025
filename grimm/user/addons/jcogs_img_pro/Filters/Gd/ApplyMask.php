<?php

/**
 * JCOGS Image Pro - GD Apply Mask Filter
 * =======================================
 * GD implementation of apply mask filter for compositing masks with images
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
 * GD-specific Apply Mask Filter
 * 
 * Applies a mask image to another image using GD library.
 * Creates alpha transparency based on mask luminance.
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
     * Apply mask filter to GD image
     * 
     * @param ImageInterface $image Source image (must be GD image)
     * @return ImageInterface Processed image with mask applied
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        if (!$image instanceof Image) {
            throw new \InvalidArgumentException('GD ApplyMask filter requires a GD image');
        }

        $size = $image->getSize();
        $size = $image->getSize();
        $mask_size = $this->mask->getSize();

        if ($size != $mask_size) {
            throw new \InvalidArgumentException(sprintf(
                'The given mask doesn\'t match current image\'s size. Mask dimensions: %s, Image dimensions: %s',
                $mask_size, $size
            ));
        }

        // Convert Imagine image to GD image
        $working_image = imagecreatefromstring($image->__toString());
        imagealphablending($working_image, false); // Turn off alpha blending
        $working_mask = $this->mask->getGdResource();

        // Go through image and set transparency to match mask value at same point
        $width = $size->getWidth();
        $height = $size->getHeight();
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                // Get the two colours at this point
                $mask_color_at_point = imagecolorsforindex($working_mask, imagecolorat($working_mask, $x, $y));
                // Is mask colour magic pink?
                if ($mask_color_at_point['red'] == 255 && $mask_color_at_point['green'] == 0 && $mask_color_at_point['blue'] == 255) {
                    // Update image pixel to be transparent
                    $new_color = imagecolorallocatealpha($working_image, 0, 0, 0, 127);
                    imagesetpixel($working_image, $x, $y, $new_color);
                }
            }
        }

        // Convert back to Imagine image
        $result_image = new Image($working_image, $image->palette(), $image->metadata());

        return $result_image;
    }
}
