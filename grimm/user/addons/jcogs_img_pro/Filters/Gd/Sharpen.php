<?php

/**
 * JCOGS Image Pro - GD Sharpen Filter Implementation
 * ==================================================
 * GD-specific implementation of manual sharpening filter
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

namespace JCOGSDesign\JCOGSImagePro\Filters\Gd;

use Imagine\Image\ImageInterface;

/**
 * GD Sharpen Filter Implementation
 * 
 * Applies sharpening effects using GD.
 */
class Sharpen
{
    /**
     * Apply sharpen filter using GD with legacy Unsharp_mask algorithm
     *
     * @param ImageInterface $image Source image (must be GD image)
     * @param array $parameters Filter parameters from top-level filter
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image, array $parameters): ImageInterface
    {
        $sharpening_value = $parameters['sharpening_value'] ?? 80;
        $radius = $parameters['radius'] ?? 0.5;
        $threshold = $parameters['threshold'] ?? 3;
        
        // Use optimized GD resource conversion
        $image_utilities = ee('jcogs_img_pro:ImageUtilities');
        
        // Get the GD resource using optimized conversion
        $gd_resource = $image_utilities->imagineToGdResource($image);
        
        if (!is_resource($gd_resource) && !is_object($gd_resource)) {
            throw new \RuntimeException('Invalid GD resource for sharpen filter');
        }
        
        // Apply legacy Unsharp Mask algorithm
        $processed_resource = $this->apply_legacy_unsharp_mask($gd_resource, $sharpening_value, $radius, $threshold);
        
        // Convert back to Imagine image format using optimized GD resource conversion
        $result = $image_utilities->gdResourceToImagine($processed_resource);
        
        // Clean up GD resource
        imagedestroy($processed_resource);
        
        return $result;
    }
    
    /**
     * Apply legacy Unsharp Mask algorithm exactly as in legacy system
     * Based on JCOGSDesign\Jcogs_img\Filters\Gd\Unsharp_mask
     *
     * @param resource $img Source image
     * @param int $sharpening_value Sharpening amount (0-500)
     * @param float $radius Blur radius
     * @param int $threshold Threshold value
     * @return resource Sharpened image
     */
    private function apply_legacy_unsharp_mask($img, int $sharpening_value, float $radius, int $threshold)
    {
        // Apply calibration to parameters exactly as legacy does
        $sharpening_value = min($sharpening_value, 500) * 0.016;
        $radius = abs(round(min(50, $radius) * 2)); // Only integers make sense.
        $threshold = min(255, $threshold);
        
        if ($radius == 0) {
            return $img;
        }
        
        $w = imagesx($img);
        $h = imagesy($img);
        $imgCanvas = imagecreatetruecolor($w, $h);
        $imgBlur   = imagecreatetruecolor($w, $h);

        // Gaussian blur matrix (exactly as legacy):
        $matrix = array(
            array(1, 2, 1),
            array(2, 4, 2),
            array(1, 2, 1)
        );
        imagecopy($imgBlur, $img, 0, 0, 0, 0, $w, $h);
        imageconvolution($imgBlur, $matrix, 16, 0);

        if ($threshold > 0) {
            // Calculate the difference between the blurred pixels and the original
            // and set the pixels
            for ($row = 0; $row < $h - 1; $row++) { // each row
                for ($col = 0; $col < $w; $col++) { // each pixel

                    $rgbOrig = imagecolorat($img, $col, $row);
                    $rOrig = round((($rgbOrig >> 16) & 0xFF),0);
                    $gOrig = round((($rgbOrig >>  8) & 0xFF),0);
                    $bOrig = round( ($rgbOrig        & 0xFF),0);
                    $aOrig = round( ($rgbOrig & 0x7F000000) >> 24,0);
                    
                    $rgbBlur = imagecolorat($imgBlur, $col, $row);
                    
                    $rBlur = round((($rgbBlur >> 16) & 0xFF),0);
                    $gBlur = round((($rgbBlur >>  8) & 0xFF),0);
                    $bBlur = round( ($rgbBlur        & 0xFF),0);
                    $aBlur = round( ($rgbBlur & 0x7F000000) >> 24,0);

                    // When the masked pixels differ less from the original
                    // than the threshold specifies, they are set to their original value.
                    $rNew = ((abs($rOrig - $rBlur) >= $threshold) ? max(0, min(255, round($sharpening_value * ($rOrig - $rBlur),0) + $rOrig)) : $rOrig);
                    $gNew = ((abs($gOrig - $gBlur) >= $threshold) ? max(0, min(255, round($sharpening_value * ($gOrig - $gBlur)) + $gOrig),0) : $gOrig);
                    $bNew = ((abs($bOrig - $bBlur) >= $threshold) ? max(0, min(255, round($sharpening_value * ($bOrig - $bBlur),0) + $bOrig)) : $bOrig);
                    $aNew = $aOrig;

                    if (($rOrig != $rNew) || ($gOrig != $gNew) || ($bOrig != $bNew)) {
                        $pixCol = imagecolorallocatealpha($img, $rNew, $gNew, $bNew, $aNew);
                        imagesetpixel($img, $col, $row, $pixCol);
                    }
                }
            }
        } else {
            for ($row = 0; $row < $h - 1; $row++) { // each row
                for ($col = 0; $col < $w; $col++) { // each pixel
                    $rgbOrig = imagecolorat($img, $col, $row);
                    $rOrig = round((($rgbOrig >> 16) & 0xFF),0);
                    $gOrig = round((($rgbOrig >>  8) & 0xFF),0);
                    $bOrig = round( ($rgbOrig        & 0xFF),0);
                    $aOrig = round( ($rgbOrig & 0x7F000000) >> 24,0);

                    $rgbBlur = imagecolorat($imgBlur, $col, $row);

                    $rBlur = round((($rgbBlur >> 16) & 0xFF),0);
                    $gBlur = round((($rgbBlur >>  8) & 0xFF),0);
                    $bBlur = round( ($rgbBlur        & 0xFF),0);
                    $aBlur = round( ($rgbBlur & 0x7F000000) >> 24,0);

                    $rNew = min(255, max(0, ($sharpening_value * ($rOrig - $rBlur)) + $rOrig));
                    $gNew = min(255, max(0, ($sharpening_value * ($gOrig - $gBlur)) + $gOrig));
                    $bNew = min(255, max(0, ($sharpening_value * ($bOrig - $bBlur)) + $bOrig));
                    $aNew = $aOrig;
                    $pixCol = imagecolorallocatealpha($img, $rNew, $gNew, $bNew, $aNew);
                    imagesetpixel($img, $col, $row, $pixCol);
                }
            }
        }
        
        // Clean up
        imagedestroy($imgCanvas);
        imagedestroy($imgBlur);
        
        return $img;
    }
}
