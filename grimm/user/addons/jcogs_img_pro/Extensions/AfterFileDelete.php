<?php

/**
 * JCOGS Image Pro - After File Delete Extension
 * ==============================================
 * EE7 extension that handles the after_file_delete hook
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      File Management Extension Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Extensions;

use ExpressionEngine\Service\Addon\Controllers\Extension\AbstractRoute;

/**
 * After File Delete Extension
 * 
 * EE7 extension that handles the after_file_delete hook. This extension processes
 * file deletion events and delegates to the main AfterFileUpdate extension for
 * actual cleanup logic.
 * 
 * The after_file_delete hook receives 2 parameters from EE: $file (model object) and $values (array).
 */
class AfterFileDelete extends AbstractRoute
{
    /**
     * Handle after_file_delete hook
     * 
     * EE7 extensions use the process() method regardless of hook name.
     * 
     * @param object $file_model The deleted file model
     * @param array $values The file model data as an array
     * @return void
     */
    public function process($file_model, $values): void
    {
        try {
            // For file deletion, we may need to clean up related image records
            // Delegate to the main AfterFileUpdate extension which has the full logic
            $main_extension = new \JCOGSDesign\JCOGSImagePro\Extensions\AfterFileUpdate();
            
            // Call the main extension's delete handling method if it exists
            if (method_exists($main_extension, 'handle_file_delete')) {
                $main_extension->handle_file_delete($file_model, $values);
            }
                
        } catch (\Exception $e) {
        }
    }
}
