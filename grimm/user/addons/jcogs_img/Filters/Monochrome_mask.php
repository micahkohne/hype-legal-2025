<?php

/**
 * JCOGS Image Filter
 * ==================
 * A monochrome mask filter
 * Approach is:
 * 1) Flatten image palette to just two colours
 * 2) Move the darker colour to solid black, move lighter colour to requested colour
 * 3) Return image
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
use Imagine\Image\Palette\Color\RGB;

/**
 * A monochrome mask filter
 */
class Monochrome_mask implements FilterInterface
{
    /**
     * @var RGB
     */
    private $color;

    /**
     * Constructs monochrome mask filter.
     *
     * @param RGB $color
     */
    public function __construct(RGB $color = null)
    {
        $this->color = $color;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        switch (true) {
            case ($image instanceof \Imagine\Gd\Image) : 
                $image = (new \JCOGSDesign\Jcogs_img\Filters\Gd\Monochrome_mask($this->color))->apply($image);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
            case ($image instanceof \Imagine\Gmagick\Image):
            default:
                // Do nothing
        }

        return $image;
    }
}
