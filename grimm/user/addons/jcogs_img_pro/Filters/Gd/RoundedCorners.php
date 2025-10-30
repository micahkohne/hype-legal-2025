<?php

/**
 * JCOGS Image Pro Filter - GD Rounded Corners
 * ============================================
 * 
 * GD-specific implementation for rounded corners transformation.
 * Follows legacy architecture pattern and algorithms for performance
 * and maintainability.
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 3 Legacy Independence
 */

namespace JCOGSDesign\JCOGSImagePro\Filters\Gd;

class RoundedCorners
{
    /**
     * @var array Corner specifications
     */
    private $corner_specs;
    
    /**
     * @var array Infill mask specifications  
     */
    private $infill_masks;
    
    /**
     * @var array Filter parameters
     */
    private $params;

    /**
     * Constructs GD Rounded Corners filter.
     *
     * @param array $corner_specs Corner specifications with radius and positions
     * @param array $infill_masks Infill rectangle specifications  
     * @param array $params Additional parameters (bg_color, save_type, etc.)
     */
    public function __construct(array $corner_specs, array $infill_masks, array $params = [])
    {
        $this->corner_specs = $corner_specs;
        $this->infill_masks = $infill_masks;
        $this->params = $params;
    }

    /**
     * Apply rounded corners to image using GD library
     * Following legacy algorithm pattern for performance and compatibility
     *
     * @param mixed $image Image object
     * @return mixed Processed image
     */
    public function apply($image)
    {
        try {
            // Get image dimensions
            $image_size = $image->getSize();
            
            // Create mask using legacy approach: magic pink background with cyan keep areas
            $mask_image = $this->create_rounded_corners_mask($image_size);
            
            // Create RGB color for cyan keep areas (legacy compatibility)
            $keep_color = new \Imagine\Image\Palette\Color\RGB(new \Imagine\Image\Palette\RGB(), [0, 255, 255], 100);
            
            // Apply mask to original image using Pro ApplyMask filter
            $apply_mask_filter = new \JCOGSDesign\JCOGSImagePro\Filters\ApplyMask($mask_image, $keep_color);
            return $apply_mask_filter->apply($image);
            
        } catch (\Exception $e) {
            error_log("JCOGS Image Pro: GD Rounded corners error - " . $e->getMessage());
            return $image;
        }
    }
    
    /**
     * Create rounded corners mask following legacy algorithm
     * Uses magic pink background with cyan keep areas, matching legacy approach
     * 
     * @param mixed $image_size Image size object
     * @return mixed Mask image
     */
    private function create_rounded_corners_mask($image_size)
    {
        $image_width = $image_size->getWidth();
        $image_height = $image_size->getHeight();
        
        // Create GD canvas for mask (following legacy color scheme)
        $mask_canvas = imagecreatetruecolor($image_width, $image_height);
        imagealphablending($mask_canvas, false); // Turn off alpha blending
        
        // Define colors following legacy pattern
        $magic_pink = imagecolorallocatealpha($mask_canvas, 255, 0, 255, 127); // Magic pink background
        $keep_color = imagecolorallocatealpha($mask_canvas, 0, 255, 255, 127);   // Cyan keep areas
        
        // Fill canvas with magic pink (remove areas)
        imagefill($mask_canvas, 0, 0, $magic_pink);
        
        // Add corner circles to mask (keep these areas)
        foreach ($this->corner_specs as $corner_id => $corner) {
            if ($corner['radius'] > 0) {
                // Calculate center positions following legacy algorithm
                switch ($corner_id) {
                    case 'tl':
                        $center_x = $corner['x'] + $corner['radius'];
                        $center_y = $corner['y'] + $corner['radius'];
                        break;
                    case 'tr':
                        $center_x = $corner['x'] - $corner['radius'];
                        $center_y = $corner['y'] + $corner['radius'];
                        break;
                    case 'bl':
                        $center_x = $corner['x'] + $corner['radius'];
                        $center_y = $corner['y'] - $corner['radius'];
                        break;
                    case 'br':
                        $center_x = $corner['x'] - $corner['radius'];
                        $center_y = $corner['y'] - $corner['radius'];
                        break;
                    default:
                        continue 2;
                }
                
                // Draw filled circle for corner
                imagefilledellipse(
                    $mask_canvas,
                    $center_x, $center_y,
                    $corner['radius'] * 2, $corner['radius'] * 2,
                    $keep_color
                );
            }
        }
        
        // Add infill rectangles to mask (keep these areas)
        foreach ($this->infill_masks as $mask) {
            imagefilledrectangle(
                $mask_canvas, 
                $mask['x'], $mask['y'],
                $mask['x'] + $mask['width'], $mask['y'] + $mask['height'],
                $keep_color
            );
        }
        
        // Convert GD resource back to Imagine image
        ob_start();
        imagepng($mask_canvas);
        $mask_data = ob_get_clean();
        imagedestroy($mask_canvas);
        
        $imagine = new \Imagine\Gd\Imagine();
        return $imagine->load($mask_data);
    }
}
