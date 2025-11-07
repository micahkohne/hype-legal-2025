<?php

use Solspace\Addons\FreeformNext\Library\Session\FormValueContext;
use Solspace\Addons\FreeformNext\Repositories\FormRepository;

require_once __DIR__ . '/vendor/autoload.php';

class Freeform_next_ft extends EE_Fieldtype
{
    /** @var array */
    public $info = [
        'name'    => 'Freeform',
        'version' => '1.0',
    ];

    public $has_array_data = TRUE;

    /**
     * Freeform_next_ft constructor.
     */
    public function __construct()
    {
        ee()->lang->loadfile('freeform_next');

        if (REQ !== 'CP' && !session_id()) {
            @session_start();
        }

        $this->info = include __DIR__ . '/addon.setup.php';

        $this->field_id = $this->settings['field_id'] ?? $this->field_id;

        $this->field_name = $this->settings['field_name'] ?? $this->field_name;
    }

    /**
     * @inheritdoc
     */
    public function update($version = ''): bool
    {
        return $version && version_compare($this->info['version'], $version, '>');
    }

    /**
     * @inheritdoc
     */
    public function display_field($data)
    {
        $formRepository = FormRepository::getInstance();

        $opts  = [
            0 => '--',
        ];
        $forms = $formRepository->getAllForms();
        foreach ($forms as $form) {
            $opts[$form->id] = $form->name;
        }

        if (empty($forms)) {
            return '<p style="margin-top:0;margin-bottom:0;">' .
                lang('no_available_composer_forms', $this->field_name) .
                '</p>';
        }

        return form_dropdown($this->field_name, $opts, $data);
    }

    /**
     * @inheritdoc
     */
    public function replace_tag($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        if ($tagdata) {
            $variables = [
                [
                    'id' => $form->getId(),
                    'name' => $form->getName(),
                    'handle' => $form->getHandle(),
                    'color' => $form->getColor(),
                    'hash' => $form->getHash(),
                    'submission_title_format' => $form->getSubmissionTitleFormat(),
                    'description' => $form->getDescription(),
                    'current_page' => $form->getCurrentPage(),
                    'return_url' => $form->getReturnUrl(),
                    'anchor' => $form->getAnchor(),
                    'default_status' => $form->getDefaultStatus(),
                    'ip_collecting_enabled' => $form->isIpCollectingEnabled(),
                    'pages' => $form->getPages(),
                    'layout' => $form->getLayout(),
                    'has_errors' => $form->hasErrors(),
                    'errors' => $form->getErrors(),
                    'marked_as_spam' => $form->isMarkedAsSpam(),
                    'valid' => $form->isValid(),
                    'page_posted' => $form->isPagePosted(),
                    'form_posted' => $form->isFormPosted(),
                    'submission_title_format_blank' => $form->isSubmissionTitleFormatBlank(),
                    'submitted_successfully' => $form->isSubmittedSuccessfully(),
                    'render' => $form->render(),
                ]
            ];

            return ee()->TMPL->parse_variables($tagdata, $variables);
        }

        return $form->render();
    }

    public function replace_id($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->getId();
    }

    public function replace_name($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->getName();
    }

    public function replace_handle($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->getHandle();
    }

    public function replace_color($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->getColor();
    }

    public function replace_hash($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->getHash();
    }

    public function replace_submission_title_format($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->getSubmissionTitleFormat();
    }

    public function replace_description($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->getDescription();
    }

    public function replace_current_page($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->getCurrentPage();
    }

    public function replace_return_url($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->getReturnUrl();
    }

    public function replace_anchor($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->getAnchor();
    }

    public function replace_default_status($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->getDefaultStatus();
    }

    public function replace_ip_collecting_enabled($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->isIpCollectingEnabled();
    }

    public function replace_pages($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->getPages();
    }

    public function replace_layout($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->getLayout();
    }

    public function replace_has_errors($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->hasErrors();
    }

    public function replace_errors($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->getErrors();
    }

    public function replace_marked_as_spam($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->isMarkedAsSpam();
    }

    public function replace_valid($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->isValid();
    }

    public function replace_page_posted($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->isPagePosted();
    }

    public function replace_form_posted($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->isFormPosted();
    }

    public function replace_submission_title_format_blank($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->isSubmissionTitleFormatBlank();
    }

    public function replace_submitted_successfully($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->isSubmittedSuccessfully();
    }

    public function replace_render($data, $params = [], $tagdata = FALSE)
    {
        $form = $this->getForm($data);
        if (!$form) {
            return '';
        }

        return $form->render();
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function accepts_content_type($name): bool
    {
        return in_array($name, ['channel', 'fluid_field', 'grid', 'blocks/1'], true);
    }

    /**
     * @return string
     */
    public function save(mixed $data)
    {
        if ((int) $data === 0) {
            return parent::save(null);
        }

        return parent::save($data);
    }

    private function getForm($data)
    {
        $formId    = (int) $data;
        $formModel = FormRepository::getInstance()->getFormById($formId);

        if (!$formModel) {
            return '';
        }

        $hash = ee()->input->post(FormValueContext::FORM_HASH_KEY, null);
        if (null !== $hash && $hash !== false) {
            if (!class_exists('Freeform_Next')) {
                require_once __DIR__ . '/mod.freeform_next.php';
            }

            $obj = new Freeform_Next();
            $obj->submitForm($formModel->getForm());
        }

        return $formModel->getForm();
    }
}
