<?php

/**
 * JCOGS Image Pro Field - FaceDetect
 *==================================
 * ExpressionEngine action endpoint/handler.
 *
 * Performs face detection for a selected file (within field policy) and returns
 * face boxes for use in the publish UI focal/crop helpers.
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
 * Face detection action controller.
 */
class FaceDetect extends AbstractRoute
{
    private const FACE_DETECT_MAX_DIMENSION = 1400;
    private const FACE_DETECT_TIME_LIMIT_SECONDS = 180;

    /**
     * Safely destroy a GD image resource or object.
     *
     * @param mixed $image GD image resource or object.
     */
    private function destroy_gd_image($image): void
    {
        if (is_resource($image) || $image instanceof \GdImage) {
            @imagedestroy($image);
        }
    }

    /**
     * Apply execution limits to keep the CP response clean and responsive.
     */
    private function apply_request_limits(): void
    {
        // Prevent PHP fatal HTML spilling into the CP UI.
        @ini_set('display_errors', '0');
        @ini_set('html_errors', '0');

        // Give face detection a little more time than default.
        // Note: may be capped by server config, but helps in typical setups.
        @ini_set('max_execution_time', (string) self::FACE_DETECT_TIME_LIMIT_SECONDS);
        @set_time_limit(self::FACE_DETECT_TIME_LIMIT_SECONDS);
    }

    /**
     * Downscale oversized images for detection, returning the scaled image and scale factor.
     *
     * @param mixed $gd_image GD image resource or object.
     * @return array{0:mixed,1:float}
     */
    private function maybe_downscale_image($gd_image, int $image_width, int $image_height): array
    {
        $max_dim = (int) self::FACE_DETECT_MAX_DIMENSION;
        if ($max_dim <= 0) {
            return [$gd_image, 1.0];
        }

        $largest = max($image_width, $image_height);
        if ($largest <= 0 || $largest <= $max_dim) {
            return [$gd_image, 1.0];
        }

        $scale = $max_dim / $largest;
        $new_w = max(1, (int) round($image_width * $scale));
        $new_h = max(1, (int) round($image_height * $scale));

        // imagescale returns a new image; keep original for later cleanup.
        $scaled = @imagescale($gd_image, $new_w, $new_h, IMG_BILINEAR_FIXED);
        if (!$scaled) {
            return [$gd_image, 1.0];
        }

        return [$scaled, $scale];
    }

    /**
     * Scale detected face boxes back to the original image coordinate space.
     *
     * @param array<int, array<string, mixed>> $faces
     * @return array<int, array<string, mixed>>
     */
    private function scale_faces_to_original(array $faces, float $scale): array
    {
        if (empty($faces) || $scale <= 0.0 || abs($scale - 1.0) < 0.000001) {
            return $faces;
        }

        $inv = 1.0 / $scale;
        $out = [];
        foreach ($faces as $face) {
            if (!is_array($face)) {
                continue;
            }
            $min_x = isset($face['min_x']) ? (float) $face['min_x'] : null;
            $min_y = isset($face['min_y']) ? (float) $face['min_y'] : null;
            $max_x = isset($face['max_x']) ? (float) $face['max_x'] : null;
            $max_y = isset($face['max_y']) ? (float) $face['max_y'] : null;
            if ($min_x === null || $min_y === null || $max_x === null || $max_y === null) {
                continue;
            }

            $min_x = (int) round($min_x * $inv);
            $min_y = (int) round($min_y * $inv);
            $max_x = (int) round($max_x * $inv);
            $max_y = (int) round($max_y * $inv);

            $face['min_x'] = $min_x;
            $face['min_y'] = $min_y;
            $face['max_x'] = $max_x;
            $face['max_y'] = $max_y;
            $face['width'] = max(0, $max_x - $min_x);
            $face['height'] = max(0, $max_y - $min_y);
            $out[] = $face;
        }
        return $out;
    }

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
     * Normalise face detection quality setting.
     */
    private function normalize_quality(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return 'balanced';
        }

        if (in_array($value, ['fast', 'balanced', 'accurate'], true)) {
            return $value;
        }

