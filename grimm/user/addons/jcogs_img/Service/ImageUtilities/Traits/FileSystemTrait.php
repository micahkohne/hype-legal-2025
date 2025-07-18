<?php

/**
 * ImageUtility Service Traits - FileSystemTrait
 * =============================================
 * A collection of traits for the ImageUtility service
 * to manage file operations.
 * =============================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.4.16.1
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.4.14
 */

namespace JCOGSDesign\Jcogs_img\Service\ImageUtilities\Traits;

// AWS SDK
use Aws\Exception\AwsException;
use \Aws\S3\S3Client;

// Flysystem API
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter as AwsPortableVisibilityConverter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;

enum AdapterType: string {
    case LOCAL = 'local';
    case S3 = 's3';
    case R2 = 'r2';
    case DOSPACES = 'dospaces';
}

trait FileSystemTrait {

    /**
     * @var array Settings array from jcogs_img:Settings
     */
    protected array $settings;

    /**
     * Batched cache writing system for better performance
     * Schedule cache updates to reduce I/O overhead
     */
    private static array $pending_cache_updates = [];
    private static bool $cache_update_scheduled = false;

    /**
     * Performance profiling system for debugging cache performance issues
     */
    private static array $performance_log = [];

    /**
     * Directory existence cache to avoid repeated filesystem checks
     */
    private static array $directory_exists_cache = [];

    /**
     * Utility function to instantiate a Flysystem adapter
     *
     * @param  string $adapter_name
     * @param  bool $validity_test
     * @return bool|Filesystem
     */

     /**
     * Backward compatibility - get current filesystem
     */
    public function __get(string $name)
    {
        if ($name === 'filesystem') {
            return $this->_get_current_filesystem();
        }
        return null;
    }

    /**
     * Clear specific adapter from cache
     */
    public function clear_adapter_cache(string $adapter_name): void
    {
        unset(static::$filesystems[$adapter_name]);
        unset(static::$adapter_urls[$adapter_name]);
        unset(static::$cache_adapter_strings[$adapter_name]);
    }

