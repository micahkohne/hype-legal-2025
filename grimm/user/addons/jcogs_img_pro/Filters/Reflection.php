<?php

/**
 * JCOGS Image Pro - Reflection Filter
 * ====================================
 * Phase 2: Native EE7 implementation pipeline architecture
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
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\Palette\RGB;
use Imagine\Gd\Imagine;

/**
 * Reflection Transformation Filter
 * 
 * Creates a reflection effect below the image with gradient opacity.
 * Based on legacy implementation with gap, opacity controls.
 */
class Reflection implements FilterInterface
{
    /**
     * @var string Reflection specification
     */
    private $reflection_spec;

    /**
     * Constructs Reflection filter.
     * 
     * @param string $reflection_spec Reflection specification
     */
    public function __construct(string $reflection_spec = '')
    {
        $this->reflection_spec = $reflection_spec;
    }

    /**
     * Apply reflection transformation to image
     * 
     * @param ImageInterface $image Source image
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Use the original Pro approach directly - it's faster than GD delegation
        return $this->apply_generic($image);
    }

    /**
     * Original Pro reflection implementation - optimized and efficient
     * 
     * @param ImageInterface $image Source image
     * @return ImageInterface Processed image
     */
    protected function apply_generic(ImageInterface $image): ImageInterface
    {
        // Use reflection spec from constructor
        $reflection_param = $this->reflection_spec;
        $bg_color = '#ffffff';
        $save_as = 'jpg';
        
        if ($reflection_param === null || $reflection_param === '') {
            return $image;
        }
        
        // Parse reflection parameters: gap,start_opacity,end_opacity,height
        $reflection_parts = explode(',', $reflection_param);
        
        $original_size = $image->getSize();
        $original_width = $original_size->getWidth();
        $original_height = $original_size->getHeight();
        
        // Parse parameters with defaults matching legacy
        $gap = $this->validate_reflection_dimension($reflection_parts[0] ?? '0', $original_height);
        $start_opacity = isset($reflection_parts[1]) && intval($reflection_parts[1]) > 0 && intval($reflection_parts[1]) <= 100 
            ? intval($reflection_parts[1]) : 80;
        $end_opacity = isset($reflection_parts[2]) && intval($reflection_parts[2]) >= 0 && intval($reflection_parts[2]) <= 100 
            ? intval($reflection_parts[2]) : 0;
        $reflection_height = round($this->validate_reflection_dimension($reflection_parts[3] ?? '50%', $original_height), 0);
        
        // Determine background color based on output format
        $reflection_color = in_array($save_as, ['png', 'webp']) 
            ? (new RGB())->color([0, 0, 0], 0) // Transparent for formats that support it
            : $this->parse_color_string($bg_color);
        
        // Create new canvas with space for reflection
        $new_height = $original_height + $gap + $reflection_height;
        $new_size = new Box($original_width, $new_height);
        
        $imagine = new Imagine();
        $canvas = $imagine->create($new_size, $reflection_color);
        
        // Paste original image at top
        $canvas->paste($image, new Point(0, 0));
        
        // Create reflection
        $reflection_image = $this->create_reflection($image, $reflection_height, $start_opacity, $end_opacity);
        
        // Paste reflection below original image (with gap)
        $reflection_y = $original_height + $gap;
        $canvas->paste($reflection_image, new Point(0, $reflection_y));
        
        return $canvas;
    }
    
    /**
     * Create reflection with gradient opacity
     * 
     * @param ImageInterface $image Source image
     * @param int $reflection_height Height of reflection
     * @param int $start_opacity Starting opacity (0-100)
     * @param int $end_opacity Ending opacity (0-100)
     * @return ImageInterface Reflection image
     */
    private function create_reflection(ImageInterface $image, int $reflection_height, int $start_opacity, int $end_opacity): ImageInterface
    {
        $original_size = $image->getSize();
        $original_width = $original_size->getWidth();
        
        // Create vertically flipped copy
        $reflection = clone $image;
        $reflection = $reflection->flipVertically();
        
        // Crop to reflection height if needed
        if ($reflection_height < $original_size->getHeight()) {
            $crop_box = new Box($original_width, $reflection_height);
            $reflection = $reflection->crop(new Point(0, 0), $crop_box);
        }
        
        // Apply gradient opacity
        $reflection = $this->apply_gradient_opacity($reflection, $start_opacity, $end_opacity);
        
        return $reflection;
    }
    
