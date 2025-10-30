<?php

/**
 * JCOGS Image Pro - GD Resource Optimization Utility
 * ==================================================
 * Utility class for efficiently converting GD resources to Imagine images
 * without unnecessary PNG stream conversions.
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      GD Resource Optimization Phase
 */

namespace JCOGSDesign\JCOGSImagePro\Filters\Gd;

use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Metadata\MetadataBag;
use Imagine\Gd\Image;

/**
 * GD Resource Optimization Utility
 * 
 * Provides optimized methods for converting between GD resources and Imagine images
 * without the performance penalty of PNG stream conversion.
 */
class GdResourceOptimizer
{
    /**
     * Convert GD resource directly to Imagine Image
     * 
     * Major performance optimization: Instead of converting GD resource to PNG stream
     * and then loading that stream into Imagine, we create the Imagine Image directly
     * from the GD resource using the constructor.
     * 
     * This eliminates:
     * - PNG encoding (imagepng + ob_get_clean)
     * - Memory stream creation (fopen + fwrite + rewind)
     * - PNG decoding (Imagine->load)
     * 
     * @param resource|\GdImage $gd_resource GD image resource
     * @return ImageInterface Optimized Imagine image
     */
    public static function gdResourceToImagine($gd_resource): ImageInterface
    {
        if (!is_resource($gd_resource) && !is_object($gd_resource)) {
            throw new \RuntimeException('Invalid GD resource provided');
        }
        
        // Create Imagine Image directly from GD resource
        $palette = new RGB();
        $metadata = new MetadataBag();
        
        return new Image($gd_resource, $palette, $metadata);
    }
    
    /**
     * Extract GD resource from Imagine Image (for processing)
     * 
     * @param ImageInterface $image Imagine image
     * @return resource|\GdImage GD resource for processing
     */
    public static function imagineToGdResource(ImageInterface $image)
    {
        return imagecreatefromstring($image->__toString());
    }
    
    /**
     * Apply GD operation and return optimized Imagine image
     * 
     * Convenience method that combines GD resource extraction, operation application,
     * and optimized conversion back to Imagine image.
     * 
     * @param ImageInterface $image Source image
     * @param callable $operation Function to apply to GD resource
     * @return ImageInterface Processed image
     */
    public static function applyGdOperation(ImageInterface $image, callable $operation): ImageInterface
    {
        // Extract GD resource
        $gd_resource = self::imagineToGdResource($image);
        
        // Apply operation to GD resource
        $operation($gd_resource);
        
        // Convert back to Imagine using optimized method
        return self::gdResourceToImagine($gd_resource);
    }
    
    /**
     * Legacy stream conversion method (for compatibility/comparison)
     * 
     * This is the old inefficient method that many filters were using.
     * Kept for reference and potential fallback scenarios.
     * 
     * @param resource|\GdImage $gd_resource
     * @return resource Stream resource
     * @deprecated Use gdResourceToImagine instead for better performance
     */
    public static function gdResourceToStream($gd_resource)
    {
        $stream = fopen('php://temp', 'r+');
        
        // Save as PNG to preserve quality and transparency
        ob_start();
        imagepng($gd_resource);
        $image_data = ob_get_clean();
        
        fwrite($stream, $image_data);
        rewind($stream);
        
        return $stream;
    }
}
