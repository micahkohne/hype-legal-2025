<?php

/**
 * JCOGS Image Pro - Abstract Route Base Class
 * ============================================
 * Base class for all Image Pro control panel routes with shared service initialization
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 2 Service Optimization
 */

namespace JCOGSDesign\JCOGSImagePro\ControlPanel\Routes;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

/**
 * Abstract Route Class with Shared Services
 * 
 * Extends EE's AbstractRoute and provides automatic initialization of common services
 * using the ServiceCache pattern for optimal performance.
 * 
 * All Image Pro control panel routes should extend this class to inherit:
 * - Shared service instances (settings, utilities, filesystem, etc.)
 * - Common functionality for Image Pro routes
 * - Automatic service optimization
 */
abstract class ImageAbstractRoute extends AbstractRoute
{
    /**
     * Shared services available to all Image Pro routes (using ServiceCache for optimal performance)
     */
    protected $settings_service;
    protected $utilities_service;
    protected $filesystem_service;
    protected $validation_service;
    protected $performance_service;
    protected $colour_service;
    protected $cache_service;
    protected $preset_service;
    
    /**
     * Constructor - automatically initializes shared services
     * 
     * Called automatically when any route extending this class is instantiated.
     * Provides immediate access to all common services without repeated instantiation.
     */
    public function __construct()
    {
        parent::__construct();
        
        // Initialize shared services using ServiceCache for optimal performance
        $this->settings_service = ServiceCache::settings();
        $this->utilities_service = ServiceCache::utilities();
        $this->filesystem_service = ServiceCache::filesystem();
        $this->validation_service = ServiceCache::validation();
        $this->performance_service = ServiceCache::performance();
        $this->colour_service = ServiceCache::colour();
        $this->cache_service = ServiceCache::cache();
        $this->preset_service = ServiceCache::preset_service();
    }
    
    /**
     * Common functionality for Image Pro routes
     */
    
    /**
     * Build sidebar using common method (available to all extending routes)
     * 
     * @param array $current_settings Current settings array
     * @return void
     */
    protected function build_sidebar(array $current_settings): void
    {
        $this->utilities_service->build_sidebar($current_settings);
    }
    
    /**
     * Get current settings with optional defaults and session cache fallback
     * 
     * @param array $defaults Optional default values to merge
     * @param bool $use_session_cache Whether to check session cache for temporary settings
     * @return array Current settings with optional defaults
     */
    protected function _get_current_settings(array $defaults = [], bool $use_session_cache = false): array
    {
        // Get settings from the service with fallback mechanism
        if ($this->settings_service && method_exists($this->settings_service, 'all')) {
            $settings = $this->settings_service->all();
        } elseif ($this->settings_service && method_exists($this->settings_service, 'get_all')) {
            $settings = $this->settings_service->get_all();
        } else {
            // Fallback to basic cache settings if no service method available
            $settings = [
                'img_cp_default_cache_duration' => ee()->config->item('jcogs_img_pro_default_cache_duration') ?? '2678400',
                'img_cp_cache_auto_manage' => ee()->config->item('jcogs_img_pro_cache_auto_manage') ?? 'y',
            ];
        }
        
        // Check session cache for temporary settings (if requested)
        if ($use_session_cache) {
            $session_settings = ee()->session->cache(__CLASS__, 'jcogs_img_pro_settings', []);
            if (!empty($session_settings)) {
                $settings = array_merge($settings, $session_settings);
            }
        }
        
        // Merge with provided defaults (defaults are applied first, then overridden by actual settings)
        if (!empty($defaults)) {
            return array_merge($defaults, $settings);
        }
        
        return $settings;
    }
    
    /**
     * Load Image Pro language file (available to all extending routes)
     * 
     * @param string $language_key Optional specific language key
     * @return void
     */
    protected function load_language(string $language_key = 'jcogs_img_pro'): void
    {
        ee()->lang->load($language_key, ee()->session->get_language(), false, true, PATH_THIRD . 'jcogs_img_pro/');
    }
    
    /**
     * Redirect to route (available to all extending routes)
     * 
     * @param string $route_name Route name to redirect to
     * @return void
     */
    protected function redirect_to_route(string $route_name): void
    {
        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/' . $route_name));
    }
    
    /**
     * Save settings with validation (available to all extending routes)
     * 
     * @param array $posted_data Posted form data
     * @return bool Success status
     */
    protected function save_settings(array $posted_data): bool
    {
        try {
            $this->settings_service->save_settings($posted_data);
            return true;
        } catch (\Exception $e) {
            // Log error using shared utilities service
            $this->utilities_service->debug_log('Settings save error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set success flash message (available to all extending routes)
     * 
     * @param string $message_key Language key for success message
     * @return void
     */
    protected function set_success_message(string $message_key): void
    {
        ee()->session->set_flashdata('message_success', lang($message_key));
    }
    
    /**
     * Validate and load preset from URL
     * 
     * Common pattern used by preset routes to extract preset ID from URL,
     * validate it exists, and load the preset data. Shows appropriate error
     * alerts and redirects if validation fails.
     * 
     * @param string $error_prefix Prefix for error alert IDs (e.g., 'edit_error')
     * @param string $redirect_route Route to redirect to on error (default: 'presets')
     * @return array|null Preset data or null if validation failed
     */
    protected function validateAndLoadPresetFromUrl(string $error_prefix = 'preset_error', string $redirect_route = 'presets'): ?array
    {
        // Get preset ID from URL segments
        $preset_id = $this->utilities_service->getPresetIdFromUrl();
        
        if (empty($preset_id)) {
            ee('CP/Alert')->makeInline($error_prefix . '_no_id')
                ->asIssue()
                ->withTitle('Invalid Request')
                ->addToBody('No preset ID specified.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/' . $redirect_route));
            return null;
        }
        
        // Load the preset using centralized PresetService
        $preset = $this->preset_service->getPresetById($preset_id);
        if (!$preset) {
            ee('CP/Alert')->makeInline($error_prefix . '_not_found')
                ->asIssue()
                ->withTitle('Preset Not Found')
                ->addToBody('The specified preset could not be found.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/' . $redirect_route));
            return null;
        }
        
        return $preset;
    }
}