        return 'balanced';
    }

    /**
     * Normalise sensitivity to an integer range.
     *
     * @param mixed $value
     */
    private function normalize_sensitivity($value): int
    {
        if ($value === null || $value === '') {
            return 5;
        }

        if (!is_numeric($value)) {
            return 5;
        }

        $n = (int) $value;
        return max(1, min(9, $n));
    }

    /**
     * Normalise a margin input to pixel units.
     */
    private function normalize_margin_pixels(string $value, int $image_width): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        if (preg_match('/^\d+(?:\.\d+)?%$/', $value)) {
            $num = (float) rtrim($value, '%');
            if (!is_finite($num)) {
                return 0;
            }

            $num = max(0.0, min(100.0, $num));
            return (int) round(($num / 100.0) * $image_width);
        }

        if (preg_match('/^\d+(?:\.\d+)?px$/i', $value)) {
            $num = (float) rtrim(strtolower($value), 'px');
            if (!is_finite($num)) {
                return 0;
            }
            return (int) round(max(0.0, $num));
        }

        if (is_numeric($value)) {
            return (int) round(max(0.0, (float) $value));
        }

        return 0;
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
        if ($contentType === 'grid' && $fieldId > 0) {
            try {
                $column = ee('Model')->get('GridColumn')
                    ->filter('col_id', $fieldId)
                    ->first();
                if ($column && isset($column->field_id) && is_numeric($column->field_id)) {
                    $candidate = (int) $column->field_id;
                    if ($candidate > 0) {
                        return $candidate;
                    }
                }
            } catch (\Throwable $e) {
                // Fall through to provided value/defaults.
            }
        }

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
     * Build a lightweight fingerprint string for a file model.
     *
     * @param object $file
     */
    private function build_fingerprint_from_file_model($file): string
    {
        $upload_location_id = isset($file->upload_location_id) ? (int) $file->upload_location_id : 0;
        $file_name = isset($file->file_name) ? (string) $file->file_name : '';
        $modified_date = isset($file->modified_date) ? (int) $file->modified_date : 0;
        $file_size = isset($file->file_size) ? (int) $file->file_size : 0;

        return implode(':', [
            $upload_location_id,
            $file_name,
            $modified_date,
            $file_size,
        ]);
    }

    /**
     * Resolve a local filesystem path for a file model.
     *
     * @param object $file
     */
    private function get_local_file_path($file): ?string
    {
        if (is_object($file) && method_exists($file, 'getAbsolutePath')) {
            $path = (string) $file->getAbsolutePath();
            return $path !== '' ? $path : null;
        }

        $file_name = is_object($file) && isset($file->file_name) ? (string) $file->file_name : '';
        $dest = is_object($file) && isset($file->UploadDestination) ? $file->UploadDestination : null;
        $server_path = is_object($dest) && isset($dest->server_path) ? (string) $dest->server_path : '';

        if ($file_name === '' || $server_path === '') {
            return null;
        }

        $server_path = rtrim($server_path, '/\\') . '/';
        return $server_path . $file_name;
    }

    /**
     * Hash detection parameters for cache reuse.
     *
     * @param array<string, mixed> $params
     */
    private function compute_params_hash(array $params): string
    {
        ksort($params);
        return sha1(json_encode($params));
    }

    /**
     * Compute a weighted focal point from detected faces.
     *
     * @param array<int, array<string, mixed>> $faces
     * @return array{x:int,y:int,x_pct:float,y_pct:float}|null
     */
    private function compute_weighted_focal(array $faces, int $image_width, int $image_height): ?array
    {
        if (empty($faces) || $image_width <= 0 || $image_height <= 0) {
            return null;
        }

        $sum_w = 0.0;
        $sum_x = 0.0;
        $sum_y = 0.0;

        foreach ($faces as $face) {
            $min_x = isset($face['min_x']) ? (float) $face['min_x'] : null;
            $min_y = isset($face['min_y']) ? (float) $face['min_y'] : null;
            $max_x = isset($face['max_x']) ? (float) $face['max_x'] : null;
            $max_y = isset($face['max_y']) ? (float) $face['max_y'] : null;

            if ($min_x === null || $min_y === null || $max_x === null || $max_y === null) {
                continue;
            }

            $w = max(0.0, $max_x - $min_x);
            $h = max(0.0, $max_y - $min_y);
            $area = max(1.0, $w * $h);

            $cx = ($min_x + $max_x) / 2.0;
            $cy = ($min_y + $max_y) / 2.0;

            $sum_w += $area;
            $sum_x += ($cx * $area);
            $sum_y += ($cy * $area);
        }

        if ($sum_w <= 0.0) {
            return null;
        }

        $x = (int) round($sum_x / $sum_w);
        $y = (int) round($sum_y / $sum_w);

        $x = max(0, min($image_width - 1, $x));
        $y = max(0, min($image_height - 1, $y));

        $x_pct = round(($x / $image_width) * 100.0, 2);
        $y_pct = round(($y / $image_height) * 100.0, 2);

        return [
            'x' => $x,
            'y' => $y,
            'x_pct' => $x_pct,
            'y_pct' => $y_pct,
        ];
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

    /**
     * Handle face detection requests for the publish UI.
     */
    public function process()
    {
        $this->apply_request_limits();

        $site_id = (int) (ee()->config->item('site_id') ?: 1);
        $auth = ee('jcogs_img_pro_field:AuthService');
        $resp = ee('jcogs_img_pro_field:ActionResponder');
        $auth_error = $auth->requireCpAccessOrAjaxError();
        if (is_array($auth_error)) {
            return ee()->output->send_ajax_response($resp->normalise($auth_error));
        }

        $entry_id = (int) ee()->input->get_post('entry_id');
        if ($entry_id <= 0) {
            return ee()->output->send_ajax_response($resp->error('missing_entry'));
        }

        $field_id = (int) ee()->input->get_post('field_id');
        if ($field_id <= 0) {
            return ee()->output->send_ajax_response($resp->error('missing_field'));
        }

        $row_id = $this->normalize_nullable_int(ee()->input->get_post('row_id'));
        $fluid_field_data_id = $this->normalize_nullable_int(ee()->input->get_post('fluid_field_data_id'));
        $block_id = $this->normalize_nullable_int(ee()->input->get_post('block_id'));

        $content_type = $this->normalize_content_type((string) ee()->input->get_post('content_type'));
        if ($content_type === 'channel') {
            if ($row_id !== null) {
                $content_type = 'grid';
            }
            elseif ($fluid_field_data_id !== null) {
                $content_type = 'fluid';
            }
            elseif ($block_id !== null) {
                $content_type = 'bloqs';
            }
        }

        $container_id = $this->normalize_nullable_int(ee()->input->get_post('container_id'));
        if ($content_type === 'channel' && $field_id > 0) {
            try {
                $gridColumn = ee('Model')->get('GridColumn')
                    ->filter('col_id', $field_id)
                    ->first();
                if ($gridColumn && isset($gridColumn->field_id) && is_numeric($gridColumn->field_id)) {
                    $content_type = 'grid';
                    $candidateContainer = (int) $gridColumn->field_id;
                    if ($candidateContainer > 0) {
                        $container_id = $candidateContainer;
                    }
                }
            } catch (\Throwable $e) {
                // Continue with posted context.
            }
        }

        if ($content_type === 'channel' && $container_id !== null && $container_id > 0) {
            try {
                $containerField = ee('Model')->get('ChannelField', $container_id)->first();
                if ($containerField && isset($containerField->field_type)) {
                    $containerFieldType = strtolower(trim((string) $containerField->field_type));
                    if ($containerFieldType === 'grid') {
                        $content_type = 'grid';
                    }
                    elseif ($containerFieldType === 'fluid_field') {
                        $content_type = 'fluid';
                    }
                    elseif ($containerFieldType === 'bloqs' || $containerFieldType === 'blocksft') {
                        $content_type = 'bloqs';
                    }
                }
            } catch (\Throwable $e) {
                // Continue with resolved/default context.
            }
        }

        if ($content_type === 'grid' && $container_id === null && $field_id > 0) {
            try {
                $column = ee('Model')->get('GridColumn')
                    ->filter('col_id', $field_id)
                    ->first();
                if ($column && isset($column->field_id) && is_numeric($column->field_id)) {
                    $candidate = (int) $column->field_id;
                    if ($candidate > 0) {
                        $container_id = $candidate;
                    }
                }
            } catch (\Throwable $e) {
                // Fail safe: retain posted container_id.
            }
        }
        $container_id = $this->resolve_container_id($content_type, $container_id, $entry_id, $field_id);

        $acl_error = $auth->requireCanEditEntryFieldOrAjaxErrorWithContext(
            $entry_id,
            $field_id,
            $content_type,
            $container_id
        );
        if (is_array($acl_error)) {
            $acl_code = isset($acl_error['error']) ? (string) $acl_error['error'] : '';
            if ($content_type !== 'channel' && in_array($acl_code, ['field_not_found', 'not_authorised'], true)) {
                $entry_acl_error = $auth->requireCanEditEntryOrAjaxError($entry_id);
                if ($entry_acl_error === null) {
                    $acl_error = null;
                }
            }
        }
        if (is_array($acl_error)) {
            return ee()->output->send_ajax_response($resp->normalise($acl_error, $field_id));
        }

        $settingsService = ee('jcogs_img_pro_field:FieldSettingsService');
        $settings = $settingsService->getForFieldId($field_id);
        if ($content_type === 'grid') {
            // Grid columns can share IDs with channel fields; prefer column settings.
            $settings = $settingsService->getForGridColumnId($field_id);
        }
        if (($settings['enable_face_detect'] ?? 'n') !== 'y') {
            return ee()->output->send_ajax_response($resp->error('not_allowed'));
        }
        $policy = ee('jcogs_img_pro_field:PolicyEnforcer');

        $file_id = (int) ee()->input->get_post('file_id');
        if ($file_id <= 0) {
            $file_value = (string) ee()->input->get_post('file_value');
            if ($file_value !== '') {
                $file_id = $this->resolve_file_id($file_value);
            }
        }
        if ($file_id <= 0) {
            return ee()->output->send_ajax_response($resp->error('missing_file'));
        }

        $quality = $this->normalize_quality((string) ee()->input->get_post('face_detection_quality'));
        $sensitivity = $this->normalize_sensitivity(ee()->input->get_post('face_detect_sensitivity'));
        $force = $this->normalize_yes_no_input((string) ee()->input->get_post('force')) === 'yes';
        if ($force && ! $auth->canUseDebugFeatures()) {
            $force = false;
        }

        $file_error = $policy->validateFileIdAgainstSettings($file_id, $settings);
        if ($file_error !== null) {
            return ee()->output->send_ajax_response($resp->error($file_error));
        }

        $file = ee('Model')->get('File', $file_id)->with('UploadDestination')->first();
        if (!$file) {
            return ee()->output->send_ajax_response($resp->error('file_not_found'));
        }

        if (isset($file->site_id) && (int) $file->site_id !== $site_id) {
            return ee()->output->send_ajax_response($resp->error('file_wrong_site'));
        }

        $file_path = $this->get_local_file_path($file);
        if ($file_path === null || !is_file($file_path) || !is_readable($file_path)) {
            return ee()->output->send_ajax_response($resp->error('file_not_readable'));
        }

        $image_bytes = @file_get_contents($file_path);
        if (!is_string($image_bytes) || $image_bytes === '') {
            return ee()->output->send_ajax_response($resp->error('failed_to_read_file'));
        }

        $gd_image = @imagecreatefromstring($image_bytes);
        if (!$gd_image) {
            return ee()->output->send_ajax_response($resp->error('not_supported_image'));
        }

        $image_width = imagesx($gd_image);
        $image_height = imagesy($gd_image);

        // Downscale very large images for performance, but scale results back to original coordinates.
        [$detect_image, $scale] = $this->maybe_downscale_image($gd_image, $image_width, $image_height);
        $detect_w = imagesx($detect_image);
        $detect_h = imagesy($detect_image);

        $margin_pixels = $this->normalize_margin_pixels((string) ee()->input->get_post('face_crop_margin'), $image_width);
        $margin_pixels = max(0, min((int) floor(min($image_width, $image_height) / 2), $margin_pixels));

        // Apply margin in detection pixel-space for consistent behaviour.
        $margin_pixels_detect = (int) round($margin_pixels * $scale);
        $margin_pixels_detect = max(0, min((int) floor(min($detect_w, $detect_h) / 2), $margin_pixels_detect));

        $params = [
            'face_detection_quality' => $quality,
            'face_detect_sensitivity' => $sensitivity,
            'face_crop_margin_px' => $margin_pixels,
            'downscale_max_dim' => (int) self::FACE_DETECT_MAX_DIMENSION,
        ];
        $params_hash = $this->compute_params_hash($params);

        $now = (int) (ee()->localize->now ?? time());
        $fingerprint = $this->build_fingerprint_from_file_model($file);

        $augment = ee()->db
            ->select('id, source_fingerprint, face_detection_result')
            ->from('jcogs_img_pro_field_file_augments')
            ->where('site_id', $site_id)
            ->where('file_id', $file_id)
            ->limit(1)
            ->get()
            ->row_array();

        if (!$augment) {
            ee()->db->insert('jcogs_img_pro_field_file_augments', [
                'site_id' => $site_id,
                'file_id' => $file_id,
                'default_preset_id' => null,
                'source_fingerprint' => $fingerprint,
                'exif_snapshot' => null,
                'face_detection_result' => null,
                'created_date' => $now,
                'modified_date' => $now,
            ]);

            $augment = ee()->db
                ->select('id, source_fingerprint, face_detection_result')
                ->from('jcogs_img_pro_field_file_augments')
                ->where('site_id', $site_id)
                ->where('file_id', $file_id)
                ->limit(1)
                ->get()
                ->row_array();
        }

        if ($augment && ($augment['source_fingerprint'] ?? '') !== $fingerprint) {
            ee()->db
                ->where('id', (int) $augment['id'])
                ->update('jcogs_img_pro_field_file_augments', [
                    'modified_date' => $now,
                    'source_fingerprint' => $fingerprint,
                    'face_detection_result' => null,
                ]);

            $augment['source_fingerprint'] = $fingerprint;
            $augment['face_detection_result'] = null;
        }

        if (!$force && $augment && !empty($augment['face_detection_result'])) {
            $decoded = json_decode((string) $augment['face_detection_result'], true);
            if (is_array($decoded) && ($decoded['source_fingerprint'] ?? '') === $fingerprint && ($decoded['params_hash'] ?? '') === $params_hash) {
                $this->destroy_gd_image($detect_image);
                if ($detect_image !== $gd_image) {
                    $this->destroy_gd_image($gd_image);
                }
                return ee()->output->send_ajax_response([
                    'success' => true,
                    'cached' => true,
                    'file_id' => $file_id,
                    'source_fingerprint' => $fingerprint,
                    'params' => $params,
                    'result' => $decoded,
                ]);
            }
        }

        // Ensure Image Pro is available.
        if (!class_exists('JCOGSDesign\\JCOGSImagePro\\Service\\FaceDetectionService')) {
            $this->destroy_gd_image($detect_image);
            if ($detect_image !== $gd_image) {
                $this->destroy_gd_image($gd_image);
            }
            return ee()->output->send_ajax_response($resp->error('img_pro_not_available'));
        }

        // Require a minimum Image Pro version.
        if (!\JCOGSDesign\JcogsImgProField\Service\DependencyService::isImageProCompatible()) {
            $this->destroy_gd_image($detect_image);
            if ($detect_image !== $gd_image) {
                $this->destroy_gd_image($gd_image);
            }
            return ee()->output->send_ajax_response($resp->error('img_pro_not_compatible', [
                'required_version' => \JCOGSDesign\JcogsImgProField\Service\DependencyService::minImageProVersion(),
                'installed_version' => \JCOGSDesign\JcogsImgProField\Service\DependencyService::installedImageProVersion(),
            ]));
        }

        try {
            $service = new \JCOGSDesign\JCOGSImagePro\Service\FaceDetectionService();
            $faces = $service->detect_faces($detect_image, $sensitivity, $quality);
            $collection_box = $service->get_bounding_box($faces, $margin_pixels_detect);
        } catch (\Throwable $e) {
            return ee()->output->send_ajax_response($resp->error('face_detect_failed', [], $e, $field_id));
        } finally {
            $this->destroy_gd_image($detect_image);
            if ($detect_image !== $gd_image) {
                $this->destroy_gd_image($gd_image);
            }
        }

        // Scale detection results back to the original image coordinate space.
        if ($scale > 0.0 && abs($scale - 1.0) > 0.000001) {
            $faces = $this->scale_faces_to_original($faces, $scale);
            if (is_array($collection_box)) {
                $collection_box = $this->scale_faces_to_original([$collection_box], $scale);
                $collection_box = $collection_box[0] ?? null;
            }
        }

        $suggested_focal = $this->compute_weighted_focal($faces, $image_width, $image_height);

        $result = [
            'version' => 1,
            'computed_at' => $now,
            'source_fingerprint' => $fingerprint,
            'params' => $params,
            'params_hash' => $params_hash,
            'image_width' => $image_width,
            'image_height' => $image_height,
            'faces' => $faces,
            'collection_box' => $collection_box,
            'suggested_focal' => $suggested_focal,
        ];

        if ($augment && isset($augment['id'])) {
            ee()->db
                ->where('id', (int) $augment['id'])
                ->update('jcogs_img_pro_field_file_augments', [
                    'modified_date' => $now,
                    'source_fingerprint' => $fingerprint,
                    'face_detection_result' => json_encode($result),
                ]);
        }

        return ee()->output->send_ajax_response([
            'success' => true,
            'cached' => false,
            'file_id' => $file_id,
            'source_fingerprint' => $fingerprint,
            'params' => $params,
            'result' => $result,
        ]);
    }
}
