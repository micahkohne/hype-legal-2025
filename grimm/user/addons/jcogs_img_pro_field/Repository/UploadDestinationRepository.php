<?php

/**
 * JCOGS Image Pro Field - UploadDestinationRepository
 *===================================================
 * Wraps EE model access for upload destinations.
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.0
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 * @since      0.1.6
 */

namespace JCOGSDesign\JcogsImgProField\Repository;

/**
 * Repository for upload destination listings.
 */
final class UploadDestinationRepository
{
    /**
     * @return array<int, array{id:int, name:string}>
     */
    public function listBySite(int $siteId): array
    {
        if ($siteId <= 0) {
            return [];
        }

        try {
            $destinations = ee('Model')->get('UploadDestination')
                ->filter('site_id', $siteId)
                ->order('name', 'asc')
                ->all();

            $rows = [];
            foreach ($destinations as $dest) {
                $id = (int) $dest->getId();
                $name = trim((string) ($dest->name ?? ''));
                if ($id > 0 && $name !== '') {
                    $rows[] = ['id' => $id, 'name' => $name];
                }
            }

            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
