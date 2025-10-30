<?php

/**
 * JCOGS Image Pro - Border Parameter Package
 * ==========================================
 * Sophisticated form interface for border parameter
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

/**
 * Border Parameter Package
 * 
 * Provides sophisticated 3-field interface for border parameter instead of requiring
 * users to construct complex pipe-separated parameter strings manually.
 * 
 * Border Parameter Format (from JCOGS documentation):
 * border="<integer>[px|%]|[#]<three or six digit hex colour code>"
 * 
 * Examples:
 * - border="10|4a2d14" - 10px width with color #4a2d14
 * - border="10px|#DDD" - 10px width with color #DDD
 * - border="15px|rgb(220,240,260)" - 15px width with RGB color
 * 
 * Documentation: https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-border
 */
class BorderParameterPackage extends AbstractParameterPackage
{
    /**
     * Package identifier
     * 
     * @return string Package name
     */
    public function getName(): string 
    {
        return 'border_parameter_package';
    }

    /**
     * Get the display label for this parameter package
     * 
     * @return string Package label
     */
    public function getLabel(): string
    {
        return 'Border Parameters';
    }

    /**
     * Get the description for this parameter package
     * 
     * @return string Package description
     */
    public function getDescription(): string
    {
        return 'Border width and color controls for image framing';
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
        return ['border'];
    }

    /**
     * Get priority for this parameter package
     * Higher numbers = higher priority when multiple packages could handle the same parameter
     * 
     * @return int Priority level (higher = more specific, gets chosen first)
     */
    public function getPriority(): int
    {
        return 24; // Higher than TransformationalParameterPackage (20)
    }

    /**
     * Get form fields for border parameter
     * 
     * @param array $current_values Current parameter values  
     * @return array Flat associative array of form fields
     */
    protected function getPackageFormFields(array $current_values = []): array
    {
        // Get current border value
        $current_value = $current_values['border'] ?? '';
        
        // Parse existing border value (format: "width|color")
        $parsed = $this->_parse_border_value($current_value);
        
        return [
            'border_width' => [
                'type' => 'text',
                'label' => $this->lang('jcogs_img_pro_param_border_width_label', 'Border Width'),
                'desc' => $this->lang('jcogs_img_pro_param_border_width_desc', 'Width of the border (e.g., 10, 15px, 5%)'),
                'value' => $parsed['width'] ?? '',
                'placeholder' => $this->lang('jcogs_img_pro_param_border_width_placeholder', 'For example: 10px')
            ],
            
            'border_color' => [
                'type' => 'text',
                'label' => $this->lang('jcogs_img_pro_param_border_color_label', 'Border Color'),
                'desc' => $this->lang('jcogs_img_pro_param_border_color_desc', 'Color of the border. Supports hex codes (3, 4, 6, or 8 digit), CSS rgb()/rgba(), or color names. JCOGS automatically adds # prefix if omitted.'),
                'value' => $parsed['color'] ?? '',
                'placeholder' => $this->lang('jcogs_img_pro_param_border_color_placeholder', 'For example: 4a2d14, #DDD, rgb(220,240,260), rgba(255,128,0,0.8)')
            ]
        ];
    }

    /**
     * Process form data to generate border parameter value
     * 
     * @param string $parameter_name Parameter name (should be 'border')
     * @param array $form_data Form submission data
     * @return string Formatted border parameter value
     */
    public function processParameterFromForm(string $parameter_name, array $form_data): string 
    {
        // Always save border parameters regardless of enabled state
        // This allows users to configure border settings incrementally
        // and toggle border on/off while preserving their configuration
        
        $components = [];

        // Component 1: border_width (required for valid border)
        $width = trim($form_data['border_width'] ?? '');
        if (empty($width)) {
            return ''; // No width means no border to save
        }
        $components[] = $width;

        // Component 2: border_color (optional, defaults to #FFFFFF)
        $color = trim($form_data['border_color'] ?? '');
        if (!empty($color)) {
            // Remove # prefix if user included it (JCOGS can handle with or without)
            $color = ltrim($color, '#');
            $components[] = $color;
        }

        // Return pipe-separated border parameter
        return implode('|', $components);
    }

    /**
     * Parse border parameter value into components
     * 
     * @param mixed $value Current border value
     * @return array Parsed components
     */
    private function _parse_border_value($value): array
    {
        if (empty($value) || !is_string($value)) {
            return ['width' => '', 'color' => ''];
        }

        // Split on pipe character
        $parts = explode('|', $value, 2);
        
        return [
            'width' => $parts[0] ?? '',
            'color' => isset($parts[1]) ? ltrim($parts[1], '#') : ''
        ];
    }

    /**
     * Validate border parameter value
     * 
     * @param string $param_name Parameter name being validated
     * @param string $value Parameter value to validate
     * @return bool|string True if valid, error message if invalid
     */
    public function validateParameter(string $param_name, $value): bool|string
    {
        if ($param_name !== 'border') {
            return parent::validateParameter($param_name, $value);
        }

        $parameter_value = (string) $value;

        // Empty border parameter is valid (no border)
        if (empty(trim($parameter_value))) {
            return true;
        }

        // Split into pipe-separated components
        $parts = explode('|', $parameter_value, 2);
        
        // Need at least width
        if (empty(trim($parts[0] ?? ''))) {
            return 'Border parameter requires a width value (e.g., "10px|#000000")';
        }

        // Validate width format
        $width = trim($parts[0]);
        if (!preg_match('/^\d+(?:px|%)?$/', $width)) {
            return 'Border width must be a number optionally followed by "px" or "%". Found: ' . $width;
        }

        // Validate color if provided
        if (!empty($parts[1])) {
            $color = trim($parts[1]);
            
            // Remove leading # if present
            $color_clean = ltrim($color, '#');
            
            // Check for valid hex color (3, 4, 6, or 8 digits for alpha)
            if (!preg_match('/^[0-9a-fA-F]{3}([0-9a-fA-F]{1}|[0-9a-fA-F]{3}|[0-9a-fA-F]{5})?$/', $color_clean)) {
                // Check for RGB format
                if (!preg_match('/^rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)$/i', $color)) {
                    // Check for RGBA format
                    if (!preg_match('/^rgba\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*,\s*(?:0|1|0?\.\d+)\s*\)$/i', $color)) {
                        return 'Border color must be a valid hex color (#RGB, #RRGGBB, #RGBA, #RRGGBBAA), RGB format (rgb(r,g,b)), or RGBA format (rgba(r,g,b,a)). Found: ' . $color;
                    }
                }
            }
        }

        return true;
    }
}
