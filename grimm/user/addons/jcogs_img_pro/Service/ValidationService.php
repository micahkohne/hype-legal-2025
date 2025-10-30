<?php

/**
 * JCOGS Image Pro - Validation Service
 * ====================================
 * Phase 2: Native implementation for validation operations
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 2 Native Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service;

use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

use JCOGSDesign\JCOGSImagePro\Contracts\SettingsInterface;

/**
 * ImageFormat Enum
 * 
 * Supported image formats with MIME type mapping
 */
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

/**
 * ImageInspectionResult Class
 * 
 * Contains results of image inspection and validation
 */
class ImageInspectionResult {
    public ?string $processed_binary_data = null;
    public string $original_file_path;
    public ?string $original_file_name = null;
    public ?string $original_extension = null;
    public ?string $detected_mime_type = null;
    public ?int $width = null;
    public ?int $height = null;
    public ?float $aspect_ratio = null;
    public bool $is_valid = false;
    public array $errors = [];

    public function __construct(string $original_file_path_param) {
        $this->original_file_path = $original_file_path_param;
    }
}

/**
 * ValidationService
 * 
 * Provides validation operations for JCOGS Image Pro.
 * Migrated from ValidationTrait with improved architecture using direct service access.
 */
class ValidationService
{
    private Utilities $utilities;
    private SettingsInterface $settings;
    
    protected static ?object $current_params = null;
    protected static bool $act_based_tag = false;

    public function __construct()
    {
        // Use shared service cache for optimal performance
        $this->utilities = ServiceCache::utilities();
        $this->settings = ServiceCache::settings();
    }

    /**
     * Clear current parameters
     *
     * @return void
     */
    public function clear_params(): void 
    {
        static::$current_params = null;
    }

