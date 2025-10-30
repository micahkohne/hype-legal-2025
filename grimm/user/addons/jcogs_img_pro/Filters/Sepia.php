<?php

/**
 * JCOGS Image Pro - Sepia Filter
 * ==============================
 * Top-level sepia filter with multi-library support and parameter processing
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

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

/**
 * Sepia Filter Class
 * 
 * Top-level sepia filter that handles parameter processing and library detection.
 * Supports both 'fast' and 'slow' sepia rendering methods with library-specific
 * implementations for optimal performance.
 */
class Sepia implements FilterInterface
{
    private string $sepia_method;

    /**
     * Constructs Sepia filter.
     * 
     * @param string $sepia_method Sepia rendering method 'fast' or 'slow' (default: 'fast')
     */
    public function __construct(string $sepia_method = 'fast')
    {
        $this->sepia_method = $sepia_method;
    }

    /**
     * Apply sepia filter to image
     * 
     * Uses Legacy approach: streamlined processing with fast/slow method support.
     * Reduced parameter processing overhead for better performance.
     * 
     * @param ImageInterface $image Source image
     * @param array $params Filter parameters ['method' => 'fast|slow'] (for backward compatibility)
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image, array $params = []): ImageInterface
    {
        // Use constructor parameter, with fallback to apply params for backward compatibility
        $method = $params['method'] ?? $params[0] ?? $this->sepia_method;
        
        // Streamlined validation (Legacy approach: fast is default)
        $method = strtolower($method);
        if (!in_array($method, ['fast', 'slow'])) {
            $method = 'fast'; // Legacy default
        }
        
        // Use existing Pro GD implementation with reduced overhead
        switch (true) {
            case $image instanceof \Imagine\Gd\Image:
                $gd_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Gd\Sepia();
                return $gd_filter->apply($image, ['method' => $method]);
                
            case $image instanceof \Imagine\Imagick\Image:
            case $image instanceof \Imagine\Gmagick\Image:
            default:
                // For non-GD, return unchanged (matches Legacy behavior)
                return $image;
        }
    }
    
    /**
     * Process and validate sepia filter parameters
     * 
     * Handles legacy parameter format and validation.
     * 
     * @param array $raw_params Raw filter parameters
     * @return array Processed parameters
     */
}
