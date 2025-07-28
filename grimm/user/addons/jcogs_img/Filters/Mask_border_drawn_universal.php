<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Drawn Mask Border filter.
 * Add border to image that has been masked using drawing methods
 * Approach is:
 * 1) Create a transparent canvas - image size plus 2x border width
 * 2) Scan source image for edges, add border-width circles of border colour at each edge encountered
 * 3) Overlay copy of source image onto border colour canvas
 * 
 * This is a slow version that does not use any GD... 
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
use Imagine\Image\Palette\Color\RGB;
use Imagine\Image\Palette;
use Imagine\Image\Point;
use Imagine\Image\Box;

class Mask_border_drawn_universal implements FilterInterface
{
    /**
     * @var int
     */
    private $border_width;

    /**
     * @var RGB
     */
    private $color;

    /**
     * Constructs Mask Border filter.
     *
     * @param int $border_width
     * @param RGB $color
     */
    public function __construct(int $border_width = 0, RGB $color = null)
    {
        $this->border_width = $border_width;
        $this->color = $color;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Did we get some parameters?
        if($this->border_width == 0 || !$this->color) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_adding_border_failed'),['width' => $this->border_width, 'color' => $this->color]);
            return $image;
        }

        // Get the dimensions of image
        $image_width = $image->getSize()->getWidth();
        $image_height = $image->getSize()->getHeight();

        // Get the dimensions of the image after border added;
        $new_x = $image_width + (2 * $this->border_width);
        $new_y = $image_height + (2 * $this->border_width);
        $new_image_size = new Box($new_x, $new_y);
        
        // Create an expanded canvas size with transparent background
        try {
            $border_image = (new Imagine())->create($new_image_size, (new Palette\RGB())->color([0,0,0],0));
        } catch(\Imagine\Exception\RuntimeException $e) {
            // Creation of image failed.
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'),$e->getMessage());
            return $image;
        }

        // Enable drawing on expanded canvas
        $border_image_drawn = $border_image->draw();

        // Scan the scanning image for transparent to opaque transitions (or opposite)
        // When you find them, add border radius circle of black to mask image at same point
        for ($j = 0; $j < $image_height-1; $j++) {
            for ($i = 0; $i < $image_width; $i++) {
                $this_pixel_is_opaque = $image->getColorAt(new Point($i,$j))->isOpaque();
                $the_pixel_above_is_opaque = $j>0 ? $image->getColorAt(new Point($i,$j-1))->isOpaque() : false;

                // Calculate some transition stuff
                $x_transition = isset($the_previous_pixel_is_opaque) && $the_previous_pixel_is_opaque != $this_pixel_is_opaque;
                $y_transition = isset($the_pixel_above_is_opaque) && $the_pixel_above_is_opaque != $this_pixel_is_opaque;
                $x = $x_transition && $this_pixel_is_opaque ? $i : $i-1;
                $y = $y_transition && $this_pixel_is_opaque ? $j : $j-1;

                // Do we have transition?
                if($x_transition || $y_transition) {
                    // Add a filled circle of border colour at pixel boundary we have found.
                    // Adding to expanded canvas, so add 1x width offset
                    $border_image_drawn->circle(new Point($x+$this->border_width,$y+$this->border_width),$this->border_width, $this->color, true);
                }
                $the_previous_pixel_is_opaque = $this_pixel_is_opaque;
            }
        }
       
        // Copy actual image on top of border image
        $border_image->paste($image, new Point($this->border_width,$this->border_width));

        // Update $image
        $image = $border_image->copy();

        // Clean up and return
        unset($border_image);

        return $image;
    }
}