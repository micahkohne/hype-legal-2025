<?php

/**
 * JCOGS Image Pro - Control Parameter Package
 * ============================================
 * Handles control category parameters: src, cache, quality, format
 * 
 * This package implements the control parameters that manage basic image processing
 * behavior: source file specification, caching policies, output quality, and format.
 * These parameters correspond to the 'control' category in Legacy's parameter categorization.
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

class ControlParameterPackage extends AbstractParameterPackage
{
    /**
     * Get the package name/identifier
     * 
     * @return string Package identifier
     */
    public function getName(): string
    {
        return 'control';
    }

    /**
     * Get the package display label for UI
     * 
     * @return string Human-readable package name
     */
    public function getLabel(): string
    {
        return $this->lang('jcogs_img_pro_param_package_control_display_name', 'Control Parameters');
    }

    /**
     * Get the package description
     * 
     * @return string Package description
     */
    public function getDescription(): string
    {
        return $this->lang('jcogs_img_pro_param_package_control_description', 'Basic image processing control: source, caching, quality, and output format settings.');
    }

    /**
     * Get the Legacy parameter category
     * 
     * @return string Legacy category for ParameterRegistry integration
     */
    public function getCategory(): string
    {
        return 'control';
    }

    /**
     * Get all parameters handled by this package
     * 
     * @return array List of core parameter names
     */
    public function getParameters(): array
    {
        return $this->parameterRegistry->getParametersByCategory('control');
    }

    /**
     * Get package priority for interface ordering
     * Core parameters should appear first
     * 
     * @return int High priority (low number)
     */
    public function getPriority(): int
    {
        return 10;
    }

    /**
     * Get required parameters for this package
     * 
     * @return array List of required parameter names
     */
    protected function getRequiredParameters(): array
    {
        return ['src']; // Source is always required
    }

    /**
     * Generate form fields for Control Panel interface
     * 
     * @param array $current_values Current parameter values
     * @return array EE-compatible form field definitions
     */
    protected function getPackageFormFields(array $current_values = []): array
    {
        $fields = [];

        // Source image field (control category)
        $fields['src'] = [
            'type' => 'text',
            'label' => 'Source Image',
            'desc' => 'Path to source image or EE Files reference ({filedir_X}filename.jpg)',
            'value' => $current_values['src'] ?? '',
            'required' => true,
            'attrs' => [
                'class' => 'form-control',
                'placeholder' => '{filedir_1}sample.jpg'
            ]
        ];

        // Cache duration field (control category) - ENHANCED with natural language support
        $fields['cache'] = [
            'type' => 'select',
            'label' => 'Cache Duration',
            'desc' => 'How long to cache the processed image. Choose a preset or use "Custom" for natural language input like "2 weeks", "forever", etc.',
            'value' => $current_values['cache'] ?? '86400',
            'choices' => [
                '0' => 'No caching',
                '3600' => '1 hour',
                '86400' => '1 day (recommended)',
                '604800' => '1 week',
                '2592000' => '30 days',
                '-1' => 'Permanent',
                'custom' => 'Custom Duration (enter value below)'
            ],
            'attrs' => [
                'class' => 'form-control cache-duration-select',
                'data-parameter' => 'cache',
                'data-toggle-field' => 'cache_custom'
            ]
        ];

        // Custom cache duration input field (shown when "custom" is selected)
        $fields['cache_custom'] = [
            'type' => 'text',
            'label' => 'Custom Cache Duration',
            'desc' => 'Enter duration using natural language like "2 weeks", "5 days", "forever", "disabled", or seconds directly (e.g. 604800).',
            'value' => '',
            'attrs' => [
                'class' => 'form-control duration-input',
                'placeholder' => 'e.g., "1 week", "forever", "disabled"',
                'data-parameter' => 'cache_custom',
                'data-duration-context' => 'cache',
                'style' => 'display: none;' // Hidden by default, shown via JavaScript when custom is selected
            ]
        ];

        // Filename customization (control category)
        $fields['filename'] = [
            'type' => 'text',
            'label' => 'Custom Filename',
            'desc' => 'Override the default filename for cached images',
            'value' => $current_values['filename'] ?? '',
            'attrs' => [
                'class' => 'form-control',
                'placeholder' => 'custom-name',
                'data-parameter' => 'filename'
            ]
        ];

        // Output format (control category)
        $fields['output'] = [
            'type' => 'select',
            'label' => 'Output Method',
            'desc' => 'How to output the processed image',
            'value' => $current_values['output'] ?? 'tag',
            'choices' => [
                'tag' => 'Image tag (<img>)',
                'url' => 'URL only',
                'url_only' => 'URL only (legacy)',
                'responsive' => 'Responsive image tag'
            ],
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'output'
            ]
        ];

        // URL Only output (control category)
        $fields['url_only'] = [
            'type' => 'radio',
            'label' => 'URL Only Output',
            'desc' => 'Return just the URL instead of a complete image tag',
            'value' => $current_values['url_only'] ?? 'no',
            'choices' => [
                'no' => 'No - Generate image tag (default)',
                'yes' => 'Yes - Return URL only'
            ],
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'url_only'
            ]
        ];

        // Lazy loading (control category)
        $fields['lazy'] = [
            'type' => 'radio',
            'label' => 'Lazy Loading',
            'desc' => 'Add lazy loading attributes to improve page performance',
            'value' => $current_values['lazy'] ?? 'no',
            'choices' => [
                'no' => 'No - Standard loading (default)',
                'yes' => 'Yes - Enable lazy loading'
            ],
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'lazy'
            ]
        ];

        // Exclude Style (control category)
        $fields['exclude_style'] = [
            'type' => 'radio',
            'label' => 'Exclude Style Attributes',
            'desc' => 'Exclude inline style attributes from image tag',
            'value' => $current_values['exclude_style'] ?? 'no',
            'choices' => [
                'no' => 'No - Include style attributes (default)',
                'yes' => 'Yes - Exclude style attributes'
            ],
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'exclude_style'
            ]
        ];

        // Hash Filename (control category)
        $fields['hash_filename'] = [
            'type' => 'radio',
            'label' => 'Hash Filename',
            'desc' => 'Generate hash-based filenames for better caching',
            'value' => $current_values['hash_filename'] ?? 'no',
            'choices' => [
                'no' => 'No - Use descriptive filenames (default)',
                'yes' => 'Yes - Use hash-based filenames'
            ],
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'hash_filename'
            ]
        ];

        // Overwrite Cache (control category)
        $fields['overwrite_cache'] = [
            'type' => 'radio',
            'label' => 'Overwrite Cache',
            'desc' => 'Force regeneration of cached images',
            'value' => $current_values['overwrite_cache'] ?? 'no',
            'choices' => [
                'no' => 'No - Use existing cache (default)',
                'yes' => 'Yes - Force regeneration'
            ],
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'overwrite_cache'
            ]
        ];

        // Use Image Path Prefix (control category)
        $fields['use_image_path_prefix'] = [
            'type' => 'radio',
            'label' => 'Use Image Path Prefix',
            'desc' => 'Apply configured path prefix to image URLs',
            'value' => $current_values['use_image_path_prefix'] ?? 'yes',
            'choices' => [
                'yes' => 'Yes - Use path prefix (default)',
                'no' => 'No - Skip path prefix'
            ],
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'use_image_path_prefix'
            ]
        ];

        // Add Dimensions (control category)
        $fields['add_dims'] = [
            'type' => 'radio',
            'label' => 'Add Dimensions',
            'desc' => 'Add width and height attributes to image tag',
            'value' => $current_values['add_dims'] ?? 'yes',
            'choices' => [
                'yes' => 'Yes - Add width/height attributes (default)',
                'no' => 'No - Skip dimension attributes'
            ],
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'add_dims'
            ]
        ];

        // HTML Attributes (control category)
        $fields['attributes'] = [
            'type' => 'text',
            'label' => 'Additional HTML Attributes',
            'desc' => 'Additional HTML attributes to add to the image tag (e.g., class="my-class" alt="description")',
            'value' => $current_values['attributes'] ?? '',
            'attrs' => [
                'class' => 'form-control',
                'placeholder' => 'class="my-class" alt="description"',
                'data-parameter' => 'attributes'
            ]
        ];

        // Add Dimensions Alias (control category)
        $fields['add_dimensions'] = [
            'type' => 'yes_no',
            'label' => 'Add Dimensions (Alias)',
            'desc' => 'Alias for add_dims parameter - add width and height attributes',
            'value' => $current_values['add_dimensions'] ?? 'y',
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'add_dimensions'
            ]
        ];

        // Consolidate Class Style (control category)
        $fields['consolidate_class_style'] = [
            'type' => 'yes_no',
            'label' => 'Consolidate Class/Style',
            'desc' => 'Consolidate multiple class and style attributes into single attributes',
            'value' => $current_values['consolidate_class_style'] ?? 'y',
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'consolidate_class_style'
            ]
        ];

        // Create Tag (control category)
        $fields['create_tag'] = [
            'type' => 'yes_no',
            'label' => 'Create HTML Tag',
            'desc' => 'Generate complete HTML <img> tag or return processed image data only',
            'value' => $current_values['create_tag'] ?? 'y',
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'create_tag'
            ]
        ];

        // Disable Browser Checks (control category)
        $fields['disable_browser_checks'] = [
            'type' => 'radio',
            'label' => 'Disable Browser Checks',
            'desc' => 'Skip browser capability checks for image format compatibility',
            'value' => $current_values['disable_browser_checks'] ?? 'no',
            'choices' => [
                'no' => 'No - Check browser compatibility (default)',
                'yes' => 'Yes - Skip browser checks'
            ],
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'disable_browser_checks'
            ]
        ];

        // Exclude Class (control category)
        $fields['exclude_class'] = [
            'type' => 'radio',
            'label' => 'Exclude Class Attributes',
            'desc' => 'Exclude class attributes from generated image tag',
            'value' => $current_values['exclude_class'] ?? 'no',
            'choices' => [
                'no' => 'No - Include class attributes (default)',
                'yes' => 'Yes - Exclude class attributes'
            ],
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'exclude_class'
            ]
        ];

        return $fields;
    }

    /**
     * Validate parameter values for this package
     * 
     * @param array $parameters Parameter values to validate
     * @return array Array of validation errors (empty if valid)
     */
    public function validateParameters(array $parameters): array
    {
        $errors = [];

        // Validate and process source parameter (including EE file directives)
        if (isset($parameters['src'])) {
            if (empty($parameters['src'])) {
                $errors['src'] = 'Source image is required';
            } else {
                // Process EE file directives like {file:135:url} and {filedir_2}filename.jpg
                // This mirrors ValidationService->_validate_src() behavior
                try {
                    $image_utilities = ee('jcogs_img_pro:ImageUtilities');
                    $parsed_src = $image_utilities->parseFiledir($parameters['src']);
                    
                    // Only use parsed result if it's not empty AND different from original
                    // Empty result means parseFiledir couldn't resolve the EE directive but that's OK
                    // for URLs - they should pass through unchanged (mirrors Legacy behavior)
                    if ($parsed_src !== '' && $parsed_src !== $parameters['src']) {
                        // parseFiledir successfully resolved an EE file directive
                        // No validation error - this is expected behavior
                    }
                    // If parsed_src is empty but original contained EE syntax, that means
                    // the directive couldn't be resolved, but we'll let the loading stage handle it
                } catch (\Exception $e) {
                    // If parseFiledir fails, log it but don't fail validation (let loading handle it)
                    // This mirrors Legacy behavior where parseFiledir errors don't stop processing
                }
            }
        }

        // Validate and process fallback_src parameter (including EE file directives)
        if (isset($parameters['fallback_src']) && !empty($parameters['fallback_src'])) {
            try {
                $image_utilities = ee('jcogs_img_pro:ImageUtilities');
                $parsed_fallback = $image_utilities->parseFiledir($parameters['fallback_src']);
                
                // Only use parsed result if it's not empty AND different from original
                // Empty result means parseFiledir couldn't resolve the EE directive but that's OK
                // for URLs - they should pass through unchanged (mirrors Legacy behavior)
                if ($parsed_fallback !== '' && $parsed_fallback !== $parameters['fallback_src']) {
                    // parseFiledir successfully resolved an EE file directive
                    // No validation error - this is expected behavior
                }
                // If parsed_fallback is empty but original contained EE syntax, that means
                // the directive couldn't be resolved, but we'll let the loading stage handle it
            } catch (\Exception $e) {
                // If parseFiledir fails, log it but don't fail validation (let loading handle it)
            }
        }

        // Validate cache parameter - ENHANCED with natural language support
        if (isset($parameters['cache']) && $parameters['cache'] !== '') {
            // Check if custom duration was used
            if ($parameters['cache'] === 'custom' && isset($parameters['cache_custom']) && !empty($parameters['cache_custom'])) {
                // Use DurationParser to validate and convert custom input
                try {
                    $duration_parser = new \JCOGSDesign\JCOGSImagePro\Service\DurationParser();
                    $parse_result = $duration_parser->parseToSeconds($parameters['cache_custom']);
                    
                    if ($parse_result['error']) {
                        $errors['cache_custom'] = $parse_result['error'];
                    } else {
                        // Validate the parsed value for cache context
                        $context_validation = $duration_parser->validateForContext($parse_result['value'], 'cache');
                        if (!$context_validation['valid']) {
                            $errors['cache_custom'] = $context_validation['error'];
                        } else {
                            // Update the cache parameter with the parsed seconds value
                            $parameters['cache'] = $parse_result['value'];
                        }
                    }
                } catch (\Exception $e) {
                    $errors['cache_custom'] = 'Invalid duration format. Use natural language like "1 week" or seconds.';
                }
            } else {
                // Standard numeric validation for preset values
                if (!is_numeric($parameters['cache']) || ($parameters['cache'] < -1)) {
                    $errors['cache'] = 'Cache duration must be -1 (permanent), 0 (no cache), or positive number of seconds';
                }
            }
        }

        // Validate filename parameter
        if (isset($parameters['filename']) && !empty($parameters['filename'])) {
            // Basic filename validation - no path separators
            if (strpos($parameters['filename'], '/') !== false || strpos($parameters['filename'], '\\') !== false) {
                $errors['filename'] = 'Filename cannot contain path separators';
            }
        }

        // Validate output parameter
        if (isset($parameters['output']) && !empty($parameters['output'])) {
            $valid_outputs = ['tag', 'url', 'url_only', 'responsive'];
            if (!in_array($parameters['output'], $valid_outputs)) {
                $errors['output'] = 'Invalid output method';
            }
        }

        // Validate connection parameter
        if (isset($parameters['connection']) && !empty($parameters['connection'])) {
            try {
                $settings_service = ee('jcogs_img_pro:SettingsService');
                $connection_data = $settings_service->getNamedConnection($parameters['connection'], true);
                
                if ($connection_data === null) {
                    $errors['connection'] = 'Connection "' . $parameters['connection'] . '" not found. Please verify the connection name or create the connection first.';
                }
            } catch (\Exception $e) {
                $errors['connection'] = 'Error validating connection: ' . $e->getMessage();
            }
        }

        // Validate save_type parameter (image format)
        if (isset($parameters['save_type']) && !empty($parameters['save_type'])) {
            $valid_formats = ['bmp', 'gif', 'jpeg', 'jpg', 'png', 'webp', 'avif'];
            if (!in_array(strtolower($parameters['save_type']), $valid_formats)) {
                $errors['save_type'] = 'Invalid image format. Valid formats: ' . implode(', ', $valid_formats);
            }
        }

        // Validate save_as parameter (alias for save_type)
        if (isset($parameters['save_as']) && !empty($parameters['save_as'])) {
            $valid_formats = ['bmp', 'gif', 'jpeg', 'jpg', 'png', 'webp', 'avif'];
            if (!in_array(strtolower($parameters['save_as']), $valid_formats)) {
                $errors['save_as'] = 'Invalid image format. Valid formats: ' . implode(', ', $valid_formats);
            }
        }

        // Validate lazy parameter
        if (isset($parameters['lazy']) && !empty($parameters['lazy'])) {
            $valid_lazy_values = ['no', 'lqip', 'dominant_color', 'js_lqip', 'js_dominant_color', 'html5'];
            if (!in_array(strtolower($parameters['lazy']), $valid_lazy_values)) {
                $errors['lazy'] = 'Invalid lazy loading method. Valid options: ' . implode(', ', $valid_lazy_values);
            }
        }

        // Validate url_only parameter
        if (isset($parameters['url_only']) && !empty($parameters['url_only'])) {
            $valid_yes_no = ['yes', 'no', 'y', 'n', '1', '0', true, false];
            if (!in_array(strtolower($parameters['url_only']), array_map('strtolower', $valid_yes_no))) {
                $errors['url_only'] = 'Invalid value. Use yes/no, y/n, 1/0, or true/false';
            }
        }

        // Helper function for yes/no validation
        $validate_yes_no = function($param_name, $value) {
            $valid_values = ['yes', 'no', 'y', 'n', '1', '0'];
            return in_array(strtolower($value), $valid_values);
        };

        // Validate boolean-like parameters (yes/no)
        $boolean_params = [
            'add_dims', 'add_dimensions', 'consolidate_class_style', 'create_tag',
            'disable_browser_checks', 'exclude_class', 'exclude_style', 'hash_filename',
            'overwrite_cache', 'use_image_path_prefix', 'interlace', 'preload'
        ];

        foreach ($boolean_params as $param) {
            if (isset($parameters[$param]) && !empty($parameters[$param])) {
                if (!$validate_yes_no($param, $parameters[$param])) {
                    $errors[$param] = 'Invalid value for ' . $param . '. Use yes/no, y/n, 1/0';
                }
            }
        }

        // Validate filename parameters (no path separators)
        $filename_params = ['filename', 'filename_prefix', 'filename_suffix'];
        foreach ($filename_params as $param) {
            if (isset($parameters[$param]) && !empty($parameters[$param])) {
                if (strpos($parameters[$param], '/') !== false || strpos($parameters[$param], '\\') !== false) {
                    $errors[$param] = ucfirst($param) . ' cannot contain path separators (/ or \\)';
                }
            }
        }

        // Validate string parameters (basic string validation)
        $string_params = ['cache_dir', 'image_path_prefix', 'attributes', 'exclude_regex', 'sizes', 'srcset'];
        foreach ($string_params as $param) {
            if (isset($parameters[$param]) && !empty($parameters[$param])) {
                if (!is_string($parameters[$param])) {
                    $errors[$param] = ucfirst($param) . ' must be a string';
                }
            }
        }

        // Validate palette_size parameter (integer >= 2)
        if (isset($parameters['palette_size']) && !empty($parameters['palette_size'])) {
            if (!is_numeric($parameters['palette_size']) || intval($parameters['palette_size']) < 2) {
                $errors['palette_size'] = 'Palette size must be an integer value of 2 or greater';
            }
        }

        // Validate quality parameter (0-100 or "lossless")
        if (isset($parameters['quality']) && !empty($parameters['quality'])) {
            if (strtolower($parameters['quality']) !== 'lossless') {
                if (!is_numeric($parameters['quality']) || intval($parameters['quality']) < 0 || intval($parameters['quality']) > 100) {
                    $errors['quality'] = 'Quality must be an integer between 0-100 or "lossless"';
                }
            }
        }

        // Validate png_quality parameter (0-9)
        if (isset($parameters['png_quality']) && !empty($parameters['png_quality'])) {
            if (!is_numeric($parameters['png_quality']) || intval($parameters['png_quality']) < 0 || intval($parameters['png_quality']) > 9) {
                $errors['png_quality'] = 'PNG quality must be an integer between 0-9';
            }
        }

        return $errors;
    }

    /**
     * Transform values from Control Panel form to tag parameters
     * 
     * @param array $form_data Raw form data from Control Panel
     * @return array Tag-compatible parameter array
     */
    public function transformFormToParameters(array $form_data): array
    {
        $parameters = [];

        // Source - pass through as-is
        if (isset($form_data['src']) && !empty($form_data['src'])) {
            $parameters['src'] = trim($form_data['src']);
        }

        // Width - ensure numeric or empty
        if (isset($form_data['width']) && !empty($form_data['width'])) {
            $parameters['width'] = (string) intval($form_data['width']);
        }

        // Height - ensure numeric or empty
        if (isset($form_data['height']) && !empty($form_data['height'])) {
            $parameters['height'] = (string) intval($form_data['height']);
        }

        // Quality - ensure numeric string
        if (isset($form_data['quality']) && !empty($form_data['quality'])) {
            $parameters['quality'] = (string) intval($form_data['quality']);
        }

        // Cache - ensure numeric string
        if (isset($form_data['cache']) && $form_data['cache'] !== '') {
            $parameters['cache'] = (string) intval($form_data['cache']);
        }

        return $parameters;
    }

    /**
     * Get parameter documentation/help text
     * 
     * @return array Parameter name => help text mapping
     */
    public function getParameterDocumentation(): array
    {
        return [
            'src' => 'The source image to process. Can be a file path, EE Files reference ({filedir_X}filename.jpg), or URL.',
            'cache' => 'Cache duration in seconds. Use 0 for no caching, -1 for permanent caching, or number of seconds to cache.',
            'filename' => 'Custom filename for the cached image. Leave blank to use auto-generated names.',
            'output' => 'How to output the processed image: tag (img element), url (URL only), or responsive (responsive img).',
            'connection' => 'Named cache connection to use for storing processed images. Leave blank to use default caching configuration.',
            'save_type' => 'Output image format: jpg, jpeg, png, webp, gif, or avif.',
            'save_as' => 'Alias for save_type. Output image format: jpg, jpeg, png, webp, gif, or avif.',
            'lazy' => 'Lazy loading method: no, lqip, dominant_color, js_lqip, js_dominant_color, or html5.',
            'url_only' => 'Return only the URL instead of complete image tag. Use yes/no, y/n, 1/0, or true/false.'
        ];
    }

    /**
     * Get JavaScript files for dynamic form behavior
     * 
     * @return array JavaScript file paths
     */
    public function getJavaScriptFiles(): array
    {
        return [
            'js/packages/core-parameters.js'
        ];
    }

    /**
     * Sanitize a parameter value for this package
     * 
     * @param string $param_name Parameter name
     * @param mixed $value Raw parameter value
     * @return mixed Sanitized parameter value
     */
    protected function sanitizeParameterValue(string $param_name, $value)
    {
        switch ($param_name) {
            case 'src':
            case 'filename':
            case 'output':
                return trim($value);
                
            case 'cache':
                return is_numeric($value) ? (string) intval($value) : '';
                
            default:
                return parent::sanitizeParameterValue($param_name, $value);
        }
    }
}
