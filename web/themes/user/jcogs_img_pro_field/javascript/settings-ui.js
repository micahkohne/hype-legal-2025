
/**
 * JCOGS Image Pro Field - JavaScript for Field Settings UI
 *=========================================================
 * 
 * Settings UI helpers for the Image Pro fieldtype.
 * 
 * Manages preset defaults/allowlists, aspect ratio defaults, nested toggle
 * enforcement, and modal re-initialization for ExpressionEngine settings forms.
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
(function($){
    function syncArtDirectionDefaultUI(context){ /* no-op */ }

    function isBloqsTemplateContext($el){
        if (!$el || !$el.length) return false;
        return $el.closest('.grid-col-settings-elements, #grid_col_settings_elements').length > 0;
    }

    function resolveSettingsScope($el){
        var $scope = $el.closest('[data-fieldtype="jcogs_img_pro_field"], .grid_col_settings_custom_field_jcogs_img_pro_field');
        if ($scope.length) return $scope;
        $scope = $el.closest('.modal, .modal-wrap, form');
        if ($scope.length) return $scope;
        return $(document);
    }

    function findSetting($root, name, elementSelector){
        var selector = elementSelector || '';
        var nameSelector = '[name="' + name + '"]' + ', [name$="[' + name + ']"]';
        return $root.find(selector + nameSelector);
    }

    function findToggleInput(root, name){
        return root.querySelector(
            'input[type="hidden"][name="' + name + '"][data-group-toggle],'
            + ' input[type="hidden"][name$="[' + name + ']"][data-group-toggle]'
        );
    }

    function normalizeAspectRatio(val){
        val = String(val || '').trim();
        if (!val) return '';
        // 16:9, 16/9, 16-9, 16_9 -> 16_9
        val = val.replace(/\s+/g, '');
        val = val.replace(/[:\/\-]/g, '_');
        return val;
    }

    function guardMiniGridEvents(context){
        var $ctx = $(context || document);
        $ctx.find('.jcogs-img-pro-field-aspect-settings, .jcogs-img-pro-field-srcset-settings, .jcogs-img-pro-field-art-direction-settings').each(function(){
            var $scope = $(this);

            // Ensure internal mini-grid controls never behave like submit buttons.
            $scope.find('button[rel="add_row"], button[rel="remove_row"]').attr('type', 'button');

            // Prevent remove-row event bleed into parent Grid field settings handlers.
            // Do not preventDefault here; EE miniGrid still needs to process the click.
            if (!$scope.data('jcogsMiniGridGuardBound')) {
                $scope.on('click.jcogsMiniGridGuard', '[rel="remove_row"]', function(e){
                    e.stopPropagation();
                });
                $scope.data('jcogsMiniGridGuardBound', true);
            }
        });
    }

    function ensureMiniGridControlsVisible($gridRoot){
        if (!$gridRoot || !$gridRoot.length) {
            return;
        }

        var rowCount = $gridRoot.find('.keyvalue-item-container .fields-keyvalue-item')
            .not('.grid-blank-row, .hidden')
            .length;

        if (rowCount > 0) {
            $gridRoot.find('.fields-keyvalue-header').show().css('display', '');
            $gridRoot.children('[rel="jcogs_add_row"], [data-jcogs-add-row="1"]').show().css('display', '');
            $gridRoot.find('.field-no-results').hide();
        } else {
            $gridRoot.children('[rel="jcogs_add_row"], [data-jcogs-add-row="1"]').show().css('display', '');
        }
    }

    function safelyAddMiniGridRow($gridRoot, instance){
        if (!instance || typeof instance._addRow !== 'function') {
            return;
        }

        var isArtDirectionGrid = $gridRoot.closest('.jcogs-img-pro-field-art-direction-settings').length > 0;
        if (!isArtDirectionGrid || !window.EE || !EE.cp || !EE.cp.formValidation || typeof EE.cp.formValidation.bindInputs !== 'function') {
            instance._addRow();
            ensureMiniGridControlsVisible($gridRoot);
            return;
        }

        var originalBindInputs = EE.cp.formValidation.bindInputs;
        EE.cp.formValidation.bindInputs = function(el){
            if ($(el).closest('.jcogs-img-pro-field-art-direction-settings').length > 0) {
                return;
            }
            return originalBindInputs.apply(this, arguments);
        };

        try {
            instance._addRow();
        } finally {
            EE.cp.formValidation.bindInputs = originalBindInputs;
        }

        ensureMiniGridControlsVisible($gridRoot);
    }

    function rewireMiniGridAddLinks(context){
        var root = context && context.nodeType ? context : document;
        $(root)
            .find('.jcogs-img-pro-field-aspect-settings .fields-keyvalue, .jcogs-img-pro-field-srcset-settings .fields-keyvalue, .jcogs-img-pro-field-art-direction-settings .fields-keyvalue')
            .each(function(){
                var gridRoot = this;
                var $gridRoot = $(gridRoot);
                if ($gridRoot.data('jcogsMiniGridAddDelegateBound') === true) {
                    return;
                }

                $gridRoot.find('[rel="add_row"]').each(function(){
                    this.setAttribute('rel', 'jcogs_add_row');
                    this.setAttribute('data-jcogs-add-row', '1');
                    if (this.tagName && this.tagName.toLowerCase() === 'a' && !this.getAttribute('href')) {
                        this.setAttribute('href', '#');
                    }
                });

                $gridRoot.on('click.jcogsMiniGridAdd', '[rel="jcogs_add_row"], [data-jcogs-add-row="1"]', function(evt){
                    evt.preventDefault();
                    evt.stopPropagation();
                    if (typeof evt.stopImmediatePropagation === 'function') {
                        evt.stopImmediatePropagation();
                    }

                    var instance = $gridRoot.data('GridInstance');
                    if (!instance && typeof $gridRoot.miniGrid === 'function') {
                        try {
                            $gridRoot.miniGrid({});
                        } catch (e) {
                            // no-op
                        }
                        instance = $gridRoot.data('GridInstance');
                    }

                    safelyAddMiniGridRow($gridRoot, instance);

                    return false;
                });

                $gridRoot.data('jcogsMiniGridAddDelegateBound', true);
                ensureMiniGridControlsVisible($gridRoot);
            });
    }

    function installMiniGridCaptureInterceptor(){
        if (window.__jcogsImgProFieldMiniGridCaptureInstalled) {
            return;
        }
        window.__jcogsImgProFieldMiniGridCaptureInstalled = true;

        document.addEventListener('click', function(evt){
            var target = evt.target;
            if (!target || typeof target.closest !== 'function') {
                return;
            }

            var addRowLink = target.closest('.jcogs-img-pro-field-aspect-settings .fields-keyvalue [rel="jcogs_add_row"], .jcogs-img-pro-field-aspect-settings .fields-keyvalue [data-jcogs-add-row="1"], .jcogs-img-pro-field-srcset-settings .fields-keyvalue [rel="jcogs_add_row"], .jcogs-img-pro-field-srcset-settings .fields-keyvalue [data-jcogs-add-row="1"], .jcogs-img-pro-field-art-direction-settings .fields-keyvalue [rel="jcogs_add_row"], .jcogs-img-pro-field-art-direction-settings .fields-keyvalue [data-jcogs-add-row="1"]');
            if (!addRowLink) {
                return;
            }

            evt.preventDefault();
            evt.stopPropagation();
            if (typeof evt.stopImmediatePropagation === 'function') {
                evt.stopImmediatePropagation();
            }

            var gridRoot = addRowLink.closest('.fields-keyvalue');
            if (!gridRoot) {
                return;
            }

            var $gridRoot = $(gridRoot);
            var instance = $gridRoot.data('GridInstance');
            if (!instance && typeof $gridRoot.miniGrid === 'function') {
                try {
                    $gridRoot.miniGrid({});
                } catch (e) {
                    // no-op
                }
                instance = $gridRoot.data('GridInstance');
            }

            safelyAddMiniGridRow($gridRoot, instance);
        }, true);
    }

    function getGridColumnNamespace($scope){
        var ns = '';
        $scope.find(':input[name^="grid[cols]["]').each(function(){
            var name = this.name || '';
            var match = name.match(/^grid\[cols\]\[([^\]]+)\]/);
            if (match && match[1]) {
                ns = match[1];
                return false;
            }
            return true;
        });
        return ns;
    }

    function isUnsavedGridNamespace(namespace){
        return /^new_[0-9]+$/.test(namespace) || /^new_row_[0-9]+$/.test(namespace);
    }

    function installGridColumnSubmitHardening(){
        if (window.__jcogsImgProFieldGridSubmitHardeningInstalled) {
            return;
        }
        window.__jcogsImgProFieldGridSubmitHardeningInstalled = true;

        $(document).on('submit', 'form', function(){
            var $form = $(this);

            $form.find(':input[name^="grid[cols]["]').each(function(){
                var name = this.name || '';
                var match = name.match(/^grid\[cols\]\[([^\]]+)\]/);
                if (!match || !isUnsavedGridNamespace(match[1])) {
                    return;
                }

                var namespace = match[1];
                $form.find(':input[name^="grid[cols][' + namespace + '][col_settings]"]').prop('disabled', true);
            });

            $form.find('.grid_col_settings_custom_field_jcogs_img_pro_field').each(function(){
                var $settingsRoot = $(this);
                var namespace = getGridColumnNamespace($settingsRoot);
                if (!isUnsavedGridNamespace(namespace)) {
                    return;
                }

                var adToggleName = 'grid[cols][' + namespace + '][col_settings][enable_art_direction]';
                var $adToggle = $form.find(':input[name="' + adToggleName.replace(/(["\\])/g, '\\$1') + '"]');
                if ($adToggle.length) {
                    $adToggle.val('n');
                }

                $form.find(':input[name^="grid[cols][' + namespace + '][col_settings][art_direction_breakpoints]"]').prop('disabled', true);
            });

            var namespaceState = {};
            $form.find(':input[name^="grid[cols]["]').each(function(){
                var name = this.name || '';
                var match = name.match(/^grid\[cols\]\[([^\]]+)\]\[([^\]]+)\]/);
                if (!match) {
                    return;
                }

                var namespace = match[1];
                var key = match[2];
                if (!namespaceState[namespace]) {
                    namespaceState[namespace] = {
                        hasLabel: false,
                        hasName: false,
                        hasType: false,
                        hasSettings: false
                    };
                }

                if (key === 'col_label') namespaceState[namespace].hasLabel = true;
                if (key === 'col_name') namespaceState[namespace].hasName = true;
                if (key === 'col_type') namespaceState[namespace].hasType = true;
                if (key === 'col_settings') namespaceState[namespace].hasSettings = true;
            });

            Object.keys(namespaceState).forEach(function(namespace){
                var state = namespaceState[namespace];
                var isMalformedSettingsOnly = state.hasSettings && !state.hasLabel && !state.hasName && !state.hasType;
                if (!isMalformedSettingsOnly) {
                    return;
                }

                $form.find(':input[name^="grid[cols][' + namespace + ']"]').prop('disabled', true);
            });
        });
    }

    function applySaveFirstGating(context){
        var $ctx = $(context || document);
        $ctx.find('.grid_col_settings_custom_field_jcogs_img_pro_field').each(function(){
            var saveFirstMessage = 'Save this field definition first, then reopen settings to configure advanced defaults.';
            var $settingsRoot = $(this);
            var namespace = getGridColumnNamespace($settingsRoot);
            var isUnsavedGridColumn = isUnsavedGridNamespace(namespace);
            var $advancedGroups = $settingsRoot.find('.jcogs-img-pro-field-aspect-settings, .jcogs-img-pro-field-srcset-settings, .jcogs-img-pro-field-art-direction-settings');
            var escapedNamespace = namespace ? namespace.replace(/(["\\])/g, '\\$1') : '';
            var $adToggle = escapedNamespace ? $settingsRoot.find(':input[name="grid[cols][' + escapedNamespace + '][col_settings][enable_art_direction]"]') : $();
            var $adToggleField = $adToggle.closest('fieldset, .field-control');

            $advancedGroups.each(function(){
                var $group = $(this);
                var $note = $group.prev('.jcogs-img-pro-field-save-first-note');

                if (isUnsavedGridColumn) {
                    if (!$note.length) {
                        $note = $('<p class="jcogs-img-pro-field-save-first-note" style="margin:0 0 8px;color:#8a6d3b;"></p>');
                        $note.text(saveFirstMessage);
                        $group.before($note);
                    }
                    $note.show();
                    $group.find(':input, button, select, textarea').prop('disabled', true);
                    $group.hide();
                } else {
                    if ($note.length) {
                        $note.hide();
                    }
                    $group.find(':input, button, select, textarea').prop('disabled', false);
                    $group.show();
                }
            });

            if ($adToggleField.length) {
                var $adToggleNote = $adToggleField.prev('.jcogs-img-pro-field-save-first-note-art-direction');
                if (isUnsavedGridColumn) {
                    if (!$adToggleNote.length) {
                        $adToggleNote = $('<p class="jcogs-img-pro-field-save-first-note-art-direction" style="margin:0 0 8px;color:#8a6d3b;"></p>');
                        $adToggleNote.text(saveFirstMessage);
                        $adToggleField.before($adToggleNote);
                    }
                    $adToggleNote.show();
                } else if ($adToggleNote.length) {
                    $adToggleNote.hide();
                }
            }

            if ($adToggle.length) {
                if (isUnsavedGridColumn) {
                    $adToggle.val('n').prop('disabled', true);
                    $adToggleField.find('button.toggle-btn').prop('disabled', true).addClass('disabled');
                } else {
                    $adToggle.prop('disabled', false);
                    $adToggleField.find('button.toggle-btn').prop('disabled', false).removeClass('disabled');
                }
            }
        });
    }

    function rebuildPresetDefaultUI(context){
        var $ctx = $(context || document);
        $ctx.find('.jcogs-img-pro-field-preset-settings').each(function(){
            var $wrap = $(this);
            if (isBloqsTemplateContext($wrap)) {
                return;
            }
            // EE yes/no toggles render a hidden input that holds the authoritative value.
            var $root = resolveSettingsScope($wrap);

            var restrict = String(findSetting($root, 'preset_restrict', 'input[type="hidden"]').val() || '').toLowerCase() === 'y';
            var enablePreset = String(findSetting($root, 'enable_preset', 'input[type="hidden"]').val() || '').toLowerCase() === 'y';
            var enablePresetChoice = String(findSetting($root, 'enable_preset_choice', 'input[type="hidden"]').val() || '').toLowerCase() === 'y';
            var allowNone = String(findSetting($root, 'preset_allow_none', 'input[type="hidden"]').val() || '').toLowerCase() === 'y';

            // Hide editor-facing "Allow None" when editors cannot choose a preset.
            var $allowNoneInput = findSetting($root, 'preset_allow_none', 'input[type="hidden"]');
            if ($allowNoneInput.length) {
                $allowNoneInput.closest('fieldset, .field-control').toggle(enablePreset && enablePresetChoice);
            }

            // If presets are disabled, always hide the allowlist table (even if restrict remains set to y).
            var $allowlistFs = $wrap.closest('fieldset, .field-control');
            if ($allowlistFs.length) {
                $allowlistFs.toggle(enablePreset && restrict);
            }

            var $checks = $wrap.find('input[data-jcogs-preset-checkbox="1"]');
            var allowed = [];
            $checks.each(function(){
                if ($(this).is(':checked')) {
                    allowed.push($(this).val());
                }
            });

            var $select = findSetting($root, 'default_preset_id', 'select');
            var $fs = $select.closest('fieldset, .field-control');
            if (!$select.length) {
                return;
            }
            var current = $select.val();

            // Build option list from the rows we rendered server-side.
            // If restrict is off, allow all rows.
            var options = [];
            // When editor choice is disabled, still allow developers to force "no preset" via Default preset.
            var allowNoneForDefault = allowNone || !enablePresetChoice;
            if (allowNoneForDefault) {
                var noneLabel = (window.JCOGS_IMG_PRO_FIELD && window.JCOGS_IMG_PRO_FIELD.noneOption)
                    ? window.JCOGS_IMG_PRO_FIELD.noneOption
                    : '';
                options.push({value: '', label: noneLabel});
            }
            $checks.each(function(){
                var $cb = $(this);
                var id = $cb.val();
                if (!restrict || allowed.indexOf(id) !== -1) {
                    var label = $cb.closest('tr').find('td').eq(1).text().trim();
                    options.push({value: id, label: label});
                }
            });

            // Rebuild select.
            $select.empty();
            options.forEach(function(opt){
                $select.append($('<option></option>').attr('value', opt.value).text(opt.label));
            });

            // Restore selection if still valid.
            var stillValid = options.some(function(opt){ return String(opt.value) === String(current); });
            if (stillValid) {
                $select.val(current);
            } else {
                $select.val(options.length && options[0].value !== undefined ? options[0].value : '');
            }

            // Show default chooser only when there is an actual choice.
            if ($fs.length && enablePreset) {
                $fs.toggle(options.length > 1);
            }
            $select.prop('disabled', !enablePreset || options.length <= 1);
        });
    }

    function rebuildAspectDefaultUI(context){
        var $ctx = $(context || document);
        $ctx.find('.jcogs-img-pro-field-aspect-settings').each(function(){
            var $wrap = $(this);
            if (isBloqsTemplateContext($wrap)) {
                return;
            }
            var $select = findSetting($wrap, 'default_aspect_ratio', 'select');
            var current = $select.val();

            var pairs = [];
            $wrap.find('input[name*="aspect_ratio_pairs"][name$="[value]"]').each(function(){
                var $value = $(this);
                var value = normalizeAspectRatio($value.val());
                if (!value) return;

                var name = $value.attr('name');
                var labelName = name.replace(/\[value\]$/, '[label]');
                var label = $wrap.find('input[name="' + labelName.replace(/(["\\])/g, '\\$1') + '"]').val();
                label = String(label || '').trim();
                if (!label) label = value;
                pairs.push({value: value, label: label});
            });

            // De-dup by value preserving order.
            var seen = {};
            var options = [];
            pairs.forEach(function(p){
                if (seen[p.value]) return;
                seen[p.value] = true;
                options.push(p);
            });

            $select.empty();
            options.forEach(function(opt){
                $select.append($('<option></option>').attr('value', opt.value).text(opt.label));
            });

            // Implicit default when <= 1.
            if (options.length <= 1) {
                $wrap.find('.jcogs-img-pro-field-aspect-default').hide();
                $select.val('');
                $select.prop('disabled', true);
                return;
            }

            $wrap.find('.jcogs-img-pro-field-aspect-default').show();
            $select.prop('disabled', false);
            var stillValid = options.some(function(opt){ return String(opt.value) === String(current); });
            if (stillValid) {
                $select.val(current);
            } else {
                $select.val(options[0].value);
            }
        });
    }

  $(function(){
      installMiniGridCaptureInterceptor();
            installGridColumnSubmitHardening();
        guardMiniGridEvents(document);
      rewireMiniGridAddLinks(document);
        applySaveFirstGating(document);
    rebuildPresetDefaultUI(document);
    rebuildAspectDefaultUI(document);
    $(document).on('change', 'input[name="enable_preset"], input[name$="[enable_preset]"], input[name="enable_preset_choice"], input[name$="[enable_preset_choice]"], input[name="preset_restrict"], input[name$="[preset_restrict]"], input[name="preset_allow_none"], input[name$="[preset_allow_none]"], input[data-jcogs-preset-checkbox="1"]', function(){
        rebuildPresetDefaultUI(document);
    });

        // EE yes/no controls are buttons that update a hidden input.
        // Depending on context, the hidden input does not always emit a change event,
        // so also rebuild after toggle button clicks.
        $(document).on('click', 'button.toggle-btn', function(){
            var $fs = $(this).closest('fieldset, .field-control');
            if (! $fs.length) {
                return;
            }
            var hasToggle = $fs.find('input[type="hidden"][name="enable_preset"], input[type="hidden"][name$="[enable_preset]"], input[type="hidden"][name="enable_preset_choice"], input[type="hidden"][name$="[enable_preset_choice]"], input[type="hidden"][name="preset_restrict"], input[type="hidden"][name$="[preset_restrict]"], input[type="hidden"][name="preset_allow_none"], input[type="hidden"][name$="[preset_allow_none]"]').length;
            if (! hasToggle) {
                return;
            }
            if (isBloqsTemplateContext($fs)) {
                return;
            }
            var root = resolveSettingsScope($fs)[0] || document;
            setTimeout(function(){
                rebuildPresetDefaultUI(root);
                enforceNestedGroupToggles(root);
            }, 0);
        });

        $(document).on('input change', 'input[name*="aspect_ratio_pairs"][name$="[value]"], input[name*="aspect_ratio_pairs"][name$="[label]"]', function(){
            if (isBloqsTemplateContext($(this))) {
                return;
            }
            var root = resolveSettingsScope($(this))[0] || document;
            rebuildAspectDefaultUI(root);
        });

        // MiniGrid actions (Add/Remove/Reorder) don't always trigger input/change events.
        // Rebuild the default chooser after those actions complete.
        $(document).on('click', '.jcogs-img-pro-field-aspect-settings a[rel="add_row"], .jcogs-img-pro-field-aspect-settings a[rel="jcogs_add_row"], .jcogs-img-pro-field-aspect-settings a[rel="remove_row"]', function(){
            if (isBloqsTemplateContext($(this))) {
                return;
            }
            var root = resolveSettingsScope($(this))[0] || document;
            setTimeout(function(){ rebuildAspectDefaultUI(root); }, 0);
        });

        $(document).on('sortstop', '.jcogs-img-pro-field-aspect-settings .keyvalue-item-container', function(){
            if (isBloqsTemplateContext($(this))) {
                return;
            }
            var root = resolveSettingsScope($(this))[0] || document;
            rebuildAspectDefaultUI(root);
        });

        // EE's group toggles only apply the toggle that changed.
        // When we have nested toggles (crop -> face detect), ensure the child toggle is re-applied
        // so face detection settings don't reappear when toggling crop on/off.
        function syncNestedToggleDisabledState(context){
            var $ctx = $(context || document);

            function reenableYesNoFieldset(fieldName){
                var $input = findSetting($ctx, fieldName, 'input[type="hidden"]');
                if (!$input.length) {
                    return;
                }
                var $fs = $input.first().closest('fieldset, .field-control');
                if (!$fs.length || !$fs.is(':visible')) {
                    return;
                }

                var $btn = $fs.find('button.toggle-btn');

                $btn.prop('disabled', false);
                $input.prop('disabled', false);

                // EE's click handler uses the 'disabled' class.
                $btn.removeClass('disabled');
            }

            // Ensure preset-restrict toggle remains usable when revealed by the preset group toggle.
            reenableYesNoFieldset('enable_preset_choice');
            reenableYesNoFieldset('preset_restrict');
            reenableYesNoFieldset('preset_allow_none');
            reenableYesNoFieldset('enable_focal');
            reenableYesNoFieldset('default_allow_scale_larger');

            // EE's group toggle show/hide can leave toggle buttons + hidden inputs disabled
            // because hidden inputs are disabled when their fieldset is hidden, but not always
            // re-enabled when shown again. Ensure the nested face-detect toggle is usable
            // whenever its fieldset is visible.
            var $input = findSetting($ctx, 'enable_face_detect', 'input[type="hidden"]');
            if ($input.length) {
                var $fs = $input.first().closest('fieldset, .field-control');
                var $btn = $fs.find('button.toggle-btn');

                // Only re-enable when visible.
                // If the fieldset is hidden, let EE manage disabled state.
                if ($fs.is(':visible')) {
                    $btn.prop('disabled', false);
                    $input.prop('disabled', false);

                    // EE's click handler uses the 'disabled' class.
                    $btn.removeClass('disabled');
                }
            }
        }

        function enforceNestedGroupToggles(context){
            try {
                if (typeof EE === 'undefined' || !EE.cp || typeof EE.cp.form_group_toggle !== 'function') {
                    return;
                }

                var root = context || document;

                var enablePreset = findToggleInput(root, 'enable_preset');
                var enablePresetOn = false;
                if (enablePreset) {
                    EE.cp.form_group_toggle(enablePreset);
                    enablePresetOn = String(enablePreset.value || '').toLowerCase() === 'y';
                }

                var presetRestrict = findToggleInput(root, 'preset_restrict');
                if (presetRestrict && enablePresetOn) EE.cp.form_group_toggle(presetRestrict);

                var enableResponsive = findToggleInput(root, 'enable_responsive_defaults');
                if (enableResponsive) EE.cp.form_group_toggle(enableResponsive);

                var enableArtDirection = findToggleInput(root, 'enable_art_direction');
                if (enableArtDirection) EE.cp.form_group_toggle(enableArtDirection);

                // EE yes/no toggles are rendered as a hidden input inside a button.toggle-btn.
                // The hidden input holds the value and the data-group-toggle attribute.
                var crop = findToggleInput(root, 'enable_crop');
                if (crop) EE.cp.form_group_toggle(crop);

                var face = findToggleInput(root, 'enable_face_detect');
                if (face) EE.cp.form_group_toggle(face);

                syncNestedToggleDisabledState(root);
            } catch (e) {
                // no-op
            }
        }

        $(document).on('change', 'input[name="enable_preset"], input[name$="[enable_preset]"], input[name="preset_restrict"], input[name$="[preset_restrict]"], input[name="enable_crop"], input[name$="[enable_crop]"], input[name="enable_face_detect"], input[name$="[enable_face_detect]"], input[name="enable_responsive_defaults"], input[name$="[enable_responsive_defaults]"], input[name="enable_art_direction"], input[name$="[enable_art_direction]"]', function(){
            var root = $(this).closest('.modal, .modal-wrap, form')[0] || document;
            setTimeout(function(){ enforceNestedGroupToggles(root); }, 0);
        });

        // Initial state (page load).
        // common.js has a naive initialiser that doesn't understand pipe-delimited data-group values;
        // enforce the authoritative EE.cp.form_group_toggle behaviour after page init.
        setTimeout(function(){ enforceNestedGroupToggles(document); }, 0);
        setTimeout(function(){ enforceNestedGroupToggles(document); }, 50);
        setTimeout(function(){ rewireMiniGridAddLinks(document); }, 0);
        setTimeout(function(){ rewireMiniGridAddLinks(document); }, 120);

    if (typeof FieldManager !== 'undefined' && FieldManager.on) {
            FieldManager.on('fieldModalDisplay', function(modal){
                guardMiniGridEvents(modal);
            rewireMiniGridAddLinks(modal[0] || document);
            setTimeout(function(){ rewireMiniGridAddLinks(modal[0] || document); }, 120);
                applySaveFirstGating(modal[0] || document);
                rebuildPresetDefaultUI(modal);
                rebuildAspectDefaultUI(modal);
                syncArtDirectionDefaultUI(modal);
                enforceNestedGroupToggles(modal[0] || document);
            });
    }
  });
})(jQuery);
