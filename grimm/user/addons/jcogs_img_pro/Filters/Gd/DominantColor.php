<?php

/**
 * JCOGS Image Pro - GD Dominant Color Filter Implementation
 * =========================================================
 * GD-specific implementation of dominant color analysis filter
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
 * GD Dominant Color Filter Implementation
 * 
 * Analyzes image to find dominant color and applies various effects.
 */
class DominantColor
{
    /**
     * Apply dominant color filter using GD
     *
     * @param mixed $image_data The image data (string, GD resource, or Imagine object)
     * @param array $parameters Processed parameters
     * @return string The processed image data as PNG string
     */
    public function apply($image_data, array $parameters): string
    {
        $mode = $parameters['mode'] ?? 'extract';  // Default to 'extract' for legacy compatibility
        $strength = $parameters['strength'] ?? 50;
        
        // Create GD resource from image data
        if (is_string($image_data)) {
            $image = imagecreatefromstring($image_data);
        } elseif ($image_data instanceof \Imagine\Gd\Image) {
            // Extract GD resource from Imagine object
            $image = imagecreatefromstring($image_data->__toString());
        } else {
            // Assume it's already a GD resource
            $image = $image_data;
        }
        
        if (!$image) {
            throw new \Exception('Failed to create image resource for dominant color');
        }
        
        // Find dominant color
        $dominant_color = $this->find_dominant_color($image);
        
        // Apply effect based on mode
        $processed_image = $this->apply_dominant_color_effect($image, $dominant_color, $mode, $strength);
        
        // Convert to PNG string
        ob_start();
        imagepng($processed_image);
        $result = ob_get_clean();
        
        // Clean up
        if ($processed_image !== $image) {
            imagedestroy($processed_image);
        }
        if (is_string($image_data)) {
            imagedestroy($image);
        }
        
        return $result;
    }
    
    /**
     * Find the dominant color in the image using ColorThief library
     *
     * @param resource $image The GD image resource
     * @return array RGB values of dominant color
     */
    private function find_dominant_color($image): array
    {
        try {
            // Use ColorThief library for more accurate color extraction
            // This uses the MMCQ (Modified Median Cut Quantization) algorithm
            $dominant_color = \ColorThief\ColorThief::getColor($image, 10);
            
            if ($dominant_color && is_array($dominant_color) && count($dominant_color) >= 3) {
                return [
                    'r' => $dominant_color[0],
                    'g' => $dominant_color[1],
                    'b' => $dominant_color[2]
                ];
            }
        } catch (\Exception $e) {
            // Fall back to simple algorithm if ColorThief fails
        }
        
        // Fallback: simple frequency-based approach
        return $this->find_dominant_color_fallback($image);
    }
    
    /**
     * Fallback dominant color detection using simple frequency analysis
     *
     * @param resource $image The GD image resource
     * @return array RGB values of dominant color
     */
    private function find_dominant_color_fallback($image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Sample image at intervals to find dominant color
        $color_count = [];
        $sample_rate = max(1, floor(min($width, $height) / 50)); // Sample every N pixels
        
        for ($x = 0; $x < $width; $x += $sample_rate) {
            for ($y = 0; $y < $height; $y += $sample_rate) {
                $color = imagecolorat($image, $x, $y);
                $r = ($color >> 16) & 0xFF;
                $g = ($color >> 8) & 0xFF;
                $b = $color & 0xFF;
                
                // Group similar colors (reduce precision to find dominant ranges)
                $r_group = intval($r / 32) * 32;
                $g_group = intval($g / 32) * 32;
                $b_group = intval($b / 32) * 32;
                
                $color_key = "{$r_group},{$g_group},{$b_group}";
                $color_count[$color_key] = ($color_count[$color_key] ?? 0) + 1;
            }
        }
        
        // Find most frequent color group
        $dominant_key = array_keys($color_count, max($color_count))[0];
        list($r, $g, $b) = explode(',', $dominant_key);
        
        return [
            'r' => intval($r),
            'g' => intval($g),
            'b' => intval($b)
        ];
    }
    
