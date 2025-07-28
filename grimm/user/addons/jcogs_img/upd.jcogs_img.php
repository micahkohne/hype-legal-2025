<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Image Updater
 * =============
 * Updater for JCOGS Image add-on
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.4.16.2
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.0.0
 */

require_once PATH_THIRD . "jcogs_img/config.php";

class Jcogs_img_upd
{

    public function install()
    {
        ee()->load->dbforge();
        ee()->load->library('smartforge');

        $data = array(
            'module_name'    => JCOGS_IMG_CLASS,
            'module_version' => JCOGS_IMG_VERSION,
            'has_cp_backend' => 'y'
        );

        ee()->db->insert('modules', $data);

        // Register hooks
        $this->_register_hook('template_post_parse', 'template_post_parse');
        $this->_register_hook('after_file_save', 'after_file_update');
        $this->_register_hook('after_file_delete', 'after_file_update');
        $this->_register_hook('cp_custom_menu', 'cp_custom_menu');
        $this->_register_hook('speedy_pre_parse', 'speedy_pre_parse');

        // Register action
        $this->_register_action('act_originated_image', true);

        // Create settings db table
        $fields = array(
            'id'                  => array('type' => 'int', 'constraint' => '10', 'unsigned' => true, 'null' => false, 'auto_increment' => true),
            'site_id'             => array('type' => 'INT'),
            'setting_name'        => array('type' => 'VARCHAR', 'constraint' => '100'),
            'value'               => array('type' => 'TEXT')
        );

        // Delete table if it already exists
        ee()->smartforge->drop_table('jcogs_img_settings');

        ee()->dbforge->add_field($fields);
        ee()->dbforge->add_key('id', TRUE);
        ee()->dbforge->create_table('jcogs_img_settings');
        
        // Add settings table index
        ee()->smartforge->add_key('jcogs_img_settings', 'setting_name', 'setting_name_index');

        // Save settings to instantiate default values
        ee('jcogs_img:Settings')->save_settings();
        
        // Create image_cache log table
        ee()->smartforge->drop_table('jcogs_img_cache_log'); // drop the table in case an earlier version exists
        $fields = array(
            'id'                  => array('type' => 'int', 'constraint' => '10', 'unsigned' => true, 'null' => false, 'auto_increment' => true),
            'site_id'             => array('type' => 'INT'),
            'adapter_name'        => array('type' => 'VARCHAR', 'constraint' => '100'),
            'path'                => array('type' => 'VARCHAR', 'constraint' => '256'),
            'image_name'          => array('type' => 'VARCHAR', 'constraint' => '256'),
            'stats'               => array('type' => 'TEXT'),
            'values'              => array('type' => 'TEXT')
        );
        ee()->dbforge->add_field($fields);
        ee()->dbforge->add_key('id', TRUE);
        ee()->dbforge->create_table('jcogs_img_cache_log');

        // Add cache log composite index
        ee()->smartforge->add_key('jcogs_img_cache_log', ['site_id', 'adapter_name', 'path'], 'cache_log_key');

        // Clear the EE cache just for safety reasons
        $this->_clear_cache();

        // Clear the EE Jump Menu to be sure our text gets added
        ee('CP/JumpMenu')->clearAllCaches();

        return true;
    }

