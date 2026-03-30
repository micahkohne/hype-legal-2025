<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources\ThreadsRunsStepsContract;
use BoldMinded\Carson\Dependency\OpenAI\Resources\ThreadsRunsSteps;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Threads\Runs\Steps\ThreadRunStepListResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Threads\Runs\Steps\ThreadRunStepResponse;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\Concerns\Testable;
class ThreadsRunsStepsTestResource implements ThreadsRunsStepsContract
{
    use Testable;
    public function resource() : string
    {
        return ThreadsRunsSteps::class;
    }
    public function retrieve(string $threadId, string $runId, string $stepId) : ThreadRunStepResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function list(string $threadId, string $runId, array $parameters = []) : ThreadRunStepListResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
}
