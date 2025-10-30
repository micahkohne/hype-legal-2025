<?php

/**
 * JCOGS Image Pro - Named Adapter Configuration Management
 * =======================================================
 * Handles configuration validation, credential encryption/decryption, 
 * and connection testing for named filesystem adapters
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Named Filesystem Adapters Feature
 */

namespace JCOGSDesign\JCOGSImagePro\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;

class NamedAdapterConfig
{
    /**
     * @var array Sensitive configuration keys that require encryption
     */
    private static array $sensitive_keys = [
        's3' => ['secret'],
        'r2' => ['secret'], 
        'dospaces' => ['secret']
    ];

    /**
     * @var array Required configuration keys per adapter type
     */
    private static array $required_keys = [
        'local' => ['cache_directory'],
        's3' => ['key', 'secret', 'region', 'bucket'],
        'r2' => ['account_id', 'key', 'secret', 'bucket'],
        'dospaces' => ['key', 'secret', 'region', 'space']
    ];

    /**
     * @var array Optional configuration keys per adapter type
     */
    private static array $optional_keys = [
        'local' => ['path_prefix'],
        's3' => ['server_path', 'url'],
        'r2' => ['server_path', 'url'],
        'dospaces' => ['server_path', 'url']
    ];

    /**
     * Create a test filesystem for connection validation
     * 
     * @param string $type Adapter type
     * @param array $config Decrypted configuration array
     * @return Filesystem|null Filesystem instance or null on failure
     */
    private static function createTestFilesystem(string $type, array $config): ?Filesystem
    {
        switch ($type) {
            case 'local':
                $root_path = ee()->config->item('base_path') ?? FCPATH;
                $cache_path = $config['cache_directory'] ?? 'images/jcogs_img_pro/cache';
                $full_path = rtrim($root_path, '/') . '/' . trim($cache_path, '/');
                
                if (!is_dir($full_path)) {
                    if (!mkdir($full_path, 0755, true)) {
                        return null;
                    }
                }
                
                $adapter = new LocalFilesystemAdapter($full_path);
                return new Filesystem($adapter);
                
            case 's3':
                $s3_client = new S3Client([
                    'version' => 'latest',
                    'region' => $config['region'],
                    'credentials' => [
                        'key' => $config['key'],
                        'secret' => $config['secret'],
                    ],
                ]);
                
                $adapter = new AwsS3V3Adapter(
                    $s3_client,
                    $config['bucket'],
                    $config['server_path'] ?? ''
                );
                
                return new Filesystem($adapter);
                
            case 'r2':
                $r2_client = new S3Client([
                    'version' => 'latest',
                    'region' => 'auto',
                    'endpoint' => 'https://' . $config['account_id'] . '.r2.cloudflarestorage.com',
                    'credentials' => [
                        'key' => $config['key'],
                        'secret' => $config['secret'],
                    ],
                ]);
                
                $adapter = new AwsS3V3Adapter(
                    $r2_client,
                    $config['bucket'],
                    $config['server_path'] ?? ''
                );
                
                return new Filesystem($adapter);
                
            case 'dospaces':
                $do_client = new S3Client([
                    'version' => 'latest',
                    'region' => $config['region'],
                    'endpoint' => 'https://' . $config['region'] . '.digitaloceanspaces.com',
                    'credentials' => [
                        'key' => $config['key'],
                        'secret' => $config['secret'],
                    ],
                ]);
                
                $adapter = new AwsS3V3Adapter(
                    $do_client,
                    $config['space'],
                    $config['server_path'] ?? ''
                );
                
                return new Filesystem($adapter);
                
            default:
                return null;
        }
    }

    /**
     * Decrypt sensitive configuration values
     * 
     * @param string $type Adapter type
     * @param array $config Configuration array with potentially encrypted values
     * @return array Configuration with decrypted sensitive values
     */
    public static function decryptSensitiveConfig(string $type, array $config): array
    {
        if (!isset(self::$sensitive_keys[$type])) {
            return $config; // No sensitive keys for this type
        }
        
        $decrypted_config = $config;
        
        foreach (self::$sensitive_keys[$type] as $sensitive_key) {
            if (isset($config[$sensitive_key]) && str_starts_with($config[$sensitive_key], 'enc_')) {
                $encrypted_value = substr($config[$sensitive_key], 4); // Remove 'enc_' prefix
                $decrypted_value = ee('Encrypt')->decode($encrypted_value);
                
                if ($decrypted_value !== false) {
                    $decrypted_config[$sensitive_key] = $decrypted_value;
                } else {
                    // Decryption failed - log error but keep original value
                    if (function_exists('log_message')) {
                        log_message('debug', 'JCOGS Image Pro: Failed to decrypt ' . $sensitive_key . ' for ' . $type . ' adapter');
                    }
                }
            }
        }
        
        return $decrypted_config;
    }

