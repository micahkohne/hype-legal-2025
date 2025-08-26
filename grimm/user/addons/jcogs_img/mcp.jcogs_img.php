<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Image Master Control Panel
 * ==========================
 * Defines the CP pages used by the  add-on
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

require_once PATH_THIRD . "jcogs_img/vendor/autoload.php";
require_once PATH_THIRD . "jcogs_img/config.php";

ee()->lang->load('jcogs_lic', ee()->session->get_language(), false, true, PATH_THIRD . 'jcogs_img/');

class Jcogs_img_mcp
{
    private $_settings = array();
    private $_data = array();
    private $license_status = null;
    private $license_key_email = '';
    private $cache_path_ok = null;
    private $cache_path = '';

    public function __construct()
    {
        $this->_settings = ee('jcogs_img:Settings')::$settings;
        $active_adapter_name = ee('jcogs_img:ImageUtilities')::$adapter_name;

        // Check we have a license in place
        if (!($this->_settings['jcogs_license_mode'] == 'valid' || $this->_settings['jcogs_license_mode'] == 'magic')) {
            // Check to see if we can run in demo mode
            $this->_settings['jcogs_license_mode'] = ee('jcogs_img:Licensing')->is_demo_mode_allowed();
        }

        // Check php version is valid... 
        if (!ee('jcogs_img:Utilities')->valid_php_version()) {
            // We cannot operate without a valid php version so bale... 
            ee('CP/Alert')->makeBanner('jcogs_img_cp_php_version_invalid')
                ->asIssue()
                ->withTitle(lang('jcogs_img_cp_php_version_invalid'))
                ->addToBody(sprintf(lang('jcogs_img_cp_php_version_invalid_desc'), strval(PHP_VERSION)))
                ->now();
        }

        // Check EE version is valid... 
        if (!ee('jcogs_img:Utilities')->valid_ee_version()) {
            // We cannot operate without a valid php version so bale... 
            ee('CP/Alert')->makeBanner('jcogs_img_cp_ee_version_invalid')
                ->asIssue()
                ->withTitle(lang('jcogs_img_cp_ee_version_invalid'))
                ->addToBody(sprintf(lang('jcogs_img_cp_ee_version_invalid_desc'), strval(APP_VER)))
                ->now();
        }

        // Check EE base_path is valid... 
        if (!ee('jcogs_img:Utilities')->get_base_path()) {
            // We cannot operate without a valid EE base_path so bale... 
            ee('CP/Alert')->makeBanner('jcogs_img_cp_ee_base_path_invalid')
                ->asIssue()
                ->withTitle(lang('jcogs_img_cp_ee_base_path_invalid'))
                ->addToBody(sprintf(lang('jcogs_img_cp_ee_base_path_invalid_desc'),ee()->config->item('base_path')))
                ->now();
        }

        // Check 1.4.4 version is installed... 
        if (!version_compare(ee('Addon')->get('jcogs_img')->getInstalledVersion(), '1.4.5.RC1', 'ge')) {
            // Until cache_log_db is installed cache is disabled so encourage installation of add-on... 
            ee('CP/Alert')->makeBanner('jcogs_img_cp_install_1_4_4')
                ->asWarning()
                ->withTitle(lang('jcogs_img_cp_install_1_4_4'))
                ->addToBody(sprintf(lang('jcogs_img_cp_install_1_4_4_desc'), strval(APP_VER)))
                ->now();
        }

        // Check we have a valid cache path in place
        // Get the path to the cache
        if($active_adapter_name !== 'local') {
            $this->cache_path = $this->_settings['img_cp_flysystem_adapter_'.$active_adapter_name.'_server_path'];
        } else {
            $this->cache_path = $this->_settings['img_cp_default_cache_directory'];
        }

        // Is it a valid cache path (only matters for local adapter)?
        $this->cache_path_ok = $active_adapter_name !== 'local' || ee('jcogs_img:ImageUtilities')->directoryExists($this->cache_path, true);
        // If it is a local adapter, it is a $_POST event and no cache, try and create cache_dir if not present
        if(!$this->cache_path_ok && $active_adapter_name == 'local' && isset($_POST['img_cp_default_cache_directory'])) {
            $this->cache_path = $_POST['img_cp_default_cache_directory'];
            $this->cache_path_ok = ee('jcogs_img:ImageUtilities')->createDirectory($this->cache_path);
        }

        if (!$this->cache_path_ok) {
            if($this->_settings['img_cp_flysystem_adapter'] == 'local') {
                // Local cache path problems are usually base_path related... 
                ee('CP/Alert')->makeBanner('jcogs_img_cp_local_cache_path_invalid')
                    ->asIssue()
                    ->withTitle(lang('jcogs_img_cp_local_cache_path_invalid'))
                    ->addToBody(lang('jcogs_img_cp_local_cache_path_invalid_desc'))
                    ->now();
            } else {
                // Issues relating to cloud system adapter are probably credential related... 
                $adapter_name = 'jcogs_img_cp_flysystem_'.$this->_settings['img_cp_flysystem_adapter'].'_adapter';
                ee('CP/Alert')->makeBanner('jcogs_img_cp_cloud_cache_path_invalid')
                    ->asIssue()
                    ->withTitle(lang('jcogs_img_cp_cloud_cache_path_invalid'))
                    ->addToBody(sprintf(lang('jcogs_img_cp_cloud_cache_path_invalid_desc'),lang($adapter_name)))
                    ->now();
            }
        }
    }

