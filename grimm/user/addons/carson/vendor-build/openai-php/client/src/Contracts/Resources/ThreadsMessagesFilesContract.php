<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Responses\Threads\Messages\Files\ThreadMessageFileListResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Threads\Messages\Files\ThreadMessageFileResponse;
interface ThreadsMessagesFilesContract
{
    /**
     * Retrieves a message file.
     *
     * @see https://platform.openai.com/docs/api-reference/messages/getMessageFile
     */
    public function retrieve(string $threadId, string $messageId, string $fileId) : ThreadMessageFileResponse;
    /**
     * Returns a list of message files.
     *
     * @see https://platform.openai.com/docs/api-reference/messages/listMessageFiles
     *
     * @param  array<string, mixed>  $parameters
     */
    public function list(string $threadId, string $messageId, array $parameters = []) : ThreadMessageFileListResponse;
}
