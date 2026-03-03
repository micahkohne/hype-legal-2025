<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
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
 * @version    1.0.0
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
}
