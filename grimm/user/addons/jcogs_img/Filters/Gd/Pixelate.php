<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Drawn Mask Border filter.
 * Add border to image that has been masked using drawing methods
 * Approach is:
 * 1) Create a transparent canvas - image size plus 2x border width
 * 2) Scan source image for edges, add border-width circles of border colour at each edge encountered
 * 3) Overlay copy of source image onto border colour canvas
 * 
 * This is a slow version that does not use any GD... 
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
 * A Pixelate filter.
 */
class Pixelate implements FilterInterface
{
    /**
     * @var int
     */
    private $block_size;

    /**
     * @var bool
     */
    private $mode;

    /**
     * Constructs Pixelate filter.
     *
     * @param int $block_size
     */
    public function __construct(int $block_size, bool $mode = false)
    {
        $this->block_size = $block_size;
        $this->mode = $mode;
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
        if (imagefilter($gd_image, IMG_FILTER_PIXELATE, $this->block_size, $this->mode)) {
            // Worked - so convert back to Imagine image
            $image = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($gd_image);
            imagedestroy($gd_image);
        } else {
            // Failed - so note and return
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_pixelate_failed'));
        }
        return $image;
    }
}