    public function create_filesystem_adapter(string $adapter_name = 'local', bool $validity_test = false): Filesystem|bool
    {
        $adapter_type = AdapterType::from($adapter_name);
        $adapter = null;
        $client_config = null;
        $bucket_name = '';

        switch ($adapter_type) {
            case AdapterType::R2:
                if ($this->settings['img_cp_flysystem_adapter_r2_is_valid'] || $validity_test) {
                    // Get values from POST when validity testing, otherwise from settings
                    if ($validity_test) {
                        $r2_key = ee()->input->post('img_cp_flysystem_adapter_r2_key');
                        $r2_account_id = ee()->input->post('img_cp_flysystem_adapter_r2_account_id');
                        $r2_bucket = ee()->input->post('img_cp_flysystem_adapter_r2_bucket');
                        $r2_path = ee()->input->post('img_cp_flysystem_adapter_r2_server_path');
                        
                        // Handle secret with obscured/actual processing
                        $r2_secret = $this->_process_secret_for_validation('r2');
                    } else {
                        $r2_key = $this->settings['img_cp_flysystem_adapter_r2_key'];
                        $r2_secret = $this->settings['img_cp_flysystem_adapter_r2_secret_actual'];
                        $r2_account_id = $this->settings['img_cp_flysystem_adapter_r2_account_id'];
                        $r2_bucket = $this->settings['img_cp_flysystem_adapter_r2_bucket'];
                        $r2_path = $this->settings['img_cp_flysystem_adapter_r2_server_path'];
                    }
                    
                    if (!empty($r2_key) && !empty($r2_secret)) {
                        $client_config = [
                            'credentials'     => [
                                'key'    => $r2_key,
                                'secret' => $r2_secret
                            ],
                            'region'          => 'auto',
                            'version'         => 'latest',
                            'endpoint'        => "https://{$r2_account_id}.r2.cloudflarestorage.com",
                            'exception_class' => AwsException::class,
                        ];
                        $bucket_name = $r2_bucket;
                    } else {
                        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_flysystem_r2_config_missing'));
                        return false;
                    }
                }
                break;

            case AdapterType::S3:
                if ($this->settings['img_cp_flysystem_adapter_s3_is_valid'] || $validity_test) {
                    // Get values from POST when validity testing, otherwise from settings
                    if ($validity_test) {
                        $s3_key = ee()->input->post('img_cp_flysystem_adapter_s3_key');
                        $s3_region = ee()->input->post('img_cp_flysystem_adapter_s3_region');
                        $s3_bucket = ee()->input->post('img_cp_flysystem_adapter_s3_bucket');
                        $s3_path = ee()->input->post('img_cp_flysystem_adapter_s3_server_path');
                        
                        // Handle secret with obscured/actual processing
                        $s3_secret = $this->_process_secret_for_validation('s3');
                    } else {
                        $s3_key = $this->settings['img_cp_flysystem_adapter_s3_key'];
                        $s3_secret = $this->settings['img_cp_flysystem_adapter_s3_secret_actual'];
                        $s3_region = $this->settings['img_cp_flysystem_adapter_s3_region'];
                        $s3_bucket = $this->settings['img_cp_flysystem_adapter_s3_bucket'];
                        $s3_path = $this->settings['img_cp_flysystem_adapter_s3_server_path'];
                    }
                    
                    if (!empty($s3_key) && !empty($s3_secret)) {
                        $client_config = [
                            'credentials'     => [
                                'key'    => $s3_key,
                                'secret' => $s3_secret
                            ],
                            'region'          => $s3_region ?: 'eu-west-2',
                            'version'         => 'latest',
                            'exception_class' => AwsException::class,
                        ];
                        $bucket_name = $s3_bucket;
                    } else {
                        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_flysystem_s3_config_missing'));
                        return false;
                    }
                }
                break;
                                        
            case AdapterType::DOSPACES:
                if ($this->settings['img_cp_flysystem_adapter_dospaces_is_valid'] || $validity_test) {
                    
                    // Get values from POST when validity testing, otherwise from settings
                    if ($validity_test) {
                        $dospaces_key = ee()->input->post('img_cp_flysystem_adapter_dospaces_key');
                        $dospaces_region = ee()->input->post('img_cp_flysystem_adapter_dospaces_region');
                        $dospaces_space = ee()->input->post('img_cp_flysystem_adapter_dospaces_space');
                        $dospaces_path = ee()->input->post('img_cp_flysystem_adapter_dospaces_server_path');
                        
                        // Handle secret with obscured/actual processing
                        $dospaces_secret = $this->_process_secret_for_validation('dospaces');
                    } else {
                        $dospaces_key = $this->settings['img_cp_flysystem_adapter_dospaces_key'];
                        $dospaces_secret = $this->settings['img_cp_flysystem_adapter_dospaces_secret_actual'];
                        $dospaces_region = $this->settings['img_cp_flysystem_adapter_dospaces_region'];
                        $dospaces_space = $this->settings['img_cp_flysystem_adapter_dospaces_space'];
                        $dospaces_path = $this->settings['img_cp_flysystem_adapter_dospaces_server_path'];
                    }
                    
                    if (!empty($dospaces_key) && !empty($dospaces_secret)) {
                        $client_config = [
                            'credentials'     => [
                                'key'    => $dospaces_key,
                                'secret' => $dospaces_secret
                            ],
                            'region'          => $dospaces_region,
                            'version'         => 'latest',
                            'endpoint'        => "https://{$dospaces_region}.digitaloceanspaces.com",
                            'exception_class' => AwsException::class
                        ];
                        $bucket_name = $dospaces_space;
                    } else {
                        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_flysystem_dospaces_config_missing'));
                        return false;
                    }
                }
                break;
        }

        // If we get here we have an adapter config worth testing

        if ($client_config && $bucket_name) {
            $adapter = $this->_create_s3_like_adapter($client_config, $bucket_name);
        }

        if ($validity_test && !$adapter) {
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_flysystem_adapter_setup_failed'), $adapter_name));
            return false;
        }

        // If we get here without a configured cloud adapter see if we can use the local adapter

        if (!$adapter) {
            $syspathRoot = null;
            $syspath     = ee('jcogs_img:Utilities')->get_base_path() ?: SYSPATH;
            $openBaseDir = ini_get('open_basedir');

            if (!empty($openBaseDir)) {
                foreach (explode(':', $openBaseDir) as $dir_path_segment) {
                    $normalizedPath = rtrim(str_replace('\\', '/', $dir_path_segment), '/') . '/';
                    if (!$syspathRoot && strlen($syspath) >= strlen($normalizedPath) && strpos($syspath, $normalizedPath) === 0) {
                        $syspathRoot = $syspath; 
                    }
                }
                if (!$syspathRoot && !empty($openBaseDir)) { // Ensure $syspathRoot check is only relevant if openBaseDir is set
                    ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_flysystem_adapter_setup_failed_open_basedir'));
                }
            }
            $adapter = new LocalFilesystemAdapter(
                location: $syspathRoot ?: $syspath,
                visibility: PortableVisibilityConverter::fromArray([
                    'file' => ['public' => 0666, 'private' => 0600],
                    'dir'  => ['public' => 0775, 'private' => 0705],
                ], defaultForDirectories: 'public'),
                writeFlags: LOCK_EX,
                linkHandling: LocalFilesystemAdapter::DISALLOW_LINKS
            );
            if (property_exists($this, 'cache_status_string')) { // Check if property exists before assigning
                $this->cache_status_string = 'locally on this server';
            }
        }

        if (!$adapter) {
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_flysystem_adapter_setup_failed'), $adapter_name));
            return false;
        }

        // If we get here we have a valid adapter, so try to create the filesystem
        try {
            $filesystem = new Filesystem($adapter);
            $return = false;

            // Test directory existence or creation
            $test_path = $adapter_name != 'local' ? '/' . trim($this->settings['img_cp_flysystem_adapter_' . $adapter_name . '_server_path'],'/') : '/' . trim($this->settings['img_cp_default_cache_directory'], '/');
            try {
                $directory_exists = $filesystem->directoryExists($test_path);
                if (!$directory_exists) {
                    $filesystem->createDirectory($test_path);
                }
                
                // Perform write/read test for comprehensive validation
                $test_file_path = rtrim($test_path, '/') . '/jcogs_img_test_' . uniqid() . '.tmp';
                $test_content = 'JCOGS IMG Test File - ' . date('Y-m-d H:i:s');
                
                // Write test file
                $filesystem->write($test_file_path, $test_content);
                
                // Read test file back
                $read_content = $filesystem->read($test_file_path);
                
                // Verify content matches
                if ($read_content === $test_content) {
                    $return = true;
                    ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_flysystem_write_read_test_success'), $adapter_name));
                } else {
                    ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_flysystem_write_read_test_content_mismatch'), $adapter_name));
                }
                
                // Clean up test file
                if ($filesystem->fileExists($test_file_path)) {
                    $filesystem->delete($test_file_path);
                }
                
            } catch (FilesystemException | UnableToCheckExistence | UnableToCreateDirectory | UnableToReadFile | UnableToDeleteFile $e) {
                ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_flysystem_error_accessing_bucket'), $adapter_name, $e->getMessage()));
                
                // Attempt cleanup even if other operations failed
                try {
                    if (isset($test_file_path) && $filesystem->fileExists($test_file_path)) {
                        $filesystem->delete($test_file_path);
                    }
                } catch (FilesystemException $cleanup_e) {
                    // Log cleanup failure but don't fail the main operation
                    ee('jcogs_img:Utilities')->debug_message(sprintf('Failed to cleanup test file: %s', $cleanup_e->getMessage()));
                }
                
                return false;
            }
        } catch (FilesystemException $e) {
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_flysystem_error_creating_filesystem'), $adapter_name, $e->getMessage()));
            return false;
        }
        
        if($return) {
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_flysystem_adapter_configured'), $adapter_name));
        }
        return $filesystem;
    }

    /**
     * Utility function to create a directory via Flysystem
     *
     * @param  string $path
     * @return bool
     */
    public function createDirectory(?string $path = null, ?string $adapter_name = null): bool
    {
        if (! $path) {
            // No path given, so bale out
            return false;
        }
        
        // Get filesystem for this operation
        $filesystem = $adapter_name 
            ? $this->_get_filesystem_for_adapter($adapter_name)
            : $this->_get_current_filesystem();
            
        if (!$filesystem) {
            return false;
        }

        // Create directory if we can 
        try {
            $filesystem->createDirectory(
                location: $path,
                config: [
                    'visibility' => 'public'
                ]
            );
            // If LocalAdapter, force directory permissions to 775 just in case
            $current_adapter = $adapter_name ?: static::$adapter_name;
            if ($current_adapter === 'local') {
                chmod(ee('jcogs_img:Utilities')->get_base_path() . $path, octdec(775));
            }
        }
        catch (FilesystemException | UnableToCreateDirectory $e) {
            // handle the error
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_flysystem_error'), $path, $e->getMessage()));
            return false;
        }

        // Update both static and cached arrays if successful
        // Use current adapter if none specified
        $adapter_name = $adapter_name ?: static::$adapter_name;
        $site_id = static::$site_id;       
        $cache_key = "jcogs_img_valid_directories_{$site_id}_{$adapter_name}";

        // Initialize arrays if needed
        if (!isset(static::$valid_directories[$site_id])) {
            static::$valid_directories[$site_id] = [];
        }
        if (!isset(static::$valid_directories[$site_id][$adapter_name])) {
            static::$valid_directories[$site_id][$adapter_name] = [];
        }
        
        // Update static array
        static::$valid_directories[$site_id][$adapter_name][$path] = true;
        
        // Schedule batched cache update instead of immediate write
        $this->_schedule_directory_cache_update(cache_key: $cache_key, site_id: $site_id, adapter_name: $adapter_name);
        
        return true;
    }

