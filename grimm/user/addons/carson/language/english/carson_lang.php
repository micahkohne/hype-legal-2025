<?php

$lang = [
    'carson_module_name'        => CARSON_NAME,
    'carson_module_description' => CARSON_DESC,
    'carson_settings'           => 'Carson Settings',

    'carson_mcp_home'           => 'Overview',
    'carson_mcp_openai'         => 'OpenAI Settings',
    'carson_mcp_anthropic'      => 'Anthropic Settings',
    'carson_mcp_license'        => 'License',
    'carson_mcp_documentation'  => 'Documentation',
    'carson_mcp_release_notes'  => 'Release Notes',

    'carson_ai_provider'        => 'AI Provider',

    'license' => 'License Key',
    'open_ai_key' => 'Open AI Key',
    'open_ai_key_desc' => 'Visit <a href="https://platform.openai.com/api-keys">platform.openai.com</a> to obtain an 
        API Key. You might start with some free credits, but eventually you will need to purchase more.',

    'carson_title_field' => 'Which custom field(s) are you using for your meta Title tag?',
    'carson_title_field_desc' => 'If you are not using the <b>SEO Lite (Pro)</b> or <b>SEEO</b> add-on and are using 
        native ExpressionEngine fields to manage your SEO content, choose which fields you are using for the Title, 
        Description, and Keywords meta data.',
    'carson_description_field' => 'Which custom field(s) are you using for your meta Description tag?',
    'carson_description_field_desc' => '',
    'carson_keywords_field' => 'Which custom field(s) are you using for your meta Keywords tag?',
    'carson_keywords_field_desc' => '<a href="https://developers.google.com/search/blog/2009/09/google-does-not-use-keywords-meta-tag">Google does not use meta keywords</a> in it\'s web search, but if you still want to use them, Carson will generate them.',

    'carson_button_label' => 'Generate Meta Data',
    'carson_button_working' => 'Generating...',
    'carson_default_prompt' => 'Summarize this page.',
    'carson_default_prompt_placeholder' => 'What can I help you with?',
    'carson_target_field' => 'Target Field(s)',
    'carson_target_populate' => 'The results of this prompt will populate <b>%s</b>',
    'carson_target_field_desc' => 'The response to the prompt can be placed in one or more fields. Which shall they be? 
        Fields within Fluid, Bloqs, or Grid are not available for selection. If you want to save the response to this
        field you must first save the settings for this field at least once, then it will be available for selection
        below.',

    'carson_seo_prompt' => 'In a JSON object literal format with the keys title, description, and keywords, give me the 
        best possible SEO title, description, and 5 keywords of the following HTML and use the %s character in the title.
        Do not wrap the JSON object in a code block: ',

    'open_ai_settings' => 'OpenAI Settings',
    'open_ai_model' => 'Default Model',
    'open_ai_model_desc' => 'Choose which model to use for text generation and reasoning.',
    'open_ai_image_model' => 'Image Model',
    'open_ai_image_model_desc' => 'Choose which model to use for image parsing.',

    'carson_temperature' => 'Temperature',
    'carson_temperature_desc' => 'Lower values for temperature result in more consistent outputs (e.g. 0.2), while 
        higher values generate more diverse and creative results (e.g. 1.0). Select a temperature value based on the 
        desired trade-off between coherence and creativity for your specific application. The temperature can range is from 0 to 2.',

    'carson_frequency' => 'Frequency Penalty',
    'carson_frequency_desc' => 'Frequency Penalty helps avoid using and repeating the same words too often.',

    'carson_presence' => 'Presence Penalty',
    'carson_presence_desc' => 'Presence Penalty encourages using a variety of words, but just not the same words.',

    'carson_omni_field_desc' => 'Carson Omni is available in this entry. Use the <span class="carson-omni-icon"><i class="fas fa-wand-magic-sparkles"></i></span> icon to leverage AI on your content.',

    'carson_prompt_translate_to' => 'Translate to %s. ',
    'carson_prompt_improve_writing' => 'Without extra commentary, improve the writing of the following content',
    'carson_prompt_improve_writing_label' => 'Improve writing',
    'carson_prompt_fix_spelling_grammar' => 'Fix spelling & grammar of the following content',
    'carson_prompt_fix_spelling_grammar_label' => 'Fix spelling & grammar',
    'carson_prompt_shorter' => 'Make this content shorter',
    'carson_prompt_shorter_label' => 'Make shorter',
    'carson_prompt_longer' => 'Make this content longer',
    'carson_prompt_longer_label' => 'Make longer',
    'carson_prompt_summarize' => 'Summarize this content',
    'carson_prompt_summarize_label' => 'Summarize',
    'carson_prompt_simplify' => 'Simplify the language of this content',
    'carson_prompt_simplify_label' => 'Simplify language',
    'carson_prompt_tone_professional' => 'Change the tone of the following content to be more professional',
    'carson_prompt_tone_professional_label' => 'Professional',
    'carson_prompt_tone_casual' => 'Change the tone of the following content to be more casual',
    'carson_prompt_tone_casual_label' => 'Casual',
    'carson_prompt_tone_straightforward' => 'Change the tone of the following content to be more straightforward',
    'carson_prompt_tone_straightforward_label' => 'Straightforward',
    'carson_prompt_tone_confident' => 'Change the tone of the following content to be more confident',
    'carson_prompt_tone_confident_label' => 'Confident',
    'carson_prompt_tone_friendly' => 'Change the tone of the following content to be more friendly',
    'carson_prompt_tone_friendly_label' => 'Friendly',
    'carson_prompt_image_description' => 'You are confident in your response. Provide a description of this image to be used as the alt text. Do not include the words alt text in your response.',
    'carson_prompt_image_description_label' => 'Describe image',

    'carson_custom_prompts_heading' => 'Custom Prompts Heading',
    'carson_custom_prompts_heading_desc' => 'Add a new section to the Omni dropdown menu with your own prompts. This will be the section heading.',
    'carson_custom_prompts' => 'Custom Prompts',
    'carson_custom_prompts_desc' => 'Add a custom prompt to the Omni dropdown menu. The prompt value will prefix the content
        that is sent to the AI provider. The icon value should match what is available in <a href="https://fontawesome.com/search">Font Awesome</a>. All icon values
        will be prefixed with "fa-", so if your icon value is "book", it will render the "fa-book" Font Awesome icon.',

    'carson_prompt_image_override' => 'Image Prompt Override',
    'carson_prompt_image_override_desc' => 'Override the default image description prompt.',

    'anthropic_key' => 'Anthropic API Key',
    'anthropic_key_desc' => 'Visit <a href="https://console.anthropic.com/settings/keys">console.anthropic.com</a> to obtain an API Key.',
    'anthropic_settings' => 'Anthropic Settings',
    'anthropic_model' => 'Default Model',
    'anthropic_model_desc' => 'Choose which model to use for text generation and reasoning.',
    'anthropic_image_model' => 'Image Model',
    'anthropic_image_model_desc' => 'Choose which model to use for image parsing.',
    'carson_anthropic_temperature' => 'Temperature',
    'carson_anthropic_temperature_desc' => 'Lower values for temperature result in more consistent outputs (e.g. 0.2), while
        higher values generate more diverse and creative results (e.g. 1.0). Select a temperature value based on the
        desired trade-off between coherence and creativity for your specific application. The temperature range is from 0 to 1.',
    'carson_anthropic_top_p' => 'Top P',
    'carson_anthropic_top_p_desc' => 'Use nucleus sampling. Only used when Temperature is not set, as Anthropic does not allow
        both in the same request. In nucleus sampling, we compute the cumulative distribution over all the options for each
        subsequent token in decreasing probability order and cut it off once it reaches a particular probability specified by top_p.',
    'carson_anthropic_top_k' => 'Top K',
    'carson_anthropic_top_k_desc' => 'Only sample from the top K options for each subsequent token. Used to remove "long tail"
        low probability responses. Recommended for advanced use cases only. You usually only need to use temperature.',
];
