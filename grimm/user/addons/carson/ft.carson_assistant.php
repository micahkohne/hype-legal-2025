<?php

// Can't use Namespaces b/c of how ft files are loaded.
use BoldMinded\Carson\Dependency\Litzinger\Basee\Trial;
use BoldMinded\Carson\Helpers\ActionUrlHelper;
use BoldMinded\Carson\Helpers\LanguageHelper;
use BoldMinded\Carson\Helpers\PromptModifierHelper;

require_once PATH_THIRD . 'carson/Fieldtype.php';

class Carson_assistant_ft extends Fieldtype
{
    public array $info = [
        'name' => 'Carson Summary',
        'version' => CARSON_VERSION,
    ];

    public function display_settings($data): array
    {
        $fieldHelper = ee('carson:FieldHelper');

        $sections = [
            [
                'title' => 'Button Label',
                'fields' => [
                    'carson_assistant[button_label]' => [
                        'type' => 'text',
                        'required' => true,
                        'value' => $data['field_settings']['button_label'] ?? lang('carson_button_label'),
                    ]
                ]
            ],
            [
                'title' => 'Button Work Text',
                'desc' => 'The text to display after the button is clicked.',
                'fields' => [
                    'carson_assistant[button_working]' => [
                        'type' => 'text',
                        'required' => true,
                        'value' => $data['field_settings']['button_working'] ?? lang('carson_button_working'),
                    ]
                ]
            ],
            [
                'title' => 'Force Context?',
                'desc' => 'Makes all queries contextually relevant to the current entry. This means all prompts
                    will use the content from the Live Preview or entry form fields as it\'s context. If this is
                    disabled, and Show Context Toggle is disabled, the users will be able to to make any query
                    to OpenAI.',
                'fields' => [
                    'carson_assistant[force_context]' => [
                        'type' => 'toggle',
                        'value' => $data['field_settings']['force_context'] ?? true,
                    ]
                ]
            ],
            [
                'title' => 'Show Context Toggle?',
                'desc' => 'By default all queries are contextually relevant to the current entry. This means all prompts
                    will use the content from the Live Preview or entry form fields as it\'s context. Enabling this will
                    give users the ability to disable the context when submitting a prompt, thus being able
                    to make any query to OpenAI they want. <b>This setting will allow users to override Force Context.</b>',
                'fields' => [
                    'carson_assistant[show_context]' => [
                        'type' => 'toggle',
                        'value' => $data['field_settings']['show_context'] ?? false,
                    ]
                ]
            ],
            [
                'title' => 'Show Prompt?',
                'desc' => 'If disabled, this field will still use the Prompt Text value, but users will not be able to change it.',
                'fields' => [
                    'carson_assistant[show_prompt]' => [
                        'type' => 'toggle',
                        'value' => $data['field_settings']['show_prompt'] ?? true,
                    ]
                ]
            ],
            [
                'title' => 'Prompt Text',
                'desc' => 'Set a default prompt, such as "Summarize this page."',
                'fields' => [
                    'carson_assistant[prompt_text]' => [
                        'type' => 'text',
                        'value' => $data['field_settings']['prompt_text'] ?? lang('carson_default_prompt'),
                    ]
                ]
            ],
            [
                'title' => 'Prompt Placeholder',
                'desc' => 'If no Prompt Text is provided, users will see this placeholder text.',
                'fields' => [
                    'carson_assistant[prompt_placeholder]' => [
                        'type' => 'text',
                        'value' => $data['field_settings']['prompt_placeholder'] ?? lang('carson_default_prompt_placeholder'),
                    ]
                ]
            ],
            [
                'title' => lang('carson_target_field'),
                'desc' => lang('carson_target_field_desc'),
                'fields' => [
                    'carson_assistant[target_field]' => [
                        'type' => 'checkbox',
                        'choices' => $fieldHelper->getFieldsAsOptions(),
                        'value' => $data['field_settings']['target_field'] ?? [],
                    ]
                ]
            ],
        ];

        return ['field_options_carson_assistant' => [
            'label' => 'field_options',
            'group' => 'carson_assistant',
            'settings' => $sections
        ]];
    }

    public function save_settings($data): array
    {
        return $data['carson_assistant'] ?? [];
    }

    public function display_field($data): string
    {
        /** @var Trial $trialService */
        $trialService = ee('carson:Trial');
        if ($trialService->isTrialExpired()) {
            return $trialService->showTrialExpiredInline();
        }

        $this->loadAssets();
        $actionUrl = (new ActionUrlHelper)->getActionUrl('Carson', 'FetchData');

        $targetFieldIds = $this->settings['field_settings']['target_field'] ?? [];
        $targetFields = [];

        foreach ($targetFieldIds as $targetFieldId) {
            $targetFields[] = 'field_id_' . $targetFieldId;
        }

        $workText = $this->settings['field_settings']['button_working'] ?? lang('carson_button_working');
        $buttonLabel = $this->settings['field_settings']['button_label'] ?? lang('carson_button_label');
        $promptText = $this->settings['field_settings']['prompt_text'] ?? lang('carson_default_prompt');
        $showContext = $this->settings['field_settings']['show_context'] ?? false;
        $forceContext = $this->settings['field_settings']['force_context'] ?? true;
        $showPrompt = $this->settings['field_settings']['show_prompt'] ?? true;
        $isSelfTarget = in_array($this->field_id, $targetFieldIds);

        /** @var \BoldMinded\Carson\Helpers\FieldHelper $fieldHelper */
        $fieldHelper = ee('carson:FieldHelper');
        $fields = $fieldHelper->getFieldsAsOptions();
        $toPopulate = array_keys(array_intersect(array_flip($fields), $targetFieldIds));
        $promptModifier = new PromptModifierHelper();

        return ee('View')->make('carson:field')->render([
            'actionUrl' => $actionUrl,
            'buttonLabel' => $buttonLabel,
            'description' => sprintf(lang('carson_target_populate'), implode(', ', $toPopulate)),
            'fieldName' => $this->field_name,
            'forceContext' => $forceContext,
            'isSelfTarget' => $isSelfTarget,
            'prompt' => $promptModifier->modify($promptText),
            'promptPlaceholder' => sprintf('placeholder="%s"', lang('carson_default_prompt_placeholder')),
            'showContext' => $showContext,
            'showPrompt' => $showPrompt,
            'targetFields' => htmlspecialchars(json_encode($targetFields)),
            'type' => 'assistant',
            'workText' => $workText,
            'value' => $data ?? '',
        ]);
    }
}
