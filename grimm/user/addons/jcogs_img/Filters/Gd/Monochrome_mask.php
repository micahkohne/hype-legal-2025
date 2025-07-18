<?php

/**
 * JCOGS Image Filter
 * ==================
 * A monochrome mask filter
 * Approach is:
 * 1) Flatten image palette to just two colours
 * 2) Move the darker colour to solid black, move lighter colour to requested colour
 * 3) Return image
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

namespace JCOGSDesign\Jcogs_img\Filters\Gd;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\Color\RGB;

/**
 * A Monochrome Mask filter.
 */
class Monochrome_mask implements FilterInterface
{
    /**
     * @var RGB
     */
    private $color;

    /**
     * Constructs Monochrome Mask filter.
     *
     * @param array $color
     */
    public function __construct(RGB $color = null)
    {
        $this->color = $color;
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

        // Force image to be monochromatic
        imagetruecolortopalette($gd_image, false, 2);

        // Force palette to either black or chosen colour
        if (imagecolorsforindex($gd_image, 0)['red'] < 127) {
            // Index 0 is black so replace with chosen colour
            imagecolorset($gd_image, 0, $this->color->getRed(), $this->color->getGreen(), $this->color->getBlue(), 0);
            // Index 1 is white so replace with magic pink 
            imagecolorset($gd_image, 1, 255, 0, 255, 0);
        } else {
            // Index 0 is white so replace with magic pink
            imagecolorset($gd_image, 0, 255, 0, 255, 0);
            // Index 1 is black so replace with chosen colour 
            imagecolorset($gd_image, 1, $this->color->getRed(), $this->color->getGreen(), $this->color->getBlue(), 0);
        }

        // Convert back to truecolor image
        imagepalettetotruecolor($gd_image);

        // Convert back to Imagine image
        $image = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($gd_image);
        unset($gd_image);

        return $image;
    }
}
