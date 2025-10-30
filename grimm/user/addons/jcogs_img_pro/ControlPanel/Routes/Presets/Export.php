<?php

/**
 * JCOGS Image Pro - Export Preset Route
 * ======================================
 * Route for exporting preset as JSON file
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

class Export extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'presets/export';

    /**
     * Export preset as JSON download
     * 
     * @param mixed $preset_id Preset ID to export
     * @return $this Fluent interface for EE7 routing
     */
    public function process($preset_id = false)
    {
        // Get preset ID from URL segments
        $preset_id = $this->utilities_service->getPresetIdFromUrl();
        
        if (empty($preset_id)) {
            ee('CP/Alert')->makeInline('export_error')
                ->asIssue()
                ->withTitle('Export Failed')
                ->addToBody('No preset ID specified for export.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
            return $this;
        }

        try {
            // Get the preset data
            $preset = $this->preset_service->getPresetById($preset_id);
            if (!$preset) {
                throw new Exception('Preset not found');
            }
            
            // Export the preset
            $export_result = $this->preset_service->exportPreset($preset_id);
            
            if (!$export_result['success']) {
                $error_message = isset($export_result['errors']) 
                    ? (is_array($export_result['errors']) ? implode(', ', $export_result['errors']) : $export_result['errors'])
                    : 'Export failed';
                throw new Exception($error_message);
            }
            
            // Prepare for download
            $filename = 'preset_' . $preset['name'] . '_' . date('Y-m-d') . '.json';
            $json_data = $export_result['json_data']; // Fixed: was 'data', should be 'json_data'
            
            // Set headers for file download
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($json_data));
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
            
            // Output the JSON data and exit
            echo $json_data;
            exit;
            
        } catch (Exception $e) {
            $this->utilities_service->debug_log('preset_export_error', $e->getMessage());
            
            ee('CP/Alert')->makeInline('export_error')
                ->asIssue()
                ->withTitle('Export Failed')
                ->addToBody('Failed to export preset: ' . $e->getMessage())
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset_id));
            return $this;
        }
    }
}
