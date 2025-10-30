<?php

/**
 * JCOGS Image Pro - Update Preview Route
 * ======================================
 * Route for updating custom preview images for presets
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

/**
 * Update Preview Route
 * 
 * Handles updating custom preview images for presets
 */
class UpdatePreview extends ImageAbstractRoute
{
    protected $route_path = 'presets/update_preview';

    /**
     * Update preview image for a specific preset
     * 
     * @param mixed $preset_id Preset ID (from URL)
     * @return $this
     */
    public function process($preset_id = false)
    {
        // Get preset ID from URL segments  
        $preset_id = $this->utilities_service->getPresetIdFromUrl();
        
        if (empty($preset_id)) {
            ee('CP/Alert')->makeInline('update_preview_error')
                ->asIssue()
                ->withTitle('Invalid Request')
                ->addToBody('Missing preset ID.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
            return $this;
        }
        
        if (count($_POST) === 0) {
            return ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset_id));
        }
        
        // Get the preset
        $preset_service = ee('jcogs_img_pro:PresetService');
        $preset = $preset_service->getPresetById($preset_id);
        
        if (!$preset) {
            ee('CP/Alert')->makeInline('update_preview_error')
                ->asIssue()
                ->withTitle('Preset Not Found')
                ->addToBody('The specified preset could not be found.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
            return $this;
        }
        
        // Update preview file ID in parameters
        $parameters = $preset['parameters'] ?? [];
        if (is_string($parameters)) {
            $parameters = json_decode($parameters, true) ?? [];
        }
        
        $preview_file_id = (int)($_POST['preview_file_id'] ?? 0);
        
        if ($preview_file_id > 0) {
            $parameters['preview_file_id'] = $preview_file_id;
        } else {
            unset($parameters['preview_file_id']);
        }
        
        // Update the preset
        try {
            $preset_service->updatePreset($preset_id, [
                'parameters' => json_encode($parameters)
            ]);
            
            ee('CP/Alert')->makeInline('preview-updated')
                ->asSuccess()
                ->withTitle('Preview Updated')
                ->addToBody('Custom preview image has been updated successfully.')
                ->defer();
                
        } catch (Exception $e) {
            ee('CP/Alert')->makeInline('preview-error')
                ->asIssue()
                ->withTitle('Update Failed')
                ->addToBody('Could not update preview image: ' . $e->getMessage())
                ->defer();
        }
        
        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset_id));
        return $this;
    }
}
