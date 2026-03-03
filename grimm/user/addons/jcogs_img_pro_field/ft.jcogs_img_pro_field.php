<?php

use JCOGSDesign\JcogsImgProField\Service\ServiceCache;
if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * JCOGS Image Pro Field - Fieldtype
 *=================================
 * ExpressionEngine 7 fieldtype implementation.
 *
 * Stores the selected file_id as the field value and stores per-entry overrides
 * (“editorial intent”) in a separate usage table, then delegates rendering to
 * JCOGS Image Pro.
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

/**
 * Method index
 *============
 * Fieldtype lifecycle:
 * - __construct()                                              Load language + EE file field helper.
 * - validate()                                                 Validate posted data on publish.
 * - payload_has_crop_override()                                Detect crop override keys in a payload.
 * - install()                                                  Provide default settings for new fields.
 *                     
 * Field settings UI:                     
 * - display_settings()                                         Render field settings form.
 * - validate_settings()                                        Validate submitted field settings.
 * - save_settings()                                            Normalise and persist field settings.
 * - posted_setting_value()                                     Read posted settings with fallback.
 *                     
 * Template variables/tags:                     
 * - replace_tag()                                              Main template replacement dispatcher.
 * - replace_src()                                              Derived src.
 * - replace_srcset()                                           Derived srcset.
 * - replace_sizes()                                            Derived sizes.
 * - replace_file_id()                                          Resolved file_id.
 * - replace_original_url()                                     Original image URL.
 * - replace_preset_id()                                        Resolved preset_id.
 * - replace_preset()                                           Preset info.
 * - replace_aspect_ratio()                                     Aspect ratio (normalised).
 * - replace_aspect_ratio_raw()                                 Aspect ratio (raw).
 * - replace_focal_x() / replace_focal_y()                      Focal point values.
 * - replace_focal_x_pct() / replace_focal_y_pct()              Focal point as percentage.
 * - replace_alt()                                              Alt text.
 * - replace_decorative()                                       Decorative flag.
 * - replace_object_position()                                  CSS object-position.
 * - replace_crop_rect_left()/top()/width()/height()            Crop rectangle values.
 * - replace_width() / replace_height()                         Derived width/height.
 * - replace_crop_offset_x() / replace_crop_offset_y()          Crop offsets.
 * - replace_crop()                                             Crop string.
 * - replace_url()                                              Full derived URL.
 * - replace_img()                                              Render <img> / <picture>.
 * - replace_art_direction()                                    Tag-pair access to art-direction rows.
 * - build_template_context()                                   Build renderer context.
 * - tag_render_callbacks()                                     Build renderer callback map.
 * - build_default_srcset_string()                              Default srcset.
 * - build_field_default_renderer_params()                      Default renderer params.
 * - render_art_direction_picture()                             Render <picture> for AD.
 *                     
 * Publish/editor UI & persistence:                     
 * - display_field()                                            Render publish field UI.
 * - save()                                                     Store main selected file_id.
 * - post_save()                                                Persist overrides (service delegate).
 * - resolve_file_id()                                          Resolve file picker value.
 * - resolve_action_id()                                        Resolve EE action_id.
 *                     
 * Settings helpers:                     
 * - normalize_aspect_ratio_setting()                           Normalise aspect ratio input.
 * - parse_aspect_ratio_choices()                               Parse aspect ratio choice string.
 * - get_aspect_ratio_choices_from_field_settings()             Read aspect ratio choices.
 * - normalise_aspect_ratio_pairs_from_posted()                 Normalise aspect ratio grid.
 * - getAspectRatioMiniGrid()                                   Render aspect ratio mini-grid.
 * - normalise_srcset_widths_from_posted()                      Normalise srcset widths.
 * - getSrcsetWidthsMiniGrid()                                  Render srcset widths mini-grid.
 * - art_direction_media_to_display_value()                     Display-friendly media.
 * - normalise_art_direction_breakpoints_from_posted()          Normalise breakpoints.
 * - getArtDirectionBreakpointsMiniGrid()                       Render breakpoints mini-grid.
 * - get_art_direction_breakpoints_from_field_settings()        Read breakpoints.
 * - get_legacy_art_direction_default_preset_id_from_settings() Legacy default.
 * - describe_art_direction_media_for_editor()                  Describe breakpoint in UI.
 * - apply_default_art_direction_preset_to_payload()            Apply AD defaults.
 * - build_payload_for_art_direction_row()                      Build AD row payload.
 * - render_select_options()                                    Render select options.
 * - get_editor_preset_options()                                Presets for publish UI.
 * - get_preset_options()                                       Presets for templates.
 * - fetch_img_pro_presets()                                    Fetch presets.
 * - fetch_img_pro_presets_via_service()                        Fetch presets via service.
 * - fetch_img_pro_presets_via_db()                             Fetch presets via DB.
 */

/**
 * EE fieldtype class.
 */
class Jcogs_img_pro_field_ft extends EE_Fieldtype
{
    public $info = [
        'name'    => 'JCOGS Image Pro Field',
        'version' => JCOGS_IMG_PRO_FIELD_VERSION,
    ];

    public $defaultEvaluationRule = 'isNotEmpty';

    // Required for ExpressionEngine to treat this field as capable of variable-pair parsing
    // (i.e. {field}...{/field}). Without this, EE will often leave the pair unparsed.
    public $has_array_data = true;

    // Enables use in Fluid fields - context detection handled in resolveContextFluidFieldDataId()
    public $is_fluid_compatible = true;

    /**
     * Construct the fieldtype.
     *
     * Called by ExpressionEngine when the fieldtype is instantiated.
     */
    public function __construct()
    {
        parent::__construct();
        ee()->lang->loadfile('jcogs_img_pro_field');
        ee()->load->library('file_field');
    }

    /**
     * Validate posted field data on publish.
     *
     * Called by EE during entry save validation; enforces required crop and/or
     * aspect ratio rules when enabled.
     */
    public function validate($data)
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        $enable_crop          = (($settings['enable_crop'] ?? 'n') === 'y');
        $require_crop         = (($settings['require_crop'] ?? 'n') === 'y');
        $require_aspect_ratio = (($settings['require_aspect_ratio'] ?? 'n') === 'y');
        if (! $enable_crop || (! $require_crop && ! $require_aspect_ratio)) {
            return true;
        }

        $file_id = $this->resolve_file_id($data);
        if ($file_id <= 0) {
            // Some publish flows may not pass the updated file value as $data yet.
            // Fall back to the raw posted value from EE's file picker.
            $raw_main = ee()->input->post($this->field_name);
            if ($raw_main === null) {
                $raw_main = $_POST[$this->field_name] ?? null;
            }
            $file_id = $this->resolve_file_id($raw_main);
        }
        if ($file_id <= 0) {
            // No image selected; do not block entry saves.
            return true;
        }

        $field_id = (int) ($this->field_id ?: 0);
        if ($field_id <= 0) {
            return true;
        }

        $context = $this->resolveCompositeContext();
        $context_content_type = (string) ($context['content_type'] ?? 'channel');
        $context_row_id = isset($context['row_id']) && is_numeric($context['row_id']) ? (int) $context['row_id'] : null;
        $context_fluid_field_data_id = isset($context['fluid_field_data_id']) && is_numeric($context['fluid_field_data_id']) ? (int) $context['fluid_field_data_id'] : null;
        $context_block_id = isset($context['block_id']) && is_numeric($context['block_id']) ? (int) $context['block_id'] : null;
        $context_container_id = null;

        $entry_id = (int) ($this->content_id() ?: 0);
        if ($entry_id <= 0) {
            $entry_id = (int) ee()->input->post('entry_id');
        }

        // Crop UI requires an existing entry ID (preview/ACT) so do not block the first save.
        if ($entry_id <= 0) {
            return true;
        }

        $context = $this->resolveCompositeContext($entry_id);
        $context_container_id = isset($context['container_id']) && is_numeric($context['container_id'])
            ? (int) $context['container_id']
            : null;

        $all = ee()->input->post('jcogs_img_pro_field');
        if (! is_array($all)) {
            $all = $_POST['jcogs_img_pro_field'] ?? [];
        }

        $posted = $this->find_posted_payload_for_validation(
            $field_id,
            $file_id,
            $context_content_type,
            $context_row_id,
            $context_container_id,
            $context_fluid_field_data_id,
            $context_block_id,
            is_array($all) ? $all : []
        );

        $this->apply_context_overrides_from_posted_payload(
            $posted,
            $context_row_id,
            $context_fluid_field_data_id,
            $context_block_id
        );

        // Bloqs may validate unsaved/new blocks using placeholder IDs (e.g. blocks_new_block_*).
        // In that specific transient state, a validation failure can send Bloqs through its
        // validated-display path that expects numeric IDs.
        $has_transient_bloqs_block_id = $this->has_transient_bloqs_block_id_for_validation(
            $context_content_type,
            $context_block_id,
            $posted
        );

        // Defer strict required-crop/aspect validation only for transient placeholder block IDs.
        if ($has_transient_bloqs_block_id) {
            return true;
        }

        $has_crop = $this->payload_has_crop_override($posted);
        if (! $has_crop) {
            $crop_present = isset($posted['crop_present']) ? trim((string) $posted['crop_present']) : '';
            if ($crop_present !== '') {
                $has_crop = true;
            }
        }
        $existing = [];

        // If nothing was posted (or JS sync ran late), accept an existing saved crop.
        if (! $has_crop) {
            $site_id = (int) (ee()->config->item('site_id') ?: 1);
            if ($entry_id > 0) {
                $existing = $this->fetch_existing_payload_for_validation(
                    $site_id,
                    $entry_id,
                    $field_id,
                    $context_content_type,
                    $file_id,
                    $context_row_id,
                    $context_fluid_field_data_id,
                    $context_block_id
                );
                $has_crop = $this->payload_has_crop_override($existing);
            }
        }

        if (! $has_crop && $this->post_contains_crop_for_field($field_id, $_POST)) {
            $has_crop = true;
        }

        if ($require_crop && ! $has_crop) {
            return lang('jcogs_img_pro_field_validation_crop_required');
        }

        if ($require_aspect_ratio && $has_crop) {
            $default_aspect_ratio = $this->normalize_aspect_ratio_setting((string) ($settings['default_aspect_ratio'] ?? ''));
            if ($default_aspect_ratio === '') {
                $pairs = $settings['aspect_ratio_pairs'] ?? [];
                if (is_array($pairs) && count($pairs) === 1) {
                    foreach (array_keys($pairs) as $k) {
                        $default_aspect_ratio = $this->normalize_aspect_ratio_setting((string) $k);
                        break;
                    }
                }
            }

            if (! array_key_exists('aspect_ratio', $posted) && empty($existing)) {
                $site_id  = (int) (ee()->config->item('site_id') ?: 1);
                $existing = $this->fetch_existing_payload_for_validation(
                    $site_id,
                    $entry_id,
                    $field_id,
                    $context_content_type,
                    $file_id,
                    $context_row_id,
                    $context_fluid_field_data_id,
                    $context_block_id
                );
            }

            $raw = '';
            if (array_key_exists('aspect_ratio', $posted)) {
                $raw = trim((string) $posted['aspect_ratio']);
            }
            elseif (is_array($existing) && array_key_exists('aspect_ratio', $existing)) {
                $raw = trim((string) $existing['aspect_ratio']);
            }

            $effective = '';
            if ($raw !== '' && $raw !== '__inherit__') {
                $effective = $this->normalize_aspect_ratio_setting($raw);
            }
            else {
                $effective = $default_aspect_ratio;
            }

            if ($effective === '') {
                return lang('jcogs_img_pro_field_validation_aspect_ratio_required');
            }
        }

