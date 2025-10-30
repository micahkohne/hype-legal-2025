<?php

/**
 * JCOGS Image Pro - Abstract Parameter Package
 * =============================================
 * Base implementation for parameter packages providing common functionality
 * 
 * This abstract class provides default implementations for common parameter package
 * operations while allowing concrete packages to override specific behavior.
 * Integrates with ValidationService and ParameterRegistry for consistency.
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

use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;
use JCOGSDesign\JCOGSImagePro\Service\ParameterRegistry;

abstract class AbstractParameterPackage implements ParameterPackageInterface
{
    /**
     * ServiceCache instance for service access
     * @var ServiceCache
     */
    protected $serviceCache;

    /**
     * ValidationService instance for parameter validation
     * @var mixed ValidationService instance
     */
    protected $validationService;

    /**
     * ParameterRegistry instance for parameter metadata
     * @var ParameterRegistry
     */
    protected $parameterRegistry;

    /**
     * Cached language strings for this package
     * @var array
     */
    protected $languageStrings = [];

    /**
     * Constructor
     * 
     * @param mixed $validationService ValidationService instance
     * @param ParameterRegistry $parameterRegistry Parameter registry instance
     */
    public function __construct(
        $validationService = null,
        ?ParameterRegistry $parameterRegistry = null
    ) {
        // Use ServiceCache for dependency resolution if parameters not provided
        $this->validationService = $validationService ?: ServiceCache::validation();
        $this->parameterRegistry = $parameterRegistry ?: new ParameterRegistry();
        
        // Load language strings for this package
        $this->loadLanguageStrings();
        
        $this->initialize();
    }

    /**
     * Initialize package-specific configuration
     * Called after constructor, override in concrete classes
     * 
     * @return void
     */
    protected function initialize(): void
    {
        // Override in concrete classes for package-specific initialization
    }

    /**
     * Get the package name/identifier
     * Must be implemented by concrete classes
     * 
     * @return string Package identifier
     */
    abstract public function getName(): string;

    /**
     * Get the Legacy parameter category this package belongs to
     * Must be implemented by concrete classes
     * 
     * @return string Legacy category for ParameterRegistry integration
     */
    abstract public function getCategory(): string;

    /**
     * Get all parameters handled by this package
     * Must be implemented by concrete classes
     * 
     * @return array List of parameter names
     */
    abstract public function getParameters(): array;

    /**
     * Get package-specific form field definitions
     * Must be implemented by concrete classes
     * 
     * @param array $current_values Current parameter values
     * @return array Package-specific form fields
     */
    abstract protected function getPackageFormFields(array $current_values = []): array;

    /**
     * Default implementation of form field generation
     * Concrete classes should override getPackageFormFields() instead
     * 
     * @param array $current_values Current parameter values for populating forms
     * @return array EE-compatible form field definitions
     */
    public function generateFormFields(array $current_values = []): array
    {
        // Check if package is enabled
        if (!$this->isEnabled()) {
            return [];
        }

        // Get package-specific fields
        $fields = $this->getPackageFormFields($current_values);

        // Add common field attributes
        foreach ($fields as $field_name => &$field) {
            // Add package identifier for CSS/JS targeting
            $field['attrs']['data-package'] = $this->getName();
            
            // Add parameter documentation as help text if not provided
            if (!isset($field['desc']) || empty($field['desc'])) {
                $documentation = $this->getParameterDocumentation();
                if (isset($documentation[$field_name])) {
                    $field['desc'] = $documentation[$field_name];
                }
            }
        }

        return $fields;
    }

    /**
     * Process form data for a specific parameter and return the final parameter value
     * 
     * This method allows each parameter package to handle complex parameter construction
     * from multiple form fields (e.g., crop parameter from separate enable/position/offset fields)
     * 
     * @param string $parameter_name The parameter being processed
     * @param array $form_data All form data submitted
     * @return string The constructed parameter value
     */
    public function processParameterFromForm(string $parameter_name, array $form_data): string
    {
        // Default implementation - just return the direct form value
        // Override in concrete packages for complex parameter processing
        return $form_data['parameter_value'] ?? '';
    }

    /**
     * Default parameter validation using ValidationService
     * Concrete classes can override for package-specific validation
     * 
     * @param array $parameters Parameter values to validate
     * @return array Array of validation errors (empty if valid)
     */
    public function validateParameters(array $parameters): array
    {
        $errors = [];
        $package_parameters = $this->getParameters();

        foreach ($package_parameters as $param_name) {
            if (isset($parameters[$param_name])) {
                // Use existing ValidationService methods
                $value = $parameters[$param_name];
                
                // Basic validation based on parameter type
                $validation_result = $this->validateParameter($param_name, $value);
                if ($validation_result !== true) {
                    $errors[$param_name] = $validation_result;
                }
            }
        }

        return $errors;
    }

    /**
     * Validate a single parameter value
     * Can be overridden by concrete classes for specific validation logic
     * 
     * @param string $param_name Parameter name
     * @param mixed $value Parameter value
     * @return bool|string True if valid, error message if invalid
     */
    protected function validateParameter(string $param_name, $value)
    {
        // Default validation - ensure value is not empty for required parameters
        if (in_array($param_name, $this->getRequiredParameters())) {
            if (empty($value) && $value !== '0') {
                return "Parameter '{$param_name}' is required";
            }
        }

        return true;
    }

    /**
     * Public wrapper for parameter validation
     * Allows external classes to validate parameters through this package
     * 
     * @param string $param_name Parameter name
     * @param mixed $value Parameter value
     * @return array Validation result with 'valid' boolean and optional 'message'
     */
    public function validateParameterValue(string $param_name, $value): array
    {
        $result = $this->validateParameter($param_name, $value);
        
        if ($result === true) {
            return ['valid' => true, 'processed_value' => $value];
        }
        
        return ['valid' => false, 'message' => $result];
    }

    /**
     * Get required parameters for this package
     * Override in concrete classes to specify required parameters
     * 
     * @return array List of required parameter names
     */
    protected function getRequiredParameters(): array
    {
        return [];
    }

    /**
     * Default form-to-parameters transformation
     * Simply passes through values, override in concrete classes for complex transforms
     * 
     * @param array $form_data Raw form data from Control Panel
     * @return array Tag-compatible parameter array
     */
    public function transformFormToParameters(array $form_data): array
    {
        $parameters = [];
        $package_parameters = $this->getParameters();

        foreach ($package_parameters as $param_name) {
            if (isset($form_data[$param_name])) {
                $parameters[$param_name] = $this->sanitizeParameterValue(
                    $param_name, 
                    $form_data[$param_name]
                );
            }
        }

        return $parameters;
    }

    /**
     * Default parameters-to-form transformation
     * Simply passes through values, override in concrete classes for complex transforms
     * 
     * @param array $parameters Tag parameter values
     * @return array Form-compatible values for populating interface
     */
    public function transformParametersToForm(array $parameters): array
    {
        $form_data = [];
        $package_parameters = $this->getParameters();

        foreach ($package_parameters as $param_name) {
            if (isset($parameters[$param_name])) {
                $form_data[$param_name] = $parameters[$param_name];
            }
        }

        return $form_data;
    }

    /**
     * Sanitize a parameter value for safe storage/use
     * Override in concrete classes for parameter-specific sanitization
     * 
     * @param string $param_name Parameter name
     * @param mixed $value Raw parameter value
     * @return mixed Sanitized parameter value
     */
    protected function sanitizeParameterValue(string $param_name, $value)
    {
        // Basic sanitization - trim strings, ensure proper types
        if (is_string($value)) {
            return trim($value);
        }

        return $value;
    }

    /**
     * Default JavaScript files (empty)
     * Override in concrete classes to include package-specific JS
     * 
     * @return array JavaScript file paths relative to addon directory
     */
    public function getJavaScriptFiles(): array
    {
        return [];
    }

    /**
     * Default CSS files (empty)
     * Override in concrete classes to include package-specific CSS
     * 
     * @return array CSS file paths relative to addon directory
     */
    public function getCssFiles(): array
    {
        return [];
    }

    /**
     * Check if this package is enabled in addon settings
     * Default implementation returns true (enabled)
     * Override in concrete classes to check specific settings
     * 
     * @return bool True if package is enabled for current site
     */
    public function isEnabled(): bool
    {
        // Default to enabled - override in concrete classes to check specific settings
        // Integration with addon settings system via ServiceCache
        $settings = \JCOGSDesign\JCOGSImagePro\Service\ServiceCache::settings();
        
        // Check if there's a specific setting for this package
        $class_name = (new \ReflectionClass($this))->getShortName();
        $package_name = strtolower(str_replace('ParameterPackage', '', $class_name));
        $setting_key = 'img_cp_enable_' . $package_name . '_package';
        
        return $settings->get($setting_key, 'y') === 'y';
    }

    /**
     * Default priority (50 - middle priority)
     * Override in concrete classes to set specific ordering
     * 
     * @return int Priority order (0-100, where 0 is highest priority)
     */
    public function getPriority(): int
    {
        return 50;
    }

    /**
     * Get parameter documentation from ParameterRegistry or package definition
     * Override in concrete classes to provide specific documentation
     * 
     * @return array Parameter name => help text mapping
     */
    public function getParameterDocumentation(): array
    {
        $documentation = [];
        
        foreach ($this->getParameters() as $param_name) {
            $documentation[$param_name] = $this->getParameterHelpText($param_name);
        }

        return $documentation;
    }

    /**
     * Get help text for a specific parameter
     * Override in concrete classes to provide detailed help
     * 
     * @param string $param_name Parameter name
     * @return string Help text for the parameter
     */
    protected function getParameterHelpText(string $param_name): string
    {
        // Default help text based on parameter name
        $help_texts = [
            'width' => 'Image width in pixels. Leave blank to maintain aspect ratio.',
            'height' => 'Image height in pixels. Leave blank to maintain aspect ratio.',
            'quality' => 'JPEG quality setting (1-100). Higher values = better quality, larger files.',
            'cache' => 'Cache duration in seconds. 0 = no caching, -1 = permanent cache.',
            'src' => 'Source image path or EE Files reference.'
        ];

        return $help_texts[$param_name] ?? "Configuration for {$param_name} parameter.";
    }

    /**
     * Load language strings for parameter form generation
     * Loads the comprehensive language file for all parameter-related text
     * 
     * @return void
     */
    protected function loadLanguageStrings(): void
    {
        if (!ee()->lang) {
            return;
        }

        // Load the main parameter language file
        ee()->lang->load('jcogs_img_pro_parameters', 'jcogs_img_pro', FALSE, TRUE, PATH_THIRD . 'jcogs_img_pro/');
        
        // Cache the loaded language strings for this instance
        $this->languageStrings = ee()->lang->language;
    }

    /**
     * Get a language string for parameter form generation
     * 
     * @param string $key Language key (e.g., 'parameter_width_label')
     * @param string $default Default text if key not found
     * @return string Localized text
     */
    protected function lang(string $key, string $default = ''): string
    {
        if (isset($this->languageStrings[$key])) {
            return $this->languageStrings[$key];
        }
        
        return $default ?: $key;
    }

    /**
     * Generate form field HTML for a specific parameter
     * This will be enhanced to handle complex parameter types
     * 
     * @param string $param_name Parameter name
     * @param mixed $current_value Current parameter value
     * @param array $field_config Field configuration options
     * @return string HTML form field
     */
    public function generateFormField(string $param_name, $current_value = '', array $field_config = []): string
    {
        $field_type = $field_config['type'] ?? 'text';
        $label = $this->lang("parameter_{$param_name}_label", ucfirst(str_replace('_', ' ', $param_name)));
        $description = $this->lang("parameter_{$param_name}_description", '');
        
        $field_id = "param_{$param_name}";
        $field_name = "parameters[{$param_name}]";
        
        $html = "<div class='parameter-field' data-parameter='{$param_name}'>";
        $html .= "<label for='{$field_id}' class='parameter-label'>{$label}</label>";
        
        switch ($field_type) {
            case 'text':
            case 'number':
                $html .= "<input type='{$field_type}' id='{$field_id}' name='{$field_name}' value='" . htmlspecialchars($current_value) . "' class='form-control parameter-input' />";
                break;
                
            case 'select':
                $options = $field_config['options'] ?? [];
                $html .= "<select id='{$field_id}' name='{$field_name}' class='form-control parameter-select'>";
                foreach ($options as $value => $option_label) {
                    $selected = ($current_value == $value) ? ' selected="selected"' : '';
                    $html .= "<option value='{$value}'{$selected}>{$option_label}</option>";
                }
                $html .= "</select>";
                break;
                
            case 'textarea':
                $html .= "<textarea id='{$field_id}' name='{$field_name}' class='form-control parameter-textarea' rows='3'>" . htmlspecialchars($current_value) . "</textarea>";
                break;
                
            default:
                $html .= "<input type='text' id='{$field_id}' name='{$field_name}' value='" . htmlspecialchars($current_value) . "' class='form-control parameter-input' />";
        }
        
        if ($description) {
            $html .= "<small class='parameter-description text-muted'>{$description}</small>";
        }
        
        $html .= "</div>";
        
        return $html;
    }

    /**
     * Generate complex multi-option field from pipe-separated parameter documentation
     * This handles the sophisticated parameter syntax found in the documentation
     * 
     * @param string $param_name Parameter name
     * @param string $documentation_string Pipe-separated options string
     * @param mixed $current_value Current parameter value
     * @param array $field_config Additional field configuration
     * @return string HTML form field
     */
    public function generateMultiOptionField(string $param_name, string $documentation_string, $current_value = '', array $field_config = []): string
    {
        $options = $this->parseDocumentationOptions($documentation_string);
        $field_id = "param_{$param_name}";
        $field_name = "parameters[{$param_name}]";
        $label = $this->lang("parameter_{$param_name}_label", ucfirst(str_replace('_', ' ', $param_name)));
        $description = $this->lang("parameter_{$param_name}_description", '');
        
        $html = "<div class='parameter-field multi-option-field' data-parameter='{$param_name}'>";
        $html .= "<label for='{$field_id}' class='parameter-label'>{$label}</label>";
        
        // Determine if this should be a select or radio group based on option count
        $option_count = count($options);
        $use_radio = $option_count <= 5 && !isset($field_config['force_select']);
        
        if ($use_radio) {
            // Radio button group for smaller option sets
            $html .= "<div class='radio-group parameter-radio-group' data-parameter='{$param_name}'>";
            foreach ($options as $option_value => $option_data) {
                $option_id = "{$field_id}_{$option_value}";
                $checked = ($current_value == $option_value) ? ' checked="checked"' : '';
                $option_label = $option_data['label'] ?? $option_value;
                $option_description = $option_data['description'] ?? '';
                
                $html .= "<div class='radio-option'>";
                $html .= "<input type='radio' id='{$option_id}' name='{$field_name}' value='{$option_value}'{$checked} class='parameter-radio' />";
                $html .= "<label for='{$option_id}' class='radio-label'>";
                $html .= "<span class='radio-title'>{$option_label}</span>";
                if ($option_description) {
                    $html .= "<span class='radio-description'>{$option_description}</span>";
                }
                $html .= "</label></div>";
            }
            $html .= "</div>";
        } else {
            // Select dropdown for larger option sets
            $html .= "<select id='{$field_id}' name='{$field_name}' class='form-control parameter-select multi-option-select'>";
            foreach ($options as $option_value => $option_data) {
                $selected = ($current_value == $option_value) ? ' selected="selected"' : '';
                $option_label = $option_data['label'] ?? $option_value;
                $html .= "<option value='{$option_value}'{$selected}>{$option_label}</option>";
            }
            $html .= "</select>";
        }
        
        if ($description) {
            $html .= "<small class='parameter-description text-muted'>{$description}</small>";
        }
        
        $html .= "</div>";
        
        return $html;
    }

    /**
     * Parse pipe-separated documentation options into structured array
     * Handles complex parameter documentation syntax
     * 
     * @param string $documentation_string Pipe-separated options string
     * @return array Structured options array
     */
    protected function parseDocumentationOptions(string $documentation_string): array
    {
        $options = [];
        $raw_options = explode('|', $documentation_string);
        
        foreach ($raw_options as $raw_option) {
            $raw_option = trim($raw_option);
            if (empty($raw_option)) continue;
            
            // Handle options with descriptions (format: "value:description" or "value (description)")
            if (strpos($raw_option, ':') !== false) {
                [$value, $desc] = explode(':', $raw_option, 2);
                $options[trim($value)] = [
                    'label' => trim($value),
                    'description' => trim($desc)
                ];
            } elseif (preg_match('/^(.+?)\s*\((.+?)\)$/', $raw_option, $matches)) {
                $value = trim($matches[1]);
                $desc = trim($matches[2]);
                $options[$value] = [
                    'label' => $value,
                    'description' => $desc
                ];
            } else {
                // Simple value without description
                $options[$raw_option] = [
                    'label' => $raw_option,
                    'description' => ''
                ];
            }
        }
        
        return $options;
    }

    /**
     * Generate coordinate input field for dimensional parameters
     * 
     * @param string $param_name Parameter name
     * @param mixed $current_value Current parameter value
     * @param array $field_config Field configuration options
     * @return string HTML form field
     */
    public function generateCoordinateField(string $param_name, $current_value = '', array $field_config = []): string
    {
        $field_id = "param_{$param_name}";
        $field_name = "parameters[{$param_name}]";
        $label = $this->lang("parameter_{$param_name}_label", ucfirst(str_replace('_', ' ', $param_name)));
        $description = $this->lang("parameter_{$param_name}_description", '');
        $unit = $field_config['unit'] ?? 'px';
        $min_value = $field_config['min'] ?? 1;
        $max_value = $field_config['max'] ?? 10000;
        
        $html = "<div class='parameter-field coordinate-field' data-parameter='{$param_name}'>";
        $html .= "<label for='{$field_id}' class='parameter-label'>{$label}</label>";
        $html .= "<div class='coordinate-input-group'>";
        $html .= "<input type='number' id='{$field_id}' name='{$field_name}' value='{$current_value}' ";
        $html .= "class='form-control coordinate-input' min='{$min_value}' max='{$max_value}' />";
        $html .= "<span class='coordinate-unit'>{$unit}</span>";
        $html .= "</div>";
        
        if ($description) {
            $html .= "<small class='parameter-description text-muted'>{$description}</small>";
        }
        
        $html .= "</div>";
        
        return $html;
    }

    /**
     * Generate color picker field for color-related parameters
     * 
     * @param string $param_name Parameter name
     * @param mixed $current_value Current parameter value
     * @param array $field_config Field configuration options
     * @return string HTML form field
     */
    public function generateColorField(string $param_name, $current_value = '', array $field_config = []): string
    {
        $field_id = "param_{$param_name}";
        $field_name = "parameters[{$param_name}]";
        $label = $this->lang("parameter_{$param_name}_label", ucfirst(str_replace('_', ' ', $param_name)));
        $description = $this->lang("parameter_{$param_name}_description", '');
        $allow_transparency = $field_config['allow_transparency'] ?? true;
        
        $html = "<div class='parameter-field color-field' data-parameter='{$param_name}'>";
        $html .= "<label for='{$field_id}' class='parameter-label'>{$label}</label>";
        $html .= "<div class='color-input-group'>";
        $html .= "<input type='color' id='{$field_id}_picker' class='color-picker' />";
        $html .= "<input type='text' id='{$field_id}' name='{$field_name}' value='{$current_value}' ";
        $html .= "class='form-control color-input' placeholder='#ffffff or rgba(255,255,255,0.5)' />";
        $html .= "</div>";
        
        if ($allow_transparency) {
            $html .= "<small class='color-help'>Supports hex (#ffffff), rgb(255,255,255), and rgba(255,255,255,0.5) formats</small>";
        }
        
        if ($description) {
            $html .= "<small class='parameter-description text-muted'>{$description}</small>";
        }
        
        $html .= "</div>";
        
        return $html;
    }
}
