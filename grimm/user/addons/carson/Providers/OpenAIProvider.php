<?php

namespace BoldMinded\Carson\Providers;

use BoldMinded\Carson\Dependency\Litzinger\Basee\Setting;
use BoldMinded\Carson\Dependency\OpenAI\Factory;

class OpenAIProvider implements ProviderInterface
{
    private Setting $setting;
    private $client;

    public function __construct(Setting $setting)
    {
        $this->setting = $setting;
        // Use the Factory directly to avoid collisions with other add-ons calling OpenAI::client() directly.
        // When that happens, there is usually a 'use OpenAI' at the top of the file which causes a conflict,
        // even though we've used PhpScoper to prefix all the dependencies.
        $this->client = (new Factory())
            ->withApiKey($this->setting->get('open_ai_key'))
            ->withHttpHeader('OpenAI-Beta', 'assistants=v1')
            ->make();
    }

    public function request(string $prompt, string $content, string $requestType): array
    {
        if ($requestType === 'image') {
            return $this->getFileDescription($prompt, $content);
        }

        $response = $this->client->chat()->create([
            'model' => $this->setting->get('open_ai_model'),
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                // @todo Add way to select 3-5 other entries on the site, and send all it's text as context
                // ['role' => 'system', 'content' => 'Use the following to help provide further context to influence your response.'],
                ['role' => 'user', 'content' => $prompt . $content],
            ],
            'temperature' => (float) $this->setting->get('temperature') ?: 1,
            'frequency_penalty' => (float) $this->setting->get('frequency_penalty') ?: 0,
            'presence_penalty' => (float) $this->setting->get('presence_penalty') ?: 0,

            // @todo add options for these?
            // https://medium.com/nerd-for-tech/model-parameters-in-openai-api-161a5b1f8129
            // 'top_p',
            // 'n',
            // 'stop',
            // 'max_tokens',
        ]);

        $content = $response->choices[0]->message->content ?? '';

        if ($this->isJson($content)) {
            $content = json_decode($content, true);
        }

        return [
            'content' => $content,
            'meta' => $response->meta(),
        ];
    }

    private function getFileDescription(string $prompt, string $fileId)
    {
        ee()->load->library('file_field');
        $file = ee()->file_field->parse_field($fileId);

        $url = $file['url'] ?? null;

        if (!$url) {
            return [
                'errorMessage' => sprintf('Unable to find a file with the ID %d', $fileId),
            ];
        }

        $response = $this->client->chat()->create([
            'model' => $this->setting->get('open_ai_image_model'),
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $url,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        return [
            'content' => $response->choices[0]->message->content ?? '',
            'meta' => $response->meta(),
        ];
    }

    private function isJson($value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }
}
