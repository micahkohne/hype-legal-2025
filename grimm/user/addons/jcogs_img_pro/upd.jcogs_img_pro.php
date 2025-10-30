<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * JCOGS Image Pro - Installer/Updater
 * ====================================
 * Native EE7 installation and update system with Pro database schema
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

use ExpressionEngine\Service\Addon\Installer;

class Jcogs_img_pro_upd extends Installer
{
    public $has_cp_backend = 'y';
    public $has_publish_fields = 'n';

    /**
     * Log debug message during installation/update
     * Uses direct log_message since addon services may not be available
     * 
     * @param string $message Message to log
     * @return void
     */
    private function log_debug($message)
    {
        if (function_exists('log_message')) {
            log_message('debug', "[JCOGS Image Pro] {$message}");
        }
    }

    public function install()
    {
        // Run parent installation (handles migrations)
        parent::install();

        // Initialize settings with defaults
        $this->initialize_settings();

        // Attempt legacy migration if legacy addon exists
        $this->migrate_from_legacy();

        // Clear EE caches for safety
        $this->clear_caches();

        // Clear Jump Menu cache to insert new menu items
        ee('CP/JumpMenu')->clearAllCaches();

        return true;
    }

    public function update($current = '')
    {
        // Run parent update (handles migrations)
        parent::update($current);

        // Update cache count for performance optimization
        $this->update_cache_performance_settings();

        // Clear caches after update
        $this->clear_caches();

        // Clear Jump Menu cache
        ee('CP/JumpMenu')->clearAllCaches();

        return true;
    }

    public function uninstall()
    {
        // Run parent uninstall (handles migrations rollback)
        parent::uninstall();

        // Clear EE cache
        ee()->cache->delete('/jcogs_img_pro/');

        return true;
    }

    /**
     * Migrate settings and data from legacy JCOGS Image addon
     * Only runs if legacy addon exists and has valid license
     * 
     * @return void
     */
    private function migrate_from_legacy(): void
    {
        try {
            // Get legacy settings once and pass to all migration methods
            $legacy_settings = $this->get_legacy_settings();
            
            // Check if legacy addon exists and is valid
            if (!$this->is_legacy_addon_valid($legacy_settings)) {
                $this->log_debug('No valid legacy installation found for migration');
                return;
            }

            $this->log_debug('Starting legacy migration process');

            // Step 1: Migrate settings
            $this->migrate_legacy_settings($legacy_settings);

            // Step 2: Create named connections from legacy adapters  
            $this->migrate_legacy_adapters($legacy_settings);

            // Note: We intentionally do NOT migrate legacy cache log entries
            // Pro should start with a clean cache state for better performance
            $this->log_debug('Cache log migration skipped - Pro will start with clean cache state');

            $this->log_debug('Legacy migration completed successfully');

        } catch (\Throwable $e) {
            $this->log_debug('Legacy migration error: ' . $e->getMessage());
            // Don't fail installation if migration fails - log and continue
        }
    }

    /**
     * Get legacy settings as associative array
     * 
     * @return array
     */
    private function get_legacy_settings(): array
    {
        // Check if legacy settings table exists
        if (!ee()->db->table_exists('jcogs_img_settings')) {
            return [];
        }

        $legacy_settings = ee()->db->get('jcogs_img_settings')->result_array();
        $settings_by_name = [];
        
        foreach ($legacy_settings as $setting) {
            $settings_by_name[$setting['setting_name']] = $setting['value'];
        }

        return $settings_by_name;
    }

    /**
     * Check if legacy JCOGS Image addon exists and has valid license
     * 
     * @param array $legacy_settings
     * @return bool
     */
    private function is_legacy_addon_valid(array $legacy_settings): bool
    {
        // Check if legacy addon is installed
        $legacy_addon = ee('Addon')->get('jcogs_img');
        if (!$legacy_addon || !$legacy_addon->isInstalled()) {
            return false;
        }

        // Check if legacy settings table exists
        if (empty($legacy_settings)) {
            return false;
        }

        // Check for valid license - only 'magic' or 'valid' are acceptable
        $license_key = $legacy_settings['jcogs_license_mode'] ?? '';
        
        return in_array($license_key, ['magic', 'valid']);
    }

