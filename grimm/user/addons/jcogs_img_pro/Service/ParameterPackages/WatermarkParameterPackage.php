<?php

/**
 * JCOGS Image Pro - Watermark Parameter Package
 * ==============================================
 * Sophisticated multi-field interface for watermark parameters
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
 * Watermark Parameter Package
 * 
 * Provides a comprehensive form interface for the watermark parameter with 14 specialized fields
 * based on the official JCOGS Image documentation format:
 * watermark='watermark_src|min_width,min_height|opacity|position_horizontal,position_vertical|offset_x,offset_y|rotation'
 * 
 * Supports watermark image overlay with position control, opacity, rotation, and repeat patterns.
 */
class WatermarkParameterPackage extends AbstractParameterPackage
{
    /**
     * Get package identifier
     * 
     * @return string Unique package name identifier
     */
    public function getName(): string 
    {
        return 'watermark_parameter_package';
    }

    /**
     * Get the display label for this parameter package
     * 
     * @return string Human-readable package label
     */
    public function getLabel(): string
    {
        return 'Watermark Parameters';
    }

    /**
     * Get the description for this parameter package
     * 
     * @return string Package description for display purposes
     */
    public function getDescription(): string
    {
        return 'Comprehensive watermark overlay parameters with position control, opacity, rotation, and repeat patterns';
    }

    /**
     * Get the category for this parameter package
     * 
     * @return string Package category classification
     */
    public function getCategory(): string
    {
        return 'transformational';
    }

    /**
     * Get parameters handled by this package
     * 
     * @return array List of parameter names this package handles
     */
    public function getParameters(): array 
    {
        return ['watermark'];
    }

    /**
     * Get package priority for parameter package selection
     * 
     * Higher priority (lower number) packages are preferred for handling parameters.
     * Set to 22 to have higher priority than TransformationalParameterPackage (30).
     * 
     * @return int Priority level (lower = higher priority)
     */
    public function getPriority(): int
    {
        return 22; // Higher priority than TransformationalParameterPackage (30)
    }

    /**
     * Generate comprehensive form fields for watermark parameter
     * 
     * Creates 14 specialized fields for complete watermark control including
     * source image, positioning, opacity, rotation, and repeat patterns.
     * 
     * @param array $current_values Current parameter values for form population
     * @return array Form field definitions for EE CP Form Service
     */
    protected function getPackageFormFields(array $current_values = []): array
    {
        // Get current watermark value from current_values array
        $currentValue = $current_values['watermark'] ?? '';
        
        // Parse current watermark value into components
        $components = $this->parseWatermarkValue($currentValue);
        
        return [
            // Watermark source image
            'watermark_src' => [
                'type' => 'text',
                'value' => $components['watermark_src'] ?? '',
                'label' => 'Watermark Image Source',
                'desc' => 'Path to the watermark image file (local or remote URL)',
                'example' => '/media/watermarks/logo.png or https://example.com/watermark.webp',
                'required' => true
            ],
            
            // Minimum dimensions
            'watermark_min_width' => [
                'type' => 'text',
                'value' => $components['min_width'] ?? '0',
                'label' => 'Minimum Image Width',
                'desc' => 'Minimum width the image must have for watermark to be applied',
                'example' => '300 (pixels) or 25% (percentage of image width)'
            ],
            
            'watermark_min_height' => [
                'type' => 'text',
                'value' => $components['min_height'] ?? '0',
                'label' => 'Minimum Image Height',
                'desc' => 'Minimum height the image must have for watermark to be applied',
                'example' => '200 (pixels) or 30% (percentage of image height)'
            ],
            
            // Opacity
            'watermark_opacity' => [
                'type' => 'text',
                'value' => $components['opacity'] ?? '100',
                'label' => 'Watermark Opacity',
                'desc' => 'Transparency level of the watermark overlay (0-100)',
                'example' => '100 (fully opaque), 50 (semi-transparent), 25 (mostly transparent)'
            ],
            
            // Position controls
            'watermark_position_horizontal' => [
                'type' => 'select',
                'choices' => [
                    'left' => 'Left',
                    'center' => 'Center',
                    'right' => 'Right',
                    'repeat' => 'Repeat Pattern'
                ],
                'value' => $components['position_horizontal'] ?? 'center',
                'label' => 'Horizontal Position',
                'desc' => 'Horizontal positioning of the watermark on the image',
                'example' => 'Choose "repeat" to create a tiled watermark pattern'
            ],
            
            'watermark_position_vertical' => [
                'type' => 'select',
                'choices' => [
                    'top' => 'Top',
                    'center' => 'Center',
                    'bottom' => 'Bottom'
                ],
                'value' => $components['position_vertical'] ?? 'center',
                'label' => 'Vertical Position',
                'desc' => 'Vertical positioning of the watermark on the image',
                'example' => '"bottom" for footer watermarks, "center" for centered overlays'
            ],
            
            // Repeat pattern spacing
            'watermark_repeat_horizontal' => [
                'type' => 'text',
                'value' => $components['repeat_horizontal'] ?? '',
                'label' => 'Repeat Horizontal Spacing',
                'desc' => 'Horizontal spacing between repeated watermarks (only used with repeat pattern)',
                'example' => '50% (half watermark width), 100px (100 pixels), leave empty for default'
            ],
            
            'watermark_repeat_vertical' => [
                'type' => 'text',
                'value' => $components['repeat_vertical'] ?? '',
                'label' => 'Repeat Vertical Spacing',
                'desc' => 'Vertical spacing between repeated watermarks (only used with repeat pattern)',
                'example' => '0 (no vertical spacing), 20px (20 pixel spacing)'
            ],
            
            // Position offset
            'watermark_offset_horizontal' => [
                'type' => 'text',
                'value' => $components['offset_horizontal'] ?? '0',
                'label' => 'Horizontal Offset',
                'desc' => 'Fine-tune watermark position with horizontal adjustment',
                'example' => '-20px (move left), 10% (move right), 0 (no adjustment)'
            ],
            
            'watermark_offset_vertical' => [
                'type' => 'text',
                'value' => $components['offset_vertical'] ?? '0',
                'label' => 'Vertical Offset',
                'desc' => 'Fine-tune watermark position with vertical adjustment',
                'example' => '10px (move down), -5% (move up), 0 (no adjustment)'
            ],
            
            // Rotation
            'watermark_rotation' => [
                'type' => 'text',
                'value' => $components['rotation'] ?? '0',
                'label' => 'Watermark Rotation',
                'desc' => 'Rotate the watermark image (anti-clockwise degrees)',
                'example' => '45 (45° rotation), -30 (clockwise), 0 (no rotation)'
            ],
            
            // Enable/disable toggle
            'watermark_enabled' => [
                'type' => 'yes_no',
                'value' => !empty($components['watermark_src']) ? 'y' : 'n',
                'label' => 'Watermark Enabled',
                'desc' => 'Enable or disable watermark application',
                'example' => 'Enable to apply watermark, disable to skip watermarking'
            ]
        ];
    }

