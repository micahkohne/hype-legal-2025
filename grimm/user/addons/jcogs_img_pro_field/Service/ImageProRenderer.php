<?php

/**
 * JCOGS Image Pro Field - ImageProRenderer
 *========================================
 * Rendering adapter for JCOGS Image Pro.
 *
 * Builds effective Image Pro parameters from field value + stored usage payload
 * + template tag params, then delegates rendering to the Image Pro pipeline.
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

declare(strict_types=1);

namespace JCOGSDesign\JcogsImgProField\Service;

/**
 * Delegates URL/img rendering to Image Pro.
 */
class ImageProRenderer
{
    /**
     * Render a processed URL for the selected file.
     */
    public function renderUrl(int $file_id, array $usage_payload = [], array $tag_params = [], array $field_defaults = []): string
    {
        return $this->render($file_id, $usage_payload, $tag_params, $field_defaults, 'url');
    }

    /**
     * Render an <img> tag (or <picture> when art-direction alternates exist).
     */
    public function renderImgTag(int $file_id, array $usage_payload = [], array $tag_params = [], array $field_defaults = []): string
    {
        return $this->render($file_id, $usage_payload, $tag_params, $field_defaults, 'img');
    }

    /**
     * Convenience wrapper: build Image Pro's crop parameter string from a stored payload.
     */
    public function buildCropParamFromPayload(array $payload): string
    {
        $crop = $this->build_crop_param_from_payload($payload);
        return is_string($crop) ? $crop : '';
    }