    /**
     * Migrate legacy settings to Pro settings table
     * 
     * @param array $legacy_settings
     * @return void
     */
    private function migrate_legacy_settings(array $legacy_settings): void
    {
        if (empty($legacy_settings)) {
            return;
        }

        $settings_service = ee('jcogs_img_pro:Settings');
        $pro_settings = $settings_service::$pro_settings;

        // Define settings to exclude (obsolete in Pro)
        $excluded_settings = [
            'jcogs_license_key',
            'jcogs_license_key_email', 
            'jcogs_staging_domain',
            'jcogs_license_mode',
            'jcogs_license_server_domain',
            'img_cp_default_cache_directory',
            'img_cp_flysystem_adapter',
            'jcogs_add_on_version'  // Preserve Pro version, don't overwrite with legacy
        ];

        // Migrate all legacy settings except excluded ones
        $migrated_count = 0;
        foreach ($legacy_settings as $legacy_name => $legacy_value) {
            // Skip excluded settings
            if (in_array($legacy_name, $excluded_settings)) {
                continue;
            }

            // Skip settings beginning with excluded prefixes
            if (strpos($legacy_name, 'img_cp_flysystem_adapter_') === 0 ||
                strpos($legacy_name, 'img_cp_ee5') === 0) {
                continue;
            }

            // Migrate all other settings directly
            $pro_settings[$legacy_name] = $legacy_value;
            $migrated_count++;
            $this->log_debug("Migrated setting {$legacy_name}");
        }

        // Save updated Pro settings
        $settings_service::$pro_settings = $pro_settings;
        $settings_service->save_settings();

        $this->log_debug("Settings migration completed ({$migrated_count} settings migrated)");
    }

