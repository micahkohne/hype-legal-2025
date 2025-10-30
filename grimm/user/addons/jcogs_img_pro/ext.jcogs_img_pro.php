<?php

/**
 * JCOGS Image Pro - EE7 Extension Entry Point
 * ============================================
 * Native EE7 extension implementation with Pro service architecture
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

use ExpressionEngine\Service\Addon\Extension;

class Jcogs_img_pro_ext extends Extension
{
    protected $addon_name = 'jcogs_img_pro';
    public $settings = [];
    public $version = '2.0.0a1';
    
    /**
     * Activate extension - Register hooks with EE7
     */
    public function activate_extension()
    {
        $this->settings = [];
        
        // EE7 extensions are automatically registered through the Extensions folder
        // No manual database insertion required - EE7 handles this via the CLI and Extensions folder
        
        return TRUE;
    }
    
    /**
     * Update extension
     */
    public function update_extension($current = '')
    {
        if ($current == '' OR $current == $this->version) {
            return FALSE;
        }
        
        return TRUE;
    }
    
    /**
     * Disable extension
     */
    public function disable_extension()
    {
        // EE7 will handle cleaning up the extension hooks automatically
        return TRUE;
    }
}
