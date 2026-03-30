<?php

// Can't use Namespaces b/c of how ft files are loaded.
require_once PATH_THIRD . 'carson/Fieldtype.php';

use BoldMinded\Carson\Dependency\Litzinger\Basee\Trial;
use BoldMinded\Carson\Helpers\ActionUrlHelper;
use BoldMinded\Carson\Helpers\LanguageHelper;
use BoldMinded\Carson\Helpers\PromptModifierHelper;
use BoldMinded\Carson\Menu\Divider;
use BoldMinded\Carson\Menu\Group;
use BoldMinded\Carson\Menu\Heading;
use BoldMinded\Carson\Menu\Item;
use BoldMinded\Carson\Menu\Menu;
use ExpressionEngine\Library\CP\MiniGridInput;

class Carson_omni_ft extends Fieldtype
{
    public array $info = [
        'name' => 'Carson Omni',
        'version' => CARSON_VERSION,
    ];

    public function display_settings($data): array
    {
        $settings = $data['field_settings'] ?? [];

        $sections = [
            [
                'title' => lang('carson_custom_prompts_heading'),
                'desc' => lang('carson_custom_prompts_heading_desc'),
                'fields' => [
                    'carson_omni[custom_prompts_heading]' => [
                        'type' => 'text',
                        'value' => $settings['custom_prompts_heading'] ?? lang('carson_custom_prompts'),
                    ]
                ]
            ],
            [
                'title' => lang('carson_custom_prompts'),
                'desc' => lang('carson_custom_prompts_desc'),
                'fields' => [
                    'carson_omni[custom_prompts]' => [
                        'type' => 'html',
                        'content' => '<div class="carson_mini_grid">' . $this->buildCustomPromptsMiniGrid($settings['custom_prompts'] ?? []) . '</div>',
                    ]
                ]
            ],
            [
                'title' => lang('carson_prompt_image_override'),
                'desc' => lang('carson_prompt_image_override_desc'),
                'fields' => [
                    'carson_omni[custom_prompt_image_override]' => [
                        'type' => 'textarea',
                        'value' => $settings['custom_prompt_image_override'] ?? '',
                        'placeholder' => lang('carson_prompt_image_description'),
                    ]
                ]
            ],
        ];

        return ['field_options_carson_omni' => [
            'label' => 'field_options',
            'group' => 'carson_omni',
            'settings' => $sections
        ]];
    }

    public function save_settings($data): array
    {
        $saveSettings = $data['carson_omni'] ?? [];

        $saveSettings['field_fmt'] = 'none';
        $saveSettings['field_show_fmt'] = 'n';
        $saveSettings['field_wide'] = true;

        return $saveSettings;
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

        ee()->javascript->set_global(
            'carson.fetchDataUrl', $actionUrl
        );

        $output = '<template id="carsonOmniMenuTemplate">'. $this->buildMenu() .'</template>';

        $output .= ee('View')->make('ee:_shared/form/fields/note')->render([
            'value' => '<span id="carson_omni"></span>' . lang('carson_omni_field_desc'),
        ]);

        return $output;
    }

    public function replace_tag($data, $params = array(), $tagdata = false): string
    {
        return '';
    }

