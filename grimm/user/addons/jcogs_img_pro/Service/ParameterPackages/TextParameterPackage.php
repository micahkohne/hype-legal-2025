<?php

/**
 * JCOGS Image Pro - Text Parameter Package
 * ========================================
 * Sophisticated multi-field interface for text overlay parameters
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Parameter Package Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service\ParameterPackages;

use JCOGSDesign\JCOGSImagePro\Service\ParameterPackages\AbstractParameterPackage;

/**
 * Text Parameter Package
 * 
 * Provides sophisticated multi-field interface for complex text overlay parameters.
 * The text parameter supports 16 pipe-separated sub-parameters for complete text overlay control.
 * 
 * Text Parameter Format (pipe-separated):
 * 1. the_text (required) - Text content to overlay
 * 2. minimum_dimensions - min_width,min_height for text addition
 * 3. font_size - Size in pixels or points (add 'pt' suffix)
 * 4. line_height - Line spacing (pixels, points, or percentage)
 * 5. font_color - Text color (hex, rgb, rgba)
 * 6. font_src - Path to TTF font file relative to web root
 * 7. text_align - left|center|right alignment
 * 8. width_adjustment - Text box width constraint
 * 9. position - horizontal,vertical position (left|center|right, top|center|bottom)
 * 10. offset - horizontal,vertical offset from position
 * 11. opacity - Text opacity (0-100)
 * 12. shadow_color - Shadow color (hex, rgb, rgba)
 * 13. shadow_offset - Shadow horizontal,vertical offset
 * 14. shadow_opacity - Shadow opacity (0-100)
 * 15. text_box_bg_color - Background color for text box
 * 16. text_bg_color - Background color for text
 * 17. rotation - Text rotation in degrees (anti-clockwise)
 */
class TextParameterPackage extends AbstractParameterPackage
{
    /**
     * Package identifier
     * 
     * @return string Package name
     */
    public function getName(): string 
    {
        return 'text_parameter_package';
    }

    /**
     * Get the display label for this parameter package
     * 
     * @return string Package label
     */
    public function getLabel(): string
    {
        return 'Text Parameters';
    }

    /**
     * Get the description for this parameter package
     * 
     * @return string Package description
     */
    public function getDescription(): string
    {
        return 'Sophisticated text overlay parameters with comprehensive typography and positioning controls';
    }

    /**
     * Get the category for this parameter package
     * 
     * @return string Package category
     */
    public function getCategory(): string
    {
        return 'transformational';
    }

    /**
     * Parameters handled by this package
     * 
     * @return array List of parameter names
     */
    public function getParameters(): array 
    {
        return ['text'];
    }

    /**
     * Priority for text parameter package
     * Higher priority than general transformational package
     * 
     * @return int Priority order (lower numbers = higher priority)
     */
    public function getPriority(): int
    {
        return 25;  // Higher priority than TransformationalParameterPackage (30)
    }

