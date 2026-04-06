<?php

/**
 * JCOGS Image Pro Field - Usage
 *==============================
 * ExpressionEngine action endpoint/handler.
 *
 * Loads and saves per-entry/per-field usage payload (“editorial intent”) for the
 * JCOGS Image Pro Field publish UI.
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
 * Usage action controller.
 */
class Usage extends AbstractRoute
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
     * Stage a usage payload to cache for later entry save persistence.
     *
     * @param array<string, mixed> $payload
     */
    private function stagePayload(
        int $entryId,
        int $fieldId,
        string $contentType,
        ?int $containerId,
        ?int $rowId,
        ?int $fluidFieldDataId,
        ?int $blockId,
        int $fileId,
        array $payload,
        bool $cleared
    ): void {
        try {
            $key = $this->buildStageKey(
                $entryId,
                $fieldId,
                $contentType,
                $containerId,
                $rowId,
                $fluidFieldDataId,
                $blockId
            );
            if ($key === '') {
                return;
            }
            $cache = (isset(ee()->cache) && is_object(ee()->cache)) ? ee()->cache : null;
            if (! $cache) {
                return;
            }

            $cache->save($key, [
                'entry_id' => $entryId,
                'field_id' => $fieldId,
                'content_type' => $contentType,
                'container_id' => $containerId,
                'row_id' => $rowId,
                'fluid_field_data_id' => $fluidFieldDataId,
                'block_id' => $blockId,
                'file_id' => $fileId,
                'payload' => $payload,
                'cleared' => $cleared ? 1 : 0,
                'stored_at' => time(),
            ], 7200);
        } catch (\Throwable $e) {
            // Fail safe: staging should not block saves.
        }
    }

    /**
     * Build a cache key for staged payloads.
     */
    private function buildStageKey(
        int $entryId,
        int $fieldId,
        string $contentType,
        ?int $containerId,
        ?int $rowId,
        ?int $fluidFieldDataId,
        ?int $blockId
    ): string {
        $sessionId = '';
        if (isset(ee()->session) && isset(ee()->session->userdata['session_id'])) {
            $sessionId = (string) ee()->session->userdata['session_id'];
        }
        if ($sessionId === '') {
            $sessionId = (string) (ee()->input->cookie('ci_session') ?? '');
        }
        $sessionId = preg_replace('/[^a-zA-Z0-9]/', '', $sessionId);
        if ($sessionId === '') {
            return '';
        }

        $ct = $contentType !== '' ? $contentType : 'channel';
        return implode(':', [
            'jcogs_img_pro_field',
            'staged',
            $sessionId,
            (string) $entryId,
            (string) $fieldId,
            $ct,
            (string) ($containerId ?? ''),
            (string) ($rowId ?? ''),
            (string) ($fluidFieldDataId ?? ''),
            (string) ($blockId ?? ''),
        ]);
    }

    /**
     * Handle AJAX requests for reading/writing usage payload rows.
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

            $member_id = isset(ee()->session->userdata['member_id']) ? (int) ee()->session->userdata['member_id'] : 0;

            $http_method = strtoupper((string) ee()->input->server('REQUEST_METHOD'));
            $op = trim((string) ee()->input->get_post('op'));

            if ($op === '') {
                $op = ($http_method === 'POST') ? 'set' : 'get';
            }

            $entry_id = (int) ee()->input->get_post('entry_id');
            $field_id = (int) ee()->input->get_post('field_id');

            if ($entry_id <= 0 || $field_id <= 0) {
                return ee()->output->send_ajax_response($resp->error('missing_entry_or_field'));
            }

            $content_type = $this->normalize_content_type((string) ee()->input->get_post('content_type'));
            $row_id = $this->normalize_nullable_int(ee()->input->get_post('row_id'));
            $fluid_field_data_id = $this->normalize_nullable_int(ee()->input->get_post('fluid_field_data_id'));
            $block_id = $this->normalize_nullable_int(ee()->input->get_post('block_id'));
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

            $settingsService = ee('jcogs_img_pro_field:FieldSettingsService');
            $settings = $settingsService->getForFieldId($field_id);
            if ($content_type === 'grid') {
                // Grid columns can share IDs with channel fields; prefer column settings.
                $settings = $settingsService->getForGridColumnId($field_id);
            }
            $policy = ee('jcogs_img_pro_field:PolicyEnforcer');

            if ($op === 'get') {
                $builder = ee()->db
                    ->select('file_id, usage_payload')
                    ->from('jcogs_img_pro_field_usages')
                    ->where('site_id', $site_id)
                    ->where('entry_id', $entry_id)
                    ->where('field_id', $field_id)
                    ->where('content_type', $content_type);
                $this->applyContextFilters($builder, $row_id, $fluid_field_data_id, $block_id);

                $row = $builder->limit(1)->get()->row_array();

                $payload = [];
                if ($row) {
                    $decoded = json_decode((string) ($row['usage_payload'] ?? ''), true);
                    if (is_array($decoded)) {
                        $payload = $policy->sanitiseUsagePayload($decoded, $settings);
                    }
                }

                return ee()->output->send_ajax_response([
                    'success' => true,
                    'entry_id' => $entry_id,
                    'field_id' => $field_id,
                    'file_id' => (int) ($row['file_id'] ?? 0),
                    'usage_payload' => $payload,
                ]);
            }

            if ($op !== 'set') {
                return ee()->output->send_ajax_response($resp->error('invalid_op'));
            }

            $file_id = (int) ee()->input->get_post('file_id');
            $file_value = (string) ee()->input->get_post('file_value');
            if ($file_id <= 0 && $file_value !== '') {
                $file_id = $this->resolve_file_id($file_value);
            }

        // Allow clearing/removing usage rows.
            if ($file_id > 0) {
                $file_error = $policy->validateFileIdAgainstSettings($file_id, $settings);
                if ($file_error !== null) {
                    return ee()->output->send_ajax_response($resp->error($file_error));
                }
            }
            $payload = [];

        // Accept a JSON payload if provided.
        $payload_json = trim((string) ee()->input->get_post('usage_payload'));
        if ($payload_json !== '') {
            $decoded = json_decode($payload_json, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        // Otherwise build from individual posted values.
        if (empty($payload)) {
            $preset_id = trim((string) ee()->input->get_post('preset_id'));
            if ($preset_id !== '') {
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

            // Optional Image Pro sizing params (needed for crop target box size).
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

            // Structured crop rectangle (percent of source) for perfect restoration.
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
            }
        }

        // Art direction alternates (media-keyed).
        // The publish UI posts art_direction_files as an index->file mapping.
        // We also accept a media->file mapping directly.
        $ad_present = trim((string) ee()->input->get_post('art_direction_files_present')) !== '';
        if ($ad_present) {
            $raw = ee()->input->post('art_direction_files');
            // In some EE contexts, Input::post() returns false for array-style keys (art_direction_files[2]).
            // Fall back to raw PHP superglobal.
            if ($raw === false && isset($_POST['art_direction_files'])) {
                $raw = $_POST['art_direction_files'];
            }
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $raw = is_array($decoded) ? $decoded : [];
            }
            if (! is_array($raw)) {
                $raw = [];
            }

            $idx_to_media = [];
            $idx_to_media_raw = trim((string) ee()->input->get_post('art_direction_index_to_media'));
            if ($idx_to_media_raw !== '') {
                $decoded = json_decode($idx_to_media_raw, true);
                if (is_array($decoded)) {
                    $idx_to_media = $decoded;
                }
            }

            $files = [];
            foreach ($raw as $k => $v) {
                $fid = $this->resolve_file_id($v);
                if ($fid <= 0) {
                    continue;
                }

                // Numeric keys are publish-row indexes; map to canonical media string.
                if (is_numeric($k)) {
                    $idx = (int) $k;
                    $media = isset($idx_to_media[(string) $idx]) ? (string) $idx_to_media[(string) $idx] : '';
                    if ($media !== '') {
                        $files[$media] = $fid;
                    }
                    continue;
                }

                // Non-numeric keys are assumed to already be canonical media strings.
                $media = trim((string) $k);
                if ($media !== '') {
                    $files[$media] = $fid;
                }
            }

            // IMPORTANT: treat "no files posted" as "no change".
            // The client may submit art_direction_files_present even when the browser couldn't extract IDs.
            // Only update/clear stored files when at least one valid file_id is received.
            if (! empty($files)) {
                if (! isset($payload['art_direction']) || ! is_array($payload['art_direction'])) {
                    $payload['art_direction'] = [];
                }
                $payload['art_direction']['files'] = $files;
            }
        }

        if (isset($payload['art_direction']) && is_array($payload['art_direction'])
            && isset($payload['art_direction']['files']) && is_array($payload['art_direction']['files'])
            && ! empty($payload['art_direction']['files'])) {
            // If AD files were submitted, allow policy to keep them even when settings are incomplete.
            if (($settings['enable_art_direction'] ?? 'n') !== 'y') {
                $settings['enable_art_direction'] = 'y';
            }
        }

        $payload = $policy->sanitiseUsagePayload($payload, $settings);

        // Enforce directory restrictions on art-direction alternates as well.
        if (isset($payload['art_direction']) && is_array($payload['art_direction'])
            && isset($payload['art_direction']['files']) && is_array($payload['art_direction']['files'])) {
            $clean = [];
            foreach ($payload['art_direction']['files'] as $media => $fid) {
                $fid = is_numeric($fid) ? (int) $fid : 0;
                if ($fid <= 0) {
                    continue;
                }
                $err = $policy->validateFileIdAgainstSettings($fid, $settings);
                if ($err === null) {
                    $clean[(string) $media] = $fid;
                }
            }
            if (empty($clean)) {
                unset($payload['art_direction']);
            } else {
                $payload['art_direction']['files'] = $clean;
            }
        }

        // Stage payload for entry-save persistence (modal workflow).
        $stageCleared = empty($payload);
        $this->stagePayload(
            $entry_id,
            $field_id,
            $content_type,
            $container_id,
            $row_id,
            $fluid_field_data_id,
            $block_id,
            $file_id,
            $payload,
            $stageCleared
        );

        // If no overrides remain, remove the usage row.
        if (empty($payload)) {
            $builder = ee()->db
                ->where('site_id', $site_id)
                ->where('entry_id', $entry_id)
                ->where('field_id', $field_id)
                ->where('content_type', $content_type);
            $this->applyContextFilters($builder, $row_id, $fluid_field_data_id, $block_id);
            $builder->delete('jcogs_img_pro_field_usages');

            return ee()->output->send_ajax_response([
                'success' => true,
                'deleted' => true,
                'entry_id' => $entry_id,
                'field_id' => $field_id,
            ]);
        }

        // File ID can be transiently unavailable in composite/new-row contexts.
        // Keep staged payload and let entry-save reconciliation attach it when file_id resolves.
        if ($file_id <= 0) {
            return ee()->output->send_ajax_response([
                'success' => true,
                'staged_only' => true,
                'entry_id' => $entry_id,
                'field_id' => $field_id,
            ]);
        }

        $now = (int) (ee()->localize->now ?? time());

        $existingBuilder = ee()->db
            ->select('id')
            ->from('jcogs_img_pro_field_usages')
            ->where('site_id', $site_id)
            ->where('entry_id', $entry_id)
            ->where('field_id', $field_id)
            ->where('content_type', $content_type);
        $this->applyContextFilters($existingBuilder, $row_id, $fluid_field_data_id, $block_id);
        $existing = $existingBuilder->limit(1)->get()->row_array();

        $row = [
            'site_id' => $site_id,
            'entry_id' => $entry_id,
            'field_id' => $field_id,
            'content_type' => $content_type,
            'container_id' => $container_id,
            'row_id' => $row_id,
            'fluid_field_data_id' => $fluid_field_data_id,
            'block_id' => $block_id,
            'file_id' => $file_id,
            'usage_payload' => json_encode($payload),
            'modified_date' => $now,
            'modified_by_member_id' => $member_id,
        ];

        if ($existing) {
            ee()->db
                ->where('id', (int) $existing['id'])
                ->update('jcogs_img_pro_field_usages', $row);

            return ee()->output->send_ajax_response([
                'success' => true,
                'updated' => true,
                'entry_id' => $entry_id,
                'field_id' => $field_id,
            ]);
        }

        $row['created_date'] = $now;
        $row['created_by_member_id'] = $member_id;

        ee()->db->insert('jcogs_img_pro_field_usages', $row);

            return ee()->output->send_ajax_response([
                'success' => true,
                'created' => true,
                'entry_id' => $entry_id,
                'field_id' => $field_id,
            ]);
        } catch (\Throwable $e) {
            $fid = $field_id_hint > 0 ? $field_id_hint : null;
            return ee()->output->send_ajax_response($resp->serverError($e, $fid));
        }
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
     * Apply composite context filters to a usage query builder.
     *
     * @param object $builder
     */
    private function applyContextFilters($builder, ?int $rowId, ?int $fluidFieldDataId, ?int $blockId): void
    {
        if ($rowId !== null) {
            $builder->where('row_id', $rowId);
        } else {
            $builder->where('row_id IS NULL', null, false);
        }

        if ($fluidFieldDataId !== null) {
            $builder->where('fluid_field_data_id', $fluidFieldDataId);
        } else {
            $builder->where('fluid_field_data_id IS NULL', null, false);
        }

        if ($blockId !== null) {
            $builder->where('block_id', $blockId);
        } else {
            $builder->where('block_id IS NULL', null, false);
        }
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
