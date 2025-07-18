<?php

/**
 * JCOGS Utilities Service
 * =======================
 * Service for common services used by JCOGS add-ons
 * =====================================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Utilities
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.0.0
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.0.0
 */

namespace JCOGSDesign\Jcogs_img\Service;

ee()->lang->load('jcogs_utils', ee()->session->get_language(), false, true, PATH_THIRD . 'jcogs_img/');

class Utilities
{
    public $settings;
    public static $browser_image_format_support;
    public static $valid_server_image_formats;
    public static $license_status;
    private $_cache_path;

    public function __construct()
    {
        $this->settings = ee('jcogs_img:Settings')::$settings;
        ee()->load->helper('file');
        $this->_cache_path = PATH_CACHE;
    }

    /**
     * Checks that allow_url_fopen is enabled
     *
     * @return boolean
     */
    public function allow_url_fopen_enabled(): bool
    {
        // Generic test of all the things that we need for file_get_contents() to work ... 
        $w = stream_get_wrappers();

        return (
            (in_array('http', $w) || in_array('https', $w)) && 
            extension_loaded  ('openssl') && 
            ini_get('allow_url_fopen')
        );
    }

    /**
     * Recursively retrieves the keys of an array up to a specified depth.
     *
     * @param array $myArray The input array.
     * @param int|float $MAXDEPTH The maximum depth to traverse. Default is INF (infinite depth).
     * @param int $depth The current depth of recursion. Default is 0.
     * @param array $arrayKeys The array to store the keys. Default is an empty array.
     * @return array An array containing the keys of the input array up to the specified depth.
     */
    public function array_keys_recursive($myArray, $MAXDEPTH = INF, $depth = 0, $arrayKeys = [])
    {
        if ($depth < $MAXDEPTH) {
            $depth++;
            $keys = array_keys($myArray);
            foreach ($keys as $key) {
                if (is_array($myArray[$key])) {
                    $arrayKeys = array_merge($arrayKeys, $this->array_keys_recursive($myArray[$key], $MAXDEPTH, $depth));
                } else {
                    $arrayKeys[$key] = $key;
                }
            }
        }

        return $arrayKeys;
    }

    /**
     * JCOGS Utility - work-around for EE cache set to dummy 
     * =====================================================
     * Convert text to UTF-8 coding (to ensure works OK with translator services)
     * Or throws an error
     *
     * @param string $operation
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @param string $scope
     * @return 
     */
    public function cache_utility(string $operation = '', ?string $key = null, object|string|array|null $data = null, int $ttl = 60, $scope = \Cache::LOCAL_SCOPE)
    {
        // Check we have a location
        if (!$key) {
            $this->debug_message(lang('jcogs_utils_no_cache_key_provided'));
            return false;
        }

        // Execute requested operation
        $key = $this->_namespaced_key($key, $scope);
        switch ($operation) {
            case 'get':
                $cacheFilePath = $this->_cache_path . $key;
                if ($cacheFilePath && !file_exists($cacheFilePath)) {
                    return false;
                }
                $data = @unserialize(file_get_contents($this->_cache_path . $key));
                if (!is_array($data)) {
                    return false;
                }
                if ($data['ttl'] > 0 && ee()->localize->now > $data['time'] + $data['ttl']) {
                    unlink($this->_cache_path . $key);
                    return false;
                }
                return $data['data'];

                case 'save':
                $contents = array(
                    'time' => ee()->localize->now,
                    'ttl' => $ttl,
                    'data' => $data
                );
                // Build file path to this key
                $path = $this->_cache_path . $key;
                // Remove the cache item name to get the path by looking backwards
                // for the directory separator
                $path = substr($path, 0, strrpos($path, DIRECTORY_SEPARATOR) + 1);
                // Create namespace directory if it doesn't exist
                if (!file_exists($path) or !is_dir($path)) {
                    @mkdir($path, 0777, true);
                    // Grab the error if there was one
                    $error = error_get_last();
                    // If we had trouble creating the directory, it's likely due to a
                    // concurrent process already having created it, so we'll check
                    // to see if that's the case and if not, something else went wrong
                    // and we'll show an error
                    if (!is_dir($path) or !is_writable($path)) {
                        trigger_error($error['message'], E_USER_WARNING);
                    } else {
                        // Write an index.html file to ensure no directory indexing
                        write_index_html($path);
                    }
                }
                if (write_file($this->_cache_path . $key, serialize($contents))) {
                    @chmod($this->_cache_path . $key, 0666);
                    return true;
                }
                return false;

                case 'delete':
                $path = $this->_cache_path . $key;

                // If we are deleting contents of a namespace
                if (strrpos($key, \Cache::NAMESPACE_SEPARATOR, strlen($key) - 1) !== false) {
                    $path .= DIRECTORY_SEPARATOR;
                    if (delete_files($path, true)) {
                        // Try to remove the namespace directory; it may not be
                        // removeable on some high traffic sites where the cache fills
                        // back up quickly
                        @rmdir($path);
                        return true;
                    }
                    return false;
                }
                return file_exists($path) ? unlink($path) : false;

                default:
                $this->debug_message(lang('jcogs_utils_unknown_cache_operation_requested'));
                return false;
        }
    }

