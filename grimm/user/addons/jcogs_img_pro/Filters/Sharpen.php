<?php

/**
 * JCOGS Image Pro - Sharpen Filter
 * ================================
 * Manual sharpening filter with configurable intensity control
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
 * Sharpen Filter
 * 
 * Applies sharpening effects to images with configurable intensity.
 * Different from auto-sharpen as it uses manual intensity control.
 */
class Sharpen implements FilterInterface
{
    /**
     * @var int Sharpening amount (0-500)
     */
    private $amount;
    
    /**
     * @var float Radius
     */
    private $radius;
    
    /**
     * @var int Threshold (0-255)
     */
    private $threshold;
    
    private string $library = 'gd';
    
    /**
     * Constructs Sharpen filter.
     * 
     * @param int $amount Sharpening amount (0-500)
     * @param float $radius Radius
     * @param int $threshold Threshold (0-255)
     */
    public function __construct(int $amount = 80, float $radius = 0.5, int $threshold = 3)
    {
        $this->amount = $amount;
        $this->radius = $radius;
        $this->threshold = $threshold;
        $this->library = 'gd';
    }
    
    /**
     * Apply sharpen filter to image using legacy Unsharp_mask algorithm
     *
     * @param ImageInterface $image The image
     * @return ImageInterface The processed image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Use parameters from constructor
        $processed_params = $this->process_sharpen_parameters($this->amount, $this->radius, $this->threshold);
        
        // Apply filter based on detected library
        switch ($this->library) {
            case 'gd':
                $gd_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Gd\Sharpen();
                return $gd_filter->apply($image, $processed_params);
            
            case 'imagick':
                // Future Imagick implementation
                throw new \Exception('Imagick support for sharpen not yet implemented');
            
            default:
                throw new \Exception('Unsupported image library: ' . $this->library);
        }
    }
    
    /**
     * Process and validate sharpen parameters for legacy Unsharp Mask
     *
     * @param int $sharpening_value Sharpening amount (0-500)
     * @param float $radius Blur radius (0.1-10.0)
     * @param int $threshold Threshold (0-255)
     * @return array Processed parameters
     */
    private function process_sharpen_parameters(int $sharpening_value, float $radius, int $threshold): array
    {
        // Use legacy parameter validation and processing
        // Legacy clamps sharpening_value to 500 and applies 0.016 calibration
        $sharpening_value = max(0, min(500, $sharpening_value));
        
        // Legacy clamps radius to 50 and applies * 2 calibration
        $radius = max(0.1, min(50.0, $radius));
        
        // Legacy clamps threshold to 255
        $threshold = max(0, min(255, $threshold));
        
        return [
            'sharpening_value' => $sharpening_value,
            'radius' => $radius,
            'threshold' => $threshold
        ];
    }
}
