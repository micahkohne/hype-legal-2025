<?php

/**
 * JCOGS Image Pro Field - ActionResponder
 *========================================
 * Shared JSON responder for ExpressionEngine ACT endpoints.
 *
 * Standardises payload shape and prevents exception leakage unless debug is
 * explicitly enabled (superadmin + field setting).
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.0
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 * @since      0.1.6
 */

namespace JCOGSDesign\JcogsImgProField\Service;

/**
 * Normalises success/error JSON responses for ACT endpoints.
 */
final class ActionResponder
{
    private AuthService $auth;
    private FieldSettingsService $fieldSettings;

    public function __construct()
    {
        $this->auth = ee('jcogs_img_pro_field:AuthService');
        $this->fieldSettings = ee('jcogs_img_pro_field:FieldSettingsService');
    }

    public function ok(array $payload = []): array
    {
        if (array_key_exists('success', $payload)) {
            return $payload;
        }

        return ['success' => true] + $payload;
    }

    public function error(string $code, array $extra = [], ?\Throwable $e = null, ?int $fieldId = null): array
    {
        $payload = ['success' => false, 'error' => $code];

        foreach (['success', 'error'] as $reserved) {
            if (array_key_exists($reserved, $extra)) {
                unset($extra[$reserved]);
            }
        }

        $payload += $extra;

        if ($e !== null && $this->isDebugEnabled($fieldId)) {
            $payload['debug'] = [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ];
        }

        return $payload;
    }

    public function serverError(\Throwable $e, ?int $fieldId = null): array
    {
        return $this->error('server_error', [], $e, $fieldId);
    }

    /**
     * Normalises legacy payloads that only include e.g. ['error' => '...'].
     */
    public function normalise(array $payload, ?int $fieldId = null): array
    {
        if (array_key_exists('success', $payload)) {
            return $payload;
        }

        if (isset($payload['error'])) {
            $code = (string) $payload['error'];
            $extra = $payload;
            unset($extra['error']);
            return $this->error($code, $extra, null, $fieldId);
        }

        return $this->ok($payload);
    }

    public function isDebugEnabled(?int $fieldId = null): bool
    {
        if (! $this->auth->canUseDebugFeatures()) {
            return false;
        }

        if ($fieldId === null || $fieldId <= 0) {
            return false;
        }

        $settings = $this->fieldSettings->getForFieldId($fieldId);
        return (($settings['enable_debug'] ?? 'n') === 'y');
    }
}
