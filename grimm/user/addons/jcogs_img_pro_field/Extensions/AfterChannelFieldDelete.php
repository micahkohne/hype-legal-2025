<?php

/**
 * JCOGS Image Pro Field - AfterChannelFieldDelete
 *===============================================
 * ExpressionEngine extension hook handler.
 *
 * Deletes per-field usage rows for this fieldtype when a Channel Field is deleted.
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
 * After-field-delete hook implementation.
 */
class AfterChannelFieldDelete extends AbstractRoute
{
    /**
     * Delete usage rows for the deleted field.
     */
    public function process($field, $data): void
    {
        $site_id = (int) (ee()->config->item('site_id') ?: 1);
        $field_id = null;

        if (is_array($data) && isset($data['field_id']) && $data['field_id'] !== '') {
            $field_id = (int) $data['field_id'];
        } elseif (is_object($field) && isset($field->field_id)) {
            $field_id = (int) $field->field_id;
        }

        if (empty($field_id)) {
            return;
        }

        if (! ee()->db->table_exists('jcogs_img_pro_field_usages')) {
            return;
        }

        try {
            ee()->db
                ->where('site_id', $site_id)
                ->where('field_id', $field_id)
                ->delete('jcogs_img_pro_field_usages');
        } catch (\Throwable $e) {
            // Fail-safe during uninstall/partial-schema states.
        }
    }
}