    /**
     * Parse watermark parameter value into individual components
     * 
     * Breaks down pipe-separated watermark value into constituent parts
     * for form field population and component-based editing.
     * 
     * @param mixed $value Raw watermark parameter value
     * @return array Parsed components with named keys
     */
    private function parseWatermarkValue($value): array
    {
        if (empty($value)) {
            return [];
        }

        $parts = explode('|', (string)$value);
        $components = [];

        // Component 1: watermark_src
        if (isset($parts[0]) && !empty($parts[0])) {
            $components['watermark_src'] = $parts[0];
        }

        // Component 2: minimum_dimensions (min_width,min_height)
        if (isset($parts[1]) && !empty($parts[1])) {
            $dimensions = explode(',', $parts[1]);
            $components['min_width'] = $dimensions[0] ?? '0';
            $components['min_height'] = $dimensions[1] ?? '0';
        }

        // Component 3: opacity
        if (isset($parts[2]) && !empty($parts[2])) {
            $components['opacity'] = $parts[2];
        }

        // Component 4: position (horizontal,vertical or repeat with spacing)
        if (isset($parts[3]) && !empty($parts[3])) {
            $position = explode(',', $parts[3]);
            $horizontalPart = $position[0] ?? 'center';
            
            // Check if it's a repeat pattern with spacing
            if (strpos($horizontalPart, 'repeat') === 0) {
                $components['position_horizontal'] = 'repeat';
                
                // Extract repeat spacing if present
                $repeatParts = explode(',', $parts[3]);
                if (count($repeatParts) >= 2) {
                    // Parse repeat,spacing format
                    $spacingParts = array_slice($repeatParts, 1);
                    $components['repeat_horizontal'] = $spacingParts[0] ?? '';
                    $components['repeat_vertical'] = $spacingParts[1] ?? '';
                }
            } else {
                $components['position_horizontal'] = $horizontalPart;
                $components['position_vertical'] = $position[1] ?? 'center';
            }
        }

        // Component 5: offset (offset_x,offset_y)
        if (isset($parts[4]) && !empty($parts[4])) {
            $offset = explode(',', $parts[4]);
            $components['offset_horizontal'] = $offset[0] ?? '0';
            $components['offset_vertical'] = $offset[1] ?? '0';
        }

        // Component 6: rotation
        if (isset($parts[5]) && !empty($parts[5])) {
            $components['rotation'] = $parts[5];
        }

        return $components;
    }

