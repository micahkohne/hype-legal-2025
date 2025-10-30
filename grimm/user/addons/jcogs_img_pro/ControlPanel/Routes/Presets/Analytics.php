<?php

/**
 * JCOGS Image Pro - Preset Analytics Route
 * =========================================
 * Route for viewing and managing preset analytics data
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Preset Analytics Feature Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\Presets;

use JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\ImageAbstractRoute;
use Exception;

/**
 * Preset Analytics Route
 * 
 * Handles viewing and managing analytics data for presets including usage statistics,
 * error tracking, and performance metrics. Provides functionality to view detailed
 * analytics and reset statistics for individual presets.
 */
class Analytics extends ImageAbstractRoute
{
    /**
     * Process analytics request
     * 
     * Handles displaying preset analytics data and processing reset statistics requests.
     * Extracts preset ID from URL, loads analytics data, and prepares view variables
     * for the analytics template.
     * 
     * @param mixed $preset_id Preset ID (extracted from URL if not provided)
     * @return $this
     */
    public function process($preset_id = false)
    {
        // Get preset ID from URL segments (like Edit route does)
        $preset_id = $this->utilities_service->getPresetIdFromUrl();
        
        if (empty($preset_id)) {
            ee('CP/Alert')->makeInline('analytics_error')
                ->asIssue()
                ->withTitle('Analytics Error')
                ->addToBody('No preset ID specified for analytics.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
            return $this;
        }

        // Handle POST request for reset statistics
        if (count($_POST) > 0 && ee()->input->post('reset_statistics')) {
            return $this->_handle_reset_statistics($preset_id);
        }

        // Get analytics data for the preset
        $analytics = $this->preset_service->getPresetAnalytics($preset_id);
        
        if (!$analytics['success']) {
            ee('CP/Alert')->makeInline('analytics_error')
                ->asIssue()
                ->withTitle('Analytics Error')
                ->addToBody($analytics['error'] ?? 'Failed to load analytics')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset_id));
            return $this;
        }

        // Get usage summary for context
        $usage_summary = $this->preset_service->getPresetUsageSummary();

        // Set up breadcrumbs
        $this->addBreadcrumb('presets', 'Preset Management', ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
        $this->addBreadcrumb('edit', $analytics['preset_name'], ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset_id));
        $this->addBreadcrumb('analytics', 'Analytics');

        // Prepare view variables
        $vars = [
            'preset_analytics' => $analytics,
            'usage_summary' => $usage_summary,
            'page_title' => 'Preset Analytics: ' . $analytics['preset_name'],
            'back_url' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset_id)->compile(),
            'preset_edit_url' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset_id)->compile()
        ];

        // Load CSS and JavaScript assets for the analytics interface
        $this->_load_analytics_assets();

        $this->setBody('preset_analytics', $vars);
        return $this;
    }

    /**
     * Handle reset statistics POST request
     * 
     * Resets all analytics statistics for the specified preset including usage count,
     * error count, last used date, and performance data. Provides user feedback
     * through CP alerts and redirects back to the analytics page.
     * 
     * @param int $preset_id Preset ID to reset statistics for
     * @return $this
     */
    private function _handle_reset_statistics(int $preset_id)
    {
        try {
            // Get preset name for success message
            $preset = $this->preset_service->getPresetById($preset_id);
            $preset_name = $preset['name'] ?? 'Unknown';
            
            // Reset all statistics for this preset
            $result = $this->preset_service->resetPresetStatistics($preset_id);
            
            if ($result) {
                ee('CP/Alert')->makeInline('reset_success')
                    ->asSuccess()
                    ->withTitle('Statistics Reset')
                    ->addToBody("All statistics for preset '{$preset_name}' have been reset.")
                    ->defer();
            } else {
                ee('CP/Alert')->makeInline('reset_error')
                    ->asIssue()
                    ->withTitle('Reset Failed')
                    ->addToBody('Failed to reset statistics. Please try again.')
                    ->defer();
            }
            
        } catch (Exception $e) {
            ee('CP/Alert')->makeInline('reset_error')
                ->asIssue()
                ->withTitle('Reset Error')
                ->addToBody('An error occurred while resetting statistics: ' . $e->getMessage())
                ->defer();
        }
        
        // Redirect back to analytics page
        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/analytics/' . $preset_id));
        return $this;
    }

    /**
     * Load CSS and JavaScript assets for the analytics interface
     * 
     * @return void
     */
    private function _load_analytics_assets(): void
    {
        // Add CSS files using EE's recommended method
        ee()->cp->add_to_head('<link rel="stylesheet" type="text/css" href="' . URL_THIRD_THEMES . 'jcogs_img_pro/css/preset-analytics.css">');
        
        // Load JavaScript files using EE's recommended method
        ee()->cp->add_to_foot('<script defer src="' . URL_THIRD_THEMES . 'jcogs_img_pro/javascript/preset-analytics.js"></script>');
        
        // Add configuration JavaScript for CSRF token
        $config_js = 'window.jcogsAnalyticsConfig = {
            csrfToken: "' . CSRF_TOKEN . '"
        };';
        
        // Add configuration JavaScript using proper EE method
        ee()->cp->add_to_foot('<script>' . $config_js . '</script>');
    }
}
