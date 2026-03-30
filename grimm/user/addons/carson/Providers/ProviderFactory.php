<?php

namespace BoldMinded\Carson\Providers;

use BoldMinded\Carson\Dependency\Litzinger\Basee\Setting;

class ProviderFactory
{
    public static function create(string $providerName, Setting $setting)
    {
        $providerClassName = $providerName . 'Provider';
        $classPath = __NAMESPACE__ . '\\' . $providerClassName;

        return new $classPath($setting);
    }
}