    /**
     * Utility function to delete a file via Flysystem
     *
     * @param  string $path
     * @return bool
     */
    public function delete(?string $path = null, ?string $adapter_name = null): bool
    {
        if (! $path) {
            // No path given, so bale out
            return false;
        }
        
        // Get filesystem for this operation
        $filesystem = $adapter_name 
            ? $this->_get_filesystem_for_adapter($adapter_name)
            : $this->_get_current_filesystem();
            
        if (!$filesystem) {
            return false;
        }

        // See if we can delete the file 
        try {
            $filesystem->delete($path);
        }
        catch (FilesystemException | UnableToDeleteFile $e) {
            // handle the error
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_flysystem_error'), $path, $e->getMessage()));
            return false;
        }

        return true;
    }

    /**
     * Utility function to delete a directory and its contents via Flysystem
     *
     * @param  string $path
     * @return bool
     */
    public function deleteDirectory(?string $path = null, ?string $adapter_name = null): bool
    {

        if (! $path) {
            // No path given, so bale out
            return false;
        }
        
        // Get filesystem for this operation
        $filesystem = $adapter_name 
            ? $this->_get_filesystem_for_adapter($adapter_name)
            : $this->_get_current_filesystem();
            
        if (!$filesystem) {
            return false;
        }

        // Recursivly delete directory if we can 
        try {
            $filesystem->deleteDirectory($path);
        }
        catch (FilesystemException | UnableToDeleteDirectory $e) {
            // handle the error
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_flysystem_error'), $path, $e->getMessage()));
            return false;
        }

        // Update both static and cached arrays if successful
        // Use current adapter if none specified
        $adapter_name = $adapter_name ?: static::$adapter_name;
        $site_id = static::$site_id;       
        $cache_key = "jcogs_img_valid_directories_{$site_id}_{$adapter_name}";

        // Initialize arrays if needed
        if (!isset(static::$valid_directories[$site_id])) {
            static::$valid_directories[$site_id] = [];
        }
        if (!isset(static::$valid_directories[$site_id][$adapter_name])) {
            static::$valid_directories[$site_id][$adapter_name] = [];
        }
        
        // Update static array - remove the directory
        unset(static::$valid_directories[$site_id][$adapter_name][$path]);
        
        // Schedule batched cache update instead of immediate write
        $this->_schedule_directory_cache_update(cache_key: $cache_key, site_id: $site_id, adapter_name: $adapter_name);

        return true;
    }

    /**
     * Utility function to check a directory exists
     * Optimized version with reduced cache overhead and batched updates
     * Now integrated with cache_log system to avoid redundant disk access
     *
     * @param  string $path
     * @param  bool $mkdir
     * @return bool
     */
    public function directoryExists(?string $path = null, ?bool $mkdir = false, ?string $adapter_name = null): bool
    {
        $profile_id = $this->_profile_filesystem_method_start('directoryExists');
        
        if (empty($path)) {
            $this->_profile_filesystem_method_end($profile_id);
            return false;
        }

        // Use current adapter if none specified
        $adapter_name = $adapter_name ?: static::$adapter_name;
        $site_id = static::$site_id;
        
        // FIRST: Check if we can infer directory existence from cache_log
        // If we have files in this directory path in the cache log, the directory must exist
        if (!$mkdir && $this->_check_directory_exists_from_cache_log($path, $site_id, $adapter_name)) {
            // Cache the positive result in our local static cache too
            if (!isset(static::$valid_directories[$site_id][$adapter_name])) {
                static::$valid_directories[$site_id][$adapter_name] = [];
            }
            static::$valid_directories[$site_id][$adapter_name][$path] = true;
            
            $this->_profile_filesystem_method_end($profile_id);
            return true;
        }
        
        // Cache key memoization to avoid repeated string concatenation
        static $cache_key_lookup = [];
        $lookup_key = "{$site_id}_{$adapter_name}";
        if (!isset($cache_key_lookup[$lookup_key])) {
            $cache_key_lookup[$lookup_key] = "jcogs_img_valid_directories_{$lookup_key}";
        }
        $cache_key = $cache_key_lookup[$lookup_key];
        
        // Initialize arrays once per adapter/site combo and load EE cache only once
        if (!isset(static::$valid_directories[$site_id][$adapter_name])) {
            static::$valid_directories[$site_id][$adapter_name] = [];
            
            // Load EE cache only once when initializing adapter arrays
            $cached_directories = ee('jcogs_img:Utilities')->cache_utility('get', $cache_key);
            if ($cached_directories && is_array($cached_directories)) {
                static::$valid_directories[$site_id][$adapter_name] = $cached_directories;
            }
        }
        
        // Simple array lookup (no repeated cache checks or merging)
        if (isset(static::$valid_directories[$site_id][$adapter_name][$path]) && !$mkdir) {
            $this->_profile_filesystem_method_end($profile_id);
            return static::$valid_directories[$site_id][$adapter_name][$path];
        }
        
        // Get filesystem for this operation
        $filesystem = $adapter_name 
            ? $this->_get_filesystem_for_adapter($adapter_name)
            : $this->_get_current_filesystem();
            
        if (!$filesystem) {
            $this->_profile_filesystem_method_end($profile_id);
            return false;
        }

        // Get the exists status if we can
        $dir_exists = false;
        try {
            $dir_exists = $filesystem->directoryExists($path);
        }
        catch (FilesystemException | UnableToCheckExistence $e) {
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_flysystem_error'), $path, $e->getMessage()));
        }

        if (!$dir_exists && $mkdir) {
            $dir_exists = $this->createDirectory(path: $path, adapter_name: $adapter_name);
        }
        
        // Update static array and schedule batched cache update
        if ($dir_exists) {
            static::$valid_directories[$site_id][$adapter_name][$path] = $dir_exists;
            $this->_schedule_directory_cache_update(cache_key: $cache_key, site_id: $site_id, adapter_name: $adapter_name);
        }
        
        $this->_profile_filesystem_method_end($profile_id);
        return $dir_exists;
    }

    /**
     * Utility function to list contents of a directory
     *
     * @param  string $path
     * @return bool|array
     */
    public function directoryList(?string $path = null, ?string $adapter_name = null): array|bool
    {
        if (! $path) {
            // No path given, so bale out
            return false;
        }
        
        // Get filesystem for this operation
        $filesystem = $adapter_name 
            ? $this->_get_filesystem_for_adapter($adapter_name)
            : $this->_get_current_filesystem();
            
        if (!$filesystem) {
            return false;
        }

        // Is it a directory?
        $locationExists = $this->directoryExists(rtrim($path, '/'));
        if (! $locationExists) {
            return false;
        }

        try {
            // Get a list of the files in the directory
            $locationFiles = (array) $filesystem
                ->listContents(rtrim($path, '/'))
                ->filter(fn(StorageAttributes $attributes) => $attributes->isFile())
                ->map($this->_mapFileAttributes(...))
                ->toArray();
            return $locationFiles;
        } catch (FilesystemException $e) {
            ee('jcogs_img:Utilities')->debug_message(sprintf("Error listing directory contents: %s", $e->getMessage()));
            return false;
        }
    }

