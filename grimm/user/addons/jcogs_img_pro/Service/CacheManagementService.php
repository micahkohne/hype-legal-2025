<?php

/**
 * JCOGS Image Pro - Cache Management Service
 * ===========================================
 * Comprehensive cache management with native EE7 cache service integration
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

use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

/**
 * Cache Management Service for JCOGS Image Pro
 * 
 * Provides comprehensive cache management functionality including:
 * - Cache auditing and cleanup
 * - Performance profiling and monitoring
 * - Orphaned entry management
 * - Cache statistics and reporting
 * - Batch cache operations for optimal performance
 * 
 * Migrated from CacheManagementTrait with improved architecture using direct service access.
 * Uses optimized service container resolution and enhanced error handling.
 * 
 * @package JCOGSImagePro\Service
 * @author JCOGS Design
 * @version 2.0.0 (Phase 2A Migration)
 * @since Pro 2.0.0
 */
class CacheManagementService
{
    /**
     * Direct service access - performance optimized
     * Following established pattern from other migrated services
     */
    private $settings;
    private $utilities_service;
    private $filesystem;
    
    /**
     * Cache configuration constants converted to static properties
     * Keeping original trait structure for compatibility
     */
    private static string $table_name = 'jcogs_img_pro_cache_log';
    private static array $performance_log = [];
    private static array $pending_cache_updates = [];
    private static bool $cache_update_scheduled = false;
    
    /**
     * Static cache index for in-memory caching
     * Following Legacy trait pattern for optimal performance
     */
    private static array $cache_log_index = [];
    
    /**
     * Track cache preload status per site/adapter combination
     * Prevents duplicate preloading within same request
     */
    private static array $preload_status = [];
    
    /**
     * Selective loading system for performance optimization
     * Matches Legacy CacheManagementTrait implementation
     */
    private static bool $use_selective_loading = false;
    private static bool $loading_strategy_determined = false;
    private static array $request_cache = [];
    private static array $db_query_cache = [];
    
    /**
     * NEW: Request-level cache for database operations
     * Prevents duplicate SELECT/DELETE/INSERT operations within same request
     */
    private static array $database_operation_cache = [];
    private static array $pending_database_operations = [];
    private static bool $batch_mode = false;
    

    
    /**
     * Constants for cache operations from Legacy trait
     */
    private static string $default_cache_dir = '.';
    private static int $initial_count = 1;
    private static int $initial_processing_time = 0;
    private static int $initial_size = 0;
    
    /**
     * Site context for Pro operations (stateless regarding connections)
     */
    private int $site_id;
    
    /**
     * Current adapter context for operations
     * Set by methods that need adapter-specific operations
     */
    private string $adapter_name;
    private string $adapter_type;
    
    /**
     * Initialize with direct service access for optimal performance
     * Following established pattern from ColourManagementService, ValidationService etc.
     * 
     * Stateless constructor - connection context is passed to methods that need it
     */
    public function __construct()
    {
        // Use shared service cache for optimal performance
        $this->settings = ServiceCache::settings();
        $this->utilities_service = ServiceCache::utilities();  
        $this->filesystem = ServiceCache::filesystem();
        
        // Initialize site context for Pro operations
        $this->site_id = ee()->config->item('site_id');
        
        // Initialize adapter context (will be set by individual methods)
        $this->adapter_name = '';
        $this->adapter_type = '';
    }
    
    /**
     * Destructor to handle cleanup
     * Preserving original trait cleanup behavior
     */
    public function __destruct()
    {
        // Guard against destructor loops during active processing
        static $destructor_running = false;
        
        if ($destructor_running) {
            return; // Prevent recursive destructor calls
        }
        
        $destructor_running = true;
        
        try {
            // Check if there's an incomplete transaction and complete it
            if (ee()->db && ee()->db->trans_status() !== false) {
                ee()->db->trans_complete();
            }
            
            // Flush any pending cache updates using enhanced batch system
            if (!empty(self::$pending_cache_updates)) {
                $this->_flush_cache_updates_batch();
            }
            
        } catch (\Throwable $e) {
            // Silently catch any destructor errors to prevent issues during shutdown
        } finally {
            $destructor_running = false;
        }
    }

    /**
     * Audit cache files for a specific adapter and cache location
     * 
     * Implements complete audit functionality matching legacy CacheManagementTrait:
     * 1. Reviews cache_log entries for freshness - removes expired entries and deletes files
     * 2. Checks files exist on disk - removes orphaned database entries
     * 3. Scans filesystem for orphaned files - deletes expired or adds fresh ones to cache_log
     * 
     * Enhanced to support multiple cache locations per adapter (following project rules):
     * - Uses filesystem adapters for all file operations (no direct glob)
     * - Accepts specific cache path rather than relying on default settings
     * - Supports both local and cloud filesystem adapters
     * - Updated for named connections: uses connection_name (adapter_name) and adapter_type
     * 
     * @param string $connection_name The named connection to audit (or legacy adapter name)
     * @param string|null $cache_path Specific cache path to audit (if null, uses default for adapter)
     * @return array Result array with enhanced audit statistics and cache path info
     */
    public function audit_cache_location(string $connection_name, ?string $cache_path = null): array
    {
        // Set adapter context for operations that need it
        $this->adapter_name = $connection_name;
        $this->adapter_type = $this->get_adapter_type($connection_name);
        
        $audit_results = [
            'files_found' => 0,
            'database_entries' => 0,
            'files_without_db_entries' => 0,
            'db_entries_without_files' => 0,
            'total_size' => 0,
            'files' => [],
            'orphaned_files' => [],
            'orphaned_db_entries' => [],
            // NEW: Track actual operations performed (matching Legacy)
            'files_removed' => 0,
            'entries_removed' => 0,
            'entries_added' => 0,
            'files_size_removed' => 0
        ];

        try {
            // Step 1: Get cache locations to audit (from database paths)
            $cache_locations = $this->_get_cache_locations_for_audit($cache_path);
            
            if (empty($cache_locations)) {
                $this->utilities_service->debug_log("JCOGS Image Pro: No cache locations found for audit");
                return [
                    'success' => true,
                    'adapter' => $connection_name,
                    'cache_path' => $cache_path ?? 'none',
                    'initial_stats' => ['files_count' => 0, 'total_size' => '0 B', 'db_entries' => 0],
                    'processed_stats' => ['files_removed' => 0, 'files_size_removed' => '0 B', 'entries_removed' => 0, 'entries_added' => 0],
                    'final_stats' => ['files_count' => 0, 'total_size' => '0 B', 'db_entries' => 0, 'orphaned_files' => 0],
                    'last_audit' => date('Y-m-d H:i:s')
                ];
            }

            // Step 2: Process each cache location
            foreach ($cache_locations as $cache_location) {
               
                // Step 3: Get files from filesystem using correct adapter
                $files_in_location = $this->_get_files_in_cache_location($cache_location, $connection_name);
                
                // Step 4: Get database entries for this location
                $db_entries = $this->_audit_database_entries_for_path($cache_location);
                
                // Step 5: Perform comprehensive audit operations (statistics + operations)
                $location_audit_results = $this->_perform_audit_operations_for_location(
                    $cache_location, 
                    $connection_name, 
                    $files_in_location, 
                    $db_entries
                );
                
                // Update audit results with both initial statistics and operations performed
                $audit_results['files_found'] += $location_audit_results['files_found'];
                $audit_results['database_entries'] += $location_audit_results['database_entries'];
                $audit_results['total_size'] += $location_audit_results['total_size'];
                $audit_results['files_without_db_entries'] += $location_audit_results['files_without_db_entries'];
                $audit_results['files_removed'] += $location_audit_results['files_removed'];
                $audit_results['entries_removed'] += $location_audit_results['entries_removed'];
                $audit_results['entries_added'] += $location_audit_results['entries_added'];
                $audit_results['files_size_removed'] += $location_audit_results['files_size_removed'];

            }

            return [
                'success' => true,
                'adapter' => $connection_name,
                'cache_path' => $cache_path ?? implode(', ', $cache_locations),
                'initial_stats' => [
                    'files_count' => $audit_results['files_found'],
                    'total_size' => $this->utilities_service->format_file_size($audit_results['total_size']),
                    'db_entries' => $audit_results['database_entries']
                ],
                'processed_stats' => [
                    'files_removed' => $audit_results['files_removed'],
                    'files_size_removed' => $this->utilities_service->format_file_size($audit_results['files_size_removed']),
                    'entries_removed' => $audit_results['entries_removed'],
                    'entries_added' => $audit_results['entries_added']
                ],
                'final_stats' => [
                    'files_count' => $audit_results['files_found'] - $audit_results['files_removed'],
                    'total_size' => $this->utilities_service->format_file_size($audit_results['total_size'] - $audit_results['files_size_removed']),
                    'db_entries' => $audit_results['database_entries'] - $audit_results['entries_removed'] + $audit_results['entries_added'],
                    'orphaned_files' => max(0, $audit_results['files_without_db_entries'] - $audit_results['entries_added'])
                ],
                'last_audit' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            
            ee('CP/Alert')->makeInline('cache-audit-error')
                ->asIssue()
                ->withTitle('Audit Error')
                ->addToBody('Error during cache audit: ' . $e->getMessage())
                ->defer();
                
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'adapter' => $connection_name,
                'cache_path' => $cache_path ?? 'unknown',
                'initial_stats' => ['files_count' => 0, 'total_size' => '0 B', 'db_entries' => 0],
                'processed_stats' => ['files_removed' => 0, 'files_size_removed' => '0 B', 'entries_removed' => 0, 'entries_added' => 0],
                'final_stats' => ['files_count' => 0, 'total_size' => '0 B', 'db_entries' => 0, 'orphaned_files' => 0]
            ];
        }
    }

    /**
     * Clear all caches across all adapters
     * 
     * @return array Result array with success status and files removed count
     */
    public function clear_all_caches(): array
    {
        try {
            $total_files_removed = 0;
            $connections_cleared = [];
            
            // Get all available named connections
            $connections = $this->filesystem->getAvailableAdapters();
            
            foreach ($connections as $connection_name) {
                $result = $this->clear_cache_location($connection_name);
                if ($result['success']) {
                    $total_files_removed += $result['files_removed'];
                    $connections_cleared[] = $connection_name;
                }
            }
            
            return [
                'success' => true,
                'files_removed' => $total_files_removed,
                'connections_cleared' => $connections_cleared
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'files_removed' => 0
            ];
        }
    }

    /**
     * Clear cache files from a specific adapter location
     * 
     * @param string $connection_name The named connection to clear cache from (or legacy adapter name)
     * @param string|null $cache_path Optional specific cache directory path
     * @return array Result array with success status and files removed count
     */
    public function clear_cache_location(string $connection_name, ?string $cache_path = null): array
    {
        try {
            $files_removed = 0;
            
            // Set adapter context for operations that need it
            $this->adapter_name = $connection_name;
            $this->adapter_type = $this->get_adapter_type($connection_name);
            
            // Get the cache path for this connection
            $cache_path = $this->filesystem->get_adapter_cache_path($connection_name);
            
            if ($this->adapter_type === 'local') {
                // For local adapter, use filesystem service for consistency
                $result = $this->filesystem->clear_adapter_cache($connection_name);
                $files_removed = $result['files_removed'] ?? 0;
            } else {
                // For cloud adapters, use the filesystem service
                $result = $this->filesystem->clear_adapter_cache($connection_name);
                $files_removed = $result['files_removed'] ?? 0;
            }
            
            // Clear database entries for this adapter
            if (ee()->db->table_exists(self::$table_name)) {
                ee()->db->where('site_id', $this->site_id)
                        ->where('adapter_name', $connection_name)
                        ->delete(self::$table_name);
            }
            
            // Clear static cache for this adapter
            if (isset(self::$cache_log_index[$this->site_id][$connection_name])) {
                unset(self::$cache_log_index[$this->site_id][$connection_name]);
            }
            
            return [
                'success' => true,
                'files_removed' => $files_removed,
                'adapter' => $connection_name
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'files_removed' => 0,
                'adapter' => $connection_name
            ];
        }
    }

    /**
     * Compare files and database entries for discrepancies (cloud adapter compatible)
     * 
     * @param array $files_in_location Array of file information
     * @param array $db_entries Array of database entries
     * @param string $cache_location Cache location being processed
     * @param array &$audit_results Audit results array to update (passed by reference)
     */
    /**
     * Convert URL or absolute path to relative path using adapter base path
     * Following PROJECT_RULES: no $_SERVER dependencies, use filesystem adapters only
     * Enhanced to handle full HTTP/HTTPS URLs for ACT processing
     * 
     * @param string $path Path or URL to convert (may be absolute or already relative)
     * @param string $adapter_name Adapter name to get base path from
     * @param object $filesystem_service FilesystemService instance
     * @return string Relative path suitable for filesystem adapters
     */
    public function convert_to_relative_path(string $path, string $adapter_name, $filesystem_service): string
    {
        // First, handle full URLs (http/https) by extracting just the path component
        if (preg_match('/^https?:\/\/[^\/]+(.*)$/', $path, $matches)) {
            $path = $matches[1]; // Extract path component after domain
        }
        
        // Start with trimmed path
        $relative_path = ltrim($path, '/');
        
        // For local adapter, check if we need to strip base path
        if ($adapter_name === 'local') {
            $base_path = $filesystem_service->get_adapter_base_path($adapter_name);
            if (!empty($base_path)) {
                // Normalize both paths for comparison (remove leading slashes)
                $normalized_base_path = ltrim($base_path, '/');
                if (str_starts_with($relative_path, $normalized_base_path)) {
                    // Remove base path prefix and ensure relative
                    $relative_path = ltrim(substr($relative_path, strlen($normalized_base_path)), '/');
                }
            }
        } else {
            // For cloud adapters, strip protocol schemes if present
            // e.g., "s3://bucket/path" → "path", "r2://bucket/path" → "path"
            $cloud_protocols = ['s3://', 'r2://', 'azure://', 'gcs://', 'dospaces://'];
            
            foreach ($cloud_protocols as $protocol) {
                if (str_starts_with($relative_path, $protocol)) {
                    // Remove protocol and bucket/container part
                    $path_after_protocol = substr($relative_path, strlen($protocol));
                    // Remove the first segment (bucket/container name) to get relative path
                    $segments = explode('/', $path_after_protocol, 2);
                    $relative_path = isset($segments[1]) ? $segments[1] : '';
                    break;
                }
            }
        }
        
        return $relative_path;
    }
    
