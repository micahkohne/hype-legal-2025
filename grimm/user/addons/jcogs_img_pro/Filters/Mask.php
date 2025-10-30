<?php

/**
 * JCOGS Image Pro - Mask Filter
 * =============================
 * Advanced masking effects filter with shape and feathering support
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 2 Native Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Filters;

use Imagine\Gd\Imagine;
use Imagine\Image\Palette;
use Imagine\Image\Point;
use Imagine\Image\PointSigned;
use Imagine\Image\Box;

/**
 * Mask Filter
 * 
 * Applies various masking effects to images using legacy-compatible Imagine approach.
 */
class Mask extends ImageAbstractFilter
{
    private array $mask_params;

    /**
     * Constructs Mask filter.
     * 
     * @param array $mask_params Mask parameters array (default: [])
     */
    public function __construct(array $mask_params = [])
    {
        parent::__construct();
        $this->mask_params = $mask_params;
    }
    
    /**
     * Apply mask filter to image
     *
     * @param mixed $image The image data (Imagine object)
     * @param array $parameters Filter parameters array (legacy format, for backward compatibility)
     * @return mixed The processed image data
     */
    public function apply($image, array $parameters = [])
    {
        // Use constructor parameters, with fallback to apply params for backward compatibility
        $working_parameters = !empty($parameters) ? $parameters : $this->mask_params;
        
        $this->utilities_service->debug_log("Pro Mask filter called with parameters: " . json_encode($working_parameters));
        $this->utilities_service->debug_log("DEBUG: JCOGS Pro Mask filter CALLED with params: " . json_encode($working_parameters));
        
        // Create a working copy of parameters for array_shift processing (legacy compatibility)
        $filter_settings = $working_parameters; // Use working_parameters, not just parameters
        
        // Extract parameters using array_shift like legacy version
        $type = trim(array_shift($filter_settings) ?? 'circle');
        
        // unpack complex shapes if required (legacy compatibility)
        $spikes = null;
        $vertices = null;
        if(substr(strtolower($type),0,5) == 'star-') {
            // it is a star
            $spikes = intval(substr($type,5));
            if(!is_int($spikes) || $spikes < 3) {
                $this->utilities_service->debug_message(lang('jcogs_img_mask_shape_param_invalid'),$type);
                return $image;
            }
            $type = 'star';
        }
        if(substr(strtolower($type),0,8) == 'polygon-') {
            // it is a polygon
            $vertices = intval(substr($type,8));
            if(!is_int($vertices) || $vertices < 3) {
                $this->utilities_service->debug_message(lang('jcogs_img_mask_shape_param_invalid'),$type);
                return $image;
            }
            $type = 'polygon';
        }
        
        // Debug logging to EE system logs
        $this->utilities_service->debug_log("Pro Mask filter extracted type: $type");

        // Set a flag for processing of mask images
        $unpack_failed = false;

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

        if(substr(strtolower($type),0,5) == 'star-') {
            // it is a star
            $spikes = intval(substr($type,5));
            if(!is_int($spikes) || $spikes < 3) {
                $this->utilities_service->debug_message(lang('jcogs_img_mask_shape_param_invalid'),$type);
                return $image;
            }
            $type = 'star';
        }
        if(substr(strtolower($type),0,8) == 'polygon-') {
            // it is a star
            $vertices = intval(substr($type,8));
            if(!is_int($vertices) || $vertices < 3) {
                $this->utilities_service->debug_message(lang('jcogs_img_mask_shape_param_invalid'),$type);
                return $image;
            }
            $type = 'polygon';
        }

        // Set a flag for processing of mask images
        $unpack_failed = false;
        
        // If it is a standard shape some common steps first then draw shape
        if(in_array($type,['circle','ellipse','polygon','rectangle','square','star'])) {
            $this->utilities_service->debug_log("Processing standard shape: $type");

            // Create a magic pink canvas the same size as working image
            try {
                $mask_image = (new Imagine())->create($working_image_size, $magic_pink);
                $this->utilities_service->debug_log("Created mask canvas: " . $working_image_size->getWidth() . "x" . $working_image_size->getHeight());
            } catch(\Imagine\Exception\RuntimeException $e) {
                // Creation of image failed.
                $this->utilities_service->debug_message(lang('jcogs_img_imagine_error'),$e->getMessage());
                $this->utilities_service->debug_log("Failed to create mask canvas: " . $e->getMessage());
                return $image;
            }

            // Create drawing layer for $mask_image
            $mask_image_draw = $mask_image->draw();

            // unpack horizontal position
            $horizontal_position = count($filter_settings) ? trim(array_shift($filter_settings)) : '50%';
            $horizontal_position = $this->image_utilities_service->validate_dimension($horizontal_position, (int) $working_image_size->getWidth());

            // unpack vertical position
            $vertical_position = count($filter_settings) ? trim(array_shift($filter_settings)) : '50%';
            $vertical_position = $this->image_utilities_service->validate_dimension($vertical_position,(int) $working_image_size->getHeight());

            // unpack size of shape
            // Use shorter of width and height for size validation - to ensure shape fits into image
            $shape_base_length = min($working_image_size->getWidth(),$working_image_size->getHeight());
            // Shape width
            $width = count($filter_settings) ? trim(array_shift($filter_settings)) : '100%';
            $width = $this->image_utilities_service->validate_dimension($width,(int) $shape_base_length);

            // process local filter choice
            switch ($type) {
                case 'circle':
                    $this->utilities_service->debug_log("Drawing circle mask with width: $width at position ($horizontal_position, $vertical_position)");
                    // Adjust shape width to 50% as circle takes radius not diameter
                    $width = round($width/2, 0);
                    // Draw the circle for the mask - use PointSigned since circle origin may be outside of the image shape
                    $mask_image_draw->circle(new PointSigned($horizontal_position, $vertical_position), (int) round($width,0), $keep_colour, true);
                    $this->utilities_service->debug_log("Circle drawn successfully");
                    break;

                case 'ellipse': 
                    // Unpack ellipse height
                    $height = count($filter_settings) ? $this->image_utilities_service->validate_dimension(trim(array_shift($filter_settings)),(int) $shape_base_length) : $width;

                    // Draw the ellipse for the mask
                    $mask_image_draw->ellipse(new PointSigned($horizontal_position, $vertical_position), new Box((int) $width, (int) $height), $keep_colour, true);
                    break;

                case 'polygon':
                    $this->utilities_service->debug_message(lang('jcogs_img_mask_draw_polygon'), $type.' with '.$vertices.' points');

                    // Unpack polygon rotation
                    $rotation = count($filter_settings) ? intval(trim(array_shift($filter_settings))) : 0;

                    // Get the polygon point array
                    $polygon_points = $this->draw_rotated_polygon($horizontal_position,$vertical_position,(int)round($width/2,0),$vertices,$rotation);

                    // Draw the polygon for the mask
                    $mask_image_draw->polygon($polygon_points, $keep_colour, true);
                    break;

                case 'rectangle':
                    // Unpack rectangle height
                    $height = count($filter_settings) ? $this->image_utilities_service->validate_dimension(trim(array_shift($filter_settings)),(int) $shape_base_length) : $width;

                    // Draw the rectangle for the mask
                    $mask_image_draw->rectangle(new PointSigned($horizontal_position - round($width/2,0), $vertical_position - round($height/2,0)), new Point($horizontal_position + round($width/2,0), $vertical_position + round($height/2,0)), $keep_colour, true);
                    break;
    
                case 'square':
                    // Draw the square for the mask
                    $mask_image_draw->rectangle(new PointSigned($horizontal_position - round($width/2,0), $vertical_position - round($width/2,0)), new Point($horizontal_position + round($width/2,0), $vertical_position + round($width/2,0)), $keep_colour, true);
                    break;
    
                case 'star':
                    $this->utilities_service->debug_message(lang('jcogs_img_mask_draw_star'), $type.' with '.$spikes.' points');

                    // Unpack star rotation
                    $rotation = count($filter_settings) ? intval(trim(array_shift($filter_settings))) : 0;

                    // Unpack split ratio
                    $star_split = count($filter_settings) ? trim(array_shift($filter_settings)) : 0.5;

                    // Get the polygon point array
                    $polygon_points = $this->draw_rotated_star($horizontal_position,$vertical_position,(int)round($width/2,0),$spikes,$star_split,$rotation);

                    // Draw the polygon for the mask
                    $mask_image_draw->polygon($polygon_points, $keep_colour, true);

                    break;
            }
            if($unpack_failed) {
                $this->utilities_service->debug_message(lang('jcogs_img_mask_unknown_shape'), $type);
                return $image;
            }
        } elseif ($type == 'image') {
            // Unpack image path
            $image_path = count($filter_settings) > 0 ? array_shift($filter_settings) : false;
            if(!$image_path) {
                $unpack_failed = true;
            }

            // Get a copy of the image if we can
            if(!$type_image = $this->image_utilities_service->get_a_local_copy_of_image($image_path)) {
                $unpack_failed = true;
            }

            if($unpack_failed) {
                $this->utilities_service->debug_message(lang('jcogs_img_mask_unknown_shape'), $type);
                return $image;
            }

            // Load image as temp_image
            try {
                $temp_image = (new Imagine())->load($type_image['image_source']);
            } catch(\Imagine\Exception\RuntimeException $e) {
                // Creation of image failed.
                $this->utilities_service->debug_message(lang('jcogs_img_mask_imagine_error'), $e->getMessage());
                return $image;
            }

            // Resize to fit mask shape
            $temp_image->resize($working_image_size);

            // Convert image to a bichromal transparent / black mask
            $monochrome_mask = new MonochromeMask($keep_colour);
            $mask_image = $monochrome_mask->apply($temp_image);

            unset($temp_image);
            unset($type_image);

        } else {
            // unknown shape so bale
            $this->utilities_service->debug_message(lang('jcogs_img_mask_unknown_shape'), $type);
            return $image;
        }

        // Now apply mask to original image
        $this->utilities_service->debug_log("Applying mask to original image");
        $apply_mask = new ApplyMask($mask_image, $keep_colour);
        $mask_image = $apply_mask->apply($working_image);
        $this->utilities_service->debug_log("Mask applied successfully");

        // Rescale working image back to normal size
        $image = $mask_image->copy()->resize($original_size);
        $this->utilities_service->debug_log("Resized back to original size: " . $original_size->getWidth() . "x" . $original_size->getHeight());

        // Clean up
        unset($mask_image);

        return $image;        
        
    }

