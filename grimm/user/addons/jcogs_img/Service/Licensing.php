<?php

/**
 * JCOGS Licensing Service
 * =======================
 * Provides license validation functions for add-ons
 * 
 * CHANGELOG
 * 
 * 11/4/2022:  1.0.0  - First Release
 * 13/4/2022:  1.0.1  - Move some functions over from Utility library
 * 15/5/2022:  1.0.2  - Widened list of demo tlds
 * 6/11/2022:  1.2.16 - allow demo mode on any installation
 * 10/01/2023: 1.3.4  - Added: trap for error polling licensing server
 * 23/07/2024: 1.4.7  - Moved all remote file transactions to generic utility
 * 
 * =====================================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Licensing
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.4.16.2
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.0.0
 */

namespace JCOGSDesign\Jcogs_img\Service;

require_once PATH_THIRD . "jcogs_img/config.php";
ee()->lang->load('jcogs_lic', ee()->session->get_language(), false, true, PATH_THIRD . 'jcogs_img/');

class Licensing
{

    public static $_settings;
    public static $license_status;
    private $settings;

    public function __construct()
    {
        $this->settings = ee('jcogs_img:Settings')::$settings;
        if (empty($this->settings)) {
            ee('jcogs_img:Utilities')->debug_message("Failed to load settings.");
        }    
    }

    /**
     * JCOGS Licening - get action ids
     * ===============================
     * Gets the action ids of licensing actions from licensing server
     *
     * @return object|boolean
     */
    public function get_licensing_action_ids(): bool|object
    {
        // Check if we have anything in the cache
        $cache_key = $this->settings['jcogs_add_on_class'] . '/licensing_action_ids';
        $action_array = ee('jcogs_img:Utilities')->cache_utility('get', $cache_key);
    
        if (is_object($action_array)) {
            return $action_array;
        }
    
        // Nothing in cache, so get the action values from the remote server
        $remote_url = 'https://' . $this->settings['jcogs_license_server_domain'] . '/actions';
        $remote_file = ee('jcogs_img:Utilities')->get_file_from_remote($remote_url);
    
        if ($remote_file === false) {
            ee('jcogs_img:Utilities')->debug_message("Failed to retrieve licensing action IDs from remote server.");
            return false;
        }
    
        $action_array = json_decode($remote_file);
    
        // Put a copy in the cache
        ee('jcogs_img:Utilities')->cache_utility('save', $cache_key, $action_array, 60 * 60);
    
        return $action_array;
    }

    /**
     * Works out if we can run in demo mode based on server info
     * Since ... it has been OK to run in demo mode on any platform when not licensed
     *
     * @return string
     */
    public function is_demo_mode_allowed($domain = null, $license_usage_ip = null)
    {
        return 'demo';
    }

    /**
     * Gets the status of current add-on license
     * If we've done it already get value from static store
     *
     * @param  string|null $license_key
     * @param  string|null $license_key_email
     * @return object
     */
    public function license_status($license_key = null, $license_key_email = null)
    {
        // Is result already in static store...?
        if (static::$license_status) {
            return static::$license_status;
        }

        if (!$license_key) {
            // We might be on a staging server ... so check status with licensing server
            static::$license_status = $this->_validate_license();
            return static::$license_status;
        }

        // If we are here we have a license key to consider ... 
        // Do we have a license_key_email value set yet?
        if (!($license_key_email || (isset($this->settings['jcogs_license_key_email']) && $license_key_email = $this->settings['jcogs_license_key_email']))) {
            return json_decode(json_encode(
                [
                    'message' => lang('jcogs_lic_cp_no_license_key_email'),
                    'status' => 'invalid',
                    'change_count' => 0,
                ]
            ));
        }

        // If we get here we have all we need, so go ahead and do a proper validation
        static::$license_status = $this->_validate_license($license_key, $license_key_email, true);

        return static::$license_status;
    }

