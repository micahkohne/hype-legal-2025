<?php

/**
 * JCOGS Image Pro - Transformational Parameter Package
 * ====================================================
 * Handles all transformational parameters (crop, rotate, flip, resize, effects)
 * 
 * This package manages parameters that control image transformations, effects,
 * and visual modifications following the Legacy categorization system.
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

class TransformationalParameterPackage extends AbstractParameterPackage
{
    /**
     * Get the category name for this parameter package
     * 
     * @return string Package category name
     */
    public function getCategory(): string
    {
        return 'transformational';
    }

    /**
     * Get the description of this parameter package
     * 
     * @return string Package description
     */
    public function getDescription(): string
    {
        return $this->lang('jcogs_img_pro_param_package_transformational_description', 'Parameters that control image transformations, effects, and visual modifications');
    }

    /**
     * Get the package display label for UI
     * 
     * @return string Human-readable package name
     */
    public function getDisplayName(): string
    {
        return $this->lang('jcogs_img_pro_param_package_transformational_display_name', 'Transformational Parameters');
    }

    /**
     * Get the display label for this parameter package
     * 
     * @return string Package label
     */
    public function getLabel(): string
    {
        return $this->lang('jcogs_img_pro_param_package_transformational_display_name', 'Transformational Parameters');
    }

    /**
     * Get the unique name for this parameter package
     * 
     * @return string Package name
     */
    public function getName(): string
    {
        return $this->lang('jcogs_img_pro_param_package_transformational_name', 'transformational');
    }

    /**
     * Get parameter documentation for transformational parameters
     * 
     * @return array Parameter documentation with enhanced formatting
     */
    public function getParameterDocumentation(): array
    {
        return [
            'crop' => [
                'type' => 'transformation',
                'description' => 'Crop method for resizing images',
                'options' => 'none|center|top|bottom|left|right|top-left|top-right|bottom-left|bottom-right|smart|face|entropy|attention',
                'example' => 'center',
                'validation' => 'enum'
            ],
            'rotate' => [
                'type' => 'transformation',
                'description' => 'Rotation angle in degrees',
                'format' => 'number (-360 to 360)',
                'example' => '90',
                'validation' => 'numeric_range'
            ],
            'flip' => [
                'type' => 'transformation',
                'description' => 'Flip direction for the image',
                'options' => 'none|horizontal|vertical|both',
                'example' => 'horizontal',
                'validation' => 'enum'
            ],
            'resize' => [
                'type' => 'transformation',
                'description' => 'Resize method for fitting dimensions',
                'options' => 'fit|fill|stretch|pad',
                'example' => 'fit',
                'validation' => 'enum'
            ],
            'filter' => [
                'type' => 'effect',
                'description' => 'Visual filter to apply to the image',
                'options' => 'none|blur|sharpen|emboss|edge|grayscale|sepia|invert|brightness|contrast|gamma|colorize',
                'example' => 'grayscale',
                'validation' => 'enum'
            ],
            'quality' => [
                'type' => 'optimization',
                'description' => 'JPEG compression quality level',
                'format' => 'number (1-100)',
                'example' => '85',
                'validation' => 'numeric_range'
            ],
            'background' => [
                'type' => 'styling',
                'description' => 'Background color for padding or transparency',
                'format' => 'color (hex, rgb, rgba, or named)',
                'example' => '#ffffff',
                'validation' => 'color_format'
            ],
            'format' => [
                'type' => 'output',
                'description' => 'Output image format',
                'options' => 'auto|jpeg|png|webp|gif|avif',
                'example' => 'webp',
                'validation' => 'enum'
            ]
        ];
    }

    /**
     * Get the parameters handled by this package
     * 
     * @return array Parameter list
     */
    public function getParameters(): array
    {
        return $this->parameterRegistry->getParametersByCategory('transformational');
    }

    /**
     * Priority for transformational parameter package
     * Lower priority than specific parameter packages
     * 
     * @return int Priority order (lower numbers = higher priority)
     */
    public function getPriority(): int
    {
        return 30;  // Lower priority than specific packages like TextParameterPackage (25)
    }

    /**
     * Process form data for filter parameter into proper filter chain format
     * 
     * Converts multi-field filter form data back into the pipe-separated format
     * required by JCOGS Image Pro (e.g., "blur:5|sharpen:80|contrast:10")
     * 
     * @param array $form_data Complete form submission data
     * @return string Formatted filter chain string
     */
    public function processFilterFormData(array $form_data): string
    {
        $filter_chain_parts = [];

        // Find all filter chain fields by looking for filter_chain_N_type pattern
        foreach ($form_data as $key => $value) {
            if (preg_match('/^filter_chain_(\d+)_type$/', $key, $matches)) {
                $filter_index = (int)$matches[1];
                $filter_type = $value;

                // Skip 'none' filter types
                if ($filter_type === 'none' || empty($filter_type)) {
                    continue;
                }

                // Collect parameters for this filter
                $parameters = [];
                $param_index = 1;
                
                while (isset($form_data["filter_chain_{$filter_index}_param{$param_index}"])) {
                    $param_value = trim($form_data["filter_chain_{$filter_index}_param{$param_index}"]);
                    if ($param_value !== '') {
                        $parameters[] = $param_value;
                    }
                    $param_index++;
                }

                // Build filter string
                if (!empty($parameters)) {
                    $filter_chain_parts[] = $filter_type . ':' . implode(',', $parameters);
                } else {
                    // Filter with no parameters
                    $filter_chain_parts[] = $filter_type;
                }
            }
        }

        return empty($filter_chain_parts) ? 'none' : implode('|', $filter_chain_parts);
    }

    /**
     * Process form data for transformational parameters
     * 
     * Handles complex parameter construction, particularly for crop parameter
     * which needs to be built from multiple form fields
     * 
     * @param string $parameter_name The parameter being processed
     * @param array $form_data All form data submitted
     * @return string The constructed parameter value
     */
    public function processParameterFromForm(string $parameter_name, array $form_data): string
    {
        // Handle filter parameter specially - construct from filter chain fields
        if ($parameter_name === 'filter') {
            return $this->processFilterFormData($form_data);
        }

        // For all other parameters, use default behavior
        return parent::processParameterFromForm($parameter_name, $form_data);
    }

    /**
     * Validate transformational parameters with enhanced validation
     * 
     * @param array $parameters Parameters to validate
     * @return array Validation errors (empty if valid)
     */
    public function validateParameters(array $parameters): array
    {
        $errors = [];

        // Validate rotate parameter
        if (isset($parameters['rotate']) && !empty($parameters['rotate'])) {
            if (!is_numeric($parameters['rotate']) || $parameters['rotate'] < -360 || $parameters['rotate'] > 360) {
                $errors['rotate'] = $this->lang('validation_rotate_range', 'Rotation must be between -360 and 360 degrees');
            }
        }

        // Validate quality parameter
        if (isset($parameters['quality']) && !empty($parameters['quality'])) {
            if (!is_numeric($parameters['quality']) || $parameters['quality'] < 1 || $parameters['quality'] > 100) {
                $errors['quality'] = $this->lang('validation_quality_range', 'Quality must be between 1 and 100');
            }
        }

        // Validate PNG quality parameter
        if (isset($parameters['png_quality']) && !empty($parameters['png_quality'])) {
            if (!is_numeric($parameters['png_quality']) || $parameters['png_quality'] < 0 || $parameters['png_quality'] > 9) {
                $errors['png_quality'] = $this->lang('validation_png_quality_range', 'PNG quality must be between 0 and 9');
            }
        }

        // Validate background color format
        if (isset($parameters['background']) && !empty($parameters['background'])) {
            if (!$this->_isValidColor($parameters['background'])) {
                $errors['background'] = $this->lang('validation_background_color', 'Background must be a valid color format (hex, rgb, rgba, or transparent)');
            }
        }

        // Validate allow_scale_larger parameter (yes/no values only)
        if (isset($parameters['allow_scale_larger']) && !empty($parameters['allow_scale_larger'])) {
            if (!in_array($parameters['allow_scale_larger'], ['y', 'n', 'yes', 'no'])) {
                $errors['allow_scale_larger'] = $this->lang('validation_allow_scale_larger', 'Allow scale larger must be y, n, yes, or no');
            }
        }

        // Validate flip parameter
        if (isset($parameters['flip']) && !empty($parameters['flip'])) {
            $valid_flip_values = ['horizontal', 'vertical', 'both', 'h', 'v', 'hv', 'vh'];
            if (!in_array(strtolower($parameters['flip']), $valid_flip_values)) {
                $errors['flip'] = 'Flip must be one of: horizontal, vertical, both (or h, v, hv)';
            }
        }

        // Validate brightness parameter (-100 to +100)
        if (isset($parameters['brightness']) && !empty($parameters['brightness'])) {
            if (!is_numeric($parameters['brightness']) || $parameters['brightness'] < -100 || $parameters['brightness'] > 100) {
                $errors['brightness'] = 'Brightness must be between -100 and +100';
            }
        }

        // Validate contrast parameter (-100 to +100)
        if (isset($parameters['contrast']) && !empty($parameters['contrast'])) {
            if (!is_numeric($parameters['contrast']) || $parameters['contrast'] < -100 || $parameters['contrast'] > 100) {
                $errors['contrast'] = 'Contrast must be between -100 and +100';
            }
        }

        // Validate saturation parameter (-100 to +100)
        if (isset($parameters['saturation']) && !empty($parameters['saturation'])) {
            if (!is_numeric($parameters['saturation']) || $parameters['saturation'] < -100 || $parameters['saturation'] > 100) {
                $errors['saturation'] = 'Saturation must be between -100 and +100';
            }
        }

        // Validate hue parameter (0-360 degrees)
        if (isset($parameters['hue']) && !empty($parameters['hue'])) {
            if (!is_numeric($parameters['hue']) || $parameters['hue'] < 0 || $parameters['hue'] > 360) {
                $errors['hue'] = 'Hue must be between 0 and 360 degrees';
            }
        }

        // Validate blur parameter (positive numbers only)
        if (isset($parameters['blur']) && !empty($parameters['blur'])) {
            if (!is_numeric($parameters['blur']) || $parameters['blur'] <= 0) {
                $errors['blur'] = 'Blur radius must be a positive number';
            }
        }

        // Validate crop parameter (specific position values)
        if (isset($parameters['crop']) && !empty($parameters['crop'])) {
            $valid_crop_positions = [
                'center', 'top', 'bottom', 'left', 'right',
                'top-left', 'top-right', 'bottom-left', 'bottom-right',
                'smart', 'face', 'entropy', 'attention', 'none'
            ];
            if (!in_array(strtolower($parameters['crop']), $valid_crop_positions)) {
                $errors['crop'] = 'Crop position must be one of: ' . implode(', ', $valid_crop_positions);
            }
        }

        // Validate sharpen parameter (0-100)
        if (isset($parameters['sharpen']) && !empty($parameters['sharpen'])) {
            if (!is_numeric($parameters['sharpen']) || $parameters['sharpen'] < 0 || $parameters['sharpen'] > 100) {
                $errors['sharpen'] = 'Sharpen amount must be between 0 and 100';
            }
        }

        // Validate pixelate parameter (positive integer)
        if (isset($parameters['pixelate']) && !empty($parameters['pixelate'])) {
            if (!is_numeric($parameters['pixelate']) || intval($parameters['pixelate']) <= 0) {
                $errors['pixelate'] = 'Pixelate size must be a positive integer';
            }
        }

        return $errors;
    }

    /**
     * Get form fields for transformational parameters with sophisticated multi-option handling
     * 
     * @param array $current_values Current parameter values
     * @return array EE-compatible form field definitions
     */
    protected function getPackageFormFields(array $current_values = []): array
    {
        $fields = [];

        // Rotate parameter with enhanced handling  
        $fields['rotate'] = [
            'type' => 'text',
            'value' => $current_values['rotate'] ?? '0',
            'desc' => $this->lang('parameter_rotate_description', 'Rotate the image by specified degrees'),
            'attrs' => [
                'class' => 'form-control rotation-input',
                'placeholder' => $this->lang('rotate_placeholder', 'e.g., 90, 180, 270'),
                'pattern' => '-?[0-9]+',
                'data-parameter' => 'rotate',
                'data-presets' => '0,45,90,135,180,225,270,315'
            ]
        ];

        // Flip parameter with radio group
        $fields['flip'] = [
            'type' => 'select',
            'value' => $current_values['flip'] ?? 'none',
            'choices' => [
                'none' => $this->lang('flip_option_none', 'No flipping'),
                'horizontal' => $this->lang('flip_option_horizontal', 'Flip horizontally'),
                'vertical' => $this->lang('flip_option_vertical', 'Flip vertically'),
                'both' => $this->lang('flip_option_both', 'Flip both directions')
            ],
            'desc' => $this->lang('parameter_flip_description', 'Flip the image horizontally, vertically, or both'),
            'attrs' => [
                'class' => 'form-control flip-parameter-select',
                'data-parameter' => 'flip'
            ]
        ];

        // Resize method with sophisticated options
        $fields['resize'] = [
            'type' => 'select',
            'value' => $current_values['resize'] ?? 'fit',
            'choices' => [
                'fit' => $this->lang('resize_option_fit', 'Fit within bounds'),
                'fill' => $this->lang('resize_option_fill', 'Fill bounds (may crop)'),
                'stretch' => $this->lang('resize_option_stretch', 'Stretch to exact dimensions'),
                'pad' => $this->lang('resize_option_pad', 'Pad to dimensions')
            ],
            'desc' => $this->lang('parameter_resize_description', 'How to resize the image to fit dimensions'),
            'attrs' => [
                'class' => 'form-control resize-parameter-select',
                'data-parameter' => 'resize'
            ]
        ];

        // Allow Scale Larger - behavior control parameter
        $fields['allow_scale_larger'] = [
            'type' => 'yes_no',
            'label' => $this->lang('jcogs_img_pro_param_transformational_allow_scale_larger_label', 'Allow Scale Larger'),
            'desc' => $this->lang('jcogs_img_pro_param_transformational_allow_scale_larger_desc', 'Allow scaling images larger than their original size'),
            'value' => $current_values['allow_scale_larger'] ?? 'n',
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'allow_scale_larger'
            ]
        ];

        // Filter effects - sophisticated multi-filter chain interface
        // Parse existing filter chain (e.g., "blur:5|sharpen:2|contrast:1.2")
        $this->_generate_filter_chain_fields($fields, $current_values['filter'] ?? '');

        // Quality parameter with slider-like interface
        $fields['quality'] = [
            'type' => 'text',
            'value' => $current_values['quality'] ?? '85',
            'desc' => $this->lang('parameter_quality_description', 'JPEG compression quality (1-100, higher = better quality)'),
            'attrs' => [
                'class' => 'form-control quality-input',
                'placeholder' => $this->lang('quality_placeholder', '1-100'),
                'pattern' => '[0-9]+',
                'min' => '1',
                'max' => '100',
                'data-parameter' => 'quality',
                'data-presets' => '50,70,85,95,100'
            ]
        ];

        // PNG Quality parameter (compression level 0-9)
        $fields['png_quality'] = [
            'type' => 'text',
            'value' => $current_values['png_quality'] ?? '6',
            'desc' => $this->lang('parameter_png_quality_description', 'PNG compression level (0-9, lower = larger file, faster)'),
            'attrs' => [
                'class' => 'form-control quality-input',
                'placeholder' => $this->lang('png_quality_placeholder', '0-9'),
                'pattern' => '[0-9]',
                'min' => '0',
                'max' => '9',
                'data-parameter' => 'png_quality',
                'data-presets' => '0,3,6,9'
            ]
        ];

        // Background color for padding/rotation
        $fields['background'] = [
            'type' => 'text',
            'value' => $current_values['background'] ?? '#ffffff',
            'desc' => $this->lang('parameter_background_description', 'Background color for padding or rotation (hex, rgb, rgba)'),
            'attrs' => [
                'class' => 'form-control color-input',
                'placeholder' => $this->lang('background_placeholder', '#ffffff or rgba(255,255,255,0.5)'),
                'data-parameter' => 'background',
                'data-color-picker' => 'true'
            ]
        ];

        // Format conversion
        $fields['format'] = [
            'type' => 'select',
            'value' => $current_values['format'] ?? 'auto',
            'choices' => [
                'auto' => $this->lang('format_option_auto', 'Auto-detect'),
                'jpeg' => $this->lang('format_option_jpeg', 'JPEG format'),
                'png' => $this->lang('format_option_png', 'PNG format'),
                'webp' => $this->lang('format_option_webp', 'WebP format'),
                'gif' => $this->lang('format_option_gif', 'GIF format'),
                'avif' => $this->lang('format_option_avif', 'AVIF format')
            ],
            'desc' => $this->lang('parameter_format_description', 'Convert image to specified format'),
            'attrs' => [
                'class' => 'form-control format-parameter-select',
                'data-parameter' => 'format'
            ]
        ];

        // Fit method for image dimensions
        $fields['fit'] = [
            'type' => 'select',
            'label' => $this->lang('jcogs_img_pro_param_transformational_fit_label', 'Fit Method'),
            'value' => $current_values['fit'] ?? 'inside',
            'choices' => [
                'inside' => $this->lang('fit_option_inside', 'Inside - Fit within bounds (default)'),
                'outside' => $this->lang('fit_option_outside', 'Outside - Cover entire area (may crop)'),
                'fill' => $this->lang('fit_option_fill', 'Fill - Stretch to exact dimensions'),
                'contain' => $this->lang('fit_option_contain', 'Contain - Fit within bounds with padding')
            ],
            'desc' => $this->lang('jcogs_img_pro_param_transformational_fit_desc', 'How to fit the image within the specified dimensions'),
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'fit'
            ]
        ];

        // Interlace for progressive loading
        $fields['interlace'] = [
            'type' => 'radio',
            'label' => $this->lang('jcogs_img_pro_param_transformational_interlace_label', 'Interlaced/Progressive'),
            'value' => $current_values['interlace'] ?? 'no',
            'choices' => [
                'no' => $this->lang('interlace_option_no', 'No - Standard encoding (default)'),
                'yes' => $this->lang('interlace_option_yes', 'Yes - Progressive/interlaced encoding')
            ],
            'desc' => $this->lang('jcogs_img_pro_param_transformational_interlace_desc', 'Enable progressive JPEG or interlaced PNG for better perceived loading'),
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'interlace'
            ]
        ];

        // Preload attribute for images
        $fields['preload'] = [
            'type' => 'radio',
            'label' => $this->lang('jcogs_img_pro_param_transformational_preload_label', 'Preload Priority'),
            'value' => $current_values['preload'] ?? 'auto',
            'choices' => [
                'auto' => $this->lang('preload_option_auto', 'Auto - Browser decides (default)'),
                'none' => $this->lang('preload_option_none', 'None - No preloading'),
                'metadata' => $this->lang('preload_option_metadata', 'Metadata - Preload metadata only'),
                'low' => $this->lang('preload_option_low', 'Low - Low priority preload'),
                'high' => $this->lang('preload_option_high', 'High - High priority preload')
            ],
            'desc' => $this->lang('jcogs_img_pro_param_transformational_preload_desc', 'Set the loading priority for the image'),
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'preload'
            ]
        ];

        // Auto sharpen after resize
        $fields['auto_sharpen'] = [
            'type' => 'yes_no',
            'label' => $this->lang('jcogs_img_pro_param_transformational_auto_sharpen_label', 'Auto Sharpen'),
            'value' => $current_values['auto_sharpen'] ?? 'y',
            'desc' => $this->lang('jcogs_img_pro_param_transformational_auto_sharpen_desc', 'Automatically apply subtle sharpening after resizing to improve quality'),
            'attrs' => [
                'class' => 'form-control',
                'data-parameter' => 'auto_sharpen'
            ]
        ];

        return $fields;
    }

    /**
     * Generate rotation field with preset angles and custom input
     * 
     * @param string $param_name Parameter name
     * @param mixed $current_value Current parameter value
     * @param array $field_config Field configuration options
     * @return array EE-compatible field definition
     */
    protected function generateRotationField(string $param_name, $current_value = '0', array $field_config = []): array
    {
        $presets = $field_config['presets'] ?? [0, 45, 90, 135, 180, 225, 270, 315];
        $min = $field_config['min'] ?? -360;
        $max = $field_config['max'] ?? 360;
        
        $field_definition = [
            'type' => 'text',
            'value' => $current_value,
            'maxlength' => 4,
            'placeholder' => '0-360',
            'attrs' => 'class="rotation-input" data-presets="' . implode(',', $presets) . '" min="' . $min . '" max="' . $max . '"'
        ];
        
        return $field_definition;
    }

    /**
     * Generate quality field with range slider interface
     * 
     * @param string $param_name Parameter name
     * @param mixed $current_value Current parameter value
     * @param array $field_config Field configuration options
     * @return array EE-compatible field definition
     */
    protected function generateQualityField(string $param_name, $current_value = '85', array $field_config = []): array
    {
        $min = $field_config['min'] ?? 1;
        $max = $field_config['max'] ?? 100;
        $step = $field_config['step'] ?? 1;
        $presets = $field_config['presets'] ?? [50, 70, 85, 95, 100];
        
        $field_definition = [
            'type' => 'range',
            'value' => $current_value,
            'attrs' => 'class="quality-slider" min="' . $min . '" max="' . $max . '" step="' . $step . '" data-presets="' . implode(',', $presets) . '"'
        ];
        
        return $field_definition;
    }

    /**
     * Validate color format (hex, rgb, rgba, named colors)
     * 
     * @param string $color Color value to validate
     * @return bool True if valid color format
     */
    private function _isValidColor(string $color): bool
    {
        $color = trim($color);
        
        // Check for transparent
        if (strtolower($color) === 'transparent') {
            return true;
        }
        
        // Check for hex format (#ffffff or #fff)
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return true;
        }
        
        // Check for rgb/rgba format
        if (preg_match('/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(,\s*[0-9.]+\s*)?\)$/', $color)) {
            return true;
        }
        
        // Check for common named colors
        $named_colors = ['white', 'black', 'red', 'green', 'blue', 'yellow', 'orange', 'purple', 'pink', 'brown', 'gray', 'grey'];
        if (in_array(strtolower($color), $named_colors)) {
            return true;
        }
        
        return false;
    }

    /**
     * Discover all available Pro filters dynamically
     *
     * Scans the Filters directory and analyzes filter classes to determine
     * their parameter requirements and labels for form generation.
     *
     * @return array Array of filter definitions with parameter info
     */
    private function _discover_available_filters(): array
    {
        $filter_types = [
            'none' => ['label' => 'No Filter', 'params' => 0]
        ];

        // Define the filter directory path
        $filters_dir = __DIR__ . '/../../Filters';
        
        if (!is_dir($filters_dir)) {
            return $filter_types;
        }

        // Get all PHP files in the Filters directory
        $filter_files = glob($filters_dir . '/*.php');
        
        // Also check Border subdirectory
        $border_dir = $filters_dir . '/Border';
        if (is_dir($border_dir)) {
            $border_files = glob($border_dir . '/*.php');
            $filter_files = array_merge($filter_files, $border_files);
        }

        foreach ($filter_files as $file_path) {
            $filename = basename($file_path, '.php');
            
            // Skip if it's an abstract class, interface, or base class
            if (strpos($filename, 'Abstract') !== false || 
                strpos($filename, 'Interface') !== false ||
                strpos($filename, 'Base') !== false ||
                $filename === 'ImageAbstractFilter') {
                continue;
            }

            $filter_key = strtolower($filename);
            $filter_info = $this->_analyze_filter_class($file_path, $filename);
            
            if ($filter_info !== null) {
                $filter_types[$filter_key] = $filter_info;
            }
        }

        return $filter_types;
    }

    /**
     * Analyze a filter class to extract parameter information
     *
     * Uses reflection to examine the constructor and extract parameter
     * names, types, and default values for form generation.
     *
     * @param string $file_path Path to the filter class file
     * @param string $class_name Filter class name
     * @return array|null Filter definition or null if analysis fails
     */
    private function _analyze_filter_class(string $file_path, string $class_name): ?array
    {
        try {
            // Determine namespace based on file location
            $namespace = 'JCOGSDesign\\JCOGSImagePro\\Filters';
            if (strpos($file_path, '/Border/') !== false) {
                $namespace .= '\\Border';
            }
            
            $full_class_name = $namespace . '\\' . $class_name;
            
            // Check if class exists (may need to include file)
            if (!class_exists($full_class_name)) {
                require_once $file_path;
            }
            
            if (!class_exists($full_class_name)) {
                return null;
            }

            $reflection = new \ReflectionClass($full_class_name);
            $constructor = $reflection->getConstructor();
            
            $filter_info = [
                'label' => $this->_format_filter_label($class_name),
                'params' => 0,
                'param_labels' => []
            ];

            if ($constructor && $constructor->getNumberOfParameters() > 0) {
                $parameters = $constructor->getParameters();
                $param_labels = [];
                
                foreach ($parameters as $param) {
                    $param_name = $param->getName();
                    $param_type = $param->getType();
                    $param_label = $this->_format_parameter_label($param_name, $param_type);
                    $param_labels[] = $param_label;
                }
                
                $filter_info['params'] = count($param_labels);
                $filter_info['param_labels'] = $param_labels;
            }

            return $filter_info;
            
        } catch (\Exception $e) {
            // If we can't analyze the class, return basic info
            return [
                'label' => $this->_format_filter_label($class_name),
                'params' => 0,
                'param_labels' => []
            ];
        }
    }

    /**
     * Format filter class name into human-readable label
     *
     * Converts CamelCase class names into space-separated labels
     * with proper capitalization.
     *
     * @param string $class_name Filter class name
     * @return string Formatted label
     */
    private function _format_filter_label(string $class_name): string
    {
        // Convert CamelCase to space-separated words
        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $class_name);
        
        // Handle special cases
        $special_cases = [
            'RGBColorspace' => 'RGB Colorspace',
            'HSVColorspace' => 'HSV Colorspace',
            'CMYK' => 'CMYK',
            'RGB' => 'RGB',
            'HSV' => 'HSV',
            'DPI' => 'DPI',
            'LQIP' => 'Low Quality Image Placeholder',
            'QR' => 'QR Code',
            'URL' => 'URL',
            'API' => 'API'
        ];
        
        foreach ($special_cases as $search => $replace) {
            $label = str_replace($search, $replace, $label);
        }
        
        return $label;
    }

    /**
     * Format parameter name into human-readable label
     *
     * Converts parameter names into descriptive labels with
     * type hints and value ranges where applicable.
     *
     * @param string $param_name Parameter name
     * @param \ReflectionType|null $param_type Parameter type
     * @return string Formatted parameter label
     */
    private function _format_parameter_label(string $param_name, ?\ReflectionType $param_type = null): string
    {
        // Convert snake_case or camelCase to proper words
        $label = preg_replace('/[_-]/', ' ', $param_name);
        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $label);
        $label = ucwords($label);
        
        // Add type hints for common parameters
        $type_hints = [
            'radius' => '(pixels)',
            'amount' => '(0-100)',
            'intensity' => '(0-100)', 
            'brightness' => '(-255 to 255)',
            'contrast' => '(-100 to 100)',
            'red' => '(-255 to 255)',
            'green' => '(-255 to 255)',
            'blue' => '(-255 to 255)',
            'alpha' => '(0-127)',
            'opacity' => '(0-1)',
            'angle' => '(degrees)',
            'width' => '(pixels)',
            'height' => '(pixels)',
            'x' => '(pixels)',
            'y' => '(pixels)',
            'size' => '(pixels)',
            'threshold' => '(0-255)',
            'quality' => '(0-100)',
            'compression' => '(0-9)'
        ];
        
        $lower_param = strtolower($param_name);
        foreach ($type_hints as $key => $hint) {
            if (strpos($lower_param, $key) !== false) {
                $label .= ' ' . $hint;
                break;
            }
        }
        
        return $label;
    }

    /**
     * Generate filter chain form fields
     * 
     * Creates form fields for the filter chain interface based on the current filter value.
     * Parses existing filter chain string and generates appropriate form controls.
     * 
     * @param array &$fields Form fields array to modify
     * @param string $current_filter Current filter chain value
     * @return void
     */
    private function _generate_filter_chain_fields(array &$fields, string $current_filter): void
    {
        // Get all available filter types dynamically
        $filter_types = $this->_discover_available_filters();

        // Parse current filter chain
        $parsed_filters = [];
        if ($current_filter && $current_filter !== 'none') {
            $filter_parts = explode('|', $current_filter);
            foreach ($filter_parts as $filter_part) {
                if (strpos($filter_part, ':') !== false) {
                    list($type, $params) = explode(':', $filter_part, 2);
                    $param_values = explode(',', $params);
                } else {
                    $type = $filter_part;
                    $param_values = [];
                }
                $parsed_filters[] = ['type' => $type, 'params' => $param_values];
            }
        }

        // Ensure at least one filter slot exists
        if (empty($parsed_filters)) {
            $parsed_filters[] = ['type' => 'none', 'params' => []];
        }

        // Add filter chain controls
        $fields['filter_chain_label'] = [
            'type' => 'html',
            'content' => '<h5>' . $this->lang('filter_chain_label', 'Filter Chain') . '</h5>' .
                        '<p class="text-muted">' . $this->lang('filter_chain_description', 'Apply multiple image filters in sequence. Filters are processed in order.') . '</p>' .
                        '<div class="filter-chain-container" data-filter-types="' . htmlspecialchars(json_encode($filter_types)) . '">'
        ];

        // Generate fields for each filter in the chain
        foreach ($parsed_filters as $index => $filter) {
            $filter_index = $index + 1;
            
            // Filter type selector
            $fields["filter_chain_{$filter_index}_type"] = [
                'type' => 'select',
                'label' => $this->lang('filter_type_label', 'Filter') . " #{$filter_index}",
                'value' => $filter['type'] ?? 'none',
                'choices' => array_combine(
                    array_keys($filter_types),
                    array_column($filter_types, 'label')
                ),
                'attrs' => [
                    'class' => 'form-control filter-type-select',
                    'data-filter-index' => $filter_index,
                    'onchange' => 'updateFilterParameters(this)'
                ]
            ];

            // Parameter fields for the selected filter
            $selected_filter_type = $filter['type'] ?? 'none';
            if (isset($filter_types[$selected_filter_type]) && $filter_types[$selected_filter_type]['params'] > 0) {
                $param_count = $filter_types[$selected_filter_type]['params'];
                $param_labels = $filter_types[$selected_filter_type]['param_labels'] ?? [];
                
                for ($i = 1; $i <= $param_count; $i++) {
                    $param_label = $param_labels[$i - 1] ?? "Parameter {$i}";
                    $param_value = $filter['params'][$i - 1] ?? '';
                    
                    $fields["filter_chain_{$filter_index}_param{$i}"] = [
                        'type' => 'text',
                        'label' => $param_label,
                        'value' => $param_value,
                        'attrs' => [
                            'class' => 'form-control filter-param-input',
                            'data-filter-index' => $filter_index,
                            'data-param-index' => $i,
                            'placeholder' => $param_label
                        ]
                    ];
                }
            }
        }

        // Add "Add Filter" and "Remove Filter" controls
        $fields['filter_chain_controls'] = [
            'type' => 'html',
            'content' => '</div>' . // Close filter-chain-container
                        '<div class="filter-chain-controls mt-3">' .
                        '<button type="button" class="btn btn-default btn-sm" onclick="addFilterToChain()">' .
                        '<i class="fa fa-plus"></i> ' . $this->lang('add_filter_button', 'Add Filter') .
                        '</button> ' .
                        '<button type="button" class="btn btn-danger btn-sm" onclick="removeLastFilter()">' .
                        '<i class="fa fa-minus"></i> ' . $this->lang('remove_filter_button', 'Remove Last Filter') .
                        '</button>' .
                        '</div>'
        ];
    }
}
