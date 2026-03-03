<?php

/**
 * JCOGS Image Pro Field - UsagePersistenceService
 *===============================================
 * Canonical persistence layer for per-entry editor overrides.
 *
 * Writes JSON usage payloads (crop, focal, art direction, etc.) to
 * exp_jcogs_img_pro_field_usages.
 *
 * This exists because some EE publish/content flows do not consistently invoke
 * fieldtype save()/post_save() callbacks.
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.0
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 * @since      0.1.7
 */

namespace JCOGSDesign\JcogsImgProField\Service;

/**
 * Persist and reconcile usage payloads for publish flows.
 */
class UsagePersistenceService
{
    /**
     * Persist all posted overrides for an entry (all jcogs_img_pro_field fields).
     *
     * Typically called from the after_channel_entry_save extension hook.
     */
    public function persistFromPost(int $entryId, ?int $siteId = null): void
    {
        $entryId = (int) $entryId;
        if ($entryId <= 0) {
            return;
        }

        $debugEnabled = (string) ee()->input->get_post('jcogs_img_pro_field_debug') === '1';
        if ($debugEnabled) {
            ee()->session->set_flashdata('jcogs_img_pro_field_debug_post', $this->buildPostDebugSummary());
        }

        $siteId = (int) ($siteId ?? (ee()->config->item('site_id') ?: 1));
        if ($siteId <= 0) {
            $siteId = 1;
        }

        $all = ee()->input->post('jcogs_img_pro_field');
        if (! is_array($all)) {
            $all = $_POST['jcogs_img_pro_field'] ?? [];
        }

        $composite = $this->extractCompositePayloadsFromPost();
        if (! is_array($all)) {
            $all = [];
        }

        if (! empty($composite)) {
            $all = array_merge(array_values($all), $composite);
        }

        $compositeFieldIds = $this->extractCompositeFieldIds($composite);

        $all = $this->dedupePayloadsByContext(is_array($all) ? $all : [], $entryId);
        // Persist channel payloads from direct field posts.
        $channelPayloads = $this->filterPayloadsByContentType($all, $entryId, ['channel']);

        // Also persist composite payloads directly from post when present.
        // This provides a reliable first-save path when staged cache context keys drift.
        $compositePayloads = $this->filterPayloadsByContentType($all, $entryId, ['grid', 'fluid', 'bloqs']);

        if (! $this->hasValidPayloads($channelPayloads) && ! $this->hasValidPayloads($compositePayloads)) {
            if ($debugEnabled) {
                ee()->session->set_flashdata('jcogs_img_pro_field_debug_note', 'no_payload');
                ee()->session->set_flashdata('jcogs_img_pro_field_debug', []);
            }
            return;
        }

        if ($this->hasValidPayloads($channelPayloads)) {
            $this->persistPayloads($entryId, $siteId, $channelPayloads, $compositeFieldIds, $debugEnabled);
        }

        if ($this->hasValidPayloads($compositePayloads)) {
            $this->persistPayloads($entryId, $siteId, $compositePayloads, [], $debugEnabled);
        }
    }

    /**
     * Apply staged modal payloads during entry save for composite contexts.
     */
    public function applyStagedPayloadsFromCache(int $entryId, ?int $siteId = null): void
    {
        $entryId = (int) $entryId;
        if ($entryId <= 0) {
            return;
        }

        $siteId = (int) ($siteId ?? (ee()->config->item('site_id') ?: 1));
        if ($siteId <= 0) {
            $siteId = 1;
        }

        $payloads = $this->extractCompositePayloadsFromPost();
        if (empty($payloads)) {
            return;
        }

        $payloads = $this->dedupePayloadsByContext($payloads, $entryId);
        $payloads = $this->filterPayloadsByContentType($payloads, $entryId, ['grid', 'fluid', 'bloqs']);
        if (empty($payloads)) {
            return;
        }

        $cache = (isset(ee()->cache) && is_object(ee()->cache)) ? ee()->cache : null;
        if (! $cache) {
            return;
        }

        $fieldSettings = ee('jcogs_img_pro_field:FieldSettingsService');
        $policy = ee('jcogs_img_pro_field:PolicyEnforcer');

        foreach ($payloads as $posted) {
            if (! is_array($posted)) {
                continue;
            }

            $fieldId = isset($posted['field_id']) && is_numeric($posted['field_id']) ? (int) $posted['field_id'] : 0;
            if ($fieldId <= 0) {
                continue;
            }

            $context = $this->extractContext($posted, $entryId);
            $contentType = $context['content_type'] ?? 'channel';
            $containerId = $context['container_id'] ?? null;
            $rowId = $context['row_id'] ?? null;
            $fluidFieldDataId = $context['fluid_field_data_id'] ?? null;
            $blockId = $context['block_id'] ?? null;

            $currentFileId = $this->resolvePostedFileId($posted, $fieldId);
            $usageRow = $this->fetchUsageRowMeta($siteId, $entryId, $fieldId, (string) $contentType, $rowId, $fluidFieldDataId, $blockId);
            $storedFileId = (int) ($usageRow['file_id'] ?? 0);

            $stageKey = $this->buildStageKey(
                $entryId,
                $fieldId,
                (string) $contentType,
                $containerId,
                $rowId,
                $fluidFieldDataId,
                $blockId
            );
            $staged = $this->fetchStagedPayloadForContext(
                $cache,
                $entryId,
                $fieldId,
                (string) $contentType,
                $containerId,
                $rowId,
                $fluidFieldDataId,
                $blockId,
                $stageKey
            );

            $stagedFileId = is_array($staged) ? (int) ($staged['file_id'] ?? 0) : 0;
            if ($currentFileId <= 0 && $stagedFileId > 0) {
                $currentFileId = $stagedFileId;
            }

            if ($contentType === 'grid' && $rowId === null && $currentFileId > 0) {
                $resolvedRowId = $this->resolveGridRowIdFromDatabase($entryId, $containerId, $fieldId, $currentFileId);
                if ($resolvedRowId !== null) {
                    $rowId = $resolvedRowId;
                    $context['row_id'] = $resolvedRowId;
                    $usageRow = $this->fetchUsageRowMeta($siteId, $entryId, $fieldId, (string) $contentType, $rowId, $fluidFieldDataId, $blockId);
                }
            }

            if ($currentFileId <= 0) {
                if (($usageRow['id'] ?? 0) > 0) {
                    $this->deleteUsageRowById((int) $usageRow['id']);
                }
                if ($stageKey !== '') {
                    $cache->delete($stageKey);
                }
                continue;
            }

            $hasStaged = is_array($staged);
            if ($hasStaged) {
                if ($stagedFileId > 0 && $stagedFileId !== $currentFileId) {
                    $hasStaged = false;
                }
            }

            if ($hasStaged) {
                $cleared = ! empty($staged['cleared']);
                $payload = (isset($staged['payload']) && is_array($staged['payload'])) ? $staged['payload'] : [];

                if ($cleared || empty($payload)) {
                    if (($usageRow['id'] ?? 0) > 0) {
                        $this->deleteUsageRowById((int) $usageRow['id']);
                    }
                    if ($stageKey !== '') {
                        $cache->delete($stageKey);
                    }
                    continue;
                }

                $settings = is_object($fieldSettings) ? $fieldSettings->getForFieldId($fieldId) : [];
                if ($contentType === 'grid' && is_object($fieldSettings)) {
                    $settings = $fieldSettings->getForGridColumnId($fieldId);
                }

                if (is_object($policy)) {
                    $payload = $policy->sanitiseUsagePayload(is_array($payload) ? $payload : [], is_array($settings) ? $settings : []);
                    $payload = $this->sanitizeArtDirectionFiles($payload, $policy, is_array($settings) ? $settings : []);
                }

                if (empty($payload)) {
                    if (($usageRow['id'] ?? 0) > 0) {
                        $this->deleteUsageRowById((int) $usageRow['id']);
                    }
                } else {
                    $this->upsertUsageRow(
                        $siteId,
                        $entryId,
                        $fieldId,
                        $currentFileId,
                        $payload,
                        (string) $contentType,
                        $containerId,
                        $rowId,
                        $fluidFieldDataId,
                        $blockId
                    );
                }

                if ($stageKey !== '') {
                    $cache->delete($stageKey);
                }
                continue;
            }

            if ($storedFileId > 0 && $storedFileId !== $currentFileId) {
                if (($usageRow['id'] ?? 0) > 0) {
                    $this->deleteUsageRowById((int) $usageRow['id']);
                }
            }
        }
    }

