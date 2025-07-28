<?php

/**
 * ImageUtility Service Traits - CacheManagementTrait
 * ==================================================
 * A collection of traits for the ImageUtility service
 * to manage cache operations.
 * =============================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.4.16.2.D3
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.4.16.D3
 */

namespace JCOGSDesign\Jcogs_img\Service\ImageUtilities\Traits;

trait CacheManagementTrait {

    /**
     * @var array Settings array from jcogs_img:Settings
     */
    protected array $settings;
    
    /**
     * Performance profiling system for debugging cache performance issues
     */
    private static array $performance_log = [];
    
    /**
     * Batch cache logging system for better performance
     */
    private static array $pending_cache_updates = [];
    private static bool $cache_update_scheduled = false;
    private static int $cache_operation_count = 0;
    private static int $max_cache_operations_per_request = 1000;
    private static array $request_cache = [];
    private static array $db_query_cache = [];
    private static bool $use_selective_loading = false;
    private static bool $loading_strategy_determined = false;

    // Cache configuration - converted from constants to static properties
    private static int $cache_ttl_week = 60 * 60 * 24 * 7;
    private static string $default_cache_dir = '.';
    private static int $initial_count = 1;
    private static int $initial_processing_time = 0;
    private static int $initial_size = 0;
    private static string $table_name = 'jcogs_img_cache_log';
    private static string $cache_key_audit = 'image_cache_audit';
    private static string $cache_key_status_info = '_cache_status_info';
    private static float $slow_operation_threshold = 0.1; // 100ms

