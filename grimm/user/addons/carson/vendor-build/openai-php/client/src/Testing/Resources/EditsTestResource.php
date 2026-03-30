<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources\EditsContract;
use BoldMinded\Carson\Dependency\OpenAI\Resources\Edits;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Edits\CreateResponse;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\Concerns\Testable;
final class EditsTestResource implements EditsContract
{
    use Testable;
    protected function resource() : string
    {
        return Edits::class;
    }
    public function create(array $parameters) : CreateResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
}
