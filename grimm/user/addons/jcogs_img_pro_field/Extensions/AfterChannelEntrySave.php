<?php

/**
 * JCOGS Image Pro Field - AfterChannelEntrySave
 *=============================================
 * ExpressionEngine extension hook handler.
 *
 * Persists per-entry editor overrides (crop, focal, art direction, etc.)
 * on normal channel entry saves.
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

namespace JCOGSDesign\JcogsImgProField\Extensions;

use ExpressionEngine\Service\Addon\Controllers\Extension\AbstractRoute;
use JCOGSDesign\JcogsImgProField\Service\ServiceCache;

/**
 * After-entry-save hook implementation.
 */
class AfterChannelEntrySave extends AbstractRoute
{
    /**
     * Persist posted overrides when an entry is saved.
     *
     * Signature varies across EE versions/contexts; accept optional args.
     */
    public function process($entry, $values = null, $modified = null): void
    {
        $debugEnabled = (string) ee()->input->get_post('jcogs_img_pro_field_debug') === '1';

        $entryId = null;
        if (is_object($entry)) {
            if (method_exists($entry, 'getId')) {
                $entryId = (int) $entry->getId();
            } elseif (isset($entry->entry_id)) {
                $entryId = (int) $entry->entry_id;
            }
        }
        if (empty($entryId)) {
            $entryId = (int) ee()->input->post('entry_id');
        }
        if ($entryId <= 0) {
            return;
        }

        $siteId = null;
        if (is_object($entry) && isset($entry->site_id)) {
            $siteId = (int) $entry->site_id;
        }

        // Only act when our publish UI payload is present.
        $all = ee()->input->post('jcogs_img_pro_field');
        if (! is_array($all)) {
            $all = $_POST['jcogs_img_pro_field'] ?? null;
        }
        if (! is_array($all) || empty($all)) {
            if (! $this->hasCompositePostPayload()) {
                try {
                    ServiceCache::usage_persistence()->applyStagedPayloadsFromCache($entryId, $siteId);
                } catch (\Throwable $e) {
                    // Fail safe: never block entry saves.
                }

                if ($debugEnabled) {
                    ee()->session->set_flashdata('jcogs_img_pro_field_debug_hook', [
                        'hit' => true,
                        'has_payload' => false,
                        'has_composite' => false,
                    ]);
                    ee()->session->set_flashdata('jcogs_img_pro_field_debug_post', $this->buildPostDebugSummary());
                }
                return;
            }
        }

        try {
            $alreadyPersistedInPostSave = ((string) (ee()->input->post('jcogs_img_pro_field_persisted_in_post_save') ?? '') === '1')
                || ((string) ($_POST['jcogs_img_pro_field_persisted_in_post_save'] ?? '') === '1');
            $alreadyPersistedInGridPostSave = ((string) (ee()->input->post('jcogs_img_pro_field_persisted_in_grid_post_save') ?? '') === '1')
                || ((string) ($_POST['jcogs_img_pro_field_persisted_in_grid_post_save'] ?? '') === '1');

            if ($debugEnabled) {
                ee()->session->set_flashdata('jcogs_img_pro_field_debug_hook', [
                    'hit' => true,
                    'has_payload' => (is_array($all) && ! empty($all)),
                    'has_composite' => $this->hasCompositePostPayload(),
                    'entry_id' => $entryId,
                    'post_save_persisted' => $alreadyPersistedInPostSave,
                    'grid_post_save_persisted' => $alreadyPersistedInGridPostSave,
                ]);
                ee()->session->set_flashdata('jcogs_img_pro_field_debug_post', $this->buildPostDebugSummary());
            }

            if (! $alreadyPersistedInPostSave && ! $alreadyPersistedInGridPostSave) {
                ServiceCache::usage_persistence()->persistFromPost($entryId, $siteId);
            }
            ServiceCache::usage_persistence()->applyStagedPayloadsFromCache($entryId, $siteId);

            $versioning_enabled = false;
            if (is_object($entry)) {
                if (isset($entry->versioning_enabled)) {
                    $versioning_enabled = ($entry->versioning_enabled === true || $entry->versioning_enabled === 'y');
                }
                if (! $versioning_enabled && isset($entry->Channel) && isset($entry->Channel->enable_versioning)) {
                    $versioning_enabled = ($entry->Channel->enable_versioning === true || $entry->Channel->enable_versioning === 'y');
                }
            }

            if ($versioning_enabled) {
                $versioning = ServiceCache::usage_versioning();
                $version_id = $this->resolveVersionIdForSnapshot($entry, $entryId);
                if ($version_id <= 0) {
                    $version_id = $versioning->getLatestVersionId($entryId);
                }
                if ($version_id > 0) {
                    $versioning->snapshotEntryUsage($entryId, $version_id, $siteId);
                }
            }
        } catch (\Throwable $e) {
            // Fail safe: never block entry saves.
        }
    }

    /**
     * Detect composite publish payloads embedded in Grid/Fluid/Bloqs posts.
     */
    private function hasCompositePostPayload(): bool
    {
        if (! isset($_POST) || ! is_array($_POST)) {
            return false;
        }

        foreach ($_POST as $key => $value) {
            if (! is_string($key) || ! is_array($value)) {
                continue;
            }
            if (! preg_match('/^field_id_\d+$/', $key)) {
                continue;
            }
                if (! isset($value['rows']) || ! is_array($value['rows'])) {
                continue;
            }
            foreach ($value['rows'] as $row) {
                if (is_array($row) && isset($row['jcogs_img_pro_field'])) {
                    return true;
                }
            }
        }

        return false;
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
     * Resolve the current version ID from the saved entry context when available.
     */
    private function resolveVersionIdForSnapshot($entry, int $entryId): int
    {
        if (is_object($entry)) {
            if (isset($entry->version_id) && is_numeric($entry->version_id)) {
                return (int) $entry->version_id;
            }
            if (method_exists($entry, 'getProperty')) {
                try {
                    $value = $entry->getProperty('version_id');
                    if (is_numeric($value)) {
                        return (int) $value;
                    }
                } catch (\Throwable $e) {
                    // Ignore and continue fallback chain.
                }
            }
        }

        $requested = (int) ee()->input->get('version');
        if ($requested > 0) {
            return $requested;
        }

        $posted = (int) ee()->input->post('version_id');
        if ($posted > 0) {
            return $posted;
        }

        return 0;
    }
}
