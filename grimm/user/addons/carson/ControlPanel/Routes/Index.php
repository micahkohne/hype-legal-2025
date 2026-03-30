<?php

namespace BoldMinded\Carson\ControlPanel\Routes;

use BoldMinded\Publisher\Service\ChannelField;
use BoldMinded\Carson\Dependency\Litzinger\Basee\Ping;
use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;

class Index extends AbstractRoute
{
    /**
     * @var string
     */
    protected $route_path = 'index';

    /**
     * @var string
     */
    protected $cp_page_title = 'Settings';

    /**
     * @param false $id
     * @return AbstractRoute
     */
    public function process($id = false)
    {
        $this->addBreadcrumb('settings', 'Settings');

        $setting = ee('carson:Setting');

        if (ee('Request')->isPost()) {
            $result = $setting->save([
                'ai_provider' => ee('Request')->post('ai_provider'),
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

        $sections = [
            [
                [
                    'title' => lang('carson_ai_provider'),
                    'fields' => [
                        'ai_provider' => [
                            'required' => true,
                            'type' => 'dropdown',
                            'choices' => [
                                'OpenAI' => 'OpenAI',
                                'Anthropic' => 'Anthropic',
                            ],
                            'value' => $setting->get('ai_provider') ?: 'OpenAI',
                        ]
                    ]
                ],
            ]
        ];

        $variables['form'] = ee('View')
            ->make('ee:_shared/form')
            ->render([
                'cp_page_title'         => lang('carson_settings'),
                'base_url'              => ee('CP/URL')->make('addons/settings/carson'),
                'save_btn_text'         => 'btn_save_settings',
                'save_btn_text_working' => 'btn_saving',
                'sections' => $sections
            ]);

        $this->setBody('settings-form', $variables);

        return $this;
    }
}
