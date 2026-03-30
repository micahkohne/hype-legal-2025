<?php

namespace BoldMinded\Carson\Providers;

use BoldMinded\Carson\Dependency\Litzinger\Basee\Setting;
use BoldMinded\Carson\Dependency\Symfony\Component\HttpClient\HttpClient;

class AnthropicProvider implements ProviderInterface
{
    private Setting $setting;
    private $client;

    public function __construct(Setting $setting)
    {
        $this->setting = $setting;
        $this->client = HttpClient::create([
            'base_uri' => 'https://api.anthropic.com',
            'headers' => [
                'x-api-key' => $this->setting->get('anthropic_key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
        ]);
    }

    public function request(string $prompt, string $content, string $requestType): array
    {
        if ($requestType === 'image') {
            return $this->getFileDescription($prompt, $content);
        }

        $body = [
            'model' => $this->setting->get('anthropic_model'),
            'max_tokens' => 4096,
            'system' => 'You are a helpful assistant.',
            'messages' => [
                ['role' => 'user', 'content' => $prompt . $content],
            ],
        ];

        $temperature = $this->setting->get('anthropic_temperature');
        $topP = $this->setting->get('anthropic_top_p');

        // Anthropic does not allow both temperature and top_p in the same request.
        // Temperature takes priority when both are set.
        if ($temperature !== '') {
            $body['temperature'] = (float) $temperature;
        } elseif ($topP !== '') {
            $body['top_p'] = (float) $topP;
        }

        $topK = $this->setting->get('anthropic_top_k');
        if ($topK !== '') {
            $body['top_k'] = (int) $topK;
        }

        $response = $this->client->request('POST', '/v1/messages', [
            'json' => $body,
        ]);

        return $this->parseResponse($response);
    }

    private function getFileDescription(string $prompt, string $fileId): array
    {
        ee()->load->library('file_field');
        $file = ee()->file_field->parse_field($fileId);

        $url = $file['url'] ?? null;

        if (!$url) {
            return [
                'errorMessage' => sprintf('Unable to find a file with the ID %d', $fileId),
            ];
        }

        $body = [
            'model' => $this->setting->get('anthropic_image_model'),
            'max_tokens' => 4096,
            'system' => 'You are a helpful assistant.',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'url',
                                'url' => $url,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->client->request('POST', '/v1/messages', [
            'json' => $body,
        ]);

        return $this->parseResponse($response);
    }

    private function parseResponse($response): array
    {
        $data = $response->toArray(false);
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            $errorMessage = $data['error']['message'] ?? 'Unknown error';
            ee()->logger->developer(sprintf('Carson: %s', $errorMessage));
            throw new \RuntimeException(sprintf('Anthropic API error (%d): %s', $statusCode, $errorMessage));
        }

        $content = $data['content'][0]['text'] ?? '';

        if ($this->isJson($content)) {
            $content = json_decode($content, true);
        }

        return [
            'content' => $content,
            'meta' => [
                'model' => $data['model'] ?? '',
                'usage' => $data['usage'] ?? [],
            ],
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
