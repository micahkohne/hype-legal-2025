<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return static function (RectorConfig $config): void {
    $config->bootstrapFiles([
        __DIR__ . '/stubs.php'
    ]);

    $config->autoloadPaths([
        __DIR__ . '/../../../ee/vendor/autoload.php',
    ]);

    // Limit scope to the add-on
    $config->paths([
        __DIR__,
    ]);

    // Skip third-party & legacy entry points
    $config->skip([
        __DIR__ . '/php7',
        __DIR__ . '/vendor',
        NewMethodCallWithoutParenthesesRector::class,
    ]);

    $config->phpVersion(PhpVersion::PHP_84);
    $config->sets([
        LevelSetList::UP_TO_PHP_84,
        SetList::TYPE_DECLARATION,
    ]);

    $config->importNames();
};
