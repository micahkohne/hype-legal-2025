<?php

/**
 * JCOGS Image Pro - Settings Service
 * ===================================
 * Pro Settings management with native EE7 database integration
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

namespace JCOGSDesign\JCOGSImagePro\Service;

use JCOGSDesign\JCOGSImagePro\Contracts\SettingsInterface;
use JCOGSDesign\JCOGSImagePro\Service\NamedAdapterConfig;
use Exception;

class Settings implements SettingsInterface
{
    public static $pro_settings; // Renamed to avoid collision with legacy
    private array $sensitive_keys = array(
        'jcogs_license_key',
        'jcogs_license_key_email'
    );

    public function __construct()
    {
        if (empty(static::$pro_settings)) {
            static::$pro_settings = $this->get_settings();
        }
    }

    /**
     * Add a new named connection
     * 
     * @param array $connection_config Connection configuration array
     * @return bool Success status
     */
    public function addNamedConnection(array $connection_config): bool
    {
        // Validate the connection configuration
        $validation = NamedAdapterConfig::validateConnectionConfig($connection_config);
        if (!$validation['success']) {
            return false;
        }
        
        $config = $this->getNamedFilesystemAdapters();
        $connection_name = $connection_config['name'];
        
        // Check if connection already exists
        if (isset($config['connections'][$connection_name])) {
            return false; // Connection name already exists
        }
        
        // Encrypt sensitive values before storing
        $connection_config['config'] = NamedAdapterConfig::encryptSensitiveConfig(
            $connection_config['type'], 
            $connection_config['config']
        );
        
        // Add the connection
        $config['connections'][$connection_name] = $connection_config;
        
        return $this->setNamedFilesystemAdapters($config);
    }

    /**
     * Get all settings or settings with a prefix
     * 
     * @param string|null $prefix Optional prefix to filter settings
     * @return array Settings array
     */
    public function all(string|null $prefix = null): array
    {
        if ($prefix === null) {
            return static::$pro_settings;
        }

        $filtered = [];
        foreach (static::$pro_settings as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Get a specific setting value
     * 
     * @param string $key Setting key
     * @param mixed $default Default value if setting not found
     * @return mixed Setting value or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return static::$pro_settings[$key] ?? $default;
    }
    
    /**
     * Get current filesystem adapter name
     * 
     * Used by cache management to identify which adapter is in use
     * for database cache log entries.
     * 
     * @return string Adapter name (local, s3, r2, dospaces)
     */
    public function get_adapter_name(): string
    {
        return static::$pro_settings['img_cp_flysystem_adapter'] ?? 'local';
    }

    /**
     * Get all settings
     * 
     * @return array All settings
     */
    public function get_all(): array
    {
        return static::$pro_settings;
    }

    /**
     * Get the default connection name
     * 
     * @return string Default connection name (empty string if none set)
     */
    public function get_default_connection_name(): string
    {
        $config = $this->getNamedFilesystemAdapters();
        return $config['default_connection'] ?? '';
    }

    /**
     * Load settings from database and merge with defaults
     * 
     * @return array Complete settings array
     */
    public function get_settings(): array
    {
        if (static::$pro_settings) {
            return static::$pro_settings;
        }

        static::$pro_settings = array();
        
        // Load from Pro-specific database table (migrated from Legacy approach)
        $query = ee()->db->get_where('jcogs_img_pro_settings', array('site_id' => ee()->config->item('site_id')));
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                static::$pro_settings[$row["setting_name"]] = $row["value"];
            }
            static::$pro_settings = array_merge($this->_default_settings(), static::$pro_settings);
        } else {
            static::$pro_settings = $this->_default_settings();
        }
        
        // Decrypt sensitive values if they appear to be encrypted
        $this->_decrypt_sensitive_settings();

        // See if we have any config.php over-rides set for control settings
        foreach(static::$pro_settings as $param => $value) {
            if (str_starts_with(haystack: $param, needle: 'img_cp' )) {
                static::$pro_settings[$param] = ee()->config->item('jcogs_img_pro_' . str_ireplace(search: 'img_cp_', replace: '', subject: $param)) ?: $value;
            }
        }

        return static::$pro_settings;
    }

    /**
     * Get all named connections
     * 
     * @param bool $decrypt_sensitive Whether to decrypt sensitive values
     * @return array Array of all named connections
     */
    public function getAllNamedConnections(bool $decrypt_sensitive = false): array
    {
        $config = $this->getNamedFilesystemAdapters();
        $connections = $config['connections'];
        
        if ($decrypt_sensitive) {
            foreach ($connections as &$connection) {
                $connection['config'] = NamedAdapterConfig::decryptSensitiveConfig(
                    $connection['type'],
                    $connection['config']
                );
            }
        }
        
        return $connections;
    }

    /**
     * Get the default named connection
     * 
     * @param bool $decrypt_sensitive Whether to decrypt sensitive values
     * @return array|null Default connection configuration or null if none set
     */
    public function getDefaultConnection(bool $decrypt_sensitive = false): ?array
    {
        $config = $this->getNamedFilesystemAdapters();
        
        if (empty($config['default_connection'])) {
            return null;
        }
        
        $connection_name = $config['default_connection'];
        
        // Get the connection directly from the config we already have (avoid redundant call)
        if (!isset($config['connections'][$connection_name])) {
            return null;
        }
        
        $connection = $config['connections'][$connection_name];
        
        if ($decrypt_sensitive) {
            $connection['config'] = NamedAdapterConfig::decryptSensitiveConfig(
                $connection['type'],
                $connection['config']
            );
        }
        
        return $connection;
    }

    /**
     * Get filesystem adapter configuration
     * 
     * @param string $adapter_name Name of the filesystem adapter
     * @return array Adapter configuration
     */
    public function getFilesystemConfig(string $adapter_name): array
    {
        // Map adapter names to config keys
        $config_mappings = [
            's3' => [
                'type' => 's3',
                'key' => static::$pro_settings['img_cp_flysystem_adapter_s3_key'] ?? '',
                'secret' => static::$pro_settings['img_cp_flysystem_adapter_s3_secret_actual'] ?? '',
                'region' => static::$pro_settings['img_cp_flysystem_adapter_s3_region'] ?? '',
                'bucket' => static::$pro_settings['img_cp_flysystem_adapter_s3_bucket'] ?? '',
                'server_path' => static::$pro_settings['img_cp_flysystem_adapter_s3_server_path'] ?? '',
                'url' => static::$pro_settings['img_cp_flysystem_adapter_s3_url'] ?? '',
            ],
            'r2' => [
                'type' => 'r2',
                'account_id' => static::$pro_settings['img_cp_flysystem_adapter_r2_account_id'] ?? '',
                'key' => static::$pro_settings['img_cp_flysystem_adapter_r2_key'] ?? '',
                'secret' => static::$pro_settings['img_cp_flysystem_adapter_r2_secret_actual'] ?? '',
                'bucket' => static::$pro_settings['img_cp_flysystem_adapter_r2_bucket'] ?? '',
                'server_path' => static::$pro_settings['img_cp_flysystem_adapter_r2_server_path'] ?? '',
                'url' => static::$pro_settings['img_cp_flysystem_adapter_r2_url'] ?? '',
            ],
            'dospaces' => [
                'type' => 'dospaces',
                'key' => static::$pro_settings['img_cp_flysystem_adapter_dospaces_key'] ?? '',
                'secret' => static::$pro_settings['img_cp_flysystem_adapter_dospaces_secret_actual'] ?? '',
                'region' => static::$pro_settings['img_cp_flysystem_adapter_dospaces_region'] ?? '',
                'space' => static::$pro_settings['img_cp_flysystem_adapter_dospaces_space'] ?? '',
                'server_path' => static::$pro_settings['img_cp_flysystem_adapter_dospaces_server_path'] ?? '',
                'url' => static::$pro_settings['img_cp_flysystem_adapter_dospaces_url'] ?? '',
            ],
            'local' => [
                'type' => 'local',
                'root' => ee()->config->item('base_path') ?? FCPATH,
                'cache_directory' => static::$pro_settings['img_cp_default_cache_directory'] ?? 'images/jcogs_img_pro/cache',
                'path_prefix' => static::$pro_settings['img_cp_path_prefix'] ?? '',
                'url' => ee()->config->item('site_url'),
            ],
        ];

        return $config_mappings[$adapter_name] ?? [];
    }

    /**
     * Get a specific named connection
     * 
     * @param string $connection_name Name of connection to retrieve
     * @param bool $decrypt_sensitive Whether to decrypt sensitive values
     * @return array|null Connection configuration or null if not found
     */
    public function getNamedConnection(string $connection_name, bool $decrypt_sensitive = false): ?array
    {
        $config = $this->getNamedFilesystemAdapters();
        
        if (!isset($config['connections'][$connection_name])) {
            return null;
        }
        
        $connection = $config['connections'][$connection_name];
        
        if ($decrypt_sensitive) {
            $connection['config'] = NamedAdapterConfig::decryptSensitiveConfig(
                $connection['type'],
                $connection['config']
            );
        }
        
        return $connection;
    }

    /**
     * Get named filesystem adapters configuration
     * 
     * @return array Decoded named adapters configuration
     */
    public function getNamedFilesystemAdapters(): array
    {
        $json_string = $this->get('img_cp_named_filesystem_adapters', '{"connections":{},"default_connection":""}');
        
        $config = json_decode($json_string, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // If JSON is invalid, return default structure
            return [
                'connections' => [],
                'default_connection' => ''
            ];
        }
        
        return $config;
    }

    /**
     * Get only valid named connections
     * 
     * @param bool $decrypt_sensitive Whether to decrypt sensitive values
     * @return array Array of valid named connections
     */
    public function getValidNamedConnections(bool $decrypt_sensitive = false): array
    {
        $all_connections = $this->getAllNamedConnections($decrypt_sensitive);
        
        return array_filter($all_connections, function($connection) {
            return $connection['is_valid'] ?? false;
        });
    }

    /**
     * Check if a setting exists
     * 
     * @param string $key Setting key
     * @return bool True if setting exists
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, static::$pro_settings);
    }

    /**
     * Check if legacy DO Spaces configuration exists
     * 
     * @param array $settings Current settings
     * @return bool True if DO Spaces settings are configured
     */
    private function hasLegacyDoSpacesConfiguration(array $settings): bool
    {
        return !empty($settings['img_cp_flysystem_adapter_dospaces_key'] ?? '')
            && !empty($settings['img_cp_flysystem_adapter_dospaces_secret'] ?? '')
            && !empty($settings['img_cp_flysystem_adapter_dospaces_space'] ?? '');
    }

    /**
     * Check if legacy migration has already been performed
     * 
     * @return bool True if legacy connections already exist
     */
    public function hasLegacyMigrationBeenPerformed(): bool
    {
        $named_config = $this->getNamedFilesystemAdapters();
        
        // Check if any legacy_ prefixed connections exist
        foreach ($named_config['connections'] as $name => $connection) {
            if (str_starts_with($name, 'legacy_')) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if legacy R2 configuration exists
     * 
     * @param array $settings Current settings
     * @return bool True if R2 settings are configured
     */
    private function hasLegacyR2Configuration(array $settings): bool
    {
        return !empty($settings['img_cp_flysystem_adapter_r2_account_id'] ?? '')
            && !empty($settings['img_cp_flysystem_adapter_r2_key'] ?? '')
            && !empty($settings['img_cp_flysystem_adapter_r2_secret'] ?? '')
            && !empty($settings['img_cp_flysystem_adapter_r2_bucket'] ?? '');
    }

    /**
     * Check if legacy S3 configuration exists
     * 
     * @param array $settings Current settings
     * @return bool True if S3 settings are configured
     */
    private function hasLegacyS3Configuration(array $settings): bool
    {
        return !empty($settings['img_cp_flysystem_adapter_s3_key'] ?? '') 
            && !empty($settings['img_cp_flysystem_adapter_s3_secret'] ?? '')
            && !empty($settings['img_cp_flysystem_adapter_s3_bucket'] ?? '');
    }

    /**
     * Migrate legacy adapter configurations to named connections
     * 
     * This method converts existing flysystem adapter settings to the new named
     * connection format, preserving all configurations with legacy_ prefixes.
     * 
     * @return array Migration result with success status and details
     */
    public function migrateLegacyAdaptersToNamedConnections(): array
    {
        $migration_result = [
            'success' => false,
            'migrated_connections' => [],
            'errors' => [],
            'warnings' => []
        ];

        try {
            // Get current legacy settings
            $current_settings = $this->get_settings();
            $current_adapter = $current_settings['img_cp_flysystem_adapter'] ?? 'local';
            
            // Get current named connections (if any)
            $named_config = $this->getNamedFilesystemAdapters();
            
            // Track what we migrate
            $migrated_count = 0;
            
            // Always create a legacy local connection 
            if ($this->migrateLegacyLocalAdapter($current_settings, $named_config, $current_adapter === 'local')) {
                $migration_result['migrated_connections'][] = 'legacy_local';
                $migrated_count++;
            }
            
            // Migrate S3 if configured
            if ($this->hasLegacyS3Configuration($current_settings)) {
                if ($this->migrateLegacyS3Adapter($current_settings, $named_config, $current_adapter === 's3')) {
                    $migration_result['migrated_connections'][] = 'legacy_s3';
                    $migrated_count++;
                } else {
                    $migration_result['errors'][] = 'Failed to migrate S3 configuration';
                }
            }
            
            // Migrate R2 if configured  
            if ($this->hasLegacyR2Configuration($current_settings)) {
                if ($this->migrateLegacyR2Adapter($current_settings, $named_config, $current_adapter === 'r2')) {
                    $migration_result['migrated_connections'][] = 'legacy_r2';
                    $migrated_count++;
                } else {
                    $migration_result['errors'][] = 'Failed to migrate R2 configuration';
                }
            }
            
            // Migrate DO Spaces if configured
            if ($this->hasLegacyDoSpacesConfiguration($current_settings)) {
                if ($this->migrateLegacyDoSpacesAdapter($current_settings, $named_config, $current_adapter === 'dospaces')) {
                    $migration_result['migrated_connections'][] = 'legacy_dospaces';
                    $migrated_count++;
                } else {
                    $migration_result['errors'][] = 'Failed to migrate DO Spaces configuration';
                }
            }
            
            // Save the updated named configuration
            if ($migrated_count > 0) {
                if ($this->setNamedFilesystemAdapters($named_config)) {
                    $migration_result['success'] = true;
                    $migration_result['warnings'][] = "Migrated {$migrated_count} legacy adapter configurations. Original settings preserved.";
                } else {
                    $migration_result['errors'][] = 'Failed to save migrated configurations';
                }
            } else {
                $migration_result['warnings'][] = 'No legacy configurations found to migrate';
                $migration_result['success'] = true; // Not an error condition
            }
            
        } catch (Exception $e) {
            $migration_result['errors'][] = 'Migration failed: ' . $e->getMessage();
        }
        
        return $migration_result;
    }

    /**
     * Migrate legacy DO Spaces adapter configuration
     * 
     * @param array $settings Current settings
     * @param array &$named_config Named config to update
     * @param bool $is_current_default Whether this is the current default adapter
     * @return bool Success status
     */
    private function migrateLegacyDoSpacesAdapter(array $settings, array &$named_config, bool $is_current_default): bool
    {
        $connection_config = [
            'name' => 'legacy_dospaces',
            'type' => 'dospaces',
            'is_valid' => ($settings['img_cp_flysystem_adapter_dospaces_is_valid'] ?? 'false') === 'true',
            'is_legacy' => true,
            'config' => [
                'key' => $settings['img_cp_flysystem_adapter_dospaces_key'] ?? '',
                'secret' => $settings['img_cp_flysystem_adapter_dospaces_secret_actual'] ?? $settings['img_cp_flysystem_adapter_dospaces_secret'] ?? '',
                'region' => $settings['img_cp_flysystem_adapter_dospaces_region'] ?? '',
                'space' => $settings['img_cp_flysystem_adapter_dospaces_space'] ?? '',
                'server_path' => $settings['img_cp_flysystem_adapter_dospaces_server_path'] ?? '',
                'url' => $settings['img_cp_flysystem_adapter_dospaces_url'] ?? ''
            ]
        ];
        
        // Validate the connection
        $validation = NamedAdapterConfig::validateConnectionConfig($connection_config);
        if (!$validation['success']) {
            return false;
        }
        
        // Encrypt sensitive values before storing  
        $connection_config['config'] = NamedAdapterConfig::encryptSensitiveConfig('dospaces', $connection_config['config']);
        
        // Add to named config
        $named_config['connections']['legacy_dospaces'] = $connection_config;
        
        // Set as default if this was the current adapter
        if ($is_current_default) {
            $named_config['default_connection'] = 'legacy_dospaces';
        }
        
        return true;
    }

    /**
     * Migrate legacy local adapter configuration
     * 
     * @param array $settings Current settings
     * @param array &$named_config Named config to update  
     * @param bool $is_current_default Whether this is the current default adapter
     * @return bool Success status
     */
    private function migrateLegacyLocalAdapter(array $settings, array &$named_config, bool $is_current_default): bool
    {
        $connection_config = [
            'name' => 'legacy_local',
            'type' => 'local', 
            'is_valid' => true, // Local is always valid
            'is_legacy' => true,
            'config' => [
                'cache_directory' => $settings['img_cp_default_cache_directory'] ?? 'images/jcogs_img_pro/cache',
                'path_prefix' => $settings['img_cp_path_prefix'] ?? ''
            ]
        ];
        
        // Validate the connection
        $validation = NamedAdapterConfig::validateConnectionConfig($connection_config);
        if (!$validation['success']) {
            return false;
        }
        
        // Add to named config
        $named_config['connections']['legacy_local'] = $connection_config;
        
        // Set as default if this was the current adapter
        if ($is_current_default) {
            $named_config['default_connection'] = 'legacy_local';
        }
        
        return true;
    }

    /**
     * Migrate legacy R2 adapter configuration
     * 
     * @param array $settings Current settings
     * @param array &$named_config Named config to update
     * @param bool $is_current_default Whether this is the current default adapter
     * @return bool Success status
     */
    private function migrateLegacyR2Adapter(array $settings, array &$named_config, bool $is_current_default): bool
    {
        $connection_config = [
            'name' => 'legacy_r2',
            'type' => 'r2',
            'is_valid' => ($settings['img_cp_flysystem_adapter_r2_is_valid'] ?? 'false') === 'true',
            'is_legacy' => true,
            'config' => [
                'account_id' => $settings['img_cp_flysystem_adapter_r2_account_id'] ?? '',
                'key' => $settings['img_cp_flysystem_adapter_r2_key'] ?? '',
                'secret' => $settings['img_cp_flysystem_adapter_r2_secret_actual'] ?? $settings['img_cp_flysystem_adapter_r2_secret'] ?? '',
                'bucket' => $settings['img_cp_flysystem_adapter_r2_bucket'] ?? '',
                'server_path' => $settings['img_cp_flysystem_adapter_r2_server_path'] ?? '',
                'url' => $settings['img_cp_flysystem_adapter_r2_url'] ?? ''
            ]
        ];
        
        // Validate the connection
        $validation = NamedAdapterConfig::validateConnectionConfig($connection_config);
        if (!$validation['success']) {
            return false;
        }
        
        // Encrypt sensitive values before storing
        $connection_config['config'] = NamedAdapterConfig::encryptSensitiveConfig('r2', $connection_config['config']);
        
        // Add to named config
        $named_config['connections']['legacy_r2'] = $connection_config;
        
        // Set as default if this was the current adapter
        if ($is_current_default) {
            $named_config['default_connection'] = 'legacy_r2';
        }
        
        return true;
    }

    /**
     * Migrate legacy S3 adapter configuration
     * 
     * @param array $settings Current settings
     * @param array &$named_config Named config to update
     * @param bool $is_current_default Whether this is the current default adapter
     * @return bool Success status
     */
    private function migrateLegacyS3Adapter(array $settings, array &$named_config, bool $is_current_default): bool
    {
        $connection_config = [
            'name' => 'legacy_s3',
            'type' => 's3',
            'is_valid' => ($settings['img_cp_flysystem_adapter_s3_is_valid'] ?? 'false') === 'true',
            'is_legacy' => true,
            'config' => [
                'key' => $settings['img_cp_flysystem_adapter_s3_key'] ?? '',
                'secret' => $settings['img_cp_flysystem_adapter_s3_secret_actual'] ?? $settings['img_cp_flysystem_adapter_s3_secret'] ?? '',
                'region' => $settings['img_cp_flysystem_adapter_s3_region'] ?? '',
                'bucket' => $settings['img_cp_flysystem_adapter_s3_bucket'] ?? '',
                'server_path' => $settings['img_cp_flysystem_adapter_s3_server_path'] ?? '',
                'url' => $settings['img_cp_flysystem_adapter_s3_url'] ?? ''
            ]
        ];
        
        // Validate the connection
        $validation = NamedAdapterConfig::validateConnectionConfig($connection_config);
        if (!$validation['success']) {
            return false;
        }
        
        // Encrypt sensitive values before storing
        $connection_config['config'] = NamedAdapterConfig::encryptSensitiveConfig('s3', $connection_config['config']);
        
        // Add to named config
        $named_config['connections']['legacy_s3'] = $connection_config;
        
        // Set as default if this was the current adapter
        if ($is_current_default) {
            $named_config['default_connection'] = 'legacy_s3';
        }
        
        return true;
    }

    /**
     * Remove a setting
     * 
     * @param string $key Setting key to remove
     * @return void
     */
    public function remove(string $key): void
    {
        unset(static::$pro_settings[$key]);
    }

    /**
     * Remove a named connection
     * 
     * @param string $connection_name Name of connection to remove
     * @return bool Success status
     */
    public function removeNamedConnection(string $connection_name): bool
    {
        $config = $this->getNamedFilesystemAdapters();
        
        // Check if connection exists
        if (!isset($config['connections'][$connection_name])) {
            return false; // Connection doesn't exist
        }
        
        $connection = $config['connections'][$connection_name];
        
        // Prevent deletion of the last local connection
        if ($connection['type'] === 'local') {
            $local_connections = array_filter($config['connections'], function($conn) {
                return $conn['type'] === 'local';
            });
            
            if (count($local_connections) <= 1) {
                return false; // Cannot delete the last local connection
            }
        }
        
        // Remove the connection
        unset($config['connections'][$connection_name]);
        
        // If this was the default connection, clear the default or set to first local
        if ($config['default_connection'] === $connection_name) {
            $config['default_connection'] = '';
            
            // Try to set default to first valid local connection
            foreach ($config['connections'] as $name => $conn) {
                if ($conn['type'] === 'local' && ($conn['is_valid'] ?? false)) {
                    $config['default_connection'] = $name;
                    break;
                }
            }
        }
        
        return $this->setNamedFilesystemAdapters($config);
    }

    /**
     * Save settings to database (migrated from Legacy approach)
     * 
     * @param array $settings Settings to save
     * @return bool Success status
     */
    public function save_settings($settings = array()): bool
    {
        // New settings are the merger of current settings and inbound settings
        $new_settings = array_merge(static::$pro_settings, $settings);
        
        // Encrypt sensitive values
        foreach ($this->sensitive_keys as $key) {
            if (isset($new_settings[$key]) && !empty($new_settings[$key]) && $new_settings[$key] !== $this->_default_settings()[$key]) {
                // Add a prefix to mark the value as encrypted
                $new_settings[$key] = 'enc_' . ee('Encrypt')->encode(($new_settings[$key]));
            }
        }
        
        // Clear the licensing server action table in case we've changed domain
        ee()->cache->delete('/jcogs_img_pro/licensing_action_ids');

        // Get what is in data table - set to array if nothing there
        $data_in_table = [];
        $query = ee()->db->get_where('jcogs_img_pro_settings', array('site_id' => ee()->config->item('site_id')));
        if ($query->num_rows) {
            $query_results = $query->result_array();
            foreach ($query_results as $row) {
                $data_in_table[$row['setting_name']] = $row['value'];
            }
        }

        // Loop through the new settings and see if we have same thing saved.
        foreach ($new_settings as $key => $value) {
            // Work out what is in data table and update to new values as required
            if (!isset($data_in_table[$key])) {
                // Value is not in table so add it to the table
                ee()->db->insert('jcogs_img_pro_settings', array('site_id' => ee()->config->item('site_id'), 'setting_name' => $key, 'value' => $value));
            } elseif ($data_in_table[$key] != $value) {
                // There is something in datatable, but update it as new value is different
                ee()->db->update('jcogs_img_pro_settings', array('value' => $value), array('site_id' => ee()->config->item('site_id'), 'setting_name' => $key));
            }
        }
        // Update static value with new settings
        static::$pro_settings = $new_settings;
        
        return true;
    }

    /**
     * Set a setting value
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        static::$pro_settings[$key] = $value;
    }

    /**
     * Set the default named connection
     * 
     * @param string $connection_name Name of connection to set as default
     * @return bool Success status
     */
    public function setDefaultConnection(string $connection_name): bool
    {
        $config = $this->getNamedFilesystemAdapters();
        
        // Check if connection exists and is valid
        if (!isset($config['connections'][$connection_name])) {
            return false; // Connection doesn't exist
        }
        
        $connection = $config['connections'][$connection_name];
        if (!($connection['is_valid'] ?? false)) {
            return false; // Connection is not valid
        }
        
        // Set as default
        $config['default_connection'] = $connection_name;
        
        return $this->setNamedFilesystemAdapters($config);
    }

    /**
     * Set named filesystem adapters configuration
     * 
     * @param array $config Named adapters configuration array
     * @return bool Success status
     */
    public function setNamedFilesystemAdapters(array $config): bool
    {
        if (!$this->validateNamedAdaptersStructure($config)) {
            return false;
        }
        
        $json_string = json_encode($config, JSON_UNESCAPED_SLASHES);
        if ($json_string === false) {
            return false;
        }
        
        $this->set('img_cp_named_filesystem_adapters', $json_string);
        return $this->save_settings();
    }

    /**
     * Test named adapters JSON storage and retrieval
     * 
     * @return bool Test success status
     */
    public function testNamedAdaptersStorage(): bool
    {
        $test_config = [
            'connections' => [
                'test_local' => [
                    'name' => 'test_local',
                    'type' => 'local',
                    'is_valid' => true,
                    'is_legacy' => false,
                    'config' => [
                        'cache_directory' => 'images/test_cache',
                        'path_prefix' => ''
                    ]
                ]
            ],
            'default_connection' => 'test_local'
        ];
        
        // Test setting the configuration
        if (!$this->setNamedFilesystemAdapters($test_config)) {
            return false;
        }
        
        // Test retrieving the configuration
        $retrieved_config = $this->getNamedFilesystemAdapters();
        
        // Verify the retrieved config matches what we set
        if ($retrieved_config['default_connection'] !== 'test_local') {
            return false;
        }
        
        if (!isset($retrieved_config['connections']['test_local'])) {
            return false;
        }
        
        $test_connection = $retrieved_config['connections']['test_local'];
        if ($test_connection['name'] !== 'test_local' || 
            $test_connection['type'] !== 'local' ||
            $test_connection['is_valid'] !== true) {
            return false;
        }
        
        // Clean up - reset to default
        $this->setNamedFilesystemAdapters([
            'connections' => [],
            'default_connection' => ''
        ]);
        
        return true;
    }

    /**
     * Test a named connection
     * 
     * @param string $connection_name Name of connection to test
     * @return array Test result with success status and details
     */
    public function testNamedConnection(string $connection_name): array
    {
        $connection = $this->getNamedConnection($connection_name, true); // Get with decrypted values
        
        if ($connection === null) {
            return [
                'success' => false,
                'error' => 'Connection not found',
                'details' => ['Connection name: ' . $connection_name]
            ];
        }
        
        $test_result = NamedAdapterConfig::testConnection($connection['type'], $connection['config']);
        
        // Update the connection's validity based on test result
        $this->updateConnectionValidity($connection_name, $test_result['success']);
        
        return $test_result;
    }

    /**
     * Update an existing named connection
     * 
     * @param string $connection_name Name of connection to update
     * @param array $connection_config Updated connection configuration
     * @return bool Success status
     */
    public function updateNamedConnection(string $connection_name, array $connection_config): bool
    {
        // Validate the connection configuration
        $validation = NamedAdapterConfig::validateConnectionConfig($connection_config);
        if (!$validation['success']) {
            return false;
        }
        
        $config = $this->getNamedFilesystemAdapters();
        
        // Check if connection exists
        if (!isset($config['connections'][$connection_name])) {
            return false; // Connection doesn't exist
        }
        
        // Encrypt sensitive values before storing
        $connection_config['config'] = NamedAdapterConfig::encryptSensitiveConfig(
            $connection_config['type'], 
            $connection_config['config']
        );
        
        // Update the connection
        $config['connections'][$connection_name] = $connection_config;
        
        // If we're updating the default connection and it's no longer valid, clear default
        if ($config['default_connection'] === $connection_name && !($connection_config['is_valid'] ?? false)) {
            $config['default_connection'] = '';
        }
        
        return $this->setNamedFilesystemAdapters($config);
    }

    /**
     * Update a connection's validity status
     * 
     * @param string $connection_name Name of connection to update
     * @param bool $is_valid Validity status
     * @return bool Success status
     */
    public function updateConnectionValidity(string $connection_name, bool $is_valid): bool
    {
        $config = $this->getNamedFilesystemAdapters();
        
        if (!isset($config['connections'][$connection_name])) {
            return false;
        }
        
        $config['connections'][$connection_name]['is_valid'] = $is_valid;
        
        // If connection became invalid and was default, clear default
        if (!$is_valid && $config['default_connection'] === $connection_name) {
            $config['default_connection'] = '';
        }
        
        return $this->setNamedFilesystemAdapters($config);
    }

    /**
     * Validate named adapters JSON structure
     * 
     * @param array $config Configuration array to validate
     * @return bool Validation result
     */
    public function validateNamedAdaptersStructure(array $config): bool
    {
        // Must have required top-level keys
        if (!isset($config['connections']) || !isset($config['default_connection'])) {
            return false;
        }
        
        // connections must be an array
        if (!is_array($config['connections'])) {
            return false;
        }
        
        // default_connection must be a string
        if (!is_string($config['default_connection'])) {
            return false;
        }
        
        // Validate each connection structure
        foreach ($config['connections'] as $name => $connection) {
            if (!$this->validateNamedConnectionStructure($connection)) {
                return false;
            }
        }
        
        // If default_connection is specified, it must exist in connections
        if (!empty($config['default_connection']) && !isset($config['connections'][$config['default_connection']])) {
            return false;
        }
        
        return true;
    }

    /**
     * Validate individual named connection structure
     * 
     * @param array $connection Connection configuration to validate
     * @return bool Validation result
     */
    public function validateNamedConnectionStructure(array $connection): bool
    {
        // Required fields
        $required_fields = ['type', 'is_valid', 'config'];
        
        foreach ($required_fields as $field) {
            if (!isset($connection[$field])) {
                return false;
            }
        }
        
        // Validate field types
        if (!in_array($connection['type'], ['local', 's3', 'r2', 'dospaces'])) {
            return false;
        }
        
        if (!is_bool($connection['is_valid'])) {
            return false;
        }
        
        if (!is_array($connection['config'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Returns the default settings for the JCOGS Image Pro add-on.
     * Pro-specific defaults with legacy adapter settings removed (Pro uses named connections)
     */
    private function _default_settings(): array
    {
        return array(
            'jcogs_add_on_class'                                => JCOGS_IMG_PRO_CLASS,
            'jcogs_add_on_name'                                 => JCOGS_IMG_PRO_NAME,
            'jcogs_add_on_version'                              => JCOGS_IMG_PRO_VERSION,
            'enable_img'                                        => 'y',
            'img_cp_speedy_escape'                              => 'n',
            'img_cp_action_links'                               => 'n',
            'img_cp_append_path_to_action_links'                => 'n',
            'img_cp_path_prefix'                                => '',
            'img_cp_default_cache_duration'                     => '2678400',
            'img_cp_default_cache_audit_after'                  => '604800',
            'img_cp_enable_debugging'                           => 'n',
            'img_cp_enable_browser_check'                       => 'n',
            'img_cp_default_filename_separator'                 => '__',
            'img_cp_enable_cache_audit'                         => 'n',
            'img_cp_cache_auto_manage'                          => 'n',
            'img_cp_default_max_source_filename_length'         => '100',
            'img_cp_include_source_in_filename_hash'            => 'y',
            'img_cp_ce_image_remote_dir'                        => 'made',
            'img_cp_default_max_image_size'                     => '25',
            'img_cp_default_min_php_ram'                        => '256',
            'img_cp_default_min_php_process_time'               => '30',
            'img_cp_default_php_remote_connect_time'            => '20',
            'img_cp_default_user_agent_string'                  => 'JCOGS Image Pro',
            'img_cp_default_image_format'                       => 'jpg',
            'img_cp_jpg_default_quality'                        => '85',
            'img_cp_png_default_quality'                        => '6',
            'img_cp_default_bg_color'                           => 'ffffff',
            'img_cp_enable_svg_passthrough'                     => 'y',
            'img_cp_default_img_width'                          => '800',
            'img_cp_default_img_height'                         => '600',
            'img_cp_allow_scale_larger_default'                 => 'n',
            'img_cp_class_consolidation_default'                => 'y',
            'img_cp_attribute_variable_expansion_default'       => 'y',
            'img_cp_class_always_output_full_urls'              => 'n',
            'img_cp_enable_auto_sharpen'                        => 'y',
            'img_cp_enable_lazy_loading'                        => 'n',
            'img_cp_lazy_loading_mode'                          => 'lqip',
            'img_cp_lazy_progressive_enhancement'               => 'n',
            'img_cp_default_preload_critical'                   => 'n',
            'img_cp_html_decoding_enabled'                      => 'y',
            'img_cp_enable_default_fallback_image'              => 'yl',
            'img_cp_fallback_image_colour'                      => 'dddddd',
            'img_cp_fallback_image_local'                       => '{file:121:url}',
            'img_cp_fallback_image_remote'                      => '',
            'img_cp_enable_auto_adjust'                         => 'n',
            'img_cp_default_max_image_dimension'                => '3000',
            'img_cp_ignore_save_type_for_animated_gifs'         => 'n',
            // Pro uses named connections - proper setting name and structure
            'img_cp_named_filesystem_adapters'                  => '{"connections":{},"default_connection":""}',
        );
    }

    /**
     * Decrypts sensitive settings values if they appear to be encrypted
     *
     * @return void
     */
    private function _decrypt_sensitive_settings(): void
    {
        foreach ($this->sensitive_keys as $key) {
            if (isset(static::$pro_settings[$key]) && !empty(static::$pro_settings[$key]) && str_starts_with(static::$pro_settings[$key], 'enc_')) {
                // Remove prefix and decrypt
                $encrypted_value = substr(static::$pro_settings[$key], 4); // Remove 'enc_'
                $decrypted = ee('Encrypt')->decode($encrypted_value);
                
                if ($decrypted !== false) {
                    static::$pro_settings[$key] = $decrypted;
                }
            }
        }
    }
}
