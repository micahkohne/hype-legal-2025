<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Smoothing filter.
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
 * A Smoothing filter.
 */
class Smooth implements FilterInterface
{
    /**
     * @var int
     */
    private $level;

    /**
     * Constructs Smoothing filter.
     *
     * @param int $level
     */
    public function __construct(int $level)
    {
        $this->level = $level;
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

        // Filter the image
        if (imagefilter($gd_image, IMG_FILTER_SMOOTH, $this->level)) {
            // Worked - so convert back to Imagine image
            $image = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($gd_image);
            unset($gd_image);
        } else {
            // Failed - so note and return
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_smooth_failed'));
        }
        return $image;
    }
}
