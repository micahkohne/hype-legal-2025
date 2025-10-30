<?php

/**
 * JCOGS Image Pro - Face Detection Service
 * Phase 2: Native EE7 implementation with HAARPHP integration
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

namespace JCOGSDesign\JCOGSImagePro\Service;

use JCOGSDesign\JCOGSImagePro\Library\HaarDetector;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

/**
 * Face Detection Service
 * 
 * Provides face detection capabilities for both crop and filter operations.
 * Uses HAARPHP library with fallback to skin tone detection.
 * Designed to be easily replaceable with OpenCV in future updates.
 */
class FaceDetectionService
{
    private Utilities $utilities_service;
    
    /**
     * Cross-sensitivity face detection cache
     * Structure: [image_hash => ['face_regions' => array, 'max_sensitivity' => int]]
     */
    private static array $face_detection_cache = [];
    
    /**
     * Haar cascade data cache (singleton pattern)
     */
    private static ?array $haar_cascade_data = null;

    public function __construct()
    {
        $this->utilities_service = ServiceCache::utilities();
    }
    
    /**
     * Detect faces in image and return face regions
     * 
     * Uses cross-sensitivity caching: runs detection once at highest sensitivity
     * and filters results for lower sensitivities to avoid redundant HAAR runs.
     *
     * @param resource $image The source image (GD resource)
     * @param int|string $sensitivity Detection sensitivity (1-9, legacy compatible)
     * @return array Array of detected face regions with bounding boxes
     */
    public function detect_faces($image, $sensitivity = 3): array
    {
        // Cast sensitivity to int for type safety
        $sensitivity = (int)$sensitivity;
        
        $this->utilities_service->debug_message('Starting face detection with sensitivity: ' . $sensitivity);
        
        if ($image === null || (!is_resource($image) && !($image instanceof \GdImage))) {
            $this->utilities_service->debug_message('Invalid image resource provided');
            return [];
        }

        // Generate cache key from image data
        $cache_key = $this->_generate_image_cache_key($image);
        
        // Check if we have cached results
        if (isset(self::$face_detection_cache[$cache_key])) {
            $cached_data = self::$face_detection_cache[$cache_key];
            
            // If cached sensitivity >= requested sensitivity, we can filter the results
            if ($cached_data['max_sensitivity'] >= $sensitivity) {
                $this->utilities_service->debug_message("Using cached face detection results (cached sensitivity: {$cached_data['max_sensitivity']}, requested: {$sensitivity})");
                
                // Filter face regions based on requested sensitivity
                $filtered_regions = $this->_filter_face_regions_by_sensitivity(
                    $cached_data['face_regions'], 
                    $cached_data['max_sensitivity'], 
                    $sensitivity
                );
                
                $this->utilities_service->debug_message('Filtered cached results: ' . count($filtered_regions) . ' faces');
                return $filtered_regions;
            }
        }
        
        // No suitable cached results - run detection at maximum sensitivity to cache for future use
        $detection_sensitivity = max($sensitivity, 7); // Use high sensitivity for comprehensive caching
        
        $this->utilities_service->debug_message("Running face detection at sensitivity {$detection_sensitivity} (requested: {$sensitivity}) for comprehensive caching");

        // Try HAAR cascade detection first
        $face_regions = $this->_detect_faces_haar($image, $detection_sensitivity);
        
        $this->utilities_service->debug_message('HAAR detection found: ' . count($face_regions) . ' faces');
        
        // If HAAR fails or finds no faces, try fallback detection
        if (empty($face_regions)) {
            $this->utilities_service->debug_message('HAAR failed, trying fallback detection');
            $face_regions = $this->_detect_faces_fallback($image);
            
            $this->utilities_service->debug_message('Fallback detection found: ' . count($face_regions) . ' faces');
        }
        
        // Cache the results with the actual detection sensitivity used
        self::$face_detection_cache[$cache_key] = [
            'face_regions' => $face_regions,
            'max_sensitivity' => $detection_sensitivity
        ];
        
        // Filter results to match requested sensitivity
        $filtered_regions = $this->_filter_face_regions_by_sensitivity(
            $face_regions, 
            $detection_sensitivity, 
            $sensitivity
        );
        
        $this->utilities_service->debug_message('Detection complete, returning ' . count($filtered_regions) . ' faces for sensitivity ' . $sensitivity);
        
        return $filtered_regions;
    }
    
