<?php

declare (strict_types=1);
namespace BoldMinded\Carson\Dependency;

use BoldMinded\Carson\Dependency\OpenAI\Client;
use BoldMinded\Carson\Dependency\OpenAI\Factory;
final class OpenAI
{
    /**
     * Creates a new Open AI Client with the given API token.
     */
    public static function client(string $apiKey, ?string $organization = null) : Client
    {
        return self::factory()->withApiKey($apiKey)->withOrganization($organization)->withHttpHeader('OpenAI-Beta', 'assistants=v1')->make();
    }
    /**
     * Creates a new factory instance to configure a custom Open AI Client
     */
    public static function factory() : Factory
    {
        return new Factory();
    }
}
\class_alias('BoldMinded\\Carson\\Dependency\\OpenAI', 'OpenAI', \false);
