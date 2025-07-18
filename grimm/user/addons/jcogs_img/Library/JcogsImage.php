<?php

/**
 * JCOGS Image Class
 * =================
 * Parent class for a JCOGS Image instance                       
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

namespace JCOGSDesign\Jcogs_img\Library;

require_once PATH_THIRD . "jcogs_img/config.php";

use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\PointSigned;
use Imagine\Image\Palette;
use Imagine\Filter;
use Contao\ImagineSvg\SvgBox;
use Maestroerror\HeicToJpg;
use ColorThief\ColorThief;
use JCOGSDesign\Jcogs_img\Filters as Filters;

class JcogsImage
{
    // Add some variables
    public $flags; // status flags used during operation
    public $ident; // holds information about filename / path etc.
    public $processed_image; // holds the processed image object if available
    public $output; // holds the output generated for this image
    public $params; // holds current validated tagparams
    public $settings; // holds a local copy of Image settings
    public $source_image_raw; // holds the raw original image if available
    public $source_image; // holds the original image object if available
    public $source_svg; // holds the original image object if available
    public static $start_time; // microtime of when this object was created
    public $stats; // holds performance information for this image
    private $transformation; // utility instance of Imagine Transformation object
    public $var_prefix; // used if we are doing prefixed variables

    // Some image variables
    public $aspect_ratio;
    public $aspect_ratio_orig;
    public $faces;
    public $filesize;
    public $fill_color;
    public $filters;
    public $local_path;
    public $new_width;
    public $new_height;
    public $new_size;
    public $offset;
    public $offset_horizontal;
    public $offset_vertical;
    public $opacity;
    public $orig_filesize;
    public $orig_width;
    public $orig_height;
    public $orig_size;
    public $placeholder;
    public $position;
    public $repeat_offset_x;
    public $repeat_offset_y;
    public $repeats_x;
    public $repeats_y;
    public $return;
    public $return_url;
    public $rotation;
    public $save_path;
    public $tagdata;
    public $vars;

    // A filesystem variable
    private $filesystem;
    
    // Instance cached ImageUtilities service
    private $image_utilities;
    
    // Instance cached Utilities service
    private $utilities;

    /**
     * Constructs the JcogsImage object.
     *
     * @param bool $show_debug_message Whether to show the debug message. Default is true.
     */
    function __construct(bool $show_debug_message = true)
    {
        // Cache ImageUtilities and Utilities instances for performance
        $this->image_utilities = ee('jcogs_img:ImageUtilities');
        $this->utilities = ee('jcogs_img:Utilities');
        
        // PRE-LOAD cache log index to optimize directory existence checks
        $this->image_utilities->preload_cache_log_index();
        
        // Start a timer
        $this->stats = new \stdClass();
        self::$start_time = microtime(true);

        // Show debug message if the flag is set to true
        if ($show_debug_message) {
            $this->utilities->debug_message(sprintf(lang('jcogs_img_object_processing_starts'), $this->image_utilities::$instance_count));
        }

        // Initialise some variables
        $this->flags = new \stdClass();
        $this->settings = ee('jcogs_img:Settings')::$settings;
        $this->ident = new \stdClass();
        $this->params = new \stdClass();
        $this->output = new \stdClass();
        $this->stats->transformation_count = 0;
        $this->transformation = new Filter\Transformation(null);
        // Prepare the var prefix
        $var_prefix = '';

        // Get the parameters
        $this->params = $this->image_utilities::$current_params;
        $this->ident->cache_path = '/' . trim($this->params->cache_dir,'/') . '/';

        // Get the var_prefix either from template or from ACT data ... 
        if($this->image_utilities::$act_based_tag  || empty(ee()->TMPL)) {
            $this->var_prefix = $this->params->act_var_prefix;
        } else {
            $tag_parts = ee()->TMPL->tagparts;
            if (is_array($tag_parts) && isset($tag_parts[2])) {
                $var_prefix = $tag_parts[2] . ':';
                $this->var_prefix = $var_prefix;
            }
        }

        // Set some flags... 
        $this->flags->animated_gif = false; // Image is not a gif till shown otherwise
        $this->flags->aspect_scaled_border = false; // Default condition is not to have symmetric borders
        $this->flags->masked_image = false; // Default is to assume image has not been masked
        $this->flags->srcset = false; // Image is not a srcset till shown otherwise
        $this->flags->svg = false; // Image is not an svg till shown otherwise
        $this->flags->use_colour_fill = false; // Default is not to use colour fill
        $this->flags->using_cache_copy = false; // Default is to assume nothing in cache
        $this->flags->valid_image = false; // Start off without a valid image
        $this->flags->allow_scale_larger = false; // Start off with assumption that we cannot scale larger

        // Find out if we are doing a crop
        $this->flags->its_a_crop = substr(strtolower($this->params->crop), 0, 1) != 'n';

        // Find out if we are doing lazy loading
        if (property_exists($this->params, 'lazy')) {
            $this->flags->doing_lazy_loading = ($this->params->lazy && substr(strtolower($this->params->lazy), 0, 1) != 'n') || (!$this->params->lazy && $this->settings['img_cp_enable_lazy_loading'] == 'y');
        }

        // See if we can enlarge image during transformation
        if (property_exists($this->params, 'allow_scale_larger')) {
            $this->flags->allow_scale_larger = substr(strtolower($this->params->allow_scale_larger), 0, 1) == 'y';
        }

        // If we have no template parser in play ... set one up
        if(empty(ee()->TMPL)) {
            ee()->load->library('template');
        }
    }

    /**
     * Initialises a new JCOGS Image object
     * 1) Checks to see if we can get hold of the source image provided
     * 1.1) Build filename for current option - is it in cache?
     * 1.2) If not in cache can we get a copy from source? 
     *
     * @param  bool $use_fallback
     * @return bool
     */
    public function initialise($use_fallback = true)
    {
        $this->utilities->debug_message(lang('jcogs_img_attempting_to_get'), $this->params->src);

        // --- Stage 1: Determine Expected Output Filename and Path for Cache Check ---
        $this->ident->orig_image_path = $this->params->src; // Store the original source

        $parsed_url = parse_url((string) $this->params->src);

        if (empty($this->params->src) || $this->params->src === 'not_set' || !isset($parsed_url['path']) || empty($parsed_url['path'])) {
            $this->ident->orig_filename = 'no_path_in_url_' . hash('tiger160,3', time() . rand(0, 1000));
        } else {
            $this->ident->orig_filename = pathinfo($parsed_url['path'], PATHINFO_FILENAME);
        }

        // Determine current_save_as based on params and potential SVG passthrough - though at this point don't know if it actually is an SVG
        $is_potential_svg = isset($parsed_url['path']) && strtolower(pathinfo($parsed_url['path'], PATHINFO_EXTENSION)) === 'svg';

        // Check if the image is a potential SVG and if passthrough is enabled
        if ($is_potential_svg) {
            // We can't change the format of SVGs, so regardless of the save_as parameter, we need to set it to SVG
            // But save the original value in case we need to revert
            $original_save_as = $this->params->save_as; 
            $this->params->save_as = 'svg';
            $this->flags->svg = true;
        }

        $this->ident->output = $this->_build_filename(filename: $this->ident->orig_filename, params: $this->params);
        $this->local_path = $this->ident->cache_path . $this->ident->output . '.' . $this->params->save_as;
        $this->save_path = rtrim($this->utilities->path($this->local_path), '/');
        // --- End Stage 1 ---

        // --- Stage 2: Cache Check ---
        // Check to see if we have a cache copy of the image - 'return' if we find anything
        if($this->image_utilities->is_image_in_cache($this->local_path)) {
            $this->flags->using_cache_copy = true; // Set flag to indicate we are using a cache copy
            $this->flags->valid_image = true; // Assume valid image if we have a cache copy
            // $this->source_image_raw = $this->image_utilities->read($this->local_path); // Load the raw image data from cache

            return true;
        }
        // if ($this->_is_image_in_cache_and_enabled(local_cache_path: $this->local_path, original_source_path: $this->ident->orig_image_path)) {
        //     $this->utilities->debug_message(sprintf(lang('jcogs_img_initialisation_complete_cache'), microtime(true) - self::$start_time));
        //     return true; 
        // }
        // --- End Stage 2 ---

        // --- Stage 3: Handle Color Fill (if no src and bg_color is provided) - 'return' if we set this up ---
        if (empty($this->params->src) && $this->params->bg_color) {
            $this->flags->use_colour_fill = true;
            $this->flags->valid_image = true;
            // Dimensions for color fill will be determined in _get_new_image_dimensions
            // Ensure ident->output and local_path are set for color fills too.
            $this->ident->orig_filename = 'color_fill_';
            $this->ident->output = $this->_build_filename(filename: $this->ident->orig_filename, params: $this->params);
            $this->local_path = $this->ident->cache_path . $this->ident->output . '.' . $this->params->save_as; // Use current save_as
            $this->save_path = rtrim($this->utilities->path($this->local_path), '/');
            $this->utilities->debug_message(lang('jcogs_img_initialisation_color_fill'));
            return true;
        }
        // --- End Stage 3 ---

        // --- Stage 4: Fetch, Inspect, and Load Source Image ---
        // We only get here if it is a cache miss and we are not doing a colour fill.
        // Setup the source image for processing
        // This is where we will try to get a local copy of the image
        // If the source is empty, we will try to use the fallback source if available
        $source_to_process = $this->ident->orig_image_path;
        $is_fallback_attempt = false;
        $inspection_result = null;
        $this->flags->valid_image = false; // Assume we don't have a valid image until we do


        while (true) {
            if (empty($source_to_process)) {
                // If source is empty, we need to check if we can use a fallback
                $this->utilities->debug_message(lang('jcogs_img_no_src_image_supplied'));
                // Try fallback if option is enabled and we haven't already attempted it
                if ($use_fallback && !$is_fallback_attempt) {
                    if ($this->params->fallback_src) {
                        // Can only do this if there is a fallback source
                        // $this->utilities->debug_message(lang('jcogs_img_attempting_fallback'), $this->params->fallback_src);
                        $source_to_process = $this->params->fallback_src;
                        $this->ident->orig_image_path = $source_to_process;
                        $is_fallback_attempt = true; // Set flag to prevent infinite loop
                        $fallback_parsed_url = parse_url((string)$source_to_process);
                        $this->ident->orig_filename = isset($fallback_parsed_url['path']) ? pathinfo($fallback_parsed_url['path'], PATHINFO_FILENAME) : 'fallback_image';
                        continue; // Restart loop with fallback source
                    } elseif ($this->_evaluate_default_image_options()) {
                        // No fallback source, but maybe we can use a default image - if so _evaluate_default_image_options sets $this->params->src to whatever it is
                        // $this->utilities->debug_message(lang('jcogs_img_attempting_default_fallback'), $this->params->src);
                        $source_to_process = $this->params->src;
                        $this->ident->orig_image_path = $source_to_process;
                        $is_fallback_attempt = true;
                        $default_fallback_parsed_url = parse_url((string)$source_to_process);
                        $this->ident->orig_filename = isset($default_fallback_parsed_url['path']) ? pathinfo($default_fallback_parsed_url['path'], PATHINFO_FILENAME) : 'default_fallback_image';
                        continue; // Restart loop with default fallback source
                    }
                }
                // If no fallback taken or source still empty
                $this->utilities->debug_message(lang('jcogs_img_cannot_get_source_or_fallback_src_empty'));
                $this->flags->valid_image = false;
                return false; // Exit if source is empty and no fallback works
            }

            // If we get here we have something to try and process - let's see if we can get a local copy of the image
            $image_data_result = $this->image_utilities->get_a_local_copy_of_image(path: $source_to_process);

            if ($image_data_result && !empty($image_data_result['image_source'])) {
                $inspection_result = $this->image_utilities->process_and_validate_image_data(
                    raw_data: $image_data_result['image_source'],
                    original_path: $image_data_result['path'] 
                );

                if ($inspection_result && $inspection_result->is_valid) {
                    // Successfully fetched and inspected
                    $this->flags->valid_image = true;
                    $this->source_image_raw = $inspection_result->processed_binary_data; 
                    
                    $this->ident->orig_filename = $inspection_result->original_file_name ?: $this->ident->orig_filename;
                    $this->ident->orig_extension = $inspection_result->original_extension;
                    $this->ident->orig_mime_type = $inspection_result->detected_mime_type;
                    $this->orig_width = $inspection_result->width;
                    $this->orig_height = $inspection_result->height;
                    $this->orig_filesize = $inspection_result->file_size;
                    $this->aspect_ratio_orig = $inspection_result->aspect_ratio;

                    $this->flags->svg = $inspection_result->is_svg;
                    $this->flags->animated_gif = $inspection_result->is_animated_gif;
                    $this->flags->png = $inspection_result->is_png; // is_png from inspection

                    // Update save_as based on inspection results (SVG, HEIC conversion)
                    if ($this->flags->svg) {
                        // If it's an SVG, we need to set save_as to svg
                        $this->params->save_as = 'svg';
                    } elseif ($inspection_result->was_heic_converted) {
                        // If it was a HEIC conversion, we need to set save_as to jpg
                        if (in_array(strtolower($this->ident->orig_extension ?? ''), ['heic', 'heif'])) {
                           $this->params->save_as = 'jpg';
                        }
                    } elseif ($is_potential_svg && !$inspection_result->is_svg) {
                        // If it was a potential SVG and the inspection suggests it is not, we need to set save_as to back to whatever we saved before
                        $this->params->save_as = $original_save_as;
                    }

                    $this->params->save_type = $this->params->save_as; // Keep them in sync

                    // Now re-build of filename and paths based on potentially updated info
                    $this->ident->output = $this->_build_filename(filename: $this->ident->orig_filename, params: $this->params, using_fallback: $is_fallback_attempt);
                    $this->local_path = $this->ident->cache_path . $this->ident->output . '.' . $this->params->save_as;
                    $this->save_path = rtrim($this->utilities->path($this->local_path), '/');
                    break; // Exit loop on success
                } else {
                    $error_msg = $inspection_result ? ($inspection_result->error_message ?? 'Unknown inspection error') : 'Image data fetch failed';
                    $this->utilities->debug_message(lang('jcogs_img_type_not_recognised') . ': ' . $error_msg, $source_to_process);
                    // Set source_to_process to empty to trigger fallback or exit
                    $source_to_process = null;
                }
            } else {
                 $this->utilities->debug_message(lang('jcogs_img_local_copy_failed'), $source_to_process);
                    // Set source_to_process to empty to trigger fallback or exit
                    $source_to_process = null;
            }
        } // End of while loop

        // This part is reached only if 'break' was hit (i.e., success)
        if ($this->flags->valid_image) {
            if (!$this->flags->svg && !$this->flags->animated_gif && $this->source_image_raw) {
                // Only load the image if we have a valid image and it's not an SVG or animated GIF
                try {
                    $this->source_image = (new Imagine())->load($this->source_image_raw);
                    // Ensure dimensions are set if not already from inspection (though they should be)
                    $this->orig_width = $this->orig_width ?: $this->source_image->getSize()->getWidth();
                    $this->orig_height = $this->orig_height ?: $this->source_image->getSize()->getHeight();
                    if ($this->orig_width && $this->orig_height && !$this->aspect_ratio_orig) {
                        $this->aspect_ratio_orig = ($this->orig_height / $this->orig_width);
                    }
                } catch (\Imagine\Exception\Exception $e) {
                    $this->utilities->debug_message(lang('jcogs_img_imagine_load_failed'), $e->getMessage());
                    $this->flags->valid_image = false;
                    return false;
                }
            }
        } else {
        // If we get here we've failed to get a local copy of the image
        $this->utilities->debug_message(lang('jcogs_img_no_backup_image_supplied'));
        return false; 
        }
        
        $this->utilities->debug_message(sprintf(lang('jcogs_img_initialisation_successful_not_cached'), microtime(true) - self::$start_time));
        return true;
    }

    /**
     * Runs through sequence of actions to process an image
     *
     * @return bool
     */
    public function process_image()
    {
        if ($this->flags->using_cache_copy) {
        $this->utilities->debug_message(lang('jcogs_img_processing_skipped_cache_copy'));
        // Ensure essential dimension properties are set if they weren't by _enable_cache_copy
        // This might be redundant if _enable_cache_copy is comprehensive
        if (empty($this->new_width) && !empty($this->ident->width)) $this->new_width = $this->ident->width;
        if (empty($this->new_height) && !empty($this->ident->height)) $this->new_height = $this->ident->height;
        return true; // Skip actual processing if we are using a cache copy
        }

        // 1: Calculate dimensions of new image 
        // ====================================
        
        if (!$this->_get_new_image_dimensions()) {
            return false;
        }

        // Don't do anything more if this is an SVG or a GIF
        if ($this->flags->svg || $this->flags->animated_gif) {
            $this->utilities->debug_message(sprintf(lang('jcogs_img_object_processing_ends'), microtime(true) - self::$start_time));
            return true;
        }

        // --- Ensure we have an Imagine object to process ---
        // This check is crucial if initialise() didn't set source_image for a non-cached image
        if (!$this->source_image || !($this->source_image instanceof \Imagine\Image\ImageInterface)) {
            $this->utilities->debug_message(lang('jcogs_img_source_image_invalid_for_processing'));
            return false;
        }

        $this->processed_image = $this->source_image->copy();


        
        // 2: Change size of processed image
        // =================================

        // Do a crop or resize if required?
        if ($this->flags->its_a_crop) {
            // It's a crop!
            if (!$this->_image_crop()) {
                return false;
            }
        } else {
            // It's a resize!
            if (!$this->_image_resize()) {
                return false;
            }
        }

        // 3: Transform adjusted image
        // ===========================
        // Process sequence: flip, filters, text overlay, watermarks, rounded corners, borders, reflection, rotation
        $transformations = [
            '_image_flip',
            '_apply_filters',
            '_add_text_overlay',
            '_add_watermark',
            '_add_rounded_corners',
            '_add_border',
            '_image_reflect',
            '_image_rotate'
        ];

        if (!$this->flags->svg && !$this->flags->animated_gif) {
            foreach ($transformations as $transformation) {
                if (!call_user_func([$this, $transformation])) {
                    return false;
                }
            }
        }

        // 4: Apply the transformation queue
        // =================================
        // Apply the transformation queue to the image
        $temp_image = $this->transformation->apply($this->processed_image);
        if ($temp_image) {
            $this->processed_image = $temp_image->copy();
            unset($temp_image);
        } else {
            $this->utilities->debug_message(lang('jcogs_img_filter_queue_failed'));
            return false;
        }

        $this->utilities->debug_message(sprintf(lang('jcogs_img_object_processing_ends'), microtime(true) - self::$start_time));
        return true;
    }

     public function post_process()
    {
        ee()->load->helper('url');
        $var_prefix = $this->var_prefix;
        $time_start = microtime(true);
        $this->utilities->debug_message(lang('jcogs_img_post_processing_image'));

        // --- SECTION 1: Ensure primary variables are set in $this->vars[0] ---
        if (!$this->flags->using_cache_copy || empty($this->vars[0])) {
            // This block executes if:
            // 1. It's NOT a cache copy ($this->flags->using_cache_copy is false).
            // 2. It IS a cache copy, but $this->vars[0] was unexpectedly empty.

            // See if we can enable the image vars
            $cache_log_vars_result = $this->image_utilities->get_cache_log_vars(image_path: $this->local_path);
            
            $cached_vars = null;
            if ($cache_log_vars_result && isset($cache_log_vars_result[0]) && is_array($cache_log_vars_result[0])) {
                $cached_vars = $cache_log_vars_result[0];
            }

            if ($cached_vars) {
                // We may have the necessary metadata from the cache log.
                $this->_enable_cache_copy(original_source_path: $this->params->src, local_cache_path: $this->local_path, cached_vars: $cached_vars);
                }

            if (empty($this->vars[0])) {
                $this->vars[0] = [];
            }

            if (!$this->flags->use_colour_fill && !$this->flags->svg && !$this->flags->animated_gif) {

                // If we don't have it already (cache copy or other reason) set size and dimensions
                if ($this->local_path && ($this->flags->using_cache_copy || $this->flags->use_colour_fill)) {
                    $this->filesize = $this->vars[0] && array_key_exists($var_prefix . 'filesize', $this->vars[0]) ? $this->vars[0][$var_prefix . 'filesize'] : $this->image_utilities->filesize($this->local_path);
                    if (!empty($this->vars[0])) {
                        $this->new_width = $this->vars[0][$var_prefix . 'width'];
                        $this->new_height = $this->vars[0][$var_prefix . 'height'];
                    } else {
                        $new_image_dimensions = $this->image_utilities->getimagesize($this->local_path);
                        // Adding 0.4 before rounding to ensure proper rounding to the nearest integer
                        $this->new_width = isset($new_image_dimensions[0]) && $new_image_dimensions[0] > 0 ? (int) round($new_image_dimensions[0] + .4, 0) : null;
                        $this->new_height = isset($new_image_dimensions[1]) && $new_image_dimensions[1] > 0 ? (int) round($new_image_dimensions[1] + .4, 0) : null;
                    }
                }

                // If we don't have it, get the image aspect ratio
                if (!isset($this->aspect_ratio) && $this->new_width > 0) {
                    $this->aspect_ratio = $this->vars[0] ? $this->vars[0][$var_prefix . 'aspect_ratio'] : $this->new_height / $this->new_width;
                }

                // Ensure ident->mime_type is consistently set for use in generate_output
                if (isset($this->vars[0][$var_prefix . 'mime_type'])) {
                    $this->ident->mime_type = $this->vars[0][$var_prefix . 'mime_type'];
                } elseif (!empty($this->params->save_as)) {
                    $this->ident->mime_type = $this->image_utilities->get_mime_type($this->params->save_as);
                } else {
                    $this->ident->mime_type = ''; // Default if not found
                }

                // If we don't have it, get the image new_size value
                if (!isset($this->new_size) && $this->new_width > 0 && $this->new_height > 0) {
                    $this->new_size = new Box($this->new_width, $this->new_height);
                }
    
                // Get the filesize of the original image to ensure we have accurate data for comparison
                // and to determine if the image has been modified or needs further processing.
                if ($this->flags->using_cache_copy) {
                    $this->orig_filesize = isset($this->vars[0][$var_prefix . 'filesize_bytes_orig']) ? $this->vars[0][$var_prefix . 'filesize_bytes_orig'] : '';
                } else {
                    $this->orig_filesize = isset($this->vars[0][$var_prefix . 'filesize_bytes_orig']) ? $this->vars[0][$var_prefix . 'filesize_bytes_orig'] : $this->orig_filesize;
                }
            }

            // Populate essential vars if not already set (e.g., by a failed cache load or for a fresh image)
            // This ensures primary variables are available for supplemental variable generation.
            $aspect_ratio_orig = property_exists($this, 'aspect_ratio_orig') && $this->aspect_ratio_orig ? $this->aspect_ratio_orig : '';
            $width_orig = isset($this->orig_width) ? (int) $this->orig_width : '';
            $height_orig = isset($this->orig_height) ? (int) $this->orig_height : '';
            if (!isset($this->vars[0][$var_prefix . 'width'])) {
            $this->vars[0] = [
                $var_prefix . 'aspect_ratio' => property_exists($this, 'aspect_ratio') && $this->aspect_ratio ? $this->aspect_ratio : $aspect_ratio_orig,
                $var_prefix . 'aspect_ratio_orig' => property_exists($this, 'aspect_ratio_orig') && $this->aspect_ratio_orig ? $this->aspect_ratio_orig : '',
                $var_prefix . 'attributes' => '',
                $var_prefix . 'extension' => $this->params->save_as,
                $var_prefix . 'extension_orig' => property_exists($this->ident, 'orig_extension') ? $this->ident->orig_extension : '',
                $var_prefix . 'height' => $this->new_height ?: $height_orig,
                $var_prefix . 'height_orig' => $height_orig,
                $var_prefix . 'made' => $this->image_utilities->get_image_path_prefix() . trim($this->local_path,'/'),
                $var_prefix . 'made_url' => $this->settings['img_cp_flysystem_adapter'] == 'local' ? rtrim(base_url(), '/') . $this->image_utilities->get_image_path_prefix() . trim($this->local_path,'/') : $this->image_utilities->get_image_path_prefix() . trim($this->local_path,'/'),
                $var_prefix . 'made_with_prefix' => $this->image_utilities->get_image_path_prefix() . trim($this->local_path,'/'),
                $var_prefix . 'mime_type' => property_exists($this, 'mime_type') ? $this->ident->mime_type : '',
                $var_prefix . 'name' => $this->ident->output,
                $var_prefix . 'name_orig' => property_exists($this->ident, 'orig_filename') ? $this->ident->orig_filename : '',
                $var_prefix . 'orig' => $this->params->src == '' ?: parse_url($this->params->src)['path'],
                $var_prefix . 'orig_url' => (string) $this->params->src,
                $var_prefix . 'path' => $this->settings['img_cp_flysystem_adapter'] == 'local' ? rtrim($this->utilities->path($this->local_path), '/') : '',
                $var_prefix . 'path_orig' => $this->params->src != '' ? rtrim($this->utilities->path(parse_url($this->params->src)['path']), '/') : null,
                $var_prefix . 'preload' => 'data-ji-preload="' . $this->image_utilities->get_image_path_prefix() . trim($this->local_path,'/') .'"',
                $var_prefix . 'width' => $this->new_width ?: $width_orig,
                $var_prefix . 'width_orig' => $width_orig,
                $var_prefix . 'type' => $this->params->save_as,
                $var_prefix . 'type_orig' => property_exists($this->ident, 'orig_extension') ? $this->ident->orig_extension : '',
            ];
            }
        }
        // --- END SECTION 1 ---

        // --- SECTION 2: Generate Supplemental Variables ---
        // This section populates additional variables.

        // 2a. File metadata (title, description, etc.) from EE's File model
        if(!isset($this->vars[0][$var_prefix . 'img_title']) && property_exists($this->ident, 'orig_image_path') && !empty($this->ident->orig_image_path) && strpos($this->ident->orig_image_path, 'http') !== 0) {
            $file_basename = pathinfo($this->ident->orig_image_path, PATHINFO_BASENAME);
            if ($file_basename) {
                // Add static caching to prevent duplicate EE File model queries
                static $file_cache = [];
                if (!isset($file_cache[$file_basename])) {
                    $file_cache[$file_basename] = ee('Model')->get('File')->filter('file_name', $file_basename)->first();
                }
                $file = $file_cache[$file_basename];
                
                if ($file) {
                    $this->vars[0][$var_prefix . 'img_title'] = $file->title;
                    $this->vars[0][$var_prefix . 'img_description'] = $file->description;
                    $this->vars[0][$var_prefix . 'img_credit'] = $file->credit;
                    $this->vars[0][$var_prefix . 'img_location'] = $file->location;
                }
            }
        }
        
        // 2b. Path parts of the original image
        if (property_exists($this->ident, 'orig_image_path') && !empty($this->ident->orig_image_path)) {
            $orig_path_info = pathinfo($this->ident->orig_image_path);
            if(!isset($this->vars[0][$var_prefix . 'path'])) $this->vars[0][$var_prefix . 'path'] = $orig_path_info['dirname'] ?? '';
            if(!isset($this->vars[0][$var_prefix . 'basename'])) $this->vars[0][$var_prefix . 'basename'] = $orig_path_info['basename'] ?? '';
            // Note: 'extension' and 'filename' for the *output* are already covered by 'save_as' and 'filename_output'
            // These are for the *original* source image.
            if(!isset($this->vars[0][$var_prefix . 'extension_orig'])) $this->vars[0][$var_prefix . 'extension_orig'] = $orig_path_info['extension'] ?? '';
            if(!isset($this->vars[0][$var_prefix . 'filename_orig'])) $this->vars[0][$var_prefix . 'filename_orig'] = $orig_path_info['filename'] ?? '';
        }
        
        // 2c. Lazy loading placeholders, dominant color, LQIP, base64 (if needed from tagdata)
        $the_tagdata = $this->image_utilities::$act_based_tag ? ($this->params->act_tagdata ?? '') : (ee()->TMPL->tagdata ?? '');
        $haystack = $the_tagdata . ($this->params->output ?? '');

        // Initialize potentially used vars to prevent notices
        if(!isset($this->vars[0][$var_prefix . 'lazy_image'])) $this->vars[0][$var_prefix . 'lazy_image'] = '';
        if(!isset($this->vars[0][$var_prefix . 'dominant_color'])) $this->vars[0][$var_prefix . 'dominant_color'] = '';
        if(!isset($this->vars[0][$var_prefix . 'lqip'])) $this->vars[0][$var_prefix . 'lqip'] = '';
        if(!isset($this->vars[0][$var_prefix . 'base64'])) $this->vars[0][$var_prefix . 'base64'] = '';

        if ($this->flags->doing_lazy_loading && !$this->flags->svg && (($this->params->lazy != '' && substr(strtolower($this->params->lazy), 0, 1) != 'h') || ($this->params->lazy == '' && $this->settings['img_cp_lazy_loading_mode'] != 'html5'))) {
            // We need to work out what mode of lazy loading we are doing
            $lazy_param = substr(strtolower($this->params->lazy), 0, 1);
            $mode = $lazy_param && $lazy_param !== 'h' ? $this->params->lazy : $this->settings['img_cp_lazy_loading_mode'];
            $this->vars[0][$var_prefix . 'lazy_image'] = $this->_generate_lazy_placeholder_image(mode: $mode) ?: '';
        }

        if (!$this->flags->svg && !$this->flags->animated_gif) {
            // Check if the tagdata contains placeholders for lazy_image, dominant_color, lqip, base64, and if needed generate values for them
            // We cannot do this if we are working with an SVG or animated GIF

            // First check to see if we need to process the tagdata or if there is an 'output' parameter specified
            $the_tagdata = $this->image_utilities::$act_based_tag ? $this->params->act_tagdata : ee()->TMPL->tagdata;
            $haystack = $the_tagdata ?: '';
            $haystack .= $this->params->output;

            // Do we have a base64 placeholder in the tagdata?
            if (stripos($haystack, '{' . $var_prefix . 'base64}') !== false && empty($this->vars[0][$var_prefix . 'base64'])) {
                $image_for_base64 = null;
                $image_content_for_base64 = null;
                if ($this->flags->using_cache_copy && $this->local_path) {
                    $image_content_for_base64 = $this->image_utilities->read(trim($this->local_path, '/'));
                } elseif (property_exists($this, 'processed_image') && $this->processed_image) {
                    $image_for_base64 = $this->processed_image; // Already an Imagine object
                } elseif ($this->source_image_raw) { // Fallback for non-Imagine processed images like SVG/GIF if direct base64 is needed
                     $image_content_for_base64 = $this->source_image_raw;
                }

                if ($image_for_base64) { // If we have an Imagine object
                     $this->vars[0][$var_prefix . 'base64'] = $this->image_utilities->encode_base64(
                        $image_for_base64,
                        $this->params->save_as,
                        $this->params->save_as == 'png' ? ($this->params->png_quality ?? $this->settings['img_cp_png_default_quality']) : ($this->params->quality ?? $this->settings['img_cp_default_quality'])
                    );
                } elseif ($image_content_for_base64) {
                    $mime = $this->vars[0][$var_prefix . 'mime'] ?? $this->image_utilities->get_mime_type($this->params->save_as);
                    if ($mime) {
                        $this->vars[0][$var_prefix . 'base64'] = 'data:' . $mime . ';base64,' . base64_encode($image_content_for_base64);
                    }
                }
            }
            
            // Do we have a base64_orig placeholder in the tagdata?
            if (!$this->flags->using_cache_copy && stripos($haystack, '{' . $var_prefix . 'base64_orig}') !== false && empty($this->vars[0][$var_prefix . 'base64_orig'])) {
                $image_content_for_base64 = null;
                if ($this->source_image_raw) { // We can only do this if we have the source raw image data
                     $image_content_for_base64 = $this->source_image_raw;
                    $mime = $this->vars[0][$var_prefix . 'mime'] ?? $this->image_utilities->get_mime_type($this->params->save_as);
                    if ($mime) {
                        $this->vars[0][$var_prefix . 'base64'] = 'data:' . $mime . ';base64,' . base64_encode($image_content_for_base64);
                    }
                }
            }

            // Do we have a dominant_color placeholder in the tagdata?
            if (stripos($haystack, '{' . $var_prefix . 'dominant_color}') !== false && $this->vars[0][$var_prefix . 'dominant_color'] == '') {
                $this->vars[0][$var_prefix . 'dominant_color'] = $this->_generate_lazy_placeholder_image('dominant_color');
            }

            // Do we have a lqip placeholder in the tagdata?
            if (stripos($haystack, '{' . $var_prefix . 'lqip}') !== false && $this->vars[0][$var_prefix . 'lqip'] == '') {
                $this->vars[0][$var_prefix . 'lqip'] = $this->_generate_lazy_placeholder_image();
            }

            // Do we have an average_color placeholder in the tagdata?
            if (stripos($haystack, '{' . $var_prefix . 'average_color}') !== false) {
                // Get the GDImage object and run through colorthief
                $img = imagecreatefromstring($this->processed_image->__toString());
                $this->vars[0][$var_prefix . 'average_color'] = ColorThief::getColor($img, 10, null, 'hex');
                unset($img);
            }

            // Do we have an aspect_ratio placeholder in the tagdata?
            if (stripos($haystack, '{' . $var_prefix . 'aspect_ratio}') !== false && $this->vars[0][$var_prefix . 'aspect_ratio'] == '') {
                // Get here because it is a cache image - really want it so get aspect_ratio
                $ar_size = @getimagesize($this->local_path);
                $this->vars[0][$var_prefix . 'aspect_ratio'] = $ar_size ? $ar_size[2] / $ar_size[1] : '';
            }
        }

        // 2d. Consolidate and set attributes
        if (
            property_exists($this->params, 'consolidate_class_style') && $this->params->consolidate_class_style == 'y' &&
            (
                isset($this->tagdata) ||
                ($this->params->attributes && trim($this->params->attributes) != '') ||
                (
                    $this->flags->doing_lazy_loading &&
                    !$this->flags->svg && $this->params->lazy != '' &&
                    substr(strtolower($this->params->lazy), 0, 1) != 'j' // Assuming 'j' was for JavaScript/HTML5 lazy loading
                )
            )
        ) {
            // Consolidate class attributes from tagdata and attributes parameter
            $new_class = '';
            if ($this->params->attributes && preg_match_all("/(?:class=)(\'|\")(.*?)\g1/", $this->params->attributes, $matches_attr_class)) {
                foreach ($matches_attr_class[2] as $class_item) {
                    $new_class = !str_contains($new_class, $class_item) ? $new_class . ' ' . $class_item : $new_class;
                }
            }
            if ($this->params->bulk_tag == 'n' && isset($this->tagdata) && preg_match_all("/(?:class=)(\'|\")(.*?)\g1/", $this->tagdata, $matches_tag_class)) {
                 foreach ($matches_tag_class[2] as $class_item) {
                    $new_class = !str_contains($new_class, $class_item) ? $new_class . ' ' . $class_item : $new_class;
                }
            }
            $new_class = trim($new_class);

            // Consolidate style attributes from tagdata and attributes parameter
            $new_style = '';
            if ($this->params->attributes && preg_match_all("/(?:style=)(\'|\")(.*?)\g1/", $this->params->attributes, $matches_attr_style)) {
                $new_style .= implode(' ', $matches_attr_style[2]);
            }
            if($this->flags->doing_lazy_loading && !$this->flags->svg && $this->params->lazy != '' && substr(strtolower($this->params->lazy), 0, 1) != 'j' && isset($this->placeholder) && property_exists($this->placeholder, 'return_url') && $this->placeholder->return_url) {
                $new_style = $new_style ? rtrim(trim($new_style),';') . ';' : '';
                // Action link for placeholder will be applied in Section 3
                $new_style .= " background-image: url(" . $this->placeholder->return_url . ");";
            }
            if ($this->params->bulk_tag == 'n' && isset($this->tagdata) && preg_match_all("/(?:style=)(\'|\")(.*?)\g1/", $this->tagdata, $matches_tag_style)) {
                $new_style .= ' ' . implode(' ', $matches_tag_style[2]);
            }
            $new_style = trim($new_style);

            // Consolidate alt attributes from tagdata and attributes parameter
            $new_alt = '';
            if ($this->params->attributes && preg_match_all("/(?:alt=)(\'|\")(.*?)\g1/", $this->params->attributes, $matches_attr_alt)) {
                foreach ($matches_attr_alt[2] as $class_item) {
                    $new_alt = !str_contains($new_alt, $class_item) ? $new_alt . ' ' . $class_item : $new_alt;
                }
            }
            if ($this->params->bulk_tag == 'n' && isset($this->tagdata) && preg_match_all("/(?:class=)(\'|\")(.*?)\g1/", $this->tagdata, $matches_tag_alt)) {
                 foreach ($matches_tag_alt[2] as $class_item) {
                    $new_alt = !str_contains($new_alt, $class_item) ? $new_alt . ' ' . $class_item : $new_alt;
                }
            }
            $new_alt = trim($new_alt);

            $current_attributes = $this->params->attributes ?? '';
            if ($current_attributes) {
                $current_attributes = preg_replace("/class=(\'|\").*?\g1/", '', $current_attributes);
                $current_attributes = preg_replace("/style=(\'|\").*?\g1/", '', $current_attributes);
                $current_attributes = preg_replace("/alt=(\'|\").*?\g1/", '', $current_attributes);
                $this->params->attributes = trim($current_attributes);
            } else {
                $this->params->attributes = '';
            }

            $this->params->attributes .= ' alt="' . trim($new_alt) . '"';

            if ($new_class && substr(strtolower($this->params->exclude_class ?? 'n'), 0, 1) != 'y') {
                $this->params->attributes .= ' class="' . trim($new_class) . '"';
            }
            if ($new_style && substr(strtolower($this->params->exclude_style ?? 'n'), 0, 1) != 'y') {
                $this->params->attributes .= ' style="' . trim($new_style) . '"';
            }
            $this->params->attributes = trim($this->params->attributes);
        }

        // Ensure attributes var is set
        if(!empty($this->vars[0][$var_prefix . 'attributes']) && !$this->flags->using_cache_copy) {
            $this->vars[0][$var_prefix . 'attributes'] .= ' ' . trim($this->params->attributes ?? '');
        } else {
            $this->vars[0][$var_prefix . 'attributes'] = trim($this->params->attributes ?? '');
        }


        // 2e. Srcset generation
        // Initialize to prevent notices if srcset not generated
        if(!isset($this->vars[0]["{$var_prefix}srcset_param"]))
            $this->vars[0]["{$var_prefix}srcset_param"] = '';
        if(!isset($this->vars[0]["{$var_prefix}sizes_param"]))
            $this->vars[0]["{$var_prefix}sizes_param"] = '';

        if ($this->params->srcset && !$this->flags->svg && !$this->flags->animated_gif) {
            $this->utilities->debug_message(lang('jcogs_img_srcset_begin'), $this->params->srcset);
            $srcset_values = explode('|', $this->params->srcset);

            $image_options = [];
            if (in_array($this->params->save_as, ['jpg', 'jpeg', 'webp'], true)) {
                $image_options['quality'] = (int) min(max($this->params->quality, 0), 100);
            } elseif (in_array($this->params->save_as, ['png'], true)) {
                $image_options['quality'] = (int) min(max($this->settings['img_cp_png_default_quality'], 0), 9);
            }

            $local_srcset_param = "";
            $local_sizes_param = "";
            if ($this->params->sizes) {
                $local_sizes_param .= rtrim($this->params->sizes, ',') . ', ';
            }
            $current_entry = 0;
            $base_srcset_image = null; // To hold the Imagine object for base image

            foreach ($srcset_values as $raw_width) {
                $width = (int) $this->image_utilities->validate_dimension($raw_width, $this->orig_width);
                if (is_numeric($width) && $width > $current_entry && ($width <= ($this->new_width ?: $this->orig_width) || $this->flags->allow_scale_larger)) {
                    $srcset_filename_suffix = '_' . $width . 'w.' . $this->params->save_as;
                    // Ensure ident->output and ident->cache_path are available
                    $base_output_name = $this->ident->output ?? pathinfo($this->local_path ?? 'default', PATHINFO_FILENAME);
                    $cache_path_prefix = $this->ident->cache_path ?? '';
                    $srcset_file_rel_path = trim($cache_path_prefix . $base_output_name . $srcset_filename_suffix, '/');

                    if (!$this->image_utilities->exists($srcset_file_rel_path)) {
                        $this->utilities->debug_message(sprintf(lang('jcogs_img_srcset_generate_image'), $raw_width), $srcset_file_rel_path);
                        
                        if (!$base_srcset_image) { // Load base image only once if needed
                            if (property_exists($this, 'processed_image') && $this->processed_image) {
                                $base_srcset_image = $this->processed_image; // It's already a copy or the source
                            } elseif ($this->source_image_raw && !$this->flags->svg && !$this->flags->animated_gif) {
                                try { $base_srcset_image = (new Imagine())->load($this->source_image_raw); } catch (\Exception $e) { break; }
                            } elseif ($this->flags->using_cache_copy && $this->local_path) {
                                 $main_cached_content = $this->image_utilities->read(trim($this->local_path, '/'));
                                 if ($main_cached_content) {
                                     try { $base_srcset_image = (new Imagine())->load($main_cached_content); } catch (\Exception $e) { break; }
                                 }
                            }
                        }

                        if (!$base_srcset_image) {
                            $this->utilities->debug_message(lang('srcset_processing_error_no_base_image'));
                            break;
                        }
                        
                        $temp_srcset_image = $base_srcset_image->copy();
                        $current_size = $temp_srcset_image->getSize();
                        $new_srcset_box = $current_size->widen($width);
                        
                        try {
                            $temp_srcset_image->resize($new_srcset_box);
                            $this->image_utilities->write($srcset_file_rel_path, $temp_srcset_image->get($this->params->save_as, $image_options));
                        } catch (\Exception $e) {
                            $this->utilities->debug_message(lang('srcset_processing_error') . ': ' . $e->getMessage());
                            unset($temp_srcset_image); // Clean up
                            break; 
                        }
                        unset($temp_srcset_image); // Clean up
                    }
                    $local_srcset_param .= $this->image_utilities->get_image_path_prefix() . trim($srcset_file_rel_path,'/') . ' ' . $width . 'w, ';
                    $local_sizes_param .= '(max-width:' . $width . 'px) ' . $width . 'px, ';
                    $current_entry = $width;
                } else {
                    $this->utilities->debug_message(sprintf(lang('jcogs_img_srcset_option_invalid'), $raw_width));
                }
            }
            // $base_srcset_image is not unset here, as it might be $this->processed_image

            if ($current_entry > 0) {
                $final_width_descriptor = $this->new_width ?: $this->orig_width; // Use the main processed image's width
                $main_image_path_for_srcset = $this->image_utilities->get_image_path_prefix() . trim($this->local_path, '/');
                
                $local_srcset_param .= $main_image_path_for_srcset . ' ' . $final_width_descriptor . 'w';
                $local_sizes_param .= $final_width_descriptor . 'px';

                $this->vars[0][$var_prefix . 'srcset_param'] = $local_srcset_param;
                $this->vars[0][$var_prefix . 'sizes_param'] = $local_sizes_param;
                $this->flags->srcset = true;
            } else {
                $this->utilities->debug_message(lang('jcogs_img_srcset_noop'));
            }
            $this->utilities->debug_message(lang('jcogs_img_srcset_end'));
        }
        // --- END SECTION 2 ---

        // --- SECTION 3: Update cache log ---
        // Now that we have the image processed and the variables set, we can update the cache log

        $force_update = $this->image_utilities::$image_log_needs_updating || !$this->flags->using_cache_copy;
        $source_var = array_key_exists($var_prefix . 'orig', $this->vars[0]) && !empty($this->vars[0][$var_prefix . 'orig']) ? $this->vars[0][$var_prefix . 'orig'] : '';

        if (!$this->image_utilities->update_cache_log(
            image_path: $this->local_path, 
            processing_time: microtime(true) - self::$start_time, 
            cache_dir: $this->params->cache_dir, 
            vars: $this->vars, 
            source_path: $source_var, 
            force_update: $force_update,
            using_cache_copy: $this->flags->using_cache_copy
        )) {
            $this->utilities->debug_message(lang('jcogs_img_cache_log_update_failed'), $this->local_path);
            return false;
        };
        // --- END SECTION 3 ---
        
        // --- SECTION 3: Final Adjustments (e.g., Action Links) ---
        // This modifies the URLs right before output.
        foreach(['made','made_url','made_with_prefix','lazy_image','dominant_color','lqip'] as $mode) {
            if (isset($this->vars[0][$var_prefix . $mode]) && !empty($this->vars[0][$var_prefix . $mode])) {
                $this->vars[0][$var_prefix . $mode] = $this->_generate_action_link(data: $this->vars[0][$var_prefix . $mode], what: $mode);
            }
        }
        // --- END SECTION 4 ---

        $this->utilities->debug_message(sprintf(lang('jcogs_img_post_processed'), microtime(true) - $time_start));
        return true;
    }

    /**
     * Utility function: Generate output to return to template
     *
     * @return array|bool
     */
    public function generate_output()
    {
        // Start a timer for this operation run
        $time_start = microtime(true);
        $this->utilities->debug_message(lang('jcogs_img_generating_output'));

        // Add a pre-parse hook for quasi-compatibility with CE Image
        // This is not quite same as some variables are set after this point... but nearly!
        $this->tagdata = $this->image_utilities::$act_based_tag ? $this->params->act_tagdata : ee()->TMPL->tagdata;
        if (ee()->extensions->active_hook('jcogs_img_pre_parse')) {
            $this->tagdata = ee()->extensions->call('ce_img_pre_parse', $this->tagdata, $this->vars, null);
        }

        // Start by creating a container for the output we are building
        $this->return = '';

        // Are we returning relative or full paths?
        $the_output_url = $this->vars[0][$this->var_prefix . 'made']; // default is to use relative paths
        if (strtolower(substr($this->settings['img_cp_class_always_output_full_urls'], 0, 1)) == 'y') {
            // full URLs requested
            $the_output_url = $this->vars[0][$this->var_prefix . 'made_url'];
            $this->utilities->debug_message(lang('jcogs_img_generating_full_urls'));
        }

        // What kind of output are we generating?
        // Options are: 
        //   1 - url only
        //   2 - single tag with output param -> parse of output param contents
        //   3 - tag data from a standard tag pair (i.e. not bulk tag pair) -> parse tagdata
        //   4 - create_tag set to 'n', no output param, no tagdata -> do nothing
        //   5 - create_tag set to 'y' or single tag with no create tag specified -> build tag

        if (substr(strtolower($this->params->url_only), 0, 1) == 'y') {
            // Option 1 - url_only set or called from ACT (which can only return URL)
            $this->return = $the_output_url;

        } elseif ($this->params->bulk_tag == 'n' && $this->params->output != '') {
            // Option 2 - single tag + output parameter -> custom output
            $this->return = $this->image_utilities::$act_based_tag ? ee()->template->parse_variables($this->params->output, $this->vars) : ee()->TMPL->parse_variables($this->params->output, $this->vars);
            
            // Option 3 - not bulk tag, there is tagdata and either no create_tag or create_tag == 'n' : just parse tagdata
        } elseif ($this->params->bulk_tag == 'n' && $this->tagdata && substr(strtolower($this->params->create_tag), 0, 1) != 'y') {
            // Option 3 - not bulk tag, there is tagdata and either no create_tag or create_tag == 'n' : just parse tagdata
            $this->return = $this->image_utilities::$act_based_tag ? ee()->template->parse_variables($this->tagdata, $this->vars) : ee()->TMPL->parse_variables($this->tagdata, $this->vars);
            // $this->return = ee()->TMPL->parse_variables($this->tagdata, $this->vars);

            // Option 4 - create_tag = 'n' so do nothing...
        } elseif (substr(strtolower($this->params->create_tag), 0, 1) == 'n') {

        } else {
            // Option 5 - create_tag == y, or single tag and no create_tag specified : build an img tag
            // To build the tag we break the activities down into components:
            // 1) Adding pass-through attributes class and style tags if there are any
            // 2) Adding in performance attributes (decoding, loading, etc) (need to this after attributes added in case they appear in the attributes)
            // 3) Adding dimension parameters if required (second as we need to know if we are doing lazy loading)
            // 4) Adding in a role= attribute if the image is an SVG
            // 5) Adding in preload attribute if required
            // 6) Reconfiguring src, srcset for lazy loading if required

            // 1) Adding attributes including any consolidated class / style tags.
            // -------------------------------------------------------------------
            if (substr(strtolower($this->settings['img_cp_attribute_variable_expansion_default']), 0, 1) == 'y') {
                $this->return .= $this->image_utilities::$act_based_tag ? ' ' . ee()->template->parse_variables($this->vars[0][$this->var_prefix . 'attributes'], $this->vars) : ' ' . ee()->TMPL->parse_variables($this->vars[0][$this->var_prefix . 'attributes'], $this->vars);
            } else {
                $this->return .= ' ' . $this->vars[0][$this->var_prefix . 'attributes'];
            }

            // Are we adding a decoding attribute?
            $decoding_str = $this->settings['img_cp_enable_lazy_loading'] == 'y' &&  !str_contains($this->return, 'decoding=') ? '' : ' decoding="async" ';
            $this->return .= $decoding_str;

            // 2) Adding performance attributes (decoding, loading, etc)
            // ------------------------------------------------------
            // If the user has already set a decoding parameter then don't include it again, otherwise set to 'async'
            // Note: the decoding attribute is not supported in all browsers, but it is in most and otherwise it is ignored
            $this->return  .= str_contains($this->return, 'decoding=') ? '' : ' decoding="async" ';

            // 3) Adding dimensions parameters if required
            // -------------------------------------------
            $add_dims = false;
            // We have two different parameters for this, so need a bit of logic to work out ... 
            if ($this->params->add_dims) {
                // add_dims has priority if both are set
                $add_dims = substr(strtolower($this->params->add_dims), 0, 1) == 'y';
            } elseif ($this->params->add_dimensions) {
                $add_dims = substr(strtolower($this->params->add_dimensions), 0, 1) == 'y';
            } 
            
            // If we have a loading="lazy" attribute already in $this->return (i.e. added by the user in the tag) then add dimensions
            $add_dims = str_contains($this->return, 'loading="lazy"') ? true : $add_dims;

            // If we are adding JCOGS Image lazy loading, then always add dimensions
            $add_dims = $this->flags->doing_lazy_loading && (str_starts_with($this->params->lazy, 'l') || str_starts_with($this->params->lazy, 'd')) ? true : $add_dims;

            // If we have an animated gif then always add dimensions
            $add_dims = $this->flags->animated_gif ? true : $add_dims;

            // Now add the dims if we are going to...
            // if we have a srcset term involved, do not add width
            $this->return .= $add_dims && !$this->flags->srcset && $this->vars[0][$this->var_prefix . 'width'] > 0 ? ' width="' . $this->vars[0][$this->var_prefix . 'width'] . '"' : '';

            $this->return .= $add_dims && $this->vars[0][$this->var_prefix . 'height'] > 0 ? ' height="' . $this->vars[0][$this->var_prefix . 'height'] . '"' : '';

            // add tagdata if there is any... (unless we're processing a bulk tag)
            $this->return .= ' ' . substr(strtolower($this->params->bulk_tag), 0, 1) == 'n' && $this->tagdata ? preg_replace('/\{.*\}/', '', $this->tagdata) : '';
            $this->return = trim($this->return);

            // 4) If image is svg add role attribute to img tag (MDN - http://developer.mozilla.org/en-US/docs/Web/HTML/Element/img#identifying_svg_as_an_image)
            // ------------------------------------------------
            if ($this->flags->svg) {
                $this->return .= ' role="img"';
            }

            // 5) If we are doing preload, add this
            // ------------------------------------

            if (substr(strtolower($this->params->preload),0, 1) === 'y') {
                $this->return .= ' data-ji-preload="'.$the_output_url.'"';
            }

            // 6) Reconfiguring src, srcset for lazy loading if required
            // ---------------------------------------------------------
            $return = '';

            // Are we doing lazy loading?
            if ($this->flags->doing_lazy_loading && !$this->flags->svg && !$this->flags->animated_gif) {
                // We are doing some kind of lazy loading so see if we need to add a loading="lazy" attribute (it might already be there)
                $lazy_string = str_contains($this->return, 'loading=') ? '' : 'loading="lazy" ';

                if (($this->params->lazy != '' && substr(strtolower($this->params->lazy), 0, 1) != 'h') || ($this->params->lazy == '' && $this->settings['img_cp_enable_lazy_loading'] == 'y' && $this->settings['img_cp_lazy_loading_mode'] != 'html5')) {
                    // We doing the full lazy option - lazy parameter not set to 'no' and not to 'html5'
                    // Find out if we are doing javascript lazy or background_lazy options
                    // Background approach implements ideas found here https://csswizardry.com/2023/09/the-ultimate-lqip-lcp-technique/#section:sub-content
                    if($this->params->lazy == 'lqip' || $this->params->lazy == 'dominant_color') {
                        // We are doing background version ... 
                        // Are we also doing srcset?
                        if ($this->flags->srcset) {
                            $return = ' srcset="' . $this->vars[0][$this->var_prefix . 'srcset_param'] . '" sizes="' . $this->vars[0][$this->var_prefix . 'sizes_param'] . '" ';
                        }
                        // Now build the tag
                        $return = '<img ' . $lazy_string . ' src="' . $the_output_url . '" data-bglzy="' . $this->vars[0]['lazy_image'] . '" '. $return . $this->return . '>';

                    } else {
                        // We are doing the javascript version ... 
                        // 1) Build <img> tag
                        if ($this->flags->srcset) {
                            $return = ' data-ji-srcset="' . $this->vars[0][$this->var_prefix . 'srcset_param'] . '" sizes="' . $this->vars[0][$this->var_prefix . 'sizes_param'] . '" ';
                        }
                        $return = '<img ' . $lazy_string . 'src="' . $this->placeholder->return_url . '" data-ji-src="' . $the_output_url . '" ' . $return . $this->return . '>';

                        // 2) If we are doing progressive enhancement add the noscript alternative tag
                        if ($this->settings['img_cp_lazy_progressive_enhancement']) {
                            $return_ns = '';
                            $return .= '<noscript class="ji__progenhlazyns"><img src="' . $this->image_utilities->get_image_path_prefix() . trim($this->ident->cache_path,'/') . $this->ident->output . '.jpg' . '" ' . $return_ns . $this->return . '></noscript>';
                        }
                    }
                } elseif (($this->params->lazy && substr(strtolower($this->params->lazy), 0, 1) == 'h') || (!$this->params->lazy && $this->settings['img_cp_enable_lazy_loading'] == 'y' && $this->settings['img_cp_lazy_loading_mode'] == 'html5') && !$this->flags->svg) {
                    // We are doing html5 only lazy loading option
                    if ($this->flags->srcset) {
                        $return = ' srcset="' . $this->vars[0][$this->var_prefix . 'srcset_param'] . '" sizes="' . $this->vars[0][$this->var_prefix . 'sizes_param'] . '" ';
                    }
                    $return = '<img ' . $lazy_string . 'src="' . $the_output_url . '" ' . $return . $this->return . '>';
                }
            } else {
                // We are not doing lazy loading
                if ($this->flags->srcset) {
                    $return = ' srcset="' . $this->vars[0][$this->var_prefix . 'srcset_param'] . '" sizes="' . $this->vars[0][$this->var_prefix . 'sizes_param'] . '" ';
                }
                // Get an image path - which depends on if we are doing prefixes
                $return = '<img src="' . $the_output_url . '" ' . $return . $this->return . '>';
            }
            $this->return = $return;
        }
        // Write to log
        $this->utilities->debug_message(sprintf(lang('jcogs_img_generated_output'), microtime(true) - $time_start));
        return true;
    }

    /**
     * Save the image
     *
     * @return bool
     */
    public function save($adapter = null)
    {
        // Save the image to the processed image cache
        $return = $this->image_utilities->save($this, $adapter);
        if (!$return) {
            $this->utilities->debug_message(lang('jcogs_img_save_error'));
        }
        return $return;
    }

    /**
     * Add a border to an image
     *
     * @param  integer $count
     * @return bool
     */
    private function _add_border()
    {

        if ($this->flags->using_cache_copy || $this->flags->masked_image || !$this->params->border) {
            // if using cache copy or it is a masked image or we have no border specified, return
            return true;
        }

        // Unpack parameters
        $border = $this->_unpack_border_params($this->params->border, $this->new_width, $this->params->bg_color);
        if (!$border) {
            // Something went wrong retrieving border parameters
            return false;
        }

        // Add border filter to transformation queue
        $this->transformation->add(new Filters\Box_border($border['width'], $border['colour']), $this->stats->transformation_count++);

        return true;
    }

    /**
     * Add border to image that has been masked using drawing methods
     *
     * @return bool
     */
    private function _add_masked_image_border(): bool
    {
        // If using cache copy, border param not set, or not a masked image, return early
        if ($this->flags->using_cache_copy || !$this->params->border || !$this->flags->masked_image) {
            return true;
        }

        // Unpack parameters
        $border = $this->_unpack_border_params($this->params->border, $this->new_width, $this->params->bg_color);
        if (!$border) {
            // Something went wrong retrieving border parameters
            return false;
        }

        // Add border filter to transformation queue
        $this->transformation->add(
            new Filters\Mask_border_drawn($border['width'], $border['colour']),
            $this->stats->transformation_count++
        );

        return true;
    }

    /**
     * Add rounded corners to image
     *
     * @return bool
     */
    private function _add_rounded_corners(): bool
    {
        // If using cache copy or no corners specified, return early
        if ($this->flags->using_cache_copy || !$this->params->rounded_corners) {
            return true;
        }

        // Unpack parameters
        $rounded_corners_params = explode('|', $this->params->rounded_corners);
        $this->utilities->debug_message(lang('jcogs_img_adding_rounded_corners'), $rounded_corners_params);

        // Initialise values for each corner
        $rounded_corner_working = [
            'tl' => ['x' => 0, 'y' => 0, 'radius' => 0],
            'tr' => ['x' => $this->new_width, 'y' => 0, 'radius' => 0],
            'bl' => ['x' => 0, 'y' => $this->new_height, 'radius' => 0],
            'br' => ['x' => $this->new_width, 'y' => $this->new_height, 'radius' => 0],
        ];

        $need_to_do_corners = false;

        // Load parameters into working array
        foreach ($rounded_corners_params as $corner_param) {
            $params = explode(',', $corner_param);
            $corner = strtolower($params[0] ?? '');
            if (in_array($corner, ['tl', 'tr', 'bl', 'br', 'all'])) {
                $radius = isset($params[1]) ? $this->image_utilities->validate_dimension($params[1], $this->new_width) : 0;
                if ($radius !== false) {
                    $need_to_do_corners = true;
                    if ($corner === 'all') {
                        foreach ($rounded_corner_working as &$data) {
                            $data['radius'] = $radius;
                        }
                    } else {
                        $rounded_corner_working[$corner]['radius'] = $radius;
                    }
                } else {
                    $this->utilities->debug_message(lang('jcogs_img_rounded_corners_invalid_radius'), $params[1]);
                }
            } else {
                $this->utilities->debug_message(lang('jcogs_img_rounded_corners_unknown_option'), $params[0]);
            }
        }

        // Do we have to do anything?
        if (!$need_to_do_corners) {
            return true;
        }

        // Add rounded corner filter to transformation queue
        $this->transformation->add(new Filters\Rounded_corners($rounded_corner_working), $this->stats->transformation_count++);

        // See if we are doing borders
        $this->flags->masked_image = true;
        $this->_add_masked_image_border();

        return true;
    }

    /**
     * Add a text-overlay to an image
     *
     * @return bool
     */
    public function _add_text_overlay()
    {
        if ($this->flags->using_cache_copy || !$this->params->text) {
            // if using cache copy or we have no watermark specified, return
            return true;
        }

        // If we have some params run Text Overlay filter
        // ===========================================
        if ($this->params->text) {
            $this->utilities->debug_message(lang('jcogs_img_adding_text_overlay'), $this->params->text);
            // Add text_overlay filter to transformation queue
            $this->transformation->add(new Filters\Text_overlay($this->params->text), $this->stats->transformation_count++);
        }
        return true;
    }

    /**
     * Add a watermark to an image
     *
     * @return bool
     */
    public function _add_watermark(): bool
    {
        // If using cache copy or no watermark specified, return early
        if ($this->flags->using_cache_copy || !$this->params->watermark) {
            return true;
        }

        // Debug message for attempting to add watermark
        $this->utilities->debug_message(lang('jcogs_img_attempting_to_add_watermark'), $this->params->watermark);

        // Unpack watermark parameters
        $watermark_params_raw = explode('|', $this->params->watermark);
        if (!is_array($watermark_params_raw)) {
            $this->utilities->debug_message(lang('jcogs_img_invalid_watermark_parameter'), $this->params->watermark);
            return true;
        }

        // Get a local copy of the watermark image
       
        $the_image = $this->image_utilities->get_a_local_copy_of_image($watermark_params_raw[0]);
        if (!$the_image || !is_array($the_image)) {
            $this->utilities->debug_message(lang('jcogs_img_watermark_source_not_valid'), $watermark_params_raw[0]);
            return true;
        }

        // Check if the image is a sanitized SVG
        if ($this->image_utilities->detect_sanitize_svg($the_image['image_source'])) {
            $this->utilities->debug_message(lang('jcogs_img_cannot_use_svg_as_watermark'), $watermark_params_raw[0]);
            return true;
        }

        // Debug message for adding watermark
        $this->utilities->debug_message(lang('jcogs_img_adding_watermark'), $watermark_params_raw);

        // Add watermark filter to transformation queue
        $this->transformation->add(new Filters\Watermark($watermark_params_raw), $this->stats->transformation_count++);

        return true;
    }

    /**
     * Utility function: Applies filters to an image
     * Filters in array $this->filters and are applied
     * in the sequence they appear in the array.
     *
     * @return bool
     */
    private function _apply_filters()
    {
        // Do we have any filters specified?
        if ($this->flags->using_cache_copy || is_null($this->params->filter)) {
            return true;
        }

        // List of valid filter options
        // key=parameter name => value=[intervention method name, default value]
        $valid_filters = [
            'auto_sharpen' => ['auto_sharpen', null],
            'blur' => ['blur', 1],
            'brightness' => ['brightness', 0],
            'colorize' => ['colorize', 0, 0, 0],
            'contrast' => ['contrast', 0],
            'dominant_color' => ['dominant_color', 10],
            'dot' => ['dot', 6, '', 'circle', 1],
            'edgedetect' => ['edgedetect', null],
            'emboss' => ['emboss', null],
            'emboss_color' => ['emboss_color', null],
            'face_detect' => ['face_detect', 'y'],
            'gaussian_blur' => ['blur', 1],
            'grayscale' => ['grayscale', null],
            'greyscale' => ['grayscale', null],
            'invert' => ['negation', null],
            'lqip' => ['lqip', null],
            'mask' => ['mask', null],
            'mean_removal' => ['mean_removal', null],
            'negate' => ['negation', null],
            'noise' => ['noise', 30],
            'opacity' => ['opacity', 100],
            'pixelate' => ['pixelate', 0, false],
            'replace_colors' => ['replace_colors', null],
            'scatter' => ['scatter', 3],
            'selective_blur' => ['selective_blur', 1],
            'sepia' => ['sepia', 'fast'],
            'sharpen' => ['sharpen', null],
            'smooth' => ['smooth', 1],
            'sobel_edgify' => ['sobel_edgify', null],
        ];

        // Unpack the filters provided to an array called $this->filters
        // of form $key = filter name, $value = option string.
        $filters_from_tag = explode('|', $this->params->filter);

        // Check each temp filter: if it is valid load default name and temp parameters into $this->filters as an array
        foreach ($filters_from_tag as $filter) {
            $this_filter = explode(',', $filter);
            // Get name from array (first value) - what is left are any settings for the filter
            $this_filter_name = array_shift($this_filter);
            if (array_key_exists($this_filter_name, $valid_filters)) {
                // Get filter and parameters
                $default_filter = $valid_filters[$this_filter_name];
                // Use correct name of filter for intervention
                $this_filter_name = array_shift($default_filter);
                // What's left in this_filter are the requested settings (check to ensure it is an array)
                $this_filter = is_array($this_filter) ? $this_filter : [];
                // What's left in default filter are the default settings (check to ensure it is an array)
                $default_filter = is_array($default_filter) ? $default_filter : [];
                // if there are filter settings required, merge given params with defaults
                // we cannot use array_merge here because the values in arrays have no keys
                if ($required_params = count($default_filter)) {
                    for ($i = 0; $i < $required_params; $i++) {
                        // If filter setting doesn't exist or is outside valid range use default
                        if (!isset($this_filter[$i])) {
                            $this_filter[$i] = $default_filter[$i];
                        }
                        // Noise - Values must be in range 0 -> 255
                        if (in_array($this_filter_name, ['noise'])) {
                            $this_filter[$i] = $this_filter[$i] > 255 ? 255 : $this_filter[$i];
                            $this_filter[$i] = $this_filter[$i] < 0 ? 0 : $this_filter[$i];
                        }
                        // Brightness / Colorize - Values must be in range -255 -> 255
                        if (in_array($this_filter_name, ['brightness', 'colorize'])) {
                            $this_filter[$i] = $this_filter[$i] > 255 ? 255 : $this_filter[$i];
                            $this_filter[$i] = $this_filter[$i] < -255 ? -255 : $this_filter[$i];
                        }
                        // Contrast - Values must be in range -100 -> 100, and inverted
                        if (in_array($this_filter_name, ['contrast'])) {
                            $this_filter[$i] = $this_filter[$i] > 100 ? 100 : $this_filter[$i];
                            $this_filter[$i] = $this_filter[$i] < -100 ? -100 : $this_filter[$i];
                            $this_filter[$i] = -$this_filter[$i];
                        }
                        // Blur / Opacity - values must be in range 0->100
                        if (in_array($this_filter_name, ['opacity', 'blur'])) {
                            $this_filter[$i] = $this_filter[$i] > 100 ? 100 : $this_filter[$i];
                            $this_filter[$i] = $this_filter[$i] < 0 ? 0 : $this_filter[$i];
                        }
                        // Sharpen - values must be in range 0->500
                        if (in_array($this_filter_name, ['sharpen'])) {
                            $this_filter[$i] = $this_filter[$i] > 500 ? 500 : $this_filter[$i];
                            $this_filter[$i] = $this_filter[$i] < 0 ? 0 : $this_filter[$i];
                        }
                        // Pixelate - values must be positive integers
                        if (in_array($this_filter_name, ['pixelate'])) {
                            $this_filter[$i] = $this_filter[$i] < 0 ? 0 : $this_filter[$i];
                        }
                        // Sepia - value must be 'fast' or 'slow'
                        if (in_array($this_filter_name, ['sepia'])) {
                            $this_filter[$i] = in_array($this_filter[$i], ['fast', 'slow']) ? $this_filter[$i] : $default_filter[$i];
                        }
                        // Check valid parameters for Scatter
                        if (in_array($this_filter_name, ['scatter'])) {
                            // We need filter[0] and filter[1] to be defined to proceed
                            // If filter[1] not specified set it to double the value for filter[0]
                            if (!isset($this_filter[0])) {
                                $this_filter[0] = $default_filter[0];
                            }
                            if (!isset($this_filter[1])) {
                                $this_filter[1] = $this_filter[0] * 2;
                            }
                            // filter 0 should be less than filter 1
                            if ($this_filter[0] < $this_filter[1]) {
                                // we are good to go... so bale
                                break;
                            } else {
                                // force filter[0] to be less than filter[1] - choose nearest integer to half value of filter[1] (round up)
                                $this_filter[0] = (int) round($this_filter[1] / 2, 0);
                                break;
                            }
                        }
                    }
                }
                // Write the adjusted values into filter stack
                $this->filters[$this_filter_name] = $this_filter;
            }
        }

        // Process the filters selected
        $this->_applyFilters();

        return true;
    }

    /**
     * Applies image filters to the current object's image
     *
     * @return void
     */
    private function _applyFilters()
    {
        if ($this->filters) {
            // Process the filters selected
            foreach ($this->filters as $filter => $filter_settings) {
                $these_settings = '';
                if (strlen(implode(',', $filter_settings)) > 0) {
                    $these_settings = lang('jcogs_img_filtering_image_params') . implode(',', $filter_settings);
                }
                $this->utilities->debug_message(lang('jcogs_img_filtering_image_start'), $filter . $these_settings);
                if (in_array($filter, ['auto_sharpen', 'blur', 'brightness', 'colorize', 'contrast', 'dominant_color', 'dot', 'edgedetect', 'emboss', 'face_detect', 'grayscale', 'lqip', 'opacity', 'mask', 'mean_removal', 'negation', 'noise', 'pixelate', 'replace_colors', 'scatter', 'selective_blur', 'sepia', 'sharpen', 'smooth', 'sobel_edgify'])) {
                    // process local filters
                    switch ($filter) {
                        case 'auto_sharpen':
                            // Applies a variable degree of sharpening depending on amount of image size
                            // reduction applied during processing. This method uses intervention's built in
                            // sharpen filter - which 
                            // Sharpen array - new/orig width ratio -> sharpen value
                            $max_sharpen_values = [
                                '0.04' => 9,
                                '0.06' => 8,
                                '0.12' => 7,
                                '0.16' => 6,
                                '0.25' => 5,
                                '0.50' => 4,
                                '0.75' => 3,
                                '0.85' => 3,
                                '0.95' => 2,
                                '1.00' => 1,
                            ];
                            $sharpening_value = $this->utilities->vlookup(max(min($this->new_width / $this->orig_width, 1), 0), $max_sharpen_values);
                            // If we get 'false' back from fast_nearest we couldn't get a match, so bale
                            if ($sharpening_value) {
                                // We didn't get 'false' so go ahead and apply the filter... 
                                // Also bracket value to be between 1 and 9 just in case...
                                $this->transformation->add(new Filters\Sharpen($sharpening_value), $this->stats->transformation_count++);
                            }
                            break;

                        case 'brightness':
                            // Add brightness filter to transformation queue
                            // Get brightness value
                            $brightness = isset($filter_settings[0]) ? $filter_settings[0] : 0;
                            // Scale brightness value to lie within 100/-100 range
                            $brightness = (int) round($brightness / 255 * 100, 0);
                            $this->transformation->add(new Filters\Brightness((int) $brightness), $this->stats->transformation_count++);
                            break;

                        case 'colorize':
                            // Add contrast filter to transformation queue
                            $this->transformation->add(new Filters\Colorize($filter_settings), $this->stats->transformation_count++);
                            break;

                        case 'contrast':
                            // Add contrast filter to transformation queue
                            $this->transformation->add(new Filters\Contrast(...$filter_settings), $this->stats->transformation_count++);
                            break;

                        case 'dominant_color':
                            // Add dominant color filter to transformation queue
                            $this->transformation->add(new Filters\Dominant_color(...$filter_settings), $this->stats->transformation_count++);
                            break;

                        case 'dot':
                            // Convert colour string (if specified) to color object
                            $filter_settings[1] = $filter_settings[1] ? $this->image_utilities->validate_colour_string($filter_settings[1]) : null;
                            // Add dot half-tone filter filter to transformation queue
                            $this->transformation->add(new Filters\Dot_filter(...$filter_settings), $this->stats->transformation_count++);
                            break;

                        case 'edgedetect':
                            // Add edgedetect filter to transformation queue
                            $this->transformation->add(new Filters\Edgedetect(), $this->stats->transformation_count++);
                            break;

                        case 'emboss':
                            // Add emboss filter to transformation queue
                            $this->transformation->add(new Filters\Emboss(), $this->stats->transformation_count++);
                            break;

                        case 'face_detect':
                            // Add face detection filter to transformation queue
                            // Use sensitvity value from within tag, otherwise use value of param.
                            $sensitivity = intval($this->_unpack_param('face_detect_sensitivity'));
                            $show_rectangles = !isset($filter_settings[0]) || isset($filter_settings[0]) && strtolower(substr($filter_settings[0], 0, 1) == 'y') ? true : false;
                            $faces = !$this->faces || is_null($this->faces) ? [] : $this->faces;

                            $this->transformation->add(new Filters\Face_detect($sensitivity, $show_rectangles, $faces), $this->stats->transformation_count++);
                            break;

                        case 'lqip':
                            // Add lqip filter to transformation queue
                            $this->transformation->add(new Filters\Lqip(), $this->stats->transformation_count++);
                            break;

                        case 'grayscale':
                            // Add grayscale filter to transformation queue
                            $this->transformation->add(new Filters\Greyscale(), $this->stats->transformation_count++);
                            break;

                        case 'mask':
                            // Do we set transparency flag?
                            $this->flags->masked_image = in_array($this->params->save_as, ['png', 'webp']);

                            // Add mask filter to transformation queue
                            $this->transformation->add(new Filters\Mask($filter_settings), $this->stats->transformation_count++);

                            // See if we are doing borders
                            $this->_add_masked_image_border();

                            break;

                        case 'mean_removal':
                            // Add mean_removal filter to transformation queue
                            $this->transformation->add(new Filters\Mean_removal(), $this->stats->transformation_count++);
                            break;

                        case 'negation':
                            // Add negation filter to transformation queue
                            $this->transformation->add(new Filter\Advanced\Negation(), $this->stats->transformation_count++);
                            break;

                        case 'noise':
                            // Add noise filter to transformation queue
                            $this->transformation->add(new Filters\Add_noise(intval($filter_settings[0])), $this->stats->transformation_count++);
                            break;

                        case 'opacity':
                            // Adjust the opacity of image
                            $given_opacity = isset($filter_settings[0]) ? abs(intval($filter_settings[0])) : 100;
                            $this->transformation->add(new Filters\Opacity($given_opacity), $this->stats->transformation_count++);
                            break;

                        case 'pixelate':
                            // Pixelate an image
                            $this->transformation->add(new Filters\Pixelate(...$filter_settings), $this->stats->transformation_count++);
                            break;

                        case 'replace_colors':
                            // Replace one colour in an image with another
                            // Require min 2 parameters - from color, to colour, tolerance
                            $from_color = isset($filter_settings[0]) ? $this->image_utilities->validate_colour_string($filter_settings[0]) : null;
                            $to_color = isset($filter_settings[1]) ? $this->image_utilities->validate_colour_string($filter_settings[1]) : null;
                            $tolerance = isset($filter_settings[2]) ? $filter_settings[2] : 0;
                            if ($from_color && $to_color) {
                                $this->transformation->add(new Filters\Replace_colors($from_color, $to_color, $tolerance), $this->stats->transformation_count++);
                            }
                            break;

                        case 'scatter':
                            // Create a scattered version of an image
                            $this->transformation->add(new Filters\Scatter(...$filter_settings), $this->stats->transformation_count++);
                            break;

                        case 'selective_blur':
                            // Apply the selective blur filter to the image
                            $this->transformation->add(new Filters\Selective_blur(), $this->stats->transformation_count++);
                            break;

                        case 'sepia':
                            // Two algorithms
                            // Two step process - shift to greyscale and then colorize
                            // From here: https://www.phpied.com/image-fun-with-php-part-2/
                            // Or pixel based method (the one used by CE Image it seems)
                            // From here: https://dyclassroom.com/image-processing-project/how-to-convert-a-color-image-into-sepia-image
                            if ($filter_settings[0] == 'fast') {
                                $this->transformation->add(new Filters\Sepia_fast(), $this->stats->transformation_count++);
                            } else {
                                $this->transformation->add(new Filters\Sepia_slow(), $this->stats->transformation_count++);
                            }
                            break;

                        case 'sharpen':
                            // uses unsharp mask rather than imagine filter
                            // Amount
                            $amount = isset($filter_settings[0]) && intval($filter_settings[0]) && $filter_settings[0] > 0 ? intval($filter_settings[0]) : 80;
                            // radius
                            $radius = isset($filter_settings[1]) && is_numeric($filter_settings[1]) && $filter_settings[1] > 0 ? floatval($filter_settings[1]) : 0.5;
                            // threshold
                            $threshold = isset($filter_settings[2]) && intval($filter_settings[2]) && $filter_settings[2] > 0 ? intval($filter_settings[2]) : 3;
                            // Add unsharp mask filter to transformation queue
                            $this->transformation->add(new Filters\Unsharp_mask($amount, $radius, $threshold), $this->stats->transformation_count++);
                            break;

                        case 'smooth':
                            // Smooth an image
                            $this->transformation->add(new Filters\Smooth(...$filter_settings), $this->stats->transformation_count++);
                            break;

                        case 'sobel_edgify':
                            // Get threshold value if one set
                            $threshold = isset($filter_settings[0]) && intval($filter_settings[0]) && $filter_settings[0] > 0 ? intval($filter_settings[0]) : 125;
                            $this->transformation->add(new Filters\Sobel($threshold), $this->stats->transformation_count++);
                            unset($threshold);
                    }
                } elseif (str_contains($filter, 'IMG_FILTER') !== false) {
                    // we've got a GD imagefilter filter
                    $this->transformation->add(new Filters\Gd\Apply_Gd_Filter($filter, $filter_settings), $this->stats->transformation_count++);
                } else {
                    // something bad happened... 
                    $this->utilities->debug_message(lang('jcogs_img_imagefilter_failed'), [$filter => $filter_settings]);
                }
            }
        }
    }

    /**
     * Generates the filename to use for the image based on various parameters and settings.
     * 
     * This method constructs a filename by considering the original filename, 
     * any specified prefixes or suffixes, and various image transformation parameters.
     * It also ensures the filename is unique and safe for use in different environments.
     *
     * @return string
     */
    private function _build_filename(?string $filename = null, ?object $params = null, bool $using_fallback = false): string
    {
        // Make sure we have a filename and some params, or add in substitute values
        if (empty($filename) && property_exists($params, 'src')) {
            // No filename, but have some params, so use a hash of the src as the filename
            $filename = hash('tiger160,3', str_replace('%', 'pct', urlencode($params->src)));
        }

        if(empty($filename) && empty($params)) {
            // We have no filename and no params, so create some
            $filename = 'no_filename' . strval(random_int(1, 999));
            $params = new \stdClass();
        }

        // If prefix / suffix or filename specified, update filename accordingly
        if (property_exists($params, 'filename') && $params->filename) {
            $filename = $params->filename;
        }
        if (property_exists($params, 'filename_prefix') && $params->filename_prefix) {
            $filename = $params->filename_prefix . $filename;
        }
        if (property_exists($params, 'filename_suffix') && $params->filename_suffix) {
            $filename = $filename . $params->filename_suffix;
        }

        // Urldecode the filename
        $filename = urldecode($filename);

        // Remove HTML Reserved characters (Issue #449) - https://www.html.am/reference/html-special-characters.cfm
        $special_chars = ['\'', '<', '>','&', '/', '\\', '?', '%', '*', ':', '|', '"', '<', '>', '!', '@', '#', '$', '^', '(', ')', '[', ']', '{', '}', ';', ':', ',', '.', '`', '~', '+', '=', ' ', ' '];
        $filename = str_replace($special_chars, '_', $filename);
        
        // And remove % characters (Issue #284 - Ligtas Server wierdness)
        $filename = str_replace('%', '_', $filename);

        // Now lowercase the whole lot to avoid issues on case sensitive servers
        $filename = strtolower(string: $filename);


        // Just in case, if need be shorten base filename to something sensible but still unique
        if (strlen($filename) >= $this->settings['img_cp_default_max_source_filename_length']) {
            try {
                $filename = trim(substr($filename, 0, $this->settings['img_cp_default_max_source_filename_length']) . strval(random_int(1, 999)));
            } catch (\Exception $e) {
                $filename = trim(substr($filename, 0, $this->settings['img_cp_default_max_source_filename_length']) . strval(mt_rand(1, 999)));
            }
        }

        $options = array();
        // Build up string of all methods applied to image and their parameters
        foreach ($params as $param => $value) {
            // Only include parameters that affect image itself
            if (array_key_exists($param, $this->image_utilities::$transformational_params)) {
                $options[$param] = $value;
            }
        }

        // Now add bg_color without Imagine Palette object
        // Check first to see if it is an object, and if not turn it into one...
        if (property_exists($params, 'bg_color')) {
            $the_colour = $params->bg_color;
            if(is_string($the_colour)) {
                $the_colour = $this->image_utilities->validate_colour_string($the_colour);
            }
            $options['bg_color'] = $the_colour->getRed() . $the_colour->getGreen() . $the_colour->getBlue() . $the_colour->getAlpha();
        }

        // If option enabled, add in the source path for the image
        if(strtolower(substr($this->settings['img_cp_include_source_in_filename_hash'], 0, 1)) == 'y') {
            $options['src'] = $params->src;
        }

        // Add an element to hash so we differentitated between valid and demo mode... 
        $options['license_mode'] = $this->settings['jcogs_license_mode'];

        // If we are using a fallback image, add that to the hash
        if($using_fallback) {
            $options['fallback'] = 'true';
        }

        // Build a hex equivalent of the cache time set for this image
        $cache_tag = is_numeric($params->cache) && $params->cache > -1 ? dechex($params->cache) : 'abcdef';

        // If requested, hash the filename
        if (property_exists($params, 'hash_filename') && strtolower(substr($params->hash_filename, 0, 1)) == 'y') {
            $filename = hash('tiger160,3', serialize($filename));
        }
        $options_string = implode($options);
        // Hash the methods string
        $hash_filename = hash('tiger160,3', $options_string);

        // Return the completed filename
        return $filename 
            . $this->settings['img_cp_default_filename_separator'] 
            . $cache_tag 
            . $this->settings['img_cp_default_filename_separator'] 
            . $hash_filename;
    }

    /**
     * Create a JavaScript lazy loading image
     *
     * This method generates a JPEG copy of the processed image for use in JavaScript-based lazy loading.
     * It ensures that the image is properly processed and saved in the cache.
     *
     * @param object $working_image The content object containing parameters and settings for the image.
     * @return bool Returns true if the JPEG copy is successfully created, false otherwise.
     */
    private function _create_js_lazy_loading_image(object $working_image): bool
    {
        if (str_starts_with($working_image->params->lazy, 'js_') && $working_image->params->save_as != 'jpg' && !$this->image_utilities->is_image_in_cache($working_image->ident->cache_path . $working_image->ident->output . '.jpg')) {
            $this->utilities->debug_message(lang('jcogs_img_lqip_making_jpg_copy'));
            if (empty($working_image->processed_image) || is_null($working_image->processed_image)) {
                $this->utilities->debug_message(lang('jcogs_img_lazy_processing_error'));
                return true;
            }
            try {
                $pe_image_size = $working_image->processed_image->getSize();
                $pe_color = is_string($this->params->bg_color) ? $this->image_utilities->validate_colour_string($this->params->bg_color) : $this->params->bg_color;
                $pe_image = (new Imagine())->create(new Box($pe_image_size->getWidth(), $pe_image_size->getHeight()), $pe_color);
            } catch (\Exception $e) {
                $this->utilities->debug_message(lang('jcogs_img_imagine_error'), $e->getMessage());
                return false;
            }
            try {
                $pe_image->paste($working_image->processed_image, new PointSigned(0, 0));
            } catch (\Imagine\Exception\RuntimeException $e) {
                $this->utilities->debug_message(lang('jcogs_img_imagine_error'), $e->getMessage());
                return false;
            }
            $save_image = $pe_image->get('jpg');
            $result = $this->image_utilities->write($working_image->ident->cache_path . $this->placeholder->output . '.jpg', $save_image);
            if (!$result) return false;
            $this->image_utilities->update_cache_log(image_path: $working_image->ident->cache_path . $this->placeholder->output . '.jpg', processing_time: microtime(true) - self::$start_time, cache_dir: $working_image->params->cache_dir, vars: $working_image->vars, source_path: $working_image->vars[0]['orig'], using_cache_copy: $this->flags->using_cache_copy);
            return true;
        }
        return false;
    }

    /**
     * Create the lazy loading placeholder image
     *
     * This method generates a placeholder image for lazy loading. It clones the current
     * image object, applies necessary filters, and saves the processed image as a placeholder.
     * The placeholder image is used until the actual image is fully loaded.
     *
     * @return string|bool Returns true if the placeholder image is successfully created, false otherwise.
     */
    private function _create_lazy_placeholder_image(object $working_image): string|bool
    {
        // Manually create a deep copy of the $working_image object with only the necessary properties
        $working_image_copy = new self(false);
        $working_image_copy->flags = clone $working_image->flags;
        $working_image_copy->local_path = $working_image->local_path;
        // Do we have a copy of image already loaded?
        if(!isset($working_image->processed_image)) {
            // No image loaded, so get a copy
            try {
                $working_image_copy->processed_image = (new Imagine())->load($this->image_utilities->read($working_image->local_path));
            } catch (\Imagine\Exception\RuntimeException $e) {
                // Creation of image failed.
                $this->utilities->debug_message(lang('jcogs_img_imagine_error'), $e->getMessage());
                return false;
            }
        } else {
            $working_image_copy->processed_image = clone $working_image->processed_image;
        }
        $working_image_copy->params = clone $working_image->params;
        $working_image_copy->transformation = clone $working_image->transformation;
        $working_image_copy->placeholder = clone $working_image->placeholder;
        $working_image_copy->vars = $working_image->vars;
        $working_image_copy->ident = clone $working_image->ident;
        $working_image_copy->params->filter = str_replace('js_', '', $this->params->lazy_type);
        // $working_image_copy->transformation = new Filter\Transformation(null);
        $working_image_copy->_apply_filters();
        // $working_image_copy->processed_image = $working_image_copy->transformation->apply($working_image_copy->processed_image);
        $working_image_copy->params->quality = $this->_set_lqip_image_quality_options($working_image_copy->params->save_as);
        $working_image_copy->local_path = $this->placeholder->local_path;
        $this->image_utilities->save($working_image_copy);
        $this->image_utilities->update_cache_log(image_path: $working_image_copy->local_path, processing_time: microtime(true) - self::$start_time, cache_dir: $working_image_copy->params->cache_dir, vars: $working_image_copy->vars, source_path: $working_image_copy->vars[0]['orig'], using_cache_copy: $this->flags->using_cache_copy);
        // Now save a jpg version for other lazy loading options
        if($working_image_copy->params->save_as != 'jpg') {
            $working_image_copy->params->save_as = 'jpg';
            $working_image_copy->local_path = $working_image_copy->ident->cache_path . $this->placeholder->output . '.jpg';
            $this->image_utilities->save($working_image_copy);
            $this->image_utilities->update_cache_log(image_path: $working_image_copy->local_path, processing_time: microtime(true) - self::$start_time, cache_dir: $working_image_copy->params->cache_dir, vars: $working_image_copy->vars, source_path: $working_image_copy->vars[0]['orig'], using_cache_copy: $this->flags->using_cache_copy);
        }

        $return = $working_image_copy->placeholder->return_url;
        unset($working_image_copy);
        return $return; 
    }

    /**
     * Enables caching for the specified image.
     *
     * Checks if the provided source path refers to a local file and updates the original image path accordingly.
     * Sets the image as valid if the source is appropriate. For SVG images, updates the save type, local path,
     * and save path to handle SVG-specific processing.
     *
     * @param string|null $src       The source file path or URL of the image. If null, no source is set.
     * @param string|null $local_path The local filesystem path to the processed image. If null, no local path is set.
     * @return bool Returns true if the cache copy is enabled and image is valid; false otherwise.
     */
    private function _enable_cache_copy(?string $original_source_path = null, ?string $local_cache_path = null, ?array $cached_vars = null): bool
    {
        if (empty($local_cache_path) || empty($cached_vars)) {
            $this->utilities->debug_message(lang('jcogs_img_enable_cache_copy_missing_data'));
            return false;
        }

        $var_prefix = $this->var_prefix;

        // Populate JcogsImage properties from $cached_vars
        $this->ident->orig_image_path = $cached_vars[$var_prefix . 'orig'] ?? $original_source_path;
        $this->local_path = $local_cache_path;
        $this->save_path = rtrim($this->utilities->path($this->local_path), '/');

        $this->ident->output = $cached_vars[$var_prefix . 'filename_output'] ?? pathinfo($local_cache_path, PATHINFO_FILENAME);
        
        // Critical: Ensure params->save_as is correctly set from cache, as it affects extension and MIME type
        $this->params->save_as = $cached_vars[$var_prefix . 'save_as'] ?? pathinfo($local_cache_path, PATHINFO_EXTENSION);
        $this->params->save_type = $this->params->save_as; // Keep save_type consistent

        $this->orig_width = (int)($cached_vars[$var_prefix . 'orig_width'] ?? 0);
        $this->orig_height = (int)($cached_vars[$var_prefix . 'orig_height'] ?? 0);
        $this->new_width = (int)($cached_vars[$var_prefix . 'width'] ?? $this->orig_width); // Fallback to orig if 'width' not set
        $this->new_height = (int)($cached_vars[$var_prefix . 'height'] ?? $this->orig_height); // Fallback to orig if 'height' not set
        
        $this->ident->width = $this->new_width; 
        $this->ident->height = $this->new_height;

        $this->ident->mime_type = $cached_vars[$var_prefix . 'mime'] ?? $this->image_utilities->get_mime_type($this->params->save_as);
        $this->ident->orig_filename = $cached_vars[$var_prefix . 'orig_filename'] ?? pathinfo($this->ident->orig_image_path ?? 'unknown', PATHINFO_FILENAME);
        $this->ident->orig_extension = $cached_vars[$var_prefix . 'orig_ext'] ?? pathinfo($this->ident->orig_image_path ?? 'unknown', PATHINFO_EXTENSION);
        $this->filesize = (int)($cached_vars[$var_prefix.'filesize'] ?? 0);
        $this->orig_filesize = (int)($cached_vars[$var_prefix.'orig_filesize'] ?? 0);


        $this->flags->svg = (strtolower($this->params->save_as) === 'svg');
        $this->flags->animated_gif = $cached_vars[$var_prefix . 'is_animated_gif'] ?? false;
        $this->flags->png = (strtolower($this->params->save_as) === 'png');

        $this->flags->valid_image = true; 
        $this->flags->using_cache_copy = true; 

        // Populate $this->vars[0] directly. This is crucial so that we can do post-processing of the cached image.
        $this->vars[0] = $cached_vars;

        // We're done here, so return true
        return true;
    }

    /**
     * Work out if we have default fallback image option to apply
     *
     * @return bool
     */
    private function _evaluate_default_image_options()
    {
        // reset src parameter - if we get to hear we've been unable to retrieve what was there before
        $this->params->src = null;
        // set an activity marker
        $found_fallback_option = false;
        if (
            (
                strtolower(substr($this->settings['img_cp_enable_default_fallback_image'], 0, 1)) == 'y' &&
                strtolower(substr($this->settings['img_cp_enable_default_fallback_image'], 1, 1)) == 'c'
            )
        ) {
            // Option 1) A colour fill requested

            // We only can do this if we have some idea how big to make the colour fill, so check that first.
            $width = $this->params->width ? $this->params->width : false;

            if(!$width) {
                $width = $this->params->min_width ? $this->params->min_width : false;
            }

            if(!$width) {
                $width = $this->params->min ? $this->params->min : false;
            }

            $height = $this->params->height ? $this->params->height : false;

            if(!$height) {
                $height = $this->params->min_height ? $this->params->min_height : false;
            }

            if(!$height) {
                $height = $this->params->min ? $this->params->min : false;
            }

            $aspect_ratio = $this->_get_aspect_ratio($this->params->aspect_ratio);

            if (!$width && $height && $aspect_ratio) {
                $width = round($height / $aspect_ratio, 0);
            }

            if (!$height && $width && $aspect_ratio) {
                $height = round($width * $aspect_ratio, 0);
            }

            if (!$width) {
                $width = intval($this->settings['img_cp_default_img_width']);
            }

            if (!$height) {
                $height = intval($this->settings['img_cp_default_img_height']);
            }
            if ($width && $height) {
                $this->params->bg_color = $this->image_utilities->validate_colour_string($this->settings['img_cp_fallback_image_colour']);
                $this->utilities->debug_message(lang('jcogs_img_no_image_supplied_using_colour_field_backup'), $this->settings['img_cp_fallback_image_colour']);
                // Give image fill some arbitary 'original' dimensions
                $this->orig_size = new Box($width, $height);
                $this->orig_width = $this->orig_size->getWidth();
                $this->orig_height = $this->orig_size->getHeight();
                $this->aspect_ratio_orig = $this->orig_height / $this->orig_width;
                $this->flags->allow_scale_larger = true; // We need to do this in case target image size is bigger than our default size.
                $file_info['filename'] = 'colour_field';
                $file_info['extension'] = 'jpg';
                $this->flags->use_colour_fill = true;
                $found_fallback_option = true;
            } else {
                $this->utilities->debug_message(lang('jcogs_img_not_enough_dimensions_for_colour_field'));
            }
        } elseif (
            strtolower(substr($this->settings['img_cp_enable_default_fallback_image'], 0, 1)) == 'y' &&
            strtolower(substr($this->settings['img_cp_enable_default_fallback_image'], 1, 1)) == 'r'
        ) {
            // Option 2) Put in remote default backup image
            $this->params->src = $this->settings['img_cp_fallback_image_remote'];
            $this->utilities->debug_message(lang('jcogs_img_no_image_supplied_using_remote_default'), $this->params->src);
            $found_fallback_option = true;
        } elseif (
            strtolower(substr($this->settings['img_cp_enable_default_fallback_image'], 0, 1)) == 'y' &&
            strtolower(substr($this->settings['img_cp_enable_default_fallback_image'], 1, 1)) == 'l'
        ) {
            // Option 3) Put in local default backup image
            $this->params->src = $this->utilities->parseFiledir($this->settings['img_cp_fallback_image_local']);
            $this->utilities->debug_message(lang('jcogs_img_no_image_supplied_using_local_default'), $this->params->src);
            $found_fallback_option = true;
        }
        if (!$found_fallback_option) {
            $this->utilities->debug_message(lang('jcogs_img_no_image_fallback_option'));
            return false;
        }
        return true;
    }

    /**
     * Utility function: Generates 'act url' if required, otherwise returns unchanged url
     *
     * @param  string $data
     * @param  string $what 
     * @return string
     */
    private function _generate_action_link(?string $data = null, ?string $what = null): string
    {
        // If $this->params-action_link is set, create an ACT url to insert in place of
        // the regular URL.
        if(substr(string: strtolower(string: $this->params->action_link), offset: 0, length: 1) == 'y') {
            // Reset action_link parameter to ensure we don't generate another act url when this is expanded
            $params = $this->params;
            $url_only = $this->params->url_only;
            $this->params->url_only = 'yes';
            $params->action_link = 'no';
            $params->act_what = $what;
            $params->act_path = $data;
            // $params->act_tagdata = ee()->TMPL->tagdata;
            $params->bg_color = $this->image_utilities->validate_colour_string($this->params->bg_color)->__toString();
            // Build our packet
            $act_packet = base64_encode(string: json_encode(value: $params));
            if(!empty($act_packet)) {
                // If empty, something went wrong (probably with json_encoding) so skip this and so return inbound url
                $act_id = $this->utilities->get_action_id('act_originated_image');
                if($act_id) {
                    if(substr(string: strtolower(string: $this->settings['img_cp_append_path_to_action_links']), offset: 0, length: 1) == 'y') {
                        $data = sprintf(lang('jcogs_img_action_url_with_url'), $act_id, $act_packet, urlencode($params->act_path));
                    } else {
                        $data = sprintf(lang('jcogs_img_action_url'), $act_id, $act_packet);
                    }
                }
            }
            $this->params->url_only = $url_only;
            $this->params->action_link = 'yes';
        }
        return $data;
    }

    /**
     * Utility function: Generates 'lazy' images
     *
     * @param  string $type
     * @return string $this->placeholder->path (path to the lazy image file)
     */
    private function _generate_lazy_placeholder_image(?string $mode = null)
    {
        $this->utilities->debug_message(lang('jcogs_img_lqip_started'));

        $this->_initialize_lazy_placeholder($mode ?: $this->params->lazy);
        $this->_set_placeholder_paths();
        
        // Bale if image is gif or svg
        if (in_array($this->params->save_as, ['gif', 'svg'])) {
            $this->utilities->debug_message(lang('jcogs_img_no_lqip_for_gif_svg'));
            $this->placeholder->return_url = '';
            return $this->placeholder->return_url;
        }

        if (!$this->image_utilities->is_image_in_cache($this->placeholder->local_path, true)) {
            if(!$this->_create_lazy_placeholder_image($this)) return false;
        }

        return $this->placeholder->return_url;
    }

    /**
     * Set the image quality options
     *
     * This method configures the quality settings for the image processing.
     * It adjusts the quality parameters based on the provided options to ensure
     * the output image meets the desired quality standards.
     * @param string $save_type The save_type for the image (e.g. jpg, png, webp)
     * @return string
     */
    private function _set_lqip_image_quality_options(string $save_type): string
    {
        $quality = 100;
        if (in_array($save_type, ['jpg', 'jpeg', 'webp'])) {
            $quality = 20;
        } elseif (in_array($save_type, ['png'])) {
            $quality = 1;
        }
        return $quality;
    }

    /**
     * Utility function: Returns the value of the aspect ratio (e.g. Y=X*ratio) 
     * as a ratio based on inputs
     *
     * @param string $input
     * @return bool|float
     */
    private function _get_aspect_ratio($input = '')
    {
        // Reset aspect ratio just in case it already has a value
        $this->aspect_ratio = null;
        // If we didn't get any $input, work out some value to use
        if(empty($input)) {
            // First get aspect ratio of original image
            if (!$this->aspect_ratio_orig) {
                $this->aspect_ratio_orig = $this->orig_height && $this->orig_width ? $this->orig_height / $this->orig_width : null;
            }

            // Do we have a requested aspect ratio, or guess something ... 
            if (!$input = $this->params->aspect_ratio) {
                // We did not get anything, so guess... 
                if ($this->new_width && $this->new_height) {
                    // we have both new_width and new_height so impute from that and return
                    return $this->aspect_ratio = $this->new_height / $this->new_width;
                } else {
                    // return original aspect ratio
                    return $this->aspect_ratio = $this->aspect_ratio_orig;
                }
            }
        }

        // If we still don't have anything, return false
        if (!$input) {
            return false;
        }

        // Is input a valid ratio definition?
        if (!(stripos($input, '_', 1) || stripos($input, '/', 1) || stripos($input, ':', 1))) {
            // not a correctly formed ratio
            $this->utilities->debug_message(lang('jcogs_img_invalid_aspect_ratio'), $input);
            return false;
        }

        // Get the ratio
        preg_match('/^(\d*)(?:_|\/|\:)(\d*)/', $input, $matches, PREG_UNMATCHED_AS_NULL);
        if (is_int(intval($matches[2])) && is_int(intval($matches[1]))) {
            // we have stuff for a ratio
            $this->utilities->debug_message(lang('jcogs_img_aspect_ratio_calc'), $input . ' => ' . $matches[2] / $matches[1]);
            return $matches[2] / $matches[1];
        }
        return false;
    }

    /**
     * Utility function: Get fit dimensions 
     * What are resize dimensions for the source image:
     * fit=cover: original image to reach edge of new shape on all sides
     * fit=contain: original image to be contained within new shape
     * fit not specified or fit=stretch: the image will get distored  (default)
     * 
     * Returns array with dimensions to allow for further adjustment before loading into image object
     *
     * @param  string $fit
     * @return array 
     */
    private function _get_fit_dimensions(string $fit = 'cover')
    {
        $new_width = $this->new_width;
        $new_height = $this->new_height;

        // Ensure aspect_ratio_orig is a valid positive float.
        // If not, it might indicate an issue with original image dimension detection (e.g., problematic SVG).
        // Defaulting to the target aspect_ratio implies no aspect ratio correction for fitting
        // if the original is unknown. If target aspect_ratio is also invalid, default to 1.0 (square).
        if (!isset($this->aspect_ratio_orig) || $this->aspect_ratio_orig <= 0) {
            $this->utilities->debug_message(lang('jcogs_img_invalid_orig_aspect_ratio_for_fit'), $this->aspect_ratio_orig ?? 'null');
            if (isset($this->aspect_ratio) && $this->aspect_ratio > 0) {
                $this->aspect_ratio_orig = $this->aspect_ratio;
                 $this->utilities->debug_message(lang('jcogs_img_orig_aspect_ratio_defaulted_to_target'), $this->aspect_ratio_orig);
            } else {
                $this->aspect_ratio_orig = 1.0; // Default to square if target AR also invalid
                $this->utilities->debug_message(lang('jcogs_img_orig_aspect_ratio_defaulted_to_square'));
            }
        }

        // FIT Calcs
        // =========    

        // We avoid distortion by preserving original image ratio and resizing image to fit
        // in new bounding box specified (by new_width and new_height)

        if ($fit == 'contain' || $fit == 'cover') {
            // We have fit parameter specified

            // Are old and new aspect ratios not the same? If so need to work out what 
            // the right dimensions for new image are
            if ($this->aspect_ratio != $this->aspect_ratio_orig) {
                // aspect ratios are not same, so check for fit
                // Is new width * original aspect ratio (ar height) greater than specified height?
                if ($this->new_width * $this->aspect_ratio_orig > $this->new_height) {
                    // Yes (Y is bounding axis)
                    // Has the fit parameter been specified?
                    if ($fit == 'contain') {
                        // Fit="contain"  - y axis is new value
                        // apply aspect ratio to calculate new_width
                        $new_width = (int) round($this->new_height / $this->aspect_ratio_orig, 0);
                    } else {
                        // Fit="cover"
                        // new_width needs to stay unchanged to fit on X axis, so work out new_height by
                        // Y is bounding axis, so new_height unchanged and work out new_width by
                        // applying original aspect ratio to new_height
                        $new_height = (int) round($this->new_width * $this->aspect_ratio_orig, 0);
                    }
                } else {
                    // No (X is bounding axis)
                    // Has the fit parameter been specified?
                    if ($fit == 'contain') {
                        // Fit="contain"  - x axis is as in new value
                        // X is bounding axis, so new_width unchanged and work out new_height by
                        // applying original aspect ratio to new_width
                        $new_height = (int) round($this->new_width * $this->aspect_ratio_orig, 0);
                    } else {
                        // Fit="cover"
                        // new_height needs to stay unchanged to fit on Y axis, so work out new_width by
                        // applying original aspect ratio to new_height
                        $new_width = (int) round($this->new_height / $this->aspect_ratio_orig, 0);
                    }
                }
            }
        }
        return [$new_width, $new_height];
    }

    /**
     * Calculates the required dimensions for the processed image
     *
     * @return bool
     */
    private function _get_new_image_dimensions()
    // Get the params
    // Create a new image object
    // Apply the appropriate modifications
    // Return the image to template
    {
        if ($this->flags->using_cache_copy) {
            // Don't reprocess if we are using the cache copy
            return true;
        }

        // Step 1 - sort out the required dimensions of the image after the transformations

        // Width / Height
        // Get the requested width / height values (if there are any)
        // Dimensions from the tag
        $this->new_width = $this->image_utilities->validate_dimension($this->_unpack_param('width'), $this->orig_width);
        $this->new_height = $this->image_utilities->validate_dimension($this->_unpack_param('height'), $this->orig_height);

        // Now get an aspect ratio if we don't have one
        if (!$this->aspect_ratio) {
            $this->aspect_ratio = $this->_get_aspect_ratio();
            if(!$this->aspect_ratio) {
                $this->utilities->debug_message(lang('jcogs_img_no_aspect_ratio'));
                // So work it out from default width and height
                $this->aspect_ratio = intval($this->settings['img_cp_default_img_height']) / intval($this->settings['img_cp_default_img_width']);
            }
        }

        // Work out if the srcset parameter requires larger new_width - if allow scale larger set then we make width the largest value specified
        if ($this->flags->allow_scale_larger && $this->params->srcset && !$this->flags->svg) {
            $this->utilities->debug_message(lang('jcogs_img_srcset_and_ASL'));
            $srcset = explode('|', $this->params->srcset);
            $new_width = 0;
            // Now build the srcset entries and images
            foreach ($srcset as $width) {
                $width = $this->image_utilities->validate_dimension($width, $this->orig_width);
                if ($new_width > $width)
                    break;
                $max_width = max($width, $this->new_width);
                $new_width = $width > $this->new_width && $max_width > $this->new_width ? $width : $this->new_width;
            }

            // Adjust image height to match change in image width to preserve aspect ratio
            // To do this we need an aspect ratio ... so if we don't have one, work it out from original image
            if ($new_width > 0) {
                $this->new_height = round($new_width * $this->aspect_ratio, 0);
                $this->new_width = $new_width;
            }
        }

        // Process min/max values to get final dimensions for processed image
        $this->_image_min_max_calcs();

        // Is original image smaller than final size?
        // If it is, and allow_scale_larger is not set to 'yes' then use original image dimensions instead
        if (
            !$this->flags->allow_scale_larger &&
            (($this->new_width && $this->new_width > $this->orig_width && $this->orig_width > 0) || // Add check for orig_width > 0
                ($this->new_height && $this->new_height > $this->orig_height && $this->orig_height > 0)) // Add check for orig_height > 0
        ) {
            // Set the new dimensions to be the same as original dimensions
            $this->new_width = $this->orig_width;
            $this->new_height = $this->orig_height;
            // $this->new_size will be set after FIT Calcs

            $this->utilities->debug_message(lang('jcogs_img_exceeds_source'), ['orig_width' => $this->orig_width, 'orig_height' => $this->orig_height, 'new_width' => $this->new_width, 'new_height' => $this->new_height,]);
        }

        // If we need to work out other dimension using Aspect Ratio
        // =========================================================
        // Ensure aspect_ratio is valid and positive, defaulting if necessary
        if (!$this->aspect_ratio || $this->aspect_ratio <= 0) {
            // Attempt to get/calculate aspect ratio again if it's invalid at this point
            $this->aspect_ratio = $this->_get_aspect_ratio(); // This should return a sane positive float
            if (!$this->aspect_ratio || $this->aspect_ratio <= 0) { // If still invalid, use defaults
                $default_w_for_ar = (int)($this->settings['img_cp_default_img_width'] ?? 100);
                $default_h_for_ar = (int)($this->settings['img_cp_default_img_height'] ?? 100);
                $default_w_for_ar = $default_w_for_ar > 0 ? $default_w_for_ar : 1; // Ensure non-zero
                $default_h_for_ar = $default_h_for_ar > 0 ? $default_h_for_ar : 1; // Ensure non-zero
                $this->aspect_ratio = $default_h_for_ar / $default_w_for_ar;
                $this->utilities->debug_message(lang('jcogs_img_aspect_ratio_defaulted_in_dims_calc'), $this->aspect_ratio);
            }
        }

        // Logic to determine final new_width and new_height
        $width_is_set = $this->new_width && $this->new_width > 0;
        $height_is_set = $this->new_height && $this->new_height > 0;

        if ($width_is_set && !$height_is_set) {
            // Width is set, Height is not: Calculate Height
            $this->new_height = round($this->new_width * $this->aspect_ratio, 0);
        } elseif (!$width_is_set && $height_is_set) {
            // Height is set, Width is not: Calculate Width
            $this->new_width = round($this->new_height / $this->aspect_ratio, 0);
        } elseif (!$width_is_set && !$height_is_set) {
            // Neither Width nor Height is set: Use original dimensions or defaults
            // Ensure original dimensions are positive, otherwise use defaults
            $base_width = ($this->orig_width && $this->orig_width > 0) ? $this->orig_width : (int)($this->settings['img_cp_default_img_width'] ?? 100);
            $base_height = ($this->orig_height && $this->orig_height > 0) ? $this->orig_height : (int)($this->settings['img_cp_default_img_height'] ?? 100);
            
            $base_width = $base_width > 0 ? $base_width : 1; // Ensure positive
            $base_height = $base_height > 0 ? $base_height : 1; // Ensure positive

            // Decide which original dimension to prioritize if aspect ratio needs to be applied
            // This logic assumes if both orig_width and orig_height were 0, we use default W/H and their implied AR.
            // If one original dim was set, use that and calculate the other.
            // If both original dims were set, use them (aspect_ratio might differ from orig_aspect_ratio here if param was set).

            if ($this->orig_width && $this->orig_width > 0 && $this->orig_height && $this->orig_height > 0) {
                // Both original dimensions are valid, prefer them if no new_width/new_height was specified by params
                // However, if an aspect_ratio param was given, it should dictate.
                // If aspect_ratio param was set, it's already in $this->aspect_ratio.
                // We need to decide if we base on orig_width or orig_height to apply $this->aspect_ratio.
                // Defaulting to using orig_width as the primary if both are available and aspect_ratio param is different.
                $this->new_width = $base_width;
                $this->new_height = round($this->new_width * $this->aspect_ratio,0);

            } elseif ($this->orig_width && $this->orig_width > 0) { // Only original width is valid
                $this->new_width = $base_width;
                $this->new_height = round($this->new_width * $this->aspect_ratio, 0);
            } elseif ($this->orig_height && $this->orig_height > 0) { // Only original height is valid
                $this->new_height = $base_height;
                $this->new_width = round($this->new_height / $this->aspect_ratio, 0);
            } else { // Neither original dimension is valid, use defaults and aspect_ratio
                $this->new_width = $base_width; // Default width
                $this->new_height = round($this->new_width * $this->aspect_ratio, 0); // Calculate height based on default width and AR
            }
        }

        // Final safeguard: ensure dimensions are positive integers and at least 1px.
        $this->new_width = max(1, (int)round($this->new_width));
        $this->new_height = max(1, (int)round($this->new_height));

        // FIT Calcs
        // =========    

        // At this point, if we are doing a crop or stretch resize we have all the info we need.
        // Otherwise, we need to work how adjust respecting crop and fit parameters to ensure
        // original image is not distorted during transformation.
        // We avoid distortion by preserving original image ratio and resizing image to fit
        // in new bounding box specified (by new_width and new_height)
        // * fit=cover: original image to reach edge of new shape on all sides
        // * fit=contain: original image to be contained within new shape  (default)
        // * fit not specified or fit=stretch: the image will get distored

        if (!$this->flags->its_a_crop && $this->params->fit != 'distort') {
            // It's not a crop (so it is a resize!)
            list($this->new_width, $this->new_height) = $this->_get_fit_dimensions($this->params->fit);
        }
        $this->new_size = new Box($this->new_width, $this->new_height);
        return true;
    }

    /**
     * Utility function: Crop image
     *
     * @return bool
     */
    private function _image_crop()
    {
        if ($this->flags->using_cache_copy || !$this->flags->its_a_crop || !$crop_params = $this->_validate_crop_params($this->params->crop)) {
            // if using cache copy or we have no crop specified or no valid crop parameters specified, return
            return true;
        }

        // Put marker in for start of crop
        $this->utilities->debug_message(lang('jcogs_img_cropping_image_start'), $this->params->crop);

        $source_width = $this->orig_width;
        $source_height = $this->orig_height;

        // Is it a smart-scale crop?
        // Ignore smart-scaling if we are doing a face_detect crop and found faces 
        if ($crop_params[3] == 'y' && !($crop_params[0] == 'f' && $this->faces)) {

            // For smart-scale, the source image is resized before crop so that its smallest
            // dimension equals the length of the longest dimension of the target image.

            list($source_width, $source_height) = $this->_get_fit_dimensions('cover');

            // Put a marker in the debug log
            $this->utilities->debug_message(lang('jcogs_img_cropping_image_smart_scale'), ['new width' => $source_width, 'new height' => $source_height]);

            // We can't wait until filter queue runs to do resize, so create a temporary queue and do it now
            $transformation = new Filter\Transformation(null);
            $transformation->add(
                new Filter\Basic\Resize(
                    new Box((int) $source_width, (int) $source_height)
                ),
                $this->stats->transformation_count++
            );
            // Apply the resize 
            $this->processed_image = $transformation->apply($this->processed_image);
        }

        // Make sure crop is smaller than source image... 
        if ($this->new_width > $source_width || $this->new_height > $source_height) {
            // Can't do crop that is bigger than source
            $this->utilities->debug_message(lang('jcogs_img_crop_exceeds_source'), ['pre-crop width' => $source_width, 'target crop width' => $this->new_width, 'pre-crop height' => $source_height, 'target crop height' => $this->new_height]);
            return false;
        }

        // Check to see if we have face_detect to consider
        if ($crop_params[1][0] == 'face_detect' || $crop_params[1][1] == 'face_detect' || $crop_params[0] == 'f') {
            // At least one, so get face detect data
            $this->faces = is_null($this->faces) ? $this->image_utilities->face_detection($this->processed_image->getGdResource(), intval($this->_unpack_param('face_detect_sensitivity'))) : $this->faces;

            // If it is a face_detect crop and we got faces then adjust crop dimensions
            if ($this->faces && $crop_params[0] == 'f') {
                // Set width and height to match face detect bounding box plus face_crop_margin
                $face_crop_margin = intval($this->_unpack_param('face_crop_margin'));
                $this->new_width = $this->faces[0]['width'] + $face_crop_margin * 2;
                $this->new_height = $this->faces[0]['height'] + $face_crop_margin * 2;
            }
        }

        // Co-ordinates to place top-left corner of new image points against original image
        $x_dimension['left'] = 0;
        $x_dimension['center'] = round(($source_width - $this->new_width) / 2, 0);
        $x_dimension['right'] = round($source_width - $this->new_width, 0);
        $x_dimension['face_detect'] = $x_dimension['center'];
        $y_dimension['top'] = 0;
        $y_dimension['center'] = round(($source_height - $this->new_height) / 2, 0);
        $y_dimension['bottom'] = $source_height - $this->new_height;
        $y_dimension['face_detect'] = $y_dimension['center'];

        // If we got something from face_detect use it to re-calculate positions
        if ($this->faces && count($this->faces) > 1) {
            $centre_face_x = round($this->faces[0]['x'] + $this->faces[0]['width'] / 2, 0);
            $centre_face_y = round($this->faces[0]['y'] + $this->faces[0]['height'] / 2, 0);
            $x_dimension['face_detect'] = round($centre_face_x - $this->new_width / 2, 0);
            $y_dimension['face_detect'] = round($centre_face_y - $this->new_height / 2, 0);
        }

        // Calculate offset based on position and offset values
        $offset_x = (int) $x_dimension[$crop_params[1][0]] + $crop_params[2][0];
        $offset_y = (int) $y_dimension[$crop_params[1][1]] + $crop_params[2][1];

        // Now check to see if crop top-left offset is still within image
        // If not use top / left boundary as limit
        $offset_x = max(0, $offset_x);
        $offset_y = max(0, $offset_y);

        // Now check to see if far edge of crop is still within image
        // If not, push crop shape up to far edge
        $offset_x = min($source_width - $this->new_width, $offset_x);
        $offset_y = min($source_height - $this->new_height, $offset_y);

        // start point is a Point object (XY)
        $crop_start_point = new PointSigned(max($offset_x, 0), max($offset_y, 0));

        // Box size is simply new_width / new_height
        $crop_size = new Box($this->new_width, $this->new_height);

        // Add the crop to the transformation queue
        $this->transformation->add(new Filter\Basic\Crop($crop_start_point, $crop_size), $this->stats->transformation_count++);

        // Now in case we have a filter using $faces, if we have done a type 1 face_crop then update $faces
        if ($this->faces && $crop_params[0] == 'f') {
            $this->faces[0]['width'] += $face_crop_margin * 2;
            $this->faces[0]['height'] += $face_crop_margin * 2;
            $x_adjust = max($this->faces[0]['x'] - $face_crop_margin, 0);
            $y_adjust = max($this->faces[0]['y'] - $face_crop_margin, 0);
            $this->faces[0]['x'] -= $face_crop_margin;
            $this->faces[0]['y'] -= $face_crop_margin;
            for ($i = 0; $i < count($this->faces); $i++) {
                $this->faces[$i]['x'] -= $x_adjust;
                $this->faces[$i]['y'] -= $y_adjust;
            }
        }

        // Now in case we have a filter using $faces, if we have done a type 2 face_crop then update $faces
        if ($this->faces && ($crop_params[1][0] == 'face_detect' || $crop_params[1][1] == 'face_detect')) {
            // Work out origin shift from faces to crop
            $origin_shift_faces_x = $source_width != $this->new_width ? round(($source_width - $this->new_width) / 2 + ($x_dimension['face_detect'] - $x_dimension['center']), 0) : 0;
            $origin_shift_faces_y = $source_height != $this->new_height ? round(($source_height - $this->new_height) / 2 + ($y_dimension['face_detect'] - $y_dimension['center']), 0) : 0;
            for ($i = 0; $i < count($this->faces); $i++) {
                $this->faces[$i]['x'] = max(0, $this->faces[$i]['x'] - $origin_shift_faces_x);
                $this->faces[$i]['y'] = max(0, $this->faces[$i]['y'] - $origin_shift_faces_y);
                $this->faces[$i]['width'] = $this->faces[$i]['x'] + $this->faces[$i]['width'] < $this->new_width ? $this->faces[$i]['width'] : $this->new_width - $this->faces[$i]['x'];
                $this->faces[$i]['height'] = $this->faces[$i]['y'] + $this->faces[$i]['height'] < $this->new_height ? $this->faces[$i]['height'] : $this->new_height - $this->faces[$i]['y'];
            }
        }

        return true;
    }

    /**
     * Utility function: Flip image
     *
     * @return bool
     */
    private function _image_flip(): bool
    {
        if ($this->flags->using_cache_copy || empty($this->params->flip)) {
            // if using cache copy or we have no flip specified, return
            return true;
        }

        // Add flips to transformation queue
        $flips = explode('|', $this->params->flip);
        foreach ($flips as $flip) {
            switch ($flip) {
                case 'h':
                    $this->transformation->add(new Filter\Basic\FlipHorizontally(), $this->stats->transformation_count++);
                    break;
                case 'v':
                    $this->transformation->add(new Filter\Basic\FlipVertically(), $this->stats->transformation_count++);
                    break;
            }
        }

        $this->utilities->debug_message(lang('jcogs_img_flipping_image'), $this->params->flip);

        return true;
    }

    /**
     * Utility function: Determines if there is a binding min or max value
     * that applies to image: if there is it calculates the appropriate dimension
     * value, otherwise leaves value at null
     *
     * @return bool
     */
    private function _image_min_max_calcs(): bool
    {
        // Set all our min-max variables to null
        $min = $max = $min_width = $max_width = $min_height = $max_height = null;

        // Get adjusted values for any dimension parameters we have been supplied
        if ($this->params->min_width) {
            $min_width = $this->image_utilities->validate_dimension($this->_unpack_param('min_width'), $this->orig_width);
        }
        if ($this->params->max_width) {
            $max_width = $this->image_utilities->validate_dimension($this->_unpack_param('max_width'), $this->orig_width);
        }
        if ($this->params->min_height) {
            $min_height = $this->image_utilities->validate_dimension($this->_unpack_param('min_height'), $this->orig_height);
        }
        if ($this->params->max_height) {
            $max_height = $this->image_utilities->validate_dimension($this->_unpack_param('max_height'), $this->orig_height);
        }
        if ($this->params->max) {
            $max = $this->image_utilities->validate_dimension($this->_unpack_param('max'), $this->orig_width);
        }
        if ($this->params->min) {
            $min = $this->image_utilities->validate_dimension($this->_unpack_param('min'), $this->orig_width);
        }

        // Determine which max value is active
        $active_max_width = $max_width ?? $max;
        $active_max_height = $max_height ?? $max;

        // Determine which min value is active
        $active_min_width = $min_width ?? $min;
        $active_min_height = $min_height ?? $min;

        // Determine if we have new dimensions to specify

        // Width - Max
        if ($active_max_width) {
            $this->new_width = $this->new_width ? min($this->new_width, $active_max_width) : min($this->orig_width, $active_max_width);
        }

        // Width - Min
        if ($active_min_width) {
            $this->new_width = $this->new_width ? max($this->new_width, $active_min_width) : max($this->orig_width, $active_min_width);
        }

        // Height - Max
        if ($active_max_height) {
            $this->new_height = $this->new_height ? min($this->new_height, $active_max_height) : min($this->orig_height, $active_max_height);
        }

        // Height - Min
        if ($active_min_height) {
            $this->new_height = $this->new_height ? max($this->new_height, $active_min_height) : max($this->orig_height, $active_min_height);
        }

        return true;
    }

    /**
     * Utility function: Reflect image
     * Method loosely from p24 of https://www.slideshare.net/avalanche123/introduction-toimagine
     *
     * @return bool
     */
    private function _image_reflect(): bool
    {
        if ($this->flags->using_cache_copy || is_null($this->params->reflection)) {
            // if using cache copy or we have no reflection specified, return
            return true;
        }

        // Default parameter values for reflection - '0,80,0,50%'
        if ($this->params->reflection !== '') {
            $this->utilities->debug_message(lang('jcogs_img_reflecting_image'), (int) $this->params->reflection);
            
            // Expand and validate parameters
            $reflection_params = explode(',', $this->params->reflection);
            $gap = $this->image_utilities->validate_dimension($reflection_params[0] ?? 0, $this->new_height);
            $start_opacity = isset($reflection_params[1]) && intval($reflection_params[1]) > 0 && intval($reflection_params[1]) <= 100 ? intval($reflection_params[1]) : 80;
            $end_opacity = isset($reflection_params[2]) && intval($reflection_params[2]) > 0 && intval($reflection_params[2]) <= 100 ? intval($reflection_params[2]) : 0;
            $reflection_height = round($this->image_utilities->validate_dimension($reflection_params[3] ?? '50%', $this->new_height), 0);
        } else {
            // If we get here somehow we got an empty parameter value into function - bail!
            return false;
        }

        // Work out background color to use
        $reflection_color = in_array($this->params->save_as, ['png', 'webp']) ? (new Palette\RGB())->color([0, 0, 0], 0) : $this->image_utilities->validate_colour_string($this->params->bg_color);

        // Add reflection filter to transformation queue
        $this->transformation->add(new Filters\Reflection($reflection_color, $reflection_height, $gap, $start_opacity, $end_opacity), $this->stats->transformation_count++);

        return true;
    }

    /**
     * Utility function: Resize image
     *
     * @return bool
     */
    private function _image_resize()
    {

        if ($this->flags->using_cache_copy || ($this->new_height == $this->orig_height && $this->new_width == $this->orig_width)) {
            // if using cache copy or if new size is identical to current size then no need to resize it, so return
            return true;
        }

        // Queue the resize...
        $this->utilities->debug_message(lang('jcogs_img_resizing_image'), array($this->new_width, $this->new_height));
        $this->transformation->add(new Filter\Basic\Resize($this->new_size), $this->stats->transformation_count++);

        return true;
    }

    /**
     * Utility function: Rotate image
     *
     * @return bool
     */
    private function _image_rotate(): bool
    {
        if ($this->flags->using_cache_copy || empty($this->params->rotate)) {
            // if using cache copy or we have no rotation specified, return
            return true;
        }

        // make sure we got an integer rotation
        $rotation_angle = intval($this->params->rotate);
        if ($rotation_angle === 0) {
            return true;
        }

        // check that we have an object type bg_color
        $this->params->bg_color = is_string($this->params->bg_color) ? $this->image_utilities->validate_colour_string($this->params->bg_color) : $this->params->bg_color;

        $this->transformation->add(new Filter\Basic\Rotate($rotation_angle, $this->params->bg_color), $this->stats->transformation_count++);
        $this->utilities->debug_message(lang('jcogs_img_rotating_image'), $rotation_angle . ',' . $this->params->bg_color->__toString());

        return true;
    }

    /**
     * Initialize the lazy placeholder for the image
     *
     * This method sets up a placeholder image that can be used for lazy loading.
     * It ensures that the placeholder is properly configured and ready to be used
     * in place of the actual image until the image is fully loaded.
     *
     * @param string $type The type of placeholder to initialize.
     * @return void
     */
    private function _initialize_lazy_placeholder(?string $type): void
    {
        if (empty($type)) {
            $type = $this->params->lazy ?: 'lqip'; // Default to 'lqip' if type and params->lazy are empty
        } else {
            // Ensure type is one of the expected values, default to 'lqip' if not
            $valid_types = ['lqip', 'dominant_color', 'js_lqip', 'js_dominant_color'];
            // Also allow types that might be set directly via params->lazy if they are valid overall lazy modes
            $valid_lazy_modes = ['lqip', 'dominant_color', 'blur', 'html5', 'js_lqip', 'js_dominant_color', 'js_blur']; // Add other valid $this->params->lazy values
            if (!in_array($type, $valid_types) && !in_array($type, $valid_lazy_modes)) {
                $type = 'lqip';
            }
        }
        $this->params->lazy_type = $type;
        // $this->params->lazy should already be set from initial parameter processing.
        // If $type was explicitly 'lqip' or 'dominant_color' from post_process,
        // ensure $this->params->lazy reflects a mode that would trigger placeholder generation.
        if (in_array($type, ['lqip', 'dominant_color']) && $this->params->lazy == 'html5') {
             // If html5 lazy is default but a specific placeholder is requested, adjust.
             // This logic might need refinement based on how $this->params->lazy is intended to interact with explicit modes.
             // For now, lazy_type drives the filter.
        } elseif (empty($this->params->lazy)) {
            $this->params->lazy = $this->settings['img_cp_lazy_loading_mode'] ?: 'lqip';
        }
    }

    /**
     * Checks if an image exists in the cache directory and caching is enabled
     *
     * This method verifies both the existence of a cached image file at the specified
     * local cache path and whether the caching system is currently enabled for the
     * image processing functionality.
     *
     * @param string $local_cache_path The file system path to the cached image file
     * @param string $original_source_path The file system path to the original source image
     * 
     * @return bool Returns true if the image exists in cache and caching is enabled, false otherwise
     */
    private function _is_image_in_cache_and_enabled(string $local_cache_path, string $original_source_path): bool
    {
        $trimmed_local_cache_path = trim($local_cache_path, '/');

        // is_image_in_cache now handles checking if caching is active,
        // if the file exists, is fresh, and cleans up if not.
        if ($this->image_utilities->is_image_in_cache($trimmed_local_cache_path)) {
            // The image is considered validly in the cache (exists, fresh, log is consistent or was handled).
            // Now, we need to get its metadata to populate the JcogsImage object.
            $cache_log_vars_result = $this->image_utilities->get_cache_log_vars(image_path: $trimmed_local_cache_path);
            
            $cached_vars = null;
            if ($cache_log_vars_result && isset($cache_log_vars_result[0]) && is_array($cache_log_vars_result[0])) {
                $cached_vars = $cache_log_vars_result[0];
            }

            if ($cached_vars) {
                // We have the necessary metadata from the cache log.
                // Pass the original $local_cache_path to _enable_cache_copy as per original method's usage.
                if ($this->_enable_cache_copy(original_source_path: $original_source_path, local_cache_path: $local_cache_path, cached_vars: $cached_vars)) {
                    $this->utilities->debug_message(lang('jcogs_img_found_in_cache'), $local_cache_path);
                    return true;
                } else {
                    // _enable_cache_copy failed even with cached_vars. This might indicate an internal issue.
                    $this->utilities->debug_message(lang('jcogs_img_enable_cache_copy_failed_with_vars'), $local_cache_path);
                    return false;
                }
            } else {
                // is_image_in_cache returned true, but we couldn't retrieve log variables.
                $this->utilities->debug_message(lang('jcogs_img_cache_log_vars_missing_for_enable'), $local_cache_path);
                return false;
            }
        } else {
            // is_image_in_cache returned false. 
            return false;
        }
    }

    /**
     * Set the paths for the lazy loading placeholder image
     *
     * This method initializes the placeholder object and sets the local path,
     * save path, and return URL for the placeholder image. These paths are used
     * to store and retrieve the placeholder image during lazy loading.
     *
     * @return void
     */
    private function _set_placeholder_paths(): void
    {
        $this->placeholder = new \stdClass;
        $this->placeholder->output = $this->ident->output . '_' . $this->params->lazy_type;
        $this->placeholder->local_path = rtrim($this->ident->cache_path . $this->placeholder->output . '.' . $this->params->save_as, '/');
        $this->placeholder->save_path = rtrim($this->placeholder->local_path, '/');
        $this->placeholder->return_url = $this->image_utilities->get_image_path_prefix() . trim($this->ident->cache_path . $this->placeholder->output . '.' . $this->params->save_as, '/');
    }

    /**
     * Unpacks the border parameters
     *
     * @return array|bool
     */
    private function _unpack_border_params(string $border_params, int $image_width, string|object $border_colour): array|bool
    {
        if (empty($border_params)) {
            $this->utilities->debug_message(lang('jcogs_img_border_param_issue'));
            return false;
        }

        $border_params = explode('|', $border_params);
        $border_width = $this->image_utilities->validate_dimension($border_params[0], $image_width);
        $border_colour = isset($border_params[1]) ? $this->image_utilities->validate_colour_string($border_params[1]) : $border_colour;

        return [
            'width' => $border_width,
            'colour' => $border_colour
        ];
    }

    /**
     * Utility function: Returns the value of a parameter if one has been set, 
     * if not the default for that parameter is returned.
     *
     * @param string $param
     * @return mixed
     */
    private function _unpack_param(string $param)
    {
        return $this->params->{$param} ?? $this->image_utilities::$valid_params[$param];
    }

    /**
     * Utility function: Validates and unpacks crop setting (which has the form:
     * yes_or_no|position|offset|smart_scale) or installs defaults.
     *
     * @param string $param
     * @return mixed
     */
    private function _validate_crop_params(string $crop_params)
    {
        // If we get null return false
        if (is_null($crop_params)) {
            return false;
        }

        // Try to explode the param
        $crop_params = explode('|', $crop_params);

        // Get the defaults
        $crop_defaults = explode('|', $this->image_utilities::$valid_params['crop']);

        // Check we have something sensible returned... and replace with default if not
        // 1 - check crop param is yes / no / face_detect
        $crop_params[0] = in_array(strtolower(substr($crop_params[0], 0, 1)), ['y', 'n', 'f']) ? strtolower(substr($crop_params[0], 0, 1)) : $crop_defaults[0];

        // 2 - check position param is sensible (and expand)
        $crop_defaults[1] = explode(',', $crop_defaults[1]);
        $crop_params[1] = isset($crop_params[1]) && $crop_params[1] ? explode(',', $crop_params[1]) : $crop_defaults[1];
        $crop_params[1][0] = isset($crop_params[1][0]) && in_array(strtolower($crop_params[1][0]), ['left', 'center', 'right', 'face_detect']) ? strtolower($crop_params[1][0]) : $crop_defaults[1][0];
        $crop_params[1][1] = array_key_exists(1, $crop_params[1]) && isset($crop_params[1][0]) && in_array(strtolower($crop_params[1][1]), ['top', 'center', 'bottom', 'face_detect']) ? strtolower($crop_params[1][1]) : $crop_defaults[1][0];

        // 3 - check offset param (and expand)
        $crop_defaults[2] = explode(',', $crop_defaults[2]);
        $crop_params[2] = isset($crop_params[2]) && $crop_params[2] ? explode(',', $crop_params[2]) : $crop_defaults[2];
        if (count($crop_params[2]) == 2) {
            $crop_params[2][0] = $this->image_utilities->validate_dimension($crop_params[2][0], $this->orig_width);
            $crop_params[2][1] = $this->image_utilities->validate_dimension($crop_params[2][1], $this->orig_height);
        } else {
            $crop_params[2] = $crop_defaults[2];
        }

        // 4 - check smart_scale param
        $crop_params[3] = isset($crop_params[3]) && in_array(strtolower(substr($crop_params[3], 0, 1)), ['y', 'n']) ? strtolower(substr($crop_params[3], 0, 1)) : $crop_defaults[3];

        // 5 - check auto-center sensitivity param
        // Auto-center sensitivity parameter is optional
        $crop_params[4] = isset($crop_params[4]) && $crop_params[4] ? $crop_params[4] : $crop_defaults[4];

        // return
        return $crop_params;
    }
}
