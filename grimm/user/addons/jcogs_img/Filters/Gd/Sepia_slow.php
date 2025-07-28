<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Slow but Good Sepia Filter.
 * Turns an image into a Sepia equivalent
 * Uses a pixel based method (the one used by CE Image it seems)
 * From here: https://dyclassroom.com/image-processing-project/how-to-convert-a-color-image-into-sepia-image
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
 * @version    1.4.16.2
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.3
 */

namespace JCOGSDesign\Jcogs_img\Filters\Gd;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

/**
 * A Slow Sepia filter.
 */
class Sepia_slow implements FilterInterface
{
    /**
     * Constructs Slow Sepia filter.
     *
     */
    public function __construct()
    {
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Get the GDImage object
        $gd_image = imagecreatefromstring($image->__toString());

        // Get image size
        $size = $image->getSize();

        for ($row = 0; $row < $size->getHeight(); $row++) {
            // scan across the column each time
            for ($col = 0; $col < $size->getWidth(); $col++) {
                // Get the colour in original
                $rgb_orig = imagecolorat($gd_image, $col, $row);
                $r_orig = round((($rgb_orig >> 16) & 0xFF), 0);
                $g_orig = round((($rgb_orig >> 8) & 0xFF), 0);
                $b_orig = round(($rgb_orig & 0xFF), 0);
                $a_orig = round(($rgb_orig & 0x7F000000) >> 24, 0);
                // Work out sepia version of pixel colours
                $r_new = round(min(max(0.393 * $r_orig + 0.769 * $g_orig + 0.189 * $b_orig, 0), 255), 0);
                $g_new = round(min(max(0.349 * $r_orig + 0.686 * $g_orig + 0.168 * $b_orig, 0), 255), 0);
                $b_new = round(min(max(0.272 * $b_orig + 0.534 * $b_orig + 0.131 * $b_orig, 0), 255), 0);
                // Write the sepia version of the colour back
                $pix_col = imagecolorallocatealpha($gd_image, $r_new, $g_new, $b_new, $a_orig);
                imagesetpixel($gd_image, $col, $row, $pix_col);
            }
        }

        // Replace original image with updated image
        $image = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($gd_image);
        unset($gd_image);
        unset($size);

        return $image;
    }
}