    public function index()
    {
        $this->_build_sidebar();

        // --------------------------------------
        // Validate and then save any changes
        // --------------------------------------
        if ($_POST) {

            // Validation
            $validator = ee('Validation')->make();

            // Define custom validation rules
            // ------------------------------

            // 1) Valid php version
            // --------------------
            $validator->defineRule('valid_php_version', function ($key, $value, $parameters) {
                // Add-on will only work when installed on systems with php 7.3 or better.
                if (!ee('jcogs_img:Utilities')->valid_php_version()) {
                    return 'jcogs_img_cp_php_version_invalid';
                }
                return true;
            });

            // 2) Valid EE version
            // --------------------
            $validator->defineRule('valid_ee_version', function ($key, $value, $parameters) {
                // Add-on will only work when installed on systems with php 7.4 or better.
                if (!ee('jcogs_img:Utilities')->valid_ee_version()) {
                    return 'jcogs_img_cp_ee_version_invalid';
                }
                return true;
            });

            $validator->setRules(array(
                'enable_img'                            => 'enum[y,n]|valid_php_version|valid_ee_version',
                'img_cp_speedy_escape'                  => 'enum[y,n]',
                'img_cp_enable_debugging'               => 'enum[y,n]',
            ));

            $result = $validator->validate($_POST);
            $extra_message_line = '';
            if (isset($this->license_status) && property_exists($this->license_status, 'message')) {
                $extra_message_line = $this->license_status->message;
            }

            if ($result->isValid()) {

                $fields = array();
                // Get all $_POST values, store them in array and save them
                // Use ee input library as it cleans up POST entries on loading
                // Define allowed settings keys
                $allowed_settings = array_keys($this->_settings);

                foreach ($_POST as $key => $value) {
                    // Only process known settings
                    if (!in_array($key, $allowed_settings)) {
                        continue;
                    }
                    
                    $fields[$key] = ee()->input->post($key);
                    $fields[$key] = is_numeric($fields[$key]) ? (int) $fields[$key] : $fields[$key];
                }
                $fields = array_merge($this->_settings, $fields);

                // Now save the settings values
                ee('jcogs_img:Settings')->save_settings($fields);

                // Pop up a save confirmation if all went well.
                ee('CP/Alert')->makeInline('shared-form')
                    ->asSuccess()
                    ->withTitle(lang('preferences_updated'))
                    ->addToBody(lang('preferences_updated_desc'))
                    ->addToBody($extra_message_line)
                    ->defer();

                // Redraw page now
                ee()->functions->redirect(ee('CP/URL', 'addons/settings/jcogs_img')->compile());
            } else {
                $this->_data['errors'] = $result;
                ee('CP/Alert')->makeInline('shared-form')
                    ->asIssue()
                    ->withTitle(lang('settings_save_error'))
                    ->addToBody(lang('settings_save_error_desc'))
                    ->addToBody($extra_message_line)
                    ->now();
            }
        }

        // No post data, so just draw the page

        // --------------------------------------
        // Build the form into $sections array
        // --------------------------------------

        $sections = array();

        $sections[lang('jcogs_img_cp_system_enable')] = array(
            'group' => 'on_off_options',
            'settings' => array(

                // ----------------------------------------
                // Global on/off switch
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_enable_globally',
                    'desc' => 'jcogs_img_cp_enable_globally_desc',
                    'fields' => array(
                        'enable_img' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['enable_img'],
                            'group_toggle' => array(
                                'y' => 'img_options|path_option|cache_options|adv_options'
                            )
                        )
                    )
                )
            )
        );

        // if(array_key_exists('speedy', ee('Addon')->installed()) && strtolower(ee()->config->item('speedy_enabled')) == 'yes') {
        //     $sections[lang('jcogs_img_cp_speedy_integration')] = array(
        //         'group' => 'speedy_escape_options',
        //         'settings' => array(

        //             // ----------------------------------------
        //             // Speedy Integration
        //             // ----------------------------------------

        //             array(
        //                 'title' => 'jcogs_img_cp_speedy_escape_enable',
        //                 'desc' => 'jcogs_img_cp_speedy_escape_enable_desc',
        //                 'fields' => array(
        //                     'img_cp_speedy_escape' => array(
        //                         'type'  => 'yes_no',
        //                         'value' => $this->_settings['img_cp_speedy_escape'],
        //                     )
        //                 )
        //             )
        //         )
        //     );
        // }

        if($action_id = ee('jcogs_img:Utilities')->get_action_id('act_originated_image')) {
            $action_id_string = sprintf(lang('jcogs_img_cp_action_image_desc'), ee()->config->item('site_url') . '?ACT=' . strval($action_id));
         } else {
            $action_id_string = sprintf(lang('jcogs_img_cp_no_action_found'));
         }

        $sections[lang('jcogs_img_cp_action_image')] = array(
            'group' => 'action_image',
            'settings' => array(

                // ----------------------------------------
                // ACT Integration message
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_action_image_title',
                    'desc' => $action_id_string
                ),

                // ----------------------------------------
                // Action Links Global Default
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_action_links_enable',
                    'desc' => 'jcogs_img_cp_action_links_enable_desc',
                    'fields' => array(
                        'img_cp_action_links' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_action_links'],
                        )
                    )
                )
            )
        );

        $sections[lang('jcogs_img_system_options_debug')] = array(
            'group' => 'app_options',
            'settings' => array(
                // --------------------------------------
                // Enable debugging reports?
                // --------------------------------------

                array(
                    'title' => 'jcogs_img_cp_enable_debugging',
                    'desc' => 'jcogs_img_cp_enable_debugging_desc',
                    'fields' => array(
                        'img_cp_enable_debugging' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_enable_debugging']
                        )
                    )
                )
            )
        );

        $this->_data += array(
            'cp_page_title' => lang('jcogs_img_system_options'),
            'base_url' => ee('CP/URL', 'addons/settings/jcogs_img')->compile(),
            'save_btn_text' => sprintf(lang('btn_save'), lang('jcogs_img_cp_main_settings')),
            'save_btn_text_working' => lang('btn_saving'),
            'sections' => $sections
        );

        return array(
            'heading'       => lang('jcogs_img_cp_main_settings'),
            'breadcrumb'    => array(
                ee('CP/URL', 'addons/settings/jcogs_img')->compile() => lang('jcogs_img_module_name')
            ),
            'body'          => ee('View')->make('ee:_shared/form')->render($this->_data),
        );
    }

    public function advanced_settings()
    {
        ee()->load->library('file_field');
        $this->_build_sidebar();

        // --------------------------------------
        // Validate and then save any changes
        // --------------------------------------
        if ($_POST) {

            // Validation
            $validator = ee('Validation')->make();

            // Define custom validation rules
            // ------------------------------

            // 1) Valid user agent string
            // --------------------------
            $validator->defineRule('valid_user_agent_string', function ($key, $value, $parameters) {
                $browser_info = ee('jcogs_img:Utilities')->getBrowser($value);
                if (!$browser_info['userAgent'] || !$browser_info['platform']) {
                    return 'jcogs_img_cp_user_agent_string';
                }
                return true;
            });

            // 2) Valid CE Image Remote Directory
            // ----------------------------------
            // If not equal to default value, needs to be valid directory
            $validator->defineRule('valid_ce_image_remote_dir', function ($key, $value, $parameters) {
                if ($value != trim($this->_settings['img_cp_ce_image_remote_dir']) && !ee('jcogs_img:ImageUtilities')->directoryExists($value)) {
                    return 'jcogs_img_cp_valid_ce_image_remote_dir';
                }
                return true;
            });

            // 3) Valid filename separator
            // ---------------------------
            $validator->defineRule('valid_separator', function ($key, $value, $parameters) {
                // Check the length and for spaces and for reserved characters
                if (
                    !strlen($value) ||
                    preg_match('/\s+/', $value) ||
                    preg_match('/[\[\^\-\\\]\_\.\~\!\*\'\(\)\;\:\@\&\=\+\$\,\/\?\%\#]/', $value)
                ) {
                    return  'jcogs_img_cp_invalid_separator_string';
                }
                return true;
            });

            // 4) Valid licensing domain
            // -------------------------
            $validator->defineRule('valid_licensing_domain', function ($key, $value, $parameters) {
                // Find out if we can poll the licensing server... 
                $action_array = ee('jcogs_img:Utilities')->get_file_from_remote('https://' . $value . '/actions');
                if ($action_array != false) {
                    return true;
                }
                return  'jcogs_lic_cp_invalid_licensing_domain';
            });

            // 5) Valid php ram value
            // ----------------------
            $validator->defineRule('valid_php_ram_value', function ($key, $value, $parameters) {
                // Find out if we have something that looks like a valid PHP ram value... 
                $requested_php_ram = ee('jcogs_img:Utilities')->normalize_memory_limit($value);
                if ($requested_php_ram > 0) {
                    return true;
                }
                return  'jcogs_img_cp_invalid_php_ram_value';
            });


            $validator->setRules(array(
                'img_cp_enable_browser_check'               => 'enum[y,n]',
                'img_cp_class_consolidation_default'        => 'enum[y,n]',
                'img_cp_default_filename_separator'         => 'valid_separator|required',
                'img_cp_ce_image_remote_dir'                => 'whenPresent|valid_ce_image_remote_dir',
                'img_cp_default_max_source_filename_length' => 'integer|greaterThan[0]|lessThan[176]|required',
                'img_cp_default_min_php_ram'                => 'greaterThan[0]|required|valid_php_ram_value',
                'img_cp_default_min_php_process_time'       => 'integer|greaterThan[-1]|required',
                'img_cp_default_php_remote_connect_time'    => 'integer|greaterThan[0]|required',
                'img_cp_default_user_agent_string'          => 'valid_user_agent_string|required',
                'jcogs_license_server_domain'               => 'valid_licensing_domain',
                'img_cp_cache_log_preload_threshold'        => 'integer|greaterThan[0]|required',
                'img_cp_default_cache_audit_after'          => 'integer|greaterThan[0]|required',
                'img_cp_enable_cache_audit'                 => 'enum[y,n]'
            ));

            $result = $validator->validate($_POST);

            if ($result->isValid()) {

                $fields = array();
                // Get all $_POST values, store them in array and save them
                // Use ee input library as it cleans up POST entries on loading
                // Define allowed settings keys
                $allowed_settings = array_keys($this->_settings);

                foreach ($_POST as $key => $value) {
                    // Only process known settings
                    if (!in_array($key, $allowed_settings)) {
                        continue;
                    }
                    
                    $fields[$key] = ee()->input->post($key);
                    $fields[$key] = is_numeric($fields[$key]) ? (int) $fields[$key] : $fields[$key];
                }
    
                // Check if threshold setting has changed - if so, update the cache count
                $old_threshold = $this->_settings['img_cp_cache_log_preload_threshold'];
                $new_threshold = $fields['img_cp_cache_log_preload_threshold'] ?? $old_threshold;
                
                if ($old_threshold != $new_threshold) {
                    // Threshold changed - update the cache count
                    $current_count = ee('jcogs_img:ImageUtilities')->get_current_cache_log_count();
                    $fields['img_cp_cache_log_current_count'] = $current_count;
                    $fields['img_cp_cache_log_count_last_updated'] = time();
                }

                // Merge the new settings with the existing ones
                $fields = array_merge($this->_settings, $fields);

                // Now save the settings values
                ee('jcogs_img:Settings')->save_settings($fields);

                // Pop up a save confirmation if all went well.
                ee('CP/Alert')->makeInline('shared-form')
                    ->asSuccess()
                    ->withTitle(lang('preferences_updated'))
                    ->addToBody(lang('preferences_updated_desc'))
                    ->defer();

                // Redraw page now
                ee()->functions->redirect(ee('CP/URL', 'addons/settings/jcogs_img/advanced_settings')->compile());
            } else {
                $this->_data['errors'] = $result;
                ee('CP/Alert')->makeInline('shared-form')
                    ->asIssue()
                    ->withTitle(lang('settings_save_error'))
                    ->addToBody(lang('settings_save_error_desc'))
                    ->now();
            }
        }

        // No post data, so just draw the page

        // --------------------------------------
        // Enable JCOGS Image Advanced options?
        // --------------------------------------

        $sections[lang('jcogs_img_advanced_settings')] = array(
            'group' => 'adv_options',
            'settings' => array(
                array(
                    'title' => 'jcogs_img_cp_advanced_options',
                    'desc' => 'jcogs_img_cp_advanced_options_desc',
                    'fields' => array(
                        'img_cp_advanced_options' => array(
                            'type'  => 'yes_no',
                            'value' => 0,
                            'group_toggle' => array(
                                'y' => 'advanced_options'
                            )
                        )
                    )
                ),

                // ----------------------------------------
                // Enable browser capability detection
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_enable_browser_check',
                    'desc' => 'jcogs_img_cp_enable_browser_check_desc',
                    'group' => 'advanced_options',
                    'fields' => array(
                        'img_cp_enable_browser_check' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_enable_browser_check']
                        )
                    )
                ),

                // ----------------------------------------
                // Enable class consolidation capability
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_class_consolidation_default',
                    'desc' => 'jcogs_img_cp_class_consolidation_default_desc',
                    'group' => 'advanced_options',
                    'fields' => array(
                        'img_cp_class_consolidation_default' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_class_consolidation_default']
                        )
                    )
                ),

                // ----------------------------------------
                // Enable attribute variable expansion capability
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_attribute_variable_expansion_default',
                    'desc' => 'jcogs_img_cp_attribute_variable_expansion_default_desc',
                    'group' => 'advanced_options',
                    'fields' => array(
                        'img_cp_attribute_variable_expansion_default' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_attribute_variable_expansion_default']
                        )
                    )
                ),

                // ----------------------------------------
                // Set filename separator
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_default_filename_separator',
                    'desc' => 'jcogs_img_cp_default_filename_separator_desc',
                    'group' => 'advanced_options',
                    'fields' => array(
                        'img_cp_default_filename_separator' => array(
                            'type'  => 'text',
                            'value' => trim($this->_settings['img_cp_default_filename_separator']),
                            'required' => true
                        )
                    )
                ),

                // ------------------------------------------
                // Set default max length for source filename
                // ------------------------------------------

                array(
                    'title' => 'jcogs_img_cp_default_max_source_filename_length',
                    'desc' => 'jcogs_img_cp_default_max_source_filename_length_desc',
                    'group' => 'advanced_options',
                    'fields' => array(
                        'img_cp_default_max_source_filename_length' => array(
                            'type'  => 'text',
                            'value' => $this->_settings['img_cp_default_max_source_filename_length'],
                            'required' => true
                        )
                    )
                ),

                // ----------------------------------------
                // Add source filename to processed image hash
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_include_source_in_filename_hash',
                    'desc' => 'jcogs_img_cp_include_source_in_filename_hash_desc',
                    'group' => 'advanced_options',
                    'fields' => array(
                        'img_cp_include_source_in_filename_hash' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_include_source_in_filename_hash']
                        )
                    )
                ),

                // ----------------------------------------
                // Append image path to action link URL
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_append_path_to_action_links',
                    'desc' => 'jcogs_img_cp_append_path_to_action_links_desc',
                    'group' => 'advanced_options',
                    'fields' => array(
                        'img_cp_append_path_to_action_links' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_append_path_to_action_links']
                        )
                    )
                ),

                // ----------------------------------------
                // Set CE Image Remote Image Cache Directory
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_ce_image_remote_dir',
                    'desc' => 'jcogs_img_cp_ce_image_remote_dir_desc',
                    'group' => 'advanced_options',
                    'fields' => array(
                        'img_cp_ce_image_remote_dir' => array(
                            'type'  => 'text',
                            'value' => trim($this->_settings['img_cp_ce_image_remote_dir']),
                            'required' => false
                        )
                    )
                ),

                // -------------------------------------------------
                // Set php memory limit to request during operation
                // -------------------------------------------------

                array(
                    'title' => 'jcogs_img_cp_default_min_php_ram',
                    'desc' => 'jcogs_img_cp_default_min_php_ram_desc',
                    'group' => 'advanced_options',
                    'fields' => array(
                        'img_cp_default_min_php_ram' => array(
                            'type'  => 'text',
                            'value' => $this->_settings['img_cp_default_min_php_ram'],
                            'required' => true
                        )
                    )
                ),

                // --------------------------------------------------------
                // Set php execution time limit to request during operation
                // --------------------------------------------------------

                array(
                    'title' => 'jcogs_img_cp_default_min_php_process_time',
                    'desc' => 'jcogs_img_cp_default_min_php_process_time_desc',
                    'group' => 'advanced_options',
                    'fields' => array(
                        'img_cp_default_min_php_process_time' => array(
                            'type'  => 'text',
                            'value' => $this->_settings['img_cp_default_min_php_process_time'],
                            'required' => true
                        )
                    )
                ),

                // --------------------------------------------------------
                // Set php connection time limit for remote file retrieval
                // --------------------------------------------------------

                array(
                    'title' => 'jcogs_img_cp_default_php_remote_connect_time',
                    'desc' => 'jcogs_img_cp_default_php_remote_connect_time_desc',
                    'group' => 'advanced_options',
                    'fields' => array(
                        'img_cp_default_php_remote_connect_time' => array(
                            'type'  => 'text',
                            'value' => $this->_settings['img_cp_default_php_remote_connect_time'],
                            'required' => true
                        )
                    )
                ),

                // --------------------------------------------------------
                // Set user agent string to use for remote file retrieval
                // --------------------------------------------------------

                array(
                    'title' => 'jcogs_img_cp_default_user_agent_string',
                    'desc' => 'jcogs_img_cp_default_user_agent_string_desc',
                    'group' => 'advanced_options',
                    'fields' => array(
                        'img_cp_default_user_agent_string' => array(
                            'type'  => 'text',
                            'value' => $this->_settings['img_cp_default_user_agent_string'],
                            'required' => true
                        )
                    )
                ),

                // --------------------------------------
                // Set cache_log preload threshold
                // --------------------------------------

                array(
                    'title' => 'jcogs_img_cp_cache_log_preload_threshold',
                    'desc' => 'jcogs_img_cp_cache_log_preload_threshold_desc',
                    'group' => 'advanced_options',
                    'fields' => array(
                        'img_cp_cache_log_preload_threshold' => array(
                            'type'  => 'text',
                            'value' => $this->_settings['img_cp_cache_log_preload_threshold'],
                            'required' => true
                        )
                    )
                ),

                // --------------------------------------
                // Cache preload threshold display
                // --------------------------------------

                array(
                    'title' => 'jcogs_img_cp_cache_log_current_count',
                    'desc' => 'jcogs_img_cp_cache_log_current_count_desc',
                    'group' => 'advanced_options',
                    'fields' => array(
                        'cache_count_display' => array(
                            'type' => 'html',
                            'content' => sprintf(
                                '%s entries (last updated: %s)', 
                                number_format((int)$this->_settings['img_cp_cache_log_current_count']),
                                $this->_settings['img_cp_cache_log_count_last_updated'] > 0 
                                    ? date('Y-m-d H:i:s', $this->_settings['img_cp_cache_log_count_last_updated'])
                                    : 'Never'
                            )
                        )
                    )
                ),

                // --------------------------------------
                // Enable cache audit?
                // --------------------------------------

                array(
                    'title' => 'jcogs_img_cp_enable_cache_audit',
                    'desc' => 'jcogs_img_cp_enable_cache_audit_desc',
                    'group' => 'advanced_options',
                    'fields' => array(
                        'img_cp_enable_cache_audit' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_enable_cache_audit'],
                        )
                    )
                ),

                // ----------------------------------------
                // Set default cache audit pause duration
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_default_cache_audit_after',
                    'desc' => 'jcogs_img_cp_default_cache_audit_after_desc',
                    'group' => 'advanced_options',
                    'fields' => array(
                        'img_cp_default_cache_audit_after' => array(
                            'type'  => 'text',
                            'value' => $this->_settings['img_cp_default_cache_audit_after'],
                            'required' => true
                        )
                    )
                ),

                // --------------------------------------------------------
                // Location of JCOGS Licensing server
                // --------------------------------------------------------

                ee('jcogs_img:Licensing')->mcp_licensing_server_domain_entry($this->_settings['jcogs_license_server_domain']),

            )
        );

        $this->_data += array(
            'cp_page_title' => lang('jcogs_img_advanced_settings_heading'),
            'base_url' => ee('CP/URL', 'addons/settings/jcogs_img/advanced_settings')->compile(),
            'save_btn_text' => sprintf(lang('btn_save'), lang('jcogs_img_advanced_settings')),
            'save_btn_text_working' => lang('btn_saving'),
            'sections' => $sections
        );

        // Tell EE to load the custom javascript for the page
        // ee()->cp->load_package_js('form_controls');

        return array(
            'heading'       => lang('jcogs_img_advanced_settings'),
            'breadcrumb'    => array(
                ee('CP/URL', 'addons/settings/jcogs_img/advanced_settings')->compile() => lang('jcogs_img_module_name')
            ),
            'body'          => ee('View')->make('ee:_shared/form')->render($this->_data),
        );
    }

    public function caching()
    {
        $this->_build_sidebar();
        // Cache_info also checks for and runs any GET based cache operations from the control table buttons
        $cache_info = ee('jcogs_img:ImageUtilities')->get_image_cache_info();
        $cache_control_table = ee('jcogs_img:ImageUtilities')->get_image_cache_control_table();

        // --------------------------------------
        // Validate and then save any changes
        // --------------------------------------
        if ($_POST) {

            // Validation
            $validator = ee('Validation')->make();

            // Define custom validation rules
            // ------------------------------

            // 1) Valid Adapter
            // ----------------
            $validator->defineRule('valid_adapter', function ($key, $value, $parameters) {

                // If the option is for a cloud adapter and we cannot setup the adapter then throw an error
                $setting = 'img_cp_flysystem_adapter_' . $value . '_is_valid';
                $result = ee('jcogs_img:ImageUtilities')->create_filesystem_adapter($value, true) ? true : false;
                // Update settings to reflect the adapter status
                $this->_settings[$setting] = $result ? true : false;
                ee('jcogs_img:Settings')->save_settings($this->_settings);
                return $result;
            });

            // 2) Valid (local) cache path
            // -------------------
            $validator->defineRule('valid_cache_path', function ($key, $value, $parameters) {

                // Cache directory needs to be included in URLs, so needs to be valid url element
                // So we need to check for non valid elements (e.g. spaces etc.)

                // First parse the stub given
                $parsed_url = parse_url($value);

                // Check to see that we only got a path value back (that should be only element provided)
                if ((!$parsed_url || count($parsed_url) > 1) && isset($parsed_url['path'])) {
                    // Something seriously wrong with provided value so bale
                    return 'jcogs_img_cp_invalid_url_path';
                }

                // Now strip out any leading '/' or '//' elements
                ee()->load->helper('string');
                $parsed_url['path'] = ltrim(reduce_double_slashes($parsed_url['path']), '/');

                // Now check the path element plus site_url is valid URL
                // filter_var is flaky with non-standard domain names, so if it fails, do parse_url and see if we get at least three components when we do parse_url...
                // scheme, host, path... 
                if (!filter_var(ee()->config->item('base_url') . $parsed_url['path'], FILTER_VALIDATE_URL)) {
                    if (count(parse_url(ee()->config->item('base_url') . $parsed_url['path'])) < 3) {
                        return 'jcogs_img_cp_invalid_url_path';
                    }
                }

                // Now check we can create the directory
                // If we are in process of changing from one adapter to another don't do this ... 
                $current_adapter = $this->_settings['img_cp_flysystem_adapter'];
                $new_adapter = !empty($_POST) && isset($_POST['img_cp_flysystem_adapter']) ? $_POST['img_cp_flysystem_adapter'] : null;
                if($current_adapter === $new_adapter) {
                    return ee('jcogs_img:ImageUtilities')->directoryExists($parsed_url['path'], true);
                } else {
                    return true;
                }
            });

            $validator->setRules(array(
                // 'img_cp_flysystem_adapter_config'           => 'valid_adapter',
                'img_cp_default_cache_directory'            => 'xss|valid_cache_path|required',
                'img_cp_class_always_output_full_urls'      => 'enum[y,n]',
                'img_cp_path_prefix'                        => 'whenPresent|url',
                'img_cp_cache_auto_manage'                  => 'enum[y,n]',
                'img_cp_default_cache_duration'             => 'integer|greaterThan[-2]|required',
            ));

            $result = $validator->validate($_POST);
            $extra_message_line = '';

            if ($result->isValid()) {

                $fields = array();
                // Get all $_POST values, store them in array and save them
                // Use ee input library as it cleans up POST entries on loading
                // Define allowed settings keys (i.e. only accept POST entries that have keys that match keys in the settings array)
                $allowed_settings = array_keys($this->_settings);

                foreach ($_POST as $key => $value) {
                    // Only process known settings
                    if (!in_array($key, $allowed_settings)) {
                        continue;
                    }
                    
                    $fields[$key] = ee()->input->post($key);
                    if ($key == 'img_cp_default_cache_directory') {
                        // Remove double slashes, leading and trailing slash if there is one.
                        ee()->load->helper('string');
                        $fields[$key] = trim(reduce_double_slashes($fields[$key]), '/');
                    }

                    $fields[$key] = is_numeric($fields[$key]) ? (int) $fields[$key] : $fields[$key];
                }
                $fields = array_merge($this->_settings, $fields);

                // Test adapter configuration if a non-local adapter was selected
                $adapter_config_value = $_POST['img_cp_flysystem_adapter_config'];
                $adapter_test_result = null;
                
                if ($adapter_config_value !== 'local') {
                    // Test the selected cloud adapter configuration
                    $adapter_test_result = ee('jcogs_img:ImageUtilities')->create_filesystem_adapter($adapter_config_value, true);
                    
                    // Update the validity setting based on test result
                    $setting_key = 'img_cp_flysystem_adapter_' . $adapter_config_value . '_is_valid';
                    $fields[$setting_key] = $adapter_test_result ? 'true' : 'false';

                    // Update the value of the adapter secret based on current settings (it was changed possibly by create_filesystem_adapter)
                    // First update settings with the latest values
                    $this->_settings = ee('jcogs_img:Settings')::$settings;
                    $fields['img_cp_flysystem_adapter_' . $adapter_config_value . '_secret_actual'] = $this->_settings['img_cp_flysystem_adapter_' . $adapter_config_value . '_secret_actual'];
                }

                // Now save the settings values (including updated validity status for cloud adapters)
                ee('jcogs_img:Settings')->save_settings($fields);

                // Determine success/warning message based on adapter test results
                if (
                    $adapter_config_value === 'local' ||
                    ($fields[$setting_key] === true || $fields[$setting_key] === 'true')
                ) {
                    // Local adapter or valid cloud adapter - standard/enhanced success message
                    $message_body = lang('preferences_updated_desc');
                    if ($adapter_config_value !== 'local') {
                        $adapter_name = lang('jcogs_img_cp_flysystem_' . $adapter_config_value . '_adapter');
                        $message_body .= sprintf(' The %s configuration has been validated and is working correctly.', $adapter_name);
                    }
                    
                    ee('CP/Alert')->makeInline('shared-form')
                        ->asSuccess()
                        ->withTitle(lang('preferences_updated'))
                        ->addToBody($message_body)
                        ->addToBody($extra_message_line)
                        ->defer();
                        
                } else {
                    // Cloud adapter configuration is invalid - warning message but settings still saved
                    $adapter_name = lang('jcogs_img_cp_flysystem_' . $adapter_config_value . '_adapter');
                    ee('CP/Alert')->makeInline('shared-form')
                        ->asWarning()
                        ->withTitle(lang('preferences_updated'))
                        ->addToBody(lang('preferences_updated_desc'))
                        ->addToBody(sprintf('Warning: The %s configuration could not be validated and cannot be used. Please check your credentials and settings.', $adapter_name))
                        ->addToBody($extra_message_line)
                        ->defer();
                }

                // Redraw page now
                ee()->functions->redirect(ee('CP/URL', 'addons/settings/jcogs_img/caching')->compile());
                
            } else {
                $this->_data['errors'] = $result;
                ee('CP/Alert')->makeInline('shared-form')
                    ->asIssue()
                    ->withTitle(lang('settings_save_error'))
                    ->addToBody(lang('settings_save_error_desc'))
                    ->addToBody($extra_message_line)
                    ->now();
            }
        }

        // No post data, so just draw the page

        // Get the cache_audit info
        // Do we have a marker from last audit?
        $marker = ee('jcogs_img:Utilities')->cache_utility('get', '/' . JCOGS_IMG_CLASS . '/' . 'image_cache_audit');
        $last_audit = $marker ? $marker : 0;

        // When is next audit due?
        $next_audit = $last_audit + $this->_settings['img_cp_default_cache_audit_after'];

        // Build next audit message / button combo
        $cache_audit_button_block = '';

        // --------------------------------------
        // Build the form into $sections array
        // --------------------------------------

        $sections = array();

        $sections['jcogs_img_cp_cache_status'] = array(
            'group' => 'cache_status',
            'settings' => array(

                // ----------------------------------------
                // Caching Status
                // ----------------------------------------

                array_key_exists('cache_performance_desc',$cache_info) ? $cache_info['cache_performance_desc'] : '',

            )
        );

        $sections['jcogs_img_cp_cache_controls'] = array(
            'group' => 'cache_controls',
            'settings' => array(
    
                // ----------------------------------------
                // Set working cache location
                // ----------------------------------------
                array(
                    'title' => 'jcogs_img_cp_choose_flysystem_adapter',
                    'desc' => 'jcogs_img_cp_choose_flysystem_adapter_desc',
                    'fields' => array(
                        'img_cp_flysystem_adapter' => array(
                            'type' => 'select',
                            'choices' => $this->_get_valid_flysystem_adapters(),
                            'value' => $this->_settings['img_cp_flysystem_adapter'],
                        )
                    )
                ),

                // ----------------------------------------
                // Add in cache control table
                // ----------------------------------------

                // $cache_audit_button_block,
                $cache_control_table
            )
        );

        $sections['jcogs_img_cp_cache_settings'] = array(
            'group' => 'cache_settings',
            'settings' => array(
                
                // ----------------------------------------
                // Set default cache duration
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_default_cache_duration',
                    'desc' => 'jcogs_img_cp_default_cache_duration_desc',
                    'fields' => array(
                        'img_cp_default_cache_duration' => array(
                            'type'  => 'text',
                            'value' => $this->_settings['img_cp_default_cache_duration'],
                            'preload' => lang('jcogs_img_cp_default_cache_duration_minus_one_option'),
                        )
                    )
                ),

                // --------------------------------------
                // Enable cache auto-manage?
                // --------------------------------------

                array(
                    'title' => 'jcogs_img_cp_enable_cache_auto_manage',
                    'desc' => 'jcogs_img_cp_enable_cache_auto_manage_desc',
                    'fields' => array(
                        'img_cp_cache_auto_manage' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_cache_auto_manage']
                        )
                    )
                )
            )
        );

        $sections['jcogs_img_cp_cache_file_system'] = array(
            'group' => 'cache_flysystem',
            'settings' => array(
        
                // --------------------------------------
                // Choose Flysystem adaptor
                // --------------------------------------

                array(
                    'title' => 'jcogs_img_cp_choose_flysystem_adapter',
                    'desc' => 'jcogs_img_cp_choose_flysystem_adapter_desc',
                    'fields' => array(
                        'img_cp_flysystem_adapter_config' => array(
                            'type' => 'select',
                            'choices' => array(
                                'local'     => lang('jcogs_img_cp_flysystem_local_adapter'),
                                's3'        => lang('jcogs_img_cp_flysystem_s3_adapter'),
                                'r2'        => lang('jcogs_img_cp_flysystem_r2_adapter'),
                                'dospaces'  => lang('jcogs_img_cp_flysystem_dospaces_adapter')
                            ),
                            'value' => $this->_settings['img_cp_flysystem_adapter_config'],
                            'group_toggle' => array(
                                'local'    => 'jcogs_img_cp_flysystem_local_adapter',
                                's3'       => 'jcogs_img_cp_flysystem_s3_adapter',
                                'r2'       => 'jcogs_img_cp_flysystem_r2_adapter',
                                'dospaces' => 'jcogs_img_cp_flysystem_dospaces_adapter'
                            )
                        )
                    )
                ),

                // ----------------------------------------
                // Local adaptor: set default cache directory
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_default_cache_directory',
                    'desc' => 'jcogs_img_cp_default_cache_directory_desc',
                    'group' => 'jcogs_img_cp_flysystem_local_adapter',
                    'fields' => array(
                        'img_cp_default_cache_directory' => array(
                            'type'  => 'text',
                            'value' => ee('jcogs_img:ImageUtilities')::$adapter_name == 'local' ? trim('/' . $this->cache_path,'/') : $this->_settings['img_cp_default_cache_directory'],
                            'preload' => lang('jcogs_img_cp_default_cache_directory_placeholder'),
                            'required' => true
                        )
                    )
                ),

                // ----------------------------------------
                // Local adaptor: Always output full URLs
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_class_always_output_full_urls',
                    'desc' => 'jcogs_img_cp_class_always_output_full_urls_desc',
                    'group' => 'jcogs_img_cp_flysystem_local_adapter',
                    'fields' => array(
                        'img_cp_class_always_output_full_urls' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_class_always_output_full_urls'],
                        )
                    )
                ),

                // ----------------------------------------
                // Local adaptor: Set CDN remote path prefix
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_path_prefix',
                    'desc' => 'jcogs_img_cp_path_prefix_desc',
                    'group' => 'jcogs_img_cp_flysystem_local_adapter',
                    'fields' => array(
                        'img_cp_path_prefix' => array(
                            'type'  => 'text',
                            'value' => trim($this->_settings['img_cp_path_prefix']),
                            'required' => false
                        )
                    )
                ),

                // ----------------------------------------
                // S3 adaptor: status display
                // ----------------------------------------

                [
                    'title' => 'S3 Status',
                    'desc' => 'Current validation status for AWS S3 configuration',
                    'group' => 'jcogs_img_cp_flysystem_s3_adapter',
                    'fields' => [
                        's3_status_display' => [
                            'type' => 'html',
                            'content' => ($this->_settings['img_cp_flysystem_adapter_s3_is_valid'] ?? false) === 'true' 
                                ? lang('jcogs_img_cp_cloud_adapter_config_valid')
                                : lang('jcogs_img_cp_cloud_adapter_config_not_valid')
                        ]
                    ]
                ],

                // ----------------------------------------
                // S3 adaptor: set default cache directory
                // ----------------------------------------

                [
                    'title' => 'Key',
                    'desc' => 'Enter your AWS S3 Key',
                    'group' => 'jcogs_img_cp_flysystem_s3_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_s3_key' => [
                            'type' => 'text',
                            'value' => $this->_settings['img_cp_flysystem_adapter_s3_key'] ?? '',
                            'required' => false
                        ]
                    ]
                ],
                [
                    'title' => 'Secret',
                    'desc' => 'Enter your AWS S3 Secret',
                    'group' => 'jcogs_img_cp_flysystem_s3_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_s3_secret' => [
                            'type' => 'text',
                            'value' => $this->_settings['img_cp_flysystem_adapter_s3_secret'] ?? '',
                            'required' => false
                        ]
                    ]
                ],
                [
                    'title' => 'Region',
                    'desc' => 'Select the region for your AWS S3 Bucket',
                    'group' => 'jcogs_img_cp_flysystem_s3_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_s3_region' => [
                            'type' => 'dropdown',
                            'choices' => $this->_listAvailableS3Regions(),
                            'value' => $this->_settings['img_cp_flysystem_adapter_s3_region'] ?? '',
                            'required' => false
                        ]
                    ]
                ],
                [
                    'title' => 'Bucket Name',
                    'desc' => 'Enter the name of your AWS S3 Bucket',
                    'group' => 'jcogs_img_cp_flysystem_s3_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_s3_bucket' => [
                            'type' => 'text',
                            'value' => $this->_settings['img_cp_flysystem_adapter_s3_bucket'] ?? '',
                            'required' => false
                        ]
                    ]
                ],
                [
                    'title' => 'Path',
                    'desc' => 'Enter the path inside your AWS S3 Bucket',
                    'group' => 'jcogs_img_cp_flysystem_s3_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_s3_server_path' => [
                            'type' => 'text',
                            'value' => $this->_settings['img_cp_flysystem_adapter_s3_server_path'] ?? '',
                            'required' => false
                        ]
                    ]
                ],
                [
                    'title' => 'Url',
                    'desc' => 'Enter the url used to access your AWS S3 Bucket',
                    'group' => 'jcogs_img_cp_flysystem_s3_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_s3_url' => [
                            'type' => 'text',
                            'value' => $this->_settings['img_cp_flysystem_adapter_s3_url'] ?? '',
                            'required' => false
                        ]
                    ]
                ],

                // ----------------------------------------
                // R2 adaptor: status display
                // ----------------------------------------

                [
                    'title' => 'R2 Status',
                    'desc' => 'Current validation status for Cloudflare R2 configuration',
                    'group' => 'jcogs_img_cp_flysystem_r2_adapter',
                    'fields' => [
                        'r2_status_display' => [
                            'type' => 'html',
                            'content' => ($this->_settings['img_cp_flysystem_adapter_r2_is_valid'] ?? false) === 'true' 
                                ? lang('jcogs_img_cp_cloud_adapter_config_valid')
                                : lang('jcogs_img_cp_cloud_adapter_config_not_valid')
                        ]
                    ]
                ],

                // ----------------------------------------
                // R2 adaptor: account_id
                // ----------------------------------------
                
                [
                    'title' => 'Account ID',
                    'desc' => 'Enter your Cloudflare R2 Account ID',
                    'group' => 'jcogs_img_cp_flysystem_r2_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_r2_account_id' => [
                            'type' => 'text',
                            'value' => $this->_settings['img_cp_flysystem_adapter_r2_account_id'] ?? '',
                            'required' => false
                        ]
                    ]
                ],
                [
                    'title' => 'Key',
                    'desc' => 'Enter your Cloudflare R2 Key',
                    'group' => 'jcogs_img_cp_flysystem_r2_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_r2_key' => [
                            'type' => 'text',
                            'value' => $this->_settings['img_cp_flysystem_adapter_r2_key'] ?? '',
                            'required' => false
                        ]
                    ]
                ],
                [
                    'title' => 'Secret',
                    'desc' => 'Enter your Cloudflare R2 Secret',
                    'group' => 'jcogs_img_cp_flysystem_r2_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_r2_secret' => [
                            'type' => 'text',
                            'value' => $this->_settings['img_cp_flysystem_adapter_r2_secret'] ?? '',
                            'required' => false
                        ]
                    ]
                ],
                [
                    'title' => 'Bucket Name',
                    'desc' => 'Enter the name of your Cloudflare R2 Bucket',
                    'group' => 'jcogs_img_cp_flysystem_r2_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_r2_bucket' => [
                            'type' => 'text',
                            'value' => $this->_settings['img_cp_flysystem_adapter_r2_bucket'] ?? '',
                            'required' => false
                        ]
                    ]
                ],
                [
                    'title' => 'Path',
                    'desc' => 'Enter the path inside your Cloudflare R2 Bucket',
                    'group' => 'jcogs_img_cp_flysystem_r2_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_r2_server_path' => [
                            'type' => 'text',
                            'value' => $this->_settings['img_cp_flysystem_adapter_r2_server_path'] ?? '',
                            'required' => false
                        ]
                    ]
                ],
                [
                    'title' => 'Url',
                    'desc' => 'Enter the url used to access your Cloudflare R2 Bucket',
                    'group' => 'jcogs_img_cp_flysystem_r2_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_r2_url' => [
                            'type' => 'text',
                            'value' => $this->_settings['img_cp_flysystem_adapter_r2_url'] ?? '',
                            'required' => false
                        ]
                    ]
                ],

                // ----------------------------------------
                // DOSPACES adaptor: status display
                // ----------------------------------------

                [
                    'title' => 'DigitalOcean Spaces Status',
                    'desc' => 'Current validation status for DigitalOcean Spaces configuration',
                    'group' => 'jcogs_img_cp_flysystem_dospaces_adapter',
                    'fields' => [
                        'dospaces_status_display' => [
                            'type' => 'html',
                            'content' => ($this->_settings['img_cp_flysystem_adapter_dospaces_is_valid'] ?? false) === 'true' 
                                ? lang('jcogs_img_cp_cloud_adapter_config_valid')
                                : lang('jcogs_img_cp_cloud_adapter_config_not_valid')
                        ]
                    ]
                ],

                // ----------------------------------------
                // DOSPACES adaptor: Key ID
                // ----------------------------------------

                [
                    'title' => 'Key',
                    'desc' => 'Enter your DigitalOcean Key',
                    'group' => 'jcogs_img_cp_flysystem_dospaces_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_dospaces_key' => [
                            'type' => 'text',
                            'value' => $this->_settings['img_cp_flysystem_adapter_dospaces_key'] ?? '',
                            'required' => false
                        ]
                    ]
                ],
                [
                    'title' => 'Secret',
                    'desc' => 'Enter your DigitalOcean Secret',
                    'group' => 'jcogs_img_cp_flysystem_dospaces_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_dospaces_secret' => [
                            'type' => 'text',
                            'value' => $this->_settings['img_cp_flysystem_adapter_dospaces_secret'] ?? '',
                            'required' => false
                        ]
                    ]
                ],
                [
                    'title' => 'Region',
                    'desc' => 'Select the region for your DigitalOcean Space',
                    'group' => 'jcogs_img_cp_flysystem_dospaces_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_dospaces_region' => [
                            'type' => 'dropdown',
                            'choices' => $this->_listAvailableDOSpacesRegions(),
                            'value' => $this->_settings['img_cp_flysystem_adapter_dospaces_region'] ?? '',
                            'required' => false
                        ]
                    ]
                ],
                [
                    'title' => 'Space Name',
                    'desc' => 'Enter the name of your DigitalOcean Space',
                    'group' => 'jcogs_img_cp_flysystem_dospaces_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_dospaces_space' => [
                            'type' => 'text',
                            'value' => $this->_settings['img_cp_flysystem_adapter_dospaces_space'] ?? '',
                            'required' => false
                        ]
                    ]
                ],
                [
                    'title' => 'Path',
                    'desc' => 'Enter the path inside your DigitalOcean Space',
                    'group' => 'jcogs_img_cp_flysystem_dospaces_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_dospaces_server_path' => [
                            'type' => 'text',
                            'value' => $this->_settings['img_cp_flysystem_adapter_dospaces_server_path'] ?? '',
                            'required' => false
                        ]
                    ]
                ],
                [
                    'title' => 'Url',
                    'desc' => 'Enter the url used to access your DigitalOcean Space',
                    'group' => 'jcogs_img_cp_flysystem_dospaces_adapter',
                    'fields' => [
                        'img_cp_flysystem_adapter_dospaces_url' => [
                            'type' => 'text',
                            'value' => $this->_settings['img_cp_flysystem_adapter_dospaces_url'] ?? '',
                            'required' => false
                        ]
                    ]
                ]
            )
        );

        $this->_data += array(
            'cp_page_title' => lang('jcogs_img_caching_page_title'),
            'base_url' => ee('CP/URL', 'addons/settings/jcogs_img/caching')->compile(),
            'save_btn_text' => sprintf(lang('btn_save'), lang('jcogs_img_cp_cache_settings')),
            'save_btn_text_working' => lang('btn_saving'),
            'sections' => $sections
        );

        return array(
            'heading'       => lang('jcogs_img_cp_cache_settings'),
            'breadcrumb'    => array(
                ee('CP/URL', 'addons/settings/jcogs_img/caching')->compile() => lang('jcogs_img_cp_caching_sidebar_label')
            ),
            'body'          => ee('View')->make('ee:_shared/form')->render($this->_data),
        );
    }

    public function image_defaults()
    {
        ee()->load->library('file_field');
        $this->_build_sidebar();
        ee()->cp->add_js_script(array(
            'file' => array('cp/form_group'),
        ));

        // Build list of valid image formats for this server
        if (!$valid_server_image_options = ee('jcogs_img:Utilities')->cache_utility('get', '/' . JCOGS_IMG_CLASS . '/' . 'server_image_format_options')) {
            if (extension_loaded('gd')) {
                $server_gd_info = gd_info();
                // Work out what capabilities we have... 
                $valid_server_image_options = ['source' => 'Use format of source image',];
                foreach ($server_gd_info as $key => $value) {
                    if (!in_array(strtolower(substr($key, 0, 2)), ['gd', 'fr', 'ji'])) {
                        $this_capability = explode(' ', $key);
                        if ($value === true && strtolower($this_capability[1]) != 'read') {
                            $this_capability[0] = strtolower($this_capability[0]) != 'jpeg' ? $this_capability[0] : 'JPG';
                            $valid_server_image_options[strtolower($this_capability[0])] = $this_capability[0];
                        }
                    }
                }
            } else {
                $valid_server_image_options = ['source' => 'GD Library not found',];
            }
            ee('jcogs_img:Utilities')->cache_utility('save', JCOGS_IMG_CLASS . '/' . 'server_image_format_options', $valid_server_image_options, 60 * 60 * 24);
        }

        // --------------------------------------
        // Validate and then save any changes
        // --------------------------------------
        if ($_POST) {

            // Validation
            $validator = ee('Validation')->make();

            // Set validation rules
            // --------------------
            $validator->setRules(array(
                'img_cp_default_max_image_dimension' => 'integer|greaterThan[-1]|required',
                'img_cp_default_img_width' => 'integer|greaterThan[-1]|required',
                'img_cp_default_img_height' => 'integer|greaterThan[-1]|required',
                'img_cp_default_max_image_size' => 'integer|greaterThan[0]|required'
            ));

            // Do the validation
            // -----------------
            $result = $validator->validate($_POST);

            if ($result->isValid()) {

                $fields = array();
                // Get all $_POST values, store them in array and save them
                // Use ee input library as it cleans up POST entries on loading
                // Define allowed settings keys
                $allowed_settings = array_keys($this->_settings);

                foreach ($_POST as $key => $value) {
                    // Only process known settings
                    if (!in_array($key, $allowed_settings)) {
                        continue;
                    }
                    
                    $fields[$key] = ee()->input->post($key);
                    $fields[$key] = is_numeric($fields[$key]) ? (int) $fields[$key] : $fields[$key];
                }
                $fields = array_merge($this->_settings, $fields);

                // Now save the settings values
                ee('jcogs_img:Settings')->save_settings($fields);

                // Pop up a save confirmation if all went well.
                ee('CP/Alert')->makeInline('shared-form')
                    ->asSuccess()
                    ->withTitle(lang('preferences_updated'))
                    ->addToBody(lang('preferences_updated_desc'))
                    ->defer();

                // Redraw page now
                ee()->functions->redirect(ee('CP/URL', 'addons/settings/jcogs_img/image_defaults')->compile());
            } else {
                $this->_data['errors'] = $result;
                ee('CP/Alert')->makeInline('shared-form')
                    ->asIssue()
                    ->withTitle(lang('settings_save_error'))
                    ->addToBody(lang('settings_save_error_desc'))
                    ->now();
            }
        }

        // No post data, so just draw the page

        // --------------------------------------
        // Build the form into $sections array
        // --------------------------------------

        $sections[lang('jcogs_img_image_options_format_section')] = array(
            'group' => 'img_options',
            'settings' => array(

                // ----------------------------------------
                // Set default image format
                // ----------------------------------------

                array(
                    'title' => 'img_cp_default_image_format',
                    'desc' => 'img_cp_default_image_format_desc',
                    'fields' => array(
                        'img_cp_default_image_format' => array(
                            'type' => 'select',
                            'choices' => $valid_server_image_options,
                            'value' => $this->_settings['img_cp_default_image_format'],
                        )
                    )
                ),

                // ----------------------------------------
                // Set default image quality for jpgs
                // ----------------------------------------

                array(
                    'title' => 'img_cp_jpg_default_quality',
                    'desc' => 'img_cp_jpg_default_quality_desc',
                    'fields' => array(
                        'img_cp_jpg_default_quality' => array(
                            'type'  => 'html',
                            'content' => '<div style=\'display:flex;align-items:center;\'><div style=\'padding-right:0.5em;\'>0</div><input type=\'range\' min=\'0\' max=\'100\' step=\'1\' id=\'img_cp_jpg_default_quality\' name=\'img_cp_jpg_default_quality\' value=\'' . $this->_settings['img_cp_jpg_default_quality'] . '\' style=\'max-width:30vw;\' required ><div style=\'padding-left:0.5em;padding-right:0.5em;\'>100 - Current value: </div><div name=\'jcogs_dqs\'>' . $this->_settings['img_cp_jpg_default_quality'] . '</div></div>'
                        )
                    )
                ),

                // ----------------------------------------
                // Set default image quality for pngs
                // ----------------------------------------

                array(
                    'title' => 'img_cp_png_default_quality',
                    'desc' => 'img_cp_png_default_quality_desc',
                    'fields' => array(
                        'img_cp_png_default_quality' => array(
                            'type'  => 'html',
                            'content' => '<div style=\'display:flex;align-items:center;\'><div style=\'padding-right:0.5em;\'>0</div><input type=\'range\' min=\'0\' max=\'9\' step=\'1\' id=\'img_cp_png_default_quality\' name=\'img_cp_png_default_quality\' value=\'' . $this->_settings['img_cp_png_default_quality'] . '\' style=\'max-width:30vw;\' required ><div style=\'padding-left:0.5em;padding-right:0.5em;\'>9 - Current value: </div><div name=\'jcogs_dpqs\'>' . $this->_settings['img_cp_png_default_quality'] . '</div></div>'
                        )
                    )
                ),

                // ----------------------------------------
                // Set default background colour
                // ----------------------------------------

                array(
                    'title' => 'img_cp_default_bg_color',
                    'desc' => 'img_cp_default_bg_color_desc',
                    'fields' => array(
                        'img_cp_default_bg_color' => array(
                            'type'  => 'html',
                            'content' => '<input type=\'color\' id=\'img_cp_default_bg_color\' name=\'img_cp_default_bg_color\' value=\'' . $this->_settings['img_cp_default_bg_color'] . '\'>'
                        )
                    )
                )
            )
        );

        $sections[lang('jcogs_img_image_operational_defaults')] = array(
            'group' => 'img_options',
            'settings' => array(


                // ----------------------------------------
                // Set default image width
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_default_img_width',
                    'desc' => 'jcogs_img_cp_default_img_width_desc',
                    'group' => 'svg_options',
                    'fields' => array(
                        'img_cp_default_img_width' => array(
                            'type'  => 'text',
                            'value' => $this->_settings['img_cp_default_img_width'],
                            'required' => true
                        )
                    )
                ),

                // ----------------------------------------
                // Set default image height
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_default_img_height',
                    'desc' => 'jcogs_img_cp_default_img_height_desc',
                    'group' => 'svg_options',
                    'fields' => array(
                        'img_cp_default_img_height' => array(
                            'type'  => 'text',
                            'value' => $this->_settings['img_cp_default_img_height'],
                            'required' => true
                        )
                    )
                ),

                // --------------------------------------
                // Enable SVG passthrough option?
                // --------------------------------------

                array(
                    'title' => 'jcogs_img_cp_enable_svg_passthrough',
                    'desc' => 'jcogs_img_cp_enable_svg_passthrough_desc',
                    'fields' => array(
                        'img_cp_enable_svg_passthrough' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_enable_svg_passthrough'],
                            'group_toggle' => array(
                                'y' => 'svg_options'
                            )
                        )
                    )
                ),

                // ------------------------------------------
                // Enable animated gif passthrough dominance?
                // ------------------------------------------

                array(
                    'title' => 'jcogs_img_cp_ignore_save_type_for_animated_gifs',
                    'desc' => 'jcogs_img_cp_ignore_save_type_for_animated_gifs_desc',
                    'fields' => array(
                        'img_cp_ignore_save_type_for_animated_gifs' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_ignore_save_type_for_animated_gifs']
                        )
                    )
                ),

                // --------------------------------------
                // Set allow_scale_larger as default option?
                // --------------------------------------

                array(
                    'title' => 'jcogs_img_cp_allow_scale_larger_default',
                    'desc' => 'jcogs_img_cp_allow_scale_larger_default_desc',
                    'fields' => array(
                        'img_cp_allow_scale_larger_default' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_allow_scale_larger_default'],
                        )
                    )
                ),

                // --------------------------------------
                // Enable auto_sharpen as default option?
                // --------------------------------------

                array(
                    'title' => 'jcogs_img_cp_enable_auto_sharpen',
                    'desc' => 'jcogs_img_cp_enable_auto_sharpen_desc',
                    'fields' => array(
                        'img_cp_enable_auto_sharpen' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_enable_auto_sharpen'],
                        )
                    )
                ),

                // --------------------------------------
                // Enable adding of decoding attribute to output tag?
                // --------------------------------------

                array(
                    'title' => 'jcogs_img_cp_html_decoding_enabled',
                    'desc' => 'jcogs_img_cp_html_decoding_enabled_desc',
                    'fields' => array(
                        'img_cp_html_decoding_enabled' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_html_decoding_enabled'],
                        )
                    )
                ),

                // --------------------------------------
                // Enable lazy loading as default option?
                // --------------------------------------

                array(
                    'title' => 'jcogs_img_cp_enable_lazy_loading',
                    'desc' => 'jcogs_img_cp_enable_lazy_loading_desc',
                    'fields' => array(
                        'img_cp_enable_lazy_loading' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_enable_lazy_loading'],
                            'group_toggle' => array(
                                'y' => 'jcogs_img_cp_lazy_loading_mode'
                            )
                        )
                    )
                ),

                // ----------------------------------------
                // Set default lazy loading mode
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_lazy_loading_mode',
                    'desc' => 'jcogs_img_cp_lazy_loading_mode_desc',
                    'group' => 'jcogs_img_cp_lazy_loading_mode',
                    'fields' => array(
                        'img_cp_lazy_loading_mode' => array(
                            'type' => 'select',
                            'choices' => array(
                                'lqip'  => lang('jcogs_img_cp_lazy_loading_mode_lqip'),
                                'dominant_color' => lang('jcogs_img_cp_lazy_loading_mode_dominant_color'),
                                'js_lqip'  => lang('jcogs_img_cp_lazy_loading_mode_js_lqip'),
                                'js_dominant_color' => lang('jcogs_img_cp_lazy_loading_mode_js_dominant_color'),
                                'html5' => lang('jcogs_img_cp_lazy_loading_mode_html5'),
                            ),
                            'value' => $this->_settings['img_cp_lazy_loading_mode'],
                            'required' => true
                        )
                    )
                ),

                // -----------------------------------------------------
                // Enable progressive enhancement mode for lazy loading?
                // -----------------------------------------------------

                array(
                    'title' => 'jcogs_img_cp_lazy_progressive_enhancement',
                    'desc' => 'jcogs_img_cp_lazy_progressive_enhancement_desc',
                    'group' => 'jcogs_img_cp_lazy_loading_mode',
                    'fields' => array(
                        'img_cp_lazy_progressive_enhancement' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_lazy_progressive_enhancement']
                        )
                    )
                ),

                // --------------------------------------
                // Enable default fallback image?
                // --------------------------------------

                array(
                    'title' => 'jcogs_img_cp_enable_default_fallback_image',
                    'desc' => 'jcogs_img_cp_enable_default_fallback_image_desc',
                    'fields' => array(
                        'img_cp_enable_default_fallback_image' => array(
                            'type' => 'select',
                            'choices' => array(
                                'n'  => lang('jcogs_img_cp_no_fallback_image_option'),
                                'yc' => lang('jcogs_img_cp_local_fallback_colour_option'),
                                'yl' => lang('jcogs_img_cp_local_fallback_image_option'),
                                'yr' => lang('jcogs_img_cp_remote_fallback_image_option')
                            ),
                            'value' => $this->_settings['img_cp_enable_default_fallback_image'],
                            'group_toggle' => array(
                                'yc' => 'jcogs_img_cp_fallback_image_colour',
                                'yl' => 'jcogs_img_cp_fallback_image_local',
                                'yr' => 'jcogs_img_cp_fallback_image_remote'
                            )
                        )
                    )
                ),

                // ----------------------------------------
                // Set default fallback colour fill
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_fallback_image_colour',
                    'group' => 'jcogs_img_cp_fallback_image_colour',
                    'desc' => 'jcogs_img_cp_fallback_image_colour_desc',
                    'fields' => array(
                        'img_cp_fallback_image_colour' => array(
                            'type'  => 'html',
                            'content' => '<input type=\'color\' id=\'img_cp_fallback_image_colour\' name=\'img_cp_fallback_image_colour\' value=\'' . $this->_settings['img_cp_fallback_image_colour'] . '\'>'
                        )
                    )
                ),

                // ----------------------------------------
                // Set default fallback local image
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_fallback_image_local',
                    'group' => 'jcogs_img_cp_fallback_image_local',
                    'desc' => 'jcogs_img_cp_fallback_image_local_desc',
                    'fields' => array(
                        'img_cp_fallback_image_local' => [
                            'type' => 'html',
                            'value' => $this->_settings['img_cp_fallback_image_local'],
                            'required' => false,
                            'content' => ee()->file_field->dragAndDropField('img_cp_fallback_image_local', $this->_settings['img_cp_fallback_image_local'], 'all', 'image'),
                        ]
                    )
                ),

                // ----------------------------------------
                // Set default fallback remote image
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_fallback_image_remote',
                    'group' => 'jcogs_img_cp_fallback_image_remote',
                    'desc' => 'jcogs_img_cp_fallback_image_remote_desc',
                    'fields' => array(
                        'img_cp_fallback_image_remote' => array(
                            'type'  => 'text',
                            'maxlength' => 255,
                            'value' => $this->_settings['img_cp_fallback_image_remote'],
                            'preload' => lang('jcogs_img_cp_fallback_image_remote_required'),
                            'required' => true,
                        )
                    )
                ),
            )
        );

        $sections[lang('jcogs_img_system_options_limits')] = array(
            'group' => 'img_options',
            'settings' => array(

                // --------------------------------------
                // Enable image Auto-adjust?
                // --------------------------------------

                array(
                    'title' => 'jcogs_img_cp_enable_auto_adjust',
                    'desc' => 'jcogs_img_cp_enable_auto_adjust_desc',
                    'fields' => array(
                        'img_cp_enable_auto_adjust' => array(
                            'type'  => 'yes_no',
                            'value' => $this->_settings['img_cp_enable_auto_adjust'],
                            'group_toggle' => array(
                                'y' => 'jcogs_img_cp_auto_adjust_mode'
                            )
                        )
                    )
                ),

                // ----------------------------------------
                // Set maximum image dimensions
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_default_max_image_dimension',
                    'group' => 'jcogs_img_cp_auto_adjust_mode',
                    'desc' => 'jcogs_img_cp_default_max_image_dimension_desc',
                    'fields' => array(
                        'img_cp_default_max_image_dimension' => array(
                            'type'  => 'text',
                            'value' => $this->_settings['img_cp_default_max_image_dimension'],
                            'required' => true
                        )
                    )
                ),


                // ----------------------------------------
                // Set maximum image size
                // ----------------------------------------

                array(
                    'title' => 'jcogs_img_cp_default_max_image_size',
                    'desc' => 'jcogs_img_cp_default_max_image_size_desc',
                    'fields' => array(
                        'img_cp_default_max_image_size' => array(
                            'type'  => 'text',
                            'value' => $this->_settings['img_cp_default_max_image_size'],
                            'required' => true
                        )
                    )
                ),
            )
        );

        $this->_data += array(
            'cp_page_title' => lang('jcogs_img_image_options'),
            'base_url' => ee('CP/URL', 'addons/settings/jcogs_img/image_defaults')->compile(),
            'save_btn_text' => sprintf(lang('btn_save'), lang('jcogs_img_cp_image_settings')),
            'save_btn_text_working' => lang('btn_saving'),
            'sections' => $sections
        );

        // Tell EE to load the custom javascript for the page
        ee()->cp->load_package_js('form_controls');

        return array(
            'heading'       => lang('img_image_settings'),
            'breadcrumb'    => array(
                ee('CP/URL', 'addons/settings/jcogs_img/image_defaults')->compile() => lang('jcogs_img_cp_image_settings')
            ),
            'body'          => ee('View')->make('ee:_shared/form')->render($this->_data),
        );
    }

    public function license()
    {
        $this->_build_sidebar();

        // --------------------------------------
        // Validate and then save any changes
        // --------------------------------------
        if ($_POST) {

            // Validation
            $validator = ee('Validation')->make();

            // Define custom validation rules
            // ------------------------------

            // 1) Valid License Key Format
            // ---------------------------
            $validator->defineRule('valid_license_key_format', function ($key, $value, $parameters) {
                // Assume license is valid format if still obscured
                if (trim($value) == ee('jcogs_img:Utilities')->obscure_key($this->_settings['jcogs_license_key'])) {
                    return true;
                }

                // So an actual license - regex only works if license pattern is correct.
                if (!preg_match("/^([a-z0-9]{8})-([a-z0-9]{4})-([a-z0-9]{4})-([a-z0-9]{4})-([a-z0-9]{12})$/", trim($value))) {
                    return 'jcogs_lic_cp_invalid_license_key_format';
                }
                return true;
            });

            // 2) Valid License
            // ----------------
            $validator->defineRule('valid_license', function ($key, $value, $parameters) {
                // Only check with licensing server if key is in right format 
                // and we have an email address
                // License key format is valid
                // Do we have a license key email to use?
                $this->license_key_email =  ee()->input->post('jcogs_license_key_email') ?: $this->_settings['jcogs_license_key_email'];
                if (!$this->license_key_email) {
                    return 'jcogs_lic_cp_missing_license_key_email';
                }
                // Do we need to validate the license?
                // If we still have the obscured placeholder value we're OK
                if (trim($value) == ee('jcogs_img:Utilities')->obscure_key($this->_settings['jcogs_license_key'])) {
                    return true;
                }
                // Otherwise go validate
                $this->license_status = ee('jcogs_img:Licensing')->license_status($value, $this->license_key_email, true);
                $this->_settings['jcogs_license_mode'] = $this->license_status->status;
                if ($this->license_status->message == 'jcogs_lic_cp_unable_to_reach_licensing_server') {
                    // But license server not reachable so continue with previous license status!
                    return 'jcogs_lic_cp_unable_to_reach_licensing_server';
                }
                if (str_contains($this->license_status->message, 'the license key given') > 0) {
                    // The email given is not valid for this license!
                    return 'jcogs_lic_cp_because_invalid_license_key_email';
                }
                if ($this->license_status->status == 'invalid' || $this->license_status->status == 'demo') {
                    // But license is not valid!
                    return 'jcogs_lic_cp_invalid_license';
                }
                return true;
            });

            // 3) Valid License Email
            // ----------------------
            $validator->defineRule('valid_license_email', function ($key, $value, $parameters) {
                $this->license_key_email =  ee()->input->post('jcogs_license_key_email');
                if (!$this->license_key_email || !filter_var($this->license_key_email, FILTER_VALIDATE_EMAIL)) {
                    // invalid emailaddress
                    return 'jcogs_lic_cp_invalid_license_key_email';
                }
                $this->license_key_email = $this->license_key_email ?: $this->_settings['jcogs_license_key_email'];
                // Only works if license is not invalid.
                $email_status = ee('jcogs_img:Licensing')->validate_license_email($this->license_key_email);
                if (!(is_object($email_status) && $email_status->status == 'valid')) {
                    return 'jcogs_lic_cp_invalid_license_key_email';
                }
                return true;
            });

            // 4) Licensed or Local
            // --------------------
            $validator->defineRule('license_not_invalid', function ($key, $value, $parameters) {
                // Only works if license is not invalid.
                if ($this->_settings['jcogs_license_mode'] == 'invalid') {
                    return 'jcogs_lic_cp_invalid_license';
                }
                return true;
            });

            // 5) Valid staging server
            // -----------------------
            $validator->defineRule('valid_staging_domain', function ($key, $value, $parameters) {
                // // Find out if we can poll the licensing server... 
                // if (($this->_settings['jcogs_license_mode'] == 'magic' || $this->_settings['jcogs_license_mode'] == 'valid') && isset($value)) {
                //     $path_to_check = count(parse_url($value)) > 1 ? parse_url($value)['host'] : parse_url($value)['path'];
                //     $result = ee('jcogs_img:Utilities')->get_file_from_remote('https://' . $path_to_check);
                //     // Did it work?
                //     if (isset($http_response_header) && strstr($http_response_header[0], '200')) {
                //         return true;
                //     }
                //     return  'jcogs_lic_cp_invalid_staging_domain';
                // }
                return true;
            });

            // Set validation rules
            // --------------------

            $validator->setRules(array(
                'jcogs_license_key_email'        => 'whenPresent[jcogs_license_key]|email|valid_license_email',
                'jcogs_license_key'              => 'valid_license_key_format|valid_license',
                'jcogs_staging_domain'           => 'whenPresent|valid_staging_domain',
            ));

            // Do the validation
            // -----------------
            $result = $validator->validate($_POST);

            if ($result->isValid()) {

                $fields = array();
                // Get all $_POST values, store them in array and save them
                // Use ee input library as it cleans up POST entries on loading
                // Define allowed settings keys
                $allowed_settings = array_keys($this->_settings);

                foreach ($_POST as $key => $value) {
                    // Only process known settings
                    if (!in_array($key, $allowed_settings)) {
                        continue;
                    }
                    
                    $fields[$key] = ee()->input->post($key);
                    $fields[$key] = is_numeric($fields[$key]) ? (int) $fields[$key] : $fields[$key];
                }
                $fields = array_merge($this->_settings, $fields);

                // Fix obscured license field if we need to
                if ($fields['jcogs_license_key'] == ee('jcogs_img:Utilities')->obscure_key($this->_settings['jcogs_license_key'])) {
                    $fields['jcogs_license_key'] = $this->_settings['jcogs_license_key'];
                }

                // Now save the settings values
                ee('jcogs_img:Settings')->save_settings($fields);

                // Pop up a save confirmation if all went well.
                ee('CP/Alert')->makeInline('shared-form')
                    ->asSuccess()
                    ->withTitle(lang('preferences_updated'))
                    ->addToBody(lang('preferences_updated_desc'))
                    ->defer();

                // Redraw page now
                ee()->functions->redirect(ee('CP/URL', 'addons/settings/jcogs_img/license')->compile());
            } else {
                $this->_data['errors'] = $result;
                ee('CP/Alert')->makeInline('shared-form')
                    ->asIssue()
                    ->withTitle(lang('settings_save_error'))
                    ->addToBody(lang('settings_save_error_desc'))
                    ->now();
            }
        }

        // No post data, so just draw the page

        // --------------------------------------
        // Build the form into $sections array
        // --------------------------------------

        $sections[lang('jcogs_lic_license')] = array(
            'group' => 'license_options',
            'settings' => array(

                // ----------------------------------------
                // Enter License Key
                // ----------------------------------------

                ee('jcogs_img:Licensing')->mcp_license_key_entry(ee()->input->post('jcogs_license_key') ?: $this->_settings['jcogs_license_key'], ee()->input->post('jcogs_license_key_email') ?: $this->_settings['jcogs_license_key_email']),

                // ----------------------------------------
                // Enter License Key email address
                // ----------------------------------------

                array(
                    'title' => 'jcogs_lic_cp_license_key_email',
                    'desc' => 'jcogs_lic_cp_license_key_email_desc',
                    'fields' => array(
                        'jcogs_license_key_email' => array(
                            'type'  => 'text',
                            'value' => $this->_settings['jcogs_license_key_email'],
                            'placeholder' => lang('jcogs_lic_cp_license_key_email_placeholder'),
                            'required' => false
                        )
                    )
                ),

                // ----------------------------------------
                // Enter Staging Domain
                // ----------------------------------------

                ee('jcogs_img:Licensing')->mcp_staging_domain_entry($this->_settings['jcogs_license_mode']),

            )
        );

        $this->_data += array(
            'cp_page_title' => lang('jcogs_lic_register_license'),
            'base_url' => ee('CP/URL', 'addons/settings/jcogs_img/license')->compile(),
            'save_btn_text' => sprintf(lang('btn_save'), lang('jcogs_lic_save_button')),
            'save_btn_text_working' => lang('btn_saving'),
            'sections' => $sections
        );

        // Tell EE to load the custom javascript for the page
        ee()->cp->load_package_js('form_controls');

        return array(
            'heading'       => lang('jcogs_lic_register_license'),
            'breadcrumb'    => array(
                ee('CP/URL', 'addons/settings/jcogs_img/license')->compile() => lang('img_image_settings')
            ),
            'body'          => ee('View')->make('ee:_shared/form')->render($this->_data),
        );
    }

    /**
     * Build the navigation menu for the module
     */
    private function _build_sidebar()
    {
        $sidebar = ee('CP/Sidebar')->make();

        $sd_div = $sidebar->addHeader(lang('jcogs_img_nav_title'));
        $sd_div_list = $sd_div->addBasicList();
        $sd_div_list->addItem(lang('jcogs_img_cp_main_settings'), ee('CP/URL', 'addons/settings/jcogs_img'));
        $sd_div_list->addItem(lang('jcogs_img_cp_caching_sidebar_label'), ee('CP/URL', 'addons/settings/jcogs_img/caching'));
        $sd_div_list->addItem(lang('jcogs_img_cp_image_settings'), ee('CP/URL', 'addons/settings/jcogs_img/image_defaults'));
        $sd_div_list->addItem(lang('jcogs_img_advanced_settings'), ee('CP/URL', 'addons/settings/jcogs_img/advanced_settings'));
        $sd_div_list->addItem(lang('jcogs_lic_license'), ee('CP/URL', 'addons/settings/jcogs_img/license'));
        $sd_div_list->addItem(lang('nav_support_page'),  ee()->cp->masked_url(ee('App')->get('jcogs_img')->get('docs_url')));
        if ($this->_settings['img_cp_enable_debugging'] === 'y') {
            $sd_debug = $sidebar->addHeader(lang('jcogs_img_debug_info'));
            $sd_debug_list = $sd_debug->addBasicList();
            $sd_debug_list->addItem(sprintf(lang('jcogs_img_version'), ee('Addon')->get('jcogs_img')->getInstalledVersion()));
            $sd_debug_list->addItem(sprintf(lang('jcogs_img_debug_php_version'),  PHP_VERSION));
            $sd_debug_list->addItem(sprintf(lang('jcogs_img_debug_ee_version'),  APP_VER));
        }
    }

    /**
     * Utility function to validate a remote image path
     *
     * @return array
     */
    private function _get_valid_flysystem_adapters(): array
    {
        // A valid flysystem adapter has the setting 'img_cp_flysystem_adapter_XX_is_valid' set to true - where XX is the adapter name
        // This function returns an array of valid adapters based on this condition, starting from a list of all possible adapters
        // Note: Local adapter is always valid (no setting required)
        $possible_adapters = array(
            'local'     => true, // Local adapter is always valid
            's3'        => $this->_settings['img_cp_flysystem_adapter_s3_is_valid'] ?? false,
            'r2'        => $this->_settings['img_cp_flysystem_adapter_r2_is_valid'] ?? false,
            'dospaces'  => $this->_settings['img_cp_flysystem_adapter_dospaces_is_valid'] ?? false
        );
        
        // Filter the possible adapters to only those that are valid
        $valid_adapters = array_filter($possible_adapters, function ($is_valid) {
            return $is_valid === true || $is_valid === 'true';
        });
        
        // All possible adapter labels
        $all_adapter_labels = array(
            'local'     => lang('jcogs_img_cp_flysystem_local_adapter'),
            's3'        => lang('jcogs_img_cp_flysystem_s3_adapter'),
            'r2'        => lang('jcogs_img_cp_flysystem_r2_adapter'),
            'dospaces'  => lang('jcogs_img_cp_flysystem_dospaces_adapter')
        );
        
        // Return only the labels for valid adapters
        $valid_adapter_labels = array();
        foreach ($valid_adapters as $adapter_key => $is_valid) {
            if (isset($all_adapter_labels[$adapter_key])) {
                $valid_adapter_labels[$adapter_key] = $all_adapter_labels[$adapter_key];
            }
        }
        
        return $valid_adapter_labels;
    }

    /**
     * Utility function to validate a remote image path
     *
     * @param  string $image_path
     * @return boolean
     */
    private function _valid_remote_image(string $image_path)
    {
        // Try and get image from remote URL
        $the_file = ee('jcogs_img:Utilities')->get_file_from_remote($image_path);
        if (!$the_file) {
            // unable to read a file from remote location
            return false;
        }
        // Got a file, is it an image?
        // Create a suitably random filename to prevent inter-process clashes
        $random_file = 'jcogs_img-' . time() . random_int(1, 999);
        $hash = hash('whirlpool', $random_file) . '.jpg';
        // Get base path
        $base_path = ee('jcogs_img:Utilities')->get_base_path();
        if (!$base_path) {
            // $basepath is invalid, so bale
            return false;
        }
        // Make sure temp path also ends in a /
        $temporary_image_path = $base_path . rtrim(ee('jcogs_img:Settings')::$this->_settings['img_cp_default_cache_directory'], '/') . '/';
        // Make sure target directory is valid
        // Even though we are cloud system enabled, do this using local file system as this for local stuff
        if (!ee('Filesystem')->exists($temporary_image_path)) {
            ee('Filesystem')->mkDir($temporary_image_path);
        }
        // Save file
        $file_save_status = file_put_contents($temporary_image_path . $hash, $the_file);
        if (!$file_save_status) {
            return false;
        }
        $is_valid_file = exif_imagetype($temporary_image_path . $hash);
        unlink($temporary_image_path . $hash);
        return $is_valid_file ? true : false;
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
}
