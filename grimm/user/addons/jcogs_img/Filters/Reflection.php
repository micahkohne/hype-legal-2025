<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Reflection filter.
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
use Imagine\Image\Palette\Color\ColorInterface;

/**
 * A Greyscale filter.
 */
class Reflection implements FilterInterface
{
    /**
     * @var ColorInterface
     */
    private $color;

    /**
     * @var int
     */
    private $gap;

    /**
     * @var int
     */
    private $starting_opacity;

    /**
     * @var int
     */
    private $ending_opacity;

    /**
     * @var int
     */
    private $height;

    /**
     * Constructs Reflection filter.
     *
     * @param ColorInterface $color
     * @param int $gap
     * @param int $starting_opacity
     * @param int $ending_opacity
     * @param int $height
     */
    public function __construct(ColorInterface $color, int $height, int $gap = 0, int $starting_opacity = 80, int $ending_opacity = 0)
    {
        $this->color = $color;
        $this->gap = $gap;
        $this->starting_opacity = $starting_opacity;
        $this->ending_opacity = $ending_opacity;
        $this->height = $height;
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
                $image = (new \JCOGSDesign\Jcogs_img\Filters\Gd\Reflection_fast($this->color, $this->height, $this->gap, $this->starting_opacity, $this->ending_opacity))->apply($image);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
            case ($image instanceof \Imagine\Gmagick\Image):
            default:
                $image = (new Reflection_slow($this->color, $this->height, $this->gap, $this->starting_opacity, $this->ending_opacity))->apply($image);
                break;
        }
        return $image;
    }
}
