<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Fast GD Reflection filter.
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
use Imagine\Gd\Imagine;
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Image\Point;
use Imagine\Image\Box;

/**
 * A fast Reflect filter
 */
class Reflection_fast implements FilterInterface
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
        // Create image into which build reflection
        // Work out dimensions
        $size = $image->getSize();
        $reflection_size = new Box($size->getWidth(), $size->getHeight() + $this->gap + $this->height - 1);
        try {
            $canvas = (new Imagine())->create($reflection_size, $this->color);
        } catch (\Imagine\Exception\RuntimeException $e) {
            // Creation of image failed.
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'), $e->getMessage());
            return $image;
        }

        // Paste in original image
        $canvas->paste($image, new Point(0, 0));

        $canvas_size = $canvas->getSize();

        // Get the GDImage object
        $gd_image = imagecreatefromstring($image->__toString());

        // Get the canvas object
        $reflection = imagecreatefromstring($canvas->__toString());

        // Work opacity/line increment in GD units (0 = opaque, 127 = transparent)
        // CE Image / Imagine params are opposite direction (0 = transparent, 100 = opaque)
        $starting_opacity = 127 * (1 - $this->starting_opacity / 100);
        $ending_opacity = 127 * (1 - $this->ending_opacity / 100);
        $opacity_change = $ending_opacity - $starting_opacity;
        $opacity_increment = $opacity_change / $this->height;

        // Scan original image to get pixels to write into reflection
        // We scan from top of reflection zone downwards to bottom of picture, and 
        // write from bottom of picture up to bottom gap
        imagealphablending($reflection, false); // Turn off alpha blending
        $row_in_original = $size->getHeight() - 1 - $this->height;
        $row_in_reflection = $canvas_size->getHeight();
        $opacity = $ending_opacity;
        for ($row = 0; $row < $this->height; $row++) {
            // scan across the column each time
            for ($col = 0; $col < $canvas_size->getWidth(); $col++) {
                // Get the pixel from original
                $original_pixel = imagecolorsforindex($gd_image, imagecolorat($gd_image, $col, $row_in_original + $row));
                // Adjust opacity
                $original_pixel['alpha'] = $opacity;
                // Set the colour in the reflection
                $new_pixel = imagecolorallocatealpha($gd_image, (int) $original_pixel['red'], (int) $original_pixel['green'], (int) $original_pixel['blue'], (int) $original_pixel['alpha']);
                imagesetpixel($reflection, $col, $row_in_reflection - $row, $new_pixel);
            }
            // increment opacity value
            $opacity = max(min($opacity - $opacity_increment, 127), 0);
        }

        // Update main image with reflection
        $image = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($reflection);

        unset($canvas);
        unset($gd_image);
        unset($reflection);

        return $image;
    }
}
