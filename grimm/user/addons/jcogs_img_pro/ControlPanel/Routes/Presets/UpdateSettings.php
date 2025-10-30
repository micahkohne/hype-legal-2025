<?php

/**
 * JCOGS Image Pro - Update Settings Route
 * ========================================
 * Route for updating global preset settings
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

class UpdateSettings extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'presets/update_settings';

    /**
     * Update global preset settings
     * 
     * @param mixed $id Not used for this route
     * @return $this Fluent interface for EE7 routing
     */
    public function process($id = false)
    {
        // Only process POST requests
        if (count($_POST) === 0) {
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
            return $this;
        }
        
        try {
            // Get posted data
            $preset_default_preview_file_id = ee()->input->post('preset_default_preview_file_id') ?: 0;
            
            // Validate file ID if provided
            if ($preset_default_preview_file_id > 0) {
                // Parse EE file directive if needed (e.g., "{file:135:url}" -> file ID 135)
                $actual_file_id = $this->parse_file_id_from_value($preset_default_preview_file_id);
                
                if (!$actual_file_id) {
                    ee('CP/Alert')->makeInline('settings_error')
                        ->asIssue()
                        ->withTitle('Invalid File Value')
                        ->addToBody('The provided file value could not be parsed. Please select a valid image file.')
                        ->defer();
                        
                    ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
                    return $this;
                }
                
                // Check if file exists and belongs to current site
                $file = ee('Model')->get('File')
                    ->filter('file_id', $actual_file_id)
                    ->filter('site_id', ee()->config->item('site_id'))
                    ->first();
                    
                if (!$file) {
                    ee('CP/Alert')->makeInline('settings_error')
                        ->asIssue()
                        ->withTitle('Invalid File')
                        ->addToBody('The selected preview image file could not be found or does not belong to this site.')
                        ->defer();
                        
                    ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
                    return $this;
                }
                
                // Additional validation: ensure it's an image file
                if (!in_array($file->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                    ee('CP/Alert')->makeInline('settings_error')
                        ->asIssue()
                        ->withTitle('Invalid File Type')
                        ->addToBody('The selected file must be an image (JPEG, PNG, GIF, or WebP).')
                        ->defer();
                        
                    ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
                    return $this;
                }
            }
            
            // Save the setting using the array format expected by save_settings()
            $this->settings_service->save_settings([
                'preset_default_preview_file_id' => $preset_default_preview_file_id
            ]);
            
            // Success message
            ee('CP/Alert')->makeInline('settings_success')
                ->asSuccess()
                ->withTitle('Settings Saved')
                ->addToBody('Global preset settings have been updated successfully.')
                ->defer();
                
        } catch (Exception $e) {
            $this->utilities_service->debug_log('preset_settings_error', $e->getMessage());
            
            ee('CP/Alert')->makeInline('settings_error')
                ->asIssue()
                ->withTitle('Save Failed')
                ->addToBody('Failed to save settings: ' . $e->getMessage())
                ->defer();
        }
        
        // Redirect back to presets page
        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
        return $this;
    }
    
    /**
     * Parse file ID from EE file directive value
     * Handles both numeric IDs and EE file directive syntax like {file:135:url}
     */
    private function parse_file_id_from_value($value)
    {
        // If already numeric, return as-is
        if (is_numeric($value)) {
            return (int) $value;
        }
        
        // Handle EE file directive syntax {file:ID:url}
        if (is_string($value) && preg_match('/\{file:(\d+):[^}]+\}/', $value, $matches)) {
            return (int) $matches[1];
        }
        
        // Return null if no valid file ID found
        return null;
    }
}
