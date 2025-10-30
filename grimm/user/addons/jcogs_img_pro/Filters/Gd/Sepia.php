<?php

/**
 * JCOGS Image Pro - GD-Specific Sepia Filter
 * ==========================================
 * High-performance sepia implementation with both fast and slow methods
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
 * GD-Specific Sepia Filter Class
 * 
 * Provides both fast and slow sepia rendering methods.
 * Fast method: Quick approximation using colorize
 * Slow method: Pixel-by-pixel processing for higher quality
 */
class Sepia
{
    /**
     * Apply GD-native sepia filter
     * 
     * @param ImageInterface $image Source image (must be GD image)
     * @param array $params Filter parameters ['method' => 'fast|slow']
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image, array $params = []): ImageInterface
    {
        $method = $params['method'] ?? 'fast';
        
        // Get the GD resource using the same method as legacy
        $gd_resource = imagecreatefromstring($image->__toString());
        
        if (!is_resource($gd_resource) && !is_object($gd_resource)) {
            throw new \RuntimeException('Invalid GD resource for sepia filter');
        }
        
        switch ($method) {
            case 'slow':
                $this->apply_slow_sepia($gd_resource);
                break;
                
            case 'fast':
            default:
                $this->apply_fast_sepia($gd_resource);
                break;
        }
        
        // Convert back to Imagine image format using optimized ImageUtilities method
        $image_utilities = ee('jcogs_img_pro:ImageUtilities');
        return $image_utilities->gdResourceToImagine($gd_resource);
    }
    
    /**
     * Apply fast sepia method using built-in filters
     * 
     * @param resource $gd_resource
     */
    private function apply_fast_sepia($gd_resource): void
    {
        // Convert to grayscale first
        imagefilter($gd_resource, IMG_FILTER_GRAYSCALE);
        
        // Apply sepia toning with colorize
        // Brown tint: increase red, decrease blue slightly
        imagefilter($gd_resource, IMG_FILTER_COLORIZE, 40, 20, -15);
    }
    
    /**
     * Apply slow sepia method with pixel-by-pixel processing
     * 
     * Higher quality sepia effect based on standard sepia formula.
     * 
     * @param resource $gd_resource
     */
    private function apply_slow_sepia($gd_resource): void
    {
        $width = imagesx($gd_resource);
        $height = imagesy($gd_resource);
        
        // Process each pixel for high-quality sepia
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgb = imagecolorat($gd_resource, $x, $y);
                
                // Extract RGB components
                $red = ($rgb >> 16) & 0xFF;
                $green = ($rgb >> 8) & 0xFF;
                $blue = $rgb & 0xFF;
                $alpha = ($rgb >> 24) & 0x7F;
                
                // Apply sepia transformation
                $sepia_red = min(255, ($red * 0.393) + ($green * 0.769) + ($blue * 0.189));
                $sepia_green = min(255, ($red * 0.349) + ($green * 0.686) + ($blue * 0.168));
                $sepia_blue = min(255, ($red * 0.272) + ($green * 0.534) + ($blue * 0.131));
                
                // Create new color and set pixel
                $sepia_color = imagecolorallocatealpha(
                    $gd_resource,
                    (int)$sepia_red,
                    (int)$sepia_green,
                    (int)$sepia_blue,
                    $alpha
                );
                
                imagesetpixel($gd_resource, $x, $y, $sepia_color);
            }
        }
    }
}
