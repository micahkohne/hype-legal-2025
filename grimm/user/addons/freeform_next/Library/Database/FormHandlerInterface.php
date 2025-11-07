<?php
/**
 * Freeform for ExpressionEngine
 *
 * @package       Solspace:Freeform
 * @author        Solspace, Inc.
 * @copyright     Copyright (c) 2008-2025, Solspace, Inc.
 * @link          https://docs.solspace.com/expressionengine/freeform/v3/
 * @license       https://docs.solspace.com/license-agreement/
 */

namespace Solspace\Addons\FreeformNext\Library\Database;

use Solspace\Addons\FreeformNext\Library\Composer\Components\Form;
use Solspace\Addons\FreeformNext\Model\SubmissionModel;

interface FormHandlerInterface
{
    public const EVENT_BEFORE_SUBMIT      = 'beforeSubmit';
    public const EVENT_AFTER_SUBMIT       = 'afterSubmit';
    public const EVENT_BEFORE_SAVE        = 'beforeSave';
    public const EVENT_AFTER_SAVE         = 'afterSave';
    public const EVENT_BEFORE_DELETE      = 'beforeDelete';
    public const EVENT_AFTER_DELETE       = 'afterDelete';
    public const EVENT_RENDER_OPENING_TAG = 'renderOpeningTag';
    public const EVENT_RENDER_CLOSING_TAG = 'renderClosingTag';
    public const EVENT_FORM_VALIDATE      = 'validateForm';

    /**
     * @param string $templateName
     * @return string
     */
    public function renderFormTemplate(Form $form, $templateName);

    /**
     * Increments the spam block counter by 1
     *
     *
     * @return int - new spam block count
     */
    public function incrementSpamBlockCount(Form $form);

    /**
     * @return bool
     */
    public function isSpamBehaviourSimulateSuccess();

    /**
     * @return bool
     */
    public function isSpamBehaviourReloadForm();

    /**
     * @return bool
     */
    public function isSpamProtectionEnabled();

    /**
     * Do something before the form is saved
     * Return bool determines whether the form should be saved or not
     *
     *
     * @return bool
     */
    public function onBeforeSubmit(Form $form);

    /**
     * Do something after the form is saved
     *
     * @param SubmissionModel|null $submission
     */
    public function onAfterSubmit(Form $form, ?SubmissionModel $submission = null);

    /**
     * Attach anything to the form after opening tag
     *
     *
     * @return string
     */
    public function onRenderOpeningTag(Form $form);

    /**
     * Attach anything to the form before the closing tag
     *
     *
     * @return string
     */
    public function onRenderClosingTag(Form $form);

    public function onFormValidate(Form $form);
}
