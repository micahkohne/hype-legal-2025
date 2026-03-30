<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Responses\Fixtures\FineTunes;

final class ListEventsResponseFixture
{
    public const ATTRIBUTES = ['object' => 'list', 'data' => [['object' => 'fine-tune-event', 'created_at' => 1614807352, 'level' => 'info', 'message' => 'Job enqueued. Waiting for jobs ahead to complete. Queue number =>  0.']]];
}
