<?php

/**
 * JCOGS Image Pro - Control Panel Index Route
 * ============================================
 * Main control panel entry point with settings overview
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

class Index extends ImageAbstractRoute
{
    /**
     * @var string
     */
    protected $route_path = 'index';

    /**
     * @var string
     */
    protected $cp_page_title = 'JCOGS Image Pro';

    /**
     * Main settings page using CP/Form components
     * 
     * @param false $id
     * @return ImageAbstractRoute
     */
    public function process($id = false)
    {
        // Load language file
        $this->load_language();
        
        // Get current settings using shared service
        $current_settings = $this->_get_current_settings();
        
        // Build the sidebar using shared method
        $this->build_sidebar($current_settings);

        // Handle form submission
        if (ee()->input->post('submit')) {
            $this->_handleFormSubmission($this->settings_service);
            // Refresh settings after save
            $current_settings = $this->_get_current_settings();
        }

        // Create CP/Form using proper EE7 pattern
        $form = ee('CP/Form');
        $form->setCpPageTitle(lang('jcogs_img_pro_cp_main_settings'))
            ->setBaseUrl(ee('CP/URL')->make('addons/settings/jcogs_img_pro'));

        // 1. System Enable Section
        $global_group = $form->getGroup(lang('jcogs_img_pro_cp_global_settings'));
        
        $enable_fieldset = $global_group->getFieldSet(lang('jcogs_img_pro_cp_enable_addon'));
        $enable_fieldset->setDesc(lang('jcogs_img_pro_cp_enable_addon_desc'));
        $enable_fieldset->getField('enable_img', 'yes_no')
            ->setValue($current_settings['enable_img'] ?? 'y');

        // 2. Action Link Settings Section (Simplified to match Legacy)
        $action_group = $form->getGroup(lang('jcogs_img_pro_cp_action_image'));
        
        // Action Information Display (matches Legacy implementation)
        $action_info_fieldset = $action_group->getFieldSet(lang('jcogs_img_pro_cp_action_image_title'));
        $action_content = $this->_build_action_link_content($current_settings);
        $action_info_fieldset->setDesc($action_content);
        
        // Add the automatic action links setting
        $auto_action_fieldset = $action_group->getFieldSet(lang('jcogs_img_pro_cp_action_links_enable'));
        $auto_action_fieldset->setDesc(lang('jcogs_img_pro_cp_action_links_enable_desc'));
        $auto_action_fieldset->getField('img_cp_action_links', 'yes_no')
            ->setValue($current_settings['img_cp_action_links'] ?? 'n');

        // 3. Debug Information Section  
        $debug_group = $form->getGroup(lang('jcogs_img_pro_cp_performance_settings'));
        
        $debug_fieldset = $debug_group->getFieldSet(lang('jcogs_img_pro_cp_enable_debug'));
        $debug_fieldset->setDesc(lang('jcogs_img_pro_cp_enable_debug_desc'));
        $debug_fieldset->getField('img_cp_enable_debugging', 'yes_no')
            ->setValue($current_settings['img_cp_enable_debugging'] ?? 'n');
            
        // $cache_dir_fieldset = $debug_group->getFieldSet(lang('jcogs_img_pro_cp_cache_directory'));
        // $cache_dir_fieldset->setDesc(lang('jcogs_img_pro_cp_cache_directory_desc'));
        // $cache_dir_fieldset->getField('img_cp_cache_directory', 'text')
        //     ->setValue($current_settings['img_cp_cache_directory'] ?? 'images/jcogs_img_pro/cache');

        // Add submit button
        $submit_button = $form->getButton('submit');
        $submit_button->setType('submit')
            ->setText(lang('jcogs_img_pro_cp_save_settings'))
            ->setValue('default_settings')
            ->setWorking(lang('jcogs_img_pro_cp_saving'));

        // Pass form data to template
        $variables = $form->toArray();
        $this->setBody('ee:_shared/form', $variables);

        return $this;
    }

    /**
     * Build action link content using legacy approach (simplified)
     */
    private function _build_action_link_content($current_settings)
    {
        if($action_id = $this->utilities_service->get_action_id('act_originated_image')) {
            $action_url = ee()->config->item('site_url') . '?ACT=' . strval($action_id);
            
            // Simple content matching Legacy implementation exactly
            return sprintf(lang('jcogs_img_pro_cp_action_image_desc'), $action_url);
            
        } else {
            return lang('jcogs_img_pro_cp_no_action_found');
        }
    }

    /**
     * Handle form submission using EE's built-in validation service
     */
    private function _handleFormSubmission($settings_service)
    {
        $posted_data = $_POST;
        
        // Debug: Log what we're receiving
        if (isset($posted_data['img_cp_enable_debugging']) && $posted_data['img_cp_enable_debugging'] === 'y') {
            $this->utilities_service->debug_log('cp_form_submission_data', print_r($posted_data, true));
        }
        
        // Use EE's built-in validation service (following legacy pattern)
        $validator = ee('Validation')->make();
        
        // Define validation rules for main settings
        $validator->setRules(array(
            'img_cp_enable_addon'                  => 'enum[y,n]',
            'img_cp_enable_debugging'              => 'enum[y,n]',
            'img_cp_action_links'                  => 'enum[y,n]',
        ));
        
        $result = $validator->validate($posted_data);
        
        if ($result->isValid()) {
            // Save settings
            $save_result = $settings_service->save_settings($posted_data);
            
            if ($save_result) {
                // Show success message
                ee()->session->set_flashdata('message_success', lang('jcogs_img_pro_cp_settings_saved'));
            } else {
                ee()->session->set_flashdata('message_failure', lang('jcogs_img_pro_cp_settings_save_error'));
            }
            
            // Redirect to prevent form resubmission
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro'));
        } else {
            // Show validation errors
            foreach ($result->getAllErrors() as $error) {
                ee()->session->set_flashdata('message_failure', $error);
            }
        }
    }
}
