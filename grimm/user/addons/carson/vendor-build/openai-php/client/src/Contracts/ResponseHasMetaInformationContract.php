<?php

declare (strict_types=1);
namespace BoldMinded\Carson\Dependency\OpenAI\Contracts;

use BoldMinded\Carson\Dependency\OpenAI\Responses\Meta\MetaInformation;
interface ResponseHasMetaInformationContract
{
    public function meta() : MetaInformation;
}
