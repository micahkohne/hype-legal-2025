<?php

/**
 * JCOGS Image Pro - Create Preset Route
 * ======================================
 * Route for creating new presets
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

class Create extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'presets/create';

    /**
     * @var string Control panel page title
     */
    protected $cp_page_title;

    /**
     * Create preset form processor
     * 
     * @param mixed $id Not used for create route
     * @return $this Fluent interface for EE7 routing
     */
    public function process($id = false)
    {
        // Handle POST request - create the preset
        if (count($_POST) > 0) {
            return $this->_handle_create_preset();
        }
        
        // GET request - show create form
        $this->cp_page_title = 'Create New Preset';
        $this->build_sidebar($this->_get_current_settings());
        $this->addBreadcrumb('presets', 'Preset Management', ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
        $this->addBreadcrumb('create', $this->cp_page_title);

        // Build the create form
        $variables = $this->_build_create_form();

        // Load CSS and JavaScript assets for the create interface
        $this->_load_create_assets();

        // Set the page body
        $this->setBody('preset_create', $variables);

        return $this;
    }

    /**
     * Handle POST request to create preset
     * 
     * @return $this
     */
    private function _handle_create_preset()
    {
        $name = ee()->input->post('name');
        $description = ee()->input->post('description') ?: '';
        
        // Basic validation
        if (empty($name)) {
            ee('CP/Alert')->makeInline('create_error')
                ->asIssue()
                ->withTitle('Validation Error')
                ->addToBody('Preset name is required.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/create'));
            return $this;
        }
        
        try {
            // Check if preset already exists
            if ($this->preset_service->presetExists($name)) {
                ee('CP/Alert')->makeInline('create_error')
                    ->asIssue()
                    ->withTitle('Preset Already Exists')
                    ->addToBody('A preset with the name "' . htmlspecialchars($name) . '" already exists.')
                    ->defer();
                    
                ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/create'));
                return $this;
            }
            
            // Create the preset with empty parameters
            $result = $this->preset_service->createPreset($name, [], $description);
            
            if ($result['success']) {
                ee('CP/Alert')->makeInline('create_success')
                    ->asSuccess()
                    ->withTitle('Preset Created')
                    ->addToBody('Preset "' . htmlspecialchars($name) . '" has been created successfully.')
                    ->defer();
                    
                // Redirect to edit the new preset
                ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $result['preset_id']));
            } else {
                throw new Exception($result['error'] ?? 'Unknown error occurred');
            }
            
        } catch (Exception $e) {
            $this->utilities_service->debug_log('preset_create_error', $e->getMessage());
            
            ee('CP/Alert')->makeInline('create_error')
                ->asIssue()
                ->withTitle('Creation Failed')
                ->addToBody('Failed to create preset: ' . $e->getMessage())
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/create'));
        }
        
        return $this;
    }

    /**
     * Build create form
     * 
     * @return array Template variables
     */
    private function _build_create_form(): array
    {
        $form = ee('CP/Form');
        
        // Set form action URL
        $form_action_url = ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/create');
        $form->setBaseUrl($form_action_url);
        
        // Configure form buttons
        $form->set('save_btn_text', 'Create Preset');
        $form->set('save_btn_text_working', 'Creating');
        
        // Create group for Preset Details
        $details_group = $form->getGroup('Preset Details');
        
        // Preset name fieldset
        $name_fieldset = $details_group->getFieldSet('Preset Name');
        $name_fieldset->setDesc('Unique name for this preset (cannot be changed after creation)');
        $name_fieldset->getField('name', 'text')
            ->setValue('')
            ->setRequired(true);
            
        // Description fieldset
        $desc_fieldset = $details_group->getFieldSet('Description');
        $desc_fieldset->setDesc('Optional description of what this preset does');
        $desc_fieldset->getField('description', 'textarea')
            ->setValue('');
        
        return [
            'cp_page_title' => $this->cp_page_title,
            'form' => $form,
            'cancel_url' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets')
        ];
    }

    /**
     * Load CSS and JavaScript assets for the create interface
     * 
     * @return void
     */
    private function _load_create_assets(): void
    {
        // Add CSS files using EE's recommended method
        ee()->cp->add_to_head('<link rel="stylesheet" type="text/css" href="' . URL_THIRD_THEMES . 'jcogs_img_pro/css/preset-create.css">');
        
        // Load JavaScript files using EE's recommended method
        ee()->cp->add_to_foot('<script defer src="' . URL_THIRD_THEMES . 'jcogs_img_pro/javascript/preset-creation-workflow.js"></script>');
    }
}
