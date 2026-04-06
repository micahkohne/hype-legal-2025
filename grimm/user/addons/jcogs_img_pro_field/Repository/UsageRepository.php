<?php

/**
 * JCOGS Image Pro Field - UsageRepository
 *======================================
 * Low-level persistence helpers for jcogs_img_pro_field_usages.
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

namespace JCOGSDesign\JcogsImgProField\Repository;

/**
 * Repository wrapper for usage payload storage.
 */
final class UsageRepository
{
    private const TABLE = 'jcogs_img_pro_field_usages';

    /**
     * Fetch usage row for an entry/field.
     *
     * @return array<string, mixed>
     */
    public function fetchUsageRow(
        int $siteId,
        int $entryId,
        int $fieldId,
        string $contentType = 'channel',
        ?int $rowId = null,
        ?int $fluidFieldDataId = null,
        ?int $blockId = null
    ): array
    {
        if ($siteId <= 0 || $entryId <= 0 || $fieldId <= 0) {
            return [];
        }

        // For new composite field instances (before first save), context identifiers will be null.
        // We should NOT fetch usage payloads from other instances - return empty to avoid
        // inheriting settings from the wrong context (e.g., main field vs. new fluid instance).
        if ($contentType === 'fluid' && $fluidFieldDataId === null) {
            return [];
        }
        if ($contentType === 'grid' && $rowId === null) {
            return [];
        }
        if ($contentType === 'bloqs' && $blockId === null) {
            return [];
        }

        try {
            $this->resetDbBuilder();

            $builder = ee()->db
                ->select('id, usage_payload')
                ->from(self::TABLE)
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

    public function deleteUsageRowById(int $usageRowId): void
    {
        if ($usageRowId <= 0) {
            return;
        }

        try {
            $this->resetDbBuilder();
            ee()->db->where('id', $usageRowId)->delete(self::TABLE);
        } catch (\Throwable $e) {
            $this->resetDbBuilder();
            // Fail safe.
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    public function updateUsageRowById(int $usageRowId, array $row): void
    {
        if ($usageRowId <= 0) {
            return;
        }

        try {
            $this->resetDbBuilder();
            ee()->db->where('id', $usageRowId)->update(self::TABLE, $row);
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
     * @param object $builder
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
