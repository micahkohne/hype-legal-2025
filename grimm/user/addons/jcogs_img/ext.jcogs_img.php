<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
use JCOGSDesign\Jcogs_img\Service\ImageUtilities;
/**
 * Extension
 * =========
 * Handles processing of EE hooks
 * 
 * CHANGELOG
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

class Jcogs_img_ext
{
    public $version            = JCOGS_IMG_VERSION;
    public $settings;

    /** 
     * Notice that for extensions you must include $settings 
     * as a parameter in the constructor
     */
    public function __construct($settings = [])
    {
        $this->settings = $settings;
    }

    public function activate_extension()
    {
        $data = [
            [
                'hook'      => 'template_post_parse', // required
                'method'    => 'template_post_parse', // required
                'priority'  => 10,
                'enabled'   => "y", // y/n
                'version'   => $this->version,
                'class'     => __CLASS__,
                'settings'  => serialize($this->settings),
            ],
            [
                'hook'      => 'after_file_save', // required
                'method'    => 'after_file_update', // required
                'priority'  => 10,
                'enabled'   => "y", // y/n
                'version'   => $this->version,
                'class'     => __CLASS__,
                'settings'  => serialize($this->settings),
            ],
            [
                'hook'      => 'after_file_delete', // required
                'method'    => 'after_file_update', // required
                'priority'  => 10,
                'enabled'   => "y", // y/n
                'version'   => $this->version,
                'class'     => __CLASS__,
                'settings'  => serialize($this->settings),
            ]
        ];
        foreach ($data as $hook) {
            ee()->db->insert('extensions', $hook);
        }
    }

    /**
     * Method for after_file_update hook
     * Looks to see if an image in JCOGS Image cache originated from this file
     * If found, the image in cache is deleted if auto-manage enabled and message sent to CP
     * If auto-manage not enabled, and image in cache then simply a warning message is flashed up in CP
     *
     * @param   object File object of modified file
     * @param   array  Parameters of the object
     * @param   array  Elements of file record modified during update
     * @return  mixed  Return the object unchanged
     */
    public function after_file_update($file, $values)
    {
        // Do we have a file?
        if (!empty($file) && !empty($values)) {
            // Get the filename for the image being saved
            if(empty($values['file_name']) || !$filename_array = pathinfo($values['file_name'])) return $file;

            // Get the local image pathinfo
            if(empty($values['upload_location_id']) || !$image_path_info = pathinfo(ee('jcogs_img:Utilities')->parseFiledir($values['upload_location_id']))) return $file;

            // Add the local path to image 
            if(empty($image_path_info['dirname']) || !$filename_array['source_path'] = $image_path_info['dirname']) return $file;

            // Set up array to hold details of affected images 
            $affected_images_count = 0;

            // Scan the db to see if we can find any entries that use this filename
            $affected_images = ee('jcogs_img:ImageUtilities')->get_file_info_from_cache_log($filename_array['filename']);
            if($affected_images) {
                foreach ($affected_images as $image) {
                    // This image potentially is affected by change
                    // Get original path for image if we have it
                    $image_values = json_decode($image->values);
                    // Check to see if source_path is same
                    if(str_contains($image_values->orig, $filename_array['source_path']) !== false) {
                        // If we have permission, delete the image from cache and clear it from cache_log
                        if (isset(ee('jcogs_img:Settings')::$settings['img_cp_cache_auto_manage']) && substr(strtolower(ee('jcogs_img:Settings')::$settings['img_cp_cache_auto_manage']), 0, 1) == 'y') {
                            // Delete the cache image
                            ee('jcogs_img:ImageUtilities')->delete_cache_log_entry($image->path);
                            // Update affected images count
                        }
                        $affected_images_count++;
                    }
                }
            }

            // Now do a notification if we don't have cache-management permission but we have found affected images
            if ($affected_images_count > 0 && (isset(ee('jcogs_img:Settings')::$settings['img_cp_cache_auto_manage']) && substr(strtolower(ee('jcogs_img:Settings')::$settings['img_cp_cache_auto_manage']), 0, 1) != 'y')) {
                ee('CP/Alert')->makeBanner('shared-form')
                ->asIssue()
                ->withTitle(lang('jcogs_img_cp_auto_manage_would_have_fired'))
                ->addToBody(sprintf(lang('jcogs_img_cp_auto_manage_would_have_fired_desc'), count($affected_images)))
                ->now();
            }
        }
        return $file; // whatever happened, return $file
    }

    /**
     * Method for cp_custom_menu hook
     * Adds fly-out menu to add-on if it is put into sidebar
     *
     * @param   object File object for current side-bar menu
     */
    public function cp_custom_menu($menu)
    {
        // Only works for EE6 and later
        if (version_compare(APP_VER, '6.0.0', '>=')) {
            $sub = $menu->addSubmenu('JCOGS Image');
            $sub->addItem(lang('fly_system_settings'), ee('CP/URL')->make('addons/settings/jcogs_img'));
            $sub->addItem(lang('fly_cache_settings'), ee('CP/URL')->make('addons/settings/jcogs_img/caching'));
            $sub->addItem(lang('fly_image_settings'), ee('CP/URL')->make('addons/settings/jcogs_img/image_defaults'));
            $sub->addItem(lang('fly_advanced_settings'), ee('CP/URL')->make('addons/settings/jcogs_img/advanced_settings'));
            $sub->addItem(lang('fly_clear_cache'), ee('CP/URL')->make('addons/settings/jcogs_img/clear_image_cache'));
        }
    }

    public function disable_extension()
    {
        ee()->db->where('class', __CLASS__);
        ee()->db->delete('extensions');
        return true;
    }

    /**
     * Catch speedy_pre_parse hooks and process tagdata
     * to hide JCOGS_IMG tags
     * @param string $tagdata
     * @return string
     */
    public function speedy_pre_parse(string $tagdata): string
    {
        // Do we need to do this?
        // if(isset(ee('jcogs_img:Settings')::$settings['img_cp_speedy_escape']) && substr(string: strtolower(string: ee('jcogs_img:Settings')::$settings['img_cp_speedy_escape']), offset: 0, length: 1) == 'y') {
        //     $tagdata = $this->_speedy_pre_parse_worker($tagdata);
        // }
        return $tagdata;
    }

    /**
     * Method for template_post_parse hook
     * Looks for a JCOGS Image Lazy class within template
     * If JCOGS Image Lazy class found, appends lazy javascript element
     *
     * @param   string  Parsed template string
     * @param   bool    Whether an embed or not
     * @param   integer Site ID
     * @return  string  Template string
     */
    public function template_post_parse($template, $sub, $site_id)
    {
        // is this the final template?
        if ($sub === false) {
            // if there are other extensions on this hook, get the output after their processing
            if (isset(ee()->extensions->last_call) && ee()->extensions->last_call) {
                $template = ee()->extensions->last_call;
            }

            // Look in $template for JCOGS Image Lazy Loading class
            // If we find it, append our lazy loading javascript
            preg_match('/data-ji-src/', $template, $matches, PREG_UNMATCHED_AS_NULL);
            if ($matches) {
                // insert the javascript stub to enable lazyloading
                $javascript = file_get_contents(PATH_THIRD . strtolower(JCOGS_IMG_CLASS) . '/javascript/lazy_load.min.js');
                $css = '<noscript><style>[data-ji-src]{display:none;}</style></noscript>';
                list($start, $rest) = explode('</head>', $template);
                list($middle, $end) = explode('</body>', $rest);
                $template = $start . PHP_EOL . $css . PHP_EOL . '</head>' . PHP_EOL . $middle . PHP_EOL. '<script>' . PHP_EOL . $javascript . PHP_EOL . '</script>' . PHP_EOL . '</body>' . PHP_EOL . $end;
            }

            // Look in $template for JCOGS Background Lazy Loading marker
            // If we find it put preload instruction in head, and remove the marker
            preg_match_all('/data-bglzy=\"(.*?)\"/', $template, $matches, PREG_UNMATCHED_AS_NULL);
            if ($matches) {
                $i = 0;
                $head_insert = '';
                foreach($matches[1] as $match) {
                    // Insert as preload into $head_insert if it is not there already
                    if(!empty($match) && strpos($head_insert, $match) === false) {
                        // insert a preloading entry into the <head> part of template to preload each lazyloading image
                        $head_insert .= "<link rel=\"preload\" as=\"image\" href=\"".$match."\" fetchpriority=\"high\">\n";
                        // remove the element from the tag as no longer needed
                        $template = str_replace($matches[0][$i],'',$template);
                    }
                    $i++;
                }
                // Now insert into the end of the <head>section if it is not there already
                if($head_insert != '') {
                    $count = 1; // how many instances to replace
                    $template = str_ireplace('</head>', $head_insert.'</head>', $template, $count);
                }
            }

            // Look in $template for JCOGS preload marker
            // If we find it put preload instruction in head, and remove the marker
            preg_match_all('/data-ji-preload=\"(.*?)\"/', $template, $matches, PREG_UNMATCHED_AS_NULL);
            if ($matches) {
                $i = 0;
                $head_insert = '';
                foreach($matches[1] as $match) {
                    // Insert as preload into $head_insert if it is not there already
                    if(!empty($match) && strpos($head_insert, $match) === false) {
                        // insert a preloading entry into the <head> part of template to preload each nominated image
                        $head_insert .= "<link rel=\"preload\" as=\"image\" href=\"".$match."\">\n";
                        // remove the element from the tag as no longer needed
                        $template = str_replace($matches[0][$i],'',$template);
                    }
                    $i++;
                }
                // Now insert into the end of the <head>section if it is not there already
                if($head_insert != '') {
                    $count = 1; // how many instances to replace
                    $template = str_ireplace('</head>', $head_insert.'</head>', $template, $count);
                }
            }
        }
        return $template; // whatever happened, return $template
    }

    public function update_extension($current = '')
    {
        return true;
    }

    /**
     * Recursively finds JCOGS_IMG tags in $tagdata and brackets them with Speedy Escape tag-pairs.
     * 
     * @param string $tagdata
     * @return string
     */
    private function _speedy_pre_parse_worker(string $tagdata, int $offset = 0): string
    {
        $count = 1;

        // 1 - find some image tags
        // 1 - 1 -> Need some regex to pull out JCOGS Image tags (and closing tags)
        //          Borrow code from Template.php parse_tags function (line 1259 and on)
        // Identify the string position of the first occurence of a matched tag
        $in_point = strpos(haystack: $tagdata, needle: LD . 'exp:jcogs_img', offset: $offset);
        if (false === $in_point) {
            // No tag found? Bale out
            return $tagdata;
        }

        // Grab the opening portion of the tag: {exp:jcogs_img:tag param="value" param="value"}
        if (!preg_match(pattern: '/' . LD . 'exp\:jcogs_img\:(.*?)\s((?:.|\R)*?)' . RD . '\s/m', subject: $tagdata, matches: $matches, offset: $offset)) {
            // Given what we just did, should never happen, but just in case ... if nothing found bale out
            return $tagdata;
        }

        // Strip out parameters etc to find out what closing tag might look like
        $full_tag = $matches[0];
        $tag = trim(string: $matches[1]);
        $cur_tag_close = LD . '/' . 'exp:jcogs_img:' . $tag . RD;
        $out_point = strpos(haystack: $tagdata, needle: $cur_tag_close, offset: $offset);

        // 2 - change tag (and tag_pair contents if so) to be unparseable by EE
        // Do we have a tag pair?
        if (false !== $out_point) {
            // Get the opening and closing tags and all between
            $block = substr(string: $tagdata, offset: $in_point, length: $out_point - $in_point + strlen(string: $cur_tag_close));
        } else {
            // Single tag...
            $block = $full_tag;
        }
        // Now replace $block with enclosed $block
        $new_block = LD . 'exp:speedy:escape' . RD . $block . LD . '/exp:speedy:escape' . RD;

        $tagdata = str_replace(search: $block, replace: $new_block, subject: $tagdata, count: $count);

        // Put something in the debug log:
        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_speedy_escape_substitution'), [$block]);

        // Work out new offset
        $offset = $offset + strlen(string: $new_block);

        // Now recurse if there are more tags in this fragment
        if (strpos(haystack: $tagdata, needle: LD . 'exp:jcogs_img', offset: $offset)) {
            // Something found ... so recurse
            $tagdata = $this->_speedy_pre_parse_worker($tagdata, $offset);
        }
        return $tagdata;
    }
}
