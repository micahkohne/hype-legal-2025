<?php

/**
 * JCOGS Image Pro - Add Parameter Route
 * ======================================
 * Route for adding parameters to presets
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

namespace JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\Presets;

use Exception;
use JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\ImageAbstractRoute;
use JCOGSDesign\JCOGSImagePro\Service\ParameterRegistry;
use JCOGSDesign\JCOGSImagePro\Service\ParameterPackageDiscovery;

class AddParameter extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'presets/add_parameter';

    /**
     * @var string Control panel page title
     */
    protected $cp_page_title;

    /**
     * Add parameter form processor
     * 
     * @param mixed $preset_id Preset ID to add parameter to (may be unused if extracted from URL)
     * @return $this Fluent interface for EE7 routing
     */
    public function process($preset_id = false)
    {
        // Validate and load preset using shared helper method
        $preset = $this->validateAndLoadPresetFromUrl('add_error');
        if (!$preset) {
            return $this; // Helper method handles error and redirect
        }
        
        // Handle POST request - add the parameter
        if (count($_POST) > 0) {
            return $this->_handle_add_parameter($preset);
        }
        
        // GET request - get parameter from query string
        $parameter_name = ee()->input->get('parameter');
        if (!$parameter_name) {
            ee('CP/Alert')->makeInline('add_error')
                ->asIssue()
                ->withTitle('Invalid Parameter')
                ->addToBody('No parameter specified.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset_id));
            return $this;
        }
        
        // Validate parameter name
        $registry = new ParameterRegistry();
        if (!$registry->parameterExists($parameter_name)) {
            ee('CP/Alert')->makeInline('add_error')
                ->asIssue()
                ->withTitle('Invalid Parameter')
                ->addToBody('The specified parameter is not valid.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset_id));
            return $this;
        }
        
        // Check if parameter already exists in preset
        $parameters = $preset['parameters'];
        if (isset($parameters[$parameter_name])) {
            ee('CP/Alert')->makeInline('add_error')
                ->asIssue()
                ->withTitle('Parameter Exists')
                ->addToBody('This parameter is already configured for this preset.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset_id));
            return $this;
        }
        
        // Show add form
        $this->cp_page_title = 'Add Parameter: ' . ucfirst(str_replace('_', ' ', $parameter_name));
        $this->build_sidebar($this->_get_current_settings());
        $this->addBreadcrumb('presets', 'Preset Management', ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
        $this->addBreadcrumb('edit', 'Edit: ' . $preset['name'], ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset_id));
        $this->addBreadcrumb('add_parameter', $this->cp_page_title);

        // Build the add form
        $variables = $this->_build_add_form($preset, $parameter_name);

        // Load CSS assets for the parameter add interface
        $this->_load_parameter_add_assets();

        // Set the page body
        $this->setBody('preset_add_parameter', $variables);

        return $this;
    }

    /**
     * Handle POST request to add parameter
     * 
     * @param array $preset Current preset data
     * @return $this
     */
    private function _handle_add_parameter(array $preset)
    {
        $parameter_name = ee()->input->post('parameter');
        $parameter_value = ee()->input->post('parameter_value') ?: '';
        
        // Basic validation
        if (empty($parameter_name)) {
            ee('CP/Alert')->makeInline('add_error')
                ->asIssue()
                ->withTitle('Validation Error')
                ->addToBody('Parameter name is required.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset['id']));
            return $this;
        }
        
        try {
            // Get current parameters
            $parameters = $preset['parameters'];
            
            // Add the new parameter
            $parameters[$parameter_name] = $parameter_value;
            
            // Update the preset
            $result = $this->preset_service->updatePreset($preset['name'], [
                'parameters' => $parameters
            ]);
            
            if ($result['success']) {
                ee('CP/Alert')->makeInline('add_success')
                    ->asSuccess()
                    ->withTitle('Parameter Added')
                    ->addToBody('Parameter "' . htmlspecialchars($parameter_name) . '" has been added successfully.')
                    ->defer();
            } else {
                throw new Exception($result['error'] ?? 'Unknown error occurred');
            }
            
        } catch (Exception $e) {
            $this->utilities_service->debug_log('parameter_add_error', $e->getMessage());
            
            ee('CP/Alert')->makeInline('add_error')
                ->asIssue()
                ->withTitle('Add Failed')
                ->addToBody('Failed to add parameter: ' . $e->getMessage())
                ->defer();
        }
        
        // Redirect back to edit page
        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset['id']));
        return $this;
    }

    /**
     * Build add parameter form
     * 
     * @param array $preset Current preset data
     * @param string $parameter_name Parameter to add
     * @return array Template variables
     */
    private function _build_add_form(array $preset, string $parameter_name): array
    {
        $form = ee('CP/Form');
        
        // Set form action URL
        $form_action_url = ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/add_parameter/' . $preset['id']);
        $form->setBaseUrl($form_action_url);
        
        // Configure form buttons
        $form->set('save_btn_text', 'Add Parameter');
        $form->set('save_btn_text_working', 'Adding');
        
        // Get parameter category and package information
        $registry = new ParameterRegistry();
        $category = $registry->getParameterCategory($parameter_name);
        
        // Create group for Parameter Details
        $details_group = $form->getGroup('Parameter Details');
        
        // Parameter name fieldset
        $name_fieldset = $details_group->getFieldSet('Parameter Name');
        $name_fieldset->setDesc('Parameter: ' . $parameter_name . ' (Category: ' . ucfirst($category) . ')');
        $name_fieldset->getField('parameter_name', 'hidden')
            ->setValue($parameter_name);
        $readonly_field = $name_fieldset->getField('parameter_name_display', 'text')
            ->setValue($parameter_name);
        $readonly_field->set('attrs', 'readonly="readonly" class="form-control readonly-field"');
        
        // Use parameter package for sophisticated form field generation
        try {
            $discovery = new ParameterPackageDiscovery($registry);
            $packages = $discovery->getPackagesByCategory($category);
            
            if (!empty($packages)) {
                // Find the package that handles this parameter
                $target_package = null;
                foreach ($packages as $package) {
                    if (in_array($parameter_name, $package->getParameters())) {
                        $target_package = $package;
                        break;
                    }
                }
                
                if ($target_package) {
                    // Generate sophisticated form fields using parameter package
                    $current_values = []; // No current value for new parameter
                    $package_fields = $target_package->generateFormFields($current_values);
                    
                    // Get the specific field for this parameter
                    if (isset($package_fields[$parameter_name])) {
                        $field_config = $package_fields[$parameter_name];
                        
                        // Create parameter value fieldset with package-generated field
                        $value_fieldset = $details_group->getFieldSet('Parameter Value');
                        $value_fieldset->setDesc($field_config['desc'] ?? 'Configure the value for this parameter');
                        
                        // Create the appropriate field type based on package configuration
                        $field = $value_fieldset->getField('parameter_value', $field_config['type'] ?? 'text');
                        $field->setValue($field_config['value'] ?? '');
                        
                        // Apply field attributes using EE7 format
                        if (isset($field_config['attrs'])) {
                            $attrs_string = '';
                            foreach ($field_config['attrs'] as $attr => $attr_value) {
                                $attrs_string .= $attr . '="' . htmlspecialchars($attr_value) . '" ';
                            }
                            if (!empty($attrs_string)) {
                                $field->set('attrs', trim($attrs_string));
                            }
                        }
                        
                        // Add choices for select fields
                        if (($field_config['type'] ?? '') === 'select' && isset($field_config['choices'])) {
                            $field->setChoices($field_config['choices']);
                        }
                        
                        // Mark as required if specified
                        if (isset($field_config['required']) && $field_config['required']) {
                            $field->setRequired();
                        }
                    } else {
                        // Fallback to basic text field if parameter not found in package
                        $this->_add_fallback_field($details_group, $parameter_name);
                    }
                } else {
                    // Fallback to basic text field if no package handles this parameter
                    $this->_add_fallback_field($details_group, $parameter_name);
                }
            } else {
                // Fallback to basic text field if no packages for this category
                $this->_add_fallback_field($details_group, $parameter_name);
            }
        } catch (Exception $e) {
            // Fallback to basic text field on any errors
            $this->_add_fallback_field($details_group, $parameter_name);
            
            // Log the error for debugging
            ee('jcogs_img_pro:Logging')->error('AddParameter package integration error: ' . $e->getMessage(), [
                'parameter_name' => $parameter_name,
                'category' => $category,
                'preset_id' => $preset['id']
            ]);
        }
        
        return [
            'cp_page_title' => $this->cp_page_title,
            'form' => $form,
            'preset' => $preset,
            'parameter_name' => $parameter_name,
            'cancel_url' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset['id'])
        ];
    }
    
    /**
     * Add fallback text field when parameter packages are unavailable
     * 
     * @param mixed $details_group Form group instance
     * @param string $parameter_name Parameter name
     * @return void
     */
    private function _add_fallback_field($details_group, string $parameter_name): void
    {
        $value_fieldset = $details_group->getFieldSet('Parameter Value');
        $value_fieldset->setDesc('Enter the value for this parameter (same format as used in tags)');
        $fallback_field = $value_fieldset->getField('parameter_value', 'text')
            ->setValue('');
        $fallback_field->set('attrs', 'class="form-control" placeholder="Enter parameter value..."');
    }

    /**
     * Load CSS assets for the parameter add interface
     * 
     * @return void
     */
    private function _load_parameter_add_assets(): void
    {
        // Add parameter add CSS file using EE's recommended method
        ee()->cp->add_to_head('<link rel="stylesheet" type="text/css" href="' . URL_THIRD_THEMES . 'jcogs_img_pro/css/parameter-add.css">');
    }
}
