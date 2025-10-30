<?php

/**
 * JCOGS Image Pro - Rounded Corners Parameter Package
 * ==================================================
 * Sophisticated form interface for rounded corners parameter
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
 * Rounded Corners Parameter Package
 * 
 * Provides sophisticated 5-field interface for rounded corners parameter instead of requiring
 * users to construct complex pipe-separated parameter strings manually.
 * 
 * Rounded Corners Parameter Format (from JCOGS documentation):
 * rounded_corners="[all|tl|tr|bl|br],<integer>[,<colour>]…"
 * 
 * Examples:
 * - rounded_corners="all,20" - 20px radius on all corners
 * - rounded_corners="tl,20|br,40px|tr,25%" - Different radius for different corners
 * - rounded_corners="all,20px|br,30%" - Set all corners to 20px, then override bottom-right to 30%
 * 
 * Documentation: https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-rounded-corners
 */
class RoundedCornersParameterPackage extends AbstractParameterPackage
{
    /**
     * Package identifier
     * 
     * @return string Package name
     */
    public function getName(): string 
    {
        return 'rounded_corners_parameter_package';
    }

    /**
     * Get the display label for this parameter package
     * 
     * @return string Package label
     */
    public function getLabel(): string
    {
        return 'Rounded Corners Parameters';
    }

    /**
     * Get the description for this parameter package
     * 
     * @return string Package description
     */
    public function getDescription(): string
    {
        return 'Corner radius controls for rounded corner effects on images';
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
        return ['rounded_corners'];
    }

    /**
     * Priority for rounded corners parameter package
     * Higher priority than general transformational package
     * 
     * @return int Priority order (lower numbers = higher priority)
     */
    public function getPriority(): int
    {
        return 23; // Higher than TransformationalParameterPackage (20)
    }

    /**
     * Get form fields for rounded corners parameter
     * 
     * @param array $current_values Current parameter values  
     * @return array Flat associative array of form fields
     */
    protected function getPackageFormFields(array $current_values = []): array
    {
        // Get current rounded_corners value
        $current_value = $current_values['rounded_corners'] ?? '';
        
        // Parse existing rounded_corners value
        $parsed = $this->_parse_rounded_corners_value($current_value);
        
        return [
            'rounded_corners_all' => [
                'type' => 'text',
                'label' => 'All Corners Radius',
                'desc' => 'Set the same radius for all four corners (e.g., 20, 15px, 5%)',
                'value' => $parsed['all'] ?? '',
                'placeholder' => 'For example: 20px'
            ],
            
            'rounded_corners_top_left' => [
                'type' => 'text',
                'label' => 'Top-Left Corner Radius',
                'desc' => 'Radius for the top-left corner (overrides "All Corners" if set)',
                'value' => $parsed['tl'] ?? '',
                'placeholder' => 'For example: 25px'
            ],
            
            'rounded_corners_top_right' => [
                'type' => 'text',
                'label' => 'Top-Right Corner Radius',
                'desc' => 'Radius for the top-right corner (overrides "All Corners" if set)',
                'value' => $parsed['tr'] ?? '',
                'placeholder' => 'For example: 25px'
            ],
            
            'rounded_corners_bottom_left' => [
                'type' => 'text',
                'label' => 'Bottom-Left Corner Radius',
                'desc' => 'Radius for the bottom-left corner (overrides "All Corners" if set)',
                'value' => $parsed['bl'] ?? '',
                'placeholder' => 'For example: 25px'
            ],
            
            'rounded_corners_bottom_right' => [
                'type' => 'text',
                'label' => 'Bottom-Right Corner Radius',
                'desc' => 'Radius for the bottom-right corner (overrides "All Corners" if set)',
                'value' => $parsed['br'] ?? '',
                'placeholder' => 'For example: 25px'
            ]
        ];
    }

