<?php

declare (strict_types=1);
namespace BoldMinded\Carson\Dependency\OpenAI\Responses\Moderations;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\ResponseContract;
use BoldMinded\Carson\Dependency\OpenAI\Contracts\ResponseHasMetaInformationContract;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Concerns\ArrayAccessible;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Concerns\HasMetaInformation;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Meta\MetaInformation;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Responses\Concerns\Fakeable;
/**
 * @implements ResponseContract<array{id: string, model: string, results: array<int, array{categories: array<string, bool>, category_scores: array<string, float>, flagged: bool}>}>
 */
final class CreateResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{id: string, model: string, results: array<int, array{categories: array<string, bool>, category_scores: array<string, float>, flagged: bool}>}>
     */
    use ArrayAccessible;
    use Fakeable;
    use HasMetaInformation;
    /**
     * @param  array<int, CreateResponseResult>  $results
     */
    private function __construct(public readonly string $id, public readonly string $model, public readonly array $results, private readonly MetaInformation $meta)
    {
    }
    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{id: string, model: string, results: array<int, array{categories: array<string, bool>, category_scores: array<string, float>, flagged: bool}>}  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta) : self
    {
        $results = \array_map(fn(array $result): CreateResponseResult => CreateResponseResult::from($result), $attributes['results']);
        return new self($attributes['id'], $attributes['model'], $results, $meta);
    }
    /**
     * {@inheritDoc}
     */
    public function toArray() : array
    {
        return ['id' => $this->id, 'model' => $this->model, 'results' => \array_map(static fn(CreateResponseResult $result): array => $result->toArray(), $this->results)];
    }
}
