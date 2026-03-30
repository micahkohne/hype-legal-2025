<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Responses\Fixtures\Completions;

final class CreateResponseFixture
{
    public const ATTRIBUTES = ['id' => 'cmpl-uqkvlQyYK7bGYrRHQ0eXlWi7', 'object' => 'text_completion', 'created' => 1589478378, 'model' => 'gpt-3.5-turbo-instruct', 'choices' => [['text' => "\n\nThis is a fake completion response.", 'index' => 0, 'logprobs' => null, 'finish_reason' => 'length']], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 7, 'total_tokens' => 12]];
}