    /**
     * JCOGS Utility - convert to utf-8 
     * ==========================================
     * Convert text to UTF-8 coding (to ensure works OK with translator services)
     * Or throws an error
     *
     * @param  string $content_to_convert
     * @return string
     */
    public function convert_to_utf_8(?string $content_to_convert): string
    {
        if ($content_to_convert === null) {
            return '';
        }

        if (
            !mb_check_encoding($content_to_convert, 'UTF-8')
            or !($content_to_convert === mb_convert_encoding(mb_convert_encoding($content_to_convert, 'UTF-32', 'UTF-8'), 'UTF-8', 'UTF-32'))
        )
        // If it fails this test then content is not in UTF-8 format so we need to convert...
        {
            $content_to_convert = mb_convert_encoding($content_to_convert, 'UTF-8');

            // Check that conversion was successful
            if (mb_check_encoding($content_to_convert, 'UTF-8')) {
                $this->debug_message(lang('jcogs_utils_UTF8_converted_text')); // Whoop! It worked.
            } else {
                return ee()->output->fatal_error(__METHOD__ . ' ' . lang('UTF8_conversion_failed')); // Oops!
            }
        }
        return $content_to_convert;
    }

    /**
     * Utility to return elapsed time between date value provided and now
     *
     * @param  integer $date
     * @return string|bool
     */
    public static function date_difference_to_now(?int $date = null): string|bool
    {
        if (!is_int($date) || $date > time() || $date <= 0) {
            return false;
        }
        $diff = time() - $date;

        if ($diff < 60) {
            $value = $diff;
            $units = $value === 1 ? ' second' : ' seconds';
        } elseif ($diff < 3600) {
            $value = round($diff / 60);
            $units = $value < 2 ? ' minute' : ' minutes';
        } elseif ($diff < 86400) {
            $value = round($diff / 3600);
            $units = $value < 2 ? ' hour' : ' hours';
        } elseif ($diff < 604800) {
            $value = round($diff / 86400);
            $units = $value < 2 ? ' day' : ' days';
        } elseif ($diff < 2600640) {
            $value = round($diff / 604800);
            $units = $value < 2 ? ' week' : ' weeks';
        } elseif ($diff < 31207680) {
            $value = round($diff / 2600640);
            $units = $value < 2 ? ' month' : ' months';
        } else {
            $value = round($diff / 31207680);
            $units = $value < 2 ? ' year' : ' years';
        }
        return $value . $units;
    }

    /**
     * JCOGS Image - debug message utility 
     * ===================================
     * If debug is enabled, write messages to the debug log
     * Params:
     * @param string $msg - The text to write to debug log
     * @param array|string $details - optional array with more information
     * @param bool $mute - optional flag to suppress output
     * @return void
     */
    public static function debug_message(string $msg, array|string|null $details = null, bool $mute = false): void
    {
        if (!$mute && ee('jcogs_img:Settings')::$settings['img_cp_enable_debugging'] === 'y' && REQ == 'PAGE' && isset(ee()->TMPL)) {
            $dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = lang('jcogs_img_module_name');
            if (is_array($details)) {
                ee()->TMPL->log_item($caller . ' (' . $dbt[1]['function'] . ') ' . '<span style=\'color:darkblue\'>' . $msg . '</span>', $details);
            } else {
                ee()->TMPL->log_item($caller . ' (' . $dbt[1]['function'] . ') ' . '<span style=\'color:darkblue\'>' . $msg . '</span> <span style=\'color:var(--ee-link)\'>' . $details . '</span>');
            }
        }
    }

