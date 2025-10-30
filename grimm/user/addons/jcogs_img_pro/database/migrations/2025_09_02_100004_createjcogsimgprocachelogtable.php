<?php

use ExpressionEngine\Service\Migration\Migration;

class CreateJcogsImgProCacheLogTable extends Migration
{
    /**
     * Execute the migration
     * @return void
     */
    public function up()
    {
        // Create Pro-specific cache log table with proper schema
        ee()->load->library('smartforge');

        // Drop the table if it exists (handles both legacy and Pro versions)
        ee()->smartforge->drop_table('jcogs_img_pro_cache_log');

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
            'adapter_name' => [
                'type' => 'varchar',
                'constraint' => 100,
                'null' => false
            ],
            'adapter_type' => [
                'type' => 'varchar',
                'constraint' => 50,
                'null' => false,
                'default' => 'local'
            ],
            'path' => [
                'type' => 'varchar',
                'constraint' => 256,
                'null' => false
            ],
            'image_name' => [
                'type' => 'varchar',
                'constraint' => 256,
                'null' => false
            ],
            'stats' => [
                'type' => 'text',
                'null' => true
            ],
            'values' => [
                'type' => 'text',
                'null' => true
            ]
        ];

        ee()->dbforge->add_field($fields);
        ee()->dbforge->add_key('id', TRUE);
        ee()->dbforge->create_table('jcogs_img_pro_cache_log');

        // Add composite index including adapter_type
        ee()->smartforge->add_key('jcogs_img_pro_cache_log', ['site_id', 'adapter_name', 'adapter_type', 'path'], 'cache_log_key');
    }

    /**
     * Undo the migration
     * @return void
     */
    public function down()
    {
        ee()->load->library('smartforge');
        ee()->smartforge->drop_table('jcogs_img_pro_cache_log');
    }
}