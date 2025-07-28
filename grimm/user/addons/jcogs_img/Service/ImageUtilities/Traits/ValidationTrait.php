<?php

/**
 * ImageUtility Service Traits - ValidationTrait
 * =============================================
 * A collection of traits for the ImageUtility service
 * to validated parameters and files.
 * =============================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.4.16.2
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.4.14
 */

namespace JCOGSDesign\Jcogs_img\Service\ImageUtilities\Traits;

use Maestroerror\HeicToJpg;

enum ImageFormat: string {
    case JPG = 'jpg';
    case PNG = 'png';
    case WEBP = 'webp';
    case AVIF = 'avif';
    case GIF = 'gif';
    
    public function getMimeType(): string {
        return match($this) {
            static::JPG => 'image/jpeg',
            static::PNG => 'image/png',
            static::WEBP => 'image/webp',
            static::AVIF => 'image/avif',
            static::GIF => 'image/gif',
        };
    }
}

class ImageInspectionResult {
    public ?string $processed_binary_data = null;
    public string $original_file_path;
    public ?string $original_file_name = null;
    public ?string $original_extension = null;
    public ?string $detected_mime_type = null;
    public ?int $width = null;
    public ?int $height = null;
    public ?float $aspect_ratio = null;
    public ?int $file_size = null; // Size of processed_binary_data
    public bool $is_svg = false;
    public bool $is_animated_gif = false;
    public bool $is_png = false;
    public bool $was_heic_converted = false;
    public ?string $error_message = null;
    public bool $is_valid = false;

    public function __construct(string $original_file_path_param) {
        $this->original_file_path = $original_file_path_param;
        $path_info = pathinfo($this->original_file_path);
        $this->original_file_name = $path_info['filename'] ?? null;
        $this->original_extension = isset($path_info['extension']) ? strtolower($path_info['extension']) : null;
    }
}

trait ValidationTrait  {

    /**
     * @var array Settings array from jcogs_img:Settings
     */
    protected array $settings;
    
    /**
     * Resets the arrays that hold current parameters and selected parameters to empty
     * @return void
     */
    public function clear_params(): void 
    {
        // Unset parameters
        static::$inbound_params = [];
        static::$current_params = [];
        // static::$transformational_params = [];
        // static::$dimension_params = [];
        // static::$control_params = [];
    }

    /**
     * Utility function: tries to unpack the contents of the `act_packet` parameter
     * These are attached to `action_link` urls
     * If object found in packet, returns it. Otherwise returns false.
     * 
     * @return bool|object
     */
    public function get_act_param_object(): bool|object 
    {
        // If there is no TMPL, it means we're working from an ACT URL. Do we have a packet from ACT call to process?
        $packet = $this->get_parameter('act_packet');
        if ($packet) {
            // We have a packet from ACT call to process. See if we can unpack the packet.
            $decoded_packet = base64_decode($packet);
            if ($decoded_packet === false) {
                ee('jcogs_img:Utilities')->debug_message("Failed to decode base64 packet.");
                return false;
            }
    
            $param_object = json_decode($decoded_packet);
            if (json_last_error() !== JSON_ERROR_NONE) {
                ee('jcogs_img:Utilities')->debug_message("Failed to decode JSON: " . json_last_error_msg());
                return false;
            }
    
            if (is_object($param_object)) {
                static::$current_params = $param_object;
                return $param_object;
            } else {
                ee('jcogs_img:Utilities')->debug_message("Decoded packet is not an object.");
            }
        }
        return false;
    }

    /**
     * Utility function: get path to processed image directory
     * path prefix can either be the local site url (for full URL output) or some other arbitrary prefix (for CDNs etc.)
     *
     * @return string
     */
    public function get_image_path_prefix(): string
    {

        // Check if we are operating on a cloud file system
        if ($this->settings['img_cp_flysystem_adapter'] === 'local') {
            $image_path_prefix = '/';

            // Check if user requested "Full URL" output
            if (strtolower(substr($this->settings['img_cp_class_always_output_full_urls'], 0, 1)) === 'y') {
                $image_path_prefix = rtrim(base_url(), '/');
            } elseif (
                !empty(static::$current_params->image_path_prefix) &&
                !empty(static::$current_params->use_image_path_prefix) &&
                strtolower(substr(static::$current_params->use_image_path_prefix, 0, 1)) === 'y'
            ) {
                // Use the specified prefix if requested
                $image_path_prefix = rtrim(static::$current_params->image_path_prefix, '/');
            }
        } else {
            $image_path_prefix = $this->get_adapter_url();
        }
        return $image_path_prefix;
    }

