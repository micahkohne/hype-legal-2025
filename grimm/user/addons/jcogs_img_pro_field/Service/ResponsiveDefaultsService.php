<?php

/**
 * JCOGS Image Pro Field - ResponsiveDefaultsService
 *=================================================
 * Encapsulates responsive-default settings parsing (srcset widths, allow scale
 * larger) and computing default renderer params.
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

final class ResponsiveDefaultsService
{
    /**
     * Normalise posted srcset widths.
     */
    public function normaliseSrcsetWidthsFromPosted($widths): array
    {
        if (! is_array($widths)) {
            return [];
        }

        if (isset($widths['rows']) && is_array($widths['rows'])) {
            $widths = $widths['rows'];
        }

        $out = [];
        foreach ($widths as $row) {
            if (is_array($row) && array_key_exists('width', $row)) {
                $raw = is_scalar($row['width']) ? trim((string) $row['width']) : '';
            } elseif (is_scalar($row)) {
                $raw = trim((string) $row);
            } else {
                $raw = '';
            }

            if ($raw === '' || ! is_numeric($raw)) {
                continue;
            }

            $w = (int) $raw;
            if ($w <= 0) {
                continue;
            }

            $out[] = (string) $w;
        }

        return array_values(array_unique($out));
    }

    /**
     * Render the srcset widths mini-grid used in field settings.
     *
     * @return mixed MiniGrid instance
     */
    public function buildSrcsetWidthsMiniGrid(array $data): mixed
    {
        $grid = ee('CP/MiniGridInput', [
            'field_name' => 'srcset_widths',
        ]);
        $grid->loadAssets();
        $grid->setColumns([
            lang('jcogs_img_pro_field_minigrid_srcset_col_width'),
        ]);
        $grid->setNoResultsText(lang('jcogs_img_pro_field_minigrid_srcset_no_results'), lang('jcogs_img_pro_field_minigrid_add_new'));
        $grid->setBlankRow([
            ['html' => form_input('width', '')],
        ]);

        $widths = $data['srcset_widths'] ?? [];
        $widths = $this->normaliseSrcsetWidthsFromPosted($widths);

        $rows = [];
        $i = 1;
        foreach ($widths as $w) {
            $rows[] = [
                'attrs' => ['row_id' => $i],
                'columns' => [
                    ['html' => form_input('width', (string) $w)],
                ],
            ];
            $i++;
        }

        $grid->setData($rows);
        return $grid;
    }

    /**
     * Build a conservative default srcset string.
     */
    public function buildDefaultSrcsetString(array $settings): string
    {
        $widths = $settings['srcset_widths'] ?? null;
        if (! is_array($widths) || empty($widths)) {
            return '';
        }

        $out = [];
        foreach ($widths as $w) {
            $w = is_scalar($w) ? trim((string) $w) : '';
            if ($w === '' || ! is_numeric($w)) {
                continue;
            }
            $i = (int) $w;
            if ($i <= 0) {
                continue;
            }
            $out[] = (string) $i;
        }

        $out = array_values(array_unique($out));
        return empty($out) ? '' : implode('|', $out);
    }

    /**
     * Build default renderer params based on field settings.
     */
    public function buildDefaultRendererParams(array $settings): array
    {
        if ((($settings['enable_responsive_defaults'] ?? 'y') === 'n')) {
            return [];
        }

        $defaults = [];

        /** @var AspectRatioService $aspect */
        $aspect = ServiceCache::aspect_ratio();

        $default_aspect_ratio = $aspect->normalizeSetting((string) ($settings['default_aspect_ratio'] ?? ''));
        if ($default_aspect_ratio === '') {
            $choices = $aspect->getChoicesFromSettings($settings);
            if (is_array($choices) && ! empty($choices)) {
                $first = array_key_first($choices);
                $default_aspect_ratio = is_string($first) ? $first : '';
            }
        }
        if ($default_aspect_ratio !== '' && $default_aspect_ratio !== '__inherit__') {
            $defaults['aspect_ratio'] = $default_aspect_ratio;
        }

        $srcset = $this->buildDefaultSrcsetString($settings);
        if ($srcset !== '') {
            $defaults['srcset'] = $srcset;
        }

        $asl = (($settings['default_allow_scale_larger'] ?? 'n') === 'y') ? 'yes' : 'no';
        $defaults['allow_scale_larger'] = $asl;

        return $defaults;
    }
}
