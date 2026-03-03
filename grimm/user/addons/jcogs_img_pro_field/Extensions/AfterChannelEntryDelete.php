<?php

/**
 * JCOGS Image Pro Field - AfterChannelEntryDelete
 *===============================================
 * ExpressionEngine extension hook handler.
 *
 * Deletes per-entry usage rows for this fieldtype when a Channel Entry is deleted.
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

namespace JCOGSDesign\JcogsImgProField\Extensions;

use ExpressionEngine\Service\Addon\Controllers\Extension\AbstractRoute;

/**
 * After-entry-delete hook implementation.
 */
class AfterChannelEntryDelete extends AbstractRoute
{
    /**
     * Delete usage rows for the deleted entry.
     */
    public function process($entry, $data): void
    {
        $site_id = (int) (ee()->config->item('site_id') ?: 1);
        $entry_id = null;

        if (is_array($data) && isset($data['entry_id']) && $data['entry_id'] !== '') {
            $entry_id = (int) $data['entry_id'];
        } elseif (is_object($entry) && isset($entry->entry_id)) {
            $entry_id = (int) $entry->entry_id;
        }

        if (empty($entry_id)) {
            return;
        }

        if (! ee()->db->table_exists('jcogs_img_pro_field_usages')) {
            return;
        }

        try {
            ee()->db
                ->where('site_id', $site_id)
                ->where('entry_id', $entry_id)
                ->delete('jcogs_img_pro_field_usages');
        } catch (\Throwable $e) {
            // Fail-safe during uninstall/partial-schema states.
        }
    }
}