    /**
     * Utility function: Return mime type string for image based on save_as value
     * https://developer.mozilla.org/en-US/docs/Web/Media/Formats/Image_types
     * 
     * @param string $type
     * @return string
     */
    public function get_mime_type(?string $type = null)
    {
        switch ($type) {
            case 'apng':
                return 'image/apng';
            case 'avif':
                return 'image/avif';
            case 'bmp':
                return 'image/bmp';
            case 'cur':
                return 'image/x-icon';
            case 'gif':
                return 'image/gif';
            case 'ico':
                return 'image/x-icon';
            case 'jpg':
            case 'jpeg':
                return 'image/jpeg';
            case 'png':
                return 'image/png';
            case 'svg':
                return 'image/svg+xml';
            case 'tif':
                return 'image/tiff';
            case 'tiff':
                return 'image/tiff';
            case 'wbmp':
                return 'image/vnd.wap.wbmp';
            case 'webp':
                return 'image/webp';
            case 'xbm':
                return 'image/xbm';
            case 'xpm':
                return 'image/xpm';
            default:
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_unknown_image_format'), $type);
                return 'jpg';
        }
    }

    /**
     * Utility function: Returns a named parameter value.
     * Checks parameters, get variable and if not found, returns default value.
     * 
     * @param string $parameter
     * @return mixed
     */
    public function get_parameter(?string $parameter = null): mixed {
        $return_parameter_value = false;
        if($parameter) {
            if(static::$act_based_tag || empty(ee()->TMPL)) {
                // Try and get from get variables
                $return_parameter_value = ee()->input->get($parameter);
                if($return_parameter_value && str_contains($return_parameter_value, '://')) {
                    $return_parameter_value = preg_replace('/(h.*\:\/\/.*\/)/', '\/', $return_parameter_value);
                }
            } else {
            // Try and get from template parameters (returns false if parameter not found)
                $return_parameter_value = ee()->TMPL->fetch_param($parameter);
            }
            if($return_parameter_value === false) {
                // If that didn't work, get default
                // Try dimensions first
                $return_parameter_value = array_key_exists(key: $parameter, array: static::$dimension_params) ? static::$dimension_params[$parameter] : false;
                if(empty($return_parameter_value)) {
                    // Otherwise try all valid params
                    if(array_key_exists(key: $parameter, array: static::$valid_params)) {
                        $return_parameter_value = static::$valid_params[$parameter];
                    }
                }
            }
        }
        return $return_parameter_value ? $this->_validate_parameters(param: $parameter, value: $return_parameter_value) : $return_parameter_value;
    }

