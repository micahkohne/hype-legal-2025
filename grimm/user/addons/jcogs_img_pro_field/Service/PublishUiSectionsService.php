<?php

/**
 * JCOGS Image Pro Field - PublishUiSectionsService
 *=================================================
 * Renders the discrete tab-panel sections for the publish UI.
 *
 * Extracted from ft.jcogs_img_pro_field.php so that display_field() acts as a
 * thin coordinator rather than a monolith.  Each method corresponds to one
 * logical section of the editor UI: tab bar, preset panel, hidden inputs block,
 * crop panel, focal/face-detection panel, art-direction panel, and the inline
 * debug footer.
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.0
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 * @since      1.0.1
 */

namespace JCOGSDesign\JcogsImgProField\Service;

/**
 * Build HTML for each publish-UI section (tab bar, preset, hidden inputs,
 * crop, focal/face-detect, art-direction, debug footer).
 */
class PublishUiSectionsService
{
    // -------------------------------------------------------------------------
    // Tab bar
    // -------------------------------------------------------------------------

    /**
     * Render the tab bar when two or more tool sections are active.
     *
     * @param bool   $showTabPreset
     * @param bool   $showTabCrop
     * @param bool   $showTabArtDirection
     * @param string $defaultTab  One of: 'preset', 'crop', 'art_direction'
     */
    public function renderTabBar(bool $showTabPreset, bool $showTabCrop, bool $showTabArtDirection, string $defaultTab): string
    {
        $html  = '<div class="jcogs-img-pro-field-tabs tab-bar" style="margin:0 0 10px 0;">'
               . '<div class="tab-bar__tabs">';

        if ($showTabPreset) {
            $html .= '<button type="button" class="tab-bar__tab jcogs-img-pro-field-tab"'
                   . ' data-jcogs-tab="preset"'
                   . ($defaultTab === 'preset' ? ' data-jcogs-tab-default="1"' : '')
                   . '>'
                   . lang('jcogs_img_pro_field_editor_label_preset')
                   . '</button>';
        }
        if ($showTabCrop) {
            $html .= '<button type="button" class="tab-bar__tab jcogs-img-pro-field-tab"'
                   . ' data-jcogs-tab="crop"'
                   . ($defaultTab === 'crop' ? ' data-jcogs-tab-default="1"' : '')
                   . '>'
                   . lang('jcogs_img_pro_field_editor_heading_crop')
                   . '</button>';
        }
        if ($showTabArtDirection) {
            $html .= '<button type="button" class="tab-bar__tab jcogs-img-pro-field-tab"'
                   . ' data-jcogs-tab="art_direction"'
                   . ($defaultTab === 'art_direction' ? ' data-jcogs-tab-default="1"' : '')
                   . '>'
                   . lang('jcogs_img_pro_field_editor_heading_art_direction')
                   . '</button>';
        }

        $html .= '</div></div>';
        return $html;
    }

    // -------------------------------------------------------------------------
    // Preset panel
    // -------------------------------------------------------------------------

    /**
     * Render the preset selector panel.
     *
     * @param array<string, string> $presetOptions
     */
    public function renderPresetPanel(
        int    $fieldId,
        int    $entryId,
        string $presetId,
        array  $presetOptions,
        string $previewActUrl,
        bool   $useTabs
    ): string {
        $html = '';

        if ($useTabs) {
            $html .= '<div class="jcogs-img-pro-field-tab-panel" data-jcogs-tab-panel="preset">';
        }

        $html .= '<div class="jcogs-img-pro-field-tab-intro">' . lang('jcogs_img_pro_field_editor_intro_preset') . '</div>';
        $html .= '<div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; padding:8px 0;">';
        $html .= '<div>';
        $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_preset') . '</div>';
        $html .= form_dropdown(
            'jcogs_img_pro_field[' . $fieldId . '][preset_id]',
            $presetOptions,
            $presetId,
            'style="width:220px;"',
        );
        $html .= '</div>';

        if ($entryId > 0 && $fieldId > 0 && $previewActUrl !== '') {
            $html .= '<div>';
            $html .= '<div class="field-instruct">&nbsp;</div>';
            $html .= form_button([
                'type'    => 'button',
                'class'   => 'button button--secondary jcogs-img-pro-field-preview',
                'content' => lang('jcogs_img_pro_field_editor_btn_preview_preset'),
            ]);
            $html .= '</div>';
        }

        $html .= '</div>';

        if ($useTabs) {
            $html .= '</div>';
        }

        return $html;
    }

    // -------------------------------------------------------------------------
    // Hidden inputs block
    // -------------------------------------------------------------------------

