<?php

/**
 * JCOGS Image Pro Field - Preview
 *===============================
 * ExpressionEngine action endpoint/handler.
 *
 * Generates preview output (URL / img / picture) for the publish UI using the
 * stored “editorial intent” payload plus current tag params and field defaults.
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

namespace JCOGSDesign\JcogsImgProField\Actions;

use ExpressionEngine\Service\Addon\Controllers\Action\AbstractRoute;

/**
 * Preview action controller.
 */
class Preview extends AbstractRoute
{
    /**
     * Normalise yes/no style inputs to "yes"/"no" or null.
     */
    private function normalize_yes_no_input(string $value): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        if ($value === 'y' || $value === 'yes' || $value === '1' || $value === 'true') {
            return 'yes';
        }
        if ($value === 'n' || $value === 'no' || $value === '0' || $value === 'false') {
            return 'no';
        }

        return null;
    }

    /**
     * Coerce yes/no input to explicit words with a default fallback.
     */
    private function normalize_yes_no_to_words(string $value, string $default = 'yes'): string
    {
        $norm = $this->normalize_yes_no_input($value);
        if ($norm === 'yes') {
            return 'yes';
        }
        if ($norm === 'no') {
            return 'no';
        }
        return $default;
    }

    /**
     * Normalise numeric percentage inputs with clamping.
     */
    private function normalize_percent_number_input(string $value, float $min, float $max): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $num = (float) $value;
        if (! is_finite($num)) {
            return null;
        }

        $num = max($min, min($max, $num));
        $num = round($num, 1);

        $out = rtrim(rtrim(number_format($num, 1, '.', ''), '0'), '.');
        return $out === '' ? null : $out;
    }

    /**
     * Normalise dimension inputs (px, %, or integer values).
     */
    private function normalize_dimension_input(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Allow: 300, 300px, 50%, 50.5%
        if (preg_match('/^\d+(?:\.\d+)?%$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d+(?:\.\d+)?px$/i', $value)) {
            return strtolower($value);
        }
        if (preg_match('/^\d+$/', $value)) {
            return $value;
        }

        return null;
    }

    /**
     * Normalise aspect ratio inputs to canonical format.
     */
    private function normalize_aspect_ratio_input(string $value): ?string
    {
        $value = trim($value);
        if ($value === '__inherit__') {
            return '__inherit__';
        }
        if ($value === '') {
            return null;
        }

        // Allow: 16_9, 16:9, 16/9, 1.777
        if (preg_match('/^\d+(?:\.\d+)?\s*[_:\/\-]\s*\d+(?:\.\d+)?$/', $value)) {
            return str_replace([':', '/', '-', ' '], ['_', '_', '_', ''], $value);
        }
        if (preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            return $value;
        }

        return null;
    }

    /**
     * Normalise the content type to a supported value.
     */
    private function normalize_content_type(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === 'blocks/1' || $value === 'bloqs/1' || $value === 'blocks') {
            $value = 'bloqs';
        }
        if (in_array($value, ['channel', 'grid', 'fluid', 'bloqs'], true)) {
            return $value;
        }
        return 'channel';
    }

    /**
     * Normalise a numeric input to a positive integer or null.
     *
     * @param mixed $value
     */
    private function normalize_nullable_int($value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    /**
     * Resolve the container ID for composite contexts.
     */
    private function resolve_container_id(string $contentType, ?int $containerId, int $entryId, int $fieldId): ?int
    {
        if ($containerId !== null) {
            return $containerId;
        }
        if ($contentType === 'bloqs' && $fieldId > 0) {
            return $fieldId;
        }
        if ($contentType === 'channel' && $entryId > 0) {
            return $entryId;
        }
        return null;
    }

    /**
     * Handle AJAX preview requests for the field publish UI.
     */
    public function process()
    {
        $site_id = (int) (ee()->config->item('site_id') ?: 1);
        $auth = ee('jcogs_img_pro_field:AuthService');
        $resp = ee('jcogs_img_pro_field:ActionResponder');
        $field_id_hint = (int) ee()->input->get_post('field_id');

        try {
            $auth_error = $auth->requireCpAccessOrAjaxError();
            if (is_array($auth_error)) {
                return ee()->output->send_ajax_response($resp->normalise($auth_error));
            }

            $entry_id = (int) ee()->input->get_post('entry_id');
            $field_id = (int) ee()->input->get_post('field_id');

            if ($entry_id <= 0) {
                return ee()->output->send_ajax_response($resp->error('missing_entry'));
            }

            if ($field_id <= 0) {
                return ee()->output->send_ajax_response($resp->error('missing_field'));
            }

            $content_type = $this->normalize_content_type((string) ee()->input->get_post('content_type'));
            $container_id = $this->normalize_nullable_int(ee()->input->get_post('container_id'));
            $container_id = $this->resolve_container_id($content_type, $container_id, $entry_id, $field_id);

            $acl_error = $auth->requireCanEditEntryFieldOrAjaxErrorWithContext(
                $entry_id,
                $field_id,
                $content_type,
                $container_id
            );
            if (is_array($acl_error)) {
                return ee()->output->send_ajax_response($resp->normalise($acl_error, $field_id));
            }

            $settings = ($field_id > 0)
                ? ee('jcogs_img_pro_field:FieldSettingsService')->getForFieldId($field_id)
                : null;
            $policy = ee('jcogs_img_pro_field:PolicyEnforcer');

            $file_id = (int) ee()->input->get_post('file_id');
            $file_value = (string) ee()->input->get_post('file_value');
            if ($file_id <= 0 && $file_value !== '') {
                $file_id = $this->resolve_file_id($file_value);
            }

            if ($file_id <= 0) {
                return ee()->output->send_ajax_response($resp->error('missing_file'));
            }

            if (is_array($settings)) {
                $file_error = $policy->validateFileIdAgainstSettings($file_id, $settings);
                if ($file_error !== null) {
                    return ee()->output->send_ajax_response($resp->error($file_error));
                }
            }

            $file = ee('Model')->get('File', $file_id)->with('UploadDestination')->first();
            if (! $file) {
                return ee()->output->send_ajax_response($resp->error('file_not_found'));
            }

        // Best-effort sanity check: ensure file belongs to current site.
            if (isset($file->site_id) && (int) $file->site_id !== $site_id) {
                return ee()->output->send_ajax_response($resp->error('file_wrong_site'));
            }

            $abs_url = method_exists($file, 'getAbsoluteURL') ? $file->getAbsoluteURL() : null;
            $thumb_url = method_exists($file, 'getAbsoluteThumbnailURL') ? $file->getAbsoluteThumbnailURL() : null;

            $payload = [];
            $payload_from_json = false;
            $payload_json = trim((string) ee()->input->get_post('usage_payload'));
            if ($payload_json !== '') {
                $decoded = json_decode($payload_json, true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                    $payload_from_json = true;
                }
            }

        if (! $payload_from_json) {
            $preset_id = trim((string) ee()->input->get_post('preset_id'));
            if ($preset_id === '') {
                $default_preset_id = trim((string) ee()->input->get_post('default_preset_id'));
                if ($default_preset_id !== '' && is_numeric($default_preset_id) && (int) $default_preset_id > 0) {
                    $preset_id = (string) ((int) $default_preset_id);
                }
            }
            if ($preset_id !== '' && is_numeric($preset_id) && (int) $preset_id >= 0) {
                $payload['preset_id'] = (int) $preset_id;
            }

            $focal_x = trim((string) ee()->input->get_post('focal_x'));
            if ($focal_x !== '') {
                $payload['focal_x'] = (float) $focal_x;
            }

            $focal_y = trim((string) ee()->input->get_post('focal_y'));
            if ($focal_y !== '') {
                $payload['focal_y'] = (float) $focal_y;
            }

            // Optional crop overrides (stored verbatim so Image Pro can normalize).
            $crop = trim((string) ee()->input->get_post('crop'));
            if ($crop !== '') {
                $payload['crop'] = $crop;
            }

            $crop_mode = trim((string) ee()->input->get_post('crop_mode'));
            if ($crop_mode !== '') {
                $payload['crop_mode'] = $crop_mode;
            }

            $crop_focus_h = trim((string) ee()->input->get_post('crop_focus_h'));
            if ($crop_focus_h !== '') {
                $payload['crop_focus_h'] = $crop_focus_h;
            }

            $crop_focus_v = trim((string) ee()->input->get_post('crop_focus_v'));
            if ($crop_focus_v !== '') {
                $payload['crop_focus_v'] = $crop_focus_v;
            }

            $crop_offset_x = trim((string) ee()->input->get_post('crop_offset_x'));
            if ($crop_offset_x !== '') {
                $payload['crop_offset_x'] = $crop_offset_x;
            }

            $crop_offset_y = trim((string) ee()->input->get_post('crop_offset_y'));
            if ($crop_offset_y !== '') {
                $payload['crop_offset_y'] = $crop_offset_y;
            }

            $crop_smart_scaling = trim((string) ee()->input->get_post('crop_smart_scaling'));
            $crop_smart_scaling_norm = $this->normalize_yes_no_input($crop_smart_scaling);
            if ($crop_smart_scaling_norm !== null) {
                $payload['crop_smart_scaling'] = $crop_smart_scaling_norm;
            }

            $width = $this->normalize_dimension_input((string) ee()->input->get_post('width'));
            if ($width !== null) {
                $payload['width'] = $width;
            }

            $height = $this->normalize_dimension_input((string) ee()->input->get_post('height'));
            if ($height !== null) {
                $payload['height'] = $height;
            }
            $aspect_ratio = $this->normalize_aspect_ratio_input((string) ee()->input->get_post('aspect_ratio'));
            if ($aspect_ratio !== null) {
                $payload['aspect_ratio'] = $aspect_ratio;
            }
        }

        // Structured crop rectangle (percent of source). Accept even when usage_payload JSON is posted.
        $rect_left = $this->normalize_percent_number_input((string) ee()->input->get_post('crop_rect_left'), 0.0, 100.0);
        $rect_top = $this->normalize_percent_number_input((string) ee()->input->get_post('crop_rect_top'), 0.0, 100.0);
        $rect_width = $this->normalize_percent_number_input((string) ee()->input->get_post('crop_rect_width'), 1.0, 100.0);
        $rect_height = $this->normalize_percent_number_input((string) ee()->input->get_post('crop_rect_height'), 1.0, 100.0);
        if ($rect_left !== null && $rect_top !== null && $rect_width !== null && $rect_height !== null) {
            $payload['crop_rect'] = [
                'left' => $rect_left,
                'top' => $rect_top,
                'width' => $rect_width,
                'height' => $rect_height,
            ];

            // IMPORTANT: do not default width/height from the crop rectangle when a preset is in play.
            // Presets commonly define sizing; overriding it here makes the preview appear to “double crop”.
            $effective_preset_id = (int) ($payload['preset_id'] ?? 0);
            if ($effective_preset_id <= 0) {
                // If sizing isn't explicitly provided, provide a best-effort box size for preview-only
                // so crop has a target. These % values scale with the source.
                if (!isset($payload['width']) || !is_string($payload['width']) || trim($payload['width']) === '') {
                    $payload['width'] = $rect_width . '%';
                }
                if (!isset($payload['height']) || !is_string($payload['height']) || trim($payload['height']) === '') {
                    $payload['height'] = $rect_height . '%';
                }
            }
        }

            if (is_array($settings)) {
                $payload = $policy->sanitiseUsagePayload($payload, $settings);
            }
        } catch (\Throwable $e) {
            $fid = $field_id_hint > 0 ? $field_id_hint : null;
            return ee()->output->send_ajax_response($resp->serverError($e, $fid));
        }

        $derived_url = null;
        $derived_error = null;
        $derived_params = null;
        $derived_action_id = null;
        $derived_required_version = null;
        $derived_installed_version = null;

        try {
            $derived = $this->try_generate_img_pro_url($file, $abs_url, $payload);
            $derived_url = $derived['url'] ?? null;
            $derived_error = $derived['error'] ?? null;
            $derived_params = $derived['params'] ?? null;
            $derived_action_id = $derived['action_id'] ?? null;
            $derived_required_version = $derived['required_version'] ?? null;
            $derived_installed_version = $derived['installed_version'] ?? null;
        } catch (\Throwable $e) {
            $derived_error = $e->getMessage();
        }

        $effective_crop = $this->build_crop_param_from_payload($payload);
        $debug = [
            'effective_crop' => $effective_crop,
            'effective_width' => isset($payload['width']) ? $payload['width'] : null,
            'effective_height' => isset($payload['height']) ? $payload['height'] : null,
            'effective_aspect_ratio' => (isset($payload['aspect_ratio']) && $payload['aspect_ratio'] !== '__inherit__') ? $payload['aspect_ratio'] : null,
            'img_pro_action_id' => is_numeric($derived_action_id) ? (int) $derived_action_id : null,
        ];

        $include_debug = false;
        if (is_array($settings) && (($settings['enable_debug'] ?? 'n') === 'y') && $auth->canUseDebugFeatures()) {
            $include_debug = true;
        }

        return ee()->output->send_ajax_response([
            'success' => true,
            'file_id' => (int) $file_id,
            'file_url' => $abs_url,
            'thumb_url' => $thumb_url,
            'derived_url' => $derived_url,
            'derived_error' => $derived_error,
            'derived_required_version' => is_string($derived_required_version) ? $derived_required_version : null,
            'derived_installed_version' => is_string($derived_installed_version) ? $derived_installed_version : null,
            'derived_params' => $derived_params,
            'usage_payload' => $payload,
            'debug' => $include_debug ? $debug : null,
        ]);
    }

    /**
     * Generate an Image Pro Action Link preview URL when available.
     *
     * @param object $file
     * @param array<string, mixed> $payload
     * @return array{url:?string,error:?string,params:?array,action_id?:int,required_version?:string,installed_version?:string}
     */
    private function try_generate_img_pro_url($file, ?string $abs_url, array $payload): array
    {
        // Only attempt if Image Pro appears installed.
        if (! defined('PATH_THIRD')) {
            return [
                'url' => null,
                'error' => 'img_pro_not_available',
                'params' => null,
            ];
        }

        // Best-effort autoload (idempotent)
        $autoload = rtrim(PATH_THIRD, '/') . '/jcogs_img_pro/vendor/autoload.php';
        if (! class_exists('JCOGSDesign\\JCOGSImagePro\\Service\\ServiceCache') && is_file($autoload)) {
            require_once $autoload;
        }

        if (! class_exists('JCOGSDesign\\JCOGSImagePro\\Service\\ServiceCache')) {
            return [
                'url' => null,
                'error' => 'img_pro_not_available',
                'params' => null,
            ];
        }

        // Require a minimum Image Pro version.
        if (!\JCOGSDesign\JcogsImgProField\Service\DependencyService::isImageProCompatible()) {
            return [
                'url' => null,
                'error' => 'img_pro_not_compatible',
                'params' => null,
                'required_version' => \JCOGSDesign\JcogsImgProField\Service\DependencyService::minImageProVersion(),
                'installed_version' => \JCOGSDesign\JcogsImgProField\Service\DependencyService::installedImageProVersion(),
            ];
        }

        // Use Image Pro's Action Link system for previews (same mechanism as preset editor).
        // This avoids subtle differences in preset merging/validation and guarantees preset params like reflection are honoured.
        $image_url = $abs_url;
        if (! $image_url) {
            return [
                'url' => null,
                'error' => 'missing_image_url',
                'params' => null,
            ];
        }

        $action_id = $this->get_img_pro_action_id('ActOriginatedImage');
        if (! $action_id) {
            return [
                'url' => null,
                'error' => 'img_pro_action_id_missing',
                'params' => null,
                'action_id' => null,
            ];
        }

        $service_cache = 'JCOGSDesign\\JCOGSImagePro\\Service\\ServiceCache';

        // Start from preset parameters (if any).
        $action_params = [];
        $preset_id = isset($payload['preset_id']) ? (int) $payload['preset_id'] : 0;
        if ($preset_id > 0) {
            try {
                $preset_service = $service_cache::preset_service();
                if ($preset_service && method_exists($preset_service, 'getPresetById')) {
                    $preset = $preset_service->getPresetById($preset_id);
                    if (is_array($preset) && isset($preset['parameters']) && is_array($preset['parameters'])) {
                        $action_params = $preset['parameters'];
                    }
                }
            } catch (\Throwable $e) {
                // Ignore and fall back to no-op preview.
            }
        }

        // Force Action Link preview.
        $action_params['src'] = $image_url;

        // Apply crop overrides from payload (override preset crop if provided).
        $crop = $this->build_crop_param_from_payload($payload);
        if (is_string($crop) && $crop !== '') {
            $action_params['crop'] = $crop;
        }

        // Apply structured crop rectangle (percent-of-source) if present.
        // This is the field editor's “close crop” representation and is honoured by Image Pro's Crop filter.
        if (isset($payload['crop_rect']) && is_array($payload['crop_rect'])) {
            $l = $payload['crop_rect']['left'] ?? null;
            $t = $payload['crop_rect']['top'] ?? null;
            $w = $payload['crop_rect']['width'] ?? null;
            $h = $payload['crop_rect']['height'] ?? null;

            if ($l !== null && $t !== null && $w !== null && $h !== null) {
                $action_params['crop_rect'] = trim((string) $l) . ',' . trim((string) $t) . ',' . trim((string) $w) . ',' . trim((string) $h);
            }
        }

        // Apply sizing overrides from payload (needed for crop target box size).
        if (isset($payload['width']) && is_string($payload['width']) && trim($payload['width']) !== '') {
            $action_params['width'] = trim($payload['width']);
        }
        if (isset($payload['height']) && is_string($payload['height']) && trim($payload['height']) !== '') {
            $action_params['height'] = trim($payload['height']);
        }
        if (isset($payload['aspect_ratio']) && is_string($payload['aspect_ratio'])) {
            $ar = trim($payload['aspect_ratio']);
            if ($ar !== '' && $ar !== '__inherit__') {
                $action_params['aspect_ratio'] = $ar;
            }
        }

        $action_params['action_link'] = 'yes';
        $action_params['cache'] = '0';
        $action_params['url_only'] = 'yes';
        $action_params['act_what'] = 'preview';
        $action_params['act_path'] = $image_url;

        $validated_params = $this->validate_img_pro_action_params($action_params);
        $packet_json = json_encode($validated_params);
        if (! is_string($packet_json) || $packet_json === '') {
            return [
                'url' => null,
                'error' => 'act_packet_encode_failed',
                'params' => $validated_params,
                'action_id' => $action_id,
            ];
        }

        $act_packet = base64_encode($packet_json);
        if (! is_string($act_packet) || $act_packet === '') {
            return [
                'url' => null,
                'error' => 'act_packet_base64_failed',
                'params' => $validated_params,
                'action_id' => $action_id,
            ];
        }

        $site_url = (string) ee()->config->item('site_url');
        $site_url = rtrim($site_url, '/');

        $url = sprintf(
            '%s/?ACT=%s&act_packet=%s&preview=1&t=%s',
            $site_url,
            $action_id,
            urlencode($act_packet),
            time()
        );

        return [
            'url' => $url,
            'error' => null,
            'params' => $validated_params,
            'action_id' => $action_id,
        ];
    }

    /**
     * Build an Image Pro crop string from the stored payload.
     *
     * @param array<string, mixed> $payload
     */
    private function build_crop_param_from_payload(array $payload): ?string
    {
        if (isset($payload['crop']) && is_string($payload['crop']) && trim($payload['crop']) !== '') {
            return trim($payload['crop']);
        }

        // Structured crop rectangle (percent of source).
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

                $smart = $this->normalize_yes_no_to_words((string) ($payload['crop_smart_scaling'] ?? 'no'), 'no');

                // Image Pro uses 4-part positional crop format: mode|position|offset|smart_scaling
                return implode('|', [
                    'yes',
                    'center,center',
                    $ox . ',' . $oy,
                    $smart,
                ]);
            }
        }

        $has_named = isset($payload['crop_mode'])
            || isset($payload['crop_focus_h'])
            || isset($payload['crop_focus_v'])
            || isset($payload['crop_offset_x'])
            || isset($payload['crop_offset_y'])
            || isset($payload['crop_smart_scaling']);

        if ($has_named) {
            // IMPORTANT: Image Pro's crop parser expects legacy positional format:
            //   yes|center,center|0,0|yes
            // Not named format (crop_mode:yes|crop_focus_h:center|...).

            $mode = isset($payload['crop_mode']) ? trim((string) $payload['crop_mode']) : 'yes';
            $mode = strtolower($mode);
            if ($mode === '' || $mode === 'y' || $mode === 'yes') {
                $mode = 'yes';
            } elseif ($mode === 'n' || $mode === 'no') {
                $mode = 'no';
            } elseif ($mode === 'face_detect' || $mode === 'f') {
                $mode = 'face_detect';
            } else {
                $mode = 'yes';
            }

            if ($mode === 'no') {
                return 'no';
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
            $smart = $this->normalize_yes_no_to_words($smart, 'yes');

            return implode('|', [
                $mode,
                $focus_h . ',' . $focus_v,
                $offset_x . ',' . $offset_y,
                $smart,
            ]);
        }

        // Back-compat: focal → crop offsets (percent).
        $has_focal = isset($payload['focal_x']) || isset($payload['focal_y']);
        if ($has_focal) {
            $fx = isset($payload['focal_x']) ? (float) $payload['focal_x'] : 50.0;
            $fy = isset($payload['focal_y']) ? (float) $payload['focal_y'] : 50.0;

            $fx = max(0.0, min(100.0, $fx));
            $fy = max(0.0, min(100.0, $fy));

            $ox = (string) ((int) round($fx - 50.0)) . '%';
            $oy = (string) ((int) round($fy - 50.0)) . '%';

            return implode('|', [
                'yes',
                'center,center',
                $ox . ',' . $oy,
                'yes',
            ]);
        }

        return null;
    }

    /**
     * Resolve an Image Pro action ID by controller method name.
     */
    private function get_img_pro_action_id(string $method): int
    {
        try {
            $action = ee('Model')->get('Action')
                ->filter('class', 'Jcogs_img_pro')
                ->filter('method', $method)
                ->first();

            if ($action && isset($action->action_id)) {
                return (int) $action->action_id;
            }
        } catch (\Throwable $e) {
            // Ignore.
        }

        return 0;
    }

    /**
     * Validate Action Link parameters against Image Pro requirements.
     *
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function validate_img_pro_action_params(array $parameters): array
    {
        $validated = [];

        // Always allow these control keys in the ACT packet.
        $allowed_passthrough = ['src', 'action_link', 'url_only', 'act_what', 'act_path', 'cache'];

        foreach ($parameters as $param_name => $param_value) {
            $param_name = (string) $param_name;

            if (in_array($param_name, $allowed_passthrough, true)) {
                $validated[$param_name] = $param_value;
                continue;
            }

            $param_name_lower = strtolower($param_name);

            // Prefer Image Pro's registry when available (canonical names/aliases),
            // but do NOT drop unknown keys: presets can contain composite parameters
            // (e.g. 'reflection', 'text', 'rounded_corners') that may not be registered.
            if (class_exists('JCOGSDesign\\JCOGSImagePro\\Service\\ParameterRegistry')) {
                try {
                    if (\JCOGSDesign\JCOGSImagePro\Service\ParameterRegistry::parameterExists($param_name_lower)) {
                        $validated[$param_name_lower] = $param_value;
                        continue;
                    }
                } catch (\Throwable $e) {
                    // Fall through to permissive behaviour.
                }
            }

            // Permissive fallback: include as-is (lowercased key).
            // This is safe here because values originate from stored presets / editor UI.
            $validated[$param_name_lower] = $param_value;
        }

        return $validated;
    }

    /**
     * Resolve a file ID from a raw field value.
     *
     * @param mixed $data
     */
    private function resolve_file_id($data): int
    {
        if (empty($data)) {
            return 0;
        }

        if (is_numeric($data)) {
            return (int) $data;
        }

        if (! isset(ee()->file_field)) {
            ee()->load->library('file_field');
        }

        $file = ee()->file_field->getFileModelForFieldData($data);
        if ($file && isset($file->file_id)) {
            return (int) $file->file_id;
        }

        return 0;
    }
}
