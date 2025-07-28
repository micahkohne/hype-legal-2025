<?php

/**
 * Image Utility Service
 * =====================
 * Service for infrequently used image utilities
 * =============================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.4.16.2
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.2.12
 */

namespace JCOGSDesign\Jcogs_img\Service;

require_once PATH_THIRD . "jcogs_img/vendor/autoload.php";
require_once PATH_THIRD . "jcogs_img/config.php";

// Flysystem API
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter as AwsPortableVisibilityConverter;
use League\Flysystem\Filesystem;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use League\Flysystem\Local\LocalFilesystemAdapter;

// JCOGS Design Traits
use JCOGSDesign\Jcogs_img\Service\ImageUtilities\Traits\CacheManagementTrait;
use JCOGSDesign\Jcogs_img\Service\ImageUtilities\Traits\FileSystemTrait;
use JCOGSDesign\Jcogs_img\Service\ImageUtilities\Traits\ImageProcessingTrait;
use JCOGSDesign\Jcogs_img\Service\ImageUtilities\Traits\ColourManagementTrait;
use JCOGSDesign\Jcogs_img\Service\ImageUtilities\Traits\ValidationTrait;

enum AdapterType: string {
    case LOCAL = 'local';
    case S3 = 's3';
    case R2 = 'r2';
    case DOSPACES = 'dospaces';
}

class ImageUtilities
{
    /**
     * Settings array from jcogs_img:Settings
     * @var array
     */
    protected array $settings;
    private        $adapter                      = null;
    private        $client                       = null;
    // private static $adapter_url                  = null;
    // private static $filesystem                   = null;
    private static $is_1_4_4_installed           = null;
    private static $site_id                      = null;
    public         $cache_info                   = null;
    public         $cache_status_string          = '';
    public static  $act_based_tag                = false;
    public static  $act_params                   = null;
    public static  $action_link                  = 'no';
    public static  $adapter_name                 = null;
    public static  $browser_image_format_support;
    public static  $cache_adapter_string         = '';
    public static  $cache_dir_locations          = null;
    public static  $cache_log_index              = null;
    public static  $control_params               = null;
    public static  $current_params               = [];
    public static  $dimension_params             = null;
    public static  $image_log_needs_updating     = false;
    public static  $inbound_params               = null;
    public static  $instance_count               = 0;
    public static  $license_status;
    public static  $transformational_params      = null;
    public static  $valid_directories            = [];
    public static  $valid_params                 = null;
    public static  $valid_server_image_formats;
    private static array $filesystems            = [];
    private static array $adapter_urls           = [];
    private static array $cache_adapter_strings  = [];

    private readonly AdapterType $adapter_type;

    use CacheManagementTrait;
    use FileSystemTrait;
    use ImageProcessingTrait;
    use ColourManagementTrait;
    use ValidationTrait;