    /**
     * Dump filesystem performance log for debugging
     */
    public static function dump_filesystem_performance_log(): void
    {
        if (empty(self::$performance_log)) {
            echo "<!-- No filesystem performance data collected -->\n";
            return;
        }
        
        echo "<!-- FileSystemTrait Performance Log -->\n";
        echo "<!-- Total filesystem methods profiled: " . count(self::$performance_log) . " -->\n";
        
        $total_time = 0;
        $slowest_methods = [];
        
        foreach (self::$performance_log as $profile_id => $data) {
            if (isset($data['duration']) && str_starts_with($data['method'], 'FileSystem::')) {
                $total_time += $data['duration'];
                $slowest_methods[] = [
                    'method' => $data['method'],
                    'duration' => $data['duration'],
                    'memory_used' => $data['memory_used'] ?? 0,
                    'profile_id' => $profile_id
                ];
            }
        }
        
        // Sort by duration (slowest first)
        usort($slowest_methods, function($a, $b) {
            return $b['duration'] <=> $a['duration'];
        });
        
        echo "<!-- Total filesystem time: " . number_format($total_time * 1000, 2) . "ms -->\n";
        echo "<!-- Top 10 slowest filesystem operations: -->\n";
        
        foreach (array_slice($slowest_methods, 0, 10) as $method) {
            echo sprintf(
                "<!-- %s: %.2fms (Memory: %s) -->\n",
                $method['method'],
                $method['duration'] * 1000,
                number_format($method['memory_used'] / 1024, 2) . 'KB'
            );
        }
        
        echo "<!-- End FileSystemTrait Performance Log -->\n";
    }

    /**
     * Utility function to check file exists via Flysystem
     *
     * @param  string $path
     * @return bool
     */
    public function exists(?string $path = null, ?string $adapter_name = null): bool
    {
        if (empty($path)) {
            // No path given, so bale out
            return false;
        }
        
        // Get filesystem for this operation
        $filesystem = $adapter_name 
            ? $this->_get_filesystem_for_adapter($adapter_name)
            : $this->_get_current_filesystem();
            
        if (!$filesystem) {
            return false;
        }

        try {
            // Get the exists status if we can 
            return $filesystem->fileExists($path);
        }
        catch (FilesystemException | UnableToCheckExistence $e) {
            // got nothing so report the error
            // ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_flysystem_not_found'), $path);
            return false;
        }
    }

    /**
     * Utility function to return filesize via Flysystem
     * Enhanced to check cache log first for better performance
     *
     * @param  string $path
     * @param  string|null $adapter_name
     * @return int|bool
     */
    public function filesize(?string $path = null, ?string $adapter_name = null): bool|int
    {
        $profile_id = $this->_profile_filesystem_method_start('filesize');
        
        if (empty($path)) {
            // No path given, so bale out
            $this->_profile_filesystem_method_end($profile_id);
            return false;
        }
        
        // Use current adapter if none specified
        $adapter_name = $adapter_name ?: static::$adapter_name;
        
        // First check the static cache log for filesize
        $trimmed_path = trim($path, '/');
        $path_parts = pathinfo($trimmed_path);
        $cache_dir = $path_parts['dirname'] ?? '.';
        $filename = $path_parts['basename'];
        
        // Check static cache first
        if (isset(static::$cache_log_index[static::$site_id][$adapter_name][$cache_dir][$filename])) {
            $cache_entry = static::$cache_log_index[static::$site_id][$adapter_name][$cache_dir][$filename];
            if (property_exists($cache_entry, 'stats') && !empty($cache_entry->stats)) {
                $decoded_stats = json_decode($cache_entry->stats, true);
                if ($decoded_stats && isset($decoded_stats['size'])) {
                    $this->_profile_filesystem_method_end($profile_id);
                    return (int)$decoded_stats['size'];
                }
            }
        }
        
        // If not in static cache, try get_file_info_from_cache_log
        $file_info_from_cache = $this->get_file_info_from_cache_log($trimmed_path);
        if (!empty($file_info_from_cache) && isset($file_info_from_cache[$filename])) {
            $cache_entry = $file_info_from_cache[$filename];
            if (property_exists($cache_entry, 'stats') && !empty($cache_entry->stats)) {
                $decoded_stats = json_decode($cache_entry->stats, true);
                if ($decoded_stats && isset($decoded_stats['size'])) {
                    $this->_profile_filesystem_method_end($profile_id);
                    return (int)$decoded_stats['size'];
                }
            }
        }
        
        // Cache miss - fall back to filesystem operation
        $filesystem = $adapter_name 
            ? $this->_get_filesystem_for_adapter($adapter_name)
            : $this->_get_current_filesystem();
            
        if (!$filesystem) {
            $this->_profile_filesystem_method_end($profile_id);
            return false;
        }

        // Get the filesize from filesystem as last resort
        try {
            $file_size = $filesystem->fileSize($path);
            
            // Cache the result for future use if we got a valid size
            // if ($file_size !== false) {
            //     $this->update_cache_log(
            //         image_path: $path,
            //         cache_dir: $cache_dir
            //     );
            // }
            
            $this->_profile_filesystem_method_end($profile_id);
            return $file_size;
        }
        catch (FilesystemException | UnableToRetrieveMetadata $e) {
            // handle the error
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_flysystem_error'), $path, $e->getMessage()));
            $this->_profile_filesystem_method_end($profile_id);
            return false;
        }
    }

    /**
     * Get adapter URL for specific adapter
     */
    public function get_adapter_url(?string $adapter_name = null): ?string
    {
        $adapter_name = $adapter_name ?: static::$adapter_name;
        
        // Ensure adapter is initialized
        $this->_get_filesystem_adapter($adapter_name);
        
        return static::$adapter_urls[$adapter_name] ?? null;
    }

    /**
     * Static access to current filesystem for backward compatibility
     */
    public static function get_filesystem(): Filesystem|bool
    {
        $instance = new static();
        return $instance->_get_current_filesystem();
    }

    /**
     * Get all initialized filesystems
     */
    public function get_initialized_adapters(): array
    {
        return array_keys(static::$filesystems);
    }

