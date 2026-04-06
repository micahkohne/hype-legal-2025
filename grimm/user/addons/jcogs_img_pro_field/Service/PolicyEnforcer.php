<?php

/**
 * JCOGS Image Pro Field - PolicyEnforcer
 *======================================
 * Server-side policy enforcement for stored editorial intent.
 *
 * Ensures persisted usage payload cannot request disallowed presets, crop tools,
 * art-direction structures, or files outside configured upload destinations.
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

namespace JCOGSDesign\JcogsImgProField\Service;

/**
 * Sanitises and validates usage payloads against field settings.
 */
class PolicyEnforcer
{
    /**
     * Validates file selection against field policy.
     *
     * Returns null when allowed, otherwise an error code.
     */
    public function validateFileIdAgainstSettings(int $fileId, array $settings): ?string
    {
        if ($fileId <= 0) {
            return 'missing_file';
        }

        $allowed = trim((string) ($settings['allowed_directories'] ?? 'all'));
        if ($allowed === '' || $allowed === 'all') {
            return null;
        }

        if (! is_numeric($allowed) || (int) $allowed <= 0) {
            return null;
        }

        $allowedDirId = (int) $allowed;

        try {
            $file = ee('Model')->get('File', $fileId)->first();
            if (! $file) {
                return 'file_not_found';
            }

            $dirId = (int) ($file->upload_location_id ?? $file->upload_destination_id ?? $file->upload_location ?? 0);
            if ($dirId > 0 && $dirId !== $allowedDirId) {
                return 'file_not_allowed';
            }
        } catch (\Throwable $e) {
            // If we cannot validate (unexpected EE model shape), fail closed.
            return 'file_not_allowed';
        }

        return null;
    }

    /**
     * Sanitises a usage payload so it cannot express intent outside field policy.
     */
    public function sanitiseUsagePayload(array $payload, array $settings): array
    {
        // Presets
        if (($settings['enable_preset'] ?? 'n') !== 'y') {
            unset($payload['preset_id']);
        } elseif (($settings['enable_preset_choice'] ?? 'y') !== 'y') {
            // Presets may still be applied at render-time via defaults, but editors cannot persist preset intent.
            unset($payload['preset_id']);
        } else {
            $restrict = (($settings['preset_restrict'] ?? 'n') === 'y');
            if ($restrict) {
                $allowed = $settings['preset_allowed_ids'] ?? [];
                if (! is_array($allowed)) {
                    $allowed = [];
                }

                $presetId = isset($payload['preset_id']) ? (int) $payload['preset_id'] : 0;
                if ($presetId > 0 && ! in_array((string) $presetId, $allowed, true)) {
                    unset($payload['preset_id']);
                    $presetId = 0;
                }

                if ($presetId <= 0 && (($settings['preset_allow_none'] ?? 'y') !== 'y')) {
                    $fallback = (int) ($settings['default_preset_id'] ?? 0);
                    if ($fallback > 0 && in_array((string) $fallback, $allowed, true)) {
                        $payload['preset_id'] = $fallback;
                    } elseif (count($allowed) === 1) {
                        $payload['preset_id'] = (int) $allowed[0];
                    }
                }
            }
        }

        // Crop
        if (($settings['enable_crop'] ?? 'n') !== 'y') {
            foreach (['crop', 'crop_mode', 'crop_focus_h', 'crop_focus_v', 'crop_offset_x', 'crop_offset_y', 'crop_smart_scaling', 'aspect_ratio', 'width', 'height', 'crop_rect'] as $k) {
                unset($payload[$k]);
            }
        } else {
            // Aspect ratio allowlist
            $allowedRatios = $settings['aspect_ratio_pairs'] ?? [];
            if (! is_array($allowedRatios)) {
                $allowedRatios = [];
            }

            $aspect = isset($payload['aspect_ratio']) ? trim((string) $payload['aspect_ratio']) : '';
            if ($aspect === '__inherit__') {
                // OK (meaning: let defaults apply)
            } elseif ($aspect !== '' && ! empty($allowedRatios) && ! array_key_exists($aspect, $allowedRatios)) {
                $aspect = '';
            }

            if ($aspect === '' && count($allowedRatios) > 1) {
                $def = trim((string) ($settings['default_aspect_ratio'] ?? ''));
                if ($def !== '' && array_key_exists($def, $allowedRatios)) {
                    $payload['aspect_ratio'] = $def;
                } else {
                    unset($payload['aspect_ratio']);
                }
            } elseif ($aspect === '') {
                unset($payload['aspect_ratio']);
            } else {
                $payload['aspect_ratio'] = $aspect;
            }
        }

        // Focal point
        if (($settings['enable_focal'] ?? 'n') !== 'y') {
            unset($payload['focal_x'], $payload['focal_y']);
        }

        // Art direction
        if (($settings['enable_art_direction'] ?? 'n') !== 'y') {
            unset($payload['art_direction'], $payload['art_direction_files']);
        } else {
            // Normalise/clean persisted shape.
            if (isset($payload['art_direction_files']) && ! isset($payload['art_direction'])) {
                // Back-compat: convert legacy key to nested structure.
                if (is_array($payload['art_direction_files'])) {
                    $payload['art_direction'] = ['files' => $payload['art_direction_files']];
                }
                unset($payload['art_direction_files']);
            }

            // Ensure structure is sensible.
            if (isset($payload['art_direction']) && is_array($payload['art_direction'])) {
                if (! isset($payload['art_direction']['files']) || ! is_array($payload['art_direction']['files'])) {
                    unset($payload['art_direction']);
                } else {
                    // Keep only non-empty media => positive int file_id.
                    $clean = [];
                    foreach ($payload['art_direction']['files'] as $media => $fileId) {
                        $media = is_scalar($media) ? trim((string) $media) : '';
                        $fid = is_numeric($fileId) ? (int) $fileId : 0;
                        if ($media !== '' && $fid > 0) {
                            $clean[$media] = $fid;
                        }
                    }
                    if (empty($clean)) {
                        unset($payload['art_direction']);
                    } else {
                        $payload['art_direction']['files'] = $clean;
                    }
                }
            }
        }

        // Face detection settings are not stored in usage payload; nothing to sanitise here.

        return $payload;
    }
}