    /**
     * Render the requested output via Image Pro (or fall back to source output).
     *
     * @param array<string, mixed> $usage_payload
     * @param array<string, mixed> $tag_params
     * @param array<string, mixed> $field_defaults
     */
    private function render(int $file_id, array $usage_payload, array $tag_params, array $field_defaults, string $mode): string
    {
        if ($file_id <= 0) {
            return '';
        }

        $file = ee('Model')->get('File', $file_id)->with('UploadDestination')->first();
        if (! $file) {
            return '';
        }

        $abs_url = method_exists($file, 'getAbsoluteURL') ? $file->getAbsoluteURL() : null;
        if (! is_string($abs_url) || $abs_url === '') {
            return '';
        }

        // Fallback output if Image Pro isn't available.
        if (! class_exists('JCOGSDesign\\JCOGSImagePro\\Service\\ServiceCache')) {
            return ($mode === 'img')
                ? '<img src="' . htmlspecialchars($abs_url, ENT_QUOTES, 'UTF-8') . '"' . $this->build_fallback_html_attributes($usage_payload, $tag_params) . '>'
                : (string) $abs_url;
        }

        // Fallback output if Image Pro is installed but below the minimum supported version.
        if (! DependencyService::isImageProCompatible()) {
            return ($mode === 'img')
                ? '<img src="' . htmlspecialchars($abs_url, ENT_QUOTES, 'UTF-8') . '"' . $this->build_fallback_html_attributes($usage_payload, $tag_params) . '>'
                : (string) $abs_url;
        }

        $debug_effective_params = false;
        if (isset($tag_params['debug_effective_params'])) {
            $v = strtolower(trim((string) $tag_params['debug_effective_params']));
            $debug_effective_params = ($v === 'y' || $v === 'yes' || $v === '1' || $v === 'true');
        }

        $effective = $this->build_effective_img_pro_params($abs_url, $usage_payload, $tag_params, $field_defaults, $mode);

        // Extension hook point: allow companion add-ons (eg EXIF policies) to
        // adjust effective Image Pro parameters without coupling.
        //
        // Hook name is intentionally specific to avoid collisions.
        // Expected return value: modified params array (or any non-array to ignore).
        $effective = $this->apply_extension_hook_effective_params(
            $file_id,
            $effective,
            $usage_payload,
            $tag_params,
            $field_defaults,
            $mode
        );

        // Avoid leaking our debug flag into the Image Pro pipeline (or HTML passthrough attributes).
        unset($effective['debug_effective_params']);

        // Keep a copy of the pre-normalised params so we can detect and prevent
        // unintended default injection during normalisation.
        $effective_before_normalise = $effective;

        $effective = $this->normalise_img_pro_params_for_pipeline($effective);

        // If normalisation injected a missing dimension (e.g. height) while the other
        // dimension was explicitly provided, drop the injected value so Image Pro's
        // normal aspect-ratio dimension calculation can run.
        if (
            array_key_exists('width', $effective_before_normalise)
            && !array_key_exists('height', $effective_before_normalise)
            && array_key_exists('height', $effective)
        ) {
            unset($effective['height']);
        }

        if (
            array_key_exists('height', $effective_before_normalise)
            && !array_key_exists('width', $effective_before_normalise)
            && array_key_exists('width', $effective)
        ) {
            unset($effective['width']);
        }

        // allow_scale_larger only has meaning when srcset is present; if it was
        // introduced during normalisation without an accompanying srcset, drop it.
        if (
            !array_key_exists('srcset', $effective_before_normalise)
            && array_key_exists('allow_scale_larger', $effective)
            && !array_key_exists('allow_scale_larger', $effective_before_normalise)
        ) {
            unset($effective['allow_scale_larger']);
        }

        if ($debug_effective_params) {
            try {
                $u = ee('jcogs_img_pro:Utilities');
                if ($u && method_exists($u, 'debug_message')) {
                    $keys = [
                        'src',
                        'preset',
                        'preset_id',
                        'width',
                        'height',
                        'max_width',
                        'min_width',
                        'max_height',
                        'min_height',
                        'max',
                        'min',
                        'aspect_ratio',
                        'fit',
                        'crop',
                        'crop_rect',
                        'crop_mode',
                        'crop_offset_x',
                        'crop_offset_y',
                        'crop_smart_scaling',
                        'filter',
                        'lazy',
                        'srcset',
                        'sizes',
                        'allow_scale_larger',
                        'save_as',
                        'quality',
                        'connection',
                        'cache_dir',
                    ];

                    $pick = static function (array $arr) use ($keys): array {
                        $out = [];
                        foreach ($keys as $k) {
                            if (array_key_exists($k, $arr)) {
                                $out[$k] = $arr[$k];
                            }
                        }
                        return $out;
                    };

                    $diff_keys = static function (array $before, array $after): array {
                        $added = array_values(array_diff(array_keys($after), array_keys($before)));
                        $removed = array_values(array_diff(array_keys($before), array_keys($after)));
                        sort($added);
                        sort($removed);
                        return ['added' => $added, 'removed' => $removed];
                    };

                    $pre_subset = $pick($effective_before_normalise);
                    $post_subset = $pick($effective);

                    $u->debug_message(
                        'jcogs_img_pro_field: effective Image Pro params',
                        [
                            'mode' => $mode,
                            'file_id' => $file_id,
                            'tag_params_subset' => $pick($tag_params),
                            'usage_payload_subset' => $pick($usage_payload),
                            'field_defaults_subset' => $pick($field_defaults),
                            'effective_pre_normalise_subset' => $pre_subset,
                            'effective_post_normalise_subset' => $post_subset,
                            'normalise_subset_diff' => $diff_keys($pre_subset, $post_subset),
                        ],
                        false,
                        'standard'
                    );
                }
            } catch (\Throwable $e) {
                // Silent fallback.
            }
        }

        try {
            $service_cache = 'JCOGSDesign\\JCOGSImagePro\\Service\\ServiceCache';
            $pipeline = $service_cache::pipeline($effective['connection'] ?? null);
            $result = $pipeline->process($effective, null);

            if (is_array($result) && !empty($result['success']) && isset($result['output']) && is_string($result['output'])) {
                return $result['output'];
            }
        } catch (\Throwable $e) {
            // Silent fallback.
        }

        return ($mode === 'img')
            ? '<img src="' . htmlspecialchars($abs_url, ENT_QUOTES, 'UTF-8') . '"' . $this->build_fallback_html_attributes($usage_payload, $tag_params) . '>'
            : (string) $abs_url;
    }

