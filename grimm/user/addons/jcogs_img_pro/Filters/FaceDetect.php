<?php

/**
 * JCOGS Image Pro - Face Detection Filter
 * =======================================
 * Face detection and face-based effects filter
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

namespace JCOGSDesign\JCOGSImagePro\Filters;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

/**
 * Face Detection Filter
 * 
 * Detects faces in the image and can apply various effects based on detected faces.
 * Uses OpenCV or similar face detection libraries.
 */
class FaceDetect implements FilterInterface
{
    private string $library = 'gd';
    private string $action = 'highlight';
    private int $strength = 50;
    private ?array $cached_face_regions = null;
    
    public function __construct(string $action = 'highlight', int $strength = 50, ?array $cached_face_regions = null)
    {
        $this->library = 'gd';
        $this->action = $action;
        $this->strength = $strength;
        $this->cached_face_regions = $cached_face_regions;
    }
    
    /**
     * Apply face detection filter to image
     *
     * @param ImageInterface $image The image data
     * @return ImageInterface The processed image data
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Use stored parameters from constructor
        $action = $this->action;
        $strength = $this->strength;
        
        // Process parameters and include cached face regions if available
        $processed_params = $this->process_face_detect_parameters($action, $strength);
        if ($this->cached_face_regions !== null) {
            $processed_params['cached_face_regions'] = $this->cached_face_regions;
        }
        
        // Apply filter based on detected library
        switch ($this->library) {
            case 'gd':
                $gd_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Gd\FaceDetect();
                $result = $gd_filter->apply($image, $processed_params);
                
                // Convert result back to Imagine object for pipeline consistency
                if (is_string($result)) {
                    $imagine = new \Imagine\Gd\Imagine();
                    return $imagine->load($result);
                }
                return $image; // Fallback to original image
            
            case 'imagick':
                // Future Imagick implementation
                throw new \Exception('Imagick support for face detection not yet implemented');
            
            default:
                throw new \Exception('Unsupported image library: ' . $this->library);
        }
    }
    
    /**
     * Process and validate face detection parameters
     *
     * @param string $action Detection action (highlight, blur, mask, detect)
     * @param int $strength Effect strength (0-100)
     * @return array Processed parameters
     */
    private function process_face_detect_parameters(string $action, int $strength): array
    {
        // Validate action
        $valid_actions = ['highlight', 'blur', 'mask', 'detect'];
        if (!in_array($action, $valid_actions)) {
            $action = 'highlight';
        }
        
        // Clamp strength to valid range
        $strength = max(0, min(100, $strength));
        
        return [
            'action' => $action,
            'strength' => $strength
        ];
    }
}
