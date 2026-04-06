<?php

/**
 * JCOGS Image Pro Field - TagRenderService
 *=========================================
 * Renders fieldtype template tags and variable pairs for output.
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
 * Render template tags and variable pairs for the fieldtype.
 */
class TagRenderService
{
    /**
     * Main template replacement entry-point.
     *
     * Mirrors the fieldtype replace_tag() behaviour but receives callbacks so the
     * fieldtype can keep private helpers private.
     *
     * @param mixed       $data
     * @param mixed       $params
     * @param string|bool $tagdata
     * @param array       $cb
     */
    public function replace_tag($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $this->resetDbBuilder();
        $params = is_array($params) ? $params : [];

        // NOTE: ExpressionEngine fieldtype variable pairs are reliably supported on the base field
        // tag ({field}...{/field}), but {field:modifier}...{/field:modifier} parsing is inconsistent.
        // Provide tag-pair access to art direction via:
        // {field pair="art_direction"}...{/field}
        if ($tagdata !== false) {
            $pair = '';
            if (array_key_exists('pair', $params)) {
                $pair = strtolower(trim((string) $params['pair']));
            } elseif (array_key_exists('mode', $params)) {
                $pair = strtolower(trim((string) $params['mode']));
            }

            if ($pair === 'art_direction' || $pair === 'art-direction') {
                return $this->replace_art_direction($data, $params, $tagdata, $cb);
            }

            // Default variable-pair behaviour: expose a single-row context so templates can
            // inspect derived output and payload metadata.
            $ctx = $this->build_ctx($cb, $data);
            $file_id = (int) ($ctx['file_id'] ?? 0);
            if ($file_id <= 0 || ! isset(ee()->TMPL)) {
                return '';
            }

            $usage_payload = is_array($ctx['usage_payload'] ?? null) ? (array) $ctx['usage_payload'] : [];

            $row = $ctx;
            $row['url'] = '';
            $row['img'] = '';

            try {
                $renderer = ee('jcogs_img_pro_field:ImageProRenderer');
                $row['url'] = $renderer->renderUrl(
                    $file_id,
                    $usage_payload,
                    $params,
                    $this->default_renderer_params($cb)
                );

                $row['img'] = $renderer->renderImgTag(
                    $file_id,
                    $usage_payload,
                    $params,
                    $this->default_renderer_params($cb)
                );
            } catch (\Throwable $e) {
                // Ignore.
            } finally {
                $this->resetDbBuilder();
            }

            return ee()->TMPL->parse_variables((string) $tagdata, [$row]);
        }

        return $this->replace_url($data, $params, $tagdata, $cb);
    }

    public function replace_src($data, $params = [], $tagdata = false, array $cb = []): string
    {
        return $this->replace_url($data, $params, $tagdata, $cb);
    }

