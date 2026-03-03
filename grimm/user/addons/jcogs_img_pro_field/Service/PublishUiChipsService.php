<?php

/**
 * JCOGS Image Pro Field - PublishUiChipsService
 *==============================================
 * Builds summary chips that describe publish settings at a glance.
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

class PublishUiChipsService
{
    /**
     * Build summary chips to make capabilities/state obvious without opening the panel.
     *
     * Expected keys in $ctx:
     * - enable_preset (bool)
     * - preset_id (string|int)
     * - preset_options (array<string, string>)
     * - default_preset_id (int)
     * - enable_art_direction (bool)
     * - ad_rows (array)
     * - usage_payload (array)
     * - enable_crop (bool)
     * - has_any_override (bool)
     * - crop_rect_width, crop_rect_height, crop_offset_x, crop_offset_y, width, height, crop (string|int|null)
     * - aspect_ratio_effective (string)
     * - aspect_ratio_choices_count (int)
     * - enable_focal (bool)
     * - focal_x, focal_y (string|int|null)
     *
     * @param array<string, mixed> $ctx
     * @return array<int, string>
     */
    public function buildChips(array $ctx): array
    {
        $chips = [];

        $enablePreset = (bool) ($ctx['enable_preset'] ?? false);
        $presetId = (string) ($ctx['preset_id'] ?? '');
        /** @var array<string, string> */
        $presetOptions = is_array($ctx['preset_options'] ?? null) ? (array) $ctx['preset_options'] : [];
        $defaultPresetId = (int) ($ctx['default_preset_id'] ?? 0);

        if ($enablePreset) {
            $presetLabel = '';
            if (trim($presetId) !== '' && isset($presetOptions[(string) $presetId])) {
                $presetLabel = (string) $presetOptions[(string) $presetId];
            } elseif ($defaultPresetId > 0 && isset($presetOptions[(string) $defaultPresetId])) {
                $presetLabel = (string) $presetOptions[(string) $defaultPresetId];
            }
            if ($presetLabel === '') {
                $presetLabel = lang('jcogs_img_pro_field_editor_none');
            }
            $chips[] = sprintf(lang('jcogs_img_pro_field_editor_chip_preset'), $presetLabel);
        }

        $enableArtDirection = (bool) ($ctx['enable_art_direction'] ?? false);
        $adRows = is_array($ctx['ad_rows'] ?? null) ? (array) $ctx['ad_rows'] : [];
        $usagePayload = is_array($ctx['usage_payload'] ?? null) ? (array) $ctx['usage_payload'] : [];

        if ($enableArtDirection) {
            // In art-direction mode, presets are defined per breakpoint row.
            $chips[] = lang('jcogs_img_pro_field_editor_chip_preset_per_breakpoint');

            $altCount = count($adRows);
            $selectedCount = 0;
            if (isset($usagePayload['art_direction']) && is_array($usagePayload['art_direction'])
                && isset($usagePayload['art_direction']['files']) && is_array($usagePayload['art_direction']['files'])
            ) {
                foreach ($usagePayload['art_direction']['files'] as $v) {
                    $n = is_numeric($v) ? (int) $v : 0;
                    if ($n > 0) {
                        $selectedCount++;
                    }
                }
            }

            if ($altCount > 0) {
                $chips[] = sprintf(lang('jcogs_img_pro_field_editor_chip_art_direction'), $selectedCount . '/' . $altCount);
            } else {
                $chips[] = sprintf(lang('jcogs_img_pro_field_editor_chip_art_direction'), '0');
            }
        }

        $enableCrop = (bool) ($ctx['enable_crop'] ?? false);
        $hasAnyOverride = (bool) ($ctx['has_any_override'] ?? false);

        $cropRectWidth = (string) ($ctx['crop_rect_width'] ?? '');
        $cropRectHeight = (string) ($ctx['crop_rect_height'] ?? '');
        $cropOffsetX = (string) ($ctx['crop_offset_x'] ?? '');
        $cropOffsetY = (string) ($ctx['crop_offset_y'] ?? '');
        $width = (string) ($ctx['width'] ?? '');
        $height = (string) ($ctx['height'] ?? '');
        $crop = (string) ($ctx['crop'] ?? '');

        $aspectRatioEffective = (string) ($ctx['aspect_ratio_effective'] ?? '');
        $aspectRatioChoicesCount = (int) ($ctx['aspect_ratio_choices_count'] ?? 0);

        if ($enableCrop) {
            $chips[] = $hasAnyOverride && (
                trim($cropRectWidth) !== ''
                || trim($cropRectHeight) !== ''
                || trim($cropOffsetX) !== ''
                || trim($cropOffsetY) !== ''
                || trim($width) !== ''
                || trim($height) !== ''
                || trim($crop) !== ''
            ) ? lang('jcogs_img_pro_field_editor_chip_crop_set') : lang('jcogs_img_pro_field_editor_chip_crop_none');

            if ($aspectRatioEffective !== '') {
                $chips[] = sprintf(lang('jcogs_img_pro_field_editor_chip_aspect'), $aspectRatioEffective);
            } else {
                // Only show “free” when aspect tools are actually present.
                if ($aspectRatioChoicesCount > 1) {
                    $chips[] = lang('jcogs_img_pro_field_editor_chip_aspect_free');
                }
            }
        }

        $enableFocal = (bool) ($ctx['enable_focal'] ?? false);
        $focalX = (string) ($ctx['focal_x'] ?? '');
        $focalY = (string) ($ctx['focal_y'] ?? '');

        if ($enableFocal) {
            $chips[] = (trim($focalX) !== '' || trim($focalY) !== '')
                ? lang('jcogs_img_pro_field_editor_chip_focal_set')
                : lang('jcogs_img_pro_field_editor_chip_focal_none');
        }

        return $chips;
    }
}
