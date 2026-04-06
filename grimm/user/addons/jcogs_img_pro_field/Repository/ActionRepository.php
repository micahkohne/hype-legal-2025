<?php

/**
 * JCOGS Image Pro Field - ActionRepository
 *=========================================
 * Low-level lookup helpers for EE's actions table.
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

final class ActionRepository
{
    /**
     * Find the action_id for a class/method pair.
     */
    public function findActionId(string $class, string $method): int
    {
        $class = trim($class);
        $method = trim($method);
        if ($class === '' || $method === '') {
            return 0;
        }

        try {
            $row = ee()->db
                ->select('action_id')
                ->from('actions')
                ->where('class', $class)
                ->where('method', $method)
                ->limit(1)
                ->get()
                ->row_array();

            return (int) ($row['action_id'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
