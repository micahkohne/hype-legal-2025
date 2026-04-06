<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

if (! defined('JCOGS_IMG_PRO_FIELD_VERSION')) {
    $addonJsonPath = __DIR__ . '/addon.json';
    $addonJsonRaw = is_file($addonJsonPath) ? file_get_contents($addonJsonPath) : false;
    $addonJson = $addonJsonRaw ? json_decode($addonJsonRaw) : null;

    defined('JCOGS_IMG_PRO_FIELD_VERSION') || define('JCOGS_IMG_PRO_FIELD_VERSION', (string) ($addonJson->version ?? '0.0.0'));
    defined('JCOGS_IMG_PRO_FIELD_CLASS') || define('JCOGS_IMG_PRO_FIELD_CLASS', (string) ($addonJson->class ?? 'Jcogs_img_pro_field'));
    defined('JCOGS_IMG_PRO_FIELD_NAME') || define('JCOGS_IMG_PRO_FIELD_NAME', (string) ($addonJson->name ?? 'JCOGS Image Pro Field'));
}

use ExpressionEngine\Service\Addon\Installer;

/**
 * JCOGS Image Pro Field - Updater
 *===============================
 * Installer/update entry-point.
 *
 * Delegates schema and registration work to EE migrations.
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.2
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 * @since      0.1.6
 */
class Jcogs_img_pro_field_upd extends Installer
{
    public $has_cp_backend = 'y';
    public $has_publish_fields = 'y';

    public function install()
    {
        parent::install();
        $this->ensureCpJsEndExtensionRegistered();
        $this->ensureRequiredActionsRegistered();
        return true;
    }

    public function update($current = '')
    {
        if ($current == JCOGS_IMG_PRO_FIELD_VERSION) {
            $this->ensureCpJsEndExtensionRegistered();
            $this->ensureRequiredActionsRegistered();
            return false;
        }

        parent::update($current);
        $this->ensureCpJsEndExtensionRegistered();
        $this->ensureRequiredActionsRegistered();
        return true;
    }

    public function uninstall()
    {
        parent::uninstall();
        return true;
    }

    private function ensureRequiredActionsRegistered(): void
    {
        $requiredMethods = ['usage', 'preview', 'face_detect'];

        foreach ($requiredMethods as $method) {
            try {
                $exists = ee('Model')->get('Action')
                    ->filter('class', 'Jcogs_img_pro_field')
                    ->filter('method', $method)
                    ->count();

                if ((int) $exists > 0) {
                    continue;
                }

                ee('Model')->make('Action', [
                    'class' => 'Jcogs_img_pro_field',
                    'method' => $method,
                    'csrf_exempt' => 0,
                ])->save();
            } catch (\Throwable $e) {
                // Fail-safe: do not block install/update if action self-heal fails.
            }
        }
    }

    private function ensureCpJsEndExtensionRegistered(): void
    {
        try {
            $addon = ee('Addon')->get('jcogs_img_pro_field');
            if (!$addon) {
                return;
            }

            $class = $addon->getExtensionClass();
            $exists = ee('Model')->get('Extension')
                ->filter('class', $class)
                ->filter('hook', 'cp_js_end')
                ->filter('method', 'cp_js_end')
                ->count();

            if ((int) $exists > 0) {
                return;
            }

            ee('Model')->make('Extension', [
                'class' => $class,
                'method' => 'cp_js_end',
                'hook' => 'cp_js_end',
                'settings' => serialize([]),
                'priority' => 10,
                'version' => JCOGS_IMG_PRO_FIELD_VERSION,
                'enabled' => 'y',
            ])->save();
        } catch (\Throwable $e) {
            // Fail-safe: do not block install/update if extension self-heal fails.
        }
    }
}