    /**
     * Process form data to generate rounded_corners parameter value
     * 
     * @param string $parameter_name Parameter name (should be 'rounded_corners')
     * @param array $form_data Form submission data
     * @return string Formatted rounded_corners parameter value
     */
    public function processParameterFromForm(string $parameter_name, array $form_data): string 
    {
        // Always save rounded_corners parameters for flexible configuration
        
        $components = [];

        // Check if "All Corners" is set first
        $all_corners = trim($form_data['rounded_corners_all'] ?? '');
        if (!empty($all_corners)) {
            $components[] = 'all,' . $all_corners;
        }

        // Then check individual corner overrides
        $corners = [
            'tl' => trim($form_data['rounded_corners_top_left'] ?? ''),
            'tr' => trim($form_data['rounded_corners_top_right'] ?? ''),
            'bl' => trim($form_data['rounded_corners_bottom_left'] ?? ''),
            'br' => trim($form_data['rounded_corners_bottom_right'] ?? '')
        ];

        foreach ($corners as $corner_code => $radius) {
            if (!empty($radius)) {
                $components[] = $corner_code . ',' . $radius;
            }
        }

        // If no corners are specified, return empty
        if (empty($components)) {
            return '';
        }

        // Return pipe-separated rounded_corners parameter
        return implode('|', $components);
    }

    /**
     * Parse rounded_corners parameter value into components
     * 
     * @param mixed $value Current rounded_corners value
     * @return array Parsed components
     */
    private function _parse_rounded_corners_value($value): array
    {
        if (empty($value) || !is_string($value)) {
            return ['all' => '', 'tl' => '', 'tr' => '', 'bl' => '', 'br' => ''];
        }

        $result = ['all' => '', 'tl' => '', 'tr' => '', 'bl' => '', 'br' => ''];

        // Split on pipe character to get individual corner definitions
        $parts = explode('|', $value);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            // Split each part on comma to get corner and radius
            $corner_parts = explode(',', $part, 2);
            if (count($corner_parts) < 2) continue;

            $corner = trim($corner_parts[0]);
            $radius = trim($corner_parts[1]);

            // Map corner codes to result array
            if (isset($result[$corner])) {
                $result[$corner] = $radius;
            }
        }
        
        return $result;
    }

    /**
     * Validate rounded corners parameter value
     * 
     * @param string $param_name Parameter name being validated
     * @param string $value Parameter value to validate
     * @return bool|string True if valid, error message if invalid
     */
    public function validateParameter(string $param_name, $value): bool|string
    {
        if ($param_name !== 'rounded_corners') {
            return parent::validateParameter($param_name, $value);
        }

        $parameter_value = (string) $value;

        // Empty rounded corners parameter is valid (no rounded corners)
        if (empty(trim($parameter_value))) {
            return true;
        }

        // Split into pipe-separated components
        $parts = explode('|', $parameter_value);
        
        $valid_corners = ['all', 'tl', 'tr', 'bl', 'br'];
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;
            
            // Each part should be in format: corner,radius[,color]
            $corner_parts = explode(',', $part);
            
            if (count($corner_parts) < 2) {
                return 'Each rounded corners component must have format "corner,radius" (e.g., "all,20"). Found: ' . $part;
            }
            
            // Validate corner identifier
            $corner = strtolower(trim($corner_parts[0]));
            if (!in_array($corner, $valid_corners)) {
                return 'Corner identifier must be one of: all, tl, tr, bl, br. Found: ' . $corner;
            }
            
            // Validate radius value
            $radius = trim($corner_parts[1]);
            if (!preg_match('/^\d+(?:px|%)?$/', $radius)) {
                return 'Radius value must be a number optionally followed by "px" or "%". Found: ' . $radius;
            }
            
            // Validate color if provided (optional third parameter)
            if (!empty($corner_parts[2])) {
                $color = trim($corner_parts[2]);
                $color_clean = ltrim($color, '#');
                
                // Check for valid hex color (3 or 6 digits)
                if (!preg_match('/^[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $color_clean)) {
                    // Check for RGB format
                    if (!preg_match('/^rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)$/i', $color)) {
                        return 'Corner color must be a valid hex color (#RGB or #RRGGBB) or RGB format. Found: ' . $color;
                    }
                }
            }
        }

        return true;
    }
}
