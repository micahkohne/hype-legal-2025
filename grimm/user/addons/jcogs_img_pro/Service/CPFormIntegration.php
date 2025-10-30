<?php

/**
 * JCOGS Image Pro - CP Form Integration Service
 * =============================================
 * Service for integrating parameter packages with CP form generation
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 4 CP Integration Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service;

use JCOGSDesign\JCOGSImagePro\Service\ParameterDefaultMapping;
use JCOGSDesign\JCOGSImagePro\Service\FormatCapabilityDetection;
use JCOGSDesign\JCOGSImagePro\Service\ParameterPackageDiscovery;
use JCOGSDesign\JCOGSImagePro\Service\ParameterRegistry;

class CPFormIntegration
{
    /**
     * @var ParameterDefaultMapping
     */
    private $mapping_service;
    
    /**
     * @var FormatCapabilityDetection
     */
    private $format_detection;
    
    /**
     * @var ParameterPackageDiscovery
     */
    private $package_discovery;
    
    /**
     * @var ParameterRegistry
     */
    private $parameter_registry;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->mapping_service = new ParameterDefaultMapping();
        $this->format_detection = new FormatCapabilityDetection();
        $this->parameter_registry = new ParameterRegistry();
        $this->package_discovery = new ParameterPackageDiscovery($this->parameter_registry);
    }

    /**
     * Generate form field for a CP setting using parameter packages
     * 
     * @param object $form_group EE CP Form group
     * @param string $setting_key CP setting key
     * @param mixed $current_value Current setting value
     * @param array $options Additional options for field generation
     * @return bool True if field was generated via packages, false for fallback
     */
    public function generateCPFormField($form_group, string $setting_key, $current_value, array $options = []): bool
    {
        $package_info = $this->mapping_service->getPackageForCPSetting($setting_key);
        
        if (!$package_info) {
            // Not mapped to parameter packages - use legacy approach
            return false;
        }

        try {
            // Get the appropriate parameter package
            $packages = $this->package_discovery->getPackagesByCategory($package_info['category']);
            $target_package = $this->findPackageForParameter($packages, $package_info['parameter']);
            
            if (!$target_package) {
                return false;
            }

            // Generate the field based on the package configuration and type
            $this->generateFieldByType($form_group, $setting_key, $package_info, $current_value, $options);
            
            return true;
            
        } catch (\Exception $e) {
            // Log error and fallback to legacy approach
            error_log('JCOGS Image Pro CP Form Integration Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate form field based on the mapped type
     * 
     * @param object $form_group EE CP Form group
     * @param string $setting_key CP setting key
     * @param array $package_info Package mapping information
     * @param mixed $current_value Current setting value
     * @param array $options Additional options
     */
    private function generateFieldByType($form_group, string $setting_key, array $package_info, $current_value, array $options): void
    {
        $field_type = $package_info['type'] ?? 'text';
        $parameter_name = $package_info['parameter'];
        
        // Get language keys for labels and descriptions
        $label_key = "jcogs_img_pro_param_{$package_info['category']}_{$parameter_name}_label";
        $desc_key = "jcogs_img_pro_param_{$package_info['category']}_{$parameter_name}_desc";
        
        $label = lang($label_key) ?: ucwords(str_replace('_', ' ', $parameter_name));
        $description = lang($desc_key) ?: '';

        // Create the fieldset
        $fieldset = $form_group->getFieldSet($label);
        if (!empty($description)) {
            $fieldset->setDesc($description);
        }

        // Generate field based on type
        switch ($field_type) {
            case 'slider':
                $this->generateSliderField($fieldset, $setting_key, $package_info, $current_value);
                break;
                
            case 'color_picker':
                $this->generateColorPickerField($fieldset, $setting_key, $current_value);
                break;
                
            case 'dynamic_format_select':
                $this->generateDynamicFormatSelectField($fieldset, $setting_key, $current_value);
                break;
                
            case 'yes_no':
                $this->generateYesNoField($fieldset, $setting_key, $current_value);
                break;
                
            case 'select':
                $this->generateSelectField($fieldset, $setting_key, $package_info, $current_value);
                break;
                
            case 'text':
            default:
                $this->generateTextField($fieldset, $setting_key, $package_info, $current_value);
                break;
        }
    }

    /**
     * Generate slider field (for quality settings)
     */
    private function generateSliderField($fieldset, string $setting_key, array $package_info, $current_value): void
    {
        $field = $fieldset->getField($setting_key, 'slider');
        $field->setValue($current_value ?? ($package_info['min'] ?? 0));
        
        if (isset($package_info['min'])) {
            $field->set('min', $package_info['min']);
        }
        if (isset($package_info['max'])) {
            $field->set('max', $package_info['max']);
        }
        if (isset($package_info['step'])) {
            $field->set('step', $package_info['step']);
        }
        
        // Add custom CSS class for enhanced styling
        $field->set('class', 'jcogs-quality-slider');
    }

    /**
     * Generate color picker field
     */
    private function generateColorPickerField($fieldset, string $setting_key, $current_value): void
    {
        $field = $fieldset->getField($setting_key, 'color_picker');
        $field->setValue($current_value ?? '#ffffff');
        
        // Set default color palette
        $field->set('allowed_colors', [
            '#ffffff', '#000000', '#ff0000', '#00ff00', '#0000ff',
            '#ffff00', '#ff00ff', '#00ffff', '#808080', '#c0c0c0'
        ]);
    }

    /**
     * Generate dynamic format select field using capability detection
     */
    private function generateDynamicFormatSelectField($fieldset, string $setting_key, $current_value): void
    {
        $field = $fieldset->getField($setting_key, 'select');
        $choices = $this->format_detection->getFormatChoices();
        
        $field->setChoices($choices);
        $field->setValue($current_value ?? 'source');
        
        // Add data attributes for JavaScript enhancement
        $field->set('data-capability-driven', 'true');
        $field->set('class', 'jcogs-format-select');
    }

    /**
     * Generate yes/no toggle field
     */
    private function generateYesNoField($fieldset, string $setting_key, $current_value): void
    {
        $field = $fieldset->getField($setting_key, 'yes_no');
        $field->setValue($current_value ?? 'n');
    }

    /**
     * Generate select field with predefined choices
     */
    private function generateSelectField($fieldset, string $setting_key, array $package_info, $current_value): void
    {
        $field = $fieldset->getField($setting_key, 'select');
        
        // Get choices from package info or parameter package
        $choices = $package_info['choices'] ?? [];
        if (empty($choices)) {
            // Try to get choices from the parameter package itself
            $packages = $this->package_discovery->getPackagesByCategory($package_info['category']);
            $target_package = $this->findPackageForParameter($packages, $package_info['parameter']);
            if ($target_package && method_exists($target_package, 'getChoicesFor')) {
                $choices = $target_package->getChoicesFor($package_info['parameter']);
            }
        }
        
        if (!empty($choices)) {
            $field->setChoices($choices);
        }
        
        $field->setValue($current_value ?? '');
    }

    /**
     * Generate text field with validation
     */
    private function generateTextField($fieldset, string $setting_key, array $package_info, $current_value): void
    {
        $field = $fieldset->getField($setting_key, 'text');
        $field->setValue($current_value ?? '');
        
        // Add validation attributes
        if (isset($package_info['validation'])) {
            $field->set('data-validation', $package_info['validation']);
        }
        
        if (isset($package_info['min'])) {
            $field->set('min', $package_info['min']);
        }
        
        if (isset($package_info['max'])) {
            $field->set('max', $package_info['max']);
        }
        
        // Set field as required if specified
        if (!empty($package_info['required'])) {
            $field->setRequired(true);
        }
    }

    /**
     * Validate form submission using parameter packages
     * 
     * @param array $form_data Submitted form data
     * @return array Validation results with 'valid' boolean and 'errors' array
     */
    public function validateCPFormSubmission(array $form_data): array
    {
        $errors = [];
        $processed_data = [];
        
        foreach ($form_data as $setting_key => $value) {
            $package_info = $this->mapping_service->getPackageForCPSetting($setting_key);
            
            if ($package_info) {
                // Use parameter package validation
                $validation_result = $this->validateSettingValue($setting_key, $value, $package_info);
                
                if (!$validation_result['valid']) {
                    $errors[$setting_key] = $validation_result['error'];
                } else {
                    $processed_data[$setting_key] = $validation_result['processed_value'];
                }
            } else {
                // No package mapping - pass through unchanged
                $processed_data[$setting_key] = $value;
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'processed_data' => $processed_data
        ];
    }

    /**
     * Validate individual setting value using parameter packages
     * 
     * @param string $setting_key CP setting key
     * @param mixed $value Submitted value
     * @param array $package_info Package mapping information
     * @return array Validation result
     */
    private function validateSettingValue(string $setting_key, $value, array $package_info): array
    {
        try {
            $packages = $this->package_discovery->getPackagesByCategory($package_info['category']);
            $target_package = $this->findPackageForParameter($packages, $package_info['parameter']);
            
            if (!$target_package) {
                return ['valid' => true, 'processed_value' => $value];
            }
            
            // Use package validation if available
            if (method_exists($target_package, 'validateParameterValue')) {
                return $target_package->validateParameterValue($package_info['parameter'], $value);
            }
            
            // Fall back to basic validation based on type
            return $this->basicValidation($value, $package_info);
            
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => 'Validation error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Basic validation for parameters without package validation
     */
    private function basicValidation($value, array $package_info): array
    {
        $validation_type = $package_info['validation'] ?? null;
        
        switch ($validation_type) {
            case 'positive_integer':
                if (!is_numeric($value) || (int)$value <= 0) {
                    return ['valid' => false, 'error' => 'Must be a positive integer'];
                }
                return ['valid' => true, 'processed_value' => (int)$value];
                
            case 'memory_value':
                if (!preg_match('/^\d+[MG]?$/i', $value) && !is_numeric($value)) {
                    return ['valid' => false, 'error' => 'Invalid memory format (e.g., 128M, 1G, or plain number)'];
                }
                return ['valid' => true, 'processed_value' => $value];
                
            case 'filename_separator':
                if (empty($value) || preg_match('/\s+/', $value)) {
                    return ['valid' => false, 'error' => 'Filename separator cannot be empty or contain spaces'];
                }
                return ['valid' => true, 'processed_value' => $value];
                
            default:
                return ['valid' => true, 'processed_value' => $value];
        }
    }

    /**
     * Find package that handles a specific parameter
     * 
     * @param array $packages Available packages
     * @param string $parameter_name Parameter to find
     * @return object|null Package instance or null
     */
    private function findPackageForParameter(array $packages, string $parameter_name): ?object
    {
        $best_package = null;
        $best_priority = -1;
        
        foreach ($packages as $package) {
            $package_params = $package->getParameters();
            if (in_array($parameter_name, $package_params)) {
                $priority = $package->getPriority();
                if ($priority > $best_priority) {
                    $best_package = $package;
                    $best_priority = $priority;
                }
            }
        }
        
        return $best_package;
    }

    /**
     * Get enhanced form field information for a CP setting
     * 
     * @param string $setting_key CP setting key
     * @return array|null Enhanced field information or null if not mapped
     */
    public function getEnhancedFieldInfo(string $setting_key): ?array
    {
        $package_info = $this->mapping_service->getPackageForCPSetting($setting_key);
        
        if (!$package_info) {
            return null;
        }
        
        // Add dynamic capabilities for format fields
        if ($package_info['type'] === 'dynamic_format_select') {
            $package_info['dynamic_choices'] = $this->format_detection->getFormatChoices();
            $package_info['capabilities'] = $this->format_detection->getDetailedCapabilities();
        }
        
        return $package_info;
    }
}