    /**
     * Utility function: If called without a parameter adds properties to $contents->params for
     * each valid parameter found in the tag, and loads specified value (or default)
     * for each of these valid parameters.
     * 
     * If called with a parameter returns the default value for the parameter, or false.
     * 
     * @param mixed $request_param
     * @param bool $get_default
     * @return string|object
     */
    public function get_parameters(?string $request_param = null, bool $get_default = false): mixed
    {       
        // First see if we are to process a single parameter request
        $request_param_value = false;
        if (is_string(value: $request_param) && strlen(string: $request_param)) {
            // We have a parameter request
            if(! array_key_exists(key: $request_param, array: static::$dimension_params)
            ) {
                // It is a not a dimension params
                if ($get_default) {
                    $request_param_value = static::$valid_params[$request_param];
                } else {
                    $request_param_value = $this->get_parameter(parameter: $request_param);
                }
            }
            return $request_param_value;
        }

        // If we get to here we are returning one or all parameter values ...
        if(empty(ee()->TMPL)) {
            // If there is no template object we're responding to an ACT request
            if($param_object = $this->get_act_param_object()) {
                return $param_object;
            }
        }

        // If we get here we're starting a new processing activity
        $temp = new \stdClass;
        // Attempt to retrieve all valid parameters
        foreach (static::$valid_params as $param => $value) {
            $temp->{$param} = $this->get_parameter(parameter: $param);
        }

        // See if we have any config.php over-rides set for control settings
        foreach(static::$control_params as $param => $value) {
            $temp->{$param} = ee()->config->item('jcogs_img_' . $param) ?: $temp->{$param};
        }

        // Set static $action_link value based on value from params
        static::$action_link = $temp->action_link;

        // Set inbound parameter array based on whether we are coming from a tag or ACT request
        $parameter_array = static::$act_based_tag || empty(ee()->TMPL) ? $_GET : ee()->TMPL->tagparams;

        // Now do some validation / reconciliation and process any other tag parameters... 
        $consolidated_attributes = '';
        $non_valid_params = array_filter(
            array_keys($parameter_array),
            $this->_isNonValidAttribute(...)
        );

        foreach ($non_valid_params as $param) {
            $consolidated_attributes .= ' ' . $param . '="' . $parameter_array[$param] . '"';
        }

        // Now reconcile attributes and consolidated / pass-through attributes
        $temp->attributes .= $consolidated_attributes;

        // Now do some housekeeping on some parameters where we have specific needs

        // 1. auto_sharpen
        // Check to see if auto_sharpen parameter set
        if (! is_null(value: $temp->auto_sharpen) && strtolower(string: substr(string: $temp->auto_sharpen, offset: 0, length: 1)) == 'y') {
            // It is set, so adjust filter parameter if necessary... 
            if (is_null(value: $temp->filter)) {
                // No filters defined yet so add auto_sharpen
                $temp->filter = 'auto_sharpen';
            }
            else {
                // Check to see if auto_sharpen already defined, if not append it... 
                if (! stripos(haystack: $temp->filter, needle: 'auto_sharpen')) {
                    $temp->filter .= '|auto_sharpen';
                }
            }
        }

        // 2. disable_browser_checks
        // Check to see if disable_browser_checks parameter set
        if (! is_null(value: $temp->disable_browser_checks) && strtolower(string: substr(string: $temp->disable_browser_checks, offset: 0, length: 1)) != 'y') {
            // It is not set to yes, so adjust filter parameter to be null regardless of what actually entered ... 
            $temp->disable_browser_checks = null;
        }

        // 3. save_type
        // Set the save_type based on value given for save_type param, or use default setting from settings
        $temp->save_as = $this->_get_save_as($temp->src, $temp->save_type);

        // Set save_as and save_type to be the same to avoid confusion later.
        $temp->save_type = $temp->save_as;

        // 4. consolidate_class_style
        // Check to see if consolidate classes / styles default set
        if (! is_null(value: $temp->consolidate_class_style) && strtolower(string: substr(string: $temp->consolidate_class_style, offset: 0, length: 1)) != 'n') {
            // It is not set to no, so adjust parameter to be yes regardless of what actually entered ... 
            $temp->consolidate_class_style = 'y';
        }

        // 5. exclude_style *and* lazy
        // Check to see if exclude_style is set *and* we have lazy set to something other than html
        // If it is, lazy loading won't happen (as standard approach uses styles in tag) so switch to javascript option
        if(strtolower(string: substr(string: $temp->exclude_style, offset: 0, length: 1)) != 'n' && (strtolower(string: substr(string: $temp->lazy, offset: 0, length: 1)) == 'l' || strtolower(string: substr(string: $temp->lazy, offset: 0, length: 1)) == 'd')) {
            $temp->lazy = 'js_' . $temp->lazy;
        }

        // 6. cache
        // Check to see if cache is set, if it is also check to see if it is non-numeric. If it is, set it to default cache duration.
        if (! is_null(value: $temp->cache) && ! is_numeric(value: $temp->cache)) {
            // It is not set to a number, so adjust parameter to be default cache duration regardless of what actually entered ... 
            $temp->cache = static::$valid_params['cache'];
        }
        
        // $test = ee()->TMPL->fetch_param('src','not_set');
        static::$current_params = $temp;
        return $temp;
    }

