<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Smoothing Filter
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

/**
 * A Gaussian Blur filter.
 */
class Blur implements FilterInterface
{
    /**
     * @var int
     */
    private $amount;

    /**
     * Constructs Blur filter.
     *
     * @param int $amount
     */
    public function __construct(int $amount = null)
    {
        $this->amount = $amount;
    }

    /**
     * Applies a blur filter to the given image.
     *
     * @param ImageInterface $image The image to which the blur filter will be applied.
     * @return ImageInterface The image with the blur filter applied.
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

        // Blur the image
        for ($i = 0; $i < intval($this->amount); $i++) {
            imagefilter($gd_image, IMG_FILTER_GAUSSIAN_BLUR);
        }

        // Convert the GD image back to Imagine image
        $image = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($gd_image);

        // Free up memory
        imagedestroy($gd_image);

        return $image;
    }
}
