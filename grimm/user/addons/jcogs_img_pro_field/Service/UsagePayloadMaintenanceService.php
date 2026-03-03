<?php

/**
 * JCOGS Image Pro Field - UsagePayloadMaintenanceService
 *======================================================
 * Helpers for reading/writing and non-enriching sanitisation/pruning of
 * persisted usage payloads.
 *
 * This service exists to keep the fieldtype class smaller and to consolidate
 * DB/payload maintenance concerns in one place.
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.0
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 * @since      0.1.8
 */

namespace JCOGSDesign\JcogsImgProField\Service;

class UsagePayloadMaintenanceService
{
    /**
     * Fetch the saved usage payload for an entry/field.
     */
    public function fetchUsagePayload(
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

        $row = ServiceCache::usage_repo()->fetchUsageRow(
            $siteId,
            $entryId,
            $fieldId,
            $contentType,
            $rowId,
            $fluidFieldDataId,
            $blockId
        );

        $decoded = json_decode((string) ($row['usage_payload'] ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Fetch the saved usage payload for a specific revision.
     */
    public function fetchUsagePayloadForVersion(
        int $versionId,
        int $siteId,
        int $entryId,
        int $fieldId,
        string $contentType = 'channel',
        ?int $rowId = null,
        ?int $fluidFieldDataId = null,
        ?int $blockId = null
    ): array
    {
        if ($versionId <= 0 || $siteId <= 0 || $entryId <= 0 || $fieldId <= 0) {
            return [];
        }

        $row = ServiceCache::usage_version_repo()->fetchUsageVersionRow(
            $versionId,
            $siteId,
            $entryId,
            $fieldId,
            $contentType,
            $rowId,
            $fluidFieldDataId,
            $blockId
        );

        $decoded = json_decode((string) ($row['usage_payload'] ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Fetch the full usage row (including id and payload).
     */
    public function fetchUsagePayloadRow(
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
            return ['id' => 0, 'payload' => []];
        }

        $row = ServiceCache::usage_repo()->fetchUsageRow(
            $siteId,
            $entryId,
            $fieldId,
            $contentType,
            $rowId,
            $fluidFieldDataId,
            $blockId
        );

        $decoded = json_decode((string) ($row['usage_payload'] ?? ''), true);

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'payload' => is_array($decoded) ? $decoded : [],
        ];
    }

    /**
     * Persist an already-pruned/sanitised usage payload back to the usage row.
     */
    public function persistPrunedUsageRow(int $usageRowId, int $fileId, array $payload): void
    {
        if ($usageRowId <= 0) {
            return;
        }

        try {
            if (empty($payload)) {
                ServiceCache::usage_repo()->deleteUsageRowById($usageRowId);
                return;
            }

            $now = (int) (ee()->localize->now ?? time());
            $member_id = null;
            if (isset(ee()->session) && isset(ee()->session->userdata['member_id'])) {
                $member_id = (int) ee()->session->userdata['member_id'];
            }

            $row = [
                'usage_payload' => json_encode($payload),
                'file_id' => $fileId,
                'modified_date' => $now,
                'modified_by_member_id' => $member_id,
            ];

            ServiceCache::usage_repo()->updateUsageRowById($usageRowId, $row);
        } catch (\Throwable $e) {
            // Fail safe: do not block rendering.
        }
    }

    /**
     * Sanitise a usage payload against current field settings.
     *
     * Non-enriching: removes invalid/disallowed keys but does not inject defaults.
     */
    public function sanitiseUsagePayloadAgainstSettings(array $payload, array $settings, array $artDirectionRows): array
    {
        if (empty($payload)) {
            return [];
        }

        $enable_preset = (($settings['enable_preset'] ?? 'n') === 'y');
        $enable_preset_choice = (($settings['enable_preset_choice'] ?? 'y') === 'y');
        $enable_crop = (($settings['enable_crop'] ?? 'n') === 'y');
        $enable_focal = (($settings['enable_focal'] ?? 'n') === 'y');

        $enable_art_direction = (($settings['enable_art_direction'] ?? 'n') === 'y') && ! empty($artDirectionRows);

        // Preset validation (non-enriching).
        if (! $enable_preset || ! $enable_preset_choice) {
            unset($payload['preset_id']);
        } else {
            $restrict = (($settings['preset_restrict'] ?? 'n') === 'y');
            if ($restrict && array_key_exists('preset_id', $payload)) {
                $allowed = $settings['preset_allowed_ids'] ?? [];
                if (! is_array($allowed)) {
                    $allowed = [];
                }

                $pid = (int) ($payload['preset_id'] ?? 0);
                if ($pid > 0 && ! in_array((string) $pid, $allowed, true)) {
                    unset($payload['preset_id']);
                }

                if ($pid <= 0 && (($settings['preset_allow_none'] ?? 'y') !== 'y')) {
                    unset($payload['preset_id']);
                }
            }
        }

        // Crop validation (non-enriching).
        if (! $enable_crop) {
            foreach (['crop', 'crop_mode', 'crop_focus_h', 'crop_focus_v', 'crop_offset_x', 'crop_offset_y', 'crop_smart_scaling', 'aspect_ratio', 'width', 'height', 'crop_rect'] as $k) {
                unset($payload[$k]);
            }
        } else {
            if (array_key_exists('aspect_ratio', $payload)) {
                $allowedRatios = $settings['aspect_ratio_pairs'] ?? [];
                if (! is_array($allowedRatios)) {
                    $allowedRatios = [];
                }

                $aspect = trim((string) ($payload['aspect_ratio'] ?? ''));
                if ($aspect === '__inherit__' || $aspect === '') {
                    // OK
                } elseif (! empty($allowedRatios) && ! array_key_exists($aspect, $allowedRatios)) {
                    unset($payload['aspect_ratio']);
                } else {
                    $payload['aspect_ratio'] = $aspect;
                }
            }

            if (array_key_exists('crop_rect', $payload)) {
                if (! isset($payload['crop_rect']) || ! is_array($payload['crop_rect'])) {
                    unset($payload['crop_rect']);
                } else {
                    $r = $payload['crop_rect'];
                    $left = isset($r['left']) ? (string) $r['left'] : '';
                    $top = isset($r['top']) ? (string) $r['top'] : '';
                    $width = isset($r['width']) ? (string) $r['width'] : '';
                    $height = isset($r['height']) ? (string) $r['height'] : '';

                    if ($left === '' || $top === '' || $width === '' || $height === ''
                        || ! is_numeric($left) || ! is_numeric($top) || ! is_numeric($width) || ! is_numeric($height)
                    ) {
                        unset($payload['crop_rect']);
                    } else {
                        $payload['crop_rect'] = [
                            'left' => $left,
                            'top' => $top,
                            'width' => $width,
                            'height' => $height,
                        ];
                    }
                }
            }
        }

        // Focal point validation (non-enriching).
        if (! $enable_focal) {
            unset($payload['focal_x'], $payload['focal_y']);
        } else {
            foreach (['focal_x', 'focal_y'] as $k) {
                if (! array_key_exists($k, $payload)) {
                    continue;
                }
                $v = trim((string) $payload[$k]);
                if ($v === '' || ! is_numeric($v)) {
                    unset($payload[$k]);
                    continue;
                }
                $n = (float) $v;
                if ($n < 0 || $n > 100) {
                    unset($payload[$k]);
                } else {
                    $payload[$k] = $n;
                }
            }
        }

        // Art direction validation (non-enriching).
        if (! $enable_art_direction) {
            unset($payload['art_direction'], $payload['art_direction_files']);
        } else {
            unset($payload['art_direction_files']);

            $allowed_media = [];
            $idx_to_media = [];
            foreach ($artDirectionRows as $r) {
                $m = isset($r['media']) ? trim((string) $r['media']) : '';
                $i = isset($r['index']) ? (int) $r['index'] : 0;
                if ($m !== '') {
                    $allowed_media[$m] = true;
                }
                if ($i > 0 && $m !== '') {
                    $idx_to_media[$i] = $m;
                }
            }

            if (! isset($payload['art_direction']) || ! is_array($payload['art_direction'])
                || ! isset($payload['art_direction']['files']) || ! is_array($payload['art_direction']['files'])
            ) {
                unset($payload['art_direction']);
            } else {
                $clean = [];
                foreach ($payload['art_direction']['files'] as $media => $fid) {
                    $media = is_scalar($media) ? trim((string) $media) : '';
                    $id = is_numeric($fid) ? (int) $fid : 0;
                    if ($media === '' || $id <= 0) {
                        continue;
                    }
                    if (is_numeric($media)) {
                        $idx = (int) $media;
                        if ($idx > 0 && isset($idx_to_media[$idx])) {
                            $media = $idx_to_media[$idx];
                        }
                    }
                    if (! empty($allowed_media) && ! isset($allowed_media[$media])) {
                        continue;
                    }
                    $clean[$media] = $id;
                }
                if (empty($clean)) {
                    unset($payload['art_direction']);
                } else {
                    $payload['art_direction']['files'] = $clean;
                }
            }
        }

        return $payload;
    }

    /**
     * Remove keys from a usage payload when features are disabled.
     */
    public function pruneUsagePayloadForDisabledFeatures(array $payload, array $settings, array $artDirectionRows): array
    {
        if (empty($payload)) {
            return [];
        }

        if ((($settings['enable_preset'] ?? 'n') !== 'y') || (($settings['enable_preset_choice'] ?? 'y') !== 'y')) {
            unset($payload['preset_id']);
        }

        if ((($settings['enable_crop'] ?? 'n') !== 'y')) {
            foreach (['crop', 'crop_mode', 'crop_focus_h', 'crop_focus_v', 'crop_offset_x', 'crop_offset_y', 'crop_smart_scaling', 'aspect_ratio', 'width', 'height', 'crop_rect'] as $k) {
                unset($payload[$k]);
            }
        }

        if ((($settings['enable_focal'] ?? 'n') !== 'y')) {
            unset($payload['focal_x'], $payload['focal_y']);
        }

        $enable_art_direction = ((($settings['enable_art_direction'] ?? 'n') === 'y') && ! empty($artDirectionRows));
        if (! $enable_art_direction) {
            unset($payload['art_direction'], $payload['art_direction_files']);
        }

        return $payload;
    }
}