    /**
     * Allow other add-ons to modify the effective Image Pro params.
     */
    private function apply_extension_hook_effective_params(
        int $file_id,
        array $effective,
        array $usage_payload,
        array $tag_params,
        array $field_defaults,
        string $mode
    ): array {
        try {
            if (!isset(ee()->extensions)) {
                return $effective;
            }

            // active_hook() exists on EE's Extensions service; check defensively.
            if (method_exists(ee()->extensions, 'active_hook')) {
                if (!ee()->extensions->active_hook('jcogs_img_pro_field_image_pro_params')) {
                    return $effective;
                }
            }

            $modified = ee()->extensions->call(
                'jcogs_img_pro_field_image_pro_params',
                $file_id,
                $effective,
                $usage_payload,
                $tag_params,
                $field_defaults,
                $mode
            );

            // Convention: if an extension returns an array, treat it as the updated params.
            return is_array($modified) ? $modified : $effective;
        } catch (\Throwable $e) {
            return $effective;
        }
    }

    /**
     * Build Image Pro parameters from stored payload, field defaults, and tag params.
     *
     * @param array<string, mixed> $usage_payload
     * @param array<string, mixed> $tag_params
     * @param array<string, mixed> $field_defaults
     * @return array<string, mixed>
     */
    private function build_effective_img_pro_params(string $abs_url, array $usage_payload, array $tag_params, array $field_defaults, string $mode): array
    {
        $effective = [];

        // 1) Start with preset parameters (stored), unless template explicitly sets a preset.
        $preset_params = [];
        $template_requests_preset = isset($tag_params['preset']) && trim((string)$tag_params['preset']) !== '';

        if (! $template_requests_preset) {
            $preset_id = isset($usage_payload['preset_id']) ? (int) $usage_payload['preset_id'] : 0;
            if ($preset_id > 0) {
                $preset_params = $this->get_preset_params_by_id($preset_id);
            }
        }

        if (!empty($preset_params)) {
            $effective = $preset_params;
        }

        // 2) Apply tag params (template overrides win).
        if (!empty($tag_params)) {
            $effective = array_merge($effective, $tag_params);
        }

        // 3) Force src from the field's file.
        $effective['src'] = $abs_url;

        // 3b) A11y: stored alt/decorative.
        // Treat these like regular HTML attributes so Image Pro can consolidate with anything
        // coming from presets / attributes packs / template params.
        $decorative = isset($usage_payload['decorative']) ? trim((string) $usage_payload['decorative']) : '';
        $decorative_lc = strtolower($decorative);
        $is_decorative = ($decorative_lc === 'y' || $decorative_lc === 'yes' || $decorative_lc === '1' || $decorative_lc === 'true');

        if (! array_key_exists('alt', $tag_params)) {
            if ($is_decorative) {
                $effective['alt'] = '';
            } elseif (isset($usage_payload['alt']) && is_string($usage_payload['alt'])) {
                $alt = trim($usage_payload['alt']);
                if ($alt !== '') {
                    $effective['alt'] = $alt;
                }
            }
        }

        // 4) Apply stored crop intent if the template didn't specify crop.
        if (!array_key_exists('crop', $tag_params)) {
            $crop = $this->build_crop_param_from_payload($usage_payload);
            if (is_string($crop) && $crop !== '') {
                // Only apply focal-derived crop if preset didn't already define crop.
                if (isset($usage_payload['crop']) || isset($usage_payload['crop_mode']) || isset($usage_payload['crop_offset_x']) || isset($usage_payload['crop_offset_y'])) {
                    $effective['crop'] = $crop;
                } elseif (!isset($effective['crop'])) {
                    $effective['crop'] = $crop;
                }
            }
        }

        // 4a) If a structured crop rectangle exists (from the field UI), pass it through
        // so Image Pro can reproduce the editor's intended zoom+pan crop window.
        if (!array_key_exists('crop_rect', $tag_params) && isset($usage_payload['crop_rect']) && is_array($usage_payload['crop_rect'])) {
            $left = isset($usage_payload['crop_rect']['left']) ? (float) $usage_payload['crop_rect']['left'] : null;
            $top = isset($usage_payload['crop_rect']['top']) ? (float) $usage_payload['crop_rect']['top'] : null;
            $width = isset($usage_payload['crop_rect']['width']) ? (float) $usage_payload['crop_rect']['width'] : null;
            $height = isset($usage_payload['crop_rect']['height']) ? (float) $usage_payload['crop_rect']['height'] : null;

            if ($left !== null && $top !== null && $width !== null && $height !== null) {
                // Keep a stable, simple format (no pipes) so security validation stays relaxed.
                $effective['crop_rect'] = implode(',', [
                    rtrim(rtrim(number_format($left, 1, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($top, 1, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($width, 1, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format($height, 1, '.', ''), '0'), '.'),
                ]);
            }
        }

        // 4b) Apply stored sizing overrides (needed for crop target box size) unless the template provided them.
        if (!array_key_exists('width', $tag_params) && isset($usage_payload['width']) && is_string($usage_payload['width']) && trim($usage_payload['width']) !== '') {
            $effective['width'] = trim($usage_payload['width']);
        }
        if (!array_key_exists('height', $tag_params) && isset($usage_payload['height']) && is_string($usage_payload['height']) && trim($usage_payload['height']) !== '') {
            $effective['height'] = trim($usage_payload['height']);
        }
        if (!array_key_exists('aspect_ratio', $tag_params) && isset($usage_payload['aspect_ratio']) && is_string($usage_payload['aspect_ratio'])) {
            $ar = trim($usage_payload['aspect_ratio']);
            if ($ar !== '' && $ar !== '__inherit__') {
                $effective['aspect_ratio'] = $ar;
            }
        }

        // 4c) Apply field-level aspect ratio default (when present), unless template or stored
        // override already set it.
        if (!array_key_exists('aspect_ratio', $tag_params) && !array_key_exists('aspect_ratio', $effective)) {
            if (isset($field_defaults['aspect_ratio']) && is_string($field_defaults['aspect_ratio'])) {
                $ar = trim($field_defaults['aspect_ratio']);
                if ($ar !== '' && $ar !== '__inherit__') {
                    $effective['aspect_ratio'] = $ar;
                }
            }
        }

        // 5) Tag context for Image Pro output routing.
        $effective['_tag_type'] = 'single';
        $effective['_called_by'] = 'Image_Tag';

        if ($mode === 'url') {
            $effective['url_only'] = 'yes';

            // URL-only renders are typically used for src/srcset values (e.g. <source srcset="...")
            // and should not trigger lazy-loading placeholder generation.
            // Keep lazy loading concerns confined to actual <img> output.
            $effective['lazy'] = 'no';
            unset($effective['debug_lazy']);
        }

        // 6) Responsive defaults (only for IMG output).
        // Avoid applying defaults for url_only renders to prevent unexpected variant generation.
        if ($mode === 'img') {
            // If preset already set srcset and the template didn't override, keep it.
            if (!isset($effective['srcset']) || trim((string) $effective['srcset']) === '') {
                $srcset = '';

                if (!array_key_exists('srcset', $tag_params)) {
                    if (isset($usage_payload['srcset']) && is_string($usage_payload['srcset'])) {
                        $srcset = trim($usage_payload['srcset']);
                    }
                    if ($srcset === '' && isset($field_defaults['srcset']) && is_string($field_defaults['srcset'])) {
                        $srcset = trim($field_defaults['srcset']);
                    }
                }

                if ($srcset !== '') {
                    $effective['srcset'] = $srcset;
                }
            }

            // Optional sizes default/pass-through.
            if (isset($effective['srcset']) && trim((string) $effective['srcset']) !== '') {
                if (!isset($effective['sizes']) || trim((string) $effective['sizes']) === '') {
                    $sizes = '';
                    if (!array_key_exists('sizes', $tag_params)) {
                        if (isset($usage_payload['sizes']) && is_string($usage_payload['sizes'])) {
                            $sizes = trim($usage_payload['sizes']);
                        }
                        if ($sizes === '' && isset($field_defaults['sizes']) && is_string($field_defaults['sizes'])) {
                            $sizes = trim($field_defaults['sizes']);
                        }
                    }
                    if ($sizes !== '') {
                        $effective['sizes'] = $sizes;
                    }
                }

                // allow_scale_larger: only meaningful when srcset is used.
                if (!isset($effective['allow_scale_larger']) || trim((string) $effective['allow_scale_larger']) === '') {
                    $asl = '';
                    if (!array_key_exists('allow_scale_larger', $tag_params)) {
                        if (isset($usage_payload['allow_scale_larger']) && is_string($usage_payload['allow_scale_larger'])) {
                            $asl = trim($usage_payload['allow_scale_larger']);
                        }
                        if ($asl === '' && isset($field_defaults['allow_scale_larger']) && is_string($field_defaults['allow_scale_larger'])) {
                            $asl = trim($field_defaults['allow_scale_larger']);
                        }
                    }
                    if ($asl !== '') {
                        $effective['allow_scale_larger'] = $asl;
                    }
                }
            }
        }

        return $effective;
    }

    /**
     * Bring field rendering in line with Image Pro's normal tag pipeline:
     * - resolve parameter aliases
     * - sanitise via SecurityValidationService
     * - apply preset expansion (when template sets preset=...)
     */
    private function normalise_img_pro_params_for_pipeline(array $tagparams): array
    {
        $service_cache = 'JCOGSDesign\\JCOGSImagePro\\Service\\ServiceCache';

        // Resolve aliases first (e.g. format -> save_type).
        $registry = 'JCOGSDesign\\JCOGSImagePro\\Service\\ParameterRegistry';
        if (class_exists($registry) && method_exists($registry, 'resolveParameterAlias')) {
            $resolved = [];
            foreach ($tagparams as $param_name => $param_value) {
                $canonical = $registry::resolveParameterAlias((string) $param_name);
                $resolved[$canonical] = $param_value;
            }
            $tagparams = $resolved;
        }

        // Apply security sanitisation (keeps safe HTML attributes, strips malicious values).
        try {
            if (class_exists($service_cache) && method_exists($service_cache, 'security')) {
                $security_service = $service_cache::security();
                if ($security_service && method_exists($security_service, 'validateAndSanitizeParameters')) {
                    $tagparams = $security_service->validateAndSanitizeParameters($tagparams);
                }
            }
        } catch (\Throwable $e) {
            // Silent fallback.
        }

        // Apply preset expansion (when preset=... is in template params).
        try {
            if (class_exists($service_cache) && method_exists($service_cache, 'preset_resolver')) {
                $preset_resolver = $service_cache::preset_resolver();
                if ($preset_resolver && method_exists($preset_resolver, 'resolveParameters')) {
                    $tagparams = $preset_resolver->resolveParameters($tagparams);
                }
            }
        } catch (\Throwable $e) {
            // Silent fallback.
        }

        return $tagparams;
    }

    /**
     * Build fallback HTML attributes when Image Pro is unavailable.
     *
     * @param array<string, mixed> $usage_payload
     * @param array<string, mixed> $tag_params
     */
    private function build_fallback_html_attributes(array $usage_payload, array $tag_params): string
    {
        // When Image Pro isn't installed, we still allow a small, safe subset of passthrough
        // attributes to keep templates portable.
        $attrs = [];

        // Stored alt/decorative.
        $decorative = isset($usage_payload['decorative']) ? trim((string) $usage_payload['decorative']) : '';
        $decorative_lc = strtolower($decorative);
        $is_decorative = ($decorative_lc === 'y' || $decorative_lc === 'yes' || $decorative_lc === '1' || $decorative_lc === 'true');

        if (! array_key_exists('alt', $tag_params)) {
            if ($is_decorative) {
                $attrs['alt'] = '';
            } elseif (isset($usage_payload['alt']) && is_string($usage_payload['alt'])) {
                $attrs['alt'] = trim($usage_payload['alt']);
            }
        }

        foreach ($tag_params as $k => $v) {
            $key = strtolower(trim((string) $k));
            if ($key === '' || $key === 'src') {
                continue;
            }

            // Allow common safe attributes + data/aria.
            $allow = (
                $key === 'alt'
                || $key === 'class'
                || $key === 'style'
                || $key === 'id'
                || $key === 'title'
                || $key === 'role'
                || $key === 'loading'
                || $key === 'decoding'
                || $key === 'fetchpriority'
                || str_starts_with($key, 'data-')
                || str_starts_with($key, 'aria-')
            );

            if (! $allow) {
                continue;
            }

            $value = is_scalar($v) ? (string) $v : '';
            $attrs[$key] = $value;
        }

        $out = '';
        foreach ($attrs as $k => $v) {
            $out .= ' ' . htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
        }
        return $out;
    }

    /**
     * Resolve Image Pro preset parameters by ID.
     *
     * @return array<string, mixed>
     */
    private function get_preset_params_by_id(int $preset_id): array
    {
        if ($preset_id <= 0) {
            return [];
        }

        try {
            $service_cache = 'JCOGSDesign\\JCOGSImagePro\\Service\\ServiceCache';
            $preset_service = $service_cache::preset_service();
            if ($preset_service && method_exists($preset_service, 'getPresetById')) {
                $preset = $preset_service->getPresetById($preset_id);
                if (is_array($preset) && isset($preset['parameters']) && is_array($preset['parameters'])) {
                    return $preset['parameters'];
                }
            }
        } catch (\Throwable $e) {
            // Ignore.
        }

        return [];
    }

    /**
     * Build an Image Pro crop string from a stored payload.
     *
     * @param array<string, mixed> $payload
     */
    private function build_crop_param_from_payload(array $payload): ?string
    {
        // Explicit crop string wins.
        if (isset($payload['crop']) && is_string($payload['crop']) && trim($payload['crop']) !== '') {
            return trim($payload['crop']);
        }

        // Structured crop rectangle (percent of source) preferred for consistent output.
        if (isset($payload['crop_rect']) && is_array($payload['crop_rect'])) {
            $left = isset($payload['crop_rect']['left']) ? (float) $payload['crop_rect']['left'] : null;
            $top = isset($payload['crop_rect']['top']) ? (float) $payload['crop_rect']['top'] : null;
            $width = isset($payload['crop_rect']['width']) ? (float) $payload['crop_rect']['width'] : null;
            $height = isset($payload['crop_rect']['height']) ? (float) $payload['crop_rect']['height'] : null;

            if ($left !== null && $top !== null && $width !== null && $height !== null) {
                $cx = max(0.0, min(100.0, $left + ($width / 2.0)));
                $cy = max(0.0, min(100.0, $top + ($height / 2.0)));

                $ox = (string) ((int) round($cx - 50.0)) . '%';
                $oy = (string) ((int) round($cy - 50.0)) . '%';

                // Image Pro default is smart scaling enabled.
                $smart = isset($payload['crop_smart_scaling']) ? trim((string) $payload['crop_smart_scaling']) : 'yes';
                $smart = strtolower($smart);
                $smart = ($smart === 'n' || $smart === 'no') ? 'n' : 'y';

                return implode('|', [
                    'y',
                    'center,center',
                    $ox . ',' . $oy,
                    $smart,
                    '50',
                ]);
            }
        }

        // Focal-only crop (when no crop_rect exists). This provides a predictable fallback that
        // aligns with the preview behaviour and supports object-fit style editorial intent.
        $fx_raw = $payload['focal_x'] ?? null;
        $fy_raw = $payload['focal_y'] ?? null;
        $fx = is_numeric($fx_raw) ? (float) $fx_raw : null;
        $fy = is_numeric($fy_raw) ? (float) $fy_raw : null;
        if ($fx !== null && $fy !== null) {
            if (is_finite($fx) && is_finite($fy) && $fx >= 0.0 && $fx <= 100.0 && $fy >= 0.0 && $fy <= 100.0) {
                $ox = (string) ((int) round($fx - 50.0)) . '%';
                $oy = (string) ((int) round($fy - 50.0)) . '%';

                // Image Pro default is smart scaling enabled.
                $smart = isset($payload['crop_smart_scaling']) ? trim((string) $payload['crop_smart_scaling']) : 'yes';
                $smart = strtolower($smart);
                $smart = ($smart === 'n' || $smart === 'no') ? 'n' : 'y';

                return implode('|', [
                    'y',
                    'center,center',
                    $ox . ',' . $oy,
                    $smart,
                    '50',
                ]);
            }
        }

        // Named components.
        $has_named = isset($payload['crop_mode'])
            || isset($payload['crop_focus_h'])
            || isset($payload['crop_focus_v'])
            || isset($payload['crop_offset_x'])
            || isset($payload['crop_offset_y'])
            || isset($payload['crop_smart_scaling']);

        if ($has_named) {
            // IMPORTANT: Image Pro's pipeline crop parser expects the legacy positional format:
            //   y|center,center|0,0|y|50
            // Not the named format (crop_mode:yes|crop_focus_h:center|...).

            $mode = isset($payload['crop_mode']) ? trim((string) $payload['crop_mode']) : 'yes';
            $mode = strtolower($mode);
            if ($mode === '' || $mode === 'y' || $mode === 'yes') {
                $mode = 'y';
            } elseif ($mode === 'n' || $mode === 'no') {
                $mode = 'n';
            } elseif ($mode === 'face_detect' || $mode === 'f') {
                $mode = 'f';
            } else {
                $mode = 'y';
            }

            if ($mode === 'n') {
                return 'n';
            }

            $focus_h = isset($payload['crop_focus_h']) ? trim((string) $payload['crop_focus_h']) : 'center';
            $focus_v = isset($payload['crop_focus_v']) ? trim((string) $payload['crop_focus_v']) : 'center';
            $focus_h = ($focus_h !== '') ? strtolower($focus_h) : 'center';
            $focus_v = ($focus_v !== '') ? strtolower($focus_v) : 'center';

            if (!in_array($focus_h, ['left', 'center', 'right', 'face_detect'], true)) {
                $focus_h = 'center';
            }
            if (!in_array($focus_v, ['top', 'center', 'bottom', 'face_detect'], true)) {
                $focus_v = 'center';
            }

            $offset_x = isset($payload['crop_offset_x']) ? trim((string) $payload['crop_offset_x']) : '0';
            $offset_y = isset($payload['crop_offset_y']) ? trim((string) $payload['crop_offset_y']) : '0';
            if ($offset_x === '') {
                $offset_x = '0';
            }
            if ($offset_y === '') {
                $offset_y = '0';
            }

            $smart = isset($payload['crop_smart_scaling']) ? trim((string) $payload['crop_smart_scaling']) : 'yes';
            $smart = strtolower($smart);
            $smart = ($smart === 'n' || $smart === 'no') ? 'n' : 'y';

            $sensitivity = 50;

            return implode('|', [
                $mode,
                $focus_h . ',' . $focus_v,
                $offset_x . ',' . $offset_y,
                $smart,
                (string) $sensitivity,
            ]);
        }

        // Back-compat: focal → crop offsets (percent).
        $has_focal = isset($payload['focal_x']) || isset($payload['focal_y']);
        if ($has_focal) {
            $fx = isset($payload['focal_x']) ? (float) $payload['focal_x'] : 50.0;
            $fy = isset($payload['focal_y']) ? (float) $payload['focal_y'] : 50.0;

            $fx = max(0.0, min(100.0, $fx));
            $fy = max(0.0, min(100.0, $fy));

            // Convert 0..100 focal position into -50..50% offsets.
            $ox = (string) ((int) round($fx - 50.0)) . '%';
            $oy = (string) ((int) round($fy - 50.0)) . '%';

            return implode('|', [
                'crop_mode:yes',
                'crop_focus_h:center',
                'crop_focus_v:center',
                'crop_offset_x:' . $ox,
                'crop_offset_y:' . $oy,
                'crop_smart_scaling:yes',
            ]);
        }

        return null;
    }
}
