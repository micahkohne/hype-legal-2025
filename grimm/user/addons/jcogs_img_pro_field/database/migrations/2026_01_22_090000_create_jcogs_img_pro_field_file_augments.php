<?php

/**
 * JCOGS Image Pro Field - Migration: File Augments Table
 *======================================================
 * Creates the jcogs_img_pro_field_file_augments table.
 *
 * Stores per-file cached metadata used by the publish UI (fingerprints, cached
 * face detection result, and future EXIF snapshot).
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.2
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 * @since      0.1.6
 */

use ExpressionEngine\Service\Migration\Migration;

/**
 * Migration: create file augments table.
 */
class CreateJcogsImgProFieldFileAugments extends Migration
{
    public function up()
    {
        ee()->load->dbforge();

        if (ee()->db->table_exists('jcogs_img_pro_field_file_augments')) {
            return;
        }

        ee()->dbforge->add_field([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'site_id' => ['type' => 'INT', 'constraint' => 4, 'unsigned' => true, 'default' => 1],
            'file_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'default_preset_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'source_fingerprint' => ['type' => 'VARCHAR', 'constraint' => 128, 'default' => ''],
            'exif_snapshot' => ['type' => 'MEDIUMTEXT', 'null' => true],
            'face_detection_result' => ['type' => 'MEDIUMTEXT', 'null' => true],
            'created_date' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'modified_date' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
        ]);

        ee()->dbforge->add_key('id', true);
        ee()->dbforge->add_key(['site_id', 'file_id']);

        ee()->dbforge->create_table('jcogs_img_pro_field_file_augments', true);

        // Ensure one row per file per site
        $table = ee()->db->dbprefix('jcogs_img_pro_field_file_augments');
        ee()->db->query("ALTER TABLE {$table} ADD UNIQUE KEY uniq_site_file (site_id, file_id)");
    }

    public function down()
    {
        ee()->load->dbforge();
        if (ee()->db->table_exists('jcogs_img_pro_field_file_augments')) {
            ee()->dbforge->drop_table('jcogs_img_pro_field_file_augments');
        }
    }
}
