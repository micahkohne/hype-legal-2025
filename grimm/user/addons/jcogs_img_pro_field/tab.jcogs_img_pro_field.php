<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * JCOGS Image Pro Field - Publish Tab Stub
 *========================================
 * Legacy publish tab entry-point (stub).
 *
 * EE will look for this file when has_publish_fields is enabled; this add-on
 * uses a fieldtype rather than legacy publish tabs.
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

// Minimal legacy Publish Tab stub.
// EE will look for this file when `has_publish_fields = 'y'` on the updater.

class Jcogs_img_pro_field_tab
{
    public function __construct()
    {
    }

    /**
     * Return publish tabs/fields (legacy).
     *
     * We don't add a legacy publish tab; the add-on provides a fieldtype instead.
     */
    public function publish_tabs($channel_id, $entry_id = '')
    {
        return [];
    }
}
