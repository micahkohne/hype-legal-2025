<?php

/**
 * JCOGS Image Pro - Crop Filter
 * =============================
 * Phase 2: Native EE7 implementation pipeline architecture
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

namespace JCOGSDesign\JCOGSImagePro\Filters;

use Imagine\Image\ImageInterface;
use Imagine\Image\Box;
use Imagine\Image\Point;
use JCOGSDesign\JCOGSImagePro\Service\Pipeline\Context;

/**
 * Crop Transformation Filter
 * 
 * Applies crop transformations to images with support for:
 * - Position-based cropping (center, left, right, top, bottom)
 * - Face detection cropping
 * - Smart scaling with crop
 * - Offset adjustments
 * - Legacy parameter compatibility
 */
class Crop extends ImageAbstractFilter
{
    private Box $target_size;
    private Context $context;

    /**
     * Constructor
     * 
     * @param Box $target_size Target crop dimensions
     * @param Context $context Processing context with crop parameters
     */
    public function __construct(Box $target_size, Context $context)
    {
        parent::__construct(); // Initialize shared services
        $this->target_size = $target_size;
        $this->context = $context;
        
    }

    /**
     * Apply crop transformation to image
     * 
     * @param ImageInterface $image Source image
     * @return ImageInterface Cropped image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // For crop operations, we need to calculate the correct target dimensions
        // EXCEPT for face detection mode, which should ignore width/height parameters
        $crop_params = $this->context->get_metadata_value('crop_parsed_params', null);
        $is_face_detect_mode = $crop_params && $crop_params['mode'] === 'f';
        
        $target_size = $this->target_size;
        if (!$is_face_detect_mode && $target_size->getWidth() == 100 && $target_size->getHeight() == 100) {
            // This was a placeholder - calculate the real target dimensions for crop
            $target_size = $this->calculate_crop_target_dimensions();
        }

        if ($this->utilities_service) {
            $this->utilities_service->debug_message(sprintf(
                'Applying crop transformation to %dx%d',
                $target_size->getWidth(),
                $target_size->getHeight()
            ), null, false, 'detailed');

            // User-friendly debug message
            $crop_param = $this->context->get_param('crop', 'no');
            $crop_display = is_array($crop_param) ? implode(', ', $crop_param) : (string)$crop_param;
            $this->utilities_service->debug_message("Cropping image with parameters: {$crop_display}");
        }

        try {
            // Smart-scale processing has been moved to pipeline stage before face detection
            // for Legacy compatibility and performance optimization
            
            // Check if preliminary smart-scale has already been applied
            $preliminary_smart_scale_applied = $this->context->get_flag('preliminary_smart_scale_applied', false);
            if ($preliminary_smart_scale_applied) {
                if ($this->utilities_service) {
                    $this->utilities_service->debug_message("Crop filter: Using image that has been preliminary smart-scaled");
                }
            }
            
            // Check if face detection is needed
            $needs_face_detection = false;
            $face_detection_service = null;
            $face_regions = [];
            
            if ($crop_params) {
                $position = $crop_params['position'] ?? ['center', 'center'];
                $needs_face_detection = in_array('face_detect', $position) || $crop_params['mode'] === 'f';
            }

            // Handle face detection if needed
            if ($needs_face_detection) {
                $face_regions = $this->detect_faces_if_needed($image);
            }

            // Calculate crop position
            $current_size = $image->getSize();
            $crop_position = $this->calculate_crop_position($current_size, $target_size);

            if ($this->utilities_service) {
                $this->utilities_service->debug_message(sprintf(
                    'Cropping image from position %d,%d with size %dx%d',
                    $crop_position->getX(),
                    $crop_position->getY(),
                    $target_size->getWidth(),
                    $target_size->getHeight()
                ));
            }

            // Apply the crop
            return $image->crop($crop_position, $target_size);

        } catch (\Exception $e) {
            if ($this->utilities_service) {
                $this->utilities_service->debug_message("Crop transformation failed: " . $e->getMessage());
            }
            throw new \Exception("Failed to crop image: " . $e->getMessage());
        }
    }

    /**
     * Calculate crop target dimensions
     * 
     * @return Box Target crop dimensions
     */
    private function calculate_crop_target_dimensions(): Box
    {
        // Get width and height parameters
        $width = $this->context->get_param('width');
        $height = $this->context->get_param('height');

        // Convert to integers
        $target_width = $width ? (int)$width : 100;
        $target_height = $height ? (int)$height : 100;

        return new Box(max(1, $target_width), max(1, $target_height));
    }

    /**
     * Detect faces if needed for crop positioning
     * 
     * @param ImageInterface $image Source image
     * @return array Face regions data
     */
    private function detect_faces_if_needed(ImageInterface $image): array
    {
        // Get existing face regions from context if available
        $face_regions = $this->context->get_metadata_value('face_regions', []);
        
        // If no face regions cached, detect them now
        if (empty($face_regions)) {
            if ($this->utilities_service) {
                $this->utilities_service->debug_message('Detecting faces for crop positioning');
            }
            
            try {
                $face_detection_service = \JCOGSDesign\JCOGSImagePro\Service\ServiceCache::face_detection();
                
                // Get GD resource from Imagine object for face detection
                $gd_resource = imagecreatefromstring($image->__toString());
                if ($gd_resource) {
                    $face_regions = $face_detection_service->detect_faces($gd_resource);
                    
                    // Cache face regions in context for reuse by other operations
                    // Store both the face regions AND the image dimensions when detected
                    $this->context->set_metadata('face_regions', $face_regions);
                    $this->context->set_metadata('face_regions_image_size', [
                        'width' => imagesx($gd_resource),
                        'height' => imagesy($gd_resource)
                    ]);
                    
                    // Also store in a global context for cross-operation sharing
                    $this->context->set_metadata('original_face_regions', $face_regions);
                    $this->context->set_metadata('original_image_size', [
                        'width' => imagesx($gd_resource),
                        'height' => imagesy($gd_resource)
                    ]);
                    
                    if ($this->utilities_service) {
                        $this->utilities_service->debug_message(sprintf(
                            'Stored face regions in context: %d faces at %dx%d',
                            count($face_regions),
                            imagesx($gd_resource),
                            imagesy($gd_resource)
                        ));
                    }

                    if ($this->utilities_service) {
                        $this->utilities_service->debug_message(sprintf('Detected %d faces', count($face_regions)));
                    }
                }
            } catch (\Exception $e) {
                if ($this->utilities_service) {
                    $this->utilities_service->debug_message('Face detection failed: ' . $e->getMessage());
                }
                $face_regions = [];
            }
        }
        
        return $face_regions;
    }