    /**
     * Get ACT parameter object from encoded packet
     *
     * @return bool|object Parameter object or false on failure
     */
    public function get_act_param_object(): bool|object 
    {
        // If there is no TMPL, it means we're working from an ACT URL. Do we have a packet from ACT call to process?
        $packet = $this->get_parameter('act_packet');
        if ($packet) {
            // We have a packet from ACT call to process. See if we can unpack the packet.
            $decoded_packet = base64_decode($packet);
            if ($decoded_packet === false) {
                $this->utilities->debug_log("Failed to decode base64 packet.");
                return false;
            }
    
            $param_object = json_decode($decoded_packet);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->utilities->debug_log("Failed to decode JSON: " . json_last_error_msg());
                return false;
            }
    
            if (is_object($param_object)) {
                static::$current_params = $param_object;
                static::$act_based_tag = true; // Set ACT processing flag
                return $param_object;
            } else {
                $this->utilities->debug_log("Decoded packet is not an object.");
            }
        }
        return false;
    }

    /**
     * Get browser image format capabilities
     * 
     * Migrated from Legacy ImageUtilities->_get_browser_image_capabilities()
     * Uses HTTP Accept header and User Agent string to determine browser support
     * for modern image formats like WebP and AVIF.
     * 
     * @return array Array of supported image formats
     */
    public function get_browser_image_capabilities(): array
    {
        // Check if the capabilities have already been determined
        static $browser_image_format_support = null;
        if ($browser_image_format_support !== null) {
            return $browser_image_format_support;
        }

        // Commonly accepted formats
        $valid_browser_formats_base = [
            'jpg',
            'jpeg',
            'png',
            'gif',
            'bmp',
        ];

        // Initialize with default formats
        $browser_image_format_support = $valid_browser_formats_base;

        // Check the HTTP Accept header
        if (!empty($_SERVER['HTTP_ACCEPT'])) {
            preg_match_all('/image\/(.*?),/', $_SERVER['HTTP_ACCEPT'], $matches);
            if (!empty($matches[1])) {
                $browser_image_format_support = array_merge($browser_image_format_support, $matches[1]);
            }
        }

        // If no additional formats were found, check the User Agent string
        if (count($browser_image_format_support) === count($valid_browser_formats_base) && !empty($_SERVER['HTTP_USER_AGENT'])) {
            $this->utilities->debug_log("Checking User Agent for browser capabilities: " . $_SERVER['HTTP_USER_AGENT']);

            $user_agent = $_SERVER['HTTP_USER_AGENT'];

            // Android
            if (preg_match('/Android\s([\d.]+);/', $user_agent, $matches)) {
                $this->utilities->debug_log("Detected Android version: " . $matches[1]);
                if (version_compare($matches[1], '4.1', '>')) {
                    $browser_image_format_support[] = 'webp';
                }
                if (version_compare($matches[1], '4.4.4', '>')) {
                    $browser_image_format_support[] = 'avif';
                }
            }
            // Chrome (also covers Edge, Opera)
            elseif (preg_match('/Chrome\/([\d.]+)\s/', $user_agent, $matches)) {
                $this->utilities->debug_log("Detected Chrome version: " . $matches[1]);
                if (version_compare($matches[1], '31', '>')) {
                    $browser_image_format_support[] = 'webp';
                }
                if (version_compare($matches[1], '84', '>')) {
                    $browser_image_format_support[] = 'avif';
                }
            }
            // Firefox
            elseif (preg_match('/Firefox\/([\d.]+)$/', $user_agent, $matches)) {
                $this->utilities->debug_log("Detected Firefox version: " . $matches[1]);
                if (version_compare($matches[1], '64', '>')) {
                    $browser_image_format_support[] = 'webp';
                }
                if (version_compare($matches[1], '92', '>')) {
                    $browser_image_format_support[] = 'avif';
                }
            }
            // Safari
            elseif (preg_match('/Version\/([\d.]+)\sSafari/', $user_agent, $matches)) {
                $this->utilities->debug_log("Detected Safari version: " . $matches[1]);
                if (version_compare($matches[1], '16', '>')) {
                    $browser_image_format_support[] = 'webp';
                    $browser_image_format_support[] = 'avif';
                }
            }
        }

        $this->utilities->debug_log("Browser image format support: " . implode(', ', $browser_image_format_support));
        return $browser_image_format_support;
    }

    /**
     * Get path prefix for processed images
     * 
     * Determines whether to use local URLs, full URLs, or CDN URLs
     *
     * @return string Path prefix
     */
    public function get_image_path_prefix(): string
    {
        // Check if we are operating on a cloud file system
        if ($this->settings->get('img_cp_flysystem_adapter', 'local') === 'local') {
            $image_path_prefix = '/';

            // Check if user requested "Full URL" output
            if (strtolower(substr($this->settings->get('img_cp_class_always_output_full_urls', 'n'), 0, 1)) === 'y') {
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
            $image_path_prefix = $this->_get_adapter_url();
        }
        return $image_path_prefix;
    }

    /**
     * Get MIME type for image format
     * 
     * @param string|null $type Image format
     * @return string MIME type
     */
    public function get_mime_type(?string $type = null): string
    {
        if (!$type) {
            return 'application/octet-stream';
        }

        try {
            $format = ImageFormat::from(strtolower($type));
            return $format->getMimeType();
        } catch (\ValueError $e) {
            // Handle unknown formats
            return match(strtolower($type)) {
                'jpeg' => 'image/jpeg',
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'avif' => 'image/avif',
                'bmp' => 'image/bmp',
                'svg' => 'image/svg+xml',
                default => 'application/octet-stream'
            };
        }
    }

    /**
     * Get single parameter value
     *
     * @param string|null $parameter Parameter name
     * @return mixed Parameter value
     */
    public function get_parameter(?string $parameter = null): mixed 
    {
        if (!$parameter) {
            return null;
        }

        $value = null;

        // Check current params object first
        if (static::$current_params && property_exists(static::$current_params, $parameter)) {
            $value = static::$current_params->$parameter;
        } else {
            // Fall back to $_GET parameters
            $value = $_GET[$parameter] ?? null;
        }

        // Apply validation if we have a value (mirrors Legacy ValidationTrait behavior)
        return $value ? $this->_validate_parameters($parameter, $value) : $value;
    }

    /**
     * Get multiple parameters or parameter object
     *
     * @param string|null $request_param Specific parameter to get
     * @param bool $get_default Whether to include default values
     * @return mixed Parameters array or specific value
     */
    public function get_parameters(?string $request_param = null, bool $get_default = false): mixed
    {
        if ($request_param) {
            return $this->get_parameter($request_param);
        }

        // Return all current parameters
        if (static::$current_params) {
            return static::$current_params;
        }

        return (object) $_GET;
    }

    /**
     * Get server image format capabilities
     * 
     * Migrated from Legacy ImageUtilities->_get_server_capabilities()
     * Checks GD library capabilities to determine what image formats
     * the server can create/write.
     * 
     * @return array Array of supported image formats
     */
    public function get_server_image_capabilities(): array
    {
        // Check if the capabilities have already been determined
        static $valid_server_image_formats = null;
        if ($valid_server_image_formats !== null) {
            return $valid_server_image_formats;
        }

        // Get a list of formats supported by the GD library
        $server_gd_info = gd_info();

        // Work out what capabilities we have... 
        $valid_server_image_formats = [];
        foreach ($server_gd_info as $key => $value) {
            if (!in_array(strtolower(substr($key, 0, 2)), ['gd', 'fr', 'ji'])) {
                $this_capability = explode(' ', strtolower($key));
                if ($value === true && strtolower($this_capability[1]) != 'read') {
                    $valid_server_image_formats[] = $this_capability[0];
                    if ($this_capability[0] == 'jpeg') {
                        $valid_server_image_formats[] = 'jpg';
                    }
                }
            }
        }
        
        $this->utilities->debug_log("Server image format capabilities: " . implode(', ', $valid_server_image_formats));
        return $valid_server_image_formats;
    }

    /**
     * Check if currently processing ACT request
     *
     * @return bool True if processing ACT request
     */
    public function is_act_processing(): bool 
    {
        return static::$act_based_tag;
    }

    /**
     * Process and validate image data
     *
     * @param string $raw_data Raw image data
     * @param string $original_path Original file path
     * @return ImageInspectionResult Validation result
     */
    public function process_and_validate_image_data(string $raw_data, string $original_path): ImageInspectionResult
    {
        $result = new ImageInspectionResult($original_path);
        
        if (empty($raw_data)) {
            $result->errors[] = "Empty image data provided";
            return $result;
        }

        try {
            // Detect image type and get dimensions
            $image_info = getimagesizefromstring($raw_data);
            
            if ($image_info === false) {
                $result->errors[] = "Unable to determine image dimensions";
                return $result;
            }

            $result->width = $image_info[0];
            $result->height = $image_info[1];
            $result->detected_mime_type = $image_info['mime'];
            $result->aspect_ratio = $result->width / $result->height;
            
            // Extract filename and extension
            $path_info = pathinfo($original_path);
            $result->original_file_name = $path_info['filename'] ?? null;
            $result->original_extension = $path_info['extension'] ?? null;

            // Validate image format
            if ($this->validate_server_image_format($result->original_extension ?? '')) {
                $result->processed_binary_data = $raw_data;
                $result->is_valid = true;
            } else {
                $result->errors[] = "Unsupported image format: " . ($result->original_extension ?? 'unknown');
            }

        } catch (\Exception $e) {
            $result->errors[] = "Image processing error: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * Set ACT processing flag to bypass template operations
     *
     * @param bool $flag ACT processing flag
     * @return void
     */
    public function set_act_processing_flag(bool $flag = true): void 
    {
        static::$act_based_tag = $flag;
    }

    /**
     * Validate browser-supported image format
     * 
     * Migrated from Legacy ValidationTrait->validate_browser_image_format()
     * Checks if the specified image format is supported by the current browser.
     *
     * @param string $image_format Image format to validate
     * @return bool True if supported by browser
     */
    public function validate_browser_image_format(string $image_format): bool
    {
        // Check if browser checking is enabled
        if ($this->settings->get('img_cp_enable_browser_check', 'y') === 'y') {
            // Get the browser image capabilities
            $browser_capabilities = $this->get_browser_image_capabilities();

            // Check if the image format is supported by the browser
            if (!in_array($image_format, $browser_capabilities)) {
                $this->utilities->debug_log(sprintf("Browser does not support the image format: %s", $image_format));
                return false;
            }
        }

        // If browser checking is disabled or the format is supported, return true
        return true;
    }

    /**
     * Validate dimension parameter (comprehensive Legacy-compatible implementation)
     * 
     * Migrated from Legacy ValidationTrait->validate_dimension()
     * Handles percentage values, pixel values, unit suffixes, and validation.
     * 
     * @param string|null $param Dimension parameter value
     * @param string|float|int|null $base_length Base length for percentage calculations
     * @return bool|int|null Validated dimension in pixels, false on invalid, null on empty
     */
    public function validate_dimension(?string $param = null, string|float|int|null $base_length = null): bool|int|null
    {
        // Handle null or non-string input
        if (!$param || !is_string($param)) {
            return null;
        }

        $param = trim($param);
        
        // Handle empty parameter
        if (empty($param)) {
            return null;
        }

        // Handle percentage values (e.g., "50%", "75%")
        if (str_ends_with($param, '%')) {
            if (!$base_length || !is_numeric($base_length)) {
                return false; // Cannot calculate percentage without base length
            }
            
            $percentage_value = rtrim($param, '%');
            if (!is_numeric($percentage_value)) {
                return false; // Invalid percentage value
            }
            
            $percentage = (float) $percentage_value;
            
            // Validate percentage range (0-1000% seems reasonable)
            if ($percentage < 0 || $percentage > 1000) {
                return false;
            }
            
            return (int) round(($percentage / 100) * (float) $base_length);
        }

        // Strip common unit suffixes (px, pt, em, rem, etc.)
        $unit_suffixes = ['px', 'pt', 'em', 'rem', 'pc', 'in', 'cm', 'mm', 'ex', 'ch', 'vw', 'vh', 'vmin', 'vmax'];
        
        foreach ($unit_suffixes as $suffix) {
            if (str_ends_with(strtolower($param), strtolower($suffix))) {
                $param = substr($param, 0, -strlen($suffix));
                break;
            }
        }
        
        $param = trim($param);
        
        // Must be numeric after unit stripping
        if (!is_numeric($param)) {
            return false;
        }

        // Convert to integer
        $value = (int) $param;
        
        // Must be positive for dimensions
        if ($value <= 0) {
            return false;
        }
        
        // Reasonable upper limit (10K pixels - matches Legacy constraint)
        if ($value > 10000) {
            return false;
        }

        return $value;
    }

    /**
     * Utility function: check font size value and normalise to px
     * 
     * Uses px = 72/96 * pt principle from here - https://pixelsconverter.com/px-to-pt
     * 
     * @param string $font_size_string
     * @return string $font_size_px
     */
    public function validate_font_size($font_size_string)
    {
        // Check to see if the string has px or pt endings and modify value accordingly
        if (stripos($font_size_string, 'pt')) {
            $font_size = str_replace('pt', '', strtolower($font_size_string));
            return (int) ($font_size * 72 / 96);
        }
        if (stripos($font_size_string, 'px')) {
            $font_size_string = str_replace('px', '', strtolower($font_size_string));
        }
        return (int)($font_size_string);
    }

    /**
     * Validate server-supported image format
     * 
     * Migrated from Legacy ValidationTrait->validate_server_image_format()
     * Checks if the specified image format can be created by the server.
     *
     * @param string $image_format Image format to validate
     * @return bool True if supported by server
     */
    public function validate_server_image_format(string $image_format): bool
    {
        try {
            $imageFormat = ImageFormat::from($image_format);
            return in_array($imageFormat->value, $this->get_server_image_capabilities());
        } catch (\ValueError $e) {
            $this->utilities->debug_log("Invalid image format: " . $image_format);
            return false;
        }
    }

    /**
     * Get adapter URL for cloud storage
     *
     * @return string Adapter URL
     */
    private function _get_adapter_url(): string
    {
        $adapter = $this->settings->get('img_cp_flysystem_adapter', 'local');
        
        return match($adapter) {
            's3' => $this->settings->get('img_cp_flysystem_adapter_s3_url', ''),
            'r2' => $this->settings->get('img_cp_flysystem_adapter_r2_url', ''),
            'dospaces' => $this->settings->get('img_cp_flysystem_adapter_dospaces_url', ''),
            default => '/'
        };
    }

    /**
     * Check if parameter is a non-valid attribute
     *
     * @param string $param Parameter name
     * @return bool True if non-valid
     */

    /**
     * Validate specific parameter values (mirrors Legacy ValidationTrait)
     *
     * @param string $param Parameter name
     * @param mixed $value Parameter value
     * @return mixed Validated value
     */
    private function _validate_parameters(string $param, $value)
    {
        return match($param) {
            'src', 'fallback_src' => $this->_validate_src($value),
            'filename' => $this->_validate_filename($value),
            'cache_dir' => $this->_validate_cache_dir($value),
            'face_detect_sensitivity' => max(min(intval($value), 9), 1),
            'palette_size' => max(intval($value), 2),
            default => $value
        };
    }

    /**
     * Validate source path and handle EE file directives (mirrors Legacy ValidationTrait)
     *
     * @param string $value Source path
     * @return string Validated source path
     */
    private function _validate_src(string $value): string
    {
        // Check to see if it is a text version of an EE file field (mirrors Legacy behavior)
        try {
            $image_utilities = ee('jcogs_img_pro:ImageUtilities');
            $parsed_filedir = $image_utilities->parseFiledir($value);
            if ($parsed_filedir !== '' && $parsed_filedir !== $value) {
                $value = $parsed_filedir;
            }
        } catch (\Exception $e) {
            // If parseFiledir fails, return the original value
            // This mirrors Legacy behavior where invalid paths are passed through
        }
        
        return $value;
    }

    /**
     * Validate filename
     *
     * @param string $value Filename
     * @return string Validated filename
     */
    private function _validate_filename(string $value): string
    {
        // Remove any potentially dangerous characters
        $value = preg_replace('/[^a-zA-Z0-9._\-]/', '', $value);
        
        return $value;
    }

    /**
     * Validate cache directory
     *
     * @param string $value Cache directory path
     * @return string Validated cache directory
     */
    private function _validate_cache_dir(string $value): string
    {
        // Remove any potentially dangerous characters
        $value = preg_replace('/[^a-zA-Z0-9._\-\/]/', '', $value);
        
        // Prevent directory traversal
        $value = str_replace(['../', '.\\'], '', $value);
        
        // Ensure it doesn't start with slash
        $value = ltrim($value, '/');
        
        return $value;
    }
}
