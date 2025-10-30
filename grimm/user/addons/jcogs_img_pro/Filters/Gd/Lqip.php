<?php

/**
 * JCOGS Image Pro - GD LQIP Filter Implementation
 * ===============================================
 * GD-specific implementation of Low Quality Image Placeholder filter
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
 * GD LQIP (Low Quality Image Placeholder) Filter Implementation
 * 
 * Creates low-quality placeholders for progressive image loading.
 */
class Lqip
{
    /**
     * Apply LQIP filter using GD
     *
     * @param mixed $image_data The image data (string, GD resource, or Imagine object)
     * @param array $parameters Processed parameters
     * @return string The processed image data in original format (preserves transparency)
     */
    public function apply($image_data, array $parameters): string
    {
        // Legacy LQIP uses fixed parameters
        $pixelate_level = $parameters['pixelate_level'] ?? 6;
        $blur_level = $parameters['blur_level'] ?? 12;
        $quality = $parameters['quality'] ?? 20;
        
        // Detect original format for transparency preservation
        $original_format = $this->detect_image_format($image_data);
        
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
            throw new \Exception('Failed to create image resource for LQIP');
        }
        
        // Apply LQIP processing with fixed legacy values
        $processed_image = $this->create_lqip($image, $pixelate_level, $blur_level);
        
        // Output in original format to preserve transparency (like Legacy does)
        ob_start();
        switch ($original_format) {
            case 'png':
                imagepng($processed_image, null, 9); // High compression for LQIP
                break;
            case 'webp':
                if (function_exists('imagewebp')) {
                    imagewebp($processed_image, null, $quality);
                } else {
                    // Fallback to PNG if WebP not supported
                    imagepng($processed_image, null, 9);
                }
                break;
            case 'gif':
                imagegif($processed_image);
                break;
            case 'jpg':
            case 'jpeg':
            default:
                imagejpeg($processed_image, null, $quality);
                break;
        }
        $result = ob_get_clean();
        
        // Clean up
        imagedestroy($processed_image);
        if (is_string($image_data)) {
            imagedestroy($image);
        }
        
