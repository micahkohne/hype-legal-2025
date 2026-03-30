<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources\ModerationsContract;
use BoldMinded\Carson\Dependency\OpenAI\Resources\Moderations;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Moderations\CreateResponse;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\Concerns\Testable;
final class ModerationsTestResource implements ModerationsContract
{
    use Testable;
    protected function resource() : string
    {
        return Moderations::class;
    }
    public function create(array $parameters) : CreateResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
}
