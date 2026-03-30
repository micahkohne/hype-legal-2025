<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources\ImagesContract;
use BoldMinded\Carson\Dependency\OpenAI\Resources\Images;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Images\CreateResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Images\EditResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Images\VariationResponse;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\Concerns\Testable;
final class ImagesTestResource implements ImagesContract
{
    use Testable;
    protected function resource() : string
    {
        return Images::class;
    }
    public function create(array $parameters) : CreateResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function edit(array $parameters) : EditResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function variation(array $parameters) : VariationResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
}
