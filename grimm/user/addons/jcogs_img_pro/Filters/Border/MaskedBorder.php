<?php

/**
 * JCOGS Image Pro - Masked Border Filter
 * =======================================
 * Phase 2: Native EE7 implementation following legacy Filter/Gd separation architecture
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

namespace JCOGSDesign\JCOGSImagePro\Filters\Border;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Gd\Imagine;
use Imagine\Image\Palette;

/**
 * Masked Border Filter
 * 
 * Creates borders around shaped/masked images using proven legacy algorithm.
 * Follows exact legacy Filter/Gd separation architecture:
 * 1) Create transparent Imagine canvases - image size plus 2x border width
 * 2) Convert to GD only for pixel analysis and border drawing
 * 3) Convert back to Imagine and use paste() for transparency-preserving composition
 */
class MaskedBorder implements FilterInterface
{
    private int $border_width;
    private string $color;
    private ?string $rounded_corners;

    /**
     * Constructs MaskedBorder filter.
     * 
     * @param int $border_width Border width in pixels (default: 0)
     * @param string $color Border color (default: '#FFFFFF')
     * @param string|null $rounded_corners Optional rounded corners parameter (default: null)
     */
    public function __construct(int $border_width = 0, string $color = '#FFFFFF', ?string $rounded_corners = null)
    {
        $this->border_width = $border_width;
        $this->color = $color;
        $this->rounded_corners = $rounded_corners;
    }

    /**
     * Apply masked border to shaped image (following legacy architecture exactly)
     * 
     * @param ImageInterface $image Source image (with transparency/mask)
     * @return ImageInterface Image with border applied
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Use constructor parameters for configuration
        $border_width = $this->border_width;
        $color = $this->color;
        $rounded_corners = $this->rounded_corners;
        
        // Note: rounded_corners parameter is accepted but not yet implemented
        // This preserves the parameter for future enhancement to maintain
        // distinct corner definitions when applying borders to rounded images
        
        if ($border_width <= 0) {
            return $image;
        }
        
        try {
            // Get image dimensions
            $image_width = $image->getSize()->getWidth();
            $image_height = $image->getSize()->getHeight();
            
            // Calculate new dimensions with border space
            $new_width = $image_width + (2 * $border_width);
            $new_height = $image_height + (2 * $border_width);
            $new_image_size = new Box($new_width, $new_height);
            
            // Create expanded transparent canvas for source image (following legacy exactly)
            $source_image = (new Imagine())->create($new_image_size, (new Palette\RGB())->color([0, 0, 0], 0));
            $source_image->paste($image, new Point($border_width, $border_width));
            
            // Create expanded transparent canvas for border image (following legacy exactly)
            $border_image = (new Imagine())->create($new_image_size, (new Palette\RGB())->color([0, 0, 0], 0));
            
            // Convert to GD for pixel-level border detection (following legacy Filter/Gd separation)
            $working_image = imagecreatefromstring($source_image->__toString());
            $working_border_image = imagecreatefromstring($border_image->__toString());
            
            // Ensure alpha transparency is preserved in GD resources (following legacy transparency handling)
            imagealphablending($working_image, false);
            imagesavealpha($working_image, true);
            imagealphablending($working_border_image, false);
            imagesavealpha($working_border_image, true);
            
            // Parse and allocate border color
            $border_color_rgb = $this->parse_color_to_rgb($color);
            $border_color_index = imagecolorallocatealpha(
                $working_border_image, 
                $border_color_rgb[0], 
                $border_color_rgb[1], 
                $border_color_rgb[2], 
                $border_color_rgb[3] ?? 0
            );
            
            // Apply legacy transition detection algorithm using GD
            $this->apply_legacy_border_detection($working_image, $working_border_image, $new_width, $new_height, $border_width, $border_color_index);
            
            // Convert GD border image back to Imagine (following legacy pattern)
            $border_image = $this->convert_gd_to_imagine($working_border_image);
            
            // Use Imagine paste() for transparency-preserving composition (following legacy exactly)
            $border_image->paste($source_image, new Point(0, 0));
            
            // Cleanup GD resources
            imagedestroy($working_image);
            imagedestroy($working_border_image);
            
            return $border_image;
            
        } catch (\Exception $e) {
            // If border application fails, return original image
            return $image;
        }
    }
    
    /**
     * Convert GD resource to Imagine image (following legacy pattern)
     * 
     * @param resource $gd_resource GD image resource
     * @return ImageInterface Imagine image
     */
    private function convert_gd_to_imagine($gd_resource): ImageInterface
    {
        // Ensure alpha transparency is preserved before conversion
        imagealphablending($gd_resource, false);
        imagesavealpha($gd_resource, true);
        
        // Convert GD to string then back to Imagine (following legacy conversion method)
        ob_start();
        imagepng($gd_resource);
        $image_data = ob_get_contents();
        ob_end_clean();
        
        return (new Imagine())->load($image_data);
    }
    