    /**
     * Generate points for rotated regular polygon
     *
     * @param int $x
     * @param int $y
     * @param int $radius
     * @param int $vertices (default 3)
     * @param int $rotation (default 0)
     * @return Point[]
     */
    public function draw_rotated_polygon(int $x, int $y, int $radius, int $vertices = 3, int $rotation = 0): array
    {
        // $x, $y -> Position in the image
        // $radius -> Radius of circle enclosing the polygon
        // $spikes -> Number of vertices
        // $rotation -> Rotation of the polygon

        // Ensure the number of vertices is greater than 2
        if ($vertices < 3) {
            throw new \InvalidArgumentException("A polygon must have at least 3 vertices.");
        }

        // Calculate the angle between vertices
        $angle = 360 / $vertices;

        // Initialize the coordinates array
        $coordinates = [];

        // Calculate the coordinates of each vertex
        for ($i = 0; $i < $vertices; $i++) {
            $vertexX = (int) round($x + ($radius * cos(deg2rad(270 - $angle * $i + $rotation))), 0);
            $vertexY = (int) round($y + ($radius * sin(deg2rad(270 - $angle * $i + $rotation))), 0);
            $coordinates[] = new Point($vertexX, $vertexY);
        }
        // Return the coordinates
        return $coordinates;
    }

