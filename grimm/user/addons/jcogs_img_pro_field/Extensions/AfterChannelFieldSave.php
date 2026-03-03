<?php

/**
 * JCOGS Image Pro Field - AfterChannelFieldSave
 *=============================================
 * ExpressionEngine extension hook handler.
 *
 * Performs hygiene clean-up when channel fields change:
 * - If a field changes away from this fieldtype: purge stored usage rows.
 * - Remove orphan rows for missing entries.
 * - Remove usage rows when the field is no longer assigned to an entry’s channel.
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
 * After-channel-field-save hook implementation.
 */
class AfterChannelFieldSave extends AbstractRoute
{
    private function extractInt($values, string $key): ?int
    {
        if (is_array($values) && isset($values[$key]) && $values[$key] !== '') {
            return (int) $values[$key];
        }

        return null;
    }

    private function extractString($values, string $key): string
    {
        if (is_array($values) && isset($values[$key]) && $values[$key] !== null) {
            return (string) $values[$key];
        }

        return '';
    }

    /**
     * Clean usage rows impacted by field edits.
     */
    public function process($field, $values): void
    {
        $site_id = (int) (ee()->config->item('site_id') ?: 1);

        $field_id = $this->extractInt($values, 'field_id')
            ?? (is_object($field) && isset($field->field_id) ? (int) $field->field_id : null);

        if (empty($field_id)) {
            return;
        }

        if (! ee()->db->table_exists('jcogs_img_pro_field_usages')) {
            return;
        }

        $field_type = $this->extractString($values, 'field_type');
        if ($field_type === '' && is_object($field) && isset($field->field_type)) {
            $field_type = (string) $field->field_type;
        }

        // If the field is no longer our fieldtype, remove all usage rows for it.
        if ($field_type !== '' && $field_type !== 'jcogs_img_pro_field') {
            try {
                ee()->db
                    ->where('site_id', $site_id)
                    ->where('field_id', $field_id)
                    ->delete('jcogs_img_pro_field_usages');
            } catch (\Throwable $e) {
                // Fail-safe during uninstall/partial-schema states.
            }
            return;
        }

        // Cleanup orphans where the entry no longer exists.
        $prefix = ee()->db->dbprefix;
        $usages = $prefix . 'jcogs_img_pro_field_usages';
        $titles = $prefix . 'channel_titles';

        try {
            ee()->db->query(
                "DELETE u FROM {$usages} u "
                . "LEFT JOIN {$titles} t ON t.entry_id = u.entry_id AND t.site_id = u.site_id "
                . "WHERE u.site_id = ? AND u.field_id = ? AND t.entry_id IS NULL",
                [$site_id, $field_id]
            );
        } catch (\Throwable $e) {
            // Fail-safe during uninstall/partial-schema states.
        }

        // Cleanup when a field is removed from a channel: keep rows only where the entry's channel still has this field.
        // Supports both direct channel<->field assignment and via field groups.
        $ccf = $prefix . 'channels_channel_fields';
        $cfgf = $prefix . 'channel_field_groups_fields';
        $ccfg = $prefix . 'channels_channel_field_groups';

        try {
            ee()->db->query(
                "DELETE u FROM {$usages} u "
                . "JOIN {$titles} t ON t.entry_id = u.entry_id AND t.site_id = u.site_id "
                . "LEFT JOIN {$ccf} ccf ON ccf.channel_id = t.channel_id AND ccf.field_id = u.field_id "
                . "LEFT JOIN {$cfgf} cfgf ON cfgf.field_id = u.field_id "
                . "LEFT JOIN {$ccfg} ccfg ON ccfg.channel_id = t.channel_id AND ccfg.group_id = cfgf.group_id "
                . "WHERE u.site_id = ? AND u.field_id = ? AND ccf.field_id IS NULL AND ccfg.group_id IS NULL",
                [$site_id, $field_id]
            );
        } catch (\Throwable $e) {
            // Fail-safe during uninstall/partial-schema states.
        }
    }
}
