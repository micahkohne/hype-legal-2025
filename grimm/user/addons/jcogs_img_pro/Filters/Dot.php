<?php

/**
 * JCOGS Image Pro - Dot Filter
 * ============================
 * Halftone dot pattern effect using Floyd-Steinberg dithering
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

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\Color\ColorInterface;

/**
 * Dot Filter
 * 
 * Creates halftone dot pattern effect using Floyd-Steinberg dithering principles.
 * Matches legacy JCOGS Image dot filter behavior exactly.
 */
class Dot implements FilterInterface
{
    private string $library = 'gd';
    private int $dot_size;
    private string $dot_color;
    private string $dot_shape;
    
    /**
     * Constructs Dot filter.
     * 
     * @param int $dot_size Dot block size (default: 2)
     * @param string $dot_color Dot color (default: '')
     * @param string $dot_shape Dot shape 'circle' or 'square' (default: 'circle')
     */
    public function __construct(int $dot_size = 2, string $dot_color = '', string $dot_shape = 'circle')
    {
        $this->library = 'gd';
        // Ensure dot_size is never zero to prevent division by zero
        $this->dot_size = max(1, $dot_size);
        $this->dot_color = $dot_color;
        $this->dot_shape = $dot_shape;
    }
    
    /**
     * Apply dot filter to image
     *
     * Uses Legacy approach: native Imagine operations for halftone effect.
     * 120% faster than complex GD resource conversion approach.
     *
     * @param ImageInterface $image The image data
     * @return ImageInterface The processed image data
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Use Legacy approach: native Imagine halftone processing
        $image_size = $image->getSize();
        $block = max(1, $this->dot_size); // Ensure block is never zero

        // First shrink image by block factor to get average colors (Legacy approach)
        $reduced_width = max(1, round($image_size->getWidth()/$block, 0));
        $reduced_image = $image->resize($image_size->widen($reduced_width));

        // Create working image using native Imagine (Legacy approach)
        try {
            $imagine = new \Imagine\Gd\Imagine();
            $working_image = $imagine->create($image_size);
        } catch(\Imagine\Exception\RuntimeException $e) {
            return $image; // Fallback to original
        }

        // Scan reduced image and draw dots (Legacy approach)
        $size = $reduced_image->getSize();
        $w = $size->getWidth();
        $h = $size->getHeight();
        
        // Ensure we have valid dimensions
        if ($w <= 0 || $h <= 0) {
            return $image; // Fallback to original
        }
        
        $pixel_count = $w * $h;
        
        for ($i = 0; $i < $pixel_count; $i++) {
            $x = $i % $w;
            $y = (int) ($i / $w);
            $this->draw_dot_callback($reduced_image, $working_image, new \Imagine\Image\Point($x, $y), $block);
        }

        $result = $working_image->copy();
        unset($reduced_image);
        unset($working_image);
        return $result;
    }
    
    /**
     * Process and validate dot parameters to match legacy behavior
     *
     * @param mixed $block Block size parameter
     * @param mixed $color Color parameter (string or empty)
     * @param string $type Shape type ('circle' or 'square')
     * @param mixed $multiplier Intensity multiplier
     * @return array Processed parameters
     */
    
    /**
     * Validate color string and convert to RGB (matches legacy behavior)
     *
     * @param string $color_string Color string (hex format like #ff0000)
     * @return ColorInterface|null RGB color object or null if invalid
     */
    private function validate_color_string(string $color_string): ?ColorInterface
    {
        // Remove # if present
        $color_string = ltrim($color_string, '#');
        
        // Must be 6 hex characters
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $color_string)) {
            return null;
        }
        
        // Convert to RGB values
        $r = hexdec(substr($color_string, 0, 2));
        $g = hexdec(substr($color_string, 2, 2));
        $b = hexdec(substr($color_string, 4, 2));
        
        // Create RGB color object (requires palette)
        $palette = new \Imagine\Image\Palette\RGB();
        return $palette->color([$r, $g, $b]);
    }
    
    /**
     * Draw dot callback (Legacy approach)
     *
     * @param ImageInterface $reduced_image The reduced image being processed
     * @param ImageInterface $working_image The working image being processed
     * @param \Imagine\Image\Point $point The point at which the callback is applied
     * @param int $block The block size for the callback
     * @return void
     */
    private function draw_dot_callback(ImageInterface $reduced_image, ImageInterface $working_image, \Imagine\Image\Point $point, int $block): void
    {
        // Legacy constants
        $fudge_factor = 1.2;
        $circle_factor = 1.2;
        $multiplier = 1.0; // Default multiplier
        
        // Ensure block size is valid
        $block = max(1, $block);
        
        // Get colour of our pixel
        $pixel_color = $reduced_image->getColorAt($point);
        
        // Get average intensity of pixel
        $intensity = (255 - ($pixel_color->grayscale())->getValue(ColorInterface::COLOR_RED)) / 255 * $multiplier * $fudge_factor;
        
        // Work out what colour to use for the dot
        // If dot_color is specified, use it; otherwise use pixel color (Legacy behavior)
        $colour_for_point = null;
        if (!empty($this->dot_color)) {
            $colour_for_point = $this->validate_color_string($this->dot_color);
        }
        $colour_for_point = $colour_for_point ?: $pixel_color;
        
        // Work out where to write the dot
        $new_x = min(max($point->getX() * $block, 0), ($working_image->getSize())->getWidth());
        $new_y = min(max($point->getY() * $block, 0), ($working_image->getSize())->getHeight());
        
        // Draw the dot if it is not zero sized
        $nudge = max(1, round($block / 2, 0));
        $radius = round($nudge * $intensity, 0);
        
        if ($radius && strtolower(substr($this->dot_shape, 0, 1)) == 's') {
            // Draw square dot
            $working_image->draw()->rectangle(
                new \Imagine\Image\Point($new_x + $radius + $nudge, $new_y + $radius + $nudge), 
                new \Imagine\Image\Point($new_x + $radius * 2 + $nudge, $new_y + $radius * 2 + $nudge),
                $colour_for_point, 
                true
            );
        } elseif ($radius) {
            // Draw circle dot
            $radius = round(($block / 2) * $intensity * $circle_factor, 0);
            $working_image->draw()->circle(
                new \Imagine\Image\Point($new_x + $radius + $nudge, $new_y + $radius + $nudge), 
                $radius, 
                $colour_for_point, 
                true
            );
        }
    }
}
