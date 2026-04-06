<?php

/**
 * JCOGS Image Pro Field - AfterFileDelete
 *=======================================
 * ExpressionEngine extension hook handler.
 *
 * Cleans up field augment cache rows and usage rows that referenced a deleted file,
 * preventing orphaned records.
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

namespace JCOGSDesign\JcogsImgProField\Extensions;

use ExpressionEngine\Service\Addon\Controllers\Extension\AbstractRoute;

/**
 * After-file-delete hook implementation.
 */
class AfterFileDelete extends AbstractRoute
{
    /**
     * Remove cached augments/usages that reference the deleted file.
     */
    public function process($file_model, $values): void
    {
        $site_id = (int) (ee()->config->item('site_id') ?: 1);
        $file_id = null;

        if (is_array($values) && isset($values['file_id']) && $values['file_id'] !== '') {
            $file_id = (int) $values['file_id'];
        } elseif (is_object($file_model) && isset($file_model->file_id)) {
            $file_id = (int) $file_model->file_id;
        }

        if (empty($file_id)) {
            return;
        }

        if (ee()->db->table_exists('jcogs_img_pro_field_file_augments')) {
            try {
                ee()->db
                    ->where('site_id', $site_id)
                    ->where('file_id', $file_id)
                    ->delete('jcogs_img_pro_field_file_augments');
            } catch (\Throwable $e) {
                // Fail-safe during uninstall/partial-schema states.
            }
        }

        // Also remove any per-entry usages that reference this file.
        // This is safe/idempotent and avoids orphaned usage rows.
        if (ee()->db->table_exists('jcogs_img_pro_field_usages')) {
            try {
                ee()->db
                    ->where('site_id', $site_id)
                    ->where('file_id', $file_id)
                    ->delete('jcogs_img_pro_field_usages');
            } catch (\Throwable $e) {
                // Fail-safe during uninstall/partial-schema states.
            }
        }
    }
}
