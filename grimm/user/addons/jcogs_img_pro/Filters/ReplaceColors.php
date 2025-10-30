<?php

/**
 * JCOGS Image Pro - Replace Colors Filter
 * =======================================
 * Color replacement filter with fuzziness tolerance support
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

namespace JCOGSDesign\JCOGSImagePro\Filters;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

/**
 * Replace Colors Filter
 * 
 * Replaces one color with another in the image with optional fuzziness tolerance.
 * Useful for color correction and artistic effects.
 */
class ReplaceColors implements FilterInterface
{
    private string $library = 'gd';
    private string $from_color;
    private string $to_color;
    private int $fuzziness;
    
    /**
     * Constructs ReplaceColors filter.
     * 
     * @param string $from_color Color to replace (default: '#000000')
     * @param string $to_color Replacement color (default: '#FFFFFF')
     * @param int $fuzziness Color matching tolerance (default: 0)
     */
    public function __construct(string $from_color = '#000000', string $to_color = '#FFFFFF', int $fuzziness = 0)
    {
        $this->library = 'gd';
        $this->from_color = $from_color;
        $this->to_color = $to_color;
        $this->fuzziness = $fuzziness;
    }
    
    /**
     * Apply color replacement filter to image
     *
     * @param ImageInterface $image The image data
     * @return ImageInterface The processed image data
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Use constructor parameters
        $from_color = $this->from_color;  // Color to replace (hex)
        $to_color = $this->to_color;      // Replacement color (hex)
        $tolerance = $this->fuzziness;    // Color matching tolerance (0-100)
        
        // Process parameters
        $processed_params = $this->process_replace_colors_parameters($from_color, $to_color, $tolerance);
        
        // Apply filter based on detected library
        switch ($this->library) {
            case 'gd':
                $gd_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Gd\ReplaceColors();
                $result = $gd_filter->apply($image, $processed_params);
                
                // Convert result back to Imagine object for pipeline consistency
                if (is_string($result)) {
                    $imagine = new \Imagine\Gd\Imagine();
                    return $imagine->load($result);
                }
                return $image; // Fallback to original image
            
            case 'imagick':
                // Future Imagick implementation
                throw new \Exception('Imagick support for replace colors not yet implemented');
            
            default:
                throw new \Exception('Unsupported image library: ' . $this->library);
        }
    }
    
    /**
     * Process and validate color replacement parameters
     *
     * @param string $from_color Source color (hex)
     * @param string $to_color Target color (hex)
     * @param int $tolerance Color matching tolerance (0-100)
     * @return array Processed parameters
     */
    private function process_replace_colors_parameters(string $from_color, string $to_color, int $tolerance): array
    {
        // Parse and validate colors
        $from_rgb = $this->parse_hex_color($from_color);
        $to_rgb = $this->parse_hex_color($to_color);
        
        // Clamp tolerance to valid range
        $tolerance = max(0, min(100, $tolerance));
        
        return [
            'from_color' => $from_rgb,
            'to_color' => $to_rgb,
            'tolerance' => $tolerance
        ];
    }
    
    /**
     * Parse hex color to RGB array
     *
     * @param string $hex_color Hex color string (with or without #)
     * @return array RGB array with keys r, g, b
     */
    private function parse_hex_color(string $hex_color): array
    {
        // Remove # if present
        $hex_color = ltrim($hex_color, '#');
        
        // Ensure 6 characters
        if (strlen($hex_color) == 3) {
            $hex_color = $hex_color[0] . $hex_color[0] . 
                        $hex_color[1] . $hex_color[1] . 
                        $hex_color[2] . $hex_color[2];
        }
        
        // Default to black if invalid
        if (strlen($hex_color) != 6 || !ctype_xdigit($hex_color)) {
            $hex_color = '000000';
        }
        
        return [
            'r' => hexdec(substr($hex_color, 0, 2)),
            'g' => hexdec(substr($hex_color, 2, 2)),
            'b' => hexdec(substr($hex_color, 4, 2))
        ];
    }
}
