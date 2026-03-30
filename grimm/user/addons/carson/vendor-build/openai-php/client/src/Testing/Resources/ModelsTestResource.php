<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources\ModelsContract;
use BoldMinded\Carson\Dependency\OpenAI\Resources\Models;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Models\DeleteResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Models\ListResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Models\RetrieveResponse;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\Concerns\Testable;
final class ModelsTestResource implements ModelsContract
{
    use Testable;
    protected function resource() : string
    {
        return Models::class;
    }
    public function list() : ListResponse
    {
        return $this->record(__FUNCTION__);
    }
    public function retrieve(string $model) : RetrieveResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function delete(string $model) : DeleteResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
}
