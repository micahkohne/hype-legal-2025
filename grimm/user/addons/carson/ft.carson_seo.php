<?php

// Can't use Namespaces b/c of how ft files are loaded.
require_once PATH_THIRD . 'carson/Fieldtype.php';

use BoldMinded\Carson\Dependency\Litzinger\Basee\Trial;
use BoldMinded\Carson\Helpers\ActionUrlHelper;
use BoldMinded\Carson\Helpers\LanguageHelper;
use BoldMinded\Carson\Helpers\PromptModifierHelper;

class Carson_seo_ft extends Fieldtype
{
    public array $info = [
        'name' => 'Carson SEO',
        'version' => CARSON_VERSION,
    ];

    public function display_settings($data): array
    {
        $fieldHelper = ee('carson:FieldHelper');

        $sections = [
            [
                'title' => 'Button Label',
                'fields' => [
                    'carson_seo[button_label]' => [
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
                    'carson_seo[button_working]' => [
                        'type' => 'text',
                        'required' => true,
                        'value' => $data['field_settings']['button_working'] ?? lang('carson_button_working'),
                    ]
                ]
            ],
            [
                'title' => 'Title separator character',
                'desc' => 'When generating SEO titles, a characters is used to separate the page title from a brief description, e,g, "My Great Page | This is a big more about my great page"',
                'fields' => [
                    'carson_seo[separator]' => [
                        'type' => 'dropdown',
                        'choices' => [
                            '|' => 'Pipe |',
                            '-' => 'Dash -',
                            '–' => 'EN Dash –',
                            '—' => 'EM Dash —',
                            ';' => 'Semi-colon ;',
                            ':' => 'Colon :',
                            '>' => 'Arrow >',
                            '»' => 'Double Arrow »',
                        ],
                        'value' => $data['field_settings']['separator'] ?? '|',
                    ]
                ]
            ],
            [
                'title' => lang('carson_title_field'),
                'desc' => lang('carson_title_field_desc'),
                'fields' => [
                    'carson_seo[title_field]' => [
                        'type' => 'checkbox',
                        'choices' => $fieldHelper->getFieldsAsOptions(),
                        'value' => $data['field_settings']['title_field'] ?? '',
                    ]
                ]
            ],
            [
                'title' => lang('carson_description_field'),
                'desc' => lang('carson_description_field_desc'),
                'fields' => [
                    'carson_seo[description_field]' => [
                        'type' => 'checkbox',
                        'choices' => $fieldHelper->getFieldsAsOptions(),
                        'value' => $data['field_settings']['description_field'] ?? '',
                    ]
                ]
            ],
            [
                'title' => lang('carson_keywords_field'),
                'desc' => lang('carson_keywords_field_desc'),
                'fields' => [
                    'carson_seo[keywords_field]' => [
                        'type' => 'checkbox',
                        'choices' => $fieldHelper->getFieldsAsOptions(),
                        'value' => $data['field_settings']['keywords_field'] ?? '',
                    ]
                ]
            ],
        ];

        return ['field_options_carson_seo' => [
            'label' => 'field_options',
            'group' => 'carson_seo',
            'settings' => $sections
        ]];
    }

    public function save_settings($data): array
    {
        ee()->load->library('form_validation');
        ee()->form_validation->set_rules([
            [
                'field' => 'carson_seo[button_label]',
                'label' => 'lang:license',
                'rules' => 'required'
            ],
            [
                'field' => 'carson_seo[button_working]',
                'label' => 'lang:open_ai_key',
                'rules' => 'required'
            ]
        ]);

        // Use defaults if the fields were empty.
        if (ee('Request')->isPost() && ee()->form_validation->run() === false) {
            return [
                'button_label' => $data['carson_seo']['button_label'] ?: lang('carson_button_label'),
                'button_working' => $data['carson_seo']['button_working'] ?: lang('carson_button_working'),
            ];
        }

        return $data['carson_seo'] ?? [];
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
        $settings = $this->settings['field_settings'] ?? [];

        foreach (['title', 'description', 'keywords'] as $settingName) {
            ee()->javascript->set_global(
                sprintf('carson.seoFields.%s', $settingName), $settings[$settingName . '_field']
            );
        }

        $targetFieldIds = $this->settings['field_settings']['target_field'] ?? [];
        $targetFields = [];

        foreach ($targetFieldIds as $targetFieldId) {
            $targetFields[] = 'field_id_' . $targetFieldId;
        }

        $workText = $this->settings['field_settings']['button_working'] ?? lang('carson_button_working');
        $buttonLabel = $this->settings['field_settings']['button_label'] ?? lang('carson_button_label');
        $separator = $this->settings['field_settings']['separator'] ?? '|';
        $promptModifier = new PromptModifierHelper();

        return ee('View')->make('carson:field')->render([
            'actionUrl' => $actionUrl,
            'buttonLabel' => $buttonLabel,
            'description' => '',
            'fieldName' => '',
            'forceContext' => true,
            'isSelfTarget' => false,
            'prompt' => $promptModifier->modify(sprintf(lang('carson_seo_prompt'), $separator)),
            'promptPlaceholder' => '',
            'showContext' => false,
            'showPrompt' => false,
            'targetFields' => htmlspecialchars(json_encode($targetFields)),
            'type' => 'seo',
            'workText' => $workText,
        ]);
    }

    public function replace_tag($data, $params = array(), $tagdata = false): string
    {
        return '';
    }
}
