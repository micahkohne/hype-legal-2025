<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * Image Module
 * ============
 * Full function image processing add-on for EE.
 * Designed to work with similar parameter and variable definitions
 * to CE-Image, to support drop-in replacement.
 *                      
 * =====================================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.4.16.1
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.0.0
 */

require_once PATH_THIRD . "jcogs_img/vendor/autoload.php";
require_once PATH_THIRD . "jcogs_img/config.php";

use JCOGSDesign\Jcogs_img\Library\JcogsImage;
use Imagine\Gd\Imagine;
use Imagine\Image\PointSigned;
use ColorThief\ColorThief;

class Jcogs_img
{
    private $has_started = false;
    private $called_by = null;
    private $EE;
    private $setup_is_valid;
    public $settings;
    private $time_start;

    function __construct()
    {
        $this->EE = get_instance();
        $this->settings = ee('jcogs_img:Settings')::$settings;
        $this->setup_is_valid = true;
        ee('jcogs_img:ImageUtilities')::$instance_count = ee('jcogs_img:ImageUtilities')::$instance_count ?: 1;
        ee()->load->library('benchmark');
    
        $this->_check_image_enabled();
        $this->_check_license();
        $this->_check_php_version();
        $this->_check_ee_version();
        $this->_check_gd_library();
        $this->_check_base_path();
    
        // Add a hook for CE Img quasi-compatibility
        if ($this->setup_is_valid && !$this->has_started) {
            $this->has_started = true;
            if (ee()->extensions->active_hook('jcogs_img_start')) {
                ee()->extensions->call('jcogs_img_start');
            }
        }
    }

    /**
     * Placeholder function to capture ACT based image tag requests
     * @return mixed
     */
    public function act_originated_image() {

        // Set this static variable so we know we are working from an ACT request
        ee('jcogs_img:ImageUtilities')::$act_based_tag = true;
        $this->called_by = $this->called_by ?: 'ACT';

        // Leave a note in the log
        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_called_from_ACT'));

        // See if we can fish out the URL ... if not found run tag instead.
        if($act_params = ee('jcogs_img:ImageUtilities')->get_act_param_object()) {
            $this->_send_act_link_image(property_exists($act_params, 'act_path' ) ? $act_params->act_path : null);
        }

        // If we got here then we couldn't get the image directly, so see if we can reconstruct!
        return $this->image();
    }

    /**
     * Shell function that finds <img> tags in a block of tag data and submits
     * image() calls for each
     *
     * @return mixed
     */
    public function bulk()
    {
        // We can only start if the session is a valid one
        if (!$this->setup_is_valid) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_setup_is_invalid'));
            ee('jcogs_img:ImageUtilities')->clear_params();
            return false;
        }

        // Put a marker into benchmarking system
        $bulk_start_instance = ee('jcogs_img:ImageUtilities')::$instance_count++;
        $this->called_by = $this->called_by ?: 'Bulk_Tag';
        $bulk_tag = $this->called_by;
        ee()->benchmark->mark(sprintf('JCOGS_Image_(%1$s)_#%2$s_start', $bulk_tag, $bulk_start_instance));

        $vars = []; // This is container for the processed images we are going to generate

        $bulk_params = new stdClass;
        // Get whatever this->content / parameters have been provided in the tag
        $bulk_params->exclude_regex = ee('jcogs_img:ImageUtilities')::$current_params->exclude_regex;

        // Grab the tagdata and fish out the <img> tags (if any)
        preg_match_all('/(:?<img\s(.*?)>)/s', ee()->TMPL->tagdata, $img_tags, PREG_SET_ORDER);

        // Exclude any images that match the regex provided in the exclude_regex parameter
        if ($bulk_params->exclude_regex) {
            $img_tags = $this->_exclude_images($img_tags, $bulk_params->exclude_regex);
        }