    /**
     * Generate form fields for text parameter
     * 
     * @param array $current_values Current parameter values
     * @return array Field configuration array
     */
    protected function getPackageFormFields(array $current_values = []): array 
    {
        // Parse current text parameter value
        $text_components = $this->parseTextParameter($current_values['text'] ?? '');
        
        return [
            // Enable/disable toggle
            'text_enabled' => [
                'type' => 'hidden',
                'value' => !empty($text_components['the_text']) ? 'yes' : 'no',
                'label' => 'Text Overlay Enabled'
            ],
            
            // Core text content
            'text_content' => [
                'type' => 'textarea',
                'value' => $text_components['the_text'] ?? '',
                'label' => 'Text Content',
                'desc' => 'The text to overlay on the image. Line breaks are supported.',
                'example' => 'Copyright 2025 • All Rights Reserved',
                'required' => true
            ],
            
            // Minimum dimensions for text addition
            'text_min_width' => [
                'type' => 'text',
                'value' => $text_components['min_width'] ?? '0',
                'label' => 'Minimum Image Width',
                'desc' => 'Minimum width the image must have for text to be added (pixels or %).',
                'example' => '100px or 50%'
            ],
            
            'text_min_height' => [
                'type' => 'text',
                'value' => $text_components['min_height'] ?? '0',
                'label' => 'Minimum Image Height',
                'desc' => 'Minimum height the image must have for text to be added (pixels or %).',
                'example' => '100px or 50%'
            ],
            
            // Typography settings
            'text_font_size' => [
                'type' => 'text',
                'value' => $text_components['font_size'] ?? '12px',
                'label' => 'Font Size',
                'desc' => 'Font size in pixels (px) or points (pt). Default: 12px.',
                'example' => '16px or 14pt'
            ],
            
            'text_line_height' => [
                'type' => 'text',
                'value' => $text_components['line_height'] ?? '1.25',
                'label' => 'Line Height',
                'desc' => 'Line spacing multiplier or pixel value. Default: 1.25 (125%).',
                'example' => '1.5 or 18px or 120%'
            ],
            
            'text_font_color' => [
                'type' => 'text',
                'value' => $text_components['font_color'] ?? '#FFFFFF',
                'label' => 'Text Color',
                'desc' => 'Text color in hex, rgb(), or rgba() format. Default: white.',
                'example' => '#FF0000 or rgb(255,0,0) or rgba(255,0,0,0.8)'
            ],
            
            'text_font_src' => [
                'type' => 'text',
                'value' => $text_components['font_src'] ?? '',
                'label' => 'Font Path',
                'desc' => 'Relative path from webroot to font file (e.g., fonts/arial.ttf). Leave empty to use default Voces Regular.',
                'placeholder' => 'fonts/your-font.ttf'
            ],
            
            'text_align' => [
                'type' => 'select',
                'value' => $text_components['text_align'] ?? 'center',
                'label' => 'Text Alignment',
                'desc' => 'How text is aligned within the text box.',
                'choices' => [
                    'left' => 'Left aligned',
                    'center' => 'Center aligned',
                    'right' => 'Right aligned'
                ]
            ],
            
            // Layout and positioning
            'text_width_adjustment' => [
                'type' => 'text',
                'value' => $text_components['width_adjustment'] ?? '',
                'label' => 'Text Box Width',
                'desc' => 'Width constraint for text box. Positive = width, negative = reduction from image width.',
                'example' => '40% or -20% or 300px'
            ],
            
            'text_position_horizontal' => [
                'type' => 'select',
                'value' => $text_components['position_horizontal'] ?? 'center',
                'label' => 'Horizontal Position',
                'desc' => 'Horizontal position of text box on image.',
                'choices' => [
                    'left' => 'Left edge',
                    'center' => 'Center',
                    'right' => 'Right edge'
                ]
            ],
            
            'text_position_vertical' => [
                'type' => 'select',
                'value' => $text_components['position_vertical'] ?? 'center',
                'label' => 'Vertical Position',
                'desc' => 'Vertical position of text box on image.',
                'choices' => [
                    'top' => 'Top edge',
                    'center' => 'Center',
                    'bottom' => 'Bottom edge'
                ]
            ],
            
            'text_offset_horizontal' => [
                'type' => 'text',
                'value' => $text_components['offset_horizontal'] ?? '0',
                'label' => 'Horizontal Offset',
                'desc' => 'Fine-tune horizontal position with pixel or percentage offset.',
                'example' => '10px or -5% or 0'
            ],
            
            'text_offset_vertical' => [
                'type' => 'text',
                'value' => $text_components['offset_vertical'] ?? '0',
                'label' => 'Vertical Offset',
                'desc' => 'Fine-tune vertical position with pixel or percentage offset.',
                'example' => '10px or -5% or 0'
            ],
            
            // Visual effects
            'text_opacity' => [
                'type' => 'text',
                'value' => $text_components['opacity'] ?? '100',
                'label' => 'Text Opacity',
                'desc' => 'Text opacity from 0 (transparent) to 100 (opaque). Default: 100.',
                'example' => '80'
            ],
            
            'text_shadow_color' => [
                'type' => 'text',
                'value' => $text_components['shadow_color'] ?? '',
                'label' => 'Shadow Color',
                'desc' => 'Color for text shadow. Leave empty for no shadow.',
                'example' => '#000000 or rgba(0,0,0,0.5)'
            ],
            
            'text_shadow_offset_horizontal' => [
                'type' => 'text',
                'value' => $text_components['shadow_offset_horizontal'] ?? '1',
                'label' => 'Shadow Horizontal Offset',
                'desc' => 'Horizontal shadow offset in pixels.',
                'example' => '2'
            ],
            
            'text_shadow_offset_vertical' => [
                'type' => 'text',
                'value' => $text_components['shadow_offset_vertical'] ?? '1',
                'label' => 'Shadow Vertical Offset',
                'desc' => 'Vertical shadow offset in pixels.',
                'example' => '2'
            ],
            
            'text_shadow_opacity' => [
                'type' => 'text',
                'value' => $text_components['shadow_opacity'] ?? '100',
                'label' => 'Shadow Opacity',
                'desc' => 'Shadow opacity from 0 (transparent) to 100 (opaque).',
                'example' => '60'
            ],
            
            // Background colors
            'text_box_bg_color' => [
                'type' => 'text',
                'value' => $text_components['text_box_bg_color'] ?? '',
                'label' => 'Text Box Background',
                'desc' => 'Background color for the entire text box area. Leave empty for transparent.',
                'example' => 'rgba(0,0,0,0.3)'
            ],
            
            'text_bg_color' => [
                'type' => 'text',
                'value' => $text_components['text_bg_color'] ?? '',
                'label' => 'Text Background',
                'desc' => 'Background color behind the text characters. Leave empty for transparent.',
                'example' => 'rgba(255,255,255,0.8)'
            ],
            
            // Rotation
            'text_rotation' => [
                'type' => 'text',
                'value' => $text_components['rotation'] ?? '0',
                'label' => 'Text Rotation',
                'desc' => 'Rotation angle in degrees (anti-clockwise). Default: 0 (no rotation).',
                'example' => '45 or -30'
            ]
        ];
    }

