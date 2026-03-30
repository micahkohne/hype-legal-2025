<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources\FilesContract;
use BoldMinded\Carson\Dependency\OpenAI\Resources\Files;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Files\CreateResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Files\DeleteResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Files\ListResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Files\RetrieveResponse;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\Concerns\Testable;
final class FilesTestResource implements FilesContract
{
    use Testable;
    protected function resource() : string
    {
        return Files::class;
    }
    public function list() : ListResponse
    {
        return $this->record(__FUNCTION__);
    }
    public function retrieve(string $file) : RetrieveResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function download(string $file) : string
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function upload(array $parameters) : CreateResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function delete(string $file) : DeleteResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
}