    /**
     * Get bounding box encompassing all detected faces with optional margin
     *
     * @param array $face_regions Array of face regions from detect_faces()
     * @param int $margin Margin to add around bounding box in pixels
     * @return array|null Bounding box coordinates or null if no faces
     */
    public function get_bounding_box(array $face_regions, int $margin = 0): ?array
    {
        if (empty($face_regions)) {
            return null;
        }
        
        // Find overall bounding box of all faces
        $min_x = PHP_INT_MAX;
        $min_y = PHP_INT_MAX;
        $max_x = PHP_INT_MIN;
        $max_y = PHP_INT_MIN;
        
        foreach ($face_regions as $region) {
            $min_x = min($min_x, $region['min_x']);
            $min_y = min($min_y, $region['min_y']);
            $max_x = max($max_x, $region['max_x']);
            $max_y = max($max_y, $region['max_y']);
        }
        
        // Apply margin
        $min_x = max(0, $min_x - $margin);
        $min_y = max(0, $min_y - $margin);
        $max_x = $max_x + $margin;
        $max_y = $max_y + $margin;
        
        return [
            'min_x' => $min_x,
            'min_y' => $min_y,
            'max_x' => $max_x,
            'max_y' => $max_y,
            'width' => $max_x - $min_x,
            'height' => $max_y - $min_y
        ];
    }
    
    /**
     * Get centroid of all detected faces
     *
     * @param array $face_regions Array of face regions from detect_faces()
     * @return array|null Centroid coordinates or null if no faces
     */
    public function get_centroid(array $face_regions): ?array
    {
        if (empty($face_regions)) {
            return null;
        }
        
        $total_x = 0;
        $total_y = 0;
        $count = 0;
        
        foreach ($face_regions as $region) {
            $center_x = ($region['min_x'] + $region['max_x']) / 2;
            $center_y = ($region['min_y'] + $region['max_y']) / 2;
            $total_x += $center_x;
            $total_y += $center_y;
            $count++;
        }
        
        return [
            'x' => intval($total_x / $count),
            'y' => intval($total_y / $count)
        ];
    }
    
    /**
     * Fallback face detection using basic skin tone analysis (from existing filter)
     *
     * @param resource $image The source image
     * @return array Array of detected face regions
     */
    private function _detect_faces_fallback($image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $regions = [];
        
        // Simple skin tone detection
        $skin_regions = $this->_detect_skin_tones($image);
        
        // Group skin regions into potential faces
        $face_candidates = $this->_group_skin_regions($skin_regions, $width, $height);
        
        // Filter candidates by size and shape
        foreach ($face_candidates as $candidate) {
            if ($this->_is_potential_face($candidate)) {
                $regions[] = $candidate;
            }
        }
        
        return $regions;
    }
    
