<?php

/**
 * JCOGS Image Pro - Reflection Parameter Package
 * ==============================================
 * Sophisticated form interface for reflection parameter
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
 * Reflection Parameter Package
 * 
 * Provides sophisticated 4-field interface for reflection parameter instead of requiring
 * users to construct complex comma-separated parameter strings manually.
 * 
 * Reflection Parameter Format (from JCOGS documentation):
 * reflection="<gap>[,<start opacity>[,<end opacity>[,<reflection height>]]]"
 * 
 * Components:
 * - gap: Vertical separation between image and reflection (default: 0)
 * - start_opacity: Opacity at top of reflection, closest to image (default: 80, range: 0-100)
 * - end_opacity: Opacity at bottom of reflection, furthest from image (default: 0, range: 0-100) 
 * - reflection_height: Height of reflection as dimension or % (default: 50%)
 * 
 * Examples:
 * - reflection="0" - Default parameters
 * - reflection="10,70,30,40%" - All parameters specified
 * - reflection="20px,70,10,60%" - With gap and custom opacity fade
 * 
 * Documentation: https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-reflection
 */
class ReflectionParameterPackage extends AbstractParameterPackage
{
    /**
     * Package identifier
     * 
     * @return string Package name
     */
    public function getName(): string 
    {
        return 'reflection_parameter_package';
    }

    /**
     * Get the display label for this parameter package
     * 
     * @return string Package label
     */
    public function getLabel(): string
    {
        return 'Reflection Parameters';
    }

    /**
     * Get the description for this parameter package
     * 
     * @return string Package description
     */
    public function getDescription(): string
    {
        return 'Controls for simulated reflection effects below images';
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
        return ['reflection'];
    }

    /**
     * Priority for reflection parameter package
     * Higher priority than general transformational package
     * 
     * @return int Priority order (lower numbers = higher priority)
     */
    public function getPriority(): int
    {
        return 21; // Higher than TransformationalParameterPackage (30)
    }

    /**
     * Get form fields for reflection parameter
     * 
     * @param array $current_values Current parameter values  
     * @return array Flat associative array of form fields
     */
    protected function getPackageFormFields(array $current_values = []): array
    {
        // Get current reflection value
        $current_value = $current_values['reflection'] ?? '';
        
        // Parse existing reflection value
        $parsed = $this->_parse_reflection_value($current_value);
        
        return [
            'reflection_gap' => [
                'type' => 'text',
                'label' => 'Gap Height',
                'desc' => 'Vertical separation between image and reflection (e.g., 0, 10px, 5%)',
                'value' => $parsed['gap'] ?? '',
                'placeholder' => 'For example: 10px'
            ],
            
            'reflection_start_opacity' => [
                'type' => 'text',
                'label' => 'Start Opacity',
                'desc' => 'Opacity at top of reflection, closest to image (0-100, default: 80)',
                'value' => $parsed['start_opacity'] ?? '',
                'placeholder' => 'For example: 80'
            ],
            
            'reflection_end_opacity' => [
                'type' => 'text',
                'label' => 'End Opacity', 
                'desc' => 'Opacity at bottom of reflection, furthest from image (0-100, default: 0)',
                'value' => $parsed['end_opacity'] ?? '',
                'placeholder' => 'For example: 0'
            ],
            
            'reflection_height' => [
                'type' => 'text',
                'label' => 'Reflection Height',
                'desc' => 'Height of reflection as dimension or percentage (e.g., 50%, 100px)',
                'value' => $parsed['height'] ?? '',
                'placeholder' => 'For example: 50%'
            ]
        ];
    }

