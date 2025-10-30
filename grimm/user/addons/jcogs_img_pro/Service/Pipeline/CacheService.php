<?php

/**
 * Cache Service
 * =============
 * Service for managing image cache operations in JCOGS Image Pro
 * ==============================================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      File available since Sprint 4
 */

namespace JCOGSDesign\JCOGSImagePro\Service\Pipeline;

use JCOGSDesign\JCOGSImagePro\Service\Settings;
use JCOGSDesign\JCOGSImagePro\Service\Pipeline\AbstractService;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

class CacheService extends AbstractService
{
    /**
     * Cache invalidation threshold in seconds
     * When overwrite=yes, images older than this are considered stale
     */
    private int $cache_invalidation_threshold = 3600; // 1 hour
    
    /**
     * Performance tracking for cache operations
     */
    private array $performance_metrics = [];
    
    public function __construct()
    {
        parent::__construct('CacheService');
        // $this->settings_service is now available via parent
        // All other common services are also available
    }
    
    /**
     * Clean up expired cache files
     * 
     * @param bool $force Force cleanup regardless of last run time
     * @return array Cleanup statistics
     */
    public function cleanup_expired_cache(bool $force = false): array
    {
        $start_time = microtime(true);
        $stats = [
            'files_scanned' => 0,
            'files_deleted' => 0,
            'bytes_freed' => 0,
            'errors' => []
        ];
        
        try {
            $settings = $this->settings_service->get_settings();
            $cache_dir = $settings['img_cp_cache_directory'] ?? '/cache/images/';
            $cache_ttl = $settings['img_cp_cache_ttl'] ?? 86400; // 24 hours
            
            if (!is_dir($cache_dir)) {
                return $stats;
            }
            
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cache_dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $stats['files_scanned']++;
                    
                    $file_age = time() - $file->getMTime();
                    if ($file_age > $cache_ttl) {
                        $file_size = $file->getSize();
                        
                        if (unlink($file->getPathname())) {
                            $stats['files_deleted']++;
                            $stats['bytes_freed'] += $file_size;
                        } else {
                            $stats['errors'][] = "Failed to delete: " . $file->getPathname();
                        }
                    }
                }
            }
            