    /**
     * Works out what text to display for license registration element
     *
     * @param  string|null $license_key
     * @param  string|null $license_key_email
     * @return array
     */
    public function mcp_license_key_entry($license_key = null, $license_key_email = null): array
    {
        // Is key the obscured value ...? If so substitute actual value.
        if ($license_key == ee('jcogs_img:Utilities')->obscure_key($this->settings['jcogs_license_key'])) {
            $license_key = $this->settings['jcogs_license_key'];
        }

        $license_info = $this->license_status($license_key, $license_key_email);
        $license_status = $license_info->status;

        // Pick a message linked to their current license status
        switch ($license_status) {
            case 'valid':
                $title = lang('jcogs_lic_cp_license_key_valid');
                $desc = lang('jcogs_lic_cp_license_key_valid_desc') . '<br>';
                $desc = lang('jcogs_lic_cp_license_valid_mode_desc') . '<br>';
                $desc .= lang('jcogs_lic_cp_license_support_desc');
                break;
            case 'staging':
                $licensing_domain = $license_info->usage_domain;
                $title = lang('jcogs_lic_cp_license_key_staging');
                $desc = sprintf(lang('jcogs_lic_cp_license_key_staging_desc'), $licensing_domain) . '<br>';
                $desc .= lang('jcogs_lic_cp_license_support_desc');
                break;
            case 'magic':
                $title = lang('jcogs_lic_cp_license_key_valid');
                $title .= lang('jcogs_lic_cp_license_key_magic');
                $desc = lang('jcogs_lic_cp_license_magic_mode_desc') . '<br>';
                $desc .= lang('jcogs_lic_cp_license_support_desc');
                break;
            case 'demo':
                $title = lang('jcogs_lic_cp_license_key_demo');
                $desc = lang('jcogs_lic_cp_license_key_demo_desc') . '<br>';
                $desc .= lang('jcogs_lic_cp_license_purchase_desc') . '<br>';
                $desc .= lang('jcogs_lic_cp_license_support_desc');
                break;
            case 'invalid':
                $title = lang('jcogs_lic_cp_license_key_invalid');
                $desc = lang('jcogs_lic_cp_license_key_invalid_desc') . '<br>';
                $desc .= lang('jcogs_lic_cp_license_purchase_desc') . '<br>';
                $desc .= lang('jcogs_lic_cp_license_support_desc');
                break;
            default:
                $title = lang('jcogs_lic_cp_license_key_process_error');
                $desc = lang('jcogs_lic_cp_license_key_process_error_desc');
        }

        // Has user attempted to enter a license?
        if (!$this->settings['jcogs_license_key'] && $license_status != 'staging') {
            // Give them an introductory message
            $desc = lang('jcogs_lic_cp_license_key_missing_desc') . '<br>';
            $desc .= lang('jcogs_lic_cp_license_key_demo_desc') . '<br>';
            $desc .= lang('jcogs_lic_cp_license_purchase_desc') . '<br>';
            $desc .= lang('jcogs_lic_cp_license_support_desc');
        }

        // Work out what to return
        return
            array(
                'title' => $title,
                'desc' => '<div style="padding-top:0.4rem;padding-bottom:0.4rem;">' . $desc . '</div>',
                'fields' => array(
                    'jcogs_license_key' => array(
                        'type'  => 'text',
                        'value' => ee('jcogs_img:Utilities')->obscure_key($this->settings['jcogs_license_key']),
                        'placeholder' => lang('jcogs_lic_cp_license_key_placeholder'),
                        'required' => false
                    )
                )
            );
    }

    /**
     * Works out what to display for staging domain element
     *
     * @param  string|null $license_mode
     * @return array
     */
    public function mcp_licensing_server_domain_entry($jcogs_license_server_domain = null): array
    {
        return
            array(
                'title' => 'jcogs_lic_cp_jcogs_licensing_server_domain',
                'desc' => 'jcogs_lic_cp_jcogs_licensing_server_domain_desc',
                'group' => 'advanced_options',
                'fields' => array(
                    'jcogs_license_server_domain' => array(
                        'type'  => 'text',
                        'value' => $jcogs_license_server_domain ?: $this->settings['jcogs_license_server_domain'],
                        'required' => true
                    )
                )
            );
    }