    public function update($current = '')
    {
        ee()->load->dbforge();
        ee()->load->library('smartforge');

        // Update add-on version information
        ee('jcogs_img:Settings')::$settings['jcogs_add_on_class'] = JCOGS_IMG_CLASS;
        ee('jcogs_img:Settings')::$settings['jcogs_add_on_name'] = JCOGS_IMG_NAME;
        ee('jcogs_img:Settings')::$settings['jcogs_add_on_version'] = JCOGS_IMG_VERSION;
        ee('jcogs_img:Settings')::$settings['img_cp_enable_cache_audit'] = 'y';

        // Fix default value for maximum length of processed filename to 175 if greater (#460)
        if (array_key_exists('img_cp_max_filename_length', ee('jcogs_img:Settings')::$settings) && ee('jcogs_img:Settings')::$settings['img_cp_max_filename_length'] > 175) {
            ee('jcogs_img:Settings')::$settings['img_cp_max_filename_length'] = 175;
        }

        // Register hooks
        $this->_register_hook('template_post_parse', 'template_post_parse');
        $this->_register_hook('after_file_save', 'after_file_update');
        $this->_register_hook('after_file_delete', 'after_file_update');
        $this->_register_hook('cp_custom_menu', 'cp_custom_menu');
        $this->_register_hook('speedy_pre_parse', 'speedy_pre_parse');

        // Add action for ACT calling of Image
        $this->_register_action('act_originated_image', true);

        // Check and update jcogs_img_cache_log table structure
        $table_exists = ee()->db->table_exists('jcogs_img_cache_log');
        $table_structure_valid = false;
        
        if ($table_exists) {
            // Check if table has the required structure
            $required_fields = [
                'id' =>             ['type' => 'int', 'constraint' => '10'],
                'site_id' =>        ['type' => 'int'],
                'adapter_name' =>   ['type' => 'varchar', 'constraint' => '100'],
                'path' =>           ['type' => 'varchar', 'constraint' => '256'],
                'image_name' =>     ['type' => 'varchar', 'constraint' => '256'],
                'stats' =>          ['type' => 'text'],
                'values' =>         ['type' => 'text']
            ];
            
            $existing_fields = ee()->db->field_data('jcogs_img_cache_log');
            $table_structure_valid = true;
            
            // Check if all required fields exist with correct types
            foreach ($required_fields as $field_name => $field_spec) {
                $field_found = false;
                foreach ($existing_fields as $existing_field) {
                    if (strtolower($existing_field->name) === $field_name) {
                        $field_found = true;
                        // Simple type check (comparing lowercase types)
                        $existing_type = strtolower($existing_field->type);
                        $required_type = strtolower($field_spec['type']);
                        
                        if (strpos($existing_type, $required_type) === false) {
                            $table_structure_valid = false;
                            break;
                        }
                    }
                }
                if (!$field_found) {
                    $table_structure_valid = false;
                    break;
                }
            }
        }
        
        if ($table_exists && $table_structure_valid) {
            // Table exists and has correct structure - update keys
            // Drop old individual keys if they exist
            ee()->smartforge->drop_key('jcogs_img_cache_log', 'site_id_index');
            ee()->smartforge->drop_key('jcogs_img_cache_log', 'adapter_name_index');
            ee()->smartforge->drop_key('jcogs_img_cache_log', 'path_index');
            ee()->smartforge->drop_key('jcogs_img_cache_log', 'image_name_index');
            // Drop old composite key if it exists
            ee()->smartforge->drop_key('jcogs_img_cache_log', 'cache_log_key');
            // Drop old index on settings table if it exists
            ee()->smartforge->drop_key('jcogs_img_settings', 'setting_name_index');
            
            // Add composite key and settings key
            ee()->smartforge->add_key('jcogs_img_cache_log', ['site_id', 'adapter_name', 'path'], 'cache_log_key');
            ee()->smartforge->add_key('jcogs_img_settings', 'setting_name', 'setting_name_index');
        } else {
            // Table doesn't exist or has wrong structure - recreate it
            ee()->smartforge->drop_table('jcogs_img_cache_log');
            
            $fields = array(
                'id'                  => array('type' => 'int', 'constraint' => '10', 'unsigned' => true, 'null' => false, 'auto_increment' => true),
                'site_id'             => array('type' => 'INT'),
                'adapter_name'        => array('type' => 'VARCHAR', 'constraint' => '100'),
                'path'                => array('type' => 'VARCHAR', 'constraint' => '256'),
                'image_name'          => array('type' => 'VARCHAR', 'constraint' => '256'),
                'stats'               => array('type' => 'TEXT'),
                'values'              => array('type' => 'TEXT')
            );
            
            ee()->dbforge->add_field($fields);
            ee()->dbforge->add_key('id', TRUE);
            ee()->dbforge->create_table('jcogs_img_cache_log');
            
            // Add keys to the new table
            ee()->smartforge->add_key('jcogs_img_cache_log', ['site_id', 'adapter_name', 'path'], 'cache_log_key');
            ee()->smartforge->add_key('jcogs_img_settings', 'setting_name', 'setting_name_index');
        }

        // In case user has set value for PNG quality copy over to new variable
        if (array_key_exists('img_cp_jpg_default_png_quality', ee('jcogs_img:Settings')::$settings) && ee('jcogs_img:Settings')::$settings['img_cp_jpg_default_png_quality'] != '-1') {
            ee('jcogs_img:Settings')::$settings['img_cp_png_default_quality'] = ee('jcogs_img:Settings')::$settings['img_cp_jpg_default_png_quality'];
            // Resave settings to instantiate new parameters
        }

        // Reset the values for obscured cloud file adapters to match unobscured values
        ee('jcogs_img:Settings')::$settings['img_cp_flysystem_adapter_s3_secret'] = ee('jcogs_img:Settings')::$settings['img_cp_flysystem_adapter_s3_secret_actual'];
        ee('jcogs_img:Settings')::$settings['img_cp_flysystem_adapter_r2_secret'] = ee('jcogs_img:Settings')::$settings['img_cp_flysystem_adapter_r2_secret_actual'];
        ee('jcogs_img:Settings')::$settings['img_cp_flysystem_adapter_dospaces_secret'] = ee('jcogs_img:Settings')::$settings['img_cp_flysystem_adapter_dospaces_secret_actual'];

        // Update caching threshold cache count
        $current_count = ee('jcogs_img:ImageUtilities')->get_current_cache_log_count();
        ee('jcogs_img:Settings')::$settings['img_cp_cache_log_current_count'] = $current_count;
        ee('jcogs_img:Settings')::$settings['img_cp_cache_log_count_last_updated'] = time();
        if ($current_count > 10000) {
            // If the current count is greater than 10,000, disable the automatic cache audit
            ee('jcogs_img:Settings')::$settings['img_cp_enable_cache_audit'] = 'n';
        }

        // Resave settings to instantiate new parameters
        ee('jcogs_img:Settings')->save_settings();

        // Clear any cache status info files from EE cache
        $this->_clear_cache();

        // Clear Jump Menu cache to insert new text
        ee('CP/JumpMenu')->clearAllCaches();

        return true;
    }

