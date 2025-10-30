<?php

/**
 * JCOGS Image Pro - GD Sobel Edge Detection Filter Implementation
 * ===============================================================
 * GD-specific implementation of Sobel edge detection filter
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
 * GD Sobel Edge Detection Filter Implementation
 * 
 * Implements Sobel edge detection algorithm using GD.
 */
class SobelEdgify
{
    /**
     * Apply Sobel edge detection using GD
     *
     * @param mixed $image_data The image data (string, GD resource, or Imagine object)
     * @param array $parameters Processed parameters
     * @return string The processed image data as PNG string
     */
    public function apply($image_data, array $parameters): string
    {
        $threshold = $parameters['threshold'] ?? 50;
        $mode = $parameters['mode'] ?? 'edges';
        
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
            throw new \Exception('Failed to create image resource for Sobel edge detection');
        }
        
        // Apply Sobel edge detection
        $processed_image = $this->apply_sobel_detection($image, $threshold, $mode);
        
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
     * Apply Sobel edge detection algorithm
     *
     * @param resource $image Source image
     * @param int $threshold Edge detection threshold
     * @param string $mode Processing mode
     * @return resource Processed image
     */
    private function apply_sobel_detection($image, int $threshold, string $mode)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Convert to grayscale for edge detection
        $gray_image = $this->convert_to_grayscale($image);
        
        // Apply Sobel operators
        $edge_data = $this->calculate_sobel_edges($gray_image, $threshold);
        
        // Create result based on mode
        switch ($mode) {
            case 'enhance':
                $result = $this->enhance_with_edges($image, $edge_data);
                break;
                
            case 'combine':
                $result = $this->combine_with_edges($image, $edge_data);
                break;
                
            case 'edges':
            default:
                $result = $this->create_edge_image($width, $height, $edge_data);
                break;
        }
        
        // Clean up
        imagedestroy($gray_image);
        
