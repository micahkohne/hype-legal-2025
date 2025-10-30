<?php

/**
 * JCOGS Image Pro - Filesystem Interface
 * =======================================
 * Unified filesystem operations contract for local and cloud storage
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

use League\Flysystem\FilesystemOperator;

/**
 * Filesystem Interface for JCOGS Image Pro
 * 
 * Defines the contract for filesystem operations across all adapters.
 * Provides unified interface for local and cloud storage operations.
 * 
 * Architecture: All methods should explicitly specify which connection they want to use.
 * The connection_name parameter is nullable only for backward compatibility - when null,
 * the service will fall back to the default connection as a safety net.
 * 
 * Best Practice: Always pass explicit connection names to avoid fallback overhead.
 */
interface FilesystemInterface 
{
    /**
     * Create or get filesystem adapter
     *
     * @param string $adapter_name Adapter name (local, s3, r2, etc.)
     * @param bool $validity_test Whether this is for configuration validation
     * @return FilesystemOperator|false Filesystem operator or false on failure
     */
    public function create_filesystem_adapter(string $adapter_name = 'local', bool $validity_test = false): FilesystemOperator|false;
    
    /**
     * Get a local copy of an image (critical for watermarking)
     *
     * @param string $path Image path
     * @param string|null $adapter_name Optional specific adapter
     * @return array|false Array with ['image_source' => content, 'path' => path] or false on failure
     */
    public function get_a_local_copy_of_image(string $path, ?string $adapter_name = null): array|false;
    
    /**
     * Get image content directly in memory (zero disk I/O optimization)
     * 
     * High-performance method for when only content is needed, not file paths.
     * Avoids temporary file creation completely.
     *
     * @param string $source_path Source file path
     * @param string|null $adapter_name Optional specific adapter (nullable for backward compatibility)
     * @return string File content
     * @throws \League\Flysystem\FilesystemException If file cannot be retrieved
     */
    public function getImageContent(string $source_path, ?string $adapter_name = null): string;
    
    /**
     * Get file from remote URL (Pro implementation)
     * 
     * Fetches remote file content using CURL with file_get_contents fallback.
     * Eliminates dependency on Legacy utilities.
     *
     * @param string $url Remote URL
     * @param array|null $post_data Optional POST data
     * @param array|null $headers Optional custom headers
     * @param string $encoding POST encoding ('form' or 'json')
     * @return string|false Remote file content or false on failure
     */
    public function getFileFromRemote(string $url, ?array $post_data = null, ?array $headers = null, string $encoding = 'form'): string|false;
    
    /**
     * Check if a file exists
     *
     * @param string $path File path
     * @param string|null $adapter_name Optional specific adapter
     * @return bool True if file exists
     */
    public function exists(string $path, ?string $adapter_name = null): bool;
    
    /**
     * Read file contents
     *
     * @param string $path File path
     * @param string|null $adapter_name Optional specific adapter
     * @return string|false File contents or false on failure
     */
    public function read(string $path, ?string $adapter_name = null): string|false;
    
    /**
     * Write file contents with robust retry logic
     * 
     * Updated to match Legacy FileSystemTrait approach with multi-attempt capability
     *
     * @param string $path File path
     * @param string $contents File contents
     * @param int $attempts Current attempt number (for internal retry logic)
     * @param string|null $adapter_name Optional specific adapter
     * @return bool True on success
     */
    public function write(string $path, string $contents, int $attempts = 0, ?string $adapter_name = null): bool;
    
    /**
     * Write Imagine image to filesystem using adapter
     *
     * @param \Imagine\Image\ImageInterface $image Image to save
     * @param string $path Destination path  
     * @param array $options Save options (quality, etc.)
     * @param string|null $adapter_name Optional specific adapter
     * @return bool True on success
     */
    public function writeImage(\Imagine\Image\ImageInterface $image, string $path, array $options = [], ?string $adapter_name = null): bool;
    
    /**
     * Delete a file
     *
     * @param string $path File path
     * @param string|null $adapter_name Optional specific adapter
     * @return bool True on success
     */
    public function delete(string $path, ?string $adapter_name = null): bool;
    
    /**
     * Check if directory exists
     *
     * @param string $path Directory path
     * @param string|null $adapter_name Optional specific adapter
     * @return bool True if directory exists
     */
    public function directoryExists(string $path, ?string $adapter_name = null): bool;
    
    /**
     * Create directory
     *
     * @param string $path Directory path
     * @param string|null $adapter_name Optional specific adapter
     * @return bool True on success
     */
    public function createDirectory(string $path, ?string $adapter_name = null): bool;
    
    /**
     * Delete directory
     *
     * @param string $path Directory path
     * @param string|null $adapter_name Optional specific adapter
     * @return bool True on success
     */
    public function deleteDirectory(string $path, ?string $adapter_name = null): bool;
    
    /**
     * Get image size information
     *
     * @param string $path Image path
     * @param string|null $adapter_name Optional specific adapter
     * @return array|false Image size array or false on failure
     */
    public function getimagesize(string $path, ?string $adapter_name = null): array|false;
    
    /**
     * Get file size
     *
     * @param string $path File path
     * @param string|null $adapter_name Optional specific adapter
     * @return int|false File size in bytes or false on failure
     */
    public function filesize(string $path, ?string $adapter_name = null): int|false;
    
    /**
     * Get last modified time
     *
     * @param string $path File path
     * @param string|null $adapter_name Optional specific adapter
     * @return int|false Last modified timestamp or false on failure
     */
    public function lastModified(string $path, ?string $adapter_name = null): int|false;
    
    /**
     * List directory contents
     *
     * @param string $path Directory path
     * @param bool $recursive Whether to list recursively
     * @param string|null $adapter_name Optional specific adapter
     * @return array|false Directory contents or false on failure
     */
    public function listContents(string $path, bool $recursive = false, ?string $adapter_name = null): array|false;
    
    /**
     * Get adapter URL for building public URLs
     *
     * @param string|null $adapter_name Optional specific adapter
     * @return string|null Adapter base URL
     */
    public function getAdapterUrl(?string $adapter_name = null): ?string;
    
    /**
     * Get current adapter name
     *
     * @return string Current adapter name
     */
    public function getCurrentAdapter(): string;
    
    /**
     * Set current adapter
     *
     * @param string $adapter_name Adapter name to set as current
     * @return void
     */
    public function setCurrentAdapter(string $adapter_name): void;
    
    /**
     * Get list of available adapters
     *
     * @return array Available adapter names
     */
    public function getAvailableAdapters(): array;
    
    /**
     * Clear adapter cache (for testing/debugging)
     *
     * @param string|null $adapter_name Optional specific adapter, or null for all
     * @return void
     */
    public function clearAdapterCache(?string $adapter_name = null): void;
}
