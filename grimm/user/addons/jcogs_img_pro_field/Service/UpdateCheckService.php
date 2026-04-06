<?php

/**
 * JCOGS Image Pro Field - UpdateCheckService
 * ==========================================
 * Fetches and caches update metadata from JCOGS update endpoint.
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

class UpdateCheckService
{
    private const CACHE_KEY = 'jcogs_img_pro_field/update_check/v1';
    private const CACHE_TTL_SECONDS = 86400;
    private const UPDATE_ENDPOINT = 'https://jcogs.net/updates/v1/latest';
    private const ADDON_SHORT_NAME = 'jcogs_img_pro_field';
    private const CHANGELOG_FALLBACK_URL = 'https://jcogs.net/documentation/jcogs_img_pro_field/jcogs_img_pro_field-changelog';

    /** @var HttpClientService */
    private $httpClient;

    /** @var Utilities */
    private $utilities;

    /** @var UpdateMarkerScriptService */
    private $updateMarkerScriptService;

    public function __construct(HttpClientService $httpClient, Utilities $utilities, UpdateMarkerScriptService $updateMarkerScriptService)
    {
        $this->httpClient = $httpClient;
        $this->utilities = $utilities;
        $this->updateMarkerScriptService = $updateMarkerScriptService;
    }

    /**
     * @return array{status:string,checked_at:int|null,local_version:string,remote_version:string|null,changelog_url:string|null,endpoint:string,error:string|null}
     */
    public function getStatus(): array
    {
        $localVersion = $this->getInstalledVersion();

        $cached = $this->getCached();
        if (is_array($cached) && !empty($cached['checked_at'])) {
            $cachedLocalVersion = (string) ($cached['local_version'] ?? '');
            $checkedAt = (int) $cached['checked_at'];
            if (
                $cachedLocalVersion === $localVersion
                && $checkedAt > 0
                && ($checkedAt + self::CACHE_TTL_SECONDS) > time()
            ) {
                return $cached;
            }
        }

        $fetched = $this->fetchStatus($localVersion);
        if ($fetched['status'] !== 'unknown') {
            $this->saveCached($fetched);
            return $fetched;
        }

        if (
            is_array($cached)
            && !empty($cached['status'])
            && (string) ($cached['local_version'] ?? '') === $localVersion
        ) {
            return $cached;
        }

        return $fetched;
    }

    private function getInstalledVersion(): string
    {
        $fallbackVersion = (string) JCOGS_IMG_PRO_FIELD_VERSION;

        try {
            $moduleName = defined('JCOGS_IMG_PRO_FIELD_CLASS') ? (string) JCOGS_IMG_PRO_FIELD_CLASS : 'Jcogs_img_pro_field';

            $module = ee('Model')
                ->get('Module')
                ->filter('module_name', $moduleName)
                ->first();

            if ($module && !empty($module->module_version)) {
                return (string) $module->module_version;
            }
        } catch (\Throwable $e) {
            // Soft-fail to package version.
        }

        return $fallbackVersion;
    }

    public function getCpBadgeScript(): string
    {
        $status = $this->getStatus();
        if (($status['status'] ?? 'unknown') !== 'update_available') {
            return '';
        }

        $remoteVersion = (string) ($status['remote_version'] ?? '');
        $changelogUrl = (string) ($status['changelog_url'] ?? '');
        if ($changelogUrl === '') {
            $changelogUrl = self::CHANGELOG_FALLBACK_URL;
        }

        $tileLabel = $remoteVersion !== '' ? ('Update ' . $remoteVersion . ' available') : 'Update available';
        $cpLabel = $remoteVersion !== '' ? ('Update available (' . $remoteVersion . ') · Click to update') : 'Update available · Click to update';

        return $this->updateMarkerScriptService->buildScript(
            self::ADDON_SHORT_NAME,
            $tileLabel,
            $cpLabel,
            $changelogUrl
        );
    }

    private function fetchStatus(string $localVersion): array
    {
        $response = $this->httpClient->getJson(self::UPDATE_ENDPOINT, 3);

        if (!$response['ok'] || !is_array($response['data'])) {
            return $this->unknown($localVersion, is_string($response['error']) ? $response['error'] : 'Fetch failed');
        }

        $addonEntry = $this->extractAddonEntry($response['data']);
        $remoteVersion = $this->extractVersionFromEntry($addonEntry);
        $changelogUrl = $this->extractChangelogUrlFromEntry($addonEntry);

        if ($remoteVersion === null) {
            $remoteVersion = $this->extractVersion($response['data']);
        }

        if (!$remoteVersion) {
            return $this->unknown($localVersion, 'Version not found in response');
        }

        $status = version_compare($remoteVersion, $localVersion, '>') ? 'update_available' : 'up_to_date';

        return [
            'status' => $status,
            'checked_at' => time(),
            'local_version' => $localVersion,
            'remote_version' => $remoteVersion,
            'changelog_url' => $changelogUrl,
            'endpoint' => self::UPDATE_ENDPOINT,
            'error' => null,
        ];
    }

    private function extractAddonEntry(array $payload): ?array
    {
        if (isset($payload['addons']) && is_array($payload['addons']) && isset($payload['addons'][self::ADDON_SHORT_NAME])) {
            $entry = $payload['addons'][self::ADDON_SHORT_NAME];
            return is_array($entry) ? $entry : null;
        }

        if (isset($payload[0]) && is_array($payload[0])) {
            foreach ($payload as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $name = (string) ($entry['addon'] ?? $entry['short_name'] ?? $entry['name'] ?? '');
                if ($name === self::ADDON_SHORT_NAME) {
                    return $entry;
                }
            }
        }

        return null;
    }

    private function extractVersionFromEntry(?array $entry): ?string
    {
        if (!is_array($entry)) {
            return null;
        }

        foreach (['version', 'current_version', 'latest_version'] as $key) {
            if (!empty($entry[$key]) && is_string($entry[$key])) {
                return trim($entry[$key]);
            }
        }

        return null;
    }

    private function extractChangelogUrlFromEntry(?array $entry): ?string
    {
        if (!is_array($entry)) {
            return null;
        }

        foreach (['changelog_url', 'changelog', 'release_notes_url'] as $key) {
            if (!empty($entry[$key]) && is_string($entry[$key])) {
                $value = trim($entry[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function extractVersion(array $payload): ?string
    {
        if (isset($payload['addons']) && is_array($payload['addons'])) {
            $addons = $payload['addons'];
            if (isset($addons[self::ADDON_SHORT_NAME])) {
                $entry = $addons[self::ADDON_SHORT_NAME];
                if (is_array($entry)) {
                    foreach (['version', 'current_version', 'latest_version'] as $key) {
                        if (!empty($entry[$key]) && is_string($entry[$key])) {
                            return trim($entry[$key]);
                        }
                    }
                } elseif (is_string($entry)) {
                    return trim($entry);
                }
            }
        }

        if (isset($payload[0]) && is_array($payload[0])) {
            foreach ($payload as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $name = (string) ($entry['addon'] ?? $entry['short_name'] ?? $entry['name'] ?? '');
                if ($name !== self::ADDON_SHORT_NAME) {
                    continue;
                }

                foreach (['version', 'current_version', 'latest_version'] as $key) {
                    if (!empty($entry[$key]) && is_string($entry[$key])) {
                        return trim($entry[$key]);
                    }
                }
            }
        }

        return null;
    }

    private function unknown(string $localVersion, string $error): array
    {
        $this->utilities->debug_message('Update check soft-fail', ['error' => $error, 'service' => 'UpdateCheckService::unknown']);

        return [
            'status' => 'unknown',
            'checked_at' => time(),
            'local_version' => $localVersion,
            'remote_version' => null,
            'changelog_url' => null,
            'endpoint' => self::UPDATE_ENDPOINT,
            'error' => $error,
        ];
    }

    private function getCached(): ?array
    {
        try {
            $cached = ee()->cache->get(self::CACHE_KEY);
            return is_array($cached) ? $cached : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function saveCached(array $status): void
    {
        try {
            ee()->cache->save(self::CACHE_KEY, $status, self::CACHE_TTL_SECONDS);
        } catch (\Throwable $e) {
            // No-op
        }
    }
}
