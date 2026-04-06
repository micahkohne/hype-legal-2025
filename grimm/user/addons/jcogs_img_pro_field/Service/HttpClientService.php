<?php

/**
 * JCOGS Image Pro Field - HttpClientService
 * =========================================
 * Lightweight HTTP client wrapper for add-on services.
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.2
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 */

namespace JCOGSDesign\JcogsImgProField\Service;

class HttpClientService
{
    /**
     * Execute a JSON GET request.
     *
     * @param string $url
     * @param int $timeoutSeconds
     * @param array $headers
     * @return array
     */
    public function getJson(string $url, int $timeoutSeconds = 3, array $headers = []): array
    {
        $result = [
            'ok' => false,
            'status_code' => null,
            'body' => null,
            'data' => null,
            'error' => null,
        ];

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $result['error'] = 'Invalid URL';
            return $result;
        }

        $defaultHeaders = [
            'Accept: application/json',
            'Cache-Control: no-cache',
        ];

        $requestHeaders = array_values(array_unique(array_merge($defaultHeaders, $headers)));

        try {
            $response = ee('Curl')->get($url, [
                'CURLOPT_CONNECTTIMEOUT' => max(1, $timeoutSeconds),
                'CURLOPT_TIMEOUT' => max(1, $timeoutSeconds),
                'CURLOPT_HTTPHEADER' => $requestHeaders,
                'CURLOPT_RETURNTRANSFER' => true,
            ])->exec();

            if (is_string($response) && $response !== '') {
                $result['body'] = $response;
                $decoded = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $result['ok'] = true;
                    $result['data'] = $decoded;
                    return $result;
                }

                $result['error'] = 'Invalid JSON response';
                return $result;
            }
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        if (!function_exists('curl_init')) {
            if (!$result['error']) {
                $result['error'] = 'No HTTP transport available';
            }
            return $result;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            $result['error'] = 'Failed to initialize cURL';
            return $result;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, max(1, $timeoutSeconds));
        curl_setopt($ch, CURLOPT_TIMEOUT, max(1, $timeoutSeconds));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            $result['error'] = $curlError ?: 'HTTP request failed';
            $result['status_code'] = $statusCode ?: null;
            return $result;
        }

        $result['body'] = $body;
        $result['status_code'] = $statusCode ?: null;

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $result['error'] = 'Invalid JSON response';
            return $result;
        }

        $result['ok'] = true;
        $result['data'] = $decoded;

        return $result;
    }
}