            $this->_record_performance_metric('cache_cleanup', $start_time, $stats);
            
        } catch (\Exception $e) {
            $stats['errors'][] = "Cache cleanup error: " . $e->getMessage();
        }
        
        return $stats;
    }
    
    /**
     * Generate cache file path based on image parameters
     * 
     * @param Context $context Processing context
     * @param string $source_path Original image path
     * @return string Cache file path
     */
    public function generate_cache_path(Context $context, string $source_path): string
    {
        $settings = $this->settings_service->get_settings();
        $cache_dir = $settings['img_cp_cache_directory'] ?? '/cache/images/';
        
        // Extract filename from source path for the CacheKeyGenerator
        $filename = basename($source_path);
        $path_info = pathinfo($filename);
        $filename_without_ext = $path_info['filename'] ?? 'unknown';
        
        // Use the definitive CacheKeyGenerator service
        $cache_key = ServiceCache::cache_key_generator()->generate_cache_key(
            $filename_without_ext, 
            $context->get_tag_params()
        );
        
        // Extract file extension from context or source
        $save_as = $context->get_param('save_as', '');
        if (empty($save_as)) {
            $save_as = $context->get_param('save_type', '');
        }
        if (empty($save_as)) {
            $extension = $path_info['extension'] ?? 'jpg';
        } else {
            $extension = $save_as;
        }
        
        // Build cache path using the definitive cache key
        $cache_filename = $cache_key . '.' . $extension;
        
        return rtrim($cache_dir, '/') . '/' . $cache_filename;
    }
    
    /**
     * Get cache statistics for reporting
     * 
     * @return array Cache statistics
     */
    public function get_cache_statistics(): array
    {
        $settings = $this->settings_service->get_settings();
        $cache_dir = $settings['img_cp_cache_directory'] ?? '/cache/images/';
        
        $stats = [
            'cache_directory' => $cache_dir,
            'total_files' => 0,
            'total_size' => 0,
            'oldest_file' => null,
            'newest_file' => null,
            'performance_metrics' => $this->performance_metrics
        ];
        
        if (!is_dir($cache_dir)) {
            return $stats;
        }
        
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cache_dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            $oldest_time = PHP_INT_MAX;
            $newest_time = 0;
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $stats['total_files']++;
                    $stats['total_size'] += $file->getSize();
                    
                    $mtime = $file->getMTime();
                    if ($mtime < $oldest_time) {
                        $oldest_time = $mtime;
                        $stats['oldest_file'] = $file->getPathname();
                    }
                    if ($mtime > $newest_time) {
                        $newest_time = $mtime;
                        $stats['newest_file'] = $file->getPathname();
                    }
                }
            }
            
        } catch (\Exception $e) {
            // Silent failure, return what we have
        }
        
        return $stats;
    }
    
    /**
     * Check if image cache should be overwritten based on parameters
     * 
     * @param Context $context Processing context with parameters
     * @param string $cache_path Expected cache file path
     * @return bool True if cache should be regenerated
     */
    public function should_overwrite_cache(Context $context, string $cache_path): bool
    {
        $start_time = microtime(true);
        
        try {
            // S4-F2-1: Check for explicit overwrite parameter
            $overwrite_param = $context->get_param('overwrite', '');
            if (strtolower(trim($overwrite_param)) === 'yes') {
                $this->_record_performance_metric('cache_overwrite_explicit', $start_time);
                return true;
            }
            
            // S4-F2-2: Check for force parameter (alias for overwrite)
            $force_param = $context->get_param('force', '');
            if (strtolower(trim($force_param)) === 'yes') {
                $this->_record_performance_metric('cache_overwrite_force', $start_time);
                return true;
            }
            
            // S4-F2-3: Check if cache file doesn't exist
            if (!file_exists($cache_path)) {
                $this->_record_performance_metric('cache_miss', $start_time);
                return true;
            }
            
            // S4-F2-4: Check cache freshness with settings
            $settings = $this->settings_service->get_settings();
            $cache_ttl = $settings['img_cp_cache_ttl'] ?? 86400; // 24 hours default
            
            $file_age = time() - filemtime($cache_path);
            if ($file_age > $cache_ttl) {
                $this->_record_performance_metric('cache_expired', $start_time);
                return true;
            }
            
            // S4-F2-5: Check for cache_refresh parameter for conditional refresh
            $cache_refresh = $context->get_param('cache_refresh', '');
            if (strtolower(trim($cache_refresh)) === 'auto' && $file_age > $this->cache_invalidation_threshold) {
                $this->_record_performance_metric('cache_auto_refresh', $start_time);
                return true;
            }
            
            $this->_record_performance_metric('cache_hit', $start_time);
            return false;
            
        } catch (\Exception $e) {
            // On error, default to regenerating cache for safety
            $this->_record_performance_metric('cache_error', $start_time);
            return true;
        }
    }
    
    /**
     * Record performance metrics for monitoring
     * 
     * @param string $operation Operation name
     * @param float $start_time Operation start time
     * @param array $context Additional context data
     */
    private function _record_performance_metric(string $operation, float $start_time, array $context = []): void
    {
        $duration = microtime(true) - $start_time;
        
        if (!isset($this->performance_metrics[$operation])) {
            $this->performance_metrics[$operation] = [
                'count' => 0,
                'total_time' => 0,
                'avg_time' => 0,
                'max_time' => 0
            ];
        }
        
        $metric = &$this->performance_metrics[$operation];
        $metric['count']++;
        $metric['total_time'] += $duration;
        $metric['avg_time'] = $metric['total_time'] / $metric['count'];
        $metric['max_time'] = max($metric['max_time'], $duration);
        
        if (!empty($context)) {
            $metric['last_context'] = $context;
        }
    }
}
