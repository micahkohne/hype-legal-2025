<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Filter to replace a colour with another subject to a tolerance value
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
 * A Smoothing filter.
 */
class Replace_colors implements FilterInterface
{

    /**
     * @var RGB
     */
    private $from_color;


    /**
     * @var RGB
     */
    private $to_color;

    /**
     * @var int // 0 -> 100
     */
    private $tolerance;

    /**
     * Constructs Smoothing filter.
     *
     * @param int $tolerance
     */
    public function __construct(RGB $from_color, RGB $to_color, int $tolerance)
    {
        $this->tolerance = min(max($tolerance,0),100);
        $this->from_color = $from_color;
        $this->to_color = $to_color;
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
                $image = (new \JCOGSDesign\Jcogs_img\Filters\Gd\Replace_colors($this->from_color, $this->to_color, $this->tolerance))->apply($image);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
            case ($image instanceof \Imagine\Gmagick\Image):
            default:
                // Do nothing
        }
        return $image;
    }
}
