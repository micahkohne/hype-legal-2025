<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources\ChatContract;
use BoldMinded\Carson\Dependency\OpenAI\Resources\Chat;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Chat\CreateResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\StreamResponse;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\Concerns\Testable;
final class ChatTestResource implements ChatContract
{
    use Testable;
    protected function resource() : string
    {
        return Chat::class;
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
