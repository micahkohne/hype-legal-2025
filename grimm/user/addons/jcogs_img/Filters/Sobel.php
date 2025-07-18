<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Sobel Edgify filter.
 * Applies a sobel edgify filter to an image.
 * Uses algorithm / code derived from: 
 * https://github.com/qmegas/sobel-operator
 * 
 * The Sobel Edgify filter is a mechanism for detecting edges in images and is used
 * in computer imaging applications.
 * 
 * Default threshold value of 40 to match default value used by CE Image
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

namespace JCOGSDesign\Jcogs_img\Filters;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Qmegas\SobelOperator;

class Sobel implements FilterInterface
{
    /**
     * @var float
     */
    private $threshold;

    /**
     * Constructs Sharpen filter.
     *
     * @param int $threshold
     */
    public function __construct(int $threshold = 125)
    {
        $this->threshold = $threshold;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Get the GDImage object
        $img = imagecreatefromstring($image->__toString());

        // Set some constant values
        $flat = true;
        $return_threshold = false;

        // Apply the filters
        $sobel = new SobelOperator;
        $temp_image = $sobel->applyFilter($img, [
            'flat' => $flat,
            'threshold' => $this->threshold,
            'return_threshold' => $return_threshold
        ]);

        // Invert the image to get black edges on white background
        imagefilter($temp_image, IMG_FILTER_NEGATE);

        // Replace original image with updated image
        $image = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($temp_image);
        unset($temp_image);
        unset($img);

        return $image;
    }
}
