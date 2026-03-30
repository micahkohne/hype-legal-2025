<?php

namespace BoldMinded\Carson\Services;

use BoldMinded\Carson\Providers\ProviderFactory;
use BoldMinded\Carson\Providers\ProviderInterface;

class AiRequest
{
    public static function make(
        string $content,
        string $prompt,
        string $requestType = 'text'
    ): array
    {
        $setting = ee('carson:Setting');
        ee()->load->library('logger');

        $errorMessage = 'There was an error with the AI request. Please check your developer logs.';

        // The JS will end up putting this in the form fields, hopefully the user should see them :)
        $errorResponse = [
            'errorMessage' => $errorMessage,
        ];

        $providerName = $setting->get('ai_provider') ?: 'OpenAI';
        $apiKeyField = $providerName === 'Anthropic' ? 'anthropic_key' : 'open_ai_key';

        if (!$setting->get($apiKeyField)) {
            ee()->logger->developer(sprintf('Carson: could not make request to %s. No API key provided.', $providerName));

            return $errorResponse;
        }

        try {
            if (!$prompt) {
                ee()->logger->developer('Carson: request did not include a prompt.');

                return [];
            }

            /** @var ProviderInterface $provider */
            $provider = ProviderFactory::create($providerName, $setting);
            $providerResponse = $provider->request($prompt, $content, $requestType);

            return $providerResponse;
        } catch (\Exception $exception) {
            ee()->logger->developer(sprintf('Carson: %s', $exception->getMessage()));

            return $errorResponse;
        }
    }
}
