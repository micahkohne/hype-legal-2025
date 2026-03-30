<?php

declare (strict_types=1);
namespace BoldMinded\Carson\Dependency\OpenAI\Responses\Models;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\ResponseContract;
use BoldMinded\Carson\Dependency\OpenAI\Contracts\ResponseHasMetaInformationContract;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Concerns\ArrayAccessible;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Concerns\HasMetaInformation;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Meta\MetaInformation;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Responses\Concerns\Fakeable;
/**
 * @implements ResponseContract<array{id: string, object: string, created: int, owned_by: string}>
 */
final class RetrieveResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{id: string, object: string, created: int, owned_by: string}>
     */
    use ArrayAccessible;
    use Fakeable;
    use HasMetaInformation;
    private function __construct(public readonly string $id, public readonly string $object, public readonly int $created, public readonly string $ownedBy, private readonly MetaInformation $meta)
    {
    }
    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{id: string, object: string, created: int, owned_by: string}  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta) : self
    {
        return new self($attributes['id'], $attributes['object'], $attributes['created'], $attributes['owned_by'], $meta);
    }
    /**
     * {@inheritDoc}
     */
    public function toArray() : array
    {
        return ['id' => $this->id, 'object' => $this->object, 'created' => $this->created, 'owned_by' => $this->ownedBy];
    }
}
