<?php

declare (strict_types=1);
namespace BoldMinded\Carson\Dependency\OpenAI\Responses\Concerns;

use BoldMinded\Carson\Dependency\OpenAI\Responses\Meta\MetaInformation;
trait HasMetaInformation
{
    public function meta() : MetaInformation
    {
        return $this->meta;
    }
}