    /**
     * Render the hidden form inputs that carry field context and crop state.
     *
     * @param array<string, mixed> $ctx  Keys: field_id, file_id, field_name,
     *   content_type, container_id, row_id, fluid_field_data_id, block_id,
     *   crop_rect_left, crop_rect_top, crop_rect_width, crop_rect_height,
     *   has_crop_defined, debug_enabled, enable_crop, aspect_ratio_hidden_value
     */
    public function renderHiddenInputs(array $ctx): string
    {
        $fieldId    = (int) ($ctx['field_id'] ?? 0);
        $fileId     = (int) ($ctx['file_id'] ?? 0);
        $fieldName  = (string) ($ctx['field_name'] ?? '');
        $contentType          = (string) ($ctx['content_type'] ?? 'channel');
        $containerId          = $ctx['container_id'] ?? null;
        $rowId                = $ctx['row_id'] ?? null;
        $fluidFieldDataId     = $ctx['fluid_field_data_id'] ?? null;
        $blockId              = $ctx['block_id'] ?? null;
        $cropRectLeft         = (string) ($ctx['crop_rect_left'] ?? '');
        $cropRectTop          = (string) ($ctx['crop_rect_top'] ?? '');
        $cropRectWidth        = (string) ($ctx['crop_rect_width'] ?? '');
        $cropRectHeight       = (string) ($ctx['crop_rect_height'] ?? '');
        $hasCropDefined       = (bool) ($ctx['has_crop_defined'] ?? false);
        $debugEnabled         = (bool) ($ctx['debug_enabled'] ?? false);
        $enableCrop           = (bool) ($ctx['enable_crop'] ?? false);
        $aspectRatioHiddenVal = (string) ($ctx['aspect_ratio_hidden_value'] ?? '');

        $html  = form_hidden('jcogs_img_pro_field[' . $fieldId . '][field_id]', $fieldId);
        $html .= form_hidden('jcogs_img_pro_field[' . $fieldId . '][file_value]', $fileId > 0 ? (string) $fileId : '');
        $html .= form_hidden('jcogs_img_pro_field[' . $fieldId . '][file_input_name]', $fieldName);
        $html .= form_hidden('jcogs_img_pro_field[' . $fieldId . '][content_type]', $contentType);
        $html .= form_hidden('jcogs_img_pro_field[' . $fieldId . '][container_id]', $containerId !== null ? (int) $containerId : '');
        $html .= form_hidden('jcogs_img_pro_field[' . $fieldId . '][row_id]', $rowId !== null ? (int) $rowId : '');
        $html .= form_hidden('jcogs_img_pro_field[' . $fieldId . '][fluid_field_data_id]', $fluidFieldDataId !== null ? (int) $fluidFieldDataId : '');
        $html .= form_hidden('jcogs_img_pro_field[' . $fieldId . '][block_id]', $blockId !== null ? (int) $blockId : '');
        $html .= form_hidden('jcogs_img_pro_field[' . $fieldId . '][crop_rect_left]', $cropRectLeft);
        $html .= form_hidden('jcogs_img_pro_field[' . $fieldId . '][crop_rect_top]', $cropRectTop);
        $html .= form_hidden('jcogs_img_pro_field[' . $fieldId . '][crop_rect_width]', $cropRectWidth);
        $html .= form_hidden('jcogs_img_pro_field[' . $fieldId . '][crop_rect_height]', $cropRectHeight);
        $html .= form_hidden('jcogs_img_pro_field[' . $fieldId . '][crop_present]', $hasCropDefined ? '1' : '');

        if ($debugEnabled) {
            $html .= form_hidden('jcogs_img_pro_field_debug', '1');
        }

        if ($enableCrop) {
            $html .= form_hidden('jcogs_img_pro_field[' . $fieldId . '][aspect_ratio]', $aspectRatioHiddenVal);
        }

        return $html;
    }

    // -------------------------------------------------------------------------
    // Crop panel
    // -------------------------------------------------------------------------

