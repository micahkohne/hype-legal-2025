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

namespace JCOGSDesign\Jcogs_img\Filters;

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
        switch (true) {
            case ($image instanceof \Imagine\Gd\Image) : 
                $image = (new \JCOGSDesign\Jcogs_img\Filters\Gd\Scatter($this->effect_subtraction_level, $this->effect_addition_level))->apply($image);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
            case ($image instanceof \Imagine\Gmagick\Image):
            default:
                // Do nothing
        }
        return $image;
    }
}
