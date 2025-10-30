<?php

/**
 * JCOGS Image Pro - Control Panel Entry Point
 * ============================================
 * Native EE7 control panel integration with Pro service architecture
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 3 Legacy Independence
 */

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

use ExpressionEngine\Service\Addon\Mcp;

class Jcogs_img_pro_mcp extends Mcp
{
    protected $addon_name = 'jcogs_img_pro';
}
