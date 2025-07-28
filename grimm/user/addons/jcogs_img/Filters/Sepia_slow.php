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

namespace JCOGSDesign\Jcogs_img\Filters;

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
        // What kind of image do we have
        $image_type = get_class($image);

        switch (true) {
            case ($image instanceof \Imagine\Gd\Image) : 
                $image = (new Gd\Sepia_slow())->apply($image);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
            case ($image instanceof \Imagine\Gmagick\Image):
            default:
                // Do nothing
        }

        return $image;
    }
}
