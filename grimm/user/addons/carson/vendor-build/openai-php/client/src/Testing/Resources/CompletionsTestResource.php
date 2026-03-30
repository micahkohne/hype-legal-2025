<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources\CompletionsContract;
use BoldMinded\Carson\Dependency\OpenAI\Resources\Completions;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Completions\CreateResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\StreamResponse;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\Concerns\Testable;
final class CompletionsTestResource implements CompletionsContract
{
    use Testable;
    protected function resource() : string
    {
        return Completions::class;
    }
    public function create(array $parameters) : CreateResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function createStreamed(array $parameters) : StreamResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
}
