<?php

/**
 * JCOGS Image Pro - Box Border Filter
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

namespace JCOGSDesign\JCOGSImagePro\Filters\Border;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Gd\Imagine;

/**
 * Box Border Filter
 * 
 * Creates a solid border around rectangular images by creating a larger canvas
 * with the border color and pasting the original image offset by the border width.
 * This is the efficient method for standard rectangular images.
 */
class BoxBorder implements FilterInterface
{
    /**
     * @var string Border specification
     */
    private $border_spec;

    /**
     * Constructs BoxBorder filter.
     * 
     * @param string $border_spec Border specification
     */
    public function __construct(string $border_spec = '')
    {
        $this->border_spec = $border_spec;
    }

    /**
     * Apply box border to image
     * 
     * @param ImageInterface $image Source image
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Parse border specification in legacy format: "width|color"
        $width = 10; // Default width
        $color = '#FFFFFF'; // Default color
        
        if (!empty($this->border_spec)) {
            // Parse using pipe separator (legacy format)
            $parts = explode('|', $this->border_spec);
            if (count($parts) >= 1) {
                $width = (int)trim($parts[0]);
            }
            if (count($parts) >= 2) {
                $color = trim($parts[1]);
            }
        }
        
        if ($width <= 0) {
            return $image;
        }
        
        try {
            // Get original image dimensions
            $original_size = $image->getSize();
            $original_width = $original_size->getWidth();
            $original_height = $original_size->getHeight();
            
            // Calculate new canvas size (original + border on all sides)
            $new_width = $original_width + (2 * $width);
            $new_height = $original_height + (2 * $width);
            $new_size = new Box($new_width, $new_height);
            
            // Parse color and create palette color
            $palette = $image->palette();
            $border_color = $this->parse_color($color, $palette);
            
            // Create new canvas with border color
            $imagine = new Imagine();
            $bordered_image = $imagine->create($new_size, $border_color);
            
            // Paste original image onto the new canvas, offset by border width
            $paste_point = new Point($width, $width);
            $bordered_image->paste($image, $paste_point);
            
            return $bordered_image;
            
        } catch (\Exception $e) {
            // If border application fails, return original image
            return $image;
        }
    }
    
    /**
     * Parse color string into Palette Color
     * 
     * @param string $color_str Color string (hex, rgb, etc.)
     * @param \Imagine\Image\Palette\PaletteInterface $palette Image palette
     * @return \Imagine\Image\Palette\Color\ColorInterface
     */
    private function parse_color(string $color_str, $palette)
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
        
        // Convert hex to RGB
        if (strlen($hex) === 6 && ctype_xdigit($hex)) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            
            return $palette->color([$r, $g, $b]);
        }
        
        // Handle rgb() format
        if (str_starts_with($color_str, 'rgb(')) {
            $rgb_match = [];
            if (preg_match('/rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)/', $color_str, $rgb_match)) {
                return $palette->color([(int)$rgb_match[1], (int)$rgb_match[2], (int)$rgb_match[3]]);
            }
        }
        
        // Default to white if parsing fails
        return $palette->color([255, 255, 255]);
    }
}
