<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources\AssistantsFilesContract;
use BoldMinded\Carson\Dependency\OpenAI\Resources\AssistantsFiles;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Assistants\Files\AssistantFileDeleteResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Assistants\Files\AssistantFileListResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Assistants\Files\AssistantFileResponse;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\Concerns\Testable;
final class AssistantsFilesTestResource implements AssistantsFilesContract
{
    use Testable;
    public function resource() : string
    {
        return AssistantsFiles::class;
    }
    public function create(string $assistantId, array $parameters) : AssistantFileResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function retrieve(string $assistantId, string $fileId) : AssistantFileResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function delete(string $assistantId, string $fileId) : AssistantFileDeleteResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function list(string $assistantId, array $parameters = []) : AssistantFileListResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
}
