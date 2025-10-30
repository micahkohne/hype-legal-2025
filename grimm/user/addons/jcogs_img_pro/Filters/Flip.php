<?php

/**
 * JCOGS Image Pro - Flip Filter
 * ==============================
 * Phase 2: Native EE7 implementation pipeline architecture
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
use Imagine\Filter\Basic\FlipHorizontally;
use Imagine\Filter\Basic\FlipVertically;

/**
 * Flip Transformation Filter
 * 
 * Applies horizontal and/or vertical flip transformations to images.
 * Supports legacy parameter format: 'h', 'v', 'h|v'
 */
class Flip implements FilterInterface
{
    private string $flip_param;

    /**
     * Constructor
     * 
     * @param string $flip_param Flip parameter ('h', 'v', or 'h|v')
     */
    public function __construct(string $flip_param = '')
    {
        $this->flip_param = $flip_param;
    }

    /**
     * Apply flip transformation to image
     * 
     * @param ImageInterface $image Source image
     * @param array $params Filter parameters (optional, constructor params take precedence)
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image, array $params = []): ImageInterface
    {
        // Use constructor parameter first, then fall back to apply() parameters for compatibility
        $flip_param = !empty($this->flip_param) ? $this->flip_param : ($params['flip'] ?? '');
        
        if (empty($flip_param)) {
            return $image;
        }
        
        // Parse flip modes (can be 'h', 'v', or 'h|v')
        $flips = explode('|', $flip_param);
        
        foreach ($flips as $flip) {
            $flip = trim($flip);
            switch (strtolower($flip)) {
                case 'h':
                    $horizontal_flip = new FlipHorizontally();
                    $image = $horizontal_flip->apply($image);
                    break;
                    
                case 'v':
                    $vertical_flip = new FlipVertically();
                    $image = $vertical_flip->apply($image);
                    break;
            }
        }
        
        return $image;
    }
}
