<?php

/**
 * JCOGS Image Pro - Delete Preset Route
 * =====================================
 * Dedicated route for deleting presets
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

class Delete extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'presets/delete';

    /**
     * Process preset deletion request
     * 
     * @param mixed $id Route parameter (may contain preset ID)
     * @return $this Fluent interface for EE7 routing
     */
    public function process($id = false)
    {
        // Get preset ID from URL segments
        $preset_id = $this->utilities_service->getPresetIdFromUrl();
        
        if (empty($preset_id)) {
            ee('CP/Alert')->makeInline('missing-preset')
                ->asIssue()
                ->withTitle('Missing Preset')
                ->addToBody('No preset specified for deletion.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
            return $this;
        }

        try {
            // Load the preset to get its name for success message
            $preset = $this->preset_service->getPresetById($preset_id);
            if (!$preset) {
                ee('CP/Alert')->makeInline('preset-not-found')
                    ->asIssue()
                    ->withTitle('Preset Not Found')
                    ->addToBody('The specified preset could not be found.')
                    ->defer();
                    
                ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
                return $this;
            }

            // Delete the preset
            $this->preset_service->deletePreset($preset_id);

            // Success message
            ee('CP/Alert')->makeInline('preset-deleted')
                ->asSuccess()
                ->withTitle('Preset Deleted')
                ->addToBody("Preset '{$preset['name']}' has been successfully deleted.")
                ->defer();

        } catch (Exception $e) {
            ee('CP/Alert')->makeInline('delete-error')
                ->asIssue()
                ->withTitle('Delete Failed')
                ->addToBody('An error occurred while deleting the preset: ' . $e->getMessage())
                ->defer();
                
            $this->utilities_service->debug_log('Preset deletion failed', $preset_id, $e->getMessage());
        }

        // Always redirect back to preset list
        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
        return $this;
    }
}
