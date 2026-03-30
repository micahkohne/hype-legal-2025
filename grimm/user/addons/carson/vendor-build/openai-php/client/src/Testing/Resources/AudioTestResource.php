<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Testing\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources\AudioContract;
use BoldMinded\Carson\Dependency\OpenAI\Resources\Audio;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Audio\SpeechStreamResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Audio\TranscriptionResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Audio\TranslationResponse;
use BoldMinded\Carson\Dependency\OpenAI\Testing\Resources\Concerns\Testable;
final class AudioTestResource implements AudioContract
{
    use Testable;
    protected function resource() : string
    {
        return Audio::class;
    }
    public function speech(array $parameters) : string
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function speechStreamed(array $parameters) : SpeechStreamResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function transcribe(array $parameters) : TranscriptionResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
    public function translate(array $parameters) : TranslationResponse
    {
        return $this->record(__FUNCTION__, \func_get_args());
    }
}
