<?php

namespace Solspace\Addons\FreeformNext\Library\Helpers;

class ExtensionHelper
{
    public const HOOK_FORM_BEFORE_SUBMIT      = 'freeform_next_form_before_submit';
    public const HOOK_FORM_AFTER_SUBMIT       = 'freeform_next_form_after_submit';
    public const HOOK_FORM_BEFORE_SAVE        = 'freeform_next_form_before_save';
    public const HOOK_FORM_AFTER_SAVE         = 'freeform_next_form_after_save';
    public const HOOK_FORM_BEFORE_DELETE      = 'freeform_next_form_before_delete';
    public const HOOK_FORM_AFTER_DELETE       = 'freeform_next_form_after_delete';
    public const HOOK_FORM_RENDER_OPENING_TAG = 'freeform_next_form_render_opening_tag';
    public const HOOK_FORM_RENDER_CLOSING_TAG = 'freeform_next_form_render_closing_tag';
    public const HOOK_FORM_VALIDATE           = 'freeform_next_form_validate';

    public const HOOK_FIELD_BEFORE_SAVE     = 'freeform_next_field_before_save';
    public const HOOK_FIELD_AFTER_SAVE      = 'freeform_next_field_after_save';
    public const HOOK_FIELD_BEFORE_VALIDATE = 'freeform_next_field_before_validate';
    public const HOOK_FIELD_AFTER_VALIDATE  = 'freeform_next_field_after_validate';
    public const HOOK_FIELD_BEFORE_DELETE   = 'freeform_next_field_before_delete';
    public const HOOK_FIELD_AFTER_DELETE    = 'freeform_next_field_after_delete';

    public const HOOK_SUBMISSION_BEFORE_SAVE   = 'freeform_next_submission_before_save';
    public const HOOK_SUBMISSION_AFTER_SAVE    = 'freeform_next_submission_after_save';
    public const HOOK_SUBMISSION_BEFORE_DELETE = 'freeform_next_submission_before_delete';
    public const HOOK_SUBMISSION_AFTER_DELETE  = 'freeform_next_submission_after_delete';

    public const HOOK_NOTIFICATION_BEFORE_SAVE   = 'freeform_next_notification_before_save';
    public const HOOK_NOTIFICATION_AFTER_SAVE    = 'freeform_next_notification_after_save';
    public const HOOK_NOTIFICATION_BEFORE_DELETE = 'freeform_next_notification_before_delete';
    public const HOOK_NOTIFICATION_AFTER_DELETE  = 'freeform_next_notification_after_delete';

    public const HOOK_STATUS_BEFORE_SAVE   = 'freeform_next_status_before_save';
    public const HOOK_STATUS_AFTER_SAVE    = 'freeform_next_status_after_save';
    public const HOOK_STATUS_BEFORE_DELETE = 'freeform_next_status_before_delete';
    public const HOOK_STATUS_AFTER_DELETE  = 'freeform_next_status_after_delete';

    public const HOOK_MAILER_BEFORE_SEND = 'freeform_next_mailer_before_send';
    public const HOOK_MAILER_AFTER_SEND  = 'freeform_next_mailer_after_send';

    public const HOOK_FILE_BEFORE_UPLOAD = 'freeform_next_file_before_upload';
    public const HOOK_FILE_AFTER_UPLOAD  = 'freeform_next_file_after_upload';

    public const HOOK_CRM_BEFORE_SAVE   = 'freeform_next_crm_before_save';
    public const HOOK_CRM_AFTER_SAVE    = 'freeform_next_crm_after_save';
    public const HOOK_CRM_BEFORE_DELETE = 'freeform_next_crm_before_delete';
    public const HOOK_CRM_AFTER_DELETE  = 'freeform_next_crm_after_delete';
    public const HOOK_CRM_BEFORE_PUSH   = 'freeform_next_crm_before_push';
    public const HOOK_CRM_AFTER_PUSH    = 'freeform_next_crm_after_push';

    public const HOOK_MAILING_LISTS_BEFORE_SAVE   = 'freeform_next_mailing_lists_before_save';
    public const HOOK_MAILING_LISTS_AFTER_SAVE    = 'freeform_next_mailing_lists_after_save';
    public const HOOK_MAILING_LISTS_BEFORE_DELETE = 'freeform_next_mailing_lists_before_delete';
    public const HOOK_MAILING_LISTS_AFTER_DELETE  = 'freeform_next_mailing_lists_after_delete';

    /**
     * Calls a hook and returns the "end_script" boolean
     * Returns FALSE if the script should end
     *         TRUE  if it shouldn't
     *
     * @param string $hookName
     *
     * @return bool
     */
    public static function call($hookName, mixed $arg1 = null): bool
    {
        $args     = func_get_args();
        $extClass = ee()->extensions;

        call_user_func_array([$extClass, 'call'], $args);

        return !$extClass->end_script;
    }

    /**
     * @return mixed|null
     */
    public static function getLastCallData()
    {
        if (ee()->extensions->last_call !== false) {
            return ee()->extensions->last_call;
        }

        return null;
    }
}