    /**
     * Works out what to display for staging domain element
     *
     * @param  string|null $license_mode
     * @return array|bool
     */
    public function mcp_staging_domain_entry($license_mode = null): array|bool
    {
        if (!$license_mode) return false;

        if ($this->settings['jcogs_license_mode'] == 'valid' || $this->settings['jcogs_license_mode'] == 'magic') {
            // Only show this if we have a full-fat license installed already
            return
                array(
                    'title' => lang('jcogs_lic_cp_staging_domain'),
                    'desc' => lang('jcogs_lic_cp_staging_domain_desc'),
                    'fields' => array(
                        'jcogs_staging_domain' => array(
                            'type'  => 'text',
                            'value' => $this->settings['jcogs_staging_domain'],
                            'required' => false
                        )
                    )
                );
        } else {
            return [];
        }
    }

    /**
     * Checks valid license
     *
     * @return 
     */
    private function _validate_license($license_key = null, $license_key_email = null, $register = null)
    {
        // Is result already in cache...?
        $result = ee('jcogs_img:Utilities')->cache_utility('get', $this->settings['jcogs_add_on_class'] . '/' . 'license_status');
        if ($result) {
            return $result;
        }

        // Is key the obscured value ...? If so substitute actual value.
        if ($license_key == ee('jcogs_img:Utilities')->obscure_key($this->settings['jcogs_license_key'])) {
            $license_key = $this->settings['jcogs_license_key'];
        }

        // Collect up some system info to send
        $packet = [
            'license_key' => $license_key,
            'license_key_email' => $license_key_email,
            'license_add_on' => $this->settings['jcogs_add_on_class'],
            'save_license_if_valid' => $register,
            'license_usage_ee_version' => APP_VER,
            'license_usage_domain' => $_SERVER['HTTP_HOST'],
            'license_staging_domain' => $this->settings['jcogs_staging_domain'],
            'license_usage_ip' => $_SERVER['SERVER_ADDR'],
            'license_usage_php_version' => phpversion(),
            'license_usage_add_on_version' => $this->settings['jcogs_add_on_version'],
            'license_usage_site_id' => ee()->config->item('site_id')
        ];

        // Get the ACTion value
        // If we cannot reach the server, continue with license value from settings
        $action_urls = $this->get_licensing_action_ids();
        if (!$action_urls || !property_exists($action_urls, 'validate')) {
            return json_decode(json_encode(
                [
                    'message' => 'jcogs_lic_cp_unable_to_reach_licensing_server',
                    'status' => ee('jcogs_img:Settings')::$settings['jcogs_license_mode'],
                    'change_count' => 0,
                ]
            ));
        }

        // Validate license via a POST request
        $result = ee('jcogs_img:Utilities')->get_file_from_remote($action_urls->validate, $packet);

        if($result) {
            // We got something ... so 
            $result = json_decode($result);
            ee('jcogs_img:Utilities')->cache_utility('save', $this->settings['jcogs_add_on_class'] . '/' . 'license_status', $result, 5);

            // Update setting value with current status
            ee('jcogs_img:Settings')->save_settings(['jcogs_license_mode' => $result->status]);

            return $result;
        }
        return false;
    }

    /**
     * Checks for valid license email address 
     * (i.e. simply that email exists in license database)
     *
     * @return object|bool
     */
    public function validate_license_email($license_key_email = null): object|bool
    {
        // Do we have a license key email - if not get value from settings
        $license_key_email = $license_key_email ?: $this->settings['jcogs_license_key_email'];

        // If we don't have an email at all, bale... 
        if (!$license_key_email) {
            return json_decode(json_encode([
                'message'       => lang('jcogs_lic_not_enough_params'),
                'status'        => 'invalid'
            ]));
        }

        // Collect up some system info to send
        $packet = [
            'license_key_email' => $license_key_email,
            'license_add_on' => $this->settings['jcogs_add_on_class'],
        ];

        // Get the ACTion value
        $action_urls = $this->get_licensing_action_ids();
        if (!is_object($action_urls) || !property_exists($action_urls, 'check_email')) {
            return false;
        }

        // Validate license via a POST request
        $result = ee('jcogs_img:Utilities')->get_file_from_remote($action_urls->check_email, $packet);

        // Did it work?
        if ($result) {
            return json_decode($result);
        }
        return false;
    }
}
