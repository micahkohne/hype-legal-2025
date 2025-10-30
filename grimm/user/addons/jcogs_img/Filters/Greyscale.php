<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Greyscale filter.
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

use Imagine\Filter;
use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

/**
 * A Greyscale filter.
 */
class Greyscale implements FilterInterface
{
    /**
     * Constructs Greyscale filter.
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
        switch (true) {
            case ($image instanceof \Imagine\Gd\Image) : 
                $image = (new Gd\Greyscale())->apply($image);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
            case ($image instanceof \Imagine\Gmagick\Image):
            default:
                $image = (new Filter\Advanced\Grayscale())->apply($image);
                break;
        }
        return $image;
    }
}
