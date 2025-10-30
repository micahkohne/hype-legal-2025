<?php

/**
 * JCOGS Image Pro - GD Dot Filter Implementation
 * ==============================================
 * GD-specific implementation of halftone dot pattern filter
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

use Imagine\Image\Palette\Color\ColorInterface;

/**
 * GD Dot Filter Implementation
 * 
 * Creates halftone dot pattern effect using Floyd-Steinberg dithering principles.
 * Matches legacy JCOGS Image dot filter behavior exactly.
 */
class Dot
{
    /**
     * Apply dot filter using GD (matches legacy algorithm exactly)
     *
     * @param mixed $image_data The image data (string, GD resource, or Imagine object)
     * @param array $parameters Processed parameters
     * @return string The processed image data as PNG string
     */
    public function apply($image_data, array $parameters): string
    {
        $block = $parameters['block'] ?? 6;
        $color = $parameters['color'] ?? null; // ColorInterface or null
        $type = $parameters['type'] ?? 'circle';
        $multiplier = $parameters['multiplier'] ?? 1.0;
        
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
            throw new \Exception('Failed to create image resource for dot filter');
        }
        
        // Apply legacy Floyd-Steinberg dithering dot algorithm
        $processed_image = $this->apply_legacy_dot_algorithm($image, $block, $color, $type, $multiplier);
        
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
     * Apply legacy dot algorithm (Floyd-Steinberg dithering approach)
     *
     * @param resource $image The source image
     * @param int $block Block size
     * @param ColorInterface|null $color Fixed color or null for pixel colors
     * @param string $type 'circle' or 'square'
     * @param float $multiplier Intensity multiplier
     * @return resource The processed image
     */
    private function apply_legacy_dot_algorithm($image, int $block, ?ColorInterface $color, string $type, float $multiplier)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Step 1: Create reduced image (matches legacy approach)
        // "First we cheat and shrink image by factor given as $block"
        $reduced_width = max(1, round($width / $block));
        $reduced_height = max(1, round($height / $block));
        
        $reduced_image = imagecreatetruecolor($reduced_width, $reduced_height);
        imagecopyresampled($reduced_image, $image, 0, 0, 0, 0, 
                          $reduced_width, $reduced_height, $width, $height);
        
        // Step 2: Create working image for the result
        $working_image = imagecreatetruecolor($width, $height);
        
        // Fill with white background (matches legacy)
        $white = imagecolorallocate($working_image, 255, 255, 255);
        imagefill($working_image, 0, 0, $white);
        
        // Step 3: Process each pixel in the reduced image (matches legacy callback)
        $pixel_count = $reduced_width * $reduced_height;
        for ($i = 0; $i < $pixel_count; $i++) {
            $x = $i % $reduced_width;
            $y = intval($i / $reduced_width);
            
            $this->process_pixel($reduced_image, $working_image, $x, $y, $block, $color, $type, $multiplier);
        }
        
        // Clean up
        imagedestroy($reduced_image);
        
        return $working_image;
    }
    
    /**
     * Process individual pixel (matches legacy callback function)
     *
     * @param resource $reduced_image Reduced source image
     * @param resource $working_image Working destination image
     * @param int $x X coordinate in reduced image
     * @param int $y Y coordinate in reduced image
     * @param int $block Block size
     * @param ColorInterface|null $color Fixed color or null for pixel colors
     * @param string $type 'circle' or 'square'
     * @param float $multiplier Intensity multiplier
     */
    private function process_pixel($reduced_image, $working_image, int $x, int $y, int $block, ?ColorInterface $color, string $type, float $multiplier): void
    {
        // Legacy constants
        $fudge_factor = 1.2;
        $circle_factor = 1.2;
        
        // Get pixel color from reduced image
        $pixel_rgb = imagecolorat($reduced_image, $x, $y);
        $pixel_r = ($pixel_rgb >> 16) & 0xFF;
        $pixel_g = ($pixel_rgb >> 8) & 0xFF;
        $pixel_b = $pixel_rgb & 0xFF;
        
        // Calculate intensity using legacy grayscale formula
        // "$intensity = (255-($pixel_color->grayscale())->getValue(ColorInterface::COLOR_RED))/255"
        $grayscale = intval(0.299 * $pixel_r + 0.587 * $pixel_g + 0.114 * $pixel_b);
        $intensity = (255 - $grayscale) / 255 * $multiplier * $fudge_factor;
        
        // Determine color to use for the dot
        // "If not specified, use the colour from pixel"
        if ($color === null) {
            // Use original pixel color (legacy behavior)
            $dot_color = imagecolorallocate($working_image, $pixel_r, $pixel_g, $pixel_b);
        } else {
            // Use specified color
            $dot_color = imagecolorallocate($working_image, 
                                          $color->getRed(), 
                                          $color->getGreen(), 
                                          $color->getBlue());
        }
        
        // Calculate position in working image
        $new_x = min(max($x * $block, 0), imagesx($working_image) - 1);
        $new_y = min(max($y * $block, 0), imagesy($working_image) - 1);
        
        // Draw the dot if it has size
        $nudge = round($block / 2.0);
        $radius = round($nudge * $intensity);
        
        if ($radius > 0) {
            if (strtolower(substr($type, 0, 1)) === 's') {
                // Square shape
                $x1 = $new_x + $radius + $nudge;
                $y1 = $new_y + $radius + $nudge;
                $x2 = $new_x + $radius * 2 + $nudge;
                $y2 = $new_y + $radius * 2 + $nudge;
                
                // Ensure coordinates are within image bounds
                $x1 = max(0, min($x1, imagesx($working_image) - 1));
                $y1 = max(0, min($y1, imagesy($working_image) - 1));
                $x2 = max(0, min($x2, imagesx($working_image) - 1));
                $y2 = max(0, min($y2, imagesy($working_image) - 1));
                
                imagefilledrectangle($working_image, $x1, $y1, $x2, $y2, $dot_color);
            } else {
                // Circle shape (default)
                $circle_radius = round(($block / 2) * $intensity * $circle_factor);
                $center_x = $new_x + $circle_radius + $nudge;
                $center_y = $new_y + $circle_radius + $nudge;
                
                // Ensure center is within image bounds
                $center_x = max($circle_radius, min($center_x, imagesx($working_image) - $circle_radius - 1));
                $center_y = max($circle_radius, min($center_y, imagesy($working_image) - $circle_radius - 1));
                
                imagefilledellipse($working_image, $center_x, $center_y, 
                                 $circle_radius * 2, $circle_radius * 2, $dot_color);
            }
        }
    }
}