    /**
     * Apply gradient opacity to reflection
     * 
     * @param ImageInterface $image Reflection image
     * @param int $start_opacity Starting opacity (0-100)
     * @param int $end_opacity Ending opacity (0-100)
     * @return ImageInterface Image with gradient opacity
     */
    private function apply_gradient_opacity(ImageInterface $image, int $start_opacity, int $end_opacity): ImageInterface
    {
        // Convert to GD for pixel-level manipulation
        $gd_resource = imagecreatefromstring($image->__toString());
        
        if (!$gd_resource) {
            return $image; // Return original if conversion fails
        }
        
        $width = imagesx($gd_resource);
        $height = imagesy($gd_resource);
        
        // Enable alpha blending
        imagealphablending($gd_resource, false);
        imagesavealpha($gd_resource, true);
        
        // Apply gradient opacity row by row
        for ($y = 0; $y < $height; $y++) {
            // Calculate opacity for this row (linear gradient)
            $progress = $height > 1 ? $y / ($height - 1) : 0;
            $current_opacity = $start_opacity + ($end_opacity - $start_opacity) * $progress;
            $alpha = (int) (127 * (1 - $current_opacity / 100));
            
            // Process each pixel in the row
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($gd_resource, $x, $y);
                
                // Extract RGB components
                $r = ($color >> 16) & 0xFF;
                $g = ($color >> 8) & 0xFF;
                $b = $color & 0xFF;
                
                // Create new color with modified alpha
                $new_color = imagecolorallocatealpha($gd_resource, $r, $g, $b, $alpha);
                imagesetpixel($gd_resource, $x, $y, $new_color);
            }
        }
        
        // Convert back to Imagine image
        ob_start();
        imagepng($gd_resource);
        $image_data = ob_get_clean();
        imagedestroy($gd_resource);
        
        $imagine = new Imagine();
        return $imagine->load($image_data);
    }
    
    /**
     * Validate dimension parameter (supports percentages)
     * 
     * @param string $dimension Dimension value
     * @param int $reference_size Reference size for percentage calculations
     * @return int Validated dimension
     */
    protected function validate_reflection_dimension(string $dimension, int $reference_size): int
    {
        $dimension = trim($dimension);
        
        if (str_ends_with($dimension, '%')) {
            $percentage = (float) rtrim($dimension, '%');
            return (int) round($reference_size * $percentage / 100);
        }
        
        return max(0, (int) $dimension);
    }
    
    /**
     * Parse color string to ColorInterface
     * 
     * @param string $color_string Color in hex format or color name
     * @return \Imagine\Image\Palette\Color\ColorInterface
     */
    private function parse_color_string(string $color_string)
    {
        $palette = new RGB();
        
        // Remove # if present
        $color_string = ltrim($color_string, '#');
        
        // Handle 3-digit hex
        if (strlen($color_string) === 3) {
            $color_string = $color_string[0] . $color_string[0] . 
                           $color_string[1] . $color_string[1] . 
                           $color_string[2] . $color_string[2];
        }
        
        // Convert hex to RGB
        if (strlen($color_string) === 6 && ctype_xdigit($color_string)) {
            $r = hexdec(substr($color_string, 0, 2));
            $g = hexdec(substr($color_string, 2, 2));
            $b = hexdec(substr($color_string, 4, 2));
            
            return $palette->color([$r, $g, $b]);
        }
        
        // Default to white if parsing fails
        return $palette->color([255, 255, 255]);
    }
}
