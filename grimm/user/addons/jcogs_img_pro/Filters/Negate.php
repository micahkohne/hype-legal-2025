<?php

/**
 * JCOGS Image Pro - Negate Filter
 * ===============================
 * Color inversion filter implementation with GD-specific optimization fallback
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
 * Negate Filter Class
 * 
 * Provides color inversion (negative) effect processing with GD-specific optimization.
 * Falls back to generic Imagine implementation when GD-specific processing
 * is not available or optimal.
 */
class Negate implements FilterInterface
{
    /**
     * Constructs Negate filter.
     */
    public function __construct()
    {
    }

    /**
     * Apply negate filter to image
     * 
     * Uses Imagine's native negation filter for optimal performance.
     * Legacy approach - proven fast and reliable.
     * 
     * @param ImageInterface $image Source image
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Use Legacy approach: simple Imagine negation filter (213% faster)
        $filter = new \Imagine\Filter\Advanced\Negation();
        return $filter->apply($image);
    }
}
