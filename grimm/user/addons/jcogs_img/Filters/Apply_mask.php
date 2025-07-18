<?php

/**
 * JCOGS Image Filter
 * ==================
 * Apply a mask filter
 * Takes two images - $image is the image to mask, $mask is the mask to apply
 * $mask is a black shape on a magic pink background.
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
use Imagine\Image\Palette;
use Imagine\Image\Palette\Color\RGB;

/**
 * Apply a mask filter
 */
class Apply_mask implements FilterInterface
{
    /**
     * @var ImageInterface
     */
    private $mask;

    /**
     * @var RGB
     */
    private $color;

    /**
     * Apply a mask filter.
     *
     * @param ImageInterface $mask
     * @param RGB $color
     */
    public function __construct(ImageInterface $mask, RGB $color = null)
    {
        $this->mask = $mask;
        $this->color = $color ?: new Palette\RGB([0,0,0],0);
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
                $image = (new Gd\Apply_mask($this->mask, $this->color))->apply($image);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
            case ($image instanceof \Imagine\Gmagick\Image):
            default:
                // Do nothing
        }

        return $image;
    }
}
