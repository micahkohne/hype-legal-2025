<?php

/**
 * JCOGS Image Pro - GD Scatter Filter
 * ====================================
 * GD-optimized scatter filter implementation
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      GD Filter Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Filters\Gd;

use Imagine\Image\ImageInterface;

/**
 * GD Scatter Filter
 * 
 * Applies scatter effect to images using GD's native IMG_FILTER_SCATTER.
 * This filter randomly distributes pixels to create a scattered appearance.
 */
class Scatter
{
    /**
     * Constructs Scatter filter
     * 
     * No parameters required in constructor - uses runtime parameters
     */
    public function __construct()
    {
        // No parameters in constructor - use runtime parameters
    }

    /**
     * Apply GD-native scatter filter
     * 
     * @param ImageInterface $image The image to apply scatter effect to
     * @param array $params Scatter parameters [subtraction, addition]
     * @return ImageInterface The filtered image
     */
    public function apply(ImageInterface $image, array $params = []): ImageInterface
    {
        // Get scatter parameters (already validated by top-level filter)
        $subtraction = $params[0] ?? 3;
        $addition = $params[1] ?? 5;
        
        // Get GD resource using same method as Legacy
        $gd_resource = imagecreatefromstring($image->__toString());
        
        if ($gd_resource === false) {
            return $image; // Failed resource creation
        }

        // Apply scatter filter using native GD (identical to Legacy approach)
        if (imagefilter($gd_resource, IMG_FILTER_SCATTER, $subtraction, $addition)) {
            // Convert back using streamlined approach
            $imagine = new \Imagine\Gd\Imagine();
            $stream = $this->gd_resource_to_stream($gd_resource);
            $result_image = $imagine->load(stream_get_contents($stream));
            fclose($stream);
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
     * @param resource $gd_resource GD image resource
     * @return resource Stream resource
     */
    private function gd_resource_to_stream($gd_resource)
    {
        $stream = fopen('php://temp', 'r+');
        ob_start();
        imagepng($gd_resource);
        $image_data = ob_get_clean();
        fwrite($stream, $image_data);
        rewind($stream);
        return $stream;
    }
}
