<?php

/**
 * JCOGS Image Pro - Image Utilities Bridge Service
 * Phase 2: Legacy compatibility bridge for image utilities
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 2 Native Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service;

use JCOGSDesign\JCOGSImagePro\Contracts\FilesystemInterface;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Metadata\MetadataBag;
use Imagine\Gd\Image;

/**
 * ImageUtilities Bridge Service for JCOGS Image Pro
 * 
 * This service acts as a bridge between Pro components and the existing
 * FilesystemService, providing Legacy-compatible method signatures.
 * 
 * This eliminates the need for Pro components to call ee('jcogs_img:ImageUtilities')
 * and instead use ee('jcogs_img_pro:ImageUtilities').
 */
class ImageUtilities
{
    private FilesystemInterface $filesystem_service;
    
    public function __construct(FilesystemInterface $filesystem_service)
    {
        $this->filesystem_service = $filesystem_service;
    }
    
    /**
     * Check if file exists - bridge to FilesystemService
     *
     * @param string $path File path
     * @param string|null $adapter_name Optional specific adapter
     * @return bool True if file exists
     */
    public function exists(string $path, ?string $adapter_name = null): bool
    {
        return $this->filesystem_service->exists($path, $adapter_name);
    }
    
    /**
     * Format file size in human-readable format
     * 
     * Consolidates the file size formatting logic used across multiple classes.
     * Uses base-2 calculation (1024) for file sizes as is standard in computing.
     * 
     * @param int $bytes File size in bytes
     * @return string Formatted file size (e.g., "1.5 MB", "512 KB") or empty string for 0 bytes
     */
    public function format_file_size(int $bytes): string
    {
        if ($bytes === 0) {
            return '';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);
        
        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
    
    /**
     * Static helper for file size formatting without dependency injection
     * 
     * @param int $bytes Size in bytes
     * @return string Formatted size with appropriate unit
     */
    public static function format_file_size_static(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);
        
        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
    
    /**
     * Get a local copy of image - bridge to FilesystemService
     *
     * @param string $path Image path
     * @param bool $cache_check Check cache first (legacy parameter)
     * @param bool $get_from_processed_image_cache Check processed cache (legacy parameter)
     * @param string|null $adapter_name Optional specific adapter
     * @return array|false Array with ['image_source' => content, 'path' => path] or false
     */
    public function get_a_local_copy_of_image(string $path, bool $cache_check = false, bool $get_from_processed_image_cache = false, ?string $adapter_name = null): array|false
    {
        return $this->filesystem_service->get_a_local_copy_of_image($path, $adapter_name);
    }

    /**
     * Get the base path (mirrors Legacy implementation)
     *
     * @return string|false Base path or false if not available
     */
    private function get_base_path(): string|false
    {
        // Is base_path set?
        if (!ee()->config->item('base_path')) {
            return false;
        }

        // Normalize path if we need to 
        // Remove invisible control characters
        $path = preg_replace('#\\p{C}+#u', '', ee()->config->item('base_path'));
        // Fix up DOS and multiple slashes etc
        $path = rtrim(str_replace(['\\', '//'], '/', $path), '/') . '/';
        
        return $path;
    }
    
    /**
     * Parse EE file directory syntax like {filedir_X}filename.jpg and {file:ID:url}
     *
     * @param string $path Path that may contain EE file directory syntax
     * @return string Resolved file path
     */
    public function parseFiledir(string $path): string
    {
        // Handle EE7 file field syntax using the file_field library (mirrors Legacy implementation)
        if (function_exists('ee') && substr(APP_VER, 0, 1) == 7) {
            ee()->load->library('file_field');
            $file_path = ee()->file_field->getFileModelForFieldData($path);
            if ($file_path) {
                // If location given is not valid filedir we get nothing, so don't do any more unless we do
                $resolved_path = $file_path->getAbsolutePath();
                
                // string returned by getAbsolutePath() will include the basepath, so for compatibility we need to remove this
                // first check that $resolved_path does indeed contain the base_path
                $base_path = $this->get_base_path();
                if ($base_path && str_contains($resolved_path, $base_path)) {
                    $final_path = str_replace(rtrim($base_path, '/'), '', $resolved_path);
                    return $final_path;
                } else {
                    // Return the resolved path as-is if base path doesn't match
                    return $resolved_path;
                }
            } else {
                // Location is not a file path so return empty string (mirrors Legacy)
                return '';
            }
        }
        
        // Handle EE file directory syntax: {filedir_X}filename.jpg
        if (preg_match('/^\{filedir_(\d+)\}(.*)$/', $path, $matches)) {
            $dir_id = (int) $matches[1];
            $filename = $matches[2];
            
            // Get upload directory info from EE
            if (function_exists('ee')) {
                $upload_dirs = ee('Model')->get('UploadDestination')
                    ->filter('id', $dir_id)
                    ->first();
                
                if ($upload_dirs) {
                    $server_path = rtrim($upload_dirs->server_path, '/');
                    $full_path = $server_path . '/' . ltrim($filename, '/');
                    
                    // Remove base path for consistency (mirrors Legacy behavior)
                    $base_path = $this->get_base_path();
                    if ($base_path && str_contains($full_path, $base_path)) {
                        return str_replace(rtrim($base_path, '/'), '', $full_path);
                    }
                    return $full_path;
                }
            }
            
            // Fallback: Remove the filedir syntax and return the filename
            return $filename;
        }
        
        // No EE syntax detected, return path as-is
        return $path;
    }
    
    /**
     * Process and validate image data
     *
     * @param string $raw_data Raw image data
     * @param string $original_path Original image path
     * @return object|false Validation result object with is_valid property
     */
    public function process_and_validate_image_data(string $raw_data, string $original_path): object|false
    {
        try {
            // Basic validation checks
            if (empty($raw_data)) {
                return (object) ['is_valid' => false, 'error' => 'Empty image data'];
            }
            
            // Check if data looks like image content (basic magic number check)
            $magic_bytes = substr($raw_data, 0, 4);
            $is_image = false;
            
            // Check common image formats
            if (str_starts_with($magic_bytes, "\xFF\xD8\xFF")) {
                $is_image = true; // JPEG
            } elseif (str_starts_with($magic_bytes, "\x89PNG")) {
                $is_image = true; // PNG
            } elseif (str_starts_with($magic_bytes, "GIF8")) {
                $is_image = true; // GIF
            } elseif (str_starts_with($magic_bytes, "RIFF") && substr($raw_data, 8, 4) === "WEBP") {
                $is_image = true; // WebP
            }
            
            // Try to get image info using getimagesizefromstring
            $image_info = @getimagesizefromstring($raw_data);
            if ($image_info !== false) {
                $is_image = true;
            }
            
            return (object) [
                'is_valid' => $is_image,
                'width' => $image_info[0] ?? null,
                'height' => $image_info[1] ?? null,
                'mime_type' => $image_info['mime'] ?? null,
                'original_path' => $original_path
            ];
            
        } catch (\Exception $e) {
            return (object) ['is_valid' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Read file contents - bridge to FilesystemService
     *
     * @param string $path File path
     * @param string|null $adapter_name Optional specific adapter
     * @return string|false File contents or false on failure
     */
    public function read(string $path, ?string $adapter_name = null): string|false
    {
        return $this->filesystem_service->read($path, $adapter_name);
    }
    
    /**
     * Validate dimension parameter - delegates to ValidationService for comprehensive handling
     *
     * @param string $dimension Dimension value (e.g., '50%', '100', '100px')
     * @param int $reference_dimension Reference dimension for percentage calculations
     * @return int Validated dimension in pixels (returns 0 for invalid dimensions)
     */
    public function validate_dimension(string $dimension, int $reference_dimension): int
    {
        // Use ValidationService for comprehensive dimension validation
        $validation_service = ServiceCache::validation();
        $result = $validation_service->validate_dimension($dimension, $reference_dimension);
        
        // Convert result to non-negative integer (ImageUtilities contract)
        if ($result === null || $result === false) {
            return 0; // Invalid or empty dimension
        }
        
        return max(0, (int) $result);
    }
    
    /**
     * Write file contents - bridge to FilesystemService
     *
     * @param string $path File path
     * @param string $contents File contents
     * @param string|null $adapter_name Optional specific adapter
     * @param int $attempts Number of write attempts (legacy parameter)
     * @return bool True on success, false on failure
     */
    public function write(string $path, string $contents, ?string $adapter_name = null, int $attempts = 5): bool
    {
        return $this->filesystem_service->write($path, $contents, $adapter_name, $attempts);
    }
    
    // ========================================================================
    // GD Resource Optimization Methods
    // ========================================================================
    
    /**
     * Convert GD resource directly to Imagine Image
     * 
     * Major performance optimization: Instead of converting GD resource to PNG stream
     * and then loading that stream into Imagine, we create the Imagine Image directly
     * from the GD resource using the constructor.
     * 
     * This eliminates:
     * - PNG encoding (imagepng + ob_get_clean)
     * - Memory stream creation (fopen + fwrite + rewind)
     * - PNG decoding (Imagine->load)
     * 
     * @param resource|\GdImage $gd_resource GD image resource
     * @return ImageInterface Optimized Imagine image
     */
    public function gdResourceToImagine($gd_resource): ImageInterface
    {
        if (!is_resource($gd_resource) && !is_object($gd_resource)) {
            throw new \RuntimeException('Invalid GD resource provided');
        }
        
        // Create Imagine Image directly from GD resource
        $palette = new RGB();
        $metadata = new MetadataBag();
        
        return new Image($gd_resource, $palette, $metadata);
    }
    
    /**
     * Extract GD resource from Imagine Image (for processing)
     * 
     * @param ImageInterface $image Imagine image
     * @return resource|\GdImage GD resource for processing
     */
    public function imagineToGdResource(ImageInterface $image)
    {
        return imagecreatefromstring($image->__toString());
    }
    
    /**
     * Apply GD operation and return optimized Imagine image
     * 
     * Convenience method that combines GD resource extraction, operation application,
     * and optimized conversion back to Imagine image.
     * 
     * @param ImageInterface $image Source image
     * @param callable $operation Function to apply to GD resource
     * @return ImageInterface Processed image
     */
    public function applyGdOperation(ImageInterface $image, callable $operation): ImageInterface
    {
        // Extract GD resource
        $gd_resource = $this->imagineToGdResource($image);
        
        // Apply operation to GD resource
        $operation($gd_resource);
        
        // Convert back to Imagine using optimized method
        return $this->gdResourceToImagine($gd_resource);
    }
    
    /**
     * Legacy stream conversion method (for compatibility/comparison)
     * 
     * This is the old inefficient method that many filters were using.
     * Kept for reference and potential fallback scenarios.
     * 
     * @param resource|\GdImage $gd_resource
     * @return resource Stream resource
     * @deprecated Use gdResourceToImagine instead for better performance
     */
    public function gdResourceToStream($gd_resource)
    {
        $stream = fopen('php://temp', 'r+');
        
        // Save as PNG to preserve quality and transparency
        ob_start();
        imagepng($gd_resource);
        $image_data = ob_get_clean();
        
        fwrite($stream, $image_data);
        rewind($stream);
        
        return $stream;
    }
}
