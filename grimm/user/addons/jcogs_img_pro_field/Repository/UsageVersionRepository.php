<?php

/**
 * JCOGS Image Pro Field - UsageVersionRepository
 *============================================
 * Low-level persistence helpers for jcogs_img_pro_field_usage_versions.
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

namespace JCOGSDesign\JcogsImgProField\Repository;

final class UsageVersionRepository
{
    private const TABLE = 'jcogs_img_pro_field_usage_versions';

    /**
     * Fetch usage version row for a revision.
     *
     * @return array<string, mixed>
     */
    /**
     * Fetch a usage snapshot row for a revision and context.
     *
     * @param int $versionId Revision ID from entry_versioning.
     * @param int $siteId Site ID.
     * @param int $entryId Entry ID.
     * @param int $fieldId Field ID.
     * @param string $contentType Content type (channel/grid/fluid/bloqs).
     * @param int|null $rowId Grid row ID when applicable.
     * @param int|null $fluidFieldDataId Fluid row ID when applicable.
     * @param int|null $blockId Bloqs block ID when applicable.
     * @return array<string, mixed>
     */
    public function fetchUsageVersionRow(
        int $versionId,
        int $siteId,
        int $entryId,
        int $fieldId,
        string $contentType = 'channel',
        ?int $rowId = null,
        ?int $fluidFieldDataId = null,
        ?int $blockId = null
    ): array
    {
        if ($versionId <= 0 || $siteId <= 0 || $entryId <= 0 || $fieldId <= 0) {
            return [];
        }

        try {
            $this->resetDbBuilder();

            $builder = ee()->db
                ->select('id, usage_payload')
                ->from(self::TABLE)
                ->where('version_id', $versionId)
                ->where('site_id', $siteId)
                ->where('entry_id', $entryId)
                ->where('field_id', $fieldId)
                ->where('content_type', $contentType);

            $this->applyContextFilters($builder, $rowId, $fluidFieldDataId, $blockId);

            $row = $builder->limit(1)->get()->row_array();

            return is_array($row) ? $row : [];
        } catch (\Throwable $e) {
            $this->resetDbBuilder();
            return [];
        }
    }

    /**
     * Delete all snapshot rows for a revision/entry.
     *
     * @param int $versionId Revision ID.
     * @param int $entryId Entry ID.
     * @param int $siteId Site ID.
     */
    public function deleteVersionRows(int $versionId, int $entryId, int $siteId): void
    {
        if ($versionId <= 0 || $entryId <= 0 || $siteId <= 0) {
            return;
        }

        try {
            $this->resetDbBuilder();
            ee()->db
                ->where('version_id', $versionId)
                ->where('site_id', $siteId)
                ->where('entry_id', $entryId)
                ->delete(self::TABLE);
        } catch (\Throwable $e) {
            $this->resetDbBuilder();
            // Fail safe.
        }
    }

    /**
     * Insert a snapshot row.
     *
     * @param array<string, mixed> $row Snapshot row data.
     */
    public function insertVersionRow(array $row): void
    {
        if (empty($row)) {
            return;
        }

        try {
            $this->resetDbBuilder();
            ee()->db->insert(self::TABLE, $row);
        } catch (\Throwable $e) {
            $this->resetDbBuilder();
            // Fail safe.
        }
    }

    /**
     * Best-effort reset of CI query builder state.
     */
    private function resetDbBuilder(): void
    {
        if (! isset(ee()->db) || ! is_object(ee()->db)) {
            return;
        }

        $db = ee()->db;
        if (method_exists($db, '_reset_select')) {
            $db->_reset_select();
        }
        if (method_exists($db, '_reset_write')) {
            $db->_reset_write();
        }
        if (method_exists($db, 'flush_cache')) {
            $db->flush_cache();
        }
    }

    /**
     * Apply context filters for composite usage rows.
     *
     * @param mixed $builder Query builder instance.
     * @param int|null $rowId Grid row ID when applicable.
     * @param int|null $fluidFieldDataId Fluid row ID when applicable.
     * @param int|null $blockId Bloqs block ID when applicable.
     */
    private function applyContextFilters($builder, ?int $rowId, ?int $fluidFieldDataId, ?int $blockId): void
    {
        if ($rowId !== null) {
            $builder->where('row_id', $rowId);
        } else {
            $builder->where('row_id IS NULL', null, false);
        }

        if ($fluidFieldDataId !== null) {
            $builder->where('fluid_field_data_id', $fluidFieldDataId);
        } else {
            $builder->where('fluid_field_data_id IS NULL', null, false);
        }

        if ($blockId !== null) {
            $builder->where('block_id', $blockId);
        } else {
            $builder->where('block_id IS NULL', null, false);
        }
    }
}

