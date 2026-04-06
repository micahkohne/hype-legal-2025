<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * JCOGS Image Pro Field - MCP Stub
 *================================
 * Legacy EE control panel entry-point (stub).
 *
 * This add-on does not currently expose CP settings; the fieldtype provides its
 * own settings UI in the Channel Fields editor.
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

// Load Composer autoloader if present
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

class Jcogs_img_pro_field_mcp
{
    public function __construct()
    {
        // Minimal CP stub so the add-on "opens" cleanly.
    }

    public function index()
    {
        if (isset(ee()->cp)) {
            ee()->cp->set_right_nav([]);
        }

        $html = '';
        $html .= '<div class="panel">';
        $html .= '<div class="panel-heading"><h1>JCOGS Image Pro Field</h1></div>';
        $html .= '<div class="panel-body">';
        $html .= '<p>This add-on is installed and the fieldtype is available.</p>';
        $html .= '<p>Control Panel settings are not implemented yet.</p>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}