        // Did we get anything to process ... ?
        if (!$img_tags) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_bulk_processing_no_images_found'));
            ee('jcogs_img:ImageUtilities')->clear_params();
            return ee()->TMPL->tagdata;
        }

        // Build a text list of images we are going to work on for debug message:
        $image_list = array_map(fn($img_tag) => $img_tag[2], $img_tags);
        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_bulk_processing_start'), $image_list);

        // Process each <img> tag found
        foreach ($img_tags as $i => $img_tag) {
            if (is_null($img_tag) || $img_tag == '') {
                // no content in img tag found so bale
                ee('jcogs_img:ImageUtilities')->clear_params();
                return;
            }

            // To avoid filter confusion, get the bulk tag params again
            $bulk_params = ee('jcogs_img:ImageUtilities')::$current_params;
            // Force output of an <img> tag 
            $bulk_params->bulk_tag = 'y';

            // Create a container to submit image_process call with
            $image_tag = new JcogsImage;
            $image_tag->params = $bulk_params;

            $temp_var = 'new_img_tag_' . $i;
            // replace found tag with a marker
            ee()->TMPL->tagdata = str_replace($img_tag[1], '{' . $temp_var . '}', ee()->TMPL->tagdata);

            // put current content of tag found into vars as default value
            $vars[0][$temp_var] = $img_tag[1];

            // Extract and process image attributes
            $this->_process_image_attributes($img_tag, $image_tag);

            // Process the image
            $vars[0][$temp_var] = $this->image($image_tag);
        }

        // Process the output to give updated tagdata
        ee()->benchmark->mark(sprintf('JCOGS_Image_(%1$s)_#%2$s_end', $bulk_tag, $bulk_start_instance));
        return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $vars);
    }

    /**
     * Shell function to provide 'single' tag option
     *
     * @return object
     */
    public function single()
    {
        $this->called_by = $this->called_by ?: 'Single_Tag';
        $return = $this->image();
        return $return;
    }

    /**
     * Shell function to provide 'size' tag option
     *
     * @return object
     */
    public function size()
    {
        $this->called_by = $this->called_by ?: 'Size_Tag';
        return $this->image();
    }

    /**
     * Shell function to provide 'pair' tag option
     *
     * @return object
     */
    public function pair()
    {
        $this->called_by = $this->called_by ?: 'Pair_Tag';
        return $this->image();
    }

    /**
     * Function to provide 'palette' tag option
     * Still need to process image, so this uses standard image call
     * and then adds call to palette method before returning palette
     *
     * @return mixed
     */
    public function palette()
    {
        // Set Called by
        $this->called_by = $this->called_by ?: 'Palette_Tag';
        $this->_mark_start();


        if (!$content = $this->image(null, true)) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_palette_no_image'));
            return;
        }    

        // ee('jcogs_img:ImageUtilities')->get_a_local_copy_of_image(path: $content->local_path, cache_check: true);

        // If using cache copy load local copy
        if (!$content->processed_image) {
            try {
                $content->processed_image = (new Imagine())->load(ee('jcogs_img:ImageUtilities')->read($content->local_path));
            } catch (\Imagine\Exception\RuntimeException $e) {
                // Creation of image failed.
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'), $e->getMessage());
                return;
            }
        }

        // Did we get a processed image to work with?
        if(empty($content->processed_image)) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_palette_unable_to_get_processed_image'), $content->local_path);
            return false;
        }

        // Get the palette for the image given
        $palette = ColorThief::getPalette($content->processed_image->getGdResource(), $content->params->palette_size, 10, null, 'rgb');

        $dc = ColorThief::getColor($content->processed_image->getGdResource(), 10, null, 'rgb');

        $i = 0;
        foreach ($palette as $color) {
            $output[0]['colors'][$i] = ['color' => $color, 'rank' => $i + 1];
            $i++;
        }

        $output[0]['dominant_color'] = $dc;

        // Parse output back to template
        if (empty($palette)) {
            unset($content);
            return ee()->TMPL->no_results();
        }

        unset($raw_palette);
        unset($palette);
        // Unset $content
        unset($content);
        ee('jcogs_img:ImageUtilities')->clear_params();
        $this->_mark_end();
        return ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $output);
    }

    /**
     * Primary method for producing image results back to EE template
     * The production process is split into four parts:
     * 1) Setup - work out name for processed and check if it is in cache,
     * 2) Process - If not in cache, validate and process image
     * 3) Transform - Apply whole-image transforms (filters, border, adding text/watermark)
     * 4) Save - Save image
     * 5) Post-process - Assemble parsed variables, generate template output requested
     *
     * @return bool|object $content
     */
    public function image($content = null, $return_content = false)
    {
        // We can only start if the session is a valid one
        if (!$this->_is_session_valid()) {
            return false;
        }
    
        // 1: Do some housekeeping and setup local copy of image to work with
        // ==================================================================

        // Initialise things (if not already done)
        $this->_initialize_image_processing($content);

        // Initialise the image
        if (!$this->_initialize_image($content)) {
            return false;
        }
    
        // 2: Process the image with parameters given
        // ==========================================

        if (!$this->_process_image($content)) {
            return false;
        }
    
        // If we are doing Palette processing, we can bail here
        if ($this->_is_palette_processing_required($return_content)) {
            return $content;
        }
    
        // 3: If we are in demo mode overlay demo watermark
        // ================================================
        // We do this here rather than in Image class so that only one demo label applied even
        // if image is a composite
        if (!$content->flags->using_cache_copy) {
            $this->_apply_demo_watermark($content);
        }

        // 4: Save - write out image to disk
        // =================================
        if (!$content->flags->using_cache_copy && !$this->_save_image($content)) {
            return false;
        }
    
        // 5: Post-process - generate parsed variables
        // ===========================================
        if (!$this->_post_process_image($content)) {
            return false;
        }
    
        // 6) Generate output... 
        // ===========================================
        if (!$this->_generate_image_output($content)) {
            return false;
        }
    
        // 7) Finish - clean up and return output
        // ======================================
        return $this->_finalize_image_processing($content, $return_content);
    }

    /**
     * Apply demo watermark if in demo mode
     * 
     * @param object $content
     * @return void
     */
    private function _apply_demo_watermark($content): void
    {
        if (!$content->flags->using_cache_copy && !$content->flags->svg && !($content->flags->animated_gif && $content->params->save_as != 'gif')) {
            if (!$content->flags->using_cache_copy && $this->settings['jcogs_license_mode'] == 'demo' && $content->new_width > 100 && $content->new_height > 100) {
                $demo_image = (new Imagine())->load(base64_decode(ee('jcogs_img:ImageUtilities')->demo_image()));
                $demo_image_size = $demo_image->getSize();
                $content->processed_image->paste($demo_image, new PointSigned(round(($content->new_width - $demo_image_size->getWidth()) / 2, 0), round(($content->new_height - $demo_image_size->getHeight()) / 2, 0)));
            }
        }
    }
    
    /**
     * Check if the EE version is valid
     * 
     * @return void
     */
    private function _check_ee_version()
    {
        if (!ee('jcogs_img:Utilities')->valid_ee_version()) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_invalid_ee_version'));
            $this->setup_is_valid = false;
        }
    }
    
    /**
     * Check if the GD library is loaded
     * 
     * @return void
     */
    private function _check_gd_library()
    {
        if (!extension_loaded('gd')) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_gd_library_not_found'));
            $this->setup_is_valid = false;
        }
    }
    
    /**
     * Check if the base path is set
     * 
     * @return void
     */
    private function _check_base_path()
    {
        if (!ee('Filesystem')->exists(ee()->config->item('base_path'))) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_no_base_path'));
            $this->setup_is_valid = false;
        }
    }
    
    /**
     * Check if the image is enabled
     * 
     * @return void
     */
    private function _check_image_enabled()
    {
        if (substr(strtolower($this->settings['enable_img']), 0, 1) == 'n') {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_not_enabled'));
            $this->setup_is_valid = false;
        }
    }
    
    /**
     * Check if the license is valid
     * 
     * @return void
     */
    private function _check_license()
    {
        if (!($this->settings['jcogs_license_mode'] == 'valid' || $this->settings['jcogs_license_mode'] == 'magic' || $this->settings['jcogs_license_mode'] == 'staging')) {
            // Check to see if we can run in demo mode
            // From 1.2.8 onwards we can always run in demo mode if no license set
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_demo_mode'));
        }
    }
    
    /**
     * Check if the PHP version is valid
     * 
     * @return void
     */
    private function _check_php_version()
    {
        if (!ee('jcogs_img:Utilities')->valid_php_version()) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_invalid_php_version'));
            $this->setup_is_valid = false;
        }
    }

    /**
     * Exclude images that match the regex provided in the exclude_regex parameter
     * 
     * @param array $img_tags
     * @param string $exclude_regex
     * @return array
     */
    private function _exclude_images(array $img_tags, string $exclude_regex): array
    {
        $regexs = explode('@', $exclude_regex);
        foreach ($regexs as $regex) {
            foreach ($img_tags as $i => $img_tag) {
                if (preg_match('/' . $regex . '/', $img_tag[2])) {
                    ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_bulk_excluding_image'), $img_tag[2]);
                    unset($img_tags[$i]);
                }
            }
        }
        return array_values($img_tags);
    }

    /**
     * Finalize image processing
     * 
     * @param object $content
     * @param bool $return_content
     * @return mixed
     */
    private function _finalize_image_processing($content, $return_content)
    {
        $elapsed_time = microtime(true) - $this->time_start;
        $elapsed_time_report = $this->_get_elapsed_time_report($elapsed_time);

        ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_processing_end'), sprintf($elapsed_time_report, $elapsed_time)));

        $this->_shut_down_session();

        if (isset(self::$cache_log_index)) {
            ee()->session->cache[JCOGS_IMG_CLASS]['cache_log_index'] = ee('jcogs_img:ImageUtilities')::$cache_log_index;
        }

        $this->_mark_end();

        if ($return_content) {
            return $content;
        } else {
            return $this->_handle_image_return($content);
        }
    }

    /**
     * Generate image output
     * 
     * @param object $content
     * @return bool
     */
    private function _generate_image_output($content): bool
    {
        if (!$content->generate_output()) {
            $this->_mark_end();
            unset($content);
            ee('jcogs_img:ImageUtilities')->clear_params();
            return false;
        }
        return true;
    }

    /**
     * Get elapsed time report
     * 
     * @param float $elapsed_time
     * @return string
     */
    private function _get_elapsed_time_report($elapsed_time): string
    {
        if ($elapsed_time > 2) {
            return '<span style="color:var(--ee-error-dark);font-weight:bold">Processing time: %0.4f seconds</span>';
        } elseif ($elapsed_time > 1) {
            return '<span style="color:var(--ee-warning-dark);font-weight:bold">Processing time: %0.4f seconds</span>';
        } else {
            return '<span style="color:var(--ee-button-success-hover-bg);font-weight:bold">Processing time: %0.4f seconds</span>';
        }
    }

    /**
     * Handle image return based on context
     * 
     * @param object $content
     * @return mixed
     */
    private function _handle_image_return($content)
    {
        $what_to_return = $content->return;

        if (ee('jcogs_img:ImageUtilities')::$act_based_tag) {
            if (!$this->_send_act_link_image($content->params->act_path)) {
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_act_link_failed'));
                return false;
            }
        } elseif (method_exists(ee()->TMPL, 'set_data') && ee()->has('coilpack') && ee()->TMPL->template_name != 'native') {
            unset($content);
            ee('jcogs_img:ImageUtilities')->clear_params();
            return ee()->TMPL->set_data(["src" => $what_to_return]);
        } else {
            unset($content);
            ee('jcogs_img:ImageUtilities')->clear_params();
            return $what_to_return;
        }

        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_processing_ended_in_error'));
        return false;
    }

    /**
     * Initialize the image
     * 
     * @param object $content
     * @return bool
     */
    private function _initialize_image($content): bool
    {
        if (!$content->initialise()) {
            $this->_mark_end();
            unset($content);
            ee('jcogs_img:ImageUtilities')->clear_params();
            return false;
        }
        return true;
    }

    /**
     * Initialize image processing
     * 
     * @param object|null $content
     * @return void
     */
    private function _initialize_image_processing(&$content): void
    {
        $this->called_by = is_null($this->called_by) ? 'Image_Tag' : $this->called_by;
        ee('jcogs_img:ImageUtilities')::$instance_count = str_contains($this->called_by, "Bulk_Tag") ? ee('jcogs_img:ImageUtilities')::$instance_count++ : ee('jcogs_img:ImageUtilities')::$instance_count;

        $this->time_start = microtime(true);
        if ($this->called_by != 'Palette_Tag') $this->_mark_start();

        $this->_setup_session();

        if (!is_object($content)) {
            $content = new JcogsImage;
        }
    }

    /**
     * Check if palette processing is required
     * 
     * @param bool $return_content
     * @return bool
     */
    private function _is_palette_processing_required($return_content): bool
    {
        if ($this->called_by == 'Palette_Tag' && $return_content) {
            $this->_shut_down_session();
            return true;
        }
        return false;
    }

    /**
     * Check if the session is valid
     * 
     * @return bool
     */
    private function _is_session_valid(): bool
    {
        if (!$this->setup_is_valid) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_setup_is_invalid'));
            ee('jcogs_img:ImageUtilities')->clear_params();
            return false;
        }
        return true;
    }

    /**
     * Starts a timer for benchmarking
     * @return void
     */
    private function _mark_start() {
        ee()->benchmark->mark(sprintf('JCOGS_Image_(%1$s)_#%2$s_start', $this->called_by, ee('jcogs_img:ImageUtilities')::$instance_count));
    }

    private function _mark_end() {
        ee()->benchmark->mark(sprintf('JCOGS_Image_(%1$s)_#%2$s_end', $this->called_by, ee('jcogs_img:ImageUtilities')::$instance_count));
        ee('jcogs_img:ImageUtilities')::$instance_count++;  
    }

    /**
     * Post-process the image
     * 
     * @param object $content
     * @return bool
     */
    private function _post_process_image($content): bool
    {
        if (!$content->post_process()) {
            $this->_mark_end();
            unset($content);
            ee('jcogs_img:ImageUtilities')->clear_params();
            return false;
        }
        return true;
    }

    /**
     * Process the image
     * 
     * @param object $content
     * @return bool
     */
    private function _process_image($content): bool
    {
        if (!$content->process_image()) {
            $this->_mark_end();
            unset($content);
            ee('jcogs_img:ImageUtilities')->clear_params();
            return false;
        }
        return true;
    }

    /**
     * Process image attributes (src, width, height, attributes)
     * 
     * @param array $img_tag
     * @param object $image_tag
     * @return void
     */
    private function _process_image_attributes(array &$img_tag, object &$image_tag): void
    {
        // Get src...
        if (!preg_match('/(?:src=(?:\"|\')(.*?)(?:\"|\'))/', $img_tag[2], $src)) {
            // no src found so bale
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_bulk_no_source'), $img_tag[2]);
            ee('jcogs_img:ImageUtilities')->clear_params();
            return;
        }
        // remove the src from $img_tag[2]
        $img_tag[2] = str_replace($src[0], '', $img_tag[2]);
        // check to see if this is a lazy-load src and swap in real image if so
        if (stripos(strtolower($src[1]), '_lqip_')) {
            $src[1] = str_replace('lqip_', '', $src[1]);
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_bulk_found_lazy_swap'), $src[1]);
        } elseif (stripos(strtolower($src[1]), '_dominant_color_')) {
            $src[1] = str_replace('dominant_color_', '', $src[1]);
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_bulk_found_lazy_swap'), $src[1]);
        }
        // add / update params with the value
        $image_tag->params->src = $src[1];

        // Get width... and if found, remove it from $img_tag[]
        if (trim($img_tag[2]) != '' && preg_match('/(?:width=(?:\"|\')(.*?)(?:\"|\'))/', $img_tag[2], $width)) {
            // remove the value from $img_tag[2]
            $img_tag[2] = str_replace($width[0], '', $img_tag[2]);
            // add / update params with the value only if no over-ruling bulk tag param set
            $image_tag->params->width = $image_tag->params->width ?: $width[1];
        }

        // Get height... and if found, remove it from $img_tag[]
        if (trim($img_tag[2]) != '' && preg_match('/(?:height=(?:\"|\')(.*?)(?:\"|\'))/', $img_tag[2], $height)) {
            // remove the value from $img_tag[2]
            $img_tag[2] = str_replace($height[0], '', $img_tag[2]);
            // add / update params with the value
            $image_tag->params->height = $image_tag->params->height ?: $height[1];
        }

        // If anything left in tag put that into the attributes parameter
        if (trim($img_tag[2]) != '') {
            $image_tag->params->attributes = property_exists($image_tag->params, 'attributes') ? trim($image_tag->params->attributes . ' ' . $img_tag[2]) : trim($img_tag[2]);
        }
    }

    /**
     * Sends an action link for the image.
     *
     * @param string|null $image_path The path to the image. If null, no image path is provided.
     * @return bool Returns true if the action link was sent successfully, false otherwise.
     */
    private function _send_act_link_image(?string $image_path = null): bool 
    {
        // If we don't have either link, bale
        if(empty($image_path)) {
            ee('jcogs_img:Utilities')->debug_message(lang(line: 'jcogs_img_no_act_path_supplied'));
            return false;
        }

        // See if we can get the file from the cache
        $image_raw = ee('jcogs_img:ImageUtilities')->get_file_from_local($image_path);

        // Did we get anything? 
        if(empty($image_raw)) {
            ee('jcogs_img:Utilities')->debug_message(lang(line: 'jcogs_img_action_link_image_not_found'));
            return false;
        }
        // Get the image size
        $image_size = strlen(string: $image_raw);
        // Set the appropriate Content-Type header
        switch (strtolower(string: pathinfo(path: $image_path, flags: PATHINFO_EXTENSION))) {
            case 'avif':
                header(header: "Content-Type: image/avif");
                break;
            case 'bmp':
                header(header: "Content-Type: image/bmp");
                break;
            case 'gif':
                header(header: "Content-Type: image/gif");
                break;
            case 'jpg':
            case 'jpeg':
                header(header: "Content-Type: image/jpeg");
                break;
            case 'png':
                header(header: "Content-Type: image/png");
                break;
            case 'svg':
                header(header: "Content-Type: image/svg+xml");
                break;
            case 'tiff':
                header(header: "Content-Type: image/tiff");
                break;
            case 'webp':
                header(header: "Content-Type: image/webp");
                break;
            default:
                header(header: $_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                echo "Unsupported file type.";
                exit;
        }

        ee('jcogs_img:ImageUtilities')->clear_params();

        // Send the content-length header
        header(header: "Content-Length: " . $image_size);
        echo $image_raw;
        exit();
    }

    /**
     * Sets up the session for the current user.
     *
     * This function initializes the session settings and configurations
     * required for the current user. It ensures that the session is properly
     * configured and ready for use.
     *
     * @return void
     */
    private function _setup_session() {

        // Request minimum amount of memory for php process ... 
        // 1 - what is it currently?
        $current_php_memory = ee('jcogs_img:Utilities')->normalize_memory_limit(ini_get('memory_limit'));

        // 2 - what is requested limit?
        $requested_php_memory = ee('jcogs_img:Utilities')->normalize_memory_limit($this->settings['img_cp_default_min_php_ram']);

        // 3 - if it is less than default temporarily ask for default amount
        if ($current_php_memory < $requested_php_memory) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_memory_uplift'), $current_php_memory . 'M / ' . $requested_php_memory . 'M');
            @ini_set('memory_limit', $requested_php_memory . 'M');
        }

        // Request minimum execution time for php process ... 
        // 1 - what is it currently?
        $current_php_execution_time_limit = ini_get('max_execution_time');
        // 2 - if it is less than default temporarily ask for default amount
        if ($current_php_execution_time_limit < $this->settings['img_cp_default_min_php_process_time']) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_process_time_uplift'), $current_php_execution_time_limit . 's / ' . $this->settings['img_cp_default_min_php_process_time'] . 's');
            @ini_set('max_execution_time', $this->settings['img_cp_default_min_php_process_time']);
        }

        // Do a cache audit if one is due
        ee('jcogs_img:ImageUtilities')->cache_audit();
    }


    /**
     * Shuts down the current session.
     *
     * This private method is responsible for terminating the current session.
     * It should be called when the session is no longer needed or before the
     * application exits to ensure proper cleanup.
     *
     * @return void
     */
    private function _shut_down_session() {

        // Request minimum amount of memory for php process ... 
        // 1 - what is it currently?
        $current_php_memory = str_replace('M', '', ini_get('memory_limit'));

        // 2 - if it is less than default temporarily ask for default amount
        if ($current_php_memory < $this->settings['img_cp_default_min_php_ram']) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_memory_uplift'), $current_php_memory . 'M / ' . $this->settings['img_cp_default_min_php_ram'] . 'M');
            @ini_set('memory_limit', $this->settings['img_cp_default_min_php_ram'] . 'M');
        }

        // Request minimum execution time for php process ... 
        // 1 - what is it currently?
        $current_php_execution_time_limit = ini_get('max_execution_time');
        // 2 - if it is less than default temporarily ask for default amount
        if ($current_php_execution_time_limit < $this->settings['img_cp_default_min_php_process_time']) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_process_time_uplift'), $current_php_execution_time_limit . 's / ' . $this->settings['img_cp_default_min_php_process_time'] . 's');
            @ini_set('max_execution_time', $this->settings['img_cp_default_min_php_process_time']);
        }

    }

    /**
     * Save the image
     * 
     * @param object $content
     * @return bool
     */
    private function _save_image($content): bool
    {
        if (!$content->flags->using_cache_copy && !$content->save()) {
            $this->_mark_end();
            unset($content);
            ee('jcogs_img:ImageUtilities')->clear_params();
            return false;
        }

        // Update the cache log index so we have something to work with if we need to do lazy or srcset stuff
        // ee('jcogs_img:ImageUtilities')->update_cache_log(image_path: $content->local_path, processing_time: microtime(true) - $content::$start_time, cache_dir: $content->params->cache_dir, source_path: $content->ident->orig_image_path ?: null);
        return true;
    }
}
