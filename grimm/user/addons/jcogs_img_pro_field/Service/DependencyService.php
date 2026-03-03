<?php

/**
 * JCOGS Image Pro Field - DependencyService
 *==========================================
 * Centralised dependency/version checks for companion add-ons.
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

declare(strict_types=1);

namespace JCOGSDesign\JcogsImgProField\Service;

/**
 * Dependency/version helper for the Image Pro companion add-on.
 */
final class DependencyService
{
    /**
     * Return the minimum supported Image Pro version.
     */
    public static function minImageProVersion(): string
    {
        if (defined('JCOGS_IMG_PRO_FIELD_MIN_IMG_PRO_VERSION')) {
            $v = (string) JCOGS_IMG_PRO_FIELD_MIN_IMG_PRO_VERSION;
            if (trim($v) !== '') {
                return trim($v);
            }
        }

        return '2.0.3';
    }

    /**
     * Resolve the installed Image Pro version, if available.
     */
    public static function installedImageProVersion(): ?string
    {
        // Prefer EE's Addon service.
        try {
            if (function_exists('ee')) {
                $addonService = ee('Addon');
                if ($addonService && method_exists($addonService, 'get')) {
                    $addon = $addonService->get('jcogs_img_pro');
                    if ($addon && method_exists($addon, 'getVersion')) {
                        $v = (string) $addon->getVersion();
                        $v = trim($v);
                        if ($v !== '') {
                            return $v;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Fall through.
        }

        // Fallback: read addon.json directly.
        try {
            if (defined('PATH_THIRD')) {
                $path = rtrim((string) PATH_THIRD, '/') . '/jcogs_img_pro/addon.json';
                if (is_file($path)) {
                    $json = json_decode((string) file_get_contents($path), true);
                    if (is_array($json) && isset($json['version']) && is_string($json['version'])) {
                        $v = trim($json['version']);
                        return $v !== '' ? $v : null;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore.
        }

        return null;
    }

    /**
     * Determine whether the installed Image Pro version meets requirements.
     */
    public static function isImageProCompatible(?string $minVersion = null): bool
    {
        $minVersion = $minVersion !== null ? trim($minVersion) : self::minImageProVersion();
        if ($minVersion === '') {
            $minVersion = '2.0.3';
        }

        $installed = self::installedImageProVersion();
        if ($installed === null) {
            return false;
        }

        // Accept pre-release builds of the same core version.
        // Example: when min is "2.0.3", treat installed "2.0.3.RC7" as compatible.
        // PHP's version_compare considers RC/beta/alpha lower than the final release, which
        // is correct for strict semver, but for our dev/release-candidate workflow we want
        // the numeric core comparison to control compatibility.
        $installed_core = null;
        $min_core = null;
        if (preg_match('/^\d+(?:\.\d+){0,3}/', $installed, $m1)) {
            $installed_core = $m1[0];
        }
        if (preg_match('/^\d+(?:\.\d+){0,3}/', $minVersion, $m2)) {
            $min_core = $m2[0];
        }
        if ($installed_core !== null && $min_core !== null) {
            if (version_compare($installed_core, $min_core, '>=')) {
                return true;
            }
        }

        return version_compare($installed, $minVersion, '>=');
    }
}
