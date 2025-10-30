<?php

/**
 * JCOGS Image Pro - GD-Specific Negate Filter
 * ===========================================
 * High-performance negate implementation using GD's native imagefilter
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

namespace JCOGSDesign\JCOGSImagePro\Filters\Gd;

use Imagine\Image\ImageInterface;
use Imagine\Gd\Imagine;

/**
 * GD-Specific Negate Filter Class
 * 
 * Uses PHP's native imagefilter function with IMG_FILTER_NEGATE
 * for optimal performance when working with GD image resources.
 */
class Negate
{
    /**
     * Apply GD-native negate filter
     * 
     * Uses imagefilter with IMG_FILTER_NEGATE for maximum performance.
     * 
     * @param ImageInterface $image Source image (must be GD image)
     * @param array $params Filter parameters (unused for negate)
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image, array $params = []): ImageInterface
    {
        // Get the GD resource using the same method as legacy
        $gd_resource = imagecreatefromstring($image->__toString());
        
        if (!is_resource($gd_resource) && !is_object($gd_resource)) {
            throw new \RuntimeException('Invalid GD resource for negate filter');
        }
        
        // Apply GD negate filter
        imagefilter($gd_resource, IMG_FILTER_NEGATE);
        
        // Convert back to Imagine image format
        $imagine = new Imagine();
        return $imagine->load(stream_get_contents($this->gd_resource_to_stream($gd_resource)));
    }
    
    /**
     * Convert GD resource to stream for Imagine
     * 
     * @param resource $gd_resource
     * @return resource
     */
    private function gd_resource_to_stream($gd_resource)
    {
        $stream = fopen('php://temp', 'r+');
        
        // Save as PNG to preserve quality
        ob_start();
        imagepng($gd_resource);
        $image_data = ob_get_clean();
        
        fwrite($stream, $image_data);
        rewind($stream);
        
        return $stream;
    }
}