    /**
     * Render the crop tab panel, including focal/face-detect if enabled, and
     * the manual overrides grid for superadmins.
     *
     * @param array<string, mixed> $ctx  See inline keys below.
     * @param array<string, string> $aspectRatioChoices  Normalised choices from field settings.
     */
    public function renderCropPanel(array $ctx, array $aspectRatioChoices): string
    {
        $fieldId              = (int) ($ctx['field_id'] ?? 0);
        $entryId              = (int) ($ctx['entry_id'] ?? 0);
        $previewActUrl        = (string) ($ctx['preview_act_url'] ?? '');
        $faceDetectActUrl     = (string) ($ctx['face_detect_act_url'] ?? '');
        $hasCropDefined       = (bool) ($ctx['has_crop_defined'] ?? false);
        $requireAspectRatio   = (bool) ($ctx['require_aspect_ratio'] ?? false);
        $aspectRatioEffective = (string) ($ctx['aspect_ratio_effective'] ?? '');
        $aspectRatioIsInherit = (bool) ($ctx['aspect_ratio_is_inherit_override'] ?? false);
        $useTabs              = (bool) ($ctx['use_tabs'] ?? false);
        $enableFocal          = (bool) ($ctx['enable_focal'] ?? false);
        $enableManual         = (bool) ($ctx['enable_manual'] ?? false);
        $enableFaceDetect     = (bool) ($ctx['enable_face_detect'] ?? false);
        $faceDetectControlsMode     = (string) ($ctx['face_detect_controls_mode'] ?? 'advanced');
        $faceDetectDefaultQuality   = (string) ($ctx['face_detect_default_quality'] ?? 'balanced');
        $faceDetectDefaultSensitivity = (int) ($ctx['face_detect_default_sensitivity'] ?? 3);
        $faceDetectDefaultMargin    = (int) ($ctx['face_detect_default_margin'] ?? 0);
        $isSuperadmin         = (bool) ($ctx['is_superadmin'] ?? false);
        $showPresetSelector   = (bool) ($ctx['show_preset_selector'] ?? false);
        $focalX               = (string) ($ctx['focal_x'] ?? '');
        $focalY               = (string) ($ctx['focal_y'] ?? '');
        $crop                 = (string) ($ctx['crop'] ?? '');
        $cropMode             = (string) ($ctx['crop_mode'] ?? '');
        $cropFocusH           = (string) ($ctx['crop_focus_h'] ?? '');
        $cropFocusV           = (string) ($ctx['crop_focus_v'] ?? '');
        $cropOffsetX          = (string) ($ctx['crop_offset_x'] ?? '');
        $cropOffsetY          = (string) ($ctx['crop_offset_y'] ?? '');
        $cropSmartScaling     = (string) ($ctx['crop_smart_scaling'] ?? '');
        $width                = (string) ($ctx['width'] ?? '');
        $height               = (string) ($ctx['height'] ?? '');
        $aspectRatioChoicesCount = count($aspectRatioChoices);

        $html = '';

        if ($useTabs) {
            $html .= '<div class="jcogs-img-pro-field-tab-panel" data-jcogs-tab-panel="crop">';
        }

        $html .= '<div style="margin-top:8px; padding:8px 0; border-top:1px solid #eee;">';
        $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_heading_crop') . '</div>';
        $html .= '<div class="jcogs-img-pro-field-tab-intro">' . lang('jcogs_img_pro_field_editor_intro_crop') . '</div>';
        $html .= '<div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-start;">';
        $html .= '<div style="flex: 1 1 260px; min-width: 260px;">';

        if ($entryId > 0 && $fieldId > 0 && $previewActUrl !== '') {
            $html .= '<div class="button-group">';
            $html .= form_button([
                'type'    => 'button',
                'class'   => 'button button--primary jcogs-img-pro-field-pick-rect',
                'content' => ($hasCropDefined ? lang('jcogs_img_pro_field_editor_btn_edit_crop') : lang('jcogs_img_pro_field_editor_btn_crop')),
            ]);
            $html .= form_button([
                'type'    => 'button',
                'class'   => 'button button--secondary jcogs-img-pro-field-preview jcogs-img-pro-field-preview-reload',
                'content' => lang('jcogs_img_pro_field_editor_btn_reload_preview'),
            ]);
            $html .= form_button([
                'type'    => 'button',
                'class'   => 'button button--secondary jcogs-img-pro-field-danger-outline jcogs-img-pro-field-clear-rect',
                'content' => lang('jcogs_img_pro_field_editor_btn_clear_crop'),
            ]);
            $html .= '</div>';

            if (! $hasCropDefined) {
                $html .= '<div style="margin-top:6px; opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_editor_help_crop_click_crop') . '</div>';
            }
            $html .= '<div style="margin-top:6px; opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_editor_help_crop_pick') . '</div>';

            if ($requireAspectRatio && $aspectRatioEffective !== '' && $aspectRatioChoicesCount <= 1) {
                $html .= '<div style="margin-top:6px; opacity:.8; font-size:12px;">'
                       . sprintf(lang('jcogs_img_pro_field_editor_help_crop_aspect_enforced'), htmlspecialchars($aspectRatioEffective, ENT_QUOTES, 'UTF-8'))
                       . '</div>';
            }

