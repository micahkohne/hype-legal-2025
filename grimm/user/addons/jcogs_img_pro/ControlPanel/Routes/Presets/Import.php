<?php

/**
 * JCOGS Image Pro - Import Preset Route
 * ======================================
 * Route for importing preset from JSON file
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

class Import extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'presets/import';

    /**
     * @var string Control panel page title
     */
    protected $cp_page_title = 'Import Preset';

    /**
     * Import preset from JSON file
     * 
     * @param mixed $id Unused route parameter
     * @return $this Fluent interface for EE7 routing
     */
    public function process($id = false)
    {
        // Handle POST request - process import
        if (count($_POST) > 0) {
            return $this->_handle_import();
        }
        
        // GET request - show import form
        $this->build_sidebar($this->_get_current_settings());
        $this->addBreadcrumb('presets', 'Preset Management', ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
        $this->addBreadcrumb('import', $this->cp_page_title);

        $variables = [
            'cp_page_title' => $this->cp_page_title,
            'import_url' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/import')->compile(),
            'presets_url' => (string) ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'),
            'csrf_token' => CSRF_TOKEN
        ];

        // Load assets for the import interface (for consistency, though no custom CSS needed)
        $this->_load_import_assets();

        $this->setBody('preset_import', $variables);
        return $this;
    }

    /**
     * Handle import form submission
     * 
     * @return $this
     */
    private function _handle_import()
    {
        try {
            // Check if file was uploaded
            if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No file uploaded or upload error occurred');
            }
            
            $upload_file = $_FILES['import_file'];
            
            // Validate file type
            if ($upload_file['type'] !== 'application/json' && 
                pathinfo($upload_file['name'], PATHINFO_EXTENSION) !== 'json') {
                throw new Exception('Please upload a JSON file');
            }
            
            // Read file contents
            $json_content = file_get_contents($upload_file['tmp_name']);
            if ($json_content === false) {
                throw new Exception('Failed to read uploaded file');
            }
            
            // Import options
            $options = [
                'overwrite' => ee()->input->post('overwrite') === 'yes',
                'auto_rename' => ee()->input->post('auto_rename') !== 'no'
            ];
            
            // Import the preset
            $import_result = $this->preset_service->importPreset($json_content, $options);
            
            if ($import_result['success']) {
                ee('CP/Alert')->makeInline('import_success')
                    ->asSuccess()
                    ->withTitle('Import Successful')
                    ->addToBody("Preset '{$import_result['preset_name']}' has been imported successfully.")
                    ->defer();
                    
                ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
            } else {
                throw new Exception(implode(', ', $import_result['errors'] ?? ['Import failed']));
            }
            
        } catch (Exception $e) {
            $this->utilities_service->debug_log('preset_import_error', $e->getMessage());
            
            ee('CP/Alert')->makeInline('import_error')
                ->asIssue()
                ->withTitle('Import Failed')
                ->addToBody('Failed to import preset: ' . $e->getMessage())
                ->defer();
        }
        
        // Redirect back to import form
        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/import'));
        return $this;
    }

    /**
     * Load assets for the import interface
     * 
     * @return void
     */
    private function _load_import_assets(): void
    {
        // Import interface uses standard EE styles - no custom CSS needed
        // This method exists for consistency with other routes
        // Future: Add custom import-specific styling if needed
    }
}
