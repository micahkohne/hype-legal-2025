
/**
 * JCOGS Image Pro Field - JasavaScript for Publish UI
 *====================================================
 *
 * ExpressionEngine 7 fieldtype publish/editing interface.
 * Handles image selection, cropping, focal point selection, face detection,
 * art direction alternates, and preview rendering.
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
    var Config = window.JCOGS_IMG_PRO_FIELD_PUBLISH || {};
    var token = (Config && Config.token) ? String(Config.token) : "";
    var I18N = (Config && Config.i18n) ? Config.i18n : {};
    function t(key, fallback){
        try {
            var v = I18N[key];
            if (v != null && String(v).length) return String(v);
        } catch (e) {}
        return fallback != null ? String(fallback) : String(key || '');
    }
    $(function(){
        var PickerState = {
            activeAdStorage: null,
            activeAdStorageExpiry: 0,
            activeAdPicker: null,
            activeAdPickerExpiry: 0,
            activeMainRoot: null,
            activeMainRootExpiry: 0
        };

        function patchFileFieldContainer(){
            try {
                if (!window.FileField || !window.FileField.prototype) return false;
                if (window.FileField.prototype.__jcogsImgProFieldPatched) return true;

                var original = window.FileField.prototype.getFieldContainer;
                window.FileField.prototype.getFieldContainer = function(mainDropzone){
                    try {
                        var $field = $(this.props && this.props.thisField ? this.props.thisField : this);
                        if (mainDropzone !== undefined) {
                            var $md = $(mainDropzone);
                            var $mdScope = $md.closest('.jcogs-img-pro-field-ad-picker');
                            if (!$mdScope.length) $mdScope = $md.closest('.jcogs-img-pro-field-main-picker');
                            if ($mdScope.length) return $mdScope;
                        }
                        var $scope = $field.closest('.jcogs-img-pro-field-ad-picker');
                        if (!$scope.length) $scope = $field.closest('.jcogs-img-pro-field-main-picker');
                        if ($scope.length) return $scope;
                    } catch (e) {}

                    if (typeof original === 'function') {
                        return original.call(this, mainDropzone);
                    }

                    return $(this.props && this.props.thisField ? this.props.thisField : this);
                };

                window.FileField.prototype.__jcogsImgProFieldPatched = true;
                return true;
            } catch (e) {}
            return false;
        }

        (function ensureFileFieldPatch(){
            var attempts = 60;
            var tick = function(){
                if (patchFileFieldContainer()) return;
                attempts -= 1;
                if (attempts <= 0) return;
                setTimeout(tick, 200);
            };
            tick();
        })();
        function scheduleFileFieldRender(target, attempts) {
            var remaining = (attempts == null ? 5 : attempts);
            var node = target && target.jquery ? target[0] : target;
            if (!node) node = document;

            try { patchFileFieldContainer(); } catch (ePatch) {}

            if (window.FileField && typeof window.FileField.renderFields === 'function') {
                try { window.FileField.renderFields(node); } catch (e) {}
                return;
            }

            if (remaining <= 0) return;
            setTimeout(function(){
                scheduleFileFieldRender(node, remaining - 1);
            }, 200);
        }

        function syncFilePickerTargets(context) {
            var $context = context && context.jquery ? context : $(context || document);
            var $containers = $context.find('div[data-file-field-react]');
            if (!$containers.length) return;

            $containers.each(function(){
                var $container = $(this);
                if (!$container.closest('.jcogs-img-pro-field').length) return;

                var $scope = $container.closest('.jcogs-img-pro-field-ad-picker');
                if (!$scope.length) {
                    $scope = $container.closest('.jcogs-img-pro-field-main-picker');
                }
                if (!$scope.length) {
                    $scope = $container.closest('.jcogs-img-pro-field');
                }

                var name = ($container.attr('data-input-value') || '').toString();
                if (!name) {
                    var $input = $scope.find('input.js-file-input').first();
                    name = $input.length ? ($input.attr('name') || '') : '';
                }
                if (!name) return;

                var safe = name.replace(/[\[\]']+/g, '_');
                $container.attr('data-input-value', name);

                $scope.find('.file-field-filepicker').each(function(){
                    var $button = $(this);
                    $button.attr('data-input-value', name);
                    $button.attr('data-input-image', safe);
                });

                var $img = $();
                try {
                    if (name) {
                        var escName = name.replace(/(["\\])/g, '\\$1');
                        var $named = $scope.find('input.js-file-input[name="' + escName + '"]').first();
                        if ($named.length) {
                            $img = $named.siblings('.fields-upload-chosen').find('img.js-file-image').first();
                        }
                    }
                } catch (eImg) {}
                if (!$img.length) {
                    $img = $scope.find('.fields-upload-chosen img.js-file-image').first();
                }
                if (!$img.length) {
                    $img = $scope.find('img.js-file-image').first();
                }
                if ($img.length) {
                    $img.attr('id', safe);
                }
            });

                var contextNode = ($context && $context.length) ? $context[0] : $context;
                scheduleFileFieldRender(contextNode || document, 3);
        }

        function initJcogsImgProField(context){
            $('[data-jcogs-img-pro-field="1"]', context || document).each(function(){
                var $root = $(this);
                if ($root.data('jcogs-img-pro-field-init')) return;
                $root.data('jcogs-img-pro-field-init', 1);

                var actUrl = $root.data('act-url');
                var previewActUrl = $root.data('preview-act-url');
                var faceDetectActUrl = $root.data('face-detect-act-url');
                var entryId = parseInt($root.data('entry-id'), 10) || 0;
                var fieldId = parseInt($root.data('field-id'), 10) || 0;
                var isComposite = String($root.data('is-composite') || '0') === '1';
                var hasModal = $root.find('.jcogs-img-pro-field-modal').length > 0;
                if (!fieldId) return;
                var canUseUsageAction = !!(actUrl && entryId > 0 && fieldId > 0);

            var $status = $root.find('.jcogs-img-pro-field-status');
            function setStatus(text, isError){
                $status.text(text || '');
                $status.css('color', isError ? '#b71c1c' : '#2e7d32');
            }

            function localizeErrorMessage(errorValue) {
                var raw = (errorValue == null ? '' : String(errorValue)).trim();
                if (!raw) return t('unexpected_response', 'Unexpected response');
                var key = raw.toLowerCase();
                if (key === 'missing_file') {
                    return t('missing_file', 'Please choose an image first');
                }
                if (/^[a-z0-9_]+$/.test(key)) {
                    return t(key, raw);
                }
                return raw;
            }

            function isCropUiActive($root) {
                return parseInt($root.data('jcogs-img-pro-field-crop-active') || '0', 10) === 1;
            }

            function setCropUiActive($root, active) {
                var on = !!active;
                $root.data('jcogs-img-pro-field-crop-active', on ? 1 : 0);
                $root.toggleClass('jcogs-img-pro-field-crop-inactive', !on);

                var $controls = $root.find('.jcogs-img-pro-field-pick-rect, .jcogs-img-pro-field-clear-rect, .jcogs-img-pro-field-preview-reload');
                $controls.prop('disabled', !on);
                $controls.attr('aria-disabled', on ? 'false' : 'true');

                try { updateValidationUi($root); } catch (e) {}
            }

            function ensureTabBadge($btn) {
                if (!$btn || !$btn.length) return;
                if ($btn.find('.jcogs-img-pro-field-tab-badge').length) return;
                $btn.append('<span class="jcogs-img-pro-field-tab-badge" aria-hidden="true">!</span>');
            }

            function setTabError($btn, hasError) {
                if (!$btn || !$btn.length) return;
                ensureTabBadge($btn);
                var on = !!hasError;
                $btn.toggleClass('jcogs-img-pro-field-tab-error', on);
                $btn.find('.jcogs-img-pro-field-tab-badge').toggle(on);
            }

            function initTabs($root) {
                var $tabs = $root.find('.jcogs-img-pro-field-tabs').first();
                if (!$tabs.length) {
                    setCropUiActive($root, true);
                    return;
                }

                var $buttons = $tabs.find('.jcogs-img-pro-field-tab');
                if (!$buttons.length) {
                    setCropUiActive($root, true);
                    return;
                }

                var $panels = $root.find('.jcogs-img-pro-field-tab-panel');
                if (!$panels.length) {
                    setCropUiActive($root, true);
                    return;
                }

                function activateTab(tab) {
                    tab = (tab == null ? '' : String(tab));
                    if (!tab) return;

                    $buttons.each(function(){
                        var $btn = $(this);
                        var isActive = String($btn.data('jcogs-tab') || '') === tab;
                        $btn.prop('disabled', false);
                        $btn.attr('aria-selected', isActive ? 'true' : 'false');
                        $btn.toggleClass('active', isActive);
                    });

                    $panels.each(function(){
                        var $panel = $(this);
                        var isMatch = String($panel.data('jcogs-tab-panel') || '') === tab;
                        $panel.css('display', isMatch ? 'block' : 'none');
                    });

                    setCropUiActive($root, tab === 'crop');
                    try { updateValidationUi($root); } catch (eVal) {}

                    var $activePanel = $panels.filter(function(){
                        return String($(this).data('jcogs-tab-panel') || '') === tab;
                    }).first();
                    if ($activePanel.length) {
                        setTimeout(function(){
                            try { syncFilePickerTargets($activePanel[0]); } catch (e) {}
                            try { scheduleFileFieldRender($activePanel[0], 5); } catch (e2) {}
                            try {
                                if (window.EE && EE.FileField && typeof EE.FileField.setup === 'function') {
                                    EE.FileField.setup($activePanel);
                                }
                            } catch (e3) {}
                        }, 0);
                    }
                }

                $root.off('click.jcogsImgProFieldTabs', '.jcogs-img-pro-field-tab');
                $root.on('click.jcogsImgProFieldTabs', '.jcogs-img-pro-field-tab', function(e){
                    try { e.preventDefault(); } catch (e1) {}
                    activateTab($(this).data('jcogs-tab'));
                });

                var initial = $buttons.filter('[data-jcogs-tab-default="1"]').first().data('jcogs-tab');
                if (!initial) initial = $buttons.first().data('jcogs-tab');
                activateTab(initial);
            }

            function normalizeYesNo(value) {
                if (value == null) return '';
                var v = String(value).trim().toLowerCase();
                if (v === 'y' || v === '1' || v === 'true' || v === 'yes') return 'yes';
                if (v === 'n' || v === '0' || v === 'false' || v === 'no') return 'no';
                return v;
            }

            try { initTabs($root); } catch (eTabs) {}

            function syncCompositeContext($root) {
                if (!$root || !$root.length) return;

                var $contentType = $root.find('input[name$="[content_type]"]');
                var $containerId = $root.find('input[name$="[container_id]"]');
                var $blockId = $root.find('input[name$="[block_id]"]');

                var $block = $root.closest('.blocksft-block');
                if ($block.length) {
                    var blockId = null;
                    var $blockIdField = $block.find('input[js-block-id-field]').first();
                    if ($blockIdField.length) {
                        blockId = $blockIdField.val();
                    }
                    if (blockId == null || String(blockId).length === 0) {
                        blockId = $block.data('id');
                    }
                    var blockIdStr = (blockId == null ? '' : String(blockId)).trim();
                    if (/^\d+$/.test(blockIdStr)) {
                        $blockId.val(blockIdStr);
                    } else {
                        // Ignore transient Bloqs placeholders (e.g. blocks_block_id_*).
                        $blockId.val('');
                    }
                    if ($contentType.length) {
                        $contentType.val('bloqs');
                    }
                    if ($containerId.length) {
                        var $blocksField = $block.closest('.blocksft');
                        var fieldId = $blocksField.data('field-id');
                        if (fieldId != null && String(fieldId).length) {
                            $containerId.val(String(fieldId));
                        }
                    }
                }
            }

            function getContextPayload($root) {
                var read = function(suffix) {
                    try {
                        return ($root.find('input[name$="[' + suffix + ']"]').val() || '').toString().trim();
                    } catch (e) {
                        return '';
                    }
                };

                var payload = {
                    content_type: read('content_type'),
                    container_id: read('container_id'),
                    row_id: read('row_id'),
                    fluid_field_data_id: read('fluid_field_data_id'),
                    block_id: read('block_id')
                };

                if (!payload.content_type) {
                    payload.content_type = ($root.data('content-type') || '').toString().trim();
                }
                if (!payload.container_id) {
                    payload.container_id = ($root.data('container-id') || '').toString().trim();
                }
                if (!payload.row_id) {
                    payload.row_id = ($root.data('row-id') || '').toString().trim();
                }
                if (!payload.fluid_field_data_id) {
                    payload.fluid_field_data_id = ($root.data('fluid-field-data-id') || '').toString().trim();
                }
                if (!payload.block_id) {
                    payload.block_id = ($root.data('block-id') || '').toString().trim();
                }

                return payload;
            }

            function clearTransientAdjustmentsForNewGridRow($root) {
                if (!$root || !$root.length) return;

                try {
                    $root.find('select[name$="[preset_id]"]').val('');
                    $root.find('input[name$="[focal_x]"]').val('');
                    $root.find('input[name$="[focal_y]"]').val('');

                    $root.find('input[name$="[crop]"]').val('');
                    $root.find('select[name$="[crop_mode]"]').val('');
                    $root.find('select[name$="[crop_focus_h]"]').val('');
                    $root.find('select[name$="[crop_focus_v]"]').val('');
                    $root.find('input[name$="[crop_offset_x]"]').val('');
                    $root.find('input[name$="[crop_offset_y]"]').val('');
                    $root.find('select[name$="[crop_smart_scaling]"]').val('');

                    $root.find('input[name$="[width]"]').val('');
                    $root.find('input[name$="[height]"]').val('');
                    $root.find('input[name$="[aspect_ratio]"]').val('');

                    $root.find('input[name$="[crop_rect_left]"]').val('');
                    $root.find('input[name$="[crop_rect_top]"]').val('');
                    $root.find('input[name$="[crop_rect_width]"]').val('');
                    $root.find('input[name$="[crop_rect_height]"]').val('');

                    $root.find('input[type="hidden"][data-jcogs-ad-storage="1"]').val('');
                    $root.find('.jcogs-img-pro-field-ad-picker input.js-file-input').each(function(){
                        try {
                            $(this).val('').attr('data-id', '').removeData('file-id');
                        } catch (eAdInput) {}
                    });
                } catch (eReset) {}

                try { updateCropButtonLabel($root); } catch (eLabel) {}
                try { syncArtDirectionPickers($root); } catch (eAdSync) {}
                try { renderLiveSummaryChips($root); } catch (eSummary) {}
                try { updateValidationUi($root); } catch (eValidation) {}
            }

            function buildGridAdjustmentSignature($root) {
                var values = [];
                var selectors = [
                    'input[name$="[file_value]"]',
                    'select[name$="[preset_id]"]',
                    'input[name$="[focal_x]"]',
                    'input[name$="[focal_y]"]',
                    'input[name$="[crop]"]',
                    'select[name$="[crop_mode]"]',
                    'select[name$="[crop_focus_h]"]',
                    'select[name$="[crop_focus_v]"]',
                    'input[name$="[crop_offset_x]"]',
                    'input[name$="[crop_offset_y]"]',
                    'select[name$="[crop_smart_scaling]"]',
                    'input[name$="[width]"]',
                    'input[name$="[height]"]',
                    'input[name$="[aspect_ratio]"]',
                    'input[name$="[crop_rect_left]"]',
                    'input[name$="[crop_rect_top]"]',
                    'input[name$="[crop_rect_width]"]',
                    'input[name$="[crop_rect_height]"]'
                ];

                for (var i = 0; i < selectors.length; i += 1) {
                    try {
                        values.push(($root.find(selectors[i]).first().val() || '').toString().trim());
                    } catch (eRead) {
                        values.push('');
                    }
                }

                var adValues = [];
                try {
                    $root.find('input[type="hidden"][data-jcogs-ad-storage="1"]').each(function(){
                        adValues.push(($(this).val() || '').toString().trim());
                    });
                } catch (eAdRead) {}

                values.push(adValues.join(','));
                return values.join('|');
            }

            function maybeResetClonedNewGridRow($root) {
                if (!$root || !$root.length) return;
                if (parseInt($root.data('jcogs-img-pro-field-grid-clone-check-done') || '0', 10) === 1) return;
                $root.data('jcogs-img-pro-field-grid-clone-check-done', 1);

                var ctx = getContextPayload($root);
                var contentType = (ctx && ctx.content_type != null ? String(ctx.content_type) : '').toLowerCase().trim();
                if (contentType !== 'grid') return;

                var rowId = (ctx && ctx.row_id != null ? String(ctx.row_id) : '').trim();
                if (/^\d+$/.test(rowId)) return;

                var signature = buildGridAdjustmentSignature($root);
                if (!signature || signature.replace(/[|,]/g, '') === '') return;

                var fieldIdStr = (fieldId || '').toString();
                var hasDuplicateSibling = false;

                $('[data-jcogs-img-pro-field="1"]').not($root).each(function(){
                    if (hasDuplicateSibling) return false;

                    var $other = $(this);
                    var otherFieldId = (($other.data('field-id') || '') + '').toString();
                    if (otherFieldId !== fieldIdStr) return;

                    var otherCtx = getContextPayload($other);
                    var otherType = (otherCtx && otherCtx.content_type != null ? String(otherCtx.content_type) : '').toLowerCase().trim();
                    if (otherType !== 'grid') return;

                    if (buildGridAdjustmentSignature($other) === signature) {
                        hasDuplicateSibling = true;
                    }
                });

                if (hasDuplicateSibling) {
                    clearTransientAdjustmentsForNewGridRow($root);
                }
            }

            function setModalOpen($root, open) {
                var $modal = $root.find('.jcogs-img-pro-field-modal').first();
                if (!$modal.length) return;
                $modal.toggleClass('is-open', !!open);
                $modal.attr('aria-hidden', open ? 'false' : 'true');
                if (open) {
                    try { alignModalToViewport($root); } catch (eAlign) {}
                    setTimeout(function(){
                        try { alignModalToViewport($root); } catch (eAlign2) {}
                        try {
                            var fileValue = getMainFileValue($root);
                            if (!fileValue) {
                                fileValue = ($root.find('input[name$="[file_value]"]').val() || '').toString().trim();
                            }
                            if (previewActUrl && fileValue) {
                                triggerPreviewReload($root);
                            } else {
                                maybeAutoLoadPreview($root);
                            }
                        } catch (e) {}
                        try { updateValidationUi($root); } catch (e2) {}
                    }, 0);
                }
            }

            function alignModalToViewport($root) {
                var $modal = $root.find('.jcogs-img-pro-field-modal').first();
                if (!$modal.length) return;

                function getRenderedLeft() {
                    try {
                        if ($modal[0] && typeof $modal[0].getBoundingClientRect === 'function') {
                            var modalRect = $modal[0].getBoundingClientRect();
                            if (modalRect && typeof modalRect.left === 'number' && isFinite(modalRect.left)) {
                                return modalRect.left;
                            }
                        }
                    } catch (eMeasure) {}
                    return 0;
                }

                var viewportWidth = 0;
                try {
                    viewportWidth = Math.max(
                        (window && window.innerWidth) ? window.innerWidth : 0,
                        (document && document.documentElement && document.documentElement.clientWidth) ? document.documentElement.clientWidth : 0
                    );
                } catch (eVw) {
                    viewportWidth = 0;
                }

                var isTabletOrBelow = viewportWidth > 0 ? (viewportWidth <= 1024) : false;
                if (!isTabletOrBelow) {
                    $modal.css({ left: '', width: '' });
                    return;
                }

                // Mobile/tablet can inherit transformed ancestors in EE CP. Correct against
                // rendered geometry instead of assuming layout origin.
                $modal.css({
                    left: '0px',
                    width: (viewportWidth > 0 ? String(viewportWidth) + 'px' : '')
                });

                var measuredLeft = getRenderedLeft();
                if (isFinite(measuredLeft) && Math.abs(measuredLeft) > 0.5) {
                    $modal.css('left', String(0 - measuredLeft) + 'px');
                }

                var measuredLeftAfter = getRenderedLeft();
                if (isFinite(measuredLeftAfter) && Math.abs(measuredLeftAfter) > 0.5) {
                    var currentLeft = parseFloat($modal.css('left'));
                    if (!isFinite(currentLeft)) currentLeft = 0;
                    $modal.css('left', String(currentLeft - measuredLeftAfter) + 'px');
                }
            }

            function resolveNumericFileIdFromString(raw) {
                raw = (raw == null ? '' : String(raw)).trim();
                if (!raw) return '';

                // Plain numeric file_id.
                if (/^\d+$/.test(raw)) return raw;

                // JSON payloads like {"file_id":123}.
                if (raw.charAt(0) === '{') {
                    try {
                        var obj = JSON.parse(raw);
                        if (obj && typeof obj === 'object' && obj.file_id != null) {
                            var n1 = parseInt(obj.file_id, 10);
                            if (isFinite(n1) && n1 > 0) return String(n1);
                        }
                    } catch (eJson) {}
                }

                // EE file tokens / embedded IDs.
                var m = raw.match(/\bfile[_\s-]*id\b\D*(\d+)/i);
                if (m && m[1]) return String(parseInt(m[1], 10));
                m = raw.match(/\{file:(\d+)(?::[^}]*)?\}/i);
                if (m && m[1]) return String(parseInt(m[1], 10));

                // Last resort: any digit run.
                m = raw.match(/(\d{1,10})/);
                if (m && m[1]) {
                    var n2 = parseInt(m[1], 10);
                    if (isFinite(n2) && n2 > 0) return String(n2);
                }

                return '';
            }

            function resolvePickerFileIdFromInput($input) {
                if (!$input || !$input.length) return '';
                var $chosen = $input.siblings('.fields-upload-chosen');
                var chosenHidden = $chosen.length && $chosen.hasClass('hidden');
                if (chosenHidden) return '';

                var dataId = ($input.data('file-id') || '').toString().trim();
                if (dataId && /^\d+$/.test(dataId)) return dataId;

                var attrId = ($input.attr('data-id') || '').toString().trim();
                if (attrId && /^\d+$/.test(attrId)) return attrId;

                var raw = ($input.val() || '').toString().trim();
                if (raw) return resolveNumericFileIdFromString(raw);

                return '';
            }

            function syncArtDirectionPickers($root){
                if (!$root || !$root.length) return;

                function extractNumericFileId($inputs) {
                    var best = '';
                    try {
                        $inputs.each(function(){
                            if (best) return;
                            var $el = $(this);
                            var raw = ($el.val() || '').toString().trim();
                            var $chosen = $el.siblings('.fields-upload-chosen');
                            var ignoreStale = (raw === '' && $chosen.length && $chosen.hasClass('hidden'));
                            if (!ignoreStale) {
                                var dataId = ($el.data('file-id') || '').toString().trim();
                                if (dataId && /^\d+$/.test(dataId)) {
                                    best = dataId;
                                    return;
                                }
                                var attrId = ($el.attr('data-id') || '').toString().trim();
                                if (attrId && /^\d+$/.test(attrId)) {
                                    best = attrId;
                                    return;
                                }
                            }
                            raw = raw;
                            if (!raw) return;
                            var v = resolveNumericFileIdFromString(raw);
                            if (!v) return;
                            best = String(v);
                        });
                    } catch (e) {}
                    return best;
                }

                // Copy values from the unique picker fields into the structured hidden inputs that
                // post_save() reads (jcogs_img_pro_field[field_id][art_direction_files][row]).
                $root.find('input[type="hidden"][data-jcogs-ad-storage="1"]').each(function(){
                    var $storage = $(this);
                    var pickerName = ($storage.data('picker-name') || '').toString();
                    if (!pickerName) return;

                    // EE's drag/drop field may emit names like:
                    // - pickerName
                    // - pickerName[]
                    // - pickerName[0]
                    // So search within the AD picker container first, then fall back.
                    var $container = $storage.closest('.jcogs-img-pro-field-ad-picker');
                    var esc = pickerName.replace(/(["\\])/g, '\\$1');
                    var $candidates = $();
                    if ($container.length) {
                        $candidates = $container.find('input[name="' + esc + '"] , input[name="' + esc + '[]"], input[name^="' + esc + '"]')
                            .not('input[data-jcogs-ad-storage="1"]');
                    }
                    if ($container.length) {
                        $candidates = $candidates.add($container.find('input.js-file-input'));
                        $candidates = $candidates.add($container.find('[data-file-id], [data-id]'));
                    }
                    // Do not fall back to root-level inputs; they can reflect the main picker.

                    var val = extractNumericFileId($candidates);
                    $storage.val(val);
                });

                try { renderLiveSummaryChips($root); } catch (eSummarySync) {}
            }

            function getMainFileInput($root) {
                try {
                    var $mainPicker = $root.find('.jcogs-img-pro-field-main-picker');
                    if ($mainPicker.length) {
                        var $visibleChosen = $mainPicker.find('input.js-file-input').filter(function(){
                            var $chosen = $(this).siblings('.fields-upload-chosen');
                            return $chosen.length && !$chosen.hasClass('hidden');
                        }).first();
                        if ($visibleChosen.length) return $visibleChosen;

                        var $withChosen = $mainPicker.find('input.js-file-input').filter(function(){
                            return $(this).siblings('.fields-upload-chosen').length > 0;
                        }).first();
                        if ($withChosen.length) return $withChosen;

                        var $pick = $mainPicker.find('input.js-file-input[name]:not([name=""])').last();
                        if ($pick.length) return $pick;
                    }

                    var name = ($root.data('main-file-input-name') || '').toString();
                    if (name) {
                        var esc = name.replace(/(["\\])/g, '\\$1');
                        var $inp = $root.find('input.js-file-input[name="' + esc + '"]');
                        if ($inp.length) return $inp.first();

                        var suffix = '\\[' + esc.replace(/([\[\]])/g, '\\$1') + '\\]';
                        $inp = $root.find('input.js-file-input[name$="' + suffix + '"]');
                        if ($inp.length) return $inp.first();
                    }
                } catch (e) {}
                // Fallback: first file input.
                return $root.find('input.js-file-input').first();
            }

            function resolveMainPickerFileValue($root) {
                var bestId = '';
                var bestScore = -1;

                try {
                    var name = ($root.data('main-file-input-name') || '').toString();
                    var $mainPicker = $root.find('.jcogs-img-pro-field-main-picker');
                    var $candidates = $();
                    if ($mainPicker.length) {
                        if (name) {
                            var esc = name.replace(/(["\\])/g, '\\$1');
                            $candidates = $candidates.add($mainPicker.find('input.js-file-input[name="' + esc + '"]'));
                            $candidates = $candidates.add($mainPicker.find('input.js-file-input[name="' + esc + '[]"]'));
                            $candidates = $candidates.add($mainPicker.find('input.js-file-input[name^="' + esc + '["]'));
                        }
                        $candidates = $candidates.add($mainPicker.find('input.js-file-input'));
                    }

                    $candidates.each(function(){
                        var $input = $(this);
                        var id = resolvePickerFileIdFromInput($input);
                        if (!id) return;

                        var score = 0;
                        var inputName = ($input.attr('name') || '').toString();
                        var rawVal = ($input.val() || '').toString().trim();
                        var $chosen = $input.siblings('.fields-upload-chosen');
                        var chosenVisible = $chosen.length && !$chosen.hasClass('hidden');

                        if (name && inputName === name) score += 100;
                        if (name && inputName.indexOf(name + '[') === 0) score += 80;
                        if (chosenVisible) score += 50;
                        if (rawVal) score += 20;
                        if ($mainPicker.length && $input.closest('.jcogs-img-pro-field-main-picker').length) score += 10;

                        if (score >= bestScore) {
                            bestScore = score;
                            bestId = String(id);
                        }
                    });

                    if ((!bestId || !/^\d+$/.test(bestId)) && $mainPicker.length) {
                        var $visibleChosenName = $mainPicker.find('.fields-upload-chosen:visible .fields-upload-chosen-name[data-id]').first();
                        var visibleChosenId = ($visibleChosenName.attr('data-id') || '').toString().trim();
                        if (visibleChosenId && /^\d+$/.test(visibleChosenId)) {
                            bestId = visibleChosenId;
                        }
                    }
                } catch (e) {}

                return bestId;
            }

            function getMainFileValue($root) {
                try {
                    var $mainInput = getMainFileInput($root);
                    var resolved = resolveMainPickerFileValue($root);
                    if (resolved) {
                        return resolved;
                    }
                    return ($mainInput.val() || '').toString();
                } catch (e) {
                    return '';
                }
            }

            function syncMainFileValue($root) {
                try {
                    var val = getMainFileValue($root);
                    var tokenVal = val ? ('{file:' + val + ':url}') : '';
                    var name = ($root.data('main-file-input-name') || '').toString();
                    if (name) {
                        var esc = name.replace(/(["\\])/g, '\\$1');
                        $root.find('input.js-file-input[name="' + esc + '"], input.js-file-input[name="' + esc + '[]"], input.js-file-input[name^="' + esc + '["]').val(tokenVal).attr('data-id', val || '');
                    } else {
                        getMainFileInput($root).val(tokenVal).attr('data-id', val || '');
                    }
                    if (!val) {
                        $root.find('.jcogs-img-pro-field-main-picker .fields-upload-chosen-name').attr('data-id', '');
                        $root.find('.jcogs-img-pro-field-main-picker input.js-file-input').removeData('file-id').attr('data-id', '');
                    }
                    $root.find('input[name$="[file_value]"]').val(val);
                } catch (e) {}
            }

            function updateMainPickerVisuals($root, fid, detail) {
                try {
                    var $mainPicker = $root.find('.jcogs-img-pro-field-main-picker').first();
                    if (!$mainPicker.length) return;

                    var numericId = resolveNumericFileIdFromString(fid);
                    var safeId = numericId ? String(numericId) : '';

                    var fileName = '';
                    var previewUrl = '';
                    if (detail && typeof detail === 'object') {
                        fileName = String(detail.file_name || detail.filename || detail.title || detail.name || '').trim();
                        previewUrl = String(
                            detail.thumb_url
                            || detail.thumbnail_url
                            || detail.image_url
                            || detail.url
                            || detail.absolute_url
                            || ''
                        ).trim();
                    }

                    var $chosen = $mainPicker.find('.fields-upload-chosen').first();
                    if ($chosen.length) {
                        $chosen.removeClass('hidden');
                    }

                    var $chosenName = $mainPicker.find('.fields-upload-chosen-name').first();
                    if ($chosenName.length && safeId) {
                        $chosenName.attr('data-id', safeId);
                        if (fileName) {
                            $chosenName.text(fileName);
                        }
                    }

                    var $img = $mainPicker.find('img.js-file-image').first();
                    if ($img.length && previewUrl) {
                        $img.attr('src', previewUrl);
                    }

                    var $mainInput = getMainFileInput($root);
                    if ($mainInput && $mainInput.length) {
                        try { $mainInput.trigger('input'); } catch (eInput) {}
                        try { $mainInput.trigger('change'); } catch (eChange) {}
                    }

                    try { syncFilePickerTargets($mainPicker); } catch (eSyncTarget) {}
                    try { scheduleFileFieldRender($mainPicker[0], 5); } catch (eRender) {}
                } catch (e) {}
            }

            function escapeHtml(value) {
                return String(value == null ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function renderLiveSummaryChips($root) {
                if (!$root || !$root.length) return;

                var $chips = $root.find('.jcogs-img-pro-field-summary-chips').first();
                if (!$chips.length) return;

                var fileId = parseInt(getMainFileId($root), 10) || 0;
                var imageSet = fileId > 0;

                var presetLabel = '';
                try {
                    var $preset = $root.find('select[name$="[preset_id]"]').first();
                    if ($preset.length) {
                        var $opt = $preset.find('option:selected').first();
                        var raw = ($opt.length ? $opt.text() : '').toString().trim();
                        if (!raw || /^none$/i.test(raw)) {
                            presetLabel = t('none', 'None');
                        } else {
                            presetLabel = raw;
                        }
                    }
                } catch (ePreset) {}
                if (!presetLabel) {
                    presetLabel = t('none', 'None');
                }

                var aspect = ($root.find('input[name$="[aspect_ratio]"]').val() || '').toString().trim();
                if (aspect === '__inherit__') {
                    aspect = ($root.data('default-aspect-ratio') || '').toString().trim();
                }

                var hasFocal = false;
                try {
                    var fx = ($root.find('input[name$="[focal_x]"]').val() || '').toString().trim();
                    var fy = ($root.find('input[name$="[focal_y]"]').val() || '').toString().trim();
                    hasFocal = !!(fx && fy);
                } catch (eFocal) {}

                var adTotal = 0;
                var adSet = 0;
                try {
                    $root.find('input[type="hidden"][data-jcogs-ad-storage="1"]').each(function(){
                        adTotal += 1;
                        var v = ($(this).val() || '').toString().trim();
                        if (v) adSet += 1;
                    });
                } catch (eAd) {}

                var chips = [];
                chips.push('<span class="jcogs-img-pro-field-chip ' + (imageSet ? 'jcogs-img-pro-field-chip-success' : 'jcogs-img-pro-field-chip-warn') + '">' + escapeHtml(t('chip_image', 'Image')) + ': ' + escapeHtml(imageSet ? t('chip_set', 'Set') : t('none', 'None')) + '</span>');
                chips.push('<span class="jcogs-img-pro-field-chip jcogs-img-pro-field-chip-info">' + escapeHtml(t('chip_preset', 'Preset')) + ': ' + escapeHtml(presetLabel) + '</span>');
                chips.push('<span class="jcogs-img-pro-field-chip ' + (hasCropDefined($root) ? 'jcogs-img-pro-field-chip-success' : 'jcogs-img-pro-field-chip-warn') + '">' + escapeHtml(t('chip_crop', 'Crop')) + ': ' + escapeHtml(hasCropDefined($root) ? t('chip_set', 'Set') : t('none', 'None')) + '</span>');
                chips.push('<span class="jcogs-img-pro-field-chip ' + (hasFocal ? 'jcogs-img-pro-field-chip-success' : 'jcogs-img-pro-field-chip-warn') + '">' + escapeHtml(t('chip_focal', 'Focal')) + ': ' + escapeHtml(hasFocal ? t('chip_set', 'Set') : t('none', 'None')) + '</span>');
                if (adTotal > 0) {
                    chips.push('<span class="jcogs-img-pro-field-chip ' + (adSet > 0 ? 'jcogs-img-pro-field-chip-info' : 'jcogs-img-pro-field-chip-warn') + '">' + escapeHtml(t('chip_art_direction', 'Art direction')) + ': ' + escapeHtml(String(adSet) + '/' + String(adTotal)) + '</span>');
                }
                if (aspect) {
                    chips.push('<span class="jcogs-img-pro-field-chip jcogs-img-pro-field-chip-neutral">' + escapeHtml(t('chip_aspect_ratio', 'Aspect ratio')) + ': ' + escapeHtml(aspect) + '</span>');
                }

                $chips.html(chips.join(''));
            }

            // EE's File field value can change formats (numeric id vs object/json) during initialisation.
            // Compare changes using a normalised numeric file_id to avoid false positives that clear overrides.
            function parseMainFileId(raw) {
                raw = (raw == null ? '' : String(raw)).trim();
                if (!raw) return 0;
                if (/^\d+$/.test(raw)) return parseInt(raw, 10) || 0;

                try {
                    if (raw.charAt(0) === '{') {
                        var obj = JSON.parse(raw);
                        if (obj && (obj.file_id != null || obj.fileId != null)) {
                            var n = parseInt(obj.file_id != null ? obj.file_id : obj.fileId, 10);
                            return isFinite(n) ? n : 0;
                        }
                    }
                } catch (e) {}

                var m = raw.match(/\{file:(\d+)/);
                if (m && m[1]) {
                    var n2 = parseInt(m[1], 10);
                    return isFinite(n2) ? n2 : 0;
                }

                return 0;
            }

            function getMainFileId($root) {
                return parseMainFileId(getMainFileValue($root));
            }

            (function bindSubmitSync(){
                if ($root.data('jcogs-img-pro-field-submit-sync-bound')) {
                    return;
                }
                $root.data('jcogs-img-pro-field-submit-sync-bound', 1);

                var $form = $root.closest('form');
                if (! $form.length) {
                    return;
                }

                $form.on('submit.jcogsImgProFieldSync', function(){
                    try { syncArtDirectionPickers($root); } catch (e1) {}
                    try { syncMainFileValue($root); } catch (e2) {}
                });
            })();

            function snapshotImageOverrides($root) {
                var get = function(sel) {
                    try { return ($root.find(sel).val() || '').toString(); } catch (e) { return ''; }
                };
                return {
                    file_value: getMainFileValue($root),
                    focal_x: get('input[name$="[focal_x]"]'),
                    focal_y: get('input[name$="[focal_y]"]'),
                    crop: get('input[name$="[crop]"]'),
                    crop_mode: get('select[name$="[crop_mode]"]'),
                    crop_focus_h: get('select[name$="[crop_focus_h]"]'),
                    crop_focus_v: get('select[name$="[crop_focus_v]"]'),
                    crop_offset_x: get('input[name$="[crop_offset_x]"]'),
                    crop_offset_y: get('input[name$="[crop_offset_y]"]'),
                    crop_smart_scaling: get('select[name$="[crop_smart_scaling]"]'),
                    width: get('input[name$="[width]"]'),
                    height: get('input[name$="[height]"]'),
                    aspect_ratio: get('input[name$="[aspect_ratio]"]'),
                    crop_rect_left: get('input[name$="[crop_rect_left]"]'),
                    crop_rect_top: get('input[name$="[crop_rect_top]"]'),
                    crop_rect_width: get('input[name$="[crop_rect_width]"]'),
                    crop_rect_height: get('input[name$="[crop_rect_height]"]')
                };
            }

            function restoreImageOverridesFromSnapshot($root, snap) {
                if (!snap) return;
                var set = function(sel, val) {
                    try { $root.find(sel).val(val == null ? '' : String(val)); } catch (e) {}
                };
                try {
                    getMainFileInput($root).val((snap.file_value || '').toString());
                } catch (e0) {}
                set('input[name$="[focal_x]"]', snap.focal_x);
                set('input[name$="[focal_y]"]', snap.focal_y);
                set('input[name$="[crop]"]', snap.crop);
                set('select[name$="[crop_mode]"]', snap.crop_mode);
                set('select[name$="[crop_focus_h]"]', snap.crop_focus_h);
                set('select[name$="[crop_focus_v]"]', snap.crop_focus_v);
                set('input[name$="[crop_offset_x]"]', snap.crop_offset_x);
                set('input[name$="[crop_offset_y]"]', snap.crop_offset_y);
                set('select[name$="[crop_smart_scaling]"]', snap.crop_smart_scaling);
                set('input[name$="[width]"]', snap.width);
                set('input[name$="[height]"]', snap.height);
                set('input[name$="[aspect_ratio]"]', snap.aspect_ratio);
                set('input[name$="[crop_rect_left]"]', snap.crop_rect_left);
                set('input[name$="[crop_rect_top]"]', snap.crop_rect_top);
                set('input[name$="[crop_rect_width]"]', snap.crop_rect_width);
                set('input[name$="[crop_rect_height]"]', snap.crop_rect_height);

                try { updateCropButtonLabel($root); } catch (e1) {}
                try { restoreCropOverlayWhenReady($root); } catch (e2) {}
                try { restoreFocalMarkerWhenReady($root); } catch (e3) {}
            }

            function getAdFilesByIndex($root) {
                var out = {};
                try {
                    $root.find('input[type="hidden"][data-jcogs-ad-storage="1"]').each(function(){
                        var $storage = $(this);
                        var idx = parseInt(($storage.closest('[data-ad-index]').data('ad-index') || $storage.data('ad-index') || '0'), 10) || 0;
                        if (idx <= 0) return;
                        var v = ($storage.val() || '').toString().trim();
                        if (v === '') {
                            // Fallback: try reading directly from the picker input in case sync hasn't run.
                            var pickerName = ($storage.data('picker-name') || '').toString();
                            if (pickerName) {
                                var esc = pickerName.replace(/(["\\])/g, '\\$1');
                                var $container = $storage.closest('.jcogs-img-pro-field-ad-picker');
                                var $candidates = $();
                                if ($container.length) {
                                    $candidates = $container.find('input[name="' + esc + '"] , input[name="' + esc + '[]"], input[name^="' + esc + '"]')
                                        .not('input[data-jcogs-ad-storage="1"]');
                                }
                                if ($container.length) {
                                    $candidates = $candidates.add($container.find('input.js-file-input'));
                                    $candidates = $candidates.add($container.find('[data-file-id], [data-id]'));
                                }
                                // Do not fall back to root-level inputs; they can reflect the main picker.
                                $candidates.each(function(){
                                    if (v !== '') return;
                                    var $el = $(this);
                                    var raw = ($el.val() || '').toString().trim();
                                    var $chosen = $el.siblings('.fields-upload-chosen');
                                    var ignoreStale = (raw === '' && $chosen.length && $chosen.hasClass('hidden'));
                                    if (!ignoreStale) {
                                        var dataId = ($el.data('file-id') || '').toString().trim();
                                        if (dataId && /^\d+$/.test(dataId)) {
                                            v = dataId;
                                            return;
                                        }
                                        var attrId = ($el.attr('data-id') || '').toString().trim();
                                        if (attrId && /^\d+$/.test(attrId)) {
                                            v = attrId;
                                            return;
                                        }
                                    }
                                    raw = raw;
                                    // Accept both plain IDs and JSON-ish values.
                                    var n = '';
                                    try {
                                        if (/^\d+$/.test(raw)) {
                                            n = raw;
                                        } else if (raw && raw.charAt(0) === '{') {
                                            var obj = JSON.parse(raw);
                                            if (obj && typeof obj === 'object' && obj.file_id != null) {
                                                var nn = parseInt(obj.file_id, 10);
                                                if (isFinite(nn) && nn > 0) n = String(nn);
                                            }
                                        }
                                    } catch (eJson2) {}
                                    if (!n) {
                                        var m = raw.match(/\bfile[_\s-]*id\b\D*(\d+)/i);
                                        if (m && m[1]) n = String(parseInt(m[1], 10));
                                    }
                                    if (!n) {
                                        var m2 = raw.match(/\{file:(\d+)(?::[^}]*)?\}/i);
                                        if (m2 && m2[1]) n = String(parseInt(m2[1], 10));
                                    }
                                    if (n) v = String(n);
                                });
                            }
                        }
                        if (v !== '') out[String(idx)] = v;
                    });
                } catch (e) {}
                return out;
            }

            function clampInt(v, min, max, defVal) {
                v = parseInt((v == null ? '' : String(v)).trim(), 10);
                if (!isFinite(v)) v = defVal;
                v = Math.max(min, Math.min(max, v));
                return v;
            }

            function normalizeQuality(q) {
                q = (q == null ? '' : String(q)).trim().toLowerCase();
                if (q !== 'fast' && q !== 'balanced' && q !== 'accurate') q = 'balanced';
                return q;
            }

            function getFaceDetectControlsMode($root) {
                var mode = ($root.data('face-detect-controls-mode') || '').toString().trim().toLowerCase();
                if (mode !== 'hidden' && mode !== 'advanced' && mode !== 'visible') mode = 'advanced';
                return mode;
            }

            function getFaceDetectDefaults($root) {
                var q = normalizeQuality(($root.data('face-detect-default-quality') || 'balanced'));
                var s = clampInt($root.data('face-detect-default-sensitivity'), 1, 9, 3);
                var m = clampInt($root.data('face-detect-default-margin'), 0, 500, 0);
                return { quality: q, sensitivity: s, margin: m };
            }

            function applyFaceDetectSettingsToUi($root, s) {
                if (!s) return;
                try { $root.find('.jcogs-img-pro-field-face-quality').val(normalizeQuality(s.quality)); } catch (e) {}
                try { $root.find('.jcogs-img-pro-field-face-sensitivity').val(clampInt(s.sensitivity, 1, 9, 3)); } catch (e2) {}
                try { $root.find('.jcogs-img-pro-field-face-margin').val(clampInt(s.margin, 0, 500, 0)); } catch (e3) {}
            }

            function getFaceDetectSettings($root) {
                var mode = getFaceDetectControlsMode($root);
                var defaults = getFaceDetectDefaults($root);
                if (mode === 'hidden') {
                    return defaults;
                }
                // Controls may still be absent (defensive).
                var $q = $root.find('.jcogs-img-pro-field-face-quality');
                var $s = $root.find('.jcogs-img-pro-field-face-sensitivity');
                var $m = $root.find('.jcogs-img-pro-field-face-margin');
                if (!$q.length || !$s.length || !$m.length) {
                    return defaults;
                }
                var quality = normalizeQuality($q.val());
                var sensitivity = clampInt($s.val(), 1, 9, 3);
                var margin = clampInt($m.val(), 0, 500, 0);
                return { quality: quality, sensitivity: sensitivity, margin: margin };
            }

            function faceSettingsStorageKey(fieldId) {
                return 'jcogs_img_pro_field_face_settings_' + String(fieldId || '0');
            }

            // Art direction picker sync:
            // - on change of any unique picker input
            // - just before the entry form is submitted
                $(document).on('change', 'input[name*="jcogs_img_pro_field_ad_"]', function(){
                    var $input = $(this);
                    var name = ($input.attr('name') || '').toString();
                    var match = name.match(/jcogs_img_pro_field_ad_(\d+)_(\d+)/);
                    var fid = resolvePickerFileIdFromInput($input);

                    var $r = $input.closest('[data-jcogs-img-pro-field="1"]');
                    if (!$r.length) {
                        $r = $input.closest('form');
                    }

                    if (match) {
                        var fieldId = match[1];
                        var idx = match[2];
                        var storageName = 'jcogs_img_pro_field[' + fieldId + '][art_direction_files][' + idx + ']';
                        var $storage = $r.find('input[type="hidden"][name="' + storageName.replace(/(["\\])/g, '\\$1') + '"]');
                        if (!$storage.length) {
                            $storage = $('input[type="hidden"][name="' + storageName.replace(/(["\\])/g, '\\$1') + '"]');
                        }
                        if ($storage.length) {
                            $storage.val(fid || '');
                        }
                    }

                    try { $r.find('input[type="hidden"][name$="[art_direction_dirty]"]').val('1'); } catch (eDirty) {}
                    if ($r.length) syncArtDirectionPickers($r);
                });

                $(document).on('change', '.jcogs-img-pro-field-ad-picker input.js-file-input', function(){
                    var $input = $(this);
                    var $picker = $input.closest('.jcogs-img-pro-field-ad-picker');
                    if ($picker.length) {
                        var $storage = $picker.find('input[type="hidden"][data-jcogs-ad-storage="1"]').first();
                        if ($storage.length) {
                            var fid = resolvePickerFileIdFromInput($input);
                            $storage.val(fid || '');
                        }
                    }

                    var $r = $input.closest('[data-jcogs-img-pro-field="1"]');
                    if (!$r.length) {
                        $r = $input.closest('form');
                    }
                    try { $r.find('input[type="hidden"][name$="[art_direction_dirty]"]').val('1'); } catch (eDirty) {}
                    if ($r.length) syncArtDirectionPickers($r);
                });

            // Guard: EE's file picker can occasionally clobber the main image input when selecting an art-direction alt.
            // Snapshot main state when interacting with an AD picker, then restore if main unexpectedly changes.

            function beginAdGuard($r) {
                try {
                    $r.data('jcogs-img-pro-field-ad-guard', 1);
                    $r.data('jcogs-img-pro-field-ad-guard-expiry', Date.now() + 60000);
                    $r.data('jcogs-img-pro-field-ad-main-snapshot', snapshotImageOverrides($r));

                    // Fail-safe: if the user opens the modal then cancels (no change event), clear the guard later.
                    setTimeout(function(){
                        try {
                            if (parseInt($r.data('jcogs-img-pro-field-ad-guard') || '0', 10) !== 1) return;
                            var exp = parseInt($r.data('jcogs-img-pro-field-ad-guard-expiry') || '0', 10) || 0;
                            if (exp > 0 && Date.now() > exp) {
                                $r.data('jcogs-img-pro-field-ad-guard', 0);
                                $r.removeData('jcogs-img-pro-field-ad-main-snapshot');
                            }
                        } catch (e0) {}
                    }, 65000);
                } catch (e) {}
            }

            function endAdGuard($r) {
                try {
                    $r.data('jcogs-img-pro-field-ad-guard', 0);
                    $r.removeData('jcogs-img-pro-field-ad-main-snapshot');
                } catch (e) {}
            }

            $root.on('mousedown', '.jcogs-img-pro-field-ad-picker', function(){
                beginAdGuard($root);
                try {
                    var $picker = $(this);
                    var $storage = $picker.find('input[type="hidden"][data-jcogs-ad-storage="1"]').first();
                    if ($storage.length) {
                        PickerState.activeAdStorage = $storage;
                        PickerState.activeAdStorageExpiry = Date.now() + 8000;
                    }
                } catch (e) {}
            });

            $root.on('mousedown', '.jcogs-img-pro-field-ad-picker .file-field-filepicker', function(){
                try {
                    var $button = $(this);
                    var $picker = $button.closest('.jcogs-img-pro-field-ad-picker');
                    if (!$picker.length) return;
                    PickerState.activeAdPicker = $picker;
                    PickerState.activeAdPickerExpiry = Date.now() + 8000;
                    PickerState.activeMainRoot = null;
                    PickerState.activeMainRootExpiry = 0;

                    var $input = $picker.find('input.js-file-input').first();
                    if (!$input.length) return;
                    var name = ($input.attr('name') || '').toString();
                    if (!name) return;
                    var safe = name.replace(/[\[\]']+/g, '_');
                    $button.attr('data-input-value', name);

                    var $img = $input.siblings('.fields-upload-chosen').find('img.js-file-image').first();
                    if ($img.length) {
                        if (!$img.attr('id')) {
                            $img.attr('id', safe);
                        }
                        $button.attr('data-input-image', $img.attr('id'));
                    } else {
                        $button.attr('data-input-image', safe);
                    }
                } catch (e) {}
            });

            $root.on('mousedown', '.jcogs-img-pro-field-main-picker .file-field-filepicker', function(){
                try {
                    var $button = $(this);
                    var $mainInput = getMainFileInput($root);
                    if ($mainInput && $mainInput.length) {
                        var inputName = ($mainInput.attr('name') || '').toString().trim();
                        if (inputName) {
                            var safe = inputName.replace(/[\[\]']+/g, '_');
                            var $mainImg = $mainInput.siblings('.fields-upload-chosen').find('img.js-file-image').first();
                            if (!$mainImg.length) {
                                $mainImg = $root.find('.jcogs-img-pro-field-main-picker img.js-file-image').first();
                            }
                            if ($mainImg.length) {
                                $mainImg.attr('id', safe);
                            }
                            $button.attr('data-input-value', inputName);
                            $button.attr('data-input-image', safe);
                        }
                    }

                    PickerState.activeMainRoot = $root;
                    PickerState.activeMainRootExpiry = Date.now() + 8000;
                    PickerState.activeAdPicker = null;
                    PickerState.activeAdPickerExpiry = 0;
                    PickerState.activeAdStorage = null;
                    PickerState.activeAdStorageExpiry = 0;
                } catch (e) {}
            });

            (function patchFilePickerCallback(){
                var attempts = 20;
                var tick = function(){
                    try {
                        if (!window.EE || !EE.FileField || typeof EE.FileField.pickerCallback !== 'function') {
                            throw new Error('missing');
                        }
                        if (EE.FileField.__jcogsImgProFieldAdPickerPatched) return;

                        var original = EE.FileField.pickerCallback;
                        EE.FileField.pickerCallback = function(data, references){
                            try {
                                if (PickerState.activeMainRoot && PickerState.activeMainRoot.length) {
                                    if (!PickerState.activeMainRootExpiry || Date.now() <= PickerState.activeMainRootExpiry) {
                                        var $mainRoot = PickerState.activeMainRoot;
                                        var $mainInput = getMainFileInput($mainRoot);
                                        if ($mainInput && $mainInput.length) {
                                            var $mainImg = $mainInput.siblings('.fields-upload-chosen').find('img.js-file-image').first();
                                            if (!$mainImg.length) {
                                                $mainImg = $mainRoot.find('.jcogs-img-pro-field-main-picker img.js-file-image').first();
                                            }
                                            var mainRefs = $.extend({}, references || {}, {
                                                input_value: $mainInput
                                            });
                                            if ($mainImg.length) {
                                                mainRefs.input_img = $mainImg;
                                            }
                                            return original.call(this, data, mainRefs);
                                        }
                                    }
                                }
                                if (PickerState.activeAdPicker && PickerState.activeAdPicker.length) {
                                    if (!PickerState.activeAdPickerExpiry || Date.now() <= PickerState.activeAdPickerExpiry) {
                                        var $input = PickerState.activeAdPicker.find('input.js-file-input').first();
                                        if ($input.length) {
                                            var $img = $input.siblings('.fields-upload-chosen').find('img.js-file-image').first();
                                            if (!$img.length) {
                                                $img = PickerState.activeAdPicker.find('img.js-file-image').first();
                                            }
                                            if ($img.length) {
                                                var refs = $.extend({}, references || {}, {
                                                    input_value: $input,
                                                    input_img: $img
                                                });
                                                return original.call(this, data, refs);
                                            }
                                        }
                                    }
                                }
                            } catch (e0) {}
                            return original.call(this, data, references);
                        };
                        EE.FileField.__jcogsImgProFieldAdPickerPatched = true;
                        return;
                    } catch (e) {}
                    attempts -= 1;
                    if (attempts <= 0) return;
                    setTimeout(tick, 200);
                };
                tick();
            })();

            $(document).on('filepicker:pick', function(evt){
                try {
                    var detail = (evt && evt.originalEvent && evt.originalEvent.detail) ? evt.originalEvent.detail : (evt && evt.detail ? evt.detail : null);
                    var fid = '';
                    if (detail && typeof detail === 'object' && detail.file_id != null) {
                        fid = resolveNumericFileIdFromString(String(detail.file_id));
                    } else if (detail) {
                        fid = resolveNumericFileIdFromString(String(detail));
                    }

                    if (!fid) return;

                    var $storage = null;
                    if (PickerState.activeAdStorage && PickerState.activeAdStorage.length) {
                        if (!PickerState.activeAdStorageExpiry || Date.now() <= PickerState.activeAdStorageExpiry) {
                            $storage = PickerState.activeAdStorage;
                        }
                    }

                    if (!$storage || !$storage.length) {
                        try {
                            if (window.globalDropzone) {
                                var $dz = $(window.globalDropzone);
                                if ($dz && $dz.length) {
                                    var $picker = $dz.closest('.jcogs-img-pro-field-ad-picker');
                                    if ($picker.length) {
                                        $storage = $picker.find('input[type="hidden"][data-jcogs-ad-storage="1"]').first();
                                    }
                                }
                            }
                        } catch (eG) {}
                    }

                    if ($storage && $storage.length) {
                        $storage.val(fid);
                        var $r = $storage.closest('[data-jcogs-img-pro-field="1"]');
                        if (!$r.length) {
                            $r = $storage.closest('form');
                        }
                        try { $r.find('input[type="hidden"][name$="[art_direction_dirty]"]').val('1'); } catch (eDirty) {}
                        if ($r.length) syncArtDirectionPickers($r);
                    } else {
                        try {
                            var $mainRoot = $();
                            try {
                                var $evtRoot = $(evt && evt.target ? evt.target : null).closest('[data-jcogs-img-pro-field="1"]');
                                if ($evtRoot.length) {
                                    $mainRoot = $evtRoot.first();
                                }
                            } catch (eEvtRoot) {}

                            if (PickerState.activeMainRoot && PickerState.activeMainRoot.length && (!PickerState.activeMainRootExpiry || Date.now() <= PickerState.activeMainRootExpiry)) {
                                $mainRoot = PickerState.activeMainRoot;
                            }

                            if ((!$mainRoot || !$mainRoot.length) && window.globalDropzone) {
                                try {
                                    var $dzMain = $(window.globalDropzone);
                                    if ($dzMain && $dzMain.length) {
                                        var $mainPicker = $dzMain.closest('.jcogs-img-pro-field-main-picker');
                                        if ($mainPicker.length) {
                                            $mainRoot = $mainPicker.closest('[data-jcogs-img-pro-field="1"]');
                                        }
                                        if ((!$mainRoot || !$mainRoot.length) && $dzMain.closest('[data-jcogs-img-pro-field="1"]').length) {
                                            $mainRoot = $dzMain.closest('[data-jcogs-img-pro-field="1"]');
                                        }
                                    }
                                } catch (eDzMain) {}
                            }

                            if (!$mainRoot || !$mainRoot.length) {
                                return;
                            }

                            var $mainInput = getMainFileInput($mainRoot);
                            if ($mainInput && $mainInput.length) {
                                $mainInput.val('{file:' + String(fid) + ':url}').attr('data-id', String(fid));
                            }
                            try { updateMainPickerVisuals($mainRoot, fid, detail); } catch (eMainVisual) {}
                            $mainRoot.find('input[name$="[file_value]"]').val(String(fid));
                            syncMainFileValue($mainRoot);
                            var prevId = parseInt($mainRoot.data('jcogs-img-pro-field-last-file-id') || '0', 10) || 0;
                            var nextId = parseInt(String(fid), 10) || 0;
                            if (nextId > 0 && prevId !== nextId) {
                                try { handleFileIdChanged($mainRoot, prevId, nextId); } catch (eMain3) {}
                            }
                            $mainRoot.data('jcogs-img-pro-field-last-file-id', nextId);
                            $mainRoot.data('jcogs-img-pro-field-last-file-value', getMainFileValue($mainRoot));
                            try { renderLiveSummaryChips($mainRoot); } catch (eMainSummary) {}
                            setTimeout(function(){
                                try { syncMainFileValue($mainRoot); } catch (eMain2) {}
                            }, 0);
                        } catch (eMain) {}
                    }
                } catch (e) {}
            });

            $(document).on('change', 'input[name*="jcogs_img_pro_field_ad_"]', function(){
                var $r = $(this).closest('[data-jcogs-img-pro-field="1"]');
                if (!$r.length) return;

                // Always sync AD values first.
                try { syncArtDirectionPickers($r); } catch (e0) {}

                // If the main input got modified by the AD picker interaction, restore it and re-apply overrides.
                try {
                    var snap = $r.data('jcogs-img-pro-field-ad-main-snapshot') || null;
                    var guard = parseInt($r.data('jcogs-img-pro-field-ad-guard') || '0', 10) === 1;
                    if (!guard || !snap) return;

                    var curMain = getMainFileValue($r);
                    var prevMain = (snap.file_value || '').toString();
                    if (prevMain && curMain && curMain !== prevMain) {
                        restoreImageOverridesFromSnapshot($r, snap);
                        // Ensure our main-file-change watcher doesn't immediately clear again.
                        $r.data('jcogs-img-pro-field-last-file-value', prevMain);
                        setStatus(t('ad_alt_selected_main_preserved', 'Alt image selected (main preserved)'), false);
                    }
                } catch (e1) {}

                // The modal interaction has completed (we got a change event); clear the guard.
                try { endAdGuard($r); } catch (e2) {}
            });

            $root.on('click', '.jcogs-img-pro-field-ad-picker .button.remove', function(){
                try {
                    var $picker = $(this).closest('.jcogs-img-pro-field-ad-picker');
                    if (!$picker.length) return;
                    var $input = $picker.find('input.js-file-input').first();
                    if ($input.length) {
                        $input.attr('data-id', '');
                        $input.removeData('file-id');
                    }
                    var $storage = $picker.find('input[type="hidden"][data-jcogs-ad-storage="1"]').first();
                    if ($storage.length) {
                        $storage.val('');
                    }
                    var $r = $picker.closest('[data-jcogs-img-pro-field="1"]');
                    if (!$r.length) {
                        $r = $picker.closest('form');
                    }
                    try { $r.find('input[type="hidden"][name$="[art_direction_dirty]"]').val('1'); } catch (eDirty) {}
                    if ($r.length) syncArtDirectionPickers($r);
                } catch (e) {}
            });

            $root.on('click', '.jcogs-img-pro-field-main-picker .button.remove', function(){
                try {
                    var $mainPicker = $(this).closest('.jcogs-img-pro-field-main-picker');
                    if (!$mainPicker.length) return;

                    var $mainInput = $mainPicker.find('input.js-file-input').first();
                    if ($mainInput.length) {
                        $mainInput.val('').attr('data-id', '');
                        $mainInput.removeData('file-id');
                    }

                    $mainPicker.find('.fields-upload-chosen-name').attr('data-id', '');

                    var $mainRoot = $mainPicker.closest('[data-jcogs-img-pro-field="1"]');
                    if (!$mainRoot.length) return;

                    $mainRoot.find('input[name$="[file_value]"]').val('');
                    $mainRoot.data('jcogs-img-pro-field-last-file-id', 0);
                    $mainRoot.data('jcogs-img-pro-field-last-file-value', '');
                    try { syncMainFileValue($mainRoot); } catch (eSyncMain) {}
                    try { renderLiveSummaryChips($mainRoot); } catch (eSummaryMain) {}
                } catch (e) {}
            });

            // Bind submit sync once per form+field.
            var $form = $root.closest('form');
            if ($form.length) {
                $form.off('submit.jcogsImgProFieldAdSync' + String(fieldId))
                    .on('submit.jcogsImgProFieldAdSync' + String(fieldId), function(){
                        syncCompositeContext($root);
                        syncArtDirectionPickers($root);
                    });
            }

            // Initial sync (important when loading a saved entry).
            syncArtDirectionPickers($root);
            syncCompositeContext($root);
            maybeResetClonedNewGridRow($root);
            applyDefaultPresetSelection($root);

            $root.on('focusin.jcogsImgProFieldContext', function(){
                syncCompositeContext($root);
            });

            if (hasModal) {
                $root.on('click', '.jcogs-img-pro-field-open-modal', function(e){
                    try { e.preventDefault(); } catch (e1) {}
                    syncCompositeContext($root);
                    setModalOpen($root, true);
                });

                $(window)
                    .off('resize.jcogsImgProFieldModalAlign' + String(fieldId))
                    .on('resize.jcogsImgProFieldModalAlign' + String(fieldId), function(){
                        try {
                            var $openModal = $root.find('.jcogs-img-pro-field-modal.is-open');
                            if (!$openModal.length) return;
                            alignModalToViewport($root);
                        } catch (eResize) {}
                    });

                $root.on('click', '.jcogs-img-pro-field-close-modal, [data-jcogs-modal-close="1"]', function(e){
                    try { e.preventDefault(); } catch (e1) {}
                    setModalOpen($root, false);
                    try { saveUsagePayload($root, { silent: true }); } catch (eSave) {}
                    try { updateValidationUi($root); } catch (eValClose) {}
                });

                $(document).on('keydown.jcogsImgProFieldModal' + String(fieldId), function(e){
                    try {
                        if (e.key !== 'Escape' && e.keyCode !== 27) return;
                        var $modal = $root.find('.jcogs-img-pro-field-modal.is-open');
                        if (!$modal.length) return;
                        setModalOpen($root, false);
                        try { saveUsagePayload($root, { silent: true }); } catch (eSave2) {}
                        try { updateValidationUi($root); } catch (eValEsc) {}
                    } catch (e2) {}
                });
            }

            function loadFaceDetectSettings($root, fieldId) {
                try {
                    // Always initialise UI with field defaults first.
                    applyFaceDetectSettingsToUi($root, getFaceDetectDefaults($root));

                    if (getFaceDetectControlsMode($root) === 'hidden') return;
                    if (!window.localStorage) return;
                    var raw = localStorage.getItem(faceSettingsStorageKey(fieldId));
                    if (!raw) return;
                    var obj = JSON.parse(raw);
                    if (!obj || typeof obj !== 'object') return;
                    applyFaceDetectSettingsToUi($root, obj);
                } catch (e) {}
            }

            function saveFaceDetectSettings($root, fieldId) {
                try {
                    if (getFaceDetectControlsMode($root) === 'hidden') return;
                    if (!window.localStorage) return;
                    var s = getFaceDetectSettings($root);
                    localStorage.setItem(faceSettingsStorageKey(fieldId), JSON.stringify(s));
                } catch (e) {}
            }

            function clearFaceDetectSettingsStorage(fieldId) {
                try {
                    if (!window.localStorage) return;
                    localStorage.removeItem(faceSettingsStorageKey(fieldId));
                } catch (e) {}
            }

            function isFaceDetectForceEnabled($root) {
                try {
                    var $cb = $root.find('.jcogs-img-pro-field-face-force');
                    if (!$cb.length) return false;
                    return !!$cb.is(':checked');
                } catch (e) {
                    return false;
                }
            }

            function syncManualVisibility() {
                var on = $root.find('.jcogs-img-pro-field-toggle-manual').is(':checked');
                $root.find('.jcogs-img-pro-field-manual').toggle(!!on);
            }

            function aspectRatioRawToUiValue(raw) {
                raw = (raw == null ? '' : String(raw)).trim();
                if (raw === '__inherit__') {
                    var requireAspect = String($root.data('require-aspect-ratio') || '0') === '1';
                    if (requireAspect) {
                        var d = String($root.data('default-aspect-ratio') || '').trim();
                        return d || '';
                    }
                    return '';
                }
                return raw;
            }

            function setAspectRatioHiddenFromUiValue(uiValue) {
                uiValue = (uiValue == null ? '' : String(uiValue)).trim();
                var hasDefault = String($root.data('has-default-aspect-ratio') || '0') === '1';
                var requireAspect = String($root.data('require-aspect-ratio') || '0') === '1';
                var defaultAspect = String($root.data('default-aspect-ratio') || '').trim();
                var $hidden = $root.find('input[name$="[aspect_ratio]"]');
                if (!$hidden.length) return;

                if (uiValue === '') {
                    if (requireAspect) {
                        var fallback = defaultAspect;
                        if (!fallback) {
                            var $select = $root.find('.jcogs-img-pro-field-aspect-ratio-select');
                            if ($select.length) {
                                $select.find('option').each(function(){
                                    var v = ($(this).attr('value') || '').toString().trim();
                                    if (v) { fallback = v; return false; }
                                });
                            }
                        }
                        if (fallback) {
                            $hidden.val(fallback);
                            return;
                        }
                    }
                    // Only store an explicit override if a developer default exists.
                    $hidden.val(hasDefault ? '__inherit__' : '');
                    return;
                }

                $hidden.val(uiValue);
                try { updateValidationUi($root); } catch (e) {}
            }

            function clearCropForAspectChange($root) {
                var $btn = $root.find('.jcogs-img-pro-field-clear-rect').first();
                if ($btn.length) {
                    $btn.trigger('click');
                    return;
                }
                try {
                    $root.find('input[name$="[crop]"]').val('');
                    $root.find('select[name$="[crop_mode]"]').val('');
                    $root.find('select[name$="[crop_focus_h]"]').val('');
                    $root.find('select[name$="[crop_focus_v]"]').val('');
                    $root.find('input[name$="[crop_offset_x]"]').val('');
                    $root.find('input[name$="[crop_offset_y]"]').val('');
                    $root.find('select[name$="[crop_smart_scaling]"]').val('');
                    $root.find('input[name$="[width]"]').val('');
                    $root.find('input[name$="[height]"]').val('');
                    $root.find('input[name$="[crop_rect_left]"]').val('');
                    $root.find('input[name$="[crop_rect_top]"]').val('');
                    $root.find('input[name$="[crop_rect_width]"]').val('');
                    $root.find('input[name$="[crop_rect_height]"]').val('');
                    $root.find('input[name$="[crop_present]"]').val('');
                    $root.find('.jcogs-img-pro-field-rect').hide();
                    updateCropButtonLabel($root);
                    setStatus(t('crop_offsets_cleared', 'Crop cleared'), false);
                } catch (e) {}
            }

            try {
                var $select = $root.find('.jcogs-img-pro-field-aspect-ratio-select');
                if ($select.length) {
                    $select.off('change.jcogsImgProFieldAspect').on('change.jcogsImgProFieldAspect', function(){
                        var uiValue = ($(this).val() || '').toString().trim();
                        setAspectRatioHiddenFromUiValue(uiValue);
                        try { clearCropForAspectChange($root); } catch (eClear) {}
                    });
                }

                var $manual = $root.find('.jcogs-img-pro-field-aspect-ratio-manual');
                if ($manual.length) {
                    $manual.off('input.jcogsImgProFieldAspect').on('input.jcogsImgProFieldAspect', function(){
                        var uiValue = ($(this).val() || '').toString().trim();
                        setAspectRatioHiddenFromUiValue(uiValue);
                    });
                }

                $root.on('change input', '.jcogs-img-pro-field-face-quality, .jcogs-img-pro-field-face-sensitivity, .jcogs-img-pro-field-face-margin', function(){
                    saveFaceDetectSettings($root, fieldId);
                });
            } catch (e) {}

            // If face-detect controls are visible, keep the panel visible even before detection.
            try {
                if (getFaceDetectControlsMode($root) !== 'hidden') {
                    $root.find('.jcogs-img-pro-field-face-detect-ui').show();
                    // Disable apply buttons until we have a result.
                    $root.find('.jcogs-img-pro-field-face-apply-focal, .jcogs-img-pro-field-face-apply-crop').prop('disabled', true);
                }
            } catch (e) {}

            $root.on('click', '.jcogs-img-pro-field-face-restore-defaults', function(){
                try {
                    clearFaceDetectSettingsStorage(fieldId);
                    applyFaceDetectSettingsToUi($root, getFaceDetectDefaults($root));
                    saveFaceDetectSettings($root, fieldId);
                    setStatus(t('face_settings_restored', 'Face detection settings restored'), false);
                } catch (e) {}
            });

            $root.on('click', '.jcogs-img-pro-field-load', function(){
                if (!canUseUsageAction) {
                    setStatus(t('save_entry_first', 'Save the entry first to load saved adjustments'), true);
                    return;
                }
                setStatus(t('loading', 'Loading…'), false);
                var ctx = getContextPayload($root);
                $.post(actUrl, {
                    op: 'get',
                    token: token,
                    entry_id: entryId,
                    field_id: fieldId,
                    content_type: ctx.content_type,
                    container_id: ctx.container_id,
                    row_id: ctx.row_id,
                    fluid_field_data_id: ctx.fluid_field_data_id,
                    block_id: ctx.block_id
                }).done(function(resp){
                    if (resp && resp.error) {
                        setStatus(localizeErrorMessage(resp.error), true);
                        return;
                    }
                    if (!resp || resp.success !== true) {
                        setStatus(t('load_failed', 'Load failed'), true);
                        return;
                    }

                    var payload = resp.usage_payload || {};
                    getMainFileInput($root).val(resp.file_id || '');

                    // New file may change face detection context.
                    try { clearFaceOverlay($root); } catch (e) {}
                    $root.find('select[name$="[preset_id]"]').val((payload.preset_id != null && parseInt(payload.preset_id, 10) > 0) ? payload.preset_id : '');
                    $root.find('input[name$="[focal_x]"]').val(payload.focal_x != null ? payload.focal_x : '');
                    $root.find('input[name$="[focal_y]"]').val(payload.focal_y != null ? payload.focal_y : '');

                    $root.find('input[name$="[crop]"]').val(payload.crop != null ? payload.crop : '');
                    $root.find('select[name$="[crop_mode]"]').val(payload.crop_mode != null ? payload.crop_mode : '');
                    $root.find('select[name$="[crop_focus_h]"]').val(payload.crop_focus_h != null ? payload.crop_focus_h : '');
                    $root.find('select[name$="[crop_focus_v]"]').val(payload.crop_focus_v != null ? payload.crop_focus_v : '');
                    $root.find('input[name$="[crop_offset_x]"]').val(payload.crop_offset_x != null ? payload.crop_offset_x : '');
                    $root.find('input[name$="[crop_offset_y]"]').val(payload.crop_offset_y != null ? payload.crop_offset_y : '');
                    $root.find('select[name$="[crop_smart_scaling]"]').val(normalizeYesNo(payload.crop_smart_scaling != null ? payload.crop_smart_scaling : ''));

                    // Structured rect for perfect restoration.
                    var rect = (payload && payload.crop_rect) ? payload.crop_rect : null;
                    $root.find('input[name$="[crop_rect_left]"]').val(rect && rect.left != null ? rect.left : '');
                    $root.find('input[name$="[crop_rect_top]"]').val(rect && rect.top != null ? rect.top : '');
                    $root.find('input[name$="[crop_rect_width]"]').val(rect && rect.width != null ? rect.width : '');
                    $root.find('input[name$="[crop_rect_height]"]').val(rect && rect.height != null ? rect.height : '');

                    $root.find('input[name$="[width]"]').val(payload.width != null ? payload.width : '');
                    $root.find('input[name$="[height]"]').val(payload.height != null ? payload.height : '');
                    $root.find('input[name$="[aspect_ratio]"]').val(payload.aspect_ratio != null ? payload.aspect_ratio : '');

                    // Populate AD storage inputs from payload (media-keyed), so subsequent saves don't wipe them.
                    try {
                        var idxToMedia = {};
                        var raw = ($root.find('input.jcogs-img-pro-field-ad-index-to-media').val() || '').toString();
                        if (raw) { idxToMedia = JSON.parse(raw) || {}; }
                        var ad = (payload && payload.art_direction) ? payload.art_direction : null;
                        var files = (ad && ad.files) ? ad.files : null;
                        if (files && typeof files === 'object') {
                            $root.find('input[type="hidden"][data-jcogs-ad-storage="1"]').each(function(){
                                var $storage = $(this);
                                var idx = parseInt(($storage.closest('[data-ad-index]').data('ad-index') || '0'), 10) || 0;
                                var media = (idxToMedia && idxToMedia[String(idx)]) ? String(idxToMedia[String(idx)]) : '';
                                var fid = (media && files[media] != null) ? String(files[media]) : '';
                                $storage.val(fid);
                                // Also set the unique picker input; UI may not refresh, but value is correct.
                                var pickerName = ($storage.data('picker-name') || '').toString();
                                if (pickerName) {
                                    var $picker = $root.find('input[name="' + pickerName.replace(/(["\\])/g, '\\$1') + '"]');
                                    if ($picker.length) {
                                        $picker.val(fid);
                                        try { $picker.trigger('change'); } catch (e2) {}
                                    }
                                }
                            });
                        }
                    } catch (eAd) {}

                    try { updateCropButtonLabel($root); } catch (e) {}
                    try { syncAspectRatioUi(); } catch (e) {}
                    try { applyDefaultPresetSelection($root); } catch (ePresetDefault) {}
                    try { renderLiveSummaryChips($root); } catch (eSummaryLoad) {}
                    setStatus(t('loaded', 'Loaded'), false);
                }).fail(function(xhr){
                    var msg = t('load_failed', 'Load failed');
                    if (xhr && xhr.responseText) {
                        msg = (xhr.responseText || '').toString().slice(0, 120);
                    }
                    setStatus(msg, true);
                });
            });

            function saveUsagePayload($root, opts) {
                opts = opts || {};
                if (!opts.silent) {
                    setStatus(t('saving', 'Saving…'), false);
                }

                // Ensure context + AD values are present before saving.
                try { syncCompositeContext($root); } catch (eSync0) {}
                try { syncArtDirectionPickers($root); } catch (eSync1) {}

                if (!canUseUsageAction) {
                    if (!opts.silent) {
                        setStatus(t('save_entry_first', 'Save the entry first to persist adjustments'), false);
                    }
                    return;
                }

                var fileValue = getMainFileValue($root);
                if (!fileValue) {
                    fileValue = ($root.find('input[name$="[file_value]"]').val() || '').toString().trim();
                }
                var defaultPresetId = ($root.data('default-preset-id') || '').toString().trim();
                var $presetSelect = $root.find('select[name$="[preset_id]"]');
                var presetId = ($presetSelect.val() || '').toString().trim();
                if ($presetSelect.length) {
                    // Selector shown: empty means explicit "None".
                    if (presetId === '') {
                        presetId = '0';
                    }
                } else if (!presetId && defaultPresetId) {
                    presetId = defaultPresetId;
                }
                var focalX = ($root.find('input[name$="[focal_x]"]').val() || '').trim();
                var focalY = ($root.find('input[name$="[focal_y]"]').val() || '').trim();
                var crop = ($root.find('input[name$="[crop]"]').val() || '').toString().trim();
                var cropMode = ($root.find('select[name$="[crop_mode]"]').val() || '').toString().trim();
                var cropFocusH = ($root.find('select[name$="[crop_focus_h]"]').val() || '').toString().trim();
                var cropFocusV = ($root.find('select[name$="[crop_focus_v]"]').val() || '').toString().trim();
                var cropOffsetX = ($root.find('input[name$="[crop_offset_x]"]').val() || '').toString().trim();
                var cropOffsetY = ($root.find('input[name$="[crop_offset_y]"]').val() || '').toString().trim();
                var cropSmartScaling = ($root.find('select[name$="[crop_smart_scaling]"]').val() || '').toString().trim();
                var width = ($root.find('input[name$="[width]"]').val() || '').toString().trim();
                var height = ($root.find('input[name$="[height]"]').val() || '').toString().trim();
                var aspectRatio = ($root.find('input[name$="[aspect_ratio]"]').val() || '').toString().trim();
                var rectLeft = ($root.find('input[name$="[crop_rect_left]"]').val() || '').toString().trim();
                var rectTop = ($root.find('input[name$="[crop_rect_top]"]').val() || '').toString().trim();
                var rectWidth = ($root.find('input[name$="[crop_rect_width]"]').val() || '').toString().trim();
                var rectHeight = ($root.find('input[name$="[crop_rect_height]"]').val() || '').toString().trim();

                // If a preset is selected, do NOT persist rectangle-derived width/height overrides.
                // Presets often define sizing (e.g. max_width or width); sending width/height here
                // causes the preview/output to appear double-cropped (typically to the central 50%).
                var hasPreset = (presetId !== '' && presetId !== '0');
                var hasRect = (rectWidth !== '' && rectHeight !== '');
                if (hasPreset && hasRect) {
                    width = '';
                    height = '';
                }

                var ctx = getContextPayload($root);
                var adFilesByIndex = getAdFilesByIndex($root);
                var adIdxToMediaRaw = '';
                try {
                    adIdxToMediaRaw = ($root.find('input.jcogs-img-pro-field-ad-index-to-media').val() || '').toString();
                } catch (eMap) {}

                return $.post(actUrl, {
                    op: 'set',
                    token: token,
                    entry_id: entryId,
                    field_id: fieldId,
                    content_type: ctx.content_type,
                    container_id: ctx.container_id,
                    row_id: ctx.row_id,
                    fluid_field_data_id: ctx.fluid_field_data_id,
                    block_id: ctx.block_id,
                    file_value: fileValue,
                    preset_id: presetId,
                    focal_x: focalX,
                    focal_y: focalY,
                    crop: crop,
                    crop_mode: cropMode,
                    crop_focus_h: cropFocusH,
                    crop_focus_v: cropFocusV,
                    crop_offset_x: cropOffsetX,
                    crop_offset_y: cropOffsetY,
                    crop_smart_scaling: cropSmartScaling,
                    width: width,
                    height: height,
                    aspect_ratio: aspectRatio,
                    crop_rect_left: rectLeft,
                    crop_rect_top: rectTop,
                    crop_rect_width: rectWidth,
                    crop_rect_height: rectHeight,
                    art_direction_files_present: 1,
                    art_direction_index_to_media: adIdxToMediaRaw,
                    art_direction_files: adFilesByIndex
                }).done(function(resp){
                    if (typeof resp === 'string') {
                        try { resp = JSON.parse(resp); }
                        catch (e) { resp = { error: (resp || '').toString().slice(0, 120) || 'unexpected_response' }; }
                    }
                    if (resp && resp.error) {
                        setStatus(localizeErrorMessage(resp.error), true);
                        return;
                    }
                    if (!opts.silent) {
                        setStatus(t('saved', 'Saved'), false);
                    }
                    try { updateValidationUi($root); } catch (eValSaved) {}
                }).fail(function(xhr){
                    var msg = t('save_failed', 'Save failed');
                    if (xhr && xhr.responseText) {
                        msg = (xhr.responseText || '').toString().slice(0, 120);
                    }
                    setStatus(msg, true);
                });
            }

            $root.on('click', '.jcogs-img-pro-field-save', function(){
                saveUsagePayload($root, { silent: false });
            });

            // Normal publish save: sync AD picker selections into structured hidden inputs
            // so the fieldtype post_save() can persist them.
            try {
                var $form = $root.closest('form');
                if ($form.length) {
                    $form.off('submit.jcogsImgProFieldAdSync').on('submit.jcogsImgProFieldAdSync', function(){
                        try { syncArtDirectionPickers($root); } catch (eSubmitSync) {}
                        return true;
                    });
                }
            } catch (eForm) {}

            try {
                var $form2 = $root.closest('form');
                if ($form2.length) {
                    $form2.off('submit.jcogsImgProFieldCropSync' + String(fieldId))
                        .on('submit.jcogsImgProFieldCropSync' + String(fieldId), function(){
                            try { syncCropFromUi($root); } catch (eSyncCrop) {}
                            return true;
                        });
                }
            } catch (eForm2) {}

            $root.on('click', '.jcogs-img-pro-field-preview', function(){
                if (!previewActUrl) {
                    setStatus(t('preview_action_missing', 'Preview action unavailable'), true);
                    return;
                }

                setStatus(t('preview_rendering', 'Rendering preview…'), false);
                var fileValue = getMainFileValue($root);
                if (!fileValue) {
                    fileValue = ($root.find('input[name$="[file_value]"]').val() || '').toString().trim();
                }
                var defaultPresetId = ($root.data('default-preset-id') || '').toString().trim();
                var $presetSelect = $root.find('select[name$="[preset_id]"]');
                var presetId = ($presetSelect.val() || '').toString().trim();
                if ($presetSelect.length) {
                    if (presetId === '') {
                        presetId = '0';
                    }
                } else if (!presetId && defaultPresetId) {
                    presetId = defaultPresetId;
                }
                var focalX = ($root.find('input[name$="[focal_x]"]').val() || '').trim();
                var focalY = ($root.find('input[name$="[focal_y]"]').val() || '').trim();
                var crop = ($root.find('input[name$="[crop]"]').val() || '').toString().trim();
                var cropMode = ($root.find('select[name$="[crop_mode]"]').val() || '').toString().trim();
                var cropFocusH = ($root.find('select[name$="[crop_focus_h]"]').val() || '').toString().trim();
                var cropFocusV = ($root.find('select[name$="[crop_focus_v]"]').val() || '').toString().trim();
                var cropOffsetX = ($root.find('input[name$="[crop_offset_x]"]').val() || '').toString().trim();
                var cropOffsetY = ($root.find('input[name$="[crop_offset_y]"]').val() || '').toString().trim();
                var cropSmartScaling = ($root.find('select[name$="[crop_smart_scaling]"]').val() || '').toString().trim();
                var width = ($root.find('input[name$="[width]"]').val() || '').toString().trim();
                var height = ($root.find('input[name$="[height]"]').val() || '').toString().trim();
                var aspectRatio = ($root.find('input[name$="[aspect_ratio]"]').val() || '').toString().trim();
                var rectLeft = ($root.find('input[name$="[crop_rect_left]"]').val() || '').toString().trim();
                var rectTop = ($root.find('input[name$="[crop_rect_top]"]').val() || '').toString().trim();
                var rectWidth = ($root.find('input[name$="[crop_rect_width]"]').val() || '').toString().trim();
                var rectHeight = ($root.find('input[name$="[crop_rect_height]"]').val() || '').toString().trim();

                // Same rule for preview: allow the preset to control sizing.
                var hasPreset = (presetId !== '' && presetId !== '0');
                var hasRect = (rectWidth !== '' && rectHeight !== '');
                if (hasPreset && hasRect) {
                    width = '';
                    height = '';
                }

                var $wrap = $root.find('.jcogs-img-pro-field-preview-wrap');
                var $body = $root.find('.jcogs-img-pro-field-preview-body');
                $wrap.show();
                $body.empty();
                $body.append('<div class="jcogs-img-pro-field-preview-loading" style="opacity:.8; font-size:12px;">Loading preview…</div>');

                var ctx = getContextPayload($root);
                $.post(previewActUrl, {
                    token: token,
                    field_id: fieldId,
                    entry_id: entryId,
                    content_type: ctx.content_type,
                    container_id: ctx.container_id,
                    row_id: ctx.row_id,
                    fluid_field_data_id: ctx.fluid_field_data_id,
                    block_id: ctx.block_id,
                    file_value: fileValue,
                    preset_id: presetId,
                    default_preset_id: defaultPresetId,
                    focal_x: focalX,
                    focal_y: focalY,
                    crop: crop,
                    crop_mode: cropMode,
                    crop_focus_h: cropFocusH,
                    crop_focus_v: cropFocusV,
                    crop_offset_x: cropOffsetX,
                    crop_offset_y: cropOffsetY,
                    crop_smart_scaling: cropSmartScaling,
                    width: width,
                    height: height,
                    aspect_ratio: aspectRatio,
                    crop_rect_left: rectLeft,
                    crop_rect_top: rectTop,
                    crop_rect_width: rectWidth,
                    crop_rect_height: rectHeight
                }).done(function(resp){
                    if (typeof resp === 'string') {
                        try { resp = JSON.parse(resp); }
                        catch (e) { resp = { error: (resp || '').toString().slice(0, 120) || 'unexpected_response' }; }
                    }
                    if (resp && resp.error) {
                        var errText = localizeErrorMessage(resp.error);
                        setStatus(errText, true);
                        try {
                            $body.empty();
                            $body.append('<div style="opacity:.8; font-size:12px; color:#b71c1c;">' + String(errText).replace(/</g, '&lt;') + '</div>');
                        } catch (e) {}
                        return;
                    }
                    if (!resp || resp.success !== true) {
                        setStatus(t('preview_failed', 'Preview failed'), true);
                        try {
                            $body.empty();
                            $body.append('<div style="opacity:.8; font-size:12px; color:#b71c1c;">' + t('preview_failed', 'Preview failed').replace(/</g, '&lt;') + '</div>');
                        } catch (e) {}
                        return;
                    }

                    // Clear loading placeholder before rendering content.
                    $body.empty();

                    var fileUrl = resp.file_url || '';
                    var thumbUrl = resp.thumb_url || '';
                    var derivedUrl = resp.derived_url || '';
                    var derivedParams = resp.derived_params || {};
                    var debug = resp.debug || null;

                    // Layout: two panes (Original -> Preview) in a row on desktop, stacked on smaller screens.
                    var $row = $('<div class="jcogs-img-pro-field-preview-row"></div>');
                    var $originalPane = $('<div class="jcogs-img-pro-field-preview-pane jcogs-img-pro-field-preview-pane-original"></div>');
                    var $derivedPane = $('<div class="jcogs-img-pro-field-preview-pane jcogs-img-pro-field-preview-pane-derived"></div>');
                    $originalPane.append('<div class="field-instruct">' + t('original_for_crop_picking', 'Original (for crop picking)').replace(/</g, '&lt;') + '</div>');
                    $derivedPane.append('<div class="field-instruct">' + t('preview', 'Preview').replace(/</g, '&lt;') + '</div>');
                    $row.append($originalPane);
                    $row.append($derivedPane);
                    $body.append($row);

                    if (derivedUrl) {
                        $derivedPane.append('<div><img class="jcogs-img-pro-field-derived-img" src="' + String(derivedUrl).replace(/"/g, '&quot;') + '" /></div>');
                        $derivedPane.append('<div style="margin-top:6px;"><a href="' + String(derivedUrl).replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer">' + t('open_derived', 'Open derived').replace(/</g, '&lt;') + '</a></div>');
                    }

                    if (!derivedUrl && thumbUrl) {
                        $derivedPane.append('<div><img src="' + String(thumbUrl).replace(/"/g, '&quot;') + '" /></div>');
                    }

                    if (fileUrl) {
                        $originalPane.append('<div class="jcogs-img-pro-field-crop-picker" style="position:relative;">'
                            + '<img class="jcogs-img-pro-field-original-img" src="' + String(fileUrl).replace(/"/g, '&quot;') + '" />'
                            + '</div>');
                    }

                    // If a crop is already defined, keep the rectangle visible after reloads.
                    try { restoreCropOverlayWhenReady($root); } catch (e) {}

                    // If a focal point is set, keep the marker visible after reloads.
                    try { restoreFocalMarkerWhenReady($root); } catch (e) {}

                    // If face detection was previously run, re-render overlay after reloads.
                    try { restoreFaceOverlayWhenReady($root); } catch (e) {}

                    if (fileUrl) {
                        $originalPane.append('<div style="margin-top:6px;"><a href="' + String(fileUrl).replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer">' + t('open_original', 'Open original').replace(/</g, '&lt;') + '</a></div>');
                    }

                    if (!derivedUrl && resp.derived_error) {
                        var extra = '';
                        try {
                            if (String(resp.derived_error) === 'img_pro_not_compatible') {
                                var req = (resp.derived_required_version || '').toString();
                                var inst = (resp.derived_installed_version || '').toString();
                                if (req || inst) {
                                    extra = ' (requires ' + req + ', installed ' + inst + ')';
                                }
                            }
                        } catch (eCompat) {}

                        $body.append('<div style="margin-top:6px; opacity:.8; font-size:12px;">'
                            + t('derived_preview_unavailable', 'Derived preview unavailable:').replace(/</g, '&lt;')
                            + ' ' + String(resp.derived_error).replace(/</g, '&lt;')
                            + String(extra).replace(/</g, '&lt;')
                            + '</div>');
                    }

                    // Optional debug block (superadmins by default).
                    try {
                        var isSuper = String($root.data('is-superadmin') || '0') === '1';
                        if (debug && isSuper) {
                            var safe = function(v){ return String(v == null ? '' : v).replace(/</g, '&lt;'); };
                            var prettyParams = '';
                            try { prettyParams = JSON.stringify(derivedParams || {}, null, 2); } catch (e) { prettyParams = ''; }

                            var html = '';
                            html += '<details style="margin-top:10px;">';
                            html += '<summary style="cursor:pointer;">' + t('debug_preview', 'Debug (preview)').replace(/</g, '&lt;') + '</summary>';
                            html += '<div style="margin-top:6px; font-family: ui-monospace, Menlo, Monaco, monospace; font-size: 12px;">';
                            if (derivedUrl) {
                                html += '<div><strong>Derived URL</strong>: <a href="' + String(derivedUrl).replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer">open</a></div>';
                            }
                            html += '<div><strong>effective_crop</strong>: ' + safe(debug.effective_crop) + '</div>';
                            html += '<div><strong>effective_width</strong>: ' + safe(debug.effective_width) + '</div>';
                            html += '<div><strong>effective_height</strong>: ' + safe(debug.effective_height) + '</div>';
                            html += '<div><strong>effective_aspect_ratio</strong>: ' + safe(debug.effective_aspect_ratio) + '</div>';
                            html += '<div><strong>img_pro_action_id</strong>: ' + safe(debug.img_pro_action_id) + '</div>';
                            if (prettyParams) {
                                html += '<div style="margin-top:6px;"><strong>derived_params</strong>:</div>';
                                html += '<pre style="margin-top:4px; padding:8px; background:#fafafa; border:1px solid #e0e0e0; overflow:auto; max-height: 240px;">' + safe(prettyParams) + '</pre>';
                            }
                            html += '</div>';
                            html += '</details>';
                            $body.append(html);
                        }
                    } catch (e) {}

                    $wrap.show();
                    setStatus('', false);

                    // Mark as loaded so auto-load doesn't re-trigger repeatedly.
                    try { $root.data('jcogs-img-pro-field-preview-autoloaded', 1); } catch (e) {}

                    try { updateCropButtonLabel($root); } catch (e) {}

                    // Store derived params on root for crop picker ratio detection.
                    try {
                        $root.data('jcogs-img-pro-field-derived-params', derivedParams || {});
                    } catch (e) {}


                    // If the user clicked "Edit crop" before the preview existed, continue into crop editing now.
                    try {
                        if (parseInt($root.data('jcogs-img-pro-field-open-crop-after-preview') || '0', 10) === 1) {
                            $root.data('jcogs-img-pro-field-open-crop-after-preview', 0);
                            setTimeout(function(){
                                $root.find('.jcogs-img-pro-field-pick-rect').trigger('click', ['__from_preview__']);
                            }, 0);
                        }
                    } catch (e) {}

                    // If the user clicked "Pick focal" before the preview existed, continue into focal pick mode now.
                    try {
                        if (parseInt($root.data('jcogs-img-pro-field-open-focal-after-preview') || '0', 10) === 1) {
                            $root.data('jcogs-img-pro-field-open-focal-after-preview', 0);
                            setTimeout(function(){
                                $root.find('.jcogs-img-pro-field-pick-focal').trigger('click', ['__from_preview__']);
                            }, 0);
                        }
                    } catch (e) {}
                }).fail(function(xhr){
                    var msg = 'Preview failed';
                    if (xhr && xhr.responseText) {
                        msg = (xhr.responseText || '').toString().slice(0, 120);
                    }
                    setStatus(msg, true);
                    try {
                        $body.empty();
                        $body.append('<div style="opacity:.8; font-size:12px; color:#b71c1c;">' + String(msg).replace(/</g, '&lt;') + '</div>');
                    } catch (e) {}
                });
            });

            function maybeAutoLoadPreview($root) {
                try {
                    if (!previewActUrl) return;
                    if (parseInt($root.data('jcogs-img-pro-field-preview-autoloaded') || '0', 10) === 1) return;

                    // Only auto-load if the preview pane exists and isn't already populated.
                    var $body = $root.find('.jcogs-img-pro-field-preview-body');
                    if (!$body.length) return;
                    if ($body.find('img').length) {
                        $root.data('jcogs-img-pro-field-preview-autoloaded', 1);
                        return;
                    }

                    // Trigger the "Reload preview" button when available.
                    triggerPreviewReload($root);
                } catch (e) {}
            }

            // Auto-load when the adjustments panel is opened.
            try {
                var $details = $root.find('details.jcogs-img-pro-field-options').first();
                if ($details.length && $details[0] && $details[0].addEventListener) {
                    $details[0].addEventListener('toggle', function(){
                        if ($details.prop('open')) {
                            maybeAutoLoadPreview($root);
                        }
                    });
                }
            } catch (e) {}

            // If the panel is already open on load (because overrides exist), auto-load immediately.
            try {
                var $details2 = $root.find('details.jcogs-img-pro-field-options').first();
                if ($details2.length && $details2.prop('open')) {
                    setTimeout(function(){ maybeAutoLoadPreview($root); }, 0);
                }
            } catch (e) {}

            function parseAspectRatioToFloat(input) {
                if (!input) return null;
                input = String(input).trim();
                if (!input) return null;
                // Support "16_9", "16:9", "16/9", "1.777".
                var m = input.match(/^\s*(\d+(?:\.\d+)?)\s*[_:\/\-]\s*(\d+(?:\.\d+)?)\s*$/);
                if (m) {
                    var w = parseFloat(m[1]);
                    var h = parseFloat(m[2]);
                    if (w > 0 && h > 0) return w / h;
                }
                var f = parseFloat(input);
                return (isFinite(f) && f > 0) ? f : null;
            }

            function getLockedAspectRatio($root) {
                // Prefer the explicit input, then derived params. If neither is set, allow free-resize.
                var fromInput = ($root.find('input[name$="[aspect_ratio]"]').val() || '').toString();
                var ratio = parseAspectRatioToFloat(fromInput);
                if (ratio) return ratio;

                // If the editor is inheriting, honour the developer default aspect ratio.
                var hasDefault = String($root.data('has-default-aspect-ratio') || '0') === '1';
                if (hasDefault) {
                    var defRaw = ($root.data('default-aspect-ratio') || '').toString();
                    ratio = parseAspectRatioToFloat(defRaw);
                    if (ratio) return ratio;
                }

                var derivedParams = $root.data('jcogs-img-pro-field-derived-params') || {};
                if (derivedParams && derivedParams.aspect_ratio) {
                    ratio = parseAspectRatioToFloat(derivedParams.aspect_ratio);
                    if (ratio) return ratio;
                }

                return null;
            }

            function applyAspectRatioToCropUi($root) {
                var ratio = getLockedAspectRatio($root);
                if (!ratio || ratio <= 0) return;

                var $picker = $root.find('.jcogs-img-pro-field-crop-picker');
                if (!$picker.length) return;

                var pw = $picker.width() || 0;
                var ph = $picker.height() || 0;
                if (pw <= 0 || ph <= 0) return;

                var $rect = $picker.find('.jcogs-img-pro-field-rect');
                if (!$rect.length || !$rect.is(':visible')) {
                    // If a crop exists but the rect isn't currently visible (e.g. after preview reload), restore it.
                    try { restoreCropOverlayWhenReady($root); } catch (e) {}
                    return;
                }

                var left = parseFloat($rect.css('left')) || 0;
                var top = parseFloat($rect.css('top')) || 0;
                var w = $rect.outerWidth() || 0;
                var h = $rect.outerHeight() || 0;
                if (w <= 0 || h <= 0) return;

                var cx = left + (w / 2);
                var cy = top + (h / 2);

                // Recompute to the locked ratio, keeping centre and staying within bounds.
                var newW = w;
                var newH = newW / ratio;

                // Enforce minimum visible size.
                var minSize = 20;
                if (newW < minSize) newW = minSize;
                newH = newW / ratio;
                if (newH < minSize) {
                    newH = minSize;
                    newW = newH * ratio;
                }

                // Fit to picker bounds while preserving ratio.
                var scale = Math.min(pw / newW, ph / newH, 1);
                newW = newW * scale;
                newH = newW / ratio;
                if (newH > ph) {
                    newH = ph;
                    newW = newH * ratio;
                }
                if (newW > pw) {
                    newW = pw;
                    newH = newW / ratio;
                }

                var newLeft = Math.max(0, Math.min(cx - (newW / 2), pw - newW));
                var newTop = Math.max(0, Math.min(cy - (newH / 2), ph - newH));

                $rect.css({ left: newLeft + 'px', top: newTop + 'px', width: newW + 'px', height: newH + 'px' });
                applyOffsetsFromRect($root, $picker, $rect);

                // Ensure the rect remains interactive after any UI changes.
                try { bindRectInteractions($root, $picker, $rect); } catch (e) {}

                // If the editor is actively cropping, keep the derived preview in sync.
                try { schedulePreviewReload($root, 400); } catch (e) {}
            }

            function triggerPreviewReload($root) {
                var $previewBtn = $root.find('.jcogs-img-pro-field-preview:enabled:visible').first();
                if (!$previewBtn.length) {
                    $previewBtn = $root.find('.jcogs-img-pro-field-preview-reload:enabled:visible').first();
                }
                if (!$previewBtn.length) {
                    $previewBtn = $root.find('.jcogs-img-pro-field-preview').first();
                }
                if ($previewBtn.length) {
                    $previewBtn.trigger('click');
                }
            }

            function isCropRectVisible($root) {
                var $picker = $root.find('.jcogs-img-pro-field-crop-picker');
                if (!$picker.length) return false;
                var $rect = $picker.find('.jcogs-img-pro-field-rect');
                return !!($rect.length && $rect.is(':visible'));
            }

            function schedulePreviewReload($root, delayMs) {
                // Only auto-reload when the crop UI is active and visible.
                if (!isCropRectVisible($root)) return;

                var delay = parseInt(delayMs, 10);
                if (!isFinite(delay) || delay < 0) delay = 0;

                var existing = $root.data('jcogs-img-pro-field-preview-reload-timer');
                if (existing) {
                    try { clearTimeout(existing); } catch (e) {}
                }

                var t = setTimeout(function(){
                    // Re-check visibility at fire time.
                    if (!isCropRectVisible($root)) return;
                    triggerPreviewReload($root);
                }, delay);
                $root.data('jcogs-img-pro-field-preview-reload-timer', t);
            }

            function ensureRectHandles($rect) {
                if ($rect.find('.jcogs-img-pro-field-rect-handle').length) return;

                var mk = function(pos) {
                    var $h = $('<div class="jcogs-img-pro-field-rect-handle jcogs-img-pro-field-rect-handle-' + pos + '" data-handle="' + pos + '"></div>');
                    $h.css({
                        position: 'absolute',
                        width: '10px',
                        height: '10px',
                        background: 'rgba(0, 120, 212, 0.95)',
                        border: '1px solid rgba(255,255,255,0.9)',
                        boxSizing: 'border-box',
                        zIndex: 3
                    });
                    if (pos === 'nw') { $h.css({ left: '-6px', top: '-6px', cursor: 'nwse-resize' }); }
                    if (pos === 'ne') { $h.css({ right: '-6px', top: '-6px', cursor: 'nesw-resize' }); }
                    if (pos === 'sw') { $h.css({ left: '-6px', bottom: '-6px', cursor: 'nesw-resize' }); }
                    if (pos === 'se') { $h.css({ right: '-6px', bottom: '-6px', cursor: 'nwse-resize' }); }
                    return $h;
                };

                $rect.append(mk('nw'), mk('ne'), mk('sw'), mk('se'));
            }

            function readCropState($root) {
                var getVal = function(sel) {
                    var $el = $root.find(sel).first();
                    return $el.length ? (($el.val() || '').toString()) : '';
                };

                return {
                    crop: getVal('input[name$="[crop]"]'),
                    crop_mode: getVal('select[name$="[crop_mode]"]'),
                    crop_focus_h: getVal('select[name$="[crop_focus_h]"]'),
                    crop_focus_v: getVal('select[name$="[crop_focus_v]"]'),
                    crop_offset_x: getVal('input[name$="[crop_offset_x]"]'),
                    crop_offset_y: getVal('input[name$="[crop_offset_y]"]'),
                    crop_smart_scaling: getVal('select[name$="[crop_smart_scaling]"]'),
                    width: getVal('input[name$="[width]"]'),
                    height: getVal('input[name$="[height]"]'),
                    crop_rect_left: getVal('input[name$="[crop_rect_left]"]'),
                    crop_rect_top: getVal('input[name$="[crop_rect_top]"]'),
                    crop_rect_width: getVal('input[name$="[crop_rect_width]"]'),
                    crop_rect_height: getVal('input[name$="[crop_rect_height]"]')
                };
            }

            function isEmptyCropState(state) {
                if (!state) return true;
                var keys = [
                    'crop',
                    'crop_mode',
                    'crop_focus_h',
                    'crop_focus_v',
                    'crop_offset_x',
                    'crop_offset_y',
                    'crop_smart_scaling',
                    'width',
                    'height',
                    'crop_rect_left',
                    'crop_rect_top',
                    'crop_rect_width',
                    'crop_rect_height'
                ];
                for (var i = 0; i < keys.length; i++) {
                    var v = (state[keys[i]] || '').toString().trim();
                    if (v !== '') return false;
                }
                return true;
            }

            function writeCropState($root, state) {
                if (!state) return;

                var setVal = function(sel, v) {
                    var $el = $root.find(sel).first();
                    if (!$el.length) return;
                    $el.val((v == null ? '' : v).toString());
                };

                setVal('input[name$="[crop]"]', state.crop);
                setVal('select[name$="[crop_mode]"]', state.crop_mode);
                setVal('select[name$="[crop_focus_h]"]', state.crop_focus_h);
                setVal('select[name$="[crop_focus_v]"]', state.crop_focus_v);
                setVal('input[name$="[crop_offset_x]"]', state.crop_offset_x);
                setVal('input[name$="[crop_offset_y]"]', state.crop_offset_y);
                setVal('select[name$="[crop_smart_scaling]"]', state.crop_smart_scaling);
                setVal('input[name$="[width]"]', state.width);
                setVal('input[name$="[height]"]', state.height);
                setVal('input[name$="[crop_rect_left]"]', state.crop_rect_left);
                setVal('input[name$="[crop_rect_top]"]', state.crop_rect_top);
                setVal('input[name$="[crop_rect_width]"]', state.crop_rect_width);
                setVal('input[name$="[crop_rect_height]"]', state.crop_rect_height);
            }

            function ensureCropRectActions($root, $picker, $rect) {
                if (!$rect || !$rect.length) return;
                if ($rect.find('.jcogs-img-pro-field-rect-actions').length) return;

                var $actions = $('<div class="jcogs-img-pro-field-rect-actions"></div>');
                var $tick = $('<button type="button" class="jcogs-img-pro-field-rect-action jcogs-img-pro-field-rect-action-tick" title="Save crop">✓</button>');
                var $restore = $('<button type="button" class="jcogs-img-pro-field-rect-action jcogs-img-pro-field-rect-action-restore" title="Restore saved crop">↺</button>');
                var $clear = $('<button type="button" class="jcogs-img-pro-field-rect-action jcogs-img-pro-field-rect-action-clear" title="Clear crop">✕</button>');
                $actions.append($tick, $restore, $clear);
                $rect.append($actions);

                var stop = function(e){
                    try { e.preventDefault(); } catch (e2) {}
                    try { e.stopPropagation(); } catch (e3) {}
                };

                $tick.on('click', function(e){
                    if (!isCropUiActive($root)) {
                        stop(e);
                        return;
                    }
                    stop(e);
                    try {
                        var cur = readCropState($root);
                        $root.data('jcogs-img-pro-field-crop-saved-state', cur);
                        $root.data('jcogs-img-pro-field-crop-ui-open', 1);
                        try { $root.find('input[name$="[crop_present]"]').val('1'); } catch (e3) {}
                        setStatus(t('crop_saved', 'Crop saved'), false);
                        triggerPreviewReload($root);
                    } catch (err) {
                        try { triggerPreviewReload($root); } catch (err2) {}
                    }
                });

                $restore.on('click', function(e){
                    if (!isCropUiActive($root)) {
                        stop(e);
                        return;
                    }
                    stop(e);
                    try {
                        var snap = $root.data('jcogs-img-pro-field-crop-saved-state') || null;
                        if (!snap) {
                            snap = readCropState($root);
                            $root.data('jcogs-img-pro-field-crop-saved-state', snap);
                        }

                        writeCropState($root, snap);
                        updateCropButtonLabel($root);

                        if (isEmptyCropState(snap)) {
                            try { $root.find('.jcogs-img-pro-field-rect').hide(); } catch (eHide) {}
                        } else {
                            try {
                                var ratio = getLockedAspectRatio($root);
                                var $r = ensureRectOverlay($picker, ratio);
                                restoreRectFromCurrentFields($root, $picker, $r, ratio);
                                $r.show();
                                ensureRectHandles($r);
                                ensureCropRectActions($root, $picker, $r);
                                bindRectInteractions($root, $picker, $r);
                            } catch (eRect) {
                                try { restoreCropOverlayWhenReady($root); } catch (eRestore) {}
                            }
                        }

                        setStatus(t('crop_restored', 'Crop restored'), false);
                        triggerPreviewReload($root);
                    } catch (err) {
                        try { triggerPreviewReload($root); } catch (err2) {}
                    }
                });

                $clear.on('click', function(e){
                    if (!isCropUiActive($root)) {
                        stop(e);
                        return;
                    }
                    stop(e);
                    try {
                        var $btn = $root.find('.jcogs-img-pro-field-clear-rect').first();
                        if ($btn.length) {
                            $btn.trigger('click');
                            return;
                        }
                    } catch (eBtn) {}

                    try {
                        $root.find('input[name$="[crop]"]').val('');
                        $root.find('select[name$="[crop_mode]"]').val('');
                        $root.find('select[name$="[crop_focus_h]"]').val('');
                        $root.find('select[name$="[crop_focus_v]"]').val('');
                        $root.find('input[name$="[crop_offset_x]"]').val('');
                        $root.find('input[name$="[crop_offset_y]"]').val('');
                        $root.find('select[name$="[crop_smart_scaling]"]').val('');
                        $root.find('input[name$="[width]"]').val('');
                        $root.find('input[name$="[height]"]').val('');
                        $root.find('input[name$="[crop_rect_left]"]').val('');
                        $root.find('input[name$="[crop_rect_top]"]').val('');
                        $root.find('input[name$="[crop_rect_width]"]').val('');
                        $root.find('input[name$="[crop_rect_height]"]').val('');
                        $root.find('input[name$="[crop_present]"]').val('');
                        $root.find('.jcogs-img-pro-field-rect').hide();
                        updateCropButtonLabel($root);
                        setStatus(t('crop_offsets_cleared', 'Crop cleared'), false);
                        triggerPreviewReload($root);
                    } catch (err) {
                        try { triggerPreviewReload($root); } catch (err2) {}
                    }
                });
            }

            function ensureRectOverlay($picker, ratio) {
                var $existing = $picker.find('.jcogs-img-pro-field-rect');
                if ($existing.length) {
                    $existing.show();
                    ensureRectHandles($existing);
                    return $existing;
                }

                var $rect = $('<div class="jcogs-img-pro-field-rect"></div>');
                $rect.css({
                    position: 'absolute',
                    border: '2px solid rgba(0, 120, 212, 0.95)',
                    background: 'rgba(0, 120, 212, 0.10)',
                    boxSizing: 'border-box',
                    cursor: 'move',
                    zIndex: 2
                });
                $picker.append($rect);
                $picker.css('position', 'relative');
                ensureRectHandles($rect);

                // Initial size: neutral 50% of available space, constrained by ratio if locked.
                var pw = $picker.width() || 0;
                var ph = $picker.height() || 0;
                if (pw <= 0 || ph <= 0) {
                    return $rect;
                }

                var maxW = pw * 0.50;
                var maxH = ph * 0.50;
                var w = maxW;
                var h = maxH;
                if (ratio && ratio > 0) {
                    h = w / ratio;
                    if (h > maxH) {
                        h = maxH;
                        w = h * ratio;
                    }
                }

                var left = Math.max(0, (pw - w) / 2);
                var top = Math.max(0, (ph - h) / 2);
                $rect.css({ left: left + 'px', top: top + 'px', width: w + 'px', height: h + 'px' });

                return $rect;
            }

            function applyOffsetsFromRect($root, $picker, $rect) {
                var pw = $picker.width() || 0;
                var ph = $picker.height() || 0;
                if (pw <= 0 || ph <= 0) return;

                var left = parseFloat($rect.css('left')) || 0;
                var top = parseFloat($rect.css('top')) || 0;
                var w = $rect.outerWidth() || 0;
                var h = $rect.outerHeight() || 0;

                var cx = left + (w / 2);
                var cy = top + (h / 2);

                var fx = (cx / pw) * 100;
                var fy = (cy / ph) * 100;

                var ox = Math.round(fx - 50);
                var oy = Math.round(fy - 50);

                // Crop box target size as percent of source.
                // Image Pro validates % sizes against original dimensions.
                var wPct = Math.max(1, Math.min(100, (w / pw) * 100));
                var hPct = Math.max(1, Math.min(100, (h / ph) * 100));
                var wPctStr = (Math.round(wPct * 10) / 10).toString() + '%';
                var hPctStr = (Math.round(hPct * 10) / 10).toString() + '%';

                // Save exact rectangle as percentages (for perfect restoration).
                var leftPct = Math.max(0, Math.min(100, (left / pw) * 100));
                var topPct = Math.max(0, Math.min(100, (top / ph) * 100));
                var leftPctStr = (Math.round(leftPct * 10) / 10).toString();
                var topPctStr = (Math.round(topPct * 10) / 10).toString();
                var wPctNumStr = (Math.round(wPct * 10) / 10).toString();
                var hPctNumStr = (Math.round(hPct * 10) / 10).toString();
                $root.find('input[name$="[crop_rect_left]"]').val(leftPctStr);
                $root.find('input[name$="[crop_rect_top]"]').val(topPctStr);
                $root.find('input[name$="[crop_rect_width]"]').val(wPctNumStr);
                $root.find('input[name$="[crop_rect_height]"]').val(hPctNumStr);

                // Prefer named crop fields (clear raw crop to avoid conflict).
                $root.find('input[name$="[crop]"]').val('');
                $root.find('select[name$="[crop_mode]"]').val('yes');
                $root.find('select[name$="[crop_focus_h]"]').val('center');
                $root.find('select[name$="[crop_focus_v]"]').val('center');
                $root.find('input[name$="[crop_offset_x]"]').val(String(ox) + '%');
                $root.find('input[name$="[crop_offset_y]"]').val(String(oy) + '%');
                // When editors are explicitly positioning the crop rectangle, default Smart Scaling off.
                $root.find('select[name$="[crop_smart_scaling]"]').val('no');

                // IMPORTANT: don't override preset sizing.
                // When a preset is selected, it commonly defines width/height (and/or max_width).
                // If we force width/height from the crop rectangle, the preview appears to crop too small.
                var defaultPresetId = ($root.data('default-preset-id') || '').toString().trim();
                var $presetSelect = $root.find('select[name$="[preset_id]"]');
                var presetId = ($presetSelect.length ? ($presetSelect.val() || '') : defaultPresetId).toString().trim();
                var hasPreset = (presetId !== '' && presetId !== '0');

                if (hasPreset) {
                    // Clear any previously set size overrides so preset sizing wins.
                    $root.find('input[name$="[width]"]').val('');
                    $root.find('input[name$="[height]"]').val('');
                } else {
                    // Supply width/height so crop has a real target box in preview.
                    $root.find('input[name$="[width]"]').val(wPctStr);
                    $root.find('input[name$="[height]"]').val(hPctStr);
                    // Optional: aspect ratio can remain blank because we provided both dimensions.
                }

                updateCropButtonLabel($root);
            }

            function syncCropFromUi($root) {
                var $picker = $root.find('.jcogs-img-pro-field-crop-picker');
                if (!$picker.length) return;
                var $rect = $picker.find('.jcogs-img-pro-field-rect:visible').first();
                if (!$rect.length) return;
                applyOffsetsFromRect($root, $picker, $rect);
            }

            function applyDefaultPresetSelection($root) {
                try {
                    if (!$root || !$root.length) return;
                    if (parseInt($root.data('jcogs-img-pro-field-default-preset-applied') || '0', 10) === 1) return;

                    var $presetSelect = $root.find('select[name$="[preset_id]"]').first();
                    if (!$presetSelect.length) return;

                    var current = ($presetSelect.val() || '').toString().trim();
                    if (current !== '') {
                        $root.data('jcogs-img-pro-field-default-preset-applied', 1);
                        return;
                    }

                    var defaultPresetId = ($root.data('default-preset-id') || '').toString().trim();
                    if (!defaultPresetId || defaultPresetId === '0') {
                        $root.data('jcogs-img-pro-field-default-preset-applied', 1);
                        return;
                    }

                    var hasDefaultOption = false;
                    $presetSelect.find('option').each(function(){
                        if (String($(this).val() || '').trim() === defaultPresetId) {
                            hasDefaultOption = true;
                            return false;
                        }
                    });

                    if (!hasDefaultOption) {
                        $root.data('jcogs-img-pro-field-default-preset-applied', 1);
                        return;
                    }

                    $presetSelect.val(defaultPresetId);
                    try { $presetSelect.trigger('change'); } catch (eChange) {}
                    $root.data('jcogs-img-pro-field-default-preset-applied', 1);
                } catch (e) {}
            }

            // If a preset is selected after a crop has been set, ensure we don't override preset sizing.
            $root.on('change', 'select[name$="[preset_id]"]', function(){
                try {
                    var presetId = ($(this).val() || '').toString().trim();
                    var hasPreset = (presetId !== '' && presetId !== '0');
                    if (hasPreset && hasCropDefined($root)) {
                        $root.find('input[name$="[width]"]').val('');
                        $root.find('input[name$="[height]"]').val('');
                        // Refresh preview so editors immediately see correct crop.
                        try { schedulePreviewReload($root, 0); } catch (e2) { try { triggerPreviewReload($root); } catch (e3) {} }
                    }
                } catch (e) {}
            });

            function parseDimensionToPx(value, totalPx) {
                value = (value || '').toString().trim();
                if (!value) return null;
                var m;
                m = value.match(/^(-?\d+(?:\.\d+)?)%$/);
                if (m) {
                    var pct = parseFloat(m[1]);
                    if (!isFinite(pct)) return null;
                    return (pct / 100) * totalPx;
                }
                m = value.match(/^(-?\d+(?:\.\d+)?)px$/i);
                if (m) {
                    var px = parseFloat(m[1]);
                    return isFinite(px) ? px : null;
                }
                m = value.match(/^(-?\d+(?:\.\d+)?)$/);
                if (m) {
                    var n = parseFloat(m[1]);
                    return isFinite(n) ? n : null;
                }
                return null;
            }


            function restoreRectFromCurrentFields($root, $picker, $rect, ratioLock) {
                var pw = $picker.width() || 0;
                var ph = $picker.height() || 0;
                if (pw <= 0 || ph <= 0) return false;

                // Prefer stored structured rect if present.
                var rectLeftRaw = ($root.find('input[name$="[crop_rect_left]"]').val() || '').toString().trim();
                var rectTopRaw = ($root.find('input[name$="[crop_rect_top]"]').val() || '').toString().trim();
                var rectWidthRaw = ($root.find('input[name$="[crop_rect_width]"]').val() || '').toString().trim();
                var rectHeightRaw = ($root.find('input[name$="[crop_rect_height]"]').val() || '').toString().trim();
                if (rectLeftRaw !== '' && rectTopRaw !== '' && rectWidthRaw !== '' && rectHeightRaw !== '') {
                    var l = parseFloat(rectLeftRaw);
                    var t = parseFloat(rectTopRaw);
                    var wPct = parseFloat(rectWidthRaw);
                    var hPct = parseFloat(rectHeightRaw);
                    if (isFinite(l) && isFinite(t) && isFinite(wPct) && isFinite(hPct)) {
                        l = Math.max(0, Math.min(100, l));
                        t = Math.max(0, Math.min(100, t));
                        wPct = Math.max(1, Math.min(100, wPct));
                        hPct = Math.max(1, Math.min(100, hPct));
                        var wPx = (wPct / 100) * pw;
                        var hPx = (hPct / 100) * ph;
                        var left = (l / 100) * pw;
                        var top = (t / 100) * ph;
                        // Clamp.
                        wPx = Math.max(20, Math.min(wPx, pw));
                        hPx = Math.max(20, Math.min(hPx, ph));
                        left = Math.max(0, Math.min(left, pw - wPx));
                        top = Math.max(0, Math.min(top, ph - hPx));
                        $rect.css({ left: left + 'px', top: top + 'px', width: wPx + 'px', height: hPx + 'px' });
                        return true;
                    }
                }

                var oxRaw = ($root.find('input[name$="[crop_offset_x]"]').val() || '').toString().trim();
                var oyRaw = ($root.find('input[name$="[crop_offset_y]"]').val() || '').toString().trim();
                var wRaw = ($root.find('input[name$="[width]"]').val() || '').toString().trim();
                var hRaw = ($root.find('input[name$="[height]"]').val() || '').toString().trim();
                var rawCrop = ($root.find('input[name$="[crop]"]').val() || '').toString().trim();

                var has = false;
                if (rawCrop) has = true;
                if (oxRaw || oyRaw || wRaw || hRaw) has = true;
                if (!has) return false;

                // If they used a raw crop string, we can't reliably parse it here; fall back to neutral.
                if (rawCrop && !(oxRaw || oyRaw || wRaw || hRaw)) return false;

                var wPx = parseDimensionToPx(wRaw, pw);
                var hPx = parseDimensionToPx(hRaw, ph);

                // Neutral size if missing.
                if (!(wPx > 0)) wPx = pw * 0.50;
                if (!(hPx > 0)) {
                    if (ratioLock && ratioLock > 0) {
                        hPx = wPx / ratioLock;
                    } else {
                        hPx = ph * 0.50;
                    }
                }

                // If ratio is locked, keep the box on-ratio.
                if (ratioLock && ratioLock > 0) {
                    var hFromW = wPx / ratioLock;
                    var wFromH = hPx * ratioLock;
                    // Prefer explicit dimensions if both supplied; otherwise derive.
                    if (wRaw && !hRaw) {
                        hPx = hFromW;
                    } else if (!wRaw && hRaw) {
                        wPx = wFromH;
                    }
                }

                // Clamp size to picker bounds.
                wPx = Math.max(20, Math.min(wPx, pw));
                hPx = Math.max(20, Math.min(hPx, ph));
                if (ratioLock && ratioLock > 0) {
                    // Ensure it fits.
                    if (wPx > pw) wPx = pw;
                    var hh = wPx / ratioLock;
                    if (hh > ph) {
                        hPx = ph;
                        wPx = hPx * ratioLock;
                    } else {
                        hPx = hh;
                    }
                }

                var oxPx = parseDimensionToPx(oxRaw, pw);
                var oyPx = parseDimensionToPx(oyRaw, ph);
                if (oxPx == null) oxPx = 0;
                if (oyPx == null) oyPx = 0;

                // Offsets are relative to centre. Approximate px offsets relative to displayed image.
                var cx = (pw / 2) + oxPx;
                var cy = (ph / 2) + oyPx;

                var left = Math.max(0, Math.min(cx - (wPx / 2), pw - wPx));
                var top = Math.max(0, Math.min(cy - (hPx / 2), ph - hPx));

                $rect.css({ left: left + 'px', top: top + 'px', width: wPx + 'px', height: hPx + 'px' });
                return true;
            }

            function bindRectInteractions($root, $picker, $rect) {
                if (!$rect || !$rect.length) return;
                ensureRectHandles($rect);

                // Drag + resize behaviour.
                // NOTE: We intentionally keep the document handlers simple and re-bind them per activation.
                // This keeps the crop UI functional after preview reloads that recreate DOM nodes.
                var dragging = false;
                var resizing = false;
                var resizeHandle = null;
                var startX = 0, startY = 0;
                var startLeft = 0, startTop = 0, startW = 0, startH = 0;

                $rect.off('mousedown.jcogsRect').on('mousedown.jcogsRect', function(e){
                    // Ignore handle mousedown here.
                    if ($(e.target).hasClass('jcogs-img-pro-field-rect-handle')) return;
                    e.preventDefault();
                    dragging = true;
                    resizing = false;
                    resizeHandle = null;
                    startX = e.pageX;
                    startY = e.pageY;
                    startLeft = parseFloat($rect.css('left')) || 0;
                    startTop = parseFloat($rect.css('top')) || 0;
                    startW = $rect.outerWidth() || 0;
                    startH = $rect.outerHeight() || 0;
                });

                $rect.find('.jcogs-img-pro-field-rect-handle').off('mousedown.jcogsRect').on('mousedown.jcogsRect', function(e){
                    e.preventDefault();
                    e.stopPropagation();
                    resizing = true;
                    dragging = false;
                    resizeHandle = String($(this).data('handle') || 'se');
                    startX = e.pageX;
                    startY = e.pageY;
                    startLeft = parseFloat($rect.css('left')) || 0;
                    startTop = parseFloat($rect.css('top')) || 0;
                    startW = $rect.outerWidth() || 0;
                    startH = $rect.outerHeight() || 0;
                });

                function fitToBounds(left, top, w, h, ratioLock) {
                    var pw = $picker.width() || 0;
                    var ph = $picker.height() || 0;
                    var minSize = 20;
                    w = Math.max(minSize, w);
                    h = Math.max(minSize, h);
                    left = Math.max(0, Math.min(left, pw - minSize));
                    top = Math.max(0, Math.min(top, ph - minSize));
                    w = Math.min(w, pw - left);
                    h = Math.min(h, ph - top);

                    if (ratioLock && ratioLock > 0) {
                        // Clamp to the largest size that fits within bounds while maintaining ratio.
                        var maxW = pw - left;
                        var maxH = ph - top;
                        var fitW = Math.min(w, maxW);
                        var fitH = fitW / ratioLock;
                        if (fitH > maxH) {
                            fitH = Math.min(h, maxH);
                            fitW = fitH * ratioLock;
                        }
                        w = Math.max(minSize, Math.min(maxW, fitW));
                        h = Math.max(minSize, Math.min(maxH, fitH));
                    }

                    return { left: left, top: top, w: w, h: h };
                }

                $(document).off('mousemove.jcogsRect mouseup.jcogsRect')
                    .on('mousemove.jcogsRect', function(e){
                        var pw = $picker.width() || 0;
                        var ph = $picker.height() || 0;
                        if (pw <= 0 || ph <= 0) return;

                        var ratioLock = getLockedAspectRatio($root);
                        var dx = e.pageX - startX;
                        var dy = e.pageY - startY;

                        if (dragging) {
                            var left = Math.max(0, Math.min(startLeft + dx, pw - startW));
                            var top = Math.max(0, Math.min(startTop + dy, ph - startH));
                            $rect.css({ left: left + 'px', top: top + 'px' });
                            applyOffsetsFromRect($root, $picker, $rect);
                            return;
                        }

                        if (!resizing) return;

                        var left = startLeft;
                        var top = startTop;
                        var w = startW;
                        var h = startH;

                        // Corner resize.
                        if (resizeHandle === 'se') {
                            w = startW + dx;
                            h = startH + dy;
                        } else if (resizeHandle === 'sw') {
                            left = startLeft + dx;
                            w = startW - dx;
                            h = startH + dy;
                        } else if (resizeHandle === 'ne') {
                            top = startTop + dy;
                            w = startW + dx;
                            h = startH - dy;
                        } else if (resizeHandle === 'nw') {
                            left = startLeft + dx;
                            top = startTop + dy;
                            w = startW - dx;
                            h = startH - dy;
                        }

                        // If ratio locked, normalise to ratio using the dominant delta.
                        if (ratioLock && ratioLock > 0) {
                            var useW = Math.abs(dx) >= Math.abs(dy);
                            if (useW) {
                                h = w / ratioLock;
                            } else {
                                w = h * ratioLock;
                            }

                            // For handles that move left/top, adjust accordingly to keep opposite corner anchored.
                            if (resizeHandle === 'sw' || resizeHandle === 'nw') {
                                left = startLeft + (startW - w);
                            }
                            if (resizeHandle === 'ne' || resizeHandle === 'nw') {
                                top = startTop + (startH - h);
                            }
                        }

                        var fitted = fitToBounds(left, top, w, h, ratioLock);
                        $rect.css({ left: fitted.left + 'px', top: fitted.top + 'px', width: fitted.w + 'px', height: fitted.h + 'px' });
                        applyOffsetsFromRect($root, $picker, $rect);
                    })
                    .on('mouseup.jcogsRect', function(){
                        if (dragging || resizing) {
                            dragging = false;
                            resizing = false;
                            resizeHandle = null;
                        }
                    });
            }

            function restoreCropOverlayWhenReady($root) {
                if (!hasCropDefined($root)) return;

                var $picker = $root.find('.jcogs-img-pro-field-crop-picker');
                var $img = $root.find('img.jcogs-img-pro-field-original-img');
                if (!$picker.length || !$img.length) return;

                var ratio = getLockedAspectRatio($root);

                var attempt = function() {
                    var pw = $picker.width() || 0;
                    var ph = $picker.height() || 0;
                    if (pw <= 0 || ph <= 0) return false;
                    var $rect = ensureRectOverlay($picker, ratio);
                    var restored = restoreRectFromCurrentFields($root, $picker, $rect, ratio);
                    if (restored) {
                        $rect.show();
                        try { ensureCropRectActions($root, $picker, $rect); } catch (e0) {}
                        try { bindRectInteractions($root, $picker, $rect); } catch (e) {}
                        return true;
                    }
                    return false;
                };

                // Fast path.
                if (attempt()) return;

                // Retry after image load / layout settles.
                try {
                    $img.off('load.jcogsCropRestore').on('load.jcogsCropRestore', function(){
                        attempt();
                    });
                } catch (e) {}

                var tries = 0;
                var maxTries = 20;
                (function tick(){
                    if (attempt()) return;
                    tries++;
                    if (tries >= maxTries) return;
                    setTimeout(tick, 100);
                })();
            }

            $root.on('click', '.jcogs-img-pro-field-clear-rect', function(){
                $root.find('input[name$="[crop_offset_x]"]').val('');
                $root.find('input[name$="[crop_offset_y]"]').val('');
                $root.find('input[name$="[width]"]').val('');
                $root.find('input[name$="[height]"]').val('');
                $root.find('input[name$="[crop_rect_left]"]').val('');
                $root.find('input[name$="[crop_rect_top]"]').val('');
                $root.find('input[name$="[crop_rect_width]"]').val('');
                $root.find('input[name$="[crop_rect_height]"]').val('');
                $root.find('.jcogs-img-pro-field-rect').hide();
                setStatus(t('crop_offsets_cleared', 'Crop offsets cleared'), false);

                updateCropButtonLabel($root);

                // After clearing, refresh the preview so editors immediately see the change.
                var $previewBtn = $root.find('.jcogs-img-pro-field-preview-reload').first();
                if (!$previewBtn.length) {
                    $previewBtn = $root.find('.jcogs-img-pro-field-preview').first();
                }
                if ($previewBtn.length) {
                    $previewBtn.trigger('click');
                }
                try { renderLiveSummaryChips($root); } catch (eSummaryClearCrop) {}
            });

            function hasCropDefined($root) {
                var rectW = ($root.find('input[name$="[crop_rect_width]"]').val() || '').toString().trim();
                var rectH = ($root.find('input[name$="[crop_rect_height]"]').val() || '').toString().trim();
                var rectL = ($root.find('input[name$="[crop_rect_left]"]').val() || '').toString().trim();
                var rectT = ($root.find('input[name$="[crop_rect_top]"]').val() || '').toString().trim();
                var ox = ($root.find('input[name$="[crop_offset_x]"]').val() || '').toString().trim();
                var oy = ($root.find('input[name$="[crop_offset_y]"]').val() || '').toString().trim();
                var w = ($root.find('input[name$="[width]"]').val() || '').toString().trim();
                var h = ($root.find('input[name$="[height]"]').val() || '').toString().trim();
                return !!(rectW || rectH || rectL || rectT || ox || oy || w || h);
            }

            function updateCropButtonLabel($root) {
                var $btn = $root.find('.jcogs-img-pro-field-pick-rect').first();
                if (!$btn.length) return;
                var hasCrop = hasCropDefined($root);
                $btn.text(hasCrop ? t('btn_edit_crop', 'Edit crop') : t('btn_crop', 'Crop'));
                try { $root.find('input[name$="[crop_present]"]').val(hasCrop ? '1' : ''); } catch (e) {}
                try { updateValidationUi($root); } catch (e2) {}
            }

            function updateValidationUi($root) {
                if (!$root || !$root.length) return;

                var requireCrop = String($root.data('require-crop') || '0') === '1';
                var requireAspect = String($root.data('require-aspect-ratio') || '0') === '1';
                var errors = [];
                var tabErrors = { crop: false };

                if (requireCrop && !hasCropDefined($root)) {
                    errors.push(t('validation_crop_required', 'Crop is required before saving.'));
                    tabErrors.crop = true;
                }

                if (requireAspect) {
                    var aspect = ($root.find('input[name$="[aspect_ratio]"]').val() || '').toString().trim();
                    if (!aspect) {
                        errors.push(t('validation_aspect_required', 'Aspect ratio is required.'));
                        tabErrors.crop = true;
                    }
                }

                var $notice = $root.find('.jcogs-img-pro-field-modal-validation').first();
                if ($notice.length) {
                    if (errors.length) {
                        $notice.text(errors.join(' ')).show();
                    } else {
                        $notice.hide().text('');
                    }
                }

                $root.find('.jcogs-img-pro-field-tab').each(function(){
                    var $btn = $(this);
                    var tab = String($btn.data('jcogs-tab') || '');
                    var hasError = !!tabErrors[tab];
                    setTabError($btn, hasError);
                });
            }

            $root.on('click', '.jcogs-img-pro-field-pick-rect', function(e, source){
                if (!isCropUiActive($root)) {
                    setStatus(t('crop_tab_inactive', 'Open the Crop tab to edit the crop box.'), true);
                    return;
                }
                var $picker = $root.find('.jcogs-img-pro-field-crop-picker');
                var $img = $root.find('img.jcogs-img-pro-field-original-img');
                if (!$picker.length || !$img.length) {
                    // If the preview isn't open yet, open it and then re-run.
                    if (source !== '__from_preview__') {
                        var $previewBtn = $root.find('.jcogs-img-pro-field-preview-reload').first();
                        if (!$previewBtn.length) {
                            $previewBtn = $root.find('.jcogs-img-pro-field-preview').first();
                        }
                        if ($previewBtn.length) {
                            $root.data('jcogs-img-pro-field-open-crop-after-preview', 1);
                            $previewBtn.trigger('click');
                            return;
                        }
                    }

                    setStatus(t('preview_original_required', 'Preview original image required'), true);
                    return;
                }

                // Initialise the "saved crop" snapshot the first time crop editing is opened.
                // This is the target state for the cross (restore) action.
                try {
                    if (!$root.data('jcogs-img-pro-field-crop-saved-state')) {
                        $root.data('jcogs-img-pro-field-crop-saved-state', readCropState($root));
                    }
                } catch (eSnap) {}

                // Lock ratio only when we can infer one from input or derived params.
                var ratio = getLockedAspectRatio($root);

                // Ensure picker has a measurable height (image must be loaded).
                if (($picker.width() || 0) <= 0 || ($picker.height() || 0) <= 0) {
                    setStatus(t('loading_image', 'Loading image…'), false);
                    try {
                        $img.one('load.jcogsPickRect', function(){
                            setTimeout(function(){
                                $root.find('.jcogs-img-pro-field-pick-rect').trigger('click', ['__from_preview__']);
                            }, 0);
                        });
                    } catch (e) {}
                    return;
                }

                var $rect = ensureRectOverlay($picker, ratio);
                // If a crop is already set, restore the rectangle to match it.
                // Otherwise use neutral 50% centered rectangle.
                var restored = restoreRectFromCurrentFields($root, $picker, $rect, ratio);
                if (!restored) {
                    // Ensure neutral state also clears raw crop to avoid surprise overrides.
                    $root.find('input[name$="[crop]"]').val('');
                }
                applyOffsetsFromRect($root, $picker, $rect);
                setStatus(t('crop_drag_resize', 'Drag/resize the box to adjust crop'), false);

                try {
                    $root.data('jcogs-img-pro-field-crop-ui-open', 1);
                    ensureCropRectActions($root, $picker, $rect);
                } catch (eAct) {}

                try { bindRectInteractions($root, $picker, $rect); } catch (e) {}
            });

            function clampPct(v) {
                v = parseFloat(v);
                if (!isFinite(v)) return null;
                return Math.max(0, Math.min(100, v));
            }

            function ensureFaceOverlayLayer($picker) {
                var $layer = $picker.find('.jcogs-img-pro-field-face-overlay');
                if ($layer.length) return $layer;
                $layer = $('<div class="jcogs-img-pro-field-face-overlay"></div>');
                $layer.css({
                    position: 'absolute',
                    left: 0,
                    top: 0,
                    right: 0,
                    bottom: 0,
                    zIndex: 3,
                    pointerEvents: 'none'
                });
                $picker.css('position', 'relative');
                $picker.append($layer);
                return $layer;
            }

            function clearFaceOverlay($root) {
                try { $root.removeData('jcogs-img-pro-field-face-detect-last'); } catch (e) {}
                try {
                    var $picker = $root.find('.jcogs-img-pro-field-crop-picker');
                    $picker.find('.jcogs-img-pro-field-face-overlay').remove();
                } catch (e) {}
                try {
                    var mode = getFaceDetectControlsMode($root);
                    if (mode === 'hidden') {
                        $root.find('.jcogs-img-pro-field-face-detect-ui').hide();
                    } else {
                        $root.find('.jcogs-img-pro-field-face-detect-ui').show();
                    }
                    $root.find('.jcogs-img-pro-field-face-detect-summary')
                        .css('color', '#555')
                        .text(mode === 'hidden' ? '' : 'Choose settings (optional), then click “Detect faces”.');
                    $root.find('.jcogs-img-pro-field-face-apply-focal, .jcogs-img-pro-field-face-apply-crop').prop('disabled', true);
                } catch (e) {}
            }

            function clearImageSpecificOverrides($root) {
                // Focal.
                try {
                    $root.find('input[name$="[focal_x]"]').val('');
                    $root.find('input[name$="[focal_y]"]').val('');
                    $root.find('.jcogs-img-pro-field-focal-marker').hide();
                    setFocalPickMode($root, false);
                } catch (e) {}

                // Crop (rect + offsets + sizing + raw crop).
                try {
                    try {
                        $root.removeData('jcogs-img-pro-field-crop-saved-state');
                        $root.data('jcogs-img-pro-field-crop-ui-open', 0);
                    } catch (eSnap) {}
                    $root.find('input[name$="[crop]"]').val('');
                    $root.find('select[name$="[crop_mode]"]').val('');
                    $root.find('select[name$="[crop_focus_h]"]').val('');
                    $root.find('select[name$="[crop_focus_v]"]').val('');
                    $root.find('input[name$="[crop_offset_x]"]').val('');
                    $root.find('input[name$="[crop_offset_y]"]').val('');
                    $root.find('select[name$="[crop_smart_scaling]"]').val('');
                    $root.find('input[name$="[width]"]').val('');
                    $root.find('input[name$="[height]"]').val('');
                    $root.find('input[name$="[crop_rect_left]"]').val('');
                    $root.find('input[name$="[crop_rect_top]"]').val('');
                    $root.find('input[name$="[crop_rect_width]"]').val('');
                    $root.find('input[name$="[crop_rect_height]"]').val('');
                    $root.find('input[name$="[crop_present]"]').val('');
                    $root.find('.jcogs-img-pro-field-rect').hide();
                    updateCropButtonLabel($root);
                } catch (e2) {}
            }

            function handleFileIdChanged($root, oldId, newId) {
                try {
                    // Changing the image invalidates face/crop/focal state.
                    clearFaceOverlay($root);
                    clearImageSpecificOverrides($root);

                    // If preview is already open, refresh it so the original image matches.
                    var $wrap = $root.find('.jcogs-img-pro-field-preview-wrap');
                    if ($wrap.length && $wrap.is(':visible')) {
                        setTimeout(function(){
                            try { triggerPreviewReload($root); } catch (e2) {}
                        }, 50);
                    }

                    if ((newId || 0) > 0 && newId !== oldId) {
                        setStatus(t('image_changed_overrides_cleared', 'Image changed (overrides cleared)'), false);
                    }
                    try { renderLiveSummaryChips($root); } catch (eSummary) {}
                } catch (e) {}
            }

            function renderFaceDetectionOverlay($root, result) {
                var $picker = $root.find('.jcogs-img-pro-field-crop-picker');
                if (!$picker.length) return;

                if (!result || !result.image_width || !result.image_height) return;
                var iw = parseFloat(result.image_width) || 0;
                var ih = parseFloat(result.image_height) || 0;
                if (!(iw > 0) || !(ih > 0)) return;

                var $layer = ensureFaceOverlayLayer($picker);
                $layer.empty();

                function toPct(x, total) {
                    x = parseFloat(x);
                    total = parseFloat(total);
                    if (!isFinite(x) || !isFinite(total) || total <= 0) return 0;
                    return (x / total) * 100;
                }

                // Faces
                try {
                    var faces = result.faces || [];
                    for (var i = 0; i < faces.length; i++) {
                        var f = faces[i] || {};
                        var left = toPct(f.min_x, iw);
                        var top = toPct(f.min_y, ih);
                        var w = toPct(f.width != null ? f.width : (f.max_x - f.min_x), iw);
                        var h = toPct(f.height != null ? f.height : (f.max_y - f.min_y), ih);
                        var $b = $('<div class="jcogs-img-pro-field-face-box"></div>');
                        $b.css({
                            position: 'absolute',
                            left: left + '%',
                            top: top + '%',
                            width: Math.max(0, w) + '%',
                            height: Math.max(0, h) + '%',
                            border: '2px solid rgba(255, 193, 7, 0.95)',
                            background: 'rgba(255, 193, 7, 0.10)',
                            boxSizing: 'border-box'
                        });
                        $layer.append($b);
                    }
                } catch (e) {}

                // Collection box
                try {
                    var c = result.collection_box || null;
                    if (c && c.min_x != null && c.min_y != null && c.max_x != null && c.max_y != null) {
                        var l2 = toPct(c.min_x, iw);
                        var t2 = toPct(c.min_y, ih);
                        var w2 = toPct((c.max_x - c.min_x), iw);
                        var h2 = toPct((c.max_y - c.min_y), ih);
                        var $cbox = $('<div class="jcogs-img-pro-field-face-collection"></div>');
                        $cbox.css({
                            position: 'absolute',
                            left: l2 + '%',
                            top: t2 + '%',
                            width: Math.max(0, w2) + '%',
                            height: Math.max(0, h2) + '%',
                            border: '2px solid rgba(46, 125, 50, 0.95)',
                            background: 'rgba(46, 125, 50, 0.08)',
                            boxSizing: 'border-box'
                        });
                        $layer.append($cbox);
                    }
                } catch (e) {}

                // Suggested focal marker (in purple)
                try {
                    var s = result.suggested_focal || null;
                    if (s && s.x_pct != null && s.y_pct != null) {
                        var $m = $('<div class="jcogs-img-pro-field-face-suggested"></div>');
                        $m.css({
                            position: 'absolute',
                            left: String(s.x_pct) + '%',
                            top: String(s.y_pct) + '%',
                            width: '14px',
                            height: '14px',
                            borderRadius: '50%',
                            border: '2px solid rgba(255,255,255,0.95)',
                            background: 'rgba(156, 39, 176, 0.95)',
                            boxShadow: '0 0 0 2px rgba(156, 39, 176, 0.35)',
                            transform: 'translate(-50%, -50%)',
                            zIndex: 4
                        });
                        $layer.append($m);
                    }
                } catch (e) {}
            }

            function restoreFaceOverlayWhenReady($root) {
                var result = $root.data('jcogs-img-pro-field-face-detect-last') || null;
                if (!result) return;
                var $picker = $root.find('.jcogs-img-pro-field-crop-picker');
                var $img = $root.find('img.jcogs-img-pro-field-original-img');
                if (!$picker.length || !$img.length) return;

                var attempt = function() {
                    if (($picker.width() || 0) <= 0 || ($picker.height() || 0) <= 0) return false;
                    renderFaceDetectionOverlay($root, result);
                    return true;
                };

                if (attempt()) return;

                try {
                    $img.off('load.jcogsFaceOverlay').on('load.jcogsFaceOverlay', function(){
                        attempt();
                    });
                } catch (e) {}

                var tries = 0;
                var maxTries = 20;
                (function tick(){
                    if (attempt()) return;
                    tries++;
                    if (tries >= maxTries) return;
                    setTimeout(tick, 100);
                })();
            }

            function ensureFocalMarker($picker) {
                var $m = $picker.find('.jcogs-img-pro-field-focal-marker');
                if ($m.length) return $m;
                $m = $('<div class="jcogs-img-pro-field-focal-marker"></div>');
                $m.css({
                    position: 'absolute',
                    width: '14px',
                    height: '14px',
                    borderRadius: '50%',
                    border: '2px solid rgba(255,255,255,0.95)',
                    background: 'rgba(220, 53, 69, 0.95)',
                    boxShadow: '0 0 0 2px rgba(220, 53, 69, 0.35)',
                    transform: 'translate(-50%, -50%)',
                    zIndex: 4,
                    pointerEvents: 'none'
                });
                $picker.css('position', 'relative');
                $picker.append($m);
                return $m;
            }

            function ensureFocalHoverMarker($picker) {
                var $m = $picker.find('.jcogs-img-pro-field-focal-marker-hover');
                if ($m.length) return $m;
                $m = $('<div class="jcogs-img-pro-field-focal-marker-hover"></div>');
                $m.css({
                    position: 'absolute',
                    width: '14px',
                    height: '14px',
                    borderRadius: '50%',
                    border: '2px solid rgba(255,255,255,0.85)',
                    background: 'rgba(0, 120, 212, 0.85)',
                    boxShadow: '0 0 0 2px rgba(0, 120, 212, 0.25)',
                    transform: 'translate(-50%, -50%)',
                    zIndex: 4,
                    pointerEvents: 'none',
                    opacity: 0.75,
                    display: 'none'
                });
                $picker.css('position', 'relative');
                $picker.append($m);
                return $m;
            }

            function ensureFocalHintOverlay($picker) {
                var $h = $picker.find('.jcogs-img-pro-field-focal-hint');
                if ($h.length) return $h;
                $h = $('<div class="jcogs-img-pro-field-focal-hint"></div>');
                $h.css({
                    position: 'absolute',
                    left: 0,
                    top: 0,
                    right: 0,
                    bottom: 0,
                    background: 'rgba(0,0,0,0.35)',
                    color: '#fff',
                    display: 'none',
                    alignItems: 'center',
                    justifyContent: 'center',
                    textAlign: 'center',
                    padding: '16px',
                    zIndex: 5,
                    pointerEvents: 'none'
                });
                $h.append(
                    '<div>'
                    + '<div style="font-weight:600; font-size:14px;">Click the image to set focal point</div>'
                    + '<div style="margin-top:6px; opacity:0.9; font-size:12px;">Press ESC to cancel</div>'
                    + '</div>'
                );
                $picker.css('position', 'relative');
                $picker.append($h);
                return $h;
            }

            function setFocalMarkerFromFields($root) {
                var $picker = $root.find('.jcogs-img-pro-field-crop-picker');
                if (!$picker.length) return;

                var fx = clampPct(($root.find('input[name$="[focal_x]"]').val() || '').toString().trim());
                var fy = clampPct(($root.find('input[name$="[focal_y]"]').val() || '').toString().trim());
                var $m = ensureFocalMarker($picker);

                if (fx == null || fy == null) {
                    $m.hide();
                    return;
                }

                $m.css({ left: fx + '%', top: fy + '%' }).show();
            }

            function restoreFocalMarkerWhenReady($root) {
                var $picker = $root.find('.jcogs-img-pro-field-crop-picker');
                var $img = $root.find('img.jcogs-img-pro-field-original-img');
                if (!$picker.length || !$img.length) return;

                var attempt = function() {
                    if (($picker.width() || 0) <= 0 || ($picker.height() || 0) <= 0) return false;
                    setFocalMarkerFromFields($root);
                    return true;
                };

                if (attempt()) return;

                try {
                    $img.off('load.jcogsFocalRestore').on('load.jcogsFocalRestore', function(){
                        attempt();
                    });
                } catch (e) {}

                var tries = 0;
                var maxTries = 20;
                (function tick(){
                    if (attempt()) return;
                    tries++;
                    if (tries >= maxTries) return;
                    setTimeout(tick, 100);
                })();
            }

            function setFocalPickMode($root, on) {
                $root.data('jcogs-img-pro-field-focal-pick-mode', on ? 1 : 0);
                var $picker = $root.find('.jcogs-img-pro-field-crop-picker');
                if ($picker.length) {
                    $picker.css('cursor', on ? 'crosshair' : 'default');
                    try {
                        ensureFocalHintOverlay($picker).css('display', on ? 'flex' : 'none');
                        ensureFocalHoverMarker($picker).toggle(false);
                    } catch (e) {}
                }
                if (on) {
                    setStatus(t('pick_focal', 'Pick focal: click the original image (ESC to cancel)'), false);
                }
            }

            // ESC cancels focal pick mode.
            var escNs = 'keydown.jcogsFocal' + String(fieldId || '');
            $(document).off(escNs).on(escNs, function(e){
                try {
                    var key = e && (e.key || e.keyCode);
                    var isEsc = (key === 'Escape' || key === 'Esc' || key === 27);
                    if (!isEsc) return;
                    if (parseInt($root.data('jcogs-img-pro-field-focal-pick-mode') || '0', 10) === 1) {
                        setFocalPickMode($root, false);
                        setStatus(t('focal_pick_cancelled', 'Focal pick cancelled'), false);
                    }
                } catch (err) {}
            });

            $root.on('click', '.jcogs-img-pro-field-pick-focal', function(e, source){
                var $picker = $root.find('.jcogs-img-pro-field-crop-picker');
                var $img = $root.find('img.jcogs-img-pro-field-original-img');
                if (!$picker.length || !$img.length) {
                    if (source !== '__from_preview__') {
                        var $previewBtn = $root.find('.jcogs-img-pro-field-preview-reload').first();
                        if (!$previewBtn.length) {
                            $previewBtn = $root.find('.jcogs-img-pro-field-preview').first();
                        }
                        if ($previewBtn.length) {
                            $root.data('jcogs-img-pro-field-open-focal-after-preview', 1);
                            $previewBtn.trigger('click');
                            return;
                        }
                    }
                    setStatus(t('preview_original_required', 'Preview original image required'), true);
                    return;
                }

                var currentlyOn = parseInt($root.data('jcogs-img-pro-field-focal-pick-mode') || '0', 10) === 1;
                setFocalPickMode($root, !currentlyOn);
            });

            $root.on('click', '.jcogs-img-pro-field-clear-focal', function(){
                $root.find('input[name$="[focal_x]"]').val('');
                $root.find('input[name$="[focal_y]"]').val('');
                $root.find('.jcogs-img-pro-field-focal-marker').hide();
                setFocalPickMode($root, false);
                setStatus(t('focal_cleared', 'Focal cleared'), false);

                // Refresh preview so derived output reflects focal changes.
                try { triggerPreviewReload($root); } catch (e) {}
                try { renderLiveSummaryChips($root); } catch (eSummaryClearFocal) {}
            });

            $root.on('input change', 'input[name$="[focal_x]"], input[name$="[focal_y]"]', function(){
                try { setFocalMarkerFromFields($root); } catch (e) {}
                try { renderLiveSummaryChips($root); } catch (eSummaryFocal) {}
            });

            $root.on('click', '.jcogs-img-pro-field-crop-picker', function(e){
                var on = parseInt($root.data('jcogs-img-pro-field-focal-pick-mode') || '0', 10) === 1;
                if (!on) return;

                var $picker = $(this);
                var off = $picker.offset();
                if (!off) return;

                var pw = $picker.width() || 0;
                var ph = $picker.height() || 0;
                if (pw <= 0 || ph <= 0) return;

                var x = e.pageX - off.left;
                var y = e.pageY - off.top;
                var fx = Math.max(0, Math.min(100, (x / pw) * 100));
                var fy = Math.max(0, Math.min(100, (y / ph) * 100));

                // Use one decimal place; store as a simple number string.
                fx = (Math.round(fx * 10) / 10).toString();
                fy = (Math.round(fy * 10) / 10).toString();

                $root.find('input[name$="[focal_x]"]').val(fx);
                $root.find('input[name$="[focal_y]"]').val(fy);
                setFocalMarkerFromFields($root);
                setFocalPickMode($root, false);
                setStatus(t('focal_set', 'Focal set'), false);

                // Refresh preview so derived output reflects focal changes.
                try { triggerPreviewReload($root); } catch (e) {}
                try { renderLiveSummaryChips($root); } catch (eSummaryPick) {}
            });

            $root.on('change input', 'select[name$="[preset_id]"], input[name$="[aspect_ratio]"], input[name$="[crop]"], select[name$="[crop_mode]"], select[name$="[crop_focus_h]"], select[name$="[crop_focus_v]"], input[name$="[crop_offset_x]"], input[name$="[crop_offset_y]"], select[name$="[crop_smart_scaling]"], input[name$="[width]"], input[name$="[height]"], input[name$="[crop_rect_left]"], input[name$="[crop_rect_top]"], input[name$="[crop_rect_width]"], input[name$="[crop_rect_height]"]', function(){
                try { renderLiveSummaryChips($root); } catch (eSummaryInput) {}
            });

            $root.on('mousemove', '.jcogs-img-pro-field-crop-picker', function(e){
                var on = parseInt($root.data('jcogs-img-pro-field-focal-pick-mode') || '0', 10) === 1;
                if (!on) return;
                var $picker = $(this);
                var off = $picker.offset();
                if (!off) return;
                var pw = $picker.width() || 0;
                var ph = $picker.height() || 0;
                if (pw <= 0 || ph <= 0) return;

                var x = e.pageX - off.left;
                var y = e.pageY - off.top;
                var fx = Math.max(0, Math.min(100, (x / pw) * 100));
                var fy = Math.max(0, Math.min(100, (y / ph) * 100));

                try {
                    var $m = ensureFocalHoverMarker($picker);
                    $m.css({ left: fx + '%', top: fy + '%' }).show();
                } catch (err) {}
            });

            $root.on('mouseleave', '.jcogs-img-pro-field-crop-picker', function(){
                try {
                    $(this).find('.jcogs-img-pro-field-focal-marker-hover').hide();
                } catch (err) {}
            });

            // Clear overlays + image-specific overrides when the selected file changes.
            // Only treat the *main* file input as triggering a crop/focal reset.
            $root.on('change input', 'input.js-file-input', function(){
                try {
                    syncMainFileValue($root);
                    // While the editor is choosing an art-direction alt image, suppress main-change resets.
                    if (parseInt($root.data('jcogs-img-pro-field-ad-guard') || '0', 10) === 1) {
                        return;
                    }
                    var prevId = parseInt($root.data('jcogs-img-pro-field-last-file-id') || '0', 10) || 0;
                    var curId = getMainFileId($root);
                    if (curId !== prevId) {
                        $root.data('jcogs-img-pro-field-last-file-id', curId);
                        $root.data('jcogs-img-pro-field-last-file-value', getMainFileValue($root));
                        handleFileIdChanged($root, prevId, curId);
                    }
                } catch (e) {}
            });

            // EE's file field sometimes updates the hidden input value programmatically without firing events.
            // Poll for changes to keep UI state consistent.
            try {
                var initVal = getMainFileValue($root);
                var initId = getMainFileId($root);
                $root.data('jcogs-img-pro-field-last-file-value', initVal);
                $root.data('jcogs-img-pro-field-last-file-id', initId);
                syncMainFileValue($root);
                setInterval(function(){
                    try {
                        syncMainFileValue($root);
                        if (parseInt($root.data('jcogs-img-pro-field-ad-guard') || '0', 10) === 1) {
                            return;
                        }
                        var prevId = parseInt($root.data('jcogs-img-pro-field-last-file-id') || '0', 10) || 0;
                        var curId = getMainFileId($root);
                        if (curId !== prevId) {
                            $root.data('jcogs-img-pro-field-last-file-id', curId);
                            $root.data('jcogs-img-pro-field-last-file-value', getMainFileValue($root));
                            handleFileIdChanged($root, prevId, curId);
                        }
                    } catch (e2) {}
                }, 500);
            } catch (e) {}

            $root.on('click', '.jcogs-img-pro-field-face-detect', function(e, source){
                if (!faceDetectActUrl) {
                    setStatus(t('face_detect_action_missing', 'Face detection action unavailable'), true);
                    return;
                }

                var $picker = $root.find('.jcogs-img-pro-field-crop-picker');
                var $img = $root.find('img.jcogs-img-pro-field-original-img');
                if (!$picker.length || !$img.length) {
                    if (source !== '__from_preview__') {
                        var $previewBtn = $root.find('.jcogs-img-pro-field-preview-reload').first();
                        if (!$previewBtn.length) {
                            $previewBtn = $root.find('.jcogs-img-pro-field-preview').first();
                        }
                        if ($previewBtn.length) {
                            $root.data('jcogs-img-pro-field-open-face-detect-after-preview', 1);
                            $previewBtn.trigger('click');
                            return;
                        }
                    }
                    setStatus(t('preview_original_required', 'Preview original image required'), true);
                    return;
                }

                var fileValueRaw = getMainFileValue($root);
                var fileId = parseInt((fileValueRaw || '').toString(), 10) || 0;
                if (!fileValueRaw) {
                    setStatus(t('choose_image_first', 'Choose an image first'), true);
                    return;
                }

                var force = isFaceDetectForceEnabled($root) || !!(e && (e.altKey || e.metaKey));
                setStatus(t('detecting_faces', 'Detecting faces…'), false);

                // Keep composite metadata current before building face-detect payload.
                try { syncCompositeContext($root); } catch (eCtx) {}
                var ctx = getContextPayload($root);

                var faceSettings = { quality: 'balanced', sensitivity: 3, margin: 0 };
                try { faceSettings = getFaceDetectSettings($root); } catch (eSet) {}
                try { saveFaceDetectSettings($root, fieldId); } catch (eSave) {}

                // Show working state in the face detect panel.
                try {
                    $root.find('.jcogs-img-pro-field-face-detect-ui').show();
                    $root.find('.jcogs-img-pro-field-face-detect-summary')
                        .css('color', '#555')
                        .text(t('detecting_short', 'Detecting…'));
                    $root.find('.jcogs-img-pro-field-face-apply-focal, .jcogs-img-pro-field-face-apply-crop').prop('disabled', true);
                } catch (e0) {}

                $.post(faceDetectActUrl, {
                    token: token,
                    entry_id: entryId,
                    field_id: fieldId,
                    content_type: ctx.content_type,
                    container_id: ctx.container_id,
                    row_id: ctx.row_id,
                    fluid_field_data_id: ctx.fluid_field_data_id,
                    block_id: ctx.block_id,
                    file_id: fileId,
                    file_value: fileValueRaw,
                    face_detection_quality: faceSettings.quality,
                    face_detect_sensitivity: faceSettings.sensitivity,
                    face_crop_margin: faceSettings.margin,
                    force: force ? 'yes' : 'no'
                }).done(function(resp){
                    if (typeof resp === 'string') {
                        try { resp = JSON.parse(resp); }
                        catch (err) { resp = { error: (resp || '').toString().slice(0, 120) || 'unexpected_response' }; }
                    }
                    if (resp && resp.error) {
                        setStatus(localizeErrorMessage(resp.error), true);
                        try {
                            $root.find('.jcogs-img-pro-field-face-detect-summary')
                                .css('color', '#b71c1c')
                                .text(String(localizeErrorMessage(resp.error) || t('face_detection_failed', 'Face detection failed')));
                        } catch (e1) {}
                        return;
                    }
                    if (!resp || resp.success !== true) {
                        setStatus(t('face_detection_failed', 'Face detection failed'), true);
                        try {
                            $root.find('.jcogs-img-pro-field-face-detect-summary')
                                .css('color', '#b71c1c')
                                .text(t('face_detection_failed', 'Face detection failed'));
                        } catch (e2) {}
                        return;
                    }

                    var result = (resp.result || null);
                    $root.data('jcogs-img-pro-field-face-detect-last', result);

                    try { renderFaceDetectionOverlay($root, result); } catch (err2) {}

                    var faceCount = 0;
                    try { faceCount = (result && result.faces && result.faces.length) ? result.faces.length : 0; } catch (err3) { faceCount = 0; }

                    try {
                        var cached = !!resp.cached;
                        var summary = (faceCount === 1)
                            ? t('face_detected_one', '1 face detected')
                            : t('face_detected_many', '%s faces detected').replace('%s', String(faceCount));
                        if (cached) summary += t('face_detected_cached_suffix', ' (cached)');
                        $root.find('.jcogs-img-pro-field-face-detect-summary')
                            .css('color', '#2e7d32')
                            .text(summary);
                        $root.find('.jcogs-img-pro-field-face-detect-ui').show();
                    } catch (err4) {}

                    // Enable apply buttons only if we have something to apply.
                    try {
                        $root.find('.jcogs-img-pro-field-face-apply-focal').prop('disabled', !(result && result.suggested_focal && result.suggested_focal.x_pct != null && result.suggested_focal.y_pct != null));
                        $root.find('.jcogs-img-pro-field-face-apply-crop').prop('disabled', !(result && result.collection_box && result.collection_box.min_x != null));
                    } catch (e5) {}

                    // Avoid duplicating the face-detection success message (we show it in the Face Detection panel).
                    setStatus('', false);
                }).fail(function(xhr){
                    var msg = t('face_detection_failed', 'Face detection failed');
                    try {
                        var rt = (xhr && xhr.responseText) ? (xhr.responseText || '').toString() : '';
                        var lower = rt.toLowerCase();
                        if (lower.indexOf('maximum execution time') !== -1 || lower.indexOf('max_execution_time') !== -1) {
                            msg = t('face_detect_timed_out', 'Face detection timed out. Try lower sensitivity, “fast”, or a smaller image.');
                        } else if (lower.indexOf('allowed memory size') !== -1 || lower.indexOf('out of memory') !== -1) {
                            msg = t('face_detect_oom', 'Face detection ran out of memory. Try “fast” or a smaller image.');
                        } else if (rt) {
                            // Strip tags if we got an HTML error page.
                            var stripped = rt.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
                            msg = stripped ? stripped.slice(0, 140) : msg;
                        }
                    } catch (eFail) {}
                    setStatus(msg, true);
                    try {
                        $root.find('.jcogs-img-pro-field-face-detect-summary')
                            .css('color', '#b71c1c')
                            .text(String(msg || t('face_detection_failed', 'Face detection failed')));
                    } catch (e6) {}
                });
            });

            $root.on('click', '.jcogs-img-pro-field-face-apply-focal', function(){
                var result = $root.data('jcogs-img-pro-field-face-detect-last') || null;
                var s = result ? (result.suggested_focal || null) : null;
                if (!s || s.x_pct == null || s.y_pct == null) {
                    setStatus(t('no_suggested_focal', 'No suggested focal available'), true);
                    return;
                }

                var fx = Math.max(0, Math.min(100, parseFloat(s.x_pct)));
                var fy = Math.max(0, Math.min(100, parseFloat(s.y_pct)));
                if (!isFinite(fx) || !isFinite(fy)) {
                    setStatus(t('no_suggested_focal', 'No suggested focal available'), true);
                    return;
                }

                fx = (Math.round(fx * 10) / 10).toString();
                fy = (Math.round(fy * 10) / 10).toString();
                $root.find('input[name$="[focal_x]"]').val(fx);
                $root.find('input[name$="[focal_y]"]').val(fy);
                try { setFocalMarkerFromFields($root); } catch (e) {}
                try { triggerPreviewReload($root); } catch (e2) {}
                setStatus(t('suggested_focal_applied', 'Suggested focal applied'), false);
                try { renderLiveSummaryChips($root); } catch (eSummaryFaceFocal) {}
            });

            $root.on('click', '.jcogs-img-pro-field-face-apply-crop', function(){
                var result = $root.data('jcogs-img-pro-field-face-detect-last') || null;
                var c = result ? (result.collection_box || null) : null;
                if (!c || c.min_x == null || c.min_y == null || c.max_x == null || c.max_y == null) {
                    setStatus(t('no_face_collection_box', 'No face collection box available'), true);
                    return;
                }

                var iw = parseFloat(result.image_width) || 0;
                var ih = parseFloat(result.image_height) || 0;
                if (!(iw > 0) || !(ih > 0)) {
                    setStatus(t('invalid_face_detection_result', 'Invalid face detection result'), true);
                    return;
                }

                var leftPct = Math.max(0, Math.min(100, (parseFloat(c.min_x) / iw) * 100));
                var topPct = Math.max(0, Math.min(100, (parseFloat(c.min_y) / ih) * 100));
                var wPct = Math.max(1, Math.min(100, ((parseFloat(c.max_x) - parseFloat(c.min_x)) / iw) * 100));
                var hPct = Math.max(1, Math.min(100, ((parseFloat(c.max_y) - parseFloat(c.min_y)) / ih) * 100));
                if (!isFinite(leftPct) || !isFinite(topPct) || !isFinite(wPct) || !isFinite(hPct)) {
                    setStatus(t('invalid_face_detection_result', 'Invalid face detection result'), true);
                    return;
                }

                $root.find('input[name$="[crop_rect_left]"]').val((Math.round(leftPct * 10) / 10).toString());
                $root.find('input[name$="[crop_rect_top]"]').val((Math.round(topPct * 10) / 10).toString());
                $root.find('input[name$="[crop_rect_width]"]').val((Math.round(wPct * 10) / 10).toString());
                $root.find('input[name$="[crop_rect_height]"]').val((Math.round(hPct * 10) / 10).toString());

                // Attempt to draw + normalise crop UI immediately.
                try { restoreCropOverlayWhenReady($root); } catch (e) {}
                try { applyAspectRatioToCropUi($root); } catch (e2) {}

                // Refresh preview so derived output reflects the new crop.
                try { triggerPreviewReload($root); } catch (e3) {}
                setStatus(t('crop_applied_from_faces', 'Crop applied from faces'), false);
                try { renderLiveSummaryChips($root); } catch (eSummaryFaceCrop) {}
            });

            $root.on('click', '.jcogs-img-pro-field-face-clear-overlay', function(){
                try { clearFaceOverlay($root); } catch (e) {}
                setStatus(t('face_overlay_cleared', 'Face overlay cleared'), false);
            });
            });
        }

        syncFilePickerTargets(document);
        initJcogsImgProField(document);
        try {
            if (window.Grid && typeof window.Grid.bind === 'function') {
                window.Grid.bind('jcogs_img_pro_field', 'display', function(cell) {
                    syncFilePickerTargets(cell);
                    initJcogsImgProField(cell);
                });
            }
            if (window.FluidField && typeof window.FluidField.on === 'function') {
                window.FluidField.on('jcogs_img_pro_field', 'add', function(el) {
                    syncFilePickerTargets(el);
                    initJcogsImgProField(el);
                });
            }
        } catch (e) {}
    });
})(jQuery);
