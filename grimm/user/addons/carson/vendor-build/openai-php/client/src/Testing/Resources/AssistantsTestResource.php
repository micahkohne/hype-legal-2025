<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources\AssistantsContract;
use BoldMinded\Carson\Dependency\OpenAI\Resources\Assistants;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Assistants\AssistantDeleteResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Assistants\AssistantListResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Assistants\AssistantResponse;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\Concerns\Testable;
final class AssistantsTestResource implements AssistantsContract
{
    use Testable;
    public function resource() : string
    {
        return Assistants::class;
    }
    public function create(array $parameters) : AssistantResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function retrieve(string $id) : AssistantResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function modify(string $id, array $parameters) : AssistantResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function delete(string $id) : AssistantDeleteResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function list(array $parameters = []) : AssistantListResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function files() : AssistantsFilesTestResource
    {
        return new AssistantsFilesTestResource($this->fake);
    }
}