   /**
     * Utility function: lookup values in array
     * based on code from 
     * https://stackoverflow.com/a/22750841/6475781
     *
     * @param  array  $array  The array to search in.
     * @param  float  $value  The value to search for.
     * @param  boolean $exact Whether to search for an exact match or the nearest value.
     * @return array|bool     An array with the nearest value and a flag indicating if it was an exact match, or false if no match found.
     */
    public function fast_nearest($array, $value, $exact = false): array|bool
    {
        if (isset($array[$value])) {
            return $this->_exact_match($array, $value);
        } elseif ($exact || empty($array)) {
            return false;
        }

        $keys = array_keys($array);
        $min = $keys[0];
        $s = 0;
        $max = end($keys);
        $e = key($keys);

        if ($s == $e) {
            return $this->_single_element($array);
        } elseif ($value < $min) {
            return $this->_below_min($array, $min);
        } elseif ($value > $max) {
            return $this->_above_max($array, $max);
        }

        return $this->_binary_search($keys, $array, $value, $s, $e, $min, $max);
    }

    /**
     * Utility function: Fixes units of bytes to proper values
     * based on code from 
     * https://stackoverflow.com/questions/2510434/format-bytes-to-kilobytes-megabytes-gigabytes
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
     * Utility function: Get an ACTion ID
     *
     * @param string $method
     * @return	mixed
     */
    public function get_action_id(?string $method = null)
    {
        $action_id = false;

        // did we get anything?
        if ($method) {
            // See if we can find a match in db
            $action_id = ee('Model')
            ->get('Action')
            ->filter('method', $method)
            ->first()
            ->action_id;
        }

        return $action_id ?: false;
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
     * Gets information from a user agent string
     * from https://gist.github.com/james2doyle/5774516
     *
     * @param  string $u_agent
     * @return array
     */
    public function getBrowser($u_agent = null)
    {

        $bname = null;
        $platform = null;
        $version = null;
        $ub = '';

        // First get the platform?
        if (preg_match('/linux/i', $u_agent)) {
            $platform = 'linux';
        } elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
            $platform = 'mac';
        } elseif (preg_match('/windows|win32/i', $u_agent)) {
            $platform = 'windows';
        }

        // Next get the name of the useragent yes seperately and for good reason
        if (preg_match('/MSIE/i', $u_agent) && !preg_match('/Opera/i', $u_agent)) {
            $bname = 'Internet Explorer';
            $ub = "MSIE";
        } elseif (preg_match('/Firefox/i', $u_agent)) {
            $bname = 'Mozilla Firefox';
            $ub = "Firefox";
        } elseif (preg_match('/Chrome/i', $u_agent)) {
            $bname = 'Google Chrome';
            $ub = "Chrome";
        } elseif (preg_match('/Safari/i', $u_agent)) {
            $bname = 'Apple Safari';
            $ub = "Safari";
        } elseif (preg_match('/Opera/i', $u_agent)) {
            $bname = 'Opera';
            $ub = "Opera";
        } elseif (preg_match('/Netscape/i', $u_agent)) {
            $bname = 'Netscape';
            $ub = "Netscape";
        }

        // finally get the correct version number
        $known = array('Version', $ub, 'other');
        $pattern = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
        if (!preg_match_all($pattern, $u_agent, $matches)) {
            // we have no matching number just continue
        }

        // see how many we have
        $i = count($matches['browser']);
        if ($i != 1) {
            //we will have two since we are not using 'other' argument yet
            //see if version is before or after the name
            if (strripos($u_agent, "Version") < strripos($u_agent, $ub)) {
                $version = $matches['version'][0];
            } else {
                $version = $matches['version'][1];
            }
        } else {
            $version = $matches['version'][0];
        }

        // check if we have a number
        if ($version == null || $version == "") {
            $version = "?";
        }

        return array(
            'userAgent' => $u_agent,
            'name'      => $bname,
            'version'   => $version,
            'platform'  => $platform,
            'pattern'   => $pattern
        );
    }

