<?php

/**
 * JCOGS Image Pro - LQIP Filter
 * =============================
 * Low Quality Image Placeholder filter for progressive loading
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
 * LQIP (Low Quality Image Placeholder) Filter
 * 
 * Creates a low-quality placeholder version of an image for progressive loading.
 * Uses the same approach as Legacy: Pixelate(6) then Blur(12).
 */
class Lqip implements FilterInterface
{
    private float $scale;
    private int $blur;
    private int $quality;
    
    public function __construct(float $scale = 0.1, int $blur = 10, int $quality = 20)
    {
        $this->scale = $scale;
        $this->blur = $blur;
        $this->quality = $quality;
    }
    
    /**
     * Apply LQIP filter to image
     *
     * @param ImageInterface $image The image to process
     * @return ImageInterface The processed image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Use stored parameters instead of defaults
        // Legacy LQIP approach: apply pixelate(6) then blur(12) using standard filters
        // This ensures transparency is preserved exactly like Legacy does
        
        // Step 1: Apply pixelate filter with block size 6 (like Legacy)
        $pixelate_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Pixelate(6); // Legacy uses block_size=6
        $image = $pixelate_filter->apply($image);
        
        // Step 2: Apply blur filter with intensity 12 (like Legacy)  
        $blur_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Blur(12); // Legacy uses amount=12
        $image = $blur_filter->apply($image);
        
        return $image;
    }
}
