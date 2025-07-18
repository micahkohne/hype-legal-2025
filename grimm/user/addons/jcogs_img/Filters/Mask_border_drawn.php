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
use Imagine\Image\Palette\Color\RGB;

class Mask_border_drawn implements FilterInterface
{
    /**
     * @var int
     */
    private $border_width;

    /**
     * @var RGB
     */
    private $color;

    /**
     * Constructs Drawn Mask Border filter.
     *
     * @param int $border_width
     * @param RGB $color
     */
    public function __construct(int $border_width = 0, RGB $color = null)
    {
        $this->border_width = $border_width;
        $this->color = $color;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // What kind of image do we have
        switch (true) {
            case ($image instanceof \Imagine\Gd\Image) : 
                $image = (new Gd\Mask_border_drawn_fast($this->border_width, $this->color))->apply($image);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
            case ($image instanceof \Imagine\Gmagick\Image):
            default:
                $image = (new Mask_border_drawn_universal($this->border_width, $this->color))->apply($image);
                break;
    }

        return $image;
    }
}
