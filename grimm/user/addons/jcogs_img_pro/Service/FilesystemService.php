<?php

/**
 * JCOGS Image Pro - Filesystem Service
 * Phase 2A: Unified filesystem operations with multi-adapter support
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 2A Filesystem Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service;

use JCOGSDesign\JCOGSImagePro\Contracts\FilesystemInterface;
use JCOGSDesign\JCOGSImagePro\Contracts\SettingsInterface;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToReadFile;

use League\Flysystem\UnableToRetrieveMetadata;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;

/**
 * Filesystem Service for JCOGS Image Pro
 * 
 * Phase 2A implementation providing unified filesystem operations
 * across multiple adapters (local, S3, R2, DigitalOcean Spaces).
 * 
 * Migrated from legacy FileSystemTrait with enhanced error handling
 * and service container integration.
 */
class FilesystemService implements FilesystemInterface 
{
    private SettingsInterface $settings;
    private Utilities $utilities_service;
    private array $adapters = [];
    private array $filesystems = [];
    private array $temp_connections = [];
    private bool $performance_profiling = false;
    private array $profiling_data = [];
    
    public function __construct(SettingsInterface $settings, Utilities $utilities_service) 
    {
        $this->settings = $settings;
        $this->utilities_service = $utilities_service;
        $this->performance_profiling = $this->settings->get('img_cp_performance_profiling', 'n') === 'y';
        
        // All connections are now resolved explicitly when methods specify them
        // No "current connection" concept - each operation specifies what it needs
    }

