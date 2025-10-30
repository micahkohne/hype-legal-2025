<?php

/**
 * JCOGS Image Pro - GD Replace Colors Filter Implementation
 * =========================================================
 * GD-specific implementation of color replacement filter
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

namespace JCOGSDesign\JCOGSImagePro\Filters\Gd;

/**
 * GD Replace Colors Filter Implementation
 * 
 * Replaces specific colors in images using GD.
 */
class ReplaceColors
{
    /**
     * Apply color replacement filter using GD
     *
     * @param mixed $image_data The image data (string, GD resource, or Imagine object)
     * @param array $parameters Processed parameters
     * @return string The processed image data as PNG string
     */
    public function apply($image_data, array $parameters): string
    {
        $from_color = $parameters['from_color'];
        $to_color = $parameters['to_color'];
        $tolerance = $parameters['tolerance'] ?? 10;
        
        // Create GD resource from image data
        if (is_string($image_data)) {
            $image = imagecreatefromstring($image_data);
        } elseif ($image_data instanceof \Imagine\Gd\Image) {
            // Try to get GD resource directly from Imagine object
            try {
                $image = $image_data->getGdResource();
            } catch (\Exception $e) {
                // Fallback to string conversion if direct access fails
                $image = imagecreatefromstring($image_data->__toString());
            }
        } else {
            // Assume it's already a GD resource
            $image = $image_data;
        }
        
        if (!$image) {
            throw new \Exception('Failed to create image resource for color replacement');
        }
        
        // Apply color replacement
        $processed_image = $this->replace_colors($image, $from_color, $to_color, $tolerance);
        
        // Convert to PNG string
        ob_start();
        imagepng($processed_image);
        $result = ob_get_clean();
        
        // Clean up
        imagedestroy($processed_image);
        if (is_string($image_data)) {
            imagedestroy($image);
        }
        
        return $result;
    }
    
    /**
     * Replace colors in the image using HSL color space (matching legacy behavior)
     *
     * @param resource $image Source image
     * @param array $from_color Source color RGB
     * @param array $to_color Target color RGB
     * @param int $tolerance Color matching tolerance
     * @return resource Processed image
     */
    private function replace_colors($image, array $from_color, array $to_color, int $tolerance)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Create result image
        $result = imagecreatetruecolor($width, $height);
        
        // Preserve transparency
        imagealphablending($result, false);
        imagesavealpha($result, true);
        $trans_color = imagecolorallocatealpha($result, 254, 254, 254, 127);
        imagefill($result, 0, 0, $trans_color);
        
        // Convert tolerance from 0-100 to 0-180 (matching legacy)
        $hue_absolute_error = $tolerance * 1.8;
        
        // Convert colors to HSL
        $color_to_replace = $this->rgb_to_hsl($from_color['r'], $from_color['g'], $from_color['b']);
        $replacement_color = $this->rgb_to_hsl($to_color['r'], $to_color['g'], $to_color['b']);
        
        // Process each pixel
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $pixel_color = imagecolorat($image, $x, $y);
                
                // Extract RGB and alpha from pixel
                $pixel_r = ($pixel_color >> 16) & 0xFF;
                $pixel_g = ($pixel_color >> 8) & 0xFF;
                $pixel_b = $pixel_color & 0xFF;
                $pixel_a = ($pixel_color & 0x7F000000) >> 24;
                
                // Convert pixel to HSL
                $color_hsl = $this->rgb_to_hsl($pixel_r, $pixel_g, $pixel_b);
                
                // Check if pixel hue matches source color within tolerance
                if (($color_hsl[0] >= $color_to_replace[0] - $hue_absolute_error) && 
                    ($color_to_replace[0] + $hue_absolute_error) >= $color_hsl[0]) {
                    
                    // Replace hue and saturation, but preserve original lightness
                    $new_color = $this->hsl_to_rgb($replacement_color[0], $replacement_color[1], $color_hsl[2]);
                    $pixel_r = $new_color[0];
                    $pixel_g = $new_color[1];
                    $pixel_b = $new_color[2];
                }
                
                // Set the pixel (handle transparency)
                if ($pixel_a == 127) {
                    imagesetpixel($result, $x, $y, $trans_color);
                } else {
                    $new_pixel = imagecolorallocatealpha($result, $pixel_r, $pixel_g, $pixel_b, $pixel_a);
                    imagesetpixel($result, $x, $y, $new_pixel);
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Convert RGB color to HSL
     * Based on legacy jcogs_img implementation
     *
     * @param int $r Red component (0-255)
     * @param int $g Green component (0-255)
     * @param int $b Blue component (0-255)
     * @return array HSL array [H, S, L]
     */
    private function rgb_to_hsl(int $r, int $g, int $b): array
    {
        $r = $r / 255;
        $g = $g / 255;
        $b = $b / 255;
        
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;
        
        // Lightness
        $l = ($max + $min) / 2;
        
        if ($delta == 0) {
            // Achromatic
            $h = 0;
            $s = 0;
        } else {
            // Saturation
            $s = $l > 0.5 ? $delta / (2 - $max - $min) : $delta / ($max + $min);
            
            // Hue
            switch ($max) {
                case $r:
                    $h = (($g - $b) / $delta) + ($g < $b ? 6 : 0);
                    break;
                case $g:
                    $h = (($b - $r) / $delta) + 2;
                    break;
                case $b:
                    $h = (($r - $g) / $delta) + 4;
                    break;
            }
            $h /= 6;
        }
        
        // Convert to 0-360 for H, 0-100 for S and L to match legacy
        return [
            $h * 360,
            $s * 100,
            $l * 100
        ];
    }
    
    /**
     * Convert HSL color to RGB
     * Based on legacy jcogs_img implementation
     *
     * @param float $h Hue (0-360)
     * @param float $s Saturation (0-100)
     * @param float $l Lightness (0-100)
     * @return array RGB array [R, G, B]
     */
    private function hsl_to_rgb(float $h, float $s, float $l): array
    {
        $h = $h / 360;
        $s = $s / 100;
        $l = $l / 100;
        
        if ($s == 0) {
            // Achromatic
            $r = $g = $b = $l;
        } else {
            $hue_to_rgb = function ($p, $q, $t) {
                if ($t < 0) $t += 1;
                if ($t > 1) $t -= 1;
                if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
                if ($t < 1/2) return $q;
                if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
                return $p;
            };
            
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            
            $r = $hue_to_rgb($p, $q, $h + 1/3);
            $g = $hue_to_rgb($p, $q, $h);
            $b = $hue_to_rgb($p, $q, $h - 1/3);
        }
        
        return [
            round($r * 255),
            round($g * 255),
            round($b * 255)
        ];
    }
}
