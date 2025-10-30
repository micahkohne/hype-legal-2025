<?php

/**
 * JCOGS Image Pro - GD-Specific Noise Filter
 * ==========================================
 * Custom noise implementation for adding random pixels to images
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

use Imagine\Image\ImageInterface;
use Imagine\Gd\Imagine;

/**
 * GD-Specific Noise Filter Class
 * 
 * Implements custom noise algorithm that adds random pixels to the image.
 * Uses pixel-by-pixel processing for fine control over noise characteristics.
 */
class Noise
{
    /**
     * Apply GD-native noise filter
     * 
     * Uses custom algorithm to add random noise to the image.
     * 
     * @param ImageInterface $image Source image (must be GD image)
     * @param array $params Filter parameters from top-level filter
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image, array $params = []): ImageInterface
    {
        // Get processed parameters from top-level filter
        $level = $params['level'] ?? 30;
        
        // Skip processing if noise level is 0
        if ($level <= 0) {
            return $image;
        }
        
        // Get the GD resource using the same method as legacy
        $gd_resource = imagecreatefromstring($image->__toString());
        
        if (!is_resource($gd_resource) && !is_object($gd_resource)) {
            throw new \RuntimeException('Invalid GD resource for noise filter');
        }
        
        // Apply noise algorithm
        $this->apply_noise_algorithm($gd_resource, $level);
        
        // Convert back to Imagine image format
        $imagine = new Imagine();
        return $imagine->load(stream_get_contents($this->gd_resource_to_stream($gd_resource)));
    }
    
    /**
     * Apply noise algorithm to GD resource (matches legacy exactly)
     * 
     * @param resource $gd_resource
     * @param int $level Noise level (0-255, matches legacy)
     */
    private function apply_noise_algorithm($gd_resource, int $level): void
    {
        $width = imagesx($gd_resource);
        $height = imagesy($gd_resource);
        
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                // Legacy: Only do the work for a random selection of pixels
                // Use random method from php rand manual page - rand()&1
                if (rand() & 1) {
                    $original_color = imagecolorat($gd_resource, $x, $y);
                    $r = ($original_color >> 16) & 0xFF;
                    $g = ($original_color >> 8) & 0xFF;
                    $b = $original_color & 0xFF;
                    
                    // Legacy: Get direction for change (positive or negative)
                    $direction = (rand() & 1) ? 1 : -1;
                    
                    // Legacy: Get size of change - rand()&$level (bitwise AND for exact legacy match)
                    // This is crucial for matching legacy noise density exactly
                    $step = rand() & $level;
                    
                    // Legacy: Get random adjustment to colour value
                    $adjustment = $step * $direction;
                    
                    // Legacy: Work out adjusted pixel values (apply to each RGB channel)
                    $new_r = max(0, min(255, $r + $adjustment));
                    $new_g = max(0, min(255, $g + $adjustment));
                    $new_b = max(0, min(255, $b + $adjustment));
                    
                    // Set the new pixel color
                    $new_color = imagecolorallocate($gd_resource, $new_r, $new_g, $new_b);
                    imagesetpixel($gd_resource, $x, $y, $new_color);
                }
            }
        }
    }
    
    /**
     * Convert GD resource to stream for Imagine
     * 
     * @param resource $gd_resource
     * @return resource
     */
    private function gd_resource_to_stream($gd_resource)
    {
        $stream = fopen('php://temp', 'r+');
        
        // Save as PNG to preserve quality
        ob_start();
        imagepng($gd_resource);
        $image_data = ob_get_clean();
        
        fwrite($stream, $image_data);
        rewind($stream);
        
        return $stream;
    }
}
