<?php

/**
 * Jump Menu Module
 * ================
 * Full function image processing add-on for EE.
 * Designed to work with similar parameter and variable definitions
 * to CE-Image, to support drop-in replacement.
 * 
 * CHANGELOG
 * 
 * 13/1/2022: 1.2.6     Add Jump Menu entry
 * 10/01/2023: 1.3.3    Improved: Updated to better reflect available functions
 *                      
 * =====================================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.4.16.2
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.2.6
 */

use ExpressionEngine\Service\JumpMenu\AbstractJumpMenu;

class Jcogs_img_jump extends AbstractJumpMenu
{
    protected static $items = array(
        'systemSettings' => array(
            'icon' => 'fa-wrench',
            'command' => 'image system settings',
            'command_title' => 'jump_system_settings',
            'dynamic' => false,
            'requires_keyword' => false,
            'target' => ''
        ),
        'imageCache' => array(
            'icon' => 'fa-database',
            'command' => 'image cache settings',
            'command_title' => 'jump_cache_settings',
            'dynamic' => false,
            'requires_keyword' => false,
            'target' => 'caching'
        ),
        'imageSettings' => array(
            'icon' => 'fa-image',
            'command' => 'image main settings',
            'command_title' => 'jump_image_settings',
            'dynamic' => false,
            'requires_keyword' => false,
            'target' => 'image_defaults'
        ),
        'advancedSettings' => array(
            'icon' => 'fa-rocket',
            'command' => 'image advanced settings',
            'command_title' => 'jump_advanced_settings',
            'dynamic' => false,
            'requires_keyword' => false,
            'target' => 'advanced_settings'
        ),
        'license' => array(
            'icon' => 'fa-certificate',
            'command' => 'image license',
            'command_title' => 'jump_license_settings',
            'dynamic' => false,
            'requires_keyword' => false,
            'target' => 'license'
        )
    );
}
