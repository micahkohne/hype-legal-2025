<?php

namespace BoldMinded\Carson\Actions;

use ExpressionEngine\Service\Addon\Controllers\Action\AbstractRoute;
use BoldMinded\Carson\Services\AiRequest;

class FetchData extends AbstractRoute
{
    public function process(): void
    {
        $content = ee('Request')->post('content');
        $prompt = ee('Request')->post('prompt');
        $targets = ee('Request')->post('target');
        $requestType = ee('Request')->post('requestType');

        $providerResponse = AiRequest::make(
            $content,
            $prompt,
            $requestType ?: 'text'
        );

        if ($targets && count($targets) > 0) {
            $contentArray = [];
            foreach ($targets as $target) {
                $contentArray[$target] = $providerResponse['content'] ?? '';
            }
            $response = $contentArray;
        } else {
            $response = $providerResponse;
        }

        $this->sendJsonResponse($response);
    }

    private function sendJsonResponse(string|array $data): void
    {
        if (is_array($data)) {
            $data = json_encode($data);
        }

        ee()->output->enable_profiler(false);
        @header('Content-Type: text/html; charset=UTF-8');
        exit($data);
    }
}
