<?php

/**
 * JCOGS Image Pro - GD Contrast Filter
 * =====================================
 * GD-optimized contrast filter implementation
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
 * GD Contrast Filter
 * 
 * Adjusts image contrast using GD's native IMG_FILTER_CONTRAST.
 * Allows both increasing and decreasing contrast levels for enhanced
 * visual impact.
 */
class Contrast
{
    /**
     * Constructs Contrast filter
     * 
     * GD-specific contrast implementation with no constructor parameters
     */
    public function __construct()
    {
        // GD-specific contrast implementation
    }

    /**
     * Apply contrast filter using GD
     * 
     * @param ImageInterface $image The image to adjust contrast for
     * @param array $params Contrast parameters [level]
     * @return ImageInterface The contrast-adjusted image
     */
    public function apply(ImageInterface $image, array $params = []): ImageInterface
    {
        // Get the already-processed contrast level from the top-level filter
        $level = $params[0] ?? 0;
        
        // Get the GDImage object using the same method as legacy
        $gd_resource = imagecreatefromstring($image->__toString());
        
        if ($gd_resource === false) {
            // Fallback if GD resource creation failed
            return $image;
        }

        // Apply contrast filter using native GD (same as legacy)
        if (imagefilter($gd_resource, IMG_FILTER_CONTRAST, $level)) {
            // Success - convert back to Imagine image using same pattern as legacy
            $imagine = new \Imagine\Gd\Imagine();
            $stream = $this->gd_resource_to_stream($gd_resource);
            $result_image = $imagine->load(stream_get_contents($stream));
            fclose($stream);
            imagedestroy($gd_resource);
            return $result_image;
        } else {
            // Filter failed - return unchanged
            imagedestroy($gd_resource);
            return $image;
        }
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
