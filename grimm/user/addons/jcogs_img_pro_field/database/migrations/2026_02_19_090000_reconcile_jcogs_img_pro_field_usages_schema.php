<?php

/**
 * JCOGS Image Pro Field - Migration: Reconcile Usages Schema
 *===========================================================
 * Ensures the usages table has context columns and indexes required by
 * context-aware persistence.
 */

use ExpressionEngine\Service\Migration\Migration;

class ReconcileJcogsImgProFieldUsagesSchema extends Migration
{
    public function up()
    {
        ee()->load->dbforge();

        if (! ee()->db->table_exists('jcogs_img_pro_field_usages')) {
            return;
        }

        $table = ee()->db->dbprefix('jcogs_img_pro_field_usages');

        if (! ee()->db->field_exists('content_type', 'jcogs_img_pro_field_usages')) {
            ee()->dbforge->add_column('jcogs_img_pro_field_usages', [
                'content_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'channel'],
            ]);
        }

        if (! ee()->db->field_exists('container_id', 'jcogs_img_pro_field_usages')) {
            ee()->dbforge->add_column('jcogs_img_pro_field_usages', [
                'container_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            ]);
        }

        if (! ee()->db->field_exists('row_id', 'jcogs_img_pro_field_usages')) {
            ee()->dbforge->add_column('jcogs_img_pro_field_usages', [
                'row_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            ]);
        }

        if (! ee()->db->field_exists('fluid_field_data_id', 'jcogs_img_pro_field_usages')) {
            ee()->dbforge->add_column('jcogs_img_pro_field_usages', [
                'fluid_field_data_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            ]);
        }

        if (! ee()->db->field_exists('block_id', 'jcogs_img_pro_field_usages')) {
            ee()->dbforge->add_column('jcogs_img_pro_field_usages', [
                'block_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            ]);
        }

        if ($this->indexExists($table, 'uniq_site_entry_field')) {
            ee()->db->query("ALTER TABLE {$table} DROP INDEX uniq_site_entry_field");
        }

        if (! $this->indexExists($table, 'uniq_site_entry_field_ctx')) {
            ee()->db->query("ALTER TABLE {$table} ADD UNIQUE KEY uniq_site_entry_field_ctx (site_id, entry_id, field_id, content_type, row_id, fluid_field_data_id, block_id)");
        }

        if (! $this->indexExists($table, 'idx_site_entry_field_type')) {
            ee()->db->query("ALTER TABLE {$table} ADD KEY idx_site_entry_field_type (site_id, entry_id, field_id, content_type)");
        }
    }

    public function down()
    {
        // No-op: schema reconciliation migration should not remove columns/indexes.
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = ee()->db->query(
            'SELECT COUNT(*) AS cnt
             FROM information_schema.STATISTICS
             WHERE table_schema = DATABASE()
               AND table_name   = ?
               AND index_name   = ?',
            [$table, $indexName]
        );

        return (int) $result->row()->cnt > 0;
    }
}
