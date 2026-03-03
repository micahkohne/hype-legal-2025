<?php

/**
 * JCOGS Image Pro Field - AssetLoaderService
 *==========================================
 * Enqueues CP CSS/JS assets for settings and publish interfaces.
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
 * Loads CP assets for settings and publish interfaces.
 */
class AssetLoaderService
{
    private const THEME_PATH = 'user/jcogs_img_pro_field/';

    private bool $settingsAssetsLoaded = false;
    private bool $publishAssetsLoaded = false;

    public function enqueueCpSettingsAssets(): void
    {
        if ($this->settingsAssetsLoaded || REQ !== 'CP') {
            return;
        }
        $this->settingsAssetsLoaded = true;

        $this->addCss('css/cp-ui.css');
        $this->addJs('javascript/settings-ui.js');

        try {
            if (isset(ee()->extensions) && ee()->extensions->active_hook('jcogs_img_pro_field_enqueue_cp_settings_assets')) {
                ee()->extensions->call('jcogs_img_pro_field_enqueue_cp_settings_assets');
            }
        } catch (\Throwable $e) {
            // Fail safe: never break CP rendering.
        }
    }

    public function enqueueCpPublishAssets(): void
    {
        if ($this->publishAssetsLoaded || REQ !== 'CP') {
            return;
        }
        $this->publishAssetsLoaded = true;

        $this->addCss('css/cp-ui.css');
        $this->addJs('javascript/publish-ui.js');

        try {
            if (isset(ee()->extensions) && ee()->extensions->active_hook('jcogs_img_pro_field_enqueue_cp_publish_assets')) {
                ee()->extensions->call('jcogs_img_pro_field_enqueue_cp_publish_assets');
            }
        } catch (\Throwable $e) {
            // Fail safe: never break CP rendering.
        }
    }

    /**
     * Build a fully-qualified URL for a theme asset path.
     */
    private function themeUrl(string $relativePath): string
    {
        $base = defined('URL_THEMES') ? (string) URL_THEMES : '';
        $base = rtrim($base, '/');
        if ($base === '') {
            return '';
        }

        // In some EE/CP contexts URL_THEMES can resolve to the CP themes path
        // (e.g. .../themes/ee). Our add-on assets live under .../themes/user/.
        // Normalise the base so requests do not include a stray /ee segment.
        $base = preg_replace('#/themes/ee$#', '/themes', $base);

        return $base . '/' . self::THEME_PATH . ltrim($relativePath, '/');
    }

    /**
     * Enqueue a CSS file in the CP head.
     */
    private function addCss(string $relativePath): void
    {
        $url = $this->themeUrl($relativePath);
        if ($url === '') {
            return;
        }

        try {
            if (isset(ee()->cp) && method_exists(ee()->cp, 'add_to_head')) {
                ee()->cp->add_to_head(
                    '<link rel="stylesheet" type="text/css" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
                );
            }
        } catch (\Throwable $e) {
            // no-op
        }
    }

    /**
     * Enqueue a JS file in the CP footer (fallback to head).
     */
    private function addJs(string $relativePath): void
    {
        $url = $this->themeUrl($relativePath);
        if ($url === '') {
            return;
        }

        try {
            if (isset(ee()->cp) && method_exists(ee()->cp, 'add_to_foot')) {
                ee()->cp->add_to_foot(
                    '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></script>'
                );
                return;
            }

            if (isset(ee()->cp) && method_exists(ee()->cp, 'add_to_head')) {
                ee()->cp->add_to_head(
                    '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></script>'
                );
            }
        } catch (\Throwable $e) {
            // no-op
        }
    }
}
