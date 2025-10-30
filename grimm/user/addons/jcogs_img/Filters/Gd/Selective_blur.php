<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Selective Blur Filter
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
 * An Selective Blur filter.
 */
class Selective_blur implements FilterInterface
{
    /**
     * Constructs Selective Blur filter.
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

        // Apply the filter
        imagefilter($gd_image, IMG_FILTER_SELECTIVE_BLUR);
        $image = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($gd_image);

        // Free up memory
        unset($gd_image);
        return $image;
    }
}
