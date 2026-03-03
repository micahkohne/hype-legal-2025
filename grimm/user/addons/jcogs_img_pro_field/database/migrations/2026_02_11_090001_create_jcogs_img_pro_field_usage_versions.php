<?php

/**
 * JCOGS Image Pro Field - Migration: Usage Versions Table
 *========================================================
 * Creates the jcogs_img_pro_field_usage_versions table.
 *
 * Stores per-revision snapshots of usage payloads.
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.0
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 * @since      1.0.1
 */

use ExpressionEngine\Service\Migration\Migration;

/**
 * Migration: create usage versions table.
 */
class CreateJcogsImgProFieldUsageVersions extends Migration
{
    public function up()
    {
        ee()->load->dbforge();

        if (ee()->db->table_exists('jcogs_img_pro_field_usage_versions')) {
            return;
        }

        ee()->dbforge->add_field([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'version_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'site_id' => ['type' => 'INT', 'constraint' => 4, 'unsigned' => true, 'default' => 1],
            'entry_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'field_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'file_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'content_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'channel'],
            'container_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'row_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'fluid_field_data_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'block_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'usage_payload' => ['type' => 'MEDIUMTEXT'],
            'created_date' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'created_by_member_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
        ]);

        ee()->dbforge->add_key('id', true);
        ee()->dbforge->add_key('version_id');
        ee()->dbforge->add_key(['site_id', 'entry_id', 'field_id', 'content_type']);

        ee()->dbforge->create_table('jcogs_img_pro_field_usage_versions', true);
    }

    public function down()
    {
        ee()->load->dbforge();
        if (ee()->db->table_exists('jcogs_img_pro_field_usage_versions')) {
            ee()->dbforge->drop_table('jcogs_img_pro_field_usage_versions');
        }
    }
}

