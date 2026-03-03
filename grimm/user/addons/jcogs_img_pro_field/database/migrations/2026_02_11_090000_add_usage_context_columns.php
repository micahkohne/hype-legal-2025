<?php

/**
 * JCOGS Image Pro Field - Migration: Usage Context Columns
 *=========================================================
 * Adds row/container-aware identifiers to jcogs_img_pro_field_usages.
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
 * Migration: add usage context columns.
 */
class AddUsageContextColumns extends Migration
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

        // Update uniqueness to include context identifiers.
        if ($this->indexExists($table, 'uniq_site_entry_field')) {
            ee()->db->query("ALTER TABLE {$table} DROP INDEX uniq_site_entry_field");
        }
        if (! $this->indexExists($table, 'uniq_site_entry_field_ctx')) {
            ee()->db->query("ALTER TABLE {$table} ADD UNIQUE KEY uniq_site_entry_field_ctx (site_id, entry_id, field_id, content_type, row_id, fluid_field_data_id, block_id)");
        }

        // Supporting index for common lookups.
        if (! $this->indexExists($table, 'idx_site_entry_field_type')) {
            ee()->db->query("ALTER TABLE {$table} ADD KEY idx_site_entry_field_type (site_id, entry_id, field_id, content_type)");
        }
    }

    public function down()
    {
        ee()->load->dbforge();

        if (! ee()->db->table_exists('jcogs_img_pro_field_usages')) {
            return;
        }

        $table = ee()->db->dbprefix('jcogs_img_pro_field_usages');

        // Drop the expanded unique key and supporting index — guard each with an
        // existence check so repeated or partial rollbacks never throw 1091.
        if ($this->indexExists($table, 'uniq_site_entry_field_ctx')) {
            ee()->db->query("ALTER TABLE {$table} DROP INDEX uniq_site_entry_field_ctx");
        }
        if ($this->indexExists($table, 'idx_site_entry_field_type')) {
            ee()->db->query("ALTER TABLE {$table} DROP INDEX idx_site_entry_field_type");
        }

        // The context columns allowed multiple rows per (site_id, entry_id, field_id)
        // tuple (e.g. one per Grid row). Restoring the original narrow unique key would
        // fail if any such duplicates exist.  Delete duplicates first, keeping the
        // highest-id row for each tuple so the ADD UNIQUE KEY always succeeds.
        ee()->db->query("
            DELETE u1
            FROM {$table} u1
            INNER JOIN {$table} u2
                ON  u1.site_id  = u2.site_id
                AND u1.entry_id = u2.entry_id
                AND u1.field_id = u2.field_id
                AND u1.id < u2.id
        ");

        // Only add the narrow key if it is not already present.
        if (! $this->indexExists($table, 'uniq_site_entry_field')) {
            ee()->db->query("ALTER TABLE {$table} ADD UNIQUE KEY uniq_site_entry_field (site_id, entry_id, field_id)");
        }

        // Remove context columns if present.
        foreach (['block_id', 'fluid_field_data_id', 'row_id', 'container_id', 'content_type'] as $column) {
            if (ee()->db->field_exists($column, 'jcogs_img_pro_field_usages')) {
                ee()->dbforge->drop_column('jcogs_img_pro_field_usages', $column);
            }
        }
    }

    /**
     * Returns true if the named index exists on the given table.
     * Pass the prefixed table name (e.g. exp_jcogs_img_pro_field_usages).
     */
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

