<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Slow but Good Sepia Filter.
 * Turns an image into a Sepia equivalent
 * The Slow Sepia filter calculates the gray-value based on RGB.
 * Uses a pixel based method (the one used by CE Image it seems)
 * From here: https://dyclassroom.com/image-processing-project/how-to-convert-a-color-image-into-sepia-image
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
use Imagine\Image\Point;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Filter\Advanced\OnPixelBased;

class Sepia_slow_OnPixel extends OnPixelBased implements FilterInterface
{
    public function __construct()
    {
        parent::__construct(function (ImageInterface $image, Point $point) {
            $color = $image->getColorAt($point);
            // Get the colour in original
            $rOrig = $color->getValue(ColorInterface::COLOR_RED);
            $gOrig = $color->getValue(ColorInterface::COLOR_GREEN);
            $bOrig = $color->getValue(ColorInterface::COLOR_BLUE);
            $aOrig = $color->getAlpha();
            // Work out sepia version of pixel colours
            $rNew = round(min(max(0.393*$rOrig + 0.769*$gOrig + 0.189*$bOrig,0),255),0);
            $gNew = round(min(max(0.349*$rOrig + 0.686*$gOrig + 0.168*$bOrig,0),255),0);
            $bNew = round(min(max(0.272*$bOrig + 0.534*$bOrig + 0.131*$bOrig,0),255),0);
            // Build new color
            $new_color = (new RGB())->color([$rNew,$gNew,$bNew], $aOrig);
            $image->draw()->dot($point, $new_color);
        });
    }
}