    /**
     * Processes and validates raw image data, performing necessary conversions and adjustments.
     * 
     * This method handles HEIC conversion, SVG sanitization, auto-adjustment for oversized images,
     * EXIF orientation correction, and extracts comprehensive image metadata for further processing.
     *
     * @param string $raw_data The raw binary image data.
     * @param string $original_path The original path of the file (used for HEIC conversion).
     * @return ImageInspectionResult An object containing details about the processed image.
     */
    public function process_and_validate_image_data(string $raw_data, string $original_path): ImageInspectionResult
    {
        $inspection_result = new ImageInspectionResult(original_file_path_param: $original_path);
        $current_raw_data = $raw_data;

        // 1. HEIC Detection and Conversion (uses $original_path for conversion)
        if ($this->detect_heic($current_raw_data)) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_heic_conversion'));
            $converted_data = null;
            try {
                $converted_data = HeicToJpg::convert($original_path)->get();
            } catch (\Exception $e) {
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_heic_error_1') . $e->getMessage());
                try {
                    $converted_data = HeicToJpg::convertOnMac($original_path, "arm64")->get();
                } catch (\Exception $e_mac) {
                    ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_heic_error_2') . $e_mac->getMessage());
                    $inspection_result->error_message = lang('jcogs_img_heic_conversion_failed') . ': ' . $e_mac->getMessage();
                    return $inspection_result;
                }
            }
            if ($converted_data) {
                $current_raw_data = $converted_data;
                $inspection_result->was_heic_converted = true;
                $inspection_result->original_extension = 'jpg';
            } else {
                $inspection_result->error_message = lang('jcogs_img_heic_conversion_failed_unknown');
                return $inspection_result;
            }
        }

        $inspection_result->file_size = strlen($current_raw_data);

