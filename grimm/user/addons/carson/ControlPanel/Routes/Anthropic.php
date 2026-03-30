<?php

namespace BoldMinded\Carson\ControlPanel\Routes;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;

class Anthropic extends AbstractRoute
{
    /**
     * @var string
     */
    protected $route_path = 'anthropic';

    /**
     * @var string
     */
    protected $cp_page_title = 'Anthropic';

    /**
     * @param false $id
     * @return AbstractRoute
     */
    public function process($id = false)
    {
        $this->addBreadcrumb('anthropic', 'Anthropic');

        $setting = ee('carson:Setting');
        ee()->load->library('form_validation');

        ee()->form_validation->set_rules([
            [
                'field' => 'anthropic_key',
                'label' => 'lang:anthropic_key',
                'rules' => 'required'
            ],
        ]);

        if (ee('Request')->isPost() && ee()->form_validation->run() !== false) {
            $result = $setting->save([
                'anthropic_key' => ee('Request')->post('anthropic_key'),
                'anthropic_model' => ee('Request')->post('anthropic_model'),
                'anthropic_image_model' => ee('Request')->post('anthropic_image_model'),
                'anthropic_temperature' => ee('Request')->post('anthropic_temperature'),
                'anthropic_top_p' => ee('Request')->post('anthropic_top_p'),
                'anthropic_top_k' => ee('Request')->post('anthropic_top_k'),
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

        $userAnthropicModels = ee()->config->item('carson_anthropic_models');
        $anthropicModels = [
            'claude-opus-4-6-20260317' => 'Claude Opus 4.6',
            'claude-sonnet-4-6-20260317' => 'Claude Sonnet 4.6',
            'claude-sonnet-4-5-20250929' => 'Claude Sonnet 4.5',
            'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5',
            'claude-opus-4-0-20250514' => 'Claude Opus 4',
        ];

        if ($userAnthropicModels && is_array($userAnthropicModels)) {
            $anthropicModels = array_merge($anthropicModels, $userAnthropicModels);
        }

        $sections = [
            [
                [
                    'title' => lang('anthropic_key'),
                    'desc' => lang('anthropic_key_desc'),
                    'fields' => [
                        'anthropic_key' => [
                            'required' => true,
                            'type' => 'text',
                            'value' => $setting->get('anthropic_key'),
                        ]
                    ]
                ],
                [
                    'title' => lang('anthropic_model'),
                    'desc' => lang('anthropic_model_desc'),
                    'fields' => [
                        'anthropic_model' => [
                            'type' => 'dropdown',
                            'choices' => $anthropicModels,
                            'value' => $setting->get('anthropic_model') ?: 'claude-sonnet-4-6-20260317',
                        ]
                    ]
                ],
                [
                    'title' => lang('anthropic_image_model'),
                    'desc' => lang('anthropic_image_model_desc'),
                    'fields' => [
                        'anthropic_image_model' => [
                            'type' => 'dropdown',
                            'choices' => $anthropicModels,
                            'value' => $setting->get('anthropic_image_model') ?: 'claude-sonnet-4-6-20260317',
                        ]
                    ]
                ],
                [
                    'title' => lang('carson_anthropic_temperature'),
                    'desc' => lang('carson_anthropic_temperature_desc'),
                    'fields' => [
                        'anthropic_temperature' => [
                            'type' => 'slider',
                            'min' => 0,
                            'max' => 1,
                            'step' => 0.1,
                            'value' => $setting->get('anthropic_temperature') ?: 1,
                        ]
                    ]
                ],
                [
                    'title' => lang('carson_anthropic_top_p'),
                    'desc' => lang('carson_anthropic_top_p_desc'),
                    'fields' => [
                        'anthropic_top_p' => [
                            'type' => 'slider',
                            'min' => 0,
                            'max' => 1,
                            'step' => 0.01,
                            'value' => $setting->get('anthropic_top_p') ?: 0,
                        ]
                    ]
                ],
                [
                    'title' => lang('carson_anthropic_top_k'),
                    'desc' => lang('carson_anthropic_top_k_desc'),
                    'fields' => [
                        'anthropic_top_k' => [
                            'type' => 'slider',
                            'min' => 0,
                            'max' => 500,
                            'step' => 1,
                            'value' => $setting->get('anthropic_top_k') ?: 0,
                        ]
                    ]
                ],
            ]
        ];

        $variables['form'] = ee('View')
            ->make('ee:_shared/form')
            ->render([
                'cp_page_title'         => lang('anthropic_settings'),
                'base_url'              => ee('CP/URL')->make('addons/settings/carson/anthropic'),
                'save_btn_text'         => 'btn_save_settings',
                'save_btn_text_working' => 'btn_saving',
                'sections' => $sections
            ]);

        $this->setBody('settings-form', $variables);

        return $this;
    }
}
