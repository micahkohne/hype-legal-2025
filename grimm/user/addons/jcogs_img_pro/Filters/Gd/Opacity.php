<?php

/**
 * JCOGS Image Pro - GD-Specific Opacity Filter
 * ============================================
 * Custom opacity implementation for setting image transparency
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
 * GD-Specific Opacity Filter Class
 * 
 * Implements opacity control by adjusting alpha channel values.
 * Supports transparency levels from 0% to 100%.
 */
class Opacity
{
    /**
     * Apply GD-native opacity filter
     * 
     * Uses custom alpha channel manipulation to set opacity.
     * 
     * @param ImageInterface $image Source image (must be GD image)
     * @param array $params Filter parameters from top-level filter
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image, array $params = []): ImageInterface
    {
        // Get processed parameters from top-level filter
        $level = $params['level'] ?? 100;
        
        // Skip processing if opacity is already 100%
        if ($level >= 100) {
            return $image;
        }
        
        // Get the GD resource using the same method as legacy
        $gd_resource = imagecreatefromstring($image->__toString());
        
        if (!is_resource($gd_resource) && !is_object($gd_resource)) {
            throw new \RuntimeException('Invalid GD resource for opacity filter');
        }
        
        // Apply opacity algorithm and get the new resource
        $processed_resource = $this->apply_opacity_algorithm($gd_resource, $level);
        
        // Convert back to Imagine image format using optimized GD resource conversion
        $image_utilities = ee('jcogs_img_pro:ImageUtilities');
        $result = $image_utilities->gdResourceToImagine($processed_resource);
        
        // Clean up GD resource
        imagedestroy($processed_resource);
        
        return $result;
    }
    
    /**
     * Apply opacity algorithm to GD resource using legacy-compatible method
     * 
     * @param resource $gd_resource
     * @param int $level Opacity level (0-100)
     * @return resource The new image resource with applied opacity
     */
    private function apply_opacity_algorithm($gd_resource, int $level)
    {
        $width = imagesx($gd_resource);
        $height = imagesy($gd_resource);
        
        // Create a temporary empty image using dimensions for processed image
        // Set image bg_colour to transparent (matching legacy implementation)
        $opacity_image = imagecreatetruecolor($width, $height);
        $backgroundColor = imagecolorallocatealpha($opacity_image, 0, 0, 0, 127);
        imagefill($opacity_image, 0, 0, $backgroundColor);
        
        // Apply opacity using the same algorithm as legacy imagecopymerge_alpha
        $processed_image = $this->apply_imagecopymerge_alpha_algorithm(
            $opacity_image, 
            $gd_resource, 
            0, 0, 0, 0, 
            $width, $height, 
            $level
        );
        
        // As we are working with opacity, set savealpha true
        imagesavealpha($processed_image, true);
        
        // Clean up original resource
        imagedestroy($gd_resource);
        
        return $processed_image;
    }
    
    /**
     * Legacy-compatible imagecopymerge_alpha implementation
     * Based on the legacy ImageProcessingTrait::imagecopymerge_alpha method
     * 
     * @param resource $dst_im
     * @param resource $src_im
     * @param int $dst_x
     * @param int $dst_y
     * @param int $src_x
     * @param int $src_y
     * @param int $src_w
     * @param int $src_h
     * @param float $pct
     * @return resource
     */
    private function apply_imagecopymerge_alpha_algorithm($dst_im, $src_im, int $dst_x, int $dst_y, int $src_x, int $src_y, int $src_w, int $src_h, float $pct)
    {
        if (!isset($pct)) {
            return $dst_im;
        }
        $pct /= 100;
        // Get image width and height 
        $w = imagesx($src_im);
        $h = imagesy($src_im);

        // Turn alpha blending off 
        imagealphablending($src_im, false);

        // Find the most opaque pixel in the image (the one with the smallest alpha value) 
        $minalpha = 127;
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $alpha = (imagecolorat($src_im, $x, $y) >> 24) & 0xFF;
                if ($alpha < $minalpha) {
                    $minalpha = $alpha;
                }
            }
        }

        //loop through image pixels and modify alpha for each 
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                //get current alpha value (represents the TRANSPARENCY!) 
                $colorxy = imagecolorat($src_im, $x, $y);
                $alpha   = ($colorxy >> 24) & 0xFF;

                //calculate new alpha 
                if ($minalpha !== 127) {
                    $alpha = 127 + 127 * $pct * ($alpha - 127) / (127 - $minalpha);
                }
                else {
                    $alpha += 127 * $pct;
                }
                $alpha = (int) $alpha;

                //get the color index with new alpha 
                $alphacolorxy = imagecolorallocatealpha($src_im, ($colorxy >> 16) & 0xFF, ($colorxy >> 8) & 0xFF, $colorxy & 0xFF, $alpha < 127 ? $alpha : 127);
                
                //set pixel with the new color + opacity 
                if (!imagesetpixel($src_im, $x, $y, $alphacolorxy)) {
                    return $dst_im;
                }
            }
        }

        // Copy the image
        imagecopy($dst_im, $src_im, (int) $dst_x, (int) $dst_y, (int) $src_x, (int) $src_y, (int) $src_w, (int) $src_h);
        return $dst_im;
    }
}
