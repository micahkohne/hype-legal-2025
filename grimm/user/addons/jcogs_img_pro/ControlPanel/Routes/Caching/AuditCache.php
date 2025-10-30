<?php

/**
 * JCOGS Image Pro - Cache Audit Route
 * ====================================
 * Dedicated route for auditing cache connections - cleaning expired files
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

class AuditCache extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'caching/audit_cache';

    /**
     * Process cache audit request
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
                ->addToBody('No connection specified for cache audit.')
                ->defer();
                
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching'));
            return $this;
        }

        try {
            // Get cached cache management service
            $cache_service = $this->cache_service;
            
            // Perform actual audit operation
            $result = $cache_service->audit_cache_location($connection_name);
            
            if ($result['success']) {
                $files_removed = $result['files_removed'] ?? 0;
                
                ee('CP/Alert')->makeInline('audit-completed')
                    ->asSuccess()
                    ->withTitle('Cache Audit Completed')
                    ->addToBody("Audit completed for connection " . $connection_name . ". " . $files_removed . " expired files were removed.")
                    ->defer();
            } else {
                ee('CP/Alert')->makeInline('audit-failed')
                    ->asIssue()
                    ->withTitle('Cache Audit Failed')
                    ->addToBody($result['message'] ?? 'Cache audit operation failed.')
                    ->defer();
            }
            
        } catch (Exception $e) {
            $this->utilities_service->debug_log("Cache audit failed for {$connection_name}: " . $e->getMessage());
            
            ee('CP/Alert')->makeInline('audit-error')
                ->asIssue()
                ->withTitle('Cache Audit Error')
                ->addToBody("Cache audit failed: " . $e->getMessage())
                ->defer();
        }

        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching'));
        return $this;
    }
}
