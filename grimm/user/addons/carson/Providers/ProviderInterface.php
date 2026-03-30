<?php

namespace BoldMinded\Carson\Providers;

use BoldMinded\Carson\Dependency\Litzinger\Basee\Setting;

interface ProviderInterface
{
    public function __construct(Setting $setting);

    public function request(string $prompt, string $content, string $requestType): array;
}
