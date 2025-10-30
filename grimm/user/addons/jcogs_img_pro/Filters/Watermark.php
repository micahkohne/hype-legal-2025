<?php

/**
 * JCOGS Image Pro - Watermark Filter
 * ===================================
 * Advanced watermark filter with positioning and repeat functionality
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Filter Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Filters;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Gd\Imagine;
use Imagine\Image\Palette;
use Imagine\Image\Point;
use Imagine\Image\Box;

use JCOGSDesign\JCOGSImagePro\Service\ColourManagementService;
use JCOGSDesign\JCOGSImagePro\Service\ImageProcessingService;
use JCOGSDesign\JCOGSImagePro\Service\ValidationService;
use JCOGSDesign\JCOGSImagePro\Service\Utilities;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

/**
 * Watermark Filter
 * 
 * Adds watermarks to images with advanced positioning, scaling, and repeat functionality.
 * Supports single watermarks with precise positioning or tiled watermarks with custom
 * spacing. Includes opacity control, rotation, and minimum size thresholds.
 * 
 * Processing Steps:
 * 1) Unpack and validate watermark parameters
 * 2) Normalize parameters and load watermark image
 * 3) Build watermark(s) on transparent canvas
 * 4) Merge canvas onto original image
 */
class Watermark extends ImageAbstractFilter
{
    /**
     * @var ImageProcessingService Specialized image processing service
     */
    protected $image_processing_service;

    /**
     * @var array Watermark parameters array
     */
    private $wpa;

    /**
     * Constructs Watermark filter
     *
     * @param string $watermark_spec Pipe-separated watermark specification string
     */
    public function __construct(string $watermark_spec = '')
    {
        // Initialize shared services via parent constructor
        parent::__construct();
        
        // Store watermark specification - will parse in apply method
        $this->wpa = ['watermark' => $watermark_spec];
        
        // Initialize specialized service using ServiceCache
        $this->image_processing_service = ee('jcogs_img_pro:ImageProcessingService');
    }

