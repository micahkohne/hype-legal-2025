<?php

/**
 * JCOGS Image Pro - Preset Management Route
 * ==========================================
 * Preset configuration and management with parameter packages
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

namespace JCOGSDesign\JCOGSImagePro\ControlPanel\Routes;

use Exception;
use ExpressionEngine\Library\CP\Table;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

class Presets extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'presets';

    /**
     * @var string Control panel page title
     */
    protected $cp_page_title;

    /**
     * Main preset management page processor
     * 
     * Handles all preset management requests including actions via URL segments.
     * EE7 MCP routing calls this method for all requests to this route.
     * 
     * @param mixed $id Route parameter (preset ID for actions)
     * @return $this Fluent interface for EE7 routing
     */
    public function process($id = false)
    {
        // Check if this is a POST request with form data - redirect to proper route
        if (count($_POST) > 0) {
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/update_settings'));
            return $this;
        }
        
        // Set page title and navigation
        $this->cp_page_title = 'Preset Management';
        $this->build_sidebar($this->_get_current_settings());
        $this->addBreadcrumb('presets', $this->cp_page_title);

        // Build the main page content
        $variables = $this->_build_page_content();

        // Load CSS assets for the management interface
        $this->_load_management_assets();

        // Set the page body using EE7 view system
        $this->setBody('preset_management', $variables);

        return $this;
    }

    /**
     * Build all page content sections
     * 
     * @return array Template variables for the view
     */
    private function _build_page_content(): array
    {
        // Get current settings and presets
        $current_settings = $this->_get_current_settings();
        $presets = $this->_get_all_presets();
        
        // Build preset table using standalone CP/Table (like documentation shows)
        $preset_table_data = $this->_build_preset_table($presets);
        
        // Build global settings form
        $settings_form = $this->_build_global_settings_form($current_settings, count($presets));
        
        return [
            'cp_page_title' => $this->cp_page_title,
            'preset_table' => $preset_table_data,
            'settings_form' => $settings_form,
            'base_url' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'),
            'create_url' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/create'),
            'has_presets' => count($presets) > 0
        ];
    }

    /**
     * Get all presets for current site
     * 
     * @return array Array of preset data
     */
    private function _get_all_presets(): array
    {
        try {
            $presetService = ServiceCache::preset_service();
            return $presetService->getAllPresets();
        } catch (Exception $e) {
            $this->utilities_service->debug_log('preset_load_error', $e->getMessage());
            return [];
        }
    }

    /**
     * Build preset table for display using CP/Table viewData (like documentation shows)
     * 
     * @param array $presets Array of preset data
     * @return array Table viewData for template rendering
     */
    private function _build_preset_table(array $presets): array
    {
        $table = ee('CP/Table', [
            'autosort' => true,
            'autosearch' => true,
            'sortable' => true,
            'class' => 'tbl-ctrls tbl-fixed'
        ]);
        
        $table->setColumns([
            'name' => [
                'label' => 'Preset Name',
                'sort' => true
            ],
            'description' => [
                'label' => 'Description', 
                'sort' => true
            ],
            'parameter_count' => [
                'label' => 'Parameters',
                'sort' => true,
                'class' => 'center'
            ],
            'created_date' => [
                'label' => 'Created',
                'sort' => true,
                'class' => 'center'
            ],
            'actions' => [
                'label' => 'Actions',
                'sort' => false,
                'encode' => false
            ]
        ]);
        
        $table->setNoResultsText(
            'No presets found.',
            'Create New Preset',
            ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/create')
        );
        
        // Build table data
        $table_data = [];
        foreach ($presets as $preset) {
            // Parameters are already decoded by PresetService->getAllPresets()
            $parameters = $preset['parameters'] ?? [];
            
            // Count parameters (exclude preview_file_id)
            $parameter_count = count(array_filter(
                array_keys($parameters), 
                fn($key) => $key !== 'preview_file_id'
            ));
            
            $table_data[] = [
                'name' => $preset['name'],
                'description' => $preset['description'] ?: '—',
                'parameter_count' => $parameter_count,
                'created_date' => $preset['created_date'] ? date('M j, Y', $preset['created_date']) : 'Unknown',
                'actions' => '
                    <div class="button-toolbar">
                        <a href="' . ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset['id']) . '" class="button button--default button--small" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="' . ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/delete/' . $preset['id']) . '" class="button button--default button--small" title="Delete" onclick="return confirm(\'Are you sure you want to delete this preset?\')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>'
            ];
        }
        
        $table->setData($table_data);
        
        // Return viewData for template rendering (like documentation shows)
        return $table->viewData(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
    }

    /**
     * Build global settings form
     * 
     * @param array $current_settings Current addon settings
     * @param int $preset_count Number of existing presets
     * @return mixed CP/Form instance
     */
    private function _build_global_settings_form(array $current_settings, int $preset_count)
    {
        $form = ee('CP/Form');
        
        // Set form action URL
        $form_action_url = ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/update_settings');
        $form->setBaseUrl($form_action_url);
        
        // Configure form buttons - hide if no presets exist
        if ($preset_count > 0) {
            $form->set('save_btn_text', 'Save Settings');
            $form->set('save_btn_text_working', 'Saving');
        }
        $form->set('hide_top_buttons', true);
        
        // Global default preview image setting
        $default_preview_file_id = $current_settings['preset_default_preview_file_id'] ?? 0;
        
        // Create group for Global Preset Settings
        $global_group = $form->getGroup('Global Preset Settings');
        
        // Default preview file fieldset
        $preview_fieldset = $global_group->getFieldSet('Default Preview File');
        $preview_fieldset->setDesc('Choose a default preview image for presets that don\'t have a specific sample image assigned.');
        
        $file_picker = $preview_fieldset->getField('preset_default_preview_file_id', 'file-picker');
        $file_picker->asImage()
                    ->setValue($default_preview_file_id)
                    ->setRequired(false);
        
        return $form;
    }

    /**
     * Load CSS assets for the management interface
     * 
     * @return void
     */
    private function _load_management_assets(): void
    {
        // Add CSS files using EE's recommended method
        ee()->cp->add_to_head('<link rel="stylesheet" type="text/css" href="' . URL_THIRD_THEMES . 'jcogs_img_pro/css/preset-management.css">');
    }
}
