<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Watermark filter.
 * Add a watermark or series of watermarks to an image
 * 1) Unpack watermark parameters
 * 2) Normalise parameters
 * 3) Build watermark(s) on blank canvas
 * 4) Merge blank canvas onto original image
 * 5) End
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

use JCOGSDesign\Jcogs_img\Library\JcogsImage;
use Imagine\Filter;
use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Gd\Imagine;
use Imagine\Image\Palette;
use Imagine\Image\Point;
use Imagine\Image\Box;

class Watermark implements FilterInterface
{
    /**
     * @var array
     */
    private $wpa;

    /**
     * Constructs Watermark filter.
     *
     * @param array $watermark_parameter_array
     */
    public function __construct(array $watermark_parameter_array)
    {
        $this->wpa = $watermark_parameter_array;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {

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
        $watermark_params['src'] = isset($this->wpa[0]) ? $this->wpa[0] : null;
        $watermark_params['minimum_dimensions'] = isset($this->wpa[1]) ? $this->wpa[1] : null;
        $watermark_params['opacity'] = isset($this->wpa[2]) ? $this->wpa[2] : null;
        $watermark_params['position'] = isset($this->wpa[3]) ? $this->wpa[3] : null;
        $watermark_params['offset'] = isset($this->wpa[4]) ? $this->wpa[4] : null;
        $watermark_params['rotation'] = isset($this->wpa[5]) ? $this->wpa[5] : null;

        unset($this->wpa);

        // Unpack the minimum dimensions setting
        $watermark_minimum_dimensions = isset($watermark_params['minimum_dimensions']) ? explode(',',$watermark_params['minimum_dimensions']) : null;
        $watermark_minimum_width = isset($watermark_minimum_dimensions[0]) ? ee('jcogs_img:ImageUtilities')->validate_dimension($watermark_minimum_dimensions[0],(int) $image_width) : 0;
        $watermark_minimum_height = isset($watermark_minimum_dimensions[1]) ? ee('jcogs_img:ImageUtilities')->validate_dimension($watermark_minimum_dimensions[1],(int) $image_height) : 0;

        // Don't do anything if image is smaller than minimum size threshold
        if($watermark_minimum_width > $image_width || $watermark_minimum_height > $image_height) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_too_small_to_add_watermark'));
            return $image;
        }

        // Still here... so 2) Normalise parameters
        // ========================================

        // Is the watermark image one we can work with?
        $watermark = new JcogsImage();
        $watermark->params->src = $watermark_params['src'];
        $watermark->params->cache = 0;
        $watermark->params->crop = 'n';
        $watermark->params->save_type = 'webp';
        $watermark->params->cache_dir = ee('jcogs_img:Settings')::$settings['img_cp_default_cache_directory'];

        // Use Image's initialise to validate src and obtain local copy of image
        $watermark->initialise(false);
        if(!$watermark->flags->valid_image) {
            // Something went wrong so bale...
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_watermark_source_not_valid'), $watermark_params['src']);                    
            return $image;
        }
        
        // Get an adjusted value for opacity (an integer in range 0-100).
        $watermark->opacity = isset($watermark_params['opacity']) ? max(min(round(abs(intval($watermark_params['opacity'])),0),100),0) : 100;
        
        // Resolve position
        $watermark->position = isset($watermark_params['position']) ? explode(',',$watermark_params['position']) : null;

        // Did we get anything? If not use default values.
        if (!$watermark->position) {
            $watermark->position[0] = 'center';
            $watermark->position[1] = 'center';
        }

        if((!in_array($watermark->position[0], ['left','center','right']) || !in_array($watermark->position[1], ['top','center','bottom'])) && $watermark->position[0] != 'repeat') {
            // We got odd things in position parameter so revert to default values
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_watermark_position_error'), [$watermark_params['position']]);
            $watermark->position[0] = 'center';
            $watermark->position[1] = 'center';
        }

        // See if we are expected to rotate the watermark image
        $watermark->rotation = isset($watermark_params['rotation']) && is_string($watermark_params['rotation']) && $watermark_params['rotation'] != 'multiply' ? intval($watermark_params['rotation']) : null;

        // If watermark is to be rotated, do it now... as it affects image size
        if($watermark->rotation) {
            // Do the rotation
            (new Filter\Transformation(new Imagine()))->applyFilter($watermark->source_image, new Filter\Basic\Rotate($watermark->rotation,  (new Palette\RGB())->color([0,0,0],0)));
            // Reset image dimensions to reflect rotation
            $watermark->orig_size = $watermark->source_image->getSize();
            $watermark->orig_width = $watermark->orig_size->getWidth();
            $watermark->orig_height = $watermark->orig_size->getHeight();
        }
        

        // Check to see if we are doing a repeat or a position
        if ($watermark->position && $watermark->position[0] != 'repeat') {
            // Doing a single image
            // Get gross position for watermark image
            switch (true) {
                case $watermark->position[0] == 'left':
                    $watermark->position[0] = 0;
                    break;

                case $watermark->position[0] == 'center':
                    $watermark->position[0] = (int) round(($image_width - $watermark->orig_width)/2,0);
                    break;

                case $watermark->position[0] == 'right':
                    $watermark->position[0] = (int) $image_width - $watermark->orig_width;
                    break;
            }
            switch (true) {
                case $watermark->position[1] == 'top':
                    $watermark->position[1] = 0;
                    break;

                case $watermark->position[1] == 'center':
                    $watermark->position[1] = (int) round(($image_height - $watermark->orig_height)/2,0);
                    break;

                case $watermark->position[1] == 'bottom':
                    $watermark->position[1] = (int) ($image_height - $watermark->orig_height);
                    break;
            } 
            // Offset the image position by 1x watermark width and 1x watermark height to allow
            // for image margin set
            $watermark->position[0] += $watermark->orig_width;
            $watermark->position[1] += $watermark->orig_height;

        } else {
            // we are doing a repeat
            // fudge to replicate CE Image handling of first offset value(as % with no units)
            if(!isset($watermark->position[2]) && isset($watermark->position[1]) && !str_contains($watermark->position[1],'%') && !str_contains($watermark->position[1],'px')) {
                $watermark->position[1] = trim($watermark->position[1]).'%';
            }
            
            // unpack offset parameters (if any)
            $watermark->repeat_offset_x = ee('jcogs_img:ImageUtilities')->validate_dimension(isset($watermark->position[1]) ? $watermark->position[1] : '50%', (int) $watermark->orig_width);
            $watermark->repeat_offset_y = ee('jcogs_img:ImageUtilities')->validate_dimension(isset($watermark->position[2]) ? $watermark->position[2] : 0, (int) $watermark->orig_height);

            // normalise repeats
            $watermark->repeat_offset_x = max(min($watermark->repeat_offset_x, $watermark->orig_width),0);
            $watermark->repeat_offset_y = max(min($watermark->repeat_offset_y, $watermark->orig_height),0);

            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_watermark_repeat_requested'), ['horizontal offset' => $watermark->repeat_offset_x, 'vertical_offset' => $watermark->repeat_offset_y]);                    

            // work out max repeats
            $watermark->repeats_x = (int) ceil(($image_width + (2 * $watermark->orig_width)) / ($watermark->orig_width + $watermark->repeat_offset_x)) + 2;
            $watermark->repeats_y = (int) ceil(($image_height + (2 * $watermark->orig_height)) / ($watermark->orig_height + $watermark->repeat_offset_y));            
        }
        
        // Work out the overall image off-set (used for insert)
        $watermark->offset =  isset($watermark_params['offset']) ? explode(',',$watermark_params['offset']) : null;
        $watermark->offset_horizontal = isset($watermark->offset[0]) ? ee('jcogs_img:ImageUtilities')->validate_dimension($watermark->offset[0],(int) $image_width) : 0;
        $watermark->offset_vertical = isset($watermark->offset[1]) ? ee('jcogs_img:ImageUtilities')->validate_dimension($watermark->offset[1],(int) $image_width) : 0;
        
        // 3) Build watermark(s) on blank canvas
        // =====================================
                
        // ii) Build a temporary image to hold watermark(s)
        // Make canvas 2x watermark size bigger
        $canvas = (new Imagine())->create(new Box($image_width + (2 * $watermark->orig_width), $image_height + (2 * $watermark->orig_height)), (new Palette\RGB())->color([0,0,0],0));
        
        // iii) Are we doing a repeat or position?
        if(isset($watermark->repeat_offset_x)) {
            // Doing a repeat

            // Iterate to add watermark
            for ($row = 0; $row < $watermark->repeats_y; $row++)
            {
                for ($col = 0; $col < $watermark->repeats_x; $col++)
                {
                    // Work out where to put the image
                    // offset x = image width * col - (imaged width - x offset) * row + x offset
                    $offset_x = $watermark->orig_width + $col * ($watermark->orig_width +  $watermark->offset_horizontal) - $row * ($watermark->orig_width + $watermark->repeat_offset_x + $watermark->offset_horizontal) +  $watermark->offset_horizontal;
                    // offset y = image height + y offset * row + y offset
                    $offset_y = $watermark->orig_height + $row * ($watermark->orig_height + $watermark->repeat_offset_y + $watermark->offset_vertical) + $watermark->offset_vertical + $watermark->repeat_offset_y;
                    // only paste image if it overlaps the image
                    if(($offset_x > 0 && $offset_x <= $image_width + $watermark->orig_width) && ($offset_y > 0 && $offset_y <= $image_height + $watermark->orig_height)) {
                        $canvas->paste($watermark->source_image, new Point($offset_x, $offset_y));
                    }
                }
            }
        } else {
            // Overlay the image onto main image
            // Work out the offset (if any)
            $offset_x = $watermark->position[0] + $watermark->offset_horizontal;
            $offset_y = $watermark->position[1] + $watermark->offset_vertical;

            // only paste image if it overlaps the image
            if(($offset_x > -$watermark->orig_width && $offset_x <= $watermark->orig_width + $image_width) && ($offset_y > -$watermark->orig_height && $offset_y <= $watermark->orig_height + $image_height)) {
                $canvas->paste($watermark->source_image, new Point($offset_x, $offset_y));
            }
        }
        
        // 4) Merge blank canvas onto original image
        // =========================================

        // First crop Canvas back to image size
        (new Filter\Transformation(new Imagine()))->applyFilter($canvas, new Filter\Basic\Crop(new Point ($watermark->orig_width, $watermark->orig_height), new Box($image_width, $image_height)));

        $image->paste($canvas, new Point(0,0), $watermark_params['opacity']);

        // 5) End
        // ======
        unset($watermark);
        unset($canvas);

        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_adding_watermark_end'));                    

        return $image;
    }
}