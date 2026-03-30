<?php

namespace BoldMinded\Carson\Helpers;

class PromptModifierHelper
{
    public function modify(string $prompt): string
    {
        $languageHelper = new LanguageHelper;
        $languageName = $languageHelper->getCurrentLanguageName();
        $promptPrefix = '';
        $promptSuffix = '';

        if (
            !$languageHelper->isDefaultLanguage() &&
            $languageName &&
            ee()->config->item('carson_menu_use_language_context') !== 'n'
        ) {
            $promptPrefix = sprintf(lang('carson_prompt_translate_to'), $languageName);
        }

        return $promptPrefix . $prompt . $promptSuffix;
    }
}
