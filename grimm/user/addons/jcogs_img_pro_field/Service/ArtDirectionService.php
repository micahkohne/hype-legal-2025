<?php

/**
 * JCOGS Image Pro Field - ArtDirectionService
 *===========================================
 * Encapsulates art-direction breakpoint normalisation and preset inheritance
 * logic.
 *
 * Extracted from the fieldtype class to reduce size and improve testability.
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.2
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 * @since      0.1.8
 */

namespace JCOGSDesign\JcogsImgProField\Service;

final class ArtDirectionService
{
    /**
     * Normalise posted/stored art-direction breakpoint rows.
     *
     * Preset selection modes:
     * - 0  (or blank): inherit main/default preset
     * - -1: explicit "no preset" for this breakpoint
     * - >0: explicit preset id
     */
    public function normaliseBreakpointsFromPosted($rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        if (isset($rows['rows']) && is_array($rows['rows'])) {
            $rows = $rows['rows'];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $raw_breakpoint = isset($row['breakpoint']) && is_scalar($row['breakpoint']) ? trim((string) $row['breakpoint']) : '';
            $raw_media = isset($row['media']) && is_scalar($row['media']) ? trim((string) $row['media']) : '';

            $raw = ($raw_breakpoint !== '') ? $raw_breakpoint : $raw_media;
            if ($raw === '') {
                continue;
            }

            $media = '';
            if ($raw_breakpoint !== '' && is_numeric($raw_breakpoint)) {
                $n = (int) $raw_breakpoint;
                if ($n <= 0) {
                    continue;
                }
                $media = '(min-width: ' . $n . 'px)';
            } else {
                $raw = preg_replace('/\s+/', ' ', $raw);
                $raw = trim((string) $raw);

                if ($raw === '' || strlen($raw) > 200) {
                    continue;
                }
                if (preg_match('/["\'\<\>\{\};]/', $raw)) {
                    continue;
                }
                $media = $raw;
            }

            $preset_id_raw = isset($row['preset_id']) ? trim((string) $row['preset_id']) : '';
            if (is_numeric($preset_id_raw)) {
                $preset_id_int = (int) $preset_id_raw;
                if ($preset_id_int === -1) {
                    $preset_id = -1;
                } elseif ($preset_id_int > 0) {
                    $preset_id = $preset_id_int;
                } else {
                    $preset_id = 0;
                }
            } else {
                $preset_id = 0;
            }

            $out[] = [
                'media' => $media,
                'preset_id' => $preset_id,
            ];

            if (count($out) >= 3) {
                break;
            }
        }

        return $out;
    }

    /**
     * Convert a media query to a canonical display value.
     */
    public function mediaToDisplayValue(string $media): string
    {
        return trim($media);
    }

    /**
     * Render the art-direction breakpoints mini-grid used in field settings.
     *
     * @return mixed MiniGrid instance
     */
    public function buildBreakpointsMiniGrid(array $data, array $presetOptions): mixed
    {
        $grid = ee('CP/MiniGridInput', [
            'field_name' => 'art_direction_breakpoints',
        ]);
        $grid->loadAssets();
        $grid->setColumns([
            lang('jcogs_img_pro_field_minigrid_art_direction_col_breakpoint'),
            lang('jcogs_img_pro_field_minigrid_art_direction_col_preset'),
        ]);
        $grid->setNoResultsText(lang('jcogs_img_pro_field_minigrid_art_direction_no_results'), lang('jcogs_img_pro_field_minigrid_add_new'));

        unset($presetOptions['']);
        $presetOptions = [
            '' => lang('jcogs_img_pro_field_minigrid_art_direction_preset_inherit'),
            '-1' => lang('jcogs_img_pro_field_minigrid_art_direction_preset_none'),
        ] + $presetOptions;

        $grid->setBlankRow([
            ['html' => form_input('breakpoint', '')],
            ['html' => form_dropdown('preset_id', $presetOptions, '')],
        ]);

        $rows = [];
        $i = 1;
        $stored = $data['art_direction_breakpoints'] ?? [];
        $stored = $this->normaliseBreakpointsFromPosted($stored);
        foreach ($stored as $row) {
            $media = isset($row['media']) ? (string) $row['media'] : '';
            if ($media === '') {
                continue;
            }

            $preset_id = isset($row['preset_id']) ? (int) $row['preset_id'] : 0;
            $preset_selected = ($preset_id === -1) ? '-1' : (($preset_id > 0) ? (string) $preset_id : '');

            $rows[] = [
                'attrs' => ['row_id' => $i],
                'columns' => [
                    ['html' => form_input('breakpoint', $this->mediaToDisplayValue($media))],
                    ['html' => form_dropdown('preset_id', $presetOptions, $preset_selected)],
                ],
            ];
            $i++;
        }

        $grid->setData($rows);
        return $grid;
    }

