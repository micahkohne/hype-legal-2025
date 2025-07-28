<?php

/**
 * Settings Service
 * ================
 * Service to set the settings for the JCOGS Image add-on
 * =====================================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.4.16.2
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.0.0
 */

/**
 * Generic path to multiple libraries code (for Imagine reference)
 * 
 * Also here - https://stackoverflow.com/questions/534159/instantiate-a-class-from-a-variable-in-php
 * 
 * $string = 'Imagine\\'.$library.'\\Imagine'
 * "Imagine\\Imagine"
 * $library = 'Gd'
 * $string = 'Imagine\\'.$library.'\\Imagine'
 * $test = new $string()
 */


namespace JCOGSDesign\Jcogs_img\Service;

class Settings
{
    public static $settings;
    private array $sensitive_keys = array(
        'jcogs_license_key',
        'jcogs_license_key_email'
    );

    public function __construct()
    {
        if (empty(static::$settings)) {
            static::$settings = $this->get_settings();
        }
    }

    /**
     * Returns the default settings for the JCOGS Image add-on.
     *
     * @return array The default settings array.
     *
     * The settings include:
     * - 'jcogs_add_on_class': The class name of the add-on.
     * - 'jcogs_add_on_name': The name of the add-on.
     * - 'jcogs_add_on_version': The version of the add-on.
     * - 'enable_img': Whether the image functionality is enabled ('y' or 'n').
     * - 'img_cp_speedy_escape': Speedy escape setting ('y' or 'n').
     * - 'img_cp_action_links': Action links setting ('y' or 'n').
     * - 'img_cp_append_path_to_action_links': Append path to action links setting ('y' or 'n').
     * - 'jcogs_license_key': The license key.
     * - 'jcogs_license_key_email': The email associated with the license key.
     * - 'jcogs_staging_domain': The staging domain.
     * - 'jcogs_license_mode': The license mode (default 'invalid').
     * - 'jcogs_license_server_domain': The license server domain.
     * - 'img_cp_default_cache_directory': The default cache directory for images.
     * - 'img_cp_path_prefix': The path prefix.
     * - 'img_cp_default_cache_duration': The default cache duration in seconds.
     * - 'img_cp_default_cache_audit_after': The default cache audit duration in seconds.
     * - 'img_cp_enable_debugging': Whether debugging is enabled ('y' or 'n').
     * - 'img_cp_enable_browser_check': Whether browser check is enabled ('y' or 'n').
     * - 'img_cp_default_filename_separator': The default filename separator.
     * - 'img_cp_enable_cache_audit': Whether cache audit is enabled ('y' or 'n').
     * - 'img_cp_cache_auto_manage': Whether cache auto-management is enabled ('y' or 'n').
     * - 'img_cp_default_max_source_filename_length': The default maximum source filename length.
     * - 'img_cp_include_source_in_filename_hash': Whether to include the source in the filename hash ('y' or 'n').
     * - 'img_cp_ce_image_remote_dir': The remote directory for CE images.
     * - 'img_cp_default_max_image_size': The default maximum image size in MB.
     * - 'img_cp_default_min_php_ram': The default minimum PHP RAM in MB.
     * - 'img_cp_default_min_php_process_time': The default minimum PHP process time in seconds.
     * - 'img_cp_default_php_remote_connect_time': The default PHP remote connect time in seconds.
     * - 'img_cp_default_user_agent_string': The default user agent string.
     * - 'img_cp_default_image_format': The default image format.
     * - 'img_cp_jpg_default_quality': The default JPG quality.
     * - 'img_cp_png_default_quality': The default PNG quality.
     * - 'img_cp_default_bg_color': The default background color.
     * - 'img_cp_enable_svg_passthrough': Whether SVG passthrough is enabled ('y' or 'n').
     * - 'img_cp_default_img_width': The default image width.
     * - 'img_cp_default_img_height': The default image height.
     * - 'img_cp_allow_scale_larger_default': Whether scaling larger than default is allowed ('y' or 'n').
     * - 'img_cp_class_consolidation_default': Whether class consolidation is enabled by default ('y' or 'n').
     * - 'img_cp_attribute_variable_expansion_default': Whether attribute variable expansion is enabled by default ('y' or 'n').
     * - 'img_cp_class_always_output_full_urls': Whether to always output full URLs ('y' or 'n').
     * - 'img_cp_enable_auto_sharpen': Whether auto-sharpening is enabled ('y' or 'n').
     * - 'img_cp_enable_lazy_loading': Whether lazy loading is enabled ('y' or 'n').
     * - 'img_cp_lazy_loading_mode': The lazy loading mode.
     * - 'img_cp_lazy_progressive_enhancement': The lazy progressive enhancement setting.
     * - 'img_cp_html_decoding_enabled': Determines whether or not to add a decoding= attribute to the tag output. (see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/img#attr-decoding)
     * - 'img_cp_enable_default_fallback_image': Whether the default fallback image is enabled ('y' or 'n').
     * - 'img_cp_fallback_image_colour': The fallback image color.
     * - 'img_cp_fallback_image_local': The local fallback image path.
     * - 'img_cp_fallback_image_remote': The remote fallback image URL.
     * - 'img_cp_enable_auto_adjust': Whether auto-adjust is enabled ('y' or 'n').
     * - 'img_cp_default_max_image_dimension': The default maximum image dimension.
     * - 'img_cp_ignore_save_type_for_animated_gifs': Whether to ignore save type for animated GIFs ('0' or '1').
     * - 'img_cp_flysystem_adapter': The Flysystem adapter in use.
     * - 'img_cp_flysystem_adapter_config': The Flysystem adapter being configured.
     * - 'img_cp_flysystem_adapter_s3_key': The S3 key for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_s3_secret': The S3 secret for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_s3_secret_actual': The actual S3 secret for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_s3_region': The S3 region for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_s3_bucket': The S3 bucket for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_s3_server_path': The S3 server path for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_s3_url': The S3 URL for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_r2_account_id': The R2 account ID for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_r2_key': The R2 key for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_r2_secret': The R2 secret for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_r2_secret_actual': The actual R2 secret for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_r2_bucket': The R2 bucket for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_r2_server_path': The R2 server path for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_r2_url': The R2 URL for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_dospaces_key': The DigitalOcean Spaces key for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_dospaces_secret': The DigitalOcean Spaces secret for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_dospaces_secret_actual': The actual DigitalOcean Spaces secret for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_dospaces_region': The DigitalOcean Spaces region for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_dospaces_space': The DigitalOcean Spaces space for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_dospaces_server_path': The DigitalOcean Spaces server path for the Flysystem adapter.
     * - 'img_cp_flysystem_adapter_dospaces_url': The DigitalOcean Spaces URL for the Flysystem adapter.
     */
    private function _default_settings(): array
    {
        return array(
            'jcogs_add_on_class'                                => JCOGS_IMG_CLASS,
            'jcogs_add_on_name'                                 => JCOGS_IMG_NAME,
            'jcogs_add_on_version'                              => JCOGS_IMG_VERSION,
            'enable_img'                                        => 'y',
            'img_cp_speedy_escape'                              => 'n',
            'img_cp_action_links'                               => 'n',
            'img_cp_append_path_to_action_links'                => 'n',
            'jcogs_license_key'                                 => '',
            'jcogs_license_key_email'                           => '',
            'jcogs_staging_domain'                              => '',
            'jcogs_license_mode'                                => 'invalid',
            'jcogs_license_server_domain'                       => 'mule.jcogs.net',
            'img_cp_default_cache_directory'                    => 'images/jcogs_img/cache',
            'img_cp_path_prefix'                                => '',
            'img_cp_default_cache_duration'                     => '2678400',
            'img_cp_default_cache_audit_after'                  => '604800',
            'img_cp_enable_debugging'                           => 'n',
            'img_cp_enable_browser_check'                       => 'y',
            'img_cp_default_filename_separator'                 => '_-_',
            'img_cp_enable_cache_audit'                         => 'y',
            'img_cp_cache_auto_manage'                          => 'n',
            'img_cp_cache_log_preload_threshold'                => '10000',
            'img_cp_cache_log_current_count'                    => '0',
            'img_cp_cache_log_count_last_updated'               => '0',
            'img_cp_default_max_source_filename_length'         => '175',
            'img_cp_include_source_in_filename_hash'            => 'n',
            'img_cp_ce_image_remote_dir'                        => 'images/remote',
            'img_cp_default_max_image_size'                     => '4',
            'img_cp_default_min_php_ram'                        => '64',
            'img_cp_default_min_php_process_time'               => '60',
            'img_cp_default_php_remote_connect_time'            => '3',
            'img_cp_default_user_agent_string'                  => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14.5; rv:127.0) Gecko/20100101 Firefox/127.0',
            'img_cp_default_image_format'                       => 'source',
            'img_cp_jpg_default_quality'                        => '90',
            'img_cp_png_default_quality'                        => '6',
            'img_cp_default_bg_color'                           => '#FFFFFF',
            'img_cp_enable_svg_passthrough'                     => 'n',
            'img_cp_default_img_width'                          => '350',
            'img_cp_default_img_height'                         => '150',
            'img_cp_allow_scale_larger_default'                 => 'n',
            'img_cp_class_consolidation_default'                => 'y',
            'img_cp_attribute_variable_expansion_default'       => 'y',
            'img_cp_class_always_output_full_urls'              => 'n',
            'img_cp_enable_auto_sharpen'                        => 'n',
            'img_cp_enable_lazy_loading'                        => 'n',
            'img_cp_lazy_loading_mode'                          => 'lqip',
            'img_cp_lazy_progressive_enhancement'               => '1',
            'img_cp_html_decoding_enabled'                      => 'y',
            'img_cp_enable_default_fallback_image'              => 'n',
            'img_cp_fallback_image_colour'                      => '#306392',
            'img_cp_fallback_image_local'                       => '',
            'img_cp_fallback_image_remote'                      => 'https://plus.unsplash.com/premium_photo-1675848493910-5474ee04c3e3',
            'img_cp_enable_auto_adjust'                         => 'n',
            'img_cp_default_max_image_dimension'                => '2500',
            'img_cp_ignore_save_type_for_animated_gifs'         => '0',
            'img_cp_flysystem_adapter'                          => 'local',
            'img_cp_flysystem_adapter_config'                   => 'local',
            'img_cp_flysystem_adapter_s3_key'                   => '',
            'img_cp_flysystem_adapter_s3_secret'                => '',
            'img_cp_flysystem_adapter_s3_secret_actual'         => '',
            'img_cp_flysystem_adapter_s3_region'                => 'eu-west-2',
            'img_cp_flysystem_adapter_s3_bucket'                => '',
            'img_cp_flysystem_adapter_s3_server_path'           => '',
            'img_cp_flysystem_adapter_s3_url'                   => '',
            'img_cp_flysystem_adapter_s3_is_valid'              => 'false',
            'img_cp_flysystem_adapter_r2_account_id'            => '',
            'img_cp_flysystem_adapter_r2_key'                   => '',
            'img_cp_flysystem_adapter_r2_secret'                => '',
            'img_cp_flysystem_adapter_r2_secret_actual'         => '',
            'img_cp_flysystem_adapter_r2_bucket'                => '',
            'img_cp_flysystem_adapter_r2_server_path'           => '',
            'img_cp_flysystem_adapter_r2_url'                   => '',
            'img_cp_flysystem_adapter_r2_is_valid'              => 'false',
            'img_cp_flysystem_adapter_dospaces_key'             => '',
            'img_cp_flysystem_adapter_dospaces_secret'          => '',
            'img_cp_flysystem_adapter_dospaces_secret_actual'   => '',
            'img_cp_flysystem_adapter_dospaces_region'          => 'lon1',
            'img_cp_flysystem_adapter_dospaces_space'           => '',
            'img_cp_flysystem_adapter_dospaces_server_path'     => '',
            'img_cp_flysystem_adapter_dospaces_url'             => '',
            'img_cp_flysystem_adapter_dospaces_is_valid'        => 'false',
        );
    }

    /**
     * Returns the app settings as an array, filling in default values where there are none saved
     *
     * @return array
     */
    private function get_settings(): array
    {
        if (static::$settings) {
            return static::$settings;
        }

        $query = ee()->db->get_where('jcogs_img_settings', array('site_id' => ee()->config->item('site_id')));
        static::$settings = array();
        if ($query->num_rows() > 0) {
            foreach ($query->result_array() as $row) {
                static::$settings[$row["setting_name"]] = $row["value"];
            }
            static::$settings = array_merge($this->_default_settings(), static::$settings);
        } else {
            static::$settings = $this->_default_settings();
        }

        // Decrypt sensitive values if they appear to be encrypted
        $this->_decrypt_sensitive_settings();

        // See if we have any config.php over-rides set for control settings
        foreach(static::$settings as $param => $value) {
            if (str_starts_with(haystack: $param, needle: 'img_cp' )) {
                static::$settings[$param] = ee()->config->item('jcogs_img_' . str_ireplace(search: 'img_cp_', replace: '', subject: $param)) ?: $value;
            }
        }

        return static::$settings;
    }

    /**
     * Saves app settings with encryption for sensitive data
     *
     * @param array $settings
     * @return bool
     */
    public function save_settings($settings = array()): bool
    {
        // New settings are the merger of current settings and inbound settings
        $new_settings = array_merge(static::$settings, $settings);

        // Encrypt sensitive values
        foreach ($this->sensitive_keys as $key) {
            if (isset($new_settings[$key]) && !empty($new_settings[$key]) && $new_settings[$key] !== $this->_default_settings()[$key]) {
                // Add a prefix to mark the value as encrypted
                $new_settings[$key] = 'enc_' . ee('Encrypt')->encode(($new_settings[$key]));
            }
        }
        // Clear the licensing server action table in case we've changed domain
        ee('jcogs_img:Utilities')->cache_utility('delete', JCOGS_IMG_CLASS . '/' . 'licensing_action_ids');

        // Get what is in data table - set to array if nothing there
        $data_in_table = [];
        $query = ee()->db->get_where('jcogs_img_settings', array('site_id' => ee()->config->item('site_id')));
        if ($query->num_rows) {
            $query_results = $query->result_array();
            foreach ($query_results as $row) {
                $data_in_table[$row['setting_name']] = $row['value'];
            }
        }

        // Loop through the new settings and see if we have same thing saved.
        foreach ($new_settings as $key => $value) {
            // Work out what is in data table and update to new values as required
            if (!isset($data_in_table[$key])) {
                // Value is not in table so add it to the table
                ee()->db->insert('jcogs_img_settings', array('site_id' => ee()->config->item('site_id'), 'setting_name' => $key, 'value' => $value));
            } elseif ($data_in_table[$key] != $value) {
                // There is something in datatable, but update it as new value is different
                ee()->db->update('jcogs_img_settings', array('value' => $value), array('site_id' => ee()->config->item('site_id'), 'setting_name' => $key));
            }
        }
        // Update static value with new settings
        static::$settings = $new_settings;

        return true;
    }

    /**
     * Decrypts sensitive settings values if they appear to be encrypted
     *
     * @return void
     */
    private function _decrypt_sensitive_settings(): void
    {
        foreach ($this->sensitive_keys as $key) {
            if (isset(static::$settings[$key]) && !empty(static::$settings[$key]) && str_starts_with(static::$settings[$key], 'enc_')) {
                // Remove prefix and decrypt
                $encrypted_value = substr(static::$settings[$key], 4); // Remove 'enc_'
                $decrypted = ee('Encrypt')->decode($encrypted_value);
                
                if ($decrypted !== false) {
                    static::$settings[$key] = $decrypted;
                }
            }
        }
    }
}