<?php

declare (strict_types=1);
namespace BoldMinded\Carson\Dependency\OpenAI\Responses\Threads\Runs;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\ResponseContract;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Concerns\ArrayAccessible;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Responses\Concerns\Fakeable;
/**
 * @implements ResponseContract<array{type: string}>
 */
final class ThreadRunResponseToolCodeInterpreter implements ResponseContract
{
    /**
     * @use ArrayAccessible<array{type: string}>
     */
    use ArrayAccessible;
    use Fakeable;
    private function __construct(public string $type)
    {
    }
    /**
     * Acts as static factory, and returns a new Response instance.
     *
     * @param  array{type: 'code_interpreter'}  $attributes
     */
    public static function from(array $attributes) : self
    {
        return new self($attributes['type']);
    }
    /**
     * {@inheritDoc}
     */
    public function toArray() : array
    {
        return ['type' => $this->type];
    }
}
