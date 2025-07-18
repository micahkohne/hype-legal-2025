<?php

/**
 * JCOGS Image Filter
 * ==================
 * A simple LQIP filter.
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

/**
 * A simple LQIP filter.
 */
class Lqip implements FilterInterface
{
    /**
     * Constructs Sharpen filter.
     *
     * @param int $sharpening_value
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
        $image = (new Gd\Pixelate(6))->apply($image);
        $image = (new Gd\Blur(12))->apply($image);
        return $image;
    }
}