    /**
     * Process form data to generate watermark parameter value
     * 
     * Converts form submission data back into pipe-separated watermark
     * parameter format. Preserves configuration regardless of enabled state
     * to allow incremental configuration and toggling.
     * 
     * @param string $parameter_name Parameter name (should be 'watermark')
     * @param array $form_data Form submission data from control panel
     * @return string Formatted watermark parameter value
     */
    public function processParameterFromForm(string $parameter_name, array $form_data): string 
    {
        // Always save watermark parameters regardless of enabled state or source
        // This allows users to configure watermark settings incrementally
        // and toggle watermark on/off while preserving their configuration
        
        $components = [];

        // Component 1: watermark_src (required)
        $components[] = $form_data['watermark_src'];

        // Component 2: minimum_dimensions
        $minWidth = !empty($form_data['watermark_min_width']) ? $form_data['watermark_min_width'] : '0';
        $minHeight = !empty($form_data['watermark_min_height']) ? $form_data['watermark_min_height'] : '0';
        $components[] = $minWidth . ',' . $minHeight;

        // Component 3: opacity
        $opacity = !empty($form_data['watermark_opacity']) ? $form_data['watermark_opacity'] : '100';
        $components[] = $opacity;

        // Component 4: position (handle repeat pattern specially)
        $horizontal = $form_data['watermark_position_horizontal'] ?? 'center';
        if ($horizontal === 'repeat') {
            $repeatHorizontal = $form_data['watermark_repeat_horizontal'] ?? '';
            $repeatVertical = $form_data['watermark_repeat_vertical'] ?? '';
            
            if (!empty($repeatHorizontal) || !empty($repeatVertical)) {
                $components[] = 'repeat,' . $repeatHorizontal . ',' . $repeatVertical;
            } else {
                $components[] = 'repeat';
            }
        } else {
            $vertical = $form_data['watermark_position_vertical'] ?? 'center';
            $components[] = $horizontal . ',' . $vertical;
        }

        // Component 5: offset
        $offsetHorizontal = !empty($form_data['watermark_offset_horizontal']) ? $form_data['watermark_offset_horizontal'] : '0';
        $offsetVertical = !empty($form_data['watermark_offset_vertical']) ? $form_data['watermark_offset_vertical'] : '0';
        $components[] = $offsetHorizontal . ',' . $offsetVertical;

        // Component 6: rotation
        $rotation = !empty($form_data['watermark_rotation']) ? $form_data['watermark_rotation'] : '0';
        $components[] = $rotation;

        return implode('|', $components);
    }

    /**
     * Validate watermark parameter value
     * 
     * Validates watermark parameter format, component values, and constraints.
     * Ensures source paths are provided, positions are valid, opacity is in range,
     * and rotation values are numeric.
     * 
     * @param string $param_name Parameter name being validated
     * @param mixed $value Parameter value to validate
     * @return bool|string True if valid, error message if invalid
     */
    public function validateParameter(string $param_name, $value): bool|string
    {
        if ($param_name !== 'watermark') {
            return parent::validateParameter($param_name, $value);
        }

        $parameter_value = (string) $value;

        // Empty watermark parameter is valid (no watermark)
        if (empty(trim($parameter_value))) {
            return true;
        }

        // Split into pipe-separated components
        $parts = explode('|', $parameter_value);
        
        // At minimum, we need the watermark source (first part)
        if (empty(trim($parts[0] ?? ''))) {
            return 'Watermark parameter requires a source image path as the first value (e.g., "/path/to/watermark.png|...")';
        }

        // Validate position if provided (part 3)
        if (!empty($parts[2])) {
            $position_parts = explode(',', $parts[2]);
            if (count($position_parts) === 2) {
                $horizontal = strtolower(trim($position_parts[0]));
                $vertical = strtolower(trim($position_parts[1]));
                
                $valid_horizontal = ['left', 'center', 'right'];
                $valid_vertical = ['top', 'center', 'bottom'];
                
                if (!in_array($horizontal, $valid_horizontal)) {
                    return 'Watermark horizontal position must be one of: left, center, right. Found: ' . $horizontal;
                }
                
                if (!in_array($vertical, $valid_vertical)) {
                    return 'Watermark vertical position must be one of: top, center, bottom. Found: ' . $vertical;
                }
            }
        }

        // Validate opacity if provided (part 4)
        if (!empty($parts[3])) {
            $opacity = trim($parts[3]);
            if (!is_numeric($opacity) || $opacity < 0 || $opacity > 100) {
                return 'Watermark opacity must be a number between 0 and 100. Found: ' . $opacity;
            }
        }

        // Validate rotation if provided (part 6)
        if (!empty($parts[5])) {
            $rotation = trim($parts[5]);
            if (!is_numeric($rotation)) {
                return 'Watermark rotation must be a number (degrees). Found: ' . $rotation;
            }
        }

        return true;
    }
}