    /**
     * Apply legacy border detection algorithm (following exact legacy implementation)
     * 
     * @param resource $working_image Source image with transparent border space
     * @param resource $working_border_image Target image for border drawing
     * @param int $new_width Total width including border space
     * @param int $new_height Total height including border space
     * @param int $border_width Border width
     * @param int $border_color_index GD color index for border
     */
    private function apply_legacy_border_detection($working_image, $working_border_image, int $new_width, int $new_height, int $border_width, int $border_color_index): void
    {
        // Scan the working image for transparent to opaque transitions (or opposite)
        // When you find them, add border radius circle of border colour at same point
        for ($j = 0; $j < $new_height - 1; $j++) {
            for ($i = 0; $i < $new_width; $i++) {
                $this_pixel_is_opaque = (imagecolorat($working_image, $i, $j) & 0x7F000000) >> 24 == 0;
                $the_pixel_above_is_opaque = $j > 0 ? (imagecolorat($working_image, $i, $j - 1) & 0x7F000000) >> 24 == 0 : false;

                // Calculate some transition stuff
                $x_transition = isset($the_previous_pixel_is_opaque) && $the_previous_pixel_is_opaque != $this_pixel_is_opaque;
                $y_transition = isset($the_pixel_above_is_opaque) && $the_pixel_above_is_opaque != $this_pixel_is_opaque;
                $x = $x_transition && $this_pixel_is_opaque ? $i : $i - 1;
                $y = $y_transition && $this_pixel_is_opaque ? $j : $j - 1;

                // Do we have transition?
                if ($x_transition || $y_transition) {
                    // Add a filled circle of border colour at pixel boundary we have found (following legacy exactly)
                    imagefilledellipse($working_border_image, $x, $y, $border_width * 2, $border_width * 2, $border_color_index);
                }
                $the_previous_pixel_is_opaque = $this_pixel_is_opaque;
            }
        }
    }
    
    /**
     * Parse color string to RGB array with alpha support (following legacy color handling)
     * 
     * @param string $color_str Color string (hex, rgb, etc.)
     * @return array RGBA values [r, g, b, a]
     */
    private function parse_color_to_rgb(string $color_str): array
    {
        // Handle hex colors
        if (str_starts_with($color_str, '#')) {
            $hex = substr($color_str, 1);
        } else {
            $hex = $color_str;
        }
        
        // Expand 3-digit hex to 6-digit
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        // Convert hex to RGB (with alpha 0 for opaque, following legacy)
        if (strlen($hex) === 6 && ctype_xdigit($hex)) {
            return [
                hexdec(substr($hex, 0, 2)),
                hexdec(substr($hex, 2, 2)),
                hexdec(substr($hex, 4, 2)),
                0  // Alpha 0 = opaque in GD
            ];
        }
        
        // Handle rgb() format
        if (str_starts_with($color_str, 'rgb(')) {
            $rgb_match = [];
            if (preg_match('/rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/', $color_str, $rgb_match)) {
                return [(int)$rgb_match[1], (int)$rgb_match[2], (int)$rgb_match[3], 0];
            }
        }
        
        // Default to white if parsing fails (following legacy fallback)
        return [255, 255, 255, 0];
    }
}
