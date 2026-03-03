<?php

/**
 * JCOGS Image Pro Field - FieldSettingsService
 *============================================
 * Server-side access to fieldtype settings.
 *
 * Used by ACT endpoints for policy enforcement (allowed directories, feature
 * toggles, preset restrictions, etc).
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

namespace JCOGSDesign\JcogsImgProField\Service;

/**
 * Loads and normalises per-field settings.
 */
class FieldSettingsService
{
    /**
     * Defaults should mirror ft.jcogs_img_pro_field.php (save_settings/display_settings).
     * Keep this intentionally small: only keys needed for policy enforcement.
     */
    public function defaults(): array
    {
        return [
            'allowed_directories' => 'all',

            'enable_preset' => 'y',
            'preset_restrict' => 'n',
            'preset_allow_none' => 'y',
            'preset_allowed_ids' => [],
            'default_preset_id' => '',

            'enable_crop' => 'y',
            'require_aspect_ratio' => 'n',
            'aspect_ratio_pairs' => [],
            'default_aspect_ratio' => '',

            'enable_art_direction' => 'n',
            'art_direction_breakpoints' => [],

            'enable_focal' => 'n',

            'enable_face_detect' => 'y',
            'enable_debug' => 'n',
        ];
    }

    /**
     * Returns merged, normalised settings for this field.
     *
     * Important: this is used by Actions for server-side validation.
     */
    public function getForFieldId(int $fieldId): array
    {
        $settings = $this->defaults();

        if ($fieldId <= 0) {
            return $settings;
        }

        try {
            $field = ee('Model')->get('ChannelField')
                ->filter('field_id', $fieldId)
                ->first();

            if ($field) {
                $raw = $field->getSettingsValues();
                if (! is_array($raw)) {
                    $raw = [];
                }

                // EE stores fieldtype settings under field_settings.
                $raw = $raw['field_settings'] ?? $raw;
                if (isset($raw['jcogs_img_pro_field']) && is_array($raw['jcogs_img_pro_field'])) {
                    $raw = $raw['jcogs_img_pro_field'];
                }

                if (is_array($raw)) {
                    $settings = array_merge($settings, $raw);
                }

                return $this->normaliseSettings($settings);
            }

            // Grid column fallback (composite contexts).
            $column = ee('Model')->get('GridColumn')
                ->filter('col_id', $fieldId)
                ->first();

            if ($column) {
                $raw = $column->col_settings ?? [];
                if (is_string($raw) && $raw !== '' && $raw[0] === '{') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $raw = $decoded;
                    }
                }
                if (! is_array($raw)) {
                    $raw = [];
                }

                $raw = $raw['field_settings'] ?? $raw;
                if (isset($raw['jcogs_img_pro_field']) && is_array($raw['jcogs_img_pro_field'])) {
                    $raw = $raw['jcogs_img_pro_field'];
                }

                if (is_array($raw)) {
                    $settings = array_merge($settings, $raw);
                }
            }
        } catch (\Throwable $e) {
            // fail safe
        }

        return $this->normaliseSettings($settings);
    }

    /**
     * Returns merged, normalised settings for a Grid column (col_id).
     */
    public function getForGridColumnId(int $colId): array
    {
        $settings = $this->defaults();

        if ($colId <= 0) {
            return $settings;
        }

        try {
            $column = ee('Model')->get('GridColumn')
                ->filter('col_id', $colId)
                ->first();

            if (! $column) {
                return $settings;
            }

            $raw = $column->col_settings ?? [];
            if (is_string($raw) && $raw !== '' && $raw[0] === '{') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $raw = $decoded;
                }
            }

            if (! is_array($raw)) {
                $raw = [];
            }

            $raw = $raw['field_settings'] ?? $raw;
            if (isset($raw['jcogs_img_pro_field']) && is_array($raw['jcogs_img_pro_field'])) {
                $raw = $raw['jcogs_img_pro_field'];
            }

            if (is_array($raw)) {
                $settings = array_merge($settings, $raw);
            }
        } catch (\Throwable $e) {
            // fail safe
        }

        return $this->normaliseSettings($settings);
    }

    /**
     * Normalise common settings keys to canonical values.
     */
    private function normaliseSettings(array $settings): array
    {
        $settings['allowed_directories'] = trim((string) ($settings['allowed_directories'] ?? 'all')) ?: 'all';

        foreach (['enable_preset', 'preset_restrict', 'preset_allow_none', 'enable_crop', 'require_aspect_ratio', 'enable_art_direction', 'enable_focal', 'enable_face_detect', 'enable_debug'] as $k) {
            $settings[$k] = $this->normaliseYesNo($settings[$k] ?? 'n');
        }

        $allowedIds = $settings['preset_allowed_ids'] ?? [];
        if (! is_array($allowedIds)) {
            $allowedIds = [];
        }
        $allowedIds = array_values(array_unique(array_filter(array_map(static function ($v) {
            $v = is_scalar($v) ? trim((string) $v) : '';
            return (is_numeric($v) && (int) $v > 0) ? (string) ((int) $v) : '';
        }, $allowedIds))));
        $settings['preset_allowed_ids'] = $allowedIds;

        $settings['default_preset_id'] = (string) ((is_numeric($settings['default_preset_id'] ?? '') && (int) $settings['default_preset_id'] > 0)
            ? (int) $settings['default_preset_id']
            : 0);

        $pairs = $settings['aspect_ratio_pairs'] ?? [];
        if (! is_array($pairs)) {
            $pairs = [];
        }
        // Store as value=>label map.
        $normalisedPairs = [];
        foreach ($pairs as $k => $v) {
            $key = is_scalar($k) ? trim((string) $k) : '';
            if ($key === '' || $key === '__inherit__') {
                continue;
            }
            $normalisedPairs[$key] = is_scalar($v) ? (string) $v : '';
        }
        $settings['aspect_ratio_pairs'] = $normalisedPairs;

        $settings['default_aspect_ratio'] = trim((string) ($settings['default_aspect_ratio'] ?? ''));

        return $settings;
    }

    /**
     * Normalise truthy/falsey input to "y"/"n".
     *
     * @param mixed $value
     */
    private function normaliseYesNo($value): string
    {
        if (is_bool($value)) {
            return $value ? 'y' : 'n';
        }
        $v = strtolower(trim((string) $value));
        if (in_array($v, ['y', 'yes', '1', 'true', 'on'], true)) {
            return 'y';
        }
        return 'n';
    }
}