    /**
     * Dump cache performance log for debugging
     * Static method accessible from anywhere for performance analysis
     * 
     * @param bool $clear_after_dump Whether to clear the log after dumping
     * @return array Performance log data
     */
    public static function dump_cache_performance_log(bool $clear_after_dump = false): array
    {
        $performance_data = [
            'total_operations' => count(self::$performance_log),
            'operations' => self::$performance_log,
            'summary' => []
        ];
        
        if (!empty(self::$performance_log)) {
            // Calculate summary statistics
            $durations = array_column(self::$performance_log, 'duration');
            $memory_usage = array_column(self::$performance_log, 'memory_used');
            
            $performance_data['summary'] = [
                'total_time' => array_sum($durations),
                'average_time' => array_sum($durations) / count($durations),
                'min_time' => min($durations),
                'max_time' => max($durations),
                'total_memory' => array_sum($memory_usage),
                'average_memory' => array_sum($memory_usage) / count($memory_usage),
                'operations_by_method' => array_count_values(array_column(self::$performance_log, 'method'))
            ];
        }
        
        if ($clear_after_dump) {
            self::$performance_log = [];
        }
        
        return $performance_data;
    }

    /**
     * Get cache information for a specific adapter
     * Enhanced to match Legacy behavior: checks both database AND filesystem for orphaned files
     * This ensures cache locations with orphaned files (no database entries) are still shown in control table
     * 
     * @param string $adapter_name The adapter to get information for
     * @return array Cache information for the specified adapter
     */
    public function get_adapter_cache_info(string $adapter_name, string $cache_dir = null): array
    {
        
        $adapter_info = [
            'file_count' => 0,
            'total_size' => 0,
            'last_modified' => null,
            'database_entries' => 0,
            'orphaned_files' => 0,
        ];
        
        // Step 1: Get database entries count and statistics
        $database_file_count = 0;
        $database_total_size = 0;
        $latest_date = null;
        
        if (ee()->db->table_exists(self::$table_name)) {
            try {
                // Get cache entries for this specific adapter and optionally specific cache directory
                $query_builder = ee()->db->select('stats')
                    ->from(self::$table_name)
                    ->where('site_id', $this->site_id)
                    ->where('adapter_name', $adapter_name);
                
                // If cache_dir is provided, filter by it as well
                if ($cache_dir !== null) {
                    // The cache_dir might be stored in the filename_path or full_filename field
                    // We need to filter for entries that have this cache directory in their path
                    $query_builder->like('path', $cache_dir, 'after');
                }
                
                $query = $query_builder->get();
                
                foreach ($query->result() as $row) {
                    $database_file_count++;
                    
                    if ($row->stats) {
                        $stats = json_decode($row->stats, true);
                        if ($stats && is_array($stats)) {
                            $database_total_size += isset($stats['size']) ? (float)$stats['size'] : 0;
                            
                            // Track latest modification date
                            if (isset($stats['inception_date'])) {
                                if ($latest_date === null || $stats['inception_date'] > $latest_date) {
                                    $latest_date = $stats['inception_date'];
                                }
                            }
                        }
                    }
                }
                
            } catch (\Exception $e) {
            }
        }
        
        // Step 2: Scan filesystem for actual files (including orphaned files)
        // This matches Legacy behavior where control table shows all cache locations with files
        $filesystem_file_count = 0;
        $filesystem_total_size = 0;
        $filesystem_last_modified = null;
        
        try {
            if ($adapter_name === 'local') {
                // Use the specific cache directory if provided, otherwise fall back to adapter default
                if ($cache_dir !== null) {
                    $cache_directory = $cache_dir;
                } else {
                    // Fallback: Scan local cache directory - use the same path resolution as the control panel
                    // Get the path from filesystem service to ensure consistency
                    $cache_directory = $this->filesystem->get_adapter_cache_path('local');
                }
                
                // Convert relative path to absolute if needed using adapter base path
                if (!str_starts_with($cache_directory, '/')) {
                    // Relative path - convert to absolute using adapter base path
                    $base_path = $this->filesystem->get_adapter_base_path('local');
                    $cache_directory = rtrim($base_path, '/') . '/' . ltrim($cache_directory, '/');
                }
                
                if (is_dir($cache_directory)) {
                    $files = glob($cache_directory . '/*', GLOB_NOSORT);
                    
                    if ($files) {
                        foreach ($files as $file) {
                            if (is_file($file)) {
                                $filesystem_file_count++;
                                // Convert absolute path to relative path for filesystem service
                                $relative_path = $this->convert_to_relative_path($file, 'local', $this->filesystem);
                                $file_size = $this->filesystem->filesize($relative_path, 'local') ?: 0;
                                $filesystem_total_size += $file_size;
                                
                                // Track latest filesystem modification date
                                $file_modified = filemtime($file);
                                if ($filesystem_last_modified === null || $file_modified > $filesystem_last_modified) {
                                    $filesystem_last_modified = $file_modified;
                                }
                            }
                        }
                    }
                }
            } else {
                // For cloud adapters, use filesystem service
                if ($cache_dir !== null) {
                    // Pass specific cache directory to audit method
                    $result = $this->filesystem->audit_adapter_cache($adapter_name, $cache_dir);
                } else {
                    // Fallback to generic adapter audit without cache_dir
                    $result = $this->filesystem->audit_adapter_cache($adapter_name);
                }
                $filesystem_file_count = $result['file_count'] ?? 0;
                $filesystem_total_size = $result['total_size'] ?? 0;
                // Note: Cloud adapter last_modified would need additional implementation
            }
            
        } catch (\Exception $e) {
        }
        
        // Step 3: Combine database and filesystem information
        // Use filesystem counts as authoritative (matches Legacy behavior)
        // Database stats provide additional metadata when available
        $adapter_info['file_count'] = $filesystem_file_count;
        $adapter_info['total_size'] = $filesystem_total_size;
        $adapter_info['database_entries'] = $database_file_count;
        $adapter_info['orphaned_files'] = max(0, $filesystem_file_count - $database_file_count);
        
        // Use the most recent date from either database or filesystem
        if ($latest_date !== null && $filesystem_last_modified !== null) {
            $adapter_info['last_modified'] = max($latest_date, $filesystem_last_modified);
        } else {
            $adapter_info['last_modified'] = $latest_date ?? $filesystem_last_modified;
        }
        
        return $adapter_info;
    }
    
    /**
     * Get adapter type for a given connection
     * Helper method for the refactored stateless architecture
     * 
     * @param string $connection_name Connection name
     * @return string Adapter type
     */
    private function get_adapter_type(string $connection_name): string
    {
        try {
            $connection_config = $this->settings->getNamedConnection($connection_name, decrypt_sensitive: true);
            return $connection_config['type'] ?? $connection_name; // Fallback to legacy behavior
        } catch (\Exception $e) {
            // Handle legacy connection names (e.g., 'legacy_local' -> 'local')
            if (str_starts_with($connection_name, 'legacy_')) {
                return substr($connection_name, 7); // Remove 'legacy_' prefix to get adapter type
            }
            
            // Fallback: treat as legacy adapter name
            return $connection_name;
        }
    }

    /**
     * Get comprehensive cache information for control panel display
     * 
     * Returns cache statistics matching Legacy format for compatibility
     * Used by the Caching control panel route to display cache overview
     * 
     * @return array Cache information array with statistics matching Legacy format
     */
    public function get_cache_info(): array
    {
        // Initialize return info with Legacy-compatible structure
        $return_info = [
            'inception_date' => 0,
            'number_of_cache_fragments' => 0,
            'number_of_cache_hits' => 0,
            'cumulative_filesize' => 0,
            'cumulative_processing_time' => 0,
            'caches_found' => 0,
            'adapter' => $this->settings->get_adapter_name(),
            'cache_performance_desc' => lang('jcogs_img_image_cache_is_empty'),
            'cache_clear_button_desc' => lang('jcogs_img_image_cache_is_empty')
        ];

        // Step 1: Get database statistics
        $database_file_count = 0;
        $database_stats = $this->_get_database_cache_stats();
        
        // Step 2: Get actual filesystem file counts for all available adapters
        $filesystem_stats = $this->_get_filesystem_cache_stats();
        
        // Step 3: Combine database and filesystem data (prioritize filesystem counts)
        $total_files = $filesystem_stats['total_files'];
        $total_size = $filesystem_stats['total_size'];
        $earliest_date = $filesystem_stats['earliest_date'];
        $active_adapters = $filesystem_stats['active_adapters'];
        
        // Use database stats for hit counts and processing time (only available in DB)
        $total_hits = $database_stats['total_hits'];
        $total_processing_time = $database_stats['total_processing_time'];
        
        // Populate return info with hybrid data
        $return_info['inception_date'] = $earliest_date ?: ($database_stats['earliest_date'] ?: 0);
        $return_info['number_of_cache_fragments'] = $total_files;
        $return_info['number_of_cache_hits'] = $total_hits;
        $return_info['cumulative_filesize'] = $total_size;
        $return_info['cumulative_processing_time'] = $total_processing_time;
        $return_info['caches_found'] = $active_adapters;
        
        // Build performance description following Legacy pattern
        if ($return_info['number_of_cache_fragments'] > 0) {
            $desc_key = 'jcogs_img_cp_cache_performance_desc_cache';
                
            $locations_desc_key = $return_info['caches_found'] > 1 ? 
                'jcogs_img_cp_cache_performance_desc_cache_many' : 
                'jcogs_img_cp_cache_performance_desc_cache_single';
            
            $return_info['cache_performance_desc'] = [
                'desc' => sprintf(
                    lang($desc_key),
                    $return_info['number_of_cache_fragments'],
                    $return_info['caches_found'] > 1 ? 
                        sprintf(lang($locations_desc_key), $return_info['caches_found']) : 
                        lang($locations_desc_key),
                    $return_info['number_of_cache_hits'],
                    $this->utilities_service->formatBytes($return_info['cumulative_filesize']),
                    $return_info['inception_date'] ? 
                        $this->_date_difference_to_now($return_info['inception_date']) : lang('jcogs_img_na'),
                    $return_info['cumulative_processing_time'] >= 1 ? 
                        round($return_info['cumulative_processing_time'], 0) . ' seconds' : 
                        round($return_info['cumulative_processing_time'], 2) . ' seconds',
                    $this->_cache_location_string(),
                    lang('jcogs_img_cp_cache_performance_desc_cache_operational')
                )
            ];
            
            $return_info['cache_clear_button_desc'] = [
                'title' => 'jcogs_img_cp_cache_clear',
                'desc' => sprintf(lang('jcogs_img_cp_cache_clear_desc'), $return_info['number_of_cache_fragments']) . PHP_EOL .
                          sprintf(lang('jcogs_img_cp_cache_clear_button'), 
                              ee('CP/URL', 'addons/settings/jcogs_img_pro/caching'))
            ];
        } else {
            $return_info['cache_performance_desc'] = ['desc' => lang('jcogs_img_image_cache_is_empty')];
            $return_info['cache_clear_button_desc'] = [
                'title' => 'jcogs_img_cp_cache_clear',
                'desc' => lang('jcogs_img_cp_cache_clear_desc_empty') . PHP_EOL .
                          sprintf(lang('jcogs_img_cp_cache_clear_button'), 
                              ee('CP/URL', 'addons/settings/jcogs_img_pro/caching'))
            ];
        }
        
        return $return_info;
    }