    /**
     * Utility function to return imagesize via Flysystem
     * Enhanced to check cache log first for better performance
     *
     * @param  string $path
     * @param  string|null $adapter_name
     * @return array|bool
     */
    public function getimagesize(?string $path = null, ?string $adapter_name = null): array|bool
    {
        if (empty($path)) {
            // No path given, so bale out
            return [];
        }
        
        // Use current adapter if none specified
        $adapter_name = $adapter_name ?: static::$adapter_name;
        
        // First check the static cache log for image dimensions
        $trimmed_path = trim($path, '/');
        $path_parts = pathinfo($trimmed_path);
        $cache_dir = $path_parts['dirname'] ?? '.';
        $filename = $path_parts['basename'];
        
        // Check static cache first
        if (isset(static::$cache_log_index[static::$site_id][$adapter_name][$cache_dir][$filename])) {
            $cache_entry = static::$cache_log_index[static::$site_id][$adapter_name][$cache_dir][$filename];
            if (property_exists($cache_entry, 'values') && !empty($cache_entry->values)) {
                $decoded_values = json_decode($cache_entry->values, true);
                if ($decoded_values && isset($decoded_values['width']) && isset($decoded_values['height'])) {
                    // Return getimagesize-compatible array format
                    return [
                        0 => (int)$decoded_values['width'],
                        1 => (int)$decoded_values['height'],
                        'width' => (int)$decoded_values['width'],
                        'height' => (int)$decoded_values['height']
                    ];
                }
            }
        }
        
        // If not in static cache, try get_file_info_from_cache_log
        $file_info_from_cache = $this->get_file_info_from_cache_log($trimmed_path);
        if (!empty($file_info_from_cache) && isset($file_info_from_cache[$filename])) {
            $cache_entry = $file_info_from_cache[$filename];
            if (property_exists($cache_entry, 'values') && !empty($cache_entry->values)) {
                $decoded_values = json_decode($cache_entry->values, true);
                if ($decoded_values && isset($decoded_values['width']) && isset($decoded_values['height'])) {
                    // Return getimagesize-compatible array format
                    return [
                        0 => (int)$decoded_values['width'],
                        1 => (int)$decoded_values['height'],
                        'width' => (int)$decoded_values['width'],
                        'height' => (int)$decoded_values['height']
                    ];
                }
            }
        }
        
        // Cache miss - fall back to filesystem operation
        $filesystem = $adapter_name 
            ? $this->_get_filesystem_for_adapter($adapter_name)
            : $this->_get_current_filesystem();
            
        if (!$filesystem) {
            return false;
        }

        try {
            // Get a copy of the file from filesystem as last resort
            $file = $this->read($path, $adapter_name);
            if($file) {
                // Get the imagesize if we can 
                $image_info = getimagesizefromstring($file);
                
                // Cache the result for future use if we got valid dimensions
                if ($image_info && isset($image_info[0]) && isset($image_info[1])) {
                    // Note: This would require updating cache_log with width/height values
                    // Implementation depends on your cache update mechanism
                }
                
                return $image_info;
            } else {
                return false;
            }
        }
        catch (FilesystemException | UnableToRetrieveMetadata $e) {
            // handle the error
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_flysystem_error'), $path, $e->getMessage()));
            return false;
        }
    }

    /**
     * Gets a local copy of an image from a path
     * @param string $path
     * @param bool $cache_check
     * @param bool $get_from_processed_image_cache
     * @return array|bool
     */
    public function get_a_local_copy_of_image(string $path, bool $cache_check = false, bool $get_from_processed_image_cache = false, ?string $adapter_name = null): array|bool
    {
        // Clean up the path just in case
        $cleaned_path = ee('Security/XSS')->clean($path);
        if (!$cleaned_path) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_XSS_fail'), [$path]);
            return false;
        }
        
        // Get filesystem for this operation
        $filesystem = $adapter_name 
            ? $this->_get_filesystem_for_adapter($adapter_name)
            : $this->_get_current_filesystem();
            
        if (!$filesystem) {
            return false;
        }

        // If it looks like filename contains encoded characters, decode them
        if (preg_match('/%[0-9A-Fa-f]{2}/', $cleaned_path)) {
            $path = urldecode($cleaned_path);
        }

        // Just for fun, see if we have a copy of image in cache (LQIP, looking at you here!)
        if ($cache_check && $this->is_image_in_cache($path)) {
            $image_source = $this->get_file_from_local($path);
            return ['image_source' => $image_source, 'path' => $path];
        }

        // Get some info about where image is
        $parse_src = parse_url($path);
        $base_url  = ee()->config->item('base_url');

        // Is file link to this domain (i.e. a local file?) 
        // Test sequence: 
        // 1) is host set? If no, probably a local file path
        // 2) does $path begin with same string as base_url? If so, local file is remainder
        // 3) does $path begin site_url? If so, probably a local file is [path]

