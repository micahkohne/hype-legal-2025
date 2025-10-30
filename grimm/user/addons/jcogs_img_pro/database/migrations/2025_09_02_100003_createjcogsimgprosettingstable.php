<?php

use ExpressionEngine\Service\Migration\Migration;

class CreateJcogsImgProSettingsTable extends Migration
{
    /**
     * Execute the migration
     * @return void
     */
    public function up()
    {
        // Create Pro-specific settings table with proper schema
        ee()->load->library('smartforge');

        // Drop the table if it exists (handles both legacy and Pro versions)
        ee()->smartforge->drop_table('jcogs_img_pro_settings');

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
            'setting_name' => [
                'type' => 'varchar',
                'constraint' => 100,
                'null' => false
            ],
            'value' => [
                'type' => 'text',
                'null' => true
            ]
        ];

        // Add settings table and index
        ee()->load->library('smartforge');
        ee()->dbforge->add_field($fields);
        ee()->dbforge->add_key('id', TRUE);
        ee()->dbforge->create_table('jcogs_img_pro_settings');

        ee()->smartforge->add_key('jcogs_img_pro_settings','setting_name');

        // Initialize default settings
        $this->initializeDefaultSettings();
    }

    /**
     * Rollback the migration
     * @return void
     */
    public function down()
    {
        ee()->load->library('smartforge');
        ee()->smartforge->drop_table('jcogs_img_pro_settings');
    }

    /**
     * Initialize default Pro settings
     */
    private function initializeDefaultSettings()
    {
        $settings_service = ee('jcogs_img_pro:Settings');
        $settings_service->save_settings();
    }
}