    /**
     * Get cached metadata for a specific image
     * 
     * Retrieves the cached metadata from the cache log for use in early cache hits.
     * Returns decoded JSON values that were stored during original processing.
     * 
     * @param string $image_path Cached image path
     * @param string|null $connection_name Connection name (if null, uses default)
     * @return array|null Cached metadata array or null if not found
     */
    public function get_cached_metadata(string $image_path, ?string $connection_name = null): ?array
    {
        try {
            // Resolve connection name
            $connection_name = $connection_name ?? $this->settings->get_default_connection_name();
            
            // Normalize the image path
            $normalized_data = $this->_normalize_cache_path_data($image_path, null);
            $cache_dir = $normalized_data['cache_dir'];
            $filename = $normalized_data['filename'];
            
            // Check if entry exists in cache index
            if (!isset(self::$cache_log_index[$this->site_id][$connection_name][$cache_dir][$filename])) {
                // Try lazy loading if selective loading is enabled
                if (self::$use_selective_loading) {
                    $this->_lazy_load_cache_entry($normalized_data);
                }
                
                // Check again after potential lazy load
                if (!isset(self::$cache_log_index[$this->site_id][$connection_name][$cache_dir][$filename])) {
                    return null;
                }
            }
            
            // Get the cache entry
            $entry = self::$cache_log_index[$this->site_id][$connection_name][$cache_dir][$filename];
            
            // Decode the JSON values if they exist
            if (isset($entry->values) && !empty($entry->values)) {
                $decoded_values = json_decode($entry->values, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded_values;
                }
            }
            
            return [];
            
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Extract cache duration from tag parameters
     * 
     * Centralized logic for parsing cache parameter values from template tag parameters.
     * Handles various cache duration formats including numeric values, time units (m/h/d/w),
     * and special values like perpetual cache (-1) and disabled caching (0).
     * 
     * @param array $tag_params Tag parameters array containing cache parameter
     * @return int Cache duration in seconds, -1 for perpetual, 0 for disabled
     */
    public function extract_cache_duration_from_tag_params(array $tag_params): int
    {
        $cache_param = $tag_params['cache'] ?? '';
        
        // Handle cache=0 or cache='0' (caching disabled)
        if ($cache_param === 0 || $cache_param === '0') {
            return 0;
        }
        
        // Handle empty cache parameter - use default
        if (empty($cache_param)) {
            $default_duration = $this->settings->get('img_cp_default_cache_duration', 500);
            return (int)$default_duration;
        }
        
        // Handle numeric cache parameter
        if (is_numeric($cache_param)) {
            $duration = (int)$cache_param;
            return $duration === -1 ? -1 : $duration; // -1 = perpetual cache
        }
        
        // Handle string cache parameter - could be time format
        if (is_string($cache_param)) {
            // Convert common time formats to seconds
            $cache_param = strtolower(trim($cache_param));
            
            // Handle units: m=minutes, h=hours, d=days, w=weeks
            if (preg_match('/^(\d+)([mhdw])$/', $cache_param, $matches)) {
                $value = (int)$matches[1];
                $unit = $matches[2];
                
                switch ($unit) {
                    case 'm': return $value * 60;        // minutes
                    case 'h': return $value * 3600;      // hours  
                    case 'd': return $value * 86400;     // days
                    case 'w': return $value * 604800;    // weeks
                }
            }
            
            // Handle special string values
            if ($cache_param === 'perpetual' || $cache_param === 'forever') {
                return -1;
            }
            
            if ($cache_param === 'disabled' || $cache_param === 'no' || $cache_param === 'off') {
                return 0;
            }
            
            // Try to parse as numeric if no unit found
            if (is_numeric($cache_param)) {
                return (int)$cache_param;
            }
        }
        
        // Default fallback
        $default_duration = $this->settings->get('img_cp_default_cache_duration', 500);
        return (int)$default_duration;
    }
    
    /**
     * Get legacy adapter names that might exist in cache_log table
     * Maps new named connections to legacy adapter names for backward compatibility
     * 
     * @param string $connection_name New connection name (e.g., 'legacy_local')
     * @return array Array of possible legacy adapter names to check
     */
    private function get_legacy_adapter_names(string $connection_name): array
    {
        // If it's already a legacy format, use as-is
        if (!str_starts_with($connection_name, 'legacy_')) {
            return [$connection_name];
        }
        
        // Map legacy_ prefixed names to their original names
        $legacy_name = substr($connection_name, 7); // Remove 'legacy_' prefix
        
        return [$connection_name, $legacy_name]; // Try both new and old formats
    }

    /**
     * Get cache information for a specific cache location (adapter + path)
     * This provides location-specific statistics to avoid duplicate counts in control table
     * 
     * @param string $adapter_name The adapter to get information for
     * @param string|null $cache_path Specific cache path to check (if null, checks all locations)
     * @return array Cache information for the specified location
     */
    public function get_location_cache_info(string $adapter_name, ?string $cache_path = null): array
    {
        $location_info = [
            'file_count' => 0,
            'total_size' => 0,
            'last_modified' => null,
            'database_entries' => 0,
            'orphaned_files' => 0
        ];
        
        // Step 1: Get database entries count for this specific cache path
        $database_file_count = 0;
        $database_total_size = 0;
        $latest_date = null;
        
        if (ee()->db->table_exists(self::$table_name) && $cache_path !== null) {
            try {
                // Convert to relative path for database comparison
                $relative_cache_path = $this->convert_to_relative_path($cache_path, $adapter_name, $this->filesystem);
                
                // Get cache entries for this specific adapter AND cache path
                $query = ee()->db->select('stats')
                    ->from(self::$table_name)
                    ->where('site_id', $this->site_id)
                    ->where('adapter_name', $adapter_name)
                    ->like('path', $relative_cache_path . '/', 'after') // Files within this cache directory
                    ->get();
                
                foreach ($query->result() as $row) {
                    $database_file_count++;
                    
                    if ($row->stats) {
                        $stats = json_decode($row->stats, true);
                        if ($stats && is_array($stats)) {
                            $database_total_size += isset($stats['size']) ? (float)$stats['size'] : 0;
                            
                            // Track latest modification date
                            if (isset($stats['inception_date'])) {
                                if ($latest_date === null || $stats['inception_date'] > $latest_date) {
                                    $latest_date = $stats['inception_date'];
                                }
                            }
                        }
                    }
                }
                
            } catch (\Exception $e) {
            }
        }
        
        // Step 2: Scan filesystem for actual files in this specific location
        $filesystem_file_count = 0;
        $filesystem_total_size = 0;
        $filesystem_last_modified = null;
        
        try {
            if ($adapter_name === 'local' && $cache_path !== null) {
                // Convert cache path to absolute path
                $absolute_cache_path = $cache_path;
                if (!str_starts_with($cache_path, '/')) {
                    // Relative path - convert to absolute using adapter base path
                    $base_path = $this->filesystem->get_adapter_base_path('local');
                    $absolute_cache_path = rtrim($base_path, '/') . '/' . ltrim($cache_path, '/');
                }
                
                if (is_dir($absolute_cache_path)) {
                    $files = glob($absolute_cache_path . '/*', GLOB_NOSORT);
                    
                    if ($files) {
                        foreach ($files as $file) {
                            if (is_file($file)) {
                                $filesystem_file_count++;
                                // Convert absolute path to relative path for filesystem service
                                $relative_path = $this->convert_to_relative_path($file, $adapter_name, $this->filesystem);
                                $file_size = $this->filesystem->filesize($relative_path, $adapter_name) ?: 0;
                                $filesystem_total_size += $file_size;
                                
                                // Track latest filesystem modification date
                                $file_modified = filemtime($file);
                                if ($filesystem_last_modified === null || $file_modified > $filesystem_last_modified) {
                                    $filesystem_last_modified = $file_modified;
                                }
                            }
                        }
                    }
                }
            } else if ($adapter_name !== 'local' && $cache_path !== null) {
                // For cloud adapters, use filesystem service to scan specific cache path
                try {
                    $result = $this->filesystem->audit_adapter_cache($adapter_name, $cache_path);
                    $filesystem_file_count = $result['file_count'] ?? 0;
                    $filesystem_total_size = $result['total_size'] ?? 0;
                    $filesystem_last_modified = $result['last_modified'] ?? null;
                } catch (\Exception $e) {
                    
                    // Alternative: Get files through the filesystem service listing
                    $files_result = $this->filesystem->list_cache_files($adapter_name, $cache_path);
                    if (!empty($files_result)) {
                        foreach ($files_result as $file_info) {
                            $filesystem_file_count++;
                            $filesystem_total_size += $file_info['size'] ?? 0;
                            
                            // Track latest modification date
                            if (isset($file_info['last_modified'])) {
                                $file_modified = $file_info['last_modified'];
                                if ($filesystem_last_modified === null || $file_modified > $filesystem_last_modified) {
                                    $filesystem_last_modified = $file_modified;
                                }
                            }
                        }
                    }
                }
            } else if ($cache_path === null) {
                // If no specific path provided, fall back to adapter-wide stats
                return $this->get_adapter_cache_info($adapter_name);
            }
            
        } catch (\Exception $e) {
        }
        
        // Step 3: Combine database and filesystem information for this location
        $location_info['file_count'] = $filesystem_file_count;
        $location_info['total_size'] = $filesystem_total_size;
        $location_info['database_entries'] = $database_file_count;
        $location_info['orphaned_files'] = max(0, $filesystem_file_count - $database_file_count);
        
        // Use the most recent date from either database or filesystem
        if ($latest_date !== null && $filesystem_last_modified !== null) {
            $location_info['last_modified'] = max($latest_date, $filesystem_last_modified);
        } else {
            $location_info['last_modified'] = $latest_date ?? $filesystem_last_modified;
        }
        
        return $location_info;
    }
    
    /**
     * Get variables from cache log for fast path output generation
     * 
     * Retrieves cached image metadata for template variable generation.
     * Used by fast cache path to avoid full pipeline execution.
     * 
     * @param string $cache_path Cache file path to look up
     * @return array|null Variables array or null if not found
     */
    public function get_variables_from_cache_log(string $cache_path): ?array
    {
        try {
            // Load cached image data using existing method
            $cache_data = $this->load_cached_image_data($cache_path);
            
            if (!$cache_data) {
                return null;
            }
            
            // Extract variables from cache data
            $variables = [];
            
            // Basic cache information
            $variables['cache_path'] = $cache_data['path'] ?? $cache_path;
            $variables['cache_url'] = $cache_data['cache_url'] ?? $cache_path;
            $variables['cache_key'] = $cache_data['cache_key'] ?? '';
            
            // Image dimensions if available in stats
            if (!empty($cache_data['stats'])) {
                $stats = json_decode($cache_data['stats'], true);
                if ($stats) {
                    $variables['width'] = $stats['width'] ?? '';
                    $variables['height'] = $stats['height'] ?? '';
                    $variables['original_width'] = $stats['original_width'] ?? '';
                    $variables['original_height'] = $stats['original_height'] ?? '';
                    $variables['target_width'] = $stats['target_width'] ?? '';
                    $variables['target_height'] = $stats['target_height'] ?? '';
                    $variables['processing_time'] = $stats['processing_time'] ?? '';
                    $variables['inception_date'] = $stats['inception_date'] ?? '';
                }
            }
            
            // Additional metadata if available in vars
            if (!empty($cache_data['vars'])) {
                $cached_vars = json_decode($cache_data['vars'], true);
                if ($cached_vars && is_array($cached_vars)) {
                    $variables = array_merge($variables, $cached_vars);
                }
            }
            
            return $variables;
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_log("Error getting variables from cache log: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if image exists in cache
     * 
     * Migrated from CacheManagementTrait->is_image_in_cache()
     * Preserving original logic and error handling patterns
     * Enhanced to support cache validity checking with duration
     * 
     * If cache_duration is null, the method will automatically extract the cache duration
     * from the filename using the existing _get_file_cache_duration_from_filename() method.
     * This eliminates the need to pass cache duration separately when it's already encoded
     * in the cache filename.
     * 
     * @param string $image_path Path to check in cache
     * @param int|null $cache_duration Optional cache duration for validity check (seconds, -1 for perpetual, 0 for disabled). 
     *                                If null, duration will be extracted from filename.
     * @param string|null $connection_name Connection name (if null, uses default)
     * @return bool True if image exists in cache (and is valid if duration provided), false otherwise
     */
    public function is_image_in_cache(string $image_path, ?int $cache_duration = null, ?string $connection_name = null): bool
    {
        // Resolve connection name early for profiling
        $connection_name = $connection_name ?? $this->settings->get_default_connection_name();
        $profile_id = $this->_profile_cache_method_start('is_image_in_cache', $connection_name);
        
        try {
            // Create cache key for result caching to avoid redundant operations
            $result_cache_key = md5($image_path . '_' . $cache_duration . '_' . $connection_name);
            
            // PERFORMANCE OPTIMIZATION: Enable request-level caching to prevent redundant cache lookups
            // This addresses the issue where Pro was doing 7.4 cache lookups per tag
            if (isset(self::$request_cache[$result_cache_key])) {
                $cached_result = self::$request_cache[$result_cache_key];
                $this->utilities_service->debug_message("Cache result (cached): " . ($cached_result ? 'HIT' : 'MISS'), [], false, 'detailed');
                $this->_profile_cache_method_end($profile_id);
                return $cached_result;
            }
            
            // Input validation - keeping original trait logic
            if (!$this->_validate_cache_check_inputs($image_path)) {
                self::$request_cache[$result_cache_key] = false;
                $this->_profile_cache_method_end($profile_id);
                return false;
            }
            
            // Early exit for explicitly disabled caching - preserving original behavior
            if ($this->_is_caching_explicitly_disabled()) {
                self::$request_cache[$result_cache_key] = false;
                $this->_profile_cache_method_end($profile_id);
                return false;
            }
            
            // CRITICAL: Ensure cache is preloaded ONCE per request per site/adapter - keeping original optimization
            $preload_key = (string)$this->site_id . '_' . (string)$connection_name;
            
            // Preload cache if needed (reduced debug noise)
            if (!isset(self::$preload_status[$preload_key])) {
                $this->preload_cache_log_index($connection_name);
                self::$preload_status[$preload_key] = true;
            }
            
            // Use existing error handling wrapper pattern - keeping original structure
            $result = $this->_execute_cache_operation(
                operation: fn() => $this->_perform_cache_validity_check($image_path, $cache_duration, $connection_name),
                operation_name: 'is_image_in_cache',
                context: ['image_path' => $image_path, 'cache_duration' => $cache_duration, 'connection_name' => $connection_name]
            );
            
            // Cache the result to avoid redundant operations within the same request
            self::$request_cache[$result_cache_key] = (bool) $result;
            
            $this->_profile_cache_method_end($profile_id);
            return (bool) $result;
            
        } catch (\Throwable $e) {
            // Cache the false result to avoid redundant failed operations
            self::$request_cache[$result_cache_key] = false;
            $this->_profile_cache_method_end($profile_id);
            $this->utilities_service->debug_message("Critical error in is_image_in_cache: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Load complete cached image data including metadata and file info
     * 
     * This is the single source of truth for loading cached images.
     * Follows project rules: uses cached metadata first, filesystem as last resort.
     * 
     * @param string $cache_path Cached image path (relative)
     * @return array|null Complete cache data or null if not found
     */
    public function load_cached_image_data(string $cache_path): ?array
    {
        try {
            // Step 1: Get template variables from cache log (PRIMARY source)
            $template_variables = $this->get_cached_metadata($cache_path);
            
            if (empty($template_variables)) {
                // No cache log entry found
                return null;
            }
            
            // Step 2: Use cached filesize data (PROJECT RULES compliance)
            $file_size = $template_variables['filesize_bytes'] ?? 0;
            $cache_url = $template_variables['made_url'] ?? '';
            $full_cache_path = $template_variables['path'] ?? '';
            
            // Step 3: Only access filesystem as LAST RESORT if critical data missing
            if (empty($full_cache_path) || $file_size === 0) {
                // Check if file actually exists
                if (!$this->filesystem->exists($cache_path)) {
                    return null;
                }
                
                // Generate missing data
                if (empty($full_cache_path)) {
                    $base_path = ee()->config->item('base_path') ?? FCPATH;
                    $full_cache_path = rtrim($base_path, '/') . '/' . $cache_path;
                }
                
                if ($file_size === 0) {
                    $file_size = $this->filesystem->filesize($cache_path) ?: 0;
                }
                
                if (empty($cache_url)) {
                    $cache_url = ee()->config->item('base_url') . ltrim($cache_path, '/');
                }
            }
            
            // Step 4: Return complete cache data
            return [
                'template_variables' => $template_variables,
                'file_info' => [
                    'path' => $full_cache_path,
                    'url' => $cache_url,
                    'size' => $file_size,
                    'exists' => true
                ]
            ];
            
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Preload cache log index for optimal performance
     * 
     * Implements threshold-based loading strategy from Legacy CacheManagementTrait.
     * Uses preload strategy for smaller datasets and selective loading for larger ones
     * to prevent the 5ms database query performance hit on high-volume sites.
     * 
     * Strategy Selection:
     * - Preload: Load all cache entries into memory when count <= threshold (default 10,000)
     * - Selective: Skip preload and use lazy loading for individual entries when count > threshold
     * 
     * Only executes once per request per site/adapter combination to prevent duplicate
     * database queries even when cache is empty.
     * 
     * @param string|null $connection_name Connection name (if null, uses default)
     * @return void
     */
    public function preload_cache_log_index(?string $connection_name = null): void
    {
        // Resolve connection name
        $connection_name = $connection_name ?? $this->settings->get_default_connection_name();
        
        // Only determine strategy once per request (like Legacy)
        if (self::$loading_strategy_determined) {
            return;
        }
        
        // Check if cache has already been loaded for this site/adapter (like Legacy)
        // This prevents duplicate database queries even when cache is empty
        $preload_key = (string)$this->site_id . '_' . (string)$connection_name;
        if (isset(self::$preload_status[$preload_key])) {
            return;
        }
        
        // Check if already preloaded to avoid duplicate loading
        if (isset(self::$cache_log_index[$this->site_id][$connection_name]) && 
            !empty(self::$cache_log_index[$this->site_id][$connection_name])) {
            return;
        }
        
        // Implement threshold-based strategy like Legacy to prevent 5ms performance hit
        $stored_count = (int)($this->settings->get('img_cp_cache_log_current_count') ?? 0);
        $threshold = (int)($this->settings->get('img_cp_cache_log_preload_threshold') ?? 10000);
        
        if ($stored_count > $threshold) {
            // Use selective loading for larger datasets - avoid 5ms database preload
            self::$use_selective_loading = true;
            
            $this->utilities_service->debug_message(sprintf(
                "Using selective loading: %d entries exceed threshold (%d) - skipping preload for performance", 
                $stored_count, 
                $threshold
            ));
            
            // Initialize empty cache structure for selective loading
            if (!isset(self::$cache_log_index[$this->site_id])) {
                self::$cache_log_index[$this->site_id] = [];
            }
            if (!isset(self::$cache_log_index[$this->site_id][$connection_name])) {
                self::$cache_log_index[$this->site_id][$connection_name] = [];
            }
            
            self::$loading_strategy_determined = true;
            // Mark preload as completed for this site/adapter (like Legacy)
            self::$preload_status[$preload_key] = true;
            return; // Skip expensive preload operation
        }
        
        // Use preload strategy for smaller datasets
        self::$use_selective_loading = false;
        
        try {
            if (!ee()->db->table_exists(self::$table_name)) {
                $this->utilities_service->debug_message("Cache table does not exist: " . self::$table_name);
                self::$loading_strategy_determined = true;
                // Mark preload as completed for this site/adapter (like Legacy)
                self::$preload_status[$preload_key] = true;
                return;
            }
            
            $query = ee()->db->select('*')
                ->from(self::$table_name)
                ->where('site_id', $this->site_id)
                ->where('adapter_name', $connection_name)
                ->order_by('path', 'ASC')
                ->get();
            
            if ($query->num_rows() > 0) {
                $this->utilities_service->debug_log(sprintf(
                    "Cache preload starting: %d cache entries to process for connection '%s'", 
                    $query->num_rows(), 
                    $connection_name
                ));
                
                $this->_process_preload_results($query, $connection_name);
                
                $this->utilities_service->debug_message(sprintf(
                    "Cache preload completed: %d cache entries loaded for connection '%s'", 
                    $query->num_rows(), 
                    $connection_name
                ));
            }
            
            self::$loading_strategy_determined = true;
            // Mark preload as completed for this site/adapter (like Legacy)
            self::$preload_status[$preload_key] = true;
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Failed to preload cache log index: " . $e->getMessage());
            self::$loading_strategy_determined = true;
            // Mark preload as completed even on error to prevent retry loops
            self::$preload_status[$preload_key] = true;
        }
    }
    
    /**
     * Remove a cache entry by path
     * 
     * Provides functionality similar to Legacy ImageUtilities->delete_cache_log_entry()
     * Removes both the database entry and the physical file from cache.
     * 
     * @param string $cache_path Path to the cached image
     * @param string|null $connection_name Connection name (if null, uses default)
     * @return bool True if removal was successful
     */
    public function remove_cache_entry_by_path(string $cache_path, ?string $connection_name = null): bool
    {
        try {
            // Resolve connection name
            $connection_name = $connection_name ?? $this->settings->get_default_connection_name();
            
            // Normalize the path data
            $normalized_data = $this->_normalize_cache_path_data($cache_path, null);
            
            // Remove from database
            $db_removed = ee()->db->where('site_id', $this->site_id)
                ->where('adapter_name', $connection_name)
                ->where('cache_dir', $normalized_data['cache_dir'])
                ->where('filename', $normalized_data['filename'])
                ->delete(self::$table_name);
            
            // Remove from static cache
            if (isset(self::$cache_log_index[$this->site_id][$connection_name][$normalized_data['cache_dir']][$normalized_data['filename']])) {
                unset(self::$cache_log_index[$this->site_id][$connection_name][$normalized_data['cache_dir']][$normalized_data['filename']]);
            }
            
            // Remove physical file using filesystem service
            try {
                if ($this->filesystem->exists($cache_path)) {
                    $this->filesystem->delete($cache_path);
                }
            } catch (\Exception $e) {
            }
            
            return $db_removed > 0;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Search cache log entries by filename
     * 
     * Provides functionality similar to Legacy ImageUtilities->get_file_info_from_cache_log()
     * Searches the cache log for entries that contain the specified filename.
     * 
     * @param string $filename Filename to search for (without path)
     * @return array Array of matching cache entries
     */
    public function search_cache_log_by_filename(string $filename): array
    {
        if (!ee()->db->table_exists(self::$table_name)) {
            return [];
        }
        
        try {
            // Search for entries where the path contains the filename
            // This matches Legacy behavior in get_file_info_from_cache_log()
            $query = ee()->db->select('*')
                ->from(self::$table_name)
                ->where('site_id', $this->site_id)
                ->like('path', $filename)
                ->get();
            
            if ($query->num_rows() === 0) {
                return [];
            }
            
            return $query->result();
            
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Test cache logging functionality with comprehensive workflow validation
     * 
     * Performs end-to-end testing of the complete cache management workflow to validate
     * that all cache operations are functioning correctly. This is a diagnostic tool
     * used for troubleshooting cache issues and validating system integrity.
     * 
     * Test Workflow:
     * 1. Cache Write: Tests update_cache_log() with simulated image processing data
     * 2. Cache Preload: Clears static cache and tests preload_cache_log_index()
     * 3. Cache Read: Tests is_image_in_cache() functionality with preloaded data
     * 4. Negative Testing: Verifies non-existent images correctly return false
     * 5. Database Validation: Direct database queries to confirm entries exist
     * 6. Cache Index Validation: Tests static cache structure and entry counts
     * 
     * This method is particularly useful for:
     * - Diagnosing cache configuration issues
     * - Validating cache functionality after system changes
     * - Troubleshooting performance problems
     * - Control panel diagnostic displays
     * 
     * @param string $test_image_path Path to use for testing (default: 'test/image.jpg')
     * @param string|null $connection_name Connection name (if null, uses default)
     * @return array Test results with success status, message, and detailed step results
     */
    public function test_cache_logging(string $test_image_path = 'test/image.jpg', ?string $connection_name = null): array
    {
        // Resolve connection name
        $connection_name = $connection_name ?? $this->settings->get_default_connection_name();
        $results = [
            'success' => false,
            'message' => '',
            'details' => []
        ];
        
        try {
            // Test basic functionality
            $test_vars = ['width' => 200, 'height' => 150, 'quality' => 85];
            $processing_time = 0.123; // Simulated processing time
            
            // Step 1: Test cache write
            $cache_result = $this->update_cache_log(
                image_path: $test_image_path,
                processing_time: $processing_time,
                vars: $test_vars,
                cache_dir: 'test',
                source_path: 'source/test/image.jpg',
                force_update: true
            );
            
            if ($cache_result) {
                $results['success'] = true;
                $results['message'] = 'Cache logging and checking test successful';
                $results['details']['cache_write'] = 'OK';
                
                // Step 2: Clear static cache to test preload functionality
                self::$cache_log_index = [];
                $results['details']['cache_cleared'] = 'OK';
                
                // Step 3: Test cache read with preload
                $cache_check = $this->is_image_in_cache($test_image_path);
                $results['details']['cache_read'] = $cache_check ? 'OK' : 'FAILED';
                
                // Step 4: Test cache read again (should use preloaded cache this time)
                $cache_check_2 = $this->is_image_in_cache($test_image_path);
                $results['details']['cache_read_preloaded'] = $cache_check_2 ? 'OK' : 'FAILED';
                
                // Step 5: Test cache read for non-existent image
                $non_existent_check = $this->is_image_in_cache('non/existent/image.jpg');
                $results['details']['non_existent_check'] = $non_existent_check ? 'FAILED' : 'OK';
                
                // Step 6: Check database directly
                $db_check = ee()->db->select('COUNT(*) as count')
                    ->from(self::$table_name)
                    ->where('site_id', $this->site_id)
                    ->where('adapter_name', $connection_name)
                    ->where('path', strtolower(trim($test_image_path, '/')))
                    ->get();
                
                $count = $db_check->row()->count ?? 0;
                $results['details']['database_entries'] = $count;
                $results['details']['site_id'] = $this->site_id;
                $results['details']['adapter_name'] = $connection_name;
                
                // Step 7: Test cache index structure
                $cache_index_exists = isset(self::$cache_log_index[$this->site_id][$connection_name]);
                $results['details']['cache_index_loaded'] = $cache_index_exists ? 'OK' : 'FAILED';
                
                if ($cache_index_exists) {
                    $index_count = 0;
                    foreach (self::$cache_log_index[$this->site_id][$connection_name] as $cache_dir => $files) {
                        $index_count += count($files);
                    }
                    $results['details']['cache_index_entries'] = $index_count;
                }
                
            } else {
                $results['message'] = 'Cache logging failed';
                $results['details']['cache_write'] = 'FAILED';
            }
            
        } catch (\Throwable $e) {
            $results['message'] = 'Cache logging test error: ' . $e->getMessage();
            $results['details']['error'] = $e->getTraceAsString();
        }
        
        return $results;
    }
    
    /**
     * Update cache log with image processing information
     * 
     * Migrated from Legacy CacheManagementTrait->update_cache_log()
     * Writes cache entries to exp_jcogs_img_pro_cache_log table
     * 
     * @param string $image_path Path to the processed image
     * @param float|null $processing_time Time taken to process the image
     * @param array|null $vars Variables associated with the processed image  
     * @param string|null $cache_dir Cache directory override
     * @param string|null $source_path Original source path of the image
     * @param bool $force_update Force update even if entry exists
     * @param bool $using_cache_copy Whether a cached copy is being used
     * @return bool True if cache log was updated successfully, false otherwise
     */
    public function update_cache_log(string $image_path, ?float $processing_time = null, ?array $vars = null, ?string $cache_dir = null, ?string $source_path = null, bool $force_update = false, bool $using_cache_copy = false, ?string $connection_name = null): bool
    {
        // Resolve connection name early for profiling
        $connection_name = $connection_name ?? $this->settings->get_default_connection_name();
        $profile_id = $this->_profile_cache_method_start('update_cache_log', $connection_name);
        
        try {
            // FIXED: Set adapter context from connection name for database operations
            $this->adapter_name = $connection_name;
            $this->adapter_type = $this->get_adapter_type($connection_name);
            
            // Use batch update system for optimal performance (Legacy parity)
            $result = $this->_schedule_cache_update($image_path, $processing_time, $vars, $cache_dir, $source_path, $force_update, $using_cache_copy, $connection_name);
            
            $this->_profile_cache_method_end($profile_id);
            return $result;
            
        } catch (\Throwable $e) {
            $this->_profile_cache_method_end($profile_id);
            $this->utilities_service->debug_message("Critical error in update_cache_log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Placeholder methods for supporting functionality
     * These will be implemented as we migrate more methods from the trait
     */
    
    /**
     * Add an orphaned file to the cache_log database
     * 
     * @param string $adapter_name The adapter name
     * @param string $cache_dir The cache directory
     * @param string $filename The filename
     * @param int $last_modified File last modified timestamp
     * @param int $file_size File size in bytes
     * @return void
     */
    private function _add_orphaned_file_to_cache_log(string $adapter_name, string $cache_dir, string $filename, int $last_modified, int $file_size): void
    {
        try {
            // Build stats array similar to Legacy format
            $stats = [
                'inception_date' => $last_modified,
                'count' => 1,
                'size' => $file_size,
                'processing_time' => 0, // Unknown for orphaned files
                'cumulative_size' => $file_size,
                'cumulative_processing_time' => 0,
                'sourcepath' => '', // Unknown for orphaned files
            ];
            
            $insert_data = [
                'site_id' => $this->site_id,
                'adapter_name' => $adapter_name,
                'adapter_type' => $this->adapter_type,
                'path' => trim($cache_dir, '/'),
                'image_name' => $filename,
                'stats' => json_encode($stats),
                'values' => '' // No template variables for orphaned files
            ];
            
            ee()->db->insert(self::$table_name, $insert_data);
            
            // Add to static cache if structure exists
            if (!isset(self::$cache_log_index[$this->site_id])) {
                self::$cache_log_index[$this->site_id] = [];
            }
            if (!isset(self::$cache_log_index[$this->site_id][$adapter_name])) {
                self::$cache_log_index[$this->site_id][$adapter_name] = [];
            }
            if (!isset(self::$cache_log_index[$this->site_id][$adapter_name][$cache_dir])) {
                self::$cache_log_index[$this->site_id][$adapter_name][$cache_dir] = [];
            }
            
            $log_object = new \stdClass;
            $log_object->site_id = $this->site_id;
            $log_object->adapter_name = $adapter_name;
            $log_object->path = trim($cache_dir, '/');
            $log_object->image_name = $filename;
            $log_object->stats = json_encode($stats);
            $log_object->values = '';
            
            self::$cache_log_index[$this->site_id][$adapter_name][$cache_dir][$filename] = $log_object;
            
        } catch (\Exception $e) {
        }
    }
    
    /**
     * Audit database entries for a specific cache path
     * Removes expired cache_log entries and deletes corresponding files
     * Removes orphaned database entries for missing files
     * 
     * @param string $adapter_name The adapter to audit
     * @param string $cache_path The specific cache path
     * @return array Results with files_removed, entries_removed, and files_size_removed counts
     */
    private function _audit_database_entries_for_path(string $cache_location): array
    {
        $db_entries = [];
        
        if (!ee()->db->table_exists(self::$table_name)) {
            return $db_entries;
        }
        
        try {
            // Convert absolute cache location to relative path for database query
            // The path column contains relative paths like "images/jcogs_img_pro/cache/filename.ext"
            $relative_cache_location = $this->convert_to_relative_path($cache_location, $this->adapter_name, $this->filesystem);
            $cache_path_pattern = rtrim($relative_cache_location, '/') . '/';
            
            $query = ee()->db->select('*')
                ->from(self::$table_name)
                ->where('site_id', $this->site_id)
                ->where('adapter_name', $this->adapter_name)
                ->like('path', $cache_path_pattern, 'after')
                ->get();
            
            foreach ($query->result() as $entry) {
                $db_entries[] = [
                    'path' => $entry->path,
                    'image_name' => $entry->image_name ?? basename($entry->path),
                    'stats' => $entry->stats ?? '{}',
                    'created' => $entry->created ?? null,
                    'id' => $entry->id ?? null
                ];
            }
            
        } catch (\Exception $e) {
        }
        
        return $db_entries;
    }
    
    /**
     * Audit a single file for cache validity and perform necessary operations
     * Following Legacy CacheManagementTrait::_audit_single_file pattern
     * 
     * @param array $file_info File information from filesystem
     * @param string $cache_location Cache location path
     * @param string $adapter_name Filesystem adapter name
     * @return array Result with action taken and size information
     */
    private function _audit_single_file_operations(array $file_info, string $cache_location, string $adapter_name): array
    {
        $result = [
            'action' => 'none',
            'size_removed' => 0
        ];
        
        try {
            $filename = $file_info['filename'];
            $relative_path = $file_info['relative_path'];
            $file_size = $file_info['size'];
            $last_modified = $file_info['mtime'];
            
            // Get cache duration for this file (following Legacy pattern)
            $cache_duration_when_saved = $this->_get_file_cache_duration_from_filename($filename);
            
            // Check if file is still valid (following Legacy _is_file_cache_valid pattern)
            $is_valid = $this->_is_file_cache_valid($cache_duration_when_saved, $last_modified);
            
            if (!$is_valid) {
                // File is expired - remove it (following Legacy pattern)
                $this->_remove_expired_cache_file($relative_path, $adapter_name, $cache_location, $filename);
                $result['action'] = 'removed';
                $result['size_removed'] = $file_size;
            } else {
                // File is valid - ensure it's in the cache log (following Legacy pattern)
                $this->_ensure_file_in_cache_log($relative_path, $cache_location, $filename, $last_modified, $file_size);
                $result['action'] = 'added_to_db';
            }
            
        } catch (\Exception $e) {
        }
        
        return $result;
    }
    
    /**
     * Build tracker statistics array
     * 
     * Migrated from Legacy CacheManagementTrait->_build_tracker_stats()
     * 
     * @param string $original_path Original path to the image file
     * @param float|null $processing_time Time taken to process the image
     * @param string|null $source_path Original source path of the image
     * @return array Array of tracker statistics
     */
    private function _build_tracker_stats(string $original_path, ?float $processing_time, ?string $source_path): array
    {
        // Try to get file size, but don't fail if file doesn't exist yet
        $file_size = self::$initial_size;
        try {
            $actual_size = $this->filesystem->filesize($original_path);
            if ($actual_size !== false) {
                $file_size = $actual_size;
            }
        } catch (\Exception $e) {
            // File might not exist yet or be inaccessible, use default size
        }
        
        $effective_processing_time = $processing_time ?: self::$initial_processing_time;
        
        return [
            'inception_date' => time(),
            'count' => self::$initial_count,
            'size' => $file_size,
            'processing_time' => $effective_processing_time,
            'cumulative_size' => $file_size,
            'cumulative_processing_time' => $effective_processing_time,
            'sourcepath' => $source_path ?: '',
        ];
    }
    
    /**
     * Check if cache entry already exists - Enhanced for Pro v2 with request-level caching
     * 
     * Migrated from Legacy CacheManagementTrait->_cache_entry_exists()
     * Enhanced with lazy loading support to match Legacy behavior
     * 
     * @param array $normalized_data Normalized cache data array
     * @param string $connection_name Connection name for cache lookup
     * @return bool True if cache entry exists, false otherwise
     */
    private function _cache_entry_exists(array $normalized_data, string $connection_name): bool
    {
        $cache_key = $this->site_id . '|' . $connection_name . '|' . $normalized_data['normalized_path'] . '|' . $normalized_data['filename'];
        
        // NEW: Enhanced request-level caching for cache existence checks
        // Cache both positive and negative results to prevent duplicate lookups
        static $cache_existence_results = [];
        if (isset($cache_existence_results[$cache_key])) {
            return $cache_existence_results[$cache_key];
        }
        
        // First check request-level database cache
        if (isset(self::$database_operation_cache[$cache_key])) {
            $cache_existence_results[$cache_key] = true;
            return true;
        }
        
        // Check if this operation is pending
        if (isset(self::$pending_database_operations[$cache_key])) {
            $cache_existence_results[$cache_key] = true;
            return true;
        }
        
        // Ensure static cache structure exists
        $this->_ensure_static_cache_structure_for_retrieval($connection_name);
        
        // Check if entry exists in static cache
        $cache_dir = $normalized_data['cache_dir'];
        $filename = strtolower($normalized_data['filename']);
        
        $cache_exists = isset(self::$cache_log_index[$this->site_id][$connection_name][$cache_dir][$filename]);
        
        // Log result only (detailed logging available in detailed mode)
        $this->utilities_service->debug_log(sprintf(
            "Cache result: %s", $cache_exists ? 'HIT' : 'MISS'
        ));
        
        // If not found in static cache and using selective loading, try lazy load
        if (!$cache_exists && self::$use_selective_loading) {
            $cache_exists = $this->_lazy_load_cache_entry($normalized_data);
        }
        
        // Cache the result for future lookups within this request
        $cache_existence_results[$cache_key] = $cache_exists;
        
        return $cache_exists;
    }

    /**
     * Helper method to get cache location string
     * Following Legacy trait pattern for compatibility
     * 
     * @return string Human-readable cache location description
     */
    private function _cache_location_string(): string
    {
        $adapter_name = $this->settings ? $this->settings->get('img_cp_flysystem_adapter') : 'local';
        
        if ($adapter_name !== 'local') {
            $adapter_labels = [
                's3' => 'Amazon S3',
                'r2' => 'Cloudflare R2',
                'spaces' => 'DigitalOcean Spaces'
            ];
            $cache_adapter_string = $adapter_labels[$adapter_name] ?? ucfirst($adapter_name);
            return 'using the ' . $cache_adapter_string . ' cloud filesystem';
        }
        
        return 'locally on the server';
    }
    
    /**
     * Create log object for static cache
     * 
     * Migrated from Legacy CacheManagementTrait->_create_log_object()
     * 
     * @param array $normalized_data Normalized cache data array
     * @param array $tracker_stats Array of tracker statistics
     * @param array|null $vars Variables associated with the processed image
     * @return \stdClass Log object for static cache storage
     */
    private function _create_log_object(array $normalized_data, array $tracker_stats, ?array $vars): \stdClass
    {
        // Fix: Extract the actual variables array from the nested structure
        $vars_to_encode = null;
        if ($vars) {
            // Check if $vars has the nested [0] structure and extract it
            if (isset($vars[0]) && is_array($vars[0])) {
                $vars_to_encode = $vars[0]; // Extract the inner array
            } else {
                $vars_to_encode = $vars; // Use as-is if it's already the right structure
            }
        }
        
        $log_object = new \stdClass;
        $log_object->site_id = $this->site_id;
        $log_object->adapter_name = $this->adapter_name;
        $log_object->path = $normalized_data['normalized_path'];
        $log_object->image_name = $normalized_data['filename'];
        $log_object->stats = $this->_safe_json_encode($tracker_stats);
        $log_object->values = $vars_to_encode ? $this->_safe_json_encode($vars_to_encode) : '';
        
        return $log_object;
    }
    
    /**
     * Check if a database entry exists for the given file
     * 
     * @param string $adapter_name The adapter name
     * @param string $cache_dir The cache directory
     * @param string $filename The filename
     * @return bool True if entry exists, false otherwise
     */
    private function _check_database_entry_exists(string $adapter_name, string $cache_dir, string $filename): bool
    {
        if (!ee()->db->table_exists(self::$table_name)) {
            return false;
        }
        
        try {
            $query = ee()->db->select('COUNT(*) as count')
                ->from(self::$table_name)
                ->where('site_id', $this->site_id)
                ->where('adapter_name', $adapter_name)
                ->where('path', trim($cache_dir, '/'))
                ->where('image_name', $filename)
                ->get();
            
            return $query->row()->count > 0;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Helper method to calculate time difference to now
     * Following Legacy trait pattern for compatibility
     * 
     * @param int $timestamp Unix timestamp
     * @return string Human-readable time difference
     */
    private function _date_difference_to_now(int $timestamp): string
    {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return $diff . ' seconds ago';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . ' minutes ago';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . ' hours ago';
        } else {
            return floor($diff / 86400) . ' days ago';
        }
    }
    
    /**
     * Ensure valid file is represented in cache log database
     * 
     * @param string $relative_path Relative file path
     * @param string $cache_location Cache location
     * @param string $filename Filename
     * @param int $last_modified File modification timestamp
     * @param int $file_size File size in bytes
     * @return void
     */
    private function _ensure_file_in_cache_log(string $relative_path, string $cache_location, string $filename, int $last_modified, int $file_size): void
    {
        try {
            // Check if entry already exists
            if (!$this->_check_database_entry_exists($this->adapter_name, $cache_location, $filename)) {
                // Add orphaned file to cache log
                $this->_add_orphaned_file_to_cache_log($this->adapter_name, $cache_location, $filename, $last_modified, $file_size);
            }
            
        } catch (\Exception $e) {
        }
    }
    
    /**
     * Ensure static cache structure exists for retrieval operations
     * 
     * Migrated from Legacy CacheManagementTrait->_ensure_static_cache_structure_for_retrieval()
     * 
     * @param string $connection_name Connection name for cache structure
     * @return void
     */
    private function _ensure_static_cache_structure_for_retrieval(string $connection_name): void
    {
        if (!isset(self::$cache_log_index[$this->site_id][$connection_name])) {
            self::$cache_log_index[$this->site_id][$connection_name] = [];
        }
    }
    
    /**
     * Executes a cache operation with error handling and logging.
     *
     * @param callable $operation The cache operation to execute
     * @param string $operation_name Name of the operation for logging purposes
     * @param array $context Additional context data for the operation
     * @return mixed The result of the cache operation
     */
    private function _execute_cache_operation(callable $operation, string $operation_name, array $context = []): mixed
    {
        $start_time = microtime(true);
        $operation_id = uniqid('cache_op_', true);
        
        try {
            // Execute the operation with error recovery
            $result = $operation();
            
            // Log successful operations in development
            if ($this->_is_development_environment()) {
                $duration = microtime(true) - $start_time;
                $this->utilities_service->debug_log(sprintf(
                    'Cache operation "%s" completed successfully in %.4fs [%s]',
                    $operation_name,
                    $duration,
                    $operation_id
                ));
            }
            
            return $result;
            
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start_time;
            
            // Enhanced error logging with context
            $error_context = array_merge($context, [
                'operation_name' => $operation_name,
                'operation_id' => $operation_id,
                'duration' => $duration,
                'site_id' => $this->site_id,
                'adapter' => $this->adapter_name,
                'error_type' => get_class($e),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            $this->utilities_service->debug_message(sprintf(
                'Cache operation "%s" failed after %.4fs: %s [%s]',
                $operation_name,
                $duration,
                $e->getMessage(),
                $operation_id
            ));
            
            // Log detailed error context in development
            if ($this->_is_development_environment()) {
                $this->utilities_service->debug_log('Cache operation error context: ' . json_encode($error_context, JSON_PRETTY_PRINT));
                $this->utilities_service->debug_log('Stack trace: ' . $e->getTraceAsString());
            }
            
            // Determine appropriate return value based on operation type
            if (str_contains($operation_name, 'check') || str_contains($operation_name, 'exists')) {
                return false; // Boolean operations return false on error
            } elseif (str_contains($operation_name, 'get') || str_contains($operation_name, 'retrieve')) {
                return []; // Array operations return empty array on error
            } elseif (str_contains($operation_name, 'count')) {
                return 0; // Count operations return 0 on error
            } else {
                return false; // Default to false for most operations
            }
        }
    }
    
    /**
     * Flush pending cache updates in batch for optimal performance
     * 
     * Migrated from Legacy CacheManagementTrait->_flush_cache_updates_batch()
     * Processes queued cache updates without database transactions
     * 
     * @return void
     */
    public function _flush_cache_updates_batch(): void
    {
        if (empty(self::$pending_cache_updates)) {
            return;
        }
        
        // Take a snapshot and clear immediately to prevent re-entry
        $updates_to_process = self::$pending_cache_updates;
        self::$pending_cache_updates = [];
        self::$cache_update_scheduled = false;
        
        // Group updates by unique image path to prevent duplicates
        $unique_updates = [];
        foreach ($updates_to_process as $cache_key => $update_data) {
            if (!isset($update_data['image_path']) || empty($update_data['image_path'])) {
                continue;
            }
            
            $image_path = $update_data['image_path'];
            
            // Keep only the most recent update for each image path
            if (!isset($unique_updates[$image_path]) || 
                ($update_data['timestamp'] ?? 0) > ($unique_updates[$image_path]['timestamp'] ?? 0)) {
                $unique_updates[$image_path] = $update_data;
            }
        }
        
        // Process unique updates using Pro service methods
        foreach ($unique_updates as $update_data) {
            try {
                if (empty($update_data['image_path'])) {
                    continue;
                }
                
                // Use selective update to handle force_update properly
                $this->_perform_immediate_selective_update(
                    image_path: $update_data['image_path'],
                    processing_time: $update_data['processing_time'] ?? null,
                    vars: $update_data['vars'] ?? null,
                    cache_dir: $update_data['cache_dir'] ?? null,
                    source_path: $update_data['source_path'] ?? null,
                    force_update: $update_data['force_update'] ?? false,
                    using_cache_copy: $update_data['using_cache_copy'] ?? false,
                    connection_name: $update_data['connection_name'] ?? $this->settings->get_default_connection_name()
                );
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * Get cache locations to audit from database paths (simplified approach)
     * 
     * @param string|null $specific_cache_path Specific cache path to audit
     * @return array Array of cache location paths from database
     */
    private function _get_cache_locations_for_audit(?string $specific_cache_path = null): array
    {
        $cache_locations = [];

        if (!ee()->db->table_exists(self::$table_name)) {
            return $cache_locations;
        }

        try {
            if ($specific_cache_path) {
                // Use the specific path directly
                $cache_locations[] = trim($specific_cache_path, '/');
            } else {
                // Get legacy adapter names to check (handles both old and new formats)
                $adapter_names = $this->get_legacy_adapter_names($this->adapter_name);
                
                // Get all unique directories from the path column for any of the possible adapter names
                $query = ee()->db->select('path')
                    ->from(self::$table_name)
                    ->where('site_id', $this->site_id)
                    ->where_in('adapter_name', $adapter_names)
                    ->get();

                $unique_dirs = [];
                foreach ($query->result() as $row) {
                    // The path column contains "images/jcogs_img_pro/cache/filename.ext"
                    // Extract just the directory part
                    $directory = dirname($row->path);
                    if ($directory && $directory !== '.') {
                        $unique_dirs[$directory] = true;
                    }
                }

                $cache_locations = array_keys($unique_dirs);
            }

        } catch (\Exception $e) {
        }

        return $cache_locations;
    }

    /**
     * Get database cache statistics
     * 
     * @return array Database statistics
     */
    private function _get_database_cache_stats(): array
    {
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'total_hits' => 0,
            'total_processing_time' => 0,
            'earliest_date' => null
        ];
        
        if (!ee()->db->table_exists(self::$table_name)) {
            return $stats;
        }
        
        try {
            $query = ee()->db->select('adapter_name, stats')
                ->from(self::$table_name)
                ->where('site_id', $this->site_id)
                ->get();
            
            $earliest_inception = time();
            
            foreach ($query->result() as $row) {
                $stats['total_files']++;
                
                if ($row->stats) {
                    $row_stats = json_decode($row->stats, true);
                    if ($row_stats && is_array($row_stats)) {
                        $stats['total_hits'] += isset($row_stats['count']) ? (int)$row_stats['count'] : 1;
                        $stats['total_size'] += isset($row_stats['size']) ? (float)$row_stats['size'] : 0;
                        $stats['total_processing_time'] += isset($row_stats['processing_time']) ? (float)$row_stats['processing_time'] : 0;
                        
                        if (isset($row_stats['inception_date']) && $row_stats['inception_date'] < $earliest_inception) {
                            $earliest_inception = $row_stats['inception_date'];
                        }
                    }
                } else {
                    $stats['total_hits'] += 1;
                }
            }
            
            $stats['earliest_date'] = ($earliest_inception === time() && $stats['total_files'] === 0) ? null : $earliest_inception;
            
        } catch (\Exception $e) {
            if ($this->settings && $this->settings->get('img_cp_enable_debugging') === 'y') {
            }
        }
        
        return $stats;
    }
    
    /**
     * Get existing database entry for update operations
     * 
     * @param array $normalized_data Normalized cache data array
     * @return object|null Existing database entry or null if not found
     */
    private function _get_existing_database_entry(array $normalized_data): ?object
    {
        if (!ee()->db->table_exists(self::$table_name)) {
            return null;
        }
        
        // NEW: Request-level caching to prevent duplicate queries
        $cache_key = $this->site_id . '|' . $this->adapter_name . '|' . $normalized_data['normalized_path'] . '|' . $normalized_data['filename'];
        
        // Check if we already have this in our request-level cache
        if (isset(self::$database_operation_cache[$cache_key])) {
            return self::$database_operation_cache[$cache_key];
        }
        
        try {
            $query = ee()->db->select('*')
                ->from(self::$table_name)
                ->where('site_id', $this->site_id)
                ->where('adapter_name', $this->adapter_name)
                ->where('path', $normalized_data['normalized_path'])
                ->where('image_name', $normalized_data['filename'])
                ->limit(1)
                ->get();
            
            $result = $query->num_rows() > 0 ? $query->row() : null;
            
            // Cache the result for the rest of this request
            self::$database_operation_cache[$cache_key] = $result;
            
            return $result;
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Failed to check existing database entry: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extract cache duration from filename using configured separator pattern
     * Migrated from Legacy CacheManagementTrait->get_file_cache_duration()
     * 
     * @param string $image_filename The image filename to parse
     * @return int|bool Cache duration in seconds, -1 for perpetual, or false if invalid
     */
    private function _get_file_cache_duration_from_filename(string $image_filename): bool|int
    {
        $default_cache_duration = $this->settings->get('img_cp_default_cache_duration', 604800); // 1 week default
        $filename_separator = $this->settings->get('img_cp_default_filename_separator', '-');
        
        $cache_duration_tag = explode($filename_separator, $image_filename);
        $cache_duration_when_saved = null;

        if (count($cache_duration_tag) > 1) {
            // Start from last element found and look for first one that looks like a cache duration
            for ($i = count($cache_duration_tag) - 1; $i >= 0; $i--) {
                if (isset($cache_duration_tag[$i]) && ctype_xdigit($cache_duration_tag[$i])) {
                    $cache_duration_when_saved = $cache_duration_tag[$i] == 'abcdef' ? -1 : hexdec($cache_duration_tag[$i]);
                    break;
                }
            }
        }
        
        if (!isset($cache_duration_when_saved)) {
            $cache_duration_when_saved = $default_cache_duration;
        }
        
        return is_int($cache_duration_when_saved) ? $cache_duration_when_saved : false;
    }

    /**
     * Get files in cache location using correct filesystem adapter (following Legacy pattern)
     * 
     * @param string $cache_location Cache directory to scan (relative path from database)
     * @param string $adapter_name Filesystem adapter to use
     * @return array Array of file information
     */
    private function _get_files_in_cache_location(string $cache_location, string $adapter_name): array
    {
        $files = [];

        try {
            // Convert absolute paths to relative paths using adapter base path (PROJECT_RULES compliance)
            $relative_cache_path = $this->convert_to_relative_path($cache_location, $adapter_name, $this->filesystem);
            
            // Check if the cache directory exists on this adapter
            if (!$this->filesystem->directoryExists($relative_cache_path, $adapter_name)) {
                return $files;
            }
            
            // Get directory listing from the correct adapter
            $directory_contents = $this->filesystem->listContents($relative_cache_path, false, $adapter_name);
            
            if ($directory_contents && is_array($directory_contents)) {
                foreach ($directory_contents as $file_info) {
                    // Filter to only include files (not directories)
                    if (isset($file_info['type']) && $file_info['type'] === 'file') {
                        $file_path = $file_info['path'] ?? '';
                        $filename = basename($file_path);
                        $relative_path = trim($file_path, '/');
                        
                        $files[] = [
                            'relative_path' => $relative_path,
                            'filename' => $filename,
                            'size' => $file_info['file_size'] ?? 0,
                            'mtime' => $file_info['last_modified'] ?? time()
                        ];
                    }
                }
            }

        } catch (\Exception $e) {
        }

        return $files;
    }

    /**
     * Get filesystem cache statistics across all adapters
     * 
     * @return array Filesystem statistics
     */
    private function _get_filesystem_cache_stats(): array
    {
        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'earliest_date' => null,
            'active_adapters' => 0
        ];
        
        try {
            $filesystem_service = ServiceCache::filesystem();
            $adapters = $filesystem_service->list_available_adapters();
            $adapters_with_files = 0;
            $earliest_modification = null;
            
            foreach ($adapters as $adapter_name) {
                $adapter_stats = $this->get_adapter_cache_info($adapter_name);
                
                if ($adapter_stats['file_count'] > 0) {
                    $adapters_with_files++;
                    $stats['total_files'] += $adapter_stats['file_count'];
                    $stats['total_size'] += $adapter_stats['total_size'];
                    
                    if ($adapter_stats['last_modified'] && 
                        ($earliest_modification === null || $adapter_stats['last_modified'] < $earliest_modification)) {
                        $earliest_modification = $adapter_stats['last_modified'];
                    }
                }
            }
            
            $stats['active_adapters'] = $adapters_with_files;
            $stats['earliest_date'] = $earliest_modification;
            
        } catch (\Exception $e) {
        }
        
        return $stats;
    }

    /**
     * Check if we're processing an ACT (Action) request
     * 
     * ACT requests bypass the normal EE template system and don't instantiate TMPL objects.
     * We need to detect this context to avoid trying to access ee()->TMPL.
     * 
     * @return bool True if processing an ACT request, false otherwise
     */
    private function _is_act_request(): bool
    {
        // Check if ACT parameter is present in the request
        if (isset($_GET['ACT']) || isset($_POST['ACT'])) {
            return true;
        }
        
        // Check if ValidationService has ACT processing flag set
        try {
            $validation_service = ee('jcogs_img_pro:ValidationService');
            if ($validation_service && $validation_service->is_act_processing()) {
                return true;
            }
        } catch (\Throwable $e) {
            // ValidationService not available - continue with other checks
        }
        
        // Check if we're in a context where TMPL is definitely not available
        // This is a safer approach than trying to access services that may not exist
        if (!isset(ee()->TMPL) || ee()->TMPL === null) {
            // Could be ACT request, CLI, or other non-template context
            // In ACT context specifically, the request will have come via direct URL access
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
            $query_string = $_SERVER['QUERY_STRING'] ?? '';
            
            // Look for ACT patterns in the URL
            if (strpos($request_uri, 'ACT=') !== false || strpos($query_string, 'ACT=') !== false) {
                return true;
            }
            
            // If TMPL is not available and we can't determine ACT, assume it's a non-template context
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if image caching has been explicitly disabled in the configuration.
     *
     * @return bool True if caching is explicitly disabled, false otherwise
     */
    private function _is_caching_explicitly_disabled(): bool
    {
        // For Pro addon, check basic settings first
        $cache_enabled = $this->settings->get('img_cp_default_enable_cache', true);
        
        if (!$cache_enabled) {
            return true; // Caching is globally disabled
        }
        
        // During Phase 2, we don't have PipelineContext yet
        // Fall back to template parameter checking
        
        // Skip template parameter checking if we're processing an ACT request
        // ACT requests bypass the normal template system and don't have TMPL object
        $is_act_request = $this->_is_act_request();
        
        // Check for template-level cache parameters via EE template system
        if (!$is_act_request && ee()->TMPL && method_exists(ee()->TMPL, 'fetch_param')) {
            // Check explicit cache disable parameter (cache=0)
            $cache_param = ee()->TMPL->fetch_param('cache');
            if ($cache_param !== false && ($cache_param == 0 || $cache_param == '0')) {
                return true;
            }
            
            // Check cache overwrite parameter (overwrite_cache='y')
            $overwrite_cache = ee()->TMPL->fetch_param('overwrite_cache');
            if ($overwrite_cache && 
                (strtolower($overwrite_cache) === 'y' || 
                 strtolower($overwrite_cache) === 'yes' ||
                 $overwrite_cache === '1')) {
                return true;
            }
            
            // Check refresh parameter (refresh='y')
            $refresh = ee()->TMPL->fetch_param('refresh');
            if ($refresh && 
                (strtolower($refresh) === 'y' || 
                 strtolower($refresh) === 'yes' ||
                 $refresh === '1')) {
                return true;
            }
            
            // Check no_cache parameter (no_cache='y')
            $no_cache = ee()->TMPL->fetch_param('no_cache');
            if ($no_cache && 
                (strtolower($no_cache) === 'y' || 
                 strtolower($no_cache) === 'yes' ||
                 $no_cache === '1')) {
                return true;
            }
            
            // Check disable_caching parameter
            $disable_caching = ee()->TMPL->fetch_param('disable_caching');
            if ($disable_caching && 
                (strtolower($disable_caching) === 'y' || 
                 strtolower($disable_caching) === 'yes' ||
                 $disable_caching === '1')) {
                return true;
            }
        }
        
        return false; // Caching is not explicitly disabled
    }
    
    /**
     * Check if running in development environment
     * 
     * @return bool True if in development environment
     */
    private function _is_development_environment(): bool
    {
        // Check various development indicators
        $is_dev = false;
        
        // Check EE debug mode
        if (defined('DEBUG') && DEBUG > 0) {
            $is_dev = true;
        }
        
        // Check for common development domains/IPs
        $host = ee()->config->item('base_url') ?? '';
        $dev_indicators = ['localhost', '127.0.0.1', '.local', '.dev', '.test', 'ddev'];
        
        foreach ($dev_indicators as $indicator) {
            if (stripos($host, $indicator) !== false) {
                $is_dev = true;
                break;
            }
        }
        
        // Check environment variable if available
        if (!$is_dev && isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development') {
            $is_dev = true;
        }
        
        return $is_dev;
    }
    
    /**
     * Check if a file is still valid for caching (following Legacy _is_file_cache_valid pattern)
     * 
     * @param int|bool $cache_duration_when_saved Cache duration extracted from filename
     * @param int $last_modified File last modified timestamp
     * @return bool True if file is still valid, false if expired
     */
    private function _is_file_cache_valid(int|bool $cache_duration_when_saved, int $last_modified): bool
    {
        // Perpetual cache (-1) is always valid
        if ($cache_duration_when_saved === -1) {
            return true;
        }
        
        // Invalid cache duration
        if ($cache_duration_when_saved === false || $cache_duration_when_saved === 0) {
            return false;
        }
        
        // Check if file has expired based on timestamp
        $file_age = time() - $last_modified;
        return $file_age < $cache_duration_when_saved;
    }

    /**
     * Lazy load a specific cache entry from database
     * 
     * Implements Legacy-style on-demand loading when selective loading is enabled.
     * Only loads the specific entry needed rather than full preload.
     * 
     * @param array $normalized_data Normalized cache data array
     * @return bool True if entry was found and loaded, false otherwise
     */
    private function _lazy_load_cache_entry(array $normalized_data): bool
    {
        if (!ee()->db->table_exists(self::$table_name)) {
            return false;
        }
        
        try {
            // Query for the specific entry
            $query = ee()->db->select('*')
                ->from(self::$table_name)
                ->where('site_id', $this->site_id)
                ->where('adapter_name', $this->adapter_name)
                ->where('path', $normalized_data['normalized_path'])
                ->where('image_name', $normalized_data['filename'])
                ->limit(1)
                ->get();
            
            if ($query->num_rows() > 0) {
                $entry = $query->row();
                
                // Process and add to static cache
                $processed_entry = $this->_process_single_entry($entry);
                if ($processed_entry) {
                    $cache_dir = $processed_entry['cache_dir'];
                    $filename = $processed_entry['filename'];
                    
                    // Ensure cache directory structure exists
                    if (!isset(self::$cache_log_index[$this->site_id][$this->adapter_name][$cache_dir])) {
                        self::$cache_log_index[$this->site_id][$this->adapter_name][$cache_dir] = [];
                    }
                    
                    // Add to static cache
                    self::$cache_log_index[$this->site_id][$this->adapter_name][$cache_dir][$filename] = $entry;
                    
                    $this->utilities_service->debug_log(sprintf(
                        'Lazy loaded cache entry: %s/%s',
                        $cache_dir,
                        $filename
                    ));
                    
                    return true;
                }
            }
            
            return false;
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Failed to lazy load cache entry: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Normalize and prepare path data for caching
     * 
     * Migrated from Legacy CacheManagementTrait->_normalize_cache_path_data()
     * Now optimized to handle pre-normalized relative paths from CacheStage
     * 
     * @param string $image_path Path to the image file (should be relative, but handles absolute as fallback)
     * @param string|null $cache_dir Cache directory override
     * @return array Normalized cache data array
     */
    private function _normalize_cache_path_data(string $image_path, ?string $cache_dir): array
    {
        $original_image_path = $image_path;
        
        // Optimization: CacheStage now passes pre-normalized relative paths, but keep fallback for absolute paths
        if (strpos($image_path, '/') === 0 || strpos($image_path, FCPATH) === 0) {
            // Handle absolute paths (fallback case)
            $base_path = ee()->config->item('base_path') ?? FCPATH;
            $base_path = rtrim($base_path, '/');
            
            if (strpos($image_path, $base_path) === 0) {
                $relative_path = substr($image_path, strlen($base_path));
                $relative_path = ltrim($relative_path, '/');
            } else {
                $relative_path = ltrim($image_path, '/');
            }
        } else {
            // Path is already relative (common case from CacheStage optimization)
            $relative_path = ltrim($image_path, '/');
        }
        
        $normalized_path = strtolower($relative_path);
        $path_parts = pathinfo($normalized_path);
        
        $effective_cache_dir = $this->_sanitize_cache_directory($cache_dir ?? $path_parts['dirname'] ?? self::$default_cache_dir);
        
        return [
            'original_path' => $original_image_path,
            'image_path' => $original_image_path,  // Add this for backwards compatibility
            'normalized_path' => $normalized_path, // This is now relative to base_path
            'filename' => $path_parts['basename'],
            'cache_dir' => $effective_cache_dir
        ];
    }
    
    /**
     * Perform comprehensive audit operations for a cache location (following Legacy pattern)
     * 
     * Implements the actual cache maintenance operations matching Legacy CacheManagementTrait:
     * 1. Audit each file for cache validity (freshness based on cache duration)
     * 2. Remove expired files from filesystem and database
     * 3. Add orphaned files to database if they're still fresh
     * 4. Remove orphaned database entries for missing files
     * 
     * @param string $cache_location Cache location path
     * @param string $adapter_name Filesystem adapter name
     * @param array $files_in_location Array of file information from filesystem
     * @param array $db_entries Array of database entries for this location
     * @return array Results with files_removed, entries_removed, entries_added, files_size_removed counts
     */
    private function _perform_audit_operations_for_location(string $cache_location, string $adapter_name, array $files_in_location, array $db_entries): array
    {
        $operation_results = [
            // Initial statistics (replacing _compare_files_and_database_entries functionality)
            'files_found' => 0,
            'database_entries' => 0,
            'total_size' => 0,
            'files_without_db_entries' => 0,
            // Operation results
            'files_removed' => 0,
            'entries_removed' => 0,
            'entries_added' => 0,
            'files_size_removed' => 0
        ];

        try {
            // Step 0: Collect initial statistics (replacing _compare_files_and_database_entries)
            $file_lookup = [];
            $db_lookup = [];
            
            // Index files by relative path and collect statistics
            foreach ($files_in_location as $file) {
                $file_lookup[$file['relative_path']] = $file;
                $operation_results['files_found']++;
                $operation_results['total_size'] += $file['size'];
            }
            
            // Index database entries by path
            foreach ($db_entries as $entry) {
                $db_lookup[$entry['path']] = $entry;
                $operation_results['database_entries']++;
            }
            
            // Count orphaned files (files without database entries)
            foreach ($file_lookup as $file_path => $file) {
                if (!isset($db_lookup[$file_path])) {
                    $operation_results['files_without_db_entries']++;
                }
            }

            // Step 1: Audit files for cache validity (following Legacy _audit_single_file pattern)
            foreach ($files_in_location as $file_info) {
                $file_result = $this->_audit_single_file_operations($file_info, $cache_location, $adapter_name);
                
                if ($file_result['action'] === 'removed') {
                    $operation_results['files_removed']++;
                    $operation_results['files_size_removed'] += $file_result['size_removed'];
                } elseif ($file_result['action'] === 'added_to_db') {
                    $operation_results['entries_added']++;
                }
            }
            
            // Step 2: Remove orphaned database entries (entries without corresponding files)
            foreach ($db_entries as $db_entry) {
                $file_exists = false;
                $db_file_path = $db_entry['path'];
                
                // Check if this database entry has a corresponding file
                foreach ($files_in_location as $file_info) {
                    if ($file_info['relative_path'] === $db_file_path) {
                        $file_exists = true;
                        break;
                    }
                }
                
                if (!$file_exists) {
                    // Remove orphaned database entry
                    $this->_remove_database_entry($db_entry);
                    $operation_results['entries_removed']++;
                }
            }
            
        } catch (\Exception $e) {
        }
        
        return $operation_results;
    }
    
    /**
     * Perform cache validity check against the cache index
     * 
     * Primary responsibility: Check if image entry exists in cache log database/index
     * Secondary: Validate cache freshness if duration provided (using inception_date from cache log)
     * Tertiary: Optionally verify filesystem presence for integrity
     * 
     * Note: EarlyCacheCheckStage handles additional freshness/duration checks
     * 
     * @param string $image_path Path to check in cache
     * @param int|null $cache_duration Optional cache duration in seconds (-1 for perpetual, 0 for disabled, null for existence only)
     * @return bool True if image exists in cache index (and is valid if duration provided)
     */
    private function _perform_cache_validity_check(string $image_path, ?int $cache_duration = null, ?string $connection_name = null): bool
    {
        // Normalize the image path to match the cache index structure
        $normalized_data = $this->_normalize_cache_path_data($image_path, null);
        
        // Primary check: Does entry exist in static cache index?
        $connection_name = $connection_name ?? $this->settings->get_default_connection_name();
        $cache_exists = $this->_cache_entry_exists($normalized_data, $connection_name);
        
        if (!$cache_exists) {
            // Entry doesn't exist in cache index, check filesystem as fallback
            try {
                $cache_file_exists = $this->filesystem->exists($image_path);
                if ($cache_file_exists) {
                    $this->utilities_service->debug_message("Cache found in filesystem, but missing from database: {$image_path}");
                    // Note: Without cache log entry, we can't validate freshness, so assume valid for existence-only check
                    return $cache_duration === null; // Only return true if we're just checking existence
                }            
                return false; // Entry does not exist in cache index OR in filesystem
                
            } catch (\Exception $e) {
                // Filesystem check failed
                $this->utilities_service->debug_message("Filesystem check failed for cached image {$image_path}: " . $e->getMessage());
                return false; // Assume entry does not exist in cache index
            }
        }
        
        // Entry exists in cache index
        // If no duration provided, extract it from the filename
        if ($cache_duration === null) {
            $filename = basename($image_path);
            $cache_duration = $this->_get_file_cache_duration_from_filename($filename);
            
            // If extraction failed, just return existence check
            if ($cache_duration === false) {
                return true;
            }
        }
        
        // Cache duration available - validate freshness using inception_date from cache log
        return $this->_validate_cache_freshness_from_log($normalized_data, $cache_duration, $connection_name);
    }
    
    /**
     * Performs immediate selective update of cache entry for a specific image
     * 
     * Migrated from Legacy CacheManagementTrait->_perform_immediate_selective_update()
     * Handles both database updates and static cache synchronization
     * 
     * @param string $image_path Path to the image file
     * @param float|null $processing_time Time taken to process the image in seconds
     * @param array|null $vars Additional variables/parameters associated with the image
     * @param string|null $cache_dir Directory where cached files are stored
     * @param string|null $source_path Original source path of the image
     * @param bool $force_update Whether to force update even if entry exists
     * @param bool $using_cache_copy Whether a cached copy is being used
     * @return bool True on successful update, false on failure
     */
    private function _perform_immediate_selective_update(string $image_path, ?float $processing_time, ?array $vars, ?string $cache_dir, ?string $source_path, bool $force_update, bool $using_cache_copy, string $connection_name): bool
    {
        if (!$this->_validate_update_cache_log_inputs($image_path, $processing_time, $vars)) {
            return false;
        }
        
        $normalized_data = $this->_normalize_cache_path_data($image_path, $cache_dir);
        $cache_key = $this->site_id . '|' . $connection_name . '|' . $normalized_data['normalized_path'] . '|' . $normalized_data['filename'];
        
        // Check if we've already processed this exact entry in this request
        if (isset(self::$pending_database_operations[$cache_key])) {
            return true; // Already processed or pending
        }
        
        // OPTIMIZATION: Skip existence check if we're doing initial cache population 
        // (when processing_time is provided, it's likely a new cache entry)
        $skip_existence_check = $force_update || ($processing_time !== null && $processing_time > 0);
        
        // Skip if entry exists and we're not forcing update (only check when necessary)
        if (!$skip_existence_check && $this->_cache_entry_exists($normalized_data, $connection_name)) {
            return true; // Entry already exists, no update needed
        }
        
        // Build tracker stats
        $tracker_stats = $this->_build_tracker_stats($image_path, $processing_time, $source_path);
        
        // NEW: Use batch mode for better performance
        if (self::$batch_mode) {
            // Store operation for later batch processing
            self::$pending_database_operations[$cache_key] = [
                'operation' => $force_update ? 'replace' : 'upsert',
                'normalized_data' => $normalized_data,
                'tracker_stats' => $tracker_stats,
                'vars' => $vars,
                'force_update' => $force_update
            ];
            return true;
        }
        
        try {
            // Mark this operation as being processed
            self::$pending_database_operations[$cache_key] = true;
            
            // Check if entry exists in database (with request caching)
            $existing_entry = $this->_get_existing_database_entry($normalized_data);
            
            if ($existing_entry) {
                if ($force_update) {
                    // Update existing entry with all new data (including stats and values)
                    $update_data = [
                        'stats' => $this->_safe_json_encode($tracker_stats),
                        'values' => $vars ? $this->_safe_json_encode($vars) : ''
                    ];
                    
                    ee()->db->where('site_id', $this->site_id)
                        ->where('adapter_name', $this->adapter_name)
                        ->where('adapter_type', $this->adapter_type)
                        ->where('path', $normalized_data['normalized_path'])
                        ->where('image_name', $normalized_data['filename'])
                        ->update(self::$table_name, $update_data);
                } else {
                    // Update existing entry
                    $update_data = [
                        'stats' => $this->_safe_json_encode($tracker_stats),
                        'values' => $vars ? $this->_safe_json_encode($vars) : ''
                    ];
                    
                    ee()->db->where('site_id', $this->site_id)
                        ->where('adapter_name', $this->adapter_name)
                        ->where('adapter_type', $this->adapter_type)
                        ->where('path', $normalized_data['normalized_path'])
                        ->where('image_name', $normalized_data['filename'])
                        ->update(self::$table_name, $update_data);
                }
            } else {
                // Insert new entry
                $insert_data = [
                    'site_id' => $this->site_id,
                    'adapter_name' => $this->adapter_name,
                    'adapter_type' => $this->adapter_type,
                    'path' => $normalized_data['normalized_path'],
                    'image_name' => $normalized_data['filename'],
                    'stats' => $this->_safe_json_encode($tracker_stats),
                    'values' => $vars ? $this->_safe_json_encode($vars) : ''
                ];
                
                ee()->db->insert(self::$table_name, $insert_data);
            }
            
            // Update request-level cache with new data
            $log_object = $this->_create_log_object($normalized_data, $tracker_stats, $vars);
            self::$database_operation_cache[$cache_key] = $log_object;
            
            // Update static cache with new data
            $this->_update_static_cache($normalized_data, $log_object);
            
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Process preload results and populate static cache
     * 
     * Migrated from Legacy CacheManagementTrait->_process_preload_results()
     * 
     * @param \CI_DB_result $query Database query result containing cache entries
     * @return void
     */
    private function _process_preload_results(\CI_DB_result $query, string $connection_name): void
    {
        foreach ($query->result() as $entry) {
            $processed_entry = $this->_process_single_entry($entry);
            
            if ($processed_entry) {
                $cache_dir = $processed_entry['cache_dir'];
                $filename = $processed_entry['filename'];
                
                // Ensure cache directory structure exists
                if (!isset(self::$cache_log_index[$this->site_id][$connection_name][$cache_dir])) {
                    self::$cache_log_index[$this->site_id][$connection_name][$cache_dir] = [];
                }
                
                // Update static cache
                self::$cache_log_index[$this->site_id][$connection_name][$cache_dir][$filename] = $entry;
            }
        }
    }
    
    /**
     * Process a single cache entry from database
     * 
     * @param object $entry Database entry object
     * @return array|null Processed entry data or null if invalid
     */
    private function _process_single_entry(object $entry): ?array
    {
        if (!isset($entry->path) || !isset($entry->image_name)) {
            return null;
        }
        
        $path_parts = pathinfo($entry->path);
        $cache_dir = $path_parts['dirname'] ?? self::$default_cache_dir;
        $filename = strtolower($entry->image_name);
        
        return [
            'cache_dir' => $cache_dir,
            'filename' => $filename,
            'entry' => $entry
        ];
    }
    
    /**
     * End performance profiling for a cache method
     * 
     * Completes timing and memory tracking, calculates performance metrics,
     * and logs slow operations. Maintains performance log size limits.
     * 
     * @param string $profile_id Unique profile ID from _profile_cache_method_start
     * @return void
     */
    private function _profile_cache_method_end(string $profile_id): void
    {
        // Only process if profiling is enabled and profile exists
        if (!isset(self::$performance_log[$profile_id])) {
            return;
        }
        
        $profile_data = &self::$performance_log[$profile_id];
        $profile_data['end_time'] = microtime(true);
        $profile_data['memory_end'] = memory_get_usage(true);
        $profile_data['duration'] = $profile_data['end_time'] - $profile_data['start_time'];
        $profile_data['memory_used'] = $profile_data['memory_end'] - $profile_data['memory_start'];
        
        // Log slow operations for performance monitoring
        $slow_threshold = $this->settings->get('img_cp_slow_operation_threshold', 0.1); // 100ms default
        if ($profile_data['duration'] > $slow_threshold) {
            $this->utilities_service->debug_log(sprintf(
                'SLOW CACHE OPERATION: %s took %.4fs (%.2fMB memory) - Site: %d, Adapter: %s',
                $profile_data['method'],
                $profile_data['duration'], 
                $profile_data['memory_used'] / 1024 / 1024,
                $profile_data['site_id'],
                $profile_data['adapter']
            ));
        }
        
        // Keep performance log size manageable (last 100 operations)
        if (count(self::$performance_log) > 100) {
            $oldest_key = array_key_first(self::$performance_log);
            unset(self::$performance_log[$oldest_key]);
        }
    }
    
    /**
     * Start performance profiling for a cache method
     * 
     * Begins timing and memory tracking for cache operations when profiling is enabled.
     * Only active in development environments or when specifically enabled in settings.
     * 
     * @param string $method_name Name of the cache method being profiled
     * @param string|null $connection_name Connection name for profiling context
     * @return string Unique profile ID for ending the profile session
     */
    private function _profile_cache_method_start(string $method_name, ?string $connection_name = null): string
    {
        $profile_id = uniqid('cache_profile_', true);
        
        // Only enable profiling in development or when specifically enabled
        if ($this->_is_development_environment() || $this->settings->get('img_cp_enable_performance_profiling', false)) {
            $connection_name = $connection_name ?? $this->settings->get_default_connection_name();
            
            self::$performance_log[$profile_id] = [
                'method' => $method_name,
                'start_time' => microtime(true),
                'memory_start' => memory_get_usage(true),
                'site_id' => $this->site_id,
                'adapter' => $connection_name
            ];
        }
        
        return $profile_id;
    }
    
    /**
     * Remove cache log entry for a specific file
     * 
     * @param string $cache_location Cache location
     * @param string $filename Filename
     * @return void
     */
    private function _remove_cache_log_entry(string $cache_location, string $filename): void
    {
        if (!ee()->db->table_exists(self::$table_name)) {
            return;
        }
        
        try {
            ee()->db->where('site_id', $this->site_id)
                    ->where('adapter_name', $this->adapter_name)
                    ->where('path', trim($cache_location, '/'))
                    ->where('image_name', $filename)
                    ->delete(self::$table_name);
            
            // Remove from static cache if present
            if (isset(self::$cache_log_index[$this->site_id][$this->adapter_name][$cache_location][$filename])) {
                unset(self::$cache_log_index[$this->site_id][$this->adapter_name][$cache_location][$filename]);
            }
            
        } catch (\Exception $e) {
        }
    }
    
    /**
     * Remove orphaned database entry
     * 
     * @param array $db_entry Database entry information
     * @return void
     */
    private function _remove_database_entry(array $db_entry): void
    {
        if (!ee()->db->table_exists(self::$table_name) || !isset($db_entry['id'])) {
            return;
        }
        
        try {
            ee()->db->where('id', $db_entry['id'])->delete(self::$table_name);
            
            // Remove from static cache if present
            $cache_location = $db_entry['path'] ?? '';
            $filename = $db_entry['image_name'] ?? '';
            if (isset(self::$cache_log_index[$this->site_id][$this->adapter_name][$cache_location][$filename])) {
                unset(self::$cache_log_index[$this->site_id][$this->adapter_name][$cache_location][$filename]);
            }
            
        } catch (\Exception $e) {
        }
    }
    
    /**
     * Remove expired cache file from filesystem and database
     * 
     * @param string $relative_path Relative file path
     * @param string $adapter_name Filesystem adapter name
     * @param string $cache_location Cache location
     * @param string $filename Filename
     * @return void
     */
    private function _remove_expired_cache_file(string $relative_path, string $adapter_name, string $cache_location, string $filename): void
    {
        try {
            // Remove file from filesystem using adapter
            if ($this->filesystem->exists($relative_path, $adapter_name)) {
                $this->filesystem->delete($relative_path, $adapter_name);
            }
            
            // Remove database entry
            $this->_remove_cache_log_entry($cache_location, $filename);
            
        } catch (\Exception $e) {
        }
    }
    
    /**
     * Safe JSON encoding with error handling
     * 
     * Migrated from Legacy CacheManagementTrait->_safe_json_encode()
     * 
     * @param mixed $data Data to encode
     * @return string JSON encoded string or empty string on error
     */
    private function _safe_json_encode(mixed $data): string
    {
        $encoded = json_encode($data);
        if ($encoded === false) {
            $this->utilities_service->debug_log("JSON encoding failed: " . json_last_error_msg());
            return '';
        }
        return $encoded;
    }
    
    /**
     * Sanitize and validate cache directory
     * 
     * Migrated from Legacy CacheManagementTrait->_sanitize_cache_directory()
     * 
     * @param string|null $cache_dir Cache directory to sanitize
     * @return string Sanitized cache directory path
     */
    private function _sanitize_cache_directory(?string $cache_dir): string
    {
        if ($cache_dir === null || $cache_dir === '') {
            return self::$default_cache_dir;
        }
        
        $sanitized = trim($cache_dir, '/');
        return $sanitized === '' ? self::$default_cache_dir : $sanitized;
    }
    
    /**
     * Schedule a cache update for batch processing (Legacy parity)
     * 
     * This method implements Legacy's batch update system for optimal performance.
     * Cache updates are collected and executed in a single transaction at request end.
     * 
     * @param string $image_path Path to the processed image
     * @param float|null $processing_time Time taken to process the image
     * @param array|null $vars Variables associated with the processed image  
     * @param string|null $cache_dir Cache directory override
     * @param string|null $source_path Original source path of the image
     * @param bool $force_update Force update even if entry exists
     * @param bool $using_cache_copy Whether a cached copy is being used
     * @param string|null $connection_name Connection name for the update
     * @return bool Always returns true for batch scheduling
     */
    private function _schedule_cache_update(string $image_path, ?float $processing_time = null, ?array $vars = null, ?string $cache_dir = null, ?string $source_path = null, bool $force_update = false, bool $using_cache_copy = false, ?string $connection_name = null): bool
    {
        // Build unique cache key for this update
        $cache_key = md5($image_path . '|' . ($cache_dir ?? '') . '|' . ($connection_name ?? $this->settings->get_default_connection_name()));
        
        // Build the update data structure
        $update_data = [
            'image_path' => $image_path,
            'processing_time' => $processing_time,
            'vars' => $vars,
            'cache_dir' => $cache_dir,
            'source_path' => $source_path,
            'force_update' => $force_update,
            'using_cache_copy' => $using_cache_copy,
            'connection_name' => $connection_name ?? $this->settings->get_default_connection_name(),
            'timestamp' => microtime(true)
        ];
        
        // Add to pending updates (overwrite if key exists to keep latest)
        self::$pending_cache_updates[$cache_key] = $update_data;
        
        // Schedule batch flush if not already scheduled
        if (!self::$cache_update_scheduled) {
            register_shutdown_function([$this, '_flush_cache_updates_batch']);
            self::$cache_update_scheduled = true;
        }
        
        return true;
    }
    
    /**
     * Update static cache with new entry
     * 
     * @param array $normalized_data Normalized cache data array
     * @param \stdClass $log_object Log object to store
     * @return void
     */
    private function _update_static_cache(array $normalized_data, \stdClass $log_object): void
    {
        $cache_dir = $normalized_data['cache_dir'];
        $filename = $normalized_data['filename'];
        
        // Ensure cache directory structure exists
        if (!isset(self::$cache_log_index[$this->site_id][$this->adapter_name][$cache_dir])) {
            self::$cache_log_index[$this->site_id][$this->adapter_name][$cache_dir] = [];
        }
        
        // Update static cache
        self::$cache_log_index[$this->site_id][$this->adapter_name][$cache_dir][$filename] = $log_object;
    }
    
    private function _validate_cache_check_inputs(string $image_path): bool
    {
        // Check if image path is provided and valid
        if (empty($image_path) || !is_string($image_path)) {
            $this->utilities_service->debug_log("Invalid image path provided for cache check");
            return false;
        }
        
        // Check if path is too long (reasonable limit)
        if (strlen($image_path) > 1000) {
            $this->utilities_service->debug_log("Image path too long for cache check: " . strlen($image_path) . " characters");
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate cache freshness using inception_date from cache log entry
     * 
     * Based on Legacy CacheManagementTrait::_get_file_age_timestamp and _is_cache_fresh logic
     * Retrieves inception_date from the static cache log index to avoid filesystem operations
     * 
     * @param array $normalized_data Normalized cache data (cache_dir, filename, etc.)
     * @param int $cache_duration Cache duration in seconds (-1 for perpetual, 0 for disabled)
     * @return bool True if cache is fresh, false if expired or disabled
     */
    private function _validate_cache_freshness_from_log(array $normalized_data, int $cache_duration, string $connection_name): bool
    {
        // Cache disabled
        if ($cache_duration === 0) {
            return false;
        }
        
        // Perpetual cache (-1) - always valid if entry exists
        if ($cache_duration === -1) {
            return true;
        }
        
        // Timed cache - need to get inception_date from cache log entry
        $cache_dir = $normalized_data['cache_dir'];
        $filename = strtolower($normalized_data['filename']);
        
        // Retrieve cache entry from static cache index using the correct connection name
        if (!isset(self::$cache_log_index[$this->site_id][$connection_name][$cache_dir][$filename])) {
            // Entry doesn't exist in static cache (shouldn't happen at this point)
            $this->utilities_service->debug_log("Cache entry not found in static cache index for connection: {$connection_name}, cache_dir: {$cache_dir}, filename: {$filename}");
            return false;
        }
        
        $entry = self::$cache_log_index[$this->site_id][$connection_name][$cache_dir][$filename];
        
        // Get inception_date from cache entry stats
        $inception_date = 0;
        if (property_exists($entry, 'stats') && !empty($entry->stats)) {
            $decoded_stats = json_decode($entry->stats, true);
            if ($decoded_stats && isset($decoded_stats['inception_date'])) {
                $inception_date = (int) $decoded_stats['inception_date'];
            }
        }
        
        // If no valid inception_date found, assume expired (safer approach)
        if ($inception_date === 0) {
            $this->utilities_service->debug_log("No valid inception_date found for cache entry: {$filename}");
            return false;
        }
        
        // Check if cache is still fresh
        $current_time = time();
        $file_age_seconds = $current_time - $inception_date;
        
        // Cache is valid if file age is less than cache duration
        $is_fresh = $file_age_seconds < $cache_duration;
        
        if (!$is_fresh) {
            // Debug: Cache expired
            $this->utilities_service->debug_log(sprintf(
                'Cache expired: file age %ds exceeds duration %ds (inception: %s)',
                $file_age_seconds,
                $cache_duration,
                date('Y-m-d H:i:s', $inception_date)
            ));
        } else {
            // Debug: Cache is fresh
            $this->utilities_service->debug_log(sprintf(
                'Cache is fresh: file age %ds within duration %ds',
                $file_age_seconds,
                $cache_duration
            ));
        }
        
        return $is_fresh;
    }
    
    /**
     * Validate inputs for update_cache_log method
     * 
     * @param string $image_path Path to validate
     * @param float|null $processing_time Processing time to validate
     * @param array|null $vars Variables to validate
     * @return bool True if inputs are valid, false otherwise
     */
    private function _validate_update_cache_log_inputs(string $image_path, ?float $processing_time, ?array $vars): bool
    {
        // Check if image path is provided and valid
        if (empty($image_path) || !is_string($image_path)) {
            $this->utilities_service->debug_log("Invalid image path provided for cache log update");
            return false;
        }
        
        // Check if path is too long (reasonable limit)
        if (strlen($image_path) > 1000) {
            $this->utilities_service->debug_log("Image path too long for cache log update: " . strlen($image_path) . " characters");
            return false;
        }
        
        // Validate processing time if provided
        if ($processing_time !== null && (!is_numeric($processing_time) || $processing_time < 0)) {
            $this->utilities_service->debug_log("Invalid processing time provided: " . $processing_time);
            return false;
        }
        
        return true;
    }
}
