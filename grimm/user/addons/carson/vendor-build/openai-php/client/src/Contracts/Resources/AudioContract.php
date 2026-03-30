<?php

namespace BoldMinded\Carson\Dependency\OpenAI\Contracts\Resources;

use BoldMinded\Carson\Dependency\OpenAI\Responses\Audio\SpeechStreamResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Audio\TranscriptionResponse;
use BoldMinded\Carson\Dependency\OpenAI\Responses\Audio\TranslationResponse;
interface AudioContract
{
    /**
     * Generates audio from the input text.
     *
     * @see https://platform.openai.com/docs/api-reference/audio/createSpeech
     *
     * @param  array<string, mixed>  $parameters
     */
    public function speech(array $parameters) : string;
    /**
     * Generates streamed audio from the input text.
     *
     * @see https://platform.openai.com/docs/api-reference/audio/createSpeech
     *
     * @param  array<string, mixed>  $parameters
     */
    public function speechStreamed(array $parameters) : SpeechStreamResponse;
    /**
     * Transcribes audio into the input language.
     *
     * @see https://platform.openai.com/docs/api-reference/audio/createTranscription
     *
     * @param  array<string, mixed>  $parameters
     */
    public function transcribe(array $parameters) : TranscriptionResponse;
    /**
     * Translates audio into English.
     *
     * @see https://platform.openai.com/docs/api-reference/audio/createTranslation
     *
     * @param  array<string, mixed>  $parameters
     */
    public function translate(array $parameters) : TranslationResponse;
}