        // 2. SVG Detection & Sanitization with Enhanced Validation (from _validate_image)
        $sanitized_svg_content = $this->detect_sanitize_svg($current_raw_data);
        if ($sanitized_svg_content !== false) {
            $inspection_result->is_svg = true;
            $inspection_result->processed_binary_data = $sanitized_svg_content;
            $inspection_result->detected_mime_type = 'image/svg+xml';
            
            // Check if SVG content is empty after sanitization
            if (empty(trim($sanitized_svg_content))) {
                $inspection_result->error_message = lang('jcogs_img_svg_empty_after_sanitization');
                return $inspection_result;
            }
            
            // Validate SVG structure - ensure it has proper SVG tags
            if (!str_contains(strtolower($sanitized_svg_content), '<svg') || !str_contains(strtolower($sanitized_svg_content), '</svg>')) {
                $inspection_result->error_message = lang('jcogs_img_svg_invalid_structure');
                return $inspection_result;
            }
            
            // Check for minimum SVG content length to avoid trivial/malformed SVGs
            if (strlen($sanitized_svg_content) < 50) {
                $inspection_result->error_message = lang('jcogs_img_svg_too_small');
                return $inspection_result;
            }
            
            // Force save_as to 'svg' - SVGs cannot be converted to other formats
            $inspection_result->original_extension = 'svg';

            // Create SVG Imagine object and handle dimensions
            try {
                $svg_image = (new \Contao\ImagineSvg\Imagine())->load($sanitized_svg_content);
                $svg_size = $svg_image->getSize(); // SvgBox object
            } catch (\Exception $e) {
                $inspection_result->error_message = lang('jcogs_img_imagine_error') . ': ' . $e->getMessage();
                return $inspection_result;
            }
            
            // Handle SVG dimension types
            $setting_default_w = (int)($this->settings['img_cp_default_img_width'] ?? 100);
            $setting_default_h = (int)($this->settings['img_cp_default_img_height'] ?? 100);
            $setting_default_w = $setting_default_w > 0 ? $setting_default_w : 1;
            $setting_default_h = $setting_default_h > 0 ? $setting_default_h : 1;

            switch ($svg_size->getType()) {
                case \Contao\ImagineSvg\SvgBox::TYPE_NONE:
                    // SVG has no defined size - use parameters or defaults
                    $param_width = static::$current_params->width ?? null;
                    $inspection_result->width = $this->validate_dimension($param_width, $setting_default_w) ?: $setting_default_w;
                    
                    $param_height = static::$current_params->height ?? null;
                    $inspection_result->height = $this->validate_dimension($param_height, $setting_default_h) ?: $setting_default_h;
                    break;
                    
                case \Contao\ImagineSvg\SvgBox::TYPE_ASPECT_RATIO:
                    // SVG has relative size
                    if ($svg_size->getWidth() > 0) {
                        $aspect_ratio = $svg_size->getHeight() / $svg_size->getWidth();
                    } else {
                        $aspect_ratio = $setting_default_h / $setting_default_w;
                    }
                    
                    $param_width = static::$current_params->width ?? null;
                    $inspection_result->width = $this->validate_dimension($param_width, $setting_default_w) ?: $setting_default_w;
                    $inspection_result->height = max(1, (int)round($inspection_result->width * $aspect_ratio));
                    break;
                    
                default: // TYPE_FIXED or other
                    $inspection_result->width = $svg_size->getWidth();
                    $inspection_result->height = $svg_size->getHeight();
                    
                    // Fallback if dimensions are invalid
                    if ($inspection_result->width <= 0 || $inspection_result->height <= 0) {
                        $inspection_result->width = $setting_default_w;
                        $inspection_result->height = $setting_default_h;
                    }
                    break;
            }
            
            // Calculate aspect ratio
            if ($inspection_result->width > 0 && $inspection_result->height > 0) {
                $inspection_result->aspect_ratio = $inspection_result->height / $inspection_result->width;
            }            
            
            // Validate SVG against maximum dimension limits if dimensions were extracted
            if ($inspection_result->width && $inspection_result->height) {
                if ($this->settings['img_cp_default_max_image_dimension'] > 0 && 
                    max($inspection_result->width, $inspection_result->height) > $this->settings['img_cp_default_max_image_dimension']) {
                    
                    ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_svg_exceeds_max_dimension'), 
                        max($inspection_result->width, $inspection_result->height), 
                        $this->settings['img_cp_default_max_image_dimension']));
                    $inspection_result->error_message = sprintf(lang('jcogs_img_svg_exceeds_max_dimension'), 
                        max($inspection_result->width, $inspection_result->height), 
                        $this->settings['img_cp_default_max_image_dimension']);
                    return $inspection_result;
                }
            }
            
            $inspection_result->is_valid = true;
            return $inspection_result;
        }

        // 3. For non-SVG, use getimagesizefromstring and other checks
        $image_size_info = @getimagesizefromstring($current_raw_data);

        if ($image_size_info === false) {
            $inspection_result->error_message = lang('jcogs_img_not_recognized_image');
            return $inspection_result;
        }

        $inspection_result->width = $image_size_info[0];
        $inspection_result->height = $image_size_info[1];
        $inspection_result->detected_mime_type = $image_size_info['mime'] ?? null;
        if ($inspection_result->width > 0 && $inspection_result->height > 0) {
            $inspection_result->aspect_ratio = $inspection_result->height / $inspection_result->width;
        }

        // 4. Specific type detections
        $inspection_result->is_png = ($inspection_result->detected_mime_type === 'image/png');
        $inspection_result->is_animated_gif = $this->is_animated_gif($current_raw_data);

        // 5. MERGED VALIDATION CHECKS FROM _get_working_image()
        if (!$inspection_result->width || !$inspection_result->height || $inspection_result->width <= 0 || $inspection_result->height <= 0) {
            $inspection_result->error_message = lang('jcogs_img_getimagesize_error');
            return $inspection_result;
        }

        // 6. EXIF ORIENTATION HANDLING AND IMAGINE OBJECT CREATION
        // Create Imagine object with EXIF support if available
        $use_exif = ee('jcogs_img:Utilities')->allow_url_fopen_enabled();
        $imagine = new \Imagine\Gd\Imagine();
        
        try {
            if ($use_exif) {
                $imagine_image = $imagine->setMetadataReader(new \Imagine\Image\Metadata\ExifMetadataReader())->load($current_raw_data);
            } else {
                $imagine_image = $imagine->load($current_raw_data);
            }
        } catch (\Imagine\Exception\RuntimeException $e) {
            $inspection_result->error_message = lang('jcogs_img_imagine_error') . ': ' . $e->getMessage();
            return $inspection_result;
        }

        // Check for and apply EXIF Orientation data
        $orientation = $imagine_image->metadata()->get('ifd0.Orientation') ?? null;
        
        if ($orientation && $orientation != 1) {
            // We have rotation information, and it is not 'do nothing'
            $orientation_actions = [
                2 => function($image) { $image->flipHorizontally(); },
                3 => function($image) { $image->rotate(180); },
                4 => function($image) { $image->flipVertically(); },
                5 => function($image) { $image->flipHorizontally()->rotate(270); },
                6 => function($image) { $image->rotate(90); },
                7 => function($image) { $image->flipHorizontally()->rotate(90); },
                8 => function($image) { $image->rotate(270); },
            ];

            if (isset($orientation_actions[$orientation])) {
                $orientation_actions[$orientation]($imagine_image);
                // Update the raw data with the orientation-corrected image
                $current_raw_data = $imagine_image->get($inspection_result->was_heic_converted ? 'jpg' : 'png');
                $inspection_result->file_size = strlen($current_raw_data);
                
                // Update dimensions after orientation correction
                $corrected_size = $imagine_image->getSize();
                $inspection_result->width = $corrected_size->getWidth();
                $inspection_result->height = $corrected_size->getHeight();
                if ($inspection_result->width > 0 && $inspection_result->height > 0) {
                    $inspection_result->aspect_ratio = $inspection_result->height / $inspection_result->width;
                }
            }
        }

        // Check for maximum dimension limit (before auto-adjust)
        if ($this->settings['img_cp_default_max_image_dimension'] > 0 && 
            max($inspection_result->width, $inspection_result->height) > $this->settings['img_cp_default_max_image_dimension'] &&
            substr(strtolower($this->settings['img_cp_enable_auto_adjust']), 0, 1) != 'y') {
            
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_exceeds_max_dimension'), 
                max($inspection_result->width, $inspection_result->height), 
                $this->settings['img_cp_default_max_image_dimension']));
            $inspection_result->error_message = sprintf(lang('jcogs_img_exceeds_max_dimension'), 
                max($inspection_result->width, $inspection_result->height), 
                $this->settings['img_cp_default_max_image_dimension']);
            return $inspection_result;
        }

        // Check for maximum file size limit (before auto-adjust)
        if ($inspection_result->file_size > $this->settings['img_cp_default_max_image_size'] * 1000000 &&
            substr(strtolower($this->settings['img_cp_enable_auto_adjust']), 0, 1) != 'y') {
            
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_too_large_to_process'), 
                ee('jcogs_img:Utilities')->formatBytes($inspection_result->file_size));
            $inspection_result->error_message = lang('jcogs_img_too_large_to_process') . ': ' . 
                ee('jcogs_img:Utilities')->formatBytes($inspection_result->file_size);
            return $inspection_result;
        }

        // 7. AUTO-ADJUST LOGIC FROM _get_working_image()
        if (!$inspection_result->is_animated_gif && !$inspection_result->is_svg && 
            substr(strtolower($this->settings['img_cp_enable_auto_adjust']), 0, 1) == 'y') {
            
            $auto_adjust_time_start = microtime(true);
            $auto_adjust_applied = false;

            // First check source image against any max image dimension
            if ($this->settings['img_cp_default_max_image_dimension'] > 0 && 
                max($inspection_result->width, $inspection_result->height) > $this->settings['img_cp_default_max_image_dimension']) {
                
                ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_auto_adjust_active_dimensions'), 
                    $this->settings['img_cp_default_max_image_dimension']));

                $rescale_ratio = $this->settings['img_cp_default_max_image_dimension'] / 
                    max($inspection_result->width, $inspection_result->height);

                try {
                    $rescaled_image_box = new \Imagine\Image\Box(
                        (int) round($inspection_result->width * $rescale_ratio, 0),
                        (int) round($inspection_result->height * $rescale_ratio, 0)
                    );
                    
                    $imagine_image->resize($rescaled_image_box);
                    $current_raw_data = $imagine_image->get($inspection_result->was_heic_converted ? 'jpg' : 'png');
                    
                    // Update dimensions
                    $inspection_result->width = $rescaled_image_box->getWidth();
                    $inspection_result->height = $rescaled_image_box->getHeight();
                    $inspection_result->file_size = strlen($current_raw_data);
                    $inspection_result->aspect_ratio = $inspection_result->height / $inspection_result->width;
                    
                    $auto_adjust_applied = true;
                    
                } catch (\Imagine\Exception\RuntimeException $e) {
                    ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'), 'Auto-adjust dimension - ' . $e->getMessage());
                    ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_auto_adjust_active_dimensions_failed'), 
                        $this->settings['img_cp_default_max_image_dimension']));
                    $inspection_result->error_message = lang('jcogs_img_auto_adjust_failed') . ': ' . $e->getMessage();
                    return $inspection_result;
                }
            }

            // Second check the image filesize
            if ($inspection_result->file_size > $this->settings['img_cp_default_max_image_size'] * 1000000) {
                ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_auto_adjust_active_size'), 
                    $this->settings['img_cp_default_max_image_size']));

                $rescale_ratio = sqrt(($this->settings['img_cp_default_max_image_size'] * 1000000) / $inspection_result->file_size);

                try {
                    $rescaled_image_box = new \Imagine\Image\Box(
                        (int) round($inspection_result->width * $rescale_ratio, 0),
                        (int) round($inspection_result->height * $rescale_ratio, 0)
                    );
                    
                    $imagine_image->resize($rescaled_image_box);
                    $current_raw_data = $imagine_image->get($inspection_result->was_heic_converted ? 'jpg' : 'png');
                    
                    // Update dimensions
                    $inspection_result->width = $rescaled_image_box->getWidth();
                    $inspection_result->height = $rescaled_image_box->getHeight();
                    $inspection_result->file_size = strlen($current_raw_data);
                    $inspection_result->aspect_ratio = $inspection_result->height / $inspection_result->width;
                    
                    $auto_adjust_applied = true;
                    
                } catch (\Imagine\Exception\RuntimeException $e) {
                    ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'), 'Auto-adjust size - ' . $e->getMessage());
                    ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_auto_adjust_active_size_failed'), 
                        $this->settings['img_cp_default_max_image_size']));
                    $inspection_result->error_message = lang('jcogs_img_auto_adjust_failed') . ': ' . $e->getMessage();
                    return $inspection_result;
                }
            }

            if ($auto_adjust_applied) {
                $auto_adjust_elapsed_time = microtime(true) - $auto_adjust_time_start;
                ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_auto_adjust_success'), 
                    max($inspection_result->width, $inspection_result->height), 
                    $inspection_result->file_size / 1000000, 
                    $auto_adjust_elapsed_time));
            }
        }

        // 8. Final validation - ensure we have valid processed data
        if (empty($current_raw_data)) {
            $inspection_result->error_message = lang('jcogs_img_no_image_data_after_processing');
            return $inspection_result;
        }

        $inspection_result->processed_binary_data = $current_raw_data;
        $inspection_result->is_valid = true;

        return $inspection_result;
    }

    /**
     * Utility function: Adjusts the value of a parameter to pixel value without units, by either:
     *  * removing px from end of string
     *  * converting % value to pixel value.
     *
     * @param string $param
     * @param string|float|int|null $base_length
     * @return int|bool|null
     */
    public function validate_dimension(?string $param = null, string|float|int|null $base_length = null): bool|int|null
    {
        // If we get null or an empty string, return null
        if (is_null($param) || (is_string($param) && strlen($param) === 0)) {
            return null;
        }

        // Ensure we have an integer value for base_length
        $base_length = $base_length ? round($base_length, 0) : $base_length;

        // Check if the parameter is a percentage
        if (str_ends_with($param, '%')) {
            // If we get a percentage and no base_length, return false
            return $base_length ? round($base_length * intval($param) / 100, 0) : false;
        }

        // Check if the parameter is in pixels
        if (str_ends_with($param, 'px')) {
            return intval($param);
        }

        // Check if the parameter is zero
        if ($param === '0' || $param === 0) {
            return 0;
        }

        // Cast to integer - if not an integer it will give 0
        if ((int) $param !== 0) {
            return (int) $param;
        }

        // Not sure what it is, so log a debug message and return false
        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_invalid_dimension'), $param);
        return false;
    }

    /**
     * Utility function: that an image format will be accepted by current browser
     * 
     * If image format is not accepted, returns false instead.
     * 
     * @param string $image_format
     * @return bool $image_format
     */
    public function validate_browser_image_format(string $image_format): bool
    {
        // Check if browser checking is enabled
        if ($this->settings['img_cp_enable_browser_check'] === 'y') {
            // Get the browser image capabilities
            $browser_capabilities = $this->_get_browser_image_capabilities();

            // Check if the image format is supported by the browser
            if (!in_array($image_format, $browser_capabilities)) {
                ee('jcogs_img:Utilities')->debug_message(sprintf("Browser does not support the image format: %s", $image_format));
                return false;
            }
        }

        // If browser checking is disabled or the format is supported, return true
        return true;
    }

    /**
     * Utility function: that an image format will be accepted by current server
     * 
     * If image format is not accepted, returns false.
     * 
     * @param string $image_format
     * @return bool 
     */
    public function validate_server_image_format(string $image_format): bool
    {
        try {
            $imageFormat = ImageFormat::from($image_format);
            return in_array($imageFormat->value, $this->_get_server_capabilities());
        } catch (\ValueError $e) {
            return false;
        }
    }

    private function _isNonValidAttribute(string $param): bool
    {
        return !array_key_exists(strtolower($param), static::$dimension_params) && 
            !array_key_exists(strtolower($param), static::$valid_params) &&
            !in_array($param, ['act_var_prefix', 'act_tagdata']);
    }

    /**
     * Utility function: validate some parameters that have complex needs
     * 
     * @param array $parameters
     * @return mixed $image_format
     */
    private function _validate_parameters(string $param, $value)
    {
        switch ($param) {
            case 'src':
            case 'fallback_src':
                $value = $this->_validate_src($value);
                break;
            case 'bg_color':
                $value = $this->validate_colour_string($value);
                break;
            case 'filename':
            case 'filename_prefix':
            case 'filename_suffix':
                $value = $this->_validate_filename($value);
                break;
            case 'cache_dir':
                $value = $this->_validate_cache_dir($value);
                break;
            case 'face_detect_sensitivity':
                $value = max(min(intval($value), 9), 1);
                break;
            case 'palette_size':
                $value = max(intval($value), 2);
                break;
        }
        return $value;
    }

    /**
     * Validates and processes the source parameter value.
     *
     * This method performs the following operations:
     * 1. Checks if the source parameter value is URL-encoded and decodes it if necessary.
     * 2. Checks if the value is a text version of an ExpressionEngine (EE) file field and processes it accordingly.
     *
     * @param string $value The source parameter value to be validated and processed.
     * @return string The validated and processed source parameter value.
     */
    private function _validate_src(string $value): string
    {
        // Just in case src parameter value coming from stash, urldecode it ...
        // $is_encoded = preg_match('~%[0-9A-F]{2}~i', $value);
        // if ($is_encoded) {
        //     $value = urldecode(str_replace(['+', '='], ['%2B', '%3D'], $value));
        // }
        // Check to see if it is a text version of an EE file field
        if ($ee_filedir_test = ee('jcogs_img:Utilities')->parseFiledir($value)) {
            $value = $ee_filedir_test != '' ? $ee_filedir_test : $value;
        }
        return $value;
    }

    /**
     * Validates and sanitizes a filename to ensure it does not contain any URI incompatible characters.
     *
     * This method replaces certain characters with their URL-encoded equivalents and makes the filename URL safe.
     *
     * @param string $value The filename to be validated and sanitized.
     * @return string The sanitized filename.
     */
    private function _validate_filename(string $value): string
    {
        // We cannot allow these to contain any URI incompatible characters
        ee()->load->library('api');
        return urlencode(str_replace(['+', '=', ' '], ['%2B', '%3D', '_'], ee()->legacy_api->make_url_safe($value)));
    }

    /**
     * Validates the cache directory path.
     *
     * This method trims the provided directory path, checks if it is not empty,
     * and verifies if the directory exists. If the directory does not exist and
     * cannot be created, it logs a debug message and sets the path back to the default.
     *
     * @param string $value The directory path to validate.
     * @return string The validated directory path.
     */
    private function _validate_cache_dir(string $value): string
    {
        $value = trim($value, '/');
        // We don't allow the cache_dir to be the root, so check that first
        $value = $value != '' ? $value : static::$valid_params['cache_dir'];
        if (!$this->directoryExists($value, true)) {
            // Cache path provided does not exist and cannot be created
            // So put a note in debug log and set path back to default
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_cache_path_not_found'), $value));
            $value = static::$valid_params['cache_dir'];
        }
        return $value;
    }

}