    /**
     * Detect faces using HAARPHP library (primary method, based on legacy)
     *
     * @param resource $image The source image
     * @param int $sensitivity Sensitivity level (1-9, legacy compatible)
     * @return array Array of detected face regions
     */
    private function _detect_faces_haar($image, int $sensitivity): array
    {
        try {
            // Load Haar cascade data (singleton pattern for performance)
            if (self::$haar_cascade_data === null) {
                require __DIR__ . '/../Library/haarcascade_frontalface_alt.php';
                
                if (!isset($haarcascade_frontalface_alt) || !is_array($haarcascade_frontalface_alt)) {
                    throw new \Exception('Invalid Haar cascade data');
                }
                
                self::$haar_cascade_data = $haarcascade_frontalface_alt;
                $this->utilities_service->debug_message('Loaded Haar cascade data (cached for reuse)');
            } else {
                $this->utilities_service->debug_message('Using cached Haar cascade data');
            }
            
            // Convert legacy sensitivity (1-9) to scale factor
            // Use exact Legacy formula for performance compatibility
            $sensitivity = min(max(1, $sensitivity), 9); // Normalise value to range 1-9
            $sensitivity = ($sensitivity - 3); // 3 == 0
            $scale = (5 + $sensitivity) / 15; // Normalise based on $sensitivity 1 => 20%, 9 => 93%.
            
            // Create face detector with cached cascade data
            $face_detector = new HaarDetector(self::$haar_cascade_data);
            
            // Detect faces with legacy-compatible parameters
            $found = $face_detector
                ->image($image, $scale)
                ->cannyThreshold(['low' => 80, 'high' => 200])
                ->detect(1, 1.1, 0.12, 1, 0.2, false);
            
            $face_regions = [];
            
            if ($found && $face_detector->objects) {
                foreach ($face_detector->objects as $face) {
                    $face_regions[] = [
                        'min_x' => $face->x,
                        'min_y' => $face->y,
                        'max_x' => $face->x + $face->width,
                        'max_y' => $face->y + $face->height,
                        'width' => $face->width,
                        'height' => $face->height
                    ];
                }
            }
            
            return $face_regions;
            
        } catch (\Exception $e) {
            // Fall back to simple skin tone detection if HAAR fails
            return [];
        }
    }
    
    /**
     * Detect skin tone regions in the image
     *
     * @param resource $image Source image
     * @return array Array of skin tone pixels
     */
    private function _detect_skin_tones($image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $skin_pixels = [];
        
        for ($x = 0; $x < $width; $x += 2) { // Sample every other pixel for performance
            for ($y = 0; $y < $height; $y += 2) {
                $color = imagecolorat($image, $x, $y);
                $r = ($color >> 16) & 0xFF;
                $g = ($color >> 8) & 0xFF;
                $b = $color & 0xFF;
                
                if ($this->_is_skin_tone($r, $g, $b)) {
                    $skin_pixels[] = ['x' => $x, 'y' => $y];
                }
            }
        }
        
        return $skin_pixels;
    }
    
    /**
     * Group skin pixels into potential face regions
     *
     * @param array $skin_pixels Array of skin tone pixels
     * @param int $width Image width
     * @param int $height Image height
     * @return array Array of face candidate regions
     */
    private function _group_skin_regions(array $skin_pixels, int $width, int $height): array
    {
        if (empty($skin_pixels)) {
            return [];
        }
        
        // Simple clustering by proximity
        $clusters = [];
        $cluster_distance = 30; // Maximum distance to be in same cluster
        
        foreach ($skin_pixels as $pixel) {
            $added_to_cluster = false;
            
            foreach ($clusters as &$cluster) {
                // Check if pixel is close to any pixel in this cluster
                foreach ($cluster['pixels'] as $cluster_pixel) {
                    $distance = sqrt(
                        pow($pixel['x'] - $cluster_pixel['x'], 2) +
                        pow($pixel['y'] - $cluster_pixel['y'], 2)
                    );
                    
                    if ($distance <= $cluster_distance) {
                        $cluster['pixels'][] = $pixel;
                        $this->_update_cluster_bounds($cluster, $pixel);
                        $added_to_cluster = true;
                        break 2;
                    }
                }
            }
            
            if (!$added_to_cluster) {
                // Create new cluster
                $clusters[] = [
                    'pixels' => [$pixel],
                    'min_x' => $pixel['x'],
                    'max_x' => $pixel['x'],
                    'min_y' => $pixel['y'],
                    'max_y' => $pixel['y']
                ];
            }
        }
        
        return $clusters;
    }
    
    /**
     * Check if a cluster could be a face
     *
     * @param array $cluster Cluster to evaluate
     * @return bool True if potential face
     */
    private function _is_potential_face(array $cluster): bool
    {
        $width = $cluster['max_x'] - $cluster['min_x'];
        $height = $cluster['max_y'] - $cluster['min_y'];
        
        // Filter by size (faces should be reasonably sized)
        if ($width < 20 || $height < 20) return false;
        if ($width > 200 || $height > 200) return false;
        
        // Filter by aspect ratio (faces are roughly oval)
        $ratio = $height / $width;
        if ($ratio < 0.8 || $ratio > 1.5) return false;
        
        // Filter by pixel density (should have enough skin pixels)
        $area = $width * $height;
        $pixel_density = count($cluster['pixels']) / $area;
        if ($pixel_density < 0.1) return false;
        
        return true;
    }
    