    /**
     * Process form data and build text parameter string
     * 
     * @param string $parameter_name Parameter being processed
     * @param array $form_data Form submission data
     * @return string Processed parameter value
     */
    public function processParameterFromForm(string $parameter_name, array $form_data): string 
    {
        if ($parameter_name !== 'text') {
            return '';
        }

        // Check if text is enabled
        if (empty($form_data['text_content'])) {
            return '';
        }

        // Build text parameter components
        $components = [];
        
        // 1. Text content (required)
        $components[] = $form_data['text_content'] ?? '';
        
        // 2. Minimum dimensions
        $min_width = $form_data['text_min_width'] ?? '0';
        $min_height = $form_data['text_min_height'] ?? '0';
        $components[] = $min_width . ',' . $min_height;
        
        // 3. Font size
        $components[] = $form_data['text_font_size'] ?? '12px';
        
        // 4. Line height
        $components[] = $form_data['text_line_height'] ?? '1.25';
        
        // 5. Font color
        $components[] = $form_data['text_font_color'] ?? '#FFFFFF';
        
        // 6. Font source
        $components[] = $form_data['text_font_src'] ?? '';
        
        // 7. Text alignment
        $components[] = $form_data['text_align'] ?? 'center';
        
        // 8. Width adjustment
        $components[] = $form_data['text_width_adjustment'] ?? '';
        
        // 9. Position
        $pos_h = $form_data['text_position_horizontal'] ?? 'center';
        $pos_v = $form_data['text_position_vertical'] ?? 'center';
        $components[] = $pos_h . ',' . $pos_v;
        
        // 10. Offset
        $offset_h = $form_data['text_offset_horizontal'] ?? '0';
        $offset_v = $form_data['text_offset_vertical'] ?? '0';
        $components[] = $offset_h . ',' . $offset_v;
        
        // 11. Opacity
        $components[] = $form_data['text_opacity'] ?? '100';
        
        // 12. Shadow color
        $components[] = $form_data['text_shadow_color'] ?? '';
        
        // 13. Shadow offset
        $shadow_h = $form_data['text_shadow_offset_horizontal'] ?? '1';
        $shadow_v = $form_data['text_shadow_offset_vertical'] ?? '1';
        $components[] = $shadow_h . ',' . $shadow_v;
        
        // 14. Shadow opacity
        $components[] = $form_data['text_shadow_opacity'] ?? '100';
        
        // 15. Text box background color
        $components[] = $form_data['text_box_bg_color'] ?? '';
        
        // 16. Text background color
        $components[] = $form_data['text_bg_color'] ?? '';
        
        // 17. Rotation
        $components[] = $form_data['text_rotation'] ?? '0';

        // Join with pipe separator, trimming empty trailing components
        $parameter_string = implode('|', $components);
        
        // Remove trailing empty components
        $parameter_string = rtrim($parameter_string, '|');
        
        return $parameter_string;
    }