        return $result;
    }
    
    /**
     * Convert image to grayscale
     *
     * @param resource $image Source image
     * @return resource Grayscale image
     */
    private function convert_to_grayscale($image)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $gray = imagecreatetruecolor($width, $height);
        
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $color = imagecolorat($image, $x, $y);
                $r = ($color >> 16) & 0xFF;
                $g = ($color >> 8) & 0xFF;
                $b = $color & 0xFF;
                
                // Calculate luminance
                $gray_value = intval(0.299 * $r + 0.587 * $g + 0.114 * $b);
                $gray_color = imagecolorallocate($gray, $gray_value, $gray_value, $gray_value);
                imagesetpixel($gray, $x, $y, $gray_color);
            }
        }
        
        return $gray;
    }
    
    /**
     * Calculate Sobel edge detection
     *
     * @param resource $gray_image Grayscale image
     * @param int $threshold Edge threshold
     * @return array Edge magnitude data
     */
    private function calculate_sobel_edges($gray_image, int $threshold): array
    {
        $width = imagesx($gray_image);
        $height = imagesy($gray_image);
        $edge_data = [];
        
        // Sobel X operator (vertical edges)
        $sobel_x = [
            [-1, 0, 1],
            [-2, 0, 2],
            [-1, 0, 1]
        ];
        
        // Sobel Y operator (horizontal edges)
        $sobel_y = [
            [-1, -2, -1],
            [0,  0,  0],
            [1,  2,  1]
        ];
        
        // Calculate threshold value
        $threshold_value = $threshold * 255 / 100;
        
        for ($x = 1; $x < $width - 1; $x++) {
            for ($y = 1; $y < $height - 1; $y++) {
                $gx = 0;
                $gy = 0;
                
                // Apply Sobel operators
                for ($i = -1; $i <= 1; $i++) {
                    for ($j = -1; $j <= 1; $j++) {
                        $pixel = imagecolorat($gray_image, $x + $i, $y + $j);
                        $intensity = ($pixel >> 16) & 0xFF; // Use red channel (all channels are same in grayscale)
                        
                        $gx += $sobel_x[$i + 1][$j + 1] * $intensity;
                        $gy += $sobel_y[$i + 1][$j + 1] * $intensity;
                    }
                }
                
                // Calculate edge magnitude
                $magnitude = sqrt($gx * $gx + $gy * $gy);
                
                // Store edge data
                $edge_data[$x][$y] = [
                    'magnitude' => $magnitude,
                    'is_edge' => $magnitude > $threshold_value
                ];
            }
        }
        
        return $edge_data;
    }
    
    /**
     * Create pure edge image
     *
     * @param int $width Image width
     * @param int $height Image height
     * @param array $edge_data Edge detection data
     * @return resource Edge image
     */
    private function create_edge_image(int $width, int $height, array $edge_data)
    {
        $result = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($result, 255, 255, 255);
        $black = imagecolorallocate($result, 0, 0, 0);
        
        // Fill with white background
        imagefill($result, 0, 0, $white);
        
        // Draw edges in black
        for ($x = 1; $x < $width - 1; $x++) {
            for ($y = 1; $y < $height - 1; $y++) {
                if (isset($edge_data[$x][$y]) && $edge_data[$x][$y]['is_edge']) {
                    imagesetpixel($result, $x, $y, $black);
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Enhance original image with edges
     *
     * @param resource $original Original image
     * @param array $edge_data Edge detection data
     * @return resource Enhanced image
     */
    private function enhance_with_edges($original, array $edge_data)
    {
        $width = imagesx($original);
        $height = imagesy($original);
        $result = imagecreatetruecolor($width, $height);
        
        // Copy original image
        imagecopy($result, $original, 0, 0, 0, 0, $width, $height);
        
        // Enhance edges
        for ($x = 1; $x < $width - 1; $x++) {
            for ($y = 1; $y < $height - 1; $y++) {
                if (isset($edge_data[$x][$y]) && $edge_data[$x][$y]['is_edge']) {
                    $original_color = imagecolorat($result, $x, $y);
                    $r = ($original_color >> 16) & 0xFF;
                    $g = ($original_color >> 8) & 0xFF;
                    $b = $original_color & 0xFF;
                    
                    // Enhance edge pixels by increasing contrast
                    $enhancement_factor = 1.5;
                    $new_r = min(255, intval($r * $enhancement_factor));
                    $new_g = min(255, intval($g * $enhancement_factor));
                    $new_b = min(255, intval($b * $enhancement_factor));
                    
                    $enhanced_color = imagecolorallocate($result, $new_r, $new_g, $new_b);
                    imagesetpixel($result, $x, $y, $enhanced_color);
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Combine original image with edge overlay
     *
     * @param resource $original Original image
     * @param array $edge_data Edge detection data
     * @return resource Combined image
     */
    private function combine_with_edges($original, array $edge_data)
    {
        $width = imagesx($original);
        $height = imagesy($original);
        $result = imagecreatetruecolor($width, $height);
        
        // Copy original image
        imagecopy($result, $original, 0, 0, 0, 0, $width, $height);
        
        // Overlay edges in black
        $black = imagecolorallocate($result, 0, 0, 0);
        
        for ($x = 1; $x < $width - 1; $x++) {
            for ($y = 1; $y < $height - 1; $y++) {
                if (isset($edge_data[$x][$y]) && $edge_data[$x][$y]['is_edge']) {
                    // Blend edge with original
                    $original_color = imagecolorat($result, $x, $y);
                    $r = ($original_color >> 16) & 0xFF;
                    $g = ($original_color >> 8) & 0xFF;
                    $b = $original_color & 0xFF;
                    
                    // Darken edge pixels
                    $blend_factor = 0.7;
                    $new_r = intval($r * $blend_factor);
                    $new_g = intval($g * $blend_factor);
                    $new_b = intval($b * $blend_factor);
                    
                    $blended_color = imagecolorallocate($result, $new_r, $new_g, $new_b);
                    imagesetpixel($result, $x, $y, $blended_color);
                }
            }
        }
        
        return $result;
    }
}
