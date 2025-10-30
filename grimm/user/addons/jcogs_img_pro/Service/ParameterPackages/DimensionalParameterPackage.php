<?php

/**
 * JCOGS Image Pro - Dimensional Parameter Package
 * ===============================================
 * Handles all dimensional parameters (width, height, max, min constraints)
 * 
 * This package manages parameters that control image dimensions and sizing
 * constraints following the Legacy categorization system.
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Presets Feature Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service\ParameterPackages;

use JCOGSDesign\JCOGSImagePro\Service\ParameterPackages\AbstractParameterPackage;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

class DimensionalParameterPackage extends AbstractParameterPackage
{
    /**
     * Get the category name for this parameter package
     * 
     * @return string Package category name
     */
    public function getCategory(): string
    {
        return 'dimensional';
    }

    /**
     * Get the package display label for UI
     * 
     * @return string Human-readable package name
     */
    public function getDisplayName(): string
    {
        return $this->lang('jcogs_img_pro_param_package_dimensional_display_name', 'Dimensional Parameters');
    }

    /**
     * Get the description of this parameter package
     * 
     * @return string Package description
     */
    public function getDescription(): string
    {
        return $this->lang('jcogs_img_pro_param_package_dimensional_description', 'Parameters that control image dimensions, sizing, and dimension constraints');
    }

    /**
     * Get the unique name for this parameter package
     * 
     * @return string Package name
     */
    public function getName(): string
    {
        return $this->lang('jcogs_img_pro_param_package_dimensional_name', 'dimensional');
    }

    /**
     * Get the display label for this parameter package
     * 
     * @return string Package label
     */
    public function getLabel(): string
    {
        return $this->lang('jcogs_img_pro_param_package_dimensional_display_name', 'Dimensional Parameters');
    }    /**
     * Get the parameters handled by this package
     * 
     * @return array Parameter list
     */
    public function getParameters(): array
    {
        return $this->parameterRegistry->getParametersByCategory('dimensional');
    }

    /**
     * Priority for dimensional parameter package
     * 
     * @return int Priority order (lower numbers = higher priority)
     */
    public function getPriority(): int
    {
        return 20;  // Higher priority than transformational packages
    }

    /**
     * Get form fields for dimensional parameters with enhanced language support
     * 
     * @param array $current_values Current parameter values
     * @return array EE-compatible form field definitions
     */
    protected function getPackageFormFields(array $current_values = []): array
    {
        $fields = [];

        // Width parameter with enhanced form generation
        $fields['width'] = [
            'type' => 'text',
            'value' => $current_values['width'] ?? '',
            'desc' => $this->lang('parameter_width_description', 'Set the output image width in pixels. Leave empty to maintain aspect ratio.'),
            'attrs' => [
                'class' => 'form-control coordinate-input dimensional-parameter',
                'placeholder' => $this->lang('width_placeholder', 'e.g. 800'),
                'pattern' => '[0-9]*',
                'data-parameter' => 'width',
                'data-unit' => 'pixels',
                'data-related-fields' => 'height,max_width,min_width'
            ]
        ];

        // Height parameter with enhanced form generation
        $fields['height'] = [
            'type' => 'text',
            'value' => $current_values['height'] ?? '',
            'desc' => $this->lang('parameter_height_description', 'Set the output image height in pixels. Leave empty to maintain aspect ratio.'),
            'attrs' => [
                'class' => 'form-control coordinate-input dimensional-parameter',
                'placeholder' => $this->lang('height_placeholder', 'e.g. 600'),
                'pattern' => '[0-9]*',
                'data-parameter' => 'height',
                'data-unit' => 'pixels',
                'data-related-fields' => 'width,max_height,min_height'
            ]
        ];

        // Max (general maximum) with coordinate input
        $fields['max'] = [
            'type' => 'text',
            'value' => $current_values['max'] ?? '',
            'desc' => $this->lang('parameter_max_description', 'Maximum size for both width and height (images scaled to fit within this constraint).'),
            'attrs' => [
                'class' => 'form-control coordinate-input dimensional-parameter',
                'placeholder' => $this->lang('max_placeholder', 'e.g. 1200'),
                'pattern' => '[0-9]*',
                'data-parameter' => 'max',
                'data-unit' => 'pixels'
            ]
        ];

        // Max Width with enhanced validation
        $fields['max_width'] = [
            'type' => 'text',
            'value' => $current_values['max_width'] ?? '',
            'desc' => $this->lang('parameter_max_width_description', 'Maximum width in pixels. Images wider than this will be scaled down.'),
            'attrs' => [
                'class' => 'form-control coordinate-input dimensional-parameter',
                'placeholder' => $this->lang('max_width_placeholder', 'e.g. 1920'),
                'pattern' => '[0-9]*',
                'data-parameter' => 'max_width',
                'data-unit' => 'pixels',
                'data-related-fields' => 'width,max'
            ]
        ];

        // Max Height with enhanced validation
        $fields['max_height'] = [
            'type' => 'text',
            'value' => $current_values['max_height'] ?? '',
            'desc' => $this->lang('parameter_max_height_description', 'Maximum height in pixels. Images taller than this will be scaled down.'),
            'attrs' => [
                'class' => 'form-control coordinate-input dimensional-parameter',
                'placeholder' => $this->lang('max_height_placeholder', 'e.g. 1080'),
                'pattern' => '[0-9]*',
                'data-parameter' => 'max_height',
                'data-unit' => 'pixels',
                'data-related-fields' => 'height,max'
            ]
        ];

        // Min (general minimum) with coordinate input
        $fields['min'] = [
            'type' => 'text',
            'value' => $current_values['min'] ?? '',
            'desc' => $this->lang('parameter_min_description', 'Minimum size for both width and height (images scaled to meet this constraint).'),
            'attrs' => [
                'class' => 'form-control coordinate-input dimensional-parameter',
                'placeholder' => $this->lang('min_placeholder', 'e.g. 200'),
                'pattern' => '[0-9]*',
                'data-parameter' => 'min',
                'data-unit' => 'pixels'
            ]
        ];

        // Min Width with enhanced form generation
        $fields['min_width'] = [
            'type' => 'text',
            'value' => $current_values['min_width'] ?? '',
            'desc' => $this->lang('parameter_min_width_description', 'Minimum width in pixels. Images narrower than this will be scaled up.'),
            'attrs' => [
                'class' => 'form-control coordinate-input dimensional-parameter',
                'placeholder' => $this->lang('min_width_placeholder', 'e.g. 300'),
                'pattern' => '[0-9]*',
                'data-parameter' => 'min_width',
                'data-unit' => 'pixels',
                'data-related-fields' => 'width,min'
            ]
        ];

        // Min Height with enhanced form generation  
        $fields['min_height'] = [
            'type' => 'text',
            'value' => $current_values['min_height'] ?? '',
            'desc' => $this->lang('parameter_min_height_description', 'Minimum height in pixels. Images shorter than this will be scaled up.'),
            'attrs' => [
                'class' => 'form-control coordinate-input dimensional-parameter',
                'placeholder' => $this->lang('min_height_placeholder', 'e.g. 200'),
                'pattern' => '[0-9]*',
                'data-parameter' => 'min_height',
                'data-unit' => 'pixels',
                'data-related-fields' => 'height,min'
            ]
        ];

        return $fields;
    }

    /**
     * Validate dimensional parameters
     * 
     * @param array $parameters Parameters to validate
     * @return array Validation errors (empty if valid)
     */
    public function validateParameters(array $parameters): array
    {
        $errors = [];
        $dimensional_params = $this->getParameters();

        foreach ($dimensional_params as $param_name) {
            if (isset($parameters[$param_name])) {
                $value = $parameters[$param_name];
                
                // Skip empty values (they're optional)
                if ($value === '' || $value === null) {
                    continue;
                }

                // Validate that it's a positive integer
                if (!$this->isValidDimension($value)) {
                    $errors[$param_name] = "'{$param_name}' must be a positive integer (got: {$value})";
                }

                // Additional validation for logical constraints
                $dimension_errors = $this->validateDimensionLogic($param_name, $value, $parameters);
                $errors = array_merge($errors, $dimension_errors);
            }
        }

        return $errors;
    }

    /**
     * Transform parameters to tag-compatible format
     * 
     * @param array $parameters Raw parameter values
     * @return array Tag-compatible parameter array
     */
    public function transformToTagParameters(array $parameters): array
    {
        $tag_params = [];
        $dimensional_params = $this->getParameters();

        foreach ($dimensional_params as $param_name) {
            if (isset($parameters[$param_name]) && $parameters[$param_name] !== '') {
                $tag_params[$param_name] = $this->sanitizeDimension($parameters[$param_name]);
            }
        }

        return $tag_params;
    }

    /**
     * Transform form data to parameter format
     * 
     * @param array $form_data Raw form data
     * @return array Parameter format data
     */
    public function transformFormToParameters(array $form_data): array
    {
        $parameters = [];
        $dimensional_params = $this->getParameters();

        foreach ($dimensional_params as $param_name) {
            if (isset($form_data[$param_name]) && $form_data[$param_name] !== '') {
                $parameters[$param_name] = $this->sanitizeDimension($form_data[$param_name]);
            }
        }

        return $parameters;
    }

    /**
     * Transform parameters to form data format
     * 
     * @param array $parameters Parameter values
     * @return array Form-compatible values
     */
    public function transformParametersToForm(array $parameters): array
    {
        $form_data = [];
        $dimensional_params = $this->getParameters();

        foreach ($dimensional_params as $param_name) {
            if (isset($parameters[$param_name])) {
                $form_data[$param_name] = (string) $parameters[$param_name];
            }
        }

        return $form_data;
    }

    /**
     * Validate that a value is a valid dimension
     * 
     * @param mixed $value Value to validate
     * @return bool True if valid dimension
     */
    private function isValidDimension($value): bool
    {
        // Use ValidationService for consistent dimension validation
        $validation_service = ServiceCache::validation();
        $result = $validation_service->validate_dimension($value);
        
        return $result !== false && $result !== null;
    }

    /**
     * Validate dimension logic constraints
     * 
     * @param string $param_name Parameter name being validated
     * @param mixed $value Parameter value
     * @param array $all_parameters All parameters for context
     * @return array Validation errors
     */
    private function validateDimensionLogic(string $param_name, $value, array $all_parameters): array
    {
        $errors = [];
        
        // Use ValidationService for consistent dimension parsing
        $validation_service = ServiceCache::validation();
        $numeric_value = $validation_service->validate_dimension($value);
        
        if ($numeric_value === false || $numeric_value === null) {
            // This shouldn't happen as isValidDimension should catch it first, but be safe
            return $errors;
        }

        // Validate min/max relationships
        switch ($param_name) {
            case 'min_width':
                if (isset($all_parameters['max_width']) && !empty($all_parameters['max_width'])) {
                    $max_width_parsed = $validation_service->validate_dimension($all_parameters['max_width']);
                    if ($max_width_parsed && $numeric_value > $max_width_parsed) {
                        $errors['min_width'] = "Minimum width ({$numeric_value}) cannot be greater than maximum width ({$max_width_parsed})";
                    }
                }
                break;

            case 'min_height':
                if (isset($all_parameters['max_height']) && !empty($all_parameters['max_height'])) {
                    $max_height_parsed = $validation_service->validate_dimension($all_parameters['max_height']);
                    if ($max_height_parsed && $numeric_value > $max_height_parsed) {
                        $errors['min_height'] = "Minimum height ({$numeric_value}) cannot be greater than maximum height ({$max_height_parsed})";
                    }
                }
                break;

            case 'min':
                if (isset($all_parameters['max']) && !empty($all_parameters['max'])) {
                    $max_parsed = $validation_service->validate_dimension($all_parameters['max']);
                    if ($max_parsed && $numeric_value > $max_parsed) {
                        $errors['min'] = "Minimum size ({$numeric_value}) cannot be greater than maximum size ({$max_parsed})";
                    }
                }
                break;
        }

        return $errors;
    }

    /**
     * Sanitize a dimension value
     * 
     * @param mixed $value Raw dimension value
     * @return int Sanitized dimension value
     */
    private function sanitizeDimension($value): int
    {
        // Use ValidationService for consistent dimension parsing and sanitization
        $validation_service = ServiceCache::validation();
        $result = $validation_service->validate_dimension($value);
        
        // Return validated result or minimum value of 1
        return $result && is_int($result) ? $result : 1;
    }

    /**
     * Get parameter definitions for this package
     * 
     * @return array Parameter definitions with metadata
     */
    public function getParameterDefinitions(): array
    {
        return [
            'width' => [
                'type' => 'dimension',
                'description' => 'Output image width in pixels',
                'example' => '800',
                'validation' => 'positive_integer'
            ],
            'height' => [
                'type' => 'dimension', 
                'description' => 'Output image height in pixels',
                'example' => '600',
                'validation' => 'positive_integer'
            ],
            'max' => [
                'type' => 'dimension',
                'description' => 'Maximum size constraint for both dimensions',
                'example' => '1200',
                'validation' => 'positive_integer'
            ],
            'max_width' => [
                'type' => 'dimension',
                'description' => 'Maximum width constraint in pixels',
                'example' => '1920',
                'validation' => 'positive_integer'
            ],
            'max_height' => [
                'type' => 'dimension',
                'description' => 'Maximum height constraint in pixels',
                'example' => '1080',
                'validation' => 'positive_integer'
            ],
            'min' => [
                'type' => 'dimension',
                'description' => 'Minimum size constraint for both dimensions',
                'example' => '200',
                'validation' => 'positive_integer'
            ],
            'min_width' => [
                'type' => 'dimension',
                'description' => 'Minimum width constraint in pixels',
                'example' => '300',
                'validation' => 'positive_integer'
            ],
            'min_height' => [
                'type' => 'dimension',
                'description' => 'Minimum height constraint in pixels',
                'example' => '200',
                'validation' => 'positive_integer'
            ]
        ];
    }

    /**
     * Generate enhanced dimensional field with coordinate input support
     * 
     * @param string $param_name Parameter name
     * @param mixed $current_value Current parameter value
     * @param array $field_config Field configuration options
     * @return array EE-compatible field definition
     */
    protected function generateDimensionalField(string $param_name, $current_value = '', array $field_config = []): array
    {
        $field_type = $field_config['type'] ?? 'coordinate_input';
        $unit = $field_config['unit'] ?? 'pixels';
        $validation = $field_config['validation'] ?? ['numeric', 'positive'];
        $help_text = $field_config['help_text'] ?? '';
        $related_fields = $field_config['related_fields'] ?? [];
        
        $field_definition = [
            'type' => 'text',
            'value' => $current_value,
            'maxlength' => 10,
            'placeholder' => $field_config['placeholder'] ?? '',
            'attrs' => 'pattern="[0-9]*" data-unit="' . $unit . '" data-validation="' . implode(',', $validation) . '"'
        ];
        
        // Add coordinate input specific attributes
        if ($field_type === 'coordinate_input') {
            $field_definition['attrs'] .= ' class="coordinate-input dimensional-parameter"';
            $field_definition['attrs'] .= ' data-parameter="' . $param_name . '"';
            
            if (!empty($related_fields)) {
                $field_definition['attrs'] .= ' data-related-fields="' . implode(',', $related_fields) . '"';
            }
        }
        
        // Add help text if provided
        if ($help_text) {
            $field_definition['help'] = $help_text;
        }
        
        return $field_definition;
    }

    /**
     * Generate complex field with multiple options and conditional logic
     * 
     * @param string $param_name Parameter name
     * @param mixed $current_value Current parameter value
     * @param array $field_config Field configuration options
     * @return array EE-compatible field definition
     */
    protected function generateComplexField(string $param_name, $current_value = '', array $field_config = []): array
    {
        $field_type = $field_config['type'] ?? 'select';
        $options = $field_config['options'] ?? [];
        $default = $field_config['default'] ?? '';
        $conditional_fields = $field_config['conditional_fields'] ?? [];
        
        $field_definition = [
            'type' => 'select',
            'value' => $current_value ?: $default,
            'choices' => $options,
            'attrs' => 'class="complex-parameter" data-parameter="' . $param_name . '"'
        ];
        
        // Add conditional field logic
        if (!empty($conditional_fields)) {
            $field_definition['attrs'] .= ' data-conditional-fields="' . htmlspecialchars(json_encode($conditional_fields)) . '"';
        }
        
        return $field_definition;
    }

    /**
     * Get aspect ratio options for the aspect ratio lock field
     * 
     * @return array Aspect ratio options
     */
    protected function getAspectRatioOptions(): array
    {
        return [
            'auto' => $this->lang('aspect_ratio_auto', 'Auto (maintain original)'),
            'lock' => $this->lang('aspect_ratio_lock', 'Lock current ratio'),
            'free' => $this->lang('aspect_ratio_free', 'Free (allow distortion)'),
            '1:1' => $this->lang('aspect_ratio_square', 'Square (1:1)'),
            '4:3' => $this->lang('aspect_ratio_4_3', 'Standard (4:3)'),
            '16:9' => $this->lang('aspect_ratio_16_9', 'Widescreen (16:9)'),
            '3:2' => $this->lang('aspect_ratio_3_2', 'Photo (3:2)'),
            'custom' => $this->lang('aspect_ratio_custom', 'Custom ratio')
        ];
    }
}