    /**
     * Restrict payloads to specified content types.
     *
     * @param array<int|string, mixed> $payloads
     * @param array<int, string> $allowedTypes
     * @return array<int|string, mixed>
     */
    private function filterPayloadsByContentType(array $payloads, int $entryId, array $allowedTypes): array
    {
        if (empty($payloads)) {
            return [];
        }

        $allowed = [];
        foreach ($allowedTypes as $t) {
            $t = strtolower(trim((string) $t));
            if ($t !== '') {
                $allowed[$t] = true;
            }
        }
        if (empty($allowed)) {
            return [];
        }

        $out = [];
        foreach ($payloads as $key => $posted) {
            if (! is_array($posted)) {
                continue;
            }
            $context = $this->extractContext($posted, $entryId);
            $type = strtolower((string) ($context['content_type'] ?? ''));
            if ($type === '') {
                $type = 'channel';
            }
            if (! isset($allowed[$type])) {
                continue;
            }
            $out[$key] = $posted;
        }

        return $out;
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
     * Fetch a staged payload with fallback keys for first-save composite contexts.
     *
     * Grid rows can transition from unknown row_id to a concrete row_id on first save,
     * so we try both exact and relaxed keys.
     *
     * @param mixed $cache
     * @return mixed
     */
    private function fetchStagedPayloadForContext(
        $cache,
        int $entryId,
        int $fieldId,
        string $contentType,
        ?int $containerId,
        ?int $rowId,
        ?int $fluidFieldDataId,
        ?int $blockId,
        string $primaryKey
    ) {
        $keys = [];
        if ($primaryKey !== '') {
            $keys[] = $primaryKey;
        }

        // Grid first-save fallback: row_id may be unresolved during modal staging.
        if ($contentType === 'grid' && $rowId !== null) {
            $k = $this->buildStageKey($entryId, $fieldId, $contentType, $containerId, null, $fluidFieldDataId, $blockId);
            if ($k !== '') {
                $keys[] = $k;
            }
        }

        // Fluid first-save fallback: fluid_field_data_id may not be final in early UI lifecycle.
        if ($contentType === 'fluid' && $fluidFieldDataId !== null) {
            $k = $this->buildStageKey($entryId, $fieldId, $contentType, $containerId, $rowId, null, $blockId);
            if ($k !== '') {
                $keys[] = $k;
            }
        }

        // Bloqs first-save fallback: block_id may be unresolved/placeholder during modal staging.
        if ($contentType === 'bloqs' && $blockId !== null) {
            $k = $this->buildStageKey($entryId, $fieldId, $contentType, $containerId, $rowId, $fluidFieldDataId, null);
            if ($k !== '') {
                $keys[] = $k;
            }
        }

        $keys = array_values(array_unique(array_filter($keys, static function ($v) {
            return is_string($v) && $v !== '';
        })));

        foreach ($keys as $key) {
            $value = $cache->get($key);
            if (is_array($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Fetch usage row metadata (id/file_id) for a given context.
     *
     * @return array{id:int,file_id:int}
     */
    private function fetchUsageRowMeta(
        int $siteId,
        int $entryId,
        int $fieldId,
        string $contentType,
        ?int $rowId,
        ?int $fluidFieldDataId,
        ?int $blockId
    ): array {
        $builder = ee()->db
            ->select('id, file_id')
            ->from('jcogs_img_pro_field_usages')
            ->where('site_id', $siteId)
            ->where('entry_id', $entryId)
            ->where('field_id', $fieldId)
            ->where('content_type', $contentType);
        $this->applyContextFilters($builder, $rowId, $fluidFieldDataId, $blockId);

        $row = $builder->limit(1)->get()->row_array();
        return is_array($row) ? $row : ['id' => 0, 'file_id' => 0];
    }

    /**
     * Delete a usage row by ID.
     */
    private function deleteUsageRowById(int $id): void
    {
        if ($id <= 0) {
            return;
        }
        ee()->db->where('id', $id)->delete('jcogs_img_pro_field_usages');
    }

    /**
     * Sanitize art direction file IDs against the field policy.
     *
     * @param object $policy
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function sanitizeArtDirectionFiles(array $payload, $policy, array $settings): array
    {
        if (! isset($payload['art_direction']) || ! is_array($payload['art_direction'])
            || ! isset($payload['art_direction']['files']) || ! is_array($payload['art_direction']['files'])) {
            return $payload;
        }

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

        return $payload;
    }

    /**
     * Persist posted overrides for a single Grid row using a known row ID.
     */
    public function persistGridRowFromPost(
        int $entryId,
        ?int $siteId,
        int $fieldId,
        int $rowId,
        ?int $containerId = null
    ): void
    {
        $entryId = (int) $entryId;
        $fieldId = (int) $fieldId;
        $rowId = (int) $rowId;
        if ($entryId <= 0 || $fieldId <= 0 || $rowId <= 0) {
            return;
        }

        $debugEnabled = (string) ee()->input->get_post('jcogs_img_pro_field_debug') === '1';

        $siteId = (int) ($siteId ?? (ee()->config->item('site_id') ?: 1));
        if ($siteId <= 0) {
            $siteId = 1;
        }

        $payload = $this->extractGridRowPayloadFromPost($fieldId, $rowId, $containerId);
        if (! is_array($payload) || empty($payload)) {
            if ($debugEnabled) {
                ee()->session->set_flashdata('jcogs_img_pro_field_debug_note', 'no_payload');
            }
            return;
        }

        $this->persistPayloads($entryId, $siteId, [$payload], [], $debugEnabled);
    }

    /**
     * Core persistence loop shared by entry-wide and Grid row saves.
     *
     * @param array<int, array<string, mixed>> $payloads
     * @param array<int, int> $compositeFieldIds
     */
    private function persistPayloads(
        int $entryId,
        int $siteId,
        array $payloads,
        array $compositeFieldIds,
        bool $debugEnabled
    ): void
    {
        $fieldSettings = ee('jcogs_img_pro_field:FieldSettingsService');
        $policy = ee('jcogs_img_pro_field:PolicyEnforcer');

        $debugRows = [];

        foreach ($payloads as $fieldIdRaw => $posted) {
            if (! is_array($posted)) {
                continue;
            }

            $fieldId = 0;
            if (isset($posted['field_id']) && is_numeric($posted['field_id'])) {
                $fieldId = (int) $posted['field_id'];
            } elseif (is_numeric($fieldIdRaw)) {
                $fieldId = (int) $fieldIdRaw;
            }

            if ($fieldId <= 0) {
                continue;
            }

            if (! empty($compositeFieldIds) && in_array($fieldId, $compositeFieldIds, true)) {
                $postedType = isset($posted['content_type']) ? strtolower(trim((string) $posted['content_type'])) : '';
                if ($postedType === '' || $postedType === 'channel') {
                    // Composite row payloads exist for this field; ignore top-level channel payloads.
                    continue;
                }
            }

            $context = $this->extractContext($posted, $entryId);
            $contentType = $context['content_type'];
            $rowId = $context['row_id'];
            $fluidFieldDataId = $context['fluid_field_data_id'];
            $blockId = $context['block_id'];
            $containerId = $context['container_id'];

            // On first save, Grid rows can be present before a stable row_id is assigned.
            // Persist with provisional null row_id; display-time migration can later rebind
            // the row via file/context once row_id becomes available.

            $fileId = $this->resolvePostedFileId($posted, $fieldId);

            if ($contentType === 'grid' && $rowId === null && $fileId > 0) {
                $resolvedRowId = $this->resolveGridRowIdFromDatabase($entryId, $containerId, $fieldId, $fileId);
                if ($resolvedRowId !== null) {
                    $rowId = $resolvedRowId;
                }
            }

            // No file selected: remove any saved usage row.
            if ($fileId <= 0) {
                // Composite contexts can have transient file-id resolution gaps on first save.
                // Do not delete existing rows in that case; allow staged/direct payload replay
                // to resolve on the same or next save cycle without data loss.
                if (in_array($contentType, ['grid', 'fluid', 'bloqs'], true)) {
                    continue;
                }

                $builder = ee()->db
                    ->where('site_id', $siteId)
                    ->where('entry_id', $entryId)
                    ->where('field_id', $fieldId)
                    ->where('content_type', $contentType);
                $this->applyContextFilters($builder, $rowId, $fluidFieldDataId, $blockId);
                $builder->delete('jcogs_img_pro_field_usages');
                continue;
            }

            $settings = is_object($fieldSettings) ? $fieldSettings->getForFieldId($fieldId) : [];
            if ($contentType === 'grid' && is_object($fieldSettings)) {
                $settings = $fieldSettings->getForGridColumnId($fieldId);
            }

            $fileError = is_object($policy) ? $policy->validateFileIdAgainstSettings($fileId, is_array($settings) ? $settings : []) : null;
            if ($fileError !== null) {
                ee()->db
                    ->where('site_id', $siteId)
                    ->where('entry_id', $entryId)
                    ->where('field_id', $fieldId)
                    ->delete('jcogs_img_pro_field_usages');
                continue;
            }

            $existingMeta = $this->fetchUsageRowMeta($siteId, $entryId, $fieldId, $contentType, $rowId, $fluidFieldDataId, $blockId);
            $existingFileId = isset($existingMeta['file_id']) && is_numeric($existingMeta['file_id']) ? (int) $existingMeta['file_id'] : 0;
            $fileChanged = ($existingFileId > 0 && $existingFileId !== $fileId);

            $payload = $this->fetchUsagePayload($siteId, $entryId, $fieldId, $contentType, $rowId, $fluidFieldDataId, $blockId);
            if (! is_array($payload)) {
                $payload = [];
            }

            if ($fileChanged) {
                $payload = $this->clearImageSpecificOverrides($payload);
            }

            $requireAspectRatio = ((($settings['enable_crop'] ?? 'n') === 'y') && (($settings['require_aspect_ratio'] ?? 'n') === 'y'));

            if (array_key_exists('preset_id', $posted)) {
                $presetId = trim((string) $posted['preset_id']);
                if ($presetId === '') {
                    $payload['preset_id'] = 0;
                } elseif (is_numeric($presetId) && (int) $presetId > 0) {
                    $payload['preset_id'] = (int) $presetId;
                } else {
                    unset($payload['preset_id']);
                }
            }

            if (array_key_exists('focal_x', $posted)) {
                $focalX = trim((string) $posted['focal_x']);
                if ($focalX !== '') {
                    $payload['focal_x'] = (float) $focalX;
                } else {
                    unset($payload['focal_x']);
                }
            }

            if (array_key_exists('focal_y', $posted)) {
                $focalY = trim((string) $posted['focal_y']);
                if ($focalY !== '') {
                    $payload['focal_y'] = (float) $focalY;
                } else {
                    unset($payload['focal_y']);
                }
            }

            foreach (['crop', 'crop_mode', 'crop_focus_h', 'crop_focus_v', 'crop_offset_x', 'crop_offset_y', 'width', 'height'] as $k) {
                if (! array_key_exists($k, $posted)) {
                    continue;
                }
                $v = trim((string) $posted[$k]);
                if ($v !== '') {
                    $payload[$k] = $v;
                } else {
                    unset($payload[$k]);
                }
            }

            if (array_key_exists('crop_smart_scaling', $posted)) {
                $raw = trim((string) $posted['crop_smart_scaling']);
                if ($raw !== '') {
                    $css = strtolower(trim((string) $raw));
                    if ($css === 'y' || $css === '1' || $css === 'true') {
                        $payload['crop_smart_scaling'] = 'yes';
                    } elseif ($css === 'n' || $css === '0' || $css === 'false') {
                        $payload['crop_smart_scaling'] = 'no';
                    } elseif ($css === 'yes' || $css === 'no') {
                        $payload['crop_smart_scaling'] = $css;
                    }
                } else {
                    unset($payload['crop_smart_scaling']);
                }
            }

            if (array_key_exists('aspect_ratio', $posted)) {
                $aspect = trim((string) $posted['aspect_ratio']);
                if ($aspect === '') {
                    unset($payload['aspect_ratio']);
                } elseif ($aspect === '__inherit__') {
                    if ($requireAspectRatio) {
                        unset($payload['aspect_ratio']);
                    } else {
                        $payload['aspect_ratio'] = '__inherit__';
                    }
                } else {
                    $payload['aspect_ratio'] = $this->normalizeAspectRatioSetting($aspect);
                }
            }

            $rectKeysPresent = array_key_exists('crop_rect_left', $posted)
                || array_key_exists('crop_rect_top', $posted)
                || array_key_exists('crop_rect_width', $posted)
                || array_key_exists('crop_rect_height', $posted);

            if ($rectKeysPresent) {
                $left = isset($posted['crop_rect_left']) ? trim((string) $posted['crop_rect_left']) : '';
                $top = isset($posted['crop_rect_top']) ? trim((string) $posted['crop_rect_top']) : '';
                $width = isset($posted['crop_rect_width']) ? trim((string) $posted['crop_rect_width']) : '';
                $height = isset($posted['crop_rect_height']) ? trim((string) $posted['crop_rect_height']) : '';

                if ($left !== '' && $top !== '' && $width !== '' && $height !== ''
                    && is_numeric($left) && is_numeric($top) && is_numeric($width) && is_numeric($height)
                ) {
                    $payload['crop_rect'] = [
                        'left' => (string) $left,
                        'top' => (string) $top,
                        'width' => (string) $width,
                        'height' => (string) $height,
                    ];
                } else {
                    unset($payload['crop_rect']);
                }
            }

            $debugIndex = null;
            if ($debugEnabled) {
                $debugRows[] = [
                    'entry_id' => $entryId,
                    'field_id' => $fieldId,
                    'content_type' => $contentType,
                    'container_id' => $containerId,
                    'row_id' => $rowId,
                    'file_value' => isset($posted['file_value']) ? (string) $posted['file_value'] : '',
                    'file_id' => $fileId,
                    'crop_rect' => $rectKeysPresent ? 'yes' : 'no',
                    'ad_files' => '',
                    'ad_debug' => '',
                    'ad_saved' => '',
                    'ad_enabled_setting' => (string) ($settings['enable_art_direction'] ?? ''),
                    'ad_pre_policy' => '',
                    'ad_post_policy' => '',
                    'ad_allowed_dirs' => (string) ($settings['allowed_directories'] ?? ''),
                ];
                $debugIndex = count($debugRows) - 1;
            }

            $adDirty = false;
            if (array_key_exists('art_direction_dirty', $posted)) {
                $rawDirty = strtolower(trim((string) $posted['art_direction_dirty']));
                $adDirty = ($rawDirty === '1' || $rawDirty === 'y' || $rawDirty === 'yes' || $rawDirty === 'true' || $rawDirty === 'on');
            }

            $rowPost = null;
            if (isset($posted['_row_post']) && is_array($posted['_row_post'])) {
                $rowPost = $posted['_row_post'];
            }

            $hasAdInputs = array_key_exists('art_direction_files', $posted)
                || $this->hasAdPickerInputs($fieldId, $rowPost);

            if ($contentType === 'grid' && $hasAdInputs) {
                $adSetting = strtolower(trim((string) ($settings['enable_art_direction'] ?? 'n')));
                if ($adSetting !== 'y') {
                    // Grid column settings may be missing; allow AD when the UI submitted inputs.
                    $settings['enable_art_direction'] = 'y';
                }
            }

            if ($hasAdInputs) {
                if ($debugEnabled && $debugIndex !== null && isset($debugRows[$debugIndex])) {
                    $debugRows[$debugIndex]['ad_debug'] = $this->collectAdDebugSummary($fieldId, $posted, $rowPost);
                }
                $files = $this->extractArtDirectionFilesFromPost($fieldId, $posted, is_array($settings) ? $settings : [], $rowPost);
                if (! empty($files)) {
                    if (! isset($payload['art_direction']) || ! is_array($payload['art_direction'])) {
                        $payload['art_direction'] = [];
                    }
                    $payload['art_direction']['files'] = $files;
                    if ($debugEnabled && $debugIndex !== null && isset($debugRows[$debugIndex])) {
                        $debugRows[$debugIndex]['ad_files'] = (string) count($files);
                        $debugRows[$debugIndex]['ad_pre_policy'] = (string) count($files);
                    }
                } elseif ($adDirty) {
                    // The UI was interacted with, but no alternates remain.
                    // Treat this as an explicit clear.
                    unset($payload['art_direction']);
                    if ($debugEnabled && $debugIndex !== null && isset($debugRows[$debugIndex])) {
                        $debugRows[$debugIndex]['ad_files'] = '0';
                        $debugRows[$debugIndex]['ad_pre_policy'] = '0';
                    }
                }
            }

            // Policy enforcement (defence-in-depth).
            try {
                if (is_object($policy)) {
                    $payload = $policy->sanitiseUsagePayload(is_array($payload) ? $payload : [], is_array($settings) ? $settings : []);

                    if (isset($payload['art_direction']) && is_array($payload['art_direction'])
                        && isset($payload['art_direction']['files']) && is_array($payload['art_direction']['files'])) {
                        $clean = [];
                        foreach ($payload['art_direction']['files'] as $media => $fid) {
                            $fid = is_numeric($fid) ? (int) $fid : 0;
                            if ($fid <= 0) {
                                continue;
                            }
                            $err = $policy->validateFileIdAgainstSettings($fid, is_array($settings) ? $settings : []);
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
                }
            } catch (\Throwable $e) {
                // Fail safe: do not block entry saves.
            }

            if ($debugEnabled && $debugIndex !== null && isset($debugRows[$debugIndex])) {
                if (isset($payload['art_direction']) && is_array($payload['art_direction'])
                    && isset($payload['art_direction']['files']) && is_array($payload['art_direction']['files'])) {
                    $debugRows[$debugIndex]['ad_saved'] = (string) count($payload['art_direction']['files']);
                    $debugRows[$debugIndex]['ad_post_policy'] = (string) count($payload['art_direction']['files']);
                } else {
                    $debugRows[$debugIndex]['ad_saved'] = '0';
                    $debugRows[$debugIndex]['ad_post_policy'] = '0';
                }
            }

            // Extension point for companion add-ons to persist additional per-entry intent.
            // Example: metadata/EXIF companion can store selections under its own namespace key.
            try {
                if (isset(ee()->extensions) && ee()->extensions->active_hook('jcogs_img_pro_field_mutate_usage_payload')) {
                    $hook_context = [
                        'site_id' => (int) $siteId,
                        'entry_id' => (int) $entryId,
                        'field_id' => (int) $fieldId,
                        'file_id' => (int) $fileId,
                        'settings' => is_array($settings) ? $settings : [],
                    ];
                    $maybe = ee()->extensions->call('jcogs_img_pro_field_mutate_usage_payload', $payload, $posted, $hook_context);
                    if ($maybe !== false && is_array($maybe)) {
                        $payload = $maybe;
                    }
                }
            } catch (\Throwable $e) {
                // Fail safe: never block entry saves.
            }

            if (empty($payload)) {
                $builder = ee()->db
                    ->where('site_id', $siteId)
                    ->where('entry_id', $entryId)
                    ->where('field_id', $fieldId)
                    ->where('content_type', $contentType);
                $this->applyContextFilters($builder, $rowId, $fluidFieldDataId, $blockId);
                $builder->delete('jcogs_img_pro_field_usages');
                continue;
            }

            $this->upsertUsageRow(
                $siteId,
                $entryId,
                $fieldId,
                $fileId,
                $payload,
                $contentType,
                $containerId,
                $rowId,
                $fluidFieldDataId,
                $blockId
            );
        }

        if ($debugEnabled) {
            ee()->session->set_flashdata('jcogs_img_pro_field_debug', $debugRows);
        }
    }
    /**
     * Extract art direction file ids from posted payloads.
     *
     * @return array<string, int>
     */
    private function extractArtDirectionFilesFromPost(int $fieldId, array $posted, array $settings, ?array $rowPost = null): array
    {
        $raw = $posted['art_direction_files'] ?? null;

        if (is_string($raw) && $raw !== '' && $raw[0] === '{') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }

        if (! is_array($raw)) {
            $raw = [];
        }

        $pickerRaw = [];
        $prefix = 'jcogs_img_pro_field_ad_' . (int) $fieldId . '_';
        $sources = [];
        if (is_array($_POST)) {
            $sources[] = $_POST;
        }
        if (is_array($rowPost)) {
            $sources[] = $rowPost;
        }

        foreach ($sources as $source) {
            foreach ($source as $k => $v) {
                if (! is_string($k) || strpos($k, $prefix) !== 0) {
                    continue;
                }
                $suffix = substr($k, strlen($prefix));
                if (! is_numeric($suffix)) {
                    continue;
                }
                $idx = (int) $suffix;
                if ($idx <= 0) {
                    continue;
                }
                $fid = $this->resolveFileId($v);
                if ($fid > 0) {
                    $pickerRaw[$idx] = $fid;
                }
            }
        }

        if (! empty($pickerRaw)) {
            foreach ($pickerRaw as $idx => $fid) {
                $raw[$idx] = $fid;
            }
        }

        $rawClean = [];
        foreach ($raw as $k => $v) {
            $idx = is_numeric($k) ? (int) $k : 0;
            if ($idx <= 0) {
                continue;
            }
            $fid = $this->resolveFileId($v);
            if ($fid <= 0) {
                continue;
            }
            $rawClean[$idx] = $fid;
        }

        if (empty($rawClean)) {
            return [];
        }

        $rows = $this->getArtDirectionBreakpointsFromSettings($settings);
        $idxToMedia = [];
        foreach ($rows as $r) {
            $i = (int) ($r['index'] ?? 0);
            $m = isset($r['media']) ? (string) $r['media'] : '';
            if ($i > 0 && $m !== '') {
                $idxToMedia[$i] = $m;
            }
        }

        if (empty($idxToMedia) && isset($posted['art_direction_index_to_media'])) {
            $rawMap = $posted['art_direction_index_to_media'];
            if (is_string($rawMap) && $rawMap !== '' && $rawMap[0] === '{') {
                $decoded = json_decode($rawMap, true);
                if (is_array($decoded)) {
                    $rawMap = $decoded;
                }
            }
            if (is_array($rawMap)) {
                foreach ($rawMap as $k => $v) {
                    $idx = is_numeric($k) ? (int) $k : 0;
                    $media = is_scalar($v) ? trim((string) $v) : '';
                    if ($idx > 0 && $media !== '') {
                        $idxToMedia[$idx] = $media;
                    }
                }
            }
        }

        $files = [];
        foreach ($rawClean as $k => $v) {
            $idx = is_numeric($k) ? (int) $k : 0;
            if ($idx <= 0) {
                continue;
            }
            $fid = (int) $v;
            if ($fid <= 0) {
                continue;
            }
            if (isset($idxToMedia[$idx])) {
                $files[$idxToMedia[$idx]] = $fid;
            } else {
                // Fallback to numeric keys when breakpoint settings are unavailable.
                $files[(string) $idx] = $fid;
            }
        }

        return $files;
    }

    /**
     * Detect whether the art direction file pickers were posted.
     */
    private function hasAdPickerInputs(int $fieldId, ?array $rowPost = null): bool
    {
        $prefix = 'jcogs_img_pro_field_ad_' . (int) $fieldId . '_';

        $sources = [];
        if (is_array($_POST)) {
            $sources[] = $_POST;
        }
        if (is_array($rowPost)) {
            $sources[] = $rowPost;
        }

        foreach ($sources as $source) {
            foreach ($source as $k => $_) {
                if (! is_string($k)) {
                    continue;
                }
                if (strpos($k, $prefix) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Build a short debug summary of posted art direction picker inputs.
     *
     * @param array<string, mixed> $posted
     * @return string
     */
    private function collectAdDebugSummary(int $fieldId, array $posted, ?array $rowPost = null): string
    {
        $prefix = 'jcogs_img_pro_field_ad_' . (int) $fieldId . '_';
        $sources = [];
        if (is_array($_POST)) {
            $sources[] = $_POST;
        }
        if (is_array($rowPost)) {
            $sources[] = $rowPost;
        }

        $items = [];
        foreach ($sources as $source) {
            foreach ($source as $k => $v) {
                if (! is_string($k) || strpos($k, $prefix) !== 0) {
                    continue;
                }
                $val = $v;
                if (is_array($val)) {
                    $val = reset($val);
                }
                $fid = $this->resolveFileId($val);
                $raw = $this->stringifyDebugValue($val);
                $items[] = $k . '=' . ($fid > 0 ? $fid : '0') . ($raw !== '' ? ' (' . $raw . ')' : '');
                if (count($items) >= 6) {
                    break 2;
                }
            }
        }

        $storage = '';
        if (isset($posted['art_direction_files'])) {
            $storage = $this->stringifyDebugValue($posted['art_direction_files']);
        }

        $summary = '';
        if (! empty($items)) {
            $summary = implode(', ', $items);
        }
        if ($storage !== '') {
            $summary = ($summary !== '' ? $summary . ' | ' : '') . 'storage=' . $storage;
        }

        return $summary;
    }

    /**
     * Render a compact string representation for debug summaries.
     *
     * @param mixed $value
     */
    private function stringifyDebugValue($value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            $raw = trim($value);
            if ($raw === '') {
                return '';
            }
            if (strlen($raw) > 80) {
                $raw = substr($raw, 0, 77) . '...';
            }
            return $raw;
        }
        if (is_array($value)) {
            $json = json_encode($value);
            if (is_string($json) && $json !== '') {
                if (strlen($json) > 80) {
                    $json = substr($json, 0, 77) . '...';
                }
                return $json;
            }
            return 'array';
        }
        return gettype($value);
    }

    /**
     * Resolve art direction breakpoints from field settings.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getArtDirectionBreakpointsFromSettings(array $settings): array
    {
        $rows = $settings['art_direction_breakpoints'] ?? null;
        if (! is_array($rows) || empty($rows)) {
            return [];
        }

        $rows = $this->normaliseArtDirectionBreakpoints($rows);
        if (empty($rows)) {
            return [];
        }

        $out = [];
        $idx = 1;
        foreach ($rows as $r) {
            $media = isset($r['media']) ? trim((string) $r['media']) : '';
            if ($media === '') {
                continue;
            }

            $out[] = [
                'index' => $idx,
                'media' => $media,
                'preset_id' => (int) ($r['preset_id'] ?? 0),
            ];
            $idx++;
            if ($idx > 3) {
                break;
            }
        }

        return $out;
    }

    /**
     * Normalise art direction breakpoints to a consistent shape.
     *
     * @param mixed $rows
     * @return array<int, array<string, mixed>>
     */
    private function normaliseArtDirectionBreakpoints($rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        if (isset($rows['rows']) && is_array($rows['rows'])) {
            $rows = $rows['rows'];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rawBreakpoint = isset($row['breakpoint']) && is_scalar($row['breakpoint']) ? trim((string) $row['breakpoint']) : '';
            $rawMedia = isset($row['media']) && is_scalar($row['media']) ? trim((string) $row['media']) : '';

            $raw = ($rawBreakpoint !== '') ? $rawBreakpoint : $rawMedia;
            if ($raw === '') {
                continue;
            }

            $media = '';
            if ($rawBreakpoint !== '' && is_numeric($rawBreakpoint)) {
                $n = (int) $rawBreakpoint;
                if ($n <= 0) {
                    continue;
                }
                $media = '(min-width: ' . $n . 'px)';
            } else {
                $raw = preg_replace('/\s+/', ' ', $raw);
                $raw = trim((string) $raw);
                if ($raw === '' || strlen($raw) > 200) {
                    continue;
                }
                if (preg_match('/["\'\<\>\{\};]/', $raw)) {
                    continue;
                }
                $media = $raw;
            }

            $presetId = isset($row['preset_id']) ? trim((string) $row['preset_id']) : '';
            $presetId = (is_numeric($presetId) && (int) $presetId > 0) ? (int) $presetId : 0;

            $out[] = [
                'media' => $media,
                'preset_id' => $presetId,
            ];

            if (count($out) >= 3) {
                break;
            }
        }

        return $out;
    }

    /**
     * Normalize aspect ratio settings to a canonical value.
     */
    private function normalizeAspectRatioSetting(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if ($value === '__inherit__') {
            return '__inherit__';
        }

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
     * Remove overrides that should not carry across different source images.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function clearImageSpecificOverrides(array $payload): array
    {
        foreach ([
            'focal_x',
            'focal_y',
            'crop',
            'crop_mode',
            'crop_focus_h',
            'crop_focus_v',
            'crop_offset_x',
            'crop_offset_y',
            'crop_smart_scaling',
            'width',
            'height',
            'aspect_ratio',
            'crop_rect',
            'art_direction',
        ] as $key) {
            if (array_key_exists($key, $payload)) {
                unset($payload[$key]);
            }
        }

        return $payload;
    }

    /**
     * Resolve an EE file picker value to a numeric file ID.
     *
     * @param mixed $data
     */
    private function resolveFileId($data): int
    {
        if (empty($data)) {
            return 0;
        }

        if (is_array($data)) {
            if (isset($data['file_id']) && is_numeric($data['file_id'])) {
                return (int) $data['file_id'];
            }

            foreach ($data as $value) {
                if (is_numeric($value)) {
                    return (int) $value;
                }
                if (is_string($value) && $value !== '') {
                    $resolved = $this->resolveFileId($value);
                    if ($resolved > 0) {
                        return $resolved;
                    }
                }
                if (is_array($value) && ! empty($value)) {
                    $resolved = $this->resolveFileId($value);
                    if ($resolved > 0) {
                        return $resolved;
                    }
                }
            }

            return 0;
        }

        if (is_string($data)) {
            $trimmed = trim($data);
            if ($trimmed !== '' && $trimmed[0] === '{') {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    $resolved = $this->resolveFileId($decoded);
                    if ($resolved > 0) {
                        return $resolved;
                    }
                }
            }

            if (preg_match('/\{file:(\d+)(?::[^}]*)?\}/i', $trimmed, $m)) {
                $n = (int) ($m[1] ?? 0);
                if ($n > 0) {
                    return $n;
                }
            }
        }

        if (is_numeric($data)) {
            return (int) $data;
        }

        return 0;
    }

    /**
     * Resolve a posted value using a bracketed field name.
     *
     * @return mixed
     */
    private function getPostedValueByFieldName(string $name)
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $source = $_POST ?? null;
        if (! is_array($source)) {
            return null;
        }

        $parts = preg_split('/\[|\]/', $name);
        if (! is_array($parts) || empty($parts)) {
            return null;
        }

        $cur = $source;
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (is_array($cur) && array_key_exists($part, $cur)) {
                $cur = $cur[$part];
                continue;
            }
            return null;
        }

        return $cur;
    }

    /**
     * Fetch an existing usage payload for a field/context.
     *
     * @return array<string, mixed>
     */
    private function fetchUsagePayload(
        int $siteId,
        int $entryId,
        int $fieldId,
        string $contentType,
        ?int $rowId,
        ?int $fluidFieldDataId,
        ?int $blockId
    ): array
    {
        $builder = ee()->db
            ->select('usage_payload')
            ->from('jcogs_img_pro_field_usages')
            ->where('site_id', $siteId)
            ->where('entry_id', $entryId)
            ->where('field_id', $fieldId)
            ->where('content_type', $contentType);
        $this->applyContextFilters($builder, $rowId, $fluidFieldDataId, $blockId);

        $row = $builder->limit(1)->get()->row_array();
        $raw = $row['usage_payload'] ?? '';
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Insert or update the usage row for a field/context.
     */
    private function upsertUsageRow(
        int $siteId,
        int $entryId,
        int $fieldId,
        int $fileId,
        array $payload,
        string $contentType,
        ?int $containerId,
        ?int $rowId,
        ?int $fluidFieldDataId,
        ?int $blockId
    ): void
    {
        $now = (int) (ee()->localize->now ?? time());
        $memberId = null;
        if (isset(ee()->session) && isset(ee()->session->userdata['member_id'])) {
            $memberId = (int) ee()->session->userdata['member_id'];
        }

        if ($contentType === 'grid' && $rowId !== null) {
            $this->rebindUnresolvedGridUsageRow($siteId, $entryId, $fieldId, $fileId, $containerId, $rowId);
        }

        $builder = ee()->db
            ->select('id')
            ->from('jcogs_img_pro_field_usages')
            ->where('site_id', $siteId)
            ->where('entry_id', $entryId)
            ->where('field_id', $fieldId)
            ->where('content_type', $contentType);
        $this->applyContextFilters($builder, $rowId, $fluidFieldDataId, $blockId);
        $existing = $builder->limit(1)->get()->row_array();

        $row = [
            'site_id' => $siteId,
            'entry_id' => $entryId,
            'field_id' => $fieldId,
            'file_id' => $fileId,
            'content_type' => $contentType,
            'container_id' => $containerId,
            'row_id' => $rowId,
            'fluid_field_data_id' => $fluidFieldDataId,
            'block_id' => $blockId,
            'usage_payload' => json_encode($payload),
            'modified_date' => $now,
            'modified_by_member_id' => $memberId,
        ];

        if ($existing) {
            ee()->db
                ->where('id', (int) $existing['id'])
                ->update('jcogs_img_pro_field_usages', $row);
            return;
        }

        $row['created_date'] = $now;
        $row['created_by_member_id'] = $memberId;
        ee()->db->insert('jcogs_img_pro_field_usages', $row);
    }

    /**
     * Reset DB builder state when available (legacy CI driver) without assuming
     * a specific DB wrapper implementation.
     */
    private function resetDbBuilder(): void
    {
        if (! isset(ee()->db)) {
            return;
        }

        $db = ee()->db;
        if (! is_object($db)) {
            return;
        }

        if (is_callable([$db, 'reset_query'])) {
            $db->reset_query();
            return;
        }

        if (is_callable([$db, '_reset_select'])) {
            $db->_reset_select();
        }
        if (is_callable([$db, '_reset_write'])) {
            $db->_reset_write();
        }
        if (is_callable([$db, 'flush_cache'])) {
            $db->flush_cache();
        }
    }

    /**
     * Resolve a concrete Grid row_id from persisted Grid data on first save.
     */
    private function resolveGridRowIdFromDatabase(int $entryId, ?int $containerId, int $fieldId, int $fileId): ?int
    {
        $entryId = (int) $entryId;
        $containerId = is_numeric($containerId) ? (int) $containerId : 0;
        $fieldId = (int) $fieldId;
        $fileId = (int) $fileId;

        if ($entryId <= 0 || $containerId <= 0 || $fieldId <= 0 || $fileId <= 0) {
            return null;
        }

        $table = 'channel_grid_field_' . $containerId;
        $column = 'col_id_' . $fieldId;

        if (! ee()->db->table_exists($table) || ! ee()->db->field_exists($column, $table)) {
            return null;
        }

        $rows = ee()->db
            ->select('row_id, ' . $column)
            ->from($table)
            ->where('entry_id', $entryId)
            ->order_by('row_id', 'DESC')
            ->get()
            ->result_array();

        if (! is_array($rows) || empty($rows)) {
            return null;
        }

        $matches = [];
        foreach ($rows as $row) {
            $rid = isset($row['row_id']) && is_numeric($row['row_id']) ? (int) $row['row_id'] : 0;
            if ($rid <= 0) {
                continue;
            }

            $candidateFileId = $this->resolveFileId($row[$column] ?? null);
            if ($candidateFileId === $fileId) {
                $matches[] = $rid;
            }
        }

        if (count($matches) === 1) {
            return (int) $matches[0];
        }

        return null;
    }

    /**
     * Rebind a provisional Grid usage row (row_id NULL) to a resolved row_id.
     */
    private function rebindUnresolvedGridUsageRow(
        int $siteId,
        int $entryId,
        int $fieldId,
        int $fileId,
        ?int $containerId,
        int $resolvedRowId
    ): void {
        if ($resolvedRowId <= 0 || $fileId <= 0) {
            return;
        }

        $builder = ee()->db
            ->select('id')
            ->from('jcogs_img_pro_field_usages')
            ->where('site_id', $siteId)
            ->where('entry_id', $entryId)
            ->where('field_id', $fieldId)
            ->where('content_type', 'grid')
            ->where('file_id', $fileId)
            ->where('row_id IS NULL', null, false)
            ->where('fluid_field_data_id IS NULL', null, false)
            ->where('block_id IS NULL', null, false);

        if ($containerId !== null) {
            $builder->where('container_id', (int) $containerId);
        }

        $rows = $builder->limit(2)->get()->result_array();
        if (! is_array($rows) || count($rows) !== 1) {
            return;
        }

        $id = isset($rows[0]['id']) && is_numeric($rows[0]['id']) ? (int) $rows[0]['id'] : 0;
        if ($id <= 0) {
            return;
        }

        ee()->db
            ->where('id', $id)
            ->update('jcogs_img_pro_field_usages', [
                'row_id' => $resolvedRowId,
                'container_id' => $containerId,
            ]);
    }

    /**
     * Determine persistence context from a posted payload.
     *
     * @return array<string, mixed>
     */
    private function extractContext(array $posted, int $entryId): array
    {
        $contentType = 'channel';
        $rowId = null;
        $fluidFieldDataId = null;
        $blockId = null;

        $postedType = isset($posted['content_type']) ? strtolower(trim((string) $posted['content_type'])) : '';
        if ($postedType === 'blocks/1' || $postedType === 'bloqs/1' || $postedType === 'blocks') {
            $postedType = 'bloqs';
        }
        if (in_array($postedType, ['channel', 'grid', 'fluid', 'bloqs'], true)) {
            $contentType = $postedType;
        }

        if (isset($posted['row_id']) && is_numeric($posted['row_id'])) {
            $rowId = (int) $posted['row_id'];
            if ($rowId <= 0) {
                $rowId = null;
            }
        }
        if (isset($posted['fluid_field_data_id']) && is_numeric($posted['fluid_field_data_id'])) {
            $fluidFieldDataId = (int) $posted['fluid_field_data_id'];
            if ($fluidFieldDataId <= 0) {
                $fluidFieldDataId = null;
            }
        }
        if (isset($posted['block_id']) && is_numeric($posted['block_id'])) {
            $blockId = (int) $posted['block_id'];
            if ($blockId <= 0) {
                $blockId = null;
            }
        }

        $containerId = null;
        if (isset($posted['container_id']) && is_numeric($posted['container_id'])) {
            $containerId = (int) $posted['container_id'];
            if ($containerId <= 0) {
                $containerId = null;
            }
        }
        if ($containerId === null && $contentType === 'bloqs') {
            $fieldId = $posted['field_id'] ?? null;
            if (is_numeric($fieldId)) {
                $containerId = (int) $fieldId;
            }
        }
        if ($containerId === null && $contentType === 'channel') {
            $containerId = $entryId;
        }

        return [
            'content_type' => $contentType,
            'container_id' => $containerId,
            'row_id' => $rowId,
            'fluid_field_data_id' => $fluidFieldDataId,
            'block_id' => $blockId,
        ];
    }

    /**
     * Build a condensed snapshot of POST payload structure for debug output.
     *
     * @return array<string, mixed>
     */
    private function buildPostDebugSummary(): array
    {
        $summary = [
            'post_keys' => [],
            'field_id_keys' => [],
            'field_rows' => [],
            'ad_picker_keys' => [],
            'ad_picker_samples' => [],
            'ad_storage_fields' => [],
        ];

        if (! isset($_POST) || ! is_array($_POST)) {
            return $summary;
        }

        $keys = array_keys($_POST);
        $summary['post_keys'] = array_slice($keys, 0, 40);

        foreach ($keys as $key) {
            if (is_string($key) && preg_match('/^field_id_\d+$/', $key)) {
                $summary['field_id_keys'][] = $key;
            }
            if (is_string($key) && strpos($key, 'jcogs_img_pro_field_ad_') === 0) {
                $summary['ad_picker_keys'][] = $key;
                if (count($summary['ad_picker_samples']) < 6) {
                    $summary['ad_picker_samples'][] = $key . '=' . $this->stringifyDebugValue($_POST[$key] ?? null);
                }
            }
        }

        if (isset($_POST['jcogs_img_pro_field']) && is_array($_POST['jcogs_img_pro_field'])) {
            foreach ($_POST['jcogs_img_pro_field'] as $fid => $payload) {
                if (! is_numeric($fid) || ! is_array($payload)) {
                    continue;
                }
                if (! isset($payload['art_direction_files']) || ! is_array($payload['art_direction_files'])) {
                    continue;
                }
                $entries = [];
                foreach ($payload['art_direction_files'] as $k => $v) {
                    if (count($entries) >= 6) {
                        break;
                    }
                    $entries[] = (string) $k . '=' . $this->stringifyDebugValue($v);
                }
                $summary['ad_storage_fields'][(string) ((int) $fid)] = $entries;
                if (count($summary['ad_storage_fields']) >= 3) {
                    break;
                }
            }
        }

        foreach ($summary['field_id_keys'] as $fieldKey) {
            $entry = [
                'rows' => 0,
                'has_jcogs' => false,
                'row_keys' => [],
                'row_summaries' => [],
            ];
            $fieldData = $_POST[$fieldKey] ?? null;
            if (is_array($fieldData) && isset($fieldData['rows']) && is_array($fieldData['rows'])) {
                $entry['rows'] = count($fieldData['rows']);
                $rowIndex = 0;
                foreach ($fieldData['rows'] as $rowKey => $rowData) {
                    if (! is_array($rowData)) {
                        continue;
                    }
                    if (empty($entry['row_keys'])) {
                        $entry['row_keys'] = array_slice(array_keys($rowData), 0, 12);
                    }
                    $rowSummary = [
                        'row_key' => $rowKey,
                    ];
                    if (isset($rowData['row_id'])) {
                        $rowSummary['row_id'] = $rowData['row_id'];
                    }
                    if (array_key_exists('jcogs_img_pro_field', $rowData)) {
                        $entry['has_jcogs'] = true;
                        $jcogs = $rowData['jcogs_img_pro_field'];
                        $rowSummary['jcogs_type'] = is_array($jcogs) ? 'array' : gettype($jcogs);
                        if (is_string($jcogs) && $jcogs !== '' && $jcogs[0] === '{') {
                            $decoded = json_decode($jcogs, true);
                            if (is_array($decoded)) {
                                $jcogs = $decoded;
                                $rowSummary['jcogs_type'] = 'json';
                            }
                        }
                        if (is_array($jcogs)) {
                            $rowSummary['jcogs_keys'] = array_slice(array_keys($jcogs), 0, 12);
                            if (isset($jcogs['context']) && is_array($jcogs['context'])) {
                                $context = $jcogs['context'];
                                if (isset($context['row_id'])) {
                                    $rowSummary['jcogs_row_id'] = $context['row_id'];
                                }
                                if (isset($context['field_id'])) {
                                    $rowSummary['jcogs_field_id'] = $context['field_id'];
                                }
                            }
                        }
                    }
                    $entry['row_summaries'][] = $rowSummary;
                    $rowIndex++;
                    if ($rowIndex >= 5) {
                        break;
                    }
                }
            }
            $summary['field_rows'][$fieldKey] = $entry;
        }

        return $summary;
    }

    /**
     * Extract a single Grid row payload from the raw POST.
     *
     * @return array<string, mixed>|null
     */
    private function extractGridRowPayloadFromPost(int $fieldId, int $rowId, ?int $containerId = null): ?array
    {
        if ($fieldId <= 0 || $rowId <= 0) {
            return null;
        }

        if (! isset($_POST) || ! is_array($_POST)) {
            return null;
        }

        $roots = [];
        if ($containerId && isset($_POST['field_id_' . $containerId]) && is_array($_POST['field_id_' . $containerId])) {
            $roots['field_id_' . $containerId] = $_POST['field_id_' . $containerId];
        }

        if (empty($roots)) {
            foreach ($_POST as $rootKey => $rootVal) {
                if (! is_string($rootKey) || ! is_array($rootVal)) {
                    continue;
                }
                if (! preg_match('/^field_id_\d+$/', $rootKey)) {
                    continue;
                }
                $roots[$rootKey] = $rootVal;
            }
        }

        foreach ($roots as $rootKey => $rootVal) {
            if (! isset($rootVal['rows']) || ! is_array($rootVal['rows'])) {
                continue;
            }

            $matchContainerId = $containerId;
            if (! $matchContainerId && preg_match('/^field_id_(\d+)$/', $rootKey, $m)) {
                $matchContainerId = (int) ($m[1] ?? 0);
            }

            foreach ($rootVal['rows'] as $rowKey => $rowData) {
                if (! is_array($rowData)) {
                    continue;
                }

                $rowKeyId = null;
                if (is_numeric($rowKey)) {
                    $rowKeyId = (int) $rowKey;
                } elseif (is_string($rowKey) && preg_match('/^row_id_(\d+)$/', $rowKey, $rm)) {
                    $rowKeyId = (int) ($rm[1] ?? 0);
                }

                $rowIdMatch = false;
                if (isset($rowData['row_id']) && is_numeric($rowData['row_id'])) {
                    $rowIdMatch = ((int) $rowData['row_id'] === $rowId);
                } elseif ($rowKeyId !== null) {
                    $rowIdMatch = ($rowKeyId === $rowId);
                }

                if (! $rowIdMatch) {
                    continue;
                }

                if (! isset($rowData['jcogs_img_pro_field'])) {
                    continue;
                }

                $rowPayload = $rowData['jcogs_img_pro_field'];
                if (is_string($rowPayload) && $rowPayload !== '' && $rowPayload[0] === '{') {
                    $decoded = json_decode($rowPayload, true);
                    if (is_array($decoded)) {
                        $rowPayload = $decoded;
                    }
                }

                if (! is_array($rowPayload)) {
                    return null;
                }

                $posted = null;
                if (isset($rowPayload[$fieldId]) && is_array($rowPayload[$fieldId])) {
                    $posted = $rowPayload[$fieldId];
                } elseif (isset($rowPayload['field_id']) && is_numeric($rowPayload['field_id']) && (int) $rowPayload['field_id'] === $fieldId) {
                    $posted = $rowPayload;
                }

                if (! is_array($posted)) {
                    return null;
                }

                $posted['field_id'] = (int) $fieldId;
                $posted['content_type'] = 'grid';
                $posted['container_id'] = $matchContainerId;
                $posted['row_id'] = $rowId;

                if (! isset($posted['file_value']) || (string) $posted['file_value'] === '') {
                    $inputName = isset($posted['file_input_name']) ? (string) $posted['file_input_name'] : '';
                    if ($inputName !== '' && array_key_exists($inputName, $rowData)) {
                        $posted['file_value'] = $rowData[$inputName];
                    }
                    if (! isset($posted['file_value']) || (string) $posted['file_value'] === '') {
                        $colKey = 'col_id_' . (int) $fieldId;
                        if (array_key_exists($colKey, $rowData)) {
                            $posted['file_value'] = $rowData[$colKey];
                        }
                    }
                }

                $posted['_row_post'] = $rowData;

                return $posted;
            }
        }

        return null;
    }

    /**
     * Extract composite publish payloads (Grid/Fluid) from the raw POST.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractCompositePayloadsFromPost(): array
    {
        if (! isset($_POST) || ! is_array($_POST)) {
            return [];
        }

        $out = [];

        foreach ($_POST as $rootKey => $rootVal) {
            if (! is_string($rootKey) || ! is_array($rootVal)) {
                continue;
            }

            if (! preg_match('/^field_id_(\d+)$/', $rootKey, $m)) {
                continue;
            }

            $containerId = (int) ($m[1] ?? 0);
            if ($containerId <= 0) {
                continue;
            }

            if (! isset($rootVal['rows']) || ! is_array($rootVal['rows'])) {
                continue;
            }

            foreach ($rootVal['rows'] as $rowKey => $rowData) {
                if (! is_array($rowData)) {
                    continue;
                }

                if (! isset($rowData['jcogs_img_pro_field'])) {
                    continue;
                }

                $rowPayload = $rowData['jcogs_img_pro_field'];
                if (is_string($rowPayload) && $rowPayload !== '' && $rowPayload[0] === '{') {
                    $decoded = json_decode($rowPayload, true);
                    if (is_array($decoded)) {
                        $rowPayload = $decoded;
                    }
                }

                if (! is_array($rowPayload)) {
                    continue;
                }

                foreach ($rowPayload as $fieldId => $posted) {
                    if (! is_numeric($fieldId) || ! is_array($posted)) {
                        continue;
                    }

                    $posted['field_id'] = (int) $fieldId;
                    $postedType = isset($posted['content_type']) ? strtolower(trim((string) $posted['content_type'])) : '';
                    if ($postedType === 'blocks/1' || $postedType === 'bloqs/1' || $postedType === 'blocks') {
                        $postedType = 'bloqs';
                    }
                    if ($postedType === 'file_grid' || $postedType === 'filegrid') {
                        $postedType = 'grid';
                    }
                    $posted['content_type'] = in_array($postedType, ['grid', 'fluid', 'bloqs', 'channel'], true)
                        ? $postedType
                        : 'grid';
                    $posted['container_id'] = $containerId;

                    if (! isset($posted['row_id']) || ! is_numeric($posted['row_id'])) {
                        if (is_numeric($rowKey)) {
                            $posted['row_id'] = (int) $rowKey;
                        } elseif (is_string($rowKey) && preg_match('/^row_id_(\d+)$/', $rowKey, $rm)) {
                            $posted['row_id'] = (int) ($rm[1] ?? 0);
                        } elseif (isset($rowData['row_id']) && is_numeric($rowData['row_id'])) {
                            $posted['row_id'] = (int) $rowData['row_id'];
                        }
                    }

                    if (! isset($posted['file_value']) || (string) $posted['file_value'] === '') {
                        $inputName = isset($posted['file_input_name']) ? (string) $posted['file_input_name'] : '';
                        if ($inputName !== '' && array_key_exists($inputName, $rowData)) {
                            $posted['file_value'] = $rowData[$inputName];
                        }
                        if (! isset($posted['file_value']) || (string) $posted['file_value'] === '') {
                            $colKey = 'col_id_' . (int) $fieldId;
                            if (array_key_exists($colKey, $rowData)) {
                                $posted['file_value'] = $rowData[$colKey];
                            }
                        }
                    }

                    $posted['_row_post'] = $rowData;
                    $out[] = $posted;
                }
            }
        }

        return $out;
    }

    /**
     * Check whether payloads contain at least one valid field entry.
     *
     * @param array<int, mixed> $payloads
     */
    private function hasValidPayloads(array $payloads): bool
    {
        foreach ($payloads as $value) {
            if (! is_array($value)) {
                continue;
            }
            if (isset($value['field_id']) && is_numeric($value['field_id'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract field IDs from composite payloads.
     *
     * @param array<int, array<string, mixed>> $composite
     * @return array<int, int>
     */
    private function extractCompositeFieldIds(array $composite): array
    {
        if (empty($composite)) {
            return [];
        }

        $ids = [];
        foreach ($composite as $posted) {
            if (! is_array($posted)) {
                continue;
            }
            $fieldId = $posted['field_id'] ?? null;
            if (is_numeric($fieldId)) {
                $ids[] = (int) $fieldId;
            }
        }

        if (empty($ids)) {
            return [];
        }

        return array_values(array_unique($ids));
    }

    /**
     * De-duplicate payloads by field/context, preferring entries with file IDs.
     *
     * @param array<int, array<string, mixed>> $payloads
     * @return array<int, array<string, mixed>>
     */
    private function dedupePayloadsByContext(array $payloads, int $entryId): array
    {
        $deduped = [];

        foreach ($payloads as $posted) {
            if (! is_array($posted)) {
                continue;
            }

            $fieldId = 0;
            if (isset($posted['field_id']) && is_numeric($posted['field_id'])) {
                $fieldId = (int) $posted['field_id'];
            }
            if ($fieldId <= 0) {
                continue;
            }

            $context = $this->extractContext($posted, $entryId);
            $key = implode('|', [
                (string) $fieldId,
                (string) ($context['content_type'] ?? ''),
                (string) ($context['row_id'] ?? ''),
                (string) ($context['fluid_field_data_id'] ?? ''),
                (string) ($context['block_id'] ?? ''),
            ]);

            $fileId = $this->resolvePostedFileId($posted, $fieldId);

            if (! isset($deduped[$key])) {
                $deduped[$key] = [
                    'payload' => $posted,
                    'file_id' => $fileId,
                ];
                continue;
            }

            $existing = $deduped[$key];
            $existingFileId = (int) ($existing['file_id'] ?? 0);

            if ($existingFileId > 0 && $fileId <= 0) {
                continue;
            }

            if ($fileId > 0 && $existingFileId <= 0) {
                $deduped[$key] = [
                    'payload' => $posted,
                    'file_id' => $fileId,
                ];
                continue;
            }

            $payload = is_array($existing['payload'] ?? null) ? $existing['payload'] : [];
            $payload = array_merge($payload, $posted);

            if (isset($existing['payload']['file_value']) && (! isset($payload['file_value']) || (string) $payload['file_value'] === '')) {
                $payload['file_value'] = $existing['payload']['file_value'];
            }
            if (isset($existing['payload']['file_input_name']) && (! isset($payload['file_input_name']) || (string) $payload['file_input_name'] === '')) {
                $payload['file_input_name'] = $existing['payload']['file_input_name'];
            }

            $deduped[$key] = [
                'payload' => $payload,
                'file_id' => max($existingFileId, $fileId),
            ];
        }

        $out = [];
        foreach ($deduped as $row) {
            if (isset($row['payload']) && is_array($row['payload'])) {
                $out[] = $row['payload'];
            }
        }

        return $out;
    }

    /**
     * Resolve a posted file ID from payload or field inputs.
     *
     * @param array<string, mixed> $posted
     */
    private function resolvePostedFileId(array $posted, int $fieldId): int
    {
        $contentType = isset($posted['content_type']) ? strtolower(trim((string) $posted['content_type'])) : '';
        if ($contentType === 'channel' || $contentType === '') {
            $directFieldValue = $_POST['field_id_' . $fieldId] ?? ee()->input->post('field_id_' . $fieldId);
            $directFieldId = $this->resolveFileId($directFieldValue);
            if ($directFieldId > 0) {
                return $directFieldId;
            }
        }

        $fileValue = '';
        if (array_key_exists('file_value', $posted)) {
            $fileValue = (string) $posted['file_value'];
        }
        if ($fileValue === '' && array_key_exists('file_input_name', $posted)) {
            $fromInput = $this->getPostedValueByFieldName((string) $posted['file_input_name']);
            if ($fromInput !== null) {
                $fileValue = is_scalar($fromInput) ? (string) $fromInput : '';
            }
        }

        if ($fileValue === '' && isset($posted['_row_post']) && is_array($posted['_row_post'])) {
            $rowPost = $posted['_row_post'];

            if (array_key_exists('file_input_name', $posted)) {
                $inputName = (string) $posted['file_input_name'];
                if ($inputName !== '' && array_key_exists($inputName, $rowPost)) {
                    $candidate = $rowPost[$inputName];
                    $fileValue = is_scalar($candidate) ? (string) $candidate : '';
                }
            }

            if ($fileValue === '') {
                $colKey = 'col_id_' . $fieldId;
                if (array_key_exists($colKey, $rowPost)) {
                    $candidate = $rowPost[$colKey];
                    $fileValue = is_scalar($candidate) ? (string) $candidate : '';
                }
            }
        }

        $fileId = $this->resolveFileId($fileValue);
        if ($fileId <= 0) {
            $fileId = $this->resolveFileId($_POST['field_id_' . $fieldId] ?? ee()->input->post('field_id_' . $fieldId));
        }

        return $fileId;
    }

    /**
     * Apply context filters for composite usage rows.
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
}
