<?php

declare (strict_types=1);
namespace BoldMinded\Carson\Dependency\OpenAI\ValueObjects\Transporter;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\StringableContract;
/**
 * @internal
 */
final class BaseUri implements StringableContract
{
    /**
     * Creates a new Base URI value object.
     */
    private function __construct(private readonly string $baseUri)
    {
        // ..
    }
    /**
     * Creates a new Base URI value object.
     */
    public static function from(string $baseUri) : self
    {
        return new self($baseUri);
    }
    /**
     * {@inheritdoc}
     */
    public function toString() : string
    {
        foreach (['http://', 'https://'] as $protocol) {
            if (\str_starts_with($this->baseUri, $protocol)) {
                return "{$this->baseUri}/";
            }
        }
        return "https://{$this->baseUri}/";
    }
}
