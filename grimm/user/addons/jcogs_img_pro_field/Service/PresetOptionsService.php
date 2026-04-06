<?php

/**
 * JCOGS Image Pro Field - PresetOptionsService
 *============================================
 * Encapsulates Image Pro preset fetching and building option arrays used for:
 * - publish UI preset selector
 * - template-facing preset option lists
 *
 * Extracted from the fieldtype class to reduce size and improve cohesion.
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

final class PresetOptionsService
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $templateOptionsCache = [];

    /**
     * Render <option> tags for a select input.
     */
    public function renderSelectOptions(array $choices, string $selectedValue): string
    {
        $html = '';
        foreach ($choices as $value => $label) {
            $value = is_scalar($value) ? (string) $value : '';
            $label = is_scalar($label) ? (string) $label : '';
            $sel = ((string) $value === (string) $selectedValue) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                . '</option>';
        }
        return $html;
    }

    /**
     * Build preset options for the publish UI.
     */
    public function getEditorPresetOptions(array $settings, int $siteId, string $selectedPresetId): array
    {
        $restrict = (($settings['preset_restrict'] ?? 'n') === 'y');
        $allowNone = (($settings['preset_allow_none'] ?? 'y') === 'y');

        $allowed = $settings['preset_allowed_ids'] ?? [];
        if (! is_array($allowed)) {
            $allowed = [];
        }

        $allowed = array_values(array_unique(array_filter(array_map(static function ($v) {
            $v = is_scalar($v) ? (string) $v : '';
            $v = trim($v);
            return (is_numeric($v) && (int) $v > 0) ? (string) ((int) $v) : '';
        }, $allowed))));

        $options = [];
        if ($allowNone) {
            $options[''] = lang('jcogs_img_pro_field_none_option');
        }

        $presets = $this->fetchImgProPresets($siteId);
        foreach ($presets as $preset) {
            $id = isset($preset['id']) ? (int) $preset['id'] : 0;
            $name = isset($preset['name']) ? (string) $preset['name'] : '';
            if ($id <= 0 || $name === '') {
                continue;
            }

            $idStr = (string) $id;
            if ($restrict && ! in_array($idStr, $allowed, true)) {
                continue;
            }

            $options[$idStr] = $name . ' (#' . $id . ')';
        }

        if ($selectedPresetId !== '' && ! isset($options[$selectedPresetId])) {
            $id = is_numeric($selectedPresetId) ? (int) $selectedPresetId : 0;
            if ($id > 0) {
                $options[$selectedPresetId] = sprintf(lang('jcogs_img_pro_field_missing_preset'), $id);
            }
        }

        return $options;
    }

    /**
     * Build preset options for template usage.
     */
    public function getPresetOptions(int $siteId, string $selectedPresetId): array
    {
        $cacheKey = (string) $siteId;

        if (! array_key_exists($cacheKey, $this->templateOptionsCache)) {
            $options = ['' => lang('jcogs_img_pro_field_none_option')];
            $presets = $this->fetchImgProPresets($siteId);

            foreach ($presets as $preset) {
                $id = isset($preset['id']) ? (int) $preset['id'] : 0;
                $name = isset($preset['name']) ? (string) $preset['name'] : '';
                if ($id > 0 && $name !== '') {
                    $options[(string) $id] = $name . ' (#' . $id . ')';
                }
            }

            $this->templateOptionsCache[$cacheKey] = $options;
        }

        $options = $this->templateOptionsCache[$cacheKey];

        if ($selectedPresetId !== '' && ! isset($options[$selectedPresetId])) {
            $id = is_numeric($selectedPresetId) ? (int) $selectedPresetId : 0;
            if ($id > 0) {
                $options[$selectedPresetId] = sprintf(lang('jcogs_img_pro_field_missing_preset'), $id);
            }
        }

        return $options;
    }

    /**
     * Fetch Image Pro presets for the given site.
     */
    public function fetchImgProPresets(int $siteId): array
    {
        if (!DependencyService::isImageProCompatible()) {
            return [];
        }

        $presets = $this->fetchImgProPresetsViaService();
        if (! empty($presets)) {
            return $presets;
        }

        return $this->fetchImgProPresetsViaDb($siteId);
    }

    /**
     * Fetch presets via the Image Pro service layer (preferred).
     */
    private function fetchImgProPresetsViaService(): array
    {
        $serviceCacheClass = '\\JCOGSDesign\\JCOGSImagePro\\Service\\ServiceCache';
        if (! class_exists($serviceCacheClass)) {
            return [];
        }

        if (! method_exists($serviceCacheClass, 'preset_service')) {
            return [];
        }

        try {
            $service = $serviceCacheClass::preset_service();
            if (! $service || ! method_exists($service, 'getAllPresets')) {
                return [];
            }
            $result = $service->getAllPresets();
            return is_array($result) ? $result : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Fetch presets via direct DB query (fallback).
     */
    private function fetchImgProPresetsViaDb(int $siteId): array
    {
        return ServiceCache::img_pro_preset_repo()->fetchPresetsBySite($siteId);
    }
}
