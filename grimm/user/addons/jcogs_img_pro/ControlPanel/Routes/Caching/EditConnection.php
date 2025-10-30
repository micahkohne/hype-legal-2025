<?php

/**
 * JCOGS Image Pro - Edit Cache Connection Route
 * ============================================
 * Route for editing existing cache connections with proper EE7 CP/Form integration.
 * Loads existing connection data and populates form fields with current values.
 * Connection name and adapter type are only to prevent configuration conflicts.
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

namespace JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\Caching;

use JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\ImageAbstractRoute;

class EditConnection extends ImageAbstractRoute
{
    /**
     * @var string
     */
    protected $route_path = 'caching/edit_connection';

    /**
     * @var string
     */
    protected $cp_page_title;

    /**
     * @var array
     */
    protected $cp_breadcrumbs;

    /**
     * @var array|null
     */
    private $connection_data = null;

    /**
     * @var string|null
     */
    private $connection_name = null;

    /**
     * Display the edit connection form using EE7 CP/Form system
     * 
     * @param mixed $id Route parameter (not used - connection name comes from GET parameter)
     * @return $this
     */
    public function process($id = false)
    {
        // Load language file
        $this->load_language();
        
        // Load CSS and JavaScript assets
        $this->_load_edit_connection_assets();
        
        // Get connection name from GET parameter instead of URL segment
        $connection_name = ee()->input->get('connection');
        
        // Debug log to confirm parameter extraction
        $this->utilities_service->debug_log('EditConnection: Got connection name from GET parameter: ' . var_export($connection_name, true));
        
        if (empty($connection_name)) {
            ee('CP/Alert')->makeInline('shared-form')
                ->asIssue()
                ->withTitle('Error')
                ->addToBody('Connection name is required for editing.')
                ->defer();
                
            return ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching'));
        }
        
        $this->connection_name = $connection_name;
        
        // Load the connection data
        $this->connection_data = $this->settings_service->getNamedConnection($connection_name, true);
        if ($this->connection_data === null) {
            ee('CP/Alert')->makeInline('shared-form')
                ->asIssue()
                ->withTitle('Error')
                ->addToBody("Connection " . $connection_name . " not found.")
                ->defer();
                
            return ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching'));
        }
        
        // Set page title and breadcrumb
        $this->cp_page_title = "Edit Connection: {$connection_name}";
        $this->addBreadcrumb('index', 'JCOGS Image Pro')
            ->addBreadcrumb('caching', 'Cache Management')
            ->addBreadcrumb('', 'Edit Connection');
        
        // Build sidebar
        $this->build_sidebar($this->_get_current_settings());
        
        // Build the form using EE7 CP/Form system with existing connection data
        $form = $this->_buildStandardForm();
        
        // Add hidden fields to the connection name fieldset (to avoid creating extra form sections)
        $main_group = $form->getGroup('Named Cache Directory Connection Configuration');
        $name_fieldset = $main_group->getFieldSet('jcogs_img_cp_choose_flysystem_adapter_name');
        
        // Add hidden fields for form processing
        $name_fieldset->getField('action_type', 'hidden')->setValue('edit');
        $name_fieldset->getField('connection_name_hidden', 'hidden')->setValue($connection_name);
        $name_fieldset->getField('adapter_type_hidden', 'hidden')->setValue($this->connection_data['type']);
        
        // Debug log to verify hidden fields
        $this->utilities_service->debug_log('EditConnection: Adding hidden fields to name fieldset - action_type=edit, connection_name=' . $connection_name . ', adapter_type=' . $this->connection_data['type']);
        
        // Set form configuration
        $form->setBaseUrl(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/update_connection'));
        $form->set('save_btn_text', 'Update Connection');
        $form->set('save_btn_text_working', 'Updating...');
        
        // Convert CP/Form to array
        $form_data = $form->toArray();
        $form_data['cp_page_title'] = $this->cp_page_title;
        $form_data['base_url'] = ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/update_connection');
        
        // Use ee:_shared/form like jcogs_img does, but with CP/Form data
        return $this->setBody('ee:_shared/form', $form_data);
    }

    /**
     * Build the standard EE7 CP Form for editing connections
     * 
     * @return \ExpressionEngine\Library\CP\Form
     */
    private function _buildStandardForm()
    {
        // Create form instance
        $form = ee('CP/Form');
        
        // Connection Information Group
        $main_group = $form->getGroup('Named Cache Directory Connection Configuration');
        
        // Connection Name (read-only for editing)
        $name_fieldset = $main_group->getFieldSet('jcogs_img_cp_choose_flysystem_adapter_name');
        $name_fieldset->setDesc('Connection name cannot be changed when editing an existing connection.');
        $name_field = $name_fieldset->getField('connection_name', 'text');
        $name_field->set('required', true);
        $name_field->set('disabled', true); // Make read-only
        $name_field->setValue($this->connection_name);
        
        // Adapter Type (read-only for editing)
        $type_fieldset = $main_group->getFieldSet('jcogs_img_cp_choose_flysystem_adapter_type');
        $type_fieldset->setDesc('Adapter type cannot be changed when editing an existing connection.');
        $type_field = $type_fieldset->getField('adapter_type', 'select');
        $type_field->set('required', true);
        $type_field->set('disabled', true); // Make read-only
        $adapter_type = $this->connection_data['type'] ?? 'local';
        $type_field->setValue($adapter_type);
        $type_field->set('choices', [
            'local' => 'Local Filesystem',
            's3' => 'Amazon S3', 
            'r2' => 'Cloudflare R2',
            'dospaces' => 'DigitalOcean Spaces'
        ]);
        
        // Since adapter type is disabled, we need to manually show the correct group
        // We'll use a hidden field to trigger the group toggle via JavaScript
        $hidden_adapter_type_field = $main_group->getFieldSet('')->getField('active_adapter_type', 'hidden');
        $hidden_adapter_type_field->setValue($adapter_type);
        
        $connection_config = $this->connection_data['config'] ?? [];
        
        // Local Filesystem Configuration Fields
        if ($adapter_type === 'local') {
            $this->_buildLocalAdapterFields($main_group, $connection_config);
        }
        
        // Amazon S3 Configuration Fields
        if ($adapter_type === 's3') {
            $this->_buildS3AdapterFields($main_group, $connection_config);
        }
        
        // Cloudflare R2 Configuration Fields
        if ($adapter_type === 'r2') {
            $this->_buildR2AdapterFields($main_group, $connection_config);
        }
        
        // DigitalOcean Spaces Configuration Fields
        if ($adapter_type === 'dospaces') {
            $this->_buildDigitalOceanAdapterFields($main_group, $connection_config);
        }

        return $form;
    }
    
    /**
     * Build Local Filesystem adapter fields
     */
    private function _buildLocalAdapterFields($main_group, array $config)
    {
        // Cache_dir Field - stored as 'cache_directory' in connection config
        $cache_dir_fieldset = $main_group->getFieldSet('jcogs_img_cp_default_cache_directory');
        $cache_dir_fieldset->set('group', 'jcogs_img_cp_flysystem_local_adapter');
        $cache_dir_fieldset->setDesc('Relative path from webroot to the directory where cache files will be stored. Must be writable by the web server.');
        $cache_dir_field = $cache_dir_fieldset->getField('local_cache_directory', 'text');
        $cache_dir_field->setPlaceholder(lang('jcogs_img_cp_default_cache_directory_placeholder'));

        // Get cache_directory from connection config, strip leading slash if present
        $cache_dir_value = $config['cache_directory'] ?? '';
        $cache_dir_value = ltrim($cache_dir_value, '/');
        $cache_dir_field->setValue($cache_dir_value);
        $cache_dir_field->set('required', true);

        // Always output full URLs
        $full_urls_fieldset = $main_group->getFieldSet('jcogs_img_cp_class_always_output_full_urls');
        $full_urls_fieldset->set('group', 'jcogs_img_cp_flysystem_local_adapter');
        $full_urls_fieldset->setDesc('Whether to always output full URLs for cached images');
        $full_urls_field = $full_urls_fieldset->getField('img_cp_class_always_output_full_urls', 'yes_no');
        $full_urls_field->setValue($config['always_output_full_urls'] ?? 'n');
        $full_urls_field->set('required', false);

        // CDN Path Prefix
        $cdn_path_fieldset = $main_group->getFieldSet('jcogs_img_cp_cdn_path_prefix');
        $cdn_path_fieldset->set('group', 'jcogs_img_cp_flysystem_local_adapter');
        $cdn_path_fieldset->setDesc('Optional CDN prefix to prepend to image URLs when serving from this connection.');
        $cdn_path_field = $cdn_path_fieldset->getField('local_cdn_path_prefix', 'text');
        $cdn_path_field->setPlaceholder('https://cdn.example.com/cache');
        $cdn_path_field->setValue($config['path_prefix'] ?? '');
        $cdn_path_field->set('required', false);
    }
    
    /**
     * Build Amazon S3 adapter fields
     */
    private function _buildS3AdapterFields($main_group, array $config)
    {
        // S3 Access Key
        $s3_key_fieldset = $main_group->getFieldSet('S3 Access Key ID');
        $s3_key_fieldset->set('group', 'jcogs_img_cp_flysystem_s3_adapter');
        $s3_key_fieldset->setDesc('Enter your AWS S3 Access Key ID');
        $s3_key_field = $s3_key_fieldset->getField('img_cp_flysystem_adapter_s3_key', 'text');
        $s3_key_field->setValue($config['key'] ?? '');
        $s3_key_field->set('required', true);
        
        // S3 Secret Key
        $s3_secret_fieldset = $main_group->getFieldSet('S3 Secret');
        $s3_secret_fieldset->set('group', 'jcogs_img_cp_flysystem_s3_adapter');
        $s3_secret_fieldset->setDesc('Enter your AWS S3 Secret');
        $s3_secret_field = $s3_secret_fieldset->getField('img_cp_flysystem_adapter_s3_secret', 'password');
        $s3_secret_field->setValue($config['secret'] ?? '');
        $s3_secret_field->set('required', true);
        
        // S3 Region
        $s3_region_fieldset = $main_group->getFieldSet('S3 Region');
        $s3_region_fieldset->set('group', 'jcogs_img_cp_flysystem_s3_adapter');
        $s3_region_fieldset->setDesc('Select the region for your AWS region (e.g., us-east-1, eu-west-1)');
        $s3_region_field = $s3_region_fieldset->getField('img_cp_flysystem_adapter_s3_region', 'dropdown');
        $s3_region_field->setValue($config['region'] ?? '');
        $s3_region_field->set('choices', $this->_listAvailableS3Regions());
        $s3_region_field->set('required', false);
        
        // S3 Bucket Name
        $s3_bucket_fieldset = $main_group->getFieldSet('S3 Bucket Name');
        $s3_bucket_fieldset->set('group', 'jcogs_img_cp_flysystem_s3_adapter');
        $s3_bucket_fieldset->setDesc('Name of the AWS S3 bucket for cache storage');
        $s3_bucket_field = $s3_bucket_fieldset->getField('img_cp_flysystem_adapter_s3_bucket', 'text');
        $s3_bucket_field->setValue($config['bucket'] ?? '');
        $s3_bucket_field->set('required', true);
        
        // S3 Cache Directory Path
        $s3_path_fieldset = $main_group->getFieldSet('S3 Cache Directory Path');
        $s3_path_fieldset->set('group', 'jcogs_img_cp_flysystem_s3_adapter');
        $s3_path_fieldset->setDesc('Path within the AWS S3 bucket for cache storage (e.g., "cache/images")');
        $s3_path_field = $s3_path_fieldset->getField('img_cp_flysystem_adapter_s3_server_path', 'text');
        // Strip leading slash from server_path
        $s3_path_value = $config['server_path'] ?? '';
        $s3_path_value = ltrim($s3_path_value, '/');
        $s3_path_field->setValue($s3_path_value);
        $s3_path_field->set('required', false);
        $s3_path_field->setPlaceholder('cache/images');

        // S3 URL
        $s3_url_fieldset = $main_group->getFieldSet('S3 URL');
        $s3_url_fieldset->set('group', 'jcogs_img_cp_flysystem_s3_adapter');
        $s3_url_fieldset->setDesc('Base URL to access your S3 bucket (e.g., "https://mybucket.s3.amazonaws.com")');
        $s3_url_field = $s3_url_fieldset->getField('img_cp_flysystem_adapter_s3_url', 'text');
        $s3_url_field->setValue($config['url'] ?? '');
        $s3_url_field->set('required', false);
        $s3_url_field->setPlaceholder('https://mybucket.s3.amazonaws.com');

        // Cloudflare R2 Configuration Fields
    }
    
    /**
     * Build Cloudflare R2 adapter fields
     */
    private function _buildR2AdapterFields($main_group, array $config)
    {
        // R2 Account ID
        $r2_account_fieldset = $main_group->getFieldSet('R2 Account ID');
        $r2_account_fieldset->set('group', 'jcogs_img_cp_flysystem_r2_adapter');
        $r2_account_fieldset->setDesc('Enter your Cloudflare R2 Account ID');
        $r2_account_field = $r2_account_fieldset->getField('img_cp_flysystem_adapter_r2_account_id', 'text');
        $r2_account_field->setValue($config['account_id'] ?? '');
        $r2_account_field->set('required', true);

        // R2 Key
        $r2_key_fieldset = $main_group->getFieldSet('R2 Key');
        $r2_key_fieldset->set('group', 'jcogs_img_cp_flysystem_r2_adapter');
        $r2_key_fieldset->setDesc('Enter your Cloudflare R2 Key');
        $r2_key_field = $r2_key_fieldset->getField('img_cp_flysystem_adapter_r2_key', 'text');
        $r2_key_field->setValue($config['key'] ?? '');
        $r2_key_field->set('required', true);

        // R2 Secret
        $r2_secret_fieldset = $main_group->getFieldSet('R2 Secret');
        $r2_secret_fieldset->set('group', 'jcogs_img_cp_flysystem_r2_adapter');
        $r2_secret_fieldset->setDesc('Enter your Cloudflare R2 Secret');
        $r2_secret_field = $r2_secret_fieldset->getField('img_cp_flysystem_adapter_r2_secret', 'password');
        $r2_secret_field->setValue($config['secret'] ?? '');
        $r2_secret_field->set('required', true);

        // R2 Bucket Name
        $r2_bucket_fieldset = $main_group->getFieldSet('R2 Bucket Name');
        $r2_bucket_fieldset->set('group', 'jcogs_img_cp_flysystem_r2_adapter');
        $r2_bucket_fieldset->setDesc('Enter the name of your Cloudflare R2 Bucket');
        $r2_bucket_field = $r2_bucket_fieldset->getField('img_cp_flysystem_adapter_r2_bucket', 'text');
        $r2_bucket_field->setValue($config['bucket'] ?? '');
        $r2_bucket_field->set('required', true);

        // R2 Cache Directory Path
        $r2_path_fieldset = $main_group->getFieldSet('R2 Cache Directory Path');
        $r2_path_fieldset->set('group', 'jcogs_img_cp_flysystem_r2_adapter');
        $r2_path_fieldset->setDesc('Path within the R2 bucket for cache storage (e.g., "cache/images")');
        $r2_path_field = $r2_path_fieldset->getField('img_cp_flysystem_adapter_r2_server_path', 'text');
        // Strip leading slash from server_path
        $r2_path_value = $config['server_path'] ?? '';
        $r2_path_value = ltrim($r2_path_value, '/');
        $r2_path_field->setValue($r2_path_value);
        $r2_path_field->set('required', false);
        $r2_path_field->setPlaceholder('cache/images');

        // R2 URL
        $r2_url_fieldset = $main_group->getFieldSet('R2 Url');
        $r2_url_fieldset->set('group', 'jcogs_img_cp_flysystem_r2_adapter');
        $r2_url_fieldset->setDesc('Enter the url used to access your Cloudflare R2 Bucket');
        $r2_url_field = $r2_url_fieldset->getField('img_cp_flysystem_adapter_r2_url', 'text');
        $r2_url_field->setValue($config['url'] ?? '');
        $r2_url_field->set('required', false);
    }
    
    /**
     * Build DigitalOcean Spaces adapter fields
     */
    private function _buildDigitalOceanAdapterFields($main_group, array $config)
    {
        // DigitalOcean Key
        $dospaces_key_fieldset = $main_group->getFieldSet('DigitalOcean Key');
        $dospaces_key_fieldset->set('group', 'jcogs_img_cp_flysystem_dospaces_adapter');
        $dospaces_key_fieldset->setDesc('Enter your DigitalOcean Key');
        $dospaces_key_field = $dospaces_key_fieldset->getField('img_cp_flysystem_adapter_dospaces_key', 'text');
        $dospaces_key_field->setValue($config['key'] ?? '');
        $dospaces_key_field->set('required', true);

        // DigitalOcean Secret
        $dospaces_secret_fieldset = $main_group->getFieldSet('DigitalOcean Secret');
        $dospaces_secret_fieldset->set('group', 'jcogs_img_cp_flysystem_dospaces_adapter');
        $dospaces_secret_fieldset->setDesc('Enter your DigitalOcean Secret');
        $dospaces_secret_field = $dospaces_secret_fieldset->getField('img_cp_flysystem_adapter_dospaces_secret', 'password');
        $dospaces_secret_field->setValue($config['secret'] ?? '');
        $dospaces_secret_field->set('required', true);

        // DigitalOcean Region
        $dospaces_region_fieldset = $main_group->getFieldSet('DigitalOcean Region');
        $dospaces_region_fieldset->set('group', 'jcogs_img_cp_flysystem_dospaces_adapter');
        $dospaces_region_fieldset->setDesc('Select the region for your DigitalOcean Space');
        $dospaces_region_field = $dospaces_region_fieldset->getField('img_cp_flysystem_adapter_dospaces_region', 'dropdown');
        $dospaces_region_field->setValue($config['region'] ?? '');
        $dospaces_region_field->set('choices', $this->_listAvailableDOSpacesRegions());
        $dospaces_region_field->set('required', true);

        // DigitalOcean Space Name
        $dospaces_space_fieldset = $main_group->getFieldSet('DigitalOcean Space Name');
        $dospaces_space_fieldset->set('group', 'jcogs_img_cp_flysystem_dospaces_adapter');
        $dospaces_space_fieldset->setDesc('Enter the name of your DigitalOcean Space');
        $dospaces_space_field = $dospaces_space_fieldset->getField('img_cp_flysystem_adapter_dospaces_space', 'text');
        $dospaces_space_field->setValue($config['space'] ?? '');
        $dospaces_space_field->set('required', true);

        // DigitalOcean Cache Directory Path
        $dospaces_path_fieldset = $main_group->getFieldSet('Spaces Cache Directory Path');
        $dospaces_path_fieldset->set('group', 'jcogs_img_cp_flysystem_dospaces_adapter');
        $dospaces_path_fieldset->setDesc('Path within the DigitalOcean Space for cache storage (e.g., "cache/images")');
        $dospaces_path_field = $dospaces_path_fieldset->getField('img_cp_flysystem_adapter_dospaces_server_path', 'text');
        // Strip leading slash from server_path
        $dospaces_path_value = $config['server_path'] ?? '';
        $dospaces_path_value = ltrim($dospaces_path_value, '/');
        $dospaces_path_field->setValue($dospaces_path_value);
        $dospaces_path_field->set('required', false);
        $dospaces_path_field->setPlaceholder('cache/images');

        // DigitalOcean URL
        $dospaces_url_fieldset = $main_group->getFieldSet('DigitalOcean Url');
        $dospaces_url_fieldset->set('group', 'jcogs_img_cp_flysystem_dospaces_adapter');
        $dospaces_url_fieldset->setDesc('Enter the url used to access your DigitalOcean Space');
        $dospaces_url_field = $dospaces_url_fieldset->getField('img_cp_flysystem_adapter_dospaces_url', 'text');
        $dospaces_url_field->setValue($config['url'] ?? '');
        $dospaces_url_field->set('required', false);
    }

    private function _listAvailableS3Regions()
    {
        return [
            'us-east-2' => 'US East (Ohio)',
            'us-east-1' => 'US East (N. Virginia)',
            'us-west-1' => 'US West (N. California)',
            'us-west-2' => 'US West (Oregon)',
            'af-south-1' => 'Africa (Cape Town)',
            'ap-east-1' => 'Asia Pacific (Hong Kong)',
            'ap-southeast-3' => 'Asia Pacific (Jakarta)',
            'ap-southeast-4' => 'Asia Pacific (Melbourne)',
            'ap-south-1' => 'Asia Pacific (Mumbai)',
            'ap-south-2' => 'Asia Pacific (Hyderabad)',
            'ap-northeast-3' => 'Asia Pacific (Osaka)',
            'ap-northeast-2' => 'Asia Pacific (Seoul)',
            'ap-southeast-1' => 'Asia Pacific (Singapore)',
            'ap-southeast-2' => 'Asia Pacific (Sydney)',
            'ap-northeast-1' => 'Asia Pacific (Tokyo)',
            'ca-central-1' => 'Canada (Central)',
            'cn-north-1' => 'China (Beijing)',
            'cn-northwest-1' => 'China (Ningxia)',
            'eu-central-1' => 'Europe (Frankfurt)',
            'eu-west-1' => 'Europe (Ireland)',
            'eu-west-2' => 'Europe (London)',
            'eu-south-1' => 'Europe (Milan)',
            'eu-west-3' => 'Europe (Paris)',
            'eu-north-1' => 'Europe (Stockholm)',
            'eu-south-2' => 'Europe (Spain)',
            'eu-central-2' => 'Europe (Zurich)',
            'me-south-1' => 'Middle East (Bahrain)',
            'me-central-1' => 'Middle East (UAE)',
            'sa-east-1' => 'South America (São Paulo)',
        ];
    }

    private function _listAvailableDOSpacesRegions()
    {
        return [
            'nyc1' => 'NYC1 - New York City',
            'nyc2' => 'NYC2 - New York City',
            'nyc3' => 'NYC3 - New York City',
            'ams2' => 'AMS2 - Amsterdam',
            'ams3' => 'AMS3 - Amsterdam',
            'sfo1' => 'SFO1 - San Francisco',
            'sfo2' => 'SFO2 - San Francisco',
            'sfo3' => 'SFO3 - San Francisco',
            'sgp1' => 'SGP1 - Singapore',
            'lon1' => 'LON1 - London',
            'fra1' => 'FRA1 - Frankfurt',
            'tor1' => 'TOR1 - Toronto',
            'blr1' => 'BLR1 - Bangalore',
            'syd1' => 'SYD1 - Sydney',
        ];
    }

    /**
     * Load CSS and JavaScript assets for edit connection interface
     * 
     * @return void
     */
    private function _load_edit_connection_assets()
    {
        // Load CSS
        ee()->cp->add_to_head('<link rel="stylesheet" type="text/css" href="' . URL_THIRD_THEMES . 'user/jcogs_img_pro/css/edit-connection.css" />');
        
        // Load JavaScript
        ee()->cp->add_to_foot('<script defer src="' . URL_THIRD_THEMES . 'user/jcogs_img_pro/javascript/edit-connection.js"></script>');
    }
}
