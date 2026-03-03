<?php

/**
 * JCOGS Image Pro Field - PublishGuidanceService
 *===============================================
 * Builds CP guidance alerts for missing actions and pre-create states.
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

namespace JCOGSDesign\JcogsImgProField\Service;

/**
 * Build CP guidance alerts for missing actions and first-save states.
 */
class PublishGuidanceService
{
    /**
     * Build guidance HTML for the publish UI when actions are missing.
     */
    public function buildGuidanceHtml(
        bool $shouldOutputJs,
        bool $showOptions,
        int $entryId,
        int $fieldId,
        int $usageActionId,
        int $previewActionId,
        int $faceDetectActionId,
        bool $enableCrop,
        bool $enableFocal,
        bool $enableFaceDetect,
        bool $enableArtDirection,
        bool $enableDebug,
        bool $isSuperadmin
    ): string {
        if ($shouldOutputJs) {
            return '';
        }

        // Only show guidance/warnings when the options UI is actually enabled.
        if (! $showOptions) {
            return '';
        }

        if ($entryId <= 0) {
            return '<div class="field-instruct" style="margin-top:10px;">'
                . lang('jcogs_img_pro_field_editor_help_overrides_after_create')
                . '</div>';
        }

        $missing = [];

        // Usage action drives “Load saved”/AJAX save.
        if ((int) $usageActionId <= 0) {
            $missing[] = 'usage';
        }

        // Preview is only required when crop/focal/face detect (or debug preview) tools are enabled.
        $needsPreview = ($enableCrop || $enableFocal || $enableFaceDetect || ($enableDebug && $isSuperadmin) || $enableArtDirection);
        if ($needsPreview && (int) $previewActionId <= 0) {
            $missing[] = 'preview';
        }

        // Face detect action is only required when face detection is enabled.
        if ($enableFaceDetect && (int) $faceDetectActionId <= 0) {
            $missing[] = 'face_detect';
        }

        if (empty($missing)) {
            return '';
        }

        $addonsUrl = '';
        try {
            $addonsUrl = ee('CP/URL')->make('addons')->compile();
        } catch (\Throwable $e) {
            $addonsUrl = '';
        }

        $alertHtml = '';
        try {
            $alert = ee('CP/Alert')->makeInline('jcogs-img-pro-field-actions-missing-' . (int) $fieldId)
                ->asIssue()
                ->withTitle(lang('jcogs_img_pro_field_editor_alert_actions_missing_title'));

            $detail = lang('jcogs_img_pro_field_editor_alert_actions_missing_detail');
            $detail .= ' ' . sprintf(lang('jcogs_img_pro_field_editor_alert_actions_missing_missing'), implode(', ', $missing));
            $alert->addToBody($detail);

            if ($addonsUrl !== '') {
                $alert->addToBody(
                    sprintf(lang('jcogs_img_pro_field_editor_alert_actions_missing_cta_link'), htmlspecialchars($addonsUrl, ENT_QUOTES, 'UTF-8')),
                    null,
                    false
                );
            } else {
                $alert->addToBody(lang('jcogs_img_pro_field_editor_alert_actions_missing_cta_plain'));
            }

            $alertHtml = $alert->render();
        } catch (\Throwable $e) {
            $alertHtml = '';
        }

        if ($alertHtml !== '') {
            return '<div style="margin-top:10px;">' . $alertHtml . '</div>';
        }

        return '<div class="field-instruct" style="margin-top:10px;">'
            . 'AJAX endpoints are not registered for this site yet (run add-on update).'
            . '</div>';
    }
}
