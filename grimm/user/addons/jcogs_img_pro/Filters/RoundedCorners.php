<?php

/**
 * JCOGS Image Pro Filter - Rounded Corners
 * =========================================
 * 
 * Applies rounded corners to an image using configurable radius values
 * for each corner individually. Follows legacy architecture pattern
 * with library-specific implementations.
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

namespace JCOGSDesign\JCOGSImagePro\Filters;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

class RoundedCorners implements FilterInterface
{
    /**
     * @var string Rounded corners specification
     */
    private $corners_spec;

    /**
     * Constructs RoundedCorners filter.
     * 
     * @param string $corners_spec Rounded corners specification
     */
    public function __construct(string $corners_spec = '')
    {
        $this->corners_spec = $corners_spec;
    }

    /**
     * Apply rounded corners transformation to image
     * 
     * @param ImageInterface $image Image object
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        if (empty($this->corners_spec)) {
            return $image;
        }
        
        try {
            // Parse the rounded corners parameter
            $corner_specs = $this->parse_rounded_corners_parameter($this->corners_spec, $image);
            
            if (empty($corner_specs) || !$this->has_any_corners($corner_specs)) {
                return $image;
            }
            
            // Calculate infill masks (rectangles between corners)
            $image_size = $image->getSize();
            $infill_masks = $this->calculate_infill_rectangles($corner_specs, $image_size->getWidth(), $image_size->getHeight());
            
            // Apply rounded corners using appropriate library-specific implementation
            switch (true) {
                case ($image instanceof \Imagine\Gd\Image):
                    $gd_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Gd\RoundedCorners($corner_specs, $infill_masks, ['rounded_corners' => $this->corners_spec]);
                    return $gd_filter->apply($image);
                    
                case ($image instanceof \Imagine\Imagick\Image):
                case ($image instanceof \Imagine\Gmagick\Image):
                default:
                    // For other libraries, return unchanged for now
                    return $image;
            }
            
        } catch (\Exception $e) {
            error_log("JCOGS Image Pro: Rounded corners filter error - " . $e->getMessage());
            return $image;
        }
    }
    
    /**
     * Parse rounded corners parameter string into corner specifications
     * 
     * @param string $rounded_corners_param Parameter string like "all,20" or "tl,10|br,15"
     * @param mixed $image Image object for dimension calculations
     * @return array Corner specifications
     */
    private function parse_rounded_corners_parameter(string $rounded_corners_param, $image): array
    {
        $image_size = $image->getSize();
        $image_width = $image_size->getWidth();
        $image_height = $image_size->getHeight();
        
        // Initialize corner specifications following legacy format
        $corners = [
            'tl' => ['radius' => 0, 'x' => 0, 'y' => 0],
            'tr' => ['radius' => 0, 'x' => $image_width - 1, 'y' => 0],
            'bl' => ['radius' => 0, 'x' => 0, 'y' => $image_height - 1], 
            'br' => ['radius' => 0, 'x' => $image_width - 1, 'y' => $image_height - 1]
        ];
        
        // Split by pipes to get individual corner specifications
        $corner_definitions = explode('|', $rounded_corners_param);
        
        foreach ($corner_definitions as $definition) {
            $parts = explode(',', trim($definition));
            
            if (count($parts) < 2) {
                continue; // Invalid definition
            }
            
            $corner_id = strtolower(trim($parts[0]));
            $radius_str = trim($parts[1]);
            
            // Parse radius value
            $radius = $this->parse_dimension_value($radius_str, $image_width);
            
            if ($radius <= 0) {
                continue; // Invalid or zero radius
            }
            
            // Apply to specific corners or all corners
            if ($corner_id === 'all') {
                // Apply to all corners
                foreach ($corners as $key => $corner) {
                    $corners[$key]['radius'] = $radius;
                }
            } elseif (isset($corners[$corner_id])) {
                // Apply to specific corner
                $corners[$corner_id]['radius'] = $radius;
            }
        }
        
        return $corners;
    }
    
    /**
     * Parse dimension value (supports px, %, or plain integers)
     * 
     * @param string $value Dimension value
     * @param int $base_dimension Base dimension for percentage calculations
     * @return int Parsed dimension in pixels
     */
    private function parse_dimension_value(string $value, int $base_dimension): int
    {
        $value = trim($value);
        
        // Handle percentage values
        if (substr($value, -1) === '%') {
            $percentage = (float)substr($value, 0, -1);
            return (int)round(($percentage / 100) * $base_dimension);
        }
        
        // Handle pixel values (remove 'px' suffix if present)
        if (substr($value, -2) === 'px') {
            $value = substr($value, 0, -2);
        }
        
        // Return integer value
        return (int)$value;
    }
    
    /**
     * Check if any corners have a radius > 0
     * 
     * @param array $corner_specs Corner specifications
     * @return bool True if any corner has a radius
     */
    private function has_any_corners(array $corner_specs): bool
    {
        foreach ($corner_specs as $corner) {
            if (isset($corner['radius']) && $corner['radius'] > 0) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Calculate infill rectangles that connect the corners (following legacy approach)
     * 
     * @param array $corner_specs Corner specifications
     * @param int $image_width Image width
     * @param int $image_height Image height
     * @return array Infill rectangle specifications
     */
    private function calculate_infill_rectangles(array $corner_specs, int $image_width, int $image_height): array
    {
        $tl_radius = $corner_specs['tl']['radius'];
        $tr_radius = $corner_specs['tr']['radius'];
        $bl_radius = $corner_specs['bl']['radius'];
        $br_radius = $corner_specs['br']['radius'];
        
        // Following legacy calculation pattern exactly
        return [
            'top' => [
                'x' => 0 + $tl_radius,
                'y' => 0,
                'width' => $image_width - $tl_radius - $tr_radius,
                'height' => $image_height - max($bl_radius, $br_radius)
            ],
            'bottom' => [
                'x' => 0 + $bl_radius,
                'y' => max($tl_radius, $tr_radius),
                'width' => $image_width - $br_radius - $bl_radius,
                'height' => $image_height - max($tl_radius, $tr_radius)
            ],
            'left' => [
                'x' => 0,
                'y' => 0 + $tl_radius,
                'width' => $image_width - max($tr_radius, $br_radius),
                'height' => $image_height - $tl_radius - $bl_radius
            ],
            'right' => [
                'x' => max($tl_radius, $bl_radius),
                'y' => 0 + $tr_radius,
                'width' => $image_width - max($tl_radius, $bl_radius),
                'height' => $image_height - $tr_radius - $br_radius
            ]
        ];
    }
}
