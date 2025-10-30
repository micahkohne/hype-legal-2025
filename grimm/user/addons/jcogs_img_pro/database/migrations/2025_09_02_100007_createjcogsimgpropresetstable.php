<?php

use ExpressionEngine\Service\Migration\Migration;

class CreateJcogsImgProPresetsTable extends Migration
{
    /**
     * Execute the migration
     * Creates the presets table for storing preset configurations
     * @return void
     */
    public function up()
    {
        // Define table fields following EE7 migration patterns
        $fields = [
            'id' => [
                'type' => 'int',
                'constraint' => 10,
                'unsigned' => true,
                'null' => false,
                'auto_increment' => true
            ],
            'site_id' => [
                'type' => 'int',
                'constraint' => 10,
                'unsigned' => true,
                'null' => false
            ],
            'name' => [
                'type' => 'varchar',
                'constraint' => 100,
                'null' => false
            ],
            'description' => [
                'type' => 'text',
                'null' => true
            ],
            'parameters' => [
                'type' => 'json',
                'null' => false
            ],
            'sample_file_id' => [
                'type' => 'int',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true
            ],
            'created_date' => [
                'type' => 'int',
                'constraint' => 10,
                'unsigned' => true,
                'null' => false
            ],
            'modified_date' => [
                'type' => 'int',
                'constraint' => 10,
                'unsigned' => true,
                'null' => false
            ],
            'usage_count' => [
                'type' => 'int',
                'constraint' => 10,
                'unsigned' => true,
                'null' => false,
                'default' => 0
            ],
            'last_used_date' => [
                'type' => 'int',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true
            ],
            'error_count' => [
                'type' => 'int',
                'constraint' => 10,
                'unsigned' => true,
                'null' => false,
                'default' => 0
            ],
            'last_error_date' => [
                'type' => 'int',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true
            ],
            'performance_data' => [
                'type' => 'json',
                'null' => true
            ]
        ];

        // Create the table
        ee()->dbforge->add_field($fields);
        ee()->dbforge->add_key('id', true);
        ee()->dbforge->create_table('jcogs_img_pro_presets');

        // Add indexes using smartforge for better compatibility
        ee()->load->library('smartforge');
        ee()->smartforge->add_key('jcogs_img_pro_presets', 'site_id');
        ee()->smartforge->add_key('jcogs_img_pro_presets', 'name');
        ee()->smartforge->add_key('jcogs_img_pro_presets', 'usage_count');
        ee()->smartforge->add_key('jcogs_img_pro_presets', 'last_used_date');
        
        // Add unique constraint for site_id + name combination
        ee()->smartforge->add_key('jcogs_img_pro_presets', ['site_id', 'name'], 'unique_preset_per_site');

        // Log the migration
        if (function_exists('log_message')) {
            log_message('debug', 'JCOGS Image Pro: Created presets table with JSON parameter storage and analytics columns');
        }
    }

    /**
     * Rollback the migration
     * Drops the presets table
     * @return void
     */
    public function down()
    {
        ee()->load->library('smartforge');
        ee()->smartforge->drop_table('jcogs_img_pro_presets');
        
        if (function_exists('log_message')) {
            log_message('debug', 'JCOGS Image Pro: Dropped presets table');
        }
    }
}
