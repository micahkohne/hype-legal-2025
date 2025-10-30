<?php

/**
 * JCOGS Image Pro - GD-Specific Smooth Filter
 * ===========================================
 * High-performance smooth implementation using GD's native imagefilter
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
 * GD-Specific Smooth Filter Class
 * 
 * Uses PHP's native imagefilter function with IMG_FILTER_SMOOTH
 * for optimal performance when working with GD image resources.
 */
class Smooth
{
    /**
     * Apply GD-native smooth filter
     * 
     * Streamlined implementation matching Legacy approach exactly.
     * 
     * @param ImageInterface $image Source image (must be GD image)
     * @param array $params Filter parameters from top-level filter
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image, array $params = []): ImageInterface
    {
        // Get level parameter (already processed by top-level filter)
        $level = $params['level'] ?? 1;
        
        // Get GD resource using same method as Legacy
        $gd_resource = imagecreatefromstring($image->__toString());
        
        if ($gd_resource === false) {
            throw new \RuntimeException('Invalid GD resource for smooth filter');
        }
        
        // Apply GD smooth filter (identical to Legacy)
        if (imagefilter($gd_resource, IMG_FILTER_SMOOTH, $level)) {
            // Convert back using streamlined approach
            $imagine = new Imagine();
            $result_image = $imagine->load(stream_get_contents($this->gd_resource_to_stream($gd_resource)));
            imagedestroy($gd_resource);
            return $result_image;
        }
        
        // Filter failed - clean up and return original
        imagedestroy($gd_resource);
        return $image;
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
