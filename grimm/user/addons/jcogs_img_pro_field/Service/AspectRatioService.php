<?php

/**
 * JCOGS Image Pro Field - AspectRatioService
 *==========================================
 * Encapsulates aspect-ratio setting parsing/normalisation and settings MiniGrid
 * rendering.
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

final class AspectRatioService
{
    /**
     * Normalise a user-provided aspect ratio value.
     */
    public function normalizeSetting(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Allow sentinel used to explicitly override a developer default back to “inherit”.
        if ($value === '__inherit__') {
            return '__inherit__';
        }

        // Allow: 16_9, 16:9, 16/9, 1.777
        if (preg_match('/^\d+(?:\.\d+)?\s*[_:\/\-]\s*\d+(?:\.\d+)?$/', $value)) {
            $value = str_replace([':', '/', '-', ' '], ['_', '_', '_', ''], $value);
            return $value;
        }
        if (preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            return $value;
        }

        return '';
    }

    /**
     * Parse a delimited aspect ratio choice list from settings.
     */
    public function parseChoices(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", (string) $raw);
        $lines = explode("\n", $raw);
        $out = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $label = '';
            $value = $line;

            if (strpos($line, '|') !== false) {
                [$label, $value] = array_map('trim', explode('|', $line, 2));
            }

            $value = $this->normalizeSetting((string) $value);
            if ($value === '' || $value === '__inherit__') {
                continue;
            }

            if ($label === '') {
                $label = $value;
            }

            $out[$value] = $label;
        }

        return $out;
    }

    /**
     * Get allowed aspect ratio choices from field settings.
     */
    public function getChoicesFromSettings(array $settings): array
    {
        $pairs = $settings['aspect_ratio_pairs'] ?? null;
        if (is_array($pairs) && ! empty($pairs)) {
            $out = [];
            foreach ($pairs as $value => $label) {
                $value = $this->normalizeSetting(is_scalar($value) ? (string) $value : '');
                if ($value === '' || $value === '__inherit__') {
                    continue;
                }
                $label = is_scalar($label) ? trim((string) $label) : '';
                if ($label === '') {
                    $label = $value;
                }
                $out[$value] = $label;
            }
            if (! empty($out)) {
                return $out;
            }
        }

        return $this->parseChoices((string) ($settings['aspect_ratio_choices'] ?? ''));
    }

    /**
     * Normalise the posted aspect ratio mini-grid rows.
     */
    public function normalisePairsFromPosted($pairs): array
    {
        if (! is_array($pairs)) {
            return [];
        }

        if (isset($pairs['rows']) && is_array($pairs['rows'])) {
            $pairs = $pairs['rows'];
        }

        $out = [];

        foreach ($pairs as $key => $value) {
            $raw_value = '';
            $raw_label = '';

            if (is_array($value) && (isset($value['value']) || isset($value['label']))) {
                $raw_value = isset($value['value']) ? (string) $value['value'] : '';
                $raw_label = isset($value['label']) ? (string) $value['label'] : '';
            } elseif (is_scalar($key)) {
                $raw_value = (string) $key;
                $raw_label = is_scalar($value) ? (string) $value : '';
            }

            $raw_value = $this->normalizeSetting($raw_value);
            if ($raw_value === '' || $raw_value === '__inherit__') {
                continue;
            }

            $raw_label = trim($raw_label);
            if ($raw_label === '') {
                $raw_label = $raw_value;
            }

            $out[$raw_value] = $raw_label;
        }

        return $out;
    }

    /**
     * Render the aspect ratio mini-grid used in field settings.
     *
     * @return mixed MiniGrid instance
     */
    public function buildMiniGrid(array $data): mixed
    {
        $grid = ee('CP/MiniGridInput', [
            'field_name' => 'aspect_ratio_pairs',
        ]);
        $grid->loadAssets();
        $grid->setColumns([
            lang('jcogs_img_pro_field_minigrid_aspect_col_value'),
            lang('jcogs_img_pro_field_minigrid_aspect_col_label'),
        ]);
        $grid->setNoResultsText(lang('jcogs_img_pro_field_minigrid_aspect_no_results'), lang('jcogs_img_pro_field_minigrid_add_new'));
        $grid->setBlankRow([
            ['html' => form_input('value', '')],
            ['html' => form_input('label', '')],
        ]);

        $pairs = [];
        if (isset($data['aspect_ratio_pairs']) && is_array($data['aspect_ratio_pairs'])) {
            $pairs = $data['aspect_ratio_pairs'];
        } elseif (! empty($data['aspect_ratio_choices'])) {
            $pairs = $this->parseChoices((string) $data['aspect_ratio_choices']);
        }

        $rows = [];
        $i = 1;
        if (isset($pairs['rows']) && is_array($pairs['rows'])) {
            $pairs = $pairs['rows'];
        }

        foreach ($pairs as $value => $label) {
            if (is_array($label) && (isset($label['value']) || isset($label['label']))) {
                $value = isset($label['value']) ? (string) $label['value'] : '';
                $label = isset($label['label']) ? (string) $label['label'] : '';
            }

            $value = $this->normalizeSetting(is_scalar($value) ? (string) $value : '');
            if ($value === '' || $value === '__inherit__') {
                continue;
            }
            $label = is_scalar($label) ? trim((string) $label) : '';
            if ($label === '') {
                $label = $value;
            }

            $rows[] = [
                'attrs' => ['row_id' => $i],
                'columns' => [
                    ['html' => form_input('value', $value)],
                    ['html' => form_input('label', $label)],
                ],
            ];
            $i++;
        }

        $grid->setData($rows);
        return $grid;
    }
}
