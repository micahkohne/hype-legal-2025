<?php

/**
 * JCOGS Image Pro - Text Overlay Filter
 * =====================================
 * Phase 2: Native implementation using GDText for advanced typography
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
 * TextOverlay Filter Class
 * 
 * Top-level text overlay filter that handles parameter processing and library detection.
 * Supports various text overlay parameters for advanced typography.
 */
class TextOverlay implements FilterInterface
{
    /**
     * @var string Text to overlay
     */
    private $text;

    /**
     * Constructs TextOverlay filter.
     * 
     * @param string $text Text to overlay on image
     */
    public function __construct(string $text = '')
    {
        $this->text = $text;
    }

    /**
     * Apply text overlay to image
     * 
     * Processes text overlay parameters and delegates to appropriate library implementation.
     * 
     * @param ImageInterface $image Source image
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Use text from constructor
        $params = ['text_overlay' => $this->text];
        
        // Delegate to appropriate library implementation
        switch (true) {
            case $image instanceof \Imagine\Gd\Image:
                $text_overlay = new \JCOGSDesign\JCOGSImagePro\Filters\Gd\TextOverlay();
                return $text_overlay->apply($image, $params);

            case $image instanceof \Imagine\Imagick\Image:
                // Future: Imagick implementation
                throw new \RuntimeException('Imagick text overlay implementation not yet available');
                
            case $image instanceof \Imagine\Gmagick\Image:
                // Future: Gmagick implementation
                throw new \RuntimeException('Gmagick text overlay implementation not yet available');

            default:
                throw new \RuntimeException('Unsupported image library for text overlay filter');
        }
    }
}