    private function buildMenu(): Menu
    {
        $menu = new Menu();
        $promptModifier = new PromptModifierHelper();

        if (ee()->config->item('carson_menu_include_image') !== 'n') {
            $imagePrompt = $this->settings['field_settings']['custom_prompt_image_override'] ?? '';

            if (!$imagePrompt) {
                $imagePrompt = lang('carson_prompt_image_description');
            }

            $menu->addGroup(
                new Group(
                    new Item(
                        label: lang('carson_prompt_image_description_label'),
                        prompt: $promptModifier->modify($imagePrompt),
                        icon: 'image',
                        isHidden: true,
                        extraClass: 'carson-prompt-image',
                        dataAttributes: ['request-type' => 'image'],
                    ),
                ),
            );
        }

        if (ee()->config->item('carson_menu_include_default') !== 'n') {
            $menu->addGroup(
                new Group(
                    new Item(
                        label: lang('carson_prompt_improve_writing_label'),
                        prompt: $promptModifier->modify(lang('carson_prompt_improve_writing')),
                        icon: 'book-sparkles',
                    ),
                    new Item(
                        label: lang('carson_prompt_fix_spelling_grammar_label'),
                        prompt: $promptModifier->modify(lang('carson_prompt_fix_spelling_grammar')),
                        icon: 'check',
                    ),
                    new Item(
                        label: lang('carson_prompt_shorter_label'),
                        prompt: $promptModifier->modify(lang('carson_prompt_shorter')),
                        icon: 'arrows-to-line',
                    ),
                    new Item(
                        label: lang('carson_prompt_longer_label'),
                        prompt: $promptModifier->modify(lang('carson_prompt_longer')),
                        icon: 'arrows-from-line',
                    ),
                    new Item(
                        label: lang('carson_prompt_summarize_label'),
                        prompt: $promptModifier->modify(lang('carson_prompt_summarize')),
                        icon: 'comment-quote',
                    ),
                    new Item(
                        label: lang('carson_prompt_simplify_label'),
                        prompt: $promptModifier->modify(lang('carson_prompt_simplify')),
                        icon: 'sparkles',
                    ),
                ),
            );
        }

        if (ee()->config->item('carson_menu_include_tone') !== 'n') {
            $menu->addGroup(
                new Group(
                    new Divider(),
                    new Heading(heading: 'Change tone'),
                    new Item(
                        label: lang('carson_prompt_tone_professional_label'),
                        prompt: $promptModifier->modify(lang('carson_prompt_tone_professional')),
                        icon: 'microphone',
                    ),
                    new Item(
                        label: lang('carson_prompt_tone_casual_label'),
                        prompt: $promptModifier->modify(lang('carson_prompt_tone_casual')),
                        icon: 'microphone',
                    ),
                    new Item(
                        label: lang('carson_prompt_tone_straightforward_label'),
                        prompt: $promptModifier->modify(lang('carson_prompt_tone_straightforward')),
                        icon: 'microphone',
                    ),
                    new Item(
                        label: lang('carson_prompt_tone_confident_label'),
                        prompt: $promptModifier->modify(lang('carson_prompt_tone_confident')),
                        icon: 'microphone',
                    ),
                    new Item(
                        label: lang('carson_prompt_tone_friendly_label'),
                        prompt: $promptModifier->modify(lang('carson_prompt_tone_friendly')),
                        icon: 'microphone',
                    ),
                ),
            );
        }

        $customPrompts = $this->settings['field_settings']['custom_prompts']['rows'] ?? [];
        $customPromptHeading = $this->settings['field_settings']['custom_prompts_heading'] ?? lang('carson_custom_prompts');

        if (!empty($customPrompts)) {
            $customPromptsGroup = new Group(
                new Divider,
                new Heading($customPromptHeading),
            );
            foreach ($customPrompts as $prompt) {
                $customPromptsGroup->addItem(new Item(
                    label: $prompt['label'],
                    prompt: $prompt['prompt'],
                    icon: $prompt['icon'],
                ));
            }

            $menu->addGroup(
                $customPromptsGroup,
            );
        }

        if (ee()->config->item('carson_menu_include_language') !== 'n') {
            $languageHelper = new LanguageHelper;
            $languageName = $languageHelper->getCurrentLanguageName();

            if (ee()->config->item('carson_languages')) {
                $languages = ee()->config->item('carson_languages');

                $languageGroup = new Group();
                $languageGroup->addItem(new Divider());
                $languageGroup->addItem(new Heading(heading: 'Translate to'));

                foreach ($languages as $language) {
                    $languageGroup->addItem(new Item(
                        label: $language,
                        prompt: sprintf(lang('carson_prompt_translate_to'), $language),
                        icon: 'globe',
                    ));
                }

                $menu->addGroup($languageGroup);
            } else if (!$languageHelper->isDefaultLanguage() && $languageName) {
                $menu->addGroup(new Group(
                    new Divider(),
                    new Heading(heading: 'Translate to'),
                    new Item(
                        label: $languageName,
                        prompt: sprintf(lang('carson_prompt_translate_to'), $languageName),
                        icon: 'globe',
                    )
                ));
            }
        }

        $menu->addGroup(
            new Group(
                new Divider(
                    isHidden: true,
                    extraClass: 'undo-divider',
                ),
                new Item(
                    label: 'Undo',
                    prompt: '',
                    icon: 'undo',
                    isHidden: true,
                    extraClass: 'undo'
                ),
            )
        );

        if (ee()->extensions->active_hook('carson_modify_menu')) {
            $menu = ee()->extensions->call('carson_modify_menu', $menu, $promptModifier);

            assert($menu instanceof Menu);
        }

        return $menu;
    }

    private function buildCustomPromptsMiniGrid(array $settings = []): string
    {
        /** @var MiniGridInput $grid */
        $grid = ee('CP/MiniGridInput', [
            'field_name' => 'carson_omni[custom_prompts]',
        ]);
        $grid->loadAssets();
        $grid->setColumns([
            'Label',
            'Prompt',
            'Icon',
        ]);

        $grid->setNoResultsText('No prompts exist', 'Add A Prompt');
        $grid->setBlankRow([
            ['html' => form_input('label', '')],
            ['html' => form_textarea([
                'name' => 'prompt',
                'value' => '',
                'rows' => 2
            ])],
            ['html' => form_input('icon', '')],
        ]);

        $pairs = [];

        if (!empty($settings['rows'])) {
            foreach ($settings['rows'] as $columnId => $rowData) {
                // concession made b/c of Low Variables apparently can't handle 2 ft files from the same add-on
                if (is_array($rowData)) {
                    $pairs[] = [
                        'attrs' => ['row_id' => $columnId],
                        'columns' => [
                            ['html' => form_input('label', $rowData['label'] ?? '')],
                            ['html' => form_textarea([
                                'name' => 'prompt',
                                'value' => $rowData['prompt'] ?? '',
                                'rows' => 2
                            ])],
                            ['html' => form_input('icon', $rowData['icon'] ?? '')],
                        ]
                    ];
                }
            }
        }

        $grid->setData($pairs);
        $miniGrid = ee('View')->make('ee:_shared/form/mini_grid')->render($grid->viewData());

        return $miniGrid;
    }
}
