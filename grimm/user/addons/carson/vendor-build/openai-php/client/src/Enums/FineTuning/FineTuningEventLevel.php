<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Enums\FineTuning;

enum FineTuningEventLevel : string
{
    case Info = 'info';
    case Warning = 'warn';
    case Error = 'error';
}