    /**
     * Create named connections from legacy adapter configurations
     * 
     * @param array $legacy_settings
     * @return void
     */
    private function migrate_legacy_adapters(array $legacy_settings): void
    {
        $connections = [];

        // Always add local connection with updated cache directory
        $legacy_cache_dir = $legacy_settings['img_cp_default_cache_directory'] ?? 'images/jcogs_img_pro/cache';
        // Update legacy cache path to use Pro directory structure
        $pro_cache_dir = $this->update_cache_path_for_pro($legacy_cache_dir);
        
        $connections['legacy_local'] = [
            'type' => 'local',
            'is_valid' => true,
            'config' => [
                'cache_directory' => $pro_cache_dir,
                'always_output_full_urls' => $legacy_settings['img_cp_class_always_output_full_urls'] ?? 'n',
                'path_prefix' => $legacy_settings['img_cp_cdn_path_prefix'] ?? ''
            ]
        ];

        // Check for S3 configuration
        if (!empty($legacy_settings['img_cp_flysystem_adapter_s3_bucket']) && 
            !empty($legacy_settings['img_cp_flysystem_adapter_s3_key']) &&
            ($legacy_settings['img_cp_flysystem_adapter_s3_is_valid'] ?? 'false') === 'true') {
            $legacy_s3_path = $legacy_settings['img_cp_flysystem_adapter_s3_server_path'] ?? '';
            $connections['legacy_s3'] = [
                'type' => 's3',
                'is_valid' => true,
                'is_legacy' => true,
                'config' => [
                    'key' => $legacy_settings['img_cp_flysystem_adapter_s3_key'] ?? '',
                    'secret' => $legacy_settings['img_cp_flysystem_adapter_s3_secret'] ?? '',
                    'region' => $legacy_settings['img_cp_flysystem_adapter_s3_region'] ?? 'us-east-1',
                    'bucket' => $legacy_settings['img_cp_flysystem_adapter_s3_bucket'] ?? '',
                    'server_path' => $this->update_cache_path_for_pro($legacy_s3_path),
                    'url' => $legacy_settings['img_cp_flysystem_adapter_s3_url'] ?? ''
                ]
            ];
        }

        // Check for R2 configuration
        if (!empty($legacy_settings['img_cp_flysystem_adapter_r2_bucket']) && 
            !empty($legacy_settings['img_cp_flysystem_adapter_r2_key']) &&
            ($legacy_settings['img_cp_flysystem_adapter_r2_is_valid'] ?? 'false') === 'true') {
            $legacy_r2_path = $legacy_settings['img_cp_flysystem_adapter_r2_server_path'] ?? '';
            $connections['legacy_r2'] = [
                'type' => 'r2',
                'is_valid' => true,
                'is_legacy' => true,
                'config' => [
                    'account_id' => $legacy_settings['img_cp_flysystem_adapter_r2_account_id'] ?? '',
                    'key' => $legacy_settings['img_cp_flysystem_adapter_r2_key'] ?? '',
                    'secret' => $legacy_settings['img_cp_flysystem_adapter_r2_secret'] ?? '',
                    'bucket' => $legacy_settings['img_cp_flysystem_adapter_r2_bucket'] ?? '',
                    'server_path' => $this->update_cache_path_for_pro($legacy_r2_path),
                    'url' => $legacy_settings['img_cp_flysystem_adapter_r2_url'] ?? ''
                ]
            ];
        }

        // Check for DigitalOcean Spaces configuration
        if (!empty($legacy_settings['img_cp_flysystem_adapter_dospaces_space']) && 
            !empty($legacy_settings['img_cp_flysystem_adapter_dospaces_key']) &&
            ($legacy_settings['img_cp_flysystem_adapter_dospaces_is_valid'] ?? 'false') === 'true') {
            $legacy_dospaces_path = $legacy_settings['img_cp_flysystem_adapter_dospaces_server_path'] ?? '';
            $connections['legacy_dospaces'] = [
                'type' => 'dospaces',
                'is_valid' => true,
                'is_legacy' => true,
                'config' => [
                    'key' => $legacy_settings['img_cp_flysystem_adapter_dospaces_key'] ?? '',
                    'secret' => $legacy_settings['img_cp_flysystem_adapter_dospaces_secret'] ?? '',
                    'region' => $legacy_settings['img_cp_flysystem_adapter_dospaces_region'] ?? 'nyc3',
                    'space' => $legacy_settings['img_cp_flysystem_adapter_dospaces_space'] ?? '',
                    'server_path' => $this->update_cache_path_for_pro($legacy_dospaces_path),
                    'url' => $legacy_settings['img_cp_flysystem_adapter_dospaces_url'] ?? ''
                ]
            ];
        }

        // Set default connection based on legacy adapter setting
        $default_adapter = $legacy_settings['img_cp_flysystem_adapter'] ?? 'local';
        $default_connection = 'legacy_' . $default_adapter;

        // Ensure the default connection exists in our connections array
        if (!isset($connections[$default_connection])) {
            $default_connection = 'legacy_local'; // Fallback to local
        }

        // Save connections to Pro settings
        if (!empty($connections)) {
            $settings_service = ee('jcogs_img_pro:Settings');
            
            // Create the correct JSON structure with connections and default_connection
            $named_adapters_config = [
                'connections' => $connections,
                'default_connection' => $default_connection
            ];
            
            $settings_service::$pro_settings['img_cp_named_filesystem_adapters'] = json_encode($named_adapters_config);
            $settings_service->save_settings();

            $this->log_debug("Created " . count($connections) . " named connections from legacy adapters (default: {$default_connection})");
        }
    }

    /**
     * Migrate legacy cache log entries to Pro cache log table
     * 
     * NOTE: This method is intentionally NOT called during installation.
     * Pro should start with a clean cache state for optimal performance.
     * This method is preserved for potential future use or debugging.
     * 
     * @return void
     */
    private function migrate_legacy_cache_log(): void
    {
        // Check if legacy cache log table exists
        if (!ee()->db->table_exists('jcogs_img_cache_log')) {
            $this->log_debug('No legacy cache log table found');
            return;
        }

        // Get legacy cache log entries in batches to avoid memory issues
        $batch_size = 1000;
        $offset = 0;
        $total_migrated = 0;

        do {
            $legacy_entries = ee()->db->limit($batch_size, $offset)
                ->get('jcogs_img_cache_log')
                ->result_array();

            if (empty($legacy_entries)) {
                break;
            }

            $pro_entries = [];
            foreach ($legacy_entries as $legacy_entry) {
                // Update cache path to use Pro directory structure
                $legacy_path = $legacy_entry['path'] ?? '';
                $updated_path = $this->update_cache_path_for_pro($legacy_path);
                
                // Map legacy fields to Pro fields
                $pro_entries[] = [
                    'site_id' => $legacy_entry['site_id'] ?? 1,
                    'adapter_name' => 'legacy_' . ($legacy_entry['adapter_name'] ?? 'local'), // Construct Pro adapter_name
                    'adapter_type' => $legacy_entry['adapter_name'] ?? 'local', // Legacy adapter_name becomes Pro adapter_type
                    'path' => $updated_path, // Use updated Pro cache path
                    'image_name' => $legacy_entry['image_name'] ?? '',
                    'stats' => $legacy_entry['stats'] ?? null,
                    'values' => $legacy_entry['values'] ?? null
                ];
            }

            // Insert batch into Pro cache log table
            if (!empty($pro_entries)) {
                ee()->db->insert_batch('jcogs_img_pro_cache_log', $pro_entries);
                $total_migrated += count($pro_entries);
            }

            $offset += $batch_size;

        } while (count($legacy_entries) === $batch_size);

        if ($total_migrated > 0) {
            $this->log_debug("Migrated {$total_migrated} cache log entries from legacy");
        }
    }

