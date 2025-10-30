<?php

/**
 * JCOGS Image Pro - Filter Parameter Package
 * ==========================================
 * Sophisticated interface for filter parameter with validation
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
 * Filter Parameter Package
 * 
 * Handles the complex filter parameter which supports chaining multiple filters
 * with individual parameters in pipe-separated format.
 * 
 * Filter Parameter Format:
 * filter="filter_name:value|filter_name:value|..."
 * 
 * Supported filters and their parameters:
 * - blur:radius (0-100)
 * - sharpen:amount (0-100) 
 * - brightness:adjustment (-100 to 100)
 * - contrast:multiplier (0.1-3.0)
 * - gamma:correction (0.1-3.0)
 * - colorize:red,green,blue (0-255 each)
 * - grayscale (no parameters)
 * - sepia (no parameters)
 * - pixelate:block_size (1-50)
 * - emboss (no parameters)
 * - edge_enhance (no parameters)
 * - smooth:level (1-10)
 * 
 * Examples:
 * - filter="blur:5"
 * - filter="blur:3|sharpen:10|brightness:20"
 * - filter="grayscale|contrast:1.5"
 * 
 * Documentation: https://jcogs.net/add-ons/image/documentation#filter
 */
class FilterParameterPackage extends AbstractParameterPackage
{
    /**
     * Package identifier
     * 
     * @return string Package name
     */
    public function getName(): string 
    {
        return 'filter_parameter_package';
    }

    /**
     * Get the display label for this parameter package
     * 
     * @return string Human-readable package name
     */
    public function getDisplayName(): string
    {
        return 'Filter Effects';
    }

    /**
     * Get package description
     * 
     * @return string Package description
     */
    public function getDescription(): string
    {
        return 'Visual filter effects that can be chained together for complex image processing';
    }

    /**
     * Parameters handled by this package
     * 
     * @return array List of parameter names
     */
    public function getParameters(): array 
    {
        return ['filter'];
    }

    /**
     * Priority for filter parameter package
     * Higher priority than general transformational package
     * 
     * @return int Priority order (lower numbers = higher priority)
     */
    public function getPriority(): int
    {
        return 21; // Higher priority than TransformationalParameterPackage (30)
    }

    /**
     * Validate filter parameter value
     * 
     * @param string $param_name Parameter name being validated
     * @param string $value Parameter value to validate
     * @return bool|string True if valid, error message if invalid
     */
    public function validateParameter(string $param_name, $value): bool|string
    {
        if ($param_name !== 'filter') {
            return parent::validateParameter($param_name, $value);
        }

        $parameter_value = (string) $value;

        // Empty filter parameter is valid (no filters)
        if (empty(trim($parameter_value))) {
            return true;
        }

        // Split into pipe-separated filter chains
        $filters = explode('|', $parameter_value);
        
        // Valid filters and their parameter requirements
        $valid_filters = [
            'blur' => ['required_params' => 1, 'param_validation' => 'numeric_range', 'min' => 0, 'max' => 100],
            'sharpen' => ['required_params' => 1, 'param_validation' => 'numeric_range', 'min' => 0, 'max' => 100],
            'brightness' => ['required_params' => 1, 'param_validation' => 'numeric_range', 'min' => -100, 'max' => 100],
            'contrast' => ['required_params' => 1, 'param_validation' => 'numeric_range', 'min' => 0.1, 'max' => 3.0],
            'gamma' => ['required_params' => 1, 'param_validation' => 'numeric_range', 'min' => 0.1, 'max' => 3.0],
            'colorize' => ['required_params' => 3, 'param_validation' => 'rgb_values'],
            'grayscale' => ['required_params' => 0],
            'sepia' => ['required_params' => 0],
            'pixelate' => ['required_params' => 1, 'param_validation' => 'numeric_range', 'min' => 1, 'max' => 50],
            'emboss' => ['required_params' => 0],
            'edge_enhance' => ['required_params' => 0],
            'smooth' => ['required_params' => 1, 'param_validation' => 'numeric_range', 'min' => 1, 'max' => 10],
        ];
        
        foreach ($filters as $filter) {
            $filter = trim($filter);
            if (empty($filter)) continue;
            
            // Split filter name and parameters
            $parts = explode(':', $filter, 2);
            $filter_name = strtolower(trim($parts[0]));
            $filter_params = isset($parts[1]) ? trim($parts[1]) : '';
            
            // Validate filter name
            if (!isset($valid_filters[$filter_name])) {
                $available_filters = implode(', ', array_keys($valid_filters));
                return "Unknown filter '{$filter_name}'. Available filters: {$available_filters}";
            }
            
            $filter_config = $valid_filters[$filter_name];
            
            // Validate parameter count
            if ($filter_config['required_params'] === 0) {
                if (!empty($filter_params)) {
                    return "Filter '{$filter_name}' does not accept parameters. Found: {$filter_params}";
                }
                continue;
            }
            
            if (empty($filter_params)) {
                return "Filter '{$filter_name}' requires parameters. Format: {$filter_name}:value";
            }
            
            // Validate parameter values based on filter type
            if (isset($filter_config['param_validation'])) {
                $validation_result = $this->validateFilterParameters($filter_name, $filter_params, $filter_config);
                if ($validation_result !== true) {
                    return $validation_result;
                }
            }
        }

        return true;
    }

    /**
     * Validate parameters for a specific filter
     * 
     * @param string $filter_name Name of the filter
     * @param string $params Parameter string
     * @param array $config Filter configuration
     * @return bool|string True if valid, error message if invalid
     */
    private function validateFilterParameters(string $filter_name, string $params, array $config): bool|string
    {
        switch ($config['param_validation']) {
            case 'numeric_range':
                if (!is_numeric($params)) {
                    return "Filter '{$filter_name}' requires a numeric parameter. Found: {$params}";
                }
                $value = (float) $params;
                if ($value < $config['min'] || $value > $config['max']) {
                    return "Filter '{$filter_name}' parameter must be between {$config['min']} and {$config['max']}. Found: {$value}";
                }
                break;
                
            case 'rgb_values':
                $rgb_parts = explode(',', $params);
                if (count($rgb_parts) !== 3) {
                    return "Filter '{$filter_name}' requires 3 RGB values (red,green,blue). Found: {$params}";
                }
                foreach ($rgb_parts as $i => $rgb_value) {
                    $rgb_value = trim($rgb_value);
                    if (!is_numeric($rgb_value)) {
                        $color_names = ['red', 'green', 'blue'];
                        return "Filter '{$filter_name}' {$color_names[$i]} value must be numeric. Found: {$rgb_value}";
                    }
                    $value = (int) $rgb_value;
                    if ($value < 0 || $value > 255) {
                        $color_names = ['red', 'green', 'blue'];
                        return "Filter '{$filter_name}' {$color_names[$i]} value must be between 0 and 255. Found: {$value}";
                    }
                }
                break;
        }
        
        return true;
    }

    /**
     * Generate form fields for filter parameter
     * For now, use a simple text field - could be enhanced with dynamic filter builder UI
     * 
     * @param array $current_values Current parameter values
     * @return array Form field definitions
     */
    protected function getPackageFormFields(array $current_values = []): array
    {
        return [
            'filter' => [
                'type' => 'text',
                'value' => $current_values['filter'] ?? '',
                'label' => 'Filter Chain',
                'desc' => 'Apply visual filters to the image. Use pipe (|) to chain multiple filters.',
                'example' => 'blur:5|sharpen:10 or grayscale|contrast:1.5',
                'attrs' => 'placeholder="blur:5|sharpen:10|brightness:20"'
            ]
        ];
    }
}