    public function replace_srcset($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['srcset'] ?? '');
    }

    public function replace_sizes($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['sizes'] ?? '');
    }

    public function replace_file_id($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        $file_id = (int) ($ctx['file_id'] ?? 0);
        return $file_id > 0 ? (string) $file_id : '';
    }

    public function replace_original_url($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['original_url'] ?? '');
    }

    public function replace_preset_id($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        $pid = (int) ($ctx['preset_id'] ?? 0);
        return $pid > 0 ? (string) $pid : '';
    }

    public function replace_preset($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['preset'] ?? '');
    }

    public function replace_aspect_ratio($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['aspect_ratio'] ?? '');
    }

    public function replace_aspect_ratio_raw($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['aspect_ratio_raw'] ?? '');
    }

    public function replace_focal_x($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['focal_x'] ?? '');
    }

    public function replace_focal_x_pct($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['focal_x_pct'] ?? '');
    }

    public function replace_focal_y($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['focal_y'] ?? '');
    }

    public function replace_focal_y_pct($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['focal_y_pct'] ?? '');
    }

    public function replace_alt($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['alt'] ?? '');
    }

    public function replace_decorative($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['decorative'] ?? '');
    }

    public function replace_object_position($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['object_position'] ?? '');
    }

    public function replace_crop_rect_left($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['crop_rect_left'] ?? '');
    }

    public function replace_crop_rect_top($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['crop_rect_top'] ?? '');
    }

    public function replace_crop_rect_width($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['crop_rect_width'] ?? '');
    }

    public function replace_crop_rect_height($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['crop_rect_height'] ?? '');
    }

    public function replace_width($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['width'] ?? '');
    }

    public function replace_height($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['height'] ?? '');
    }

    public function replace_crop_offset_x($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['crop_offset_x'] ?? '');
    }

    public function replace_crop_offset_y($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['crop_offset_y'] ?? '');
    }

    public function replace_crop($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $ctx = $this->build_ctx($cb, $data);
        return (string) ($ctx['crop'] ?? '');
    }

    public function replace_url($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $this->resetDbBuilder();
        $params = is_array($params) ? $params : [];

        $ctx = $this->build_ctx($cb, $data);
        $file_id = (int) ($ctx['file_id'] ?? 0);
        if ($file_id <= 0) {
            return '';
        }

        $usage_payload = is_array($ctx['usage_payload'] ?? null) ? (array) $ctx['usage_payload'] : [];

        try {
            $renderer = ee('jcogs_img_pro_field:ImageProRenderer');
            return (string) $renderer->renderUrl(
                $file_id,
                $usage_payload,
                $params,
                $this->default_renderer_params($cb)
            );
        } catch (\Throwable $e) {
            // Fall through.
        } finally {
            $this->resetDbBuilder();
        }

        return (string) ($ctx['original_url'] ?? '');
    }

    public function replace_img($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $this->resetDbBuilder();
        $params = is_array($params) ? $params : [];

        $ctx = $this->build_ctx($cb, $data);
        $file_id = (int) ($ctx['file_id'] ?? 0);
        if ($file_id <= 0) {
            return '';
        }

        $usage_payload = is_array($ctx['usage_payload'] ?? null) ? (array) $ctx['usage_payload'] : [];

        // Art direction: render a <picture> when enabled and alternate images exist.
        $settings = $this->settings($cb);
        $ad_rows = $this->ad_rows($cb);
        $ad_enabled = (($settings['enable_art_direction'] ?? 'n') === 'y') && ! empty($ad_rows);
        if ($ad_enabled) {
            try {
                $fn = $cb['render_ad_picture'] ?? null;
                if (is_callable($fn)) {
                    $picture = (string) $fn((int) $file_id, $usage_payload, $params);
                    if ($picture !== '') {
                        return $picture;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore.
            } finally {
                $this->resetDbBuilder();
            }
        }

        try {
            $renderer = ee('jcogs_img_pro_field:ImageProRenderer');

            $payload = $usage_payload;
            try {
                $fn = $cb['apply_default_ad_preset'] ?? null;
                if (is_callable($fn)) {
                    $maybe = $fn((int) $file_id, $usage_payload, $params);
                    if (is_array($maybe)) {
                        $payload = $maybe;
                    }
                }
            } catch (\Throwable $e) {
                $payload = $usage_payload;
            }

            return (string) $renderer->renderImgTag(
                $file_id,
                $payload,
                $params,
                $this->default_renderer_params($cb)
            );
        } catch (\Throwable $e) {
            // Fall back to a simple IMG tag.
        } finally {
            $this->resetDbBuilder();
        }

        $url = (string) ($ctx['original_url'] ?? '');
        if ($url === '') {
            return '';
        }

        return '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
    }

    public function replace_art_direction($data, $params = [], $tagdata = false, array $cb = []): string
    {
        $this->resetDbBuilder();
        if ($tagdata === false) {
            return '';
        }

        $params = is_array($params) ? $params : [];

        $ctx = $this->build_ctx($cb, $data);
        $file_id = (int) ($ctx['file_id'] ?? 0);
        if ($file_id <= 0) {
            return '';
        }

        $rows = $this->ad_rows($cb);
        $settings = $this->settings($cb);
        if ((($settings['enable_art_direction'] ?? 'n') !== 'y') || empty($rows)) {
            return '';
        }

        $usage_payload = is_array($ctx['usage_payload'] ?? null) ? (array) $ctx['usage_payload'] : [];
        $files = [];
        if (isset($usage_payload['art_direction']) && is_array($usage_payload['art_direction'])
            && isset($usage_payload['art_direction']['files']) && is_array($usage_payload['art_direction']['files'])
        ) {
            $files = $usage_payload['art_direction']['files'];
        }

        $vars = [];
        foreach ($rows as $row) {
            $idx = (int) ($row['index'] ?? 0);
            if ($idx <= 0) {
                continue;
            }
            $row_media = (string) ($row['media'] ?? '');

            // Alternate images only; the main field image is always the fallback.
            $row_file_id = 0;
            if ($row_media !== '' && isset($files[$row_media])) {
                $row_file_id = (int) $files[$row_media];
            } else {
                // Back-compat: numeric-indexed payload.
                $row_file_id = (int) ($files[(string) $idx] ?? 0);
            }

            $row_vars = [
                'index' => (string) $idx,
                'media' => $row_media,
                // Back-compat: legacy templates may reference this key.
                'is_default' => '0',
                'preset_id' => (string) ((int) ($row['preset_id'] ?? 0)),
                'file_id' => ($row_file_id > 0) ? (string) $row_file_id : '',
                'url' => '',
                'img' => '',
            ];

            if ($row_file_id > 0) {
                try {
                    $renderer = ee('jcogs_img_pro_field:ImageProRenderer');

                    $row_payload = [];
                    $fn = $cb['build_ad_row_payload'] ?? null;
                    if (is_callable($fn)) {
                        $maybe = $fn(
                            (int) $row_file_id,
                            $usage_payload,
                            (int) ($row['preset_id'] ?? 0),
                            $params
                        );
                        if (is_array($maybe)) {
                            $row_payload = $maybe;
                        }
                    }

                    $row_vars['url'] = (string) $renderer->renderUrl(
                        $row_file_id,
                        $row_payload,
                        $params,
                        $this->default_renderer_params($cb)
                    );

                    $row_vars['img'] = (string) $renderer->renderImgTag(
                        $row_file_id,
                        $row_payload,
                        $params,
                        $this->default_renderer_params($cb)
                    );
                } catch (\Throwable $e) {
                    // Ignore.
                } finally {
                    $this->resetDbBuilder();
                }
            }

            $vars[] = $row_vars;
        }

        if (empty($vars) || ! isset(ee()->TMPL)) {
            return '';
        }

        return ee()->TMPL->parse_variables((string) $tagdata, $vars);
    }

    /**
     * Build a template context array via callbacks.
     *
     * @param array<string, callable> $cb
     * @param mixed $data
     * @return array<string, mixed>
     */
    private function build_ctx(array $cb, $data): array
    {
        $this->resetDbBuilder();
        $fn = $cb['build_ctx'] ?? null;
        if (! is_callable($fn)) {
            return [];
        }

        try {
            $ctx = $fn($data);
            return is_array($ctx) ? $ctx : [];
        } catch (\Throwable $e) {
            return [];
        } finally {
            $this->resetDbBuilder();
        }
    }

    /**
     * Fetch field settings via callback.
     *
     * @param array<string, callable> $cb
     * @return array<string, mixed>
     */
    private function settings(array $cb): array
    {
        $this->resetDbBuilder();
        $fn = $cb['settings'] ?? null;
        if (! is_callable($fn)) {
            return [];
        }

        try {
            $settings = $fn();
            return is_array($settings) ? $settings : [];
        } catch (\Throwable $e) {
            return [];
        } finally {
            $this->resetDbBuilder();
        }
    }

    /**
     * Fetch art direction rows via callback.
     *
     * @param array<string, callable> $cb
     * @return array<int, array<string, mixed>>
     */
    private function ad_rows(array $cb): array
    {
        $this->resetDbBuilder();
        $fn = $cb['ad_rows'] ?? null;
        if (! is_callable($fn)) {
            return [];
        }

        try {
            $rows = $fn();
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        } finally {
            $this->resetDbBuilder();
        }
    }

    /**
     * Fetch default renderer params via callback.
     *
     * @param array<string, callable> $cb
     * @return array<string, mixed>
     */
    private function default_renderer_params(array $cb): array
    {
        $this->resetDbBuilder();
        $fn = $cb['default_renderer_params'] ?? null;
        if (! is_callable($fn)) {
            return [];
        }

        try {
            $params = $fn();
            return is_array($params) ? $params : [];
        } catch (\Throwable $e) {
            return [];
        } finally {
            $this->resetDbBuilder();
        }
    }

    /**
     * Best-effort reset of CI query builder state.
     */
    private function resetDbBuilder(): void
    {
        if (! isset(ee()->db) || ! is_object(ee()->db)) {
            return;
        }

        $db = ee()->db;
        if (method_exists($db, '_reset_select')) {
            $db->_reset_select();
        }
        if (method_exists($db, '_reset_write')) {
            $db->_reset_write();
        }
        if (method_exists($db, 'flush_cache')) {
            $db->flush_cache();
        }
    }
}