    /**
     * Check if RGB values represent a skin tone
     *
     * @param int $r Red value
     * @param int $g Green value
     * @param int $b Blue value
     * @return bool True if likely skin tone
     */
    private function _is_skin_tone(int $r, int $g, int $b): bool
    {
        // Simple skin tone detection based on common ranges
        // This is a basic approach - real implementations use HSV or YCbCr
        
        // Rule 1: R > G > B generally for skin tones
        if ($r <= $g || $g <= $b) {
            return false;
        }
        
        // Rule 2: Check ranges for typical skin tones
        if ($r < 95 || $r > 255) return false;
        if ($g < 40 || $g > 220) return false;
        if ($b < 20 || $b > 170) return false;
        
        // Rule 3: Check ratios
        if (($r - $g) < 15) return false;
        if (($g - $b) < 5) return false;
        
        return true;
    }
    
    /**
     * Update cluster boundaries with new pixel
     *
     * @param array &$cluster Cluster to update
     * @param array $pixel New pixel coordinates
     */
    private function _update_cluster_bounds(array &$cluster, array $pixel): void
    {
        $cluster['min_x'] = min($cluster['min_x'], $pixel['x']);
        $cluster['max_x'] = max($cluster['max_x'], $pixel['x']);
        $cluster['min_y'] = min($cluster['min_y'], $pixel['y']);
        $cluster['max_y'] = max($cluster['max_y'], $pixel['y']);
    }
    
    /**
     * Generate cache key from image data for cross-sensitivity caching
     *
     * @param resource $image GD resource
     * @return string Cache key based on image content
     */
    private function _generate_image_cache_key($image): string
    {
        // Get image dimensions for key
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Create a lightweight hash from image data
        // Sample a few pixels for performance (corners + center)
        $sample_points = [
            [0, 0], [$width-1, 0], [0, $height-1], [$width-1, $height-1], // corners
            [intval($width/2), intval($height/2)] // center
        ];
        
        $sample_data = '';
        foreach ($sample_points as [$x, $y]) {
            $color = imagecolorat($image, $x, $y);
            $sample_data .= dechex($color);
        }
        
        // Include dimensions and sample data in hash
        return 'face_cache_' . md5($width . 'x' . $height . '_' . $sample_data);
    }
    
    /**
     * Filter face regions based on sensitivity difference
     * 
     * Lower sensitivity should show fewer/more confident faces.
     * This simulates running detection at lower sensitivity by filtering results.
     *
     * @param array $face_regions Face regions from high-sensitivity detection
     * @param int $cached_sensitivity The sensitivity used for cached detection
     * @param int $requested_sensitivity The requested sensitivity level
     * @return array Filtered face regions
     */
    private function _filter_face_regions_by_sensitivity(array $face_regions, int $cached_sensitivity, int $requested_sensitivity): array
    {
        // If requested sensitivity is higher or equal, return all faces
        if ($requested_sensitivity >= $cached_sensitivity) {
            return $face_regions;
        }
        
        // Lower sensitivity = more conservative = fewer faces
        // Filter based on face size (larger faces = more confident detection)
        $sensitivity_factor = $requested_sensitivity / $cached_sensitivity;
        
        $filtered_regions = [];
        foreach ($face_regions as $region) {
            $face_area = $region['width'] * $region['height'];
            
            // Keep larger faces for lower sensitivity (more conservative)
            // Minimum area threshold increases as sensitivity decreases
            $min_area_threshold = 2000 * (1 - $sensitivity_factor); // Adjust multiplier as needed
            
            if ($face_area >= $min_area_threshold) {
                $filtered_regions[] = $region;
            }
        }
        
        $this->utilities_service->debug_message(
            "Sensitivity filtering: {$cached_sensitivity} → {$requested_sensitivity}, " . 
            count($face_regions) . " → " . count($filtered_regions) . " faces"
        );
        
        return $filtered_regions;
    }
}
