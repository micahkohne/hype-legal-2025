<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Scatter filter.
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
 * A Scatter filter.
 */
class Scatter implements FilterInterface
{
    /**
     * @var int
     */
    private $effect_subtraction_level;

    /**
     * @var int
     */
    private $effect_addition_level;

    /**
     * Constructs Scatter filter.
     *
     * @param int $effect_subtraction_level
     * @param int $effect_addition_level
     */
    public function __construct(int $effect_subtraction_level, int $effect_addition_level)
    {
        $this->effect_subtraction_level = $effect_subtraction_level;
        $this->effect_addition_level = $effect_addition_level;
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

        // Check if the image was created successfully
        if ($img === false) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_image_creation_failed'));
            return $image;
        }

        // Apply the scatter filter to the image
        if (imagefilter($img, IMG_FILTER_SCATTER, $this->effect_subtraction_level, $this->effect_addition_level)) {
            // Convert the GD image back to Imagine image
            $image = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($img);
        } else {
            // Log the failure message
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_scatter_failed'));
        }

        // Free up memory
        imagedestroy($img);

        return $image;
    }
}
