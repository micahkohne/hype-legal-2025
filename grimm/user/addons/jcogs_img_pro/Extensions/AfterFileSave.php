<?php

/**
 * JCOGS Image Pro - After File Save Extension
 * ============================================
 * Handles cache cleanup when files are saved via file upload/creation
 * Delegates to AfterFileUpdate for actual processing
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 3 Legacy Independence
 */

namespace JCOGSDesign\JCOGSImagePro\Extensions;

use ExpressionEngine\Service\Addon\Controllers\Extension\AbstractRoute;

/**
 * After File Save Extension
 * 
 * Lightweight extension that handles the after_file_save hook by delegating
 * to the main AfterFileUpdate extension. This approach maintains separation
 * of concerns while avoiding code duplication.
 * 
 * The after_file_save hook receives 2 parameters from EE: $file_model and $values.
 * 
 * Created to resolve migration compatibility issues where the database
 * expects this extension class to exist.
 */
class AfterFileSave extends AbstractRoute
{
    /**
     * Handle after_file_save hook
     * 
     * This hook is triggered when files are uploaded or created in EE.
     * EE7 extensions use the process() method regardless of hook name.
     * 
     * @param object $file_model The saved file model
     * @param array $values Original form values
     * @return void
     */
    public function process($file_model, $values): void
    {
        try {
            // Delegate to the main AfterFileUpdate extension
            $after_file_update = new AfterFileUpdate();
            $after_file_update->after_file_update($file_model, $values);
            
        } catch (\Throwable $e) {
            // Re-throw only if we're in development to aid debugging
            if (defined('DEBUG') && DEBUG) {
                throw $e;
            }
        }
    }
    
    /**
     * Extension settings - None required for this delegation extension
     * 
     * @return array Empty settings array
     */
    public function settings(): array
    {
        return [];
    }
    
    /**
     * Update extension settings - No-op for this delegation extension
     * 
     * @param array $settings New settings
     * @return array Settings unchanged
     */
    public function update_extension($settings = []): array
    {
        return $settings;
    }
    
    /**
     * Disable extension - Clean up hook registration
     * 
     * @return void
     */
    public function disable_extension(): void
    {
        ee()->db->where('class', __CLASS__)
                ->delete('extensions');
    }
}
