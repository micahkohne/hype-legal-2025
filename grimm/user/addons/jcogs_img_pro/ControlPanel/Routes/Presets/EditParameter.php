<?php

/**
 * JCOGS Image Pro - Edit Parameter Route
 * ======================================
 * Route for editing individual preset parameters
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://jcogs.net/add-ons/jcogs_img_pro
 * @since      Presets Feature Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\Presets;

use Exception;
use JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\ImageAbstractRoute;
use JCOGSDesign\JCOGSImagePro\Service\ParameterRegistry;
use JCOGSDesign\JCOGSImagePro\Service\ParameterPackageDiscovery;

class EditParameter extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'presets/edit_parameter';

    /**
     * @var string Control panel page title
     */
    protected $cp_page_title;

    /**
     * Edit parameter processor
     * 
     * @param mixed $preset_id Preset ID (may be unused if extracted from URL)
     * @return $this Fluent interface for EE7 routing
     */
    public function process($preset_id = false)
    {
        // Get preset ID and parameter name from URL segments
        $preset_id = $this->utilities_service->getPresetIdFromUrl();
        $parameter_name = $this->utilities_service->getParameterNameFromUrl();
        
        if (empty($preset_id) || empty($parameter_name)) {
            ee('CP/Alert')->makeInline('edit_parameter_error')
                ->asIssue()
                ->withTitle('Invalid Request')
                ->addToBody('Missing preset ID or parameter name.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
            return $this;
        }
        
        // Load the preset using shared PresetService
        $preset = $this->preset_service->getPresetById($preset_id);
        if (!$preset) {
            ee('CP/Alert')->makeInline('edit_parameter_error')
                ->asIssue()
                ->withTitle('Preset Not Found')
                ->addToBody('The specified preset could not be found.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
            return $this;
        }

        // Check if parameter exists
        $parameters = $preset['parameters'] ?? [];
        if (!isset($parameters[$parameter_name])) {
            ee('CP/Alert')->makeInline('edit_parameter_error')
                ->asIssue()
                ->withTitle('Parameter Not Found')
                ->addToBody("Parameter '{$parameter_name}' not found in this preset.")
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset_id));
            return $this;
        }

        // Handle POST request - update parameter
        if (count($_POST) > 0) {
            return $this->_handle_update_parameter($preset, $parameter_name);
        }
        
        // GET request - show edit form
        $this->cp_page_title = "Edit Parameter: {$parameter_name}";
        $this->build_sidebar($this->_get_current_settings());
        $this->addBreadcrumb('presets', 'Preset Management', ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
        $this->addBreadcrumb('edit', $preset['name'], ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset_id));
        $this->addBreadcrumb('edit_parameter', $this->cp_page_title);

        // Build the edit interface
        $variables = $this->_build_edit_interface($preset, $parameter_name);

        // Set the page body
        $this->setBody('parameter_edit', $variables);

        return $this;
    }

    /**
     * Handle POST request to update parameter
     * 
     * @param array $preset Current preset data
     * @param string $parameter_name Parameter to update
     * @return $this
     */
    private function _handle_update_parameter(array $preset, string $parameter_name)
    {
        try {
            // Get all form data directly from $_POST
            $form_data = $_POST;
            
            // Ensure form_data is an array
            if (!is_array($form_data)) {
                throw new Exception("Invalid form data received. Expected array, got: " . gettype($form_data));
            }
            
            // Check if we have any form data
            if (empty($form_data)) {
                throw new Exception("No form data received");
            }
            
            // Get the appropriate parameter package for this parameter
            $parameter_category = ParameterRegistry::getParameterCategory($parameter_name);
            $target_package = $this->_get_package_for_parameter($parameter_name, $parameter_category);
            
            if (!$target_package) {
                throw new Exception("No package found for parameter: {$parameter_name} (category: {$parameter_category})");
            }
            
            // Let the package process the form data and construct the final parameter value
            $parameter_value = $target_package->processParameterFromForm($parameter_name, $form_data);
            
            // Validate the processed parameter value
            $validation_result = $this->_validate_parameter_value($parameter_name, $parameter_value);
            if ($validation_result !== true) {
                throw new Exception($validation_result);
            }
            
            // Get current parameters
            $parameters = $preset['parameters'] ?? [];
            
            // Update the specific parameter
            $parameters[$parameter_name] = $parameter_value;
            
            // Update the preset with new parameters
            $result = $this->preset_service->updatePreset($preset['name'], [
                'parameters' => $parameters
            ]);
            
            if ($result['success']) {
                ee('CP/Alert')->makeInline('update_success')
                    ->asSuccess()
                    ->withTitle('Parameter Updated')
                    ->addToBody("Parameter '{$parameter_name}' has been updated successfully.")
                    ->defer();
                
                // Check the action type to determine where to redirect
                $action_type = $form_data['action_type'] ?? 'save_close';
                
                if ($action_type === 'save_continue') {
                    // Redirect back to parameter edit page to continue editing
                    ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit_parameter/' . $preset['id'] . '/' . urlencode($parameter_name)));
                } else {
                    // Default: redirect to preset edit page 
                    ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset['id']));
                }
            } else {
                // Handle different error formats from PresetService
                $error_message = 'Unknown error occurred';
                
                if (!empty($result['errors'])) {
                    // Handle array of errors
                    if (is_array($result['errors'])) {
                        $error_message = implode('; ', $result['errors']);
                    } else {
                        $error_message = $result['errors'];
                    }
                } elseif (!empty($result['error'])) {
                    // Handle single error message
                    $error_message = $result['error'];
                }
                
                throw new Exception($error_message);
            }
            
        } catch (Exception $e) {
            $this->utilities_service->debug_log('parameter_update_error', $e->getMessage());
            
            ee('CP/Alert')->makeInline('update_error')
                ->asIssue()
                ->withTitle('Update Failed')
                ->addToBody('Failed to update parameter: ' . $e->getMessage())
                ->defer();
        }
        
        // Redirect back to this page on error
        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit_parameter/' . $preset['id'] . '/' . urlencode($parameter_name)));
        return $this;
    }

    /**
     * Build edit interface
     * 
     * @param array $preset Current preset data
     * @param string $parameter_name Parameter to edit
     * @return array Template variables
     */
    private function _build_edit_interface(array $preset, string $parameter_name): array
    {
        $parameters = $preset['parameters'] ?? [];
        $current_value = $parameters[$parameter_name] ?? '';
        
        // Get parameter information from registry
        $registry = new ParameterRegistry();
        $parameter_category = ParameterRegistry::getParameterCategory($parameter_name);

        // Build basic parameter info since getParameterInfo() doesn't exist
        $parameter_info = [
            'category' => $parameter_category,
            'description' => 'Configure the value for the ' . ucfirst(str_replace('_', ' ', $parameter_name)) . ' parameter',
            'type' => 'text' // Default to text input
        ];
        
        // Build parameter edit form and render it to string
        $parameter_form = $this->_build_parameter_form($preset, $parameter_name, $current_value, $parameter_info);

        // Inject CSS to hide the default form footer save button
        $this->_load_parameter_edit_assets();

        return [
            'cp_page_title' => $this->cp_page_title,
            'preset' => $preset,
            'parameter_name' => $parameter_name,
            'current_value' => $current_value,
            'parameter_info' => $parameter_info,
            'parameter_form' => $parameter_form->render(), // Convert form to string
            'edit_preset_url' => (string) ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset['id']),
            'presets_url' => (string) ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets')
        ];
    }

    /**
     * Build parameter edit form
     * 
     * @param array $preset Current preset data
     * @param string $parameter_name Parameter to edit
     * @param string $current_value Current parameter value
     * @param array $parameter_info Parameter metadata from registry
     * @return mixed CP/Form instance
     */
    private function _build_parameter_form(array $preset, string $parameter_name, string $current_value, array $parameter_info)
    {
        $form = ee('CP/Form');
        
        // Set form action URL
        $form_action_url = ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit_parameter/' . $preset['id'] . '/' . urlencode($parameter_name));
        $form->setBaseUrl($form_action_url);
        
        // Configure form buttons - we'll add custom buttons with different actions
        $form->set('hide_top_buttons', true);
        $form->set('hide_bottom_buttons', true); // We'll create custom buttons
        $form->set('save_btn_text', ''); // Also try to clear the save button text
        $form->set('save_btn_text_working', ''); // Clear working text too
        $form->set('has_save_btn', false); // Explicitly disable save button
        
        // Create group for Parameter Information
        $param_group = $form->getGroup('Parameter Configuration');
        
        // Parameter name display (read-only)
        $name_fieldset = $param_group->getFieldSet('Parameter Name');
        
        // Add documentation link to parameter name description if available
        $name_description = 'Parameter name cannot be changed';
        $doc_url = ParameterRegistry::getParameterDocumentationUrl($parameter_name);
        if ($doc_url) {
            $name_description .= ' <a href="' . htmlspecialchars($doc_url) . '" target="_blank" class="btn btn-default btn-sm" style="margin-left: 15px;">📖 View Documentation</a>';
        }
        $name_fieldset->setDesc($name_description);
        
        $name_field = $name_fieldset->getField('parameter_name_display', 'text')
            ->setValue($parameter_name);
            
        // Set readonly attribute using the correct EE7 method
        $name_field->set('attrs', 'readonly="readonly"');

        // Use parameter package for sophisticated form field generation
        try {
            $registry = new ParameterRegistry();
            $category = $registry->getParameterCategory($parameter_name);
            $discovery = new ParameterPackageDiscovery($registry);
            $packages = $discovery->getPackagesByCategory($category);
            
            if (!empty($packages)) {
                // Find the package that handles this parameter with the highest priority
                $target_package = null;
                $best_priority = PHP_INT_MAX;
                
                foreach ($packages as $package) {
                    $package_params = $package->getParameters();
                    
                    if (in_array($parameter_name, $package_params)) {
                        $package_priority = $package->getPriority();
                        
                        // Lower priority number = higher priority, so choose the package with lowest priority number
                        if ($package_priority < $best_priority) {
                            $target_package = $package;
                            $best_priority = $package_priority;
                        }
                    }
                }
                
                if ($target_package) {
                    // Generate sophisticated form fields using parameter package
                    $current_values = [$parameter_name => $current_value];
                    $package_fields = $target_package->generateFormFields($current_values);
                    
                    // Check if we have multiple fields for this parameter (like crop_enable, crop_position, etc.)
                    $parameter_fields = [];
                    foreach ($package_fields as $field_name => $field_config) {
                        if ($field_name === $parameter_name || strpos($field_name, $parameter_name . '_') === 0) {
                            $parameter_fields[$field_name] = $field_config;
                        }
                    }
                    
                    if (!empty($parameter_fields)) {
                        // Handle hidden fields separately using proper EE method
                        $hidden_fields = [];
                        $visible_fields = [];
                        
                        // Separate hidden fields from visible fields
                        foreach ($parameter_fields as $field_name => $field_config) {
                            if (($field_config['type'] ?? 'text') === 'hidden') {
                                $hidden_fields[$field_name] = $field_config;
                            } else {
                                $visible_fields[$field_name] = $field_config;
                            }
                        }
                        
                        // Add hidden fields using proper EE method
                        foreach ($hidden_fields as $field_name => $field_config) {
                            $hidden_field = $form->getHiddenField($field_name);
                            $hidden_field->setValue($field_config['value'] ?? '');
                        }
                        
                        // Process visible fields
                        if (!empty($visible_fields)) {
                        // Create separate fieldsets for each crop component for better UX
                        if (count($visible_fields) > 1) {
                            // Multiple fields - create individual fieldsets for each component
                            foreach ($visible_fields as $field_name => $field_config) {
                                $field_key = str_replace($parameter_name . '_', '', $field_name);
                                $field_title = $field_config['label'] ?? ucfirst(str_replace('_', ' ', $field_key));
                                
                                // Create a dedicated fieldset for this component
                                $component_fieldset = $param_group->getFieldSet($field_title);
                                
                                // Ensure description is a string
                                $description = $field_config['desc'] ?? '';
                                if (is_array($description)) {
                                    $description = implode(' ', $description);
                                }
                                $component_fieldset->setDesc($description);
                                
                                // Add example if available
                                if (isset($field_config['example'])) {
                                    $component_fieldset->setExample('For example: ' . $field_config['example']);
                                }
                                
                                // Create the field within its own fieldset
                                $field_type = $field_config['type'] ?? 'text';
                                
                                // Handle HTML fields specially
                                if ($field_type === 'html') {
                                    // For HTML fields, we'll inject the content directly into the fieldset
                                    $html_content = $field_config['content'] ?? '';
                                    // Use a custom HTML field approach
                                    $field = $component_fieldset->getField($field_name, 'html');
                                    if (method_exists($field, 'setContent')) {
                                        $field->setContent($html_content);
                                    } else {
                                        // Fallback: use setValue for HTML content
                                        $field->setValue($html_content);
                                    }
                                } else {
                                    // Regular field creation
                                    $field = $component_fieldset->getField($field_name, $field_type);
                                    $field->setValue($field_config['value'] ?? '');
                                }
                                
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
                                
                                // Add choices for select and radio fields
                                if (in_array($field_config['type'] ?? '', ['select', 'radio']) && isset($field_config['choices'])) {
                                    $field->setChoices($field_config['choices']);
                                }
                                
                                // Mark as required if specified
                                if (isset($field_config['required']) && $field_config['required']) {
                                    $field->setRequired(true);
                                }
                                
                                // Add field note if provided
                                if (isset($field_config['note'])) {
                                    $field->setNote($field_config['note']);
                                }
                            }
                        } else {
                            // Single field - use simple fieldset approach
                            $field_config = reset($visible_fields);
                            $value_fieldset = $param_group->getFieldSet('Parameter Value');
                            
                            // Ensure description is a string
                            $description = $field_config['desc'] ?? $parameter_info['description'] ?? 'Configure the value for this parameter';
                            if (is_array($description)) {
                                $description = implode(' ', $description);
                            }
                            $value_fieldset->setDesc($description);
                            
                            $field = $value_fieldset->getField('parameter_value', $field_config['type'] ?? 'text');
                            $field->setValue($field_config['value'] ?? '');
                            
                            if (($field_config['type'] ?? '') === 'select' && isset($field_config['choices'])) {
                                $field->setChoices($field_config['choices']);
                            }
                        }
                        } // Close the visible_fields if block
                    } else {
                        // Fallback to basic text field if parameter not found in package
                        $this->_add_fallback_edit_field($param_group, $parameter_name, $current_value, $parameter_info);
                    }
                } else {
                    // Fallback to basic text field if no package handles this parameter
                    $this->_add_fallback_edit_field($param_group, $parameter_name, $current_value, $parameter_info);
                }
            } else {
                // Fallback to basic text field if no packages for this category
                $this->_add_fallback_edit_field($param_group, $parameter_name, $current_value, $parameter_info);
            }
        } catch (Exception $e) {
            // Fallback to basic text field on any errors
            $this->_add_fallback_edit_field($param_group, $parameter_name, $current_value, $parameter_info);
            
            // Log the error for debugging
            ee('jcogs_img_pro:Logging')->error('EditParameter package integration error: ' . $e->getMessage(), [
                'parameter_name' => $parameter_name,
                'current_value' => $current_value,
                'preset_id' => $preset['id']
            ]);
        }
        
        // Add custom action buttons
        $this->_add_action_buttons($form, $preset['id'], $parameter_name);
        
        return $form;
    }
    
    /**
     * Add fallback text field when parameter packages are unavailable
     * 
     * @param mixed $param_group Form group instance
     * @param string $parameter_name Parameter name
     * @param string $current_value Current parameter value
     * @param array $parameter_info Parameter metadata
     * @return void
     */
    private function _add_fallback_edit_field($param_group, string $parameter_name, string $current_value, array $parameter_info): void
    {
        $value_fieldset = $param_group->getFieldSet('Parameter Value');
        $value_fieldset->setDesc($parameter_info['description'] ?? 'Configure the value for this parameter');
        
        // Choose appropriate field type based on parameter info
        $field_type = $this->_get_field_type($parameter_info);
        $value_field = $value_fieldset->getField('parameter_value', $field_type)
            ->setValue($current_value);
            
        // Set class attribute using EE7 format
        $value_field->set('attrs', 'class="form-control"');
            
        // Add options for select fields
        if ($field_type === 'select' && isset($parameter_info['options'])) {
            $value_field->setChoices($parameter_info['options']);
        }
    }

    /**
     * Determine appropriate field type for parameter
     * 
     * @param array $parameter_info Parameter metadata
     * @return string Field type
     */
    private function _get_field_type(array $parameter_info): string
    {
        // Since we don't have detailed parameter metadata yet,
        // use basic field type determination
        if (isset($parameter_info['type'])) {
            return $parameter_info['type'];
        }
        
        return 'text'; // Default to text input
    }

    /**
     * Get the parameter package for a specific parameter
     * 
     * @param string $parameter_name Parameter name
     * @param string $parameter_category Parameter category
     * @return mixed Package instance or null
     */
    private function _get_package_for_parameter(string $parameter_name, string $parameter_category)
    {
        $registry = new ParameterRegistry();
        $discovery = new ParameterPackageDiscovery($registry);
        $packages = $discovery->getPackagesByCategory($parameter_category);
        
        if (!empty($packages)) {
            // Find the package that handles this parameter
            foreach ($packages as $package) {
                if (in_array($parameter_name, $package->getParameters())) {
                    return $package;
                }
            }
        }
        
        return null;
    }

    /**
     * Add custom action buttons for save operations
     * 
     * @param mixed $form Form instance
     * @param int $preset_id Preset ID
     * @param string $parameter_name Parameter name
     */
    private function _add_action_buttons($form, int $preset_id, string $parameter_name): void
    {
        // Create buttons group
        $buttons_group = $form->getGroup('Actions');
        $buttons_fieldset = $buttons_group->getFieldSet('Save Options');
        $buttons_fieldset->setDesc('Choose whether to continue editing or return to the preset after saving.');
        
        // Hidden field to track the action type
        $action_field = $form->getHiddenField('action_type');
        $action_field->setValue('save_continue'); // Default action
        
        // Create custom HTML for buttons
        $preset_edit_url = ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset_id);
        $param_edit_url = ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit_parameter/' . $preset_id . '/' . urlencode($parameter_name));
        
        $buttons_html = '
        <div class="form-btns form-btns-top">
            <fieldset class="form-ctrls">
                <input type="submit" name="submit_continue" value="Save &amp; Continue Editing" class="btn action" 
                       onclick="document.querySelector(\'input[name=action_type]\').value=\'save_continue\';">
                <input type="submit" name="submit_close" value="Save &amp; Close" class="btn" 
                       onclick="document.querySelector(\'input[name=action_type]\').value=\'save_close\';">
                <a href="' . $preset_edit_url . '" class="btn btn-default">Cancel</a>
            </fieldset>
        </div>';
        
        // Add the HTML as a custom field
        $html_field = $buttons_fieldset->getField('custom_buttons', 'html');
        $html_field->setContent($buttons_html);
    }
    
    /**
     * Validate a parameter value using the parameter package system
     * 
     * @param string $parameter_name Parameter name to validate
     * @param mixed $parameter_value Parameter value to validate
     * @return bool|string True if valid, error message if invalid
     */
    private function _validate_parameter_value(string $parameter_name, $parameter_value)
    {
        try {
            // Load the parameter package discovery service
            $packageDiscovery = new ParameterPackageDiscovery();
            
            // Validate the specific parameter
            $validation_errors = $packageDiscovery->validateAllParameters([
                $parameter_name => $parameter_value
            ]);
            
            // Check if there are validation errors for this parameter
            if (!empty($validation_errors) && isset($validation_errors[$parameter_name])) {
                return $validation_errors[$parameter_name];
            }
            
            return true;
            
        } catch (Exception $e) {
            // If validation service fails, allow the update (graceful degradation)
            return true;
        }
    }

    /**
     * Load CSS assets for the parameter edit interface
     * 
     * @return void
     */
    private function _load_parameter_edit_assets(): void
    {
        // Add parameter edit CSS file using EE's recommended method
        ee()->cp->add_to_head('<link rel="stylesheet" type="text/css" href="' . URL_THIRD_THEMES . 'jcogs_img_pro/css/parameter-edit.css">');
    }
}