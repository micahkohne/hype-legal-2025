<?php

/**
 * JCOGS Image Pro - Advanced Settings Route
 * ==========================================
 * Advanced image processing configuration with native EE7 validation
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

namespace JCOGSDesign\JCOGSImagePro\ControlPanel\Routes;

use Exception;

class AdvancedSettings extends ImageAbstractRoute
{
    /**
     * @var string
     */
    protected $route_path = 'advanced_settings';

    /**
     * @var string
     */
    protected $cp_page_title = 'jcogs_img_pro_cp_advanced_settings';

    /**
     * Advanced configuration settings page
     * 
     * @param false $id
     * @return ImageAbstractRoute
     */
    public function process($id = false)
    {
        // Load language files
        $this->load_language();
        $this->load_language('jcogs_img_pro_parameters');
        
        // Set page title using language key
        $this->cp_page_title = lang('jcogs_img_pro_cp_advanced_settings');
        
        // Add breadcrumb
        $this->addBreadcrumb('advanced_settings', lang('jcogs_img_pro_cp_advanced_settings'));

        // Get current settings using shared service
        $current_settings = $this->_get_current_settings();
        
        // Build the sidebar using shared method
        $this->build_sidebar($current_settings);

        // Handle form submission
        if (ee()->input->post('submit')) {
            $this->_handleAdvancedSettings();
        }

        // Build form data using EE7 CP/Form
        $form = $this->_buildAdvancedSettingsForm($current_settings);

        // Pass form data to template
        $variables = $form->toArray();
        $variables['cp_page_title'] = $this->cp_page_title;  // Add page title for template
        $this->setBody('ee:_shared/form', $variables);

        return $this;
    }

    /**
     * Build advanced settings form using EE7 CP/Form service
     */
    private function _buildAdvancedSettingsForm($current_settings)
    {
        // Create EE7 CP/Form
        $form = ee('CP/Form');
        $form->asFileUpload()
            ->setBaseUrl(ee('CP/URL')->make('addons/settings/jcogs_img_pro/advanced_settings'));

        // Advanced Options Toggle Section (like legacy)
        $advanced_toggle_group = $form->getGroup(lang('jcogs_img_pro_advanced_settings'));
        
        // View Advanced Options toggle
        $toggle_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_view_advanced_options'));
        $toggle_fieldset->setDesc(lang('jcogs_img_pro_cp_view_advanced_options_desc'));
        $toggle_field = $toggle_fieldset->getField('img_cp_advanced_options', 'yes_no');
        $toggle_field->setValue('n');
        
        // Set up group toggle functionality like AddConnection - proper EE CP/Form approach
        $toggle_field->set('group_toggle', [
            'y' => 'advanced_options'
        ]);

        // Enable Browser Capability Detection
        $browser_check_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_enable_browser_check'));
        $browser_check_fieldset->setDesc(lang('jcogs_img_pro_cp_enable_browser_check_desc'));
        $browser_check_fieldset->set('group', 'advanced_options'); // Set group on fieldset
        $browser_check_fieldset->getField('img_cp_enable_browser_check', 'yes_no')
            ->setValue($current_settings['img_cp_enable_browser_check'] ?? 'y');

        // Enable Class Consolidation
        $class_consolidation_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_class_consolidation_default'));
        $class_consolidation_fieldset->setDesc(lang('jcogs_img_pro_cp_class_consolidation_default_desc'));
        $class_consolidation_fieldset->set('group', 'advanced_options'); // Set group on fieldset
        $class_consolidation_fieldset->getField('img_cp_class_consolidation_default', 'yes_no')
            ->setValue($current_settings['img_cp_class_consolidation_default'] ?? 'y');

        // Enable Attribute Variable Expansion
        $attribute_expansion_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_attribute_variable_expansion_default'));
        $attribute_expansion_fieldset->setDesc(lang('jcogs_img_pro_cp_attribute_variable_expansion_default_desc'));
        $attribute_expansion_fieldset->set('group', 'advanced_options'); // Set group on fieldset
        $attribute_expansion_fieldset->getField('img_cp_attribute_variable_expansion_default', 'yes_no')
            ->setValue($current_settings['img_cp_attribute_variable_expansion_default'] ?? 'y');

        // Face Detection Crop Focus (Pro Feature)
        $face_detect_crop_focus_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_face_detect_crop_focus'));
        $face_detect_crop_focus_fieldset->setDesc(lang('jcogs_img_pro_cp_face_detect_crop_focus_desc'));
        $face_detect_crop_focus_fieldset->set('group', 'advanced_options'); // Set group on fieldset
        $face_detect_crop_focus_field = $face_detect_crop_focus_fieldset->getField('img_cp_default_face_detect_crop_focus', 'select');
        $face_detect_crop_focus_field->setValue($current_settings['img_cp_default_face_detect_crop_focus'] ?? 'first_face')
            ->setChoices([
                'first_face' => lang('jcogs_img_pro_cp_face_detect_crop_focus_first_face'),
                'all' => lang('jcogs_img_pro_cp_face_detect_crop_focus_all')
            ]);

        // Cache Filename Separator
        $separator_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_default_filename_separator'));
        $separator_fieldset->setDesc(lang('jcogs_img_pro_cp_default_filename_separator_desc'));
        $separator_fieldset->set('group', 'advanced_options'); // Set group on fieldset
        $separator_fieldset->getField('img_cp_default_filename_separator', 'text')
            ->setValue($current_settings['img_cp_default_filename_separator'] ?? '_-_')
            ->setRequired(true);

        // Maximum Source Filename Length
        $filename_length_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_default_max_source_filename_length'));
        $filename_length_fieldset->setDesc(lang('jcogs_img_pro_cp_default_max_source_filename_length_desc'));
        $filename_length_fieldset->set('group', 'advanced_options'); // Set group on fieldset
        $filename_length_fieldset->getField('img_cp_default_max_source_filename_length', 'text')
            ->setValue($current_settings['img_cp_default_max_source_filename_length'] ?? '175')
            ->setRequired(true);

        // Include Source in Filename Hash
        $include_source_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_include_source_in_filename_hash'));
        $include_source_fieldset->setDesc(lang('jcogs_img_pro_cp_include_source_in_filename_hash_desc'));
        $include_source_fieldset->set('group', 'advanced_options'); // Set group on fieldset
        $include_source_fieldset->getField('img_cp_include_source_in_filename_hash', 'yes_no')
            ->setValue($current_settings['img_cp_include_source_in_filename_hash'] ?? 'n');

        // Append Path to Action Links
        $append_path_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_append_path_to_action_links'));
        $append_path_fieldset->setDesc(lang('jcogs_img_pro_cp_append_path_to_action_links_desc'));
        $append_path_fieldset->set('group', 'advanced_options'); // Set group on fieldset
        $append_path_fieldset->getField('img_cp_append_path_to_action_links', 'yes_no')
            ->setValue($current_settings['img_cp_append_path_to_action_links'] ?? 'n');

        // CE Image Remote Directory
        $ce_remote_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_ce_image_remote_dir'));
        $ce_remote_fieldset->setDesc(lang('jcogs_img_pro_cp_ce_image_remote_dir_desc'));
        $ce_remote_fieldset->set('group', 'advanced_options'); // Set group on fieldset
        $ce_remote_fieldset->getField('img_cp_ce_image_remote_dir', 'text')
            ->setValue($current_settings['img_cp_ce_image_remote_dir'] ?? 'images/remote');

        // PHP Memory Limit
        $php_ram_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_default_min_php_ram'));
        $php_ram_fieldset->setDesc(lang('jcogs_img_pro_cp_default_min_php_ram_desc'));
        $php_ram_fieldset->set('group', 'advanced_options'); // Set group on fieldset
        $php_ram_fieldset->getField('img_cp_default_min_php_ram', 'text')
            ->setValue($current_settings['img_cp_default_min_php_ram'] ?? '128')
            ->setRequired(true);

        // PHP Execution Time - ENHANCED with natural language support
        $php_time_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_default_min_php_process_time'));
        $php_time_fieldset->setDesc(lang('jcogs_img_pro_cp_default_min_php_process_time_desc'));
        $php_time_fieldset->set('group', 'advanced_options'); // Set group on fieldset
        
        // Use enhanced duration field with natural language support for timeout context
        try {
            $duration_parser = new \JCOGSDesign\JCOGSImagePro\Service\DurationParser();
            $duration_form_field = new \JCOGSDesign\JCOGSImagePro\Service\DurationFormField($duration_parser);
            
            $current_process_time = $current_settings['img_cp_default_min_php_process_time'] ?? '60';
            $process_time_field = $duration_form_field->createField(
                $php_time_fieldset, 
                'img_cp_default_min_php_process_time', 
                $current_process_time, 
                'timeout'
            );
            
            // Add help text for better UX
            $help_text = $duration_form_field->getHelpText('timeout');
            $php_time_fieldset->setDesc(lang('jcogs_img_pro_cp_default_min_php_process_time_desc') . '<br><small style="color: #64748b;">' . $help_text . '</small>');
            
        } catch (Exception $e) {
            // Fallback to basic field if duration services fail
            $php_time_fieldset->getField('img_cp_default_min_php_process_time', 'text')
                ->setValue($current_settings['img_cp_default_min_php_process_time'] ?? '60')
                ->setRequired(true);
        }

        // PHP Remote Connection Time - ENHANCED with natural language support
        $remote_time_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_default_php_remote_connect_time'));
        $remote_time_fieldset->setDesc(lang('jcogs_img_pro_cp_default_php_remote_connect_time_desc'));
        $remote_time_fieldset->set('group', 'advanced_options'); // Set group on fieldset
        
        // Use enhanced duration field with natural language support for timeout context
        try {
            $duration_parser = new \JCOGSDesign\JCOGSImagePro\Service\DurationParser();
            $duration_form_field = new \JCOGSDesign\JCOGSImagePro\Service\DurationFormField($duration_parser);
            
            $current_remote_time = $current_settings['img_cp_default_php_remote_connect_time'] ?? '3';
            $remote_time_field = $duration_form_field->createField(
                $remote_time_fieldset, 
                'img_cp_default_php_remote_connect_time', 
                $current_remote_time, 
                'timeout'
            );
            
            // Add help text for better UX
            $help_text = $duration_form_field->getHelpText('timeout');
            $remote_time_fieldset->setDesc(lang('jcogs_img_pro_cp_default_php_remote_connect_time_desc') . '<br><small style="color: #64748b;">' . $help_text . '</small>');
            
        } catch (Exception $e) {
            // Fallback to basic field if duration services fail
            $remote_time_fieldset->getField('img_cp_default_php_remote_connect_time', 'text')
                ->setValue($current_settings['img_cp_default_php_remote_connect_time'] ?? '3')
                ->setRequired(true);
        }

        // User Agent String
        $user_agent_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_default_user_agent_string'));
        $user_agent_fieldset->setDesc(lang('jcogs_img_pro_cp_default_user_agent_string_desc'));
        $user_agent_fieldset->set('group', 'advanced_options'); // Set group on fieldset
        $user_agent_fieldset->getField('img_cp_default_user_agent_string', 'text')
            ->setValue($current_settings['img_cp_default_user_agent_string'] ?? 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14.5; rv:127.0) Gecko/20100101 Firefox/127.0')
            ->setRequired(true);

        // Cache Log Preload Threshold
        $threshold_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_cache_log_preload_threshold'));
        $threshold_fieldset->setDesc(lang('jcogs_img_pro_cp_cache_log_preload_threshold_desc'));
        $threshold_fieldset->set('group', 'advanced_options'); // Set group on fieldset
        $threshold_fieldset->getField('img_cp_cache_log_preload_threshold', 'text')
            ->setValue($current_settings['img_cp_cache_log_preload_threshold'] ?? '10000')
            ->setRequired(true);

        // Current Cache Entries (read-only display)
        $current_count = $current_settings['img_cp_cache_log_current_count'] ?? 1;
        $last_updated = $current_settings['img_cp_cache_log_count_last_updated'] ?? time();
        $last_updated_formatted = date('Y-m-d H:i:s', $last_updated);
        $cache_count_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_current_cache_entries'));
        $cache_count_fieldset->setDesc(lang('jcogs_img_pro_cp_current_cache_entries_desc'));
        $cache_count_fieldset->set('group', 'advanced_options'); // Set group on fieldset
        $cache_count_fieldset->getField('cache_count_display', 'html')
            ->setValue(sprintf('%d %s (%s: %s)', 
                $current_count, 
                lang('jcogs_img_pro_cp_entries'),
                lang('jcogs_img_pro_cp_last_updated'),
                $last_updated_formatted
            ));

        // Enable Cache Audit
        $audit_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_enable_cache_audit'));
        $audit_fieldset->setDesc(lang('jcogs_img_pro_cp_enable_cache_audit_desc'));
        $audit_fieldset->set('group', 'advanced_options');
        $audit_fieldset->getField('img_cp_enable_cache_audit', 'yes_no')
            ->setValue($current_settings['img_cp_enable_cache_audit'] ?? 'y');

        // Cache Audit Interval - ENHANCED with natural language support
        $audit_interval_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_default_cache_audit_after'));
        $audit_interval_fieldset->setDesc(lang('jcogs_img_pro_cp_default_cache_audit_after_desc'));
        $audit_interval_fieldset->set('group', 'advanced_options');
        
        // Use enhanced duration field with natural language support
        try {
            $duration_parser = new \JCOGSDesign\JCOGSImagePro\Service\DurationParser();
            $duration_form_field = new \JCOGSDesign\JCOGSImagePro\Service\DurationFormField($duration_parser);
            
            $current_audit_interval = $current_settings['img_cp_default_cache_audit_after'] ?? '604800';
            $audit_field = $duration_form_field->createField(
                $audit_interval_fieldset, 
                'img_cp_default_cache_audit_after', 
                $current_audit_interval, 
                'audit'
            );
            
            // Add help text for better UX
            $help_text = $duration_form_field->getHelpText('audit');
            $audit_interval_fieldset->setDesc(lang('jcogs_img_pro_cp_default_cache_audit_after_desc') . '<br><small style="color: #64748b;">' . $help_text . '</small>');
            
        } catch (Exception $e) {
            // Fallback to basic field if duration services fail
            $audit_interval_fieldset->getField('img_cp_default_cache_audit_after', 'text')
                ->setValue($current_settings['img_cp_default_cache_audit_after'] ?? '604800')
                ->setRequired(true);
        }

        // JCOGS License Server Domain
        $license_server_fieldset = $advanced_toggle_group->getFieldSet(lang('jcogs_img_pro_cp_license_server_domain'));
        $license_server_fieldset->setDesc(lang('jcogs_img_pro_cp_license_server_domain_desc'));
        $license_server_fieldset->set('group', 'advanced_options');
        $license_server_fieldset->getField('jcogs_license_server_domain', 'text')
            ->setValue($current_settings['jcogs_license_server_domain'] ?? 'mule.jcogs.net')
            ->setRequired(true);

        // Add submit button
        $submit_button = $form->getButton('submit');
        $submit_button->setType('submit')
            ->setText(lang('jcogs_img_pro_cp_save_advanced_settings'))
            ->setValue('advanced_settings')
            ->setWorking(lang('jcogs_img_pro_cp_saving'));

        return $form;
    }

    /**
     * Get system information for advanced settings
     */

    /**
     * Handle advanced settings form submission
     */
    private function _handleAdvancedSettings()
    {
        $posted_data = $_POST;
        
        // Basic validation for advanced settings
        $errors = [];
        
        // Validate filename separator
        if (isset($posted_data['img_cp_default_filename_separator'])) {
            $separator = $posted_data['img_cp_default_filename_separator'];
            if (empty($separator) || 
                preg_match('/\s+/', $separator) || 
                preg_match('/[\[\^\-\\\]\_\.\~\!\*\'\(\)\;\:\@\&\=\+\$\,\/\?\%\#]/', $separator)) {
                $errors[] = lang('jcogs_img_pro_cp_invalid_separator_string');
            }
        }
        
        // Validate max source filename length
        if (isset($posted_data['img_cp_default_max_source_filename_length'])) {
            $length = (int)$posted_data['img_cp_default_max_source_filename_length'];
            if ($length <= 0 || $length > 175) {
                $errors[] = lang('jcogs_img_pro_cp_invalid_filename_length');
            }
        }
        
        // Validate PHP RAM value
        if (isset($posted_data['img_cp_default_min_php_ram'])) {
            $ram_value = $posted_data['img_cp_default_min_php_ram'];
            if (empty($ram_value) || !$this->_isValidMemoryValue($ram_value)) {
                $errors[] = lang('jcogs_img_pro_cp_invalid_php_ram_value');
            }
        }
        
        // DurationParser instance
        $duration_parser = new \JCOGSDesign\JCOGSImagePro\Service\DurationParser();

        // Parse and validate duration fields
        $duration_fields = [
            'img_cp_default_min_php_process_time' => 'timeout',
            'img_cp_default_php_remote_connect_time' => 'timeout',
            'img_cp_default_cache_audit_after' => 'audit'
        ];
        foreach ($duration_fields as $field => $context) {
            if (isset($posted_data[$field])) {
                $parsed = $duration_parser->parseToSeconds($posted_data[$field]);
                if ($parsed['error']) {
                    $errors[] = $parsed['error'];
                } else {
                    $validation = $duration_parser->validateForContext($parsed['value'], $context);
                    if (!$validation['valid']) {
                        $errors[] = $validation['error'];
                    }
                    $posted_data[$field] = $parsed['value'];
                }
            }
        }
        
        // Validate remote connection time
        if (isset($posted_data['img_cp_default_php_remote_connect_time'])) {
            $connect_time = (int)$posted_data['img_cp_default_php_remote_connect_time'];
            if ($connect_time <= 0) {
                $errors[] = lang('jcogs_img_pro_cp_invalid_connection_time');
            }
        }
        
        // Validate user agent string
        if (isset($posted_data['img_cp_default_user_agent_string'])) {
            $user_agent = trim($posted_data['img_cp_default_user_agent_string']);
            if (empty($user_agent)) {
                $errors[] = lang('jcogs_img_pro_cp_invalid_user_agent');
            }
        }
        
        // Validate cache log preload threshold
        if (isset($posted_data['img_cp_cache_log_preload_threshold'])) {
            $threshold = (int)$posted_data['img_cp_cache_log_preload_threshold'];
            if ($threshold <= 0) {
                $errors[] = lang('jcogs_img_pro_cp_invalid_cache_threshold');
            }
        }
        
        // Validate cache audit interval
        if (isset($posted_data['img_cp_default_cache_audit_after'])) {
            $interval = (int)$posted_data['img_cp_default_cache_audit_after'];
            if ($interval <= 0) {
                $errors[] = lang('jcogs_img_pro_cp_invalid_audit_interval');
            }
        }
        
        // Validate license server domain
        if (isset($posted_data['jcogs_license_server_domain'])) {
            $domain = trim($posted_data['jcogs_license_server_domain']);
            if (empty($domain) || !$this->_isValidDomain($domain)) {
                $errors[] = lang('jcogs_img_pro_cp_invalid_license_domain');
            }
        }
        
        // Validate CE Image remote directory
        if (isset($posted_data['img_cp_ce_image_remote_dir'])) {
            $remote_dir = trim($posted_data['img_cp_ce_image_remote_dir']);
            if (!empty($remote_dir) && !$this->_isValidPath($remote_dir)) {
                $errors[] = lang('jcogs_img_pro_cp_invalid_remote_directory');
            }
        }
        
        // Validate face detection crop focus
        if (isset($posted_data['img_cp_default_face_detect_crop_focus'])) {
            $crop_focus = $posted_data['img_cp_default_face_detect_crop_focus'];
            if (!in_array($crop_focus, ['first_face', 'all'])) {
                $errors[] = lang('jcogs_img_pro_cp_invalid_face_detect_crop_focus');
            }
        }
        
        if (empty($errors)) {
            // Save settings using shared service method
            if ($this->save_settings($posted_data)) {
                $this->set_success_message('jcogs_img_pro_cp_advanced_settings_saved');
            } else {
                $this->utilities_service->debug_log('message_failure', lang('jcogs_img_pro_cp_settings_save_failed'));
            }
        } else {
            foreach ($errors as $error) {
                $this->utilities_service->debug_log('message_failure', $error);
            }
        }
        
        $this->redirect_to_route('advanced_settings');
    }

    /**
     * Validate domain format
     */
    private function _isValidDomain($domain): bool
    {
        return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
    }

    /**
     * Validate memory value format
     */
    private function _isValidMemoryValue($value): bool
    {
        // Allow numeric values (assumed MB) or values with units (M, G, etc.)
        return preg_match('/^\d+[MG]?$/i', $value) || is_numeric($value);
    }

    /**
     * Validate path format
     */
    private function _isValidPath($path): bool
    {
        // Basic path validation - no absolute paths, no dangerous characters
        return !preg_match('/^\/|\.\./', $path) && !preg_match('/[<>:"|?*]/', $path);
    }

}