    /**
     * Audit cache files for a specific adapter
     * 
     * @param string $adapter_name The adapter to audit
     * @param string|null $cache_path Optional specific cache path to audit, if not provided uses default
     * @return array Result array with audit statistics
     */
    public function audit_adapter_cache(string $adapter_name, ?string $cache_path = null): array
    {
        try {
            // Handle both connection names and adapter types
            $filesystem = $this->_createFilesystemForAudit($adapter_name);
            $file_count = 0;
            $total_size = 0;
            $last_modified = null;
            
            // Use provided cache path or fall back to default cache directory
            if ($cache_path !== null) {
                $cache_dir = $cache_path;
            } else {
                $cache_dir = $this->settings->get('img_cp_cache_directory') ?? 'images/jcogs_img_pro/cache';
            }
            
            $this->utilities_service->debug_log("audit_adapter_cache: auditing {$adapter_name} with cache_dir={$cache_dir}");
            
            try {
                $contents = $filesystem->listContents($cache_dir, true);
                
                // Convert DirectoryListing to array to count and iterate
                $contents_array = iterator_to_array($contents);
                
                foreach ($contents_array as $item) {
                    if ($item['type'] === 'file') {
                        $file_count++;
                        try {
                            $file_size = $filesystem->fileSize($item['path']);
                            $total_size += $file_size;
                            
                            // Track the most recent modification time
                            try {
                                $file_modified = $filesystem->lastModified($item['path']);
                                if ($last_modified === null || $file_modified > $last_modified) {
                                    $last_modified = $file_modified;
                                }
                            } catch (\Exception $e) {
                                // Continue if we can't get last modified time
                            }
                        } catch (\Exception $e) {
                            // Continue if we can't get file size
                            $this->utilities_service->debug_log("Could not get size for file {$item['path']}: " . $e->getMessage());
                        }
                    }
                }
            } catch (\Exception $e) {
                // Directory might not exist or be empty
                $this->utilities_service->debug_log("No cache files found for adapter {$adapter_name} in path {$cache_dir}: " . $e->getMessage());
            }
            
            $this->utilities_service->debug_log("audit_adapter_cache returning: files={$file_count}, size={$total_size}, modified=" . ($last_modified ?? 'null'));
            
            return [
                'success' => true,
                'file_count' => $file_count,
                'total_size' => $total_size,
                'last_modified' => $last_modified
            ];
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_log("Error auditing cache for adapter {$adapter_name}: " . $e->getMessage());
            return [
                'success' => false,
                'file_count' => 0,
                'total_size' => 0,
                'last_modified' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Clear memory caches
     *
     * @param string|null $adapter_name Adapter name to clear, or null for all
     * @return void
     */
    public function clearAdapterCache(?string $adapter_name = null): void 
    {
        if ($adapter_name === null) {
            $this->filesystems = [];
            $this->adapters = [];
        } else {
            unset($this->filesystems[$adapter_name]);
            unset($this->adapters[$adapter_name]);
        }
    }

    /**
     * Clear file cache from a specific adapter
     * 
     * @param string $adapter_name The adapter to clear cache from
     * @return array Result array with files removed count
     */
    public function clear_adapter_cache(string $adapter_name): array
    {
        try {
            $filesystem = $this->createFilesystemAdapter($adapter_name);
            $files_removed = 0;
            
            // Get the cache directory path without prefixes for Flysystem operations
            $cache_dir = $this->get_adapter_cache_path($adapter_name, true);
            
            // Remove leading slash if present to ensure proper relative path
            $cache_dir = ltrim($cache_dir, '/');
            
            $this->utilities_service->debug_log("clear_adapter_cache: adapter={$adapter_name}, cache_dir={$cache_dir}");
            
            try {
                $contents = $filesystem->listContents($cache_dir, true);
                
                // Convert DirectoryListing to array to properly iterate
                $contents_array = iterator_to_array($contents);
                
                $this->utilities_service->debug_log("clear_adapter_cache: found " . count($contents_array) . " items in cache directory");
                
                foreach ($contents_array as $item) {
                    if ($item['type'] === 'file') {
                        try {
                            $filesystem->delete($item['path']);
                            $files_removed++;
                        } catch (\Exception $e) {
                            // Continue with other files if one fails
                            $this->utilities_service->debug_log("Failed to delete file {$item['path']}: " . $e->getMessage());
                        }
                    }
                }
            } catch (\Exception $e) {
                // Directory might not exist or be empty
                $this->utilities_service->debug_log("No cache files found for adapter {$adapter_name}: " . $e->getMessage());
            }
            
            return [
                'success' => true,
                'files_removed' => $files_removed
            ];
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_log("Error clearing cache for adapter {$adapter_name}: " . $e->getMessage());
            return [
                'success' => false,
                'files_removed' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Copy file from source to destination
     *
     * @param string $source_path Source file path
     * @param string $destination_path Destination file path
     * @param string|null $source_adapter Source adapter
     * @param string|null $destination_adapter Destination adapter
     * @return bool True on success
     */
    public function copy(string $source_path, string $destination_path, ?string $source_adapter = null, ?string $destination_adapter = null): bool 
    {
        try {
            $content = $this->read($source_path, $source_adapter);
            return $this->write($destination_path, $content, $destination_adapter);
        } catch (FilesystemException $e) {
            return false;
        }
    }
    
    /**
     * Create directory
     *
     * @param string $path Directory path
     * @param string|null $adapter_name Adapter to use
     * @return bool True on success
     */
    public function createDirectory(string $path, ?string $adapter_name = null): bool 
    {
        try {
            $adapter_name = $adapter_name ?? $this->settings->get_default_connection_name();
            $filesystem = $this->createFilesystemAdapter($adapter_name);
            $filesystem->createDirectory($path);

            // Check if directory exists
            $dir_exists = false;
            // For local adapter, check with is_dir and chmod if needed
            $connection_config = $this->getConnectionConfig($adapter_name);
            if ($connection_config && $connection_config['type'] === 'local') {
                $root_path = rtrim($connection_config['config']['root'] ?? ee()->config->item('base_path') ?? FCPATH, '/');
                $full_path = $root_path . '/' . ltrim($path, '/');
                if (is_dir($full_path)) {
                    $dir_exists = true;
                    $perms = substr(sprintf('%o', fileperms($full_path)), -3);
                    if ($perms !== '755') {
                        chmod($full_path, 0755);
                    }
                }
            } else {
                // For remote adapters, assume success if no exception
                $dir_exists = $filesystem->directoryExists($path);
            }
            return $dir_exists;
        } catch (FilesystemException $e) {
            return false;
        }
    }
    
    /**
     * Legacy wrapper for Create filesystem adapter based on configuration
     *
     * @param string $connection_name Connection name or legacy adapter name for backward compatibility
     * @return Filesystem Configured filesystem instance
     * @throws \InvalidArgumentException If adapter configuration is invalid
     */
    public function createFilesystemAdapter(string $connection_name): Filesystem 
    {
        $start_time = $this->performance_profiling ? microtime(true) : 0;
        
        // ALWAYS check static cache first - this is the key to replicating Legacy performance
        // Legacy uses static::$filesystems which persists across all instances
        try {
            $cached_filesystem = ImageProcessingPipeline::get_cached_filesystem($connection_name);
            if ($cached_filesystem !== null) {
                // Debug: Log static cache hit
                $this->utilities_service->debug_log("[JCOGS_IMG_PRO_DEBUG] Static filesystem cache HIT for connection: {$connection_name}");
                return $cached_filesystem;
            }
        } catch (\Throwable $e) {
            // Static cache access failed, continue with normal creation
            $this->utilities_service->debug_log("[JCOGS_IMG_PRO_DEBUG] Static cache access failed: " . $e->getMessage());
        }
        
        // Debug: Log static cache miss - need to create new filesystem
        $this->utilities_service->debug_log("[JCOGS_IMG_PRO_DEBUG] Static filesystem cache MISS for connection: {$connection_name} - creating new adapter");
        
        // Get connection configuration (named connection or legacy fallback)
        $connection_config = $this->getConnectionConfig($connection_name);
        if (!$connection_config) {
            throw new \InvalidArgumentException("1 Connection configuration not found: {$connection_name}");
        }
        
        $adapter_type = $connection_config['type'];
        $config = $connection_config['config'];
        
        switch ($adapter_type) {
            case 'local':
                // For local adapter, use the root path from config or fallback to base_path
                $root_path = $config['root'] ?? ee()->config->item('base_path') ?? FCPATH;
                $root_path = rtrim($root_path, '/');
                
                // Debug: Log the root path being used
                $this->utilities_service->debug_log("Pro createFilesystemAdapter: local adapter root_path={$root_path}");
                
                $adapter = new LocalFilesystemAdapter(
                    $root_path,
                    null, // use default permissions
                    LOCK_EX
                );
                break;
                
            case 's3':
                $adapter = $this->_createS3Adapter($config);
                break;
                
            case 'r2':
                $adapter = $this->_createR2Adapter($config);
                break;
                
            case 'dospaces':
                $adapter = $this->_createDoSpacesAdapter($config);
                break;
                
            default:
                throw new \InvalidArgumentException("Unsupported adapter type: {$adapter_type}");
        }
        
        $filesystem = new Filesystem($adapter);
        
        // Test adapter connection (mirrors Legacy behavior)
        if ($adapter_type !== 'local') {
            $this->_test_filesystem_connectivity($filesystem, $connection_name);
        }
        
        // Store in both instance and static caches
        $this->filesystems[$connection_name] = $filesystem;
        
        // Calculate adapter URL for static cache
        $adapter_url = null;
        if ($adapter_type === 'local') {
            $adapter_url = ee()->config->item('site_url');
        } else {
            // For named connections, get URL from connection config
            $adapter_url_value = $config['url'] ?? '';
            if ($adapter_url_value) {
                $adapter_url = rtrim($adapter_url_value, '/') . '/';
            }
        }
        
        // Store in static cache (replicates Legacy behavior) - ALWAYS store regardless of how service was instantiated
        try {
            ImageProcessingPipeline::set_cached_filesystem($connection_name, $filesystem, $adapter_url);
            // Debug: Log static cache storage
            $this->utilities_service->debug_log("[JCOGS_IMG_PRO_DEBUG] Stored filesystem adapter in static cache: {$connection_name}");
        } catch (\Throwable $e) {
            // Static cache storage failed, but continue
            $this->utilities_service->debug_log("[JCOGS_IMG_PRO_DEBUG] Failed to store in static cache: " . $e->getMessage());
        }

        if ($this->performance_profiling) {
            $this->profiling_data['create_adapter'][] = [
                'adapter' => $connection_name,
                'duration' => microtime(true) - $start_time
            ];
        }

        return $filesystem;
    }    
    
    /**
     * Create filesystem adapter with validity test
     *
     * @param string $adapter_name Adapter name
     * @param bool $validity_test Run validity test
     * @return FilesystemOperator|bool Filesystem or false on failure
     */
    public function create_filesystem_adapter(string $adapter_name = 'local', bool $validity_test = false): FilesystemOperator|false 
    {
        try {
            $filesystem = $this->createFilesystemAdapter($adapter_name);
            
            if ($validity_test) {
                // Test basic operations
                $test_path = '.jcogs_test_' . uniqid();
                $test_content = 'test';
                
                try {
                    $filesystem->write($test_path, $test_content);
                    $read_content = $filesystem->read($test_path);
                    $filesystem->delete($test_path);
                    
                    return ($read_content === $test_content) ? $filesystem : false;
                } catch (FilesystemException $e) {
                    return false;
                }
            }
            
            return $filesystem;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Delete file
     *
     * @param string $path File path
     * @param string|null $adapter_name Adapter to use
     * @return bool True on success
     */
    public function delete(string $path, ?string $adapter_name = null): bool 
    {
        try {
            $adapter_name = $adapter_name ?? $this->settings->get('img_cp_flysystem_adapter', 'local');
            $filesystem = $this->createFilesystemAdapter($adapter_name);
            $filesystem->delete($path);
            return true;
        } catch (FilesystemException $e) {
            return false;
        }
    }
    
    /**
     * Delete directory
     *
     * @param string $path Directory path
     * @param string|null $adapter_name Adapter to use
     * @return bool True on success
     */
    public function deleteDirectory(string $path, ?string $adapter_name = null): bool 
    {
        try {
            $adapter_name = $adapter_name ?? $this->settings->get('img_cp_flysystem_adapter', 'local');
            $filesystem = $this->createFilesystemAdapter($adapter_name);
            $filesystem->deleteDirectory($path);
            return true;
        } catch (FilesystemException $e) {
            return false;
        }
    }
    
    /**
     * Legacy method: Check if directory exists
     *
     * @param string $path Directory path
     * @param string|null $adapter_name Adapter to use
     * @return bool True if directory exists
     */
    public function directoryExists(string $path, ?string $adapter_name = null): bool 
    {
        try {
            // Use explicit connection or fallback to default (safety net)
            if ($adapter_name === null) {
                $adapter_name = $this->settings->get_default_connection_name();
                $this->utilities_service->debug_log("Pro directoryExists: No connection specified, falling back to default: {$adapter_name}");
            }
            
            $filesystem = $this->createFilesystemAdapter($adapter_name);
            return $filesystem->directoryExists($path);
        } catch (FilesystemException $e) {
            return false;
        }
    }
    
    /**
     * Check if file exists
     *
     * @param string $path File path
     * @param string|null $connection_name Connection to use (nullable for backward compatibility)
     * @return bool True if file exists
     */
    public function exists(string $path, ?string $connection_name = null): bool 
    {
        try {
            // Use explicit connection or fallback to default (safety net)
            if ($connection_name === null) {
                $connection_name = $this->settings->get_default_connection_name();
                $this->utilities_service->debug_log("Pro exists: No connection specified, falling back to default: {$connection_name}");
            }
            
            $filesystem = $this->createFilesystemAdapter($connection_name);
            return $filesystem->fileExists($path);
        } catch (FilesystemException $e) {
            return false;
        }
    }

    /**
     * Get connection configuration by name
     * 
     * @param string $connection_name Name of the connection
     * @return array|null Connection configuration or null if not found
     */
    public function getConnectionConfig(string $connection_name): ?array
    {
        // Check if it's a temporary connection first
        if (isset($this->temp_connections[$connection_name])) {
            return $this->temp_connections[$connection_name];
        }
        
        // Try to get from named connections
        $connection = $this->settings->getNamedConnection($connection_name, true); // with decryption
        if ($connection) {
            return $connection;
        }
        
        // Legacy fallback - try to resolve as legacy adapter name
        if (str_starts_with($connection_name, 'legacy_')) {
            $adapter_type = substr($connection_name, 7); // Remove 'legacy_' prefix
            return [
                'name' => $connection_name,
                'type' => $adapter_type,
                'is_legacy' => true,
                'config' => $this->settings->getFilesystemConfig($adapter_type)
            ];
        }
        
        return null;
    }
    
    /**
     * Legacy method: Get file size
     *
     * @param string $path File path
     * @param string|null $adapter_name Adapter to use
     * @return int|bool File size or false on failure
     */
    public function filesize(string $path, ?string $adapter_name = null): int|false 
    {
        try {
            return $this->getSize($path, $adapter_name);
        } catch (FilesystemException $e) {
            return false;
        }
    }

    /**
     * Get local copy of image file (optimized for memory-first approach)
     *
     * Performance-optimized version that avoids disk writes when possible.
     * Only creates temporary files when a file path is specifically required.
     *
     * @param string $source_path Source file path
     * @param string $connection_name Connection to use (required)
     * @param bool $need_file_path Whether a file path is required (vs. content)
     * @return string|array Local file path, or ['content' => $data, 'temp_path' => $path] for cleanup
     * @throws FilesystemException If file cannot be retrieved
     */
    public function getLocalCopyOfImage(string $source_path, string $connection_name, bool $need_file_path = true): string|array 
    {
        $start_time = $this->performance_profiling ? microtime(true) : 0;
        
        // If already local, return path directly
        if ($connection_name === 'local' || str_starts_with($connection_name, 'legacy_local')) {
            $config = $this->getConnectionConfig($connection_name);
            if (!$config) {
                throw new UnableToReadFile("2 Connection configuration not found: {$connection_name}");
            }
            
            $full_path = rtrim($config['config']['root'] ?? '', '/') . '/' . ltrim($source_path, '/');
            
            if (file_exists($full_path)) {
                return $full_path;
            }
            throw new UnableToReadFile("Local file not found: {$full_path}");
        }
        
        // For remote adapters, get content first (always in memory)
        $filesystem = $this->createFilesystemAdapter($connection_name);
        
        if (!$filesystem->fileExists($source_path)) {
            throw new UnableToReadFile("Source file not found: {$source_path}");
        }
        
        try {
            $content = $filesystem->read($source_path);
            
            // If caller doesn't need file path, return content directly (avoid disk I/O)
            if (!$need_file_path) {
                if ($this->performance_profiling) {
                    $this->profiling_data['get_local_copy'][] = [
                        'source' => $source_path,
                        'connection' => $connection_name,
                        'size' => strlen($content),
                        'disk_write' => false,
                        'duration' => microtime(true) - $start_time
                    ];
                }
                
                return ['content' => $content, 'temp_path' => null];
            }
            
            // Only create temporary file when file path is specifically needed
            $temp_dir = sys_get_temp_dir();
            $temp_filename = 'jcogs_img_' . uniqid() . '_' . basename($source_path);
            $temp_path = $temp_dir . '/' . $temp_filename;
            
            file_put_contents($temp_path, $content);
            
            if ($this->performance_profiling) {
                $this->profiling_data['get_local_copy'][] = [
                    'source' => $source_path,
                    'connection' => $connection_name,
                    'size' => strlen($content),
                    'disk_write' => true,
                    'temp_path' => $temp_path,
                    'duration' => microtime(true) - $start_time
                ];
            }
            
            return $temp_path;
            
        } catch (FilesystemException $e) {
            throw $e;
        }
    }
    
    /**
     * Get image content directly in memory (zero disk I/O for remote files)
     * 
     * High-performance method that never writes to disk, optimized for processing
     * workflows where only file content is needed, not file paths.
     *
     * @param string $source_path Source file path
     * @param string|null $connection_name Connection to use (nullable for backward compatibility, will fallback to default)
     * @return string File content
     * @throws FilesystemException If file cannot be retrieved
     */
    public function getImageContent(string $source_path, ?string $connection_name = null): string 
    {
        $start_time = $this->performance_profiling ? microtime(true) : 0;
        
        // Use explicit connection or fallback to default (safety net)
        if ($connection_name === null) {
            $connection_name = $this->settings->get_default_connection_name();
            $this->utilities_service->debug_log("Pro getImageContent: No connection specified, falling back to default: {$connection_name}");
        }
        
        // Debug: Log the attempt to read image content
        $this->utilities_service->debug_log("Pro getImageContent: source_path={$source_path}, connection_name={$connection_name}");
        
        try {
            // For all adapters (including local), use read() - no temp files
            $filesystem = $this->createFilesystemAdapter($connection_name);
            
            // Debug: Check if the file exists first
            $file_exists = $filesystem->fileExists($source_path);
            $this->utilities_service->debug_log("Pro getImageContent: file_exists={$file_exists} for path: {$source_path}");
            
            if (!$file_exists) {
                throw new UnableToReadFile("File does not exist: {$source_path}");
            }
            
            $content = $filesystem->read($source_path);
            $this->utilities_service->debug_log("Pro getImageContent: Successfully read " . strlen($content) . " bytes from {$source_path}");
            
            if ($this->performance_profiling) {
                $this->profiling_data['get_image_content'][] = [
                    'source' => $source_path,
                    'connection' => $connection_name,
                    'size' => strlen($content),
                    'disk_write' => false,
                    'duration' => microtime(true) - $start_time
                ];
            }
            
            return $content;
            
        } catch (FilesystemException $e) {
            $this->utilities_service->debug_log("Pro getImageContent: Filesystem error: " . $e->getMessage());
            throw new UnableToReadFile("Unable to read image content from: {$source_path} - " . $e->getMessage());
        }
    }
    
    /**
     * Get file from remote URL (Pro implementation - eliminates Legacy dependency)
     * 
     * Migrated from Legacy Utilities->get_file_from_remote() with enhanced error handling.
     * Uses CURL with defensive settings and file_get_contents fallback.
     *
     * @param string $url Remote URL
     * @param array|null $post_data Optional POST data
     * @param array|null $headers Optional custom headers
     * @param string $encoding POST encoding ('form' or 'json')
     * @return string|false Remote file content or false on failure
     */
    public function getFileFromRemote(string $url, ?array $post_data = null, ?array $headers = null, string $encoding = 'form'): string|false 
    {
        if (empty($url)) {
            return false;
        }
        
        $start_time = $this->performance_profiling ? microtime(true) : 0;
        
        // Try CURL first (Legacy approach)
        $content = $this->_getRemoteContentCurl($url, $post_data, $headers, $encoding);
        
        if (!$content) {
            // Fallback to file_get_contents if CURL fails
            $content = $this->_getRemoteContentFGC($url, $post_data, $headers, $encoding);
        }
        
        if ($this->performance_profiling && $content !== false) {
            $this->profiling_data['get_remote_file'][] = [
                'url' => $url,
                'size' => strlen($content),
                'has_post_data' => !empty($post_data),
                'method' => $content ? 'success' : 'failed',
                'duration' => microtime(true) - $start_time
            ];
        }
        
        return $content;
    }
    
    /**
     * Read file contents
     *
     * @param string $path File path
     * @param string|null $connection_name Connection to use (nullable for backward compatibility)
     * @return string File contents
     * @throws UnableToReadFile If file cannot be read
     */
    public function read(string $path, ?string $connection_name = null): string 
    {
        // Use explicit connection or fallback to default (safety net)
        if ($connection_name === null) {
            $connection_name = $this->settings->get_default_connection_name();
            $this->utilities_service->debug_log("Pro read: No connection specified, falling back to default: {$connection_name}");
        }
        
        $filesystem = $this->createFilesystemAdapter($connection_name);
        return $filesystem->read($path);
    }
    
    /**
     * Write file contents using Legacy robust approach
     * 
     * Migrated from Legacy FileSystemTrait->write() with proven multi-attempt logic,
     * post-write verification, and S3/DigitalOcean permissions handling.
     *
     * @param string $path File path
     * @param string $contents File contents
     * @param int $attempts Current attempt number (for recursion)
     * @param string|null $connection_name Connection to use (nullable for backward compatibility)
     * @return bool True on success
     */
    public function write(string $path, string $contents, int $attempts = 0, ?string $connection_name = null): bool 
    {
        if (empty($path)) {
            // No path given, so bail out
            return false;
        }
        
        // Use explicit connection or fallback to default (safety net)
        if ($connection_name === null) {
            $connection_name = $this->settings->get_default_connection_name();
            $this->utilities_service->debug_log("Pro write: No connection specified, falling back to default: {$connection_name}");
        }
        
        // Get filesystem for this operation (with caching)
        $filesystem = $this->createFilesystemAdapter($connection_name);
        
        if (!$filesystem) {
            return false;
        }

        // Maximum number of attempts to write the file (Legacy proven value)
        $max_attempts = 3;
    
        try {
            // Write the file to the filesystem
            $filesystem->write($path, $contents);
            
            // Critical: Check if the file exists (Legacy post-write verification)
            if ($this->exists($path, $connection_name)) {
                // Legacy: Set visibility for S3/DigitalOcean specifically
                $connection_config = $this->getConnectionConfig($connection_name);
                $adapter_type = $connection_config['type'] ?? '';
                
                if ($adapter_type === 's3' || $adapter_type === 'dospaces') {
                    try {
                        $filesystem->setVisibility($path, 'public');
                    } catch (FilesystemException $e) {
                        // Handle the error
                        $this->utilities_service->debug_log("Failed to set visibility for {$path}: " . $e->getMessage());
                        return false;
                    }
                }
                
                // If the file exists, log a success message
                $this->utilities_service->debug_log("File written successfully: {$path}");
                return true;
            } else {
                // If the file does not exist, increment the attempts counter
                $attempts++;
    
                // If the maximum number of attempts has not been reached, try writing again
                if ($attempts < $max_attempts) {
                    return $this->write($path, $contents, $attempts, $connection_name);
                } else {
                    // If the maximum number of attempts has been reached, log an error message
                    $this->utilities_service->debug_log("Failed to write file after {$max_attempts} attempts: {$path}");
                    return false;
                }
            }
        } catch (\Exception $e) {
            // Log the exception message
            $this->utilities_service->debug_log("Filesystem write error for {$path}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Write Imagine image to filesystem using Legacy robust approach
     * 
     * Migrated from Legacy ImageProcessingTrait->_save_image() with proper content extraction
     * and robust write logic. Uses Imagine->get() method like Legacy instead of temp files.
     *
     * @param \Imagine\Image\ImageInterface $image Image to save
     * @param string $path Destination path  
     * @param array $options Save options (quality, etc.)
     * @param string|null $adapter_name Adapter to use
     * @return bool True on success
     */
    public function writeImage(\Imagine\Image\ImageInterface $image, string $path, array $options = [], ?string $adapter_name = null): bool 
    {
        $adapter_name = $adapter_name ?? $this->settings->get_default_connection_name();
        
        try {
            // Legacy approach: Use Imagine->get() to extract binary content directly
            // Determine format from file extension
            $path_info = pathinfo($path);
            $format = strtolower($path_info['extension'] ?? 'jpg');
            
            // Map common extensions to Imagine format names
            $format_map = [
                'jpeg' => 'jpg',
                'jpg' => 'jpg',
                'png' => 'png',
                'gif' => 'gif',
                'webp' => 'webp',
                'avif' => 'avif'
            ];
            
            $save_format = $format_map[$format] ?? 'jpg';
            
            // Extract image content using Imagine (Legacy approach)
            $image_content = $image->get($save_format, $options);
            
            if (empty($image_content)) {
                $this->utilities_service->debug_log("Failed to extract image content for format: {$save_format}");
                return false;
            }
            
            // Use the robust write method with multi-attempt logic and verification
            return $this->write($path, $image_content, 0, $adapter_name);
            
        } catch (\Imagine\Exception\Exception $e) {
            // Catch specific Imagine exceptions (Legacy pattern)
            $this->utilities_service->debug_log("Imagine error during image save: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            $this->utilities_service->debug_log("General error during image save to {$path}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Move file from source to destination
     *
     * @param string $source_path Source file path
     * @param string $destination_path Destination file path
     * @param string|null $source_adapter Source adapter
     * @param string|null $destination_adapter Destination adapter
     * @return bool True on success
     */
    public function move(string $source_path, string $destination_path, ?string $source_adapter = null, ?string $destination_adapter = null): bool 
    {
        if ($this->copy($source_path, $destination_path, $source_adapter, $destination_adapter)) {
            return $this->delete($source_path, $source_adapter);
        }
        return false;
    }
    
    /**
     * Get file size
     *
     * @param string $path File path
     * @param string|null $adapter_name Adapter to use
     * @return int File size in bytes
     * @throws UnableToRetrieveMetadata If size cannot be retrieved
     */
    public function getSize(string $path, ?string $adapter_name = null): int 
    {
        $adapter_name = $adapter_name ?? $this->settings->get('img_cp_flysystem_adapter', 'local');
        $filesystem = $this->createFilesystemAdapter($adapter_name);
        return $filesystem->fileSize($path);
    }
    
    /**
     * Get file MIME type
     *
     * @param string $path File path
     * @param string|null $adapter_name Adapter to use
     * @return string MIME type
     * @throws UnableToRetrieveMetadata If MIME type cannot be retrieved
     */
    public function getMimeType(string $path, ?string $adapter_name = null): string 
    {
        $adapter_name = $adapter_name ?? $this->settings->get('img_cp_flysystem_adapter', 'local');
        $filesystem = $this->createFilesystemAdapter($adapter_name);
        return $filesystem->mimeType($path);
    }
    
    /**
     * Get file last modified timestamp
     *
     * @param string $path File path
     * @param string|null $adapter_name Adapter to use
     * @return int Unix timestamp
     * @throws UnableToRetrieveMetadata If timestamp cannot be retrieved
     */
    public function getLastModified(string $path, ?string $adapter_name = null): int 
    {
        $adapter_name = $adapter_name ?? $this->settings->get('img_cp_flysystem_adapter', 'local');
        $filesystem = $this->createFilesystemAdapter($adapter_name);
        return $filesystem->lastModified($path);
    }
    
    /**
     * List directory contents
     *
     * @param string $path Directory path
     * @param bool $recursive Include subdirectories
     * @param string|null $adapter_name Adapter to use
     * @return array List of files and directories
     */
    public function listContents(string $path, bool $recursive = false, ?string $adapter_name = null): array 
    {
        $adapter_name = $adapter_name ?? $this->settings->get('img_cp_flysystem_adapter', 'local');
        $filesystem = $this->createFilesystemAdapter($adapter_name);
        
        $listing = $filesystem->listContents($path, $recursive);
        $results = [];
        
        foreach ($listing as $item) {
            $results[] = $item->jsonSerialize();
        }
        
        return $results;
    }
    
    /**
     * Get public URL for file
     *
     * @param string $path File path
     * @param string|null $adapter_name Adapter to use
     * @return string Public URL
     */
    public function getPublicUrl(string $path, ?string $adapter_name = null): string 
    {
        $adapter_name = $adapter_name ?? $this->settings->get('img_cp_flysystem_adapter', 'local');
        
        // Check if this is a named connection first
        $named_connection = $this->settings->getNamedConnection($adapter_name, true);
        if ($named_connection !== null) {
            $config = $named_connection['config'];
            $config['type'] = $named_connection['type']; // Add type from connection
        } else {
            // Fall back to legacy filesystem config
            $config = $this->settings->getFilesystemConfig($adapter_name);
        }
        
        if (empty($config) || !isset($config['type'])) {
            throw new \InvalidArgumentException("Invalid adapter configuration for: {$adapter_name}");
        }
        
        switch ($config['type']) {
            case 'local':
                // For local connections, use base_url for URL generation
                $base_url = $config['url'] ?? ee()->config->item('base_url');
                return rtrim($base_url, '/') . '/' . ltrim($path, '/');
                
            case 's3':
            case 'r2':
            case 'dospaces':
                $base_url = rtrim($config['url'], '/');
                $server_path = trim($config['server_path'] ?? '/', '/');
                $file_path = ltrim($path, '/');
                
                if ($server_path) {
                    return "{$base_url}/{$server_path}/{$file_path}";
                }
                return "{$base_url}/{$file_path}";
                
            default:
                throw new \InvalidArgumentException("Cannot generate URL for adapter type: {$config['type']}");
        }
    }
    
    /**
     * Get performance profiling data
     *
     * @return array Profiling data
     */
    public function getProfilingData(): array 
    {
        return $this->profiling_data;
    }
    
    /**
     * Clear performance profiling data
     *
     * @return void
     */
    public function clearProfilingData(): void 
    {
        $this->profiling_data = [];
    }
    
    /**
     * List available filesystem adapters based on configuration
     * 
     * Returns array of adapter names that are configured and available
     * Used by control panel to display cache location options
     * 
     * @return array Array of available adapter names
     */
    public function list_available_adapters(): array
    {
        $adapters = ['local']; // Local is always available
        
        // Check for S3 configuration
        $s3_config = $this->settings->getFilesystemConfig('s3');
        if (!empty($s3_config['key']) && !empty($s3_config['secret']) && !empty($s3_config['bucket'])) {
            $adapters[] = 's3';
        }
        
        // Check for R2 configuration
        $r2_config = $this->settings->getFilesystemConfig('r2');
        if (!empty($r2_config['account_id']) && !empty($r2_config['key']) && !empty($r2_config['secret']) && !empty($r2_config['bucket'])) {
            $adapters[] = 'r2';
        }
        
        // Check for DigitalOcean Spaces configuration
        $dospaces_config = $this->settings->getFilesystemConfig('dospaces');
        if (!empty($dospaces_config['key']) && !empty($dospaces_config['secret']) && !empty($dospaces_config['space'])) {
            $adapters[] = 'dospaces';
        }
        
        // Check for other cloud providers if configured (future support)
        $azure_config = $this->settings->getFilesystemConfig('azure');
        if (!empty($azure_config['account']) && !empty($azure_config['key'])) {
            $adapters[] = 'azure';
        }
        
        $gcs_config = $this->settings->getFilesystemConfig('gcs');
        if (!empty($gcs_config['project_id']) && !empty($gcs_config['key_file'])) {
            $adapters[] = 'gcs';
        }
        
        return $adapters;
    }
    
    /**
     * Test connection to a specific adapter
     * 
     * Attempts to create the adapter and perform a basic operation
     * Used by control panel to show adapter status
     * 
     * @param string $adapter_name Name of the adapter to test
     * @return bool True if adapter is working, false otherwise
     */
    public function test_adapter_connection(string $adapter_name): bool
    {
        try {
            $filesystem = $this->createFilesystemAdapter($adapter_name);
            return $this->_test_filesystem_connectivity($filesystem, $adapter_name);
        } catch (\Exception $e) {
            // Log error if debugging is enabled
            $this->utilities_service->debug_log("JCOGS Image Pro (create_filesystem_adapter) Flysystem: {$adapter_name} adapter test failed - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get the cache path for a specific adapter
     * 
     * Returns the base cache directory path for the specified adapter
     * Used by control panel to show where cache files are stored
     * 
     * @param string $adapter_name Name of the adapter
     * @return string Cache path for the adapter
     */
    /**
     * Get cache path for a named connection or legacy adapter
     * 
     * Updated to support named connections. First tries to resolve as named connection,
     * then falls back to legacy adapter logic for backward compatibility.
     * 
     * @param string $adapter_name Named connection name or legacy adapter name
     * @param bool $return_local_path If true, returns path without prefixes for Flysystem operations
     * @return string Cache path for the connection/adapter
     */
    public function get_adapter_cache_path(string $adapter_name, bool $return_local_path = false): string
    {
        // First try to get as named connection
        try {
            $connection = $this->settings->getNamedConnection($adapter_name);
            if ($connection) {
                $config = $connection['config'] ?? [];
                $type = $connection['type'] ?? 'local';
                
                switch ($type) {
                    case 'local':
                        $cache_dir = $config['cache_directory'] ?? 'images/jcogs_img_pro/cache';
                        return $return_local_path ? $cache_dir : FCPATH . $cache_dir;
                        
                    case 's3':
                        $bucket = $config['bucket'] ?? 'unknown';
                        $server_path = $config['server_path'] ?? '';
                        $path = !empty($server_path) ? $server_path : 'cache';
                        return $return_local_path ? $path : "s3://{$bucket}/{$path}";
                        
                    case 'r2':
                        $bucket = $config['bucket'] ?? 'unknown';
                        $server_path = $config['server_path'] ?? '';
                        $path = !empty($server_path) ? $server_path : 'cache';
                        return $return_local_path ? $path : "r2://{$bucket}/{$path}";
                        
                    case 'dospaces':
                        $space = $config['space'] ?? 'unknown';
                        $server_path = $config['server_path'] ?? '';
                        $path = !empty($server_path) ? $server_path : 'cache';
                        return $return_local_path ? $path : "dospaces://{$space}/{$path}";
                        
                    case 'azure':
                        $container = $config['container'] ?? 'images';
                        $server_path = $config['server_path'] ?? '';
                        $path = !empty($server_path) ? $server_path : 'cache';
                        return $return_local_path ? $path : "azure://{$container}/{$path}";
                        
                    case 'gcs':
                        $bucket = $config['bucket'] ?? 'unknown';
                        $server_path = $config['server_path'] ?? '';
                        $path = !empty($server_path) ? $server_path : 'cache';
                        return $return_local_path ? $path : "gcs://{$bucket}/{$path}";
                        
                    default:
                        $cache_dir = $config['cache_directory'] ?? 'images/jcogs_img_pro/cache';
                        return $return_local_path ? $cache_dir : $cache_dir;
                }
            }
        } catch (\Exception $e) {
            // Named connection lookup failed, fall back to legacy logic
        }
        
        // Fall back to legacy adapter logic for backward compatibility
        $cache_dir = $this->settings->get('img_cp_cache_directory') ?? 'images/jcogs_img_pro/cache';
        
        switch ($adapter_name) {
            case 'local':
                return $return_local_path ? $cache_dir : FCPATH . $cache_dir;
                
            case 's3':
                $s3_config = $this->settings->getFilesystemConfig('s3');
                $bucket = $s3_config['bucket'] ?? 'unknown';
                return $return_local_path ? $cache_dir : "s3://{$bucket}/{$cache_dir}";
                
            case 'r2':
                $r2_config = $this->settings->getFilesystemConfig('r2');
                $bucket = $r2_config['bucket'] ?? 'unknown';
                return $return_local_path ? $cache_dir : "r2://{$bucket}/{$cache_dir}";
                
            case 'dospaces':
                $dospaces_config = $this->settings->getFilesystemConfig('dospaces');
                $space = $dospaces_config['space'] ?? 'unknown';
                return $return_local_path ? $cache_dir : "dospaces://{$space}/{$cache_dir}";
                
            case 'azure':
                $azure_config = $this->settings->getFilesystemConfig('azure');
                $container = $azure_config['container'] ?? 'images';
                return $return_local_path ? $cache_dir : "azure://{$container}/{$cache_dir}";
                
            case 'gcs':
                $gcs_config = $this->settings->getFilesystemConfig('gcs');
                $bucket = $gcs_config['bucket'] ?? 'unknown';
                return $return_local_path ? $cache_dir : "gcs://{$bucket}/{$cache_dir}";
                
            default:
                // Handle legacy_local and other legacy patterns
                if (str_starts_with($adapter_name, 'legacy_')) {
                    return $return_local_path ? $cache_dir : FCPATH . $cache_dir;
                }
                return $return_local_path ? $cache_dir : $cache_dir;
        }
    }
    
    /**
     * Get the base path for a specific adapter
     * 
     * Returns the root/base path that the adapter uses for path prefixing
     * This allows conversion between absolute and relative paths for Legacy compatibility
     * 
     * @param string $adapter_name Name of the adapter
     * @return string Base path for the adapter
     */
    public function get_adapter_base_path(string $adapter_name): string
    {
        switch ($adapter_name) {
            case 'local':
                $config = $this->settings->getFilesystemConfig($adapter_name);
                return rtrim($config['root'] ?? ee()->config->item('base_path') ?? FCPATH, '/');
                
            case 's3':
            case 'r2':
            case 'dospaces':
            case 'azure':
            case 'gcs':
                // Cloud adapters don't have local base paths
                return '';
                
            default:
                return '';
        }
    }
    
    /**
     * Legacy method: Get local copy of image
     *
     * @param string $path File path
     * @param string|null $adapter_name Adapter to use
     * @return string|bool Local file path or false on failure
     */
    public function get_a_local_copy_of_image(string $path, ?string $adapter_name = null): array|false 
    {
        // Clean up the path for security
        $cleaned_path = ee('Security/XSS')->clean($path);
        if (!$cleaned_path) {
            $this->utilities_service->debug_log("Pro get_a_local_copy_of_image: XSS clean failed for path: {$path}");
            return false;
        }
        
        // Use explicit connection or fallback to default (safety net)
        if ($adapter_name === null) {
            $adapter_name = $this->settings->get_default_connection_name();
            $this->utilities_service->debug_log("Pro get_a_local_copy_of_image: No connection specified, falling back to default: {$adapter_name}");
        }
        
        // Parse URL to determine if local or remote
        $parse_src = parse_url($cleaned_path);
        $base_url = ee()->config->item('base_url');
        
        // Debug: Log the URL parsing
        $this->utilities_service->debug_log("Pro get_a_local_copy_of_image: ENTRY path={$cleaned_path}, adapter={$adapter_name}, base_url={$base_url}");
        $this->utilities_service->debug_log("Pro get_a_local_copy_of_image: parse_src=" . json_encode($parse_src));
        
        // Check if this is a local file (Legacy logic with strpos)
        $is_local_file = !isset($parse_src['host']) || 
                        strpos($cleaned_path, $base_url) === 0 || 
                        (isset($parse_src['host']) && $parse_src['host'] === parse_url($base_url)['host']);
        
        $this->utilities_service->debug_log("Pro get_a_local_copy_of_image: is_local_file=" . ($is_local_file ? 'true' : 'false'));
        
        if ($is_local_file) {
            // Handle local file with Legacy-compatible path resolution
            $local_path = $this->_resolveLocalPath($cleaned_path, $parse_src, $base_url);
            
            $this->utilities_service->debug_log("Pro get_a_local_copy_of_image: resolved local_path={$local_path}");
            
            // Legacy-compatible file existence checking with fallbacks
            if (!$this->_checkFileExists($local_path, $adapter_name)) {
                $this->utilities_service->debug_log("Pro get_a_local_copy_of_image: checkFileExists failed, trying is_readable");
                // Try is_readable as fallback (Legacy behavior)
                if (!is_readable($local_path)) {
                    $this->utilities_service->debug_log("Pro get_a_local_copy_of_image: is_readable also failed for {$local_path}");
                    return false;
                }
                $this->utilities_service->debug_log("Pro get_a_local_copy_of_image: is_readable succeeded for {$local_path}");
            }
            
            try {
                $image_source = $this->_getFileFromLocal($local_path, $adapter_name);
                if (!$image_source) {
                    $this->utilities_service->debug_log("Pro get_a_local_copy_of_image: getFileFromLocal failed");
                    return false;
                }
                
                $this->utilities_service->debug_log("Pro get_a_local_copy_of_image: SUCCESS - returning image_source and path");
                return ['image_source' => $image_source, 'path' => $local_path];
            } catch (\Exception $e) {
                $this->utilities_service->debug_log("Pro get_a_local_copy_of_image: Exception in getFileFromLocal: " . $e->getMessage());
                return false;
            }
        } else {
            // Handle remote URL - use Pro implementation (eliminates Legacy dependency)
            $image_source = $this->getFileFromRemote($cleaned_path);
            if (!$image_source) {
                // Legacy fallback: Try CE Image remote cache (optional)
                $basename = pathinfo($cleaned_path, PATHINFO_BASENAME);
                if (!$basename) {
                    return false;
                }
                
                // Pro implementation: CE Image remote cache lookup
                $ce_cache_path = $this->_lookForCeImageRemoteFiles($basename, $adapter_name);
                if ($ce_cache_path) {
                    try {
                        $image_source = $this->_getFileFromLocal($ce_cache_path, $adapter_name);
                        if ($image_source) {
                            return ['image_source' => $image_source, 'path' => $ce_cache_path];
                        }
                    } catch (\Exception $e) {
                        // Fall through to return false
                    }
                }
                
                return false;
            }
            
            return ['image_source' => $image_source, 'path' => $cleaned_path];
        }
    }
    
    /**
     * Legacy method: Get image size information (optimized for memory-first approach)
     *
     * @param string $path File path
     * @param string|null $adapter_name Adapter to use
     * @return array|bool Image size info or false on failure
     */
    public function getimagesize(string $path, ?string $adapter_name = null): array|false 
    {
        try {
            // Use explicit connection or fallback to default (safety net)
            if ($adapter_name === null) {
                $adapter_name = $this->settings->get_default_connection_name();
                $this->utilities_service->debug_log("Pro getimagesize: No connection specified, falling back to default: {$adapter_name}");
            }
            
            // For local files, use direct getimagesize() - no temp files needed
            if ($adapter_name === 'local' || str_starts_with($adapter_name, 'legacy_local')) {
                $config = $this->getConnectionConfig($adapter_name);
                if (!$config) {
                    return false;
                }
                $full_path = rtrim($config['config']['root'] ?? '', '/') . '/' . ltrim($path, '/');
                
                if (file_exists($full_path)) {
                    return getimagesize($full_path) ?: false;
                }
                return false;
            }
            
            // For remote files, try memory-based approach first (PHP 5.4+)
            try {
                $content = $this->getImageContent($path, $adapter_name);
                
                // Use getimagesizefromstring() if available (PHP 5.4+, much faster)
                if (function_exists('getimagesizefromstring')) {
                    return getimagesizefromstring($content) ?: false;
                }
                
                // Fallback: create temporary file only if needed for older PHP
                $temp_path = tempnam(sys_get_temp_dir(), 'jcogs_img_size_');
                file_put_contents($temp_path, $content);
                $image_info = getimagesize($temp_path);
                unlink($temp_path);
                
                return $image_info ?: false;
                
            } catch (\Exception $e) {
                // Fallback to old approach if memory method fails
                $local_path = $this->getLocalCopyOfImage($path, $adapter_name, true);
                $image_info = getimagesize($local_path);
                
                // Clean up temporary file
                if (file_exists($local_path) && str_contains($local_path, sys_get_temp_dir())) {
                    unlink($local_path);
                }
                
                return $image_info ?: false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Legacy method: Get last modified timestamp
     *
     * @param string $path File path
     * @param string|null $adapter_name Adapter to use
     * @return int|bool Timestamp or false on failure
     */
    public function lastModified(string $path, ?string $adapter_name = null): int|false 
    {
        try {
            return $this->getLastModified($path, $adapter_name);
        } catch (FilesystemException $e) {
            return false;
        }
    }
    
    /**
     * Get current adapter name (legacy compatibility method)
     *
     * @deprecated Use explicit connection names in method calls instead
     * @return string Default connection name as fallback
     */
    public function getCurrentAdapter(): string 
    {
        $default_connection = $this->settings->get_default_connection_name();
        $this->utilities_service->debug_log("Pro getCurrentAdapter: DEPRECATED method called, returning default: {$default_connection}");
        return $default_connection;
    }
    
    /**
     * Set current adapter
     *
     * @param string $adapter_name Adapter name
     * @return void
     */
    public function setCurrentAdapter(string $adapter_name): void 
    {
        $this->current_connection_name = $adapter_name;
    }
    
    /**
     * Get available adapters
     *
     * @return array List of available adapters
     */
    public function getAvailableAdapters(): array 
    {
        $named_adapters = $this->settings->getNamedFilesystemAdapters();
        return array_keys($named_adapters);
    }
    
    /**
     * Get adapter URL
     *
     * @param string|null $adapter_name Adapter name
     * @return string|null Adapter base URL
     */
    public function getAdapterUrl(?string $adapter_name = null): ?string 
    {
        try {
            // Use explicit connection or fallback to default (safety net)
            if ($adapter_name === null) {
                $adapter_name = $this->settings->get_default_connection_name();
                $this->utilities_service->debug_log("Pro getAdapterUrl: No connection specified, falling back to default: {$adapter_name}");
            }
            
            $config = $this->getConnectionConfig($adapter_name);
            return $config['url'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Check if file exists with Legacy-compatible fallbacks
     * 
     * @param string $path File path to check
     * @param string|null $adapter_name Adapter to use
     * @return bool True if file exists or is readable
     */
    private function _checkFileExists(string $path, ?string $adapter_name = null): bool 
    {
        if (ee('Filesystem')->exists($path) || $this->exists($path, $adapter_name)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Create S3 adapter
     *
     * @param array $config S3 configuration
     * @return AwsS3V3Adapter S3 adapter instance
     */
    private function _createS3Adapter(array $config): AwsS3V3Adapter 
    {
        $client_config = [
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret']
            ],
            'region' => $config['region'],
            'version' => 'latest'
        ];
        
        if (!empty($config['endpoint'])) {
            $client_config['endpoint'] = $config['endpoint'];
        }
        
        $client = new S3Client($client_config);
        
        return new AwsS3V3Adapter(
            $client,
            $config['bucket'],
            trim($config['server_path'] ?? '', '/')
        );
    }
    
    /**
     * Create Cloudflare R2 adapter
     *
     * @param array $config R2 configuration
     * @return AwsS3V3Adapter R2 adapter instance (uses S3 compatibility)
     */
    private function _createR2Adapter(array $config): AwsS3V3Adapter 
    {
        $endpoint = "https://{$config['account_id']}.r2.cloudflarestorage.com";
        
        $client = new S3Client([
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret']
            ],
            'region' => 'auto',
            'version' => 'latest',
            'endpoint' => $endpoint
        ]);
        
        return new AwsS3V3Adapter(
            $client,
            $config['bucket'],
            trim($config['server_path'] ?? '', '/')
        );
    }
    
    /**
     * Create DigitalOcean Spaces adapter
     *
     * @param array $config DigitalOcean Spaces configuration
     * @return AwsS3V3Adapter Spaces adapter instance (uses S3 compatibility)
     */
    private function _createDoSpacesAdapter(array $config): AwsS3V3Adapter 
    {
        $endpoint = !empty($config['endpoint']) 
            ? $config['endpoint']
            : "https://{$config['region']}.digitaloceanspaces.com";
        
        $client = new S3Client([
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret']
            ],
            'region' => $config['region'],
            'version' => 'latest',
            'endpoint' => $endpoint
        ]);
        
        return new AwsS3V3Adapter(
            $client,
            $config['space'],
            trim($config['server_path'] ?? '', '/')
        );
    }

    /**
     * Create filesystem adapter for audit operations (handles both connection names and adapter types)
     * 
     * @param string $identifier Connection name or adapter type
     * @return Filesystem Configured filesystem instance
     * @throws \InvalidArgumentException If adapter configuration is invalid
     */
    private function _createFilesystemForAudit(string $identifier): Filesystem
    {
        // First try as connection name
        $connection_config = $this->getConnectionConfig($identifier);
        
        if ($connection_config) {
            // It's a valid connection name, use it directly
            return $this->createFilesystemAdapter($identifier);
        }
        
        // Not a connection name, treat as adapter type and create temporary connection
        $adapter_types = ['local', 's3', 'r2', 'dospaces', 'azure', 'gcs'];
        
        if (in_array($identifier, $adapter_types)) {
            // Create a temporary connection name for this adapter type
            $temp_connection_name = "temp_{$identifier}_" . uniqid();
            
            // Get configuration for this adapter type
            $adapter_config = $this->settings->getFilesystemConfig($identifier);
            
            // Create temporary connection configuration
            $temp_connection = [
                'name' => $temp_connection_name,
                'type' => $identifier,
                'config' => $adapter_config,
                'is_temporary' => true
            ];
            
            // Store temporarily for createFilesystemAdapter to find
            $this->temp_connections[$temp_connection_name] = $temp_connection;
            
            try {
                $filesystem = $this->createFilesystemAdapter($temp_connection_name);
                // Clean up temporary connection
                unset($this->temp_connections[$temp_connection_name]);
                return $filesystem;
            } catch (\Exception $e) {
                // Clean up on error
                unset($this->temp_connections[$temp_connection_name]);
                throw $e;
            }
        }
        
        throw new \InvalidArgumentException("Invalid identifier (not a connection name or adapter type): {$identifier}");
    }

    /**
     * Get file from local source with Legacy-compatible fallbacks
     * 
     * @param string $path File path
     * @param string|null $adapter_name Adapter to use
     * @return string|false File contents or false
     */
    private function _getFileFromLocal(string $path, ?string $adapter_name = null): string|false 
    {
        $adjusted_path = parse_url(url: $path, component: PHP_URL_PATH) ?: $path;
        
        try {
            // Try reading the file using the primary EE method
            return ee('Filesystem')->read($adjusted_path);
        } catch (\ExpressionEngine\Library\Filesystem\FilesystemException $e) {
            try {
                // Fallback to Pro filesystem service
                return $this->read($adjusted_path, $adapter_name);
            } catch (FilesystemException $e) {
                return false;
            }
        }
    }
    
    /**
     * Get remote content using CURL (migrated from Legacy)
     */
    private function _getRemoteContentCurl(string $url, ?array $post_data = null, ?array $headers = null, string $encoding = 'form'): string|false 
    {
        if (!function_exists('curl_init')) {
            return false;
        }
        
        // Clean up URL (Legacy approach)
        $url_parts = pathinfo($url);
        $clean_url = $url_parts['dirname'] . '/' . $url_parts['filename'];
        $clean_url .= isset($url_parts['extension']) ? '.' . $url_parts['extension'] : '';
        
        // Set up headers (Legacy defensive approach)
        $default_headers = [
            'Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5',
            'Cache-Control: max-age=0',
            'Connection: keep-alive',
            'Keep-Alive: 300',
            'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'Accept-Language: en-us,en;q=0.5'
        ];
        
        if ($headers && is_array($headers)) {
            $default_headers = array_merge($default_headers, $headers);
        }
        
        // Get user agent from settings
        $user_agent = $this->settings->get('img_cp_default_user_agent_string', 'JCOGS Image Pro/1.0');
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $default_headers);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $clean_url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        
        // Handle safe_mode and open_basedir (Legacy compatibility)
        if (!ini_get('open_basedir') && !ini_get('safe_mode')) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        }
        
        // Handle POST data if provided
        if (!empty($post_data)) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_URL, $url); // Use unsanitized URL for POST
            
            if ($encoding === 'form') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
            }
        }
        
        try {
            $content = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200 && $content !== false) {
                return $content;
            }
            
        } catch (\Exception $e) {
            if (isset($ch)) {
                curl_close($ch);
            }
        }
        
        return false;
    }
    
    /**
     * Get remote content using file_get_contents (migrated from Legacy)
     */
    private function _getRemoteContentFGC(string $url, ?array $post_data = null, ?array $headers = null, string $encoding = 'form'): string|false 
    {
        // Check if allow_url_fopen is enabled
        if (!ini_get('allow_url_fopen')) {
            return false;
        }
        
        // Clean URL (Legacy approach)  
        $url_parts = pathinfo($url);
        $clean_url = $url_parts['dirname'] . '/' . urlencode($url_parts['filename']);
        $clean_url .= isset($url_parts['extension']) ? '.' . $url_parts['extension'] : '';
        
        $default_headers = ['Accept-language: en'];
        
        if ($headers && is_array($headers)) {
            $default_headers = array_merge($default_headers, $headers);
        }
        
        $context_options = [
            'http' => [
                'method' => 'GET',
                'header' => $default_headers,
            ]
        ];
        
        // Handle POST data
        if (!empty($post_data)) {
            $post_content = ($encoding === 'form') ? http_build_query($post_data) : json_encode($post_data);
            $context_options['http']['method'] = 'POST';
            $context_options['http']['content'] = $post_content;
            $clean_url = $url; // Use original URL for POST
        }
        
        try {
            $context = stream_context_create($context_options);
            $content = @file_get_contents($clean_url, false, $context);
            
            return $content !== false ? $content : false;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Look for CE Image remote files as final fallback
     * 
     * Implements CE Image addon compatibility by searching for cached remote images
     * in the CE Image remote directory cache. This serves as a final fallback when
     * images cannot be found via normal local/remote paths.
     * 
     * @param string $basename Original filename basename to search for
     * @param string|null $adapter_name Filesystem adapter to use for file operations
     * @return string|false Path to cached file if found, false otherwise
     */
    private function _lookForCeImageRemoteFiles(string $basename, ?string $adapter_name = null): string|false
    {
        // Get CE Image remote directory from settings
        $remote_dir_setting = $this->settings->get('img_cp_ce_image_remote_dir', '');
        if (empty($remote_dir_setting)) {
            return false;
        }
        
        // Build full remote directory path
        $base_path = $this->utilities_service->get_base_path();
        $remote_dir = $base_path . $remote_dir_setting;
        
        // Check if remote directory exists
        if (!$this->directoryExists($remote_dir, $adapter_name)) {
            return false;
        }
        
        try {
            // Get directory listing using Pro filesystem
            $files = $this->listContents($remote_dir, false, $adapter_name);
            if (empty($files)) {
                return false;
            }
            
            // Filter files that contain our basename
            $matching_files = [];
            foreach ($files as $file) {
                if ($file['type'] === 'file' && strpos($file['basename'], $basename) !== false) {
                    $matching_files[] = $file;
                }
            }
            
            if (empty($matching_files)) {
                return false;
            }
            
            // Find the largest matching file (best quality)
            $largest_file = null;
            $largest_size = 0;
            
            foreach ($matching_files as $file) {
                try {
                    $file_size = $this->getSize($file['path'], $adapter_name);
                    if ($file_size > $largest_size) {
                        $largest_size = $file_size;
                        $largest_file = $file;
                    }
                } catch (\Exception $e) {
                    // Continue if we can't get file size
                    continue;
                }
            }
            
            return $largest_file ? $largest_file['path'] : false;
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_log("Error searching CE Image remote files: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Resolve local path from URL components
     * 
     * @param string $path Original path
     * @param array $parse_src Parsed URL components
     * @param string $base_url Site base URL
     * @return string Resolved local path
     */
    private function _resolveLocalPath(string $path, array $parse_src, string $base_url): string 
    {
        $original_path = $path;
        
        $this->utilities_service->debug_log("Pro resolveLocalPath: ENTRY original_path={$original_path}");
        $this->utilities_service->debug_log("Pro resolveLocalPath: parse_src=" . json_encode($parse_src));
        $this->utilities_service->debug_log("Pro resolveLocalPath: base_url={$base_url}");
        
        if (!isset($parse_src['host'])) {
            $this->utilities_service->debug_log("Pro resolveLocalPath: No host, checking if path starts with utilities path");
            $utilities_base_path = $this->utilities_service->path();
            $this->utilities_service->debug_log("Pro resolveLocalPath: utilities_base_path={$utilities_base_path}");
            
            if (!str_starts_with($path, $utilities_base_path)) {
                $stripped_path = ltrim($parse_src['path'], '/');
                $new_path = $this->utilities_service->path($stripped_path);
                $this->utilities_service->debug_log("Pro resolveLocalPath: Applied utilities->path($stripped_path) = {$new_path}");
                $path = $new_path;
            }
        } elseif (strpos($path, $base_url) === 0) {
            $this->utilities_service->debug_log("Pro resolveLocalPath: Path starts with base_url, stripping it");
            $stripped = ltrim(str_replace($base_url, '', $path), '/');
            $new_path = $this->utilities_service->path($stripped);
            $this->utilities_service->debug_log("Pro resolveLocalPath: utilities->path({$stripped}) = {$new_path}");
            $path = $new_path;
        } elseif ($parse_src['host'] == parse_url($base_url)['host']) {
            $this->utilities_service->debug_log("Pro resolveLocalPath: Host matches, using path from URL");
            $url_path = ltrim($parse_src['path'], '/');
            $new_path = $this->utilities_service->path($url_path);
            $this->utilities_service->debug_log("Pro resolveLocalPath: utilities->path({$url_path}) = {$new_path}");
            $path = $new_path;
        }
        
        $final_path = rtrim($path, '/');
        $this->utilities_service->debug_log("Pro resolveLocalPath: Final transformation: {$original_path} -> {$final_path}");
        return $final_path;
    }

    /**
     * Test filesystem connectivity with an existing filesystem instance
     * 
     * @param Filesystem $filesystem The filesystem instance to test
     * @param string $adapter_name Name of the adapter for logging
     * @return bool True if connectivity test passes
     */
    private function _test_filesystem_connectivity(Filesystem $filesystem, string $adapter_name): bool
    {
        try {
            if ($adapter_name === 'local') {
                // For local, just check if we can access the filesystem
                $this->utilities_service->debug_log("JCOGS Image Pro (create_filesystem_adapter) Flysystem: {$adapter_name} adapter configured and ready for use.");
                return true;
            } else {
                // For cloud adapters, try a write/read test to verify connectivity (mirrors Legacy)
                $test_file = 'jcogs_img_pro_test_' . time() . '.txt';
                $test_content = 'JCOGS Image Pro connection test';
                
                // Write test file
                $filesystem->write($test_file, $test_content);
                
                // Read test file back
                $read_content = $filesystem->read($test_file);
                
                // Clean up test file
                $filesystem->delete($test_file);
                
                // Verify content matches
                if ($read_content === $test_content) {
                    $this->utilities_service->debug_log("JCOGS Image Pro (create_filesystem_adapter) Flysystem: {$adapter_name} adapter write/read test successful.");
                    $this->utilities_service->debug_log("JCOGS Image Pro (create_filesystem_adapter) Flysystem: {$adapter_name} adapter configured and ready for use.");
                    return true;
                } else {
                    $this->utilities_service->debug_log("JCOGS Image Pro (create_filesystem_adapter) Flysystem: {$adapter_name} adapter write/read test FAILED - content mismatch.");
                    return false;
                }
            }
        } catch (\Exception $e) {
            // Log error if debugging is enabled
            $this->utilities_service->debug_log("JCOGS Image Pro (create_filesystem_adapter) Flysystem: {$adapter_name} adapter test failed - " . $e->getMessage());
            return false;
        }
    }
}
