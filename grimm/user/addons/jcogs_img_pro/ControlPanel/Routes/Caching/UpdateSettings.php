<?php

/**
 * JCOGS Image Pro - Cache Settings Update Route
 * ============================================
 * Handles form submissions for cache management settings updates
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 3 Legacy Independence - Named Connections System
 */

namespace JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\Caching;

use JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\ImageAbstractRoute;
use Exception;

class UpdateSettings extends ImageAbstractRoute
{
    
    /**
     * @var array|null Cached named filesystem adapters configuration
     */
    private $cached_named_adapters_config = null;

    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'caching/update_settings';

    /**
     * Process cache settings update form submission
     * 
     * This route handles POST form submissions from the unified cache management form.
     * After processing, it redirects back to the main cache management page.
     * 
     * @param mixed $id Route parameter (unused for this action)
     * @return $this Fluent interface for EE7 routing
     */
    public function process($id = false)
    {
        // Load language file for internationalization
        $this->load_language();
        
        try {
            // Get form data
            $default_cache_duration_raw = ee()->input->post('img_cp_default_cache_duration', true);
            $cache_auto_manage = ee()->input->post('img_cp_cache_auto_manage', true);
            $default_connection = ee()->input->post('default_cache_connection', true);

            // Parse cache duration using DurationParser
            $duration_parser = new \JCOGSDesign\JCOGSImagePro\Service\DurationParser();
            $parsed = $duration_parser->parseToSeconds($default_cache_duration_raw);
            if ($parsed['error']) {
                throw new Exception($parsed['error']);
            }
            $cache_duration = $parsed['value'];
            $validation = $duration_parser->validateForContext($cache_duration, 'cache');
            if (!$validation['valid']) {
                throw new Exception($validation['error']);
            }

            // Handle default connection update if provided
            if (!empty($default_connection)) {
                $this->utilities_service->debug_log("JCOGS IMG PRO DEBUG - Processing default connection update");
                // ...existing code...
            } else {
                $this->utilities_service->debug_log("JCOGS IMG PRO DEBUG - No default_connection provided in POST");
            }

            // Validate auto-manage setting
            if (!in_array($cache_auto_manage, ['y', 'n'])) {
                $cache_auto_manage = 'y'; // Default to enabled
            }

            // Get current settings and update them
            $current_settings = $this->_get_current_settings();
            $current_settings['img_cp_default_cache_duration'] = strval($cache_duration);
            $current_settings['img_cp_cache_auto_manage'] = $cache_auto_manage;

            // Save settings using the settings service
            if ($this->settings_service && method_exists($this->settings_service, 'save')) {
                $this->settings_service->save($current_settings);
            } else {
                // Fallback to direct EE settings save
                $this->_save_settings_direct($current_settings);
            }

            // Show success message
            $saved_items = [];
            if (!empty($default_connection)) {
                $saved_items[] = 'default cache connection';
            }
            $saved_items[] = 'cache duration';
            $saved_items[] = 'auto-management setting';

            ee('CP/Alert')->makeInline('cache-settings')
                ->asSuccess()
                ->withTitle('Settings Saved')
                ->addToBody('Successfully saved: ' . implode(', ', $saved_items) . '.');

        } catch (Exception $e) {
            // Show error message
            ee('CP/Alert')->makeInline('cache-settings')
                ->asIssue()
                ->withTitle('Error Saving Settings')
                ->addToBody('Failed to save cache settings: ' . $e->getMessage());
        }

        // Redirect back to cache management
        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching'));
        return $this;
    }
    
    /**
     * Get named filesystem adapters configuration
     * 
     * @return array Named adapters configuration
     */
    private function _get_named_adapters_config(): array
    {
        if ($this->cached_named_adapters_config !== null) {
            return $this->cached_named_adapters_config;
        }
        
        if ($this->settings_service && method_exists($this->settings_service, 'getNamedFilesystemAdapters')) {
            $config = $this->settings_service->getNamedFilesystemAdapters();
            if (is_array($config)) {
                $this->cached_named_adapters_config = $config;
                return $config;
            }
        }
        
        // Return empty configuration if not found
        $this->cached_named_adapters_config = [
            'connections' => [],
            'default_connection' => ''
        ];
        return $this->cached_named_adapters_config;
    }
    
    /**
     * Save settings directly to EE configuration (fallback method)
     * 
     * @param array $settings Settings to save
     * @return void
     */
    private function _save_settings_direct(array $settings): void
    {
        foreach ($settings as $key => $value) {
            // Convert addon setting keys to EE config keys
            $config_key = str_replace('img_cp_', 'jcogs_img_pro_', $key);
            ee()->config->set_item($config_key, $value);
        }
    }
}
