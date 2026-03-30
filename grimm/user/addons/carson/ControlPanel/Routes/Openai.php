<?php

namespace BoldMinded\Carson\ControlPanel\Routes;

use BoldMinded\Carson\Dependency\Litzinger\Basee\Ping;
use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;

class Openai extends AbstractRoute
{
    /**
     * @var string
     */
    protected $route_path = 'openai';

    /**
     * @var string
     */
    protected $cp_page_title = 'Openai';

    /**
     * @param false $id
     * @return AbstractRoute
     */
    public function process($id = false)
    {
        $this->addBreadcrumb('openai', 'OpenAI');

        $setting = ee('carson:Setting');
        ee()->load->library('form_validation');

        ee()->form_validation->set_rules([
            [
                'field' => 'open_ai_key',
                'label' => 'lang:open_ai_key',
                'rules' => 'required'
            ],
        ]);

        if (ee('Request')->isPost() && ee()->form_validation->run() !== false) {
            $result = $setting->save([
                'open_ai_key' => ee('Request')->post('open_ai_key'),
                'open_ai_model' => ee('Request')->post('open_ai_model'),
                'open_ai_image_model' => ee('Request')->post('open_ai_image_model'),
                'temperature' => ee('Request')->post('temperature'),
                'frequency_penalty' => ee('Request')->post('frequency_penalty'),
                'presence_penalty' => ee('Request')->post('presence_penalty'),
            ]);

            if ($result) {
                ee('CP/Alert')
                    ->makeInline('shared-form')
                    ->asSuccess()
                    ->withTitle(lang('settings_saved'))
                    ->now();
            } else {
                ee('CP/Alert')
                    ->makeInline('shared-form')
                    ->asIssue()
                    ->withTitle('Error when saving settings')
                    ->now();
            }
        }

        $userOpenAIModels = ee()->config->item('carson_open_ai_models');
        $openAIModels = [
            'gpt-5' => 'GPT-5',
            'gpt-5-mini' => 'GPT-5 mini',
            'gpt-5-nano' => 'GPT-5 nano',
            'gpt-4o' => 'GPT-4o',
            'gpt-4.1' => 'GPT-4.1',
        ];

        if ($userOpenAIModels && is_array($userOpenAIModels)) {
            $openAIModels = array_merge($openAIModels, $userOpenAIModels);
        }

        $sections = [
            [
                [
                    'title' => lang('open_ai_key'),
                    'desc' => lang('open_ai_key_desc'),
                    'fields' => [
                        'open_ai_key' => [
                            'required' => true,
                            'type' => 'text',
                            'value' => $setting->get('open_ai_key'),
                        ]
                    ]
                ],
                [
                    'title' => lang('open_ai_model'),
                    'desc' => lang('open_ai_model_desc'),
                    'fields' => [
                        'open_ai_model' => [
                            'type' => 'dropdown',
                            'choices' => $openAIModels,
                            'value' => $setting->get('open_ai_model') ?: 'gpt-5',
                        ]
                    ]
                ],
                [
                    'title' => lang('open_ai_image_model'),
                    'desc' => lang('open_ai_image_model_desc'),
                    'fields' => [
                        'open_ai_image_model' => [
                            'type' => 'dropdown',
                            'choices' => $openAIModels,
                            'value' => $setting->get('open_ai_image_model') ?: 'gpt-5',
                        ]
                    ]
                ],
                [
                    'title' => lang('carson_temperature'),
                    'desc' => lang('carson_temperature_desc'),
                    'fields' => [
                        'temperature' => [
                            'type' => 'slider',
                            'min' => 0,
                            'max' => 2,
                            'step' => 0.1,
                            'value' => $setting->get('temperature') ?: 1,
                        ]
                    ]
                ],
                [
                    'title' => lang('carson_frequency'),
                    'desc' => lang('carson_frequency_desc'),
                    'fields' => [
                        'frequency_penalty' => [
                            'type' => 'slider',
                            'min' => -2,
                            'max' => 2,
                            'step' => 0.1,
                            'value' => $setting->get('frequency_penalty') ?: 0,
                        ]
                    ]
                ],
                [
                    'title' => lang('carson_presence'),
                    'desc' => lang('carson_presence_desc'),
                    'fields' => [
                        'presence_penalty' => [
                            'type' => 'slider',
                            'min' => -2,
                            'max' => 2,
                            'step' => 0.1,
                            'value' => $setting->get('presence_penalty') ?: 0,
                        ]
                    ]
                ],
            ]
        ];

        $variables['form'] = ee('View')
            ->make('ee:_shared/form')
            ->render([
                'cp_page_title'         => lang('open_ai_settings'),
                'base_url'              => ee('CP/URL')->make('addons/settings/carson/openai'),
                'save_btn_text'         => 'btn_save_settings',
                'save_btn_text_working' => 'btn_saving',
                'sections' => $sections
            ]);

        $this->setBody('settings-form', $variables);

        return $this;
    }
}
