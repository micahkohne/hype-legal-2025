<?php

/**
 * JCOGS Image Pro - Brightness Filter
 * ====================================
 * Brightness adjustment filter with library detection
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Filter Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Filters;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

/**
 * Brightness Filter
 * 
 * Adjusts image brightness using Imagine's native effects.
 * Provides optimized brightness adjustment with parameter validation
 * and range clamping for consistent results.
 */
class Brightness implements FilterInterface
{
    /**
     * @var int Brightness amount (-255 to 255)
     */
    private $amount;

    /**
     * Constructs Brightness filter
     * 
     * @param int $amount Brightness adjustment amount (-255 to 255)
     */
    public function __construct(int $amount = 0)
    {
        $this->amount = $amount;
    }

    /**
     * Apply brightness filter to image
     * 
     * @param ImageInterface $image The image to adjust brightness for
     * @return ImageInterface The brightness-adjusted image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Use the brightness amount from constructor
        $amount = $this->amount;
        $amount = $this->process_brightness_parameter($amount);
        
        // Use Legacy approach: simple Imagine native brightness (213% faster)
        $image->effects()->brightness($amount);
        return $image;
    }
    
    /**
     * Process brightness parameter to match legacy behavior exactly
     *
     * @param mixed $value Raw parameter value
     * @return int Processed brightness value
     */
    private function process_brightness_parameter($value): int
    {
        // Convert to integer
        $value = (int) $value;
        
        // Clamp to range -255 to 255 (legacy validation)
        $value = max(-255, min(255, $value));
        
        return $value;
    }
}
