<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Dominant Colour Filter
 * Uses library from https://github.com/ksubileau/color-thief-php
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
use Imagine\Gd\Imagine;
use Imagine\Image\Palette;
use ColorThief\ColorThief;

/**
 * A Dominant Colour Filter
 *  
 */
class Dominant_color implements FilterInterface
{
    /**
     * @var int
     */
    private $quality;

    /**
     * Constructs Dominant Color filter.
     *
     * @param int $quality
     */
    public function __construct(int $quality)
    {
        $this->quality = $quality;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Set $dominantColor to false
        $dominantColor = false;

        // Work out dimensions
        $size = $image->getSize();

        // Get the dominant colour
        switch (true) {
            case ($image instanceof \Imagine\Gd\Image) : 
                // Get the GDImage object
                $img = imagecreatefromstring($image->__toString());
                $dominantColor = ColorThief::getColor($img, $this->quality);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
            case ($image instanceof \Imagine\Gmagick\Image):
            default:
                // Do nothing
        }

        // Replace image with dominant colour
        if($dominantColor) {
            try {
                $new_image = (new Imagine())->create($size, (new Palette\RGB())->color($dominantColor));
            } catch(\Imagine\Exception\RuntimeException $e) {
                // Creation of image failed.
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'),$e->getMessage());
                return $image;
            }
        }
        return $new_image;
    }
}
