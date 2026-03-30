<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\Concerns;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\ResponseContract;
use BoldMinded\Carson\Dependency\OpenAI\Contracts\ResponseStreamContract;
use BoldMinded\Carson\Dependency\OpenAI\Testing\ClientFake;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Requests\TestRequest;
trait Testable
{
    public function __construct(protected ClientFake $fake)
    {
    }
    protected abstract function resource() : string;
    /**
     * @param  array<string, mixed>  $args
     */
    protected function record(string $method, array $args = []) : ResponseContract|ResponseStreamContract|string
    {
        return $this->fake->record(new TestRequest($this->resource(), $method, $args));
    }
    public function assertSent(callable|int|null $callback = null) : void
    {
        $this->fake->assertSent($this->resource(), $callback);
    }
    public function assertNotSent(callable|int|null $callback = null) : void
    {
        $this->fake->assertNotSent($this->resource(), $callback);
    }
}
