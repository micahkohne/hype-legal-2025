<?php

/**
 * JCOGS Image Filter
 * ==================
 * An EdgeDetect Filter
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
 * An EdgeDetect filter.
 */
class Edgedetect implements FilterInterface
{
    /**
     * Constructs Edgedetect filter.
     *
     * @param int $amount
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

        // Check if the GD image was created successfully
        if ($gd_image === false) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_image_creation_failed'));
            return $image;
        }

        // Apply the filter
        imagefilter($gd_image, IMG_FILTER_EDGEDETECT);

        // Convert the GD image back to Imagine image
        $image = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($gd_image);

        // Free up memory
        imagedestroy($gd_image);

        return $image;
    }
}