    public function uninstall()
    {
        ee()->load->library('smartforge');

        // Clear the EE caches just for safety reasons
        ee()->cache->delete('/'.JCOGS_IMG_CLASS.'/');

        //delete the module
        ee()->db->where('module_name', JCOGS_IMG_CLASS);
        ee()->db->delete('modules');

        //remove actions
        ee()->db->where('class', JCOGS_IMG_CLASS);
        ee()->db->delete('actions');

        //remove extensions
        ee()->db->where('class', JCOGS_IMG_CLASS . '_ext');
        ee()->db->delete('extensions');

        ee()->db->where('module_name', 'jcogs_img');
        ee()->db->delete('modules');

        //Remove the settings table from the database
        ee()->smartforge->drop_table('jcogs_img_settings');

        //Remove the image cache log table from the database
        ee()->smartforge->drop_table('jcogs_img_cache_log');

        return true;
    }

    private function _clear_cache()
    {
        // Attempt to clear OPcache
        if (function_exists('opcache_reset')) {
            // The opcache_reset() function is subject to restrictions
            // by the opcache.restrict_api directive in php.ini.
            // If the directive is set and the current script's path
            // is not allowed, this call will fail or do nothing.
            $opcache_cleared = opcache_reset();

            if ($opcache_cleared) {
                ee()->logger->developer('JCOGS Img: OPcache reset attempted and reported success during update.');
            } else {
                ee()->logger->developer('JCOGS Img: OPcache reset attempted but reported failure (possibly due to restrict_api).');
            }
        } else {
            ee()->logger->developer('JCOGS Img: opcache_reset() function does not exist. OPcache may not be enabled or available.');
        }
        // Clear the EE cache for safety reasons
        ee()->cache->delete('/'.JCOGS_IMG_CLASS.'/');
    }

    private function _register_action($method, $csrf_exempt = 0)
    {
        if (ee()->db->where('class',JCOGS_IMG_CLASS)
                ->where('method', $method)
                ->count_all_results('actions') == 0)
        {
            // Remove any previous action for this class / method
            $this->_remove_action($method);

            ee()->db->insert('actions', array(
                'class' => JCOGS_IMG_CLASS,
                'method' => $method,
                'csrf_exempt' => $csrf_exempt
            ));
        }
    }

    private function _remove_action($method)
    {
        if (ee()->db->where('class',JCOGS_IMG_CLASS)
                ->where('method', $method)
                ->count_all_results('actions') != 0)
        {
            ee()->db->delete('actions', array(
                'class' => JCOGS_IMG_CLASS,
                'method' => $method,
            ));
        }
    }

    private function _register_hook($hook, $method = NULL, $priority = 10)
    {
        if (is_null($method)) {
            $method = $hook;
        }

        if (
            ee()->db->where('class', JCOGS_IMG_CLASS.'_ext')
            ->where('hook', $hook)
            ->count_all_results('extensions') == 0
        ) {
            ee()->db->insert('extensions', array(
                'class'        => JCOGS_IMG_CLASS . '_ext',
                'method'    => $method,
                'hook'        => $hook,
                'settings'    => '',
                'priority'    => $priority,
                'version'    => JCOGS_IMG_VERSION,
                'enabled'    => 'y'
            ));
        }
    }
    
    private function _remove_hook($hook = null)
    {
        if (is_null($hook))
        {
            return;
        }
 
        if (ee()->db->where('class', JCOGS_IMG_CLASS.'_ext')
                ->where('hook', $hook)
                ->count_all_results('extensions') > 0)
        {
            ee()->db->delete('extensions', array(
                'class'		=> JCOGS_IMG_CLASS.'_ext',
                'hook'		=> $hook,
            ));
        }
    }
}