    /**
     * Calculate crop position based on parsed crop parameters
     * 
     * @param Box $current_size Current image size
     * @param Box $target_size Target crop size
     * @return Point Crop starting point
     */
    private function calculate_crop_position(Box $current_size, Box $target_size): Point
    {
        // Get parsed crop parameters, or use defaults if not available
        $crop_params = $this->context->get_metadata_value('crop_parsed_params', [
            'position' => ['center', 'center'],
            'offset' => [0, 0]
        ]);
        
        $position = $crop_params['position'] ?? ['center', 'center'];
        $offset = $crop_params['offset'] ?? [0, 0];
        
        // Check if face detection is needed for positioning
        $needs_face_detection = in_array('face_detect', $position);
        $face_centroid = null;
        
        if ($needs_face_detection) {
            $face_regions = $this->context->get_metadata_value('face_regions', []);
            
            // Get face centroid if faces were detected
            if (!empty($face_regions)) {
                $face_detection_service = \JCOGSDesign\JCOGSImagePro\Service\ServiceCache::face_detection();
                $face_centroid = $face_detection_service->get_centroid($face_regions);
                
                if ($this->utilities_service) {
                    $this->utilities_service->debug_message(sprintf(
                        'Face centroid calculated at (%d, %d)',
                        $face_centroid['x'] ?? 0,
                        $face_centroid['y'] ?? 0
                    ));
                }
            } else {
                if ($this->utilities_service) {
                    $this->utilities_service->debug_message('No faces detected for positioning, falling back to center');
                }
            }
        }
        
        $current_width = $current_size->getWidth();
        $current_height = $current_size->getHeight();
        $target_width = $target_size->getWidth();
        $target_height = $target_size->getHeight();
        
        // Calculate base position depending on alignment, with face detection support
        $crop_x = $this->calculate_position_coordinate_with_faces(
            $position[0], $current_width, $target_width, 
            $face_centroid ? $face_centroid['x'] : null
        );
        $crop_y = $this->calculate_position_coordinate_with_faces(
            $position[1], $current_height, $target_height, 
            $face_centroid ? $face_centroid['y'] : null
        );
        
        // Apply offset adjustments
        $crop_x += $offset[0];
        $crop_y += $offset[1];
        
        // Ensure crop position stays within image boundaries
        $crop_x = max(0, min($crop_x, $current_width - $target_width));
        $crop_y = max(0, min($crop_y, $current_height - $target_height));

        if ($this->utilities_service) {
            $this->utilities_service->debug_message(sprintf(
                'Crop position calculation: %s,%s with offset %d,%d = final position %d,%d',
                $position[0], $position[1], $offset[0], $offset[1], $crop_x, $crop_y
            ));
        }
        
        return new Point($crop_x, $crop_y);
    }

    /**
     * Calculate position coordinate for crop alignment with face detection support
     * 
     * @param string $alignment 'left'/'top', 'center', 'right'/'bottom', 'face_detect'
     * @param int $current_dimension Current image dimension (width or height)
     * @param int $target_dimension Target crop dimension
     * @param int|null $face_coordinate Face centroid coordinate (if available)
     * @return int Position coordinate
     */
    private function calculate_position_coordinate_with_faces(string $alignment, int $current_dimension, int $target_dimension, ?int $face_coordinate): int
    {
        if ($alignment === 'face_detect' && $face_coordinate !== null) {
            // Center the crop on the face coordinate
            $position = $face_coordinate - (int)round($target_dimension / 2);
            // Ensure it stays within image bounds
            return max(0, min($position, $current_dimension - $target_dimension));
        } else if ($alignment === 'face_detect') {
            // Fall back to center if no face coordinate available
            return (int)round(($current_dimension - $target_dimension) / 2);
        } else {
            // Use regular position calculation
            return $this->calculate_position_coordinate($alignment, $current_dimension, $target_dimension);
        }
    }

    /**
     * Calculate position coordinate for crop alignment
     * 
     * @param string $alignment 'left'/'top', 'center', 'right'/'bottom'
     * @param int $current_dimension Current image dimension (width or height)
     * @param int $target_dimension Target crop dimension
     * @return int Position coordinate
     */
    private function calculate_position_coordinate(string $alignment, int $current_dimension, int $target_dimension): int
    {
        switch (strtolower($alignment)) {
            case 'left':
            case 'top':
                return 0;
                
            case 'right':
            case 'bottom':
                return $current_dimension - $target_dimension;
                
            case 'center':
            default:
                return (int)round(($current_dimension - $target_dimension) / 2);
        }
    }
}