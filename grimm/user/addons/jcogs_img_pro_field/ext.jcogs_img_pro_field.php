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

use ExpressionEngine\Service\Addon\Extension;

/**
 * JCOGS Image Pro Field - Extension Stub
 *======================================
 * Legacy EE extension entry-point (stub).
 *
 * EE7 dispatches hooks to PSR-4 classes under Extensions/; this file exists for
 * backward compatibility and version tracking.
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
class Jcogs_img_pro_field_ext extends Extension
{
    protected $addon_name = 'jcogs_img_pro_field';
    public $settings = [];
    public $version = JCOGS_IMG_PRO_FIELD_VERSION;

    public function activate_extension()
    {
        $this->settings = [];
        return true;
    }

    public function update_extension($current = '')
    {
        if ($current === '' || $current === $this->version) {
            return false;
        }

        return true;
    }

    public function disable_extension()
    {
        return true;
    }

    /**
     * Inject custom scripts at end of CP output.
     */
    public function cp_js_end()
    {
        $extension = new \JCOGSDesign\JcogsImgProField\Extensions\CpJsEnd();
        return $extension->process();
    }
}
