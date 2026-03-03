<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * JCOGS Image Pro Field - Module Stub
 *===================================
 * Legacy EE module entry-point (stub).
 *
 * This add-on primarily provides a fieldtype; ACT endpoints live in Actions/.
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

// Load Composer autoloader if present
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

class Jcogs_img_pro_field extends \ExpressionEngine\Service\Addon\Module
{
    protected $addon_name = 'jcogs_img_pro_field';
}