    /**
     * Apply dominant color effect to image
     *
     * @param resource $image The source image
     * @param array $dominant_color The dominant color RGB values
     * @param string $mode Effect mode
     * @param int $strength Effect strength (0-100)
     * @return resource The processed image
     */
    private function apply_dominant_color_effect($image, array $dominant_color, string $mode, int $strength)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        
        switch ($mode) {
            case 'extract':
                return $this->create_dominant_color_image($width, $height, $dominant_color);
                
            case 'tint':
                return $this->apply_dominant_color_tint($image, $dominant_color, $strength);
                
            case 'overlay':
            default:
                return $this->apply_dominant_color_overlay($image, $dominant_color, $strength);
        }
    }
    
    /**
     * Create an image filled with the dominant color
     *
     * @param int $width Image width
     * @param int $height Image height
     * @param array $color RGB color values
     * @return resource New image with dominant color
     */
    private function create_dominant_color_image(int $width, int $height, array $color)
    {
        $image = imagecreatetruecolor($width, $height);
        $fill_color = imagecolorallocate($image, $color['r'], $color['g'], $color['b']);
        imagefill($image, 0, 0, $fill_color);
        return $image;
    }
    
    /**
     * Apply dominant color as overlay
     *
     * @param resource $image Source image
     * @param array $color Dominant color
     * @param int $strength Overlay strength (0-100)
     * @return resource Processed image
     */
    private function apply_dominant_color_overlay($image, array $color, int $strength)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $result = imagecreatetruecolor($width, $height);
        
        // Copy original image
        imagecopy($result, $image, 0, 0, 0, 0, $width, $height);
        
        // Create overlay layer
        $overlay = imagecreatetruecolor($width, $height);
        $overlay_color = imagecolorallocate($overlay, $color['r'], $color['g'], $color['b']);
        imagefill($overlay, 0, 0, $overlay_color);
        
        // Blend overlay with original
        $alpha = intval((100 - $strength) * 1.27); // Convert to 0-127 range
        imagesetpixel($overlay, 0, 0, imagecolorallocatealpha($overlay, $color['r'], $color['g'], $color['b'], $alpha));
        
        // Manual blending for better control
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $orig_color = imagecolorat($result, $x, $y);
                $orig_r = ($orig_color >> 16) & 0xFF;
                $orig_g = ($orig_color >> 8) & 0xFF;
                $orig_b = $orig_color & 0xFF;
                
                // Blend with dominant color
                $blend_factor = $strength / 100;
                $new_r = intval($orig_r * (1 - $blend_factor) + $color['r'] * $blend_factor);
                $new_g = intval($orig_g * (1 - $blend_factor) + $color['g'] * $blend_factor);
                $new_b = intval($orig_b * (1 - $blend_factor) + $color['b'] * $blend_factor);
                
                $new_color = imagecolorallocate($result, $new_r, $new_g, $new_b);
                imagesetpixel($result, $x, $y, $new_color);
            }
        }
        
        imagedestroy($overlay);
        return $result;
    }
    
    /**
     * Apply dominant color as tint
     *
     * @param resource $image Source image
     * @param array $color Dominant color
     * @param int $strength Tint strength (0-100)
     * @return resource Processed image
     */
    private function apply_dominant_color_tint($image, array $color, int $strength)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $result = imagecreatetruecolor($width, $height);
        
        $tint_factor = $strength / 200; // Reduce factor for subtler effect
        
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $pixel = imagecolorat($image, $x, $y);
                $r = ($pixel >> 16) & 0xFF;
                $g = ($pixel >> 8) & 0xFF;
                $b = $pixel & 0xFF;
                
                // Apply tint by shifting colors toward dominant color
                $new_r = intval($r + ($color['r'] - $r) * $tint_factor);
                $new_g = intval($g + ($color['g'] - $g) * $tint_factor);
                $new_b = intval($b + ($color['b'] - $b) * $tint_factor);
                
                // Clamp values
                $new_r = max(0, min(255, $new_r));
                $new_g = max(0, min(255, $new_g));
                $new_b = max(0, min(255, $new_b));
                
                $new_color = imagecolorallocate($result, $new_r, $new_g, $new_b);
                imagesetpixel($result, $x, $y, $new_color);
            }
        }
        
        return $result;
    }
}
