<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Responses\Embeddings\CreateResponse;
interface EmbeddingsContract
{
    /**
     * Creates an embedding vector representing the input text.
     *
     * @see https://platform.openai.com/docs/api-reference/embeddings/create
     *
     * @param  array<string, mixed>  $parameters
     */
    public function create(array $parameters) : CreateResponse;
}
