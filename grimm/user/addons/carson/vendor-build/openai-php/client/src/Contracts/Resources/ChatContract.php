<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Responses\Chat\CreateResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Chat\CreateStreamedResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\StreamResponse;
interface ChatContract
{
    /**
     * Creates a completion for the chat message
     *
     * @see https://platform.openai.com/docs/api-reference/chat/create
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(array $parameters) : CreateResponse;
    /**
     * Creates a streamed completion for the chat message
     *
     * @see https://platform.openai.com/docs/api-reference/chat/create
     *
     * @param  array<string, mixed>  $parameters
     * @return StreamResponse<CreateStreamedResponse>
     */
    public function createStreamed(array $parameters) : StreamResponse;
}
