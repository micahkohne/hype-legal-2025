<?php

/**
 * JCOGS Image Pro - After File Update Extension
 * ==============================================
 * Handles cache cleanup when files are saved/updated or deleted from the file manager
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

namespace JCOGSDesign\JCOGSImagePro\Extensions;

use ExpressionEngine\Service\Addon\Controllers\Extension\AbstractRoute;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

/**
 * After File Update Extension
 * 
 * Consolidated extension handling cleanup of cached images when source files 
 * are updated or deleted. Follows Legacy pattern where a single method handles
 * both after_file_save and after_file_delete hooks.
 * 
 * Migrated from Legacy ext.jcogs_img.php->after_file_update() with
 * enhanced Pro architecture using ServiceCache pattern.
 * 
 * Key Features:
 * - Handles both file saves/updates and deletions
 * - Automatic cache cleanup when auto-manage is enabled
 * - Cache impact notifications when auto-manage is disabled
 * - Integration with Pro cache management service
 * - Support for multiple filesystem adapters
 * - Single method pattern reducing code duplication
 */
class AfterFileUpdate extends AbstractRoute
{
    protected $addon_name = 'jcogs_img_pro';
    protected $version = '2.0.0';
    
    /**
     * @var object Settings service instance
     */
    private $settings_service;
    
    /**
     * @var object Cache management service instance
     */
    private $cache_service;
    
    /**
     * @var object Utilities service instance
     */
    private $utilities_service;
    
    /**
     * Constructor - Initialize services using ServiceCache
     */
    public function __construct()
    {
        // Use ServiceCache for optimal performance and consistency
        $this->settings_service = ServiceCache::settings();
        $this->cache_service = ServiceCache::cache();
        $this->utilities_service = ServiceCache::utilities();
    }
    
    /**
     * Process file update (save/update or delete) - check for affected cache entries
     * 
     * Consolidated method that handles both after_file_save and after_file_delete hooks.
     * Migrated from Legacy ext.jcogs_img.php->after_file_update() with
     * enhanced Pro architecture.
     * 
     * This follows the Legacy pattern where a single method is registered for
     * multiple hooks via database configuration, reducing code duplication.
     * 
     * @param mixed $file File object or ID being saved/updated/deleted
     * @param array $values File data array with update information
     * @return mixed Returns the file object unchanged to allow processing to continue
     */
    public function after_file_update($file, $values)
    {
        // Input validation - following Legacy pattern
        if (empty($file) || empty($values)) {
            return $file;
        }
        
        try {
            // Get file information for cache lookup
            $file_info = $this->_extract_file_information($values);
            if (!$file_info) {
                return $file; // Unable to extract file info, allow operation to proceed
            }
            
            // Search for affected cache entries
            $affected_images = $this->_find_affected_cache_entries($file_info);
            
            if (empty($affected_images)) {
                return $file; // No affected images found
            }
            
            // Check auto-manage setting
            $auto_manage_enabled = $this->_is_auto_manage_enabled();
            
            if ($auto_manage_enabled) {
                // Auto-manage enabled - delete affected cache entries
                $this->_cleanup_affected_cache_entries($affected_images, $file_info);
            } else {
                // Auto-manage disabled - show notification
                $this->_show_cache_impact_notification(count($affected_images));
            }
            
        } catch (\Exception $e) {
            // Log error but don't prevent file operation
            $this->utilities_service->debug_log("Error in AfterFileUpdate: " . $e->getMessage());
        }
        
        return $file; // Always return file object unchanged to allow operation to proceed
    }
    
    /**
     * Extract file information from file operation data
     * 
     * @param array $values File data from save/delete event
     * @return array|null File information array or null if invalid
     */
    private function _extract_file_information(array $values): ?array
    {
        // Get filename from data - following Legacy pattern
        if (empty($values['file_name'])) {
            return null;
        }
        
        $filename_array = pathinfo($values['file_name']);
        if (!$filename_array || empty($filename_array['filename'])) {
            return null;
        }
        
        // Get upload location info
        if (empty($values['upload_location_id'])) {
            return null;
        }
        
        // Use utilities service to parse file directory (Pro pattern)
        $upload_path = $this->utilities_service->parse_file_directory($values['upload_location_id']);
        if (!$upload_path) {
            return null;
        }
        
        $path_info = pathinfo($upload_path);
        if (empty($path_info['dirname'])) {
            return null;
        }
        
        // Build file information array following Legacy structure
        $filename_array['source_path'] = $path_info['dirname'];
        
        return [
            'filename' => $filename_array['filename'],
            'extension' => $filename_array['extension'] ?? '',
            'source_path' => $filename_array['source_path'],
            'upload_location_id' => $values['upload_location_id']
        ];
    }
    
    /**
     * Find cache entries affected by file changes
     * 
     * @param array $file_info File information for lookup
     * @return array Array of affected cache entries
     */
    private function _find_affected_cache_entries(array $file_info): array
    {
        // Use cache service to search for entries with matching source file
        return $this->cache_service->find_entries_by_source_file(
            $file_info['source_path'],
            $file_info['filename'],
            $file_info['extension']
        );
    }
    
    /**
     * Check if auto-manage cache setting is enabled
     * 
     * @return bool True if auto-manage is enabled
     */
    private function _is_auto_manage_enabled(): bool
    {
        $auto_manage_cache_setting = $this->settings_service->get_settings('auto_manage_cache');
        return !empty($auto_manage_cache_setting) && $auto_manage_cache_setting === 'y';
    }
    
    /**
     * Clean up affected cache entries when auto-manage is enabled
     * 
     * @param array $affected_images Array of affected cache entries
     * @param array $file_info File information for logging
     * @return void
     */
    private function _cleanup_affected_cache_entries(array $affected_images, array $file_info): void
    {
        $deleted_count = 0;
        
        foreach ($affected_images as $cache_entry) {
            try {
                if ($this->cache_service->delete_cache_entry($cache_entry)) {
                    $deleted_count++;
                }
            } catch (\Exception $e) {
                $this->utilities_service->debug_log("Failed to delete cache entry: " . $e->getMessage());
            }
        }
        
        if ($deleted_count > 0) {
            // Show success message
            $this->_show_cleanup_success_notification($deleted_count, $file_info['filename']);
        }
    }
    
    /**
     * Show notification about cache impact when auto-manage is disabled
     * 
     * @param int $affected_count Number of affected cache entries
     * @return void
     */
    private function _show_cache_impact_notification(int $affected_count): void
    {
        if (REQ === 'CP') {
            ee('CP/Alert')->makeInline('jcogs_img_pro_cache_impact')
                ->asWarning()
                ->withTitle('JCOGS Image Pro Cache Impact')
                ->addToBody("File changes may affect {$affected_count} cached image(s). Consider enabling auto-manage cache or manually clearing affected entries.")
                ->now();
        }
    }
    
    /**
     * Show success notification for cache cleanup
     * 
     * @param int $deleted_count Number of cache entries deleted
     * @param string $filename Original filename
     * @return void
     */
    private function _show_cleanup_success_notification(int $deleted_count, string $filename): void
    {
        if (REQ === 'CP') {
            ee('CP/Alert')->makeInline('jcogs_img_pro_cache_cleanup')
                ->asSuccess()
                ->withTitle('JCOGS Image Pro Cache Cleanup')
                ->addToBody("Automatically removed {$deleted_count} cached image(s) affected by changes to '{$filename}'.")
                ->now();
        }
    }
}
