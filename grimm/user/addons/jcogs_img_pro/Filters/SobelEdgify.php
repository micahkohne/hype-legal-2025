<?php

/**
 * JCOGS Image Pro - Sobel Edge Detection Filter
 * =============================================
 * Advanced edge detection filter using Sobel algorithm
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
 * Sobel Edge Detection Filter
 * 
 * Applies Sobel edge detection algorithm to highlight edges in the image.
 * Creates stylized edge-only representations with customizable effects.
 */
class SobelEdgify implements FilterInterface
{
    private string $library = 'gd';
    private int $threshold;
    private string $mode;
    
    public function __construct(int $threshold = 125, string $mode = 'edges')
    {
        $this->library = 'gd';
        $this->threshold = $threshold;
        $this->mode = $mode;
    }
    
    /**
     * Apply Sobel edge detection filter to image
     *
     * @param ImageInterface $image The image data
     * @return ImageInterface The processed image data
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Use stored parameters from constructor
        $threshold = $this->threshold;
        $mode = $this->mode;
        
        // Process parameters
        $processed_params = $this->process_sobel_parameters($threshold, $mode);
        
        // Apply filter based on detected library
        switch ($this->library) {
            case 'gd':
                $gd_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Gd\SobelEdgify();
                $result = $gd_filter->apply($image, $processed_params);
                
                // Convert result back to Imagine object for pipeline consistency
                if (is_string($result)) {
                    $imagine = new \Imagine\Gd\Imagine();
                    return $imagine->load($result);
                }
                return $image; // Fallback to original image
            
            case 'imagick':
                // Future Imagick implementation
                throw new \Exception('Imagick support for Sobel edge detection not yet implemented');
            
            default:
                throw new \Exception('Unsupported image library: ' . $this->library);
        }
    }
    
    /**
     * Process and validate Sobel edge detection parameters
     *
     * @param int $threshold Edge detection threshold (0-100)
     * @param string $mode Processing mode (edges, enhance, combine)
     * @return array Processed parameters
     */
    private function process_sobel_parameters(int $threshold, string $mode): array
    {
        // Clamp threshold to valid range
        $threshold = max(0, min(100, $threshold));
        
        // Validate mode
        $valid_modes = ['edges', 'enhance', 'combine'];
        if (!in_array($mode, $valid_modes)) {
            $mode = 'edges';
        }
        
        return [
            'threshold' => $threshold,
            'mode' => $mode
        ];
    }
}
