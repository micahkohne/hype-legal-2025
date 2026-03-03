<?php

/**
 * JCOGS Image Pro Field - UsageVersioningService
 *==============================================
 * Creates per-revision snapshots of usage payloads.
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

namespace JCOGSDesign\JcogsImgProField\Service;

class UsageVersioningService
{
    /**
     * Snapshot current usage payloads for an entry/version.
     */
    public function snapshotEntryUsage(int $entryId, int $versionId, ?int $siteId = null): void
    {
        $entryId = (int) $entryId;
        $versionId = (int) $versionId;
        if ($entryId <= 0 || $versionId <= 0) {
            return;
        }

        $siteId = (int) ($siteId ?? (ee()->config->item('site_id') ?: 1));
        if ($siteId <= 0) {
            $siteId = 1;
        }

        $repo = ServiceCache::usage_version_repo();

        // Clear any prior snapshots for this version/entry.
        $repo->deleteVersionRows($versionId, $entryId, $siteId);

        $rows = $this->fetchUsageRows($siteId, $entryId);
        if (empty($rows)) {
            return;
        }

        $now = (int) (ee()->localize->now ?? time());
        $memberId = null;
        if (isset(ee()->session) && isset(ee()->session->userdata['member_id'])) {
            $memberId = (int) ee()->session->userdata['member_id'];
        }

        foreach ($rows as $row) {
            $repo->insertVersionRow([
                'version_id' => $versionId,
                'site_id' => $siteId,
                'entry_id' => $entryId,
                'field_id' => (int) ($row['field_id'] ?? 0),
                'file_id' => (int) ($row['file_id'] ?? 0),
                'content_type' => (string) ($row['content_type'] ?? 'channel'),
                'container_id' => $row['container_id'] ?? null,
                'row_id' => $row['row_id'] ?? null,
                'fluid_field_data_id' => $row['fluid_field_data_id'] ?? null,
                'block_id' => $row['block_id'] ?? null,
                'usage_payload' => (string) ($row['usage_payload'] ?? ''),
                'created_date' => $now,
                'created_by_member_id' => $memberId,
            ]);
        }
    }

    /**
     * Resolve the latest version_id for an entry.
     */
    public function getLatestVersionId(int $entryId): int
    {
        if ($entryId <= 0) {
            return 0;
        }

        try {
            $row = ee()->db
                ->select('version_id')
                ->from('entry_versioning')
                ->where('entry_id', $entryId)
                ->order_by('version_date', 'desc')
                ->limit(1)
                ->get()
                ->row_array();

            return isset($row['version_id']) ? (int) $row['version_id'] : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Fetch all usage rows for an entry (including composite contexts).
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchUsageRows(int $siteId, int $entryId): array
    {
        try {
            $builder = ee()->db
                ->select('field_id, file_id, usage_payload, content_type, container_id, row_id, fluid_field_data_id, block_id')
                ->from('jcogs_img_pro_field_usages')
                ->where('site_id', $siteId)
                ->where('entry_id', $entryId);

            $rows = $builder->get()->result_array();
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}

