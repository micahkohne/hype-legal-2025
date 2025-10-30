<?php

/**
 * JCOGS Image Pro - Opacity Filter
 * ================================
 * Top-level opacity filter with multi-library support and parameter processing
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
 * Opacity Filter Class
 * 
 * Top-level opacity filter that handles parameter processing and library detection.
 * Sets image opacity/transparency level from 0% (fully transparent) to 100% (opaque).
 */
class Opacity implements FilterInterface
{
    /**
     * @var int Opacity level (0-100)
     */
    private $level;

    /**
     * Constructs Opacity filter.
     * 
     * @param int $level Opacity level (0-100)
     */
    public function __construct(int $level = 100)
    {
        $this->level = $level;
    }

    /**
     * Apply opacity filter to image
     * 
     * Processes opacity parameters and delegates to appropriate library implementation.
     * 
     * @param ImageInterface $image Source image
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Process opacity parameters using constructor value
        $processed_params = $this->process_opacity_parameters([$this->level]);
        
        // Delegate to appropriate library implementation
        switch (true) {
            case $image instanceof \Imagine\Gd\Image:
                $gd_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Gd\Opacity();
                return $gd_filter->apply($image, $processed_params);
                
            case $image instanceof \Imagine\Imagick\Image:
                // Future: Imagick implementation
                throw new \RuntimeException('Imagick opacity implementation not yet available');
                
            case $image instanceof \Imagine\Gmagick\Image:
                // Future: Gmagick implementation
                throw new \RuntimeException('Gmagick opacity implementation not yet available');
                
            default:
                throw new \RuntimeException('Unsupported image library for opacity filter');
        }
    }
    
    /**
     * Process and validate opacity filter parameters
     * 
     * Handles legacy parameter format and validation.
     * 
     * @param array $raw_params Raw filter parameters
     * @return array Processed parameters
     */
    private function process_opacity_parameters(array $raw_params): array
    {
        $processed = [
            'level' => 100  // Default opacity level (fully opaque)
        ];
        
        // Handle opacity level parameter
        if (!empty($raw_params[0])) {
            $level = (int) $raw_params[0];
            if ($level >= 0 && $level <= 100) {
                $processed['level'] = $level;
            }
        }
        
        return $processed;
    }
}
