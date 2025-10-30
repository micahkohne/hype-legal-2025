<?php

/**
 * JCOGS Image Pro - Cache Clear Route
 * ===================================
 * Dedicated route for clearing all cache files from a connection
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 3 Legacy Independence - Named Connections System
 */

namespace JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\Caching;

use Exception;
use JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\ImageAbstractRoute;

class ClearCache extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'caching/clear_cache';

    /**
     * Process cache clear request
     * 
     * @param mixed $id Route parameter (may contain action name)
     * @return $this Fluent interface for EE7 routing
     */
    public function process($id = false)
    {
        // Get connection name from URL segments
        $connection_name = $this->utilities_service->getConnectionNameFromUrl();
        
        if (empty($connection_name)) {
            ee('CP/Alert')->makeInline('missing-connection')
                ->asIssue()
                ->withTitle('Missing Connection')
                ->addToBody('No connection specified for cache clearing.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching'));
            return $this;
        }

        try {
            // Get cached cache management service  
            $cache_service = $this->cache_service;
            
            // Perform actual clear operation
            $result = $cache_service->clear_cache_location($connection_name);
            
            if ($result['success']) {
                $files_removed = $result['files_removed'] ?? 0;
                
                ee('CP/Alert')->makeInline('cache-cleared')
                    ->asSuccess()
                    ->withTitle('Cache Cleared Successfully')
                    ->addToBody("Cache cleared for connection " . $connection_name . ". " . $files_removed . " files were removed.")
                    ->defer();
            } else {
                ee('CP/Alert')->makeInline('cache-clear-failed')
                    ->asIssue()
                    ->withTitle('Cache Clear Failed')
                    ->addToBody($result['message'] ?? 'Cache clear operation failed.')
                    ->defer();
            }
            
        } catch (Exception $e) {
            $this->utilities_service->debug_log("Cache clear failed for {$connection_name}: " . $e->getMessage());
            
            ee('CP/Alert')->makeInline('cache-clear-error')
                ->asIssue()
                ->withTitle('Cache Clear Error')  
                ->addToBody("Cache clear failed: " . $e->getMessage())
                ->defer();
        }

        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching'));
        return $this;
    }
}