    /**
     * Update legacy cache paths to use Pro directory structure
     * 
     * @param string $legacy_path The legacy cache directory path
     * @return string The updated Pro cache directory path
     */
    private function update_cache_path_for_pro(string $legacy_path): string
    {
        // If the path contains 'jcogs_img' but not 'jcogs_img_pro', update it
        if (str_contains($legacy_path, 'jcogs_img') && !str_contains($legacy_path, 'jcogs_img_pro')) {
            // Replace 'jcogs_img' with 'jcogs_img_pro'
            $updated_path = str_replace('jcogs_img', 'jcogs_img_pro', $legacy_path);
            $this->log_debug("Updated cache path from '{$legacy_path}' to '{$updated_path}'");
            return $updated_path;
        }
        
        // If path doesn't contain jcogs_img references, ensure it uses Pro structure
        if (!str_contains($legacy_path, 'jcogs_img_pro')) {
            // Default to Pro cache structure
            $default_path = 'images/jcogs_img_pro/cache';
            $this->log_debug("Replaced non-jcogs cache path '{$legacy_path}' with Pro default '{$default_path}'");
            return $default_path;
        }
        
        // Path already uses Pro structure
        return $legacy_path;
    }

    /**
     * Initialize settings with defaults
     * 
     * @return void
     */
    private function initialize_settings(): void
    {
        try {
            // Initialize settings service and save defaults
            ee('jcogs_img_pro:Settings')->save_settings();
            $this->log_debug('Settings initialized with defaults');
        } catch (\Throwable $e) {
            $this->log_debug('Error initializing settings: ' . $e->getMessage());
        }
    }

    /**
     * Clear various EE caches for safety
     * 
     * @return void
     */
    private function clear_caches(): void
    {
        // Clear OPcache if available
        if (function_exists('opcache_reset')) {
            $opcache_cleared = opcache_reset();
            
            if ($opcache_cleared) {
                $this->log_debug('OPcache reset successfully.');
            } else {
                $this->log_debug('OPcache reset attempted but failed (possibly restricted).');
            }
        }

        // Clear EE cache
        ee()->cache->delete('/jcogs_img_pro/');
    }

    /**
     * Update cache performance settings during upgrade
     * 
     * @return void
     */
    private function update_cache_performance_settings(): void
    {
        try {
            // Update current cache count for performance optimization
            $current_count = ee('jcogs_img_pro:ImageUtilities')->get_current_cache_log_count();
            $settings = ee('jcogs_img_pro:Settings');
            $settings::$settings['img_cp_cache_log_current_count'] = $current_count;
            $settings::$settings['img_cp_cache_log_count_last_updated'] = time();
            
            if ($current_count > 10000) {
                // If the current count is greater than 10,000, disable the automatic cache audit
                $settings::$settings['img_cp_enable_cache_audit'] = 'n';
                $this->log_debug('Disabled cache audit due to high cache count (' . number_format($current_count) . ')');
            }
            
            $settings->save_settings();
            $this->log_debug('Cache performance settings updated');
        } catch (\Throwable $e) {
            $this->log_debug('Error updating cache performance settings: ' . $e->getMessage());
        }
    }
}
