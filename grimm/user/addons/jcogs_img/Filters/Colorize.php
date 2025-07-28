<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Colorize Filter
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
 * A Colorize filter.
 */
class Colorize implements FilterInterface
{
    /**
     * @var array
     */
    private $rgb_adjustment;

    /**
     * Constructs Colorize filter.
     *
     * @param array $rgb_adjustment
     */
    public function __construct(array $rgb_adjustment = null)
    {
        $this->rgb_adjustment = $rgb_adjustment;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Did we get some parameters?
        if(!$this->rgb_adjustment) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_colorize_failed'));
            return $image;
        }

        switch (true) {
            case ($image instanceof \Imagine\Gd\Image) : 
                $image = (new Gd\Colorize($this->rgb_adjustment))->apply($image);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
            case ($image instanceof \Imagine\Gmagick\Image):
            default:
                // Do nothing
        }
        return $image;
    }
}
