<?php

/**
 * JCOGS Image Pro - Smooth Filter
 * ===============================
 * Top-level smooth filter with multi-library support and parameter processing
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
 * Smooth Filter Class
 * 
 * Top-level smooth filter that handles parameter processing and library detection.
 * Supports smoothing level parameter for controlling the smoothing amount.
 */
class Smooth implements FilterInterface
{
    private int $smoothness;

    /**
     * Constructs Smooth filter.
     * 
     * @param int $smoothness Smoothing level (default: 6)
     */
    public function __construct(int $smoothness = 6)
    {
        $this->smoothness = $smoothness;
    }

    /**
     * Apply smooth filter to image
     * 
     * Processes smooth parameters and delegates to appropriate library implementation.
     * 
     * @param ImageInterface $image Source image
     * @param array $params Filter parameters [smoothing_level] (for backward compatibility)
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image, array $params = []): ImageInterface
    {
        // Use constructor parameter, with fallback to apply params for backward compatibility
        $smoothing_level = $params[0] ?? $this->smoothness;
        
        // Process smooth parameters
        $processed_params = $this->process_smooth_parameters([$smoothing_level]);
        
        // Delegate to appropriate library implementation
        switch (true) {
            case $image instanceof \Imagine\Gd\Image:
                $gd_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Gd\Smooth();
                return $gd_filter->apply($image, $processed_params);
                
            case $image instanceof \Imagine\Imagick\Image:
                // Future: Imagick implementation
                throw new \RuntimeException('Imagick smooth implementation not yet available');
                
            case $image instanceof \Imagine\Gmagick\Image:
                // Future: Gmagick implementation
                throw new \RuntimeException('Gmagick smooth implementation not yet available');
                
            default:
                throw new \RuntimeException('Unsupported image library for smooth filter');
        }
    }
    
    /**
     * Process and validate smooth filter parameters
     * 
     * Streamlined parameter processing matching Legacy behavior.
     * 
     * @param array $raw_params Raw filter parameters
     * @return array Processed parameters
     */
    private function process_smooth_parameters(array $raw_params): array
    {
        // Streamlined processing - Legacy doesn't apply complex validation
        return ['level' => (int) ($raw_params[0] ?? 1)];
    }
}
