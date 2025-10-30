<?php

/**
 * JCOGS Image Pro - Update Connection Route
 * =========================================
 * Dedicated route for processing connection update form submissions
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

class UpdateConnection extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'caching/update_connection';

    /**
     * Process connection update form submission
     * 
     * @param mixed $id Route parameter (not used for POST submissions)
     * @return $this Fluent interface for EE7 routing
     */
    public function process($id = false)
    {
        // Get action type to determine if this is add or edit operation
        $action_type = ee()->input->post('action_type');
        $is_editing = ($action_type === 'edit');
        
        // Validate action type
        if (!in_array($action_type, ['add', 'edit'])) {
            ee('CP/Alert')->makeInline('invalid-action')
                ->asIssue()
                ->withTitle('Invalid Action')
                ->addToBody('Invalid form action type.')
                ->defer();
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching'));
            return $this;
        }
        
        // Get connection name and adapter type from POST data
        if ($is_editing) {
            // For editing, get values from hidden fields (since visible fields are disabled)
            $connection_name = ee()->input->post('connection_name_hidden');
            $adapter_type = ee()->input->post('adapter_type_hidden');
        } else {
            // For adding, get values from visible form fields
            $connection_name = ee()->input->post('connection_name');
            $adapter_type = ee()->input->post('adapter_type');
        }
        
        if (empty($connection_name) || empty($adapter_type)) {
            ee('CP/Alert')->makeInline('missing-data')
                ->asIssue()
                ->withTitle('Missing Data')
                ->addToBody('Connection name and adapter type are required.')
                ->defer();
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching'));
            return $this;
        }

        // Get current named adapters configuration
        $named_adapters_config = $this->settings_service->getNamedFilesystemAdapters();
        
        // Use the editing flag we determined earlier
        $is_new_connection = !$is_editing;
        
        // If editing, verify the connection still exists
        if ($is_editing && !isset($named_adapters_config['connections'][$connection_name])) {
            ee('CP/Alert')->makeInline('connection-not-found')
                ->asIssue()
                ->withTitle('Connection Not Found')
                ->addToBody('The connection you are trying to edit no longer exists.')
                ->defer();
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching'));
            return $this;
        }
        
        if ($is_new_connection) {
            // For new connections, verify the connection name doesn't already exist
            if (isset($named_adapters_config['connections'][$connection_name])) {
                ee('CP/Alert')->makeInline('connection-exists')
                    ->asIssue()
                    ->withTitle('Connection Already Exists')
                    ->addToBody('A connection named "' . $connection_name . '" already exists. Please choose a different name.')
                    ->defer();
                ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/add_connection'));
                return $this;
            }
            
            // Validate connection name format
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $connection_name)) {
                ee('CP/Alert')->makeInline('invalid-connection-name')
                    ->asIssue()
                    ->withTitle('Invalid Connection Name')
                    ->addToBody('Connection name can only contain letters, numbers, and underscores.')
                    ->defer();
                ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/add_connection'));
                return $this;
            }
        } else {
            // For existing connections, verify connection exists
            if (!isset($named_adapters_config['connections'][$connection_name])) {
                ee('CP/Alert')->makeInline('connection-not-found')
                    ->asIssue()
                    ->withTitle('Connection Not Found')
                    ->addToBody('Connection "' . $connection_name . '" does not exist.')
                    ->defer();
                ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching'));
                return $this;
            }
        }

        // Build updated configuration based on adapter type
        $updated_config = $this->_buildUpdatedConfig($adapter_type);
        
        if ($updated_config === false) {
            ee('CP/Alert')->makeInline('validation-error')
                ->asIssue()
                ->withTitle('Validation Error')
                ->addToBody('Please check your input and try again.')
                ->defer();
                
            if ($is_new_connection) {
                ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/add_connection'));
            } else {
                ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/edit_connection', ['connection' => $connection_name]));
            }
            return $this;
        }

        // Update the connection configuration with correct structure
        $named_adapters_config['connections'][$connection_name] = [
            'type' => $adapter_type,
            'is_valid' => true, // Assume valid for now - could add validation later
            'config' => $updated_config
        ];

        // Save the updated configuration
        try {
            $this->settings_service->setNamedFilesystemAdapters($named_adapters_config);
            
            if ($is_new_connection) {
                ee('CP/Alert')->makeInline('connection-created')
                    ->asSuccess()
                    ->withTitle('Connection Created')
                    ->addToBody('Cache connection "' . $connection_name . '" has been created successfully.')
                    ->defer();
            } else {
                ee('CP/Alert')->makeInline('connection-updated')
                    ->asSuccess()
                    ->withTitle('Connection Updated')
                    ->addToBody('Cache connection "' . $connection_name . '" has been updated successfully.')
                    ->defer();
            }
        } catch (\Exception $e) {
            $action = $is_new_connection ? 'create' : 'save';
            ee('CP/Alert')->makeInline('save-error')
                ->asIssue()
                ->withTitle(ucfirst($action) . ' Error')
                ->addToBody('Failed to ' . $action . ' connection settings: ' . $e->getMessage())
                ->defer();
        }

        // Redirect back to caching overview
        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching'));
        return $this;
    }

    /**
     * Build updated configuration array based on adapter type and form data
     * 
     * @param string $adapter_type
     * @return array|false Configuration array or false on validation error
     */
    private function _buildUpdatedConfig($adapter_type)
    {
        switch ($adapter_type) {
            case 'local':
                return $this->_buildLocalConfig();
            case 's3':
                return $this->_buildS3Config();
            case 'r2':
                return $this->_buildR2Config();
            case 'dospaces':
                return $this->_buildDoSpacesConfig();
            default:
                return false;
        }
    }

    /**
     * Build local adapter configuration from form data
     */
    private function _buildLocalConfig()
    {
        $cache_directory = trim(ee()->input->post('local_cache_directory'));
        $path_prefix = trim(ee()->input->post('local_cdn_path_prefix'));
        $always_full_urls = ee()->input->post('img_cp_class_always_output_full_urls');
        
        if (empty($cache_directory)) {
            return false;
        }

        $config = [
            'cache_directory' => $cache_directory
        ];
        
        // Add optional path prefix if provided
        if (!empty($path_prefix)) {
            $config['path_prefix'] = $path_prefix;
        }
        
        // Add always output full URLs setting if provided
        if (!empty($always_full_urls)) {
            $config['always_output_full_urls'] = $always_full_urls;
        }

        return $config;
    }

    /**
     * Build S3 adapter configuration from form data  
     */
    private function _buildS3Config()
    {
        $bucket = trim(ee()->input->post('img_cp_flysystem_adapter_s3_bucket'));
        $region = trim(ee()->input->post('img_cp_flysystem_adapter_s3_region'));
        $key = trim(ee()->input->post('img_cp_flysystem_adapter_s3_key'));
        $secret = trim(ee()->input->post('img_cp_flysystem_adapter_s3_secret'));
        $server_path = trim(ee()->input->post('img_cp_flysystem_adapter_s3_server_path'));
        $url = trim(ee()->input->post('img_cp_flysystem_adapter_s3_url'));

        if (empty($bucket) || empty($region) || empty($key)) {
            return false;
        }

        $config = [
            'bucket' => $bucket,
            'region' => $region,
            'key' => $key,
            'server_path' => $server_path,
            'url' => $url
        ];

        // Only update secret if provided (allow keeping existing)
        if (!empty($secret)) {
            $config['secret'] = $secret;
        } else {
            // Keep existing secret if not provided - determine connection name based on action type
            $existing_config = $this->settings_service->getNamedFilesystemAdapters();
            $action_type = ee()->input->post('action_type');
            $connection_name = ($action_type === 'edit') ? ee()->input->post('connection_name_hidden') : ee()->input->post('connection_name');
            if (isset($existing_config['connections'][$connection_name]['config']['secret'])) {
                $config['secret'] = $existing_config['connections'][$connection_name]['config']['secret'];
            }
        }

        return $config;
    }

    /**
     * Build R2 adapter configuration from form data
     */
    private function _buildR2Config()
    {
        $account_id = trim(ee()->input->post('img_cp_flysystem_adapter_r2_account_id'));
        $bucket = trim(ee()->input->post('img_cp_flysystem_adapter_r2_bucket'));
        $key = trim(ee()->input->post('img_cp_flysystem_adapter_r2_key'));
        $secret = trim(ee()->input->post('img_cp_flysystem_adapter_r2_secret'));
        $server_path = trim(ee()->input->post('img_cp_flysystem_adapter_r2_server_path'));
        $url = trim(ee()->input->post('img_cp_flysystem_adapter_r2_url'));

        if (empty($bucket) || empty($key)) {
            return false;
        }

        $config = [
            'account_id' => $account_id,
            'bucket' => $bucket,
            'key' => $key,
            'server_path' => $server_path,
            'url' => $url
        ];

        // Only update secret if provided (allow keeping existing)
        if (!empty($secret)) {
            $config['secret'] = $secret;
        } else {
            // Keep existing secret if not provided - determine connection name based on action type
            $existing_config = $this->settings_service->getNamedFilesystemAdapters();
            $action_type = ee()->input->post('action_type');
            $connection_name = ($action_type === 'edit') ? ee()->input->post('connection_name_hidden') : ee()->input->post('connection_name');
            if (isset($existing_config['connections'][$connection_name]['config']['secret'])) {
                $config['secret'] = $existing_config['connections'][$connection_name]['config']['secret'];
            }
        }

        return $config;
    }

    /**
     * Build DigitalOcean Spaces adapter configuration from form data
     */
    private function _buildDoSpacesConfig()
    {
        $key = trim(ee()->input->post('img_cp_flysystem_adapter_dospaces_key'));
        $secret = trim(ee()->input->post('img_cp_flysystem_adapter_dospaces_secret'));
        $region = trim(ee()->input->post('img_cp_flysystem_adapter_dospaces_region'));
        $space = trim(ee()->input->post('img_cp_flysystem_adapter_dospaces_space'));
        $server_path = trim(ee()->input->post('img_cp_flysystem_adapter_dospaces_server_path'));
        $url = trim(ee()->input->post('img_cp_flysystem_adapter_dospaces_url'));

        if (empty($space) || empty($region) || empty($key)) {
            return false;
        }

        $config = [
            'key' => $key,
            'region' => $region,
            'space' => $space,
            'server_path' => $server_path,
            'url' => $url
        ];

        // Only update secret if provided (allow keeping existing)
        if (!empty($secret)) {
            $config['secret'] = $secret;
        } else {
            // Keep existing secret if not provided - determine connection name based on action type
            $existing_config = $this->settings_service->getNamedFilesystemAdapters();
            $action_type = ee()->input->post('action_type');
            $connection_name = ($action_type === 'edit') ? ee()->input->post('connection_name_hidden') : ee()->input->post('connection_name');
            if (isset($existing_config['connections'][$connection_name]['config']['secret'])) {
                $config['secret'] = $existing_config['connections'][$connection_name]['config']['secret'];
            }
        }

        return $config;
    }
}
