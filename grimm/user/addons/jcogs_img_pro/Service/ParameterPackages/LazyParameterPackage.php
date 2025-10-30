<?php

/**
 * JCOGS Image Pro - Lazy Loading Parameter Package
 * ===============================================
 * Sophisticated interface for lazy loading parameter with validation
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://jcogs.net/
 * @since      Parameter Package Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service\ParameterPackages;

/**
 * Lazy Loading Parameter Package
 * 
 * Handles the complex lazy loading parameter which supports multiple approaches
 * to optimize image loading performance.
 * 
 * Lazy Parameter Options:
 * - no: Disables lazy loading for this tag
 * - lqip: Low Quality Image Placeholder - loads a low-resolution recognizable version (~25% size)
 * - dominant_color: Dominant Colour Field - replaces with color field using most common color
 * - js_lqip: JavaScript Method Low Quality Image Placeholder - uses JS for replacement
 * - js_dominant_color: JavaScript Method Dominant Colour Field - uses JS for color replacement
 * - html5: Adds HTML5 loading="lazy" attribute only (no placeholder substitution)
 * 
 * Examples:
 * - lazy="no"
 * - lazy="lqip"
 * - lazy="dominant_color"
 * - lazy="js_lqip"
 * - lazy="js_dominant_color"
 * - lazy="html5"
 * 
 * Documentation: https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-background-lazy
 */
class LazyParameterPackage extends AbstractParameterPackage
{
    /**
     * Package identifier
     * 
     * @return string Package name
     */
    public function getName(): string 
    {
        return 'lazy_parameter_package';
    }

    /**
     * Get the display label for this parameter package
     * 
     * @return string Human-readable package name
     */
    public function getDisplayName(): string
    {
        return 'Lazy Loading';
    }

    /**
     * Get package description
     * 
     * @return string Package description
     */
    public function getDescription(): string
    {
        return 'Configure lazy loading behavior to optimize image loading performance';
    }

    /**
     * Parameters handled by this package
     * 
     * @return array List of parameter names
     */
    public function getParameters(): array 
    {
        return ['lazy'];
    }

    /**
     * Priority for lazy parameter package
     * Higher priority than general control package
     * 
     * @return int Priority order (lower numbers = higher priority)
     */
    public function getPriority(): int
    {
        return 11; // Higher priority than ControlParameterPackage (50)
    }

    /**
     * Validate lazy parameter value
     * 
     * @param string $param_name Parameter name being validated
     * @param string $value Parameter value to validate
     * @return bool|string True if valid, error message if invalid
     */
    public function validateParameter(string $param_name, $value): bool|string
    {
        if ($param_name !== 'lazy') {
            return parent::validateParameter($param_name, $value);
        }

        $parameter_value = (string) $value;

        // Empty lazy parameter defaults to control panel setting
        if (empty(trim($parameter_value))) {
            return true;
        }

        // Valid lazy loading options
        $valid_options = [
            'no' => 'Disables lazy loading for this tag',
            'lqip' => 'Low Quality Image Placeholder',
            'dominant_color' => 'Dominant Colour Field',
            'js_lqip' => 'JavaScript Method Low Quality Image Placeholder',
            'js_dominant_color' => 'JavaScript Method Dominant Colour Field',
            'html5' => 'HTML5 loading="lazy" attribute only'
        ];
        
        $option = strtolower(trim($parameter_value));
        
        if (!isset($valid_options[$option])) {
            $available_options = implode(', ', array_keys($valid_options));
            return "Invalid lazy loading option '{$parameter_value}'. Available options: {$available_options}";
        }

        return true;
    }

    /**
     * Generate form fields for lazy parameter
     * 
     * @param array $current_values Current parameter values
     * @return array Form field definitions
     */
    protected function getPackageFormFields(array $current_values = []): array
    {
        return [
            'lazy' => [
                'type' => 'select',
                'value' => $current_values['lazy'] ?? '',
                'label' => 'Lazy Loading Method',
                'desc' => 'Choose how images should be lazy loaded for better performance',
                'choices' => [
                    '' => 'Use default setting',
                    'no' => 'Disabled - load normally',
                    'lqip' => 'Low Quality Placeholder',
                    'dominant_color' => 'Dominant Color Field',
                    'js_lqip' => 'JavaScript Low Quality Placeholder',
                    'js_dominant_color' => 'JavaScript Dominant Color',
                    'html5' => 'HTML5 loading="lazy" only'
                ]
            ]
        ];
    }
}
