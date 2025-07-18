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
 * @version    1.4.16.1
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.3
 */

namespace JCOGSDesign\Jcogs_img\Filters\Gd;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Gd\Imagine;
use Imagine\Image\Palette\Color\RGB;
use Imagine\Image\Palette;
use Imagine\Image\Point;
use Imagine\Image\Box;

/**
 * A Box Border filter.
 */
class Mask_border_drawn_fast implements FilterInterface
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
    public function apply(ImageInterface $image)
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
        
        // Create an expanded canvas size with transparent background for source image
        try {
            $source_image = (new Imagine())->create($new_image_size, (new Palette\RGB())->color([0,0,0],0));
        } catch(\Imagine\Exception\RuntimeException $e) {
            // Creation of image failed.
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'),$e->getMessage());
            return $image;
        }
        $source_image->paste($image, new Point($this->border_width,$this->border_width));

        // Create an expanded canvas size with transparent background for border image
        try {
            $border_image = (new Imagine())->create($new_image_size, (new Palette\RGB())->color([0,0,0],0));
        } catch(\Imagine\Exception\RuntimeException $e) {
            // Creation of image failed.
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'),$e->getMessage());
            return $image;
        }

        // Flip image and mask into GDImage objects
        $working_image = imagecreatefromstring($source_image->__toString());
        $working_border_image = imagecreatefromstring($border_image->__toString());

        // Get the chosen border colour as an index
        $border_color_index = imagecolorallocatealpha($working_border_image, $this->color->getRed(), $this->color->getGreen(), $this->color->getBlue(), $this->color->getAlpha());

        // Scan the working image for transparent to opaque transitions (or opposite)
        // When you find them, add border radius circle of black to mask image at same point
        for ($j = 0; $j < $new_y-1; $j++) {
            for ($i = 0; $i < $new_x; $i++) {
                $this_pixel_is_opaque = (imagecolorat($working_image,$i,$j) & 0x7F000000) >> 24 == 0;
                $the_pixel_above_is_opaque = $j>0 ? (imagecolorat($working_image,$i,$j-1) & 0x7F000000) >> 24 == 0 : false;

                // Calculate some transition stuff
                $x_transition = isset($the_previous_pixel_is_opaque) && $the_previous_pixel_is_opaque != $this_pixel_is_opaque;
                $y_transition = isset($the_pixel_above_is_opaque) && $the_pixel_above_is_opaque != $this_pixel_is_opaque;
                $x = $x_transition && $this_pixel_is_opaque ? $i : $i-1;
                $y = $y_transition && $this_pixel_is_opaque ? $j : $j-1;

                // Do we have transition?
                if($x_transition || $y_transition) {
                    // Add a filled circle of border colour at pixel boundary we have found.
                    imagefilledellipse($working_border_image, $x, $y, $this->border_width*2, $this->border_width*2, $border_color_index);
                }
                $the_previous_pixel_is_opaque = $this_pixel_is_opaque;
            }
        }

        // Flip working image back to border image
        $border_image = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($working_border_image);

        // Copy actual image on top of border image
        $border_image->paste($source_image, new Point(0,0));

        // Update $image
        $image = $border_image->copy();

        // Clean up and return
        unset($border_image);
        unset($source_image);
        unset($border_working_image);

        return $image;
    }

    /**
     * Checks to see if co-ordinates given are over an opaque pixel in an image
     *
     * @param  resource|object  $image
     * @param  int  $x
     * @param  int  $y
     * @return boolean
     */
    protected function is_pixel_opaque ($image,$x,$y) {
        $x = round($x,0);
        $y = round($y,0);
        if (((gettype($image) == "object" && get_class($image) == "GdImage") || get_resource_type($image) == 'gd') && $x >= 0 && $x < imagesx($image) && $y >= 0 && $y < imagesy($image)) {
                return (imagecolorat($image,$x,$y) & 0x7F000000) >> 24 == 0;
            } else {
                return false;
            }
    }
}