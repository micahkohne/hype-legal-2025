<?php

/**
 * JCOGS Image Pro - English Language File
 * ========================================
 * Complete language definitions for all Pro features and control panel
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

$lang = [
    // =============================================================================
    // MODULE INFORMATION
    // =============================================================================
    'jcogs_img_pro_module_name'        => 'JCOGS Image Pro',
    'jcogs_img_pro_module_description' => 'Advanced Image Manipulation for EE6/7',
    'jcogs_img_pro_settings'           => 'JCOGS Image Pro Settings',

    // =============================================================================
    // CACHE STAGE DEBUG MESSAGES
    // =============================================================================
    'debug_cache_stage_starting'            => 'Starting cache stage',
    'debug_cache_stage_completed'           => 'Cache stage completed',
    'debug_cache_hit'                       => 'Cache hit for key: %s',
    'debug_cache_hit_continuing_to_output'  => 'Cache hit: Loading from cache and continuing to output generation',
    'debug_cache_file_found'                => 'Cache file found: %s',
    'debug_cache_file_not_found'            => 'Cache file not found: %s',
    'debug_cache_loaded_success'            => 'Successfully loaded from cache: %s',
    'debug_cache_loaded_failed'             => 'Failed to load from cache: %s',
    'debug_cache_no_image_to_save'          => 'No processed image to cache',
    'debug_cache_saved_success'             => 'Successfully saved to cache: %s',
    'debug_cache_saved_failed'              => 'Failed to save to cache: %s',

    // =============================================================================
    // DEBUG TESTING MESSAGES
    // =============================================================================
    'debug_mode_test'                  => 'Debug mode test: debug param = %s',
    'detailed_debug_active'            => 'Detailed debug mode is active',
    'debug_pipeline_stage_timing'      => 'Pipeline stage "%s" completed in %s seconds',

    // =============================================================================
    // SIDEBAR NAVIGATION LANGUAGE STRINGS
    // =============================================================================
    'jcogs_img_pro_nav_title'                    => 'JCOGS Image Pro',
    'jcogs_img_pro_sidebar_title'                => 'Options',
    'jcogs_img_pro_cp_main_settings'             => 'System Settings',
    'jcogs_img_pro_cp_preset_management'         => 'Presets Management',
    'jcogs_img_pro_cp_caching_sidebar_label'     => 'Cache Management',
    'jcogs_img_pro_cp_image_settings'            => 'Image Defaults',
    'jcogs_img_pro_advanced_settings'            => 'Advanced Settings',
    'jcogs_img_pro_cp_license'                   => 'License',
    'jcogs_img_pro_debug_info'                   => 'Debug Information',
    'jcogs_img_pro_debug_php_version'            => 'PHP %s',
    'jcogs_img_pro_debug_ee_version'             => 'EE %s',
    'jcogs_img_pro_cp_action_image'              => 'Action Link Settings',
    'jcogs_img_pro_cp_action_image_title'        => 'Calling JCOGS Image via an Action Link',
    'jcogs_img_pro_cp_action_image_desc'         => 'You can run an Image tag via an Action Link.<br>The action will return the processed image in a form that can be read by an HTML <strong>img</strong> tag via the <strong>src=</strong> attribute.<br>Use the URL given below, with parameters appended as GET variables.<br><h3><strong>%s</strong></h3>',
    'jcogs_img_pro_cp_no_action_found'           => 'An action that will trigger a JCOGS Image processing operation has not been found. Please contact JCOGS Support to resolve.',
    'jcogs_img_pro_cp_action_links_enable'       => 'Enable automatic Action Links',
    'jcogs_img_pro_cp_action_links_enable_desc'  => 'This option converts the links to processed images from a direct URL to an Action Link.<br>Action Links remain active after a template has been processed and saved in a cache, and when called by the loading of the cached template cause JCOGS Image to return the required processed image from the processed image cache: if the processed image is not found in the processed image cache JCOGS Image will regenerate it for you.<br>Action Links ensure that JCOGS Image processed images remain available even if the copy in the processed image cache is removed, and also enable the use of non-perpetual image cache durations with static caching solutions.<br><strong>Note:</strong>Use of Action Links introduces a <u>small</u> performance penalty compared to using regular Image tags, and so if you are not using cached templates it is better for this option to be disabled.',
    'jcogs_img_pro_cp_action_image_desc_general' => 'Action links provide direct URLs for serving processed images through JCOGS Image Pro.',
    'jcogs_img_pro_cp_action_image_usage'        => 'Action Link Usage',
    'jcogs_img_pro_cp_action_image_help'         => 'The action link can be used to serve processed images directly from URLs in your templates.',
    'jcogs_img_pro_version'                      => 'JCOGS Image Pro v%s',

    // =============================================================================
    // FLY OUT MENU LANGUAGE STRINGS
    // =============================================================================
    'jcogs_img_pro_fly_system_settings'                        => 'System Settings',
    'jcogs_img_pro_fly_cache_settings'                         => 'Cache Management',
    'jcogs_img_pro_fly_image_settings'                         => 'Image Defaults',
    'jcogs_img_pro_fly_advanced_settings'                      => 'Advanced Settings',
    'jcogs_img_pro_fly_presets_settings'                       => 'Presets Management',
    'jcogs_img_pro_fly_clear_cache'                            => 'Clear Image Cache',



    // =============================================================================
    // OUTPUT STAGE DEBUG MESSAGES
    // =============================================================================
    'debug_output_stage_starting'           => 'Starting output stage',
    'debug_output_stage_completed'          => 'Output stage completed',
    'debug_output_already_generated'        => 'Output already generated, skipping',
    'debug_output_img_tag_generated'        => 'Generated IMG tag',
    'debug_output_pair_vars_generated'      => 'Generated pair variables output',
    'debug_output_url_only_generated'       => 'Generated URL-only output: %s',
    'debug_responsive_images_generated'     => 'Generated responsive image variants: %d',
    'debug_lazy_loading_enabled'            => 'Lazy loading enabled with mode: %s',
    'debug_lqip_background_added'           => 'Added LQIP background style with URL: %s',

    // =============================================================================
    // INITIALIZE STAGE DEBUG MESSAGES
    // =============================================================================
    'debug_initialize_stage_starting'  => 'Starting initialize stage',
    'debug_initialize_stage_completed' => 'Initialize stage completed',
    'debug_source_image_loaded'        => 'Source image loaded: %s',
    'debug_metadata_extracted'         => 'Image metadata extracted: %dx%d',

    // =============================================================================
    // EARLY CACHE CHECK STAGE DEBUG MESSAGES
    // =============================================================================
    'debug_early_cache_check_starting'          => 'Early cache check: Looking for cached image...',
    'debug_early_cache_check_querying_log'      => 'Checking cache log for key: %s',
    'debug_early_cache_check_miss_continuing'   => 'Early cache check: No cached image found, continuing with processing',
    'debug_early_cache_check_hit'               => 'Early cache check: Found cached image',
    'debug_early_cache_check_file_valid'        => 'Cache file verified and exists: %s',
    'debug_early_cache_metadata_basic_loaded'   => 'Cache metadata loaded for: %s',
    'debug_early_cache_check_completed'         => 'Early cache check completed',
    'debug_cache_disabled_skipping_early_check' => 'Cache disabled (cache="0"), skipping early cache check',
    'debug_cache_disabled_ignoring_existing'    => 'Cache disabled (cache="0"), ignoring existing cache',

    // =============================================================================
    // LOAD SOURCE STAGE DEBUG MESSAGES
    // =============================================================================
    'debug_load_source_starting'       => 'Starting load source stage',
    'debug_load_source_completed'      => 'Load source stage completed',
    'debug_source_loaded_from_url'     => 'Loaded image from URL: %s',
    'debug_source_loaded_from_file'    => 'Loaded image from file: %s',
    // =============================================================================
    'debug_init_stage_starting'        => 'Starting initialize stage',
    'debug_init_stage_completed'       => 'Initialize stage completed',
    'debug_init_params_processed'      => 'Parameters processed: %d parameters',
    'debug_init_cache_key_generated'   => 'Cache key generated: %s',

    // =============================================================================
    // LOAD SOURCE STAGE DEBUG MESSAGES
    // =============================================================================
    'debug_load_stage_starting'        => 'Starting load source stage',
    'debug_load_stage_completed'       => 'Load source stage completed',
    'debug_load_special_type_handled'  => 'Handled special image type, skipping normal loading',
    'debug_load_source_found'          => 'Source image found: %s',
    'debug_load_source_not_found'      => 'Source image not found: %s',
    'debug_load_source_loaded'         => 'Source image loaded successfully: %dx%d',

    // =============================================================================
    // AUTO-ADJUST DEBUG MESSAGES
    // =============================================================================
    'jcogs_img_pro_auto_adjust_active_dimensions'           => 'Auto-adjust active - re-scaling image to maximum dimension of %1$s px',
    'jcogs_img_pro_auto_adjust_active_dimensions_failed'    => 'Auto-adjust failed to rescale image successfully, probably too large to process, baling out...',
    'jcogs_img_pro_auto_adjust_active_size'                 => 'Auto-adjust active - re-scaling image to reduce image filesize to below %1$s MB',
    'jcogs_img_pro_auto_adjust_active_success'              => 'Auto-adjust succeeded: image to be used in processing has maximum dimension of %1$d px and adjusted filesize of %2$0.2f MB. Auto-adjust processing took %3$0.2f seconds.',
    'jcogs_img_pro_auto_adjust_active_size_failed'          => 'Auto-adjust failed to rescale image successfully, probably too large to process, baling out...',

    // =============================================================================
    // PROCESS IMAGE STAGE DEBUG MESSAGES
    // =============================================================================
    'debug_process_stage_starting'     => 'Starting process image stage',
    'debug_process_stage_completed'    => 'Process image stage completed',
    'debug_process_operation_applied'  => 'Applied operation: %s',
    'debug_process_resize_applied'     => 'Resize applied: %dx%d',
    'debug_process_quality_set'        => 'Quality set to: %d',

    // =============================================================================
    // PIPELINE ORCHESTRATOR DEBUG MESSAGES
    // =============================================================================
    'debug_pipeline_starting'          => 'Starting image processing pipeline',
    'debug_pipeline_completed'         => 'Pipeline processing completed',
    'debug_pipeline_stage_executing'   => 'Executing stage: %s',
    'debug_pipeline_stage_skipped'     => 'Skipped stage: %s',
    'debug_pipeline_error'             => 'Pipeline error in stage %s: %s',
    
    // New pipeline debug messages
    'jcogs_img_pro_pipeline_start'          => 'Pipeline starting with %s stages',
    'jcogs_img_pro_pipeline_stage_start'    => 'Starting pipeline stage: %s',
    'jcogs_img_pro_pipeline_stage_complete' => 'Completed pipeline stage: %s',
    'jcogs_img_pro_pipeline_error'          => 'Pipeline error: %s',
    'jcogs_img_pro_pipeline_exception'      => 'Pipeline exception: %s',
    'jcogs_img_pro_pipeline_early_exit'     => 'Pipeline early exit requested',
    'jcogs_img_pro_pipeline_complete'       => 'Pipeline completed. Status: %s',
    'debug_crop_decision'                   => 'DEBUG: Crop decision: %s (source aspect: %s, target aspect: %s, diff: %s)',
    
    // =============================================================================
    // USER-FRIENDLY TEMPLATE DEBUG MESSAGES (FOR EE TEMPLATE DEBUG OUTPUT)
    // =============================================================================
    
    // Initialize Stage Messages
    'jcogs_img_debug_processing_start'     => 'Image processing starting for image #%d',
    'jcogs_img_debug_working_with_source'  => 'Working with source image: %s',
    'jcogs_img_debug_init_complete'        => 'Initialisation complete - proceeding to check cache and process image',
    
    // Process Image Stage Messages
    'jcogs_img_debug_cropping_primary'     => 'Cropping primary image (crop mode: %s)',
    'jcogs_img_debug_smart_scale_resize'   => 'Smart scale crop requires primary image to be resized, using following dimensions: %dx%d',
    'jcogs_img_debug_processing_complete'  => 'Image processing completed in %s seconds',
    
    // Cache Stage Messages
    'jcogs_img_debug_cache_written'        => 'Processed image file written to cache',
    'jcogs_img_debug_cache_save_time'      => 'Image saved to cache in %s seconds',
    
    // Output Stage Messages
    'jcogs_img_debug_post_processing_start' => 'Begin post-processing of image',
    'jcogs_img_debug_post_processing_complete' => 'Post-processing completed in %s seconds',
    'jcogs_img_debug_generating_outputs'   => 'Generating tag outputs',
    'jcogs_img_debug_generation_complete'  => 'Generation completed for output in %s seconds',
    
    // Pipeline Overall Messages
    'jcogs_img_debug_overall_complete'     => 'Generation completed for image #%d in %s seconds',
    
    // =============================================================================
    // Additional language strings for Legacy compatibility
    // =============================================================================
    'jcogs_img_image_cache_is_empty'                           => 'Cache is empty',
    'jcogs_img_no_parameters_for_add_text_overlay'             => 'No text parameters provided for text overlay, skipping text overlay processing',
    'jcogs_img_cp_cache_performance_desc_cache'                => '%8$s<ul><li>The cache is stored %7$s.</li><li>There are %1$d processed images in %2$s.</li></ul>',
    'jcogs_img_cp_cache_performance_desc_cache_single'         => 'the JCOGS Image cache folder',
    'jcogs_img_cp_cache_performance_desc_cache_many'           => '%1$d JCOGS Image cache folders',
    'jcogs_img_cp_cache_clear_desc'                           => 'Clear %d cache files',
    'jcogs_img_cp_cache_clear_desc_empty'                     => 'No cache files to clear',
    'jcogs_img_cp_cache_clear_button'                         => '<a class="btn action" href="%s">Clear Cache</a>',
    'jcogs_img_cp_cache_clear'                                => 'Clear Cache',
    'jcogs_img_na'                                            => 'N/A',
    
    // These are for Legacy compatibility but implemented using lang() function in Pro
    'nav_support_page'                                         => 'Documentation & Support',
    'jcogs_img_cp_cache_status'                                => 'Image Cache Status',
    'jcogs_img_cp_cache_controls'                              => 'Image Cache Controls',
    'jcogs_img_cp_cache_settings'                              => 'Image Cache Settings',
    'jcogs_img_cp_cache_file_system'                           => 'Cache Storage Location Options',
    'jcogs_img_cp_choose_flysystem_adapter'                    => 'File System Adapter',
    'jcogs_img_cp_choose_flysystem_adapter_desc'               => 'JCOGS Image by default writes its processed images to a cache folder within the server\'s local file system. However you can also choose to write its processed images to one of several cloud storage providers, such as AWS or Cloudflare. <strong>Cloud storage connections need to be configured before they can be used.</strong> Add configuration information using the <strong>File System Adapter</strong> settings options below. All adapters with validated configurations will be listed in this drop-down.',
    'jcogs_img_cp_flysystem_local_adapter'                     => 'Local File System',
    'jcogs_img_cp_flysystem_s3_adapter'                        => 'AWS S3',
    'jcogs_img_cp_flysystem_r2_adapter'                        => 'Cloudflare R2',
    'jcogs_img_cp_flysystem_dospaces_adapter'                  => 'DigitalOcean Spaces',
    
    // Add connection form language keys
    'jcogs_img_cp_choose_flysystem_adapter_name'               => 'Connection Name',
    'jcogs_img_cp_choose_flysystem_adapter_name_desc'          => 'Enter a name for this cache storage connection. This name will be used to identify the connection in the adapter dropdown.',
    'jcogs_img_cp_choose_flysystem_adapter_type'               => 'Adapter Type',
    'jcogs_img_cp_choose_flysystem_adapter_type_desc'          => 'Choose the type of file system adapter you want to configure.',
    'jcogs_img_cp_cdn_path_prefix'                             => 'CDN Path Prefix',
    'jcogs_img_cp_cdn_path_prefix_desc'                        => 'Optional prefix to add to URLs when serving images from this storage location (e.g. CDN URL).',
    'jcogs_img_cp_enable_cache_auto_manage'                    => 'Enable Cache Auto-Management',
    'jcogs_img_cp_enable_cache_auto_manage_desc'               => 'When <a href="https://jcogs.net/documentation/jcogs_img/jcogs_img-advanced-topics" target="_blank">Image Cache Auto-Management</a> is enabled, JCOGS Image will monitor the <a href="https://docs.expressionengine.com/latest/control-panel/file-manager/file-manager.html" target="_blank">File Manager</a> system; when an image that has been used to generate one or more images in the JCOGS Image cache is modified, those processed images will be cleared from the cache. This is to ensure that any changes made to the source image are reflected on the site.',
    'jcogs_img_cp_default_cache_directory'                     => 'File System Cache Directory',
    'jcogs_img_cp_default_cache_directory_desc'                => 'The directory within the server\'s filing system that JCOGS Image will use to store processed images. Can be overridden by the <span style="color:var(--ee-success-dark);font-weight:bold">cache_dir=</span> parameter within a tag.<br> <strong>Note:</strong> Since the image cache path needs to be one that is within your site\'s webroot JCOGS Image assumes that the a path entered here is <strong>relative to your webroot</strong>. <br>If the path you specify does not exist JCOGS Image will attempt to create it.',
    'jcogs_img_cp_default_cache_directory_placeholder'         => 'There needs to be a default directory specified.',
    'jcogs_img_cp_class_always_output_full_urls'               => 'Always output full URLs',
    'jcogs_img_cp_class_always_output_full_urls_desc'          => 'When enabled, all image URLs will include the full domain path',
    'jcogs_img_cp_path_prefix'                                 => 'Set CDN remote path prefix',
    'jcogs_img_cp_path_prefix_desc'                            => 'Enter the CDN path prefix for accessing cached images',
    'jcogs_img_cp_cache_performance_desc_cache_empty'          => '<div  style="font-size:15px;padding-top:0.4rem;padding-bottom:0.4rem;"><span class=\'status-tag st-warning\'>CACHE EMPTY</span></div>',
    'jcogs_img_cp_cache_performance_desc_cache_operational'    => '<div  style="font-size:15px;padding-top:0.4rem;padding-bottom:0.4rem;"><span class=\'status-tag st-open\'>CACHE OPERATIONAL</span></div>',
    'jcogs_img_cache_location_status'                          => 'This location contains %1$d processed image%2$s.',
    'jcogs_img_cp_cache_location_title'                        => 'Manage Cache Locations',
    'jcogs_img_cp_cache_intro_text'                            => '<strong>Cache Audit:</strong> Running a cache audit will remove from a processed image cache any images that have existed there for longer than the cache duration set for them when they were saved, and ensure that the cache records are aligned with the content of the associated processed image cache folder: it will save disk space but will not otherwise affect Image\'s operations.<br><strong>Cache Clear:</strong> Clearing a processed image cache will remove all the images from the cache. This action will save disk space on the currently active File System Adapter but will require JCOGS Image to reprocess each image when it is next requested by a template tag; this additional image processing may temporarily affect site performance.',
    'jcogs_img_cp_cache_location_audit'                        => 'Running a cache audit will remove from a processed image cache any images that have existed there for longer than the cache duration set for them when they were saved, and ensure that the cache records are aligned with the content of the associated processed image cache folder: it will save disk space but will not otherwise affect Image\'s operations.',
    'jcogs_img_cp_cache_location_audit_icon'                   => '<i class="fa-solid fa-rotate"></i> Audit this Cache Location',
    'jcogs_img_cp_cache_location_clear'                        => 'Clearing a processed image cache will remove all the images from the cache. This action will save disk space on the currently active File System Adapter but will require JCOGS Image to reprocess each image when it is next requested by a template tag; this additional image processing may temporarily affect site performance.',
    'jcogs_img_cp_cache_location_clear_icon'                   => '<i class="fa-solid fa-eraser"></i> Clear this Cache Location',
    'jcogs_img_cp_cloud_adapter_config_not_valid'              => 'CONFIG NOT VALID',
    'jcogs_img_cp_cloud_adapter_config_valid'                  => 'CONFIG VALID',
    
    // =============================================================================
    // ADDITIONAL CACHE MANAGEMENT LANGUAGE STRINGS
    // =============================================================================
    'jcogs_img_cp_cache_audit_text'                            => 'Audit',
    'jcogs_img_cp_cache_clear_text'                            => 'Clear',
    'jcogs_img_cp_cache_operational'                           => 'CACHE OPERATIONAL',
    'jcogs_img_cp_cache_empty'                                 => 'CACHE EMPTY',
    'jcogs_img_cp_cache_operational_desc'                      => 'The cache is stored locally on the server.',
    'jcogs_img_cp_cache_count_desc'                            => 'There are %d processed images in %d JCOGS Image cache folders.',
    'jcogs_img_cp_cache_empty_adapter_desc'                    => 'The cache is using %s storage adapter.',
    
    // Auto-Manage Cache Notification Messages
    'jcogs_img_pro_cp_auto_manage_would_have_fired'            => 'Cache Auto-Management Impact Detected',
    'jcogs_img_pro_cp_auto_manage_would_have_fired_desc'       => 'A file has been updated that affects %d cached image(s). Cache Auto-Management is currently disabled, so these cached images were not automatically removed. Enable Cache Auto-Management in the cache settings to automatically clean up affected cache entries when source files are modified.',
    'jcogs_img_cp_cache_empty_unused_desc'                     => 'The cache has not been used since it was last reset.',
    'jcogs_img_cp_cache_enable_audit'                          => 'Enable Cache Audit',
    'jcogs_img_cp_cache_enable_audit_desc'                     => 'When enabled, the cache audit system will automatically monitor and clean up expired cache entries.',
    'jcogs_img_cp_cache_status_display'                        => 'Cache Status',
    'jcogs_img_cp_s3_status_desc'                              => 'Current validation status for AWS S3 configuration',
    'jcogs_img_cp_r2_status_desc'                              => 'Current validation status for Cloudflare R2 configuration',
    'jcogs_img_cp_dospaces_status_desc'                        => 'Current validation status for DigitalOcean Spaces configuration',
    'jcogs_img_cp_s3_key'                                      => 'S3 Key',
    'jcogs_img_cp_s3_key_desc'                                 => 'Enter your AWS S3 Key',
    'jcogs_img_cp_s3_secret'                                   => 'S3 Secret',
    'jcogs_img_cp_s3_secret_desc'                              => 'Enter your AWS S3 Secret',
    'jcogs_img_cp_s3_region'                                   => 'S3 Region',
    'jcogs_img_cp_s3_region_desc'                              => 'Select the region for your AWS S3 Bucket',
    'jcogs_img_cp_s3_bucket'                                   => 'S3 Bucket Name',
    'jcogs_img_cp_s3_bucket_desc'                              => 'Enter the name of your AWS S3 Bucket',
    'jcogs_img_cp_s3_path'                                     => 'S3 Path',
    'jcogs_img_cp_s3_path_desc'                                => 'Enter the path inside your AWS S3 Bucket',
    'jcogs_img_cp_s3_url'                                      => 'S3 Url',
    'jcogs_img_cp_s3_url_desc'                                 => 'Enter the url used to access your AWS S3 Bucket',
    'jcogs_img_cp_r2_account_id'                               => 'R2 Account ID',
    'jcogs_img_cp_r2_account_id_desc'                          => 'Enter your Cloudflare R2 Account ID',
    'jcogs_img_cp_r2_key'                                      => 'R2 Key',
    'jcogs_img_cp_r2_key_desc'                                 => 'Enter your Cloudflare R2 Key',
    'jcogs_img_cp_r2_secret'                                   => 'R2 Secret',
    'jcogs_img_cp_r2_secret_desc'                              => 'Enter your Cloudflare R2 Secret',
    'jcogs_img_cp_r2_bucket'                                   => 'R2 Bucket Name',
    'jcogs_img_cp_r2_bucket_desc'                              => 'Enter the name of your Cloudflare R2 Bucket',
    'jcogs_img_cp_r2_path'                                     => 'R2 Path',
    'jcogs_img_cp_r2_path_desc'                                => 'Enter the path inside your Cloudflare R2 Bucket',
    'jcogs_img_cp_r2_url'                                      => 'R2 Url',
    'jcogs_img_cp_r2_url_desc'                                 => 'Enter the url used to access your Cloudflare R2 Bucket',
    'jcogs_img_cp_dospaces_key'                                => 'DigitalOcean Key',
    'jcogs_img_cp_dospaces_key_desc'                           => 'Enter your DigitalOcean Key',
    'jcogs_img_cp_dospaces_secret'                             => 'DigitalOcean Secret',
    'jcogs_img_cp_dospaces_secret_desc'                        => 'Enter your DigitalOcean Secret',
    'jcogs_img_cp_dospaces_region'                             => 'DigitalOcean Region',
    'jcogs_img_cp_dospaces_region_desc'                        => 'Select the region for your DigitalOcean Space',
    'jcogs_img_cp_dospaces_space'                              => 'DigitalOcean Space Name',
    'jcogs_img_cp_dospaces_space_desc'                         => 'Enter the name of your DigitalOcean Space',
    'jcogs_img_cp_dospaces_path'                               => 'DigitalOcean Path',
    'jcogs_img_cp_dospaces_path_desc'                          => 'Enter the path inside your DigitalOcean Space',
    'jcogs_img_cp_dospaces_url'                                => 'DigitalOcean Url',
    'jcogs_img_cp_dospaces_url_desc'                           => 'Enter the url used to access your DigitalOcean Space',
    
    // =============================================================================
    // IMAGE DEFAULTS PAGE LANGUAGE STRINGS  
    // =============================================================================
    'jcogs_img_pro_cp_save_image_settings'                     => 'Save Image Settings',
    
    // Image Format Options Section
    'jcogs_img_pro_cp_image_format_options'                    => 'Image Format Options',
    'jcogs_img_pro_cp_default_image_format'                    => 'Default image format',
    'jcogs_img_pro_cp_default_image_format_desc'               => 'Choose the default image format to use for processed images.<br> Can be overridden by using the <span style="color:var(--ee-success-dark);font-weight:bold">save_type=</span> parameter within a tag.<br> Choosing <strong>Source</strong> tells JCOGS Image to try and use source image format for output format.<br> The image formats listed are those that your server is able to write/create.<br> <strong>Note: Not all image formats can be rendered by browsers.</strong> If you choose a format that a browser visiting your site cannot render, JCOGS Image will, for that visitor only, substitute an image format that their browser can <strong>can</strong> work with - usually JPG.',
    
    // Image Operational Defaults Section
    'jcogs_img_pro_cp_image_operational_defaults'              => 'Image Operational Defaults',
    'jcogs_img_pro_cp_enable_svg_passthrough'                  => 'Enable SVG Passthrough',
    'jcogs_img_pro_cp_enable_svg_passthrough_desc'             => 'When enabled JCOGS Image will automatically pass any SVG format image through to the output without processing it: if specified,  caching and size adjustment changes will be applied to the image. This option can be over-ridden by use of the  <span style="color:var(--ee-success-dark);font-weight:bold">svg_passthrough=</span> parameter within a tag.',
    'jcogs_img_pro_cp_ignore_save_type_for_animated_gifs'      => 'Preserve animated gif format',
    'jcogs_img_pro_cp_ignore_save_type_for_animated_gifs_desc' => 'When enabled JCOGS Image will ignore <span style="color:var(--ee-success-dark);font-weight:bold">save_type=</span> settings when processing an image source file containing an animated gif.',
    'jcogs_img_pro_cp_allow_scale_larger_default'              => 'Allow Scale Larger parameter default setting',
    'jcogs_img_pro_cp_allow_scale_larger_default_desc'         => 'When the <a href="https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-allow-scale-larger" target="_blank">Allow Scale Larger</a> default is set to enabled JCOGS Image will enlarge images to fit dimensions given if the source image is smaller than the final dimensions required by the settings in a JCOGS Image tag. The initial default settings for this option is disabled. This setting is over-ridden by the value given by a <span style="color:var(--ee-success-dark);font-weight:bold">allow_scale_larger=</span> parameter within a tag.',
    'jcogs_img_pro_cp_enable_auto_sharpen'                     => 'Auto Sharpening parameter default setting',
    'jcogs_img_pro_cp_enable_auto_sharpen_desc'                => 'When the <a href="https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-auto-sharpen" target="_blank">Auto Sharpening</a> default is set to enabled JCOGS Image will automatically apply the auto-sharpen filter to every image. The auto-sharpen filter increases the sharpness of images that have been reduced in size during manipulations; the greater the reduction in size the greater the degree of sharpening applied. This option can be over-ridden by use of the  <span style="color:var(--ee-success-dark);font-weight:bold">auto_sharpen=</span> parameter within a tag.',
    'jcogs_img_pro_cp_html_decoding_enabled'                   => 'Add HTML Decoding attribute to Image Tags',
    'jcogs_img_pro_cp_html_decoding_enabled_desc'              => 'When enabled, JCOGS Image will add the <strong>decoding="async"</strong> attribute to the <img> tags it generates. This attribute is used by browsers to control how images are decoded and rendered, which can improve page load performance. The default value is enabled, but you can disable it if you prefer not to use this feature.',
    'jcogs_img_pro_cp_enable_lazy_loading'                     => 'Enable Lazy Loading',
    'jcogs_img_pro_cp_enable_lazy_loading_desc'                => 'When the <a href="https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-lazy" target="_blank">Lazy</a> parameter default is set to enabled JCOGS Image will set the <strong><img></strong> tags it creates to lazy-load by default.  Can be overridden by using the <span style="color:var(--ee-success-dark);font-weight:bold">lazy=</span> parameter within a tag.',
    'jcogs_img_pro_cp_lazy_loading_mode'                       => 'Choose default lazy loading mode.',
    'jcogs_img_pro_cp_lazy_loading_mode_desc'                  => 'When lazy-loading is enabled JCOGS Image will initially load an image tag with a smaller placeholder image prior to its the "lazy" replacement by the full-quality image. Placeholder images can be either a <strong>Low Quality Image Placeholder (LQIP)</strong> or a <strong>Dominant Colour Field</strong>.<ul><li>LQIP is a low-resolution but recognisable version of the processed image that is typically about 25% of the size of the processed image.</li><li>Dominant Colour Field replaces the image with a colour-field the same size as the processed image with the colour set to the most-common colour found in the processed image.</li><li>A third option - HTML5 - is offered that does not add the placeholder substitution but does add the HTML5 <strong>loading="lazy"</strong> parameter to the completed image tag. This option gives marginally better performance compared to disabling lazy loading on modern browsers where you cannot be sure that javascript will be enabled on the target browser.</ul>',
    'jcogs_img_pro_cp_lazy_progressive_enhancement'            => 'Enable progressive enhancement mode.',
    'jcogs_img_pro_cp_lazy_progressive_enhancement_desc'       => 'When either Javascript LQIP or Javascript Dominant Colour modes are chosen for Lazy Loading, this option adds some additional code to the rendered template to ensure that the images generated will render correctly in browsers where javascript support is not present or not enabled (<a href="https://gds.blog.gov.uk/2013/10/21/how-many-people-are-missing-out-on-javascript-enhancement/" target="_blank">fewer than 1% of web users</a>). <strong>It is strongly recommended that you leave this option enabled.</strong> Disabling this option will speed the loading of pages slightly, but will also result in users with javascript disabled seeing degraded images; if absolute speed in modern browsers is important, a better solution would be to select the HTML5 option in the Lazy Loading Mode selector above. <br>This option has no effect if either of the Background modes or the HTML5 mode are chosen, or if Lazy Loading is disabled.',
    'jcogs_img_pro_cp_default_preload_critical'                => 'Enable critical image preloading by default',
    'jcogs_img_pro_cp_default_preload_critical_desc'           => 'When enabled, images will be marked for preloading by default when no explicit <span style="color:var(--ee-success-dark);font-weight:bold">preload=</span> parameter is specified. This can improve loading performance for above-the-fold images that are critical to the user experience. Images marked for preloading are given priority by the browser and loaded earlier in the page lifecycle. <strong>Note:</strong> Only enable this for sites where most images are critical - overuse of preloading can actually hurt performance by competing with other critical resources.',
    'jcogs_img_pro_cp_enable_default_fallback_image'           => 'Set a default fallback image',
    'jcogs_img_pro_cp_enable_default_fallback_image_desc'      => 'Set a default image (or colour field) to use to render tags where you fail to specify a src or fallback_src or when sources specified do not map to valid picture files.<br>Choose one of: <ul><li>filling the image space with a colour field</li><li>using a local image</li><li>using a remote image</li></ul>',
    
    // Lazy Loading Options
    'jcogs_img_pro_cp_lazy_lqip'                               => 'Low Quality Image Placeholder',
    'jcogs_img_pro_cp_lazy_dominant_color'                     => 'Dominant Colour Field',
    'jcogs_img_pro_cp_lazy_js_lqip'                            => 'Javascript Method Low Quality Image Placeholder',
    'jcogs_img_pro_cp_lazy_js_dominant_color'                  => 'Javascript Method Dominant Colour Field',
    'jcogs_img_pro_cp_lazy_html5'                              => 'Add HTML5 loading="lazy" only',
    
    // Fallback Options
    'jcogs_img_pro_cp_no_fallback'                             => 'No default fallback image',
    'jcogs_img_pro_cp_fallback_color'                          => 'Use a colour field',
    'jcogs_img_pro_cp_fallback_local'                          => 'Use a local fallback image',
    'jcogs_img_pro_cp_fallback_remote'                         => 'Use a remote fallback image',
    
    // Operational Limits Section
    'jcogs_img_pro_cp_operational_limits'                      => 'Operational Limits',
    'jcogs_img_pro_cp_enable_auto_adjust'                      => 'Enable Auto-adjust for Oversized Source Images',
    'jcogs_img_pro_cp_enable_auto_adjust_desc'                 => 'If selected this enables JCOGS Image\'s ability to attempt to auto-adjust the file size and dimensions of <strong>oversized source images</strong>.<br><strong>Note:</strong> A source image is considered to be oversized if it exceeds either your choice for the a maximum dimension for the source image or your choice for the maximum file size for the source image.<br>While this option is enabled JCOGS Image will try to rescale source images that exceed either of the set limits and so create a smaller interim version of the image file that is compliant with the limits which will then be used as the base for any image transformations requested.<br>Using the smaller interim version may improve overall processing speed and mitigate problems that would otherwise be caused by hitting php resource limits on your server.<br>Images are tested first against the image dimension limit (if set) and then against the size limit.<br> <strong>Note:</strong> if this options is disabled, the constraint on maximum file size will still be applied, but rather than triggering an attempt to rescale the image, the limit will cause Image to not attempt to process the image.',
    'jcogs_img_pro_cp_default_max_image_dimension'             => 'Maximum dimension limit for source image processing (px)',
    'jcogs_img_pro_cp_default_max_image_dimension_desc'        => 'Set a maximum dimension for <strong>source</strong> image files.<br>JCOGS Image will attempt to temporarily rescale any source file that is larger than the set limit in either dimension such that its maximum dimension equals the limit value (the image aspect ratio is preserved).<br>Setting this limit can help to mitigate problems caused by php resource limits on your server.<br>The limit is expressed as an integer pixel value: to enter a value use an integer value (e.g. 2500).<br>To disable this option set this value to zero (which is the default value).',
    'jcogs_img_pro_cp_default_max_image_size'                  => 'Maximum source image size (MB)',
    'jcogs_img_pro_cp_default_max_image_size_desc'             => 'Set a maximum filesize for <strong>source</strong> image files.<br> If <strong>Auto-adjust for Oversized Source Images</strong> is enabled, JCOGS Image will attempt to temporarily rescale any source image with a filesize larger than the set limit to one with a filesize smaller than the limit. Otherwise if a source file has a filesize larger than the limit set it will not be processed.<br>Enter an integer value equal to the maximum size in Mbytes an image file may be (e.g. 4 - the default value).',

    // =============================================================================
    // ADVANCED SETTINGS PAGE LANGUAGE STRINGS (ADDITIONAL)
    // =============================================================================
    'jcogs_img_pro_cp_save_advanced_settings'                  => 'Save Advanced Settings',
    'jcogs_img_pro_cp_max_image_height'                        => 'Maximum Image Height',
    'jcogs_img_pro_cp_max_image_height_desc'                   => 'Maximum allowed height for processed images in pixels',
    'jcogs_img_pro_cp_max_image_width_desc'                    => 'Maximum allowed width for processed images in pixels',

    // =============================================================================
    // MAIN CONTROL PANEL LANGUAGE STRINGS
    // =============================================================================
    'jcogs_img_pro_cp_global_settings'                         => 'Global Settings',
    'jcogs_img_pro_cp_enable_addon'                            => 'Enable JCOGS Image Pro',
    'jcogs_img_pro_cp_enable_addon_desc'                       => 'When enabled, JCOGS Image Pro will process images. When disabled, all processing is bypassed.',
    'jcogs_img_pro_cp_debug_settings'                          => 'Debug Settings',
    'jcogs_img_pro_cp_enable_debug'                            => 'Enable Debug Mode',
    'jcogs_img_pro_cp_enable_debug_desc'                       => 'When enabled, detailed debug information will be logged',
    'jcogs_img_pro_cp_performance_settings'                    => 'Performance Settings',
    'jcogs_img_pro_cp_max_memory'                              => 'Maximum Memory Usage',
    'jcogs_img_pro_cp_max_memory_desc'                         => 'Maximum memory allowed for image processing operations',
    'jcogs_img_pro_cp_cache_settings'                          => 'Cache Settings',
    'jcogs_img_pro_cp_cache_directory'                         => 'Cache Directory',
    'jcogs_img_pro_cp_cache_directory_desc'                    => 'The directory where JCOGS Image Pro will store processed images. Path is relative to your site root.',
    'jcogs_img_pro_cp_image_defaults'                          => 'Image Defaults',
    'jcogs_img_pro_cp_advanced_settings'                       => 'Advanced Settings',
    'jcogs_img_pro_cp_save_settings'                           => 'Save Settings',
    
    // =============================================================================
    // ADVANCED SETTINGS PAGE LANGUAGE STRINGS
    // =============================================================================
    // Performance Section
    'jcogs_img_pro_cp_memory_limit_override'                   => 'Memory Limit Override',
    'jcogs_img_pro_cp_memory_limit_override_desc'              => 'Override PHP memory limit for image processing (e.g., 256M, 512M)',
    'jcogs_img_pro_cp_max_execution_time'                      => 'Max Execution Time',
    'jcogs_img_pro_cp_max_execution_time_desc'                 => 'Maximum execution time for image processing (seconds)',
    'jcogs_img_pro_cp_enable_performance_monitoring'           => 'Enable Performance Monitoring',
    'jcogs_img_pro_cp_enable_performance_monitoring_desc'      => 'Track detailed performance metrics for optimization',
    
    // Image Processing Section
    'jcogs_img_pro_cp_image_processing'                        => 'Image Processing',
    'jcogs_img_pro_cp_processing_library'                      => 'Image Processing Library',
    'jcogs_img_pro_cp_processing_library_desc'                 => 'Choose the image processing library to use',
    'jcogs_img_pro_cp_auto_detection'                          => 'Automatic Detection',
    'jcogs_img_pro_cp_gd_library'                              => 'GD Library',
    'jcogs_img_pro_cp_imagick'                                 => 'ImageMagick',
    'jcogs_img_pro_cp_default_quality'                         => 'Default Quality Setting',
    'jcogs_img_pro_cp_default_quality_desc'                    => 'Default quality for processed images (1-100)',
    'jcogs_img_pro_cp_enable_progressive_jpeg'                 => 'Enable Progressive JPEG',
    'jcogs_img_pro_cp_enable_progressive_jpeg_desc'            => 'Create progressive JPEG images for better loading',
    
    // Cache Configuration Section
    'jcogs_img_pro_cp_cache_configuration'                     => 'Cache Configuration',
    'jcogs_img_pro_cp_cache_expiry_time'                       => 'Cache Expiry Time',
    'jcogs_img_pro_cp_cache_expiry_time_desc'                  => 'Cache expiry time in seconds (0 = never expire)',
    'jcogs_img_pro_cp_max_cache_size'                          => 'Max Cache Size',
    'jcogs_img_pro_cp_max_cache_size_desc'                     => 'Maximum cache size in MB (0 = unlimited)',
    'jcogs_img_pro_cp_enable_cache_compression'                => 'Enable Cache Compression',
    'jcogs_img_pro_cp_enable_cache_compression_desc'           => 'Compress cached images to save space',
    
    // Security Section
    'jcogs_img_pro_cp_security'                                => 'Security',
    'jcogs_img_pro_cp_allowed_image_types'                     => 'Allowed Image Types',
    'jcogs_img_pro_cp_allowed_image_types_desc'                => 'Comma-separated list of allowed image extensions',
    'jcogs_img_pro_cp_max_image_dimensions'                    => 'Max Image Dimensions',
    'jcogs_img_pro_cp_max_image_dimensions_desc'               => 'Maximum width/height for processed images (pixels)',
    'jcogs_img_pro_cp_max_file_size'                           => 'Max File Size',
    'jcogs_img_pro_cp_max_file_size_desc'                      => 'Maximum file size for image processing (MB)',
    
    // Error Handling Section
    'jcogs_img_pro_cp_error_handling'                          => 'Error Handling',
    'jcogs_img_pro_cp_error_handling_mode'                     => 'Error Handling Mode',
    'jcogs_img_pro_cp_error_handling_mode_desc'                => 'How to handle image processing errors',
    'jcogs_img_pro_cp_graceful_handling'                       => 'Graceful (return original image)',
    'jcogs_img_pro_cp_strict_handling'                         => 'Strict (show error message)',
    'jcogs_img_pro_cp_silent_handling'                         => 'Silent (return blank)',
    'jcogs_img_pro_cp_enable_error_logging'                    => 'Enable Error Logging',
    'jcogs_img_pro_cp_enable_error_logging_desc'               => 'Log image processing errors to system log',
    'jcogs_img_pro_cp_fallback_image_url'                      => 'Fallback Image URL',
    'jcogs_img_pro_cp_fallback_image_url_desc'                 => 'URL to fallback image when processing fails',
    
    // Advanced Settings Messages
    'jcogs_img_pro_cp_advanced_settings_saved'                 => 'Advanced settings saved successfully',

    // Advanced Options Toggle
    'jcogs_img_pro_cp_view_advanced_options'                   => 'View Advanced Options',
    'jcogs_img_pro_cp_view_advanced_options_desc'              => 'Use this to view some Advanced Options for JCOGS Image processing: be careful how you change these values - in most cases the default values are sufficient for stable / safe use of this add-on.',

    // Core Features Section
    'jcogs_img_pro_cp_core_features'                           => 'Core Image Processing Features',
    'jcogs_img_pro_cp_enable_browser_check'                    => 'Enable active checking of browser capabilities',
    'jcogs_img_pro_cp_enable_browser_check_desc'               => 'When enabled JCOGS Image will actively check the image rendering capabilities of the browser visiting your EE site; if the browser is not able to render the image format you have selected (either via the <code>save_type=</code> parameter, or via the default option for processed image file format) it will change the format of the processed image to be one that the browser can render; if the target image format is one that enables transparency the fallback format will be PNG, otherwise it will be JPG.',
    'jcogs_img_pro_cp_class_consolidation_default'             => 'Enable class / style attribute consolidation',
    'jcogs_img_pro_cp_class_consolidation_default_desc'        => 'When enabled JCOGS Image will separately find and consolidate the contents of all of the class=\'\' and style=\'\' parameters if finds enclosed within a JCOGS Image tag-pair, along with the content of any style=\'\' or class=\'\' parameter specfied within the opening JCOGS Image tag, to ensure that there is at most a single composite class and / or style statement in the final tag produced. JCOGS Image will also remove any class duplicates. By default this function is enabled. You may want to disable the function if you plan to enclose complex HTML structures with multiple class statements within a JCOGS Image tag pair or nest multiple tags with differing class statements for each.',
    'jcogs_img_pro_cp_attribute_variable_expansion_default'    => 'Enable expansion of Image Variables within attribute parameter text',
    'jcogs_img_pro_cp_attribute_variable_expansion_default_desc' => 'When enabled JCOGS Image will expand any Image Variables found within the content of the <span style="color:var(--ee-success-dark);font-weight:bold">attribute=</span> parameter before the output tag is assembled.',

    // Filename Configuration Section
    'jcogs_img_pro_cp_filename_configuration'                  => 'Filename Configuration',
    'jcogs_img_pro_cp_default_filename_separator'              => 'Cache filename separator character(s)',
    'jcogs_img_pro_cp_default_filename_separator_desc'         => 'The filenames used for processed images have three components (original filename, cache duration marker, image processing options marker). To be able to separate out these three elements the filename needs to include a couple of separator markers.<br>If the filename separator used is also used in the filenames of the images you are processing some problems may arise relating to the retrieval of cached images.<br>To avoid such naming conflicts you can change the separator used here.<br>The separator can be any sequence of characters, but it cannot include <strong>spaces</strong> or any <strong>reserved characters</strong>: <code> !*\'();:@&=+$,/?#[])</code>',
    'jcogs_img_pro_cp_default_max_source_filename_length'      => 'Maximum length of processed image filename',
    'jcogs_img_pro_cp_default_max_source_filename_length_desc' => 'Some servers have a limit on the length of a filename; if you hit such a limit reduce this number. The change simply shortens the reflection of the filename of the source file in the name given to the processed image. To avoid clashes, when a filename is shortened a random number is added to the shortened string.',
    'jcogs_img_pro_cp_include_source_in_filename_hash'         => 'Include path to image source in processed filename hash',
    'jcogs_img_pro_cp_include_source_in_filename_hash_desc'    => 'When enabled this includes the path to the source image in the processed image hash. This option allows you to differentiate between source images that share the same name but come from different locations.<br><b>Note:</b> when the status of this option is changed it will it will effectively invalidate all existing processed images stored in your file cache(s), requiring all these images to be reprocessed.',

    // Action Link Configuration Section
    'jcogs_img_pro_cp_action_link_configuration'               => 'Action Link Configuration',
    'jcogs_img_pro_cp_append_path_to_action_links'             => 'Append path to processed image to Action Link',
    'jcogs_img_pro_cp_append_path_to_action_links_desc'        => 'When enabled, this option causes a url-encoded version of the link to the image referenced by an Action Link to be included as an additional GET variable in the action link. This is a non-functional change but maybe of utility to developers wishing to manipulate action links in server level caching systems.',

    // CE Image Integration Section
    'jcogs_img_pro_cp_ce_image_integration'                    => 'CE Image Integration',
    'jcogs_img_pro_cp_ce_image_remote_dir'                     => 'CE Image Remote Image Cache Directory',
    'jcogs_img_pro_cp_ce_image_remote_dir_desc'                => 'The directory used by CE Image to cache copies of remote images: if JCOGS Image cannot retrieve a remote image it will look for it in the CE Image remote image cache (if present) - while not a complete solution to missing remote images, this facility can help with site updates / migrations. Since the path needs to be one that is within your site\'s webroot JCOGS Image assumes that the a path entered here is <strong>relative to your webroot</strong>. <br>The default value is the default used by CE Image - i.e. <strong>images/remote</strong>.<br>To minimise potential problems with JCOGS Image (and other add-ons) please also ensure that your <strong><a href=\'/cp/settings/urls\' target=\'_blank\'>EE \'default base path\'</a></strong> is set correctly.',

    // PHP Resource Management Section
    'jcogs_img_pro_cp_php_resource_management'                 => 'PHP Resource Management',
    'jcogs_img_pro_cp_default_min_php_ram'                     => 'Request specific php memory allocation (Mbytes)',
    'jcogs_img_pro_cp_default_min_php_ram_desc'                => 'Enter a mminimum size for requested php memory allocation: if currently allocated memory is greater than this nothing will change, in other cases the add-on will <strong>temporarily</strong> request allocation of the specified amount from php.<br>Increase this value if your server reports exceeding its memory usage limits - when such happens image processing will fail and may halt the EE system too (resulting in users seeing the "White Screen of Death").<br>If the value entered has no units, it is assumed to represent an amount of memory in MBytes.<br>Image will recognise standard php unit quantifiers if used (e.g. 4G = 4 Gigabytes, 128M = 128 Mbytes).<br>The default value of 64 (Mbytes) is usually ample for most web purposes.',
    'jcogs_img_pro_cp_default_user_agent_string'               => 'User agent string to use for remote file retrieval',
    'jcogs_img_pro_cp_default_user_agent_string_desc'          => 'Specify a user-agent string to report to remote servers when retriveing images from remote locations on the web. The default value works well for most web purposes.',

    // Cache Management Section
    'jcogs_img_pro_cp_cache_management'                        => 'Cache Management',
    'jcogs_img_pro_cp_cache_log_preload_threshold'             => 'Cache Log Preload Threshold',
    'jcogs_img_pro_cp_cache_log_preload_threshold_desc'        => 'Number of cache log entries below which the entire cache will be preloaded. Above this threshold, entries will be loaded on-demand. Recommended: 10000 for most sites.',
    'jcogs_img_pro_cp_current_cache_entries'                   => 'Current Cache Entries',
    'jcogs_img_pro_cp_current_cache_entries_desc'              => 'Number of entries currently in the cache log database. This count is updated when the threshold setting changes or after major cache operations.',
    'jcogs_img_pro_cp_entries'                                 => 'entries',
    'jcogs_img_pro_cp_last_updated'                            => 'last updated',
    'jcogs_img_pro_cp_enable_cache_audit'                      => 'Enable Image Cache Audits',
    'jcogs_img_pro_cp_enable_cache_audit_desc'                 => 'When <a href="https://jcogs.net/documentation/jcogs_img/jcogs_img-advanced-topics" target="_blank">Image Cache Audit</a> is enabled, JCOGS Image will periodically inspect the default image cache folder (and from any other image cache folders specified via use of the <span style="color:var(--ee-success-dark);font-weight:bold">cache_dir=</span> parameter within JCOGS Image tags) for any images that have existed for longer than the cache duration in force when they were saved, and remove them.',

    // Licensing Configuration Section
    'jcogs_img_pro_cp_licensing_configuration'                 => 'Licensing Configuration',
    'jcogs_img_pro_cp_license_server_domain'                   => 'JCOGS License Server Domain',
    'jcogs_img_pro_cp_license_server_domain_desc'              => 'The domain of the JCOGS License server.',

    // Validation Error Messages
    'jcogs_img_pro_cp_invalid_separator_string'                => 'Invalid filename separator. Cannot contain spaces or reserved characters: !*\'();:@&=+$,/?#[]',
    'jcogs_img_pro_cp_invalid_filename_length'                 => 'Maximum filename length must be between 1 and 175 characters',
    'jcogs_img_pro_cp_invalid_php_ram_value'                   => 'Invalid PHP memory value. Use numeric value (MB assumed) or with units (e.g., 256M, 1G)',
    'jcogs_img_pro_cp_invalid_execution_time'                  => 'Execution time must be numeric (seconds)',
    'jcogs_img_pro_cp_invalid_connection_time'                 => 'Connection time must be a positive number',
    'jcogs_img_pro_cp_invalid_user_agent'                      => 'User agent string cannot be empty',
    'jcogs_img_pro_cp_invalid_cache_threshold'                 => 'Cache threshold must be a positive number',
    'jcogs_img_pro_cp_invalid_audit_interval'                  => 'Audit interval must be a positive number (seconds)',
    'jcogs_img_pro_cp_invalid_license_domain'                  => 'Invalid license server domain',
    'jcogs_img_pro_cp_invalid_remote_directory'                => 'Invalid remote directory path',
    
    // =============================================================================
    // ACTION LINK CONTENT LANGUAGE STRINGS
    // =============================================================================
    'img_pro_action_link_settings'                             => 'Action Link Settings',
    'img_pro_action_links_disabled'                            => '<p><strong>Action links are currently disabled.</strong></p><p>To enable action links, set "Enable JCOGS Image Pro" to "Yes" in the Global Settings above.</p>',
    'img_pro_action_id_not_found'                              => '<p><strong>Error:</strong> Action ID not found. Please ensure the module is properly installed.</p>',
    'img_pro_action_link_url'                                  => 'Action Link URL',
    'img_pro_action_link_usage'                                => 'Usage Instructions',
    'img_pro_action_link_description'                          => 'This action link allows direct image processing through URL parameters. Simply append the required parameters to the URL above.',
    'img_pro_action_link_parameters'                           => 'Available Parameters',
    'img_pro_param_method_desc'                                => 'Image processing method (resize, crop, etc.)',
    'img_pro_param_path_desc'                                  => 'Path to the source image',
    'img_pro_param_quality_desc'                               => 'JPEG quality (1-100, optional)',
    'img_pro_param_format_desc'                                => 'Output format (jpg, png, webp, optional)',
    'img_pro_action_link_examples'                             => 'Example URLs',
    'img_pro_example_resize'                                   => 'Resize Image',
    'img_pro_example_crop'                                     => 'Crop Image',
    
    // =============================================================================
    // IMAGE DEFAULTS PAGE LANGUAGE STRINGS
    // =============================================================================
    'jcogs_img_pro_cp_image_processing_defaults'               => 'Image Processing Defaults',
    'jcogs_img_pro_cp_save_default_settings'                   => 'Save Default Settings',
    'jcogs_img_pro_cp_saving'                                  => 'Saving...',
    'jcogs_img_pro_cp_image_defaults_saved'                    => 'Image default settings saved successfully',
    
    // Default Dimensions
    'jcogs_img_pro_cp_default_dimensions'                      => 'Default Dimensions',
    'jcogs_img_pro_cp_default_width'                           => 'Default Width',
    'jcogs_img_pro_cp_default_width_desc'                      => 'Default width for processed images in pixels. Can be overridden by the width parameter in tags.',
    'jcogs_img_pro_cp_default_height'                          => 'Default Height',
    'jcogs_img_pro_cp_default_height_desc'                     => 'Default height for processed images in pixels. Can be overridden by the height parameter in tags.',
    'jcogs_img_pro_cp_default_fit_mode'                        => 'Default Fit Mode',
    'jcogs_img_pro_cp_default_fit_mode_desc'                   => 'How images should fit within specified dimensions by default.',
    'jcogs_img_pro_cp_allow_scale_larger'                      => 'Allow Scale Larger',
    'jcogs_img_pro_cp_allow_scale_larger_desc'                 => 'Allow upscaling images larger than their original size.',
    
    // Fit Mode Options
    'jcogs_img_pro_cp_fit_contain'                             => 'Contain (fit within dimensions)',
    'jcogs_img_pro_cp_fit_cover'                               => 'Cover (fill dimensions, may crop)',
    'jcogs_img_pro_cp_fit_distort'                             => 'Distort (stretch to exact dimensions)',
    'jcogs_img_pro_cp_fit_crop'                                => 'Crop (crop to exact dimensions)',
    
    // Quality and Format
    'jcogs_img_pro_cp_quality_format'                          => 'Quality and Format',
    'jcogs_img_pro_cp_default_jpeg_quality'                    => 'Default JPEG Quality',
    'jcogs_img_pro_cp_default_jpeg_quality_desc'               => 'Default quality for JPEG images (1-100). Higher values mean better quality but larger file sizes.',
    'jcogs_img_pro_cp_default_png_compression'                 => 'Default PNG Compression',
    'jcogs_img_pro_cp_default_png_compression_desc'            => 'Default compression level for PNG images (0-9). Higher values mean smaller file sizes but slower processing.',
    'jcogs_img_pro_cp_default_webp_quality'                    => 'Default WebP Quality',
    'jcogs_img_pro_cp_default_webp_quality_desc'               => 'Default quality for WebP images (1-100). WebP typically provides better compression than JPEG.',
    'jcogs_img_pro_cp_default_format'                          => 'Default Format',
    'jcogs_img_pro_cp_default_format_desc'                     => 'Default output format for processed images.',
    
    // Background and Effects
    'jcogs_img_pro_cp_background_effects'                      => 'Background and Effects',
    'jcogs_img_pro_cp_default_background_color'                => 'Default Background Color',
    'jcogs_img_pro_cp_default_background_color_desc'           => 'Default background color for transparent areas (hex color code).',
    'jcogs_img_pro_cp_default_sharpen'                         => 'Default Sharpening',
    'jcogs_img_pro_cp_default_sharpen_desc'                    => 'Apply sharpening to processed images by default.',
    
    // Validation Messages
    'jcogs_img_pro_cp_invalid_width'                           => 'Default width must be a valid number.',
    'jcogs_img_pro_cp_invalid_height'                          => 'Default height must be a valid number.',
    'jcogs_img_pro_cp_invalid_jpeg_quality'                    => 'JPEG quality must be between 1 and 100.',
    'jcogs_img_pro_cp_invalid_png_compression'                 => 'PNG compression must be between 0 and 9.',
    
    // Settings save messages
    'jcogs_img_pro_cp_settings_saved'                          => 'Settings saved successfully',
    'jcogs_img_pro_cp_settings_save_error'                     => 'There was an error saving your settings',
    
    // =============================================================================
    // LICENSE PAGE LANGUAGE STRINGS
    // =============================================================================
    'jcogs_img_pro_cp_license_management'                      => 'License Management',
    'jcogs_img_pro_cp_cache_settings_saved'                    => 'Cache settings saved successfully',
    
    // =============================================================================
    // CACHE OPERATIONS LANGUAGE STRINGS
    // =============================================================================
    'jcogs_img_pro_cp_cache_unknown_operation'                 => 'Unknown cache operation',
    'jcogs_img_pro_cp_cache_error_clearing'                    => 'Error clearing cache: %s',
    'jcogs_img_pro_cp_cache_error_clearing_location'           => 'Error clearing %s: %s',
    'jcogs_img_pro_cp_no_cache_locations'                      => 'No cache locations with data found.',
    
    // Cache operation success messages
    
    // =============================================================================
    // ADDITIONAL IMAGEDEFAULTS PAGE LANGUAGE STRINGS FOR LEGACY COMPATIBILITY
    // =============================================================================
    'jcogs_img_pro_cp_jpg_default_quality'                     => 'Default image quality for JPG images.',
    'jcogs_img_pro_cp_avif_default_quality'                    => 'Default image quality for AVIF images.',
    'jcogs_img_pro_cp_webp_default_quality'                    => 'Default image quality for WEBP images.',
    'jcogs_img_pro_cp_max_image_width'                         => 'Set a maximum width for processed images',
    'jcogs_img_pro_cp_cache_clear_success'                     => 'Cache Clear Completed',
    'jcogs_img_pro_cp_cache_clear_error'                       => 'Cache Clear Failed',
    'jcogs_img_pro_cp_cache_clear_all_desc'                    => 'Successfully cleared %d cache files from %d adapter location%s.',
    'jcogs_img_pro_cp_cache_clear_location_desc'               => 'Successfully cleared %d cache files from %s adapter.',
    'jcogs_img_pro_cp_cache_unknown_error'                     => 'An unknown error occurred during cache operation.',
    
    // Cache audit messages
    'jcogs_img_pro_cp_cache_audit_completed'                   => 'Cache Audit Completed',
    'jcogs_img_pro_cp_cache_audit_error'                       => 'Cache Audit Failed',
    'jcogs_img_pro_cp_cache_audit_success'                     => 'Cache Audit Completed',
    'jcogs_img_pro_cp_cache_audit_completed_desc'              => 'Audit of %s adapter completed successfully:<br>• Total files: %d<br>• Total size: %s<br>• Database entries: %d<br>• Orphaned files: %d',
    'jcogs_img_pro_cp_cache_audit_detailed_report'             => 'Audit of %s adapter completed successfully:

Initial Status:
• Total files: %s
• Total size: %s
• Database entries: %s

Operations Performed:
• Files removed (expired): %d
• Database entries removed (orphaned): %d
• Database entries added (orphaned files): %d
• Size cleaned up: %s

Final Status:
• Total files: %s
• Total size: %s
• Database entries: %s
• Orphaned files: %s',
    
    // =============================================================================
    // PRO FACE DETECTION FEATURE LANGUAGE STRINGS
    // =============================================================================
    'jcogs_img_pro_face_detect_crop_focus_first_face'          => 'Using first face for crop focus (Legacy compatible)',
    'jcogs_img_pro_face_detect_crop_focus_all'                 => 'Using all faces for crop focus (Pro feature)',

    // =============================================================================
    // CONTROL PANEL: FACE DETECTION SETTINGS
    // =============================================================================
    'jcogs_img_pro_cp_face_detect_crop_focus'                  => 'Face Detection Crop Focus',
    'jcogs_img_pro_cp_face_detect_crop_focus_desc'             => 'Controls how face detection determines crop area when multiple faces are detected. "First Face" provides Legacy compatibility, while "All Faces" uses Pro\'s enhanced algorithm.',
    'jcogs_img_pro_cp_face_detect_crop_focus_first_face'       => 'First Face (Legacy Compatible)',
    'jcogs_img_pro_cp_face_detect_crop_focus_all'              => 'All Faces (Pro Enhancement)',
    'jcogs_img_pro_cp_invalid_face_detect_crop_focus'          => 'Face detection crop focus must be either "first_face" or "all"',
    
    // =============================================================================
    // FALLBACK IMAGE LANGUAGE STRINGS
    // =============================================================================
    'jcogs_img_pro_cp_fallback_image_colour'                   => 'Fallback colour field',
    'jcogs_img_pro_cp_fallback_image_colour_desc'              => 'The colour to use when using a colour field as the fallback image',
    'jcogs_img_pro_cp_fallback_image_local'                    => 'Local fallback image',
    'jcogs_img_pro_cp_fallback_image_local_desc'               => 'Select a local image file to use as the fallback when image processing fails',
    'jcogs_img_pro_cp_fallback_image_remote'                   => 'Remote fallback image URL',
    'jcogs_img_pro_cp_fallback_image_remote_desc'              => 'Enter a URL for a remote image to use as the fallback when image processing fails',

    // =============================================================================
    // JUMP MENU LANGUAGE STRINGS
    // =============================================================================
    'jump_system_settings'                                     => 'JCOGS Image Pro System Settings',
    'jump_advanced_settings'                                   => 'JCOGS Image Pro Advanced Settings',
    'jump_cache_connections'                                   => 'JCOGS Image Pro Cache Connections',
    'jump_add_connection'                                      => 'Add New Cache Connection',
    'jump_edit_connection'                                     => 'Edit Cache Connection',
    'jump_clone_connection'                                    => 'Clone Cache Connection',
    'jump_clear_cache'                                         => 'Clear Image Cache',
    'jump_audit_cache'                                         => 'Audit Image Cache',
    'jump_cache_stats'                                         => 'Cache Statistics & Performance',
    'jump_test_connection'                                     => 'Test Cache Connection',
    'jump_system_info'                                         => 'System Information & Diagnostics',
    'jump_migrate_legacy'                                      => 'Migrate Legacy Settings',
    'jump_license_settings'                                    => 'JCOGS Image Pro License',

    // =============================================================================
    // DASHBOARD WIDGET LANGUAGE STRINGS
    // =============================================================================
    'jcogs_img_pro_widget_cache_status_title'                 => 'Image Cache Status',
    'jcogs_img_pro_widget_quick_actions_title'                => 'Image Cache Actions',

    // =============================================================================
    // PRESET SYSTEM DEBUG MESSAGES
    // =============================================================================
    'preset_created_success'                                  => 'Created preset "%s" (ID: %d)',
    'preset_create_failed'                                    => 'Failed to create preset "%s": %s',
    'preset_updated_success'                                  => 'Updated preset "%s"',
    'preset_update_failed'                                    => 'Failed to update preset "%s": %s',
    'preset_deleted_success'                                  => 'Deleted preset "%s"',
    'preset_delete_failed'                                    => 'Failed to delete preset "%s": %s',
    'preset_not_found'                                        => 'Preset "%s" not found, using tag parameters only',
    'preset_resolved_success'                                 => 'Resolved preset "%s" with %d parameters',
    'cp_form_submission_data'                                 => 'Form submission data: %s',
];
