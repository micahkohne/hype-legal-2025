<?php

/**
 * JCOGS Image Pro - Rotate Filter
 * ================================
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
use Imagine\Filter\Basic\Rotate as ImagineRotate;
use Imagine\Image\Palette\RGB;

/**
 * Rotate Transformation Filter
 * 
 * Applies rotation transformation to images with background color support.
 */
class Rotate implements FilterInterface
{
    /**
     * @var string Rotation specification
     */
    private $rotate_spec;

    /**
     * Constructs Rotate filter.
     * 
     * @param string $rotate_spec Rotation specification (angle)
     */
    public function __construct(string $rotate_spec = '0')
    {
        $this->rotate_spec = $rotate_spec;
    }

    /**
     * Apply rotation transformation to image
     * 
     * @param ImageInterface $image Source image
     * @return ImageInterface Processed image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        $angle = (int) $this->rotate_spec;
        
        if ($angle === 0) {
            return $image;
        }
        
        // Handle background color
        $bg_color = $params['bg_color'] ?? '#ffffff';
        
        // Convert string color to ColorInterface if needed
        if (is_string($bg_color)) {
            $bg_color = $this->parse_color_string($bg_color);
        }
        
        $rotate_filter = new ImagineRotate($angle, $bg_color);
        return $rotate_filter->apply($image);
    }
    
    /**
     * Parse color string to ColorInterface
     * 
     * @param string $color_string Color in hex format (#ffffff) or color name
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