    /**
     * Generate points for rotated star
     * With inspiration from examples on
     * http://www.php.net/manual/en/function.imagefilledpolygon.php
     *
     * @param int $x
     * @param int $y
     * @param int $radius
     * @param int $spikes
     * @param float $split
     * @param int $rotation
     * @return Point[]
     */
    public function draw_rotated_star(int $x, int $y, int $radius, int $spikes = 5, float $split = 0.5, int $rotation = 0): array
    {

        // $x, $y -> Position in the image
        // $radius -> Radius of the star
        // $spikes -> Number of spikes
        // $split -> Factor to determine the inner shape of the star
        // $rotation -> Rotation of the star

    // Ensure the number of spikes is greater than 2
    if ($spikes < 3) {
        throw new \InvalidArgumentException("A star must have at least 3 spikes.");
    }

    // Calculate the angle between spikes
    $angle = 360 / $spikes;

    // Initialize the coordinates array
    $coordinates = [];

    // Calculate the coordinates of the outer shape of the star
    $outer_shape = [];
    for ($i = 0; $i < $spikes; $i++) {
        $vertexX = (int) round($x + ($radius * cos(deg2rad(270 - $angle * $i + $rotation))), 0);
        $vertexY = (int) round($y + ($radius * sin(deg2rad(270 - $angle * $i + $rotation))), 0);
        $outer_shape[] = ['x' => $vertexX, 'y' => $vertexY];
    }

    // Calculate the coordinates of the inner shape of the star
    $inner_shape = [];
    for ($i = 0; $i < $spikes; $i++) {
        $vertexX = (int) round($x + ($split * $radius * cos(deg2rad(270 - 180 - $angle * $i + $rotation))), 0);
        $vertexY = (int) round($y + ($split * $radius * sin(deg2rad(270 - 180 - $angle * $i + $rotation))), 0);
        $inner_shape[] = ['x' => $vertexX, 'y' => $vertexY];
    }

    // Bring the coordinates in the right order
    foreach ($inner_shape as $key => $value) {
        if ($key == (floor($spikes / 2) + 1)) {
            break;
        }
        $inner_shape[] = $value;
        unset($inner_shape[$key]);
    }

    // Reset the keys
    $inner_shape = array_values($inner_shape);

    // "Merge" outer and inner shape
    foreach ($outer_shape as $key => $value) {
        $coordinates[] = new Point($outer_shape[$key]['x'], $outer_shape[$key]['y']);
        $coordinates[] = new Point($inner_shape[$key]['x'], $inner_shape[$key]['y']);
    }

    // Return the coordinates
    return $coordinates;
    }
}