    /**
     * Get art-direction breakpoints configured for this field.
     */
    public function getBreakpointsFromFieldSettings(array $settings): array
    {
        $rows = $settings['art_direction_breakpoints'] ?? null;
        if (! is_array($rows) || empty($rows)) {
            return [];
        }

        $rows = $this->normaliseBreakpointsFromPosted($rows);
        if (empty($rows)) {
            return [];
        }

        $out = [];
        $idx = 1;
        foreach ($rows as $r) {
            $media = isset($r['media']) ? trim((string) $r['media']) : '';
            if ($media === '') {
                continue;
            }

            $out[] = [
                'index' => $idx,
                'media' => $media,
                'preset_id' => (int) ($r['preset_id'] ?? 0),
            ];
            $idx++;
            if ($idx > 3) {
                break;
            }
        }

        return $out;
    }

    /**
     * Determine the legacy default art-direction preset id (if configured).
     */
    public function getLegacyDefaultPresetId(array $settings): int
    {
        $rows = $settings['art_direction_breakpoints'] ?? null;
        if (! is_array($rows) || empty($rows)) {
            return 0;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['is_default'] ?? 'n') !== 'y') {
                continue;
            }
            $preset_id = isset($row['preset_id']) ? (int) $row['preset_id'] : 0;
            return ($preset_id > 0) ? $preset_id : 0;
        }

        return 0;
    }

    /**
     * Describe an art-direction media query for editor display.
     */
    public function describeMediaForEditor(string $media): array
    {
        $media = trim($media);
        if ($media === '') {
            return [
                'title' => lang('jcogs_img_pro_field_editor_ad_alt_title_generic'),
                'media' => '',
            ];
        }

        if (preg_match('/\(\s*max-width\s*:\s*(\d+)px\s*\)/i', $media, $m)) {
            return [
                'title' => sprintf(lang('jcogs_img_pro_field_editor_ad_alt_title_max_width'), (string) ((int) $m[1])),
                'media' => $media,
            ];
        }

        if (preg_match('/\(\s*min-width\s*:\s*(\d+)px\s*\)/i', $media, $m)) {
            return [
                'title' => sprintf(lang('jcogs_img_pro_field_editor_ad_alt_title_min_width'), (string) ((int) $m[1])),
                'media' => $media,
            ];
        }

        return [
            'title' => sprintf(lang('jcogs_img_pro_field_editor_ad_alt_title_media'), $media),
            'media' => $media,
        ];
    }

    /**
     * Apply the main/default preset to the payload (if configured).
     */
    public function applyDefaultPresetToPayload(array $settings, array $usagePayload, array $tagParams): array
    {
        $enable_preset = (($settings['enable_preset'] ?? 'y') === 'y');
        if (! $enable_preset) {
            unset($usagePayload['preset_id']);
            return $usagePayload;
        }

        $template_requests_preset = isset($tagParams['preset']) && trim((string) $tagParams['preset']) !== '';
        if ($template_requests_preset) {
            return $usagePayload;
        }

        if (array_key_exists('preset_id', $usagePayload)) {
            return $usagePayload;
        }

        $default_preset_id = trim((string) ($settings['default_preset_id'] ?? ''));
        $default_preset_id_int = (is_numeric($default_preset_id) && (int) $default_preset_id > 0) ? (int) $default_preset_id : 0;

        if ($default_preset_id_int <= 0) {
            $default_preset_id_int = $this->getLegacyDefaultPresetId($settings);
        }

        if ($default_preset_id_int > 0) {
            $usagePayload['preset_id'] = $default_preset_id_int;
        }

        return $usagePayload;
    }

    /**
     * Build per-row payload for an art-direction source.
     */
    public function buildRowPayload(array $settings, array $mainUsagePayload, int $presetId, array $tagParams): array
    {
        $payload = [];

        $enable_preset = (($settings['enable_preset'] ?? 'y') === 'y');
        if (! $enable_preset) {
            return $payload;
        }

        if ($presetId < 0) {
            return $payload;
        }

        if ($presetId > 0) {
            $payload['preset_id'] = $presetId;
            return $payload;
        }

        $main_effective = $this->applyDefaultPresetToPayload($settings, $mainUsagePayload, $tagParams);
        $inherit_id = isset($main_effective['preset_id']) ? (int) $main_effective['preset_id'] : 0;
        if ($inherit_id > 0) {
            $payload['preset_id'] = $inherit_id;
        }

        return $payload;
    }
}