    /**
     * Builds a context sensitive cache key to use with EE's cache class
     *
     * @param  string $unique_id
     * @return string
     */
    public function getCacheKey($unique_id)
    {
        // $class = __CLASS__ ? __CLASS__.'/' : '';
        return '/' . JCOGS_IMG_CLASS . '/' . $unique_id;
    }

    /**
	 * Calculate the duration between two timestamps.
     * From: https://codepal.ai/code-generator/query/8t2pNWAN/php-function-calculate-duration-between-timestamps
	 *
	 * @param int $timestamp1 The first timestamp.
	 * @param int $timestamp2 The second timestamp.
	 *
	 * @return string The duration in a human-readable format.
	 */
	function get_duration_between_timestamps($timestamp1, $timestamp2 = 0) {
	    // Calculate the difference in seconds between the two timestamps.
	    $time_diff = abs($timestamp2 - $timestamp1);
	 
	    // Define the time intervals in seconds.
	    $intervals = array(
	        'year' => 31536000,
	        'month' => 2592000,
	        'week' => 604800,
	        'day' => 86400,
	        'hour' => 3600,
	        'minute' => 60,
	        'second' => 1
	    );
	 
	    $output = '';
	 
	    foreach ($intervals as $interval_name => $interval_seconds) {
	        // Calculate the number of whole intervals.
	        $interval_count = floor($time_diff / $interval_seconds);
	 
	        // Add to the output if there are intervals.
	        if ($interval_count > 0) {
	            $output .= $interval_count . ' ' . $interval_name;
	            $output .= ($interval_count > 1) ? 's ' : ' ';
	 
	            // Reduce the time difference by the intervals accounted for.
	            $time_diff -= $interval_count * $interval_seconds;
	        }
	    }
	 
	    return $output;
	}

