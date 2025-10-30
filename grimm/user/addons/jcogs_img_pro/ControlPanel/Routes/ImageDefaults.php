<?php

/**
 * JCOGS Image Pro - Image Defaults Route
 * =======================================
 * Default image processing settings with native EE7 validation
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

use JCOGSDesign\JCOGSImagePro\Service\CPFormIntegration;

class ImageDefaults extends ImageAbstractRoute
{
    /**
     * @var string
     */
    protected $route_path = 'image_defaults';

    /**
     * @var CPFormIntegration
     */
    private $cp_form_integration;

    /**
     * @var string
     */
    protected $cp_page_title = 'jcogs_img_pro_cp_image_defaults';

    /**
     * Image processing default settings page
     * 
     * @param false $id
     * @return ImageAbstractRoute
     */
    public function process($id = false)
    {
        // Initialize CP Form Integration service
        $this->cp_form_integration = new CPFormIntegration();
        
        // Load language file
        $this->load_language();
        
        // Set page title using language key
        $this->cp_page_title = lang('jcogs_img_pro_cp_image_processing_defaults');
        
        // Add breadcrumb
        $this->addBreadcrumb('image_defaults', lang('jcogs_img_pro_cp_image_defaults'));

        // Get current settings using shared service
        $current_settings = $this->_get_current_settings();
        
        // Build the sidebar using shared method
        $this->build_sidebar($current_settings);

        // Handle form submission
        if (ee()->input->post('submit')) {
            $this->_handleImageDefaults();
            // Refresh settings after save
            $current_settings = $this->_get_current_settings();
        }

        // Build form data using EE7 CP/Form
        $form = $this->_buildImageDefaultsForm($current_settings);

        // Pass form data to template
        $variables = $form->toArray();
        $this->setBody('ee:_shared/form', $variables);

        return $this;
    }

    /**
     * Build image defaults form using EE7 CP/Form service
     */
    private function _buildImageDefaultsForm($current_settings)
    {
        // Create EE7 CP/Form
        $form = ee('CP/Form');
        $form->setCpPageTitle(lang('jcogs_img_pro_cp_image_defaults'))
            ->setBaseUrl(ee('CP/URL')->make('addons/settings/jcogs_img_pro/image_defaults'));

        // Image Format Options Section
        $format_group = $form->getGroup(lang('jcogs_img_pro_cp_image_format_options'));
        
        // ==========================================
        // ENHANCED: Default Image Format using dynamic format detection
        // ==========================================
        if (!$this->cp_form_integration->generateCPFormField($format_group, 'img_cp_default_image_format', $current_settings['img_cp_default_image_format'] ?? 'source')) {
            // Fallback to legacy approach if parameter package integration fails
            $format_fieldset = $format_group->getFieldSet(lang('jcogs_img_pro_cp_default_image_format'));
            $format_fieldset->setDesc(lang('jcogs_img_pro_cp_default_image_format_desc'));
            $format_fieldset->getField('img_cp_default_image_format', 'select')
                ->setChoices([
                    'source' => lang('jcogs_img_pro_cp_format_source', 'Use source image format'),
                    'gif' => 'GIF',
                    'jpg' => 'JPG',
                    'png' => 'PNG',
                    'wbmp' => 'WBMP',
                    'xpm' => 'XPM',
                    'xbm' => 'XBM',
                    'webp' => 'WebP',
                    'bmp' => 'BMP',
                    'avif' => 'AVIF'
                ])
                ->setValue($current_settings['img_cp_default_image_format'] ?? 'webp');
        }

        // ==========================================
        // ENHANCED: JPEG Quality using parameter package slider
        // ==========================================
        if (!$this->cp_form_integration->generateCPFormField($format_group, 'img_cp_jpg_default_quality', $current_settings['img_cp_jpg_default_quality'] ?? 90)) {
            // Fallback to legacy approach
            $quality_fieldset = $format_group->getFieldSet(lang('jcogs_img_pro_cp_default_image_quality', 'Default image quality'));
            $quality_fieldset->setDesc(lang('jcogs_img_pro_cp_default_image_quality_desc', ''));
            $quality_fieldset->getField('img_cp_jpg_default_quality', 'slider')
                ->setMin(1)
                ->setMax(100)
                ->setStep(1)
                ->setUnit('%')
                ->setValue((int)($current_settings['img_cp_jpg_default_quality'] ?? 90));
        }

        // ==========================================
        // ENHANCED: PNG Quality using parameter package slider
        // ==========================================
        if (!$this->cp_form_integration->generateCPFormField($format_group, 'img_cp_png_default_quality', $current_settings['img_cp_png_default_quality'] ?? 6)) {
            // Fallback to legacy approach
            $png_quality_fieldset = $format_group->getFieldSet(lang('jcogs_img_pro_cp_png_default_quality', 'Default image quality for PNG images'));
            $png_quality_fieldset->setDesc(lang('jcogs_img_pro_cp_png_default_quality_desc', ''));
            $png_quality_fieldset->getField('img_cp_png_default_quality', 'slider')
                ->setMin(0)
                ->setMax(9)
                ->setStep(1)
                ->setUnit('')
                ->setValue((int)($current_settings['img_cp_png_default_quality'] ?? 6));
        }

        // Default Background Color using native EE color field (keeping legacy approach for now)
        $bg_color_fieldset = $format_group->getFieldSet(lang('jcogs_img_pro_cp_default_bg_color', 'Default background colour'));
        $bg_color_fieldset->setDesc(lang('jcogs_img_pro_cp_default_bg_color_desc', ''));
        $bg_color_fieldset->getField('img_cp_default_bg_color', 'color')
            ->setValue($current_settings['img_cp_default_bg_color'] ?? '#ff9200');

        // Image Operational Defaults Section
        $operational_group = $form->getGroup(lang('jcogs_img_pro_cp_image_operational_defaults'));
        
        // ==========================================
        // ENHANCED: Default Width using parameter package
        // ==========================================
        if (!$this->cp_form_integration->generateCPFormField($operational_group, 'img_cp_default_img_width', $current_settings['img_cp_default_img_width'] ?? '350')) {
            // Fallback to legacy approach
            $default_width_fieldset = $operational_group->getFieldSet(lang('jcogs_img_pro_cp_default_img_width', 'Set default width for unsized images'));
            $default_width_fieldset->setDesc(lang('jcogs_img_pro_cp_default_img_width_desc', ''));
            $default_width_fieldset->getField('img_cp_default_img_width', 'text')
                ->setValue($current_settings['img_cp_default_img_width'] ?? '350');
        }

        // ==========================================
        // ENHANCED: Default Height using parameter package
        // ==========================================
        if (!$this->cp_form_integration->generateCPFormField($operational_group, 'img_cp_default_img_height', $current_settings['img_cp_default_img_height'] ?? '150')) {
            // Fallback to legacy approach
            $default_height_fieldset = $operational_group->getFieldSet(lang('jcogs_img_pro_cp_default_img_height', 'Set default height for unsized images'));
            $default_height_fieldset->setDesc(lang('jcogs_img_pro_cp_default_img_height_desc', ''));
            $default_height_fieldset->getField('img_cp_default_img_height', 'text')
                ->setValue($current_settings['img_cp_default_img_height'] ?? '150');
        }

        // Preserve Animated GIF Format (keeping legacy - complex functionality)
        $animated_gif_fieldset = $operational_group->getFieldSet(lang('jcogs_img_pro_cp_ignore_save_type_for_animated_gifs'));
        $animated_gif_fieldset->setDesc(lang('jcogs_img_pro_cp_ignore_save_type_for_animated_gifs_desc'));
        $animated_gif_fieldset->getField('img_cp_ignore_save_type_for_animated_gifs', 'toggle')
            ->setValue($current_settings['img_cp_ignore_save_type_for_animated_gifs'] ?? 'y');

        // ==========================================
        // ENHANCED: Allow Scale Larger Default using parameter package
        // ==========================================
        if (!$this->cp_form_integration->generateCPFormField($operational_group, 'img_cp_allow_scale_larger_default', $current_settings['img_cp_allow_scale_larger_default'] ?? 'n')) {
            // Fallback to legacy approach
            $scale_larger_fieldset = $operational_group->getFieldSet(lang('jcogs_img_pro_cp_allow_scale_larger_default'));
            $scale_larger_fieldset->setDesc(lang('jcogs_img_pro_cp_allow_scale_larger_default_desc'));
            $scale_larger_fieldset->getField('img_cp_allow_scale_larger_default', 'toggle')
                ->setValue($current_settings['img_cp_allow_scale_larger_default'] ?? 'n');
        }

        // ==========================================
        // ENHANCED: Enable Auto Sharpen using parameter package  
        // ==========================================
        if (!$this->cp_form_integration->generateCPFormField($operational_group, 'img_cp_enable_auto_sharpen', $current_settings['img_cp_enable_auto_sharpen'] ?? 'n')) {
            // Fallback to legacy approach
            $auto_sharpen_fieldset = $operational_group->getFieldSet(lang('jcogs_img_pro_cp_enable_auto_sharpen'));
            $auto_sharpen_fieldset->setDesc(lang('jcogs_img_pro_cp_enable_auto_sharpen_desc'));
            $auto_sharpen_fieldset->getField('img_cp_enable_auto_sharpen', 'toggle')
                ->setValue($current_settings['img_cp_enable_auto_sharpen'] ?? 'n');
        }

        // HTML Decoding Enabled (keeping legacy - specific to EE functionality)
        $html_decoding_fieldset = $operational_group->getFieldSet(lang('jcogs_img_pro_cp_html_decoding_enabled'));
        $html_decoding_fieldset->setDesc(lang('jcogs_img_pro_cp_html_decoding_enabled_desc'));
        $html_decoding_fieldset->getField('img_cp_html_decoding_enabled', 'toggle')
            ->setValue($current_settings['img_cp_html_decoding_enabled'] ?? 'y');

        // ==========================================
        // ENHANCED: Enable Lazy Loading using parameter package
        // ==========================================
        if (!$this->cp_form_integration->generateCPFormField($operational_group, 'img_cp_enable_lazy_loading', $current_settings['img_cp_enable_lazy_loading'] ?? 'n')) {
            // Fallback to legacy approach (keeping complex group toggle logic)
            $lazy_loading_fieldset = $operational_group->getFieldSet(lang('jcogs_img_pro_cp_enable_lazy_loading'));
            $lazy_loading_fieldset->setDesc(lang('jcogs_img_pro_cp_enable_lazy_loading_desc'));
            $lazy_loading_field = $lazy_loading_fieldset->getField('img_cp_enable_lazy_loading', 'toggle')
                ->setValue($current_settings['img_cp_enable_lazy_loading'] ?? 'n');
        }

        // Lazy Loading Mode (keeping legacy - complex group toggle and choices)
        $lazy_mode_fieldset = $operational_group->getFieldSet(lang('jcogs_img_pro_cp_lazy_loading_mode'));
        $lazy_mode_fieldset->setDesc(lang('jcogs_img_pro_cp_lazy_loading_mode_desc'));
        $lazy_mode_field = $lazy_mode_fieldset->getField('img_cp_lazy_loading_mode', 'select');
        $lazy_mode_field->setChoices([
                'lqip' => lang('jcogs_img_pro_cp_lazy_lqip'),
                'dominant_color' => lang('jcogs_img_pro_cp_lazy_dominant_color'),
                'js_lqip' => lang('jcogs_img_pro_cp_lazy_js_lqip'),
                'js_dominant_color' => lang('jcogs_img_pro_cp_lazy_js_dominant_color'),
                'html5' => lang('jcogs_img_pro_cp_lazy_html5')
            ])
            ->setValue($current_settings['img_cp_lazy_loading_mode'] ?? 'lqip')
            ->setGroup('lazy_loading_options');

        // Progressive Enhancement (keeping legacy - complex group logic)
        $progressive_fieldset = $operational_group->getFieldSet(lang('jcogs_img_pro_cp_lazy_progressive_enhancement'));
        $progressive_fieldset->setDesc(lang('jcogs_img_pro_cp_lazy_progressive_enhancement_desc'));
        $progressive_fieldset->getField('img_cp_lazy_progressive_enhancement', 'toggle')
            ->setValue($current_settings['img_cp_lazy_progressive_enhancement'] ?? 'y')
            ->setGroup('lazy_loading_options');

        // Default Preload Critical Images (keeping legacy)
        $preload_critical_fieldset = $operational_group->getFieldSet(lang('jcogs_img_pro_cp_default_preload_critical'));
        $preload_critical_fieldset->setDesc(lang('jcogs_img_pro_cp_default_preload_critical_desc'));
        $preload_critical_fieldset->getField('img_cp_default_preload_critical', 'toggle')
            ->setValue($current_settings['img_cp_default_preload_critical'] ?? 'n');

        // Default Fallback Image with custom group toggle (keeping legacy - very complex logic)
        $fallback_fieldset = $operational_group->getFieldSet(lang('jcogs_img_pro_cp_enable_default_fallback_image'));
        $fallback_fieldset->setDesc(lang('jcogs_img_pro_cp_enable_default_fallback_image_desc'));
        $fallback_field = $fallback_fieldset->getField('img_cp_enable_default_fallback_image', 'select')
            ->setChoices([
                'n' => lang('jcogs_img_pro_cp_no_fallback'),
                'yc' => lang('jcogs_img_pro_cp_fallback_color'),
                'yl' => lang('jcogs_img_pro_cp_fallback_local'),
                'yr' => lang('jcogs_img_pro_cp_fallback_remote')
            ])
            ->setValue($current_settings['img_cp_enable_default_fallback_image'] ?? 'n');
        
        // Set up group toggle to show/hide appropriate fallback option fields
        $fallback_field->set('group_toggle', [
            'n' => null, // Hide all fallback groups when "No fallback" is selected
            'yc' => 'fallback_color_options',
            'yl' => 'fallback_local_options', 
            'yr' => 'fallback_remote_options'
        ]);

        // Fallback Color Field using native color picker
        $fallback_color_fieldset = $operational_group->getFieldSet(lang('jcogs_img_pro_cp_fallback_image_colour'));
        $fallback_color_fieldset->set('group', 'fallback_color_options');
        $fallback_color_fieldset->setDesc(lang('jcogs_img_pro_cp_fallback_image_colour_desc'));
        $fallback_color_fieldset->getField('img_cp_fallback_image_colour', 'color')
            ->setValue($current_settings['img_cp_fallback_image_colour'] ?? '#306392')
            ->setGroup('fallback_color_options');

        // Fallback Local Image using native file picker
        $fallback_local_fieldset = $operational_group->getFieldSet(lang('jcogs_img_pro_cp_fallback_image_local'));
        $fallback_local_fieldset->set('group', 'fallback_local_options');
        $fallback_local_fieldset->setDesc(lang('jcogs_img_pro_cp_fallback_image_local_desc'));
        $fallback_local_fieldset->getField('img_cp_fallback_image_local', 'file-picker')
            ->setValue($current_settings['img_cp_fallback_image_local'] ?? '')
            ->asImage()
            ->withAll()
            ->setGroup('fallback_local_options');

        // Fallback Remote Image
        $fallback_remote_fieldset = $operational_group->getFieldSet(lang('jcogs_img_pro_cp_fallback_image_remote'));
        $fallback_remote_fieldset->set('group', 'fallback_remote_options');
        $fallback_remote_fieldset->setDesc(lang('jcogs_img_pro_cp_fallback_image_remote_desc'));
        $fallback_remote_fieldset->getField('img_cp_fallback_image_remote', 'url')
            ->setValue($current_settings['img_cp_fallback_image_remote'] ?? '')
            ->setPlaceholder('https://example.com/fallback-image.jpg')
            ->setAttrs('pattern="https?://.+\.(jpg|jpeg|png|gif|webp|bmp|avif)"')
            ->setGroup('fallback_remote_options');

        // Operational Limits Section
        $limits_group = $form->getGroup(lang('jcogs_img_pro_cp_operational_limits'));

        // Enable Auto-adjust for Oversized Source Images with group toggle (keeping legacy)
        $auto_adjust_fieldset = $limits_group->getFieldSet(lang('jcogs_img_pro_cp_enable_auto_adjust'));
        $auto_adjust_fieldset->setDesc(lang('jcogs_img_pro_cp_enable_auto_adjust_desc'));
        $auto_adjust_field = $auto_adjust_fieldset->getField('img_cp_enable_auto_adjust', 'toggle')
            ->setValue($current_settings['img_cp_enable_auto_adjust'] ?? 'y');
        $auto_adjust_field->set('group_toggle', ['y' => 'auto_adjust_options']);

        // Maximum Image Dimension
        $max_dimension_fieldset = $limits_group->getFieldSet(lang('jcogs_img_pro_cp_default_max_image_dimension'));
        $max_dimension_fieldset->setDesc(lang('jcogs_img_pro_cp_default_max_image_dimension_desc'));
        $max_dimension_fieldset->getField('img_cp_default_max_image_dimension', 'text')
            ->setValue($current_settings['img_cp_default_max_image_dimension'] ?? '1500')
            ->setGroup('auto_adjust_options');

        // Maximum Image Size
        $max_size_fieldset = $limits_group->getFieldSet(lang('jcogs_img_pro_cp_default_max_image_size'));
        $max_size_fieldset->setDesc(lang('jcogs_img_pro_cp_default_max_image_size_desc'));
        $max_size_fieldset->getField('img_cp_default_max_image_size', 'text')
            ->setValue($current_settings['img_cp_default_max_image_size'] ?? '4');

        // Add submit button
        $submit_button = $form->getButton('submit');
        $submit_button->setType('submit')
            ->setText(lang('jcogs_img_pro_cp_save_image_settings'))
            ->setValue('image_defaults')
            ->setWorking(lang('jcogs_img_pro_cp_saving'));

        return $form;
    }

    /**
     * Handle image defaults form submission with enhanced parameter package validation
     */
    private function _handleImageDefaults()
    {
        $posted_data = $_POST;
        
        // ==========================================
        // ENHANCED: Use parameter package validation for mapped settings
        // ==========================================
        $validation_result = $this->cp_form_integration->validateCPFormSubmission($posted_data);
        
        if (!$validation_result['valid']) {
            // Show parameter package validation errors
            foreach ($validation_result['errors'] as $field => $error) {
                ee()->session->set_flashdata('message_failure', "Field {$field}: {$error}");
            }
            $this->redirect_to_route('image_defaults');
            return;
        }
        
        // Use processed data from parameter packages (includes validation and normalization)
        $processed_data = $validation_result['processed_data'];
        
        // ==========================================
        // LEGACY: Additional validation for non-mapped fields
        // ==========================================
        $errors = [];
        
        // Validate max dimension (not mapped to packages yet)
        if (isset($posted_data['img_cp_default_max_image_dimension']) && 
            !empty($posted_data['img_cp_default_max_image_dimension']) && 
            !is_numeric($posted_data['img_cp_default_max_image_dimension'])) {
            $errors[] = 'Maximum image dimension must be numeric';
        }
        
        // Validate max size (not mapped to packages yet)
        if (isset($posted_data['img_cp_default_max_image_size']) && 
            !empty($posted_data['img_cp_default_max_image_size']) && 
            !is_numeric($posted_data['img_cp_default_max_image_size'])) {
            $errors[] = 'Maximum image size must be numeric';
        }
        
        // Validate fallback URLs if present
        if (!empty($posted_data['img_cp_fallback_image_remote']) && 
            !filter_var($posted_data['img_cp_fallback_image_remote'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Fallback remote image must be a valid URL';
        }
        
        if (empty($errors)) {
            // Merge processed parameter package data with remaining form data
            $final_data = array_merge($posted_data, $processed_data);
            
            // Save settings using shared service method
            if ($this->save_settings($final_data)) {
                $this->set_success_message('jcogs_img_pro_cp_image_defaults_saved');
            } else {
                ee()->session->set_flashdata('message_failure', lang('jcogs_img_pro_cp_settings_save_failed'));
            }
        } else {
            foreach ($errors as $error) {
                ee()->session->set_flashdata('message_failure', $error);
            }
        }
        
        $this->redirect_to_route('image_defaults');
    }

}
