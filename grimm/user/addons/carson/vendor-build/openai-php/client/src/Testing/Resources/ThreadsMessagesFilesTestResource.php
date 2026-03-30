<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources\ThreadsMessagesFilesContract;
use BoldMinded\Carson\Dependency\OpenAI\Resources\ThreadsMessagesFiles;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Threads\Messages\Files\ThreadMessageFileListResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Threads\Messages\Files\ThreadMessageFileResponse;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\Concerns\Testable;
final class ThreadsMessagesFilesTestResource implements ThreadsMessagesFilesContract
{
    use Testable;
    public function resource() : string
    {
        return ThreadsMessagesFiles::class;
    }
    public function retrieve(string $threadId, string $messageId, string $fileId) : ThreadMessageFileResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function list(string $threadId, string $messageId, array $parameters = []) : ThreadMessageFileListResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
}
