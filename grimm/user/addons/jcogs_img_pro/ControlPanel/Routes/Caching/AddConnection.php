<?php

/**
 * JCOGS Image Pro - Add Cache Connection Route
 * ============================================
 * Route for adding new cache connections with proper EE7 CP/Form integration
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

class AddConnection extends ImageAbstractRoute
{
    /**
     * @var string
     */
    protected $route_path = 'caching/add_connection';

    /**
     * @var string
     */
    protected $cp_page_title;

    /**
     * @var array
     */
    protected $cp_breadcrumbs;

    /**
     * @var array|null Connection data for cloning operations
     */
    private $clone_data = null;

    /**
     * Display the add connection form using EE7 CP/Form system
     * 
     * @param mixed $id Not used for add connection
     * @return $this
     */
    public function process($id = false)
    {
        // Load language file
        $this->load_language();
        
        // Check if this is a clone operation
        $clone_connection = ee()->input->get('clone');
        if (!empty($clone_connection)) {
            $this->clone_data = $this->_loadConnectionDataForCloning($clone_connection);
            if ($this->clone_data) {
                $this->cp_page_title = 'Clone Connection: ' . htmlspecialchars($clone_connection);
            } else {
                $this->cp_page_title = 'Add Cache Connection';
                ee('CP/Alert')->makeInline('clone-error')
                    ->asIssue()
                    ->withTitle('Clone Error')
                    ->addToBody('Unable to load connection data for cloning.')
                    ->defer();
            }
        } else {
            $this->cp_page_title = 'Add Cache Connection';
        }
        
        // Set breadcrumbs
        $this->addBreadcrumb('index', 'JCOGS Image Pro')
            ->addBreadcrumb('caching', 'Cache Management')
            ->addBreadcrumb('', $this->clone_data ? 'Clone Connection' : 'Add Connection');
        
        
        // Build sidebar
        $this->build_sidebar($this->_get_current_settings());
        
        // Build the form using EE7 CP/Form system
        $form = $this->_buildStandardForm($this->clone_data);
        
        // Add hidden field to the connection name fieldset (to avoid creating extra form sections)
        $main_group = $form->getGroup('Named Cache Directory Connection Configuration');
        $name_fieldset = $main_group->getFieldSet('jcogs_img_cp_choose_flysystem_adapter_name');
        
        // Add action type hidden field to identify this as an add operation
        $name_fieldset->getField('action_type', 'hidden')->setValue('add');
        
        // Debug log to verify hidden field
        $this->utilities_service->debug_log('AddConnection: Adding hidden field to name fieldset - action_type=add');
        
        // Set form configuration
        $form->setBaseUrl(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/update_connection'));
        $form->set('save_btn_text', 'Create Connection');
        $form->set('save_btn_text_working', 'Creating...');
        
        // Convert CP/Form to array
        $form_data = $form->toArray();
        $form_data['cp_page_title'] = $this->cp_page_title;
        $form_data['base_url'] = ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/update_connection');
                        
        // Use ee:_shared/form like jcogs_img does, but with CP/Form data
        return $this->setBody('ee:_shared/form', $form_data);
    }

    /**
     * Build the standard EE7 CP Form for adding connections
     * 
     * @param array|null $clone_data Optional data from existing connection for cloning
     * @return \ExpressionEngine\Library\CP\Form
     */
    private function _buildStandardForm($clone_data = null)
    {
        // Create form instance
        $form = ee('CP/Form');
        
        // Connection Information Group
        $main_group = $form->getGroup('Named Cache Directory Connection Configuration');
        
        // Connection Name
        $name_fieldset = $main_group->getFieldSet('jcogs_img_cp_choose_flysystem_adapter_name');
        $name_fieldset->setDesc('Enter a unique name for this cache connection. Only letters, numbers, hyphens (-), and underscores (_) are allowed. No spaces or special characters.');
        $name_field = $name_fieldset->getField('connection_name', 'text');
        $name_field->set('required', true);
        $name_field->set('attrs', ' pattern="[a-zA-Z0-9_-]+" title="Only letters, numbers, hyphens (-), and underscores (_) are allowed"');
        $name_field->setPlaceholder('my_cache_connection');
        
        // Which Type of connector to configure?
        $type_fieldset = $main_group->getFieldSet('jcogs_img_cp_choose_flysystem_adapter_type');
        $type_fieldset->setDesc('jcogs_img_cp_choose_flysystem_adapter_type_desc');
        $type_field = $type_fieldset->getField('adapter_type', 'select');
        $type_field->set('required', true);
        $type_field->setValue('local');
        $type_field->set('choices', [
            'local' => 'Local Filesystem',
            's3' => 'Amazon S3', 
            'r2' => 'Cloudflare R2',
            'dospaces' => 'DigitalOcean Spaces'
        ]);
        $type_field->set('group_toggle', [
            'local' => 'jcogs_img_cp_flysystem_local_adapter',
            's3' => 'jcogs_img_cp_flysystem_s3_adapter',
            'r2' => 'jcogs_img_cp_flysystem_r2_adapter',
            'dospaces' => 'jcogs_img_cp_flysystem_dospaces_adapter'
        ]);
        
        // Local Filesystem Configuration Fields
        // Cache_dir Field
        $cache_dir_fieldset = $main_group->getFieldSet('jcogs_img_cp_default_cache_directory');
        $cache_dir_fieldset->set('group', 'jcogs_img_cp_flysystem_local_adapter'); // Set group toggle
        $cache_dir_fieldset->setDesc('Relative path from webroot to the directory where cache files will be stored. Must be writable by the web server. (e.g., "cache/images" not "/var/www/cache/images")');
        $cache_dir_field = $cache_dir_fieldset->getField('local_cache_directory', 'text');
        $cache_dir_field->setPlaceholder(lang('jcogs_img_cp_default_cache_directory_placeholder'));
        $cache_dir_field->set('required', true);

        // Always output full URLs
        $full_urls_fieldset = $main_group->getFieldSet('jcogs_img_cp_class_always_output_full_urls');
        $full_urls_fieldset->set('group', 'jcogs_img_cp_flysystem_local_adapter'); // Set group toggle
        $full_urls_fieldset->setDesc('Whether to always output full URLs for cached images');
        $full_urls_field = $full_urls_fieldset->getField('img_cp_class_always_output_full_urls', 'yes_no');
        $full_urls_field->setPlaceholder(lang('jcogs_img_cp_class_always_output_full_urls_placeholder'));
        $full_urls_field->set('required', false);

        // Set CDN remote path prefix
        $cdn_path_fieldset = $main_group->getFieldSet('jcogs_img_cp_cdn_path_prefix');
        $cdn_path_fieldset->set('group', 'jcogs_img_cp_flysystem_local_adapter'); // Set group toggle
        $cdn_path_fieldset->setDesc('Optional CDN path prefix for serving cached images from a CDN. Leave empty if not using a CDN.');
        $cdn_path_field = $cdn_path_fieldset->getField('local_cdn_path_prefix', 'text');
        $cdn_path_field->setPlaceholder('https://cdn.example.com/cache');
        $cdn_path_field->set('required', false);

        // Amazon S3 Configuration Fields
        // Amazon S3 - Access Key
        $s3_key_fieldset = $main_group->getFieldSet('S3 Access Key ID');
        $s3_key_fieldset->set('group', 'jcogs_img_cp_flysystem_s3_adapter'); // Set group toggle
        $s3_key_fieldset->setDesc('Enter your AWS S3 Access Key ID');
        $s3_key_field = $s3_key_fieldset->getField('img_cp_flysystem_adapter_s3_key', 'text');
        $s3_key_field->set('required', true);
        
        // Amazon S3 - Secret Key
        $s3_secret_fieldset = $main_group->getFieldSet('S3 Secret');
        $s3_secret_fieldset->set('group', 'jcogs_img_cp_flysystem_s3_adapter'); // Set group toggle
        $s3_secret_fieldset->setDesc('Enter your AWS S3 Secret');
        $s3_secret_field = $s3_secret_fieldset->getField('img_cp_flysystem_adapter_s3_secret', 'password');
        $s3_secret_field->set('required', true);
        
        // Amazon S3 - Region
        $s3_region_fieldset = $main_group->getFieldSet('S3 Region');
        $s3_region_fieldset->set('group', 'jcogs_img_cp_flysystem_s3_adapter'); // Set group toggle
        $s3_region_fieldset->setDesc('Select the region for your AWS region (e.g., us-east-1, eu-west-1)');
        $s3_region_field = $s3_region_fieldset->getField('img_cp_flysystem_adapter_s3_region', 'dropdown');
        $s3_region_field->set('choices', $this->_listAvailableS3Regions());
        $s3_region_field->set('required', false);
        
        // Amazon S3 - Bucket Name
        $s3_bucket_fieldset = $main_group->getFieldSet('S3 Bucket Name');
        $s3_bucket_fieldset->set('group', 'jcogs_img_cp_flysystem_s3_adapter'); // Set group toggle
        $s3_bucket_fieldset->setDesc('Name of the AWS S3 bucket for cache storage');
        $s3_bucket_field = $s3_bucket_fieldset->getField('img_cp_flysystem_adapter_s3_bucket', 'text');
        $s3_bucket_field->set('required', true);
        
        // Amazon S3 - Cache Directory Path
        $s3_path_fieldset = $main_group->getFieldSet('S3 Cache Directory Path');
        $s3_path_fieldset->set('group', 'jcogs_img_cp_flysystem_s3_adapter'); // Set group toggle
        $s3_path_fieldset->setDesc('Path within the AWS S3 bucket for cache storage (e.g., "cache/images")');
        $s3_path_field = $s3_path_fieldset->getField('img_cp_flysystem_adapter_s3_server_path', 'text');
        $s3_path_field->set('required', false);
        $s3_path_field->setPlaceholder('cache/images');

        // Amazon S3 - URL
        $s3_url_fieldset = $main_group->getFieldSet('S3 URL');
        $s3_url_fieldset->set('group', 'jcogs_img_cp_flysystem_s3_adapter'); // Set group toggle
        $s3_url_fieldset->setDesc('Base URL to access your S3 bucket (e.g., "https://mybucket.s3.amazonaws.com")');
        $s3_url_field = $s3_url_fieldset->getField('img_cp_flysystem_adapter_s3_url', 'text');
        $s3_url_field->set('required', false);
        $s3_url_field->setPlaceholder('https://mybucket.s3.amazonaws.com');

        // Cloudflare R2 Configuration Fields
        // Cloudflare R2 - Account ID
        $r2_account_fieldset = $main_group->getFieldSet('R2 Account ID');
        $r2_account_fieldset->set('group', 'jcogs_img_cp_flysystem_r2_adapter'); // Set group toggle
        $r2_account_fieldset->setDesc('Enter your Cloudflare R2 Account ID');
        $r2_account_field = $r2_account_fieldset->getField('img_cp_flysystem_adapter_r2_account_id', 'text');
        $r2_account_field->set('required', true);

        // Cloudflare R2 - Key
        $r2_key_fieldset = $main_group->getFieldSet('R2 Key');
        $r2_key_fieldset->set('group', 'jcogs_img_cp_flysystem_r2_adapter'); // Set group toggle
        $r2_key_fieldset->setDesc('Enter your Cloudflare R2 Key');
        $r2_key_field = $r2_key_fieldset->getField('img_cp_flysystem_adapter_r2_key', 'text');
        $r2_key_field->set('required', true);

        // Cloudflare R2 - Secret
        $r2_secret_fieldset = $main_group->getFieldSet('R2 Secret');
        $r2_secret_fieldset->set('group', 'jcogs_img_cp_flysystem_r2_adapter'); // Set group toggle
        $r2_secret_fieldset->setDesc('Enter your Cloudflare R2 Secret');
        $r2_secret_field = $r2_secret_fieldset->getField('img_cp_flysystem_adapter_r2_secret', 'password');
        $r2_secret_field->set('required', true);

        // Cloudflare R2 - Bucket Name
        $r2_bucket_fieldset = $main_group->getFieldSet('R2 Bucket Name');
        $r2_bucket_fieldset->set('group', 'jcogs_img_cp_flysystem_r2_adapter'); // Set group toggle
        $r2_bucket_fieldset->setDesc('Enter the name of your Cloudflare R2 Bucket');
        $r2_bucket_field = $r2_bucket_fieldset->getField('img_cp_flysystem_adapter_r2_bucket', 'text');
        $r2_bucket_field->set('required', true);

        // Cloudflare R2 - Cache Directory Path
        $r2_path_fieldset = $main_group->getFieldSet('Cache Directory Path');
        $r2_path_fieldset->set('group', 'jcogs_img_cp_flysystem_r2_adapter'); // Set group toggle
        $r2_path_fieldset->setDesc('Path within the R2 bucket for cache storage (e.g., "cache/images")');
        $r2_path_field = $r2_path_fieldset->getField('img_cp_flysystem_adapter_r2_server_path', 'text');
        $r2_path_field->set('required', false);
        $r2_path_field->setPlaceholder('cache/images');

        // Cloudflare R2 - URL
        $r2_url_fieldset = $main_group->getFieldSet('R2 Url');
        $r2_url_fieldset->set('group', 'jcogs_img_cp_flysystem_r2_adapter'); // Set group toggle
        $r2_url_fieldset->setDesc('Enter the url used to access your Cloudflare R2 Bucket');
        $r2_url_field = $r2_url_fieldset->getField('img_cp_flysystem_adapter_r2_url', 'text');
        $r2_url_field->set('required', false);

        // DigitalOcean Spaces Configuration Fields
        // DigitalOcean Spaces - Key
        $dospaces_key_fieldset = $main_group->getFieldSet('DigitalOcean Key');
        $dospaces_key_fieldset->set('group', 'jcogs_img_cp_flysystem_dospaces_adapter'); // Set group toggle
        $dospaces_key_fieldset->setDesc('Enter your DigitalOcean Key');
        $dospaces_key_field = $dospaces_key_fieldset->getField('img_cp_flysystem_adapter_dospaces_key', 'text');
        $dospaces_key_field->set('required', true);

        // DigitalOcean Spaces - Secret
        $dospaces_secret_fieldset = $main_group->getFieldSet('DigitalOcean Secret');
        $dospaces_secret_fieldset->set('group', 'jcogs_img_cp_flysystem_dospaces_adapter'); // Set group toggle
        $dospaces_secret_fieldset->setDesc('Enter your DigitalOcean Secret');
        $dospaces_secret_field = $dospaces_secret_fieldset->getField('img_cp_flysystem_adapter_dospaces_secret', 'password');
        $dospaces_secret_field->set('required', true);

        // DigitalOcean Spaces - Region
        $dospaces_region_fieldset = $main_group->getFieldSet('DigitalOcean Region');
        $dospaces_region_fieldset->set('group', 'jcogs_img_cp_flysystem_dospaces_adapter'); // Set group toggle
        $dospaces_region_fieldset->setDesc('Select the region for your DigitalOcean Space');
        $dospaces_region_field = $dospaces_region_fieldset->getField('img_cp_flysystem_adapter_dospaces_region', 'dropdown');
        $dospaces_region_field->set('choices', $this->_listAvailableDOSpacesRegions());
        $dospaces_region_field->set('required', true);

        // DigitalOcean Spaces - Space Name
        $dospaces_space_fieldset = $main_group->getFieldSet('DigitalOcean Space Name');
        $dospaces_space_fieldset->set('group', 'jcogs_img_cp_flysystem_dospaces_adapter'); // Set group toggle
        $dospaces_space_fieldset->setDesc('Enter the name of your DigitalOcean Space');
        $dospaces_space_field = $dospaces_space_fieldset->getField('img_cp_flysystem_adapter_dospaces_space', 'text');
        $dospaces_space_field->set('required', true);

        // DigitalOcean Spaces - Cache Directory Path
        $dospaces_path_fieldset = $main_group->getFieldSet('Cache Directory Path');
        $dospaces_path_fieldset->set('group', 'jcogs_img_cp_flysystem_dospaces_adapter'); // Set group toggle
        $dospaces_path_fieldset->setDesc('Path within the DigitalOcean Space for cache storage (e.g., "cache/images")');
        $dospaces_path_field = $dospaces_path_fieldset->getField('img_cp_flysystem_adapter_dospaces_server_path', 'text');
        $dospaces_path_field->set('required', false);
        $dospaces_path_field->setPlaceholder('cache/images');

        // DigitalOcean Spaces - URL
        $dospaces_url_fieldset = $main_group->getFieldSet('DigitalOcean Url');
        $dospaces_url_fieldset->set('group', 'jcogs_img_cp_flysystem_dospaces_adapter'); // Set group toggle
        $dospaces_url_fieldset->setDesc('Enter the url used to access your DigitalOcean Space');
        $dospaces_url_field = $dospaces_url_fieldset->getField('img_cp_flysystem_adapter_dospaces_url', 'text');
        $dospaces_url_field->set('required', false);

        // If clone data is available, populate form fields with cloned values
        if (!empty($clone_data)) {
            $this->_populateFormWithCloneData($form, $clone_data);
        }

        return $form;
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
     * Load connection data for cloning operations
     * 
     * @param string $connection_name Name of connection to clone
     * @return array|null Connection data or null if not found
     */
    private function _loadConnectionDataForCloning(string $connection_name): ?array
    {
        $connection = $this->settings_service->getNamedConnection($connection_name, true); // Get with decrypted values
        if ($connection) {
            // Clear the connection name so user can set a new one
            $connection['name'] = '';
            // Add suffix to suggest it's a clone
            if (!empty($connection['config'])) {
                // Add some indication this is a clone in the UI
                $this->utilities_service->debug_log('AddConnection: Loaded clone data for connection: ' . $connection_name);
            }
        }
        return $connection;
    }

    /**
     * Populate form fields with data from cloned connection
     * 
     * @param \ExpressionEngine\Library\CP\Form $form The form instance
     * @param array $clone_data The connection data to clone
     */
    private function _populateFormWithCloneData($form, $clone_data)
    {
        $type = $clone_data['type'] ?? '';
        $config = $clone_data['config'] ?? [];

        // Set adapter type (this will trigger the appropriate field group to show)
        $main_group = $form->getGroup('Named Cache Directory Connection Configuration');
        $type_fieldset = $main_group->getFieldSet('jcogs_img_cp_choose_flysystem_adapter_type');
        $type_field = $type_fieldset->getField('adapter_type');
        if ($type_field && $type) {
            $type_field->setValue($type);
        }

        // Connection name - leave empty so user can set a new name
        // (already handled in loadConnectionDataForCloning by setting name to empty)

        // Populate fields based on adapter type
        switch ($type) {
            case 'local':
                $this->_populateLocalFields($main_group, $config);
                break;
            case 's3':
                $this->_populateS3Fields($main_group, $config);
                break;
            case 'r2':
                $this->_populateR2Fields($main_group, $config);
                break;
            case 'dospaces':
                $this->_populateDoSpacesFields($main_group, $config);
                break;
        }
    }

    /**
     * Populate Local adapter fields from clone data
     */
    private function _populateLocalFields($main_group, $config)
    {
        if (!empty($config['cache_directory'])) {
            $cache_dir_fieldset = $main_group->getFieldSet('jcogs_img_cp_default_cache_directory');
            $cache_dir_field = $cache_dir_fieldset->getField('local_cache_directory');
            if ($cache_dir_field) {
                $cache_dir_field->setValue($config['cache_directory']);
            }
        }

        if (isset($config['always_output_full_urls'])) {
            $full_urls_fieldset = $main_group->getFieldSet('jcogs_img_cp_class_always_output_full_urls');
            $full_urls_field = $full_urls_fieldset->getField('img_cp_class_always_output_full_urls');
            if ($full_urls_field) {
                $full_urls_field->setValue($config['always_output_full_urls']);
            }
        }

        if (!empty($config['path_prefix'])) {
            $cdn_path_fieldset = $main_group->getFieldSet('jcogs_img_cp_cdn_path_prefix');
            $cdn_path_field = $cdn_path_fieldset->getField('local_cdn_path_prefix');
            if ($cdn_path_field) {
                $cdn_path_field->setValue($config['path_prefix']);
            }
        }
    }

    /**
     * Populate S3 adapter fields from clone data
     */
    private function _populateS3Fields($main_group, $config)
    {
        if (!empty($config['key'])) {
            $s3_key_fieldset = $main_group->getFieldSet('S3 Access Key ID');
            $s3_key_field = $s3_key_fieldset->getField('img_cp_flysystem_adapter_s3_key');
            if ($s3_key_field) {
                $s3_key_field->setValue($config['key']);
            }
        }

        // Include secret but display it obscured (like edit screen)
        if (!empty($config['secret'])) {
            $s3_secret_fieldset = $main_group->getFieldSet('S3 Secret');
            $s3_secret_field = $s3_secret_fieldset->getField('img_cp_flysystem_adapter_s3_secret');
            if ($s3_secret_field) {
                $s3_secret_field->setValue($config['secret']);
            }
        }

        if (!empty($config['region'])) {
            $s3_region_fieldset = $main_group->getFieldSet('S3 Region');
            $s3_region_field = $s3_region_fieldset->getField('img_cp_flysystem_adapter_s3_region');
            if ($s3_region_field) {
                $s3_region_field->setValue($config['region']);
            }
        }

        if (!empty($config['bucket'])) {
            $s3_bucket_fieldset = $main_group->getFieldSet('S3 Bucket Name');
            $s3_bucket_field = $s3_bucket_fieldset->getField('img_cp_flysystem_adapter_s3_bucket');
            if ($s3_bucket_field) {
                $s3_bucket_field->setValue($config['bucket']);
            }
        }

        if (!empty($config['server_path'])) {
            $s3_path_fieldset = $main_group->getFieldSet('Cache Directory Path');
            $s3_path_field = $s3_path_fieldset->getField('img_cp_flysystem_adapter_s3_server_path');
            if ($s3_path_field) {
                $s3_path_field->setValue($config['server_path']);
            }
        }

        if (!empty($config['url'])) {
            $s3_url_fieldset = $main_group->getFieldSet('S3 URL');
            $s3_url_field = $s3_url_fieldset->getField('img_cp_flysystem_adapter_s3_url');
            if ($s3_url_field) {
                $s3_url_field->setValue($config['url']);
            }
        }
    }

    /**
     * Populate R2 adapter fields from clone data
     */
    private function _populateR2Fields($main_group, $config)
    {
        if (!empty($config['account_id'])) {
            $r2_account_fieldset = $main_group->getFieldSet('R2 Account ID');
            $r2_account_field = $r2_account_fieldset->getField('img_cp_flysystem_adapter_r2_account_id');
            if ($r2_account_field) {
                $r2_account_field->setValue($config['account_id']);
            }
        }

        if (!empty($config['key'])) {
            $r2_key_fieldset = $main_group->getFieldSet('R2 Access Key ID');
            $r2_key_field = $r2_key_fieldset->getField('img_cp_flysystem_adapter_r2_key');
            if ($r2_key_field) {
                $r2_key_field->setValue($config['key']);
            }
        }

        // Include secret but display it obscured (like edit screen)
        if (!empty($config['secret'])) {
            $r2_secret_fieldset = $main_group->getFieldSet('R2 Secret');
            $r2_secret_field = $r2_secret_fieldset->getField('img_cp_flysystem_adapter_r2_secret');
            if ($r2_secret_field) {
                $r2_secret_field->setValue($config['secret']);
            }
        }

        if (!empty($config['bucket'])) {
            $r2_bucket_fieldset = $main_group->getFieldSet('R2 Bucket Name');
            $r2_bucket_field = $r2_bucket_fieldset->getField('img_cp_flysystem_adapter_r2_bucket');
            if ($r2_bucket_field) {
                $r2_bucket_field->setValue($config['bucket']);
            }
        }

        if (!empty($config['server_path'])) {
            $r2_path_fieldset = $main_group->getFieldSet('Cache Directory Path');
            $r2_path_field = $r2_path_fieldset->getField('img_cp_flysystem_adapter_r2_server_path');
            if ($r2_path_field) {
                $r2_path_field->setValue($config['server_path']);
            }
        }

        if (!empty($config['url'])) {
            $r2_url_fieldset = $main_group->getFieldSet('R2 URL');
            $r2_url_field = $r2_url_fieldset->getField('img_cp_flysystem_adapter_r2_url');
            if ($r2_url_field) {
                $r2_url_field->setValue($config['url']);
            }
        }
    }

    /**
     * Populate DigitalOcean Spaces adapter fields from clone data
     */
    private function _populateDoSpacesFields($main_group, $config)
    {
        if (!empty($config['key'])) {
            $dospaces_key_fieldset = $main_group->getFieldSet('DigitalOcean Spaces Access Key ID');
            $dospaces_key_field = $dospaces_key_fieldset->getField('img_cp_flysystem_adapter_dospaces_key');
            if ($dospaces_key_field) {
                $dospaces_key_field->setValue($config['key']);
            }
        }

        // Include secret but display it obscured (like edit screen)
        if (!empty($config['secret'])) {
            $dospaces_secret_fieldset = $main_group->getFieldSet('DigitalOcean Spaces Secret');
            $dospaces_secret_field = $dospaces_secret_fieldset->getField('img_cp_flysystem_adapter_dospaces_secret');
            if ($dospaces_secret_field) {
                $dospaces_secret_field->setValue($config['secret']);
            }
        }

        if (!empty($config['region'])) {
            $dospaces_region_fieldset = $main_group->getFieldSet('DigitalOcean Spaces Region');
            $dospaces_region_field = $dospaces_region_fieldset->getField('img_cp_flysystem_adapter_dospaces_region');
            if ($dospaces_region_field) {
                $dospaces_region_field->setValue($config['region']);
            }
        }

        if (!empty($config['space'])) {
            $dospaces_space_fieldset = $main_group->getFieldSet('DigitalOcean Spaces Name');
            $dospaces_space_field = $dospaces_space_fieldset->getField('img_cp_flysystem_adapter_dospaces_space');
            if ($dospaces_space_field) {
                $dospaces_space_field->setValue($config['space']);
            }
        }

        if (!empty($config['server_path'])) {
            $dospaces_path_fieldset = $main_group->getFieldSet('Cache Directory Path');
            $dospaces_path_field = $dospaces_path_fieldset->getField('img_cp_flysystem_adapter_dospaces_server_path');
            if ($dospaces_path_field) {
                $dospaces_path_field->setValue($config['server_path']);
            }
        }

        if (!empty($config['url'])) {
            $dospaces_url_fieldset = $main_group->getFieldSet('DigitalOcean Spaces URL');
            $dospaces_url_field = $dospaces_url_fieldset->getField('img_cp_flysystem_adapter_dospaces_url');
            if ($dospaces_url_field) {
                $dospaces_url_field->setValue($config['url']);
            }
        }
    }
}
