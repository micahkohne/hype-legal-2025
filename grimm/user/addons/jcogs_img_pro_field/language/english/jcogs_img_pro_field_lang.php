<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

$lang = [
    // Fieldtype settings (CP).
    'jcogs_img_pro_field_all_upload_destinations' => 'All upload destinations',
    'jcogs_img_pro_field_none_option' => '— None —',
    'jcogs_img_pro_field_missing_preset' => '(Missing preset #%s)',

    // Preset allowlist table (CP).
    'jcogs_img_pro_field_preset_allowlist_col_use' => 'Use',
    'jcogs_img_pro_field_preset_allowlist_col_preset' => 'Preset',
    'jcogs_img_pro_field_preset_allowlist_no_presets' => 'No <b>Image Pro presets</b> found.',

    // MiniGrid UI strings.
    'jcogs_img_pro_field_minigrid_add_new' => 'Add New',

    'jcogs_img_pro_field_minigrid_aspect_col_value' => 'Value',
    'jcogs_img_pro_field_minigrid_aspect_col_label' => 'Label',
    'jcogs_img_pro_field_minigrid_aspect_no_results' => 'No aspect ratio options exist.',

    'jcogs_img_pro_field_minigrid_srcset_col_width' => 'Width (px)',
    'jcogs_img_pro_field_minigrid_srcset_no_results' => 'No srcset widths exist.',

    'jcogs_img_pro_field_minigrid_art_direction_col_breakpoint' => 'Breakpoint / Media',
    'jcogs_img_pro_field_minigrid_art_direction_col_preset' => 'Preset',
    'jcogs_img_pro_field_minigrid_art_direction_preset_inherit' => 'Inherit main preset',
    'jcogs_img_pro_field_minigrid_art_direction_preset_none' => 'No preset (use original size)',
    'jcogs_img_pro_field_minigrid_art_direction_no_results' => 'No breakpoints exist.',

    // Publish/content-editor panel (entry editor).
    'jcogs_img_pro_field_editor_adjust_summary' => 'Adjust source image',
    'jcogs_img_pro_field_editor_modal_title' => 'Image adjustments',
    'jcogs_img_pro_field_editor_btn_open_modal' => 'Edit adjustments',
    'jcogs_img_pro_field_editor_btn_close_modal' => 'Done',
        'jcogs_img_pro_field_editor_intro_preset' => 'Choose an Image Pro preset to apply when this image is rendered in templates.',
    'jcogs_img_pro_field_editor_none' => 'none',

    'jcogs_img_pro_field_editor_chip_preset' => 'Preset: %s',
    'jcogs_img_pro_field_editor_chip_preset_per_breakpoint' => 'Preset: per breakpoint',
    'jcogs_img_pro_field_editor_chip_crop_set' => 'Crop: set',
    'jcogs_img_pro_field_editor_chip_crop_none' => 'Crop: none',
    'jcogs_img_pro_field_editor_chip_aspect' => 'Aspect: %s',
    'jcogs_img_pro_field_editor_chip_aspect_free' => 'Aspect: free',
    'jcogs_img_pro_field_editor_chip_focal_set' => 'Focal: set',
    'jcogs_img_pro_field_editor_chip_focal_none' => 'Focal: none',
    'jcogs_img_pro_field_editor_chip_art_direction' => 'Art direction: %s',

    'jcogs_img_pro_field_editor_label_preset' => 'Preset',
    'jcogs_img_pro_field_editor_btn_preview_preset' => 'Preview preset',

    'jcogs_img_pro_field_editor_heading_art_direction' => 'Art direction',
    'jcogs_img_pro_field_editor_help_art_direction' => 'Main image is always the default fallback. Each row below defines when an alternate image is used (via its media query). If no alternate is chosen, the main image will be used instead.',

    'jcogs_img_pro_field_editor_ad_alt_title_generic' => 'Alternate image',
    'jcogs_img_pro_field_editor_ad_alt_title_max_width' => 'Alternate image for viewports %spx or less',
    'jcogs_img_pro_field_editor_ad_alt_title_min_width' => 'Alternate image for viewports %spx or more',
    'jcogs_img_pro_field_editor_ad_alt_title_media' => 'Alternate image for: %s',
    'jcogs_img_pro_field_editor_ad_alt_media_caption' => 'Media query: %s',
    'jcogs_img_pro_field_editor_ad_alt_help_fallback' => 'If left blank, the main image will be used.',
    'jcogs_img_pro_field_editor_ad_alt_preset_caption' => 'Preset: #%d',

    'jcogs_img_pro_field_editor_heading_focal' => 'Focal point',
        'jcogs_img_pro_field_editor_intro_focal' => 'Set a focal point so crops and smart scaling prioritise the most important area of the image.',
    'jcogs_img_pro_field_editor_btn_load_preview' => 'Load preview',
    'jcogs_img_pro_field_editor_btn_pick_focal' => 'Pick focal',
    'jcogs_img_pro_field_editor_btn_clear_focal' => 'Clear focal',
    'jcogs_img_pro_field_editor_btn_detect_faces' => 'Detect faces',
    'jcogs_img_pro_field_editor_help_pick_focal' => 'Click “Pick focal”, then click the original image. Press ESC to cancel.',
    'jcogs_img_pro_field_editor_help_focal_after_create' => 'Focal picking is available after the entry has been created.',

    'jcogs_img_pro_field_editor_heading_face_detection' => 'Face detection',
    'jcogs_img_pro_field_editor_intro_face_detection' => 'Detect faces to suggest a focal point and/or generate a crop based on people in the image.',
    'jcogs_img_pro_field_editor_help_face_detection' => 'Choose settings (optional), then click “Detect faces”.',
    'jcogs_img_pro_field_editor_label_quality' => 'Quality',
    'jcogs_img_pro_field_editor_label_sensitivity' => 'Sensitivity',
    'jcogs_img_pro_field_editor_label_margin_px' => 'Margin (px)',
    'jcogs_img_pro_field_editor_label_ignore_cache' => 'Ignore cache',
    'jcogs_img_pro_field_editor_btn_restore_defaults' => 'Restore defaults',
    'jcogs_img_pro_field_editor_summary_face_detection_settings' => 'Advanced face detection settings',
    'jcogs_img_pro_field_editor_btn_apply_suggested_focal' => 'Apply suggested focal',
    'jcogs_img_pro_field_editor_btn_apply_crop_from_faces' => 'Apply crop from faces',
    'jcogs_img_pro_field_editor_btn_clear_overlay' => 'Clear face detection',

    'jcogs_img_pro_field_editor_summary_advanced_numeric' => 'Advanced: set focal point manually',
    'jcogs_img_pro_field_editor_label_focal_x' => 'Focal X (0–100)',
    'jcogs_img_pro_field_editor_label_focal_y' => 'Focal Y (0–100)',

    'jcogs_img_pro_field_editor_heading_crop' => 'Crop',
        'jcogs_img_pro_field_editor_intro_crop' => 'Pick a crop rectangle to control framing for this image.',
    'jcogs_img_pro_field_editor_btn_crop' => 'Crop',
    'jcogs_img_pro_field_editor_btn_edit_crop' => 'Edit crop',
    'jcogs_img_pro_field_editor_btn_reload_preview' => 'Reload preview',
    'jcogs_img_pro_field_editor_btn_clear_crop' => 'Clear crop',
    'jcogs_img_pro_field_editor_help_crop_click_crop' => 'Tip: click “Crop” to start picking a rectangle.',
    'jcogs_img_pro_field_editor_help_crop_pick' => 'Pick/resize a rectangle on the original image.',
    'jcogs_img_pro_field_editor_help_crop_aspect_enforced' => 'This crop is constrained to an aspect ratio of %s.',
    'jcogs_img_pro_field_editor_label_aspect_ratio' => 'Aspect ratio',
    'jcogs_img_pro_field_editor_option_inherit' => '(inherit)',
    'jcogs_img_pro_field_editor_option_custom_aspect' => '(Custom: %s)',
    'jcogs_img_pro_field_editor_help_aspect_locks' => 'Locks the crop box to this ratio.',
    'jcogs_img_pro_field_editor_help_crop_after_create' => 'Crop preview is available after the entry has been created.',

    'jcogs_img_pro_field_editor_label_preview' => 'Preview',
    'jcogs_img_pro_field_editor_help_preview_lazy' => 'Preview will load when you open “Adjust image output”.',
    'jcogs_img_pro_field_editor_help_preview_after_create' => 'Preview is available after the entry has been created (and actions are registered).',

    'jcogs_img_pro_field_editor_label_edit_manually' => 'Edit manually',
    'jcogs_img_pro_field_editor_help_manual' => 'Advanced: crop string/offsets/sizing.',
    'jcogs_img_pro_field_editor_label_crop_override_raw' => 'Crop override (raw)',
    'jcogs_img_pro_field_editor_placeholder_crop_override' => 'Optional: Image Pro crop string',
    'jcogs_img_pro_field_editor_label_crop_mode' => 'Crop mode',
    'jcogs_img_pro_field_editor_label_focus_h' => 'Focus H',
    'jcogs_img_pro_field_editor_label_focus_v' => 'Focus V',
    'jcogs_img_pro_field_editor_label_offset_x' => 'Offset X',
    'jcogs_img_pro_field_editor_label_offset_y' => 'Offset Y',
    'jcogs_img_pro_field_editor_label_crop_width' => 'Crop width',
    'jcogs_img_pro_field_editor_label_crop_height' => 'Crop height',
    'jcogs_img_pro_field_editor_label_smart_scaling' => 'Smart scaling',

    'jcogs_img_pro_field_editor_help_overrides_after_create' => 'Overrides can be saved after the entry has been created.',
    'jcogs_img_pro_field_editor_alert_actions_missing_title' => 'JCOGS Image Pro Field needs an update',
    'jcogs_img_pro_field_editor_alert_actions_missing_detail' => 'This site is missing required action registrations for AJAX crop/preview/face detection.',
    'jcogs_img_pro_field_editor_alert_actions_missing_missing' => 'Missing: %s.',
    'jcogs_img_pro_field_editor_alert_actions_missing_cta_link' => 'Go to <a href="%s">Add-ons</a> and click Update for “JCOGS Image Pro Field”.',
    'jcogs_img_pro_field_editor_alert_actions_missing_cta_plain' => 'Go to Add-ons and click Update for “JCOGS Image Pro Field”.',

    // Publish panel JS UI text.
    'jcogs_img_pro_field_js_open_derived' => 'Open derived',
    'jcogs_img_pro_field_js_open_original' => 'Open original',
    'jcogs_img_pro_field_js_original_for_crop_picking' => 'Original (for crop picking)',
    'jcogs_img_pro_field_js_derived_preview_unavailable' => 'Derived preview unavailable:',
    'jcogs_img_pro_field_js_debug_preview' => 'Debug (preview)',

    'jcogs_img_pro_field_js_detecting_short' => 'Detecting…',
    'jcogs_img_pro_field_js_face_detected_one' => '1 face detected',
    'jcogs_img_pro_field_js_face_detected_many' => '%s faces detected',
    'jcogs_img_pro_field_js_face_detected_cached_suffix' => ' (cached)',
    'jcogs_img_pro_field_js_face_detect_timed_out' => 'Face detection timed out. Try lower sensitivity, “fast”, or a smaller image.',
    'jcogs_img_pro_field_js_face_detect_oom' => 'Face detection ran out of memory. Try “fast” or a smaller image.',

    // Publish panel JS status messages.
    'jcogs_img_pro_field_js_status_loading' => 'Loading…',
    'jcogs_img_pro_field_js_status_load_failed' => 'Load failed',
    'jcogs_img_pro_field_js_status_loaded' => 'Loaded',
    'jcogs_img_pro_field_js_status_saving' => 'Saving…',
    'jcogs_img_pro_field_js_status_save_failed' => 'Save failed',
    'jcogs_img_pro_field_js_status_saved' => 'Saved',
    'jcogs_img_pro_field_js_status_preview_rendering' => 'Rendering preview…',
    'jcogs_img_pro_field_js_status_preview_failed' => 'Preview failed',
    'jcogs_img_pro_field_js_status_preview_ready' => 'Preview ready',
    'jcogs_img_pro_field_js_status_preview_action_missing' => 'Preview action unavailable',
    'jcogs_img_pro_field_js_status_preview_original_required' => 'Preview original image required',
    'jcogs_img_pro_field_js_status_loading_image' => 'Loading image…',
    'jcogs_img_pro_field_js_status_crop_offsets_cleared' => 'Crop offsets cleared',
    'jcogs_img_pro_field_js_status_crop_drag_resize' => 'Drag/resize the box to adjust crop',
    'jcogs_img_pro_field_js_status_image_changed_overrides_cleared' => 'Image changed (overrides cleared)',
    'jcogs_img_pro_field_js_status_pick_focal' => 'Pick focal: click the original image (ESC to cancel)',
    'jcogs_img_pro_field_js_status_focal_pick_cancelled' => 'Focal pick cancelled',
    'jcogs_img_pro_field_js_status_focal_cleared' => 'Focal cleared',
    'jcogs_img_pro_field_js_status_focal_set' => 'Focal set',
    'jcogs_img_pro_field_js_status_choose_image_first' => 'Choose an image first',
    'jcogs_img_pro_field_js_status_detecting_faces' => 'Detecting faces…',
    'jcogs_img_pro_field_js_status_face_detect_action_missing' => 'Face detection action unavailable',
    'jcogs_img_pro_field_js_status_face_detection_failed' => 'Face detection failed',
    'jcogs_img_pro_field_js_status_field_not_found' => 'Field configuration could not be resolved for this context',
    'jcogs_img_pro_field_js_status_faces_detected' => 'Faces detected',
    'jcogs_img_pro_field_js_status_no_faces_detected' => 'No faces detected',
    'jcogs_img_pro_field_js_status_no_suggested_focal' => 'No suggested focal available',
    'jcogs_img_pro_field_js_status_suggested_focal_applied' => 'Suggested focal applied',
    'jcogs_img_pro_field_js_status_no_face_collection_box' => 'No face collection box available',
    'jcogs_img_pro_field_js_status_invalid_face_detection_result' => 'Invalid face detection result',
    'jcogs_img_pro_field_js_status_crop_applied_from_faces' => 'Crop applied from faces',
    'jcogs_img_pro_field_js_status_face_overlay_cleared' => 'Face overlay cleared',
    'jcogs_img_pro_field_js_status_face_settings_restored' => 'Face detection settings restored',
    'jcogs_img_pro_field_js_status_ad_alt_selected_main_preserved' => 'Alt image selected (main preserved)',

    'jcogs_img_pro_field_setting_allowed_directories_title' => 'Allowed upload directory',
    'jcogs_img_pro_field_setting_allowed_directories_desc' => 'Restrict file selection to a single upload destination (matches the core File field picker). ExpressionEngine’s drag-and-drop picker supports “All” or ONE directory ID.',

    'jcogs_img_pro_field_setting_enable_preset_title' => 'Enable presets',
    'jcogs_img_pro_field_setting_enable_preset_desc' => 'When enabled, this field can apply an Image Pro preset. Templates can still override via tag parameters.',

    'jcogs_img_pro_field_setting_enable_preset_choice_title' => 'Allow editors to choose a preset',
    'jcogs_img_pro_field_setting_enable_preset_choice_desc' => 'When enabled, editors may choose an Image Pro preset in the publish UI (from your allowed list, if you restrict it). When disabled, editors cannot change the preset; the default preset (below) will be forced unless the template tag specifies preset=...',

    'jcogs_img_pro_field_setting_preset_allow_none_title' => 'Allow “None” (no preset)',
    'jcogs_img_pro_field_setting_preset_allow_none_desc' => 'When enabled, editors may select no preset. If disabled, the publish UI will always select a preset (either the default you set or the only available option).',

    'jcogs_img_pro_field_setting_default_preset_title' => 'Default preset',
    'jcogs_img_pro_field_setting_default_preset_desc' => 'Only shown when there is more than one option available to editors.',

    'jcogs_img_pro_field_setting_preset_restrict_title' => 'Restrict selectable presets',
    'jcogs_img_pro_field_setting_preset_restrict_desc' => 'When enabled, editors can only choose from the presets you tick below. If disabled, all Image Pro presets are available (subject to the “show selector only when there is a choice” rule).',

    'jcogs_img_pro_field_setting_preset_allowlist_title' => 'Allowed presets',
    'jcogs_img_pro_field_setting_preset_allowlist_desc' => 'Tick the presets you want available to editors. This section appears only when “Restrict selectable presets” is enabled.',

    'jcogs_img_pro_field_setting_enable_crop_title' => 'Enable crop tools',
    'jcogs_img_pro_field_setting_enable_crop_desc' => 'When enabled, editors can define crop intent (crop rectangle, aspect ratio, offsets, focal point, etc.). Stored intent is applied only when the template does not explicitly provide crop parameters.',

    'jcogs_img_pro_field_setting_require_crop_title' => 'Require a crop',
    'jcogs_img_pro_field_setting_require_crop_desc' => 'When enabled, editors must define a crop (crop rectangle or manual crop/offsets) before the entry can be saved.',

    'jcogs_img_pro_field_setting_require_aspect_ratio_title' => 'Require an aspect ratio',
    'jcogs_img_pro_field_setting_require_aspect_ratio_desc' => 'When enabled, freeform crops are not allowed. If a crop is defined, an aspect ratio must be in effect (either the default you configure or an explicit selection).',

    'jcogs_img_pro_field_setting_aspect_ratio_pairs_title' => 'Aspect ratio options (crop panel)',
    'jcogs_img_pro_field_setting_aspect_ratio_pairs_desc' => 'Value/Label pairs. Values are normalised to underscores (e.g. 16:9 → 16_9). The dropdown appears to editors when you provide 2+ options.',
    'jcogs_img_pro_field_setting_default_aspect_ratio_label' => 'Default aspect ratio',
    'jcogs_img_pro_field_setting_default_aspect_ratio_help' => 'Used as the initial selection in the crop panel, and as the default when no aspect ratio is chosen by the editor (or provided by template/preset parameters).',

    'jcogs_img_pro_field_setting_enable_focal_title' => 'Enable focal inputs',
    'jcogs_img_pro_field_setting_enable_focal_desc' => 'When enabled, editors can set a focal point for the image. This is stored as intent and may be used by Image Pro (or presets) to bias crops, depending on your template/preset parameters.',

    'jcogs_img_pro_field_setting_enable_face_detect_title' => 'Enable face detection tools',
    'jcogs_img_pro_field_setting_enable_face_detect_desc' => 'When enabled (and crop tools are enabled), editors can run “Detect faces” in the publish UI to assist with choosing a focal point and/or suggested crop. Results are cached per file for performance.',

    'jcogs_img_pro_field_setting_face_detect_controls_title' => 'Face detection settings visibility',
    'jcogs_img_pro_field_setting_face_detect_controls_desc' => 'Controls whether editors can adjust face detection settings in the publish UI. Hidden: use the defaults below only. Advanced: show a collapsed settings panel. Visible: always show the settings panel.',
    'jcogs_img_pro_field_setting_face_detect_controls_hidden' => 'Hidden (use defaults below)',
    'jcogs_img_pro_field_setting_face_detect_controls_advanced' => 'Advanced (collapsed panel)',
    'jcogs_img_pro_field_setting_face_detect_controls_visible' => 'Visible (always shown)',

    'jcogs_img_pro_field_setting_face_detect_default_quality_title' => 'Face detection default quality',
    'jcogs_img_pro_field_setting_face_detect_default_quality_desc' => 'Used when settings are hidden, and as the starting value for editors. “fast” is quickest; “accurate” is slowest.',

    'jcogs_img_pro_field_setting_face_detect_default_sensitivity_title' => 'Face detection default sensitivity',
    'jcogs_img_pro_field_setting_face_detect_default_sensitivity_desc' => 'Integer 1–9. Higher may be slower and find smaller faces.',

    'jcogs_img_pro_field_setting_face_detect_default_margin_title' => 'Face detection default margin (px)',
    'jcogs_img_pro_field_setting_face_detect_default_margin_desc' => 'Extra padding around the face collection box. Integer 0–500.',

    'jcogs_img_pro_field_setting_enable_responsive_defaults_title' => 'Enable responsive image defaults',
    'jcogs_img_pro_field_setting_enable_responsive_defaults_desc' => 'When enabled, the field can provide developer-defined defaults for responsive output (srcset widths and allow_scale_larger) when rendering {field:img}. Template parameters always win.',

    'jcogs_img_pro_field_setting_srcset_widths_title' => 'Responsive images: default srcset widths',
    'jcogs_img_pro_field_setting_srcset_widths_desc' => 'Optional. If provided, {field:img} will include an Image Pro srcset using these widths (pipe-delimited under the hood). Image Pro can generate a default sizes attribute, but setting sizes explicitly in the template is still best practice.',
    'jcogs_img_pro_field_setting_srcset_widths_example' => 'Example widths: 320, 480, 768, 1024, 1280.',

    'jcogs_img_pro_field_setting_default_allow_scale_larger_title' => 'Responsive images: default allow_scale_larger',
    'jcogs_img_pro_field_setting_default_allow_scale_larger_desc' => 'Only applies when srcset is used. When enabled, Image Pro may generate variants larger than the source image. Use deliberately (it can increase processing and disk usage).',

    'jcogs_img_pro_field_setting_enable_art_direction_title' => 'Enable art direction (picture element)',
    'jcogs_img_pro_field_setting_enable_art_direction_desc' => 'When enabled, {field:img} can output a <picture> element with breakpoint-specific <source> tags (art direction). The main field image is always the default/fallback image.',

    'jcogs_img_pro_field_setting_art_direction_breakpoints_title' => 'Art direction: media query rows (max 3)',
    'jcogs_img_pro_field_setting_art_direction_breakpoints_desc' => 'Define up to 3 rows. Each row defines an <b>alternate image</b>: a <b>media query</b> and an optional preset. If you enter a number (e.g. 768), it is saved as <code>(min-width: 768px)</code> so the interpretation is explicit. The main field image is always the fallback when no media query matches, or when no alternate is chosen.',
    'jcogs_img_pro_field_setting_art_direction_breakpoints_tip' => 'Tip: order <b>largest</b> min-width first so your <source> tags are evaluated in the expected order. Use explicit max-width/min-width queries when you want the main image to be used in the “gap” between alternates.',

    'jcogs_img_pro_field_setting_enable_debug_title' => 'Enable debug (superadmins)',
    'jcogs_img_pro_field_setting_enable_debug_desc' => 'When enabled, exposes additional debugging output to superadmins in the publish UI to help diagnose settings, preview, and processing behaviour.',

    // Validation messages.
    'jcogs_img_pro_field_validation_aspect_default_required' => 'Choose a Default aspect ratio when you define 2+ aspect ratios.',

    'jcogs_img_pro_field_validation_aspect_ratio_options_required' => 'Require an aspect ratio is enabled: add at least one aspect ratio option.',
    'jcogs_img_pro_field_validation_aspect_ratio_required' => 'An aspect ratio is required for this crop.',

    'jcogs_img_pro_field_validation_crop_required' => 'A crop is required for this field.',

    'jcogs_img_pro_field_validation_art_direction_invalid_chars' => 'One or more art direction breakpoint values contain invalid characters.',
    'jcogs_img_pro_field_validation_art_direction_too_many_rows' => 'Art direction supports a maximum of 3 breakpoint rows.',

    'jcogs_img_pro_field_validation_preset_restrict_requires_allowed_or_none' => 'When restricting presets, choose at least one allowed preset (or enable Allow “None”).',
    'jcogs_img_pro_field_validation_default_preset_must_be_allowed' => 'Default preset must be one of the allowed presets.',

    'jcogs_img_pro_field_validation_face_detect_quality_invalid' => 'Face detection default quality must be one of: fast, balanced, accurate.',
    'jcogs_img_pro_field_validation_face_detect_sensitivity_range' => 'Face detection default sensitivity must be an integer from 1 to 9.',
    'jcogs_img_pro_field_validation_face_detect_margin_range' => 'Face detection default margin must be an integer from 0 to 500.',

    'jcogs_img_pro_field_validation_srcset_widths_too_many' => 'Srcset widths: please provide 20 or fewer widths.',
    'jcogs_img_pro_field_validation_srcset_widths_invalid' => 'Srcset widths: each width must be a positive integer.',

    // License governance (companion constraints).
    'jcogs_img_pro_field_license_restriction_title' => 'Companion licence required',
    'jcogs_img_pro_field_license_restriction_desc' => 'JCOGS Image Pro Field is currently unlicensed. Existing field definitions are read-only and new field definitions cannot be saved until a valid licence is applied.',
    'jcogs_img_pro_field_validation_license_required' => 'JCOGS Image Pro Field is currently unlicensed. Field definition changes are read-only until a valid licence is applied.',
    'jcogs_img_pro_field_license_notice_trial_title' => 'Companion licence trial',
    'jcogs_img_pro_field_license_notice_trial_desc' => 'JCOGS Image Pro Field is currently in trial status. Apply a valid licence to avoid field-definition restrictions.',
    'jcogs_img_pro_field_license_notice_invalid_grace_title' => 'Companion licence requires attention',
    'jcogs_img_pro_field_license_notice_invalid_grace_desc' => 'JCOGS Image Pro Field licence is currently invalid. Field definition changes are still allowed during the grace period ({days} day(s) remaining).',
];
