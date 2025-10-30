<?php

/**
 * JCOGS Image Pro - GD Auto Sharpen Filter Implementation
 * =======================================================
 * GD-specific implementation of automatic sharpening filter
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
 * GD Auto Sharpen Filter Implementation
 * 
 * Implements automatic sharpening for GD library.
 * Uses edge detection to analyze image and apply optimal sharpening.
 */
class AutoSharpen
{
    /**
     * Apply auto sharpen filter using GD
     *
     * @param mixed $image_data The image data (string, GD resource, or Imagine object)
     * @param array $parameters Processed parameters
     * @return string The processed image data as PNG string
     */
    public function apply($image_data, array $parameters): string
    {
        $strength = $parameters['strength'] ?? 1.0;
        
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
            throw new \Exception('Failed to create image resource for auto sharpen');
        }
        
        // Apply auto sharpening algorithm
        $processed_image = $this->apply_auto_sharpen_algorithm($image, $strength);
        
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
     * Apply auto sharpening algorithm
     *
     * @param resource $image The GD image resource
     * @param float $strength Sharpening strength multiplier
     * @return resource The processed image resource
     */
    private function apply_auto_sharpen_algorithm($image, float $strength)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Create a copy for processing
        $sharpened = imagecreatetruecolor($width, $height);
        imagecopy($sharpened, $image, 0, 0, 0, 0, $width, $height);
        
        // Auto-sharpening matrix - adaptive based on strength
        $base_matrix = [
            [0, -1, 0],
            [-1, 5, -1],
            [0, -1, 0]
        ];
        
        // Adjust matrix based on strength
        $matrix = [];
        for ($i = 0; $i < 3; $i++) {
            for ($j = 0; $j < 3; $j++) {
                if ($i == 1 && $j == 1) {
                    // Center value - increase with strength
                    $matrix[$i][$j] = 1 + (4 * $strength);
                } else if ($base_matrix[$i][$j] != 0) {
                    // Edge values - scale with strength
                    $matrix[$i][$j] = $base_matrix[$i][$j] * $strength;
                } else {
                    $matrix[$i][$j] = 0;
                }
            }
        }
        
        // Apply convolution matrix
        if (function_exists('imageconvolution')) {
            $divisor = array_sum(array_map('array_sum', $matrix));
            if ($divisor == 0) $divisor = 1;
            
            imageconvolution($sharpened, $matrix, $divisor, 0);
        } else {
            // Fallback: apply basic sharpening
            $this->apply_manual_sharpen($sharpened, $strength);
        }
        
        return $sharpened;
    }
    
    /**
     * Manual sharpening fallback for systems without imageconvolution
     *
     * @param resource $image The image resource to sharpen
     * @param float $strength Sharpening strength
     */
    private function apply_manual_sharpen($image, float $strength): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Simple edge enhancement
        for ($x = 1; $x < $width - 1; $x++) {
            for ($y = 1; $y < $height - 1; $y++) {
                $center = imagecolorat($image, $x, $y);
                $top = imagecolorat($image, $x, $y - 1);
                $bottom = imagecolorat($image, $x, $y + 1);
                $left = imagecolorat($image, $x - 1, $y);
                $right = imagecolorat($image, $x + 1, $y);
                
                // Extract RGB components
                $center_r = ($center >> 16) & 0xFF;
                $center_g = ($center >> 8) & 0xFF;
                $center_b = $center & 0xFF;
                
                // Calculate edge strength
                $edge_strength = abs(($top >> 16 & 0xFF) - $center_r) +
                               abs(($bottom >> 16 & 0xFF) - $center_r) +
                               abs(($left >> 16 & 0xFF) - $center_r) +
                               abs(($right >> 16 & 0xFF) - $center_r);
                
                // Apply sharpening based on edge strength
                if ($edge_strength > 10) { // Only sharpen where there are edges
                    $factor = 1 + ($strength * 0.1);
                    $new_r = min(255, max(0, $center_r * $factor));
                    $new_g = min(255, max(0, $center_g * $factor));
                    $new_b = min(255, max(0, $center_b * $factor));
                    
                    $new_color = imagecolorallocate($image, $new_r, $new_g, $new_b);
                    imagesetpixel($image, $x, $y, $new_color);
                }
            }
        }
    }
}