    /**
     * Utility function: Scans cache folder and deletes images that have expired
     * Refactored for 1.4.16 for better performance and maintainability.
     *
     * @param  bool $force - run an audit even if one is not due / required
     * @param  string|null $location - specific location to audit
     * @return mixed
     */
    public function cache_audit(bool $force = false, ?string $location = null): mixed
    {
        $start_time = microtime(true);
        
        // Flush any pending cache updates before audit
        if (!empty(self::$pending_cache_updates)) {
            self::_flush_cache_updates_batch();
        }

        // Reset the update flag to ensure fresh state
        static::$image_log_needs_updating = false;

        try {
            // Use existing error handling wrapper pattern
            $result = $this->_execute_cache_operation(
                operation: fn() => $this->_perform_cache_audit($force, $location),
                operation_name: 'cache_audit',
                context: ['force' => $force, 'location' => $location]
            );

            $this->_monitor_performance($start_time, $location ?? 'all_locations');
            
            return $result;
            
        } catch (\Throwable $e) {
            ee('jcogs_img:Utilities')->debug_message("Critical error in cache_audit: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Returns a descriptive string indicating where the image cache is located
     * 
     * @return string Human-readable cache location description (local or cloud filesystem)
     */
    public function cache_location_string(): string
    {
        return static::$adapter_name != 'local' ? 'using the ' . $this::$cache_adapter_string . ' cloud filesystem' : 'locally on the server';
    }

    /**
     * Clean up orphaned cache entries that exist in the database but no longer have corresponding files
     * 
     * @return int Number of orphaned entries that were removed
     */
    public function cleanup_orphaned_cache_entries(): int
    {
        if (!ee()->db->table_exists(self::$table_name)) {
            return 0;
        }
        
        try {
            $removed_count = 0;
            
            // Get all entries grouped by base image pattern
            $all_entries = ee()->db->select('*')
                                ->from(self::$table_name)
                                ->where('site_id', static::$site_id)
                                ->where('adapter_name', static::$adapter_name)
                                ->get()
                                ->result_array();
            
            $grouped_entries = [];
            foreach ($all_entries as $entry) {
                $base_pattern = preg_replace('/_(lqip|thumb|small|medium|large)\.(jpg|jpeg|png|webp|gif)$/i', '', $entry['image_name']);
                $base_pattern = preg_replace('/\.(jpg|jpeg|png|webp|gif)$/i', '', $base_pattern);
                
                if (!isset($grouped_entries[$base_pattern])) {
                    $grouped_entries[$base_pattern] = [];
                }
                $grouped_entries[$base_pattern][] = $entry;
            }
            
            // For each group, keep only the latest entries and remove older ones
            foreach ($grouped_entries as $base_pattern => $entries) {
                if (count($entries) > 3) { // More than expected (main + 2 lqip variants)
                    // Sort by inception_date and keep only the 3 most recent
                    usort($entries, function($a, $b) {
                        $stats_a = json_decode($a['stats'], true);
                        $stats_b = json_decode($b['stats'], true);
                        return ($stats_b['inception_date'] ?? 0) <=> ($stats_a['inception_date'] ?? 0);
                    });
                    
                    $entries_to_remove = array_slice($entries, 3);
                    foreach ($entries_to_remove as $entry) {
                        ee()->db->where('id', $entry['id'])->delete(self::$table_name);
                        $removed_count++;
                    }
                }
            }
            
            // Clear static cache to force reload
            static::$cache_log_index = [];
            
            ee('jcogs_img:Utilities')->debug_message("Cleaned up {$removed_count} orphaned cache entries");
            return $removed_count;
            
        } catch (\Exception $e) {
            ee('jcogs_img:Utilities')->debug_message("Failed to cleanup orphaned entries: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clear database query cache
     * 
     * @return void
     */
    public static function clear_db_query_cache(): void
    {
        self::$db_query_cache = [];
    }
    
    /**
     * Clear cache information for a given cache path
     * 
     * @param string|null $cache_path The path to the cache directory to be cleared
     * @return bool True if the cache was cleared successfully, false otherwise
     */
    public function clear_cache_info(?string $cache_path = null): bool
    {
        if ($cache_path) {
            $cache_key = JCOGS_IMG_CLASS . '/' . 'cache_info' . '_' . static::$adapter_name . '_' . str_replace(search: '/', replace: '_', subject: trim(string: $cache_path, characters: '/'));
            try {
                ee('jcogs_img:Utilities')->cache_utility('delete', $cache_key);
                ee('jcogs_img:Utilities')->debug_message("Cache cleared for path: $cache_path");
                return true;
            } catch (\Exception $e) {
                ee('jcogs_img:Utilities')->debug_message("Failed to clear cache for path: $cache_path. Error: " . $e->getMessage());
                return false;
            }
        }
        ee('jcogs_img:Utilities')->debug_message("No cache path provided.");
        return false;
    }

    /**
     * Clear image cache for specified location or all locations
     * Removes cached images from the filesystem and database log
     *
     * @param string|null $location Specific cache location to clear, or null to clear all
     * @return bool|array Returns array of cache entries or bool indicating success
     */
    public function clear_image_cache($location = null): bool|array
    {
        $return = 'nothing_to_clear';
        $affected_records = 0;

        // Get a copy of cache_log
        $cache_entries = $this->get_file_info_from_cache_log() ?: [];

        // Do we have a specific location to clear?
        if($location) {
            $cache_locations = [$location];
            if(isset($cache_entries[static::$site_id][static::$adapter_name][$location]) && is_array($cache_entries[static::$site_id][static::$adapter_name][$location])) {
                $affected_records = count($cache_entries[static::$site_id][static::$adapter_name][$location]);
            }
            else {
                // No entries in this location so set to 0
                $affected_records = 0;
            }
        } else {
            // No location specified so clear all cache locations
            // Get an array of cache locations?
            $cache_locations = [];
            foreach ($cache_entries[static::$site_id][static::$adapter_name] as $cache_dir => $entry) {
                // Extract where the known cache folders are for this site / filesystem adapter
                if (! in_array(needle: $cache_dir, haystack: $cache_locations)) {
                    $cache_locations[] = $cache_dir;
                }    
                // Add total entries in cache_dir to the running total
                $affected_records += count($entry);
            }    

            // If we got nothing, pick up default location
            if (empty($cache_locations)) {
                $cache_locations = [$this->settings['img_cp_default_cache_directory']];
            }    
        }

        // After clearing cache, update the stored count
        if ($affected_records > 0) {
            $this->update_stored_cache_count();
        }

        // Clear image cache(s)
        foreach ($cache_locations as $cache_path) {
            $cache_path = trim(string: $cache_path, characters: '/');
            // Check we have a valid cache path in place
            if ($this->directoryExists(path: $cache_path)) {
                // Valid directory so delete it (and its contents)
                $this->deleteDirectory(path: $cache_path);
                // Also delete the directory content from the cache log
                $this->delete_cache_log($cache_path);
                // Set the return variable to true
                $return = 'success';
            }
        }

        // Present a message based on content of the $return variable
        switch ($return) {
            case 'nothing_to_clear':
                ee('CP/Alert')->makeInline('shared-form')
                    ->asWarning()
                    ->withTitle(lang('jcogs_img_cp_cache_nothing_to_clear'))
                    ->addToBody(lang('jcogs_img_cp_cache_nothing_to_clear_desc'))
                    ->defer();
                break;
            default:
                ee('CP/Alert')->makeInline('shared-form')
                    ->asSuccess()
                    ->withTitle(lang('jcogs_img_cp_cache_cleared'))
                    ->addToBody(sprintf(lang('jcogs_img_cp_cache_cleared_desc'), $affected_records))
                    ->defer();
                break;
        }    
        ee()->functions->redirect(ee('CP/URL', 'addons/settings/jcogs_img/caching'), 'refresh');

        return $cache_entries;
    }

    /**
     * Clear request-level cache (called at end of request or when needed)
     * 
     * @return void
     */
    public static function clear_request_cache(): void
    {
        self::$request_cache = [];
    }
    
    /**
     * Removes cache log for current filesystem
     *
     * @param string|null $path Specific path to delete from cache log, or null to delete all
     * @return bool True if cache log was deleted successfully, false otherwise
     */
    public function delete_cache_log(?string $path = null): bool
    {
        $success = false;

        // Get a copy of the cached version of the cache log
        $cache_key = ee('jcogs_img:Utilities')->getCacheKey(static::$site_id . '_' . static::$adapter_name . '_cache_status_info');
        $cache_status_info = ee()->cache->get($cache_key) ?: [];

        // If we have a path, get the file and cache directory info
        if ($path) {
            // Ensure $location has a leading slash and no trailing slash for consistent path comparison
            $normalized_location = trim(strtolower($path), '/');
        }

        // delete the static cache log entry
        if (!empty(static::$cache_log_index) && static::$cache_log_index) {
            // Check if we have a path to delete
            if ($path) {
                unset(static::$cache_log_index[static::$site_id][static::$adapter_name][$path]);
            } else {
            // No path given, so delete the whole cache log
                unset(static::$cache_log_index[static::$site_id][static::$adapter_name]);
            }
        }

        // delete the static cache locations entry and cache status info entry
        if (!empty(static::$cache_dir_locations) && static::$cache_dir_locations) {
            // Check to see if we have a path to delete
            if ($path) {
                foreach (static::$cache_dir_locations as $key => $entry) {
                    if (strpos(strtolower($entry), $normalized_location) === 0) {
                        unset(static::$cache_dir_locations[$key]);
                        // Also remove from the cache status info
                        if (isset($cache_status_info[static::$site_id][static::$adapter_name][$entry])) {
                            unset($cache_status_info[static::$site_id][static::$adapter_name][$entry]);
                        }
                    }
                }
            } else {
                // No path given, so delete the whole cache log
                static::$cache_dir_locations = null;
            }
        }

        // Now delete entries from the database
        if (ee()->db->table_exists('jcogs_img_cache_log')) {
            try {
                // delete the db entries for this site / adapter combination
                if(!empty($path)) {
                    ee()->db->like('path', $path);
                }
                ee()->db->delete('jcogs_img_cache_log', [
                    'site_id'      => ee()->config->item('site_id'),
                    'adapter_name' => static::$adapter_name
                ]);
                ee('jcogs_img:Utilities')->debug_message("Cache log deleted successfully.");
                $success = true;
            } catch (\Exception $e) {
                ee('jcogs_img:Utilities')->debug_message("Failed to delete cache log. Error: " . $e->getMessage());
            }
        }
        // Now update the disk version of the cache log
        if ($success) {
            ee()->cache->save($cache_key, $cache_status_info, 60 * 60 * 24 * 7);
        } else {
            ee('jcogs_img:Utilities')->debug_message("Cache log table does not exist.");
        }
        return $success;
    }

    /**
     * Removes an entry from cache log for current filesystem
     *
     * @param string|null $image_path Path to the image file to remove from cache log
     * @return bool True if entry was deleted successfully, false otherwise
     */
    public function delete_cache_log_entry(?string $image_path = null): bool
    {
        // No path or no cache log? bale out
        if (! $image_path)
            return false;

        // Check to see file exists, delete it if so
        if ($this->exists(path: $image_path)) {
            $this->delete(path: $image_path);
        }

        // Check to see lower case version of file exists, delete it if so
        if ($this->exists(path: strtolower(string: $image_path))) {
            $this->delete(path: strtolower(string: $image_path));
        }

        // Strip out all but image name from $image_path
        $filename = pathinfo(path: $image_path, flags: PATHINFO_BASENAME);
        $cache_dir = pathinfo(path: $image_path, flags: PATHINFO_DIRNAME);

        // Remove from static cache log index (if it exists)
        if (isset(static::$cache_log_index) && static::$cache_log_index) {
            unset(static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir][$filename]);
            unset(static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir][strtolower(string: $filename)]);
        }

        if (ee()->db->table_exists('jcogs_img_cache_log')) {
            try {
                // delete the cache log for current site id / filesystem adapter
                ee()->db->delete('jcogs_img_cache_log', [
                    'site_id'      => static::$site_id,
                    'adapter_name' => static::$adapter_name,
                    'path'         => trim(string: $image_path, characters: '/'),
                    'image_name'   => $filename
                ]);
    
                // Did that work? If not try again with lowercased image path and filename
                if (ee()->db->affected_rows() == 0) {
                    ee()->db->delete('jcogs_img_cache_log', [
                        'site_id'      => static::$site_id,
                        'adapter_name' => static::$adapter_name,
                        'path'         => strtolower(string: trim(string: $image_path, characters: '/')),
                        'image_name'   => strtolower(string: $filename)
                    ]);
                }
    
                // ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_cache_log_deleted'), $image_path);
                return true;
            } catch (\Exception $e) {
                ee('jcogs_img:Utilities')->debug_message(sprintf('jcogs_img_cache_log_delete_failed', $image_path, $e->getMessage()));
                return false;
            }
        }
    
        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_cache_log_table_not_exist'));
        return false;
    }

    /**
     * Gets stats details of an entry from cache log for current filesystem
     *
     * @param string|null $image_path Path to the image file to get stats for
     * @return bool|array Array of stats data if found, false otherwise
     */
   public function get_cache_log_stats(?string $image_path = null): bool|array
    {
        if (empty($image_path)) {
            return false;
        }

        $trimmed_image_path = trim($image_path, '/');
        $path_parts = pathinfo($trimmed_image_path);
        $cache_dir = $path_parts['dirname'] ?? '.';
        $filename = $path_parts['basename'];

        if (empty($filename)) {
            return false;
        }

        // Check static cache first
        if (isset(static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir][$filename])) {
            $entry = static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir][$filename];
            if (property_exists($entry, 'stats') && !empty($entry->stats)) {
                $decoded_stats = json_decode($entry->stats, true);
                // ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_cache_log_vars_hit_static'), $trimmed_image_path);
                return $decoded_stats ? [$decoded_stats] : false; 
            }
        }

        // If not in static cache, try to get the entry from the database
        $file_info_array = $this->get_file_info_from_cache_log($trimmed_image_path); 
        
        if (!empty($file_info_array) && isset($file_info_array[$filename])) {
            $entry = $file_info_array[$filename];
            if (property_exists($entry, 'stats') && !empty($entry->stats)) {
                $decoded_stats = json_decode($entry->stats, true);
                // Static cache was already updated by get_file_info_from_cache_log
                // ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_cache_log_vars_from_db'), $trimmed_image_path);
                return $decoded_stats ? [$decoded_stats] : false; 
            }
        }
        // ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_cache_log_vars_not_found'), $trimmed_image_path);
        return false;
    }

    /**
     * Gets vars details of an entry from cache log for current filesystem
     *
     * @param string|null $image_path Path to the image file to get vars for
     * @return bool|array Array of variables data if found, false otherwise
     */
   public function get_cache_log_vars(?string $image_path = null): bool|array
    {
        if (empty($image_path)) {
            return false;
        }

        $trimmed_image_path = trim($image_path, '/');
        $filename_key_from_path = pathinfo($trimmed_image_path, PATHINFO_BASENAME);

        if (empty($filename_key_from_path)) {
            return false;
        }

        // Rely on get_file_info_from_cache_log to handle static caching and DB lookup.
        // It returns an array like [$filename => $entry_object] or an empty array [].
        $file_info_result = $this->get_file_info_from_cache_log($trimmed_image_path); 
        
        // Check if the result is not empty and the specific filename exists as a key
        if (!empty($file_info_result) && isset($file_info_result[$filename_key_from_path])) {
            $entry = $file_info_result[$filename_key_from_path];
            if (property_exists($entry, 'values') && !empty($entry->values)) {
                $decoded_values = json_decode($entry->values, true);
                // Return in the format expected by callers: [0 => (array)$decoded_values]
                // Ensure json_decode was successful before wrapping.
                return $decoded_values !== null ? [$decoded_values] : false;
            }
        }
        // ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_cache_log_vars_not_found'), $trimmed_image_path);
        return false;
    }

    /**
     * Get current count of cache log entries for this site/adapter
     * 
     * @return int Current count of cache log entries
     */
    public function get_current_cache_log_count(): int
    {
        if (!ee()->db->table_exists(self::$table_name)) {
            return 0;
        }
        
        try {
            $count_query = ee()->db->select('COUNT(*) as total')
                ->from(self::$table_name)
                ->where('site_id', static::$site_id)
                ->where('adapter_name', static::$adapter_name)
                ->get();
            
            return (int)$count_query->row()->total;
            
        } catch (\Exception $e) {
            ee('jcogs_img:Utilities')->debug_message("Failed to get cache log count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Extract cache duration from filename using configured separator pattern
     *
     * @param string $image_filename The image filename to parse
     * @param int|null $default_cache_duration Default duration to use if none found in filename
     * @return int|bool Cache duration in seconds, -1 for perpetual, or false if invalid
     */
    public function get_file_cache_duration(string $image_filename, ?int $default_cache_duration = null): bool|int
    {

        $default_cache_duration = $default_cache_duration ?: $this->settings['img_cp_default_cache_duration'];
        $cache_duration_tag     = explode($this->settings['img_cp_default_filename_separator'], $image_filename);
        $cache_duration_when_saved = null;

        if (count($cache_duration_tag)) {
            // Start from last element found and look for first one that looks like a cache duration... 
            for ($i = count($cache_duration_tag) - 1; $i >= 0; $i--) {
                if (isset($cache_duration_tag[$i]) && ctype_xdigit($cache_duration_tag[$i])) {
                    $cache_duration_when_saved = $cache_duration_tag[$i] == 'abcdef' ? -1 : hexdec($cache_duration_tag[$i]);
                    break;
                }
            }
        }
        if (! isset($cache_duration_when_saved)) {
            $cache_duration_when_saved = $default_cache_duration;
        }
        return is_int($cache_duration_when_saved) ? $cache_duration_when_saved : false;
    }

    /**
     * Gets info from cache_log db table
     * Enhanced for 1.4.16 for better performance and maintainability.
     * If $path is specified, only files where image_name has same stub as $path value are returned
     * If $path is not specified, all file records are returned.
     * 
     * @param string|null $path Optional path to filter results
     * @return array Array of cache log entries, empty array if none found
     */
    public function get_file_info_from_cache_log(?string $path = null): array
    {
        $profile_id = $this->_profile_cache_method_start('get_file_info_from_cache_log');
        
        try {
            // If we're using selective loading and have a specific path
            if (self::$use_selective_loading && !empty($path)) {
                return $this->_get_selective_cache_entry($path);
            }
            
            // Otherwise use the existing full-load approach
            return $this->_perform_cache_log_retrieval($path);
            
        } catch (\Throwable $e) {
            ee('jcogs_img:Utilities')->debug_message("Error in get_file_info_from_cache_log: " . $e->getMessage());
            $this->_profile_cache_method_end($profile_id);
            return [];
        }
    }

    /**
     * Generate the HTML needed to show the image cache control table
     * 
     * @return string Generated HTML for cache control table
     */
    public function get_image_cache_control_table()
    {
        $vars['base_url'] = ee('CP/URL', 'addons/settings/jcogs_img/caching');
        $cache_audit_results = [];

        // First up check to see if we have any bulk actions to execute
        $bulk_action = ee()->input->post('bulk_action');
        if ($bulk_action) {
            switch ($bulk_action) {
                case 'audit':
                    // We need to audit the chosen cache locations
                    $cache_audit_results = $this->cache_audit(force: true);
                    break;
                case 'clear':
                    // We need to clear the chosen cache locations
                    $this->clear_image_cache();
                    break;
            }
        }

        // Now check to see if there are specific cache location actions to carry out
        if ($audit_cache_location = ee()->input->get('audit')) {
            // We need to audit the chosen cache location
            $cache_audit_results = $this->cache_audit(force: true, location: $audit_cache_location);
        }

        if ($clear_cache_location = ee()->input->get('clear')) {
            // We need to clear the chosen cache location
            $this->clear_image_cache(location: $clear_cache_location);
        }

        // If we did either bulk or specific actions these would have reloaded the page after operation.
        // If we get here, it means we are not doing anything, so we need to generate the table showing the cache locations

        // How this works:
        // - Get a list of current cache Locations
        // - Get a list and count of the files in each cache location
        // - Generate a table with one row per cache location
        // - Each row has links to the cache location with options to clear or audit cache

        // Summary cache information is cached ... so see if we have any info already saved
        $cache_key = ee('jcogs_img:Utilities')->getCacheKey(static::$site_id . '_' . static::$adapter_name . '_cache_status_info');
        $cache_status_info = ee()->cache->get($cache_key) ?: [];

        // We might already have a static variable with cache locations, if so use it, otherwise make a new one
        if (empty(static::$cache_dir_locations)) {
            static::$cache_dir_locations = [];
            // Check to see if we already have this list in $cache_status_info
            if(!empty($cache_status_info['location_list'])) {
                static::$cache_dir_locations = $cache_status_info['location_list'];
            } else {
                // Scan the content of cache_status_info to see if we have any cache locations
                if(is_array($cache_status_info) && isset($cache_status_info[static::$site_id][static::$adapter_name])) {
                    foreach ($cache_status_info[static::$site_id][static::$adapter_name] as $location => $values) {
                    // We have some cache locations, so use them
                        // Only add the location if it has something in it
                        if(count($values) > 0) {
                            static::$cache_dir_locations[] = $location;
                        }
                    }
                } else {
                    // We don't have any cache locations, but we know there is a default location so use that
                    static::$cache_dir_locations[] = ee('jcogs_img:Settings')::$settings['img_cp_default_cache_directory'];
                }
            }
        }

        // Setup a variable to hold count of total number of files in cache
        $total_files = 0;
        //  Setup a data array to hold the cache location data
        $data = [];
        // Get a total for number of files currently in cache directories
        foreach (static::$cache_dir_locations as $location) {
            $dirList = $this->directoryList($location) ?: [];
            if(count($dirList) > 0) {
                // We have some files in the directory
                $data[$location] = $dirList;
                $total_files += count($data[$location]);
            } else {
                // No files in the directory
                // So don't add it to the data array;
                // But do remove it from the static cache log index
                if (isset(static::$cache_log_index[static::$site_id][static::$adapter_name][$location])) {
                    unset(static::$cache_log_index[static::$site_id][static::$adapter_name][$location]);
                }
                // Also remove it from the static cache dir locations
                if (($key = array_search($location, static::$cache_dir_locations)) !== false) {
                    unset(static::$cache_dir_locations[$key]);
                }
            }
        }

        // Now we have a list of cache locations and the number of files in each, let's generate the HTML
        $cache_locations = [];
        $i = 1;
        foreach ($data as $location => $values) {
            $audit_url = ee('CP/URL')->make('addons/settings/jcogs_img/caching?' . 'audit=' . $location);
            $clear_url = ee('CP/URL')->make('addons/settings/jcogs_img/caching?' . 'clear=' . $location);
            // Sort out if we are showing the cache audit button or not
            if($this->settings['img_cp_enable_cache_audit'] == 'y') {
                // We are showing the cache audit button
                $audit_entry = [
                    'href' => $audit_url,
                    'title' => lang('jcogs_img_cp_cache_location_audit'),
                    'content' => ' ' . lang('jcogs_img_cp_cache_location_audit_icon')
                ];
            } else {
                $audit_entry = [];
            }

            $cache_locations[] = [
                'id' => $i++,
                // 'path' => \htmlspecialchars((string) $location),
                'htmlLabel' => 1,
                'label' => '<p>Cache location: ' . \htmlspecialchars((string) $location) . '</p>',
                'href' => 'javascript:void(0);',
                'status_info' => sprintf(lang('jcogs_img_cache_location_status'), count($values), count($values) > 1 ? 's' : ''),
                'toolbar_items' => [
                    'xsync' => $audit_entry,
                    'xclear' => [
                        'href' => $clear_url,
                        'title' => lang('jcogs_img_cp_cache_location_clear'),
                        'content' => ' ' . lang('jcogs_img_cp_cache_location_clear_icon')
                    ]
                ]
            ];
        }

        $vars['cache_location_title'] = lang('jcogs_img_cp_cache_location_title');
        $vars['cache_intro_text'] = lang('jcogs_img_cp_cache_intro_text');
        $vars['cache_locations'] = $cache_locations;
        $vars['base_url'] = ee('CP/URL', 'addons/settings/jcogs_img/caching');

        // Check to see if we have anything, if not put up a no-results message
        $vars['no_results']['text'] = count($data) > 0 ? '' : lang('jcogs_img_cp_cache_control_no_results');

        $view = ee('View')->make('jcogs_img:cache_status');
        $return = $view->render($vars);
        return $return;
    }

    /**
     * Get image info from cache log
     * 
     * @param string|null $location Optional specific location to get info for
     * @return array Cache information including statistics and performance data
     */
    public function get_image_cache_info($location = null)
    {
        // Get the cache status info from the cache log
        // First, refresh the copy of the cache log index - we don't care about the return value as the array is static and is set also
        $file_info = $this->get_file_info_from_cache_log(); // Populates static::$cache_log_index
        static::$cache_dir_locations = [];

        // Initialize global summary statistics
        $log_earliest_inception = time();
        $log_cumulative_count = 0; // Sum of all tracker_stats['count'] (e.g., hits or generations)
        $log_cumulative_filesize = 0; // Sum of all tracker_stats['size']
        $log_cumulative_processing_time = 0;
        
        $cache_status_info = []; // This will hold per-directory stats under [site_id][adapter_name][cache_dir]
                                  // and a global 'count' for total physical files.

        // Initialize the global physical file count (used for 'number_of_cache_fragments')
        $cache_status_info['count'] = 0;

        // Check if the relevant part of static::$cache_log_index is populated
        if (isset(static::$cache_log_index[static::$site_id][static::$adapter_name]) && 
            is_array(static::$cache_log_index[static::$site_id][static::$adapter_name])) {

            // Ensure base structure for $cache_status_info for the current site/adapter's directory stats
            if (!isset($cache_status_info[static::$site_id])) {
                $cache_status_info[static::$site_id] = [];
            }
            if (!isset($cache_status_info[static::$site_id][static::$adapter_name])) {
                $cache_status_info[static::$site_id][static::$adapter_name] = [];
            }

            // Iterate over each cache directory found in the log
            foreach (static::$cache_log_index[static::$site_id][static::$adapter_name] as $cache_dir => $logged_files_in_dir) {
                // Track this cache directory path for later use (e.g., in get_image_cache_control_table)
                if (!in_array($cache_dir, static::$cache_dir_locations)) {
                    static::$cache_dir_locations[] = $cache_dir;
                }
                
                // Initialize statistics for this specific $cache_dir within $cache_status_info
                if (!isset($cache_status_info[static::$site_id][static::$adapter_name][$cache_dir])) {
                    $cache_status_info[static::$site_id][static::$adapter_name][$cache_dir] = [
                        'inception_date' => 0,
                        'count' => 0, // Sum of tracker_stats['count'] for this directory
                        'cumulative_filesize' => 0,
                        'cumulative_processing_time' => 0
                    ];
                }
                
                // Count actual physical files in this directory and add to global physical file count
                $physical_files_in_dir_list = $this->directoryList($cache_dir) ?: [];
                $cache_status_info['count'] += count($physical_files_in_dir_list); 

                if (empty($physical_files_in_dir_list)) {
                    // No physical files in the directory, so remove it from static::$cache_dir_locations if it was added
                    // This ensures static::$cache_dir_locations only contains directories with actual files for UI purposes.
                    if (($key = array_search($cache_dir, static::$cache_dir_locations)) !== false) {
                        unset(static::$cache_dir_locations[$key]);
                    }
                    // Optionally, you might want to 'continue' here if there are no physical files,
                    // or still process logged entries if they might be orphaned.
                    // Current logic processes logged_files_in_dir regardless.
                }
                
                // Process each logged file in this directory
                foreach ($logged_files_in_dir as $filename => $log_data_for_file) {
                    // Ensure $log_data_for_file is an object and has 'stats' property
                    if (!is_object($log_data_for_file) || !property_exists($log_data_for_file, 'stats')) {
                        continue; // Skip if data is not as expected
                    }
                    $tracker_stats = json_decode($log_data_for_file->stats, true) ?: [];
                    
                    if (!empty($tracker_stats)) {
                        $current_dir_level_stats =& $cache_status_info[static::$site_id][static::$adapter_name][$cache_dir];

                        // Update directory-specific inception date
                        if (isset($tracker_stats['inception_date']) && $tracker_stats['inception_date']) {
                            if ($current_dir_level_stats['inception_date'] == 0 || 
                                $tracker_stats['inception_date'] < $current_dir_level_stats['inception_date']) {
                                $current_dir_level_stats['inception_date'] = $tracker_stats['inception_date'];
                            }
                        }
                        
                        // Update directory-specific counts and sums from tracker_stats
                        $current_dir_level_stats['count'] += isset($tracker_stats['count']) ? (int)$tracker_stats['count'] : 0;
                        $current_dir_level_stats['cumulative_filesize'] += isset($tracker_stats['size']) ? (float)$tracker_stats['size'] : 0;
                        $current_dir_level_stats['cumulative_processing_time'] += isset($tracker_stats['processing_time']) ? (float)$tracker_stats['processing_time'] : 0;
                        
                        // Update global log summary statistics (these are separate from the $cache_status_info per-directory structure)
                        if (isset($tracker_stats['inception_date']) && $tracker_stats['inception_date'] && $tracker_stats['inception_date'] < $log_earliest_inception) {
                            $log_earliest_inception = $tracker_stats['inception_date'];
                        }
                        $log_cumulative_count += isset($tracker_stats['count']) ? (int)$tracker_stats['count'] : 0;
                        $log_cumulative_filesize += isset($tracker_stats['size']) ? (float)$tracker_stats['size'] : 0;
                        $log_cumulative_processing_time += isset($tracker_stats['processing_time']) ? (float)$tracker_stats['processing_time'] : 0;
                    }
                } 
            }
            // Now save the compiled $cache_status_info (which includes per-directory stats and the global 'count') to the EE cache
            $ee_cache_key = ee('jcogs_img:Utilities')->getCacheKey(static::$site_id . '_' . static::$adapter_name . '_cache_status_info');
            ee()->cache->save($ee_cache_key, $cache_status_info, 60 * 60 * 24 * 7); // Save for a week
        }

        // Set some basic cache performance information for the return value
        $return_info                              = [];
        $return_info['inception_date']            = '';
        $return_info['number_of_cache_fragments'] = '';
        $return_info['number_of_cache_hits']      = '';
        $return_info['cumulative_filesize']       = '';
        $return_info['cache_performance_desc']    = lang('jcogs_img_image_cache_is_empty');
        $return_info['cache_clear_button_desc']   = lang('jcogs_img_image_cache_is_empty');

        // Get information on currently cached images (if any) from cache_log
        $return_info['caches_found'] = count(static::$cache_dir_locations);
        $return_info['inception_date'] = ($log_earliest_inception === time() && $cache_status_info['count'] === 0) ? 0 : $log_earliest_inception;
        $return_info['number_of_cache_fragments'] = $cache_status_info['count']; // Total physical files
        $return_info['number_of_cache_hits'] = $log_cumulative_count;
        $return_info['cumulative_filesize'] = $log_cumulative_filesize;
        $return_info['cumulative_processing_time'] = $log_cumulative_processing_time;

        if ($return_info['number_of_cache_fragments'] > 0) {
            $desc_key = $return_info['number_of_cache_fragments'] == 1 ? 'jcogs_img_cp_cache_performance_desc_cache_one_fragment' : 'jcogs_img_cp_cache_performance_desc_cache';
            // Assuming 'jcogs_img_cp_cache_performance_desc_cache_one_fragment' is like '... {0} fragment in ...'
            // And 'jcogs_img_cp_cache_performance_desc_cache' is like '... {0} fragments in ...'
            
            $locations_desc_key = $return_info['caches_found'] > 1 ? 'jcogs_img_cp_cache_performance_desc_cache_many' : 'jcogs_img_cp_cache_performance_desc_cache_single';
            // Assuming 'jcogs_img_cp_cache_performance_desc_cache_many' is like '{0} cache locations'
            // And 'jcogs_img_cp_cache_performance_desc_cache_single' is like 'a single cache location'

            $return_info['cache_performance_desc']  = [
                'desc' => sprintf(
                    lang($desc_key),
                    $return_info['number_of_cache_fragments'],
                    $return_info['caches_found'] > 1 ? sprintf(lang($locations_desc_key), $return_info['caches_found']) : lang($locations_desc_key),
                    $return_info['number_of_cache_hits'],
                    ee('jcogs_img:Utilities')->formatBytes($return_info['cumulative_filesize']),
                    $return_info['inception_date'] ? ee('jcogs_img:Utilities')->date_difference_to_now($return_info['inception_date']) : lang('jcogs_img_na'),
                    $return_info['cumulative_processing_time'] >= 1 ? round($return_info['cumulative_processing_time'], 0) . ' seconds' : round($return_info['cumulative_processing_time'], 2) . ' seconds',
                    $this->cache_location_string(),
                    lang('jcogs_img_cp_cache_performance_desc_cache_operational')
                )
            ];
            $return_info['cache_clear_button_desc'] = [
                'title' => 'jcogs_img_cp_cache_clear',
                'desc'  =>
                    sprintf(lang('jcogs_img_cp_cache_clear_desc'), $return_info['number_of_cache_fragments']) . PHP_EOL .
                    sprintf(lang('jcogs_img_cp_cache_clear_button'), ee('CP/URL', 'addons/settings/jcogs_img/clear_image_cache'), '')
            ];
        } else {
            $return_info['cache_performance_desc']  = ['desc' => lang('jcogs_img_image_cache_is_empty')];
            $return_info['cache_clear_button_desc'] = [
                'title' => 'jcogs_img_cp_cache_clear',
                'desc'  => lang('jcogs_img_cp_cache_clear_desc_empty') . PHP_EOL .
                           sprintf(lang('jcogs_img_cp_cache_clear_button'), ee('CP/URL', 'addons/settings/jcogs_img/clear_image_cache'), 'disabled')
            ];
        }
        return $return_info;
    }

    /**
     * Check if an image is currently cached and valid
     * Enhanced for 1.4.16 for better performance and maintainability.
     *
     * @param string $image_path Path to the image file to check
     * @return bool True if image is cached and valid, false otherwise
     */
    public function is_image_in_cache(string $image_path): bool
    {
        $profile_id = $this->_profile_cache_method_start('is_image_in_cache');
        
        try {
            // Input validation
            if (!$this->_validate_cache_check_inputs($image_path)) {
                $this->_profile_cache_method_end($profile_id);
                return false;
            }
            
            // Early exit for explicitly disabled caching
            if ($this->_is_caching_explicitly_disabled()) {
                $this->_profile_cache_method_end($profile_id);
                return false;
            }
            
            // CRITICAL: Ensure cache is preloaded ONCE per request
            static $preload_called = false;
            if (!$preload_called) {
                $this->preload_cache_log_index();
                $preload_called = true;
            }
            
            // Use existing error handling wrapper pattern
            $result = $this->_execute_cache_operation(
                operation: fn() => $this->_perform_cache_validity_check($image_path),
                operation_name: 'is_image_in_cache',
                context: ['image_path' => $image_path]
            );
            
            $this->_profile_cache_method_end($profile_id);
            return (bool) $result;
            
        } catch (\Throwable $e) {
            $this->_profile_cache_method_end($profile_id);
            ee('jcogs_img:Utilities')->debug_message("Critical error in is_image_in_cache: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Preloads the cache log index to optimize performance for subsequent cache operations.
     * 
     * This method initializes and loads the cache log index into memory, improving
     * the efficiency of cache lookups and management operations by avoiding
     * repeated disk I/O operations.
     * 
     * @return void
     */
    public function preload_cache_log_index(): void
    {
        // Only determine strategy once per request
        if (self::$loading_strategy_determined) {
            return;
        }
        
        // Determine loading strategy based on stored count vs threshold
        $stored_count = (int)($this->settings['img_cp_cache_log_current_count'] ?? 0);
        $threshold = (int)($this->settings['img_cp_cache_log_preload_threshold'] ?? 10000);
        
        if ($stored_count <= $threshold) {
            // Use preload strategy for smaller datasets
            self::$use_selective_loading = false;
            $count = $this->_preload_full_cache_index();
            ee('jcogs_img:Utilities')->debug_message(
                sprintf("Using preload strategy: %d images found in processed image caches on this Filesystem adapter (threshold: %d)", $count, $threshold)
            );
        } else {
            // Use selective loading for larger datasets
            self::$use_selective_loading = true;
            
            // Initialize empty cache structure
            if (!isset(static::$cache_log_index[static::$site_id])) {
                static::$cache_log_index[static::$site_id] = [];
            }
            if (!isset(static::$cache_log_index[static::$site_id][static::$adapter_name])) {
                static::$cache_log_index[static::$site_id][static::$adapter_name] = [];
            }
            
            ee('jcogs_img:Utilities')->debug_message(
                sprintf("Using selective loading: %d entries (threshold: %d)", $stored_count, $threshold)
            );
        }
        
        self::$loading_strategy_determined = true;
    }
    
    /**
     * Update image cache log for nominated image
     * Refactored for 1.4.16 for better performance and maintainability.
     *
     * @param string $image_path Path to the image being cached
     * @param float|null $processing_time Time taken to process the image
     * @param array|null $vars Variables associated with the processed image
     * @param string|null $cache_dir Cache directory override
     * @param string|null $source_path Original source path of the image
     * @param bool $force_update Force update even if entry exists
     * @param bool $using_cache_copy Whether this image was loaded from cache
     * @return bool True if cache log was updated successfully, false otherwise
     */
    public function update_cache_log(string $image_path, ?float $processing_time = null, ?array $vars = null, ?string $cache_dir = null, ?string $source_path = null, bool $force_update = false, bool $using_cache_copy = false): bool
    {
        if (self::$use_selective_loading) {
            // For selective loading, always use immediate database updates
            return $this->_perform_immediate_selective_update($image_path, $processing_time, $vars, $cache_dir, $source_path, $force_update, $using_cache_copy);
        } else {
            // Use existing batch update approach for smaller datasets
            return $this->_perform_cache_log_update($image_path, $processing_time, $vars, $cache_dir, $source_path, $force_update);
        }
    }

    /**
     * Update the stored cache count in settings
     * Called after bulk cache operations that significantly change the count
     * 
     * @return bool Success status
     */
    public function update_stored_cache_count(): bool
    {
        try {
            $current_count = $this->get_current_cache_log_count();
            $updated_settings = array_merge($this->settings, [
                'img_cp_cache_log_current_count' => $current_count,
                'img_cp_cache_log_count_last_updated' => time()
            ]);
            
            return ee('jcogs_img:Settings')->save_settings($updated_settings);
            
        } catch (\Exception $e) {
            ee('jcogs_img:Utilities')->debug_message("Failed to update stored cache count: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Destructor to ensure any pending transactions and cache updates are completed
     */
    public function __destruct()
    {
        // Guard against destructor loops during active processing
        static $destructor_running = false;
        
        if ($destructor_running) {
            return; // Prevent recursive destructor calls
        }
        
        $destructor_running = true;
        
        try {
            // Only commit if we have an active transaction
            if (ee()->db && ee()->db->trans_status() !== false && ee()->db->trans_status() !== true) {
                ee()->db->trans_complete();
            }
            
            // Flush pending cache updates only if there are any
            if (!empty(self::$pending_cache_updates)) {
                self::_flush_cache_updates_batch();
            }
            
            // Clear request-level cache
            self::clear_request_cache();
            
            // Clear database query cache
            self::clear_db_query_cache();
            
        } catch (\Exception $e) {
            // Log error but don't throw from destructor
            error_log("Cache destructor error: " . $e->getMessage());
        } finally {
            $destructor_running = false;
        }
    }

    /**
     * Audit a specific cache location for expired files
     * 
     * @param string $location The cache location to audit
     * @return int Number of files removed, or -1 on error
     */
    private function _audit_location(string $location): int
    {
        $files_removed_in_location = 0;
        
        try {
            // Get list of files in the directory
            $location_files = $this->directoryList($location);
            
            if (!$location_files) {
                return 0;
            }
            
            foreach ($location_files as $location_file) {
                $audit_result = $this->_audit_single_file($location_file, $location);
                
                if ($audit_result === 'removed') {
                    $files_removed_in_location++;
                }
            }
            
            return $files_removed_in_location;
            
        } catch (\Exception $e) {
            ee('jcogs_img:Utilities')->debug_message("Error auditing location {$location}: " . $e->getMessage());
            return -1; // Error indicator
        }
    }

    /**
     * Audit a single file for cache validity
     * 
     * @param array $location_file File information array from directory listing
     * @param string $location Cache location path
     * @return string Action taken: 'removed', 'updated', or error status
     */
    private function _audit_single_file(array $location_file, string $location): string
    {
        $file_path = $location_file['path'];
        
        // Get cache duration for this file
        $cache_duration_when_saved = $this->get_file_cache_duration($file_path);
        
        // Check if file should still be cached
        $is_valid = $this->_is_file_cache_valid($cache_duration_when_saved, $location_file);
        
        if (!$is_valid) {
            // File is invalid - remove it
            $this->delete_cache_log_entry($file_path);
            return 'removed';
        } else {
            // File is valid - ensure it's in the cache log
            $this->update_cache_log(image_path: $file_path, cache_dir: $location, force_update: true);
            return 'updated';
        }
    }

    /**
     * Batch update static cache for improved performance
     * 
     * @param array $batch_update_data Array of processed entries to update in static cache
     * @return void
     */
    private function _batch_update_static_cache(array $batch_update_data): void
    {
        foreach ($batch_update_data as $processed_entry) {
            $cache_dir = $processed_entry['cache_dir'];
            $filename = $processed_entry['filename'];
            $entry = $processed_entry['entry'];
            
            // Ensure cache directory structure exists
            if (!isset(static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir])) {
                static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir] = [];
            }
            
            // Update static cache
            static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir][$filename] = $entry;
        }
    }

    /**
     * Build optimized database query with request-level caching
     * 
     * @param array $normalized_path_data Normalized path data for query building
     * @return \CI_DB_result|null Database query result or null if error
     */
    private function _build_optimized_database_query(array $normalized_path_data): ?\CI_DB_result
    {
        // Check request-level cache first to prevent duplicate queries
        if ($normalized_path_data['is_specific_path']) {
            $cache_key = static::$site_id . '_' . static::$adapter_name . '_' . $normalized_path_data['trimmed_path'];
            
            if (isset(self::$request_cache[$cache_key])) {
                // Return cached result - create a mock result object
                return $this->_create_mock_result_from_cache(self::$request_cache[$cache_key]);
            }
        }
        
        $query_builder = ee()->db->from(self::$table_name)
            ->where('site_id', static::$site_id)
            ->where('adapter_name', static::$adapter_name);
        
        // Add path filter for specific requests
        if ($normalized_path_data['is_specific_path']) {
            $query_builder->where('path', $normalized_path_data['trimmed_path']);
            // Also limit to a single result for specific path requests
            $query_builder->limit(1);
        }
        
        // Add ordering for consistent results
        $query_builder->order_by('path', 'ASC');
        
        $result = $query_builder->get();
        
        // Cache the result for this request if it's a specific path
        if ($normalized_path_data['is_specific_path'] && $result && $result->num_rows() > 0) {
            $cache_key = static::$site_id . '_' . static::$adapter_name . '_' . $normalized_path_data['trimmed_path'];
            self::$request_cache[$cache_key] = $result->result();
        }
        
        return $result;
    }

    /**
     * Build tracker statistics array
     * 
     * @param string $original_path Original path to the image file
     * @param float|null $processing_time Time taken to process the image
     * @param string|null $source_path Original source path of the image
     * @return array Array of tracker statistics
     */
    private function _build_tracker_stats(string $original_path, ?float $processing_time, ?string $source_path): array
    {
        $file_size = $this->filesize($original_path) ?: self::$initial_size;
        $effective_processing_time = $processing_time ?: self::$initial_processing_time;
        
        return [
            'inception_date' => time(),
            'count' => self::$initial_count,
            'size' => $file_size,
            'processing_time' => $effective_processing_time,
            'cumulative_size' => $file_size,
            'cumulative_processing_time' => $effective_processing_time,
            'sourcepath' => $source_path ?: '',
        ];
    }

    /**
     * Check if cache entry already exists
     * 
     * @param array $normalized_data Normalized cache data array
     * @return bool True if cache entry exists, false otherwise
     */
    private function _cache_entry_exists(array $normalized_data): bool
    {
        // Don't trust cache during active processing
        if (static::$image_log_needs_updating) {
            return false;
        }
        
        // Ensure static cache structure exists
        $this->_ensure_static_cache_structure_for_retrieval();
        
        // Check if entry exists in static cache
        $cache_dir = $normalized_data['cache_dir'];
        $filename = strtolower($normalized_data['filename']);
        
        return isset(static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir][$filename]);
    }

    /**
     * Check static cache for specific path requests
     * 
     * @param array $normalized_path_data Normalized path data for cache lookup
     * @return array|null Cache entry array if found, null if not found
     */
    private function _check_static_cache(array $normalized_path_data): ?array
    {
        $cache_dir = $normalized_path_data['cache_dir'];
        $filename = $normalized_path_data['filename'];
        
        if (isset(static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir][$filename])) {
            // Cache hit - return single entry
            return [$filename => static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir][$filename]];
        } else {
            // Create an empty entry if it doesn't exist
            [$filename => static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir][$filename]] = null;
        }
        
        return null; // Not found in static cache
    }

    /**
     * Clean up orphaned cache log entries (files in log but not on disk)
     * 
     * @param array $audit_locations Array of cache locations to audit
     * @return void
     */
    private function _clean_orphaned_cache_entries(array $audit_locations): void
    {
        foreach ($audit_locations as $location) {
            if (!isset(static::$cache_log_index[static::$site_id][static::$adapter_name][$location])) {
                continue;
            }
            
            $cache_entries = static::$cache_log_index[static::$site_id][static::$adapter_name][$location];
            
            foreach ($cache_entries as $filename => $cache_entry) {
                if (!property_exists($cache_entry, 'path')) {
                    continue;
                }
                
                // Check if file exists on disk
                if (!$this->exists($cache_entry->path)) {
                    $this->delete_cache_log_entry($cache_entry->path);
                }
            }
        }
    }

    /**
     * Cleanup invalid cache entries
     * 
     * @param string $image_path Path to the image file
     * @param bool $file_exists_in_log Whether file exists in cache log
     * @return void
     */
    private function _cleanup_invalid_cache_entry(string $image_path, bool $file_exists_in_log): void
    {
        if ($file_exists_in_log) { 
            $this->delete_cache_log_entry($image_path); 
        } elseif ($this->exists($image_path)) { 
            $this->delete($image_path); // If file exists but wasn't processed above
        }
    }

    /**
     * Clears image data from the static cache based on normalized data parameters.
     *
     * This method removes cached image entries from the static cache using the provided
     * normalized data array. It's typically called when image cache needs to be invalidated
     * or refreshed to ensure data consistency.
     *
     * @param array $normalized_data Array containing normalized image data used to identify
     *                              and remove specific cache entries
     * @return void
     */
    private function _clear_from_static_cache(array $normalized_data): void
    {
        $site_id = static::$site_id;
        $adapter_name = static::$adapter_name;
        $cache_dir = $normalized_data['cache_dir'];
        
        // Extract base name pattern
        $base_image_name = $normalized_data['filename'];
        $base_name_pattern = preg_replace('/_(lqip|thumb|small|medium|large)\.(jpg|jpeg|png|webp|gif)$/i', '', $base_image_name);
        $base_name_pattern = preg_replace('/\.(jpg|jpeg|png|webp|gif)$/i', '', $base_name_pattern);
        
        // Clear all related entries from static cache
        if (isset(static::$cache_log_index[$site_id][$adapter_name][$cache_dir])) {
            $cleared_count = 0;
            foreach (static::$cache_log_index[$site_id][$adapter_name][$cache_dir] as $filename => $entry) {
                if (str_starts_with($filename, $base_name_pattern)) {
                    unset(static::$cache_log_index[$site_id][$adapter_name][$cache_dir][$filename]);
                    $cleared_count++;
                }
            }
            
            // if ($cleared_count > 0) {
            //     ee('jcogs_img:Utilities')->debug_message("Cleared {$cleared_count} expired entries from static cache for base: {$base_name_pattern}");
            // }
        }
    }

    /**
     * Create mock database result from cached data
     * 
     * @param array $cached_data Cached result data
     * @return object|null Mock result object or null
     */
    private function _create_mock_result_from_cache(array $cached_data): object|null
    {
        if (empty($cached_data)) {
            return null;
        }
        
        // Create a mock result object that mimics CI_DB_result behavior
        $mock_result = new class($cached_data) {
            private array $cached_data;
            
            public function __construct(array $data) {
                $this->cached_data = $data;
            }
            
            public function result(): array {
                return $this->cached_data;
            }
            
            public function num_rows(): int {
                return count($this->cached_data);
            }
        };
        
        return $mock_result;
    }
    
    /**
     * Create or update cache entry with optimized transaction handling
     * Uses immediate DB updates for cache sharing, but optimizes transaction overhead
     * 
     * @param array $normalized_data Normalized cache data array
     * @param float|null $processing_time Time taken to process the image
     * @param array|null $vars Variables associated with the processed image
     * @param string|null $source_path Original source path of the image
     * @return bool True if cache entry was created/updated successfully, false otherwise
     */
    private function _create_or_update_cache_entry(array $normalized_data, ?float $processing_time, ?array $vars, ?string $source_path): bool
    {
        try {
            // Build tracker stats
            $tracker_stats = $this->_build_tracker_stats($normalized_data['original_path'], $processing_time, $source_path);
            
            // Create log object
            $log_object = $this->_create_log_object($normalized_data, $tracker_stats, $vars);
            
            // Update static cache FIRST
            $this->_update_static_cache($normalized_data, $log_object);
            
            // Let _insert_database_entry handle its own simple operations
            if (!$this->_insert_database_entry($normalized_data, $tracker_stats, $vars)) {
                $this->_rollback_static_cache($normalized_data);
                return false;
            }
            
            // Update cumulative cache info
            $this->_update_cumulative_cache_info($normalized_data['cache_dir'], $tracker_stats);
            
            return true;
            
        } catch (\Exception $e) {
            ee('jcogs_img:Utilities')->debug_message("Failed to create cache log entry for {$normalized_data['filename']}: " . $e->getMessage());
            $this->_rollback_static_cache($normalized_data);
            return false;
        }
    }

    /**
     * Create log object for static cache
     * 
     * @param array $normalized_data Normalized cache data array
     * @param array $tracker_stats Array of tracker statistics
     * @param array|null $vars Variables associated with the processed image
     * @return \stdClass Log object for static cache storage
     */
    private function _create_log_object(array $normalized_data, array $tracker_stats, ?array $vars): \stdClass
    {
        // Fix: Extract the actual variables array from the nested structure
        $vars_to_encode = null;
        if ($vars) {
            // Check if $vars has the nested [0] structure and extract it
            if (isset($vars[0]) && is_array($vars[0])) {
                $vars_to_encode = $vars[0]; // Extract the inner array
            } else {
                $vars_to_encode = $vars; // Use as-is if it's already the right structure
            }
        }
        
        $log_object = new \stdClass;
        $log_object->site_id = static::$site_id;
        $log_object->adapter_name = static::$adapter_name;
        $log_object->path = $normalized_data['normalized_path'];
        $log_object->image_name = $normalized_data['filename'];
        $log_object->stats = $this->_safe_json_encode($tracker_stats);
        $log_object->values = $vars_to_encode ? $this->_safe_json_encode($vars_to_encode) : '';
        
        return $log_object;
    }

    /**
     * Deletes all cache entries related to the provided normalized data.
     *
     * This method removes all cached entries that are associated with the given
     * normalized data array, ensuring cache consistency when data is updated or removed.
     *
     * @param array $normalized_data The normalized data array used to identify related cache entries
     * @return void
     */
    private function _delete_all_related_cache_entries(array $normalized_data): void
    {
        if (!ee()->db->table_exists(self::$table_name)) {
            return;
        }
        
        try {
            // Extract the base name without suffixes like _lqip
            $base_image_name = $normalized_data['filename'];
            
            // Remove common suffixes to find the base name pattern
            $base_name_pattern = preg_replace('/_(lqip|thumb|small|medium|large)\.(jpg|jpeg|png|webp|gif)$/i', '', $base_image_name);
            $base_name_pattern = preg_replace('/\.(jpg|jpeg|png|webp|gif)$/i', '', $base_name_pattern);
            
            // Get ALL entries that match this base pattern
            $query = ee()->db->select('id, image_name')
                            ->from(self::$table_name)
                            ->where('site_id', static::$site_id)
                            ->where('adapter_name', static::$adapter_name)
                            ->where('path', $normalized_data['normalized_path'])
                            ->get();
            
            $entries_to_delete = [];
            $found_entries = [];
            
            foreach ($query->result() as $row) {
                // Check if this entry belongs to the same base image
                $entry_base_pattern = preg_replace('/_(lqip|thumb|small|medium|large)\.(jpg|jpeg|png|webp|gif)$/i', '', $row->image_name);
                $entry_base_pattern = preg_replace('/\.(jpg|jpeg|png|webp|gif)$/i', '', $entry_base_pattern);
                
                if ($entry_base_pattern === $base_name_pattern) {
                    $entries_to_delete[] = $row->id;
                    $found_entries[] = $row->image_name;
                }
            }
            
            // Delete ALL found entries at once
            if (!empty($entries_to_delete)) {
                ee()->db->where_in('id', $entries_to_delete)->delete(self::$table_name);
                $affected_rows = ee()->db->affected_rows();
                
                // if ($affected_rows > 0) {
                //     ee('jcogs_img:Utilities')->debug_message("Deleted {$affected_rows} expired cache entries for base image: {$base_name_pattern} (entries: " . implode(', ', $found_entries) . ")");
                // }
            } else {
                // ee('jcogs_img:Utilities')->debug_message("No cache entries found to delete for base image: {$base_name_pattern}");
            }
            
        } catch (\Exception $e) {
            ee('jcogs_img:Utilities')->debug_message("Failed to delete related cache entries: " . $e->getMessage());
        }
    }

    /**
     * Display appropriate completion message based on audit result
     * 
     * @param string $result Audit result code (not_enabled, empty_cache_log, etc.)
     * @param int $locations_count Number of locations audited
     * @param int $files_removed Number of files removed during audit
     * @return void
     */
    private function _display_audit_completion_message(string $result, int $locations_count, int $files_removed): void
    {
        switch ($result) {
            case 'not_enabled':
                ee('CP/Alert')->makeInline('shared-form')
                    ->asWarning()
                    ->withTitle(lang('jcogs_img_cp_cache_audit_not_enabled'))
                    ->addToBody(lang('jcogs_img_cp_cache_audit_not_enabled_desc'))
                    ->defer();
                break;
                
            case 'empty_cache_log':
                ee('CP/Alert')->makeInline('shared-form')
                    ->asWarning()
                    ->withTitle(lang('jcogs_img_cp_cache_audit_empty_cache_log'))
                    ->addToBody(lang('jcogs_img_cp_cache_audit_empty_cache_log_desc'))
                    ->defer();
                break;
                
            case 'not_valid_location':
                ee('CP/Alert')->makeInline('shared-form')
                    ->asWarning()
                    ->withTitle(lang('jcogs_img_cp_cache_path_not_OK'))
                    ->addToBody(lang('jcogs_img_cp_cache_path_not_OK_desc'))
                    ->defer();
                break;
                
            default:
                ee('CP/Alert')->makeInline('shared-form')
                    ->asSuccess()
                    ->withTitle(lang('jcogs_img_cp_cache_audit_now_completed'))
                    ->addToBody(sprintf(
                        lang('jcogs_img_cp_cache_audit_now_completed_desc'),
                        $locations_count,
                        $files_removed,
                        $locations_count > 1 ? 's' : ''
                    ))
                    ->defer();
                break;
        }
    }

    /**
     * Dump performance log for debugging
     * 
     * @return void
     */
    public static function _dump_cache_performance_log(): void
    {
        if (empty(self::$performance_log)) {
            echo "<!-- No cache performance data collected -->\n";
            return;
        }
        
        echo "<!-- CacheManagementTrait Performance Log -->\n";
        echo "<!-- Total methods profiled: " . count(self::$performance_log) . " -->\n";
        
        $total_time = 0;
        $slowest_methods = [];
        
        foreach (self::$performance_log as $profile_id => $data) {
            if (isset($data['duration'])) {
                $total_time += $data['duration'];
                $slowest_methods[] = [
                    'method' => $data['method'],
                    'duration' => $data['duration'],
                    'memory_used' => $data['memory_used'] ?? 0,
                    'profile_id' => $profile_id
                ];
            }
        }
        
        // Sort by duration (slowest first)
        usort($slowest_methods, function($a, $b) {
            return $b['duration'] <=> $a['duration'];
        });
        
        echo "<!-- Total cache time: " . number_format($total_time * 1000, 2) . "ms -->\n";
        echo "<!-- Top 10 slowest cache operations: -->\n";
        
        foreach (array_slice($slowest_methods, 0, 10) as $method) {
            echo sprintf(
                "<!-- %s: %.2fms (Memory: %s) -->\n",
                $method['method'],
                $method['duration'] * 1000,
                number_format($method['memory_used'] / 1024, 2) . 'KB'
            );
        }
        
        echo "<!-- End CacheManagementTrait Performance Log -->\n";
    }

    /**
     * Ensure cache status structure exists
     * 
     * @param array $cache_status_info Cache status information array (passed by reference)
     * @param string $cache_dir Cache directory path
     * @return void
     */
    private function _ensure_cache_status_structure(array &$cache_status_info, string $cache_dir): void
    {
        if (!isset($cache_status_info[static::$site_id])) {
            $cache_status_info[static::$site_id] = [];
        }
        if (!isset($cache_status_info[static::$site_id][static::$adapter_name])) {
            $cache_status_info[static::$site_id][static::$adapter_name] = [];
        }
        if (!isset($cache_status_info[static::$site_id][static::$adapter_name][$cache_dir])) {
            $cache_status_info[static::$site_id][static::$adapter_name][$cache_dir] = [
                'inception_date' => 0,
                'count' => 0,
                'cumulative_filesize' => 0,
                'cumulative_processing_time' => 0
            ];
        }
    }

    /**
     * Ensure static cache structure exists
     * 
     * @param string $cache_dir Cache directory path
     * @return void
     */
    private function _ensure_static_cache_structure(string $cache_dir): void
    {
        if (!isset(static::$cache_log_index[static::$site_id][static::$adapter_name])) {
            static::$cache_log_index[static::$site_id][static::$adapter_name] = [];
        }
        if (!isset(static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir])) {
            static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir] = [];
        }
    }

    /**
     * Ensure static cache structure exists for retrieval operations
     * 
     * @return void
     */
    private function _ensure_static_cache_structure_for_retrieval(): void
    {
        if (!isset(static::$cache_log_index[static::$site_id][static::$adapter_name])) {
            static::$cache_log_index[static::$site_id][static::$adapter_name] = [];
        }
    }

    /**
     * Compare two cache entries to see if they differ significantly
     * 
     * @param \stdClass $existing Existing cache entry object
     * @param \stdClass $new New cache entry object to compare
     * @return bool True if entries differ significantly, false otherwise
     */
    private function _entries_differ(\stdClass $existing, \stdClass $new): bool
    {
        // Compare key properties
        $existing_stats = json_decode($existing->stats ?? '{}', true);
        $new_stats = json_decode($new->stats ?? '{}', true);
        
        // If stats differ significantly, consider them different
        if (($existing_stats['count'] ?? 0) !== ($new_stats['count'] ?? 0)) {
            return true;
        }
        
        // If processing time differs by more than 10%, consider different
        $existing_time = $existing_stats['processing_time'] ?? 0;
        $new_time = $new_stats['processing_time'] ?? 0;
        
        if ($existing_time > 0 && abs($existing_time - $new_time) / $existing_time > 0.1) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if cache entry already exists to prevent duplicates
     * 
     * @param array $normalized_data Normalized cache data array
     * @return bool True if entry already exists, false otherwise
     */
    private function _entry_already_exists(array $normalized_data): bool
    {
        $site_id = static::$site_id;
        $adapter_name = static::$adapter_name;
        $cache_dir = $normalized_data['cache_dir'];
        $filename = $normalized_data['filename'];
        
        // Check static cache first
        if (isset(static::$cache_log_index[$site_id][$adapter_name][$cache_dir][$filename])) {
            return true;
        }
        
        // Check database for existing entry
        if (ee()->db->table_exists(self::$table_name)) {
            $query = ee()->db->select('id')
                ->from(self::$table_name)
                ->where([
                    'site_id' => $site_id,
                    'adapter_name' => $adapter_name,
                    'path' => $normalized_data['normalized_path'] // Fix: Use 'normalized_path' instead of 'image_path'
                ])
                ->limit(1)
                ->get();
                
            return $query->num_rows() > 0;
        }
        
        return false;
    }

    /**
     * Evaluate cache validity based on duration type
     * 
     * @param int $cache_duration_when_saved Cache duration in seconds
     * @param array $cache_lookup_result Cache lookup result data
     * @param string $image_path Path to the image file
     * @param array $normalized_data Normalized cache data array
     * @return bool True if cache is valid, false otherwise
     */
    private function _evaluate_cache_validity(int $cache_duration_when_saved, array $cache_lookup_result, string $image_path, array $normalized_data): bool
    {
        // Cache duration is -1 (perpetual cache)
        if ($cache_duration_when_saved === -1) {
            return $this->_handle_perpetual_cache($cache_lookup_result['file_exists_in_log'], $image_path, $normalized_data);
        }

        // Positive cache duration (must check log/disk for freshness)
        return $this->_handle_timed_cache($cache_duration_when_saved, $cache_lookup_result, $image_path, $normalized_data);
    }

    /**
     * Execute audit operations for all locations
     * 
     * @param array $audit_locations Array of cache locations to audit
     * @return int Total number of files removed across all locations
     */
    private function _execute_audit_operations(array $audit_locations): int
    {
        $total_files_removed = 0;
        
        ee('jcogs_img:Utilities')->debug_message(
            sprintf(lang('jcogs_img_cache_audit_begin'), count($audit_locations), implode(', ', $audit_locations))
        );
        
        // First pass: Clean up orphaned cache log entries
        $this->_clean_orphaned_cache_entries($audit_locations);
        
        // Second pass: Audit files in each location
        foreach ($audit_locations as $audit_location) {
            $files_removed = $this->_audit_location($audit_location);
            if ($files_removed >= 0) {
                $total_files_removed += $files_removed;
            }
        }
        
        return $total_files_removed;
    }

    /**
     * Enhanced error handling wrapper for cache operations
     * 
     * @param callable $operation Operation to execute
     * @param string $operation_name Name of the operation for debugging
     * @param array $context Additional context for error reporting
     * @return mixed Result of the operation or false on error
     */
    private function _execute_cache_operation(callable $operation, string $operation_name, array $context = []): mixed
    {
        try {
            return $operation();
        } catch (\Throwable $e) {
            $error_context = !empty($context) ? $this->_safe_json_encode($context) : '';
            $error_message = sprintf(
                "Cache operation '%s' failed: %s. Context: %s", 
                $operation_name, 
                $e->getMessage(),
                $error_context
            );
            
            ee('jcogs_img:Utilities')->debug_message($error_message);
            
            // Log to PHP error log for debugging
            error_log("[JCOGS_IMG] " . $error_message);
            
            return false;
        }
    }

    /**
     * Fetch from database with optimized query and update static cache
     * 
     * @param array $normalized_path_data Normalized path data for database query
     * @return array Array of cache entries from database
     */
    private function _fetch_from_database_and_update_cache(array $normalized_path_data): array
    {
        if (!ee()->db->table_exists(self::$table_name)) {
            return [];
        }
        
        try {
            // Build optimized database query
            $query = $this->_build_optimized_database_query($normalized_path_data);
            
            if (!$query || $query->num_rows() === 0) {
                return $this->_handle_empty_query_result($normalized_path_data);
            }
            
            // Process query results efficiently
            return $this->_process_query_results($query, $normalized_path_data);
            
        } catch (\Exception $e) {
            ee('jcogs_img:Utilities')->debug_message("Database query failed in get_file_info_from_cache_log: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Static method to flush all pending cache updates in batch
     * 
     * @return void
     */
    public static function _flush_cache_updates_batch(): void
    {
        if (empty(self::$pending_cache_updates)) {
            return;
        }
        
        // Take a snapshot and clear immediately to prevent re-entry
        $updates_to_process = self::$pending_cache_updates;
        self::$pending_cache_updates = [];
        self::$cache_update_scheduled = false;
        
        // Group updates by unique image path to prevent duplicates
        $unique_updates = [];
        foreach ($updates_to_process as $cache_key => $update_data) {
            if (!isset($update_data['image_path']) || empty($update_data['image_path'])) {
                continue;
            }
            
            $image_path = $update_data['image_path'];
            
            // Keep only the most recent update for each image path
            if (!isset($unique_updates[$image_path]) || 
                ($update_data['timestamp'] ?? 0) > ($unique_updates[$image_path]['timestamp'] ?? 0)) {
                $unique_updates[$image_path] = $update_data;
            }
        }
        
        // Process unique updates
        foreach ($unique_updates as $update_data) {
            try {
                if (empty($update_data['image_path'])) {
                    continue;
                }
                
                self::_perform_immediate_cache_update(
                    image_path: $update_data['image_path'],
                    processing_time: $update_data['processing_time'] ?? null,
                    vars: $update_data['vars'] ?? null,
                    cache_dir: $update_data['cache_dir'] ?? null,
                    source_path: $update_data['source_path'] ?? null
                );
            } catch (\Exception $e) {
                error_log("Failed to flush cache update for {$update_data['image_path']}: " . $e->getMessage());
            }
        }
    }

    /**
     * Format final result based on request type
     * 
     * @param array $file_info_to_return File information array to format
     * @param array $normalized_path_data Normalized path data for request context
     * @return array Formatted result array
     */
    private function _format_final_result(array $file_info_to_return, array $normalized_path_data): array
    {
        if ($normalized_path_data['is_specific_path']) {
            // Specific path requested - return only if found
            $requested_filename = $normalized_path_data['requested_filename'];
            return isset($file_info_to_return[$requested_filename]) 
                ? [$requested_filename => $file_info_to_return[$requested_filename]] 
                : [];
        }
        
        // All entries requested - return everything found
        return $file_info_to_return;
    }
    
    /**
     * Generate standardized cache key
     * 
     * @param string $suffix Suffix to append to cache key
     * @return string Generated cache key
     */
    private function _generate_cache_key(string $suffix): string
    {
        return static::$site_id . '_' . static::$adapter_name . $suffix;
    }

    /**
     * Generate cache status key
     * 
     * @return string Generated cache status key
     */
    private function _generate_cache_status_key(): string
    {
        return ee('jcogs_img:Utilities')->getCacheKey(static::$site_id . '_' . static::$adapter_name . '_cache_status_info');
    }

    /**
     * Get all audit locations from cache index
     * 
     * @return array Array of cache locations available for audit
     */
    private function _get_all_audit_locations(): array
    {
        $audit_locations = [];
        
        if (!static::$cache_log_index || 
            !isset(static::$cache_log_index[static::$site_id][static::$adapter_name])) {
            return [];
        }
        
        foreach (static::$cache_log_index[static::$site_id][static::$adapter_name] as $cache_dir => $entries) {
            if (!empty($entries) && !in_array($cache_dir, $audit_locations)) {
                $audit_locations[] = $cache_dir;
            }
        }
        
        return $audit_locations;
    }

    /**
     * Get cache configuration value
     * 
     * @param string $key Configuration key to retrieve
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value or default
     */
    private function _get_cache_config(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Get cache lookup data
     * 
     * @param string $image_path Path to the image file
     * @param string $lookup_filename_key Filename key for cache lookup
     * @return array Array containing log entry and existence flag
     */
    private function _get_cache_lookup_data(string $image_path, string $lookup_filename_key): array
    {
        $file_info_from_log = $this->get_file_info_from_cache_log($image_path);
        $log_entry = null;
        $file_exists_in_log = false;

        if (!empty($file_info_from_log) && isset($file_info_from_log[$lookup_filename_key])) {
            $log_entry = $file_info_from_log[$lookup_filename_key];
            $file_exists_in_log = true;
        }

        return [
            'log_entry' => $log_entry,
            'file_exists_in_log' => $file_exists_in_log
        ];
    }

    /**
     * Get cached database result or execute query if not cached
     * 
     * @param string $path Path to query for
     * @return array Database result as array
     */
    private function _get_cached_db_result(string $path): array
    {
        $trimmed_path = trim($path, '/');
        $cache_key = static::$site_id . '_' . static::$adapter_name . '_' . $trimmed_path;
        
        // Return cached result if available
        if (isset(self::$db_query_cache[$cache_key])) {
            return self::$db_query_cache[$cache_key];
        }
        
        // Check if this is already in static cache to avoid DB query entirely
        $path_parts = pathinfo($trimmed_path);
        $cache_dir = $path_parts['dirname'] ?? '.';
        $filename = $path_parts['basename'];
        
        // Check static cache first (should be populated by preload)
        if (isset(static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir][$filename])) {
            $entry = static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir][$filename];
            $result = [$filename => $entry];
            
            // Cache the result in DB query cache too
            self::$db_query_cache[$cache_key] = $result;
            return $result;
        }
        
        // If we get here, preload didn't work or entry doesn't exist
        // Log this as it shouldn't happen if preload worked correctly
        ee('jcogs_img:Utilities')->debug_message("Database query after preload for: {$trimmed_path}");
        
        // Cache empty result to prevent repeated queries for non-existent entries
        self::$db_query_cache[$cache_key] = [];
        return [];
    }

    /**
     * Get timestamp from disk with error handling
     * 
     * @param string $image_path Path to the image file
     * @param bool $file_exists_in_log Whether file exists in cache log
     * @return int Timestamp from disk or 0 if error
     */
    private function _get_disk_timestamp(string $image_path, bool $file_exists_in_log): int
    {
        if ($this->exists($image_path)) {
            $last_modified_time = $this->lastModified($image_path);
            if ($last_modified_time === false) {
                // If log entry existed but disk mtime failed, log entry might be for a problematic file
                $this->delete_cache_log_entry($image_path); 
                return 0; 
            }
            return $last_modified_time;
        } else {
            // No valid log inception_date AND file not on disk.
            if ($file_exists_in_log) {
                $this->delete_cache_log_entry($image_path); // Clean up orphaned log entry
            }
            return 0;
        }
    }

    /**
     * Get file age timestamp with improved error handling
     * 
     * @param array $cache_lookup_result Cache lookup result data
     * @param string $image_path Path to the image file
     * @return int File age timestamp or 0 if invalid
     */
    private function _get_file_age_timestamp(array $cache_lookup_result, string $image_path): int
    {
        $age_of_file_timestamp = 0;
        $log_entry = $cache_lookup_result['log_entry'];
        $file_exists_in_log = $cache_lookup_result['file_exists_in_log'];

        // Try to get timestamp from log entry first
        if ($file_exists_in_log && property_exists($log_entry, 'stats')) {
            $stats = json_decode($log_entry->stats);
            if ($stats && property_exists($stats, 'inception_date') && (int)$stats->inception_date > 0) {
                $age_of_file_timestamp = (int) $stats->inception_date;
            }
        }

        // If log didn't provide a valid timestamp, try disk
        if ($age_of_file_timestamp === 0) {
            $age_of_file_timestamp = $this->_get_disk_timestamp($image_path, $file_exists_in_log);
        }

        // Final validation - if still 0, cleanup and return 0
        if ($age_of_file_timestamp === 0) {
            $this->_cleanup_invalid_cache_entry($image_path, $file_exists_in_log);
        }

        return $age_of_file_timestamp;
    }

    /**
     * Retrieve a single cache entry for a specific file path using selective loading strategy.
     * 
     * This method implements selective cache loading by performing individual database lookups
     * for specific file paths, which is more efficient for large datasets than bulk loading.
     * Results are cached in static memory to avoid repeated database queries.
     *
     * @param string $path The file path to retrieve cache entry for
     * @return array Empty array if not found or non-specific path, otherwise associative array 
     *               with filename as key and cache entry object as value
     */
    private function _get_selective_cache_entry(string $path): array
    {
        $normalized_data = $this->_normalize_path_for_retrieval($path);
        
        if (!$normalized_data['is_specific_path']) {
            // For non-specific paths with selective loading, return empty
            // This forces individual lookups which is more efficient for large datasets
            return [];
        }
        
        $cache_dir = $normalized_data['cache_dir'];
        $filename = $normalized_data['filename'];
        
        // Check if already in static cache
        if (isset(static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir][$filename])) {
            return [$filename => static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir][$filename]];
        }
        
        // Single database lookup with LIMIT 1
        try {
            $trimmed_path = trim($path, '/');
            $query = ee()->db->select('*')
                ->from(self::$table_name)
                ->where('site_id', static::$site_id)
                ->where('adapter_name', static::$adapter_name)
                ->where('path', $trimmed_path)
                ->limit(1)
                ->get();
            
            if ($query->num_rows() > 0) {
                $entry = $query->row();
                $processed_entry = $this->_process_single_entry($entry);
                
                if ($processed_entry) {
                    // Cache this single entry in static cache
                    $this->_ensure_static_cache_structure($cache_dir);
                    static::$cache_log_index[static::$site_id][static::$adapter_name][$cache_dir][$filename] = (object)$processed_entry;
                    
                    return [$filename => (object)$processed_entry];
                }
            }
            
            return [];
            
        } catch (\Exception $e) {
            ee('jcogs_img:Utilities')->debug_message("Failed selective cache lookup for {$path}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get specific audit location and validate it exists
     */
    private function _get_specific_audit_location(string $location): array
    {
        $normalized_location = trim(strtolower($location), '/');
        
        // Validate location exists in cache index
        if (!isset(static::$cache_log_index[static::$site_id][static::$adapter_name][$location])) {
            return [];
        }
        
        // Verify location has entries
        $entries = static::$cache_log_index[static::$site_id][static::$adapter_name][$location];
        if (empty($entries)) {
            return [];
        }
        
        return [$location];
    }

    /**
     * Handle audit completion with appropriate messaging
     * 
     * @param bool $force Whether audit was forced
     * @param string $result Audit result code
     * @param int $locations_count Number of locations audited
     * @param int $files_removed Number of files removed
     * @return void
     */
    private function _handle_audit_completion(bool $force, string $result, int $locations_count = 0, int $files_removed = 0): void
    {
        // Only show messages for forced (manual) audits
        if (!$force) {
            return;
        }
        
        $this->_display_audit_completion_message($result, $locations_count, $files_removed);
        
        // Redirect after showing message
        ee()->functions->redirect(ee('CP/URL', 'addons/settings/jcogs_img/caching'), 'refresh');
    }

    /**
     * Handle empty query results appropriately
     * 
     * @param array $normalized_path_data Normalized path data for context
     * @return array Empty array or appropriate default response
     */
    private function _handle_empty_query_result(array $normalized_path_data): array
    {
        if ($normalized_path_data['is_specific_path']) {
            // Specific path requested but not found
            return [];
        }
        
        // No entries found for site/adapter combination
        return [];
    }

    /**
     * Handle fresh cache scenarios
     * 
     * @param array $cache_lookup_result Cache lookup result data
     * @param string $image_path Path to the image file
     * @param array $normalized_data Normalized cache data array
     * @return bool True if cache is fresh and valid, false otherwise
     */
    private function _handle_fresh_cache(array $cache_lookup_result, string $image_path, array $normalized_data): bool
    {
        $file_exists_in_log = $cache_lookup_result['file_exists_in_log'];
        
        // Check if file exists on disk but not in log (similar to perpetual cache handling)
        if (!$file_exists_in_log && $this->exists($image_path)) {
            // File exists on disk but not in log, log it now using existing method
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_cache_file_on_disk_only_so_logging'));
            
            // Set flag to trigger cache log update
            // $this->update_cache_log(
            //     image_path: $image_path, 
            //     cache_dir: $normalized_data['cache_dir']
            // );
            static::$image_log_needs_updating = true;
        }
        
        return true;
    }

    /**
     * Handle perpetual cache logic
     * 
     * @param bool $file_exists_in_log Whether file exists in cache log
     * @param string $image_path Path to the image file
     * @param array $normalized_data Normalized cache data array
     * @return bool True if perpetual cache is valid, false otherwise
     */
    private function _handle_perpetual_cache(bool $file_exists_in_log, string $image_path, array $normalized_data): bool
    {
        if ($file_exists_in_log) {
            return true; 
        }

        // No log entry. Check disk as a fallback.
        if ($this->exists($image_path)) {
            // File exists on disk but not in log. Attempt to log it using existing method.
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_cache_file_on_disk_only_so_logging'));
            
            // Set flag to trigger cache log update
            // $this->update_cache_log(
            //     image_path: $image_path, 
            //     cache_dir: $normalized_data['cache_dir']
            // );
            static::$image_log_needs_updating = true;
            return true;
        }

        return false;
    }

    /**
     * Handle timed cache logic
     * 
     * @param int $cache_duration_when_saved Cache duration in seconds
     * @param array $cache_lookup_result Cache lookup result data
     * @param string $image_path Path to the image file
     * @param array $normalized_data Normalized cache data array
     * @return bool True if timed cache is valid, false otherwise
     */
    private function _handle_timed_cache(int $cache_duration_when_saved, array $cache_lookup_result, string $image_path, array $normalized_data): bool
    {
        $age_of_file_timestamp = $this->_get_file_age_timestamp($cache_lookup_result, $image_path);
        
        if ($age_of_file_timestamp === 0) {
            return false; // Invalid timestamp or file issues
        }

        $is_fresh = $this->_is_cache_fresh($age_of_file_timestamp, $cache_duration_when_saved);

        if ($is_fresh) {
            return $this->_handle_fresh_cache($cache_lookup_result, $image_path, $normalized_data);
        } else {
            // Cache has expired - clean up and force regeneration
            $this->_delete_all_related_cache_entries($normalized_data);
            
            // Clear from static cache as well to ensure fresh lookup
            $this->_clear_from_static_cache($normalized_data);
            
            // Force cache log update flag for regeneration
            static::$image_log_needs_updating = true;
            
            return false;
        }
    }

    /**
     * Insert entry into database
     * 
     * @param array $normalized_data Normalized cache data array
     * @param array $tracker_stats Array of tracker statistics
     * @param array|null $vars Variables associated with the processed image
     * @return bool True if entry was inserted successfully, false otherwise
     */
    private function _insert_database_entry(array $normalized_data, array $tracker_stats, ?array $vars): bool
    {
        if (!ee()->db->table_exists(self::$table_name)) {
            ee('jcogs_img:Utilities')->debug_message("Cache logging failed: table " . self::$table_name . " does not exist");
            return false;
        }
        
        try {
            $vars_to_encode = null;
            if ($vars) {
                if (isset($vars[0]) && is_array($vars[0])) {
                    $vars_to_encode = $vars[0];
                } else {
                    $vars_to_encode = $vars;
                }
            }
            
            $insert_data = [
                'site_id' => static::$site_id,
                'adapter_name' => static::$adapter_name,
                'path' => $normalized_data['normalized_path'],
                'image_name' => $normalized_data['filename'],
                'stats' => $this->_safe_json_encode($tracker_stats),
                'values' => $vars_to_encode ? $this->_safe_json_encode($vars_to_encode) : null
            ];
            
            // Pre-emptively delete any existing entries for this exact image to prevent duplicates
            ee()->db->where('site_id', static::$site_id)
                    ->where('adapter_name', static::$adapter_name)
                    ->where('path', $normalized_data['normalized_path'])
                    ->where('image_name', $normalized_data['filename'])
                    ->delete(self::$table_name);
            
            $pre_delete_count = ee()->db->affected_rows();
            if ($pre_delete_count > 0) {
                // ee('jcogs_img:Utilities')->debug_message("Pre-deleted {$pre_delete_count} existing entries for: " . $normalized_data['filename']);
            }
            
            // Insert the new entry
            ee()->db->insert(self::$table_name, $insert_data);
            
            // ee('jcogs_img:Utilities')->debug_message("Cache log entry created/updated for: " . $normalized_data['filename']);
            return true;
            
        } catch (\Exception $e) {
            ee('jcogs_img:Utilities')->debug_message("Failed to insert cache log entry: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if audit is due based on last audit timestamp
     * 
     * @return bool True if audit is due, false otherwise
     */
    private function _is_audit_due(): bool
    {
        $marker = ee('jcogs_img:Utilities')->cache_utility('get', JCOGS_IMG_CLASS . '/' . self::$cache_key_audit);
        $last_audit = $marker ?: 0;
        
        return (time() - $last_audit) > $this->settings['img_cp_default_cache_audit_after'];
    }

    /**
     * Check if a file's cache is still valid based on duration and timestamp
     * 
     * @param int|bool $cache_duration_when_saved Cache duration or false if invalid
     * @param array $location_file File information array from directory listing
     * @return bool True if cache is valid, false otherwise
     */
    private function _is_file_cache_valid(int|bool $cache_duration_when_saved, array $location_file): bool
    {
        // Perpetual cache (-1) is always valid
        if ($cache_duration_when_saved === -1) {
            return true;
        }
        
        // Invalid cache duration
        if ($cache_duration_when_saved === false || $cache_duration_when_saved === 0) {
            return false;
        }
        
        // Check if file has expired based on timestamp
        $file_age = time() - $location_file['lastModified'];
        return $file_age < $cache_duration_when_saved;
    }

    /**
     * Check if caching is explicitly disabled
     * 
     * @return bool True if caching is disabled, false otherwise
     */
    private function _is_caching_explicitly_disabled(): bool
    {
        return (static::$current_params->cache == 0 || 
                strtolower(substr(static::$current_params->overwrite_cache ?? 'n', 0, 1)) == 'y');
    }

    /**
     * Check if cache is fresh based on timestamp and duration
     * 
     * @param int $age_of_file_timestamp File age timestamp
     * @param int $cache_duration_when_saved Cache duration in seconds
     * @return bool True if cache is fresh, false otherwise
     */
    private function _is_cache_fresh(int $age_of_file_timestamp, int $cache_duration_when_saved): bool
    {
        return (time() - $age_of_file_timestamp < $cache_duration_when_saved);
    }

    /**
     * Detect if running in development environment
     * 
     * @return bool True if development environment detected, false otherwise
     */
    private function _is_development_environment(): bool
    {
        // Check for common development indicators
        if (defined('DEBUG') && DEBUG) {
            return true;
        }
        
        if (isset($_SERVER['HTTP_HOST']) && 
            (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
            strpos($_SERVER['HTTP_HOST'], '.local') !== false ||
            strpos($_SERVER['HTTP_HOST'], '.dev') !== false)) {
            return true;
        }
        
        // Check EE debug settings
        if (ee()->config->item('debug') > 0) {
            return true;
        }
        
        return false;
    }

    /**
     * Validate cache duration and handle invalid cases
     * 
     * @param int|bool $cache_duration_when_saved Cache duration or false if invalid
     * @param string $filename Image filename
     * @param string $image_path Path to the image file
     * @return bool True if cache duration is valid, false otherwise
     */
    private function _is_valid_cache_duration(int|bool $cache_duration_when_saved, string $filename, string $image_path): bool
    {
        // Case 1: Cache duration is 0 (explicitly not cached or stale by filename convention)
        if ($cache_duration_when_saved === 0) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_cache_duration_zero_filename'), $filename);
            $this->delete_cache_log_entry($image_path);
            return false;
        }

        // Case 2: Cache duration is false (invalid format in filename)
        if ($cache_duration_when_saved === false) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_cache_invalid_duration_filename'), $filename);
            $this->delete_cache_log_entry($image_path);
            return false;
        }

        return true;
    }

    /**
     * Performance monitoring
     * 
     * @param float $start_time Start time for monitoring
     * @param string $image_path Path to the image being monitored
     * @return void
     */
    private function _monitor_performance(float $start_time, string $image_path): void
    {
        $execution_time = microtime(true) - $start_time;
        if ($execution_time > self::$slow_operation_threshold) {
            ee('jcogs_img:Utilities')->debug_message("Slow cache log update detected: {$execution_time}s for {$image_path}");
        }
    }

    /**
     * Normalize and prepare path data for caching
     * 
     * @param string $image_path Path to the image file
     * @param string|null $cache_dir Cache directory override
     * @return array Normalized cache data array
     */
    private function _normalize_cache_path_data(string $image_path, ?string $cache_dir): array
    {
        $original_image_path = $image_path;
        $normalized_path = strtolower(trim($image_path, '/'));
        $path_parts = pathinfo($normalized_path);
        
        $effective_cache_dir = $this->_sanitize_cache_directory($cache_dir ?? $path_parts['dirname'] ?? self::$default_cache_dir);
        
        return [
            'original_path' => $original_image_path,
            'image_path' => $original_image_path,  // Add this for backwards compatibility
            'normalized_path' => $normalized_path,
            'filename' => $path_parts['basename'],
            'cache_dir' => $effective_cache_dir
        ];
    }

    /**
     * Normalize path data for retrieval operations
     * 
     * @param string|null $path Path to normalize for retrieval
     * @return array Normalized path data array
     */
    private function _normalize_path_for_retrieval(?string $path): array
    {
        $trimmed_path = $path ? trim($path, '/') : null;
        $is_specific_path = !empty($trimmed_path);
        
        if ($is_specific_path) {
            $path_parts = pathinfo($trimmed_path);
            $cache_dir = $path_parts['dirname'] ?? self::$default_cache_dir;
            $filename = $path_parts['basename'];
            
            return [
                'is_specific_path' => true,
                'trimmed_path' => $trimmed_path,
                'cache_dir' => $cache_dir,
                'filename' => $filename,
                'requested_filename' => $filename
            ];
        }
        
        return [
            'is_specific_path' => false,
            'trimmed_path' => null,
            'cache_dir' => null,
            'filename' => null,
            'requested_filename' => null
        ];
    }
    
    /**
     * Core cache audit logic
     * 
     * @param bool $force Whether to force audit regardless of schedule
     * @param string|null $location Specific location to audit
     * @return mixed Audit result (true/false or string status)
     */
    private function _perform_cache_audit(bool $force, ?string $location): mixed
    {
        // Early validation checks
        $validation_result = $this->_validate_audit_prerequisites($force);
        if ($validation_result !== 'continue') {
            $this->_handle_audit_completion($force, $validation_result);
            return $validation_result;
        }
        

        // Get audit locations
        $audit_locations = $location ? $this->_get_specific_audit_location($location) : $this->_get_all_audit_locations();

        if (empty($audit_locations)) {
            $this->_handle_audit_completion($force, 'not_valid_location');
            return 'not_valid_location';
        }
        
        // Update audit timestamp early
        $this->_update_audit_timestamp();
        
        // Perform the actual audit
        $files_removed = $this->_execute_audit_operations($audit_locations);

        // After completing audit, update the stored count if significant changes occurred
        if ($files_removed > 100 || $force) { // Only update if we deleted many files or forced
            $this->update_stored_cache_count();
        }
        
        // Handle completion
        $final_result = $files_removed >= 0 ? 'success' : 'error';
        $this->_handle_audit_completion($force, $final_result, count($audit_locations), $files_removed);
        
        return $final_result === 'success' ? true : false;
    }

    /**
     * Core cache log retrieval logic
     * 
     * @param string|null $path Optional path to filter results
     * @return array Array of cache log entries
     */
    private function _perform_cache_log_retrieval(?string $path): array
    {
        // Early validation and initialization
        if (!$this->_validate_cache_log_prerequisites()) {
            return [];
        }

        // Ensure static cache structure exists
        $this->_ensure_static_cache_structure_for_retrieval();
        
        // Normalize path using existing pattern
        $normalized_path_data = $this->_normalize_path_for_retrieval($path);
        
        // Check static cache first
        if ($normalized_path_data['is_specific_path']) {
            $static_result = $this->_check_static_cache($normalized_path_data);
            if ($static_result !== null) {
                return $static_result;
            }
        }

        // Not in static cache so fetch from database using optimized query
        return $this->_fetch_from_database_and_update_cache($normalized_path_data);
    }

    /**
     * Core cache log update logic
     * 
     * @param string $image_path Path to the image being cached
     * @param float|null $processing_time Time taken to process the image
     * @param array|null $vars Variables associated with the processed image
     * @param string|null $cache_dir Cache directory override
     * @param string|null $source_path Original source path of the image
     * @param bool $force_update Force update even if entry exists
     * @return bool True if cache log was updated successfully, false otherwise
     */
    private function _perform_cache_log_update(string $image_path, ?float $processing_time, ?array $vars, ?string $cache_dir, ?string $source_path, bool $force_update): bool
    {
        // Add guard against recursive updates
        static $updating_paths = [];
        
        // Create unique key for this update operation
        $update_key = static::$site_id . '_' . static::$adapter_name . '_' . trim($image_path, '/');
        
        // Check if we're already updating this path
        if (isset($updating_paths[$update_key])) {
            return true; // Prevent infinite recursion
        }
        
        // Mark this path as being updated
        $updating_paths[$update_key] = true;
        
        try {
            // Early validation
            if (!$this->_validate_cache_log_prerequisites()) {
                unset($updating_paths[$update_key]);
                return false;
            }

            $normalized_data = $this->_normalize_cache_path_data($image_path, $cache_dir);
            
            // For newly generated images or forced updates, always create/update entry
            if ($force_update) {
                $result = $this->_create_or_update_cache_entry($normalized_data, $processing_time, $vars, $source_path);
                unset($updating_paths[$update_key]);
                return $result;
            }
            
            // Check existing entry only if not forcing update
            if ($this->_cache_entry_exists($normalized_data)) {
                unset($updating_paths[$update_key]);
                return true;
            }

            $result = $this->_create_or_update_cache_entry($normalized_data, $processing_time, $vars, $source_path);
            unset($updating_paths[$update_key]);
            return $result;
            
        } catch (\Exception $e) {
            unset($updating_paths[$update_key]);
            throw $e;
        }
    }

    /**
     * Core cache validity check logic
     * 
     * @param string $image_path Path to the image file to check
     * @return bool True if image is cached and valid, false otherwise
     */
    private function _perform_cache_validity_check(string $image_path): bool
    {
        // Get normalised data for the image path
        $normalized_data = $this->_normalize_cache_path_data($image_path, null);
        $pathinfo = pathinfo($image_path);
        $filename = $pathinfo['basename']; 
        $lookup_filename_key = strtolower($filename); 

        // Get cache duration 
        $cache_duration_when_saved = $this->get_file_cache_duration($image_path);

        // Handle invalid cache durations
        if (!$this->_is_valid_cache_duration($cache_duration_when_saved, $filename, $image_path)) {
            return false;
        }

        // Get cache log entry for this file
        $cache_lookup_result = $this->_get_cache_lookup_data($image_path, $lookup_filename_key);
        
        // Handle different cache duration scenarios
        return $this->_evaluate_cache_validity(
            $cache_duration_when_saved, 
            $cache_lookup_result, 
            $image_path, 
            $normalized_data
        );
    }

    /**
     * Perform immediate cache update without scheduling
     * 
     * @param string $image_path Path to the image being cached
     * @param float|null $processing_time Time taken to process the image
     * @param array|null $vars Variables associated with the processed image
     * @param string|null $cache_dir Cache directory override
     * @param string|null $source_path Original source path of the image
     * @return void
     */
    private static function _perform_immediate_cache_update(string $image_path, ?float $processing_time = null, ?array $vars = null, ?string $cache_dir = null, ?string $source_path = null): void
    {
        // Validate required parameter
        if (empty($image_path)) {
            error_log("[JCOGS_IMG] _perform_immediate_cache_update called with empty image_path");
            return;
        }
        
        try {
            // Create instance to access non-static methods
            $instance = ee('jcogs_img:ImageUtilities');
            
            // BYPASS normal update_cache_log to prevent infinite loops
            // Call the internal update method directly
            $normalized_data = $instance->_normalize_cache_path_data($image_path, $cache_dir);
            
            // Check if entry already exists to prevent duplicates
            if ($instance->_entry_already_exists($normalized_data)) {
                return; // Skip if already exists
            }
            
            // Build tracker stats
            $tracker_stats = $instance->_build_tracker_stats($normalized_data['original_path'], $processing_time, $source_path);
            
            // Create log object
            $log_object = $instance->_create_log_object($normalized_data, $tracker_stats, $vars);
            
            // Insert into database directly
            $success = $instance->_insert_database_entry($normalized_data, $tracker_stats, $vars);
            
            if ($success) {
                // Update static cache as well
                $instance->_update_static_cache($normalized_data, $log_object);
                
                // Update cumulative cache info
                $instance->_update_cumulative_cache_info($normalized_data['cache_dir'], $tracker_stats);
            }
            
        } catch (\Throwable $e) {
            // Silently handle errors to prevent breaking the image processing
            error_log("[JCOGS_IMG] Batch cache update error for {$image_path}: " . $e->getMessage());
        }
    }

    /**
     * Performs an immediate selective update of cache entry for a specific image.
     *
     * This method handles both database updates and static cache synchronization for image
     * processing entries. It will either insert a new record or update an existing one
     * based on the normalized image path.
     *
     * @param string $image_path The path to the image file
     * @param float|null $processing_time Time taken to process the image in seconds
     * @param array|null $vars Additional variables/parameters associated with the image
     * @param string|null $cache_dir Directory where cached files are stored
     * @param string|null $source_path Original source path of the image
     * @param bool $force_update Whether to force update even if entry exists
     * @param bool $using_cache_copy Whether a cached copy is being used
     * @return bool True on successful update, false on failure or validation error
     * @throws \Exception On database operation failures
     */
    private function _perform_immediate_selective_update(string $image_path, ?float $processing_time, ?array $vars, ?string $cache_dir, ?string $source_path, bool $force_update, bool $using_cache_copy): bool
    {
        if (!$this->_validate_update_cache_log_inputs($image_path, $processing_time, $vars)) {
            return false;
        }
        
        $normalized_data = $this->_normalize_cache_path_data($image_path, $cache_dir);
        
        // Skip if entry exists and we're not forcing update
        if (!$force_update && $this->_cache_entry_exists($normalized_data)) {
            return true;
        }
        
        // Build tracker stats
        $tracker_stats = $this->_build_tracker_stats($image_path, $processing_time, $source_path);
        
        try {
            // Check if entry exists in database
            $existing_query = ee()->db->select('path')
                ->from(self::$table_name)
                ->where('site_id', static::$site_id)
                ->where('adapter_name', static::$adapter_name)
                ->where('path', $normalized_data['normalized_path'])
                ->limit(1)
                ->get();
            
            if ($existing_query->num_rows() > 0) {
                // Update existing entry
                $update_data = [
                    'stats' => $this->_safe_json_encode($tracker_stats),
                    'values' => $vars ? $this->_safe_json_encode($vars) : ''
                ];
                
                ee()->db->where('site_id', static::$site_id)
                    ->where('adapter_name', static::$adapter_name)
                    ->where('path', $normalized_data['normalized_path'])
                    ->update(self::$table_name, $update_data);
            } else {
                // Insert new entry
                $insert_data = [
                    'site_id' => static::$site_id,
                    'adapter_name' => static::$adapter_name,
                    'path' => $normalized_data['normalized_path'],
                    'image_name' => $normalized_data['filename'],
                    'stats' => $this->_safe_json_encode($tracker_stats),
                    'values' => $vars ? $this->_safe_json_encode($vars) : ''
                ];
                
                ee()->db->insert(self::$table_name, $insert_data);
            }
            
            // Update static cache with new data
            $log_object = $this->_create_log_object($normalized_data, $tracker_stats, $vars);
            $this->_update_static_cache($normalized_data, $log_object);
            
            return true;
            
        } catch (\Exception $e) {
            ee('jcogs_img:Utilities')->debug_message("Failed immediate selective update for {$image_path}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Preloads the complete cache index into memory for improved performance.
     * 
     * This method loads all cache entries at once to reduce individual file system
     * calls during subsequent cache operations. Should be called before bulk cache
     * operations to optimize performance.
     * 
     * @return int Number of cache entries preloaded, or 0 if none found
     */
    private function _preload_full_cache_index(): int
    {
        if (isset(static::$cache_log_index[static::$site_id][static::$adapter_name]) && 
            !empty(static::$cache_log_index[static::$site_id][static::$adapter_name])) {
            return 0;
        }
        
        $query = ee()->db->select('*')
            ->from(self::$table_name)
            ->where('site_id', static::$site_id)
            ->where('adapter_name', static::$adapter_name)
            ->order_by('path', 'ASC')
            ->get();
        
        if (($cache_count = $query->num_rows()) > 0) {
            $this->_process_preload_results($query);
        }

        return $cache_count ?: 0;
    }

    /**
     * Process preload results and populate static cache
     * 
     * @param \CI_DB_result $query Database query result containing cache entries
     * @return void
     */
    private function _process_preload_results(\CI_DB_result $query): void
    {
        $batch_update_data = [];
        
        foreach ($query->result() as $entry) {
            $processed_entry = $this->_process_single_entry($entry);
            
            if ($processed_entry) {
                $batch_update_data[] = [
                    'entry' => $entry,
                    'cache_dir' => $processed_entry['cache_dir'],
                    'filename' => $processed_entry['filename']
                ];
            }
        }
        
        // Batch update static cache for better performance
        $this->_batch_update_static_cache($batch_update_data);
        
        // ee('jcogs_img:Utilities')->debug_message(
        //     sprintf("Preloaded %d cache entries into static cache", count($batch_update_data))
        // );
    }

    /**
     * Process query results efficiently and update static cache
     * 
     * @param \CI_DB_result $query Database query result
     * @param array $normalized_path_data Normalized path data for request context
     * @return array Array of processed cache entries
     */
    private function _process_query_results(\CI_DB_result $query, array $normalized_path_data): array
    {
        $file_info_to_return = [];
        $batch_update_data = [];
        
        foreach ($query->result() as $entry) {
            $processed_entry = $this->_process_single_entry($entry);
            
            if ($processed_entry) {
                $file_info_to_return[$processed_entry['filename']] = $entry;
                $batch_update_data[] = $processed_entry;
            }
        }
        
        // Batch update static cache for better performance
        $this->_batch_update_static_cache($batch_update_data);
        
        // Return appropriate result based on request type
        return $this->_format_final_result($file_info_to_return, $normalized_path_data);
    }

    /**
     * Process single database entry with validation
     * 
     * @param \stdClass $entry Database entry to process
     * @return array|null Processed entry data or null if invalid
     */
    private function _process_single_entry(\stdClass $entry): ?array
    {
        if (!property_exists($entry, 'path') || !property_exists($entry, 'image_name')) {
            ee('jcogs_img:Utilities')->debug_message("Invalid cache log entry structure found");
            return null;
        }
        
        $entry_trimmed_path = trim($entry->path, '/');
        $entry_path_parts = pathinfo($entry_trimmed_path);
        $entry_cache_dir = $entry_path_parts['dirname'] ?? self::$default_cache_dir;
        $entry_filename = $entry_path_parts['basename'];
        
        return [
            'entry' => $entry,
            'trimmed_path' => $entry_trimmed_path,
            'cache_dir' => $entry_cache_dir,
            'filename' => $entry_filename
        ];
    }

    /**
     * End profiling a cache method call
     * 
     * @param string $profile_id Unique profile ID to end
     * @return void
     */
    private function _profile_cache_method_end(string $profile_id): void
    {
        if (!isset(self::$performance_log[$profile_id])) {
            return;
        }
        
        $end_time = microtime(true);
        $end_memory = memory_get_usage(true);
        $end_peak_memory = memory_get_peak_usage(true);
        
        self::$performance_log[$profile_id]['end_time'] = $end_time;
        self::$performance_log[$profile_id]['duration'] = $end_time - self::$performance_log[$profile_id]['start_time'];
        self::$performance_log[$profile_id]['memory_end'] = $end_memory;
        self::$performance_log[$profile_id]['memory_used'] = $end_memory - self::$performance_log[$profile_id]['memory_start'];
        self::$performance_log[$profile_id]['peak_memory_end'] = $end_peak_memory;
        self::$performance_log[$profile_id]['peak_memory_used'] = $end_peak_memory - self::$performance_log[$profile_id]['peak_memory_start'];
    }

    /**
     * Start profiling a cache method call
     * 
     * @param string $method_name Name of the method being profiled
     * @return string Unique profile ID for this call
     */
    private function _profile_cache_method_start(string $method_name): string
    {
        $profile_id = uniqid('cache_' . $method_name . '_', true);
        self::$performance_log[$profile_id] = [
            'method' => 'Cache::' . $method_name,
            'start_time' => microtime(true),
            'memory_start' => memory_get_usage(true),
            'peak_memory_start' => memory_get_peak_usage(true)
        ];
        return $profile_id;
    }

    /**
     * Rollback static cache on database failure
     * 
     * @param array $normalized_data Normalized cache data array
     * @return void
     */
    private function _rollback_static_cache(array $normalized_data): void
    {
        if (isset(static::$cache_log_index[static::$site_id][static::$adapter_name][$normalized_data['cache_dir']][$normalized_data['filename']])) {
            unset(static::$cache_log_index[static::$site_id][static::$adapter_name][$normalized_data['cache_dir']][$normalized_data['filename']]);
        }
    }

    /**
     * Safe JSON encoding with error handling
     * 
     * @param mixed $data Data to encode as JSON
     * @return string JSON-encoded string or empty string on error
     */
    private function _safe_json_encode(mixed $data): string
    {
        $encoded = json_encode($data);
        if ($encoded === false) {
            ee('jcogs_img:Utilities')->debug_message("JSON encoding failed: " . json_last_error_msg());
            return '';
        }
        return $encoded;
    }

    /**
     * Sanitize and validate cache directory
     * 
     * @param string|null $cache_dir Cache directory to sanitize
     * @return string Sanitized cache directory path
     */
    private function _sanitize_cache_directory(?string $cache_dir): string
    {
        if ($cache_dir === null || $cache_dir === '') {
            return self::$default_cache_dir;
        }
        
        $sanitized = trim($cache_dir, '/');
        return $sanitized === '' ? self::$default_cache_dir : $sanitized;
    }

    /**
     * Schedule cache update instead of immediate write for better performance
     * 
     * @param string $image_path Path to the image being cached
     * @param float|null $processing_time Time taken to process the image
     * @param array|null $vars Variables associated with the processed image
     * @param string|null $cache_dir Cache directory override
     * @param string|null $source_path Original source path of the image
     * @return void
     */
    private function _schedule_cache_update(string $image_path, ?float $processing_time = null, ?array $vars = null, ?string $cache_dir = null, ?string $source_path = null): void
    {
        // Validate required parameter
        if (empty($image_path)) {
            ee('jcogs_img:Utilities')->debug_message("Cannot schedule cache update: empty image_path");
            return;
        }
        
        $cache_key = md5($image_path . '|' . ($cache_dir ?? ''));
        
        // Prevent duplicate scheduling
        if (isset(self::$pending_cache_updates[$cache_key])) {
            return; // Already scheduled
        }
        
        // Rate limiting to prevent excessive cache operations
        if (self::$cache_operation_count >= self::$max_cache_operations_per_request) {
            return;
        }
        
        // Mark this cache entry for batch update with all required fields
        self::$pending_cache_updates[$cache_key] = [
            'image_path' => $image_path,
            'processing_time' => $processing_time,
            'vars' => $vars,
            'cache_dir' => $cache_dir ?? null,
            'source_path' => $source_path ?? null,
            'timestamp' => time()
        ];
        
        self::$cache_operation_count++;
        
        // Schedule batch flush on shutdown if not already scheduled
        if (!self::$cache_update_scheduled) {
            register_shutdown_function([self::class, '_flush_cache_updates_batch']);
            self::$cache_update_scheduled = true;
        }
    }

    /**
     * Update audit timestamp marker
     * 
     * @return void
     */
    private function _update_audit_timestamp(): void
    {
        ee('jcogs_img:Utilities')->cache_utility(
            'save', 
            JCOGS_IMG_CLASS . '/' . self::$cache_key_audit, 
            time(), 
            $this->settings['img_cp_default_cache_audit_after']
        );
    }

    /**
     * Update cumulative cache information with better error handling
     * 
     * @param string $cache_dir Cache directory path
     * @param array $tracker_stats Array of tracker statistics
     * @return void
     */
    private function _update_cumulative_cache_info(string $cache_dir, array $tracker_stats): void
    {
        try {
            $cache_key = $this->_generate_cache_status_key();
            $cache_status_info = ee()->cache->get($cache_key) ?: [];
            
            $this->_ensure_cache_status_structure($cache_status_info, $cache_dir);
            $this->_update_directory_stats($cache_status_info, $cache_dir, $tracker_stats);
            
            ee()->cache->save($cache_key, $cache_status_info, self::$cache_ttl_week);
            
        } catch (\Exception $e) {
            ee('jcogs_img:Utilities')->debug_message("Failed to update cumulative cache status info: " . $e->getMessage());
        }
    }

    /**
     * Update directory statistics
     * 
     * @param array $cache_status_info Cache status information array (passed by reference)
     * @param string $cache_dir Cache directory path
     * @param array $tracker_stats Array of tracker statistics
     * @return void
     */
    private function _update_directory_stats(array &$cache_status_info, string $cache_dir, array $tracker_stats): void
    {
        $dir_stats = &$cache_status_info[static::$site_id][static::$adapter_name][$cache_dir];
        
        $dir_stats['inception_date'] = ($dir_stats['inception_date'] > 0) ? 
            min($dir_stats['inception_date'], $tracker_stats['inception_date']) : 
            $tracker_stats['inception_date'];
            
        $dir_stats['count'] += $tracker_stats['count'];
        $dir_stats['cumulative_filesize'] += $tracker_stats['size'];
        $dir_stats['cumulative_processing_time'] += $tracker_stats['processing_time'];
    }

    /**
     * Update static cache with new entry
     * 
     * @param array $normalized_data Normalized cache data array
     * @param \stdClass $log_object Log object for static cache storage
     * @return void
     */
    private function _update_static_cache(array $normalized_data, \stdClass $log_object): void
    {
        $site_id = static::$site_id;
        $adapter_name = static::$adapter_name;
        $cache_dir = $normalized_data['cache_dir'];
        $filename = $normalized_data['filename'];
        
        // Initialize structure if needed
        if (!isset(static::$cache_log_index[$site_id])) {
            static::$cache_log_index[$site_id] = [];
        }
        if (!isset(static::$cache_log_index[$site_id][$adapter_name])) {
            static::$cache_log_index[$site_id][$adapter_name] = [];
        }
        if (!isset(static::$cache_log_index[$site_id][$adapter_name][$cache_dir])) {
            static::$cache_log_index[$site_id][$adapter_name][$cache_dir] = [];
        }
        
        // Only update if entry doesn't exist or is different
        $existing_entry = static::$cache_log_index[$site_id][$adapter_name][$cache_dir][$filename] ?? null;
        
        if (!$existing_entry || $this->_entries_differ($existing_entry, $log_object)) {
            static::$cache_log_index[$site_id][$adapter_name][$cache_dir][$filename] = $log_object;
            // ee('jcogs_img:Utilities')->debug_message("Static cache updated for: " . $filename);
        } else {
            // ee('jcogs_img:Utilities')->debug_message("Static cache entry unchanged for: " . $filename);
        }
    }

    /**
     * Validate audit prerequisites and check if audit is needed
     * 
     * @param bool $force Whether audit was forced
     * @return string Validation result code (continue, not_enabled, not_due, empty_cache_log)
     */
    private function _validate_audit_prerequisites(bool $force): string
    {
        // Check if cache auditing is enabled
        if (substr(strtolower($this->settings['img_cp_enable_cache_audit']), 0, 1) != 'y' || 
            !($this->settings['img_cp_default_cache_audit_after'] > 0)) {
            return 'not_enabled';
        }
        
        // Check if audit is due (unless forced)
        if (!$force && !$this->_is_audit_due()) {
            return 'not_due';
        }
        
        // Update cache log and validate it's not empty
        $this->get_file_info_from_cache_log();
        if (empty(static::$cache_log_index)) {
            return 'empty_cache_log';
        }
        
        return 'continue';
    }

    /**
     * Validate cache log prerequisites
     * 
     * @return bool True if prerequisites are met, false otherwise
     */
    private function _validate_cache_log_prerequisites(): bool
    {
        // Check if database table exists
        if (!ee()->db->table_exists(self::$table_name)) {
            ee('jcogs_img:Utilities')->debug_message("Cache log table does not exist: " . self::$table_name);
            return false;
        }
        
        // Ensure we have required static properties
        if (!isset(static::$site_id) || !isset(static::$adapter_name)) {
            ee('jcogs_img:Utilities')->debug_message("Missing required static properties: site_id or adapter_name");
            return false;
        }
        
        return true;
    }

    /**
     * Validate cache check inputs
     * 
     * @param string $image_path Path to the image file to check
     * @return bool True if inputs are valid, false otherwise
     */
    private function _validate_cache_check_inputs(string $image_path): bool
    {
        // Check if image path is provided and valid
        if (empty($image_path) || !is_string($image_path)) {
            ee('jcogs_img:Utilities')->debug_message("Invalid image path provided for cache check");
            return false;
        }
        
        // Check if path is too long (reasonable limit)
        if (strlen($image_path) > 1000) {
            ee('jcogs_img:Utilities')->debug_message("Image path too long for cache check: " . strlen($image_path) . " characters");
            return false;
        }
        
        return true;
    }

    /**
     * Validate cache log update inputs
     * 
     * @param string $image_path Path to the image being cached
     * @param float|null $processing_time Time taken to process the image
     * @param array|null $vars Variables associated with the processed image
     * @return bool True if inputs are valid, false otherwise
     */
    private function _validate_update_cache_log_inputs(string $image_path, ?float $processing_time, ?array $vars): bool
    {
        // Check if image path is provided and valid
        if (empty($image_path) || !is_string($image_path)) {
            ee('jcogs_img:Utilities')->debug_message("Invalid image path provided for cache log update");
            return false;
        }
        
        // Check if path is too long (reasonable limit)
        if (strlen($image_path) > 1000) {
            ee('jcogs_img:Utilities')->debug_message("Image path too long for cache log update: " . strlen($image_path) . " characters");
            return false;
        }
        
        // Validate processing time if provided
        if ($processing_time !== null && (!is_numeric($processing_time) || $processing_time < 0)) {
            ee('jcogs_img:Utilities')->debug_message("Invalid processing time provided: " . var_export($processing_time, true));
            return false;
        }
        
        // Validate vars if provided
        if ($vars !== null && !is_array($vars)) {
            ee('jcogs_img:Utilities')->debug_message("Invalid vars provided - must be array or null");
            return false;
        }
        
        return true;
    }
}