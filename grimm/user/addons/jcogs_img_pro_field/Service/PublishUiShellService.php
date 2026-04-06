<?php

/**
 * JCOGS Image Pro Field - PublishUiShellService
 *==============================================
 * Renders the structural HTML shell for the publish UI panel.
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

namespace JCOGSDesign\JcogsImgProField\Service;

/**
 * Render the publish UI shell markup.
 */
class PublishUiShellService
{
    /**
     * Render the opening markup for the publish options panel.
     *
     * @param array<int, string> $chips
     */
    public function renderOptionsOpen(array $chips, bool $hasAnyOverride, bool $isComposite = false): string
    {
        if ($isComposite) {
            return '<div class="jcogs-img-pro-field-options jcogs-img-pro-field-options--composite">'
                . '<div style="margin-top:8px;">';
        }

        $chipsHtml = $this->renderChipsHtml($chips);

        return '<details class="jcogs-img-pro-field-options" style="margin-top:8px;"'
            . ($hasAnyOverride ? ' open' : '')
            . '>'
            . '<summary class="field-instruct" style="cursor:pointer; user-select:none;">'
            . lang('jcogs_img_pro_field_editor_adjust_summary')
            . $chipsHtml
            . '</summary>'
            . '<div style="margin-top:8px;">';
    }

    /**
     * Render the closing markup for the publish options panel.
     */
    public function renderOptionsClose(bool $isComposite = false): string
    {
        if ($isComposite) {
            return '</div></div>';
        }

        return '</div></details>';
    }

    /**
     * Render the composite-field summary block (modal trigger).
     *
     * @param array<int, string> $chips
     */
    public function renderCompositeSummary(array $chips): string
    {
        $chipsHtml = $this->renderChipsHtml($chips);

        return '<div class="jcogs-img-pro-field-composite-summary">'
            . '<div class="jcogs-img-pro-field-composite-summary-main">'
            . '<div class="field-instruct">'
            . lang('jcogs_img_pro_field_editor_adjust_summary')
            . '</div>'
            . $chipsHtml
            . '</div>'
            . '<div class="jcogs-img-pro-field-composite-summary-action">'
            . '<button type="button" class="button button--secondary jcogs-img-pro-field-open-modal">'
            . lang('jcogs_img_pro_field_editor_btn_open_modal')
            . '</button>'
            . '</div>'
            . '</div>';
    }

    /**
     * Render the opening markup for the composite modal.
     */
    public function renderCompositeModalOpen(): string
    {
        $title = lang('jcogs_img_pro_field_editor_modal_title');

        return '<div class="jcogs-img-pro-field-modal" aria-hidden="true">'
            . '<div class="jcogs-img-pro-field-modal-backdrop" data-jcogs-modal-close="1"></div>'
            . '<div class="jcogs-img-pro-field-modal-dialog" role="dialog" aria-modal="true" aria-label="'
            . htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8')
            . '">'
            . '<div class="jcogs-img-pro-field-modal-header">'
            . '<h2 class="dialog__title jcogs-img-pro-field-modal-title">' . htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<button type="button" class="button button--secondary jcogs-img-pro-field-close-modal" data-jcogs-modal-close="1">'
            . lang('jcogs_img_pro_field_editor_btn_close_modal')
            . '</button>'
            . '</div>'
            . '<div class="jcogs-img-pro-field-modal-body">'
            . '<div class="jcogs-img-pro-field-modal-validation" role="status" aria-live="polite" style="display:none;"></div>';
    }

    /**
     * Render the closing markup for the composite modal.
     */
    public function renderCompositeModalClose(): string
    {
        return '</div></div></div>';
    }

    /**
     * Render the wrapper opening for the workspace layout.
     */
    public function renderWorkspaceOpen(): string
    {
        return '<div class="jcogs-img-pro-field-workspace">';
    }

    /**
     * Render the wrapper closing for the workspace layout.
     */
    public function renderWorkspaceClose(): string
    {
        return '</div>'; // .jcogs-img-pro-field-workspace
    }

    /**
     * Render the controls column opening.
     */
    public function renderControlsOpen(): string
    {
        return '<div class="jcogs-img-pro-field-controls">';
    }

    /**
     * Render the controls column closing.
     */
    public function renderControlsClose(): string
    {
        return '</div>'; // .jcogs-img-pro-field-controls
    }

    /**
     * Render a status block placeholder when enabled.
     */
    public function renderStatusBlock(bool $show): string
    {
        if (! $show) {
            return '';
        }

        return '<div style="margin:2px 0 10px 0;">'
            . '<span class="jcogs-img-pro-field-status" style="font-size:12px;"></span>'
            . '</div>';
    }

    /**
     * Render the preview column opening wrappers.
     */
    public function renderPreviewColOpen(): string
    {
        return '<div class="jcogs-img-pro-field-preview-col">'
            . '<div class="jcogs-img-pro-field-preview-wrap">';
    }

    /**
     * Render the preview body placeholder text.
     */
    public function renderPreviewBody(string $bodyText): string
    {
        return '<div class="jcogs-img-pro-field-preview-body" style="min-height: 40px; opacity:.8; font-size:12px;">'
            . $bodyText
            . '</div>';
    }

    /**
     * Render the preview column closing wrappers.
     */
    public function renderPreviewColClose(): string
    {
        return '</div>'
            . '</div>'; // .jcogs-img-pro-field-preview-col
    }

    /**
     * @param array<int, string> $chips
     */
    private function renderChipsHtml(array $chips): string
    {
        if (empty($chips)) {
            return '';
        }

        $chipSpans = [];
        foreach ($chips as $chip) {
            $chipSpans[] = '<span style="display:inline-block; padding:2px 8px; border-radius:999px; background:#f3f5f7; border:1px solid #e2e6ea; font-size:11px; line-height:16px; color:#334155;">'
                . htmlspecialchars((string) $chip, ENT_QUOTES, 'UTF-8')
                . '</span>';
        }

        return '<span class="jcogs-img-pro-field-summary-chips" style="display:inline-flex; flex-wrap:wrap; gap:6px; margin-left:10px; vertical-align:middle;">'
            . implode('', $chipSpans)
            . '</span>';
    }
}
