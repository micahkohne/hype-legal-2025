<?php

/**
 * JCOGS Image Pro - Settings Interface
 * ====================================
 * Pro settings abstraction layer for clean service architecture
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

namespace JCOGSDesign\JCOGSImagePro\Contracts;

/**
 * Settings Interface for JCOGS Image Pro
 * 
 * Provides abstraction layer for settings management that allows
 */
interface SettingsInterface 
{
    /**
     * Get a setting value
     *
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @return mixed Setting value
     */
    public function get(string $key, mixed $default = null): mixed;
    
    /**
     * Set a setting value
     *
     * @param string $key Setting key  
     * @param mixed $value Setting value
     * @return void
     */
    public function set(string $key, mixed $value): void;
    
    /**
     * Get filesystem adapter configuration
     *
     * @param string $adapter_name Adapter name (local, s3, r2, etc.)
     * @return array Adapter configuration array
     */
    public function getFilesystemConfig(string $adapter_name): array;
    
    /**
     * Check if a setting exists
     *
     * @param string $key Setting key
     * @return bool True if setting exists
     */
    public function has(string $key): bool;
    
    /**
     * Get all settings (filtered by prefix if provided)
     *
     * @param string|null $prefix Optional prefix filter
     * @return array All settings or filtered by prefix
     */
    public function all(?string $prefix = null): array;
    
    /**
     * Remove a setting
     *
     * @param string $key Setting key
     * @return void
     */
    public function remove(string $key): void;
    
    /**
     * Get the default named connection
     * 
     * @param bool $decrypt_sensitive Whether to decrypt sensitive values
     * @return array|null Default connection configuration or null if none set
     */
    public function getDefaultConnection(bool $decrypt_sensitive = false): ?array;
    
    /**
     * Get a specific named connection
     * 
     * @param string $connection_name Name of connection to retrieve
     * @param bool $decrypt_sensitive Whether to decrypt sensitive values
     * @return array|null Connection configuration or null if not found
     */
    public function getNamedConnection(string $connection_name, bool $decrypt_sensitive = false): ?array;

    /**
     * Get all named filesystem adapters
     * 
     * @return array All named connections with decrypted configuration
     */
    public function getNamedFilesystemAdapters(): array;

    /**
     * Get the default connection name
     * 
     * @return string Default connection name (empty string if none set)
     */
    public function get_default_connection_name(): string;
}
