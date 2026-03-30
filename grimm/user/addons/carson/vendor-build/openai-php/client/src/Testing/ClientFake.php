<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\ClientContract;
use BoldMinded\Carson\Dependency\OpenAI\Contracts\ResponseContract;
use BoldMinded\Carson\Dependency\OpenAI\Contracts\ResponseStreamContract;
use BoldMinded\Carson\Dependency\OpenAI\Responses\StreamResponse;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Requests\TestRequest;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\AssistantsTestResource;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\AudioTestResource;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\ChatTestResource;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\CompletionsTestResource;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\EditsTestResource;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\EmbeddingsTestResource;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\FilesTestResource;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\FineTunesTestResource;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\FineTuningTestResource;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\ImagesTestResource;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\ModelsTestResource;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\ModerationsTestResource;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\ThreadsTestResource;
use PHPUnit\Framework\Assert as PHPUnit;
use Throwable;
class ClientFake implements ClientContract
{
    /**
     * @var array<array-key, TestRequest>
     */
    private array $requests = [];
    /**
     * @param  array<array-key, ResponseContract|StreamResponse|string>  $responses
     */
    public function __construct(protected array $responses = [])
    {
    }
    /**
     * @param  array<array-key, Response>  $responses
     */
    public function addResponses(array $responses) : void
    {
        $this->responses = [...$this->responses, ...$responses];
    }
    public function assertSent(string $resource, callable|int|null $callback = null) : void
    {
        if (\is_int($callback)) {
            $this->assertSentTimes($resource, $callback);
            return;
        }
        PHPUnit::assertTrue($this->sent($resource, $callback) !== [], "The expected [{$resource}] request was not sent.");
    }
    private function assertSentTimes(string $resource, int $times = 1) : void
    {
        $count = \count($this->sent($resource));
        PHPUnit::assertSame($times, $count, "The expected [{$resource}] resource was sent {$count} times instead of {$times} times.");
    }
    /**
     * @return mixed[]
     */
    private function sent(string $resource, ?callable $callback = null) : array
    {
        if (!$this->hasSent($resource)) {
            return [];
        }
        $callback = $callback ?: fn(): bool => \true;
        return \array_filter($this->resourcesOf($resource), fn(TestRequest $resource) => $callback($resource->method(), ...$resource->args()));
    }
    private function hasSent(string $resource) : bool
    {
        return $this->resourcesOf($resource) !== [];
    }
    public function assertNotSent(string $resource, ?callable $callback = null) : void
    {
        PHPUnit::assertCount(0, $this->sent($resource, $callback), "The unexpected [{$resource}] request was sent.");
    }
    public function assertNothingSent() : void
    {
        $resourceNames = \implode(separator: ', ', array: \array_map(fn(TestRequest $request): string => $request->resource(), $this->requests));
        PHPUnit::assertEmpty($this->requests, 'The following requests were sent unexpectedly: ' . $resourceNames);
    }
    /**
     * @return array<array-key, TestRequest>
     */
    private function resourcesOf(string $type) : array
    {
        return \array_filter($this->requests, fn(TestRequest $request): bool => $request->resource() === $type);
    }
    public function record(TestRequest $request) : ResponseContract|ResponseStreamContract|string
    {
        $this->requests[] = $request;
        $response = \array_shift($this->responses);
        if (\is_null($response)) {
            throw new \Exception('No fake responses left.');
        }
        if ($response instanceof Throwable) {
            throw $response;
        }
        return $response;
    }
    public function completions() : CompletionsTestResource
    {
        return new CompletionsTestResource($this);
    }
    public function chat() : ChatTestResource
    {
        return new ChatTestResource($this);
    }
    public function embeddings() : EmbeddingsTestResource
    {
        return new EmbeddingsTestResource($this);
    }
    public function audio() : AudioTestResource
    {
        return new AudioTestResource($this);
    }
    public function edits() : EditsTestResource
    {
        return new EditsTestResource($this);
    }
    public function files() : FilesTestResource
    {
        return new FilesTestResource($this);
    }
    public function models() : ModelsTestResource
    {
        return new ModelsTestResource($this);
    }
    public function fineTunes() : FineTunesTestResource
    {
        return new FineTunesTestResource($this);
    }
    public function fineTuning() : FineTuningTestResource
    {
        return new FineTuningTestResource($this);
    }
    public function moderations() : ModerationsTestResource
    {
        return new ModerationsTestResource($this);
    }
    public function images() : ImagesTestResource
    {
        return new ImagesTestResource($this);
    }
    public function assistants() : AssistantsTestResource
    {
        return new AssistantsTestResource($this);
    }
    public function threads() : ThreadsTestResource
    {
        return new ThreadsTestResource($this);
    }
}
