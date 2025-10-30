<?php

/**
 * JCOGS Image Pro - Duplicate Preset Route
 * =========================================
 * Route for duplicating existing preset with new name
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Advanced Features Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\Presets;

use Exception;
use JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\ImageAbstractRoute;

class Duplicate extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'presets/duplicate';

    /**
     * @var string Control panel page title
     */
    protected $cp_page_title = 'Duplicate Preset';

    /**
     * Duplicate existing preset with new name
     * 
     * @param mixed $preset_id Preset ID to duplicate (may be unused if extracted from URL)
     * @return $this Fluent interface for EE7 routing
     */
    public function process($preset_id = false)
    {
        // Get preset ID from URL segments (like edit and other routes)
        $preset_id = $this->utilities_service->getPresetIdFromUrl();
        
        if (empty($preset_id)) {
            ee('CP/Alert')->makeInline('invalid_id')
                ->asIssue()
                ->withTitle('Invalid Request')
                ->addToBody('Preset ID is required to duplicate a preset.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
            return $this;
        }

        // Handle POST request - process duplication
        if (count($_POST) > 0) {
            return $this->_handle_duplicate($preset_id);
        }
        
        // GET request - show duplicate form
        try {
            $preset = $this->preset_service->loadPreset($preset_id);
            
            if (!$preset) {
                throw new Exception('Preset not found');
            }
            
            $this->build_sidebar($this->_get_current_settings());
            $this->addBreadcrumb('presets', 'Preset Management', ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
            $this->addBreadcrumb('duplicate', $this->cp_page_title);

            $variables = [
                'cp_page_title' => $this->cp_page_title,
                'preset' => $preset,
                'duplicate_url' => ee('CP/URL')->make("addons/settings/jcogs_img_pro/presets/duplicate/{$preset_id}")->compile(),
                'presets_url' => (string) ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'),
                'csrf_token' => CSRF_TOKEN,
                'suggested_name' => $preset['name'] . '_copy'
            ];

            // Load CSS assets for the duplicate interface
            $this->_load_duplicate_assets();

            $this->setBody('preset_duplicate', $variables);
            return $this;
            
        } catch (Exception $e) {
            $this->utilities_service->debug_log('preset_duplicate_load_error', $e->getMessage());
            
            ee('CP/Alert')->makeInline('load_error')
                ->asIssue()
                ->withTitle('Load Failed')
                ->addToBody('Failed to load preset for duplication: ' . $e->getMessage())
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
            return $this;
        }
    }

    /**
     * Handle duplicate form submission
     * 
     * @param int $preset_id Original preset ID
     * @return $this
     */
    private function _handle_duplicate($preset_id)
    {
        try {
            $new_name = trim(ee()->input->post('preset_name'));
            $new_description = trim(ee()->input->post('preset_description'));
            
            if (empty($new_name)) {
                throw new Exception('Preset name is required');
            }
            
            // Duplicate the preset
            $duplicate_result = $this->preset_service->duplicatePreset($preset_id, $new_name, $new_description);
            
            if ($duplicate_result['success']) {
                ee('CP/Alert')->makeInline('duplicate_success')
                    ->asSuccess()
                    ->withTitle('Duplication Successful')
                    ->addToBody("Preset '{$new_name}' has been created successfully.")
                    ->defer();
                    
                // Redirect to edit the new preset
                ee()->functions->redirect(ee('CP/URL')->make("addons/settings/jcogs_img_pro/presets/edit/{$duplicate_result['preset_id']}"));
            } else {
                throw new Exception(implode(', ', $duplicate_result['errors'] ?? ['Duplication failed']));
            }
            
        } catch (Exception $e) {
            $this->utilities_service->debug_log('preset_duplicate_error', $e->getMessage());
            
            ee('CP/Alert')->makeInline('duplicate_error')
                ->asIssue()
                ->withTitle('Duplication Failed')
                ->addToBody('Failed to duplicate preset: ' . $e->getMessage())
                ->defer();
        }
        
        // Redirect back to duplicate form
        ee()->functions->redirect(ee('CP/URL')->make("addons/settings/jcogs_img_pro/presets/duplicate/{$preset_id}"));
        return $this;
    }

    /**
     * Load CSS assets for the duplicate interface
     * 
     * @return void
     */
    private function _load_duplicate_assets(): void
    {
        // Add CSS files using EE's recommended method
        ee()->cp->add_to_head('<link rel="stylesheet" type="text/css" href="' . URL_THIRD_THEMES . 'jcogs_img_pro/css/preset-duplicate.css">');
    }
}
