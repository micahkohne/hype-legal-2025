<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources\EmbeddingsContract;
use BoldMinded\Carson\Dependency\OpenAI\Resources\Embeddings;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Embeddings\CreateResponse;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\Concerns\Testable;
final class EmbeddingsTestResource implements EmbeddingsContract
{
    use Testable;
    protected function resource() : string
    {
        return Embeddings::class;
    }
    public function create(array $parameters) : CreateResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
}