        $is_local_file = !isset($parse_src['host']) || strpos($path, $base_url) === 0 || (isset($parse_src['host']) && $parse_src['host'] == parse_url($base_url)['host']);
        if ($is_local_file) 
        {
            // Seems to be a local file... 
            if (!$get_from_processed_image_cache) {
                $path = $this->_resolve_local_path($path, $parse_src, $base_url);
            }
    
            // Strip off a trailing '/' if there is one... 
            $path = rtrim($path, '/');

            if (!$this->_file_exists($path)) {
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_unable_to_open_path_retry'), $path);
                if (!is_readable($path)) {
                    ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_unable_to_open_path_2'), $path);
                    return false;
                }
            }
    
            // If we get here the path to file exists (huzzah!) - now see if we can get content from it
            // Try to get a copy of the image
            $image_source = $this->get_file_from_local($path);
            if (! $image_source) {
                // we have got a junk path so return
                return false;
            }
        }
        else {
            // Try and get image from remote URL
            if (! $image_source = ee('jcogs_img:Utilities')->get_file_from_remote($path)) {
                // Last effort - see if file is in CE Image Remote cache (if there is one)
                if (! pathinfo($path)['basename']) {
                    return false;
                }
                if (! $path = $this->look_for_ce_image_remote_files(pathinfo($path)['basename'])) {
                    // We got nothing so bale...)
                    return false;
                }
                // We got something! So set that to our image and continue ... 
                $image_source = $this->get_file_from_local($path);
                if (! $image_source) {
                    // if we get nothing bale
                    return false;
                }
            }
        }
        return ['image_source' => $image_source, 'path' => $path];
    }

    /**
     * Utility function: Get a file from local source
     * Custom version that includes call to Flysystem if attempt fails
     * Returns the file or false
     *
     * @param string $path
     * @return string|bool
     */
    public function get_file_from_local(string $path, ?string $adapter_name = null)
    {
        $adjusted_path = parse_url(url: $path, component: PHP_URL_PATH);
        // ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_gffl_started'), $adjusted_path);
        
        // Get filesystem for this operation
        $filesystem = $adapter_name 
            ? $this->_get_filesystem_for_adapter($adapter_name)
            : $this->_get_current_filesystem();
            
        if (!$filesystem) {
            return false;
        }

        // Try first using file_get_contents, which works most of the time
        $local_file = false;
        
        try {
            // Try reading the file using the primary method
            $local_file = ee('Filesystem')->read($adjusted_path);
        } catch (\ExpressionEngine\Library\Filesystem\FilesystemException $e) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_unable_to_open_path_retry'), $adjusted_path);
            try {
                // Fallback to the secondary method if the primary fails
                $local_file = ee('jcogs_img:ImageUtilities')->read($adjusted_path);
            } catch (FilesystemException | UnableToReadFile $e) {
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_unable_to_open_path_2'), $adjusted_path);
                return false;
            }
        }
        
        if (! $local_file) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_unable_to_open_path_1'), $adjusted_path);
            ;
            return false;
        }
        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_gffl_success'), $adjusted_path);
        return $local_file;
    }

    /**
     * Utility function to check file lastModified time via Flysystem
     *
     * @param  string $path
     * @return int|bool
     */
    public function lastModified(?string $path = null, ?string $adapter_name = null): bool|int
    {
        if (! $path) {
            // No path given, so bale out
            return false;
        }
        
        // Get filesystem for this operation
        $filesystem = $adapter_name 
            ? $this->_get_filesystem_for_adapter($adapter_name)
            : $this->_get_current_filesystem();
            
        if (!$filesystem) {
            return false;
        }

        // Get the lastModified status if we can 
        try {
            $filemtime = $filesystem->lastModified($path);
        }
        catch (FilesystemException $e) {
            // got nothing so report the error
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_flysystem_error'), $e->getMessage());
            return false;
        }
        return $filemtime;
    }

    /**
     * Gets a listing of files within any directory called remote found within webroot or level below
     * or to remote directory path specified in settings
     *
     * @param  string $filename // Name of image we are looking for
     * @return string|bool // path to remote image or false
     */
    public function look_for_ce_image_remote_files(string $filename, ?string $adapter_name = null)
    {
        if (! is_string($filename)) {
            return false;
        }
        
        // Get filesystem for this operation
        $filesystem = $adapter_name 
            ? $this->_get_filesystem_for_adapter($adapter_name)
            : $this->_get_current_filesystem();
            
        if (!$filesystem) {
            return false;
        }

        $remote_dir = ee('jcogs_img:Utilities')->get_base_path() . ee('jcogs_img:Settings')::$settings['img_cp_ce_image_remote_dir'];

        // Do we have a remote image cache directory?
        if (! $this->directoryExists($remote_dir)) {
            return false;
        }

        // Do we have a copy of directory inventory in cache?
        $ce_image_remote_cache_listing = ee('jcogs_img:Utilities')->cache_utility('get', JCOGS_IMG_CLASS . '/ce_image_remote_cache_listing');

        if (! $ce_image_remote_cache_listing) {
            // Generate a new directory inventory
            $ce_image_remote_cache_listing = ee('Filesystem')->getDirectoryContents($remote_dir, true);

            // if we got something, save it to the cache
            if ($ce_image_remote_cache_listing) {
                ee('jcogs_img:Utilities')->cache_utility('save', JCOGS_IMG_CLASS . '/ce_image_remote_cache_listing', $ce_image_remote_cache_listing, 60);
                // Save it to cache
            }
        }

        if ($ce_image_remote_cache_listing) {
            // Search directory inventory for image
            $results = array_filter(
                $ce_image_remote_cache_listing,
                fn($path) => $this->_isMatchingFile($path, $filename)
            );

                if ($results) {
                $path     = '';
                $max_size = 0;
                foreach ($results as $result) {
                    $size = getimagesize($result);
                    if ($size) {
                        $max_size = max($max_size, $size[0] + $size[1]);
                        if ($max_size == $size[0] + $size[1]) {
                            $path = $result;
                        }
                    }
                }
                // Get path of image found and return
                return $path;
            }
        }
        return false;
    }

    // Clones of EE7 Filesystem calls for EE6 compatibility
    /**
     * Normalize a path and return the complete path address
     *
     * @param string $path
     * @return string
     */
    public function normalizeAbsolutePath($path): string
    {
        if (empty($path)) {
            return '';
        }
    
        // Remove invisible control characters
        $path = preg_replace('#\\p{C}+#u', '', $path);
    
        // Normalize the path
        $normalizedPath = implode('', [
            in_array(substr($path, 0, 1), ['/', '\\']) ? '/' : '',
            $path,
            in_array(substr($path, -1), ['/', '\\']) ? '/' : ''
        ]);
    
        // Replace double slashes with single slash
        return str_replace('//', '/', $normalizedPath);
    }

    /**
     * Read a file (from cache directory)
     *
     * @param object $path
     * @return bool|string
     */
    public function read(?string $path = null, ?string $adapter_name = null): bool|string
    {
        // if we don't have a value for $path something has gone wrong...
        if (empty($path)) {
            // we were trying to produce something so report on what it was so we can debug
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_read_path_missing'));
            return false;
        }
        
        // Get filesystem for this operation
        $filesystem = $adapter_name 
            ? $this->_get_filesystem_for_adapter($adapter_name)
            : $this->_get_current_filesystem();
            
        if (!$filesystem) {
            return false;
        }

        // If we have the SYSPATH included, remove it (as flysystem adds it back)
        $path = str_replace(ee('jcogs_img:Utilities')->get_base_path(), '', $path);

        // See if we can read in the image file
        try {
            return $filesystem->read($path);
        }
        catch (FilesystemException | UnableToReadFile $e) {
            // handle the error
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_flysystem_error'), $path, $e->getMessage()));
            return false;
        }
    }

    /**
     * Switch default adapter
     */
    public function switch_default_adapter(string $adapter_name): bool
    {
        $filesystem = $this->_get_filesystem_adapter($adapter_name);
        if ($filesystem) {
            static::$adapter_name = $adapter_name;
            return true;
        }
        return false;
    }

    /**
     * Utility function to write a file via Flysystem
     *
     * @param  string $path
     * @param  string $image
     * @return bool
     */
    public function write(string $path, string $contents, int $attempts = 0, ?string $adapter_name = null): bool
    {
        if (! $path) {
            // No path given, so bale out
            return false;
        }
        
        // Get filesystem for this operation
        $filesystem = $adapter_name 
            ? $this->_get_filesystem_for_adapter($adapter_name)
            : $this->_get_current_filesystem();
            
        if (!$filesystem) {
            return false;
        }

        // Maximum number of attempts to write the file
        $max_attempts = 3;
    
        try {
            // Write the file to the filesystem
            $filesystem->write($path, $contents);
            
            // Check if the file exists
            if ($this->exists($path)) {
                if($this->settings['img_cp_flysystem_adapter'] == 's3' || $this->settings['img_cp_flysystem_adapter'] == 'dospaces') {
                    // Set the file visibility to public on S3 specifically
                    try {
                        $filesystem->setVisibility(
                        path: $path,
                        visibility: 'public'
                    );
                    } catch (FilesystemException $e) {
                        // Handle the error
                        ee('jcogs_img:Utilities')->debug_message(sprintf("jcogs_img_flysystem_write_error", $path, $e->getMessage()));
                        return false;
                    }
                }
                // If the file exists, log a success message
                ee('jcogs_img:Utilities')->debug_message(lang("jcogs_img_file_written"), $path);
                return true;
            } else {
                // If the file does not exist, increment the attempts counter
                $attempts++;
    
                // If the maximum number of attempts has not been reached, try writing again
                if ($attempts < $max_attempts) {
                    return $this->write($path, $contents, $attempts);
                } else {
                    // If the maximum number of attempts has been reached, log an error message
                    ee('jcogs_img:Utilities')->debug_message(sprintf("jcogs_img_flysystem_write_error", $max_attempts, $path));
                    return false;
                }
            }
        } catch (\Exception $e) {
            // Log the exception message
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang("jcogs_img_flysystem_error"), $path, $e->getMessage()));
            return false;
        }
    }

    /**
     * Check if directory exists by examining cache_log entries
     * If we have cached files in this directory, the directory must exist
     * 
     * @param string $path Directory path to check
     * @param string $site_id Site ID 
     * @param string $adapter_name Adapter name
     * @return bool True if directory can be inferred to exist from cache_log
     */
    private function _check_directory_exists_from_cache_log(string $path, string $site_id, string $adapter_name): bool
    {
        // Ensure cache_log_index is loaded - this should be done by the constructor but let's be safe
        if (!isset(static::$cache_log_index[$site_id][$adapter_name])) {
            // Try to load cache log if not already loaded
            if (method_exists($this, 'get_file_info_from_cache_log')) {
                $this->get_file_info_from_cache_log();
            }
            
            // If still not loaded, we can't use cache_log for this check
            if (!isset(static::$cache_log_index[$site_id][$adapter_name])) {
                return false;
            }
        }
        
        $normalized_path = trim($path, '/');
        
        // If we have files in this exact directory path, directory exists
        if (isset(static::$cache_log_index[$site_id][$adapter_name][$normalized_path]) && 
            !empty(static::$cache_log_index[$site_id][$adapter_name][$normalized_path])) {
            return true;
        }
        
        // Check if any cached files are in subdirectories of this path
        // This would also confirm the parent directory exists
        foreach (static::$cache_log_index[$site_id][$adapter_name] as $cached_dir => $files) {
            if (!empty($files) && str_starts_with($cached_dir, $normalized_path . '/')) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Creates an S3-like adapter (AwsS3V3Adapter) using the provided S3Client configuration and bucket name.
     *
     * @param array $client_config Configuration array for the S3Client.
     * @param string $bucket_name The name of the bucket.
     * @param string $prefix Optional path prefix within the bucket.
     * @return AwsS3V3Adapter|null The adapter instance or null on failure.
     */
    private function _create_s3_like_adapter(array $client_config, string $bucket_name, string $prefix = ''): ?AwsS3V3Adapter
    {
        try {
            $client = new S3Client($client_config);
            return new AwsS3V3Adapter(
                $client,
                $bucket_name,
                $prefix,
                new AwsPortableVisibilityConverter(Visibility::PUBLIC)
            );
        } catch (AwsException $e) {
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_aws_client_exception'), $e->getMessage()));
        } catch (\Exception $e) {
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_adapter_creation_exception'), $e->getMessage()));
        }
        return null;
    }

    /**
     * Optimized directory existence check with caching
     */
    private function _directory_exists_optimized(string $directory_path): bool
    {
        $profile_id = $this->_profile_filesystem_method_start('directoryExists');
            
        // Check cache first
        if (isset(self::$directory_exists_cache[$directory_path])) {
            $this->_profile_filesystem_method_end($profile_id);
            return self::$directory_exists_cache[$directory_path];
        }
        
        // Perform the actual filesystem check
        $exists = is_dir($directory_path);
        
        // Cache the result with memory management to prevent memory leak
        if (count(self::$directory_exists_cache) > 1000) {
            // Clear oldest entries to prevent memory bloat
            self::$directory_exists_cache = array_slice(self::$directory_exists_cache, 500, null, true);
        }
        self::$directory_exists_cache[$directory_path] = $exists;
        
        $this->_profile_filesystem_method_end($profile_id);
        return $exists;
    }

    /**
     * Get the current filesystem or create it lazily
     */
    private function _get_current_filesystem(): Filesystem|bool
    {
        return $this->_get_filesystem_adapter();
    }

    /**
     * Get or create filesystem adapter for specified adapter name
     *
     * @param string|null $adapter_name
     * @return Filesystem|bool
     */
    private function _get_filesystem_adapter(?string $adapter_name = null): Filesystem|bool
    {
        $profile_id = $this->_profile_filesystem_method_start('_get_filesystem_adapter');
        
        // Use current adapter if none specified
        $adapter_name = $adapter_name ?: static::$adapter_name;
        
        // Return existing adapter if already initialized
        if (isset(static::$filesystems[$adapter_name])) {
            $this->_profile_filesystem_method_end($profile_id);
            return static::$filesystems[$adapter_name];
        }
        
        error_log("[JCOGS_IMG_DEBUG] Creating new filesystem adapter: {$adapter_name}");
        
        // Track why adapter is being created
        $stack_trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $caller_info = [];
        foreach ($stack_trace as $frame) {
            if (isset($frame['class']) && isset($frame['function'])) {
                $caller_info[] = $frame['class'] . '::' . $frame['function'];
            }
        }
        
        error_log(sprintf(
            "[JCOGS_IMG_DEBUG] Creating adapter '%s' called from: %s",
            $adapter_name,
            implode(' -> ', $caller_info)
        ));
        
        // Create new adapter
        $filesystem = $this->create_filesystem_adapter($adapter_name, false);
        
        if ($filesystem) {
            // Cache the filesystem
            static::$filesystems[$adapter_name] = $filesystem;
            
            // Set adapter URL
            if ($adapter_name === 'local') {
                static::$adapter_urls[$adapter_name] = ee()->config->item('site_url');
            } else {
                $adapter_url_key = 'img_cp_flysystem_adapter_' . $adapter_name . '_url';
                static::$adapter_urls[$adapter_name] = $this->settings[$adapter_url_key];
            }
            
            // Set cache directory for cloud adapters
            if ($adapter_name !== 'local') {
                static::$valid_params['cache_dir'] = $this->settings['img_cp_flysystem_adapter_' . $adapter_name . '_server_path'];
            }
            
            $this->_profile_filesystem_method_end($profile_id);
            return $filesystem;
        }
        
        $this->_profile_filesystem_method_end($profile_id);
        return false;
    }

    /**
     * Get filesystem for specific adapter
     */
    private function _get_filesystem_for_adapter(string $adapter_name): Filesystem|bool
    {
        return $this->_get_filesystem_adapter($adapter_name);
    }
  
    /**
     * Checks if a file exists at the given path.
     *
     * This method checks for the existence of a file using two different methods:
     * 1. It uses the `ee('Filesystem')->exists` method to check if the file exists in the filesystem.
     * 2. It uses the `ee('jcogs_img:ImageUtilities')->exists` method to check if the file exists using the ImageUtilities service.
     *
     * @param string $path The path to the file to check.
     * @return bool Returns true if the file exists, false otherwise.
     */
    private function _file_exists(string $path, ?string $adapter_name = null): bool
    {
                
        // Get filesystem for this operation
        $filesystem = $adapter_name 
            ? $this->_get_filesystem_for_adapter($adapter_name)
            : $this->_get_current_filesystem();
            
        if (!$filesystem) {
            return false;
        }

        return ee('Filesystem')->exists($path) || $this->exists($path);
    }

    /**
     * Batch flush cache updates - called on shutdown to write all pending cache updates at once
     *
     * @return void
     */
    public function _flush_directory_cache_updates(): void
    {
        self::_flush_directory_cache_updates_static();
    }

    /**
     * Static version of cache flush for shutdown function
     *
     * @return void
     */
    public static function _flush_directory_cache_updates_static(): void
    {
        foreach (self::$pending_cache_updates as $cache_key => $cache_data) {
            // Fix: Check if $cache_data is an array with the expected structure
            if (!is_array($cache_data) || count($cache_data) < 2) {
                ee('jcogs_img:Utilities')->debug_message("Invalid cache data structure for key: $cache_key");
                continue;
            }
            
            // Extract site_id and adapter_name safely using array indices
            $site_id = $cache_data[0] ?? null;
            $adapter_name = $cache_data[1] ?? null;
            
            if ($site_id === null || $adapter_name === null) {
                ee('jcogs_img:Utilities')->debug_message("Missing site_id or adapter_name for cache key: $cache_key");
                continue;
            }
            
            // Check if the directory data exists before using it
            if (isset(static::$valid_directories[$site_id][$adapter_name])) {
                ee('jcogs_img:Utilities')->cache_utility(
                    'save',
                    $cache_key,
                    static::$valid_directories[$site_id][$adapter_name],
                    300
                );
            }
        }
        
        // Clear pending updates
        self::$pending_cache_updates = [];
        self::$cache_update_scheduled = false;
    }
    
    /**
     * Determines if a file matches specific criteria for processing.
     *
     * @param string $path The directory path to check
     * @param string $filename The filename to validate
     * @return bool True if the file matches the criteria, false otherwise
     */
    private function _isMatchingFile(string $path, string $filename): bool
    {
        return stripos($path, $filename) !== false && exif_imagetype($path);
    }

    private function _mapFileAttributes(StorageAttributes $attributes): array
    {
        return [
            'path'         => $attributes->path(),
            'fileName'     => pathinfo($attributes->path(), PATHINFO_BASENAME),
            'lastModified' => $attributes->lastModified()
        ];
    }

    /**
     * Processes secret for validation by checking if POST value is obscured or actual secret
     *
     * @param string $adapter_key_segment The segment of the setting key (e.g., 'r2', 's3', 'dospaces').
     * @return string The actual secret to use for validation
     */
    private function _process_secret_for_validation(string $adapter_key_segment): string
    {
        $publc_view_key = "img_cp_flysystem_adapter_{$adapter_key_segment}_secret";
        $setting_key_actual = "img_cp_flysystem_adapter_{$adapter_key_segment}_secret_actual";
        
        // Get the posted secret value
        $posted_secret = ee()->input->post($publc_view_key);
        
        if (empty($posted_secret)) {
            return '';
        }

        $current_actual_secret = $this->settings[$setting_key_actual] ?? '';
        
        // Get the obscured value of current stored actual secret
        $obscured_actual_secret = ee('jcogs_img:Utilities')->obscure_key($current_actual_secret);
        
        // Check if posted value matches the current obscured value
        if ($obscured_actual_secret === $posted_secret && !empty($current_actual_secret)) {
            // Posted value is the obscured version of current secret, so use stored actual secret
            return $current_actual_secret;
        } else {
            // Posted value is a new/different to stored actual secret, save it and return it
            ee('jcogs_img:Settings')->save_settings([
                $setting_key_actual => $posted_secret,
                $publc_view_key => $obscured_actual_secret,
            ]);
            
            return $posted_secret;
        }
    }

    /**
     * End profiling a filesystem method call
     */
    private function _profile_filesystem_method_end(string $profile_id): void
    {
        if (!isset(self::$performance_log[$profile_id])) {
            return;
        }
        
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        $end_peak_memory = memory_get_peak_usage(true);
        
        self::$performance_log[$profile_id]['end_time'] = $end_time;
        self::$performance_log[$profile_id]['duration'] = $end_time - self::$performance_log[$profile_id]['start_time'];
        self::$performance_log[$profile_id]['memory_end'] = $end_memory;
        self::$performance_log[$profile_id]['memory_used'] = $end_memory - self::$performance_log[$profile_id]['memory_start'];
        self::$performance_log[$profile_id]['peak_memory_end'] = $end_peak_memory;
        self::$performance_log[$profile_id]['peak_memory_used'] = $end_peak_memory - self::$performance_log[$profile_id]['peak_memory_start'];
    }

    /**
     * Start profiling a filesystem method call
     */
    private function _profile_filesystem_method_start(string $method_name): string
    {
        $profile_id = uniqid('fs_' . $method_name . '_', true);
        self::$performance_log[$profile_id] = [
            'method' => 'FileSystem::' . $method_name,
            'start_time' => microtime(true),
            'memory_start' => memory_get_usage(true),
            'peak_memory_start' => memory_get_peak_usage(true)
        ];
        return $profile_id;
    }

    /**
     * Resolves the local file path based on the given URL path, parsed source, and base URL.
     *
     * This method determines the local file path by checking if the URL path is relative or absolute,
     * and if it matches the base URL. It uses the jcogs_img:Utilities service to generate the correct
     * local path.
     *
     * @param string $path The URL path to resolve.
     * @param array $parse_src The parsed URL components of the source path.
     * @param string $base_url The base URL to compare against.
     * @return string The resolved local file path.
     */
    private function _resolve_local_path(string $path, array $parse_src, string $base_url): string
    {
        if (!isset($parse_src['host'])) {
            if (!str_starts_with($path, ee('jcogs_img:Utilities')->path())) {
                $path = ee('jcogs_img:Utilities')->path(ltrim($parse_src['path'], '/'));
            }
        } elseif (strpos($path, $base_url) === 0) {
            $path = ee('jcogs_img:Utilities')->path(ltrim(str_replace($base_url, '', $path), '/'));
        } elseif ($parse_src['host'] == parse_url($base_url)['host']) {
            $path = ee('jcogs_img:Utilities')->path(ltrim($parse_src['path'], '/'));
        }
        return $path;
    }

    /**
     * Schedule cache update instead of immediate write
     *
     * @param string $cache_key
     * @param string $site_id
     * @param string $adapter_name
     * @return void
     */
    private function _schedule_directory_cache_update(string $cache_key, string $site_id, string $adapter_name): void
    {
        // Mark this cache key for update
        self::$pending_cache_updates[$cache_key] = [$site_id, $adapter_name];
        
        // Schedule batch update on shutdown if not already scheduled
        if (!self::$cache_update_scheduled) {
            register_shutdown_function([self::class, '_flush_directory_cache_updates_static']);
            self::$cache_update_scheduled = true;
        }
    }
}