<?php

/**
 * JCOGS Image Filter
 * ==================
 * Apply a mask filter
 * Takes two images - $image is the image to mask, $mask is the mask to apply
 * $mask is a black shape on a magic pink background.
 * 
 * @return object $image
 * 
 * CHANGELOG
 * 
 * 12/12/2022: 1.3      First release
 * 
 * =====================================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.4.16.1
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.3
 */

namespace JCOGSDesign\Jcogs_img\Filters\Gd;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\Color\RGB;

/**
 * Apply a mask filter
 */
class Apply_mask implements FilterInterface
{
    /**
     * @var ImageInterface
     */
    private $mask;

    /**
     * @var RGB
     */
    private $keep_color;

    /**
     * Apply a mask filter
     *
     * @param ImageInterface $mask
     * @param RGB $color
     */
    public function __construct(ImageInterface $mask, RGB $keep_color = null)
    {
        $this->mask = $mask;
        $this->keep_color = $keep_color;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
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
        $image = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($working_image);
        imagedestroy($working_image);

        return $image;
    }
}
