<?php

declare (strict_types=1);
namespace BoldMinded\Carson\Dependency\OpenAI\Responses\Assistants\Files;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\ResponseContract;
use BoldMinded\Carson\Dependency\OpenAI\Contracts\ResponseHasMetaInformationContract;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Concerns\ArrayAccessible;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Concerns\HasMetaInformation;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Meta\MetaInformation;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Responses\Concerns\Fakeable;
/**
 * @implements ResponseContract<array{id: string, object: string, created_at: int, assistant_id: string}>
 */
final class AssistantFileResponse implements ResponseContract, ResponseHasMetaInformationContract
{
    /**
     * @use ArrayAccessible<array{id: string, object: string, created_at: int, assistant_id: string}>
     */
    use ArrayAccessible;
    use Fakeable;
    use HasMetaInformation;
    private function __construct(public string $id, public string $object, public int $createdAt, public string $assistantId, private readonly MetaInformation $meta)
    {
    }
    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{id: string, object: string, created_at: int, assistant_id: string}  $attributes
     */
    public static function from(array $attributes, MetaInformation $meta) : self
    {
        return new self($attributes['id'], $attributes['object'], $attributes['created_at'], $attributes['assistant_id'], $meta);
    }
    /**
     * {@inheritDoc}
     */
    public function toArray() : array
    {
        return ['id' => $this->id, 'object' => $this->object, 'created_at' => $this->createdAt, 'assistant_id' => $this->assistantId];
    }
}
