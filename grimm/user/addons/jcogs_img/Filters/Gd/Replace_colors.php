<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Filter to replace a colour with another subject to a tolerance value
 * Adapted from method "Replace a color with another color in an image with PHP"
 * found at https://www.itcodar.com/php/php-replace-colour-within-image.html
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

 namespace JCOGSDesign\Jcogs_img\Filters\Gd;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\Color\RGB;

/**
 * A Gaussian Blur filter.
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
     * Constructs Replace_colors filter.
     *
     * @param RGB $from_color
     * @param RGB $to_color
     * @param int $tolerance
     */
    public function __construct(RGB $from_color, RGB $to_color, int $tolerance)
    {
        $this->tolerance = min(max($tolerance, 0), 100) * 1.8; // converts tolerance from 0-100 to 0-180
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
        // Get the GDImage object
        $gd_image = imagecreatefromstring($image->__toString());

        $from_r = $this->from_color->getRed();
        $from_g = $this->from_color->getGreen();
        $from_b = $this->from_color->getBlue();

        $to_r = $this->to_color->getRed();
        $to_g = $this->to_color->getGreen();
        $to_b = $this->to_color->getBlue();

        // Set up the replacement
        $color_to_replace = ee('jcogs_img:ImageUtilities')->RGBtoHSL($from_r, $from_g, $from_b);
        $replacement_color = ee('jcogs_img:ImageUtilities')->RGBtoHSL($to_r, $to_g, $to_b);
        $hue_absolute_error = $this->tolerance;
        
        $out = imagecreatetruecolor(imagesx($gd_image), imagesy($gd_image));
        $trans_color = imagecolorallocatealpha($out, 254, 254, 254, 127);
        imagefill($out, 0, 0, $trans_color);

        for ($x = 0; $x < imagesx($gd_image); $x++) {
            for ($y = 0; $y < imagesy($gd_image); $y++) {
                $pixel = imagecolorat($gd_image, $x, $y);

                $red = ($pixel >> 16) & 0xFF;
                $green = ($pixel >> 8) & 0xFF;
                $blue = $pixel & 0xFF;
                $alpha = ($pixel & 0x7F000000) >> 24;

                $color_hsl = ee('jcogs_img:ImageUtilities')->RGBtoHSL($red, $green, $blue);

                if ((($color_hsl[0] >= $color_to_replace[0] - $hue_absolute_error) && ($color_to_replace[0] + $hue_absolute_error) >= $color_hsl[0])) {
                    $color = ee('jcogs_img:ImageUtilities')->HSLtoRGB($replacement_color[0], $replacement_color[1], $color_hsl[2]);
                    $red = $color[0];
                    $green = $color[1];
                    $blue = $color[2];
                }

                if ($alpha == 127) {
                    imagesetpixel($out, $x, $y, $trans_color);
                } else {
                    imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $red, $green, $blue, $alpha));
                }
            }
        }
        $image = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($out);
        unset($gd_image);
        unset($out);
        return $image;
    }
}