    /**
     * Class ImageUtilities
     * 
     * This class provides various utility functions for image processing.
     * 
     * @package jcogs_img\Service
     */
    public function __construct()
    {
        // Setup some settings ... :)
        ee()->load->helper('string');
        $this->settings = ee('jcogs_img:Settings')::$settings;
    
        // Get site_id
        if (is_null(static::$site_id)) {
            static::$site_id = ee()->config->item('site_id');
        }

        // Preload session cache to prevent issues later ... 
        if (! isset(ee()->session->cache[JCOGS_IMG_CLASS]['cats_are_evil'])) {
                ee()->session->cache[JCOGS_IMG_CLASS]['cats_are_evil'] = 'you have been warned!';
            }


        // Check to see if1.4.4 or later is loaded
        if (is_null(static::$is_1_4_4_installed)) {
            static::$is_1_4_4_installed = version_compare(ee('Addon')->get('jcogs_img')->getInstalledVersion(), '1.4.5.RC1', 'ge');
        }

        // Check to see if we already have our parameters set up
        // If not, set them up
        if(empty(static::$current_params) || empty(static::$valid_params) || empty(static::$transformational_params) || empty(static::$dimension_params) || empty(static::$control_params)) {

            // Valid Params type can be of three kinds - 
            //  transformational - where the parameter potentially can affect the image itself
            //  dimensional - where the parameter affects the size of the output image, and may have various unit types associated with value
            //  control - where the parameter does not affect image directly but controls some Image operations (e.g. preload)
            // Parameters also have a default value
            // Set up an array of valid parameters with this information for each ... 
            static::$inbound_params = [
                'act'                     =>
                    [
                        'default' => null,
                        'type'    => 'control'
                    ],
                'act_packet'              =>
                    [
                        'default' => null,
                        'type'    => 'control'
                    ],
                'act_tagdata'             =>
                    [
                        'default' => '',
                        'type'    => 'control'
                    ],
                'action_link'             =>
                    [
                        'default' => $this->settings['img_cp_action_links'],
                        'type'    => 'control'
                    ],
                'add_dimensions'          =>
                    [
                        'default' => null,
                        'type'    => 'control'
                    ],
                'add_dims'                =>
                    [
                        'default' => null,
                        'type'    => 'control'
                    ],
                'allow_scale_larger'      =>
                    [
                        'default' => $this->settings['img_cp_allow_scale_larger_default'],
                        'type'    => 'control'
                    ],
                'aspect_ratio'            =>
                    [
                        'default' => null,
                        'type'    => 'dimensional'
                    ],
                'attributes'              =>
                    [
                        'default' => null,
                        'type'    => 'control'
                    ],
                'auto_sharpen'            =>
                    [
                        'default' => $this->settings['img_cp_enable_auto_sharpen'],
                        'type'    => 'transformational'
                    ],
                'bg_color'                =>
                    [
                        'default' => $this->settings['img_cp_default_bg_color'],
                        'type'    => 'transformational'
                    ],
                'border'                  =>
                    [
                        'default' => null,
                        'type'    => 'transformational'
                    ],
                'bulk_tag'                =>
                    [
                        'default' => 'n',
                        'type'    => 'control'
                    ],
                'cache_dir'               =>
                    [
                        'default' => $this->settings['img_cp_default_cache_directory'],
                        'type'    => 'control'
                    ],
                'cache_mode'              =>
                    [
                        'default' => 'f',
                        'type'    => 'control'
                    ],
                'cache'                   =>
                    [
                        'default' => $this->settings['img_cp_default_cache_duration'],
                        'type'    => 'control'
                    ],
                'consolidate_class_style' =>
                    [
                        'default' => $this->settings['img_cp_class_consolidation_default'],
                        'type'    => 'control'
                    ],
                'create_tag'              =>
                    [
                        'default' => '',
                        'type'    => 'control'
                    ],
                'crop'                    =>
                    [
                        'default' => 'n|center,center|0,0|y|3', // crop:yes_or_no|position|offset|smart_scale|face_sensitivity
                        'type'    => 'transformational'
                    ],
                'default_img_height'      =>
                    [
                        'default' => $this->settings['img_cp_default_img_height'],
                        'type'    => 'dimensional'
                    ],
                'default_img_width'       =>
                    [
                        'default' => $this->settings['img_cp_default_img_width'],
                        'type'    => 'dimensional'
                    ],
                'disable_browser_checks'  =>
                    [
                        'default' => null,
                        'type'    => 'control'
                    ],
                'encode_urls'             =>
                    [
                        'default' => 'yes',
                        'type'    => 'control'
                    ],
                'exclude_class'           =>
                    [
                        'default' => 'no',
                        'type'    => 'control'
                    ],
                'exclude_style'           =>
                    [
                        'default' => 'no',
                        'type'    => 'control'
                    ],
                'exclude_regex'           =>
                    [
                        'default' => null,
                        'type'    => 'control'
                    ],
                'face_crop_margin'        =>
                    [
                        'default' => 0,
                        'type'    => 'transformational'
                    ],
                'face_detect_sensitivity' =>
                    [
                        'default' => 3,
                        'type'    => 'transformational'
                    ],
                'fallback_src'            =>
                    [
                        'default' => null,
                        'type'    => 'transformational'
                    ],
                'filename_prefix'         =>
                    [
                        'default' => null,
                        'type'    => 'control'
                    ],
                'filename_suffix'         =>
                    [
                        'default' => null,
                        'type'    => 'control'
                    ],
                'filename'                =>
                    [
                        'default' => null,
                        'type'    => 'control'
                    ],
                'filter'                  =>
                    [
                        'default' => null,
                        'type'    => 'transformational'
                    ],
                'fit'                     =>
                    [
                        'default' => 'contain',
                        'type'    => 'transformational'
                    ],
                'flip'                    =>
                    [
                        'default' => null,
                        'type'    => 'transformational'
                    ],
                'hash_filename'           =>
                    [
                        'default' => 'no',
                        'type'    => 'control'
                    ],
                'image_path_prefix'       =>
                    [
                        'default' => $this->settings['img_cp_path_prefix'],
                        'type'    => 'control'
                    ],
                'interlace'               =>
                    [
                        'default' => 'no',
                        'type'    => 'transformational'
                    ],
                'lazy'                    =>
                    [
                        'default' => '',
                        'type'    => 'control'
                    ],
                'output'                  =>
                    [
                        'default' => '',
                        'type'    => 'control'
                    ],
                'overwrite_cache'         =>
                    [
                        'default' => 'no',
                        'type'    => 'control'
                    ],
                'palette_size'            =>
                    [
                        'default' => 8,
                        'type'    => 'control'
                    ],
                'png_quality'             =>
                    [
                        'default' => $this->settings['img_cp_png_default_quality'],
                        'type'    => 'transformational'
                    ],
                'preload'                 =>
                    [
                        'default' => 'no',
                        'type'    => 'transformational'
                    ],
                'quality'                 =>
                    [
                        'default' => $this->settings['img_cp_jpg_default_quality'],
                        'type'    => 'transformational'
                    ],
                'reflection'              =>
                    [
                        'default' => null,
                        'type'    => 'transformational'
                    ],
                'rotate'                  =>
                    [
                        'default' => null,
                        'type'    => 'transformational'
                    ],
                'rounded_corners'         =>
                    [
                        'default' => '',
                        'type'    => 'transformational'
                    ],
                'save_as'                 =>
                    [
                        'default' => null,
                        'type'    => 'control'
                    ],
                'save_type'               =>
                    [
                        'default' => $this->settings['img_cp_default_image_format'],
                        'type'    => 'control'
                    ],
                'sizes'                   =>
                    [
                        'default' => null,
                        'type'    => 'control'
                    ],
                'src'                     =>
                    [
                        'default' => 'not_set',
                        'type'    => 'control'
                    ],
                'srcset'                  =>
                    [
                        'default' => null,
                        'type'    => 'control'
                    ],
                // 'svg_height' =>
                //     [
                //         'default' => $this->settings['img_cp_default_img_height'],
                //         'type' => 'dimensional'
                //     ],
                'svg_passthrough'         =>
                    [
                        'default' => $this->settings['img_cp_enable_svg_passthrough'],
                        'type'    => 'control'
                    ],
                // 'svg_width' =>
                //     [
                //         'default' => $this->settings['img_cp_default_img_width'],
                //         'type' => 'dimensional'
                //     ],
                'text'                    =>
                    [
                        'default' => null,
                        'type'    => 'transformational'
                    ],
                'url_only'                =>
                    [
                        'default' => '',
                        'type'    => 'control'
                    ],
                'use_image_path_prefix'   =>
                    [
                        'default' => null,
                        'type'    => 'control'
                    ],
                'watermark'               =>
                    [
                        'default' => null,
                        'type'    => 'transformational'
                    ],
                'width'                   =>
                    [
                        'default' => null,
                        'type'    => 'dimensional'
                    ],
                'height'                  =>
                    [
                        'default' => null,
                        'type'    => 'dimensional'
                    ],
                'max'                     =>
                    [
                        'default' => '',
                        'type'    => 'dimensional'
                    ],
                'max_height'              =>
                    [
                        'default' => '',
                        'type'    => 'dimensional'
                    ],
                'max_width'               =>
                    [
                        'default' => '',
                        'type'    => 'dimensional'
                    ],
                'min'                     =>
                    [
                        'default' => '',
                        'type'    => 'dimensional'
                    ],
                'min_height'              =>
                    [
                        'default' => '',
                        'type'    => 'dimensional'
                    ],
                'min_width'               =>
                    [
                        'default' => '',
                        'type'    => 'dimensional'
                    ]
            ];

            if (is_null(static::$valid_params)) {
                static::$valid_params = [];
                foreach (static::$inbound_params as $parameter => $value) {
                    static::$valid_params[$parameter] = $value['default'];
                }
            }

            if (is_null(static::$transformational_params)) {
                static::$transformational_params = [];
                foreach (static::$inbound_params as $parameter => $value) {
                    if ($value['type'] !== 'control') {
                        static::$transformational_params[$parameter] = $value['default'];
                    }
                }
            }

            static::$control_params = [];
            static::$dimension_params = [];
            foreach (static::$inbound_params as $parameter => $value) {
                if ($value['type'] === 'dimensional') {
                    static::$dimension_params[$parameter] = $value['default'];
                }
                if ($value['type'] === 'control') {
                    static::$control_params[$parameter] = $value['default'];
                }
            }
        }

        // Set up the default adapter name only
        if (is_null(static::$adapter_name)) {
            static::$adapter_name = $this->settings['img_cp_flysystem_adapter'];
        }

        // Now get the current parameters
        static::$current_params = empty(static::$current_params)? $this->get_parameters() : static::$current_params;
        
        // Initialize performance monitoring if debug mode
        if (isset($_GET['debug']) && $_GET['debug'] === 'yes') {
            register_shutdown_function([self::class, 'safe_debug_output']);
        }
    }

