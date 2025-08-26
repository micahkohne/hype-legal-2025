<?php

/**
 * JCOGS Image Filter
 * ==================
 * A mask filter
 * Mask an image mask to get cut-out of another image
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
use Imagine\Filter;
use Imagine\Image\Palette\Color\RGB;
use Imagine\Image\Palette;
use Imagine\Image\Point;
use Imagine\Image\PointSigned;
use Imagine\Image\Box;

/**
 * A Mask filter.
 */
class Mask implements FilterInterface
{
    /**
     * @var array
     */
    private $filter_settings;

    /**
     * Constructs Mask filter.
     *
     * @param array $filter_settings
     * @param RGB $background_colour
     */
    public function __construct(array $filter_settings)
    {
        $this->filter_settings = $filter_settings;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Do we have any settings?
        if(empty($this->filter_settings)) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_mask_shape_param_invalid'));
            return $image;
        }
        // Define some colours
        $magic_pink = (new Palette\RGB())->color([255,0,255],100);
        $keep_colour = (new Palette\RGB())->color([0,255,255],100);

        // Using fake anti-alias method
        $original_size = $image->getSize();
        // Work out dimensions for double size image
        $working_image_size = $image->getSize()->scale(2);
        // Make a copy of main image twice the size of original
        $working_image = $image->resize($working_image_size);

        // Process Filter settings to work out what we need to build 
        // Mask Filter Settings: 
        //      shape, 
        //      horizontal position, vertical position, 
        //      width, [height | rotation, star-split]

        $shape = trim(array_shift($this->filter_settings));
        // unpack complex shapes if required
        if(substr(strtolower($shape),0,5) == 'star-') {
            // it is a star
            $spikes = intval(substr($shape,5));
            if(!is_int($spikes) || $spikes < 3) {
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_mask_shape_param_invalid'),$shape);
                return $image;
            }
            $shape = 'star';
        }
        if(substr(strtolower($shape),0,8) == 'polygon-') {
            // it is a star
            $vertices = intval(substr($shape,8));
            if(!is_int($vertices) || $vertices < 3) {
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_mask_shape_param_invalid'),$shape);
                return $image;
            }
            $shape = 'polygon';
        }

        // Set a flag for processing of mask images
        $unpack_failed = false;
        