        return $result;
    }
    
    /**
     * Create LQIP version of image (matches legacy order: pixelate then blur)
     *
     * @param resource $image Source image
     * @param float $scale Scale factor (converted to pixelate level)
     * @param int $blur Blur intensity
     * @return resource Processed LQIP image
     */
    private function create_lqip($image, int $pixelate_level, int $blur_level)
    {
        $original_width = imagesx($image);
        $original_height = imagesy($image);
        
        // Legacy approach: pixelate(6) then blur(12) - fixed values exactly!
        // Use the passed fixed values directly 
        
        // Step 1: Apply pixelation exactly like legacy (fixed level)
        $pixelated_image = $this->apply_pixelation($image, $pixelate_level);
        
        // Step 2: Apply blur after pixelation (fixed level)
        $final_image = $this->apply_blur($pixelated_image, $blur_level);
        
        // Clean up intermediate image
        if ($pixelated_image !== $image) {
            imagedestroy($pixelated_image);
        }
        
        return $final_image;
    }
    
    /**
     * Resize image to new dimensions
     *
     * @param resource $image Source image
     * @param int $new_width Target width
     * @param int $new_height Target height
     * @return resource Resized image
     */
    
    /**
     * Apply blur effect to image
     *
     * @param resource $image Image to blur
     * @param int $blur_intensity Blur intensity (1-20)
     * @return resource Blurred image
     */
    private function apply_blur($image, int $blur_intensity)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $blurred = imagecreatetruecolor($width, $height);
        
        // Preserve transparency for images with alpha channel
        imagealphablending($blurred, false);
        imagesavealpha($blurred, true);
        $transparent = imagecolorallocatealpha($blurred, 255, 255, 255, 127);
        imagefill($blurred, 0, 0, $transparent);
        imagealphablending($blurred, true);
        
        // Copy original
        imagecopy($blurred, $image, 0, 0, 0, 0, $width, $height);
        
        // Apply multiple passes of gaussian blur
        $passes = min(10, intval($blur_intensity / 2));
        
        for ($i = 0; $i < $passes; $i++) {
            if (function_exists('imagefilter')) {
                imagefilter($blurred, IMG_FILTER_GAUSSIAN_BLUR);
            } else {
                // Fallback manual blur
                $this->manual_blur($blurred);
            }
        }
        
        return $blurred;
    }
    
    /**
     * Manual blur implementation for systems without imagefilter
     *
     * @param resource $image Image to blur (modified in place)
     */
    private function manual_blur($image): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Simple box blur
        $temp = imagecreatetruecolor($width, $height);
        imagecopy($temp, $image, 0, 0, 0, 0, $width, $height);
        
        for ($x = 1; $x < $width - 1; $x++) {
            for ($y = 1; $y < $height - 1; $y++) {
                // Sample 3x3 area around pixel
                $colors = [];
                for ($dx = -1; $dx <= 1; $dx++) {
                    for ($dy = -1; $dy <= 1; $dy++) {
                        $sample_color = imagecolorat($temp, $x + $dx, $y + $dy);
                        $colors[] = [
                            'r' => ($sample_color >> 16) & 0xFF,
                            'g' => ($sample_color >> 8) & 0xFF,
                            'b' => $sample_color & 0xFF
                        ];
                    }
                }
                
                // Calculate average
                $avg_r = array_sum(array_column($colors, 'r')) / count($colors);
                $avg_g = array_sum(array_column($colors, 'g')) / count($colors);
                $avg_b = array_sum(array_column($colors, 'b')) / count($colors);
                
                $new_color = imagecolorallocate($image, $avg_r, $avg_g, $avg_b);
                imagesetpixel($image, $x, $y, $new_color);
            }
        }
        
        imagedestroy($temp);
    }
    
    /**
     * Apply pixelation effect to image
     *
     * @param resource $image Image to pixelate
     * @param int $pixel_size Pixel size for effect
     * @return resource Pixelated image
     */
    private function apply_pixelation($image, int $pixel_size)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $pixelated = imagecreatetruecolor($width, $height);
        
        // Preserve transparency for images with alpha channel
        imagealphablending($pixelated, false);
        imagesavealpha($pixelated, true);
        $transparent = imagecolorallocatealpha($pixelated, 255, 255, 255, 127);
        imagefill($pixelated, 0, 0, $transparent);
        imagealphablending($pixelated, true);
        
        // Apply pixelation effect
        if (function_exists('imagefilter')) {
            imagecopy($pixelated, $image, 0, 0, 0, 0, $width, $height);
            imagefilter($pixelated, IMG_FILTER_PIXELATE, $pixel_size, true);
        } else {
            // Manual pixelation fallback
            for ($x = 0; $x < $width; $x += $pixel_size) {
                for ($y = 0; $y < $height; $y += $pixel_size) {
                    // Get average color for this block
                    $avg_color = $this->get_average_color($image, $x, $y, 
                        min($pixel_size, $width - $x), 
                        min($pixel_size, $height - $y));
                    
                    // Fill the block with average color
                    imagefilledrectangle($pixelated, $x, $y, 
                        min($x + $pixel_size - 1, $width - 1), 
                        min($y + $pixel_size - 1, $height - 1), 
                        $avg_color);
                }
            }
        }
        
        return $pixelated;
    }
        
    /**
     * Detect image format from image data
     *
     * @param mixed $image_data The image data
     * @return string Format (png, webp, gif, jpg)
     */
    private function detect_image_format($image_data): string
    {
        if ($image_data instanceof \Imagine\Gd\Image) {
            // For Imagine objects, try to detect from the binary data
            $binary_data = $image_data->__toString();
        } elseif (is_string($image_data)) {
            $binary_data = $image_data;
        } else {
            // For GD resource, default to jpg
            return 'jpg';
        }
        
        // Check magic bytes to detect format
        $header = substr($binary_data, 0, 12);
        
        if (substr($header, 0, 8) === "\x89PNG\r\n\x1a\n") {
            return 'png';
        } elseif (substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP') {
            return 'webp';
        } elseif (substr($header, 0, 6) === 'GIF87a' || substr($header, 0, 6) === 'GIF89a') {
            return 'gif';
        } elseif (substr($header, 0, 3) === "\xFF\xD8\xFF") {
            return 'jpg';
        }
        
        // Default to jpg if format can't be detected
        return 'jpg';
    }

    /**
     * Get average color of an image region
     *
     * @param resource $image Source image
     * @param int $x Starting X coordinate
     * @param int $y Starting Y coordinate
     * @param int $width Width of region
     * @param int $height Height of region
     * @return int Color index
     */
    private function get_average_color($image, int $x, int $y, int $width, int $height): int
    {
        $total_r = 0;
        $total_g = 0;
        $total_b = 0;
        $total_a = 0;
        $pixel_count = 0;
        
        for ($px = $x; $px < $x + $width; $px++) {
            for ($py = $y; $py < $y + $height; $py++) {
                $color = imagecolorat($image, $px, $py);
                $colors = imagecolorsforindex($image, $color);
                
                $total_r += $colors['red'];
                $total_g += $colors['green'];
                $total_b += $colors['blue'];
                $total_a += $colors['alpha'];
                $pixel_count++;
            }
        }
        
        if ($pixel_count === 0) {
            return imagecolorallocate($image, 0, 0, 0);
        }
        
        $avg_r = intval($total_r / $pixel_count);
        $avg_g = intval($total_g / $pixel_count);
        $avg_b = intval($total_b / $pixel_count);
        $avg_a = intval($total_a / $pixel_count);
        
        return imagecolorallocatealpha($image, $avg_r, $avg_g, $avg_b, $avg_a);
    }
}
