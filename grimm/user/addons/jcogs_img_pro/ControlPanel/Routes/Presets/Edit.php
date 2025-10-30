<?php

/**
 * JCOGS Image Pro - Edit Preset Route
 * ====================================
 * Route for editing preset parameters (Level 2 interface)
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
use JCOGSDesign\JCOGSImagePro\Service\ParameterPackageDiscovery;
use JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\ImageAbstractRoute;
use JCOGSDesign\JCOGSImagePro\Service\ParameterRegistry;

class Edit extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'presets/edit';

    /**
     * @var string Control panel page title
     */
    protected $cp_page_title;

    /**
     * Edit preset form processor
     * 
     * @param mixed $preset_id Preset ID to edit (may be unused if extracted from URL)
     * @return $this Fluent interface for EE7 routing
     */
    public function process($preset_id = false)
    {
        // Validate and load preset using shared helper method
        $preset = $this->validateAndLoadPresetFromUrl('edit_error');
        if (!$preset) {
            return $this; // Helper method handles error and redirect
        }
        
        // Handle POST request - update preset description
        if (count($_POST) > 0) {
            return $this->_handle_update_preset($preset);
        }
        
        // GET request - show edit form
        $this->cp_page_title = 'Edit Preset: ' . $preset['name'];
        $this->build_sidebar($this->_get_current_settings());
        $this->addBreadcrumb('presets', 'Preset Management', ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
        $this->addBreadcrumb('edit', $this->cp_page_title);

        // Load CSS and JavaScript using EE's recommended methods
        $this->_load_preset_edit_assets($preset);

        // Build the edit interface
        $variables = $this->_build_edit_interface($preset);

        // Set the page body
        $this->setBody('preset_edit', $variables);

        return $this;
    }

    /**
     * Handle POST request to update preset
     * 
     * @param array $preset Current preset data
     * @return $this
     */
    private function _handle_update_preset(array $preset)
    {
        // Check if this is a quick edit request
        $quick_edit_parameter = ee()->input->post('quick_edit_parameter');
        $quick_edit_value = ee()->input->post('quick_edit_value');
        
        if ($quick_edit_parameter && $quick_edit_value !== null) {
            return $this->_handle_quick_edit_parameter($preset, $quick_edit_parameter, $quick_edit_value);
        }
        
        // Regular description update
        $description = ee()->input->post('description') ?: '';
        
        try {
            // Update the preset description
            $result = $this->preset_service->updatePreset($preset['name'], [
                'description' => $description
            ]);
            
            if ($result['success']) {
                ee('CP/Alert')->makeInline('update_success')
                    ->asSuccess()
                    ->withTitle('Preset Updated')
                    ->addToBody('Preset description has been updated successfully.')
                    ->defer();
            } else {
                throw new Exception($result['error'] ?? 'Unknown error occurred');
            }
            
        } catch (Exception $e) {
            $this->utilities_service->debug_log('preset_update_error', $e->getMessage());
            
            ee('CP/Alert')->makeInline('update_error')
                ->asIssue()
                ->withTitle('Update Failed')
                ->addToBody('Failed to update preset: ' . $e->getMessage())
                ->defer();
        }
        
        // Redirect back to this page
        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset['id']));
        return $this;
    }

    /**
     * Handle quick edit parameter update
     * 
     * @param array $preset Current preset data
     * @param string $parameter_name Parameter to update
     * @param string $parameter_value New value
     * @return $this
     */
    private function _handle_quick_edit_parameter(array $preset, string $parameter_name, string $parameter_value)
    {
        try {
            // Get current parameters
            $current_parameters = $preset['parameters'] ?? [];
            
            // Validate the parameter value before updating
            $validation_result = $this->_validate_parameter_value($parameter_name, $parameter_value);
            if ($validation_result !== true) {
                throw new Exception($validation_result);
            }
            
            // Update the specific parameter
            $current_parameters[$parameter_name] = $parameter_value;
            
            // Update the preset with new parameters
            $result = $this->preset_service->updatePreset($preset['name'], [
                'parameters' => $current_parameters
            ]);
            
            if ($result['success']) {
                ee('CP/Alert')->makeInline('quick_edit_success')
                    ->asSuccess()
                    ->withTitle('Parameter Updated')
                    ->addToBody("Parameter '{$parameter_name}' has been updated to '{$parameter_value}'.")
                    ->defer();
            } else {
                throw new Exception($result['error'] ?? 'Unknown error occurred');
            }
            
        } catch (Exception $e) {
            
            // Check if this is an AJAX request
            $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
            
            if ($is_ajax) {
                // Return JSON error response for AJAX requests
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'title' => 'Quick Edit Failed'
                ]);
                exit;
            } else {
                // Traditional form submission - use alerts
                ee('CP/Alert')->makeInline('quick_edit_error')
                    ->asIssue()
                    ->withTitle('Quick Edit Failed')
                    ->addToBody('Failed to update parameter: ' . $e->getMessage())
                    ->defer();
                    
                // Also set a session flash message as backup
                ee()->session->set_flashdata('quick_edit_error', 'Failed to update parameter: ' . $e->getMessage());
            }
        }
        
        // Redirect back to this page
        $redirect_url = ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset['id']);
        ee()->functions->redirect($redirect_url);
        return $this;
    }

    /**
     * Build edit interface
     * 
     * @param array $preset Current preset data
     * @return array Template variables
     */
    private function _build_edit_interface(array $preset): array
    {
        // Parameters are already decoded by PresetService->getPresetById()
        $parameters = $preset['parameters'] ?? [];
        
        // Ensure parameters is an array (defensive programming)
        if (is_string($parameters)) {
            $parameters = json_decode($parameters, true) ?? [];
        }
        
        // Filter out preview_file_id from parameters display
        $display_parameters = array_filter(
            $parameters, 
            fn($key) => $key !== 'preview_file_id', 
            ARRAY_FILTER_USE_KEY
        );
        
        // Build preset info form
        $preset_form = $this->_build_preset_info_form($preset);
        
        // Build preview image management
        $preview_image_data = $this->_build_preview_image_data($preset, $parameters);
        
        // Build custom preview file picker field HTML
        $preview_file_picker = $this->_build_preview_file_picker($preset, $parameters);
        
        // Get available parameters for dropdown
        $available_parameters = $this->_get_available_parameters();
        
        // Get analytics data for header
        $analytics_data = $this->_get_analytics_summary($preset);
        
        return [
            'cp_page_title' => $this->cp_page_title,
            'preset' => $preset,
            'preset_form' => $preset_form,
            'preview_image' => $preview_image_data,
            'preview_file_picker' => $preview_file_picker,
            'available_parameters' => $available_parameters,
            'analytics' => $analytics_data,
            'add_parameter_url' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/add_parameter/' . $preset['id'])->compile(),
            'presets_url' => (string) ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'),
            'csrf_token' => CSRF_TOKEN,
            'remove_parameter_url' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/remove_parameter/' . $preset['id'])->compile(),
            'update_parameter_url' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/update_parameter/' . $preset['id'])->compile(),
            'update_preview_url' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/update_preview/' . $preset['id'])->compile()
        ];
    }

    /**
     * Build preset info form
     * 
     * @param array $preset Current preset data
     * @return string Rendered form HTML
     */
    private function _build_preset_info_form(array $preset): string
    {
        $form = ee('CP/Form');
        
        // Set form action URL
        $form_action_url = ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset['id']);
        $form->setBaseUrl($form_action_url);
        
        // Configure form buttons
        $form->set('save_btn_text', 'Update Preset');
        $form->set('save_btn_text_working', 'Updating');
        $form->set('hide_top_buttons', true);
        
        // Create group for Preset Information
        $info_group = $form->getGroup('Preset Information');
        
        // Preset name fieldset (read-only)
        $name_fieldset = $info_group->getFieldSet('Preset Name');
        $name_fieldset->setDesc('Preset name cannot be changed after creation');
        $name_fieldset->getField('name_display', 'text')
            ->setValue($preset['name']);
            
        // Description fieldset
        $desc_fieldset = $info_group->getFieldSet('Description');
        $desc_fieldset->setDesc('Optional description of what this preset does');
        $desc_fieldset->getField('description', 'textarea')
            ->setValue($preset['description'] ?? '');
        
        // Render the form to HTML string to avoid nesting issues
        return $form->render();
    }

    /**
     * Get available parameters for dropdown
     * 
     * @return array Available parameters grouped by category
     */
    private function _get_available_parameters(): array
    {
        $registry = new ParameterRegistry();
        $all_parameters = $registry->getAllParameters();
        
        $grouped_parameters = [];
        foreach ($all_parameters as $param_name) {
            $category = ParameterRegistry::getParameterCategory($param_name);
            $category_label = ucfirst($category);
            
            if (!isset($grouped_parameters[$category_label])) {
                $grouped_parameters[$category_label] = [];
            }
            
            $grouped_parameters[$category_label][$param_name] = ucfirst(str_replace('_', ' ', $param_name));
        }
        
        // Sort parameters alphabetically within each group
        foreach ($grouped_parameters as $category => $parameters) {
            asort($grouped_parameters[$category]);
        }
        
        return $grouped_parameters;
    }
    
    /**
     * Build preview image data with EE Files fallback hierarchy
     * 
     * @param array $preset Preset data
     * @param array $parameters Preset parameters
     * @return array Preview image data for template
     */
    private function _build_preview_image_data(array $preset, array $parameters): array
    {
        $preview_data = [
            'has_preview' => false,
            'preview_url' => null,
            'preview_alt' => '',
            'preview_source' => 'none'
        ];
        
        try {
            // DEBUG: Add breakpoint here to inspect parameters
            $debug_info = [
                'preset_id' => $preset['id'],
                'preset_name' => $preset['name'],
                'preset_sample_file_id' => $preset['sample_file_id'] ?? 'not_set',
                'parameters' => $parameters
            ];
            
            // Level 1: Preset-specific preview image (sample_file_id in main preset record)
            if (isset($preset['sample_file_id']) && !empty($preset['sample_file_id'])) {
                $file_info = $this->_get_file_info((int)$preset['sample_file_id']);
                if ($file_info && $this->_is_image_file($file_info)) {
                    $preview_data['has_preview'] = true;
                    $preview_data['preview_url'] = $file_info['url'];
                    $preview_data['preview_alt'] = 'Preview for ' . $preset['name'];
                    $preview_data['preview_source'] = 'preset';
                    return $preview_data;
                }
            }
            
            // Level 2: Global default preview image (from settings)
            $current_settings = $this->_get_current_settings();
            $default_preview_setting = $current_settings['preset_default_preview_file_id'] ?? '';
            
            // DEBUG: Add breakpoint here to check settings and default file ID
            $debug_info['current_settings'] = $current_settings;
            $debug_info['default_preview_setting'] = $default_preview_setting;
            
            // Parse EE Files field format: {file:97:url} -> extract file ID
            $default_preview_file_id = 0;
            if (!empty($default_preview_setting)) {
                if (preg_match('/\{file:(\d+):url\}/', $default_preview_setting, $matches)) {
                    $default_preview_file_id = (int)$matches[1];
                } elseif (is_numeric($default_preview_setting)) {
                    $default_preview_file_id = (int)$default_preview_setting;
                }
            }
            
            $debug_info['parsed_default_file_id'] = $default_preview_file_id;
            
            if ($default_preview_file_id > 0) {
                $file_info = $this->_get_file_info($default_preview_file_id);
                
                // DEBUG: Add breakpoint here to check file_info result
                $debug_info['default_file_info'] = $file_info;
                $debug_info['is_image_file'] = $file_info ? $this->_is_image_file($file_info) : false;
                
                if ($file_info && $this->_is_image_file($file_info)) {
                    $preview_data['has_preview'] = true;
                    $preview_data['preview_url'] = $file_info['url'];
                    $preview_data['preview_alt'] = 'Default preset preview';
                    $preview_data['preview_source'] = 'default';
                    return $preview_data;
                }
            }
            
            // Level 3: No preview available - return placeholder data
            $preview_data['preview_alt'] = 'No preview image selected';
            $preview_data['preview_source'] = 'none';
            
            // DEBUG: Final debug info for no preview case
            $debug_info['final_result'] = 'no_preview';
            
        } catch (Exception $e) {
            // Log error but don't break the interface
            ee('jcogs_img_pro:Logging')->error('Preview image generation error: ' . $e->getMessage(), [
                'preset_id' => $preset['id'],
                'preset_name' => $preset['name']
            ]);
        }
        
        return $preview_data;
    }
    
    /**
     * Get file information from EE Files
     * 
     * @param int $file_id File ID
     * @return array|null File information or null if not found
     */
    private function _get_file_info(int $file_id): ?array
    {
        try {
            $file = ee('Model')->get('File', $file_id)->first();
            if ($file) {
                return [
                    'id' => $file->file_id,
                    'name' => $file->file_name,
                    'url' => $file->getAbsoluteURL(),
                    'mime_type' => $file->mime_type
                ];
            }
        } catch (Exception $e) {
            // File not found or access denied
        }
        
        return null;
    }
    
    /**
     * Check if file is an image
     * 
     * @param array $file_info File information
     * @return bool True if file is an image
     */
    private function _is_image_file(array $file_info): bool
    {
        return isset($file_info['mime_type']) && 
               strpos($file_info['mime_type'], 'image/') === 0;
    }

    /**
     * Build preview file picker field HTML (for embedding in main form)
     * 
     * @param array $preset Preset data
     * @param array $parameters Preset parameters
     * @return string File picker field HTML
     */
    private function _build_preview_file_picker(array $preset, array $parameters): string
    {
        // For now, return a simple working file picker to test the structure
        $current_file_id = (int)($parameters['preview_file_id'] ?? 0);
        
        // Return a basic but complete file picker structure
        return '<input type="hidden" class="js-file-input" name="preview_file_id" value="' . $current_file_id . '" data-id="">

<div class="fields-upload-chosen list-item hidden">
    <div class="fields-upload-chosen-name" data-id="">
        <div title="' . $current_file_id . '"></div>
    </div>
    <div class="fields-upload-chosen-controls">
        <div class="fields-upload-tools">
            <div class="button-group button-group-small">
                <a href="" class="edit-meta button button--default" title="Edit meta data"><i class="fal fa-money-check-pen"></i></a>
                <a class="m-link filepicker file-field-filepicker button button--default" title="Edit" rel="modal-file" href="#" data-input-image="preview_file_id" data-input-value="preview_file_id" data-input-name="preview_file_id"><i class="fal fa-pen"></i><span class="hidden">Edit</span></a>
                <a href="" class="remove button button--default" title="Remove"><i class="fa fa-times"></i></a>
            </div>
        </div>
    </div>
    <div class="fields-upload-chosen-file">
        <figure class="no-img">
            <img src="' . ee()->config->item('theme_folder_url') . 'ee/asset/img/missing.jpg" id="preview_file_id" alt="" class="js-file-image" loading="lazy">
        </figure>
    </div>
</div>

<div class="file-field">
    <div class="file-field__dropzone">
        <div class="file-field__dropzone-title">Drop File(s) Here to Upload</div>
        <div class="file-field__dropzone-button">Please choose a directory: 
            <button type="button" class="button js-dropdown-toggle has-sub button--default button--small">Choose Directory</button>
        </div>
        <div class="file-field__dropzone-icon"><i class="fal fa-cloud-upload-alt"></i></div>
    </div>
    <div class="file-field__buttons">
        <div class="button-segment">
            <button type="button" class="button js-dropdown-toggle has-sub button--default button--small">Choose Existing</button>
            <button type="button" class="button js-dropdown-toggle has-sub button--default button--small">Upload New</button>
        </div>
    </div>
</div>';
    }

    /**
     * Load CSS and JavaScript assets for the preset edit interface
     * 
     * @param array $preset The preset data
     * @return void
     */
    private function _load_preset_edit_assets(array $preset): void
    {
        // Add CSS files using EE's recommended method
        ee()->cp->add_to_head('<link rel="stylesheet" type="text/css" href="' . URL_THIRD_THEMES . 'jcogs_img_pro/css/live-preview.css">');
        ee()->cp->add_to_head('<link rel="stylesheet" type="text/css" href="' . URL_THIRD_THEMES . 'jcogs_img_pro/css/preset-edit.css">');
        
        // Load JavaScript files from add-on's javascript directory using EE's package system
        ee()->cp->add_to_foot('<script defer src="' .  URL_THIRD_THEMES . 'jcogs_img_pro/javascript/live-preview.js"></script>');
        ee()->cp->add_to_foot('<script defer src="' .  URL_THIRD_THEMES . 'jcogs_img_pro/javascript/preset-edit-enhanced.js"></script>');
        ee()->cp->add_to_foot('<script defer src="' .  URL_THIRD_THEMES . 'jcogs_img_pro/javascript/preset-edit.js"></script>');

        // Add configuration JavaScript
        $config_js = 'window.jcogsImageProConfig = {
            csrfToken: "' . CSRF_TOKEN . '",
            removeParameterUrl: "' . ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/remove_parameter/' . $preset['id'])->compile() . '",
            updateParameterUrl: "' . ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset['id'])->compile() . '",
            previewUrl: "' . ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/preview/' . $preset['id'])->compile() . '",
            presetId: ' . $preset['id'] . ',
            currentPreviewFileId: ' . (int)($preset['sample_file_id'] ?? 0) . '
        };';
        
        // Add configuration JavaScript using proper EE method
        ee()->cp->add_to_foot('<script>' . $config_js . '</script>');
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
     * Get analytics summary data for header display
     * 
     * @param array $preset Current preset data
     * @return array Analytics summary for template
     */
    private function _get_analytics_summary(array $preset): array
    {
        try {
            $analytics = $this->preset_service->getPresetAnalytics($preset['id']);
            
            if ($analytics['success']) {
                return [
                    'success' => true,
                    'usage_count' => $analytics['usage_count'],
                    'last_used' => $analytics['last_used_date'] ? date('M j, Y', $analytics['last_used_date']) : 'Never',
                    'error_count' => $analytics['error_count'],
                    'created_date' => date('M j, Y', $analytics['created_date']),
                    'days_since_creation' => $analytics['days_since_creation'],
                    'avg_daily_usage' => $analytics['avg_daily_usage']
                ];
            } else {
                // Return default values on error
                return [
                    'success' => false,
                    'usage_count' => '--',
                    'last_used' => '--',
                    'error_count' => '--',
                    'created_date' => '--',
                    'days_since_creation' => '--',
                    'avg_daily_usage' => '--'
                ];
            }
        } catch (Exception $e) {
            $this->utilities_service->debug_log('preset_analytics_summary_error', $e->getMessage());
            
            // Return default values on exception
            return [
                'success' => false,
                'usage_count' => '--',
                'last_used' => '--',
                'error_count' => '--',
                'created_date' => '--',
                'days_since_creation' => '--',
                'avg_daily_usage' => '--'
            ];
        }
    }
}
