<?php

declare (strict_types=1);
namespace BoldMinded\Carson\Dependency\OpenAI\Resources\Concerns;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\TransporterContract;
trait Transportable
{
    /**
     * Creates a Client instance with the given API token.
     */
    public function __construct(private readonly TransporterContract $transporter)
    {
        // ..
    }
}