    /**
     * Apply watermark filter to image
     * 
     * @param ImageInterface $image The image to add watermark to
     * @return ImageInterface The watermarked image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Use constructor params - extract and parse the watermark specification
        $watermark_spec = $this->wpa['watermark'] ?? '';
        
        if (empty($watermark_spec)) {
            $this->utilities_service->debug_message('No watermark parameters provided');
            return $image;
        }
        
        // Parse pipe-separated watermark parameters
        $watermark_params_array = explode('|', $watermark_spec);

        // 1) Unpack parameters
        // ====================

        // Get the size of the image we are processing
        $image_size = $image->getSize();
        $image_width = $image_size->getWidth();
        $image_height = $image_size->getHeight();
        
        // CE Image reference for params - https://docs.causingeffect.com/expressionengine/ce-image/user-guide/parameters.html#watermark
        // watermark="watermark_src|minimum_dimensions|opacity|position|offset|blend"
            // Parameter list:
            // param # | name | value type
            // 0 | watermark_src | string
            // 1 | ​minimum_dimensions | x,y
            // 2​ | opacity | 0 < int < 100
            // 3 | position | left|center|right, top|center|bottom | repeat (string),0 < int < 100
            // 4​ | offset | x,y
            // 5​ | blend | string

        // Create an informative array for the parameters
        $watermark_params['src'] = isset($watermark_params_array[0]) ? $watermark_params_array[0] : null;
        $watermark_params['minimum_dimensions'] = isset($watermark_params_array[1]) ? $watermark_params_array[1] : null;
        $watermark_params['opacity'] = isset($watermark_params_array[2]) ? $watermark_params_array[2] : null;
        $watermark_params['position'] = isset($watermark_params_array[3]) ? $watermark_params_array[3] : null;
        $watermark_params['offset'] = isset($watermark_params_array[4]) ? $watermark_params_array[4] : null;
        $watermark_params['rotation'] = isset($watermark_params_array[5]) ? $watermark_params_array[5] : null;

        // Unpack the minimum dimensions setting
        $watermark_minimum_dimensions = isset($watermark_params['minimum_dimensions']) ? explode(',',$watermark_params['minimum_dimensions']) : null;
        $watermark_minimum_width = isset($watermark_minimum_dimensions[0]) ? $this->validation_service->validate_dimension($watermark_minimum_dimensions[0],(int) $image_width) : 0;
        $watermark_minimum_height = isset($watermark_minimum_dimensions[1]) ? $this->validation_service->validate_dimension($watermark_minimum_dimensions[1],(int) $image_height) : 0;

        // Don't do anything if image is smaller than minimum size threshold
        if($watermark_minimum_width > $image_width || $watermark_minimum_height > $image_height) {
            $this->utilities_service->debug_message(lang('jcogs_img_too_small_to_add_watermark'));
            return $image;
        }

        // Still here... so 2) Normalise parameters
        // ========================================

        // Validate and load watermark image (use same approach as LoadSourceStage)
        $watermark_src = $watermark_params['src'];
        if (!$watermark_src) {
            $this->utilities_service->debug_message('No watermark source specified');
            return $image;
        }

        // Get image data using Pro ImageUtilities service
        $image_utilities = $this->image_utilities_service;
        $watermark_data = $image_utilities->get_a_local_copy_of_image($watermark_src);
        
        if (!$watermark_data || !isset($watermark_data['image_source'])) {
            $this->utilities_service->debug_message(lang('jcogs_img_watermark_source_not_valid'), $watermark_src);
            return $image;
        }

        // Load watermark image from the retrieved data
        try {
            $watermark_image = (new Imagine())->load($watermark_data['image_source']);
            $watermark_size = $watermark_image->getSize();
            $watermark_width = $watermark_size->getWidth();
            $watermark_height = $watermark_size->getHeight();
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message(lang('jcogs_img_watermark_source_not_valid'), $watermark_src);
            return $image;
        }
        
        // Get an adjusted value for opacity (an integer in range 0-100).
        $watermark_opacity = isset($watermark_params['opacity']) ? max(min(round(abs(intval($watermark_params['opacity'])),0),100),0) : 100;
        
        // Resolve position
        $watermark_position = isset($watermark_params['position']) ? explode(',',$watermark_params['position']) : null;

        // Did we get anything? If not use default values.
        if (!$watermark_position) {
            $watermark_position[0] = 'center';
            $watermark_position[1] = 'center';
        }

        if((!in_array($watermark_position[0], ['left','center','right']) || !in_array($watermark_position[1], ['top','center','bottom'])) && $watermark_position[0] != 'repeat') {
            // We got odd things in position parameter so revert to default values
            $this->utilities_service->debug_message(lang('jcogs_img_watermark_position_error'), [$watermark_params['position']]);
            $watermark_position[0] = 'center';
            $watermark_position[1] = 'center';
        }

        // See if we are expected to rotate the watermark image
        $watermark_rotation = isset($watermark_params['rotation']) && is_string($watermark_params['rotation']) && $watermark_params['rotation'] != 'multiply' ? intval($watermark_params['rotation']) : null;

        // If watermark is to be rotated, do it now... as it affects image size
        if($watermark_rotation) {
            // Do the rotation
            $watermark_image = $watermark_image->rotate($watermark_rotation, (new Palette\RGB())->color([0,0,0],0));
            // Reset image dimensions to reflect rotation
            $watermark_size = $watermark_image->getSize();
            $watermark_width = $watermark_size->getWidth();
            $watermark_height = $watermark_size->getHeight();
        }
        

        // Check to see if we are doing a repeat or a position
        if ($watermark_position && $watermark_position[0] != 'repeat') {
            // Doing a single image
            // Get gross position for watermark image
            switch (true) {
                case $watermark_position[0] == 'left':
                    $watermark_position[0] = 0;
                    break;

                case $watermark_position[0] == 'center':
                    $watermark_position[0] = (int) round(($image_width - $watermark_width)/2,0);
                    break;

                case $watermark_position[0] == 'right':
                    $watermark_position[0] = (int) $image_width - $watermark_width;
                    break;
            }
            switch (true) {
                case $watermark_position[1] == 'top':
                    $watermark_position[1] = 0;
                    break;

                case $watermark_position[1] == 'center':
                    $watermark_position[1] = (int) round(($image_height - $watermark_height)/2,0);
                    break;

                case $watermark_position[1] == 'bottom':
                    $watermark_position[1] = (int) ($image_height - $watermark_height);
                    break;
            } 
            // Offset the image position by 1x watermark width and 1x watermark height to allow
            // for image margin set
            $watermark_position[0] += $watermark_width;
            $watermark_position[1] += $watermark_height;

        } else {
            // we are doing a repeat
            // fudge to replicate CE Image handling of first offset value(as % with no units)
            if(!isset($watermark_position[2]) && isset($watermark_position[1]) && !str_contains($watermark_position[1],'%') && !str_contains($watermark_position[1],'px')) {
                $watermark_position[1] = trim($watermark_position[1]).'%';
            }
            
            // unpack offset parameters (if any)
            $watermark_repeat_offset_x = $this->validation_service->validate_dimension(isset($watermark_position[1]) ? $watermark_position[1] : '50%', (int) $watermark_width);
            $watermark_repeat_offset_y = $this->validation_service->validate_dimension(isset($watermark_position[2]) ? $watermark_position[2] : 0, (int) $watermark_height);

            // normalise repeats
            $watermark_repeat_offset_x = max(min($watermark_repeat_offset_x, $watermark_width),0);
            $watermark_repeat_offset_y = max(min($watermark_repeat_offset_y, $watermark_height),0);

            $this->utilities_service->debug_message(lang('jcogs_img_watermark_repeat_requested'), [$watermark_repeat_offset_x, $watermark_repeat_offset_y]);

            // work out max repeats
            $watermark_repeats_x = (int) ceil(($image_width + (2 * $watermark_width)) / ($watermark_width + $watermark_repeat_offset_x)) + 2;
            $watermark_repeats_y = (int) ceil(($image_height + (2 * $watermark_height)) / ($watermark_height + $watermark_repeat_offset_y));            
        }
        
        // Work out the overall image off-set (used for insert)
        $watermark_offset =  isset($watermark_params['offset']) ? explode(',',$watermark_params['offset']) : null;
        $watermark_offset_horizontal = isset($watermark_offset[0]) ? $this->validation_service->validate_dimension($watermark_offset[0],(int) $image_width) : 0;
        $watermark_offset_vertical = isset($watermark_offset[1]) ? $this->validation_service->validate_dimension($watermark_offset[1],(int) $image_width) : 0;

        // 3) Build watermark(s) on blank canvas
        // =====================================
                
        // ii) Build a temporary image to hold watermark(s)
        // Make canvas 2x watermark size bigger
        $canvas = (new Imagine())->create(new Box($image_width + (2 * $watermark_width), $image_height + (2 * $watermark_height)), (new Palette\RGB())->color([0,0,0],0));
        
        // iii) Are we doing a repeat or position?
        if(isset($watermark_repeat_offset_x)) {
            // Doing a repeat

            // Iterate to add watermark
            for ($row = 0; $row < $watermark_repeats_y; $row++)
            {
                for ($col = 0; $col < $watermark_repeats_x; $col++)
                {
                    // Work out where to put the image
                    // offset x = image width * col - (imaged width - x offset) * row + x offset
                    $offset_x = $watermark_width + $col * ($watermark_width +  $watermark_offset_horizontal) - $row * ($watermark_width + $watermark_repeat_offset_x + $watermark_offset_horizontal) +  $watermark_offset_horizontal;
                    // offset y = image height + y offset * row + y offset
                    $offset_y = $watermark_height + $row * ($watermark_height + $watermark_repeat_offset_y + $watermark_offset_vertical) + $watermark_offset_vertical + $watermark_repeat_offset_y;
                    // only paste image if it overlaps the image
                    if(($offset_x > 0 && $offset_x <= $image_width + $watermark_width) && ($offset_y > 0 && $offset_y <= $image_height + $watermark_height)) {
                        $canvas->paste($watermark_image, new Point($offset_x, $offset_y));
                    }
                }
            }
        } else {
            // Overlay the image onto main image
            // Work out the offset (if any)
            $offset_x = $watermark_position[0] + $watermark_offset_horizontal;
            $offset_y = $watermark_position[1] + $watermark_offset_vertical;

            // only paste image if it overlaps the image
            if(($offset_x > -$watermark_width && $offset_x <= $watermark_width + $image_width) && ($offset_y > -$watermark_height && $offset_y <= $watermark_height + $image_height)) {
                $canvas->paste($watermark_image, new Point($offset_x, $offset_y));
            }
        }
        
        // 4) Merge blank canvas onto original image
        // =========================================

        // First crop Canvas back to image size
        $canvas = $canvas->crop(new Point ($watermark_width, $watermark_height), new Box($image_width, $image_height));

        $image->paste($canvas, new Point(0,0), $watermark_opacity);

        // 5) End
        // ======
        unset($watermark_image);
        unset($canvas);

        $this->utilities_service->debug_message(lang('jcogs_img_adding_watermark_end'));

        return $image;
    }
}