    /**
     * Parse existing text parameter into components
     * 
     * @param string $text_parameter Text parameter string
     * @return array Parsed components
     */
    private function parseTextParameter(string $text_parameter): array 
    {
        if (empty($text_parameter)) {
            return [];
        }

        $parts = explode('|', $text_parameter);
        $components = [];

        // 1. Text content
        $components['the_text'] = $parts[0] ?? '';
        
        // 2. Minimum dimensions
        if (isset($parts[1])) {
            $min_dims = explode(',', $parts[1]);
            $components['min_width'] = $min_dims[0] ?? '0';
            $components['min_height'] = $min_dims[1] ?? '0';
        }
        
        // 3-17. Other components
        $components['font_size'] = $parts[2] ?? '12px';
        $components['line_height'] = $parts[3] ?? '1.25';
        $components['font_color'] = $parts[4] ?? '#FFFFFF';
        $components['font_src'] = $parts[5] ?? '';
        $components['text_align'] = $parts[6] ?? 'center';
        $components['width_adjustment'] = $parts[7] ?? '';
        
        // Position
        if (isset($parts[8])) {
            $position = explode(',', $parts[8]);
            $components['position_horizontal'] = $position[0] ?? 'center';
            $components['position_vertical'] = $position[1] ?? 'center';
        }
        
        // Offset
        if (isset($parts[9])) {
            $offset = explode(',', $parts[9]);
            $components['offset_horizontal'] = $offset[0] ?? '0';
            $components['offset_vertical'] = $offset[1] ?? '0';
        }
        
        $components['opacity'] = $parts[10] ?? '100';
        $components['shadow_color'] = $parts[11] ?? '';
        
        // Shadow offset
        if (isset($parts[12])) {
            $shadow_offset = explode(',', $parts[12]);
            $components['shadow_offset_horizontal'] = $shadow_offset[0] ?? '1';
            $components['shadow_offset_vertical'] = $shadow_offset[1] ?? '1';
        }
        
        $components['shadow_opacity'] = $parts[13] ?? '100';
        $components['text_box_bg_color'] = $parts[14] ?? '';
        $components['text_bg_color'] = $parts[15] ?? '';
        $components['rotation'] = $parts[16] ?? '0';

        return $components;
    }

    /**
     * Validate text parameter value
     * 
     * @param string $param_name Parameter name being validated
     * @param string $value Parameter value to validate
     * @return bool|string True if valid, error message if invalid
     */
    public function validateParameter(string $param_name, $value): bool|string
    {
        if ($param_name !== 'text') {
            return parent::validateParameter($param_name, $value);
        }

        $parameter_value = (string) $value;

        // Empty text parameter is valid (no text overlay)
        if (empty(trim($parameter_value))) {
            return true;
        }

        // Split into pipe-separated components
        $parts = explode('|', $parameter_value);
        
        // At minimum, we need the text content (first part)
        if (empty(trim($parts[0] ?? ''))) {
            return 'Text parameter requires text content as the first value (e.g., "Hello World|...")';
        }

        // Validate text alignment if provided (part 7)
        if (!empty($parts[6])) {
            $valid_alignments = ['left', 'center', 'right'];
            if (!in_array(strtolower($parts[6]), $valid_alignments)) {
                return 'Text alignment must be one of: left, center, right. Found: ' . $parts[6];
            }
        }

        // Validate position if provided (part 9)
        if (!empty($parts[8])) {
            $position_parts = explode(',', $parts[8]);
            if (count($position_parts) === 2) {
                $horizontal = strtolower(trim($position_parts[0]));
                $vertical = strtolower(trim($position_parts[1]));
                
                $valid_horizontal = ['left', 'center', 'right'];
                $valid_vertical = ['top', 'center', 'bottom'];
                
                if (!in_array($horizontal, $valid_horizontal)) {
                    return 'Text horizontal position must be one of: left, center, right. Found: ' . $horizontal;
                }
                
                if (!in_array($vertical, $valid_vertical)) {
                    return 'Text vertical position must be one of: top, center, bottom. Found: ' . $vertical;
                }
            }
        }

        // Validate opacity values (parts 11 and 14)
        foreach ([10 => 'text opacity', 13 => 'shadow opacity'] as $index => $name) {
            if (!empty($parts[$index])) {
                $opacity = trim($parts[$index]);
                if (!is_numeric($opacity) || $opacity < 0 || $opacity > 100) {
                    return ucfirst($name) . ' must be a number between 0 and 100. Found: ' . $opacity;
                }
            }
        }

        // Validate rotation if provided (part 17)
        if (!empty($parts[16])) {
            $rotation = trim($parts[16]);
            if (!is_numeric($rotation)) {
                return 'Text rotation must be a number (degrees). Found: ' . $rotation;
            }
        }

        return true;
    }
}