    /**
     * Returns a semi-transparent image to overlay on images when in demo mode
     * The image is saved as a base64 encoded png file, without the mime type and base 64 header
     * added in to make it into a Data URI format image (i.e. 'data:image/png;base64,$imagedata')
     *
     * @return string
     */
    public function demo_image()
    {

        return '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';
    }

    /**
     * Utility function: Checks to see what image formats browser will accept
     * Browser accept: responses only list novel formats (webp, avif etc.)
     * Uses two methods. 
     * First it tries the HTTP Accept header from $_SERVER superglobal 
     * This is google approved method 
     * (https://developers.google.com/speed/webp/faq#server-side_content_negotiation_via_accept_headers)
     * Then, since Apple doesn't always put this info into the HTTP_ACCEPT header for Safari also need to
     * check via the HTTP User Agent string.
     * Due to Chrome sometimes reporting itself as Safari, put Apple last so if anything else can match
     * it will etc.
     * 
     * Based on https://caniuse.com/?search=webp we need to filter for Versions less than the following:
     * Android Browser - 4.2
     * Chrome - 32
     * Edge - 18
     * Firefox - 65
     * Opera - 19
     * IE - any version
     * 
     * Reference UserAgent strings from https://myip.ms
     *
     * @return	array	array with parameters
     */
    private function _get_browser_image_capabilities(): array
    {
        // Check if the capabilities have already been determined
        if (static::$browser_image_format_support) {
            return static::$browser_image_format_support;
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
        static::$browser_image_format_support = $valid_browser_formats_base;

        // Check the HTTP Accept header
        if (!empty($_SERVER['HTTP_ACCEPT'])) {
            preg_match_all('/image\/(.*?),/', $_SERVER['HTTP_ACCEPT'], $matches);
            if (!empty($matches[1])) {
                static::$browser_image_format_support = array_merge(static::$browser_image_format_support, $matches[1]);
            }
        }

        // If no additional formats were found, check the User Agent string
        if (count(static::$browser_image_format_support) === count($valid_browser_formats_base) && !empty($_SERVER['HTTP_USER_AGENT'])) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_browser_user_agent_string'), [$_SERVER['HTTP_USER_AGENT']], true);

            $user_agent = $_SERVER['HTTP_USER_AGENT'];

            // Android
            if (preg_match('/Android\s([\d.]+);/', $user_agent, $matches)) {
                ee('jcogs_img:Utilities')->debug_message(lang('android'), $matches, true);
                if (version_compare($matches[1], '4.1', '>')) {
                    static::$browser_image_format_support[] = 'webp';
                }
                if (version_compare($matches[1], '4.4.4', '>')) {
                    static::$browser_image_format_support[] = 'avif';
                }
            }
            // Chrome (also covers Edge, Opera)
            elseif (preg_match('/Chrome\/([\d.]+)\s/', $user_agent, $matches)) {
                ee('jcogs_img:Utilities')->debug_message(lang('chrome'), $matches, true);
                if (version_compare($matches[1], '31', '>')) {
                    static::$browser_image_format_support[] = 'webp';
                }
                if (version_compare($matches[1], '84', '>')) {
                    static::$browser_image_format_support[] = 'avif';
                }
            }
            // Firefox
            elseif (preg_match('/Firefox\/([\d.]+)$/', $user_agent, $matches)) {
                ee('jcogs_img:Utilities')->debug_message(lang('firefox'), $matches, true);
                if (version_compare($matches[1], '64', '>')) {
                    static::$browser_image_format_support[] = 'webp';
                }
                if (version_compare($matches[1], '92', '>')) {
                    static::$browser_image_format_support[] = 'avif';
                }
            }
            // Safari
            elseif (preg_match('/Version\/([\d.]+)\sSafari/', $user_agent, $matches)) {
                ee('jcogs_img:Utilities')->debug_message(lang('safari'), $matches, true);
                if (version_compare($matches[1], '16', '>')) {
                    static::$browser_image_format_support[] = 'webp';
                    static::$browser_image_format_support[] = 'avif';
                }
            }
        }

        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_browser_image_support'), static::$browser_image_format_support, true);
        return static::$browser_image_format_support;
    }

