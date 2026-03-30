<?php

declare (strict_types=1);
namespace BoldMinded\Carson\Dependency\Bamarni\Composer\Bin\Config;

use BoldMinded\Carson\Dependency\Composer\Config as ComposerConfig;
use BoldMinded\Carson\Dependency\Composer\Factory;
use BoldMinded\Carson\Dependency\Composer\Json\JsonFile;
use BoldMinded\Carson\Dependency\Composer\Json\JsonValidationException;
use BoldMinded\Carson\Dependency\Seld\JsonLint\ParsingException;
final class ConfigFactory
{
    /**
     * @throws JsonValidationException
     * @throws ParsingException
     */
    public static function createConfig() : ComposerConfig
    {
        $config = Factory::createConfig();
        $file = new JsonFile(Factory::getComposerFile());
        if (!$file->exists()) {
            return $config;
        }
        $file->validateSchema(JsonFile::LAX_SCHEMA);
        $config->merge($file->read());
        return $config;
    }
    private function __construct()
    {
    }
}
