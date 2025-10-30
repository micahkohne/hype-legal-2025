<?php

/**
 * JCOGS Image Pro - Utilities Service
 * ===================================
 * Phase 2: Native EE7 implementation utilities
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

class Utilities
{
    /**
     * @var string Service name for debug logging
     */
    private string $name = 'JCOGS Image Pro';
    
    /**
     * @var array Settings cache for efficient access
     */
    private array $settings;
    
    /**
     * @var bool Track detailed debug state for this instance
     */
    private bool $detailed_debug_enabled = false;
    
    /**
     * Constructor - load settings once for efficient access
     */
    public function __construct()
    {
        // Load settings once for efficient access throughout the service
        try {
            $settings_service = new Settings();
            $this->settings = $settings_service->get_all();
        } catch (\Exception $e) {
            // Fallback to defaults if settings can't be loaded
            $this->settings = ['img_cp_enable_debugging' => 'n'];
        }
    }

    /**
     * Build common sidebar for all Pro control panel pages
     * 
     * @param array $current_settings Current addon settings
     */
    public function build_sidebar(array $current_settings): void
    {
        // Load language file
        ee()->lang->load('jcogs_img_pro', ee()->session->get_language(), false, true, PATH_THIRD . 'jcogs_img_pro/');
        
        $sidebar = ee('CP/Sidebar')->make();

        $sd_div = $sidebar->addHeader(lang('jcogs_img_pro_sidebar_title'));
        $sd_div_list = $sd_div->addBasicList();
        $sd_div_list->addItem(lang('jcogs_img_pro_cp_main_settings'), ee('CP/URL')->make('addons/settings/jcogs_img_pro'));
        $sd_div_list->addItem(lang('jcogs_img_pro_cp_preset_management'), ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
        $sd_div_list->addItem(lang('jcogs_img_pro_cp_caching_sidebar_label'), ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching'));
        $sd_div_list->addItem(lang('jcogs_img_pro_cp_image_settings'), ee('CP/URL')->make('addons/settings/jcogs_img_pro/image_defaults'));
        $sd_div_list->addItem(lang('jcogs_img_pro_advanced_settings'), ee('CP/URL')->make('addons/settings/jcogs_img_pro/advanced_settings'));
        $sd_div_list->addItem(lang('nav_support_page'),  ee()->cp->masked_url(ee('Addon')->get('jcogs_img_pro')->get('docs_url')));
        
        // Debug information section (only show when debugging is enabled)
        if (($current_settings['img_cp_enable_debugging'] ?? 'n') === 'y') {
            $sd_debug = $sidebar->addHeader(lang('jcogs_img_pro_debug_info'));
            $sd_debug_list = $sd_debug->addBasicList();
            $sd_debug_list->addItem(sprintf(lang('jcogs_img_pro_version'), ee('Addon')->get('jcogs_img_pro')->getInstalledVersion()));
            $sd_debug_list->addItem(sprintf(lang('jcogs_img_pro_debug_php_version'), PHP_VERSION));
            $sd_debug_list->addItem(sprintf(lang('jcogs_img_pro_debug_ee_version'), APP_VER));
        }
    }
    
    /**
     * Convert URL to relative path for FilesystemService
     * 
     * Handles both full URLs and relative paths, extracting the path component
     * from URLs and ensuring they point to local resources.
     * 
     * @param string $url URL or path to convert
     * @return string|null Relative path or null if external URL or invalid
     */
    public static function convert_url_to_relative_path(string $url): string
    {
        // If it's already a relative path, return as-is
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            return ltrim($url, '/');
        }
        
        // Parse the URL
        $parsed_url = parse_url($url);
        if (!$parsed_url || !isset($parsed_url['path'])) {
            // Malformed URL or no path - return the URL as-is stripped of protocol
            $fallback = preg_replace('#^https?://[^/]*/?#', '', $url);
            return ltrim($fallback, '/');
        }
        
        // Check if host is available for domain validation
        if (isset($parsed_url['host'])) {
            // Get the site URL to compare domains
            $site_url = ee()->config->item('site_url');
            $site_parsed = parse_url($site_url);
            
            // Check if this is a local URL (same domain)
            if (isset($site_parsed['host']) && $parsed_url['host'] === $site_parsed['host']) {
                // This is a local URL, extract the path
                return ltrim($parsed_url['path'], '/');
            }
        }
        
        // External URL or unable to validate - return the path portion
        return ltrim($parsed_url['path'], '/');
    }
    
    /**
     * Helper method to log debug messages using language file entries
     * Respects the three-state debug system (OFF/ON/DETAILED)
     * 
     * @param string $lang_key Language file key
     * @param mixed ...$args Optional sprintf arguments
     */
    public function debug_log(string $lang_key, ...$args): void 
    {
        // Check CP debug setting - if disabled, no logging at all (OFF state)
        if (($this->settings['img_cp_enable_debugging'] ?? 'n') !== 'y') {
            return; // OFF state - no debug logging
        }
        
        if (function_exists('log_message')) {
            // Get language line
            $message = ee()->lang->line($lang_key);
            
            // If language line not found, use the key as fallback
            if (empty($message)) {
                $message = $lang_key;
            }
            
            // Apply sprintf formatting if arguments provided
            if (!empty($args)) {
                try {
                    $message = sprintf($message, ...$args);
                } catch (\ArgumentCountError $e) {
                    // Handle mismatch between format specifiers and arguments
                    $message = $message . ' [Args: ' . implode(', ', $args) . ']';
                }
            }
            
            log_message('debug', "[{$this->name}] {$message}");
        }
    }

    /**
     * Debug log method for detailed debug messages
    /**
     * JCOGS Image Pro - debug message utility 
     * ======================================
     * Three-state debug system:
     * - OFF: CP setting disabled → No debug output
     * - ON: CP setting enabled, no tag parameter → Standard debug info
     * - DETAILED: CP setting enabled + debug='y' in tag → Verbose debug info
     * 
     * @param string $msg The text to write to debug log (language key)
     * @param array|string $details Optional array with more information  
     * @param bool $mute Optional flag to suppress output
     * @param string $level Debug level: 'standard' or 'detailed'
     * @return void
     */
    public function debug_message(string $msg, array|string|null $details = null, bool $mute = false, string $level = 'standard'): void
    {
        if (!$mute && REQ == 'PAGE' && isset(ee()->TMPL)) {
            // Check CP debug setting - if disabled, no output at all (OFF state)
            if (($this->settings['img_cp_enable_debugging'] ?? 'n') !== 'y') {
                return; // OFF state - no debug output
            }
            
            // Check if detailed debug is requested via instance setting
            $detailed_debug = $this->detailed_debug_enabled;
            
            // ON state: Standard debug when CP enabled but no tag debug='y'
            // DETAILED state: Verbose debug when CP enabled AND tag debug='y'
            if ($level === 'detailed' && !$detailed_debug) {
                return; // Skip detailed messages unless explicitly requested
            }
            
            // Load the Pro language file if not already loaded
            ee()->lang->load('jcogs_img_pro', ee()->session->get_language(), false, true, PATH_THIRD . 'jcogs_img_pro/');
            
            // Get the message from the Pro language file
            $message = ee()->lang->line($msg);
            
            // If language line not found, use the key as fallback
            if (empty($message)) {
                $message = $msg;
            }
            
            // If details provided and it's an array with sprintf args, apply formatting
            if (is_array($details) && !empty($details)) {
                try {
                    // Check if message contains positional parameters (like %1$s, %2$d)
                    if (preg_match('/%\d+\$/', $message)) {
                        // For positional parameters, use vsprintf instead of sprintf with unpacking
                        $message = vsprintf($message, $details);
                    } else {
                        // For regular parameters, use sprintf with unpacking
                        $message = sprintf($message, ...$details);
                    }
                    $details = null; // Clear details since we used them for sprintf
                } catch (\ValueError $e) {
                    // If sprintf fails due to format issues, fall back to simple concatenation
                    $message = $message . ' [' . implode(', ', $details) . ']';
                    $details = null;
                } catch (\ArgumentCountError $e) {
                    // If sprintf fails due to argument count mismatch (e.g., pipe characters), fall back to simple concatenation
                    $message = $message . ' [' . implode(', ', $details) . ']';
                    $details = null;
                } catch (\Exception $e) {
                    // Catch any other sprintf-related errors
                    $message = $message . ' [' . implode(', ', $details) . ']';
                    $details = null;
                }
            }
            
            // Get caller information using debug_backtrace (like legacy)
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = lang('jcogs_img_pro_module_name'); // "JCOGS Image Pro"
            
            // Format message with legacy styling: addon name (method) blue-message details
            if (is_array($details)) {
                ee()->TMPL->log_item($caller . ' (' . ($dbt[1]['function'] ?? 'unknown') . ') ' . '<span style=\'color:darkblue\'>' . $message . '</span>', $details);
            } else {
                ee()->TMPL->log_item($caller . ' (' . ($dbt[1]['function'] ?? 'unknown') . ') ' . '<span style=\'color:darkblue\'>' . $message . '</span>' . ($details ? ' <span style=\'color:var(--ee-link)\'>' . $details . '</span>' : ''));
            }
        }
    }

    /**
     * Format file size in human-readable format
     * 
     * @param int $bytes File size in bytes
     * @param int $precision Number of decimal places
     * @return string Formatted file size
     */
    public function format_file_size(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Format bytes into human readable format
     * Migrated from Legacy Utilities service
     *
     * @param integer $bytes
     * @param integer $precision
     * @return string
     */
    public function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KiB', 'MiB', 'GiB', 'TiB');

        $bytes = max(intval($bytes), 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        // Uncomment one of the following alternatives
        $bytes /= pow(1024, $pow);
        // $bytes /= (1 << (10 * $pow)); 

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Get action ID for a specific action method
     * 
     * @param string $method The action method name
     * @return int|null The action ID or null if not found
     */
    public function get_action_id(string $method): ?int
    {
        $actions = ee()->db->where('class', 'Jcogs_img_pro')
                          ->where('method', $method)
                          ->get('actions')
                          ->result_array();
        
        return isset($actions[0]['action_id']) ? (int) $actions[0]['action_id'] : null;
    }

    /**
     * Utility function: Get the basepath
     *
     * @return	string|boolean	$path
     */
    public function get_base_path()
    {
        // Is base_path set?
        if (!ee()->config->item('base_path')) {
            // $basepath is missing, so put a note into template debugger...
            $this->debug_message(lang('jcogs_utils_no_base_path'), rtrim(ee()->config->item('base_path'), '/') . '/');
            return false;
        }

        // Normalize path if we need to 

        // Remove invisible control characters
        $path = preg_replace('#\\p{C}+#u', '', ee()->config->item('base_path'));
        // Fix up DOS and multiple slashes etc
        $path = str_replace('//', '/', implode([
            in_array(substr($path, 0, 1), ['/', '\\']) ? '/' : '',
            $path,
            in_array(substr($path, -1), ['/', '\\']) ? '/' : ''
        ])).'/';

        // Now return the path if it is considered valid ... use php function because we do this before 
        // Flysystem driver is loaded, so using Flysystem causes infinite loop.
        // However we know base path is going to be local ... so not an issue!
        if(is_dir($path)) {
            return rtrim($path,'/').'/';
        }

        // Otherwise report an issue to template debugger
        $this->debug_message(lang('jcogs_utils_invalid_base_path'), $path);
        return false;
    }

    /**
     * Extract connection name from URL segments
     * 
     * For URLs like /addons/settings/jcogs_img_pro/caching/audit_cache/my_connection
     * we need to get 'my_connection' from the URL segments
     * 
     * @return string|null Connection name or null if not found
     */
    public function getConnectionNameFromUrl()
    {
        // Get the current URI segments
        $uri = ee()->uri->uri_string();
        
        // Look for the pattern: /caching/[operation]/[connection_name]
        // Supports both audit_cache and clear_cache operations
        if (preg_match('/\/caching\/(?:audit_cache|clear_cache)\/(.+?)(?:\/|$)/', $uri, $matches)) {
            $connection_name = urldecode($matches[1]);
            return $connection_name;
        }
        
        return null;
    }

    /**
     * Extract preset ID from URL segments
     * 
     * For URLs like /addons/settings/jcogs_img_pro/presets/edit/123
     * or /addons/settings/jcogs_img_pro/presets/add_parameter/123
     * we need to get '123' from the URL segments
     * 
     * @return int|null Preset ID or null if not found
     */
    public function getPresetIdFromUrl(): ?int
    {
        // Get the current URI segments
        $uri = ee()->uri->uri_string();
        
        // Look for the pattern: /presets/[operation]/[preset_id]
        // Supports edit, delete, export, import, duplicate, add_parameter, delete_parameter, analytics, and other preset operations
        if (preg_match('/\/presets\/(?:edit|delete|export|import|duplicate|add_parameter|delete_parameter|edit_parameter|analytics)\/(\d+)(?:\/|$)/', $uri, $matches)) {
            return (int)$matches[1];
        }
        
        return null;
    }

    /**
     * Get parameter name from URL segments for preset parameter operations
     * 
     * Used by preset parameter management routes (EditParameter, DeleteParameter)
     * to extract the parameter name from URLs like:
     * /addons/settings/jcogs_img_pro/presets/edit_parameter/123/width
     * /addons/settings/jcogs_img_pro/presets/delete_parameter/456/crop_method
     * 
     * @return string|null Parameter name (URL decoded) or null if not found
     */
    public function getParameterNameFromUrl(): ?string
    {
        $uri = ee()->uri->uri_string();
        
        // Pattern: /presets/[operation]/[preset_id]/[parameter_name]
        // Supports edit_parameter, delete_parameter operations
        if (preg_match('/\/presets\/(?:edit_parameter|delete_parameter)\/\d+\/([^\/]+)(?:\/|$)/', $uri, $matches)) {
            return urldecode($matches[1]);
        }
        
        return null;
    }

    /**
     * Convert upload location ID to real path
     * 
     * Migrated from Legacy Utilities->parseFiledir()
     * Handles both EE6/7 file field API and direct upload destination ID lookup.
     * 
     * @param mixed $location Upload location ID or file directory reference
     * @return string|bool File path or false on failure
     */
    public function parse_file_directory($location)
    {
        $path = '';
        
        if (substr(APP_VER, 0, 1) == 7) {
            // EE7 approach using file_field library
            ee()->load->library('file_field');
            $file_path = ee()->file_field->getFileModelForFieldData($location);
            
            if ($file_path) {
                // Get absolute path from file model
                $path = $file_path->getAbsolutePath();
                
                // Remove base path for compatibility (following Legacy pattern)
                $base_path = $this->get_base_path();
                if ($base_path && str_contains($path, $base_path)) {
                    $path = str_replace(rtrim($base_path, '/'), '', $path);
                } else {
                    // Something's wrong - log and return false
                    $this->debug_log('jcogs_utils_no_base_path', $path);
                    return false;
                }
            } else {
                // Location is not a valid file path
                return '';
            }
        } else {
            // EE6 approach - handle filedir format and direct ID lookup
            if (str_contains($location, 'filedir_')) {
                preg_match('/{filedir_(.*?)}(.*)$/', $location, $matches);
                if (isset($matches[0])) {
                    $location = $matches[1];
                } else {
                    return '';
                }
            }
            
            if (intval($location) > 0) {
                $upload_dest = ee('Model')->get('UploadDestination')->filter('id', $location)->first();
                if ($upload_dest) {
                    $path = $upload_dest->url;
                    $path = isset($matches[2]) ? $path . $matches[2] : $path;
                }
            }
        }
        
        return $path;
    }

    /**
     * Utility function: Optionally creates and returns the path in which we will be working with
     * our files
     *
     * @param string $rel_path
     * @param boolean $mk_dir
     * @return string
     */
    public function path($rel_path = '', $mk_dir = false)
    {
        // Get basepath, add rel path and check if exists.
        if (!$path = $this->get_base_path()) {
            // We cannot operate without a valid base_path so bale out!
            $this->debug_message(lang('jcogs_utils_no_base_path'), [$path]);
            return false;
        };

        // Check for and remove double-slashes if any present in composite path
        ee()->load->helper('string');
        $clean_path = reduce_double_slashes($path . $rel_path);

        // Got a good base_path so test the rest of the path provided ... 
        if (!ee('Filesystem')->exists($clean_path) && $mk_dir) {
            ee('Filesystem')->mkDir($clean_path);
        }
        return rtrim($clean_path, '/') . '/';
    }
    
    /**
     * Set detailed debug mode based on tag parameters
     * 
     * @param array $tag_params Tag parameters from template
     * @return void
     */
    public function set_debug_mode(array $tag_params): void
    {
        if (isset($tag_params['debug'])) {
            $debug_param = strtolower($tag_params['debug']);
            $this->detailed_debug_enabled = in_array($debug_param, ['y', 'yes', '1', 'true', 'on']);
        }
    }

    /**
     * Determine whether to add width/height attributes to image tags
     * 
     * Consolidates logic from OutputGenerationService and OutputStage to eliminate duplication.
     * Checks multiple parameters and conditions that should force dimension attributes:
     * - add_dims parameter (primary control)
     * - add_dimensions parameter (alias for add_dims)
     * - Lazy loading requirements (forces dimensions for layout stability)
     * - Animated GIFs (forces dimensions for proper display)
     * - HTML5 loading="lazy" attribute presence
     * 
     * @param object $context Context object with parameter access methods
     * @return bool True if width/height attributes should be added
     */
    public function should_add_dimensions($context): bool
    {
        // Check explicit parameters first - add_dims has priority
        $add_dims = $context->get_param('add_dims', '');
        if (!empty($add_dims)) {
            return substr(strtolower($add_dims), 0, 1) === 'y';
        }
        
        // Check add_dimensions parameter (alias for add_dims)
        $add_dimensions = $context->get_param('add_dimensions', '');
        if (!empty($add_dimensions)) {
            return substr(strtolower($add_dimensions), 0, 1) === 'y';
        }
        
        // Force dimensions for lazy loading (prevents layout jank)
        $lazy_param = $context->get_param('lazy', '');
        if (!empty($lazy_param)) {
            $lazy_lower = strtolower($lazy_param);
            // Check for JCOGS lazy loading modes that require dimensions
            if (str_starts_with($lazy_lower, 'l') || str_starts_with($lazy_lower, 'd')) {
                return true;
            }
        }
        
        // Force dimensions for animated GIFs (ensures proper display)
        if ($context->get_metadata_value('is_animated_gif', false)) {
            return true;
        }
        
        // Check for existing HTML5 loading="lazy" attribute
        // Note: This would need to be implemented based on existing attribute checking
        // For now, we'll skip this check as it would require examining tag content
        
        // Default: don't add dimensions unless explicitly requested or required
        return false;
    }    
}
