<?php

/**
 * JCOGS Image Pro - Delete Connection Route
 * ==========================================
 * Dedicated route for deleting cache connections
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

use JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\ImageAbstractRoute;

class DeleteConnection extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'caching/delete_connection';

    /**
     * Process connection deletion request
     * 
     * @param mixed $id Route parameter (may contain action name)
     * @return $this Fluent interface for EE7 routing
     */
    public function process($id = false)
    {
        // Get connection name from URL segments or POST data
        $connection_name = $this->utilities_service->getConnectionNameFromUrl();
        if (empty($connection_name)) {
            $connection_name = ee()->input->post('original_name') ?: ee()->input->post('connection_name');
        }
        
        if (empty($connection_name)) {
            ee('CP/Alert')->makeInline('missing-connection')
                ->asIssue()
                ->withTitle('Missing Connection')
                ->addToBody('No connection specified for deletion.')
                ->defer();
        } else {
            // Get current named adapters configuration
            $named_adapters_config = $this->settings_service->getNamedFilesystemAdapters();
            
            if (isset($named_adapters_config['connections'][$connection_name])) {
                // Remove the connection
                unset($named_adapters_config['connections'][$connection_name]);
                
                // If this was the default connection, clear the default
                if (($named_adapters_config['default_connection'] ?? '') === $connection_name) {
                    $named_adapters_config['default_connection'] = '';
                }
                
                // Save the updated configuration
                $this->settings_service->setNamedFilesystemAdapters($named_adapters_config);
                
                ee('CP/Alert')->makeInline('connection-deleted')
                    ->asSuccess()
                    ->withTitle('Connection Deleted')
                    ->addToBody('Cache connection "' . $connection_name . '" has been deleted.')
                    ->defer();
            } else {
                ee('CP/Alert')->makeInline('connection-not-found')
                    ->asIssue()
                    ->withTitle('Connection Not Found')
                    ->addToBody('Connection "' . $connection_name . '" does not exist.')
                    ->defer();
            }
        }

        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching'));
        return $this;
    }
}
