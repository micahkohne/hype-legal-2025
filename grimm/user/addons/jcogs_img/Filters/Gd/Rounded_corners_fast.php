<?php

/**
 * JCOGS Image Filter
 * ==================
 * Add rounded corners to image
 * Approach is build a mask based on four corner circles and fill in
 * space between, and then mask the image with what results.
 * 1) unpack parameters
 * 2) calculate dimensions / origins of corners and fill-in rectangles
 * 3) build mask with shapes
 * 4) apply mask to image
 * 5) move masked image back to image
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
use Imagine\Filter;
use Imagine\Image\Palette;

/**
 * A Box Border filter.
 */
class Rounded_corners_fast implements FilterInterface
{
    /**
     * @var array
     */
    private $rounded_corner_working;

    /**
     * @var array
     */
    private $infill_masks;

    /**
     * Constructs Mask Border filter.
     *
     * @param array $rounded_corner_masks
     * @param array $infill_masks
     */
    public function __construct(array $rounded_corner_working, array $infill_masks)
    {
        $this->rounded_corner_working = $rounded_corner_working;
        $this->infill_masks = $infill_masks;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Get the dimensions of image
        $image_size = $image->getSize();
        
        // Define some colours
        $magic_pink = (new Palette\RGB())->color([255,0,255],100);
        $keep_colour = (new Palette\RGB())->color([0,255,255],100);

        // Create a magic pink canvas the same size as working image
        try {
            $canvas = (new Imagine())->create($image_size, $magic_pink);
        } catch(\Imagine\Exception\RuntimeException $e) {
            // Creation of image failed.
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'),$e->getMessage());
            return $image;
        }

        // Flip image and mask into GDImage objects
        $working_canvas = imagecreatefromstring($canvas->__toString());
        imagealphablending($working_canvas, false); // Turn off alpha blending

        // Get palette index for $keep_colour
        $keep_color_index = imagecolorallocatealpha($working_canvas, 0, 255, 255, 127);

        // Add corners to mask image
        foreach($this->rounded_corner_working as $corner) {
            if($corner['radius'] > 0) {
                // Draw the circle for the mask
                imagefilledellipse($working_canvas, $corner['x'], $corner['y'], $corner['radius']*2, $corner['radius']*2, $keep_color_index);
            }
        }

        // Add fill in boxes to mask image
        foreach($this->infill_masks as $mask) {
            imagefilledrectangle($working_canvas, $mask['x'],$mask['y'],$mask['x']+$mask['width'],$mask['y']+$mask['height'],$keep_color_index);
        }

        // Convert back to Imagine image
        $canvas = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($working_canvas);

        // Now apply mask to original image
        $image_transform = new Filter\Transformation(new Imagine());
        $new_image = $image_transform->applyFilter($image, new Apply_mask($canvas, $keep_colour));

        // Clean up
        $image = $new_image->copy();
        unset($new_image);
        unset($working_canvas);
        unset($canvas);

        // Return
        return $image;
    }
}