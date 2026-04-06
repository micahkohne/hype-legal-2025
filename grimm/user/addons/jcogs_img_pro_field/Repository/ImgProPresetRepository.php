<?php

/**
 * JCOGS Image Pro Field - ImgProPresetRepository
 *==============================================
 * DB fallback for Image Pro presets (jcogs_img_pro_presets).
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
 * Repository for Image Pro preset lookup via the database.
 */
final class ImgProPresetRepository
{
    private const TABLE = 'jcogs_img_pro_presets';

    /**
     * @return array<int, array{id:int, name:string}>
     */
    public function fetchPresetsBySite(int $siteId): array
    {
        if ($siteId <= 0) {
            return [];
        }

        try {
            $this->resetDbBuilder();

            if (! ee()->db->table_exists(self::TABLE)) {
                return [];
            }

            $query = ee()->db
                ->select('id, name')
                ->from(self::TABLE)
                ->where('site_id', (int) $siteId)
                ->order_by('name', 'ASC')
                ->get();

            if (! $query || $query->num_rows() === 0) {
                return [];
            }

            $rows = [];
            foreach ($query->result_array() as $row) {
                $rows[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? ''),
                ];
            }

            return $rows;
        } catch (\Throwable $e) {
            $this->resetDbBuilder();
            return [];
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
}