    /**
     * Process form data to generate reflection parameter value
     * 
     * @param string $parameter_name Parameter name (should be 'reflection')
     * @param array $form_data Form submission data
     * @return string Formatted reflection parameter value
     */
    public function processParameterFromForm(string $parameter_name, array $form_data): string 
    {
        // Always save reflection parameters for flexible configuration
        
        $components = [];

        // Get reflection components
        $gap = trim($form_data['reflection_gap'] ?? '');
        $start_opacity = trim($form_data['reflection_start_opacity'] ?? '');
        $end_opacity = trim($form_data['reflection_end_opacity'] ?? '');
        $height = trim($form_data['reflection_height'] ?? '');

        // Build comma-separated reflection parameter
        // Only include values that are actually specified
        
        if (!empty($gap)) {
            $components[] = $gap;
            
            if (!empty($start_opacity)) {
                $components[] = $start_opacity;
                
                if (!empty($end_opacity)) {
                    $components[] = $end_opacity;
                    
                    if (!empty($height)) {
                        $components[] = $height;
                    }
                }
            }
        }

        // If no components are specified, return empty
        if (empty($components)) {
            return '';
        }

        // Return comma-separated reflection parameter
        return implode(',', $components);
    }

    /**
     * Parse reflection parameter value into components
     * 
     * @param mixed $value Current reflection value
     * @return array Parsed components
     */
    private function _parse_reflection_value($value): array
    {
        if (empty($value) || !is_string($value)) {
            return ['gap' => '', 'start_opacity' => '', 'end_opacity' => '', 'height' => ''];
        }

        $result = ['gap' => '', 'start_opacity' => '', 'end_opacity' => '', 'height' => ''];

        // Split on comma to get components
        $parts = explode(',', $value);
        
        // Map parts to result components
        if (isset($parts[0])) {
            $result['gap'] = trim($parts[0]);
        }
        if (isset($parts[1])) {
            $result['start_opacity'] = trim($parts[1]);
        }
        if (isset($parts[2])) {
            $result['end_opacity'] = trim($parts[2]);
        }
        if (isset($parts[3])) {
            $result['height'] = trim($parts[3]);
        }
        
        return $result;
    }

    /**
     * Validate reflection parameter value
     * 
     * @param string $param_name Parameter name being validated
     * @param string $value Parameter value to validate
     * @return bool|string True if valid, error message if invalid
     */
    public function validateParameter(string $param_name, $value): bool|string
    {
        if ($param_name !== 'reflection') {
            return parent::validateParameter($param_name, $value);
        }

        $parameter_value = (string) $value;

        // Empty reflection parameter is valid (no reflection)
        if (empty(trim($parameter_value))) {
            return true;
        }

        // Split into comma-separated components
        $parts = explode(',', $parameter_value, 4);
        
        // Validate gap (required first parameter)
        if (empty(trim($parts[0] ?? ''))) {
            return 'Reflection parameter requires a gap value (e.g., "0,80,0,50%")';
        }
        
        $gap = trim($parts[0]);
        if (!preg_match('/^\d+(?:px)?$/', $gap)) {
            return 'Reflection gap must be a number optionally followed by "px". Found: ' . $gap;
        }

        // Validate start opacity if provided
        if (!empty($parts[1])) {
            $start_opacity = trim($parts[1]);
            if (!is_numeric($start_opacity) || $start_opacity < 0 || $start_opacity > 100) {
                return 'Reflection start opacity must be a number between 0 and 100. Found: ' . $start_opacity;
            }
        }

        // Validate end opacity if provided
        if (!empty($parts[2])) {
            $end_opacity = trim($parts[2]);
            if (!is_numeric($end_opacity) || $end_opacity < 0 || $end_opacity > 100) {
                return 'Reflection end opacity must be a number between 0 and 100. Found: ' . $end_opacity;
            }
        }

        // Validate reflection height if provided
        if (!empty($parts[3])) {
            $height = trim($parts[3]);
            if (!preg_match('/^\d+(?:px|%)?$/', $height)) {
                return 'Reflection height must be a number optionally followed by "px" or "%". Found: ' . $height;
            }
        }

        return true;
    }
}