    /**
     * Utility function: Checks to see what image formats server can create
     * For now this assumes use of GD2 library... 
     *
     * @return	array	array with parameters
     */
    private function _get_server_capabilities()
    {

        // Have we done this already on this instance?
        if (static::$valid_server_image_formats) {
            return static::$valid_server_image_formats;
        }

        // Get a list of formats supported by the GD library
        $server_gd_info = gd_info();

        // Work out what capabilities we have... 
        static::$valid_server_image_formats = [];
        foreach ($server_gd_info as $key => $value) {
            if (! in_array(strtolower(substr($key, 0, 2)), ['gd', 'fr', 'ji'])) {
                $this_capability = explode(' ', strtolower($key));
                if ($value === true && strtolower($this_capability[1]) != 'read') {
                    static::$valid_server_image_formats[] = $this_capability[0];
                    if ($this_capability[0] == 'jpeg') {
                        static::$valid_server_image_formats[] = 'jpg';
                    }
                }
            }
        }
        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_server_capabilities'), static::$valid_server_image_formats, true);

        return static::$valid_server_image_formats;
    }

    /**
     * Get or create filesystem adapter for specified adapter name
     *
     * @param string|null $adapter_name
     * @return Filesystem|bool
     */
    private function _get_filesystem_adapter(?string $adapter_name = null): Filesystem|bool
    {
        // Use current adapter if none specified
        $adapter_name = $adapter_name ?: static::$adapter_name;
        
        // Return existing adapter if already initialized
        if (isset(static::$filesystems[$adapter_name])) {
            return static::$filesystems[$adapter_name];
        }
        
        // Create new adapter
        $filesystem = $this->create_filesystem_adapter($adapter_name, false);
        
        if ($filesystem) {
            // Cache the filesystem
            static::$filesystems[$adapter_name] = $filesystem;
            
            // Set adapter URL
            if ($adapter_name === 'local') {
                static::$adapter_urls[$adapter_name] = ee()->config->item('site_url');
            } else {
                $adapter_url_key = 'img_cp_flysystem_adapter_' . $adapter_name . '_url';
                static::$adapter_urls[$adapter_name] = $this->settings[$adapter_url_key] . '/';
            }
            
            // Set cache directory for cloud adapters
            if ($adapter_name !== 'local') {
                static::$valid_params['cache_dir'] = $this->settings['img_cp_flysystem_adapter_' . $adapter_name . '_server_path'];
            }
            
            return $filesystem;
        }
        
        return false;
    }

