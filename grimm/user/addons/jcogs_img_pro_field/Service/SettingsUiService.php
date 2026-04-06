<?php

/**
 * JCOGS Image Pro Field - SettingsUiService
 *========================================
 * Builds and validates the field settings UI for the Control Panel.
 *
 * Extracted from the fieldtype class to reduce size and keep the fieldtype
 * focused on EE entry points.
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

final class SettingsUiService
{
    /**
     * Build the array of settings rows used by EE when configuring the field.
     */
    public function buildSettingsSections(array $data, int $siteId): array
    {
        $defaults = [
            'allowed_directories' => 'all',
            'enable_preset' => 'y',
            'enable_preset_choice' => 'y',
            'preset_restrict' => 'n',
            'preset_allow_none' => 'y',
            'preset_allowed_ids' => [],
            'default_preset_id' => '',
            'enable_crop' => 'y',
            'require_crop' => 'n',
            'require_aspect_ratio' => 'n',
            'aspect_ratio_choices' => '',
            'aspect_ratio_pairs' => [],
            'default_aspect_ratio' => '',
            // Responsive image defaults (template-level convenience).
            'enable_responsive_defaults' => 'y',
            'srcset_widths' => [],
            'default_allow_scale_larger' => 'n',
            // Art direction (picture/source).
            'enable_art_direction' => 'n',
            'art_direction_breakpoints' => [],
            'enable_focal' => 'n',
            // Face detection settings (publish UI).
            'enable_face_detect' => 'y',
            'face_detect_controls' => 'advanced',
            'face_detect_default_quality' => 'balanced',
            'face_detect_default_sensitivity' => 3,
            'face_detect_default_margin' => 0,
            'enable_debug' => 'n',
        ];

        $data = array_merge($defaults, $data);

        // Allowed upload destination (EE core drag/drop picker supports 'all' OR a single directory ID).
        $uploadChoices = ['all' => lang('jcogs_img_pro_field_all_upload_destinations')];
        try {
            $destinations = ServiceCache::upload_destination_repo()->listBySite($siteId);
            foreach ($destinations as $dest) {
                $id = (int) ($dest['id'] ?? 0);
                $name = trim((string) ($dest['name'] ?? ''));
                if ($id > 0 && $name !== '') {
                    $uploadChoices[(string) $id] = $name . ' (#' . $id . ')';
                }
            }
        } catch (\Throwable $e) {
            // Leave the default choice only.
        }

        $allowedDirectories = (string) ($data['allowed_directories'] ?? 'all');
        $allowedDirectories = trim($allowedDirectories);
        if ($allowedDirectories !== 'all' && ! array_key_exists($allowedDirectories, $uploadChoices)) {
            $allowedDirectories = 'all';
        }

        // --- Presets ---
        $presets = ServiceCache::preset_options()->fetchImgProPresets($siteId);

        $presetAllowedIds = $data['preset_allowed_ids'] ?? [];
        if (! is_array($presetAllowedIds)) {
            $presetAllowedIds = [];
        }
        $presetAllowedIds = array_values(array_unique(array_filter(array_map(static function ($v) {
            $v = is_scalar($v) ? (string) $v : '';
            $v = trim($v);
            return (is_numeric($v) && (int) $v > 0) ? (string) ((int) $v) : '';
        }, $presetAllowedIds))));

        $presetRowsHtml = '';
        if (empty($presets)) {
            $presetRowsHtml .= '<div class="field-no-results"><p>' . lang('jcogs_img_pro_field_preset_allowlist_no_presets') . '</p></div>';
        } else {
            $presetRowsHtml .= '<table class="table table--striped">';
            $presetRowsHtml .= '<thead><tr>';
            $presetRowsHtml .= '<th style="width:40px;">' . lang('jcogs_img_pro_field_preset_allowlist_col_use') . '</th>';
            $presetRowsHtml .= '<th>' . lang('jcogs_img_pro_field_preset_allowlist_col_preset') . '</th>';
            $presetRowsHtml .= '</tr></thead><tbody>';

            foreach ($presets as $preset) {
                $id = isset($preset['id']) ? (int) $preset['id'] : 0;
                $name = isset($preset['name']) ? (string) $preset['name'] : '';
                if ($id <= 0 || $name === '') {
                    continue;
                }

                $idStr = (string) $id;
                $checked = in_array($idStr, $presetAllowedIds, true) ? ' checked' : '';

                $presetRowsHtml .= '<tr>';
                $presetRowsHtml .= '<td><input type="checkbox" data-jcogs-preset-checkbox="1" name="preset_allowed_ids[]" value="' . htmlspecialchars($idStr, ENT_QUOTES, 'UTF-8') . '"' . $checked . '></td>';
                $presetRowsHtml .= '<td>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' <span style="opacity:.7;">(#' . (int) $id . ')</span></td>';
                $presetRowsHtml .= '</tr>';
            }

            $presetRowsHtml .= '</tbody></table>';
        }

        $presetRestrict = (($data['preset_restrict'] ?? 'n') === 'y');
        $enablePresetChoice = (($data['enable_preset_choice'] ?? 'y') === 'y');
        $allowNone = (($data['preset_allow_none'] ?? 'y') === 'y');

        $defaultPresetIdRaw = trim((string) ($data['default_preset_id'] ?? ''));
        $defaultPresetIdRaw = (is_numeric($defaultPresetIdRaw) && (int) $defaultPresetIdRaw > 0) ? (string) ((int) $defaultPresetIdRaw) : '';

        $defaultChoices = [];
        // The “Allow None” toggle is an editor-facing choice. If editors cannot choose a preset,
        // still allow developers to force “no preset” via the Default preset setting.
        $allowNoneForDefaultSelect = $allowNone || ! $enablePresetChoice;
        if ($allowNoneForDefaultSelect) {
            $defaultChoices[''] = lang('jcogs_img_pro_field_none_option');
        }

        foreach ($presets as $preset) {
            $id = isset($preset['id']) ? (int) $preset['id'] : 0;
            $name = isset($preset['name']) ? (string) $preset['name'] : '';
            if ($id <= 0 || $name === '') {
                continue;
            }

            $idStr = (string) $id;
            if ($presetRestrict && ! in_array($idStr, $presetAllowedIds, true)) {
                continue;
            }

            $defaultChoices[$idStr] = $name . ' (#' . $id . ')';
        }

        // --- Aspect ratio mini-grid (value/label pairs) ---
        $aspectGrid = ServiceCache::aspect_ratio()->buildMiniGrid($data);
        $aspectGridHtml = ee('View')->make('ee:_shared/form/mini_grid')->render($aspectGrid->viewData());

        $aspectDefaultOptions = ServiceCache::aspect_ratio()->normalisePairsFromPosted($data['aspect_ratio_pairs'] ?? []);
        if (empty($aspectDefaultOptions) && ! empty($data['aspect_ratio_choices'])) {
            $aspectDefaultOptions = ServiceCache::aspect_ratio()->parseChoices((string) $data['aspect_ratio_choices']);
        }

        $aspectDefaultSelected = ServiceCache::aspect_ratio()->normalizeSetting((string) ($data['default_aspect_ratio'] ?? ''));
        if (count($aspectDefaultOptions) <= 1) {
            $aspectDefaultSelected = '';
        } elseif ($aspectDefaultSelected === '' || ! array_key_exists($aspectDefaultSelected, $aspectDefaultOptions)) {
            foreach (array_keys($aspectDefaultOptions) as $k) {
                $aspectDefaultSelected = (string) $k;
                break;
            }
        }

        // --- Srcset widths mini-grid ---
        $srcsetGrid = ServiceCache::responsive_defaults()->buildSrcsetWidthsMiniGrid($data);
        $srcsetGridHtml = ee('View')->make('ee:_shared/form/mini_grid')->render($srcsetGrid->viewData());

        // --- Art direction breakpoints mini-grid ---
        $presetOptions = ServiceCache::preset_options()->getPresetOptions($siteId, '');
        $adGrid = ServiceCache::art_direction()->buildBreakpointsMiniGrid($data, $presetOptions);
        $adGridHtml = ee('View')->make('ee:_shared/form/mini_grid')->render($adGrid->viewData());

        return [
            [
                'title' => lang('jcogs_img_pro_field_setting_allowed_directories_title'),
                'desc' => lang('jcogs_img_pro_field_setting_allowed_directories_desc'),
                'fields' => [
                    'allowed_directories' => [
                        'type' => 'select',
                        'choices' => $uploadChoices,
                        'value' => $allowedDirectories,
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_enable_preset_title'),
                'desc' => lang('jcogs_img_pro_field_setting_enable_preset_desc'),
                'fields' => [
                    'enable_preset' => [
                        'type' => 'yes_no',
                        'group_toggle' => [
                            'y' => 'jcogs_img_pro_field_preset',
                        ],
                        'value' => ($data['enable_preset'] === 'n') ? 'n' : 'y',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_enable_preset_choice_title'),
                'desc' => lang('jcogs_img_pro_field_setting_enable_preset_choice_desc'),
                'group' => 'jcogs_img_pro_field_preset',
                'fields' => [
                    'enable_preset_choice' => [
                        'type' => 'yes_no',
                        'value' => (($data['enable_preset_choice'] ?? 'y') === 'n') ? 'n' : 'y',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_preset_allow_none_title'),
                'desc' => lang('jcogs_img_pro_field_setting_preset_allow_none_desc'),
                'group' => 'jcogs_img_pro_field_preset',
                'fields' => [
                    'preset_allow_none' => [
                        'type' => 'yes_no',
                        'value' => ($data['preset_allow_none'] === 'y') ? 'y' : 'n',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_default_preset_title'),
                'desc' => lang('jcogs_img_pro_field_setting_default_preset_desc'),
                'group' => 'jcogs_img_pro_field_preset',
                'fields' => [
                    'default_preset_id' => [
                        'type' => 'select',
                        'choices' => $defaultChoices,
                        'value' => $defaultPresetIdRaw,
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_preset_restrict_title'),
                'desc' => lang('jcogs_img_pro_field_setting_preset_restrict_desc'),
                'group' => 'jcogs_img_pro_field_preset',
                'fields' => [
                    'preset_restrict' => [
                        'type' => 'yes_no',
                        'group_toggle' => [
                            'y' => 'jcogs_img_pro_field_preset_restrict',
                        ],
                        'value' => ($data['preset_restrict'] === 'y') ? 'y' : 'n',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_preset_allowlist_title'),
                'desc' => lang('jcogs_img_pro_field_setting_preset_allowlist_desc'),
                'group' => 'jcogs_img_pro_field_preset_restrict',
                'fields' => [
                    'jcogs_img_pro_field_preset_allowlist' => [
                        'type' => 'html',
                        'content' => '<div class="jcogs-img-pro-field-preset-settings jcogs-img-pro-field-settings-box">'
                            . $presetRowsHtml
                            . '</div>',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_enable_crop_title'),
                'desc' => lang('jcogs_img_pro_field_setting_enable_crop_desc'),
                'fields' => [
                    'enable_crop' => [
                        'type' => 'yes_no',
                        'group_toggle' => [
                            'y' => 'jcogs_img_pro_field_crop',
                        ],
                        'value' => ($data['enable_crop'] === 'n') ? 'n' : 'y',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_require_crop_title'),
                'desc' => lang('jcogs_img_pro_field_setting_require_crop_desc'),
                'group' => 'jcogs_img_pro_field_crop',
                'fields' => [
                    'require_crop' => [
                        'type' => 'yes_no',
                        'value' => (($data['require_crop'] ?? 'n') === 'y') ? 'y' : 'n',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_require_aspect_ratio_title'),
                'desc' => lang('jcogs_img_pro_field_setting_require_aspect_ratio_desc'),
                'group' => 'jcogs_img_pro_field_crop',
                'fields' => [
                    'require_aspect_ratio' => [
                        'type' => 'yes_no',
                        'value' => (($data['require_aspect_ratio'] ?? 'n') === 'y') ? 'y' : 'n',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_aspect_ratio_pairs_title'),
                'desc' => lang('jcogs_img_pro_field_setting_aspect_ratio_pairs_desc'),
                'group' => 'jcogs_img_pro_field_crop',
                'fields' => [
                    'aspect_ratio_pairs' => [
                        'type' => 'html',
                        'content' => '<div class="jcogs-img-pro-field-aspect-settings jcogs-img-pro-field-settings-box">'
                            . $aspectGridHtml
                            . '<div style="clear: both;"></div>'
                            . '<div class="jcogs-img-pro-field-aspect-default" style="margin-top: 10px; position: relative; z-index: 2;">'
                            . '<div class="field-instruct"><label>' . lang('jcogs_img_pro_field_setting_default_aspect_ratio_label') . '</label></div>'
                            . '<select class="select" name="default_aspect_ratio" style="min-width: 220px;">'
                            . ServiceCache::preset_options()->renderSelectOptions($aspectDefaultOptions, $aspectDefaultSelected)
                            . '</select>'
                            . '<div style="margin-top:4px; opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_setting_default_aspect_ratio_help') . '</div>'
                            . '</div>'
                            . '</div>',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_enable_focal_title'),
                'desc' => lang('jcogs_img_pro_field_setting_enable_focal_desc'),
                'group' => 'jcogs_img_pro_field_crop',
                'fields' => [
                    'enable_focal' => [
                        'type' => 'yes_no',
                        'value' => ($data['enable_focal'] === 'y') ? 'y' : 'n',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_enable_face_detect_title'),
                'desc' => lang('jcogs_img_pro_field_setting_enable_face_detect_desc'),
                'group' => 'jcogs_img_pro_field_crop',
                'fields' => [
                    'enable_face_detect' => [
                        'type' => 'yes_no',
                        'group_toggle' => [
                            'y' => 'jcogs_img_pro_field_face_detect',
                        ],
                        'value' => (($data['enable_face_detect'] ?? 'y') === 'n') ? 'n' : 'y',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_face_detect_controls_title'),
                'desc' => lang('jcogs_img_pro_field_setting_face_detect_controls_desc'),
                'group' => 'jcogs_img_pro_field_crop|jcogs_img_pro_field_face_detect',
                'fields' => [
                    'face_detect_controls' => [
                        'type' => 'select',
                        'choices' => [
                            'hidden' => lang('jcogs_img_pro_field_setting_face_detect_controls_hidden'),
                            'advanced' => lang('jcogs_img_pro_field_setting_face_detect_controls_advanced'),
                            'visible' => lang('jcogs_img_pro_field_setting_face_detect_controls_visible'),
                        ],
                        'value' => in_array((string) ($data['face_detect_controls'] ?? 'advanced'), ['hidden', 'advanced', 'visible'], true)
                            ? (string) $data['face_detect_controls']
                            : 'advanced',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_face_detect_default_quality_title'),
                'desc' => lang('jcogs_img_pro_field_setting_face_detect_default_quality_desc'),
                'group' => 'jcogs_img_pro_field_crop|jcogs_img_pro_field_face_detect',
                'fields' => [
                    'face_detect_default_quality' => [
                        'type' => 'select',
                        'choices' => [
                            'fast' => 'fast',
                            'balanced' => 'balanced',
                            'accurate' => 'accurate',
                        ],
                        'value' => in_array((string) ($data['face_detect_default_quality'] ?? 'balanced'), ['fast', 'balanced', 'accurate'], true)
                            ? (string) $data['face_detect_default_quality']
                            : 'balanced',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_face_detect_default_sensitivity_title'),
                'desc' => lang('jcogs_img_pro_field_setting_face_detect_default_sensitivity_desc'),
                'group' => 'jcogs_img_pro_field_crop|jcogs_img_pro_field_face_detect',
                'fields' => [
                    'face_detect_default_sensitivity' => [
                        'type' => 'text',
                        'value' => (string) (is_numeric($data['face_detect_default_sensitivity'] ?? null)
                            ? max(1, min(9, (int) $data['face_detect_default_sensitivity']))
                            : 3),
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_face_detect_default_margin_title'),
                'desc' => lang('jcogs_img_pro_field_setting_face_detect_default_margin_desc'),
                'group' => 'jcogs_img_pro_field_crop|jcogs_img_pro_field_face_detect',
                'fields' => [
                    'face_detect_default_margin' => [
                        'type' => 'text',
                        'value' => (string) (is_numeric($data['face_detect_default_margin'] ?? null)
                            ? max(0, min(500, (int) $data['face_detect_default_margin']))
                            : 0),
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_enable_responsive_defaults_title'),
                'desc' => lang('jcogs_img_pro_field_setting_enable_responsive_defaults_desc'),
                'fields' => [
                    'enable_responsive_defaults' => [
                        'type' => 'yes_no',
                        'group_toggle' => [
                            'y' => 'jcogs_img_pro_field_responsive',
                        ],
                        'value' => (($data['enable_responsive_defaults'] ?? 'y') === 'n') ? 'n' : 'y',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_srcset_widths_title'),
                'desc' => lang('jcogs_img_pro_field_setting_srcset_widths_desc'),
                'group' => 'jcogs_img_pro_field_responsive',
                'fields' => [
                    'srcset_widths' => [
                        'type' => 'html',
                        'content' => '<div class="jcogs-img-pro-field-srcset-settings jcogs-img-pro-field-settings-box" style="max-width: 420px;">'
                            . $srcsetGridHtml
                            . '<div style="margin-top:4px; opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_setting_srcset_widths_example') . '</div>'
                            . '</div>',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_default_allow_scale_larger_title'),
                'desc' => lang('jcogs_img_pro_field_setting_default_allow_scale_larger_desc'),
                'group' => 'jcogs_img_pro_field_responsive',
                'fields' => [
                    'default_allow_scale_larger' => [
                        'type' => 'yes_no',
                        'value' => (($data['default_allow_scale_larger'] ?? 'n') === 'y') ? 'y' : 'n',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_enable_art_direction_title'),
                'desc' => lang('jcogs_img_pro_field_setting_enable_art_direction_desc'),
                'fields' => [
                    'enable_art_direction' => [
                        'type' => 'yes_no',
                        'group_toggle' => [
                            'y' => 'jcogs_img_pro_field_art_direction',
                        ],
                        'value' => (($data['enable_art_direction'] ?? 'n') === 'y') ? 'y' : 'n',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_art_direction_breakpoints_title'),
                'desc' => lang('jcogs_img_pro_field_setting_art_direction_breakpoints_desc'),
                'group' => 'jcogs_img_pro_field_art_direction',
                'fields' => [
                    'art_direction_breakpoints' => [
                        'type' => 'html',
                        'content' => '<div class="jcogs-img-pro-field-art-direction-settings jcogs-img-pro-field-settings-box" style="max-width: 760px;">'
                            . $adGridHtml
                            . '<div style="margin-top:4px; opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_setting_art_direction_breakpoints_tip') . '</div>'
                            . '</div>',
                    ],
                ],
            ],
            [
                'title' => lang('jcogs_img_pro_field_setting_enable_debug_title'),
                'desc' => lang('jcogs_img_pro_field_setting_enable_debug_desc'),
                'fields' => [
                    'enable_debug' => [
                        'type' => 'yes_no',
                        'value' => ($data['enable_debug'] === 'y') ? 'y' : 'n',
                    ],
                ],
            ],
        ];
    }

    /**
     * Validate submitted settings.
     *
     * Returns an EE Validation Result.
     */
    public function validateSettings(array $data, callable $postedSettingValue)
    {
        $validator = ee('Validation')->make([
            'default_aspect_ratio' => 'aspectDefaultValid',
            'require_aspect_ratio' => 'requireAspectRatioValid',
            'art_direction_breakpoints' => 'artDirectionValid',
            'preset_restrict' => 'presetRestrictionValid',
            'default_preset_id' => 'defaultPresetValid',
            'face_detect_default_quality' => 'faceDetectQualityValid',
            'face_detect_default_sensitivity' => 'faceDetectSensitivityValid',
            'face_detect_default_margin' => 'faceDetectMarginValid',
            'srcset_widths' => 'srcsetWidthsValid',
        ]);

        $validator->defineRule('aspectDefaultValid', function ($key, $value) use ($data, $postedSettingValue) {
            $enableCrop = ((string) $postedSettingValue('enable_crop', 'y') === 'y');
            if (! $enableCrop) {
                return true;
            }

            $pairs = $data['aspect_ratio_pairs'] ?? [];
            $pairs = ServiceCache::aspect_ratio()->normalisePairsFromPosted($pairs);
            if (empty($pairs) && ! empty($data['aspect_ratio_choices'])) {
                $pairs = ServiceCache::aspect_ratio()->parseChoices((string) $data['aspect_ratio_choices']);
            }

            $values = [];
            if (is_array($pairs)) {
                foreach (array_keys($pairs) as $k) {
                    $k = ServiceCache::aspect_ratio()->normalizeSetting((string) $k);
                    if ($k !== '' && $k !== '__inherit__') {
                        $values[] = $k;
                    }
                }
            }

            $values = array_values(array_unique($values));
            if (count($values) <= 1) {
                return true;
            }

            $default = ServiceCache::aspect_ratio()->normalizeSetting((string) $value);
            if ($default === '' || ! in_array($default, $values, true)) {
                return lang('jcogs_img_pro_field_validation_aspect_default_required');
            }

            return true;
        });

        $validator->defineRule('requireAspectRatioValid', function ($key, $value) use ($data, $postedSettingValue) {
            $enableCrop = ((string) $postedSettingValue('enable_crop', 'y') === 'y');
            $requireAspect = ((string) $postedSettingValue('require_aspect_ratio', 'n') === 'y');
            if (! $enableCrop || ! $requireAspect) {
                return true;
            }

            $pairs = $data['aspect_ratio_pairs'] ?? [];
            $pairs = ServiceCache::aspect_ratio()->normalisePairsFromPosted($pairs);
            if (empty($pairs) && ! empty($data['aspect_ratio_choices'])) {
                $pairs = ServiceCache::aspect_ratio()->parseChoices((string) $data['aspect_ratio_choices']);
            }

            $values = [];
            if (is_array($pairs)) {
                foreach (array_keys($pairs) as $k) {
                    $k = ServiceCache::aspect_ratio()->normalizeSetting((string) $k);
                    if ($k !== '' && $k !== '__inherit__') {
                        $values[] = $k;
                    }
                }
            }

            $values = array_values(array_unique($values));
            if (count($values) < 1) {
                return lang('jcogs_img_pro_field_validation_aspect_ratio_options_required');
            }

            return true;
        });

        $validator->defineRule('artDirectionValid', function ($key, $value) use ($data, $postedSettingValue) {
            if ((string) $postedSettingValue('enable_art_direction', 'n') !== 'y') {
                return true;
            }

            if (! is_array($value)) {
                return true;
            }

            $rows = $value;
            if (isset($rows['rows']) && is_array($rows['rows'])) {
                $rows = $rows['rows'];
            }

            $validRows = 0;
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $rawBreakpoint = isset($row['breakpoint']) && is_scalar($row['breakpoint']) ? trim((string) $row['breakpoint']) : '';
                $rawMedia = isset($row['media']) && is_scalar($row['media']) ? trim((string) $row['media']) : '';
                $raw = ($rawBreakpoint !== '') ? $rawBreakpoint : $rawMedia;
                if ($raw === '') {
                    continue;
                }

                if (strlen($raw) > 200 || preg_match('/["\'\<\>\{\};]/', $raw)) {
                    return lang('jcogs_img_pro_field_validation_art_direction_invalid_chars');
                }

                $validRows++;
            }

            if ($validRows === 0) {
                return true;
            }

            if ($validRows > 3) {
                return lang('jcogs_img_pro_field_validation_art_direction_too_many_rows');
            }

            return true;
        });

        $validator->defineRule('presetRestrictionValid', function ($key, $value) use ($data) {
            if ((($data['enable_preset'] ?? 'y') !== 'y') || ((string) $value !== 'y')) {
                return true;
            }

            $allowNone = (($data['preset_allow_none'] ?? 'y') === 'y');
            $allowedIds = $data['preset_allowed_ids'] ?? [];
            if (! is_array($allowedIds)) {
                $allowedIds = [];
            }

            $allowedIds = array_values(array_unique(array_filter(array_map(static function ($v) {
                $v = is_scalar($v) ? (string) $v : '';
                $v = trim($v);
                return (is_numeric($v) && (int) $v > 0) ? (string) ((int) $v) : '';
            }, $allowedIds))));

            if (empty($allowedIds) && ! $allowNone) {
                return lang('jcogs_img_pro_field_validation_preset_restrict_requires_allowed_or_none');
            }

            return true;
        });

        $validator->defineRule('defaultPresetValid', function ($key, $value) use ($data) {
            if ((($data['enable_preset'] ?? 'y') !== 'y') || (($data['preset_restrict'] ?? 'n') !== 'y')) {
                return true;
            }

            $defaultId = is_scalar($value) ? trim((string) $value) : '';
            $defaultId = (is_numeric($defaultId) && (int) $defaultId > 0) ? (string) ((int) $defaultId) : '';
            if ($defaultId === '') {
                return true;
            }

            $allowedIds = $data['preset_allowed_ids'] ?? [];
            if (! is_array($allowedIds)) {
                $allowedIds = [];
            }

            $allowedIds = array_values(array_unique(array_filter(array_map(static function ($v) {
                $v = is_scalar($v) ? (string) $v : '';
                $v = trim($v);
                return (is_numeric($v) && (int) $v > 0) ? (string) ((int) $v) : '';
            }, $allowedIds))));

            if (! in_array($defaultId, $allowedIds, true)) {
                return lang('jcogs_img_pro_field_validation_default_preset_must_be_allowed');
            }

            return true;
        });

        $validator->defineRule('faceDetectQualityValid', function ($key, $value) use ($data) {
            if ((($data['enable_crop'] ?? 'y') !== 'y') || (($data['enable_face_detect'] ?? 'y') !== 'y')) {
                return true;
            }

            $q = is_scalar($value) ? (string) $value : '';
            if (! in_array($q, ['fast', 'balanced', 'accurate'], true)) {
                return lang('jcogs_img_pro_field_validation_face_detect_quality_invalid');
            }

            return true;
        });

        $validator->defineRule('faceDetectSensitivityValid', function ($key, $value) use ($data) {
            if ((($data['enable_crop'] ?? 'y') !== 'y') || (($data['enable_face_detect'] ?? 'y') !== 'y')) {
                return true;
            }

            $raw = is_scalar($value) ? trim((string) $value) : '';
            if ($raw === '' || ! is_numeric($raw)) {
                return lang('jcogs_img_pro_field_validation_face_detect_sensitivity_range');
            }

            $n = (int) $raw;
            if ((string) $n !== (string) ((int) $raw) || $n < 1 || $n > 9) {
                return lang('jcogs_img_pro_field_validation_face_detect_sensitivity_range');
            }

            return true;
        });

        $validator->defineRule('faceDetectMarginValid', function ($key, $value) use ($data) {
            if ((($data['enable_crop'] ?? 'y') !== 'y') || (($data['enable_face_detect'] ?? 'y') !== 'y')) {
                return true;
            }

            $raw = is_scalar($value) ? trim((string) $value) : '';
            if ($raw === '' || ! is_numeric($raw)) {
                return lang('jcogs_img_pro_field_validation_face_detect_margin_range');
            }

            $n = (int) $raw;
            if ($n < 0 || $n > 500) {
                return lang('jcogs_img_pro_field_validation_face_detect_margin_range');
            }

            return true;
        });

        $validator->defineRule('srcsetWidthsValid', function ($key, $value) use ($data) {
            if ((($data['enable_responsive_defaults'] ?? 'y') !== 'y')) {
                return true;
            }

            if (! is_array($value) || empty($value)) {
                return true;
            }

            $rows = $value;
            if (isset($rows['rows']) && is_array($rows['rows'])) {
                $rows = $rows['rows'];
            }

            $invalidFound = false;
            foreach ($rows as $row) {
                $raw = '';
                if (is_array($row) && array_key_exists('width', $row)) {
                    $raw = is_scalar($row['width']) ? trim((string) $row['width']) : '';
                } elseif (is_scalar($row)) {
                    $raw = trim((string) $row);
                }

                if ($raw === '') {
                    continue;
                }

                if (! is_numeric($raw) || (int) $raw <= 0) {
                    $invalidFound = true;
                    break;
                }
            }

            if ($invalidFound) {
                return lang('jcogs_img_pro_field_validation_srcset_widths_invalid');
            }

            $widths = ServiceCache::responsive_defaults()->normaliseSrcsetWidthsFromPosted($value);
            if (count($widths) > 20) {
                return lang('jcogs_img_pro_field_validation_srcset_widths_too_many');
            }

            return true;
        });

        return $validator->validate($data);
    }

    /**
     * Normalise settings from POST.
     */
    public function saveSettings(array $data, callable $postedSettingValue): array
    {
        $settings = [];

        $allowedDirectories = trim((string) ($data['allowed_directories'] ?? 'all'));
        if ($allowedDirectories !== 'all') {
            $allowedDirectories = (is_numeric($allowedDirectories) && (int) $allowedDirectories > 0) ? (string) ((int) $allowedDirectories) : 'all';
        }
        $settings['allowed_directories'] = $allowedDirectories;

        $settings['enable_preset'] = ((string) $postedSettingValue('enable_preset', 'y') === 'n') ? 'n' : 'y';

        $settings['enable_preset_choice'] = ((string) $postedSettingValue('enable_preset_choice', 'y') === 'n') ? 'n' : 'y';
        if ($settings['enable_preset'] === 'n') {
            $settings['enable_preset_choice'] = 'n';
        }

        $settings['preset_restrict'] = ((string) $postedSettingValue('preset_restrict', 'n') === 'y') ? 'y' : 'n';
        $settings['preset_allow_none'] = ((string) $postedSettingValue('preset_allow_none', 'y') === 'y') ? 'y' : 'n';

        $allowedIds = $data['preset_allowed_ids'] ?? [];
        if (! is_array($allowedIds)) {
            $allowedIds = [];
        }
        $allowedIds = array_values(array_unique(array_filter(array_map(static function ($v) {
            $v = is_scalar($v) ? (string) $v : '';
            $v = trim($v);
            return (is_numeric($v) && (int) $v > 0) ? (string) ((int) $v) : '';
        }, $allowedIds))));
        $settings['preset_allowed_ids'] = $allowedIds;

        $defaultPresetId = trim((string) ($data['default_preset_id'] ?? ''));
        $defaultPresetId = (is_numeric($defaultPresetId) && (int) $defaultPresetId > 0)
            ? (string) ((int) $defaultPresetId)
            : '';

        // If presets are restricted, ensure the default is valid.
        if ($settings['preset_restrict'] === 'y') {
            if ($defaultPresetId !== '' && ! in_array($defaultPresetId, $allowedIds, true)) {
                $defaultPresetId = '';
            }

            // If the editor will have exactly one preset choice (and none is not allowed), enforce it.
            if ($defaultPresetId === '' && $settings['preset_allow_none'] === 'n' && count($allowedIds) === 1) {
                $defaultPresetId = (string) $allowedIds[0];
            }
        }

        $settings['default_preset_id'] = $defaultPresetId;

        $settings['enable_crop'] = ((string) $postedSettingValue('enable_crop', 'y') === 'n') ? 'n' : 'y';

        // Require crop is only meaningful when crop tools are enabled.
        if ($settings['enable_crop'] === 'n') {
            $settings['require_crop'] = 'n';
        } else {
            $settings['require_crop'] = ((string) $postedSettingValue('require_crop', 'n') === 'y') ? 'y' : 'n';
        }

        // Require aspect ratio is only meaningful when crop tools are enabled.
        if ($settings['enable_crop'] === 'n') {
            $settings['require_aspect_ratio'] = 'n';
        } else {
            $settings['require_aspect_ratio'] = ((string) $postedSettingValue('require_aspect_ratio', 'n') === 'y') ? 'y' : 'n';
        }

        // Aspect ratio choices: prefer mini-grid pairs, but keep legacy textarea for backwards compatibility.
        $pairs = $data['aspect_ratio_pairs'] ?? [];
        $settings['aspect_ratio_pairs'] = ServiceCache::aspect_ratio()->normalisePairsFromPosted($pairs);

        $rawChoices = (string) ($data['aspect_ratio_choices'] ?? '');
        $rawChoices = str_replace(["\r\n", "\r"], "\n", $rawChoices);
        $settings['aspect_ratio_choices'] = trim($rawChoices);

        $defaultAspectRatio = ServiceCache::aspect_ratio()->normalizeSetting((string) ($data['default_aspect_ratio'] ?? ''));

        // If more than one aspect ratio is defined, require (and normalise) a default.
        $aspectPairs = $settings['aspect_ratio_pairs'] ?? [];
        $aspectValues = [];
        if (is_array($aspectPairs)) {
            foreach (array_keys($aspectPairs) as $k) {
                $aspectValues[] = (string) $k;
            }
        }

        if (count($aspectValues) <= 1) {
            $defaultAspectRatio = '';
        } else {
            if ($defaultAspectRatio === '' || ! array_key_exists($defaultAspectRatio, $aspectPairs)) {
                foreach ($aspectValues as $v) {
                    $defaultAspectRatio = (string) $v;
                    break;
                }
            }
        }

        $settings['default_aspect_ratio'] = $defaultAspectRatio;

        $settings['enable_responsive_defaults'] = ((string) $postedSettingValue('enable_responsive_defaults', 'y') === 'n') ? 'n' : 'y';

        $settings['srcset_widths'] = ServiceCache::responsive_defaults()->normaliseSrcsetWidthsFromPosted($data['srcset_widths'] ?? []);
        $settings['default_allow_scale_larger'] = ((string) $postedSettingValue('default_allow_scale_larger', 'n') === 'y') ? 'y' : 'n';

        // Art direction.
        $settings['enable_art_direction'] = ((string) $postedSettingValue('enable_art_direction', 'n') === 'y') ? 'y' : 'n';
        $settings['art_direction_breakpoints'] = ServiceCache::art_direction()->normaliseBreakpointsFromPosted($data['art_direction_breakpoints'] ?? []);

        // Manual crop fields are intentionally hidden from field settings; keep for backwards compatibility.
        $settings['enable_manual'] = 'n';

        if ($settings['enable_crop'] === 'n') {
            $settings['enable_focal'] = 'n';
        } else {
            $settings['enable_focal'] = ((string) $postedSettingValue('enable_focal', 'n') === 'y') ? 'y' : 'n';
        }

        if ($settings['enable_crop'] === 'n') {
            $settings['enable_face_detect'] = 'n';
        } else {
            $settings['enable_face_detect'] = ((string) $postedSettingValue('enable_face_detect', 'y') === 'n') ? 'n' : 'y';
        }

        $faceControls = trim((string) ($data['face_detect_controls'] ?? 'advanced'));
        if (! in_array($faceControls, ['hidden', 'advanced', 'visible'], true)) {
            $faceControls = 'advanced';
        }
        $settings['face_detect_controls'] = $faceControls;

        $quality = strtolower(trim((string) ($data['face_detect_default_quality'] ?? 'balanced')));
        if (! in_array($quality, ['fast', 'balanced', 'accurate'], true)) {
            $quality = 'balanced';
        }
        $settings['face_detect_default_quality'] = $quality;

        $sens = (int) ($data['face_detect_default_sensitivity'] ?? 3);
        if ($sens < 1) {
            $sens = 1;
        } elseif ($sens > 9) {
            $sens = 9;
        }
        $settings['face_detect_default_sensitivity'] = $sens;

        $margin = (int) ($data['face_detect_default_margin'] ?? 0);
        if ($margin < 0) {
            $margin = 0;
        } elseif ($margin > 500) {
            $margin = 500;
        }
        $settings['face_detect_default_margin'] = $margin;

        $settings['enable_debug'] = ((string) $postedSettingValue('enable_debug', 'n') === 'y') ? 'y' : 'n';
        $settings['field_wide'] = true;

        return $settings;
    }
}
