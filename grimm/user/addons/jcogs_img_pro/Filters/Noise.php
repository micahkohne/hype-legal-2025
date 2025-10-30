<?php

/**
 * JCOGS Image Pro - Noise Filter
 * ==============================
 * Top-level noise filter with multi-library support and parameter processing
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
 * Noise Filter Class
 * 
 * Top-level noise filter that handles parameter processing and library detection.
 * Adds random noise to the image with configurable intensity.
 */
class Noise implements FilterInterface
{
    private int $noise_level;

    /**
     * Constructs Noise filter.
     * 
     * @param int $noise_level Noise intensity level (default: 5)
     */
    public function __construct(int $noise_level = 5)
    {
        $this->noise_level = $noise_level;
    }

    /**
     * Apply noise filter to image
     * 
     * Processes noise parameters and delegates to appropriate library implementation.
     * 
     * @param ImageInterface $image Source image
     * @param array $params Filter parameters [noise_level] (for backward compatibility)
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image, array $params = []): ImageInterface
    {
        // Use constructor parameter, with fallback to apply params for backward compatibility
        $noise_level = $params[0] ?? $this->noise_level;
        
        // Process noise parameters
        $processed_params = $this->process_noise_parameters([$noise_level]);
        
        // Delegate to appropriate library implementation
        switch (true) {
            case $image instanceof \Imagine\Gd\Image:
                $gd_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Gd\Noise();
                return $gd_filter->apply($image, $processed_params);
                
            case $image instanceof \Imagine\Imagick\Image:
                // Future: Imagick implementation
                throw new \RuntimeException('Imagick noise implementation not yet available');
                
            case $image instanceof \Imagine\Gmagick\Image:
                // Future: Gmagick implementation
                throw new \RuntimeException('Gmagick noise implementation not yet available');
                
            default:
                throw new \RuntimeException('Unsupported image library for noise filter');
        }
    }
    
    /**
     * Process and validate noise filter parameters
     * 
     * Handles legacy parameter format and validation.
     * 
     * @param array $raw_params Raw filter parameters
     * @return array Processed parameters
     */
    private function process_noise_parameters(array $raw_params): array
    {
        $processed = [
            'level' => 30  // Default noise level
        ];
        
        // Handle noise level parameter
        if (!empty($raw_params[0])) {
            $level = (int) $raw_params[0];
            if ($level >= 0 && $level <= 100) {
                $processed['level'] = $level;
            }
        }
        
        return $processed;
    }
}