    /**
     * Encrypt sensitive configuration values
     * 
     * @param string $type Adapter type
     * @param array $config Configuration array
     * @return array Configuration with encrypted sensitive values
     */
    public static function encryptSensitiveConfig(string $type, array $config): array
    {
        if (!isset(self::$sensitive_keys[$type])) {
            return $config; // No sensitive keys for this type
        }
        
        $encrypted_config = $config;
        
        foreach (self::$sensitive_keys[$type] as $sensitive_key) {
            if (isset($config[$sensitive_key]) && !empty($config[$sensitive_key])) {
                // Only encrypt if not already encrypted
                if (!str_starts_with($config[$sensitive_key], 'enc_')) {
                    $encrypted_value = ee('Encrypt')->encode($config[$sensitive_key]);
                    $encrypted_config[$sensitive_key] = 'enc_' . $encrypted_value;
                }
            }
        }
        
        return $encrypted_config;
    }

    /**
     * Perform connectivity test on the filesystem
     * 
     * @param Filesystem $filesystem Filesystem instance to test
     * @param string $type Adapter type
     * @param array $config Configuration array
     * @return array Test result
     */
    private static function performConnectivityTest(Filesystem $filesystem, string $type, array $config): array
    {
        $test_filename = '.jcogs_img_pro_connection_test_' . time() . '.txt';
        $test_content = 'JCOGS Image Pro connection test - ' . date('Y-m-d H:i:s');
        
        try {
            // Test 1: Write a test file
            $filesystem->write($test_filename, $test_content);
            
            // Test 2: Check if file exists
            if (!$filesystem->fileExists($test_filename)) {
                return [
                    'success' => false,
                    'error' => 'File existence check failed after write',
                    'details' => ['Test file was written but cannot be found']
                ];
            }
            
            // Test 3: Read the test file back
            $read_content = $filesystem->read($test_filename);
            
            if ($read_content !== $test_content) {
                return [
                    'success' => false,
                    'error' => 'File content verification failed',
                    'details' => ['Written content does not match read content']
                ];
            }
            
            // Test 4: Delete the test file
            $filesystem->delete($test_filename);
            
            // Test 5: Verify deletion
            if ($filesystem->fileExists($test_filename)) {
                return [
                    'success' => false,
                    'error' => 'File deletion verification failed',
                    'details' => ['Test file still exists after deletion attempt']
                ];
            }
            
            return [
                'success' => true,
                'error' => null,
                'details' => [
                    'tests_passed' => ['write', 'exists', 'read', 'delete', 'verify_deletion'],
                    'adapter_type' => $type,
                    'test_duration' => 'Under 1 second'
                ]
            ];
            
        } catch (\Exception $e) {
            // Clean up test file if it exists
            try {
                if ($filesystem->fileExists($test_filename)) {
                    $filesystem->delete($test_filename);
                }
            } catch (\Exception $cleanup_error) {
                // Ignore cleanup errors
            }
            
            return [
                'success' => false,
                'error' => 'Connectivity test failed: ' . $e->getMessage(),
                'details' => [
                    'exception' => get_class($e),
                    'message' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Mask sensitive values for display purposes
     * 
     * @param string $type Adapter type
     * @param array $config Configuration array
     * @return array Configuration with masked sensitive values
     */
    public static function maskSensitiveConfig(string $type, array $config): array
    {
        if (!isset(self::$sensitive_keys[$type])) {
            return $config; // No sensitive keys for this type
        }
        
        $masked_config = $config;
        
        foreach (self::$sensitive_keys[$type] as $sensitive_key) {
            if (isset($config[$sensitive_key]) && !empty($config[$sensitive_key])) {
                $value = $config[$sensitive_key];
                
                // If encrypted, show as encrypted
                if (str_starts_with($value, 'enc_')) {
                    $masked_config[$sensitive_key] = '****encrypted****';
                } else {
                    // Mask the actual value
                    $length = strlen($value);
                    if ($length <= 8) {
                        $masked_config[$sensitive_key] = str_repeat('*', $length);
                    } else {
                        $masked_config[$sensitive_key] = substr($value, 0, 4) . str_repeat('*', $length - 8) . substr($value, -4);
                    }
                }
            }
        }
        
        return $masked_config;
    }

    /**
     * Test a connection configuration by attempting to create and use the filesystem
     * 
     * @param string $type Adapter type
     * @param array $config Configuration array (should be decrypted)
     * @return array Test result with success status, error message, and details
     */
    public static function testConnection(string $type, array $config): array
    {
        try {
            // First validate the configuration
            $validation = self::validateConnectionConfig([
                'name' => 'test_connection',
                'type' => $type,
                'config' => $config
            ]);
            
            if (!$validation['success']) {
                return [
                    'success' => false,
                    'error' => 'Configuration validation failed: ' . implode(', ', $validation['errors']),
                    'details' => $validation['errors']
                ];
            }
            
            // Create the filesystem adapter
            $filesystem = self::createTestFilesystem($type, $config);
            
            if ($filesystem === null) {
                return [
                    'success' => false,
                    'error' => 'Failed to create filesystem adapter',
                    'details' => ['Unable to initialize ' . $type . ' filesystem adapter']
                ];
            }
            
            // Perform basic connectivity test
            $test_result = self::performConnectivityTest($filesystem, $type, $config);
            
            return $test_result;
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Connection test failed: ' . $e->getMessage(),
                'details' => [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        }
    }

    /**
     * Validate a named connection configuration
     * 
     * @param array $config Connection configuration to validate
     * @return array Validation result with success status and errors
     */
    public static function validateConnectionConfig(array $config): array
    {
        $errors = [];
        
        // Validate basic structure
        if (!isset($config['name']) || !is_string($config['name']) || empty(trim($config['name']))) {
            $errors[] = 'Connection name is required and must be a non-empty string';
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $config['name'])) {
            $errors[] = 'Connection name can only contain letters, numbers, underscores, and hyphens';
        }
        
        if (!isset($config['type']) || !in_array($config['type'], ['local', 's3', 'r2', 'dospaces'])) {
            $errors[] = 'Connection type must be one of: local, s3, r2, dospaces';
            return ['success' => false, 'errors' => $errors]; // Cannot continue without valid type
        }
        
        if (!isset($config['config']) || !is_array($config['config'])) {
            $errors[] = 'Connection config must be an array';
            return ['success' => false, 'errors' => $errors]; // Cannot continue without config array
        }
        
        // Validate type-specific configuration
        $type_errors = self::validateTypeSpecificConfig($config['type'], $config['config']);
        $errors = array_merge($errors, $type_errors);
        
        return [
            'success' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate type-specific configuration requirements
     * 
     * @param string $type Adapter type
     * @param array $config Configuration array
     * @return array Array of validation errors
     */
    private static function validateTypeSpecificConfig(string $type, array $config): array
    {
        $errors = [];
        
        // Check required keys
        if (isset(self::$required_keys[$type])) {
            foreach (self::$required_keys[$type] as $required_key) {
                if (!isset($config[$required_key]) || empty(trim($config[$required_key]))) {
                    $errors[] = ucfirst($type) . " adapter requires '{$required_key}' configuration";
                }
            }
        }
        
        // Type-specific validations
        switch ($type) {
            case 'local':
                if (isset($config['cache_directory'])) {
                    $cache_dir = trim($config['cache_directory'], '/');
                    if (empty($cache_dir)) {
                        $errors[] = 'Local cache directory cannot be empty or root path';
                    }
                }
                break;
                
            case 's3':
                if (isset($config['region']) && !preg_match('/^[a-z0-9-]+$/', $config['region'])) {
                    $errors[] = 'S3 region must be a valid AWS region identifier';
                }
                if (isset($config['bucket']) && !preg_match('/^[a-z0-9.-]+$/', $config['bucket'])) {
                    $errors[] = 'S3 bucket name contains invalid characters';
                }
                break;
                
            case 'r2':
                if (isset($config['account_id']) && !preg_match('/^[a-f0-9]{32}$/', $config['account_id'])) {
                    $errors[] = 'R2 account ID must be a 32-character hexadecimal string';
                }
                break;
                
            case 'dospaces':
                if (isset($config['region']) && !preg_match('/^[a-z0-9-]+$/', $config['region'])) {
                    $errors[] = 'DigitalOcean Spaces region must be valid';
                }
                break;
        }
        
        return $errors;
    }
}
