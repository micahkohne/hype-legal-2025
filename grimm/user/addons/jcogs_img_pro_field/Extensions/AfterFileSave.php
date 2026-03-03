<?php

/**
 * JCOGS Image Pro Field - AfterFileSave
 *=====================================
 * ExpressionEngine extension hook handler.
 *
 * Maintains the file augment cache row used by the field publish UI
 * (fingerprints, cached face detection result, future EXIF snapshot).
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
 * After-file-save hook implementation.
 */
class AfterFileSave extends AbstractRoute
{
    /**
     * Upsert/refresh the augment row for a saved file.
     */
    public function process($file_model, $values): void
    {
        $site_id = (int) (ee()->config->item('site_id') ?: 1);
        $file_id = $this->extractInt($values, 'file_id')
            ?? (is_object($file_model) && isset($file_model->file_id) ? (int) $file_model->file_id : null);

        if (empty($file_id)) {
            return;
        }

        if (! ee()->db->table_exists('jcogs_img_pro_field_file_augments')) {
            return;
        }

        $fingerprint = $this->buildFingerprint($values, $file_model);
        $now = (int) (ee()->localize->now ?? time());

        try {
            $existing = ee()->db
                ->select('id, source_fingerprint')
                ->from('jcogs_img_pro_field_file_augments')
                ->where('site_id', $site_id)
                ->where('file_id', $file_id)
                ->limit(1)
                ->get()
                ->row_array();
        } catch (\Throwable $e) {
            return;
        }

        if ($existing) {
            $update = [
                'modified_date' => $now,
                'source_fingerprint' => $fingerprint,
            ];

            if (($existing['source_fingerprint'] ?? '') !== $fingerprint) {
                $update['exif_snapshot'] = null;
                $update['face_detection_result'] = null;
            }

            try {
                ee()->db
                    ->where('id', (int) $existing['id'])
                    ->update('jcogs_img_pro_field_file_augments', $update);
            } catch (\Throwable $e) {
                // Fail-safe during uninstall/partial-schema states.
            }

            return;
        }

        try {
            ee()->db->insert('jcogs_img_pro_field_file_augments', [
                'site_id' => $site_id,
                'file_id' => $file_id,
                'default_preset_id' => null,
                'source_fingerprint' => $fingerprint,
                'exif_snapshot' => null,
                'face_detection_result' => null,
                'created_date' => $now,
                'modified_date' => $now,
            ]);
        } catch (\Throwable $e) {
            // Fail-safe during uninstall/partial-schema states.
        }
    }

    /**
     * Extract an integer from a values array when present.
     *
     * @param array<string, mixed>|mixed $values
     */
    private function extractInt($values, string $key): ?int
    {
        if (is_array($values) && isset($values[$key]) && $values[$key] !== '') {
            return (int) $values[$key];
        }

        return null;
    }

    /**
     * Extract a string from a values array when present.
     *
     * @param array<string, mixed>|mixed $values
     */
    private function extractString($values, string $key): string
    {
        if (is_array($values) && isset($values[$key]) && $values[$key] !== null) {
            return (string) $values[$key];
        }

        return '';
    }

    /**
     * Build a fingerprint based on file metadata to detect changes.
     *
     * @param array<string, mixed>|mixed $values
     * @param object $file_model
     */
    private function buildFingerprint($values, $file_model): string
    {
        $modified_date = $this->extractInt($values, 'modified_date')
            ?? (is_object($file_model) && isset($file_model->modified_date) ? (int) $file_model->modified_date : 0);
        $file_size = $this->extractInt($values, 'file_size')
            ?? (is_object($file_model) && isset($file_model->file_size) ? (int) $file_model->file_size : 0);
        $file_name = $this->extractString($values, 'file_name');
        if ($file_name === '' && is_object($file_model) && isset($file_model->file_name)) {
            $file_name = (string) $file_model->file_name;
        }

        $upload_location_id = $this->extractInt($values, 'upload_location_id')
            ?? (is_object($file_model) && isset($file_model->upload_location_id) ? (int) $file_model->upload_location_id : 0);

        return implode(':', [
            $upload_location_id,
            $file_name,
            $modified_date,
            $file_size,
        ]);
    }
}
