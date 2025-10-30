<?php

/**
 * JCOGS Image Pro - GD Face Detection Filter Implementation
 * =========================================================
 * GD-specific implementation of face detection filter
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

use JCOGSDesign\JCOGSImagePro\Service\FaceDetectionService;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

/**
 * GD Face Detection Filter Implementation
 * 
 * Face detection using shared FaceDetectionService.
 * Updated to use centralized face detection logic.
 */
class FaceDetect
{
    /**
     * Apply face detection filter using GD
     *
     * @param mixed $image_data The image data (string, GD resource, or Imagine object)
     * @param array $parameters Processed parameters
     * @return string The processed image data as PNG string
     */
    public function apply($image_data, array $parameters): string
    {
        // Force action to 'highlight' to match legacy behavior by default
        $action = $parameters['action'] ?? 'highlight';
        $strength = $parameters['strength'] ?? 50;
        
        // Create GD resource from image data
        if (is_string($image_data)) {
            $image = imagecreatefromstring($image_data);
        } elseif ($image_data instanceof \Imagine\Gd\Image) {
            // Extract GD resource from Imagine object
            $image = imagecreatefromstring($image_data->__toString());
        } else {
            $image = $image_data;
        }
        
        if (!$image) {
            throw new \Exception('Failed to create image resource for face detection');
        }
        
        // Check if cached face regions are provided (performance optimization)
        $face_regions = $parameters['cached_face_regions'] ?? null;
        
        if ($face_regions === null) {
            // No cached regions, run face detection
            $face_detection_service = ServiceCache::face_detection();
            
            // Convert strength (0-100) to sensitivity (1-9) for legacy compatibility
            $sensitivity = max(1, min(9, intval(($strength / 100) * 9) + 1));
            
            // Detect faces
            $face_regions = $face_detection_service->detect_faces($image, $sensitivity);
        } else {
            // Using cached face regions - add debug logging
            $utilities_service = \JCOGSDesign\JCOGSImagePro\Service\ServiceCache::utilities();
            $utilities_service->debug_message("JCOGS Image Pro (GD FaceDetect): Using cached face regions: " . count($face_regions) . " faces");
            $utilities_service->debug_message("JCOGS Image Pro (GD FaceDetect): Face regions data type: " . gettype($face_regions));
            $utilities_service->debug_message("JCOGS Image Pro (GD FaceDetect): Face regions content: " . print_r($face_regions, true));
        }
        
        // Apply action to detected regions - always use highlight for legacy compatibility
        $processed_image = $this->apply_face_action($image, $face_regions, 'highlight', $strength);
        
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
     * Apply face detection action to image
     *
     * @param resource $image Source image
     * @param array $face_regions Detected face regions
     * @param string $action Action to perform
     * @param int $strength Effect strength
     * @return resource Processed image
     */
    private function apply_face_action($image, array $face_regions, string $action, int $strength)
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $result = imagecreatetruecolor($width, $height);
        
        // Copy original image
        imagecopy($result, $image, 0, 0, 0, 0, $width, $height);
        
        foreach ($face_regions as $index => $region) {
            switch ($action) {
                case 'highlight':
                    $this->highlight_region($result, $region, $strength, $index === 0);
                    break;
                    
                case 'blur':
                    $this->blur_region($result, $region, $strength);
                    break;
                    
                case 'mask':
                    $this->mask_region($result, $region, $strength);
                    break;
                    
                case 'detect':
                default:
                    $this->outline_region($result, $region);
                    break;
            }
        }
        
        return $result;
    }
    
    /**
     * Highlight a detected face region with colored rectangle outline (matches legacy)
     *
     * @param resource $image Target image
     * @param array $region Face region
     * @param int $strength Highlight strength (used for line thickness)
     * @param bool $is_first_face Whether this is the first detected face
     */
    private function highlight_region($image, array $region, int $strength, bool $is_first_face = false): void
    {
        // Validate region has required keys
        if (!isset($region['min_x'], $region['min_y'], $region['max_x'], $region['max_y'])) {
            return; // Skip invalid regions
        }
        
        // Legacy colors: first face green (#01bf42), subsequent faces yellow (#eded03)
        if ($is_first_face) {
            $border_color = imagecolorallocate($image, 1, 191, 66);  // Green #01bf42
        } else {
            $border_color = imagecolorallocate($image, 237, 237, 3); // Yellow #eded03
        }
        
        // Ensure alpha blending is enabled for proper color display
        imagealphablending($image, true);
        
        // Legacy uses thickness of 2 pixels - use fixed thickness to match exactly
        $thickness = 2;
        
        // Draw rectangle outline only (not filled) - matches legacy behavior exactly
        for ($i = 0; $i < $thickness; $i++) {
            imagerectangle(
                $image,
                $region['min_x'] - $i, $region['min_y'] - $i,
                $region['max_x'] + $i, $region['max_y'] + $i,
                $border_color
            );
        }
    }
    
