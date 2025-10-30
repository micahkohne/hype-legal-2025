<?php

/**
 * JCOGS Image Pro - Parameter Language File
 * =========================================
 * Language strings for parameter packages and preset form generation
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Presets Feature Implementation
 */

$lang = [

    // ==============================================
    // Parameter Package Labels & Descriptions
    // ==============================================
    
    'jcogs_img_pro_param_package_control_label'          => 'Control Parameters',
    'jcogs_img_pro_param_package_control_desc'           => 'Parameters that control image source, quality, and caching behavior',
    'jcogs_img_pro_param_package_dimensional_label'      => 'Dimensional Parameters', 
    'jcogs_img_pro_param_package_dimensional_desc'       => 'Parameters that control image dimensions and sizing behavior',
    'jcogs_img_pro_param_package_transformational_label' => 'Transformational Parameters',
    'jcogs_img_pro_param_package_transformational_desc'  => 'Parameters that control image effects, filters, and visual transformations',

    // ==============================================
    // Control Parameters
    // ==============================================
    
    // Source
    'jcogs_img_pro_param_src_label'                      => 'Image Source',
    'jcogs_img_pro_param_src_desc'                       => 'Path to the source image file (local path or URL)',
    'jcogs_img_pro_param_src_placeholder'                => 'e.g., /media/images/photo.jpg',
    
    // Quality
    'jcogs_img_pro_param_quality_label'                  => 'Image Quality',
    'jcogs_img_pro_param_quality_desc'                   => 'JPEG/WebP quality level (0-100, higher values = better quality, larger files)',
    'jcogs_img_pro_param_quality_unit'                   => '%',
    
    // Cache
    'jcogs_img_pro_param_cache_label'                    => 'Cache Duration',
    'jcogs_img_pro_param_cache_desc'                     => 'How long to cache processed images (-1 = forever, 0 = no cache, or seconds)',
    'jcogs_img_pro_param_cache_option_perpetual'         => 'Perpetual (-1)',
    'jcogs_img_pro_param_cache_option_disabled'          => 'No Caching (0)',
    'jcogs_img_pro_param_cache_option_custom'            => 'Custom Duration (seconds)',
    'jcogs_img_pro_param_cache_placeholder'              => 'e.g., 604800 (1 week)',

    // Lazy Loading
    'jcogs_img_pro_param_control_lazy_loading_label'     => 'Enable Lazy Loading',
    'jcogs_img_pro_param_control_lazy_loading_desc'      => 'When the <a href="https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-lazy" target="_blank">Lazy</a> parameter default is set to enabled JCOGS Image will set the <strong>&lt;img&gt;</strong> tags it creates to lazy-load by default. Can be overridden by using the <span style="color:var(--ee-success-dark);font-weight:bold">lazy=</span> parameter within a tag.',

    // ==============================================
    // Dimensional Parameters  
    // ==============================================
    
    'jcogs_img_pro_param_width_label'                    => 'Width',
    'jcogs_img_pro_param_width_desc'                     => 'Image width in pixels or percentage (leave blank for auto)',
    'jcogs_img_pro_param_width_placeholder'              => 'e.g., 400 or 50%',
    
    'jcogs_img_pro_param_height_label'                   => 'Height', 
    'jcogs_img_pro_param_height_desc'                    => 'Image height in pixels or percentage (leave blank for auto)',
    'jcogs_img_pro_param_height_placeholder'             => 'e.g., 300 or 75%',

    // Default dimensional parameters for CP integration
    'jcogs_img_pro_param_dimensional_width_label'        => 'Set default width for unsized images',
    'jcogs_img_pro_param_dimensional_width_desc'         => 'Operations within JCOGS Image sometimes need to know the width of the original image: for example when Image replaces a missing image with a colour field, or when the image is an SVG. Not all SVG image files contain this information. Some do, others just an aspect ratio (but no base width) and some no information at all. If the SVG contains width information that value will be used to set the original width value for the image, otherwise this value will be used as the default width (in px). This option can be over-ridden by use of the <span style="color:var(--ee-success-dark);font-weight:bold">default_img_width=</span> parameter within a tag.',
    
    'jcogs_img_pro_param_dimensional_height_label'       => 'Set default height for unsized images',
    'jcogs_img_pro_param_dimensional_height_desc'        => 'Operations within JCOGS Image sometimes need to know the height of the original image: for example when Image replaces a missing image with a colour field, or when the image is an SVG. Not all SVG image files contain this information. Some do, others just an aspect ratio (but no base height) and some no information at all. If the SVG contains height information that value will be used to set the original height value for the image, otherwise this value will be used as the default height (in px). This option can be over-ridden by use of the <span style="color:var(--ee-success-dark);font-weight:bold">default_img_height=</span> parameter within a tag.',

    'jcogs_img_pro_param_dimensional_allow_scale_larger_label' => 'Allow Scale Larger parameter default setting',
    'jcogs_img_pro_param_dimensional_allow_scale_larger_desc'  => 'When the <a href="https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-allow-scale-larger" target="_blank">Allow Scale Larger</a> default is set to enabled JCOGS Image will enlarge images to fit dimensions given if the source image is smaller than the final dimensions required by the settings in a JCOGS Image tag. The initial default settings for this option is disabled. This setting is over-ridden by the value given by a <span style="color:var(--ee-success-dark);font-weight:bold">allow_scale_larger=</span> parameter within a tag.',
    
    'jcogs_img_pro_param_max_width_label'                => 'Maximum Width',
    'jcogs_img_pro_param_max_width_desc'                 => 'Maximum allowed width - overrides width parameter if smaller',
    
    'jcogs_img_pro_param_max_height_label'               => 'Maximum Height',
    'jcogs_img_pro_param_max_height_desc'                => 'Maximum allowed height - overrides height parameter if smaller',
    
    'jcogs_img_pro_param_min_width_label'                => 'Minimum Width',
    'jcogs_img_pro_param_min_width_desc'                 => 'Minimum required width - overrides width parameter if larger',
    
    'jcogs_img_pro_param_min_height_label'               => 'Minimum Height',
    'jcogs_img_pro_param_min_height_desc'                => 'Minimum required height - overrides height parameter if larger',
    
    'jcogs_img_pro_param_max_label'                      => 'Maximum Dimension',
    'jcogs_img_pro_param_max_desc'                       => 'Maximum value for both width and height',
    
    'jcogs_img_pro_param_min_label'                      => 'Minimum Dimension', 
    'jcogs_img_pro_param_min_desc'                       => 'Minimum value for both width and height',
    
    // Fit Parameter
    'jcogs_img_pro_param_fit_label'                      => 'Fit Behavior',
    'jcogs_img_pro_param_fit_desc'                       => 'How to scale image when both width and height are specified',
    'jcogs_img_pro_param_fit_option_contain'             => 'Contain - Scale to fit within bounds (default)',
    'jcogs_img_pro_param_fit_option_cover'               => 'Cover - Scale to fill bounds completely',
    'jcogs_img_pro_param_fit_option_distort'             => 'Distort - Stretch to exact dimensions',
    
    // Aspect Ratio
    'jcogs_img_pro_param_aspect_ratio_label'             => 'Aspect Ratio',
    'jcogs_img_pro_param_aspect_ratio_desc'              => 'Force specific aspect ratio (e.g., 16_9, 4:3, 5/7)',
    'jcogs_img_pro_param_aspect_ratio_placeholder'       => 'e.g., 16_9 or 4:3',

    // ==============================================
    // Transformational Parameters
    // ==============================================
    
    // Crop
    'jcogs_img_pro_param_crop_label'                     => 'Crop Settings',
    'jcogs_img_pro_param_crop_desc'                      => 'Enable and configure image cropping behavior',
    'jcogs_img_pro_param_crop_enable_label'              => 'Enable Cropping',
    'jcogs_img_pro_param_crop_enable_option_no'          => 'No - Resize image to fit',
    'jcogs_img_pro_param_crop_enable_option_yes'         => 'Yes - Crop to exact dimensions',
    'jcogs_img_pro_param_crop_enable_option_face_detect' => 'Face Detection - Focus on detected faces',
    
    'jcogs_img_pro_param_crop_position_label'            => 'Crop Position',
    'jcogs_img_pro_param_crop_position_desc'             => 'Where to position the crop area on the source image',
    'jcogs_img_pro_param_crop_h_position_label'          => 'Horizontal Position',
    'jcogs_img_pro_param_crop_h_option_left'             => 'Left',
    'jcogs_img_pro_param_crop_h_option_center'           => 'Center',
    'jcogs_img_pro_param_crop_h_option_right'            => 'Right',
    'jcogs_img_pro_param_crop_h_option_face_detect'      => 'Face Detection',
    
    'jcogs_img_pro_param_crop_v_position_label'          => 'Vertical Position',
    'jcogs_img_pro_param_crop_v_option_top'              => 'Top',
    'jcogs_img_pro_param_crop_v_option_center'           => 'Center', 
    'jcogs_img_pro_param_crop_v_option_bottom'           => 'Bottom',
    'jcogs_img_pro_param_crop_v_option_face_detect'      => 'Face Detection',
    
    'jcogs_img_pro_param_crop_offset_label'              => 'Crop Offset',
    'jcogs_img_pro_param_crop_offset_desc'               => 'Fine-tune crop position with pixel offsets',
    'jcogs_img_pro_param_crop_offset_x_label'            => 'Horizontal Offset',
    'jcogs_img_pro_param_crop_offset_y_label'            => 'Vertical Offset',
    'jcogs_img_pro_param_crop_offset_placeholder'        => 'e.g., -20 or 10px',
    
    'jcogs_img_pro_param_crop_smart_scaling_label'       => 'Smart Scaling',
    'jcogs_img_pro_param_crop_smart_scaling_desc'        => 'Automatically scale image before cropping for best results',
    'jcogs_img_pro_param_crop_smart_scaling_option_yes'  => 'Yes - Enable smart scaling (recommended)',
    'jcogs_img_pro_param_crop_smart_scaling_option_no'   => 'No - Use exact crop dimensions',
    
    // Filter
    'jcogs_img_pro_param_filter_label'                   => 'Image Filters',
    'jcogs_img_pro_param_filter_desc'                    => 'Apply filters to modify image appearance (pipe-separated list)',
    'jcogs_img_pro_param_filter_placeholder'             => 'e.g., grayscale|blur,5|colorize,-20,20,-20',
    'jcogs_img_pro_param_filter_help'                    => 'Separate multiple filters with | character. Each filter can have parameters separated by commas.',
    
    // Rotate & Flip
    'jcogs_img_pro_param_rotate_label'                   => 'Rotation',
    'jcogs_img_pro_param_rotate_desc'                    => 'Rotate image by degrees (positive = counter-clockwise)',
    'jcogs_img_pro_param_rotate_placeholder'             => 'e.g., 90, -45, 180',
    
    'jcogs_img_pro_param_flip_label'                     => 'Flip Image',
    'jcogs_img_pro_param_flip_desc'                      => 'Flip image horizontally and/or vertically',
    'jcogs_img_pro_param_flip_option_none'               => 'No Flip',
    'jcogs_img_pro_param_flip_option_h'                  => 'Horizontal (h)',
    'jcogs_img_pro_param_flip_option_v'                  => 'Vertical (v)',
    'jcogs_img_pro_param_flip_option_both'               => 'Both (h|v)',

    // ==============================================
    // Complex Parameter Common Elements
    // ==============================================
    
    'jcogs_img_pro_param_coordinate_x_label'             => 'X Coordinate',
    'jcogs_img_pro_param_coordinate_y_label'             => 'Y Coordinate',
    'jcogs_img_pro_param_color_label'                    => 'Color',
    'jcogs_img_pro_param_color_desc'                     => 'Color in hex format (e.g., #FF0000) or rgb() format',
    'jcogs_img_pro_param_color_placeholder'              => 'e.g., #FF0000 or rgb(255,0,0)',
    'jcogs_img_pro_param_opacity_label'                  => 'Opacity',
    'jcogs_img_pro_param_opacity_desc'                   => 'Transparency level (0 = transparent, 100 = opaque)',
    'jcogs_img_pro_param_opacity_unit'                   => '%',

    // ==============================================
    // Validation Messages
    // ==============================================
    
    'jcogs_img_pro_param_validation_required'            => 'This parameter is required',
    'jcogs_img_pro_param_validation_invalid_number'      => 'Please enter a valid number',
    'jcogs_img_pro_param_validation_invalid_color'       => 'Please enter a valid color (hex or rgb format)',
    'jcogs_img_pro_param_validation_invalid_dimension'   => 'Please enter a valid dimension (number with optional px or %)',
    'jcogs_img_pro_param_validation_range_error'         => 'Value must be between {min} and {max}',
    'jcogs_img_pro_param_validation_dependency_error'    => 'This parameter requires {dependency} to be set',
    'jcogs_img_pro_param_validation_pipe_syntax_error'   => 'Invalid pipe-separated syntax. Please check parameter format.',
    
    // Parameter-specific validation messages (used by parameter packages)
    'jcogs_img_pro_param_validation_rotate_range'        => 'Rotation must be between -360 and 360 degrees',
    'jcogs_img_pro_param_validation_quality_range'       => 'Quality must be between 1 and 100',
    'jcogs_img_pro_param_validation_background_color'    => 'Background must be a valid color format (hex, rgb, rgba, or transparent)',
    'jcogs_img_pro_param_validation_allow_scale_larger'  => 'Allow scale larger must be y, n, yes, or no',
    'jcogs_img_pro_param_validation_dimension_positive'  => 'Dimensions must be positive integers',
    'jcogs_img_pro_param_validation_min_greater_than_max' => 'Minimum value cannot be greater than maximum value',

    // ==============================================
    // Parameter Package Labels & Descriptions (used by package methods)
    // ==============================================
    
    // Control Parameter Package
    'jcogs_img_pro_param_package_control_name'           => 'control',
    'jcogs_img_pro_param_package_control_display_name'   => 'Control Parameters',
    'jcogs_img_pro_param_package_control_description'    => 'Basic image processing control: source, caching, quality, and output format settings.',
    
    // Dimensional Parameter Package
    'jcogs_img_pro_param_package_dimensional_name'       => 'dimensional',
    'jcogs_img_pro_param_package_dimensional_display_name' => 'Dimensional Parameters',
    'jcogs_img_pro_param_package_dimensional_description' => 'Parameters that control image dimensions, sizing, and dimension constraints',
    
    // Transformational Parameter Package  
    'jcogs_img_pro_param_package_transformational_name'  => 'transformational',
    'jcogs_img_pro_param_package_transformational_display_name' => 'Transformational Parameters',
    'jcogs_img_pro_param_package_transformational_description' => 'Parameters that control image transformations, effects, and visual modifications',

    // ==============================================
    // Dimensional Parameter Form Fields (missing from packages)
    // ==============================================
    
    // Parameter descriptions (fallback defaults for lang() calls)
    'parameter_width_description'                        => 'Set the output image width in pixels. Leave empty to maintain aspect ratio.',
    'parameter_height_description'                       => 'Set the output image height in pixels. Leave empty to maintain aspect ratio.',
    'parameter_max_description'                          => 'Maximum size for both width and height (images scaled to fit within this constraint).',
    'parameter_max_width_description'                    => 'Maximum width in pixels. Images wider than this will be scaled down.',
    'parameter_max_height_description'                   => 'Maximum height in pixels. Images taller than this will be scaled down.',
    'parameter_min_description'                          => 'Minimum size for both width and height (images scaled to meet this constraint).',
    'parameter_min_width_description'                    => 'Minimum width in pixels. Images narrower than this will be scaled up.',
    'parameter_min_height_description'                   => 'Minimum height in pixels. Images shorter than this will be scaled up.',
    
    // Parameter placeholders (fallback defaults for lang() calls)  
        // Parameter placeholders (fallback defaults for lang() calls)  
    'width_placeholder'                                  => 'e.g. 800',
    'height_placeholder'                                 => 'e.g. 600',
    'max_placeholder'                                    => 'e.g. 1200',
    'max_width_placeholder'                              => 'e.g. 1920',
    'max_height_placeholder'                             => 'e.g. 1080',
    'min_placeholder'                                    => 'e.g. 200',
    'min_width_placeholder'                              => 'e.g. 300',
    'min_height_placeholder'                             => 'e.g. 200',
    
    // Quality parameter descriptions (for parameter packages)
    'parameter_quality_description'                     => 'JPEG compression quality (1-100, higher = better quality)',
    'parameter_png_quality_description'                 => 'PNG compression level (0-9, lower = larger file, faster)',
    'quality_placeholder'                               => '1-100',
    'png_quality_placeholder'                           => '0-9',
    'validation_quality_range'                          => 'Quality must be between 1 and 100',
    'validation_png_quality_range'                      => 'PNG quality must be between 0 and 9',

    // CP Integration quality language keys (moved from main jcogs_img_pro_lang.php)
    'jcogs_img_pro_cp_default_image_quality'            => 'Default image quality',
    'jcogs_img_pro_cp_default_image_quality_desc'       => 'Some image formats (e.g. avif, jpg, webp) have optional quality levels that allow a trade-off between image file-size and image quality; <b>higher</b> quality values equate to larger files but better looking images. The default value (85) is good for most purposes. For AVIF and WebP images, setting the quality value to 100 will result in lossless compression of the image on servers running php 8.1 or better (biggest file-size, best quality image). <br>The value can be overridden by using the <span style="color:var(--ee-success-dark);font-weight:bold">quality=</span> parameter within a tag.',
    
    'jcogs_img_pro_cp_png_default_quality'              => 'Default image quality for PNG images',
    'jcogs_img_pro_cp_png_default_quality_desc'         => 'Set the default image quality level for PNG images. The quality level allows a trade-off between image file-size and image quality; for PNG images a <b>lower</b> value equates to a larger file-size but better looking image. The default value (6) is good for most purposes. Setting the quality value to 0 will result in lossless compression (biggest file-size, best image quality). <br>The value can be overridden by using the <span style="color:var(--ee-success-dark);font-weight:bold">png_quality=</span> parameter within a tag.',

    // Background Color Settings
    'jcogs_img_pro_cp_default_bg_color'                 => 'Default background colour',
    'jcogs_img_pro_cp_default_bg_color_desc'            => 'Sets the colour to use to fill in background of image should an image operation expose some of the background.  Can be overridden by using the <span style="color:var(--ee-success-dark);font-weight:bold">bg_color=</span> parameter within a tag.',
    
    // Image Format Settings  
    'jcogs_img_pro_cp_format_source'                    => 'Use source image format',
    
    // Default Image Dimensions
    'jcogs_img_pro_cp_default_img_width'                => 'Set default width for unsized images',
    'jcogs_img_pro_cp_default_img_width_desc'           => 'Operations within JCOGS Image sometimes need to know the width of the original image: for example when Image replaces a missing image with a colour field, or when the image is an SVG. Not all SVG image files contain this information. Some do, others just an aspect ratio (but no base width) and some no information at all. If the SVG contains width information that value will be used to set the original width value for the image, otherwise this value will be used as the default width (in px). This option can be over-ridden by use of the  <span style="color:var(--ee-success-dark);font-weight:bold">default_img_width=</span> parameter within a tag.',
    'jcogs_img_pro_cp_default_img_height'               => 'Set default height for unsized images',
    'jcogs_img_pro_cp_default_img_height_desc'          => 'Operations within JCOGS Image sometimes need to know the width of the original image: for example when Image replaces a missing image with a colour field, or when the image is an SVG. Not all SVG image files contain this information. Some do, others just an aspect ratio (but no base width) and some no information at all. If the SVG contains height information that value will be used to set the original width value for the image, otherwise this value will be used as the default height (in px). This option can be over-ridden by use of the  <span style="color:var(--ee-success-dark);font-weight:bold">default_img_height=</span> parameter within a tag.',

    // ==============================================
    // Cache Duration Settings
    'jcogs_img_pro_cp_default_cache_duration'           => 'Default cache duration',
    'jcogs_img_pro_cp_default_cache_duration_desc'      => 'How long to cache processed images. You can use natural language like "1 week", "2 days", "forever", or "disabled". You can also enter seconds directly (e.g. 604800). Set to "forever" or -1 for permanent caching, "disabled" or 0 to disable caching. This can be overridden by the <span style="color:var(--ee-success-dark);font-weight:bold">cache=</span> parameter in individual tags.<br><strong>Examples:</strong> "1 month", "2 weeks", "5 days", "forever", "disabled"',

    // Cache Audit Interval Settings
    'jcogs_img_pro_cp_default_cache_audit_after'        => 'Cache audit interval',
    'jcogs_img_pro_cp_default_cache_audit_after_desc'   => 'How often to run cache audits to clean up expired cache files. You can use natural language like "daily", "weekly", "1 week", or "2 days". You can also enter seconds directly (e.g. 604800). Set to a reasonable interval to balance cleanup with performance.<br><strong>Examples:</strong> "daily", "1 week", "2 days", "weekly"',

    // PHP Timeout Settings
    'jcogs_img_pro_cp_default_min_php_process_time'     => 'PHP execution timeout',
    'jcogs_img_pro_cp_default_min_php_process_time_desc' => 'Maximum time allowed for PHP to process images. You can use natural language like "30 seconds", "1 minute", or "2 minutes". You can also enter seconds directly (e.g. 60). Increase this if processing large images fails due to timeouts.<br><strong>Examples:</strong> "30 seconds", "1 minute", "2 minutes", "5 minutes"',
    
    'jcogs_img_pro_cp_default_php_remote_connect_time'  => 'Remote connection timeout',
    'jcogs_img_pro_cp_default_php_remote_connect_time_desc' => 'Maximum time allowed to connect to remote image sources. You can use natural language like "3 seconds", "5 seconds", or "10 seconds". You can also enter seconds directly (e.g. 3). Lower values fail faster but may miss slow connections.<br><strong>Examples:</strong> "3 seconds", "5 seconds", "10 seconds", "20 seconds"',
    
    // Duration Input Helpers
    'duration_input_placeholder'                        => 'e.g., "1 week", "3 days", "forever", or "604800"',
    'duration_current_value'                            => 'Currently: %s',
    'duration_parsing_help'                             => 'You can enter natural language (like "2 weeks" or "daily") or seconds directly',
    'duration_examples_cache'                           => 'Examples: "1 month", "2 weeks", "forever", "disabled"',
    'duration_examples_audit'                           => 'Examples: "daily", "1 week", "every 2 days"',
    'duration_examples_timeout'                         => 'Examples: "30 seconds", "1 minute", "2 minutes"',

    // CP Integration mapping keys (for CPFormIntegration to map to existing keys)
    'jcogs_img_pro_param_transformational_quality_label' => 'Default image quality',
    'jcogs_img_pro_param_transformational_quality_desc'  => 'Some image formats (e.g. avif, jpg, webp) have optional quality levels that allow a trade-off between image file-size and image quality; <b>higher</b> quality values equate to larger files but better looking images. The default value (85) is good for most purposes. For AVIF and WebP images, setting the quality value to 100 will result in lossless compression of the image on servers running php 8.1 or better (biggest file-size, best quality image). <br>The value can be overridden by using the <span style="color:var(--ee-success-dark);font-weight:bold">quality=</span> parameter within a tag.',
    
    'jcogs_img_pro_param_transformational_png_quality_label' => 'Default image quality for PNG images',
    'jcogs_img_pro_param_transformational_png_quality_desc'  => 'Set the default image quality level for PNG images. The quality level allows a trade-off between image file-size and image quality; for PNG images a <b>lower</b> value equates to a larger file-size but better looking image. The default value (6) is good for most purposes. Setting the quality value to 0 will result in lossless compression (biggest file-size, best image quality). <br>The value can be overridden by using the <span style="color:var(--ee-success-dark);font-weight:bold">png_quality=</span> parameter within a tag.',

    // ==============================================
    // Transformational Parameter Form Fields
    // ==============================================
    
    // Allow Scale Larger (moved from DimensionalParameterPackage)
    'jcogs_img_pro_param_transformational_allow_scale_larger_label' => 'Allow Scale Larger',
    'jcogs_img_pro_param_transformational_allow_scale_larger_desc' => 'Allow scaling images larger than their original size',
    
    // Auto Sharpen parameters
    'jcogs_img_pro_param_transformational_auto_sharpen_label' => 'Auto Sharpening parameter default setting',
    'jcogs_img_pro_param_transformational_auto_sharpen_desc' => 'When the <a href="https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-auto-sharpen" target="_blank">Auto Sharpening</a> default is set to enabled JCOGS Image will automatically apply the auto-sharpen filter to every image. The auto-sharpen filter increases the sharpness of images that have been reduced in size during manipulations; the greater the reduction in size the greater the degree of sharpening applied. This option can be over-ridden by use of the <span style="color:var(--ee-success-dark);font-weight:bold">auto_sharpen=</span> parameter within a tag.',
    
    // Rotation parameters
    'jcogs_img_pro_param_transformational_rotate_label'  => 'Rotation',
    'jcogs_img_pro_param_transformational_rotate_desc'   => 'Rotate image by degrees (positive = counter-clockwise)',
    'jcogs_img_pro_param_transformational_rotate_placeholder' => 'e.g., 90, -45, 180',
    
    // Flip parameters
    'jcogs_img_pro_param_transformational_flip_label'    => 'Flip Image',
    'jcogs_img_pro_param_transformational_flip_desc'     => 'Flip image horizontally, vertically, or both',
    'flip_option_horizontal'                             => 'Flip horizontally',
    'flip_option_vertical'                               => 'Flip vertically', 
    'flip_option_both'                                   => 'Flip both directions',
    
    // Resize method parameters
    'jcogs_img_pro_param_transformational_resize_label'  => 'Resize Method',
    'jcogs_img_pro_param_transformational_resize_desc'   => 'How to resize the image to fit dimensions',
    'resize_option_fit'                                  => 'Fit within bounds',
    'resize_option_fill'                                 => 'Fill bounds (may crop)',
    'resize_option_stretch'                              => 'Stretch to exact dimensions',
    'resize_option_pad'                                  => 'Pad to dimensions',
    
    // Fit method parameters
    'jcogs_img_pro_param_transformational_fit_label'     => 'Fit Method',
    'jcogs_img_pro_param_transformational_fit_desc'      => 'How to fit the image within the specified dimensions',
    'fit_option_inside'                                  => 'Inside - Fit within bounds (default)',
    'fit_option_outside'                                 => 'Outside - Cover entire area (may crop)',
    'fit_option_fill'                                    => 'Fill - Stretch to exact dimensions',
    'fit_option_contain'                                 => 'Contain - Fit within bounds with padding',
    
    // Interlace parameters
    'jcogs_img_pro_param_transformational_interlace_label' => 'Interlaced/Progressive',
    'jcogs_img_pro_param_transformational_interlace_desc' => 'Enable progressive JPEG or interlaced PNG for better perceived loading',
    'interlace_option_no'                                => 'No - Standard encoding (default)',
    'interlace_option_yes'                               => 'Yes - Progressive/interlaced encoding',
    
    // Preload parameters
    'jcogs_img_pro_param_transformational_preload_label' => 'Preload Priority',
    'jcogs_img_pro_param_transformational_preload_desc'  => 'Set the loading priority for the image',
    'preload_option_auto'                                => 'Auto - Browser decides (default)',
    'preload_option_none'                                => 'None - No preloading',
    'preload_option_metadata'                            => 'Metadata - Preload metadata only',
    'preload_option_low'                                 => 'Low - Low priority preload',
    'preload_option_high'                                => 'High - High priority preload',
    
    // Format parameters
    'jcogs_img_pro_param_transformational_format_label'  => 'Output Format',
    'jcogs_img_pro_param_transformational_format_desc'   => 'Convert image to specified format',
    'format_option_auto'                                 => 'Auto-detect',
    'format_option_jpeg'                                 => 'JPEG format',
    'format_option_png'                                  => 'PNG format',
    'format_option_webp'                                 => 'WebP format',
    'format_option_gif'                                  => 'GIF format',
    'format_option_avif'                                 => 'AVIF format',

    // Control Parameter Output Format Options (for CP Integration)
    'jcogs_img_pro_param_control_output_format_source'   => 'Keep Original Format',
    'jcogs_img_pro_param_control_output_format_jpg'      => 'JPEG',
    'jcogs_img_pro_param_control_output_format_jpeg'     => 'JPEG',
    'jcogs_img_pro_param_control_output_format_png'      => 'PNG',
    'jcogs_img_pro_param_control_output_format_gif'      => 'GIF',
    'jcogs_img_pro_param_control_output_format_webp'     => 'WebP',
    'jcogs_img_pro_param_control_output_format_avif'     => 'AVIF',
    'jcogs_img_pro_param_control_output_format_bmp'      => 'BMP',
    'jcogs_img_pro_param_control_output_format_tiff'     => 'TIFF',
    'jcogs_img_pro_param_control_output_format_heic'     => 'HEIC',
    'jcogs_img_pro_param_control_output_format_heif'     => 'HEIF',

    // ==============================================
    // Complex Parameter Packages (BorderParameterPackage, RoundedCornersParameterPackage, etc.)
    // ==============================================
    
    // Border Parameter Package
    'jcogs_img_pro_param_border_width_label'             => 'Border Width',
    'jcogs_img_pro_param_border_width_desc'              => 'Width of the border (e.g., 10, 15px, 5%)',
    'jcogs_img_pro_param_border_width_placeholder'       => 'For example: 10px',
    'jcogs_img_pro_param_border_color_label'             => 'Border Color',
    'jcogs_img_pro_param_border_color_desc'              => 'Color of the border. Supports hex codes (3, 4, 6, or 8 digit), CSS rgb()/rgba(), or color names. JCOGS automatically adds # prefix if omitted.',
    'jcogs_img_pro_param_border_color_placeholder'       => 'For example: 4a2d14, #DDD, rgb(220,240,260), rgba(255,128,0,0.8)',
    
    // Rounded Corners Parameter Package
    'jcogs_img_pro_param_rounded_corners_all_label'      => 'All Corners Radius',
    'jcogs_img_pro_param_rounded_corners_all_desc'       => 'Set the same radius for all four corners (e.g., 20, 15px, 5%)',
    'jcogs_img_pro_param_rounded_corners_all_placeholder' => 'For example: 20px',
    'jcogs_img_pro_param_rounded_corners_top_left_label' => 'Top-Left Corner Radius',
    'jcogs_img_pro_param_rounded_corners_top_left_desc'  => 'Radius for the top-left corner (overrides "All Corners" if set)',
    'jcogs_img_pro_param_rounded_corners_top_right_label' => 'Top-Right Corner Radius',
    'jcogs_img_pro_param_rounded_corners_top_right_desc' => 'Radius for the top-right corner (overrides "All Corners" if set)',
    'jcogs_img_pro_param_rounded_corners_bottom_left_label' => 'Bottom-Left Corner Radius',
    'jcogs_img_pro_param_rounded_corners_bottom_left_desc' => 'Radius for the bottom-left corner (overrides "All Corners" if set)',
    'jcogs_img_pro_param_rounded_corners_bottom_right_label' => 'Bottom-Right Corner Radius',
    'jcogs_img_pro_param_rounded_corners_bottom_right_desc' => 'Radius for the bottom-right corner (overrides "All Corners" if set)',
    'jcogs_img_pro_param_rounded_corners_corner_placeholder' => 'For example: 25px',

    // ==============================================
    // General/Shared Parameter Elements
    // ==============================================
    
    'jcogs_img_pro_param_general_pixels_unit'            => 'pixels',
    'jcogs_img_pro_param_general_percent_unit'           => '%',
    'jcogs_img_pro_param_general_degrees_unit'           => 'degrees',
    'jcogs_img_pro_param_general_yes_option'             => 'Yes',
    'jcogs_img_pro_param_general_no_option'              => 'No',
    'jcogs_img_pro_param_general_auto_option'            => 'Auto',
    'jcogs_img_pro_param_general_none_option'            => 'None',
    'jcogs_img_pro_param_general_default_option'         => 'Default',

    // ==============================================
    // Form UI Elements
    // ==============================================
    
    'jcogs_img_pro_param_form_advanced_options'          => 'Advanced Options',
    'jcogs_img_pro_param_form_show_advanced'             => 'Show Advanced Options',
    'jcogs_img_pro_param_form_hide_advanced'             => 'Hide Advanced Options',
    'jcogs_img_pro_param_form_reset_default'             => 'Reset to Default',
    'jcogs_img_pro_param_form_clear_value'               => 'Clear Value',
    'jcogs_img_pro_param_form_example_value'             => 'Example: {example}',
    'jcogs_img_pro_param_form_current_value'             => 'Current: {value}',
    'jcogs_img_pro_param_form_default_value'             => 'Default: {default}',

];