        return true;
    }

    private function find_posted_payload_for_validation(
        int $field_id,
        int $file_id,
        string $context_content_type,
        ?int $context_row_id,
        ?int $context_container_id,
        ?int $context_fluid_field_data_id,
        ?int $context_block_id,
        array $all
    ): array {
        $posted = $this->extract_composite_posted_payload_for_validation(
            $field_id,
            $context_content_type,
            $context_container_id,
            $context_row_id,
            $context_fluid_field_data_id,
            $context_block_id
        );

        if (! is_array($posted)) {
            $posted = [];
        }

        $payload_matches_validation_context = function (array $payload) use ($field_id, $file_id, $context_content_type, $context_row_id) {
            return $this->payload_matches_validation_context(
                $payload,
                $field_id,
                $file_id,
                $context_content_type,
                $context_row_id
            );
        };

        if (! empty($posted) && ! $payload_matches_validation_context($posted)) {
            $posted = [];
        }

        if (empty($posted)) {
            $posted = (isset($all[$field_id]) && is_array($all[$field_id])) ? $all[$field_id] : [];
            if (! empty($posted) && ! $payload_matches_validation_context($posted)) {
                $posted = [];
            }
        }
        if (empty($posted)) {
            $fallback = null;
            foreach ($all as $maybe) {
                if (! is_array($maybe)) {
                    continue;
                }
                $pid = $maybe['field_id'] ?? null;
                if (is_numeric($pid) && (int) $pid === $field_id) {
                    if ($payload_matches_validation_context($maybe)) {
                        $posted = $maybe;
                        break;
                    }
                    if ($fallback === null) {
                        $fallback = $maybe;
                    }
                }
            }
            if (empty($posted) && is_array($fallback)) {
                $posted = $fallback;
            }
        }

        if (empty($posted)) {
            $found = $this->find_crop_payload_in_post_for_validation($_POST, $field_id, $payload_matches_validation_context);
            if (is_array($found)) {
                $posted = $found;
            }
        }

        return is_array($posted) ? $posted : [];
    }

    private function payload_matches_validation_context(
        array $payload,
        int $field_id,
        int $file_id,
        string $context_content_type,
        ?int $context_row_id
    ): bool {
        $pid = $payload['field_id'] ?? null;
        if ($pid !== null && (! is_numeric($pid) || (int) $pid !== $field_id)) {
            return false;
        }

        $ptype = isset($payload['content_type']) ? $this->normalizeContentTypeForContext((string) $payload['content_type']) : '';
        if ($context_content_type === 'grid' && $ptype !== '' && $ptype !== 'grid') {
            return false;
        }

        if ($context_row_id !== null && isset($payload['row_id']) && is_numeric($payload['row_id']) && (int) $payload['row_id'] !== $context_row_id) {
            return false;
        }

        if ($file_id > 0) {
            $candidate_file = 0;
            if (array_key_exists('file_value', $payload)) {
                $candidate_file = $this->resolve_file_id($payload['file_value']);
            }
            if ($candidate_file <= 0 && array_key_exists('file_id', $payload)) {
                $candidate_file = $this->resolve_file_id($payload['file_id']);
            }
            if ($candidate_file > 0 && $candidate_file !== $file_id) {
                return false;
            }
        }

        return true;
    }

    private function find_crop_payload_in_post_for_validation($data, int $field_id, callable $payload_matches_validation_context)
    {
        if (! is_array($data)) {
            return null;
        }

        $hasCropKey = false;
        foreach ($this->validation_crop_keys() as $k) {
            if (array_key_exists($k, $data)) {
                $hasCropKey = true;
                break;
            }
        }
        if ($hasCropKey) {
            $pid = $data['field_id'] ?? null;
            if ($pid === null || (is_numeric($pid) && (int) $pid === $field_id)) {
                if ($payload_matches_validation_context($data)) {
                    return $data;
                }
            }
        }

        foreach ($data as $v) {
            if (! is_array($v)) {
                continue;
            }
            $found = $this->find_crop_payload_in_post_for_validation($v, $field_id, $payload_matches_validation_context);
            if (is_array($found)) {
                return $found;
            }
        }

        return null;
    }

    private function apply_context_overrides_from_posted_payload(
        array $posted,
        ?int &$context_row_id,
        ?int &$context_fluid_field_data_id,
        ?int &$context_block_id
    ): void {
        if (($context_row_id === null || $context_row_id <= 0) && isset($posted['row_id']) && is_numeric($posted['row_id'])) {
            $context_row_id = (int) $posted['row_id'];
            if ($context_row_id <= 0) {
                $context_row_id = null;
            }
        }
        if (($context_fluid_field_data_id === null || $context_fluid_field_data_id <= 0) && isset($posted['fluid_field_data_id']) && is_numeric($posted['fluid_field_data_id'])) {
            $context_fluid_field_data_id = (int) $posted['fluid_field_data_id'];
            if ($context_fluid_field_data_id <= 0) {
                $context_fluid_field_data_id = null;
            }
        }
        if (($context_block_id === null || $context_block_id <= 0) && isset($posted['block_id']) && is_numeric($posted['block_id'])) {
            $context_block_id = (int) $posted['block_id'];
            if ($context_block_id <= 0) {
                $context_block_id = null;
            }
        }
    }

    private function has_transient_bloqs_block_id_for_validation(string $context_content_type, ?int $context_block_id, array $posted): bool
    {
        if ($context_content_type !== 'bloqs' || $context_block_id !== null) {
            return false;
        }

        $block_id_candidates = [
            $posted['block_id'] ?? null,
            $this->settings['block_id'] ?? null,
            $this->settings['bloqs_block_id'] ?? null,
            $this->settings['blocks_block_id'] ?? null,
            $this->settings['grid_row_name'] ?? null,
        ];
        foreach ($block_id_candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }
            $candidate = trim($candidate);
            if ($candidate !== '' && preg_match('/^blocks_new_block_\d+$/', $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function fetch_existing_payload_for_validation(
        int $site_id,
        int $entry_id,
        int $field_id,
        string $context_content_type,
        int $file_id,
        ?int $context_row_id,
        ?int $context_fluid_field_data_id,
        ?int $context_block_id
    ): array {
        $existing = ServiceCache::usage_payload_maintenance()->fetchUsagePayload(
            $site_id,
            $entry_id,
            $field_id,
            $context_content_type,
            $context_row_id,
            $context_fluid_field_data_id,
            $context_block_id
        );

        if (empty($existing) && $file_id > 0) {
            $existing = $this->fetch_usage_payload_for_validation_fallback(
                $site_id,
                $entry_id,
                $field_id,
                $context_content_type,
                $file_id,
                $context_row_id,
                $context_fluid_field_data_id,
                $context_block_id
            );
        }

        return is_array($existing) ? $existing : [];
    }

    private function post_contains_crop_for_field(int $field_id, $data): bool
    {
        if (! is_array($data)) {
            return false;
        }

        $pid = $data['field_id'] ?? null;
        if ($pid !== null && (! is_numeric($pid) || (int) $pid !== $field_id)) {
            // Not our field payload.
        }
        else {
            foreach ($this->validation_crop_keys() as $k) {
                if (array_key_exists($k, $data) && trim((string) $data[$k]) !== '') {
                    return true;
                }
            }
        }

        foreach ($data as $v) {
            if ($this->post_contains_crop_for_field($field_id, $v)) {
                return true;
            }
        }

        return false;
    }

    private function validation_crop_keys(): array
    {
        return ['crop_present', 'crop', 'crop_offset_x', 'crop_offset_y', 'width', 'height', 'crop_rect_left', 'crop_rect_top', 'crop_rect_width', 'crop_rect_height'];
    }

    /**
     * Check whether a usage payload contains a crop override.
     *
     * Called by validate() (and other internal checks).
     */
    private function payload_has_crop_override(array $payload): bool
    {
        if (empty($payload)) {
            return false;
        }

        foreach (['crop', 'crop_offset_x', 'crop_offset_y', 'width', 'height'] as $k) {
            if (array_key_exists($k, $payload) && trim((string) $payload[$k]) !== '') {
                return true;
            }
        }

        // Posted publish payload uses flat keys; persisted payload uses nested crop_rect.
        $flat = ['crop_rect_left', 'crop_rect_top', 'crop_rect_width', 'crop_rect_height'];
        $all_present = true;
        $any_numeric = false;
        foreach ($flat as $k) {
            if (! array_key_exists($k, $payload) || trim((string) $payload[$k]) === '') {
                $all_present = false;
            }
            if (array_key_exists($k, $payload)) {
                $raw = trim((string) $payload[$k]);
                if ($raw !== '') {
                    $raw = rtrim($raw, "%");
                    if (is_numeric($raw)) {
                        $any_numeric = true;
                    }
                }
            }
        }
        if (
            $all_present
            && is_numeric((string) $payload['crop_rect_left'])
            && is_numeric((string) $payload['crop_rect_top'])
            && is_numeric((string) $payload['crop_rect_width'])
            && is_numeric((string) $payload['crop_rect_height'])
        ) {
            return true;
        }
        if ($any_numeric) {
            return true;
        }

        if (isset($payload['crop_rect']) && is_array($payload['crop_rect'])) {
            $r = $payload['crop_rect'];
            $all_rect = true;
            $any_rect = false;
            foreach (['left', 'top', 'width', 'height'] as $k) {
                if (! isset($r[$k]) || trim((string) $r[$k]) === '') {
                    $all_rect = false;
                    continue;
                }
                $raw = rtrim(trim((string) $r[$k]), "%");
                if (is_numeric($raw)) {
                    $any_rect = true;
                }
                if (! is_numeric($raw)) {
                    $all_rect = false;
                }
            }
            if ($all_rect || $any_rect) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract a row-aware composite payload for validation when available.
     *
     * Grid/File Grid rows can post field payloads nested inside row structures,
     * which are not always present in top-level jcogs_img_pro_field payloads.
     *
     * @return array<string, mixed>|null
     */
    private function extract_composite_posted_payload_for_validation(
        int $field_id,
        string $content_type,
        ?int $container_id,
        ?int $row_id,
        ?int $fluid_field_data_id,
        ?int $block_id
    ): ?array {
        if ($field_id <= 0) {
            return null;
        }

        if (! in_array($content_type, ['grid', 'bloqs'], true)) {
            return null;
        }

        if (! isset($_POST) || ! is_array($_POST)) {
            return null;
        }

        $roots = [];
        if ($container_id !== null && $container_id > 0) {
            $root_key = 'field_id_' . (int) $container_id;
            if (isset($_POST[$root_key]) && is_array($_POST[$root_key])) {
                $roots[$root_key] = $_POST[$root_key];
            }
        }

        if (empty($roots)) {
            foreach ($_POST as $root_key => $root_val) {
                if (! is_string($root_key) || ! is_array($root_val)) {
                    continue;
                }
                if (! preg_match('/^field_id_\d+$/', $root_key)) {
                    continue;
                }
                $roots[$root_key] = $root_val;
            }
        }

        foreach ($roots as $root_key => $root_val) {
            if (! isset($root_val['rows']) || ! is_array($root_val['rows'])) {
                continue;
            }

            $root_container_id = null;
            if (preg_match('/^field_id_(\d+)$/', (string) $root_key, $m)) {
                $root_container_id = (int) ($m[1] ?? 0);
            }

            foreach ($root_val['rows'] as $row_key => $row_data) {
                if (! is_array($row_data)) {
                    continue;
                }

                $candidate_row_id = null;
                if (isset($row_data['row_id']) && is_numeric($row_data['row_id'])) {
                    $candidate_row_id = (int) $row_data['row_id'];
                }
                elseif (is_numeric($row_key)) {
                    $candidate_row_id = (int) $row_key;
                }
                elseif (is_string($row_key) && preg_match('/^row_id_(\d+)$/', $row_key, $rm)) {
                    $candidate_row_id = (int) ($rm[1] ?? 0);
                }

                if ($content_type === 'grid') {
                    if ($row_id !== null && $row_id > 0 && $candidate_row_id !== $row_id) {
                        continue;
                    }
                }

                if ($content_type === 'bloqs') {
                    $candidate_block_id = null;
                    if (isset($row_data['block_id']) && is_numeric($row_data['block_id'])) {
                        $candidate_block_id = (int) $row_data['block_id'];
                    }
                    if ($candidate_block_id === null && $candidate_row_id !== null) {
                        $candidate_block_id = $candidate_row_id;
                    }
                    if ($block_id !== null && $block_id > 0 && $candidate_block_id !== $block_id) {
                        continue;
                    }
                }

                if (! isset($row_data['jcogs_img_pro_field'])) {
                    continue;
                }

                $row_payload = $row_data['jcogs_img_pro_field'];
                if (is_string($row_payload) && $row_payload !== '' && $row_payload[0] === '{') {
                    $decoded = json_decode($row_payload, true);
                    if (is_array($decoded)) {
                        $row_payload = $decoded;
                    }
                }
                if (! is_array($row_payload)) {
                    continue;
                }

                $posted = null;
                if (isset($row_payload[$field_id]) && is_array($row_payload[$field_id])) {
                    $posted = $row_payload[$field_id];
                }
                elseif (isset($row_payload['field_id']) && is_numeric($row_payload['field_id']) && (int) $row_payload['field_id'] === $field_id) {
                    $posted = $row_payload;
                }

                if (! is_array($posted)) {
                    continue;
                }

                $posted['field_id'] = $field_id;
                $posted['content_type'] = $content_type;
                $posted['container_id'] = $container_id ?? $root_container_id;
                if ($content_type === 'grid') {
                    $posted['row_id'] = $row_id ?? $candidate_row_id;
                } else {
                    $candidate_block_id = null;
                    if (isset($row_data['block_id']) && is_numeric($row_data['block_id'])) {
                        $candidate_block_id = (int) $row_data['block_id'];
                    }
                    if ($candidate_block_id === null && $candidate_row_id !== null) {
                        $candidate_block_id = $candidate_row_id;
                    }
                    $posted['block_id'] = $block_id ?? $candidate_block_id;
                    $posted['row_id'] = $row_id;
                }
                $posted['fluid_field_data_id'] = $fluid_field_data_id;
                if (! isset($posted['block_id'])) {
                    $posted['block_id'] = $block_id;
                }

                if (! isset($posted['file_value']) || trim((string) $posted['file_value']) === '') {
                    $input_name = isset($posted['file_input_name']) ? (string) $posted['file_input_name'] : '';
                    if ($input_name !== '' && array_key_exists($input_name, $row_data)) {
                        $posted['file_value'] = $row_data[$input_name];
                    }
                    if (! isset($posted['file_value']) || trim((string) $posted['file_value']) === '') {
                        $col_key = 'col_id_' . $field_id;
                        if (array_key_exists($col_key, $row_data)) {
                            $posted['file_value'] = $row_data[$col_key];
                        }
                    }
                }

                return $posted;
            }
        }

        return null;
    }

    /**
     * Fallback usage payload lookup for validation when EE does not provide full
     * composite row context in validate() callbacks.
     *
     * @return array<string, mixed>
     */
    private function fetch_usage_payload_for_validation_fallback(
        int $site_id,
        int $entry_id,
        int $field_id,
        string $content_type,
        int $file_id,
        ?int $row_id,
        ?int $fluid_field_data_id,
        ?int $block_id
    ): array {
        if ($site_id <= 0 || $entry_id <= 0 || $field_id <= 0 || $file_id <= 0) {
            return [];
        }

        try {
            $builder = ee()->db
                ->select('usage_payload')
                ->from('jcogs_img_pro_field_usages')
                ->where('site_id', $site_id)
                ->where('entry_id', $entry_id)
                ->where('field_id', $field_id)
                ->where('content_type', $content_type)
                ->where('file_id', $file_id);

            if ($row_id !== null) {
                $builder->where('row_id', $row_id);
            }
            if ($fluid_field_data_id !== null) {
                $builder->where('fluid_field_data_id', $fluid_field_data_id);
            }
            if ($block_id !== null) {
                $builder->where('block_id', $block_id);
            }

            $rows = $builder
                ->order_by('modified_date', 'DESC')
                ->order_by('id', 'DESC')
                ->limit(5)
                ->get()
                ->result_array();

            if (! is_array($rows) || empty($rows)) {
                return [];
            }

            $firstDecoded = [];
            foreach ($rows as $row) {
                $decoded = json_decode((string) ($row['usage_payload'] ?? ''), true);
                if (is_array($decoded)) {
                    if (empty($firstDecoded)) {
                        $firstDecoded = $decoded;
                    }
                    if ($this->payload_has_crop_override($decoded)) {
                        return $decoded;
                    }
                }
            }

            if (! empty($firstDecoded)) {
                return $firstDecoded;
            }
        }
        catch (\Throwable $e) {
            return [];
        }

        return [];
    }

    /**
     * Provide default settings when a field is installed.
     *
     * Called by EE when adding this fieldtype to a channel field.
     */
    public function install()
    {
        return [];
    }

    /**
     * Declare supported content types for composite fields.
     */
    public function accepts_content_type($name)
    {
        return in_array($name, ['channel', 'grid', 'fluid_field', 'bloqs/1', 'blocks/1'], true);
    }

    /**
     * Render the field settings UI.
     *
     * Called by EE in the Control Panel when configuring a field.
     *
     * @return array
     */
    public function display_settings($data)
    {
        $data = is_array($data) ? $data : [];

        $site_id = (int) (ee()->config->item('site_id') ?: 1);

        $settings = ServiceCache::settings_ui()->buildSettingsSections($data, $site_id);

        ServiceCache::assets()->enqueueCpSettingsAssets();

        static $settings_js_globals_added = false;
        if (! $settings_js_globals_added && REQ === 'CP') {
            $settings_js_globals_added = true;

            $script = '<script>'
                . 'window.JCOGS_IMG_PRO_FIELD = window.JCOGS_IMG_PRO_FIELD || {};'
                . 'window.JCOGS_IMG_PRO_FIELD.noneOption = ' . json_encode(lang('jcogs_img_pro_field_none_option')) . ';'
                . '</script>';

            try {
                if (isset(ee()->cp) && method_exists(ee()->cp, 'add_to_head')) {
                    ee()->cp->add_to_head($script);
                }
            }
            catch (\Throwable $e) {
                // no-op
            }
        }

        $settings_content_type = $this->normalizeContentTypeForContext((string) $this->content_type());
        if ($settings_content_type === 'grid') {
            return ['field_options' => $settings];
        }
        if ($settings_content_type === 'bloqs') {
            return ['field_options' => $settings];
        }

        return [
            'field_options_jcogs_img_pro_field' => [
                'label'    => 'field_options',
                'group'    => 'jcogs_img_pro_field',
                'settings' => $settings,
            ],
        ];
    }

    /**
     * Validate field settings before they are saved.
     *
     * Returning an EE Validation Result enables inline error messaging in the CP.
     */
    /**
     * Validate submitted settings.
     *
     * Called by EE before save_settings(); returns TRUE or a language key string.
     */
    public function validate_settings($data)
    {
        $data = is_array($data) ? $data : [];

        $posted = function (string $key, $fallback = null) use ($data) {
            return $this->posted_setting_value($data, $key, $fallback);
        };

        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::settings_ui()->validateSettings($data, $posted);
    }

    /**
     * Save/normalise settings from POST.
     *
     * Called by EE after validate_settings().
     */
    public function save_settings($data)
    {
        $data = is_array($data) ? $data : [];

        $posted = function (string $key, $fallback = null) use ($data) {
            return $this->posted_setting_value($data, $key, $fallback);
        };

        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::settings_ui()->saveSettings($data, $posted);
    }

    /**
     * Safely read a posted setting value with a fallback.
     *
     * Internal helper for save_settings().
     */
    private function posted_setting_value(array $data, string $key, $fallback = null)
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        try {
            if (isset(ee()->input)) {
                $v = ee()->input->post($key);
                if ($v !== null) {
                    return $v;
                }
            }
        }
        catch (\Throwable $e) {
            // Ignore.
        }

        if (isset($_POST[$key])) {
            return $_POST[$key];
        }

        // Some CP contexts nest field options under a group key.
        foreach (['field_options_jcogs_img_pro_field', 'field_options', 'field_settings', 'settings'] as $root) {
            if (isset($_POST[$root]) && is_array($_POST[$root]) && array_key_exists($key, $_POST[$root])) {
                return $_POST[$root][$key];
            }
        }

        return $fallback;
    }

    /**
     * Main template replacement entry-point.
     *
     * Called by EE template parsing (e.g. {field}, {field:src}, and tag-pairs).
     */
    public function replace_tag($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_tag($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for the derived URL (src).
     */
    public function replace_src($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_src($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for the derived srcset.
     */
    public function replace_srcset($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_srcset($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for the derived sizes attribute.
     */
    public function replace_sizes($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_sizes($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement exposing the resolved file_id.
     */
    public function replace_file_id($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_file_id($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for the original file URL.
     */
    public function replace_original_url($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_original_url($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for the resolved preset_id.
     */
    public function replace_preset_id($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_preset_id($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement exposing preset info.
     */
    public function replace_preset($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_preset($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for aspect_ratio (normalised).
     */
    public function replace_aspect_ratio($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_aspect_ratio($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for aspect_ratio (raw).
     */
    public function replace_aspect_ratio_raw($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_aspect_ratio_raw($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for focal_x.
     */
    public function replace_focal_x($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_focal_x($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for focal_x as a percentage.
     */
    public function replace_focal_x_pct($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_focal_x_pct($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for focal_y.
     */
    public function replace_focal_y($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_focal_y($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for focal_y as a percentage.
     */
    public function replace_focal_y_pct($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_focal_y_pct($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for alt text.
     */
    public function replace_alt($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_alt($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement exposing decorative flag.
     */
    public function replace_decorative($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_decorative($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for CSS object-position.
     */
    public function replace_object_position($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_object_position($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for crop_rect left.
     */
    public function replace_crop_rect_left($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_crop_rect_left($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for crop_rect top.
     */
    public function replace_crop_rect_top($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_crop_rect_top($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for crop_rect width.
     */
    public function replace_crop_rect_width($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_crop_rect_width($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for crop_rect height.
     */
    public function replace_crop_rect_height($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_crop_rect_height($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for derived width.
     */
    public function replace_width($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_width($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for derived height.
     */
    public function replace_height($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_height($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for crop_offset_x.
     */
    public function replace_crop_offset_x($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_crop_offset_x($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for crop_offset_y.
     */
    public function replace_crop_offset_y($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_crop_offset_y($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for crop string.
     */
    public function replace_crop($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_crop($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement for full derived URL.
     */
    public function replace_url($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_url($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Template replacement rendering an <img> (or <picture> when applicable).
     */
    public function replace_img($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_img($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    /**
     * Build a template context array for Image Pro rendering.
     *
     * Internal helper used by the replace_* methods.
     */
    private function build_template_context($data): array
    {
        static $cache = [];

        $site_id  = (int) (ee()->config->item('site_id') ?: 1);
        $version_id = $this->resolve_requested_version_id();
        $debug_enabled = $this->is_debug_enabled_for_request();
        $entry_id = 0;
        if (isset($this->row) && is_array($this->row) && isset($this->row['entry_id'])) {
            $entry_id = (int) $this->row['entry_id'];
        }
        elseif (method_exists($this, 'content_id')) {
            $entry_id = (int) ($this->content_id() ?: 0);
        }
        $field_id = (int) ($this->field_id ?: 0);
        $file_id  = (int) $this->resolve_file_id($data);

        $context = $this->resolveCompositeContext($entry_id);
        $context_content_type = (string) ($context['content_type'] ?? 'channel');
        $context_row_id = isset($context['row_id']) && is_numeric($context['row_id']) ? (int) $context['row_id'] : null;
        $context_fluid_field_data_id = isset($context['fluid_field_data_id']) && is_numeric($context['fluid_field_data_id']) ? (int) $context['fluid_field_data_id'] : null;
        $context_block_id = isset($context['block_id']) && is_numeric($context['block_id']) ? (int) $context['block_id'] : null;

        $effective_settings = $this->effective_settings_for_context($field_id, $context_content_type);

        $cache_key = implode(':', [
            $site_id,
            $entry_id,
            $field_id,
            $file_id,
            $version_id,
            $context_content_type,
            $context_row_id !== null ? (int) $context_row_id : '',
            $context_fluid_field_data_id !== null ? (int) $context_fluid_field_data_id : '',
            $context_block_id !== null ? (int) $context_block_id : '',
        ]);
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        $ctx = [
            'site_id'              => $site_id,
            'entry_id'             => $entry_id,
            'field_id'             => $field_id,
            'file_id'              => $file_id,
            'usage_payload'        => [],
            'original_url'         => '',
            'file_name'            => '',
            'file_title'           => '',
            'file_description'     => '',
            'mime_type'            => '',
            'file_size'            => '',
            'upload_location_id'   => '',
            'upload_location_name' => '',
            'preset_id'            => 0,
            'preset'               => '',
            'aspect_ratio_raw'     => '',
            'aspect_ratio'         => '',
            'focal_x'              => '',
            'focal_y'              => '',
            'focal_x_pct'          => '',
            'focal_y_pct'          => '',
            'object_position'      => '',
            'crop'                 => '',
            'crop_offset_x'        => '',
            'crop_offset_y'        => '',
            'width'                => '',
            'height'               => '',
            'crop_rect_left'       => '',
            'crop_rect_top'        => '',
            'crop_rect_width'      => '',
            'crop_rect_height'     => '',
            'srcset'               => '',
            'sizes'                => '',
            'alt'                  => '',
            'decorative'           => '',
        ];

        if ($file_id <= 0) {
            $cache[$cache_key] = $ctx;
            return $ctx;
        }

        // File URL + best-effort metadata.
        $file = null;
        try {
            $file                = ServiceCache::file_repo()->findFileWithUploadDestination($file_id);
            $url                 = ($file && method_exists($file, 'getAbsoluteURL')) ? $file->getAbsoluteURL() : '';
            $ctx['original_url'] = is_string($url) ? $url : '';

            if ($file) {
                if (isset($file->file_name) && is_string($file->file_name)) {
                    $ctx['file_name'] = $file->file_name;
                }
                if (isset($file->title) && is_string($file->title)) {
                    $ctx['file_title'] = $file->title;
                }
                if (isset($file->description) && is_string($file->description)) {
                    $ctx['file_description'] = $file->description;
                }
                if (isset($file->mime_type) && is_string($file->mime_type)) {
                    $ctx['mime_type'] = $file->mime_type;
                }
                if (isset($file->file_size)) {
                    $ctx['file_size'] = (string) $file->file_size;
                }
                if (isset($file->upload_location_id)) {
                    $ctx['upload_location_id'] = (string) $file->upload_location_id;
                }

                if (isset($file->UploadDestination)) {
                    $dest = $file->UploadDestination;
                    if (is_object($dest)) {
                        if (isset($dest->id)) {
                            $ctx['upload_location_id'] = (string) $dest->id;
                        }
                        if (isset($dest->name) && is_string($dest->name)) {
                            $ctx['upload_location_name'] = $dest->name;
                        }
                    }
                }
            }
        }
        catch (Throwable $e) {
            $ctx['original_url'] = '';
        }

        // Usage payload (stored editorial intent).
        $usage_fetch = $this->fetch_usage_payload_with_row_id(
            $version_id,
            $site_id,
            $entry_id,
            $field_id,
            $context_content_type,
            $context_row_id,
            $context_fluid_field_data_id,
            $context_block_id
        );
        $usage_payload = is_array($usage_fetch['usage_payload'] ?? null) ? (array) $usage_fetch['usage_payload'] : [];
        $usage_row_id = isset($usage_fetch['usage_row_id']) ? (int) $usage_fetch['usage_row_id'] : 0;

        $db_ad_count = '';
        $db_ad_keys = '';
        $db_payload_excerpt = '';
        if ($debug_enabled && $usage_row_id > 0) {
            try {
                $row = ee()->db
                    ->select('usage_payload')
                    ->from('jcogs_img_pro_field_usages')
                    ->where('id', $usage_row_id)
                    ->limit(1)
                    ->get()
                    ->row_array();
                $raw = isset($row['usage_payload']) ? (string) $row['usage_payload'] : '';
                if ($raw !== '') {
                    $db_payload_excerpt = strlen($raw) > 120 ? (substr($raw, 0, 117) . '...') : $raw;
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)
                        && isset($decoded['art_direction']) && is_array($decoded['art_direction'])
                        && isset($decoded['art_direction']['files']) && is_array($decoded['art_direction']['files'])) {
                        $db_ad_count = (string) count($decoded['art_direction']['files']);
                        $db_ad_keys = implode(', ', array_map('strval', array_keys($decoded['art_direction']['files'])));
                    }
                }
            } catch (Throwable $e) {
                // Fail safe: no debug DB output.
            }
        }

        $raw_ad_count = 0;
        $raw_ad_keys = '';
        if (isset($usage_payload['art_direction']) && is_array($usage_payload['art_direction'])
            && isset($usage_payload['art_direction']['files']) && is_array($usage_payload['art_direction']['files'])) {
            $raw_ad_count = count($usage_payload['art_direction']['files']);
            $raw_ad_keys = implode(', ', array_map('strval', array_keys($usage_payload['art_direction']['files'])));
        }
        if (! is_array($usage_payload)) {
            $usage_payload = [];
        }

        // Persistently prune legacy overrides when tools are retrospectively disabled,
        // then enforce current field policy at render time so disabled tools cannot
        // affect template output. IMPORTANT: this pipeline does not add defaults.
        $usage_payload = $this->apply_usage_policy_pipeline(
            $usage_payload,
            $effective_settings,
            $usage_row_id,
            (int) $file_id,
            $version_id
        );

        // Apply field-level default preset when presets are enabled.
        // This happens after non-enriching sanitisation so editor-stored preset intent can be
        // stripped (e.g. when editor choice is disabled) without preventing a forced default.
        if ((($effective_settings['enable_preset'] ?? 'n') === 'y')) {
            $default_preset_id = trim((string) ($effective_settings['default_preset_id'] ?? ''));
            if (! array_key_exists('preset_id', $usage_payload) && $default_preset_id !== '' && is_numeric($default_preset_id) && (int) $default_preset_id > 0) {
                $usage_payload['preset_id'] = (int) $default_preset_id;
            }
        }

        $ctx['usage_payload'] = $usage_payload;

        // Template pass-through values (if stored) + field-level defaults.
        $ctx['srcset'] = (isset($usage_payload['srcset']) && is_string($usage_payload['srcset'])) ? trim($usage_payload['srcset']) : '';
        $ctx['sizes']  = (isset($usage_payload['sizes']) && is_string($usage_payload['sizes'])) ? trim($usage_payload['sizes']) : '';
        if ($ctx['srcset'] === '') {
            $ctx['srcset'] = $this->build_default_srcset_string();
        }

        // a11y defaults.
        if (isset($usage_payload['alt']) && is_string($usage_payload['alt'])) {
            $ctx['alt'] = trim($usage_payload['alt']);
        }
        if ($ctx['alt'] === '') {
            $ctx['alt'] = (string) ($ctx['file_title'] ?? '');
        }

        $decorative    = isset($usage_payload['decorative']) ? trim((string) $usage_payload['decorative']) : '';
        $decorative_lc = strtolower($decorative);
        if ($decorative_lc === 'y' || $decorative_lc === 'yes' || $decorative_lc === '1' || $decorative_lc === 'true') {
            $ctx['decorative'] = 'y';
        }
        elseif ($decorative_lc === 'n' || $decorative_lc === 'no' || $decorative_lc === '0' || $decorative_lc === 'false') {
            $ctx['decorative'] = 'n';
        }
        if ($ctx['decorative'] === '') {
            $ctx['decorative'] = 'n';
        }

        $pid              = isset($usage_payload['preset_id']) ? (int) $usage_payload['preset_id'] : 0;
        $ctx['preset_id'] = $pid;
        if ($pid > 0) {
            try {
                $presets = $this->fetch_img_pro_presets($site_id);
                foreach ($presets as $p) {
                    $id   = isset($p['id']) ? (int) $p['id'] : 0;
                    $name = isset($p['name']) ? (string) $p['name'] : '';
                    if ($id === $pid && $name !== '') {
                        $ctx['preset'] = $name;
                        break;
                    }
                }
            }
            catch (Throwable $e) {
                $ctx['preset'] = '';
            }
        }

        // Crop + sizing.
        $ctx['crop']          = isset($usage_payload['crop']) ? (string) $usage_payload['crop'] : '';
        $ctx['crop_offset_x'] = isset($usage_payload['crop_offset_x']) ? (string) $usage_payload['crop_offset_x'] : '';
        $ctx['crop_offset_y'] = isset($usage_payload['crop_offset_y']) ? (string) $usage_payload['crop_offset_y'] : '';
        $ctx['width']         = isset($usage_payload['width']) ? (string) $usage_payload['width'] : '';
        $ctx['height']        = isset($usage_payload['height']) ? (string) $usage_payload['height'] : '';
        if (isset($usage_payload['crop_rect']) && is_array($usage_payload['crop_rect'])) {
            $ctx['crop_rect_left']   = isset($usage_payload['crop_rect']['left']) ? (string) $usage_payload['crop_rect']['left'] : '';
            $ctx['crop_rect_top']    = isset($usage_payload['crop_rect']['top']) ? (string) $usage_payload['crop_rect']['top'] : '';
            $ctx['crop_rect_width']  = isset($usage_payload['crop_rect']['width']) ? (string) $usage_payload['crop_rect']['width'] : '';
            $ctx['crop_rect_height'] = isset($usage_payload['crop_rect']['height']) ? (string) $usage_payload['crop_rect']['height'] : '';
        }

        // Aspect ratio (effective).
        $aspect_raw              = array_key_exists('aspect_ratio', $usage_payload) ? (string) $usage_payload['aspect_ratio'] : '';
        $ctx['aspect_ratio_raw'] = $aspect_raw;
        $aspect_stored           = ($aspect_raw === '__inherit__') ? '' : (string) $aspect_raw;

        $default_aspect_ratio = $this->resolve_default_aspect_ratio_for_render($effective_settings);

        $aspect_effective = $aspect_stored;
        if (! array_key_exists('aspect_ratio', $usage_payload) && $default_aspect_ratio !== '') {
            $aspect_effective = $default_aspect_ratio;
        }
        if ($aspect_raw === '__inherit__' && $default_aspect_ratio !== '') {
            $aspect_effective = $default_aspect_ratio;
        }
        $ctx['aspect_ratio'] = (string) $aspect_effective;

        // Focal + object-position.
        $fx  = isset($usage_payload['focal_x']) ? trim((string) $usage_payload['focal_x']) : '';
        $fy  = isset($usage_payload['focal_y']) ? trim((string) $usage_payload['focal_y']) : '';
        $fxn = is_numeric($fx) ? (float) $fx : null;
        $fyn = is_numeric($fy) ? (float) $fy : null;
        if ($fxn !== null && $fxn >= 0 && $fxn <= 100) {
            $ctx['focal_x']     = (string) ((round($fxn * 10) / 10));
            $ctx['focal_x_pct'] = $ctx['focal_x'];
        }
        if ($fyn !== null && $fyn >= 0 && $fyn <= 100) {
            $ctx['focal_y']     = (string) ((round($fyn * 10) / 10));
            $ctx['focal_y_pct'] = $ctx['focal_y'];
        }
        if ($ctx['focal_x'] !== '' && $ctx['focal_y'] !== '') {
            $ctx['object_position'] = $ctx['focal_x'] . '% ' . $ctx['focal_y'] . '%';
        }

        // Computed Image Pro crop param.
        try {
            $renderer = ee('jcogs_img_pro_field:ImageProRenderer');
            if ($renderer && method_exists($renderer, 'buildCropParamFromPayload')) {
                $computed = $renderer->buildCropParamFromPayload($usage_payload);
                if (is_string($computed) && trim($computed) !== '') {
                    $ctx['crop'] = trim($computed);
                }
            }
        }
        catch (Throwable $e) {
            // Ignore.
        }

        $cache[$cache_key] = $ctx;
        return $ctx;
    }

    /**
     * Tag-pair replacement exposing art-direction rows.
     *
     * Called by EE when parsing {field pair="art_direction"}...{/field}.
     */
    public function replace_art_direction($data, $params = [], $tagdata = false)
    {
        return ServiceCache::tag_render()->replace_art_direction($data, $params, $tagdata, $this->tag_render_callbacks());
    }

    private function tag_render_callbacks(): array
    {
        return [
            'build_ctx'               => function ($data): array {
                return $this->build_template_context($data);
            },
            'settings'                => function (): array {
                return is_array($this->settings) ? $this->settings : [];
            },
            'ad_rows'                 => function (): array {
                $rows = $this->get_art_direction_breakpoints_from_field_settings();
                return is_array($rows) ? $rows : [];
            },
            'default_renderer_params' => function (): array {
                return $this->build_field_default_renderer_params();
            },
            'render_ad_picture'       => function (int $main_file_id, array $usage_payload, array $tag_params): string {
                return $this->render_art_direction_picture($main_file_id, $usage_payload, $tag_params);
            },
            'apply_default_ad_preset' => function (int $file_id, array $usage_payload, array $tag_params): array {
                return $this->apply_default_art_direction_preset_to_payload($file_id, $usage_payload, $tag_params);
            },
            'build_ad_row_payload'    => function (int $row_file_id, array $usage_payload, int $row_preset_id, array $tag_params): array {
                return $this->build_payload_for_art_direction_row($row_file_id, $usage_payload, $row_preset_id, $tag_params);
            },
        ];
    }

    /**
     * Build a conservative default srcset string.
     */
    private function build_default_srcset_string(): string
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::responsive_defaults()->buildDefaultSrcsetString(
            is_array($this->settings) ? $this->settings : []
        );
    }

    /**
     * Build default renderer params based on field settings.
     */
    private function build_field_default_renderer_params(): array
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::responsive_defaults()->buildDefaultRendererParams(
            is_array($this->settings) ? $this->settings : []
        );
    }

    private function normalizeContentTypeForContext(string $raw): string
    {
        $raw = strtolower(trim($raw));
        if ($raw === 'file_grid' || $raw === 'filegrid') {
            return 'grid';
        }
        if ($raw === 'fluid_field') {
            return 'fluid';
        }
        if ($raw === 'blocks/1' || $raw === 'bloqs/1' || $raw === 'blocks') {
            return 'bloqs';
        }
        if (in_array($raw, ['channel', 'grid', 'fluid', 'bloqs'], true)) {
            return $raw;
        }
        return 'channel';
    }

    private function resolveGridColumnId(): ?int
    {
        $candidates = [
            $this->settings['grid_col_id'] ?? null,
            $this->settings['col_id'] ?? null,
            $this->settings['grid_col'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                return (int) $candidate;
            }
        }

        $fieldName = isset($this->field_name) ? (string) $this->field_name : '';
        if ($fieldName !== '' && preg_match('/col_id_(\d+)/', $fieldName, $m)) {
            $n = (int) ($m[1] ?? 0);
            if ($n > 0) {
                return $n;
            }
        }

        return null;
    }

    private function get_grid_column_settings_for_display(int $fieldId): array
    {
        if ($fieldId <= 0) {
            return [];
        }

        try {
            $column = ee('Model')->get('GridColumn')
                ->filter('col_id', $fieldId)
                ->first();

            if (! $column) {
                return [];
            }

            $raw = $column->col_settings ?? [];
            if (is_string($raw) && $raw !== '' && $raw[0] === '{') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $raw = $decoded;
                }
            }
            if (! is_array($raw) || empty($raw)) {
                return [];
            }

            $raw = $raw['field_settings'] ?? $raw;
            if (isset($raw['jcogs_img_pro_field']) && is_array($raw['jcogs_img_pro_field'])) {
                $raw = $raw['jcogs_img_pro_field'];
            }

            return is_array($raw) ? $raw : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function effective_settings_for_context(int $fieldId, string $contentType): array
    {
        $effective_settings = is_array($this->settings) ? $this->settings : [];
        if ($contentType === 'grid' && $fieldId > 0) {
            $grid_settings = $this->get_grid_column_settings_for_display($fieldId);
            if (is_array($grid_settings) && ! empty($grid_settings)) {
                $effective_settings = array_merge($effective_settings, $grid_settings);
            }
        }

        return $effective_settings;
    }

    private function is_debug_enabled_for_request(): bool
    {
        return (string) ee()->input->get_post('jcogs_img_pro_field_debug') === '1';
    }

    private function resolve_requested_version_id(): int
    {
        return (int) ee()->input->get('version');
    }

    private function fetch_usage_payload_with_row_id(
        int $versionId,
        int $siteId,
        int $entryId,
        int $fieldId,
        string $contentType,
        ?int $rowId,
        ?int $fluidFieldDataId,
        ?int $blockId
    ): array {
        $usage_payload = [];
        $usage_row_id = 0;

        if ($entryId > 0 && $fieldId > 0) {
            if ($versionId > 0) {
                $usage_payload = ServiceCache::usage_payload_maintenance()->fetchUsagePayloadForVersion(
                    $versionId,
                    $siteId,
                    $entryId,
                    $fieldId,
                    $contentType,
                    $rowId,
                    $fluidFieldDataId,
                    $blockId
                );
            }
            else {
                $usage_row = ServiceCache::usage_payload_maintenance()->fetchUsagePayloadRow(
                    $siteId,
                    $entryId,
                    $fieldId,
                    $contentType,
                    $rowId,
                    $fluidFieldDataId,
                    $blockId
                );
                $usage_row_id = (int) ($usage_row['id'] ?? 0);
                $usage_payload = is_array($usage_row['payload'] ?? null) ? (array) $usage_row['payload'] : [];
            }
        }

        if (! is_array($usage_payload)) {
            $usage_payload = [];
        }

        return [
            'usage_payload' => $usage_payload,
            'usage_row_id' => $usage_row_id,
        ];
    }

    private function apply_usage_policy_pipeline(
        array $usagePayload,
        array $effectiveSettings,
        int $usageRowId,
        int $fileId,
        int $versionId
    ): array {
        $ad_rows = ServiceCache::art_direction()->getBreakpointsFromFieldSettings($effectiveSettings);
        $ad_rows = is_array($ad_rows) ? $ad_rows : [];

        // Persistently prune legacy overrides when tools are retrospectively disabled.
        // This ensures that if the tool is re-enabled later, old values do not “come back”.
        if ($usageRowId > 0 && ! empty($usagePayload) && $versionId <= 0) {
            $pruned = ServiceCache::usage_payload_maintenance()->pruneUsagePayloadForDisabledFeatures(
                $usagePayload,
                $effectiveSettings,
                $ad_rows
            );
            if ($pruned != $usagePayload) {
                ServiceCache::usage_payload_maintenance()->persistPrunedUsageRow($usageRowId, $fileId, $pruned);
                $usagePayload = $pruned;
            }
        }

        return ServiceCache::usage_payload_maintenance()->sanitiseUsagePayloadAgainstSettings(
            $usagePayload,
            $effectiveSettings,
            $ad_rows
        );
    }

    private function resolve_default_aspect_ratio_for_render(array $effectiveSettings): string
    {
        $default_aspect_ratio = $this->normalize_aspect_ratio_setting((string) ($effectiveSettings['default_aspect_ratio'] ?? ''));
        if ($default_aspect_ratio === '') {
            $field_aspect_choices = $this->get_aspect_ratio_choices_from_field_settings();
            if (is_array($field_aspect_choices) && count($field_aspect_choices) === 1) {
                foreach ($field_aspect_choices as $k => $_) {
                    $default_aspect_ratio = (string) $k;
                    break;
                }
            }
        }

        return $default_aspect_ratio;
    }

    private function get_grid_column_settings_debug(int $fieldId): array
    {
        if ($fieldId <= 0) {
            return ['keys' => '', 'enable_ad' => '', 'excerpt' => ''];
        }

        try {
            $column = ee('Model')->get('GridColumn')
                ->filter('col_id', $fieldId)
                ->first();

            if (! $column) {
                return ['keys' => '', 'enable_ad' => '', 'excerpt' => ''];
            }

            $raw = $column->col_settings ?? [];
            if (is_string($raw) && $raw !== '' && $raw[0] === '{') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $raw = $decoded;
                }
            }
            if (! is_array($raw)) {
                $excerpt = is_scalar($raw) ? (string) $raw : gettype($raw);
                return ['keys' => '', 'enable_ad' => '', 'excerpt' => $excerpt];
            }

            $excerpt = '';
            try {
                $json = json_encode($raw);
                if (is_string($json)) {
                    $excerpt = strlen($json) > 120 ? (substr($json, 0, 117) . '...') : $json;
                }
            } catch (Throwable $e) {
                $excerpt = '';
            }

            $settings = $raw['field_settings'] ?? $raw;
            if (isset($settings['jcogs_img_pro_field']) && is_array($settings['jcogs_img_pro_field'])) {
                $settings = $settings['jcogs_img_pro_field'];
            }

            if (! is_array($settings)) {
                return ['keys' => '', 'enable_ad' => '', 'excerpt' => $excerpt];
            }

            $keys = implode(', ', array_map('strval', array_keys($settings)));
            $enable = isset($settings['enable_art_direction']) ? (string) $settings['enable_art_direction'] : '';

            return ['keys' => $keys, 'enable_ad' => $enable, 'excerpt' => $excerpt];
        } catch (Throwable $e) {
            return ['keys' => '', 'enable_ad' => '', 'excerpt' => ''];
        }
    }

    private function resolveContextRowId(): ?int
    {
        $candidate = $this->settings['grid_row_id'] ?? null;
        if (! is_numeric($candidate)) {
            $candidate = $this->row('row_id');
        }
        if (! is_numeric($candidate)) {
            return null;
        }
        $candidate = (int) $candidate;
        return $candidate > 0 ? $candidate : null;
    }

    private function resolveContextFluidFieldDataId(): ?int
    {
        $candidate = $this->settings['fluid_field_data_id'] ?? null;
        if (! is_numeric($candidate)) {
            $candidate = $this->row('fluid_field_data_id');
        }
        if (! is_numeric($candidate)) {
            $candidate = ee()->input->get('fluid_field_data_id');
        }
        if (! is_numeric($candidate)) {
            $candidate = ee()->input->post('fluid_field_data_id');
        }
        if (! is_numeric($candidate)) {
            return null;
        }
        $candidate = (int) $candidate;
        return $candidate > 0 ? $candidate : null;
    }

    private function resolveContextBlockId(?string $contentType = null): ?int
    {
        $normalizedContentType = $contentType !== null
            ? $this->normalizeContentTypeForContext((string) $contentType)
            : '';

        if ($normalizedContentType === 'grid') {
            return null;
        }

        $candidate = $this->settings['block_id']
            ?? ($this->settings['bloqs_block_id'] ?? ($this->settings['blocks_block_id'] ?? ($this->settings['grid_row_id'] ?? null)));
        if (! is_numeric($candidate)) {
            $candidate = $this->row('block_id');
        }
        if (! is_numeric($candidate)) {
            $candidate = ee()->input->get('block_id');
        }
        if (! is_numeric($candidate)) {
            $candidate = ee()->input->post('block_id');
        }
        if (! is_numeric($candidate)) {
            return null;
        }
        $candidate = (int) $candidate;
        return $candidate > 0 ? $candidate : null;
    }

    private function resolveContextContainerId(int $entryId, string $contentType): ?int
    {
        $candidate = $this->settings['container_id']
            ?? ($this->settings['bloqs_container_id'] ?? ($this->settings['blocks_atom_id'] ?? null));
        if (! is_numeric($candidate)) {
            if ($contentType === 'grid' && isset($this->settings['grid_field_id']) && is_numeric($this->settings['grid_field_id'])) {
                $candidate = $this->settings['grid_field_id'];
            }
        }
        if (! is_numeric($candidate)) {
            if ($contentType === 'bloqs' && isset($this->settings['grid_field_id']) && is_numeric($this->settings['grid_field_id'])) {
                $candidate = $this->settings['grid_field_id'];
            }
        }
        if (! is_numeric($candidate)) {
            if ($contentType === 'fluid' && isset($this->settings['fluid_field_id']) && is_numeric($this->settings['fluid_field_id'])) {
                $candidate = $this->settings['fluid_field_id'];
            }
        }
        if (! is_numeric($candidate)) {
            $candidate = $this->row('container_id');
        }
        if (! is_numeric($candidate)) {
            $candidate = ee()->input->get('container_id');
        }
        if (! is_numeric($candidate)) {
            $candidate = ee()->input->post('container_id');
        }
        if (is_numeric($candidate)) {
            $candidate = (int) $candidate;
            if ($candidate > 0) {
                return $candidate;
            }
        }
        if ($contentType === 'bloqs') {
            $fieldId = (int) ($this->field_id ?? 0);
            if ($fieldId > 0) {
                return $fieldId;
            }
        }
        if ($contentType === 'channel' && $entryId > 0) {
            return $entryId;
        }
        return null;
    }

    private function resolveCompositeContext(int $entryId = 0): array
    {
        $contentType = $this->normalizeContentTypeForContext((string) $this->content_type());
        $rowId = $this->resolveContextRowId();
        $fluidFieldDataId = $this->resolveContextFluidFieldDataId();
        $blockId = $this->resolveContextBlockId($contentType);
        $containerId = $this->resolveContextContainerId($entryId, $contentType);

        return [
            'content_type' => $contentType,
            'row_id' => $rowId,
            'fluid_field_data_id' => $fluidFieldDataId,
            'block_id' => $blockId,
            'container_id' => $containerId,
        ];
    }

    /**
     * Render the publish field UI.
     *
     * Called by EE when displaying the entry edit form.
     */
    public function display_field($data)
    {
        $site_id  = (int) (ee()->config->item('site_id') ?: 1);
        $entry_id = (int) ($this->content_id() ?: 0);
        $field_id = (int) ($this->field_id ?: 0);
        $version_id = $this->resolve_requested_version_id();
        $debug_enabled = $this->is_debug_enabled_for_request();

        $context = $this->resolveCompositeContext($entry_id);
        $context_content_type = (string) ($context['content_type'] ?? 'channel');
        $context_row_id = isset($context['row_id']) && is_numeric($context['row_id']) ? (int) $context['row_id'] : null;
        $context_fluid_field_data_id = isset($context['fluid_field_data_id']) && is_numeric($context['fluid_field_data_id']) ? (int) $context['fluid_field_data_id'] : null;
        $context_block_id = isset($context['block_id']) && is_numeric($context['block_id']) ? (int) $context['block_id'] : null;
        $context_container_id = isset($context['container_id']) && is_numeric($context['container_id']) ? (int) $context['container_id'] : null;

        $is_composite = ($context_content_type !== 'channel');

        $file_id = $this->resolve_file_id($data);

        $effective_settings = $this->effective_settings_for_context($field_id, $context_content_type);

        if ($entry_id > 0 && $field_id > 0 && $file_id > 0 && $context_content_type === 'grid' && $context_row_id !== null) {
            $this->migrate_grid_usage_row_if_needed(
                $site_id,
                $entry_id,
                $field_id,
                $file_id,
                $context_row_id,
                $context_container_id
            );
        }

        $is_superadmin = false;
        if (isset(ee()->session) && isset(ee()->session->userdata['group_id'])) {
            $is_superadmin = ((int) ee()->session->userdata['group_id'] === 1);
        }

        $usage_fetch = $this->fetch_usage_payload_with_row_id(
            $version_id,
            $site_id,
            $entry_id,
            $field_id,
            $context_content_type,
            $context_row_id,
            $context_fluid_field_data_id,
            $context_block_id
        );
        $usage_payload = is_array($usage_fetch['usage_payload'] ?? null) ? (array) $usage_fetch['usage_payload'] : [];
        $usage_row_id = isset($usage_fetch['usage_row_id']) ? (int) $usage_fetch['usage_row_id'] : 0;

        // Persistently prune legacy overrides when tools are retrospectively disabled,
        // then enforce current field policy in publish UI so legacy values cannot drive
        // state when those tools are disabled.
        $usage_payload = $this->apply_usage_policy_pipeline(
            $usage_payload,
            $effective_settings,
            $usage_row_id,
            (int) $file_id,
            $version_id
        );

        $preset_id = isset($usage_payload['preset_id']) ? (string) $usage_payload['preset_id'] : '';
        $focal_x   = isset($usage_payload['focal_x']) ? (string) $usage_payload['focal_x'] : '';
        $focal_y   = isset($usage_payload['focal_y']) ? (string) $usage_payload['focal_y'] : '';

        $crop               = isset($usage_payload['crop']) ? (string) $usage_payload['crop'] : '';
        $crop_mode          = isset($usage_payload['crop_mode']) ? (string) $usage_payload['crop_mode'] : '';
        $crop_focus_h       = isset($usage_payload['crop_focus_h']) ? (string) $usage_payload['crop_focus_h'] : '';
        $crop_focus_v       = isset($usage_payload['crop_focus_v']) ? (string) $usage_payload['crop_focus_v'] : '';
        $crop_offset_x      = isset($usage_payload['crop_offset_x']) ? (string) $usage_payload['crop_offset_x'] : '';
        $crop_offset_y      = isset($usage_payload['crop_offset_y']) ? (string) $usage_payload['crop_offset_y'] : '';
        $crop_smart_scaling = isset($usage_payload['crop_smart_scaling']) ? (string) $usage_payload['crop_smart_scaling'] : '';

        // Normalize legacy/boolean-ish values so the CP dropdown reliably reflects saved state.
        $css = strtolower(trim((string) $crop_smart_scaling));
        if ($css === 'y' || $css === '1' || $css === 'true') {
            $crop_smart_scaling = 'yes';
        }
        elseif ($css === 'n' || $css === '0' || $css === 'false') {
            $crop_smart_scaling = 'no';
        }

        $width  = isset($usage_payload['width']) ? (string) $usage_payload['width'] : '';
        $height = isset($usage_payload['height']) ? (string) $usage_payload['height'] : '';

        $aspect_ratio_override_present    = array_key_exists('aspect_ratio', $usage_payload);
        $aspect_ratio_stored_raw          = $aspect_ratio_override_present ? (string) $usage_payload['aspect_ratio'] : '';
        $aspect_ratio_is_inherit_override = ($aspect_ratio_stored_raw === '__inherit__');
        $aspect_ratio_stored              = $aspect_ratio_is_inherit_override ? '' : $aspect_ratio_stored_raw;

        $default_aspect_ratio = $this->resolve_default_aspect_ratio_for_render($effective_settings);
        $aspect_ratio_effective = $aspect_ratio_stored;
        if (! $aspect_ratio_override_present && $default_aspect_ratio !== '') {
            $aspect_ratio_effective = $default_aspect_ratio;
        }

        $require_aspect_ratio = (($effective_settings['require_aspect_ratio'] ?? 'n') === 'y');

        // Hidden field stores the canonical value posted on save/preview.
        // Use a sentinel when editor explicitly wants to override a default back to “inherit”.
        $aspect_ratio_hidden_value = $aspect_ratio_is_inherit_override ? '__inherit__' : $aspect_ratio_effective;

        if ($require_aspect_ratio) {
            // When aspect ratio is required, always post an explicit ratio.
            $aspect_ratio_hidden_value = $aspect_ratio_effective;
            if ($aspect_ratio_hidden_value === '') {
                $field_aspect_choices = $this->get_aspect_ratio_choices_from_field_settings();
                if (is_array($field_aspect_choices) && ! empty($field_aspect_choices)) {
                    foreach (array_keys($field_aspect_choices) as $k) {
                        $aspect_ratio_effective    = (string) $k;
                        $aspect_ratio_hidden_value = (string) $k;
                        break;
                    }
                }
            }
        }

        $crop_rect_left   = '';
        $crop_rect_top    = '';
        $crop_rect_width  = '';
        $crop_rect_height = '';
        if (isset($usage_payload['crop_rect']) && is_array($usage_payload['crop_rect'])) {
            $crop_rect_left   = isset($usage_payload['crop_rect']['left']) ? (string) $usage_payload['crop_rect']['left'] : '';
            $crop_rect_top    = isset($usage_payload['crop_rect']['top']) ? (string) $usage_payload['crop_rect']['top'] : '';
            $crop_rect_width  = isset($usage_payload['crop_rect']['width']) ? (string) $usage_payload['crop_rect']['width'] : '';
            $crop_rect_height = isset($usage_payload['crop_rect']['height']) ? (string) $usage_payload['crop_rect']['height'] : '';
        }

        $enable_preset         = (($effective_settings['enable_preset'] ?? 'y') === 'y');
        $enable_preset_choice  = (($effective_settings['enable_preset_choice'] ?? 'y') === 'y');
        $default_preset_id     = trim((string) ($effective_settings['default_preset_id'] ?? ''));
        $default_preset_id_int = (is_numeric($default_preset_id) && (int) $default_preset_id > 0) ? (int) $default_preset_id : 0;
        $enable_crop           = (($effective_settings['enable_crop'] ?? 'y') === 'y');
        $require_crop          = $enable_crop && (($effective_settings['require_crop'] ?? 'n') === 'y');
        $require_aspect_ratio  = $enable_crop && $require_aspect_ratio;
        // Focal point is crop-adjacent; if crop tools are disabled, do not expose focal tools.
        $enable_focal = $enable_crop && (($effective_settings['enable_focal'] ?? 'n') === 'y');
        $enable_debug = (($effective_settings['enable_debug'] ?? 'n') === 'y');

        $ad_rows              = ServiceCache::art_direction()->getBreakpointsFromFieldSettings($effective_settings);
        $enable_art_direction = (($effective_settings['enable_art_direction'] ?? 'n') === 'y') && ! empty($ad_rows);
        // Art direction selects alternate source images per breakpoint row.
        // Keep the main preset selector available so editors can still choose the base preset.

        // Face detection is treated as a crop-adjacent tool. If crop is disabled, force face detection off.
        $enable_face_detect = $enable_crop && (($effective_settings['enable_face_detect'] ?? 'y') === 'y');

        $face_detect_controls_mode = trim((string) ($effective_settings['face_detect_controls'] ?? 'advanced'));
        if (! in_array($face_detect_controls_mode, ['hidden', 'advanced', 'visible'], true)) {
            $face_detect_controls_mode = 'advanced';
        }

        if (! $enable_face_detect) {
            $face_detect_controls_mode = 'hidden';
        }
        $face_detect_default_quality = strtolower(trim((string) ($effective_settings['face_detect_default_quality'] ?? 'balanced')));
        if (! in_array($face_detect_default_quality, ['fast', 'balanced', 'accurate'], true)) {
            $face_detect_default_quality = 'balanced';
        }
        $face_detect_default_sensitivity = (int) ($effective_settings['face_detect_default_sensitivity'] ?? 3);
        $face_detect_default_sensitivity = max(1, min(9, $face_detect_default_sensitivity));
        $face_detect_default_margin      = (int) ($effective_settings['face_detect_default_margin'] ?? 0);
        $face_detect_default_margin      = max(0, min(500, $face_detect_default_margin));

        // Manual fields are power-user only; hide from editors (and from most installs).
        $enable_manual = (($effective_settings['enable_manual'] ?? 'n') === 'y') && $enable_debug && $is_superadmin;

        $preset_options = $this->get_editor_preset_options($site_id, $preset_id);
        if ($default_preset_id_int > 0 && ! array_key_exists((string) $default_preset_id_int, $preset_options)) {
            $default_preset_id_int = 0;
        }

        // Only show the preset selector if there is an actual choice, or if a preset is already set
        // (so editors can see/change what is currently in use).
        $has_effective_preset = ($preset_id !== '' && $preset_id !== '0');
        $show_preset_selector = $enable_preset
            && $enable_preset_choice
            && ((is_array($preset_options) && count($preset_options) > 1) || $has_effective_preset);

        // Auto-open options when something is already set.
        $has_any_override = (
            trim((string) $preset_id) !== ''
            || trim((string) $focal_x) !== ''
            || trim((string) $focal_y) !== ''
            || trim((string) $crop) !== ''
            || trim((string) $crop_mode) !== ''
            || trim((string) $crop_focus_h) !== ''
            || trim((string) $crop_focus_v) !== ''
            || trim((string) $crop_offset_x) !== ''
            || trim((string) $crop_offset_y) !== ''
            || trim((string) $crop_smart_scaling) !== ''
            || trim((string) $width) !== ''
            || trim((string) $height) !== ''
            || $aspect_ratio_override_present
            || trim((string) $crop_rect_left) !== ''
            || trim((string) $crop_rect_top) !== ''
            || trim((string) $crop_rect_width) !== ''
            || trim((string) $crop_rect_height) !== ''
            || ($enable_art_direction && isset($usage_payload['art_direction']) && is_array($usage_payload['art_direction'])
                && isset($usage_payload['art_direction']['files']) && is_array($usage_payload['art_direction']['files'])
                && ! empty($usage_payload['art_direction']['files']))
        );
        $has_crop_defined = (
            trim((string) $crop_rect_left) !== ''
            || trim((string) $crop_rect_top) !== ''
            || trim((string) $crop_rect_width) !== ''
            || trim((string) $crop_rect_height) !== ''
            || trim((string) $crop_offset_x) !== ''
            || trim((string) $crop_offset_y) !== ''
            || trim((string) $width) !== ''
            || trim((string) $height) !== ''
            || trim((string) $crop) !== ''
        );

        $act_url               = '';
        $preview_act_url       = '';
        $usage_action_id       = 0;
        $preview_action_id     = 0;
        $face_detect_act_url   = '';
        $face_detect_action_id = 0;
        if ($entry_id > 0 && $field_id > 0) {
            $usage_action_id = $this->resolve_action_id('Jcogs_img_pro_field', 'usage');
            if ($usage_action_id > 0) {
                $base     = ee()->functions->fetch_site_index(0, 0);
                $base    .= (strpos($base, '?') === false) ? '?' : '&';
                $act_url  = $base . 'ACT=' . $usage_action_id;
            }

            $preview_action_id = $this->resolve_action_id('Jcogs_img_pro_field', 'preview');
            if ($preview_action_id > 0) {
                $base             = ee()->functions->fetch_site_index(0, 0);
                $base            .= (strpos($base, '?') === false) ? '?' : '&';
                $preview_act_url  = $base . 'ACT=' . $preview_action_id;
            }

            if ($enable_face_detect) {
                $face_detect_action_id = $this->resolve_action_id('Jcogs_img_pro_field', 'face_detect');
                if ($face_detect_action_id > 0) {
                    $base                 = ee()->functions->fetch_site_index(0, 0);
                    $base                .= (strpos($base, '?') === false) ? '?' : '&';
                    $face_detect_act_url  = $base . 'ACT=' . $face_detect_action_id;
                }
            }
        }

        $preview_available = ($entry_id > 0 && $field_id > 0 && $preview_act_url !== '');

        $html  = '';
        $html .= '<div class="jcogs-img-pro-field"'
            . ' data-jcogs-img-pro-field="1"'
            . ' data-entry-id="' . (int) $entry_id . '"'
            . ' data-field-id="' . (int) $field_id . '"'
            . ' data-content-type="' . htmlspecialchars($context_content_type, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-container-id="' . htmlspecialchars((string) ($context_container_id !== null ? (int) $context_container_id : ''), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-row-id="' . htmlspecialchars((string) ($context_row_id !== null ? (int) $context_row_id : ''), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-fluid-field-data-id="' . htmlspecialchars((string) ($context_fluid_field_data_id !== null ? (int) $context_fluid_field_data_id : ''), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-block-id="' . htmlspecialchars((string) ($context_block_id !== null ? (int) $context_block_id : ''), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-is-composite="' . ($is_composite ? '1' : '0') . '"'
            . ' data-main-file-input-name="' . htmlspecialchars((string) $this->field_name, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-act-url="' . htmlspecialchars($act_url, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-preview-act-url="' . htmlspecialchars($preview_act_url, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-face-detect-act-url="' . htmlspecialchars($face_detect_act_url, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-face-detect-controls-mode="' . htmlspecialchars($face_detect_controls_mode, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-face-detect-default-quality="' . htmlspecialchars($face_detect_default_quality, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-face-detect-default-sensitivity="' . (int) $face_detect_default_sensitivity . '"'
            . ' data-face-detect-default-margin="' . (int) $face_detect_default_margin . '"'
            . ' data-default-preset-id="' . (int) $default_preset_id_int . '"'
            . ' data-default-aspect-ratio="' . htmlspecialchars($default_aspect_ratio, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-has-default-aspect-ratio="' . ($default_aspect_ratio !== '' ? '1' : '0') . '"'
            . ' data-require-crop="' . ($require_crop ? '1' : '0') . '"'
            . ' data-require-aspect-ratio="' . ($require_aspect_ratio ? '1' : '0') . '"'
            . ' data-is-superadmin="' . ($is_superadmin ? '1' : '0') . '"'
            . '>';

        ServiceCache::assets()->enqueueCpPublishAssets();

        static $publish_js_globals_added = false;
        if (! $publish_js_globals_added && REQ === 'CP') {
            $publish_js_globals_added = true;

            $token = defined('CSRF_TOKEN') ? CSRF_TOKEN : '';
            $i18n  = [
                'ad_alt_selected_main_preserved'  => lang('jcogs_img_pro_field_js_status_ad_alt_selected_main_preserved'),
                'debug_preview'                   => lang('jcogs_img_pro_field_js_debug_preview'),
                'derived_preview_unavailable'     => lang('jcogs_img_pro_field_js_derived_preview_unavailable'),
                'open_derived'                    => lang('jcogs_img_pro_field_js_open_derived'),
                'open_original'                   => lang('jcogs_img_pro_field_js_open_original'),
                'preview'                         => lang('jcogs_img_pro_field_editor_label_preview'),
                'original_for_crop_picking'       => lang('jcogs_img_pro_field_js_original_for_crop_picking'),
                'face_settings_restored'          => lang('jcogs_img_pro_field_js_status_face_settings_restored'),
                'detecting_short'                 => lang('jcogs_img_pro_field_js_detecting_short'),
                'loading'                         => lang('jcogs_img_pro_field_js_status_loading'),
                'load_failed'                     => lang('jcogs_img_pro_field_js_status_load_failed'),
                'loaded'                          => lang('jcogs_img_pro_field_js_status_loaded'),
                'saving'                          => lang('jcogs_img_pro_field_js_status_saving'),
                'save_failed'                     => lang('jcogs_img_pro_field_js_status_save_failed'),
                'saved'                           => lang('jcogs_img_pro_field_js_status_saved'),
                'preview_rendering'               => lang('jcogs_img_pro_field_js_status_preview_rendering'),
                'preview_failed'                  => lang('jcogs_img_pro_field_js_status_preview_failed'),
                'preview_ready'                   => lang('jcogs_img_pro_field_js_status_preview_ready'),
                'preview_action_missing'          => lang('jcogs_img_pro_field_js_status_preview_action_missing'),
                'preview_original_required'       => lang('jcogs_img_pro_field_js_status_preview_original_required'),
                'loading_image'                   => lang('jcogs_img_pro_field_js_status_loading_image'),
                'crop_offsets_cleared'            => lang('jcogs_img_pro_field_js_status_crop_offsets_cleared'),
                'crop_drag_resize'                => lang('jcogs_img_pro_field_js_status_crop_drag_resize'),
                'image_changed_overrides_cleared' => lang('jcogs_img_pro_field_js_status_image_changed_overrides_cleared'),
                'pick_focal'                      => lang('jcogs_img_pro_field_js_status_pick_focal'),
                'focal_pick_cancelled'            => lang('jcogs_img_pro_field_js_status_focal_pick_cancelled'),
                'focal_cleared'                   => lang('jcogs_img_pro_field_js_status_focal_cleared'),
                'focal_set'                       => lang('jcogs_img_pro_field_js_status_focal_set'),
                'choose_image_first'              => lang('jcogs_img_pro_field_js_status_choose_image_first'),
                'detecting_faces'                 => lang('jcogs_img_pro_field_js_status_detecting_faces'),
                'face_detect_action_missing'      => lang('jcogs_img_pro_field_js_status_face_detect_action_missing'),
                'face_detection_failed'           => lang('jcogs_img_pro_field_js_status_face_detection_failed'),
                'face_detected_one'               => lang('jcogs_img_pro_field_js_face_detected_one'),
                'face_detected_many'              => lang('jcogs_img_pro_field_js_face_detected_many'),
                'face_detected_cached_suffix'     => lang('jcogs_img_pro_field_js_face_detected_cached_suffix'),
                'face_detect_timed_out'           => lang('jcogs_img_pro_field_js_face_detect_timed_out'),
                'face_detect_oom'                 => lang('jcogs_img_pro_field_js_face_detect_oom'),
                'faces_detected'                  => lang('jcogs_img_pro_field_js_status_faces_detected'),
                'no_faces_detected'               => lang('jcogs_img_pro_field_js_status_no_faces_detected'),
                'no_suggested_focal'              => lang('jcogs_img_pro_field_js_status_no_suggested_focal'),
                'suggested_focal_applied'         => lang('jcogs_img_pro_field_js_status_suggested_focal_applied'),
                'no_face_collection_box'          => lang('jcogs_img_pro_field_js_status_no_face_collection_box'),
                'invalid_face_detection_result'   => lang('jcogs_img_pro_field_js_status_invalid_face_detection_result'),
                'crop_applied_from_faces'         => lang('jcogs_img_pro_field_js_status_crop_applied_from_faces'),
                'face_overlay_cleared'            => lang('jcogs_img_pro_field_js_status_face_overlay_cleared'),
                'btn_crop'                        => lang('jcogs_img_pro_field_editor_btn_crop'),
                'btn_edit_crop'                   => lang('jcogs_img_pro_field_editor_btn_edit_crop'),
            ];

            $config = [
                'token' => $token,
                'i18n'  => $i18n,
            ];

            // Allow companion add-ons to extend the publish JS config (for UI components,
            // action IDs, i18n, etc.) without requiring modifications to the field add-on.
            try {
                if (isset(ee()->extensions) && ee()->extensions->active_hook('jcogs_img_pro_field_publish_js_config')) {
                    $hook_context = [
                        'site_id' => (int) $site_id,
                        'entry_id' => (int) $entry_id,
                        'field_id' => (int) $field_id,
                        'file_id' => (int) $file_id,
                        'usage_action_id' => (int) $usage_action_id,
                        'preview_action_id' => (int) $preview_action_id,
                        'face_detect_action_id' => (int) $face_detect_action_id,
                        'act_url' => (string) $act_url,
                        'preview_act_url' => (string) $preview_act_url,
                        'face_detect_act_url' => (string) $face_detect_act_url,
                        'settings' => is_array($this->settings) ? $this->settings : [],
                    ];
                    $maybe = ee()->extensions->call('jcogs_img_pro_field_publish_js_config', $config, $hook_context);
                    if ($maybe !== false && is_array($maybe)) {
                        $config = $maybe;
                    }
                }
            } catch (\Throwable $e) {
                // Fail safe: never break publish UI.
            }

            $script = '<script>'
                . 'window.JCOGS_IMG_PRO_FIELD_PUBLISH = ' . json_encode($config) . ';'
                . '</script>';

            try {
                if (isset(ee()->cp) && method_exists(ee()->cp, 'add_to_head')) {
                    ee()->cp->add_to_head($script);
                }
            }
            catch (\Throwable $e) {
                // no-op
            }
        }

        // Use EE's native File field UI (drag+drop) but store numeric file_id.
        ee()->javascript->set_global([
            'file.publishCreateUrl' => ee('CP/URL')->make('files/file/view/###', ['modal_form' => 'y'])->compile(),
        ]);
        $allowed_dirs = trim((string) ($this->settings['allowed_directories'] ?? 'all'));
        if ($allowed_dirs !== 'all') {
            $allowed_dirs = (is_numeric($allowed_dirs) && (int) $allowed_dirs > 0) ? (int) $allowed_dirs : 'all';
        }
        $html .= '<div class="jcogs-img-pro-field-main-picker">';
        $html .= ee()->file_field->dragAndDropField($this->field_name, ($file_id > 0 ? (string) $file_id : ''), $allowed_dirs, 'image');
        $html .= '</div>';

        // Only show the Image Pro options UI when there is something the editor can actually adjust.
        // Debug mode should not force the options panel open.
        $show_options = (
            $show_preset_selector
            || $enable_crop
            || $enable_focal
            || $enable_manual
            || $enable_art_direction
        );

        $use_modal = $show_options;

        $show_tab_preset       = (bool) $show_preset_selector;
        $show_tab_crop         = (bool) $enable_crop;
        $show_tab_art_direction = (bool) $enable_art_direction;
        $tab_count = (int) ($show_tab_preset ? 1 : 0)
            + (int) ($show_tab_crop ? 1 : 0)
            + (int) ($show_tab_art_direction ? 1 : 0);
        $use_tabs = ($tab_count >= 2);
        $default_tab = $show_tab_preset
            ? 'preset'
            : ($show_tab_crop ? 'crop' : 'art_direction');

        // When all Image Pro Field options are disabled, show a simple visual preview so editors still
        // get inline confirmation of what’s selected (without relying on preview actions / AJAX).
        if (! $show_options) {
            $thumb_html = '';
            $abs_url    = '';
            $title      = '';
            try {
                $file = null;
                if ($file_id > 0) {
                    $file = ServiceCache::file_repo()->findFileWithUploadDestination((int) $file_id);
                }
                if ($file) {
                    if (method_exists($file, 'getAbsoluteURL')) {
                        $abs_url = (string) $file->getAbsoluteURL();
                    }
                    if (isset($file->title) && is_string($file->title) && trim($file->title) !== '') {
                        $title = $file->title;
                    }
                    elseif (isset($file->file_name) && is_string($file->file_name)) {
                        $title = $file->file_name;
                    }

                    try {
                        $thumb_html = (string) ee('Thumbnail')->get($file)->tag;
                    }
                    catch (\Throwable $e) {
                        $thumb_html = '';
                    }
                }
            }
            catch (\Throwable $e) {
                $thumb_html = '';
                $abs_url    = '';
                $title      = '';
            }

            $html .= '<div class="jcogs-img-pro-field-basic-preview" style="margin-top:8px;">'
                . '<div class="field-instruct">'
                . lang('jcogs_img_pro_field_editor_label_preview')
                . '<div style="margin-top:2px; opacity:.8; font-size:12px;">Source image only — final output depends on templates and defaults.</div>'
                . '</div>';

            if ($file_id > 0 && ($thumb_html !== '' || $abs_url !== '')) {
                $inner = '';
                if ($thumb_html !== '') {
                    $inner = $thumb_html;
                }
                elseif ($abs_url !== '') {
                    $inner = '<img src="' . htmlspecialchars($abs_url, ENT_QUOTES, 'UTF-8') . '"'
                        . ' alt=""'
                        . ' style="max-width:100%; height:auto; display:block; border:1px solid #e2e6ea; border-radius:4px;">';
                }

                if ($abs_url !== '') {
                    $html .= '<a href="' . htmlspecialchars($abs_url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener"'
                        . ' style="display:inline-block; text-decoration:none;">'
                        . $inner
                        . '</a>';
                }
                else {
                    $html .= $inner;
                }

                if (trim($title) !== '') {
                    $html .= '<div style="margin-top:6px; opacity:.8; font-size:12px;">'
                        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
                        . '</div>';
                }
            }
            else {
                $html .= '<div style="min-height: 40px; opacity:.8; font-size:12px;">'
                    . 'Select an image to see a preview.'
                    . '</div>';
            }

            $html .= '</div>';
        }

        // Image Pro options: keep the default view close to EE's native File field.
        if ($show_options) {
            $aspect_ratio_choices_for_chip = $this->get_aspect_ratio_choices_from_field_settings();
            $aspect_ratio_choices_count = is_array($aspect_ratio_choices_for_chip) ? count($aspect_ratio_choices_for_chip) : 0;

            $chips = ServiceCache::publish_ui_chips()->buildChips([
                'enable_preset' => (bool) ($enable_preset && $enable_preset_choice),
                'preset_id' => $preset_id,
                'preset_options' => $preset_options,
                'default_preset_id' => (int) $default_preset_id_int,
                'enable_art_direction' => (bool) $enable_art_direction,
                'ad_rows' => $ad_rows,
                'usage_payload' => $usage_payload,
                'enable_crop' => (bool) $enable_crop,
                'has_any_override' => (bool) $has_any_override,
                'crop_rect_width' => $crop_rect_width,
                'crop_rect_height' => $crop_rect_height,
                'crop_offset_x' => $crop_offset_x,
                'crop_offset_y' => $crop_offset_y,
                'width' => $width,
                'height' => $height,
                'crop' => $crop,
                'aspect_ratio_effective' => $aspect_ratio_effective,
                'aspect_ratio_choices_count' => (int) $aspect_ratio_choices_count,
                'enable_focal' => (bool) $enable_focal,
                'focal_x' => $focal_x,
                'focal_y' => $focal_y,
            ]);

            if ($use_modal) {
                $html .= ServiceCache::publish_ui_shell()->renderCompositeSummary($chips);
                $html .= ServiceCache::publish_ui_shell()->renderCompositeModalOpen();
            }

            $html .= ServiceCache::publish_ui_shell()->renderOptionsOpen($chips, (bool) $has_any_override, $use_modal);

            $html .= ServiceCache::publish_ui_shell()->renderWorkspaceOpen();
            $html .= ServiceCache::publish_ui_shell()->renderControlsOpen();
            $html .= ServiceCache::publish_ui_shell()->renderStatusBlock($entry_id > 0 && $field_id > 0);
            $debug_context = [
                'field_id' => (string) $field_id,
                'content_type' => $context_content_type,
                'row_id' => $context_row_id,
                'container_id' => $context_container_id,
                'fluid_field_data_id' => $context_fluid_field_data_id,
                'block_id' => $context_block_id,
            ];
            $raw_ad_count = isset($raw_ad_count) ? $raw_ad_count : 0;
            $raw_ad_keys = isset($raw_ad_keys) ? $raw_ad_keys : '';
            $db_ad_count = isset($db_ad_count) ? $db_ad_count : '';
            $db_ad_keys = isset($db_ad_keys) ? $db_ad_keys : '';
            $db_payload_excerpt = isset($db_payload_excerpt) ? $db_payload_excerpt : '';
            $debug_ad_count = 0;
            $debug_ad_keys = '';
            if (isset($usage_payload['art_direction']) && is_array($usage_payload['art_direction'])
                && isset($usage_payload['art_direction']['files']) && is_array($usage_payload['art_direction']['files'])) {
                $debug_ad_count = count($usage_payload['art_direction']['files']);
                $debug_ad_keys = implode(', ', array_map('strval', array_keys($usage_payload['art_direction']['files'])));
            }
            $debug_context['ad_count'] = $debug_ad_count;
            $debug_context['ad_keys'] = $debug_ad_keys;
            $debug_context['raw_ad_count'] = (string) $raw_ad_count;
            $debug_context['raw_ad_keys'] = $raw_ad_keys;
            $debug_context['usage_row_id'] = (string) $usage_row_id;
            $debug_context['db_ad_count'] = $db_ad_count;
            $debug_context['db_ad_keys'] = $db_ad_keys;
            $debug_context['db_payload_excerpt'] = $db_payload_excerpt;
            $debug_context['ad_enabled_setting'] = (string) ($effective_settings['enable_art_direction'] ?? '');
            $debug_context['ad_rows_count'] = (string) (is_array($ad_rows) ? count($ad_rows) : 0);
            $settings_keys = is_array($this->settings) ? implode(', ', array_map('strval', array_keys($this->settings))) : '';
            $debug_context['settings_keys'] = $settings_keys;
            $debug_context['settings_enable_ad'] = isset($this->settings['enable_art_direction']) ? (string) $this->settings['enable_art_direction'] : '';
            $grid_col_id = $context_content_type === 'grid' ? $this->resolveGridColumnId() : null;
            $debug_context['grid_col_id'] = $grid_col_id !== null ? (string) $grid_col_id : '';
            if ($context_content_type === 'grid' && $field_id > 0) {
                $grid_debug = $this->get_grid_column_settings_debug($grid_col_id ?: $field_id);
                $debug_context['grid_col_keys'] = $grid_debug['keys'] ?? '';
                $debug_context['grid_col_enable_ad'] = $grid_debug['enable_ad'] ?? '';
                $debug_context['grid_col_excerpt'] = $grid_debug['excerpt'] ?? '';
            }

            $html .= $this->render_debug_panel_if_needed((int) $field_id, $debug_enabled, $debug_context);

            $restore_button_html = '';

            if ($use_tabs) {
                $html .= '<div class="jcogs-img-pro-field-tabs tab-bar" style="margin:0 0 10px 0;">'
                    . '<div class="tab-bar__tabs">';
                if ($show_tab_preset) {
                    $html .= '<button type="button" class="tab-bar__tab jcogs-img-pro-field-tab"'
                        . ' data-jcogs-tab="preset"'
                        . ($default_tab === 'preset' ? ' data-jcogs-tab-default="1"' : '')
                        . '>'
                        . lang('jcogs_img_pro_field_editor_label_preset')
                        . '</button>';
                }
                if ($show_tab_crop) {
                    $html .= '<button type="button" class="tab-bar__tab jcogs-img-pro-field-tab"'
                        . ' data-jcogs-tab="crop"'
                        . ($default_tab === 'crop' ? ' data-jcogs-tab-default="1"' : '')
                        . '>'
                        . lang('jcogs_img_pro_field_editor_heading_crop')
                        . '</button>';
                }
                if ($show_tab_art_direction) {
                    $html .= '<button type="button" class="tab-bar__tab jcogs-img-pro-field-tab"'
                        . ' data-jcogs-tab="art_direction"'
                        . ($default_tab === 'art_direction' ? ' data-jcogs-tab-default="1"' : '')
                        . '>'
                        . lang('jcogs_img_pro_field_editor_heading_art_direction')
                        . '</button>';
                }
                $html .= '</div></div>';
            }

            if ($show_preset_selector) {
                if ($use_tabs) {
                    $html .= '<div class="jcogs-img-pro-field-tab-panel" data-jcogs-tab-panel="preset">';
                }
                $html .= '<div class="jcogs-img-pro-field-tab-intro">' . lang('jcogs_img_pro_field_editor_intro_preset') . '</div>';
                $html .= '<div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; padding:8px 0;">';
                $html .= '<div>';
                $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_preset') . '</div>';
                $html .= form_dropdown(
                    'jcogs_img_pro_field[' . (int) $field_id . '][preset_id]',
                    $preset_options,
                    $preset_id,
                    'style="width:220px;"',
                );
                $html .= '</div>';

                // Standalone preview: primarily to show preset effect.
                if ($entry_id > 0 && $field_id > 0 && $preview_act_url !== '') {
                    $html .= '<div>';
                    $html .= '<div class="field-instruct">&nbsp;</div>';
                    $html .= form_button([
                        'type'    => 'button',
                        'class'   => 'button button--secondary jcogs-img-pro-field-preview',
                        'content' => lang('jcogs_img_pro_field_editor_btn_preview_preset'),
                    ]);
                    $html .= '</div>';
                }
                $html .= '</div>';
                if ($use_tabs) {
                    $html .= '</div>';
                }
            }
            // Hidden structured crop rectangle (percentages of source; used for exact restoration).
            $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][field_id]', (int) $field_id);
            $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][file_value]', $file_id > 0 ? (string) $file_id : '');
            $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][file_input_name]', (string) $this->field_name);
            $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][content_type]', $context_content_type);
            $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][container_id]', $context_container_id !== null ? (int) $context_container_id : '');
            $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][row_id]', $context_row_id !== null ? (int) $context_row_id : '');
            $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][fluid_field_data_id]', $context_fluid_field_data_id !== null ? (int) $context_fluid_field_data_id : '');
            $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][block_id]', $context_block_id !== null ? (int) $context_block_id : '');
            $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][crop_rect_left]', $crop_rect_left);
            $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][crop_rect_top]', $crop_rect_top);
            $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][crop_rect_width]', $crop_rect_width);
            $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][crop_rect_height]', $crop_rect_height);
            $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][crop_present]', $has_crop_defined ? '1' : '');

            if ($debug_enabled) {
                $html .= form_hidden('jcogs_img_pro_field_debug', '1');
            }

            if ($enable_crop) {
                // Canonical aspect ratio value (used by preview + saved usage payload).
                $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][aspect_ratio]', $aspect_ratio_hidden_value);
            }
        }

        // Crop section: buttons left, preview right.
        if ($enable_crop) {
            if ($use_tabs) {
                $html .= '<div class="jcogs-img-pro-field-tab-panel" data-jcogs-tab-panel="crop">';
            }
            $html .= '<div style="margin-top:8px; padding:8px 0; border-top:1px solid #eee;">';
            $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_heading_crop') . '</div>';
            $html .= '<div class="jcogs-img-pro-field-tab-intro">' . lang('jcogs_img_pro_field_editor_intro_crop') . '</div>';
            $html .= '<div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-start;">';

            $html .= '<div style="flex: 1 1 260px; min-width: 260px;">';
            if ($entry_id > 0 && $field_id > 0 && $preview_act_url !== '') {
                $html .= '<div class="button-group">';
                $html .= form_button([
                    'type'    => 'button',
                    'class'   => 'button button--primary jcogs-img-pro-field-pick-rect',
                    'content' => ($has_crop_defined ? lang('jcogs_img_pro_field_editor_btn_edit_crop') : lang('jcogs_img_pro_field_editor_btn_crop')),
                ]);
                $html .= form_button([
                    'type'    => 'button',
                    'class'   => 'button button--secondary jcogs-img-pro-field-preview jcogs-img-pro-field-preview-reload',
                    'content' => lang('jcogs_img_pro_field_editor_btn_reload_preview'),
                ]);
                $html .= form_button([
                    'type'    => 'button',
                    'class'   => 'button button--secondary jcogs-img-pro-field-danger-outline jcogs-img-pro-field-clear-rect',
                    'content' => lang('jcogs_img_pro_field_editor_btn_clear_crop'),
                ]);
                $html .= '</div>';
                if (! $has_crop_defined) {
                    $html .= '<div style="margin-top:6px; opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_editor_help_crop_click_crop') . '</div>';
                }
                $html .= '<div style="margin-top:6px; opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_editor_help_crop_pick') . '</div>';
                if ($require_aspect_ratio && $aspect_ratio_effective !== '' && $aspect_ratio_choices_count <= 1) {
                    $html .= '<div style="margin-top:6px; opacity:.8; font-size:12px;">'
                        . sprintf(lang('jcogs_img_pro_field_editor_help_crop_aspect_enforced'), htmlspecialchars((string) $aspect_ratio_effective, ENT_QUOTES, 'UTF-8'))
                        . '</div>';
                }

                $aspect_ratio_choices = $this->get_aspect_ratio_choices_from_field_settings();
                if (is_array($aspect_ratio_choices) && count($aspect_ratio_choices) > 1) {
                    $options = $require_aspect_ratio
                        ? $aspect_ratio_choices
                        : (['' => lang('jcogs_img_pro_field_editor_option_inherit')] + $aspect_ratio_choices);
                    if ($aspect_ratio_effective !== '' && ! array_key_exists($aspect_ratio_effective, $options)) {
                        $options[$aspect_ratio_effective] = sprintf(lang('jcogs_img_pro_field_editor_option_custom_aspect'), $aspect_ratio_effective);
                    }

                    if ($require_aspect_ratio) {
                        $selected_for_ui = $aspect_ratio_effective;
                        if ($selected_for_ui === '') {
                            foreach (array_keys($options) as $k) {
                                $selected_for_ui = (string) $k;
                                break;
                            }
                        }
                    }
                    else {
                        $selected_for_ui = $aspect_ratio_is_inherit_override ? '' : $aspect_ratio_effective;
                    }

                    $html .= '<div style="margin-top:10px; max-width:220px;">';
                    $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_aspect_ratio') . '</div>';
                    $html .= '<select class="select jcogs-img-pro-field-aspect-ratio-select" style="width:220px;">';
                    foreach ($options as $val => $label) {
                        $sel   = ((string) $val === (string) $selected_for_ui) ? ' selected' : '';
                        $html .= '<option value="' . htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
                            . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8')
                            . '</option>';
                    }
                    $html .= '</select>';
                    $html .= '<div style="margin-top:4px; opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_editor_help_aspect_locks') . '</div>';
                    $html .= '</div>';
                }
            }
            else {
                $html .= '<div style="opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_editor_help_crop_after_create') . '</div>';
            }
            $html .= '</div>';

            $html .= '</div>';

            $html .= '</div>';

            // Focal / Face detection (placed after crop so editors typically start with cropping/aspect).
            if ($enable_focal) {
                $has_prev_section  = ($show_preset_selector || $enable_crop || $enable_manual);
                $html             .= '<div style="margin-top:' . ($has_prev_section ? '8px' : '0') . '; padding:8px 0; border-top:' . ($has_prev_section ? '1px solid #eee' : 'none') . ';">';
                $html             .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_heading_focal') . '</div>';
                $html             .= '<div class="jcogs-img-pro-field-tab-intro">' . lang('jcogs_img_pro_field_editor_intro_focal') . '</div>';

                // Primary UX: click-to-set.
                if ($entry_id > 0 && $field_id > 0 && $preview_act_url !== '') {
                    $html .= '<div class="button-group">';
                    if (! $enable_crop && ! $show_preset_selector) {
                        $html .= form_button([
                            'type'    => 'button',
                            'class'   => 'button button--secondary jcogs-img-pro-field-preview',
                            'content' => lang('jcogs_img_pro_field_editor_btn_load_preview'),
                        ]);
                    }
                    $html .= form_button([
                        'type'    => 'button',
                        'class'   => 'button button--primary jcogs-img-pro-field-pick-focal',
                        'content' => lang('jcogs_img_pro_field_editor_btn_pick_focal'),
                    ]);
                    $html .= form_button([
                        'type'    => 'button',
                        'class'   => 'button button--secondary jcogs-img-pro-field-danger-outline jcogs-img-pro-field-clear-focal',
                        'content' => lang('jcogs_img_pro_field_editor_btn_clear_focal'),
                    ]);
                    $html .= '</div>';
                    $html .= '<div style="margin-top:6px; opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_editor_help_pick_focal') . '</div>';
                }
                else {
                    $html .= '<div style="opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_editor_help_focal_after_create') . '</div>';
                }

                // Advanced numeric inputs (superadmins only).
                if ($is_superadmin) {
                    $html .= '<details class="jcogs-img-pro-field-advanced" style="margin-top:8px;">'
                        . '<summary style="cursor:pointer; user-select:none; opacity:.85; font-size:12px;"><span class="sub-arrow"></span>' . lang('jcogs_img_pro_field_editor_summary_advanced_numeric') . '</summary>'
                        . '<div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:8px;">';

                    $html .= '<div>';
                    $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_focal_x') . '</div>';
                    $html .= form_input([
                        'name'        => 'jcogs_img_pro_field[' . (int) $field_id . '][focal_x]',
                        'value'       => $focal_x,
                        'placeholder' => '50',
                        'style'       => 'width:120px;',
                    ]);
                    $html .= '</div>';

                    $html .= '<div>';
                    $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_focal_y') . '</div>';
                    $html .= form_input([
                        'name'        => 'jcogs_img_pro_field[' . (int) $field_id . '][focal_y]',
                        'value'       => $focal_y,
                        'placeholder' => '50',
                        'style'       => 'width:120px;',
                    ]);
                    $html .= '</div>';

                    $html .= '</div></details>';
                }
                else {
                    // Hidden inputs so focal can still be saved without exposing numeric controls.
                    $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][focal_x]', $focal_x);
                    $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][focal_y]', $focal_y);
                }

                if ($enable_face_detect && $entry_id > 0 && $field_id > 0 && $face_detect_act_url !== '') {
                    $face_ui_visible  = ($face_detect_controls_mode !== 'hidden');
                    $html            .= '<div class="jcogs-img-pro-field-face-detect-ui" style="' . ($face_ui_visible ? 'display:block;' : 'display:none;') . ' margin-top:10px; padding-top:8px; border-top:1px solid #eee;">'
                        . '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_heading_face_detection') . '</div>'
                        . '<div class="jcogs-img-pro-field-tab-intro">' . lang('jcogs_img_pro_field_editor_intro_face_detection') . '</div>'
                        . '<div class="jcogs-img-pro-field-face-detect-summary" style="opacity:.85; font-size:12px; color:#555;">' . ($face_ui_visible ? lang('jcogs_img_pro_field_editor_help_face_detection') : '') . '</div>';

                    $html .= '<div class="button-group" style="margin-top:6px;">'
                        . form_button([
                            'type'    => 'button',
                            'class'   => 'button button--primary jcogs-img-pro-field-face-detect',
                            'content' => lang('jcogs_img_pro_field_editor_btn_detect_faces'),
                        ])
                        . form_button([
                            'type'    => 'button',
                            'class'   => 'button button--secondary jcogs-img-pro-field-face-apply-focal',
                            'content' => lang('jcogs_img_pro_field_editor_btn_apply_suggested_focal'),
                        ])
                        . form_button([
                            'type'    => 'button',
                            'class'   => 'button button--secondary jcogs-img-pro-field-face-apply-crop',
                            'content' => lang('jcogs_img_pro_field_editor_btn_apply_crop_from_faces'),
                        ])
                        . form_button([
                            'type'    => 'button',
                            'class'   => 'button button--secondary jcogs-img-pro-field-danger-outline jcogs-img-pro-field-face-clear-overlay',
                            'content' => lang('jcogs_img_pro_field_editor_btn_clear_overlay'),
                        ])
                        . '</div>';

                    $settings_row_html = ''
                        . '<div style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-top:6px;">'
                        . '<div>'
                        . '<div class="field-instruct" style="font-size:12px;">' . lang('jcogs_img_pro_field_editor_label_quality') . '</div>'
                        . '<select class="select jcogs-img-pro-field-face-quality" style="width:140px;">'
                        . '<option value="fast"' . ($face_detect_default_quality === 'fast' ? ' selected' : '') . '>fast</option>'
                        . '<option value="balanced"' . ($face_detect_default_quality === 'balanced' ? ' selected' : '') . '>balanced</option>'
                        . '<option value="accurate"' . ($face_detect_default_quality === 'accurate' ? ' selected' : '') . '>accurate</option>'
                        . '</select>'
                        . '</div>'
                        . '<div>'
                        . '<div class="field-instruct" style="font-size:12px;">' . lang('jcogs_img_pro_field_editor_label_sensitivity') . '</div>'
                        . '<input type="number" class="input jcogs-img-pro-field-face-sensitivity" value="' . (int) $face_detect_default_sensitivity . '" min="1" max="9" step="1" style="width:90px;">'
                        . '</div>'
                        . '<div>'
                        . '<div class="field-instruct" style="font-size:12px;">' . lang('jcogs_img_pro_field_editor_label_margin_px') . '</div>'
                        . '<input type="number" class="input jcogs-img-pro-field-face-margin" value="' . (int) $face_detect_default_margin . '" min="0" max="500" step="1" style="width:110px;">'
                        . '</div>'
                        . '<label style="display:flex; align-items:center; gap:6px; font-size:12px; opacity:.9;">'
                        . '<input type="checkbox" class="jcogs-img-pro-field-face-force" value="1">'
                        . '<span>' . lang('jcogs_img_pro_field_editor_label_ignore_cache') . '</span>'
                        . '</label>'
                        . '<div>'
                        . form_button([
                            'type'    => 'button',
                            'class'   => 'button button--default jcogs-img-pro-field-face-restore-defaults',
                            'content' => lang('jcogs_img_pro_field_editor_btn_restore_defaults'),
                        ])
                        . '</div>'
                        . '</div>';

                    if ($face_detect_controls_mode === 'visible') {
                        $html .= $settings_row_html;
                    }
                    elseif ($face_detect_controls_mode === 'advanced') {
                        $html .= '<details class="jcogs-img-pro-field-advanced" style="margin-top:6px;">'
                            . '<summary style="cursor:pointer; user-select:none; opacity:.85; font-size:12px;"><span class="sub-arrow"></span>' . lang('jcogs_img_pro_field_editor_summary_face_detection_settings') . '</summary>'
                            . '<div style="margin-top:8px;">'
                            . $settings_row_html
                            . '</div>'
                            . '</details>';
                    }
                    $html .= '</div>';
                }

                $html .= '</div>';
            }

                // Manual (advanced) crop entry grid.
                if ($enable_manual) {
                    $html .= '<div style="margin-top:8px; padding:8px 0; border-top:1px solid #eee;">'
                        . '<label style="display:flex; align-items:center; gap:6px;">'
                        . '<input type="checkbox" class="jcogs-img-pro-field-toggle-manual" value="1">'
                        . '<span class="field-instruct" style="margin:0;">' . lang('jcogs_img_pro_field_editor_label_edit_manually') . '</span>'
                        . '</label>'
                        . '<div style="opacity:.8; font-size:12px;">' . lang('jcogs_img_pro_field_editor_help_manual') . '</div>'
                        . '</div>';

                    $html .= '<div class="jcogs-img-pro-field-manual" style="display:none;">';
                    $html .= '<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-top:8px;">';

                    $html .= '<div style="grid-column: 1 / -1;">';
                    $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_crop_override_raw') . '</div>';
                    $html .= form_input([
                        'name'        => 'jcogs_img_pro_field[' . (int) $field_id . '][crop]',
                        'value'       => $crop,
                        'placeholder' => lang('jcogs_img_pro_field_editor_placeholder_crop_override'),
                        'style'       => 'width:100%;',
                    ]);
                    $html .= '</div>';

                    $html .= '<div>';
                    $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_crop_mode') . '</div>';
                    $html .= form_dropdown(
                        'jcogs_img_pro_field[' . (int) $field_id . '][crop_mode]',
                        [
                            ''    => lang('jcogs_img_pro_field_editor_option_inherit'),
                            'yes' => 'yes',
                            'no'  => 'no',
                        ],
                        $crop_mode,
                        'style="width:140px;"',
                    );
                    $html .= '</div>';

                    $html .= '<div>';
                    $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_focus_h') . '</div>';
                    $html .= form_dropdown(
                        'jcogs_img_pro_field[' . (int) $field_id . '][crop_focus_h]',
                        [
                            ''       => lang('jcogs_img_pro_field_editor_option_inherit'),
                            'left'   => 'left',
                            'center' => 'center',
                            'right'  => 'right',
                        ],
                        $crop_focus_h,
                        'style="width:140px;"',
                    );
                    $html .= '</div>';

                    $html .= '<div>';
                    $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_focus_v') . '</div>';
                    $html .= form_dropdown(
                        'jcogs_img_pro_field[' . (int) $field_id . '][crop_focus_v]',
                        [
                            ''       => lang('jcogs_img_pro_field_editor_option_inherit'),
                            'top'    => 'top',
                            'center' => 'center',
                            'bottom' => 'bottom',
                        ],
                        $crop_focus_v,
                        'style="width:140px;"',
                    );
                    $html .= '</div>';

                    $html .= '<div>';
                    $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_offset_x') . '</div>';
                    $html .= form_input([
                        'name'        => 'jcogs_img_pro_field[' . (int) $field_id . '][crop_offset_x]',
                        'value'       => $crop_offset_x,
                        'placeholder' => '0% / 10px',
                        'style'       => 'width:120px;',
                    ]);
                    $html .= '</div>';

                    $html .= '<div>';
                    $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_crop_width') . '</div>';
                    $html .= form_input([
                        'name'        => 'jcogs_img_pro_field[' . (int) $field_id . '][width]',
                        'value'       => $width,
                        'placeholder' => '50% / 300px',
                        'style'       => 'width:120px;',
                    ]);
                    $html .= '</div>';

                    $html .= '<div>';
                    $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_offset_y') . '</div>';
                    $html .= form_input([
                        'name'        => 'jcogs_img_pro_field[' . (int) $field_id . '][crop_offset_y]',
                        'value'       => $crop_offset_y,
                        'placeholder' => '0% / 10px',
                        'style'       => 'width:120px;',
                    ]);
                    $html .= '</div>';

                    $html .= '<div>';
                    $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_crop_height') . '</div>';
                    $html .= form_input([
                        'name'        => 'jcogs_img_pro_field[' . (int) $field_id . '][height]',
                        'value'       => $height,
                        'placeholder' => '50% / 300px',
                        'style'       => 'width:120px;',
                    ]);
                    $html .= '</div>';

                    $html .= '<div>';
                    $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_aspect_ratio') . '</div>';
                    $html .= '<input type="text" class="input jcogs-img-pro-field-aspect-ratio-manual"'
                        . ' value="' . htmlspecialchars($aspect_ratio_is_inherit_override ? '' : $aspect_ratio_effective, ENT_QUOTES, 'UTF-8') . '"'
                        . ' placeholder="16_9"'
                        . ' style="width:120px; font-family: ui-monospace, Menlo, Monaco, monospace; font-size: 12px;"'
                        . '>';
                    $html .= '</div>';

                    $html .= '<div>';
                    $html .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_label_smart_scaling') . '</div>';
                    $html .= form_dropdown(
                        'jcogs_img_pro_field[' . (int) $field_id . '][crop_smart_scaling]',
                        [
                            ''    => lang('jcogs_img_pro_field_editor_option_inherit'),
                            'yes' => 'yes',
                            'no'  => 'no',
                        ],
                        $crop_smart_scaling,
                        'style="width:160px;"',
                    );
                    $html .= '</div>';

                    $html .= '</div>';
                    $html .= '</div>';
                }

                if ($use_tabs) {
                    $html .= '</div>';
                }
            }

        // Art direction (placed after crop + focal so it's treated as the most “structural” override).
        if ($enable_art_direction) {
            if ($use_tabs) {
                $html .= '<div class="jcogs-img-pro-field-tab-panel" data-jcogs-tab-panel="art_direction">';
            }

            // Hidden flag used to distinguish “user cleared all alternates” from
            // “art-direction inputs are present but untouched this request”.
            $html .= form_hidden('jcogs_img_pro_field[' . (int) $field_id . '][art_direction_dirty]', '0');

            $idx_to_media = [];
            foreach ($ad_rows as $r) {
                $i = (int) ($r['index'] ?? 0);
                $m = isset($r['media']) ? (string) $r['media'] : '';
                if ($i > 0 && $m !== '') {
                    $idx_to_media[$i] = $m;
                }
            }
            if (! empty($idx_to_media)) {
                $html .= '<input type="hidden"'
                    . ' class="jcogs-img-pro-field-ad-index-to-media"'
                    . ' name="jcogs_img_pro_field[' . (int) $field_id . '][art_direction_index_to_media]"'
                    . ' value="' . htmlspecialchars(json_encode($idx_to_media), ENT_QUOTES, 'UTF-8') . '"'
                    . '>';
            }

            $files = [];
            if (
                isset($usage_payload['art_direction']) && is_array($usage_payload['art_direction'])
                && isset($usage_payload['art_direction']['files']) && is_array($usage_payload['art_direction']['files'])
            ) {
                $files = $usage_payload['art_direction']['files'];
            }

            $has_prev_section  = ($show_preset_selector || $enable_crop || $enable_focal || $enable_manual);
            $html             .= '<div style="margin-top:' . ($has_prev_section ? '8px' : '0') . '; padding:8px 0; border-top:' . ($has_prev_section ? '1px solid #eee' : 'none') . ';">';
            $html             .= '<div class="field-instruct">' . lang('jcogs_img_pro_field_editor_heading_art_direction') . '</div>';
            $html             .= '<div style="font-size:12px; opacity:.85; margin:2px 0 8px 0;">'
                . lang('jcogs_img_pro_field_editor_help_art_direction')
                . '</div>';

            foreach ($ad_rows as $row) {
                $idx   = (int) ($row['index'] ?? 0);
                $media = (string) ($row['media'] ?? '');
                if ($idx <= 0 || $media === '') {
                    continue;
                }
                $preset_id_row = (int) ($row['preset_id'] ?? 0);
                // Prefer media-keyed storage; fall back to legacy numeric keys.
                $picked = 0;
                if ($media !== '' && isset($files[$media])) {
                    $picked = (int) $files[$media];
                }
                elseif (isset($files[(string) $idx])) {
                    $picked = (int) $files[(string) $idx];
                }

                $desc          = $this->describe_art_direction_media_for_editor($media);
                $label         = isset($desc['title']) ? (string) $desc['title'] : ($media !== '' ? $media : ('Breakpoint #' . $idx));
                $media_caption = isset($desc['media']) ? (string) $desc['media'] : $media;

                $html .= '<div class="jcogs-img-pro-field-ad-row">';
                $html .= '<div class="jcogs-img-pro-field-ad-row-meta">';
                $html .= '<div class="field-instruct jcogs-img-pro-field-ad-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>';
                if ($media_caption !== '') {
                    $html .= '<div class="jcogs-img-pro-field-ad-meta-line">'
                        . sprintf(lang('jcogs_img_pro_field_editor_ad_alt_media_caption'), '<span style="font-family: ui-monospace, Menlo, Monaco, monospace;">' . htmlspecialchars($media_caption, ENT_QUOTES, 'UTF-8') . '</span>')
                        . '</div>';
                }
                $html .= '<div class="jcogs-img-pro-field-ad-meta-line">' . lang('jcogs_img_pro_field_editor_ad_alt_help_fallback') . '</div>';
                if ($preset_id_row > 0) {
                    $html .= '<div class="jcogs-img-pro-field-ad-meta-line jcogs-img-pro-field-ad-meta-line--preset">'
                        . sprintf(lang('jcogs_img_pro_field_editor_ad_alt_preset_caption'), (int) $preset_id_row)
                        . '</div>';
                }
                $html .= '</div>';

                // IMPORTANT: EE's drag/drop file field JS can behave unexpectedly with complex bracketed names,
                // especially when multiple file pickers exist inside one field UI.
                // Use a unique picker field name, then sync into the real posted hidden input.
                $picker_name  = 'jcogs_img_pro_field_ad_' . (int) $field_id . '_' . (int) $idx;
                $storage_name = 'jcogs_img_pro_field[' . (int) $field_id . '][art_direction_files][' . (int) $idx . ']';

                $html .= '<div class="jcogs-img-pro-field-ad-row-picker">';
                $html .= '<div class="jcogs-img-pro-field-ad-picker" data-jcogs-ad-picker="1" data-ad-index="' . (int) $idx . '">';
                $html .= '<div class="grid-file-upload jcogs-img-pro-field-ad-file-container">';
                $html .= ee()->file_field->dragAndDropField($picker_name, ($picked > 0 ? (string) $picked : ''), $allowed_dirs, 'image');
                $html .= '</div>';
                $html .= '<input type="hidden"'
                    . ' name="' . htmlspecialchars($storage_name, ENT_QUOTES, 'UTF-8') . '"'
                    . ' value="' . htmlspecialchars((string) ($picked > 0 ? $picked : ''), ENT_QUOTES, 'UTF-8') . '"'
                    . ' data-jcogs-ad-storage="1"'
                    . ' data-picker-name="' . htmlspecialchars($picker_name, ENT_QUOTES, 'UTF-8') . '"'
                    . '>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
            }

            $html .= '</div>';

            if ($use_tabs) {
                $html .= '</div>';
            }
        }

            // Close controls column and add the preview column.
            if ($show_options) {
                $html .= ServiceCache::publish_ui_shell()->renderControlsClose();
                $html .= ServiceCache::publish_ui_shell()->renderPreviewColOpen();
                $html .= ServiceCache::publish_ui_shell()->renderPreviewBody(
                    $preview_available
                        ? lang('jcogs_img_pro_field_editor_help_preview_lazy')
                        : lang('jcogs_img_pro_field_editor_help_preview_after_create'),
                );
                $html .= ServiceCache::publish_ui_shell()->renderPreviewColClose();
                $html .= ServiceCache::publish_ui_shell()->renderWorkspaceClose();
            }

            $can_run_editor_ajax = ($show_options && $entry_id > 0 && $field_id > 0 && $act_url !== '');

                $html .= ServiceCache::publish_guidance()->buildGuidanceHtml(
                    $can_run_editor_ajax,
                    $show_options,
                    (int) $entry_id,
                    (int) $field_id,
                    (int) $usage_action_id,
                    (int) $preview_action_id,
                    (int) $face_detect_action_id,
                    (bool) $enable_crop,
                    (bool) $enable_focal,
                    (bool) $enable_face_detect,
                    (bool) $enable_art_direction,
                    (bool) $enable_debug,
                    (bool) $is_superadmin,
                );

            // Allow companion add-ons to append publish UI blocks inside the field UI.
            // Intended for add-ons like metadata/EXIF editors that need a dedicated panel.
            try {
                if (isset(ee()->extensions) && ee()->extensions->active_hook('jcogs_img_pro_field_publish_ui_html')) {
                    $hook_context = [
                        'position' => 'after_guidance',
                        'site_id' => (int) $site_id,
                        'entry_id' => (int) $entry_id,
                        'field_id' => (int) $field_id,
                        'file_id' => (int) $file_id,
                        'settings' => is_array($this->settings) ? $this->settings : [],
                        'usage_payload' => is_array($usage_payload) ? $usage_payload : [],
                    ];
                    $snippet = ee()->extensions->call('jcogs_img_pro_field_publish_ui_html', $hook_context);
                    if ($snippet !== false && is_string($snippet) && trim($snippet) !== '') {
                        $html .= $snippet;
                    }
                }
            } catch (\Throwable $e) {
                // Fail safe.
            }

            if ($show_options) {
                $html .= ServiceCache::publish_ui_shell()->renderOptionsClose($use_modal);
                if ($use_modal) {
                    $html .= ServiceCache::publish_ui_shell()->renderCompositeModalClose();
                }
            }

            $html .= '</div>';

            if ($enable_debug && $is_superadmin) {
                $html .= '<div class="field-instruct" style="margin-top:10px; font-family: ui-monospace, Menlo, Monaco, monospace; font-size: 12px; opacity: .85;">'
                    . 'Debug: site_id=' . (int) $site_id
                    . ' entry_id=' . (int) $entry_id
                    . ' field_id=' . (int) $field_id
                    . ' stored_file_id=' . (int) $file_id
                    . ' usage_action_id=' . (int) $usage_action_id
                    . ' preview_action_id=' . (int) $preview_action_id
                    . ' face_detect_action_id=' . (int) $face_detect_action_id
                    . ' show_options=' . ($show_options ? '1' : '0')
                    . ' show_preset_selector=' . ($show_preset_selector ? '1' : '0')
                    . ' enable_preset=' . ($enable_preset ? 'y' : 'n')
                    . ' enable_crop=' . ($enable_crop ? 'y' : 'n')
                    . ' enable_focal=' . ($enable_focal ? 'y' : 'n')
                    . ' enable_face_detect=' . ($enable_face_detect ? 'y' : 'n')
                    . ' enable_art_direction=' . ($enable_art_direction ? 'y' : 'n')
                    . ' settings{enable_preset=' . (string) ($this->settings['enable_preset'] ?? '')
                    . ', enable_crop=' . (string) ($this->settings['enable_crop'] ?? '')
                    . ', enable_focal=' . (string) ($this->settings['enable_focal'] ?? '')
                    . ', enable_face_detect=' . (string) ($this->settings['enable_face_detect'] ?? '')
                    . ', enable_art_direction=' . (string) ($this->settings['enable_art_direction'] ?? '')
                    . ', enable_debug=' . (string) ($this->settings['enable_debug'] ?? '')
                    . '}'
                    . '</div>';
            }

            return $html;
        }


    /**
     * Fieldtype save callback.
     *
     * Called by EE to store the main field value (the selected file_id).
     */
    public function save($data)
    {
        $file_id = $this->resolve_file_id($data);

        $field_id = (int) ($this->field_id ?: 0);
        if ($field_id > 0) {
            $entry_id = (int) ($this->content_id() ?: 0);
            $context = $this->resolveCompositeContext($entry_id);
            $content_type = (string) ($context['content_type'] ?? 'channel');

            if ($content_type === 'grid') {
                $container_id = isset($context['container_id']) && is_numeric($context['container_id']) ? (int) $context['container_id'] : null;
                $row_id = isset($context['row_id']) && is_numeric($context['row_id']) ? (int) $context['row_id'] : null;
                $fluid_field_data_id = isset($context['fluid_field_data_id']) && is_numeric($context['fluid_field_data_id']) ? (int) $context['fluid_field_data_id'] : null;
                $block_id = isset($context['block_id']) && is_numeric($context['block_id']) ? (int) $context['block_id'] : null;

                $posted = $this->extract_composite_posted_payload_for_validation(
                    $field_id,
                    $content_type,
                    $container_id,
                    $row_id,
                    $fluid_field_data_id,
                    $block_id
                );

                if (is_array($posted)) {
                    if (array_key_exists('file_value', $posted)) {
                        $raw = trim((string) $posted['file_value']);
                        $candidate = $this->resolve_file_id($posted['file_value']);
                        if ($raw === '' || $candidate > 0) {
                            $file_id = $candidate;
                        }
                    } elseif (array_key_exists('file_id', $posted)) {
                        $candidate = $this->resolve_file_id($posted['file_id']);
                        if ($candidate > 0) {
                            $file_id = $candidate;
                        }
                    }
                }
            }
        }

        return $file_id > 0 ? (string) $file_id : '';
    }

    private function render_debug_panel_if_needed(int $fieldId, bool $debugEnabled, array $debugContext = []): string
    {
        if (! $debugEnabled) {
            return '';
        }

        static $debugPayload = null;
        static $debugLoaded = false;

        if (! $debugLoaded) {
            $debugLoaded = true;
            $debugPayload = ee()->session->flashdata('jcogs_img_pro_field_debug');
        }

        $debugNote = ee()->session->flashdata('jcogs_img_pro_field_debug_note');
        $debugHook = ee()->session->flashdata('jcogs_img_pro_field_debug_hook');
        $debugPost = ee()->session->flashdata('jcogs_img_pro_field_debug_post');

        $rows = [];
        if (is_array($debugPayload) && ! empty($debugPayload)) {
            $rows = array_values(array_filter($debugPayload, static function ($row) use ($fieldId) {
                return is_array($row) && (int) ($row['field_id'] ?? 0) === $fieldId;
            }));
        }

        $html = '<div class="jcogs-img-pro-field-debug">'
            . '<div class="field-instruct">JCOGS Image Pro Field Debug</div>'
            . '<div style="font-size:12px; opacity:.85; margin:4px 0 8px 0;">Most recent save payload summary.</div>'
            . ($this->format_debug_context_line($debugContext))
            . '<table style="width:100%; border-collapse:collapse; font-size:12px;">'
            . '<thead><tr>'
            . '<th style="text-align:left; padding:4px 6px; border-bottom:1px solid #e2e6ea;">Context</th>'
            . '<th style="text-align:left; padding:4px 6px; border-bottom:1px solid #e2e6ea;">Row</th>'
            . '<th style="text-align:left; padding:4px 6px; border-bottom:1px solid #e2e6ea;">File</th>'
            . '<th style="text-align:left; padding:4px 6px; border-bottom:1px solid #e2e6ea;">Crop</th>'
            . '<th style="text-align:left; padding:4px 6px; border-bottom:1px solid #e2e6ea;">AD</th>'
            . '</tr></thead><tbody>';

        if (empty($rows)) {
            $state = 'No payload captured.';
            if ($debugNote === 'no_payload') {
                $state = 'No payload found in POST.';
            }
            if (is_array($debugHook) && isset($debugHook['hit']) && $debugHook['hit']) {
                $state .= ' Hook ran.';
                if (isset($debugHook['has_composite'])) {
                    $state .= ' Composite=' . ($debugHook['has_composite'] ? 'yes' : 'no') . '.';
                }
            } else {
                $state .= ' Hook did not run.';
            }
            $html .= '<tr><td colspan="5" style="padding:6px; color:#b91c1c;">' . htmlspecialchars($state, ENT_QUOTES, 'UTF-8') . '</td></tr>';
            if (is_array($debugPost)) {
                $keys = isset($debugPost['post_keys']) && is_array($debugPost['post_keys'])
                    ? implode(', ', array_map('strval', $debugPost['post_keys']))
                    : '';
                $fieldKeys = isset($debugPost['field_id_keys']) && is_array($debugPost['field_id_keys'])
                    ? implode(', ', array_map('strval', $debugPost['field_id_keys']))
                    : '';
                $adPickerKeys = isset($debugPost['ad_picker_keys']) && is_array($debugPost['ad_picker_keys'])
                    ? implode(', ', array_map('strval', $debugPost['ad_picker_keys']))
                    : '';
                $adPickerSamples = isset($debugPost['ad_picker_samples']) && is_array($debugPost['ad_picker_samples'])
                    ? implode(', ', array_map('strval', $debugPost['ad_picker_samples']))
                    : '';
                $html .= '<tr><td colspan="5" style="padding:6px; font-size:12px; color:#475569;">'
                    . 'POST keys: ' . htmlspecialchars($keys, ENT_QUOTES, 'UTF-8') . '<br>'
                    . 'field_id_* keys: ' . htmlspecialchars($fieldKeys, ENT_QUOTES, 'UTF-8') . '<br>'
                    . 'ad picker keys: ' . htmlspecialchars($adPickerKeys, ENT_QUOTES, 'UTF-8') . '<br>'
                    . 'ad picker samples: ' . htmlspecialchars($adPickerSamples, ENT_QUOTES, 'UTF-8')
                    . '</td></tr>';

                if (isset($debugPost['ad_storage_fields']) && is_array($debugPost['ad_storage_fields'])) {
                    foreach ($debugPost['ad_storage_fields'] as $fid => $entries) {
                        if (! is_array($entries)) {
                            continue;
                        }
                        $line = implode(', ', array_map('strval', $entries));
                        $html .= '<tr><td colspan="5" style="padding:6px; font-size:12px; color:#475569;">'
                            . 'ad storage field ' . htmlspecialchars((string) $fid, ENT_QUOTES, 'UTF-8') . ': '
                            . htmlspecialchars($line, ENT_QUOTES, 'UTF-8')
                            . '</td></tr>';
                    }
                }

                if (isset($debugPost['field_rows']) && is_array($debugPost['field_rows'])) {
                    foreach ($debugPost['field_rows'] as $fieldKey => $info) {
                        if (! is_array($info)) {
                            continue;
                        }
                        $rowsCount = (string) ($info['rows'] ?? '0');
                        $hasJcogs = ! empty($info['has_jcogs']) ? 'yes' : 'no';
                        $rowKeys = isset($info['row_keys']) && is_array($info['row_keys'])
                            ? implode(', ', array_map('strval', $info['row_keys']))
                            : '';
                        $jcogsType = isset($info['jcogs_type']) ? (string) $info['jcogs_type'] : '';
                        $jcogsKeys = isset($info['jcogs_keys']) && is_array($info['jcogs_keys'])
                            ? implode(', ', array_map('strval', $info['jcogs_keys']))
                            : '';
                        $html .= '<tr><td colspan="5" style="padding:6px; font-size:12px; color:#475569;">'
                            . htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') . ': '
                            . 'rows=' . htmlspecialchars($rowsCount, ENT_QUOTES, 'UTF-8')
                            . ', has_jcogs=' . htmlspecialchars($hasJcogs, ENT_QUOTES, 'UTF-8')
                            . ', row_keys=' . htmlspecialchars($rowKeys, ENT_QUOTES, 'UTF-8')
                            . ', jcogs_type=' . htmlspecialchars($jcogsType, ENT_QUOTES, 'UTF-8')
                            . ', jcogs_keys=' . htmlspecialchars($jcogsKeys, ENT_QUOTES, 'UTF-8')
                            . '</td></tr>';
                    }
                }
            }
        }

        foreach ($rows as $row) {
            $context = htmlspecialchars((string) ($row['content_type'] ?? ''), ENT_QUOTES, 'UTF-8');
            $rowId = htmlspecialchars((string) ($row['row_id'] ?? ''), ENT_QUOTES, 'UTF-8');
            $fileId = htmlspecialchars((string) ($row['file_id'] ?? ''), ENT_QUOTES, 'UTF-8');
            $fileValue = htmlspecialchars((string) ($row['file_value'] ?? ''), ENT_QUOTES, 'UTF-8');
            $crop = htmlspecialchars((string) ($row['crop_rect'] ?? ''), ENT_QUOTES, 'UTF-8');
            $ad = htmlspecialchars((string) ($row['ad_files'] ?? ''), ENT_QUOTES, 'UTF-8');
            $adDebug = htmlspecialchars((string) ($row['ad_debug'] ?? ''), ENT_QUOTES, 'UTF-8');
            $adSaved = htmlspecialchars((string) ($row['ad_saved'] ?? ''), ENT_QUOTES, 'UTF-8');
            $adEnabledSetting = htmlspecialchars((string) ($row['ad_enabled_setting'] ?? ''), ENT_QUOTES, 'UTF-8');
            $adPrePolicy = htmlspecialchars((string) ($row['ad_pre_policy'] ?? ''), ENT_QUOTES, 'UTF-8');
            $adPostPolicy = htmlspecialchars((string) ($row['ad_post_policy'] ?? ''), ENT_QUOTES, 'UTF-8');
            $adAllowedDirs = htmlspecialchars((string) ($row['ad_allowed_dirs'] ?? ''), ENT_QUOTES, 'UTF-8');

            $html .= '<tr>'
                . '<td style="padding:4px 6px; border-bottom:1px solid #f1f5f9;">' . $context . '</td>'
                . '<td style="padding:4px 6px; border-bottom:1px solid #f1f5f9;">' . $rowId . '</td>'
                . '<td style="padding:4px 6px; border-bottom:1px solid #f1f5f9;">id=' . $fileId . '<br>val=' . $fileValue . '</td>'
                . '<td style="padding:4px 6px; border-bottom:1px solid #f1f5f9;">' . $crop . '</td>'
                . '<td style="padding:4px 6px; border-bottom:1px solid #f1f5f9;">'
                . $ad
                . ($adDebug !== '' ? '<div style="margin-top:2px; font-size:11px; color:#64748b;">' . $adDebug . '</div>' : '')
                . ($adSaved !== '' || $adEnabledSetting !== ''
                    ? '<div style="margin-top:2px; font-size:11px; color:#64748b;">saved=' . $adSaved . ', setting=' . $adEnabledSetting . '</div>'
                    : '')
                . (($adPrePolicy !== '' || $adPostPolicy !== '' || $adAllowedDirs !== '')
                    ? '<div style="margin-top:2px; font-size:11px; color:#64748b;">pre=' . $adPrePolicy . ', post=' . $adPostPolicy . ', allowed=' . $adAllowedDirs . '</div>'
                    : '')
                . '</td>'
                . '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    private function format_debug_context_line(array $debugContext): string
    {
        if (empty($debugContext)) {
            return '';
        }

        $fieldId = isset($debugContext['field_id']) ? (string) $debugContext['field_id'] : '';
        $contentType = isset($debugContext['content_type']) ? (string) $debugContext['content_type'] : '';
        $rowId = isset($debugContext['row_id']) ? (string) $debugContext['row_id'] : '';
        $containerId = isset($debugContext['container_id']) ? (string) $debugContext['container_id'] : '';
        $fluidId = isset($debugContext['fluid_field_data_id']) ? (string) $debugContext['fluid_field_data_id'] : '';
        $blockId = isset($debugContext['block_id']) ? (string) $debugContext['block_id'] : '';
        $adCount = isset($debugContext['ad_count']) ? (string) $debugContext['ad_count'] : '';
        $adKeys = isset($debugContext['ad_keys']) ? (string) $debugContext['ad_keys'] : '';
        $rawAdCount = isset($debugContext['raw_ad_count']) ? (string) $debugContext['raw_ad_count'] : '';
        $rawAdKeys = isset($debugContext['raw_ad_keys']) ? (string) $debugContext['raw_ad_keys'] : '';
        $usageRowId = isset($debugContext['usage_row_id']) ? (string) $debugContext['usage_row_id'] : '';
        $dbAdCount = isset($debugContext['db_ad_count']) ? (string) $debugContext['db_ad_count'] : '';
        $dbAdKeys = isset($debugContext['db_ad_keys']) ? (string) $debugContext['db_ad_keys'] : '';
        $dbPayload = isset($debugContext['db_payload_excerpt']) ? (string) $debugContext['db_payload_excerpt'] : '';
        $adEnabled = isset($debugContext['ad_enabled_setting']) ? (string) $debugContext['ad_enabled_setting'] : '';
        $adRowsCount = isset($debugContext['ad_rows_count']) ? (string) $debugContext['ad_rows_count'] : '';
        $gridColKeys = isset($debugContext['grid_col_keys']) ? (string) $debugContext['grid_col_keys'] : '';
        $gridColEnableAd = isset($debugContext['grid_col_enable_ad']) ? (string) $debugContext['grid_col_enable_ad'] : '';
        $gridColExcerpt = isset($debugContext['grid_col_excerpt']) ? (string) $debugContext['grid_col_excerpt'] : '';
        $settingsKeys = isset($debugContext['settings_keys']) ? (string) $debugContext['settings_keys'] : '';
        $settingsEnableAd = isset($debugContext['settings_enable_ad']) ? (string) $debugContext['settings_enable_ad'] : '';
        $gridColId = isset($debugContext['grid_col_id']) ? (string) $debugContext['grid_col_id'] : '';

        $line = 'Display context: '
            . 'field_id=' . $fieldId
            . ', content_type=' . $contentType
            . ', row_id=' . $rowId
            . ', container_id=' . $containerId
            . ', fluid_field_data_id=' . $fluidId
            . ', block_id=' . $blockId
            . ', ad_count=' . $adCount
            . ', ad_keys=' . $adKeys
            . ', raw_ad_count=' . $rawAdCount
            . ', raw_ad_keys=' . $rawAdKeys
            . ', usage_row_id=' . $usageRowId
            . ', db_ad_count=' . $dbAdCount
            . ', db_ad_keys=' . $dbAdKeys
            . ', db_payload=' . $dbPayload
            . ', ad_enabled_setting=' . $adEnabled
            . ', ad_rows_count=' . $adRowsCount
            . ', grid_col_id=' . $gridColId
            . ', grid_col_keys=' . $gridColKeys
            . ', grid_col_enable_ad=' . $gridColEnableAd
            . ', grid_col_excerpt=' . $gridColExcerpt
            . ', settings_keys=' . $settingsKeys
            . ', settings_enable_ad=' . $settingsEnableAd;

        return '<div style="font-size:12px; color:#475569; margin:0 0 8px 0;">'
            . htmlspecialchars($line, ENT_QUOTES, 'UTF-8')
            . '</div>';
    }

    private function migrate_grid_usage_row_if_needed(
        int $siteId,
        int $entryId,
        int $fieldId,
        int $fileId,
        int $rowId,
        ?int $containerId
    ): void
    {
        try {
            $existing = ee()->db
                ->select('id')
                ->from('jcogs_img_pro_field_usages')
                ->where('site_id', $siteId)
                ->where('entry_id', $entryId)
                ->where('field_id', $fieldId)
                ->where('content_type', 'grid')
                ->where('row_id', $rowId)
                ->limit(1)
                ->get()
                ->row_array();

            if ($existing) {
                return;
            }

            $candidate = ee()->db
                ->select('id')
                ->from('jcogs_img_pro_field_usages')
                ->where('site_id', $siteId)
                ->where('entry_id', $entryId)
                ->where('field_id', $fieldId)
                ->where('content_type', 'grid')
                ->where('file_id', $fileId)
                ->where('row_id IS NULL', null, false)
                ->where('fluid_field_data_id IS NULL', null, false)
                ->where('block_id IS NULL', null, false)
                ->order_by('id', 'desc')
                ->limit(1)
                ->get()
                ->row_array();

            if (! $candidate || ! isset($candidate['id'])) {
                return;
            }

            $row = [
                'row_id' => $rowId,
                'container_id' => $containerId,
            ];

            ee()->db
                ->where('id', (int) $candidate['id'])
                ->update('jcogs_img_pro_field_usages', $row);
        } catch (\Throwable $e) {
            // Fail safe.
        }
    }

    /**
     * Grid fieldtype save callback.
     */
    public function grid_save($data)
    {
        return $this->save($data);
    }

    /**
     * Grid fieldtype post-save callback.
     */
    public function grid_post_save($data)
    {
        $site_id = (int) (ee()->config->item('site_id') ?: 1);
        $entry_id = (int) ($this->content_id() ?: 0);
        if ($entry_id <= 0) {
            $entry_id = (int) $this->row('entry_id');
        }
        if ($entry_id <= 0) {
            $entry_id = (int) ee()->input->post('entry_id');
        }

        $field_id = (int) ($this->field_id ?: 0);
        $row_id = $this->resolveContextRowId();
        $container_id = $this->resolveContextContainerId($entry_id, 'grid');

        if ($entry_id <= 0 || $field_id <= 0 || $row_id === null) {
            return;
        }

        try {
            ee('jcogs_img_pro_field:UsagePersistenceService')->persistGridRowFromPost(
                $entry_id,
                $site_id,
                $field_id,
                (int) $row_id,
                $container_id
            );
        } catch (\Throwable $e) {
            // Fail safe: never block entry saves.
        }
    }

    /**
     * Fieldtype post-save callback (compatibility).
     *
     * When invoked by EE, delegates persistence of editor overrides to the
     * UsagePersistenceService.
     */
    public function post_save($data)
    {
        // In this add-on, per-entry adjustments are persisted via the entry-save extension hook.
        // Keep post_save() as a compatibility wrapper in case EE calls it in some contexts.
        $site_id  = (int) (ee()->config->item('site_id') ?: 1);
        $entry_id = (int) ($this->content_id() ?: 0);
        if ($entry_id <= 0) {
            $entry_id = (int) ee()->input->post('entry_id');
        }
        if ($entry_id <= 0) {
            return;
        }

        try {
            ee('jcogs_img_pro_field:UsagePersistenceService')->persistFromPost($entry_id, $site_id);
            $_POST['jcogs_img_pro_field_persisted_in_post_save'] = '1';
        }
        catch (\Throwable $e) {
            // Fail safe: never block entry saves.
        }
    }

    /**
     * Resolve various EE file picker value shapes into a numeric file_id.
     */
    private function resolve_file_id($data): int
    {
        if (empty($data)) {
            return 0;
        }

        if (is_array($data)) {
            if (isset($data['file_input_name']) && is_string($data['file_input_name'])) {
                $input_name = trim((string) $data['file_input_name']);
                if ($input_name !== '' && array_key_exists($input_name, $data)) {
                    $resolved = $this->resolve_file_id($data[$input_name]);
                    if ($resolved > 0) {
                        return $resolved;
                    }
                }
            }

            if (isset($this->field_name) && is_string($this->field_name)) {
                $field_name = trim((string) $this->field_name);
                if ($field_name !== '' && array_key_exists($field_name, $data)) {
                    $resolved = $this->resolve_file_id($data[$field_name]);
                    if ($resolved > 0) {
                        return $resolved;
                    }
                }
            }

            if (array_key_exists('file_value', $data)) {
                $resolved = $this->resolve_file_id($data['file_value']);
                if ($resolved > 0) {
                    return $resolved;
                }
            }

            if (isset($data['file_id']) && is_numeric($data['file_id'])) {
                return (int) $data['file_id'];
            }

            // Some EE fields post nested values; grab the first scalar-ish value.
            foreach ($data as $value) {
                if (is_numeric($value)) {
                    return (int) $value;
                }
                if (is_string($value) && $value !== '') {
                    $resolved = $this->resolve_file_id($value);
                    if ($resolved > 0) {
                        return $resolved;
                    }
                }
                if (is_array($value) && ! empty($value)) {
                    $resolved = $this->resolve_file_id($value);
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
                    $resolved = $this->resolve_file_id($decoded);
                    if ($resolved > 0) {
                        return $resolved;
                    }
                }
            }
        }

        if (is_numeric($data)) {
            return (int) $data;
        }

        // Handle standard EE file field formats like {filedir_#}filename.ext or {file:ID:url}
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
     * Resolve an EE ACT action_id for a class/method pair.
     */
    private function resolve_action_id(string $class, string $method): int
    {
        // In CP, `ee()->cp->fetch_action_id()` returns the numeric ID.
        // `ee()->functions->fetch_action_id()` returns a template placeholder token.
        if (isset(ee()->cp) && method_exists(ee()->cp, 'fetch_action_id')) {
            $this->reset_db_builder();
            $id = ee()->cp->fetch_action_id($class, $method);
            return $id ? (int) $id : 0;
        }

        // Fallback: direct DB lookup.
        $this->reset_db_builder();
        return ServiceCache::action_repo()->findActionId($class, $method);
    }

    /**
     * Reset the EE active record state to avoid query builder leakage.
     */
    private function reset_db_builder(): void
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
     * Normalise a user-provided aspect ratio value.
     */
    private function normalize_aspect_ratio_setting(string $value): string
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::aspect_ratio()->normalizeSetting($value);
    }

    /**
     * Parse a delimited aspect ratio choice list from settings.
     */
    private function parse_aspect_ratio_choices(string $raw): array
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::aspect_ratio()->parseChoices($raw);
    }

    /**
     * Get allowed aspect ratio choices from field settings.
     */
    private function get_aspect_ratio_choices_from_field_settings(): array
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::aspect_ratio()->getChoicesFromSettings(
            is_array($this->settings) ? $this->settings : []
        );
    }

    /**
     * Normalise the posted aspect ratio mini-grid rows.
     */
    private function normalise_aspect_ratio_pairs_from_posted($pairs): array
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::aspect_ratio()->normalisePairsFromPosted($pairs);
    }

    /**
     * Render the aspect ratio mini-grid used in field settings.
     */
    private function getAspectRatioMiniGrid(array $data)
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::aspect_ratio()->buildMiniGrid($data);
    }

    /**
     * Normalise posted srcset widths.
     */
    private function normalise_srcset_widths_from_posted($widths): array
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::responsive_defaults()->normaliseSrcsetWidthsFromPosted($widths);
    }

    /**
     * Render the srcset widths mini-grid used in field settings.
     */
    private function getSrcsetWidthsMiniGrid(array $data)
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::responsive_defaults()->buildSrcsetWidthsMiniGrid($data);
    }

    /**
     * Convert a media query to a human-friendly display string.
     */
    private function art_direction_media_to_display_value(string $media): string
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::art_direction()->mediaToDisplayValue($media);
    }

    /**
     * Normalise posted art-direction breakpoint rows.
     */
    private function normalise_art_direction_breakpoints_from_posted($rows): array
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::art_direction()->normaliseBreakpointsFromPosted($rows);
    }

    /**
     * Render the art-direction breakpoints mini-grid used in field settings.
     */
    private function getArtDirectionBreakpointsMiniGrid(array $data, int $site_id)
    {
        $preset_options = $this->get_preset_options($site_id, '');
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::art_direction()->buildBreakpointsMiniGrid($data, $preset_options);
    }

    /**
     * Get art-direction breakpoints configured for this field.
     */
    private function get_art_direction_breakpoints_from_field_settings(): array
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::art_direction()->getBreakpointsFromFieldSettings($this->settings);
    }

    /**
     * Determine the legacy default art-direction preset id (if configured).
     */
    private function get_legacy_art_direction_default_preset_id_from_settings(): int
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::art_direction()->getLegacyDefaultPresetId($this->settings);
    }

    /**
     * Describe an art-direction media query for editor display.
     */
    private function describe_art_direction_media_for_editor(string $media): array
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::art_direction()->describeMediaForEditor($media);
    }

    /**
     * Apply a default art-direction preset to the payload (if configured).
     */
    private function apply_default_art_direction_preset_to_payload(int $file_id, array $usage_payload, array $tag_params): array
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::art_direction()->applyDefaultPresetToPayload($this->settings, $usage_payload, $tag_params);
    }

    /**
     * Build the per-row payload for an art-direction source.
     */
    private function build_payload_for_art_direction_row(int $file_id, array $main_usage_payload, int $preset_id, array $tag_params): array
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::art_direction()->buildRowPayload($this->settings, $main_usage_payload, $preset_id, $tag_params);
    }

    /**
     * Render a <picture> element when art-direction alternates exist.
     */
    private function render_art_direction_picture(int $main_file_id, array $usage_payload, array $tag_params): string
    {
        $rows = $this->get_art_direction_breakpoints_from_field_settings();
        if (empty($rows)) {
            return '';
        }

        $files = [];
        if (
            isset($usage_payload['art_direction']) && is_array($usage_payload['art_direction'])
            && isset($usage_payload['art_direction']['files']) && is_array($usage_payload['art_direction']['files'])
        ) {
            $files = $usage_payload['art_direction']['files'];
        }

        $sources_html = '';
        try {
            $renderer = ee('jcogs_img_pro_field:ImageProRenderer');

            // For art direction <source> tags, let each breakpoint preset control sizing/crop.
            // Template params like width/height are intended for the main fallback image only.
            $source_tag_params = $tag_params;
            foreach ([
                'preset',
                'width',
                'height',
                'aspect_ratio',
                'fit',
                'crop',
                'crop_mode',
                'crop_offset_x',
                'crop_offset_y',
                'crop_smart_scaling',
                'srcset',
                'sizes',
                'allow_scale_larger',
                'lazy',
                'debug_lazy',
            ] as $k) {
                unset($source_tag_params[$k]);
            }

            foreach ($rows as $row) {
                $idx   = (int) ($row['index'] ?? 0);
                $media = (string) ($row['media'] ?? '');
                if ($idx <= 0 || $media === '') {
                    continue;
                }

                // Prefer media-keyed storage; fall back to legacy numeric keys.
                $alt_file_id = 0;
                if (isset($files[$media])) {
                    $alt_file_id = (int) $files[$media];
                }
                elseif (isset($files[(string) $idx])) {
                    $alt_file_id = (int) $files[(string) $idx];
                }
                if ($alt_file_id <= 0) {
                    continue;
                }

                $row_payload = $this->build_payload_for_art_direction_row(
                    $alt_file_id,
                    $usage_payload,
                    (int) ($row['preset_id'] ?? 0),
                    $tag_params,
                );

                $src = $renderer->renderUrl(
                    $alt_file_id,
                    $row_payload,
                    $source_tag_params,
                    $this->build_field_default_renderer_params(),
                );

                if ($src === '') {
                    continue;
                }

                $sources_html .= '<source media="' . htmlspecialchars($media, ENT_QUOTES, 'UTF-8') . '" srcset="'
                    . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '">';
            }
        }
        catch (Throwable $e) {
            return '';
        }

        if ($sources_html === '') {
            return '';
        }

        try {
            $renderer = ee('jcogs_img_pro_field:ImageProRenderer');
            $img      = $renderer->renderImgTag(
                $main_file_id,
                $this->apply_default_art_direction_preset_to_payload($main_file_id, $usage_payload, $tag_params),
                $tag_params,
                $this->build_field_default_renderer_params(),
            );
        }
        catch (Throwable $e) {
            return '';
        }

        if (! is_string($img) || trim($img) === '') {
            return '';
        }

        return '<picture>' . $sources_html . $img . '</picture>';
    }

    /**
     * Render <option> tags for a select input.
     */
    private function render_select_options(array $choices, string $selected_value): string
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::preset_options()->renderSelectOptions($choices, $selected_value);
    }

    /**
     * Build preset options for the publish UI.
     */
    private function get_editor_preset_options(int $site_id, string $selected_preset_id): array
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::preset_options()->getEditorPresetOptions($this->settings, $site_id, $selected_preset_id);
    }

    /**
     * Build preset options for template usage.
     */
    private function get_preset_options(int $site_id, string $selected_preset_id): array
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::preset_options()->getPresetOptions($site_id, $selected_preset_id);
    }

    /**
     * Fetch Image Pro presets for the given site.
     */
    private function fetch_img_pro_presets(int $site_id): array
    {
        return \JCOGSDesign\JcogsImgProField\Service\ServiceCache::preset_options()->fetchImgProPresets($site_id);
    }

    /**
     * Fetch presets via the Image Pro service layer (preferred).
     */
    private function fetch_img_pro_presets_via_service(): array
    {
        return [];
    }

    /**
     * Fetch presets via direct DB query (fallback).
     */
    private function fetch_img_pro_presets_via_db(int $site_id): array
    {
        return [];
    }
}