    /**
     * Apply blur to a face region
     *
     * @param resource $image Target image
     * @param array $region Face region
     * @param int $strength Blur strength
     */
    private function blur_region($image, array $region, int $strength): void
    {
        // Validate region has required keys
        if (!isset($region['min_x'], $region['min_y'], $region['max_x'], $region['max_y'])) {
            return; // Skip invalid regions
        }
        
        // Simple blur by sampling nearby pixels
        $blur_radius = intval($strength / 20) + 1;
        
        for ($x = $region['min_x']; $x <= $region['max_x']; $x++) {
            for ($y = $region['min_y']; $y <= $region['max_y']; $y++) {
                if ($x >= 0 && $y >= 0 && $x < imagesx($image) && $y < imagesy($image)) {
                    $blurred_color = $this->get_averaged_color($image, $x, $y, $blur_radius);
                    imagesetpixel($image, $x, $y, $blurred_color);
                }
            }
        }
    }
    
    /**
     * Mask a face region
     *
     * @param resource $image Target image
     * @param array $region Face region
     * @param int $strength Mask opacity
     */
    private function mask_region($image, array $region, int $strength): void
    {
        // Validate region has required keys
        if (!isset($region['min_x'], $region['min_y'], $region['max_x'], $region['max_y'])) {
            return; // Skip invalid regions
        }
        
        $black = imagecolorallocate($image, 0, 0, 0);
        $alpha = intval((100 - $strength) * 1.27);
        $mask_color = imagecolorallocatealpha($image, 0, 0, 0, $alpha);
        
        // Draw semi-transparent black rectangle
        imagefilledrectangle(
            $image,
            $region['min_x'], $region['min_y'],
            $region['max_x'], $region['max_y'],
            $mask_color
        );
    }
    
    /**
     * Outline a detected face region
     *
     * @param resource $image Target image
     * @param array $region Face region
     */
    private function outline_region($image, array $region): void
    {
        // Validate region has required keys
        if (!isset($region['min_x'], $region['min_y'], $region['max_x'], $region['max_y'])) {
            return; // Skip invalid regions
        }
        
        $red = imagecolorallocate($image, 255, 0, 0);
        
        // Draw rectangle outline
        imagerectangle(
            $image,
            $region['min_x'], $region['min_y'],
            $region['max_x'], $region['max_y'],
            $red
        );
    }
    
    /**
     * Get averaged color around a pixel
     *
     * @param resource $image Source image
     * @param int $x Center X coordinate
     * @param int $y Center Y coordinate
     * @param int $radius Sampling radius
     * @return int Averaged color
     */
    private function get_averaged_color($image, int $x, int $y, int $radius): int
    {
        $total_r = 0;
        $total_g = 0;
        $total_b = 0;
        $count = 0;
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        for ($dx = -$radius; $dx <= $radius; $dx++) {
            for ($dy = -$radius; $dy <= $radius; $dy++) {
                $sample_x = $x + $dx;
                $sample_y = $y + $dy;
                
                if ($sample_x >= 0 && $sample_y >= 0 && $sample_x < $width && $sample_y < $height) {
                    $color = imagecolorat($image, $sample_x, $sample_y);
                    $total_r += ($color >> 16) & 0xFF;
                    $total_g += ($color >> 8) & 0xFF;
                    $total_b += $color & 0xFF;
                    $count++;
                }
            }
        }
        
        if ($count == 0) return imagecolorat($image, $x, $y);
        
        $avg_r = intval($total_r / $count);
        $avg_g = intval($total_g / $count);
        $avg_b = intval($total_b / $count);
        
        return imagecolorallocate($image, $avg_r, $avg_g, $avg_b);
    }
}