        // If it is a standard shape some common steps first then draw shape
        if(in_array($shape,['circle','ellipse','polygon','rectangle','square','star'])) {

            // Create a magic pink canvas the same size as working image
            try {
                $mask_image = (new Imagine())->create($working_image_size, $magic_pink);
            } catch(\Imagine\Exception\RuntimeException $e) {
                // Creation of image failed.
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'),$e->getMessage());
                return $image;
            }

            // Create drawing layer for $mask_image
            $mask_image_draw = $mask_image->draw();

            // unpack horizontal position
            $shape_horizontal_position = count($this->filter_settings) ? trim(array_shift($this->filter_settings)) : '50%';
            $shape_horizontal_position = ee('jcogs_img:ImageUtilities')->validate_dimension($shape_horizontal_position, (int) $working_image_size->getWidth());

            // unpack vertical position
            $shape_vertical_position = count($this->filter_settings) ? trim(array_shift($this->filter_settings)) : '50%';
            $shape_vertical_position = ee('jcogs_img:ImageUtilities')->validate_dimension($shape_vertical_position,(int) $working_image_size->getHeight());

            // unpack size of shape
            // Use shorter of width and height for size validation - to ensure shape fits into image
            $shape_base_length = min($working_image_size->getWidth(),$working_image_size->getHeight());
            // Shape width
            $shape_width = count($this->filter_settings) ? trim(array_shift($this->filter_settings)) : '100%';
            $shape_width = ee('jcogs_img:ImageUtilities')->validate_dimension($shape_width,(int) $shape_base_length);

            // process local filter choice
            switch ($shape) {
                case 'circle':
                    // Adjust shape width to 50% as circle takes radius not diameter
                    $shape_width = round($shape_width/2, 0);
                    // Draw the circle for the mask - use PointSigned since circle origin may be outside of the image shape
                    $mask_image_draw->circle(new PointSigned($shape_horizontal_position, $shape_vertical_position), (int) round($shape_width,0), $keep_colour, true);
                    break;

                case 'ellipse': 
                    // Unpack elipse height
                    $shape_height = count($this->filter_settings) ? ee('jcogs_img:ImageUtilities')->validate_dimension(trim(array_shift($this->filter_settings)),(int) $shape_base_length) : $shape_width;

                    // Draw the ellipse for the mask
                    $mask_image_draw->ellipse(new PointSigned($shape_horizontal_position, $shape_vertical_position), new Box((int) $shape_width, (int) $shape_height), $keep_colour, true);
                    break;

                case 'polygon':
                    ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_mask_draw_polygon'),$shape.' with '.$vertices.' points');

                    // Unpack polygon rotation
                    $shape_rotation = count($this->filter_settings) ? intval(trim(array_shift($this->filter_settings))) : 0;

                    // Get the polygon point array
                    $polygon_points = ee('jcogs_img:ImageUtilities')->draw_rotated_polygon($shape_horizontal_position,$shape_vertical_position,(int)round($shape_width/2,0),$vertices,$shape_rotation);

                    // Draw the polygon for the mask
                    $mask_image_draw->polygon($polygon_points, $keep_colour, true);
                    break;

                case 'rectangle':
                    // Unpack rectangle height
                    $shape_height = count($this->filter_settings) ? ee('jcogs_img:ImageUtilities')->validate_dimension(trim(array_shift($this->filter_settings)),(int) $shape_base_length) : $shape_width;

                    // Draw the rectangle for the mask
                    $mask_image_draw->rectangle(new PointSigned($shape_horizontal_position - round($shape_width/2,0), $shape_vertical_position - round($shape_height/2,0)), new Point($shape_horizontal_position + round($shape_width/2,0), $shape_vertical_position + round($shape_height/2,0)), $keep_colour, true);
                    break;
    
                case 'square':
                    // Draw the square for the mask
                    $mask_image_draw->rectangle(new PointSigned($shape_horizontal_position - round($shape_width/2,0), $shape_vertical_position - round($shape_width/2,0)), new Point($shape_horizontal_position + round($shape_width/2,0), $shape_vertical_position + round($shape_width/2,0)), $keep_colour, true);
                    break;
    
                case 'star':
                    ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_mask_draw_star'),$shape.' with '.$spikes.' points');

                    // Unpack star rotation
                    $shape_rotation = count($this->filter_settings) ? intval(trim(array_shift($this->filter_settings))) : 0;

                    // Unpack split ratio
                    $split = count($this->filter_settings) ? trim(array_shift($this->filter_settings)) : 0.5;

                    // Get the polygon point array
                    $polygon_points = ee('jcogs_img:ImageUtilities')->draw_rotated_star($shape_horizontal_position,$shape_vertical_position,(int)round($shape_width/2,0),$spikes,$split,$shape_rotation);

                    // Draw the polygon for the mask
                    $mask_image_draw->polygon($polygon_points, $keep_colour, true);

                    break;
            }
            if($unpack_failed) {
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_mask_unknown_shape'),$shape);
                return $image;
            }
        } elseif ($shape == 'image') {
            // Unpack image path
            $shape_image_path = count($this->filter_settings) > 0 ? array_shift($this->filter_settings) : false;
            if(!$shape_image_path) {
                $unpack_failed = true;
            }

            // Get a copy of the image if we can
            if(!$shape_image = ee('jcogs_img:ImageUtilities')->get_a_local_copy_of_image($shape_image_path)) {
                $unpack_failed = true;
            }

            if($unpack_failed) {
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_mask_unknown_shape'),$shape);
                return $image;
            }

            // Load image as temp_image
            try {
                $temp_image = (new Imagine())->load($shape_image['image_source']);
            } catch(\Imagine\Exception\RuntimeException $e) {
                // Creation of image failed.
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'),$e->getMessage());
                return $image;
            }

            // Resize to fit mask shape
            $temp_image->resize($working_image_size);

            // Convert image to a bichromal transparent / black mask
            $temp_image_transform = new Filter\Transformation(new Imagine());
            $mask_image = $temp_image_transform->applyFilter($temp_image, new Monochrome_mask($keep_colour));

            unset($temp_image);
            unset($temp_image_transform);
            unset($shape_image);

        } else {
            // unknown shape so bale
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_mask_unknown_shape'),$shape);
            return $image;
        }

        // Now apply mask to original image
        $mask_image_transform = new Filter\Transformation(new Imagine());
        $mask_image = $mask_image_transform->applyFilter($working_image, new Apply_mask($mask_image, $keep_colour));

        // Rescale working image back to normal size
        $image = $mask_image->copy()->resize($original_size);

        // Clean up
        unset($mask_image);
        unset($mask_image_transform);

        return $image;
    }
}
