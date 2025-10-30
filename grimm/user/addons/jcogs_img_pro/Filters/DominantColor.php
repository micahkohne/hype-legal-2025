<?php

/**
 * JCOGS Image Pro - Dominant Color Filter
 * =======================================
 * Color analysis and dominant color extraction filter
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
 * Dominant Color Filter
 * 
 * Analyzes image to find dominant color and applies color effects based on it.
 * Can extract dominant color or apply dominant color overlay effects.
 */
class DominantColor implements FilterInterface
{
    private string $library = 'gd';
    
    public function __construct()
    {
        $this->library = 'gd';
    }
    
    /**
     * Apply dominant color filter to image
     *
     * @param ImageInterface $image The image data
     * @return ImageInterface The processed image data
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Default parameters for dominant color extraction
        $mode = 'extract';
        $strength = 100; // Full strength for extract mode
        
        // Process parameters
        $processed_params = $this->process_dominant_color_parameters($mode, $strength);
        
        // Apply filter based on detected library
        switch ($this->library) {
            case 'gd':
                $gd_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Gd\DominantColor();
                $result = $gd_filter->apply($image, $processed_params);
                
                // Convert result back to Imagine object for pipeline consistency
                if (is_string($result)) {
                    $imagine = new \Imagine\Gd\Imagine();
                    return $imagine->load($result);
                }
                return $image; // Fallback to original image
            
            case 'imagick':
                // Future Imagick implementation
                throw new \Exception('Imagick support for dominant color not yet implemented');
            
            default:
                throw new \Exception('Unsupported image library: ' . $this->library);
        }
    }
    
    /**
     * Process and validate dominant color parameters
     *
     * @param string $mode Effect mode (overlay, extract, tint)
     * @param int $strength Effect strength (0-100)
     * @return array Processed parameters
     */
    private function process_dominant_color_parameters(string $mode, int $strength): array
    {
        // Validate mode
        $valid_modes = ['overlay', 'extract', 'tint'];
        if (!in_array($mode, $valid_modes)) {
            $mode = 'overlay';
        }
        
        // Clamp strength to valid range
        $strength = max(0, min(100, $strength));
        
        return [
            'mode' => $mode,
            'strength' => $strength
        ];
    }
}