    /**
     * Utility function: Get a remote file using CURL
     * Returns either the file or false
     *
     * @param string $path
     * @param array $packet
     * @param array $inbound_header
     * @param string $encoding
     * @return string|bool
     */
    private function get_file_contents_curl(string $path, ?array $packet = null, ?array $inbound_header = null, string $encoding = 'form')
    {
        if (!$path) {
            // No path
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_gfcc_no_path'), $path);
            return false;
        }
        if (!function_exists('curl_init')) {
            // No CURL
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_gfcc_no_curl'), $path);
            return false;
        }

        // ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_gfcc_started'), $path);

        // Clean up the path provided
        $remote_file = false;
        $remote_path_array = pathinfo($path);
        $clean_remote_path = $remote_path_array['dirname'] . '/' . $remote_path_array['filename'];
        $clean_remote_path .= isset($remote_path_array['extension']) ? '.' . $remote_path_array['extension'] : '';

        // Set up CURL transaction
        // Use a super-defensive setup (from here: https://forums.phpfreaks.com/topic/162357-solved-file_get_contents-cant-access-a-url-that-a-browser-can/?do=findComment&comment=857753)
        $header = array(
            'Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5',
            'Cache-Control: max-age=0',
            'Connection: keep-alive',
            'Keep-Alive: 300',
            'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'Accept-Language: en-us,en;q=0.5'
        );
        if($inbound_header && is_array($inbound_header)) {
            $header = array_merge($header, $inbound_header);
        }

        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true); 
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $clean_remote_path);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->settings['img_cp_default_user_agent_string']);

        // if either of these options are enabled, need this to skip an error
        if (!ini_get('open_basedir') && !ini_get('safe_mode')) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        }

        // if we received a data packet it must be a POST request
        if(!empty($packet)) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            // If we are doing POST use unsanitised URL since it is one of ours
            curl_setopt($ch, CURLOPT_URL, $path);
            if($encoding === 'form') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($packet));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($packet));
            }
        }

        // Get the return file if you can
        try {
            $remote_file = curl_exec($ch);
        } catch (\Exception $e) {
            // API connection failed.
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_gfcc_error'), $e->getMessage());
            return false;
        }

        // Check we got a good response (i.e. not 400, 500 or whatever)
        $https_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Clean up
        curl_close($ch);

        if ($https_code != 200 || !$remote_file) {
            // Something went wrong ... 
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_gfcc_failed'), $clean_remote_path);
            return false;
        }

        // We probably got something ... 
        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_gfcc_success'), $clean_remote_path);

        return $remote_file;
    }

    /**
     * Utility function: Get a remote file using file_get_contents
     * Returns either the file or false
     *
     * @param string $path
     * @param array $packet
     * @param array $inbound_header
     * @param string $encoding
     * @return string|bool
     */
    private function get_file_contents_fgc(string $path, ?array $packet = null, ?array $inbound_header = null, string $encoding = 'form')
    {
        if (!$path) {
            // No path
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_gfgc_no_path'), $path);
            return false;
        }

        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_gfgc_started'), $path);

        // Clean up the path provided
        $remote_file = false;
        $remote_path_array = pathinfo($path);
        $clean_remote_path = $remote_path_array['dirname'] . '/' . urlencode($remote_path_array['filename']);
        $clean_remote_path .= isset($remote_path_array['extension']) ? '.' . $remote_path_array['extension'] : '';

        $header[] = "Accept-language: en";

        if($inbound_header && is_array($inbound_header)) {
            $header = array_merge($header, $inbound_header);
        }

        // Do some connection massaging... 
        $options = array(
            'http' => array(
                'method' => "GET",
                'header' => $header,
            )
        );
        
        // If we received a data packet it must be a POST request
        if(!empty($packet)) {
            $postdata = $encoding === 'form' ? http_build_query($packet) : json_encode($packet);
            $options['http']['method'] = "POST";
            $options['http']['content'] = $postdata;
            $clean_remote_path = $path;
        }

        $context = stream_context_create($options);
        try {
            $remote_file = @file_get_contents($clean_remote_path, false, $context);
        } catch (\Exception $e) {
            // API connection failed.
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_gfgc_error'), $e->getMessage());
            return false;
        }

        // Did it work?
        if (!$remote_file) {
            // No joy?
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_gfgc_failed'), $clean_remote_path);
            return false;
        }

        // We probably got something ... 
        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_gfgc_success'), $clean_remote_path);

        return $remote_file;
    }

    /**
     * Utility function: Get a file from local source
     * Returns the file or false
     *
     * @param string $path
     * @return string|bool
     */
    public function get_file_from_local(string $path)
    {
        // ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_gffl_started'), $path);
        // Try first using file_get_contents, which works most of the time
        
        $local_file = false;
        try {
            $local_file = ee('Filesystem')->read($path);
        } catch (\ExpressionEngine\Library\Filesystem\FilesystemException) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_unable_to_open_path_2'), $path);
            return false;
        }
        if (!$local_file) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_unable_to_open_path_1'), $path);
            ;
            return false;
        }
        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_utils_gffl_success'), $path);
        return $local_file;
    }

    /**
     * Utility function: Get a file from remote source
     * Uses file_get_contents, but if that fails tries two alternatives:
     * - less secure file_get_contents
     * - CURL
     * Returns the file or false
     *
     * @param string $path
     * @param array $packet
     * @param array $headers
     * @return string|bool
     */
    public function get_file_from_remote(string $path, $packet = null, $headers = null, string $encoding = 'form')
    {
        if (!$path) {
            // No path
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_gffr_no_path'));
            return false;
        }

        $this->debug_message(lang('jcogs_utils_gffr_started'), $path);

        $remote_file = false; 

        // Try first using Curl, which works most of the time
        $remote_file = $this->get_file_contents_curl($path, $packet, $headers, $encoding);
        if (!$remote_file) {
            // unable to read file from remote location
            $this->debug_message(lang('jcogs_utils_unable_to_open_path_4'), $path);
            return false;
        }
        if (!$remote_file) {
            // Have another go using file_get_contents ...
            if($this->allow_url_fopen_enabled()) {
                // Unless allow_url_fopen is not open - in which case skip...
                $remote_file = $this->get_file_contents_fgc($path, $packet, $headers, $encoding);
                // No joy? 
                if (!$remote_file) {
                }
            }
        }
        return $remote_file;
    }

    /**
     * Convert memory limit string from php.ini to integer number of megabytes
     * @param mixed $memory_limit
     * @return int
     */
    function normalize_memory_limit($memory_limit): int {
        if (is_numeric(value: $memory_limit)) {
            return (int) $memory_limit;
        }
    
        $unit = strtoupper(string: substr(string: $memory_limit, offset: -1));
        $value = (int)substr(string: $memory_limit, offset: 0, length: -1);
    
        switch ($unit) {
            case 'K':
                return (int)($value / 1024);
            case 'M':
                return $value;
            case 'G':
                return (int)($value * 1024);
            default:
                return 0; // Ignore other values
        }
    }

    /**
     * Returns the first n characters of a string
     *
     * @param  string $key
     * @return string $key
     */
    public function obscure_key(string &$key, $length = 10): string
    {
        $key_actual_length = strlen($key);

        if ($key_actual_length <= $length) {
            return $key;
        }

        $visible_part = substr($key, 0, $length);
        $num_chars_to_obscure = $key_actual_length - $length;
        $obscured_part = str_repeat('•', $num_chars_to_obscure);

        return $visible_part . $obscured_part;
    }

    /**
     * Utility function: Output JSON and die
     *
     * @param string $rel_path
     * @param boolean $mk_dir
     * @return string
     */
    public function output_json($json)
    {
        header('Content-type: application/json');
        echo json_encode($json);
        die;
    }

    /**
     * Convert {filedir_X} to real path
     * 
     * @param string $location
     * @return string|bool
     */
    public function parseFiledir($location)
    {
        $path = '';
        if (substr(APP_VER, 0, 1) == 7) {
            ee()->load->library('file_field');
            $file_path = ee()->file_field->getFileModelForFieldData($location);
            if ($file_path) {
                // If location given is not valid filedir we get nothing, so don't do any more unless we do
                $path = ee()->file_field->getFileModelForFieldData($location)->getAbsolutePath();
                // string returned by getAbsolutePath() will include the basepath, so for compatibility we need to remove this
                // first check that $path does indeed contain the base_path
                if (str_contains($path, $this->get_base_path()) === false) {
                    // weird stuff going on so bale... 
                    $this->debug_message(lang('jcogs_utils_no_base_path'), [$path]);
                    return false;
                } else {
                    $path = str_replace(rtrim($this->get_base_path(), '/'), '', $path);
                }
            } else {
                // Location is not a file path so return empty string
                return '';
            }
        } else {
            // Do we have a string or a number ... ?
            if(str_contains($location,'filedir_')) {
                preg_match('/{filedir_(.*?)}(.*)$/', $location, $matches);
                if (isset($matches[0])) {
                    $location = $matches[1];
                } else {
                    return '';
                }
            }
            if (intval($location) > 0) {
                $path = ee('Model')->get('UploadDestination')->filter('id', $location)->first()->url;
                $path = isset($matches[2]) ? $path . $matches[2] : $path;
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
     * Checks current php version for being > 6.2...
     *
     * @return bool|int
     */
    public function valid_ee_version()
    {
        return version_compare(APP_VER, '6.2.0', '>=');
    }

    /**
     * Checks current php version for being > 8.1...
     *
     * @return bool|int
     */
    public function valid_php_version()
    {
        return version_compare(PHP_VERSION, '8.1.0', '>=');
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
     * Look up first value that is less than the lookup value supplied
     *
     * @param  float $lookupValue
     * @param  array $array
     * @return mixed
     */
    public function vlookup(float $lookupValue, $array)
    {
        $keys = array_keys($array);
        sort($keys);

        $low = 0;
        $high = count($keys) - 1;

        while ($low <= $high) {
            $mid = (int) (($low + $high) / 2);

            if ($keys[$mid] == $lookupValue) {
                return $array[$keys[$mid]];
            }

            if ($keys[$mid] < $lookupValue) {
                $low = $mid + 1;
                continue;
            }

            $high = $mid - 1;
        }

        return false;
    }

    /**
     * Checks if the value at the specified index in the array is above the maximum.
     *
     * @param array $array The array to check.
     * @param int|string $max The index to check in the array.
     * @return array An array containing the value at the specified index and a flag indicating it is not exact.
     */
    private function _above_max($array, $max): array
    {
        return array($max => $array[$max], 'exact' => false);
    }

    /**
     * Checks if the given array has a value below the specified minimum.
     *
     * @param array $array The array to check.
     * @param int $min The minimum value to check against.
     * @return array An array containing the minimum value and a flag indicating if the value is exact.
     */
    private function _below_min($array, $min): array
    {
        return array($min => $array[$min], 'exact' => false);
    }

    /**
     * Performs a binary search to find the closest range for a given value.
     *
     * @param array $keys An array of keys to search through.
     * @param array $array The array of values corresponding to the keys.
     * @param mixed $value The value to search for.
     * @param int $s The starting index of the search range.
     * @param int $e The ending index of the search range.
     * @param mixed $min The minimum value in the search range.
     * @param mixed $max The maximum value in the search range.
     * @return array|bool An array with the closest range and a flag indicating if the match is exact, or false if no match is found.
     */
    private function _binary_search($keys, $array, $value, $s, $e, $min, $max): array|bool
    {
        $result = false;
        do {
            $guess = $s + (int)(($value - $min) / ($max - $min) * ($e - $s));
            if ($guess < $s) {
                $result = $keys[$s];
            } elseif ($guess > $e) {
                $result = $keys[$e];
            } elseif ($keys[$guess] > $value && $keys[$guess - 1] < $value) {
                $result = $this->_find_range($keys, $guess, $value);
            } elseif ($keys[$guess] < $value && $keys[$guess + 1] > $value) {
                $result = $this->_find_range($keys, $guess + 1, $value);
            } elseif ($keys[$guess] > $value) {
                $e = $guess - 1;
                $max = $keys[$e];
            } elseif ($keys[$guess] < $value) {
                $s = $guess + 1;
                $min = $keys[$s];
            }
        } while ($e != $s && $result === false);

        if ($result === false) {
            return false;
        }

        return array($result => $array[$result], 'exact' => false);
    }

    /**
     * Finds an exact match in the array for the given value.
     *
     * @param array $array The array to search in.
     * @param mixed $value The value to search for.
     * @return array An array containing the matched value and an 'exact' key set to true.
     */
    private function _exact_match($array, $value): array
    {
        return array($value => $array[$value], 'exact' => true);
    }

    /**
     * Finds the closest range value from the given keys based on the provided guess and value.
     *
     * @param array $keys An array of keys to search within.
     * @param int $guess The index to start the guess from.
     * @param mixed $value The value to compare against the keys.
     * @return mixed The closest key value to the provided value.
     */
    private function _find_range($keys, $guess, $value)
    {
        return (($value - $keys[$guess - 1]) < ($keys[$guess] - $value)
            ? $keys[$guess - 1]
            : $keys[$guess]);
    }

    /**
     * If a namespace was specified, prefixes the key with it
     *
     * For the file driver, namespaces will be actual folders
     *
     * @param	string	$key	Key name
     * @param	mixed	$scope	Cache::LOCAL_SCOPE or Cache::GLOBAL_SCOPE
     *		 for local or global scoping of the cache item
     * @return	string	Key prefixed with namespace
     */
    protected function _namespaced_key($key, $scope = \Cache::LOCAL_SCOPE)
    {
        // Make sure the key doesn't begin or end with a namespace separator or
        // directory separator to force the last segment of the key to be the
        // file name and so we can prefix a directory reliably
        $key = trim($key, \Cache::NAMESPACE_SEPARATOR . DIRECTORY_SEPARATOR);

        // Sometime class names are used as keys, replace class namespace
        // slashes with underscore to prevent filesystem issues
        $key = str_replace('\\', '_', $key);

        // Replace all namespace separators with the system's directory separator
        $key = str_replace(\Cache::NAMESPACE_SEPARATOR, DIRECTORY_SEPARATOR, $key);

        // For locally-cached items, separate by site name
        if ($scope == \Cache::LOCAL_SCOPE) {
            $key = (!empty(ee()->config->item('site_short_name')) ? ee()->config->item('site_short_name') . DIRECTORY_SEPARATOR : '') . $key;
        }

        return $key;
    }

    /**
     * Adds an 'exact' key with a value of false to the given array.
     *
     * @param array $array The input array to which the 'exact' key will be added.
     * @return array The modified array with the 'exact' key added.
     */
    private function _single_element($array): array
    {
        return array_merge($array, array('exact' => false));
    }


}
