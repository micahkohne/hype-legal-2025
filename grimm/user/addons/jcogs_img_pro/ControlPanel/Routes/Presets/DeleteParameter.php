<?php

/**
 * JCOGS Image Pro - Delete Parameter Route
 * =========================================
 * Route for deleting parameters from presets
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

class DeleteParameter extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'presets/delete_parameter';

    /**
     * @var string Control panel page title
     */
    protected $cp_page_title;

    /**
     * Delete parameter processor
     * 
     * @param mixed $preset_id Preset ID to delete parameter from (may be unused if extracted from URL)
     * @return $this Fluent interface for EE7 routing
     */
    public function process($preset_id = false)
    {
        // Get preset ID and parameter name from URL segments (like EditParameter route)
        $preset_id = $this->utilities_service->getPresetIdFromUrl();
        $parameter_name = $this->utilities_service->getParameterNameFromUrl();
        
        if (empty($preset_id) || empty($parameter_name)) {
            ee('CP/Alert')->makeInline('delete_error')
                ->asIssue()
                ->withTitle('Invalid Request')
                ->addToBody('Preset ID and parameter name are required.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
            return $this;
        }
        
        // Load the preset using shared PresetService
        $preset = $this->preset_service->getPresetById($preset_id);
        if (!$preset) {
            ee('CP/Alert')->makeInline('delete_error')
                ->asIssue()
                ->withTitle('Preset Not Found')
                ->addToBody('The specified preset could not be found.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
            return $this;
        }
        
        // Handle POST request - delete the parameter
        if (count($_POST) > 0) {
            return $this->_handle_delete_parameter($preset, $parameter_name);
        }
        
        // GET request - show confirmation form
        $this->cp_page_title = 'Delete Parameter: ' . ucfirst(str_replace('_', ' ', $parameter_name));
        $this->build_sidebar($this->_get_current_settings());
        $this->addBreadcrumb('presets', 'Preset Management', ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
        $this->addBreadcrumb('edit', 'Edit: ' . $preset['name'], ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset_id));
        $this->addBreadcrumb('delete_parameter', $this->cp_page_title);

        // Build the confirmation form
        $variables = $this->_build_confirmation_form($preset, $parameter_name);

        // Load CSS assets for the delete parameter interface
        $this->_load_delete_parameter_assets();

        // Set the page body
        $this->setBody('preset_delete_parameter', $variables);

        return $this;
    }

    /**
     * Handle POST request to delete parameter
     * 
     * @param array $preset Current preset data
     * @param string $parameter_name Parameter to delete
     * @return $this
     */
    private function _handle_delete_parameter(array $preset, string $parameter_name)
    {
        // Check for confirmation
        $confirmed = ee()->input->post('confirm_delete');
        if ($confirmed !== 'yes') {
            ee('CP/Alert')->makeInline('delete_error')
                ->asIssue()
                ->withTitle('Delete Cancelled')
                ->addToBody('Parameter deletion was not confirmed.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset['id']));
            return $this;
        }
        
        try {
            // Get current parameters - already decoded by PresetService->getPresetById()
            $parameters = $preset['parameters'] ?? [];
            
            // Ensure parameters is an array (defensive programming)
            if (is_string($parameters)) {
                $parameters = json_decode($parameters, true) ?? [];
            }
            
            // Check if parameter exists
            if (!isset($parameters[$parameter_name])) {
                ee('CP/Alert')->makeInline('delete_error')
                    ->asIssue()
                    ->withTitle('Parameter Not Found')
                    ->addToBody('The specified parameter does not exist in this preset.')
                    ->defer();
                    
                ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset['id']));
                return $this;
            }
            
            // Remove the parameter
            unset($parameters[$parameter_name]);
            
            // Update the preset
            $result = $this->preset_service->updatePreset($preset['name'], [
                'parameters' => $parameters
            ]);
            
            if ($result['success']) {
                ee('CP/Alert')->makeInline('delete_success')
                    ->asSuccess()
                    ->withTitle('Parameter Deleted')
                    ->addToBody('Parameter "' . htmlspecialchars($parameter_name) . '" has been deleted successfully.')
                    ->defer();
            } else {
                throw new Exception($result['error'] ?? 'Unknown error occurred');
            }
            
        } catch (Exception $e) {
            $this->utilities_service->debug_log('parameter_delete_error', $e->getMessage());
            
            ee('CP/Alert')->makeInline('delete_error')
                ->asIssue()
                ->withTitle('Delete Failed')
                ->addToBody('Failed to delete parameter: ' . $e->getMessage())
                ->defer();
        }
        
        // Redirect back to edit page
        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset['id']));
        return $this;
    }

    /**
     * Build confirmation form
     * 
     * @param array $preset Current preset data
     * @param string $parameter_name Parameter to delete
     * @return array Template variables
     */
    private function _build_confirmation_form(array $preset, string $parameter_name): array
    {
        $form = ee('CP/Form');
        
        // Set form action URL
        $form_action_url = ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/delete_parameter/' . $preset['id'] . '/' . urlencode($parameter_name));
        $form->setBaseUrl($form_action_url);
        
        // Configure form buttons
        $form->set('save_btn_text', 'Delete Parameter');
        $form->set('save_btn_text_working', 'Deleting');
        $form->set('btn_class', 'btn-danger');
        
        // Get parameter value - parameters are already decoded by PresetService->getPresetById()
        $parameters = $preset['parameters'] ?? [];
        
        // Ensure parameters is an array (defensive programming)
        if (is_string($parameters)) {
            $parameters = json_decode($parameters, true) ?? [];
        }
        
        $parameter_value = $parameters[$parameter_name] ?? '';
        
        // Add confirmation fields
        // Create group for Confirmation
        $confirm_group = $form->getGroup('Confirm Deletion');
        
        // Parameter info fieldset
        $info_fieldset = $confirm_group->getFieldSet('Parameter to Delete');
        $info_fieldset->setDesc('This parameter will be permanently removed from the preset');
        $info_fieldset->getField('parameter_info', 'html')
            ->setContent($this->_build_parameter_info_html($parameter_name, $parameter_value));
            
        // Confirm deletion fieldset
        $confirm_fieldset = $confirm_group->getFieldSet('Confirm Deletion');
        $confirm_fieldset->setDesc('Type "yes" to confirm you want to delete this parameter');
        $confirm_fieldset->getField('confirm_delete', 'text')
            ->setValue('')
            ->setRequired(true);
            
        // Hidden fields for processing
        $confirm_fieldset->getField('preset_id', 'hidden')
            ->setValue($preset['id']);
        $confirm_fieldset->getField('parameter_name', 'hidden')
            ->setValue($parameter_name);
        
        return [
            'cp_page_title' => $this->cp_page_title,
            'form' => $form,
            'preset' => $preset,
            'parameter_name' => $parameter_name,
            'parameter_value' => $parameter_value,
            'cancel_url' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset['id'])
        ];
    }

    /**
     * Build HTML for parameter info display
     * 
     * @param string $parameter_name
     * @param string $parameter_value
     * @return string
     */
    private function _build_parameter_info_html(string $parameter_name, string $parameter_value): string
    {
        return sprintf(
            '<div class="parameter-info-display">
                <p><strong>Parameter:</strong> %s</p>
                <p><strong>Current Value:</strong> %s</p>
            </div>',
            htmlspecialchars($parameter_name),
            htmlspecialchars($parameter_value ?: '(empty)')
        );
    }

    /**
     * Load CSS assets for the delete parameter interface
     * 
     * @return void
     */
    private function _load_delete_parameter_assets(): void
    {
        // Add CSS files using EE's recommended method
        ee()->cp->add_to_head('<link rel="stylesheet" type="text/css" href="' . URL_THIRD_THEMES . 'jcogs_img_pro/css/preset-delete-parameter.css">');
    }
}