    /**
     * Enable performance debugging - call this early in your processing
     * This will automatically dump performance logs at the end of script execution
     */
    public static function enable_performance_debugging(): void
    {
        // Register shutdown function to dump performance logs
        register_shutdown_function([self::class, 'dump_all_performance_logs']);
        
        // Log that debugging has been enabled
        error_log("[JCOGS_IMG_DEBUG] Performance debugging enabled - logs will be dumped at script end");
    }

    /**
     * Enhanced debug output that checks class usage and trait loading
     */
    public static function safe_debug_output(): void
    {
        if (!isset($_GET['debug']) || $_GET['debug'] !== 'yes') {
            echo "<!-- JCOGS Image Debug: Add ?debug=yes to URL to enable -->\n";
            return;
        }
        
        echo "<!-- ================================================= -->\n";
        echo "<!-- JCOGS IMAGE PERFORMANCE DEBUG REPORT            -->\n";
        echo "<!-- Debug executed at: " . date('Y-m-d H:i:s') . " -->\n";
        echo "<!-- ================================================= -->\n";
        
        // Check if this class uses the traits
        $reflection = new \ReflectionClass(self::class);
        $traits = $reflection->getTraitNames();
        echo "<!-- ImageUtilities class traits: " . implode(', ', $traits) . " -->\n";
        
        // Check only already loaded classes to avoid autoloading
        $declared_classes = get_declared_classes();
        $filesystem_class = 'JCOGSDesign\\Jcogs_img\\Service\\ImageUtilities\\Traits\\FileSystemTrait';
        $cache_class = 'JCOGSDesign\\Jcogs_img\\Service\\ImageUtilities\\Traits\\CacheManagementTrait';
        
        $filesystem_loaded = in_array($filesystem_class, $declared_classes);
        $cache_loaded = in_array($cache_class, $declared_classes);
        
        echo "<!-- FileSystemTrait loaded: " . ($filesystem_loaded ? 'YES' : 'NO') . " -->\n";
        echo "<!-- CacheManagementTrait loaded: " . ($cache_loaded ? 'YES' : 'NO') . " -->\n";
        echo "<!-- Total declared classes: " . count($declared_classes) . " -->\n";
        
        // Look for any jcogs_img related classes
        $jcogs_classes = array_filter($declared_classes, fn($class) => str_contains($class, 'jcogs_img') || str_contains($class, 'Jcogs_img'));
        echo "<!-- JCOGS IMG related classes loaded: " . count($jcogs_classes) . " -->\n";
        foreach ($jcogs_classes as $class) {
            echo "<!-- - " . $class . " -->\n";
        }
        
        // Check if we have any static properties that might contain performance data
        if ($reflection->hasProperty('performance_log')) {
            echo "<!-- ImageUtilities has performance_log property -->\n";
            try {
                $property = $reflection->getProperty('performance_log');
                $property->setAccessible(true);
                $performance_data = $property->getValue();
                
                if (!empty($performance_data)) {
                    echo "<!-- ImageUtilities Performance: " . count($performance_data) . " entries -->\n";
                    
                    // Analyze the performance data
                    $total_time = 0;
                    $filesystem_operations = [];
                    $cache_operations = [];
                    $other_operations = [];
                    
                    foreach ($performance_data as $profile_id => $data) {
                        if (isset($data['duration']) && isset($data['method'])) {
                            $total_time += $data['duration'];
                            
                            if (str_contains($data['method'], 'FileSystem::')) {
                                $filesystem_operations[] = [
                                    'method' => $data['method'],
                                    'duration' => $data['duration'],
                                    'memory_used' => $data['memory_used'] ?? 0
                                ];
                            } elseif (str_contains($data['method'], 'Cache::')) {
                                $cache_operations[] = [
                                    'method' => $data['method'],
                                    'duration' => $data['duration'],
                                    'memory_used' => $data['memory_used'] ?? 0
                                ];
                            } else {
                                $other_operations[] = [
                                    'method' => $data['method'],
                                    'duration' => $data['duration'],
                                    'memory_used' => $data['memory_used'] ?? 0
                                ];
                            }
                        }
                    }
                    
                    echo "<!-- Total execution time: " . number_format($total_time * 1000, 2) . "ms -->\n";
                    echo "<!-- FileSystem operations: " . count($filesystem_operations) . " -->\n";
                    echo "<!-- Cache operations: " . count($cache_operations) . " -->\n";
                    echo "<!-- Other operations: " . count($other_operations) . " -->\n";
                    
                    // Show top 10 slowest operations overall
                    $all_operations = array_merge($filesystem_operations, $cache_operations, $other_operations);
                    usort($all_operations, fn($a, $b) => $b['duration'] <=> $a['duration']);
                    
                    echo "<!-- TOP 10 SLOWEST OPERATIONS: -->\n";
                    foreach (array_slice($all_operations, 0, 10) as $i => $op) {
                        echo sprintf(
                            "<!-- %d. %s: %.2fms (Memory: %s) -->\n",
                            $i + 1,
                            $op['method'],
                            $op['duration'] * 1000,
                            number_format($op['memory_used'] / 1024, 2) . 'KB'
                        );
                    }
                    
                    // Show filesystem performance summary
                    if (!empty($filesystem_operations)) {
                        $fs_total_time = array_sum(array_column($filesystem_operations, 'duration'));
                        echo "<!-- FILESYSTEM SUMMARY: -->\n";
                        echo "<!-- Total FileSystem time: " . number_format($fs_total_time * 1000, 2) . "ms -->\n";
                        
                        usort($filesystem_operations, fn($a, $b) => $b['duration'] <=> $a['duration']);
                        echo "<!-- Top 5 FileSystem operations: -->\n";
                        foreach (array_slice($filesystem_operations, 0, 5) as $op) {
                            echo sprintf(
                                "<!-- %s: %.2fms (Memory: %s) -->\n",
                                $op['method'],
                                $op['duration'] * 1000,
                                number_format($op['memory_used'] / 1024, 2) . 'KB'
                            );
                        }
                    }
                    
                    // Show cache performance summary
                    if (!empty($cache_operations)) {
                        $cache_total_time = array_sum(array_column($cache_operations, 'duration'));
                        echo "<!-- CACHE SUMMARY: -->\n";
                        echo "<!-- Total Cache time: " . number_format($cache_total_time * 1000, 2) . "ms -->\n";
                        
                        usort($cache_operations, fn($a, $b) => $b['duration'] <=> $a['duration']);
                        echo "<!-- Top 5 Cache operations: -->\n";
                        foreach (array_slice($cache_operations, 0, 5) as $op) {
                            echo sprintf(
                                "<!-- %s: %.2fms (Memory: %s) -->\n",
                                $op['method'],
                                $op['duration'] * 1000,
                                number_format($op['memory_used'] / 1024, 2) . 'KB'
                            );
                        }
                    }
                } else {
                    echo "<!-- No ImageUtilities performance data -->\n";
                }
            } catch (\Exception $e) {
                echo "<!-- Error accessing ImageUtilities performance_log: " . $e->getMessage() . " -->\n";
            }
        } else {
            echo "<!-- ImageUtilities does NOT have performance_log property -->\n";
        }
        
        if ($filesystem_loaded) {
            try {
                $reflection = new \ReflectionClass($filesystem_class);
                if ($reflection->hasProperty('performance_log')) {
                    $property = $reflection->getProperty('performance_log');
                    $property->setAccessible(true);
                    $performance_data = $property->getValue();
                    
                    if (!empty($performance_data)) {
                        echo "<!-- FileSystem Performance: " . count($performance_data) . " entries -->\n";
                        
                        $total_time = 0;
                        foreach ($performance_data as $data) {
                            if (isset($data['duration'])) {
                                $total_time += $data['duration'];
                            }
                        }
                        echo "<!-- Total FileSystem time: " . number_format($total_time * 1000, 2) . "ms -->\n";
                    } else {
                        echo "<!-- No FileSystem performance data -->\n";
                    }
                }
            } catch (\Exception $e) {
                echo "<!-- FileSystem error: " . $e->getMessage() . " -->\n";
            }
        }
        
        if ($cache_loaded) {
            try {
                $reflection = new \ReflectionClass($cache_class);
                if ($reflection->hasProperty('performance_log')) {
                    $property = $reflection->getProperty('performance_log');
                    $property->setAccessible(true);
                    $performance_data = $property->getValue();
                    
                    if (!empty($performance_data)) {
                        echo "<!-- Cache Performance: " . count($performance_data) . " entries -->\n";
                        
                        $total_time = 0;
                        foreach ($performance_data as $data) {
                            if (isset($data['duration'])) {
                                $total_time += $data['duration'];
                            }
                        }
                        echo "<!-- Total Cache time: " . number_format($total_time * 1000, 2) . "ms -->\n";
                    } else {
                        echo "<!-- No Cache performance data -->\n";
                    }
                }
            } catch (\Exception $e) {
                echo "<!-- Cache error: " . $e->getMessage() . " -->\n";
            }
        }
        
        echo "<!-- ================================================= -->\n";
        echo "<!-- END DEBUG REPORT -->\n";
        echo "<!-- ================================================= -->\n";
    }
}