            if ($aspectRatioChoicesCount > 1) {
                $options = $requireAspectRatio
                    ? $aspectRatioChoices
                    : (['' => lang('jcogs_img_pro_field_editor_option_inherit')] + $aspectRatioChoices);
                if ($aspectRatioEffective !== '' && ! array_key_exists($aspectRatioEffective, $options)) {
                    $options[$aspectRatioEffective] = sprintf(lang('jcogs_img_pro_field_editor_option_custom_aspect'), $aspectRatioEffective);
                }

                if ($requireAspectRatio) {
                    $selectedForUi = $aspectRatioEffective;
                    if ($selectedForUi === '') {
                        foreach (array_keys($options) as $k) {
                            $selectedForUi = (string) $k;
                            break;
                        }
                    }
                } else {
                    $selectedForUi = $aspectRatioIsInherit ? '' : $aspectRatioEffective;
                }

                $html .= '<div style="margin-top:10px; max-width:220px;">';
                $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_aspect_ratio') . '</div>';
                $html .= '<select class="select jcogs-img-pro-field-aspect-ratio-select" style="width:220px;">';
                foreach ($options as $val => $label) {
                    $sel   = ((string) $val === (string) $selectedForUi) ? ' selected' : '';
                    $html .= '<option value="' . htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
                           . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8')
                           . '</option>';
                }
                $html .= '</select>';
                $html .= '<div style="margin-top:4px; opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_editor_help_aspect_locks') . '</div>';
                $html .= '</div>';
            }
        } else {
            $html .= '<div style="opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_editor_help_crop_after_create') . '</div>';
        }

        $html .= '</div></div></div>';

        // Focal / Face detection (within the crop tab when tabs are in use).
        if ($enableFocal) {
            $hasPrevSection  = ($showPresetSelector || true /* crop is always shown here */);
            $html           .= '<div style="margin-top:' . ($hasPrevSection ? '8px' : '0') . '; padding:8px 0; border-top:' . ($hasPrevSection ? '1px solid #eee' : 'none') . ';">';
            $html           .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_heading_focal') . '</div>';
            $html           .= '<div class="jcogs-img-pro-field-tab-intro">' . lang('jcogs_img_pro_field_editor_intro_focal') . '</div>';

            if ($entryId > 0 && $fieldId > 0 && $previewActUrl !== '') {
                $html .= '<div class="button-group">';
                if (! $showPresetSelector) {
                    $html .= form_button([
                        'type'    => 'button',
                        'class'   => 'button button--secondary jcogs-img-pro-field-preview',
                        'content' => lang('jcogs_img_pro_field_editor_btn_load_preview'),
                    ]);
                }
                $html .= form_button([
                    'type'    => 'button',
                    'class'   => 'button button--primary jcogs-img-pro-field-pick-focal',
                    'content' => lang('jcogs_img_pro_field_editor_btn_pick_focal'),
                ]);
                $html .= form_button([
                    'type'    => 'button',
                    'class'   => 'button button--secondary jcogs-img-pro-field-danger-outline jcogs-img-pro-field-clear-focal',
                    'content' => lang('jcogs_img_pro_field_editor_btn_clear_focal'),
                ]);
                $html .= '</div>';
                $html .= '<div style="margin-top:6px; opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_editor_help_pick_focal') . '</div>';
            } else {
                $html .= '<div style="opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_editor_help_focal_after_create') . '</div>';
            }

            if ($isSuperadmin) {
                $html .= '<details class="jcogs-img-pro-field-advanced" style="margin-top:8px;">'
                       . '<summary style="cursor:pointer; user-select:none; opacity:.85; font-size:12px;"><span class="sub-arrow"></span>' . lang('jcogs_img_pro_field_editor_summary_advanced_numeric') . '</summary>'
                       . '<div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:8px;">';
                $html .= '<div>';
                $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_focal_x') . '</div>';
                $html .= form_input(['name' => 'jcogs_img_pro_field[' . $fieldId . '][focal_x]', 'value' => $focalX, 'placeholder' => '50', 'style' => 'width:120px;']);
                $html .= '</div>';
                $html .= '<div>';
                $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_focal_y') . '</div>';
                $html .= form_input(['name' => 'jcogs_img_pro_field[' . $fieldId . '][focal_y]', 'value' => $focalY, 'placeholder' => '50', 'style' => 'width:120px;']);
                $html .= '</div>';
                $html .= '</div></details>';
            } else {
                $html .= form_hidden('jcogs_img_pro_field[' . $fieldId . '][focal_x]', $focalX);
                $html .= form_hidden('jcogs_img_pro_field[' . $fieldId . '][focal_y]', $focalY);
            }

            if ($enableFaceDetect && $entryId > 0 && $fieldId > 0 && $faceDetectActUrl !== '') {
                $html .= $this->renderFaceDetectBlock(
                    $faceDetectControlsMode,
                    $faceDetectDefaultQuality,
                    $faceDetectDefaultSensitivity,
                    $faceDetectDefaultMargin
                );
            }

            $html .= '</div>';
        }

        // Manual overrides grid (superadmin only).
        if ($enableManual) {
            $html .= $this->renderManualOverridesBlock(
                $fieldId,
                $crop,
                $cropMode,
                $cropFocusH,
                $cropFocusV,
                $cropOffsetX,
                $cropOffsetY,
                $width,
                $height,
                $cropSmartScaling,
                $aspectRatioIsInherit,
                $aspectRatioEffective
            );
        }

        if ($useTabs) {
            $html .= '</div>';
        }

        return $html;
    }

    // -------------------------------------------------------------------------
    // Art-direction panel
    // -------------------------------------------------------------------------

    /**
     * Render the art-direction tab panel.
     *
     * @param int                  $fieldId
     * @param array<string, mixed>[] $adRows           Breakpoint rows from field settings.
     * @param array<string, mixed> $usagePayload       Current usage payload.
     * @param string               $allowedDirs        Allowed upload directories identifier.
     * @param bool                 $hasPrevSection
     * @param bool                 $useTabs
     * @param callable             $describeMedia      function(string $media): array
     */
    public function renderArtDirectionPanel(
        int      $fieldId,
        array    $adRows,
        array    $usagePayload,
        string   $allowedDirs,
        bool     $hasPrevSection,
        bool     $useTabs,
        callable $describeMedia
    ): string {
        $html = '';

        if ($useTabs) {
            $html .= '<div class="jcogs-img-pro-field-tab-panel" data-jcogs-tab-panel="art_direction">';
        }

        // Hidden dirty flag.
        $html .= form_hidden('jcogs_img_pro_field[' . $fieldId . '][art_direction_dirty]', '0');

        // Index-to-media map for JS.
        $idxToMedia = [];
        foreach ($adRows as $r) {
            $i = (int) ($r['index'] ?? 0);
            $m = isset($r['media']) ? (string) $r['media'] : '';
            if ($i > 0 && $m !== '') {
                $idxToMedia[$i] = $m;
            }
        }
        if (! empty($idxToMedia)) {
            $html .= '<input type="hidden"'
                   . ' class="jcogs-img-pro-field-ad-index-to-media"'
                   . ' name="jcogs_img_pro_field[' . $fieldId . '][art_direction_index_to_media]"'
                   . ' value="' . htmlspecialchars((string) json_encode($idxToMedia), ENT_QUOTES, 'UTF-8') . '"'
                   . '>';
        }

        $files = [];
        if (
            isset($usagePayload['art_direction']) && is_array($usagePayload['art_direction'])
            && isset($usagePayload['art_direction']['files']) && is_array($usagePayload['art_direction']['files'])
        ) {
            $files = $usagePayload['art_direction']['files'];
        }

        $html .= '<div style="margin-top:' . ($hasPrevSection ? '8px' : '0') . '; padding:8px 0; border-top:' . ($hasPrevSection ? '1px solid #eee' : 'none') . ';">';
        $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_heading_art_direction') . '</div>';
        $html .= '<div style="font-size:12px; opacity:.85; margin:2px 0 8px 0;">' . lang('jcogs_img_pro_field_editor_help_art_direction') . '</div>';

        foreach ($adRows as $row) {
            $idx   = (int) ($row['index'] ?? 0);
            $media = (string) ($row['media'] ?? '');
            if ($idx <= 0 || $media === '') {
                continue;
            }
            $presetIdRow = (int) ($row['preset_id'] ?? 0);

            $picked = 0;
            if (isset($files[$media])) {
                $picked = (int) $files[$media];
            } elseif (isset($files[(string) $idx])) {
                $picked = (int) $files[(string) $idx];
            }

            $desc         = $describeMedia($media);
            $label        = isset($desc['title']) ? (string) $desc['title'] : ($media !== '' ? $media : ('Breakpoint #' . $idx));
            $mediaCaption = isset($desc['media']) ? (string) $desc['media'] : $media;

            $html .= '<div class="jcogs-img-pro-field-ad-row">';
            $html .= '<div class="jcogs-img-pro-field-ad-row-meta">';
            $html .= '<div class="field-instruct jcogs-img-pro-field-ad-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>';
            if ($mediaCaption !== '') {
                $html .= '<div class="jcogs-img-pro-field-ad-meta-line">'
                       . sprintf(lang('jcogs_img_pro_field_editor_ad_alt_media_caption'), '<span style="font-family: ui-monospace, Menlo, Monaco, monospace;">' . htmlspecialchars($mediaCaption, ENT_QUOTES, 'UTF-8') . '</span>')
                       . '</div>';
            }
            $html .= '<div class="jcogs-img-pro-field-ad-meta-line">' . lang('jcogs_img_pro_field_editor_ad_alt_help_fallback') . '</div>';
            if ($presetIdRow > 0) {
                $html .= '<div class="jcogs-img-pro-field-ad-meta-line jcogs-img-pro-field-ad-meta-line--preset">'
                       . sprintf(lang('jcogs_img_pro_field_editor_ad_alt_preset_caption'), $presetIdRow)
                       . '</div>';
            }
            $html .= '</div>';

            // IMPORTANT: EE's drag/drop file field JS can behave unexpectedly with complex bracketed names,
            // especially when multiple file pickers exist inside one field UI.
            // Use a unique picker field name, then sync into the real posted hidden input.
            $pickerName  = 'jcogs_img_pro_field_ad_' . $fieldId . '_' . $idx;
            $storageName = 'jcogs_img_pro_field[' . $fieldId . '][art_direction_files][' . $idx . ']';

            $html .= '<div class="jcogs-img-pro-field-ad-row-picker">';
            $html .= '<div class="jcogs-img-pro-field-ad-picker" data-jcogs-ad-picker="1" data-ad-index="' . $idx . '">';
            $html .= '<div class="grid-file-upload jcogs-img-pro-field-ad-file-container">';
            $html .= ee()->file_field->dragAndDropField($pickerName, ($picked > 0 ? (string) $picked : ''), $allowedDirs, 'image');
            $html .= '</div>';
            $html .= '<input type="hidden"'
                   . ' name="' . htmlspecialchars($storageName, ENT_QUOTES, 'UTF-8') . '"'
                   . ' value="' . htmlspecialchars((string) ($picked > 0 ? $picked : ''), ENT_QUOTES, 'UTF-8') . '"'
                   . ' data-jcogs-ad-storage="1"'
                   . ' data-picker-name="' . htmlspecialchars($pickerName, ENT_QUOTES, 'UTF-8') . '"'
                   . '>';
            $html .= '</div></div></div>';
        }

        $html .= '</div>';

        if ($useTabs) {
            $html .= '</div>';
        }

        return $html;
    }

    // -------------------------------------------------------------------------
    // Inline debug footer
    // -------------------------------------------------------------------------

    /**
     * Render the single-line debug summary footer shown below the field.
     *
     * Only called when $enableDebug && $isSuperadmin.
     *
     * @param array<string, mixed> $ctx
     */
    public function renderDebugFooter(array $ctx): string
    {
        $siteId              = (int) ($ctx['site_id'] ?? 0);
        $entryId             = (int) ($ctx['entry_id'] ?? 0);
        $fieldId             = (int) ($ctx['field_id'] ?? 0);
        $fileId              = (int) ($ctx['file_id'] ?? 0);
        $usageActionId       = (int) ($ctx['usage_action_id'] ?? 0);
        $previewActionId     = (int) ($ctx['preview_action_id'] ?? 0);
        $faceDetectActionId  = (int) ($ctx['face_detect_action_id'] ?? 0);
        $showOptions         = (bool) ($ctx['show_options'] ?? false);
        $showPresetSelector  = (bool) ($ctx['show_preset_selector'] ?? false);
        $enablePreset        = (bool) ($ctx['enable_preset'] ?? false);
        $enableCrop          = (bool) ($ctx['enable_crop'] ?? false);
        $enableFocal         = (bool) ($ctx['enable_focal'] ?? false);
        $enableFaceDetect    = (bool) ($ctx['enable_face_detect'] ?? false);
        $enableArtDirection  = (bool) ($ctx['enable_art_direction'] ?? false);
        $settings            = is_array($ctx['settings'] ?? null) ? (array) $ctx['settings'] : [];

        return '<div class="field-instruct" style="margin-top:10px; font-family: ui-monospace, Menlo, Monaco, monospace; font-size: 12px; opacity: .85;">'
             . 'Debug: site_id=' . $siteId
             . ' entry_id=' . $entryId
             . ' field_id=' . $fieldId
             . ' stored_file_id=' . $fileId
             . ' usage_action_id=' . $usageActionId
             . ' preview_action_id=' . $previewActionId
             . ' face_detect_action_id=' . $faceDetectActionId
             . ' show_options=' . ($showOptions ? '1' : '0')
             . ' show_preset_selector=' . ($showPresetSelector ? '1' : '0')
             . ' enable_preset=' . ($enablePreset ? 'y' : 'n')
             . ' enable_crop=' . ($enableCrop ? 'y' : 'n')
             . ' enable_focal=' . ($enableFocal ? 'y' : 'n')
             . ' enable_face_detect=' . ($enableFaceDetect ? 'y' : 'n')
             . ' enable_art_direction=' . ($enableArtDirection ? 'y' : 'n')
             . ' settings{enable_preset=' . (string) ($settings['enable_preset'] ?? '')
             . ', enable_crop=' . (string) ($settings['enable_crop'] ?? '')
             . ', enable_focal=' . (string) ($settings['enable_focal'] ?? '')
             . ', enable_face_detect=' . (string) ($settings['enable_face_detect'] ?? '')
             . ', enable_art_direction=' . (string) ($settings['enable_art_direction'] ?? '')
             . ', enable_debug=' . (string) ($settings['enable_debug'] ?? '')
             . '}'
             . '</div>';
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Render the face-detection UI block (buttons + settings row).
     */
    private function renderFaceDetectBlock(
        string $controlsMode,
        string $defaultQuality,
        int    $defaultSensitivity,
        int    $defaultMargin
    ): string {
        $faceUiVisible = ($controlsMode !== 'hidden');

        $html  = '<div class="jcogs-img-pro-field-face-detect-ui" style="' . ($faceUiVisible ? 'display:block;' : 'display:none;') . ' margin-top:10px; padding-top:8px; border-top:1px solid #eee;">'
               . '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_heading_face_detection') . '</div>'
               . '<div class="jcogs-img-pro-field-tab-intro">' . lang('jcogs_img_pro_field_editor_intro_face_detection') . '</div>'
               . '<div class="jcogs-img-pro-field-face-detect-summary" style="opacity:.85; font-size:12px; color:#555;">'
               . ($faceUiVisible ? lang('jcogs_img_pro_field_editor_help_face_detection') : '')
               . '</div>';

        $html .= '<div class="button-group" style="margin-top:6px;">'
               . form_button(['type' => 'button', 'class' => 'button button--primary jcogs-img-pro-field-face-detect',           'content' => lang('jcogs_img_pro_field_editor_btn_detect_faces')])
               . form_button(['type' => 'button', 'class' => 'button button--secondary jcogs-img-pro-field-face-apply-focal',     'content' => lang('jcogs_img_pro_field_editor_btn_apply_suggested_focal')])
               . form_button(['type' => 'button', 'class' => 'button button--secondary jcogs-img-pro-field-face-apply-crop',      'content' => lang('jcogs_img_pro_field_editor_btn_apply_crop_from_faces')])
               . form_button(['type' => 'button', 'class' => 'button button--secondary jcogs-img-pro-field-danger-outline jcogs-img-pro-field-face-clear-overlay', 'content' => lang('jcogs_img_pro_field_editor_btn_clear_overlay')])
               . '</div>';

        $settingsRowHtml = '<div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-top:6px;">'
            . '<div>'
            . '<div class="field-instruct" style="font-size:12px;">' . lang('jcogs_img_pro_field_editor_label_quality') . '</div>'
            . '<select class="select jcogs-img-pro-field-face-quality" style="width:140px;">'
            . '<option value="fast"'     . ($defaultQuality === 'fast'     ? ' selected' : '') . '>fast</option>'
            . '<option value="balanced"' . ($defaultQuality === 'balanced' ? ' selected' : '') . '>balanced</option>'
            . '<option value="accurate"' . ($defaultQuality === 'accurate' ? ' selected' : '') . '>accurate</option>'
            . '</select>'
            . '</div>'
            . '<div>'
            . '<div class="field-instruct" style="font-size:12px;">' . lang('jcogs_img_pro_field_editor_label_sensitivity') . '</div>'
            . '<input type="number" class="input jcogs-img-pro-field-face-sensitivity" value="' . $defaultSensitivity . '" min="1" max="9" step="1" style="width:90px;">'
            . '</div>'
            . '<div>'
            . '<div class="field-instruct" style="font-size:12px;">' . lang('jcogs_img_pro_field_editor_label_margin_px') . '</div>'
            . '<input type="number" class="input jcogs-img-pro-field-face-margin" value="' . $defaultMargin . '" min="0" max="500" step="1" style="width:110px;">'
            . '</div>'
            . '<label style="display:flex; align-items:center; gap:6px; font-size:12px; opacity:.9;">'
            . '<input type="checkbox" class="jcogs-img-pro-field-face-force" value="1">'
            . '<span>' . lang('jcogs_img_pro_field_editor_label_ignore_cache') . '</span>'
            . '</label>'
            . '<div>'
            . form_button(['type' => 'button', 'class' => 'button button--default jcogs-img-pro-field-face-restore-defaults', 'content' => lang('jcogs_img_pro_field_editor_btn_restore_defaults')])
            . '</div>'
            . '</div>';

        if ($controlsMode === 'visible') {
            $html .= $settingsRowHtml;
        } elseif ($controlsMode === 'advanced') {
            $html .= '<details class="jcogs-img-pro-field-advanced" style="margin-top:6px;">'
                   . '<summary style="cursor:pointer; user-select:none; opacity:.85; font-size:12px;"><span class="sub-arrow"></span>' . lang('jcogs_img_pro_field_editor_summary_face_detection_settings') . '</summary>'
                   . '<div style="margin-top:8px;">' . $settingsRowHtml . '</div>'
                   . '</details>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render the manual (power-user) crop override grid.
     */
    private function renderManualOverridesBlock(
        int    $fieldId,
        string $crop,
        string $cropMode,
        string $cropFocusH,
        string $cropFocusV,
        string $cropOffsetX,
        string $cropOffsetY,
        string $width,
        string $height,
        string $cropSmartScaling,
        bool   $aspectRatioIsInherit,
        string $aspectRatioEffective
    ): string {
        $html  = '<div style="margin-top:8px; padding:8px 0; border-top:1px solid #eee;">'
               . '<label style="display:flex; align-items:center; gap:6px;">'
               . '<input type="checkbox" class="jcogs-img-pro-field-toggle-manual" value="1">'
               . '<span class="field-instruct" style="margin:0;">' . lang('jcogs_img_pro_field_editor_label_edit_manually') . '</span>'
               . '</label>'
               . '<div style="opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_editor_help_manual') . '</div>'
               . '</div>';

        $html .= '<div class="jcogs-img-pro-field-manual" style="display:none;">';
        $html .= '<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-top:8px;">';

        $html .= '<div style="grid-column: 1 / -1;">';
        $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_crop_override_raw') . '</div>';
        $html .= form_input(['name' => 'jcogs_img_pro_field[' . $fieldId . '][crop]', 'value' => $crop, 'placeholder' => lang('jcogs_img_pro_field_editor_placeholder_crop_override'), 'style' => 'width:100%;']);
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_crop_mode') . '</div>';
        $html .= form_dropdown('jcogs_img_pro_field[' . $fieldId . '][crop_mode]', ['' => lang('jcogs_img_pro_field_editor_option_inherit'), 'yes' => 'yes', 'no' => 'no'], $cropMode, 'style="width:140px;"');
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_focus_h') . '</div>';
        $html .= form_dropdown('jcogs_img_pro_field[' . $fieldId . '][crop_focus_h]', ['' => lang('jcogs_img_pro_field_editor_option_inherit'), 'left' => 'left', 'center' => 'center', 'right' => 'right'], $cropFocusH, 'style="width:140px;"');
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_focus_v') . '</div>';
        $html .= form_dropdown('jcogs_img_pro_field[' . $fieldId . '][crop_focus_v]', ['' => lang('jcogs_img_pro_field_editor_option_inherit'), 'top' => 'top', 'center' => 'center', 'bottom' => 'bottom'], $cropFocusV, 'style="width:140px;"');
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_offset_x') . '</div>';
        $html .= form_input(['name' => 'jcogs_img_pro_field[' . $fieldId . '][crop_offset_x]', 'value' => $cropOffsetX, 'placeholder' => '0% / 10px', 'style' => 'width:120px;']);
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_crop_width') . '</div>';
        $html .= form_input(['name' => 'jcogs_img_pro_field[' . $fieldId . '][width]', 'value' => $width, 'placeholder' => '50% / 300px', 'style' => 'width:120px;']);
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_offset_y') . '</div>';
        $html .= form_input(['name' => 'jcogs_img_pro_field[' . $fieldId . '][crop_offset_y]', 'value' => $cropOffsetY, 'placeholder' => '0% / 10px', 'style' => 'width:120px;']);
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_crop_height') . '</div>';
        $html .= form_input(['name' => 'jcogs_img_pro_field[' . $fieldId . '][height]', 'value' => $height, 'placeholder' => '50% / 300px', 'style' => 'width:120px;']);
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_aspect_ratio') . '</div>';
        $html .= '<input type="text" class="input jcogs-img-pro-field-aspect-ratio-manual"'
               . ' value="' . htmlspecialchars($aspectRatioIsInherit ? '' : $aspectRatioEffective, ENT_QUOTES, 'UTF-8') . '"'
               . ' placeholder="16_9"'
               . ' style="width:120px; font-family: ui-monospace, Menlo, Monaco, monospace; font-size: 12px;"'
               . '>';
        $html .= '</div>';

        $html .= '<div>';
        $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_smart_scaling') . '</div>';
        $html .= form_dropdown('jcogs_img_pro_field[' . $fieldId . '][crop_smart_scaling]', ['' => lang('jcogs_img_pro_field_editor_option_inherit'), 'yes' => 'yes', 'no' => 'no'], $cropSmartScaling, 'style="width:160px;"');
        $html .= '</div>';

        $html .= '</div></div>';
        return $html;
    }
}
