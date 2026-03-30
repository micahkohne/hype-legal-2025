<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources\FineTunesContract;
use BoldMinded\Carson\Dependency\OpenAI\Resources\FineTunes;
use BoldMinded\Carson\Dependency\OpenAI\Responses\FineTunes\ListEventsResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\FineTunes\ListResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\FineTunes\RetrieveResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\StreamResponse;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\Concerns\Testable;
final class FineTunesTestResource implements FineTunesContract
{
    use Testable;
    protected function resource() : string
    {
        return FineTunes::class;
    }
    public function create(array $parameters) : RetrieveResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function list() : ListResponse
    {
        return $this->record(__FUNCTION__);
    }
    public function retrieve(string $fineTuneId) : RetrieveResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function cancel(string $fineTuneId) : RetrieveResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function listEvents(string $fineTuneId) : ListEventsResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function listEventsStreamed(string $fineTuneId) : StreamResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
}
