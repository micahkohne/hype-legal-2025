<?php

/**
 * JCOGS Image Pro - Jump Menu Integration
 * =======================================
 * Control panel quick navigation integration for EE7
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

use ExpressionEngine\Service\JumpMenu\AbstractJumpMenu;

class Jcogs_img_pro_jump extends AbstractJumpMenu
{
    /**
     * Define the add-ons jumps in array below.
     * See Docs for array reference
     * 
     * https://docs.expressionengine.com/latest/development/jump-menu.html
     */

    protected static $items = [
        // Core Settings Navigation
        'systemSettings' => array(
            'icon' => 'fa-wrench',
            'command' => 'image system settings main pro',
            'command_title' => 'jump_system_settings',
            'dynamic' => false,
            'requires_keyword' => false,
            'target' => ''
        ),
        'advancedSettings' => array(
            'icon' => 'fa-rocket',
            'command' => 'image advanced settings pro configuration',
            'command_title' => 'jump_advanced_settings',
            'dynamic' => false,
            'requires_keyword' => false,
            'target' => 'advanced_settings'
        ),
        
        // Connection Management (Pro Features)
        'cacheConnections' => array(
            'icon' => 'fa-plug',
            'command' => 'image cache connections manage',
            'command_title' => 'jump_cache_connections',
            'dynamic' => false,
            'requires_keyword' => false,
            'target' => 'caching'
        ),
        'addConnection' => array(
            'icon' => 'fa-plus-circle',
            'command' => 'add new image cache connection',
            'command_title' => 'jump_add_connection',
            'dynamic' => false,
            'requires_keyword' => false,
            'target' => 'caching/add_connection'
        ),
        'editConnection' => array(
            'icon' => 'fa-edit',
            'command' => 'edit image cache connection',
            'command_title' => 'jump_edit_connection',
            'dynamic' => true,
            'requires_keyword' => true,
            'target' => 'caching/edit_connection'
        ),
        'cloneConnection' => array(
            'icon' => 'fa-copy',
            'command' => 'clone duplicate image connection',
            'command_title' => 'jump_clone_connection',
            'dynamic' => true,
            'requires_keyword' => true,
            'target' => 'caching/add_connection'
        ),
        
        // Cache Operations
        'clearCache' => array(
            'icon' => 'fa-trash',
            'command' => 'clear image cache all cleanup',
            'command_title' => 'jump_clear_cache',
            'dynamic' => false,
            'requires_keyword' => false,
            'target' => 'caching?action=clear_all'
        ),
        'auditCache' => array(
            'icon' => 'fa-search',
            'command' => 'audit image cache cleanup maintenance',
            'command_title' => 'jump_audit_cache',
            'dynamic' => false,
            'requires_keyword' => false,
            'target' => 'caching?action=audit'
        ),
        'cacheStats' => array(
            'icon' => 'fa-chart-bar',
            'command' => 'image cache statistics stats performance',
            'command_title' => 'jump_cache_stats',
            'dynamic' => false,
            'requires_keyword' => false,
            'target' => 'dashboard'
        ),
        
        // Testing & Diagnostics
        'testConnection' => array(
            'icon' => 'fa-stethoscope',
            'command' => 'test image connection health check',
            'command_title' => 'jump_test_connection',
            'dynamic' => true,
            'requires_keyword' => true,
            'target' => 'caching/test_connection'
        ),
        'systemInfo' => array(
            'icon' => 'fa-info-circle',
            'command' => 'image system info diagnostics php',
            'command_title' => 'jump_system_info',
            'dynamic' => false,
            'requires_keyword' => false,
            'target' => 'system_info'
        ),
        
        // License Management
        'license' => array(
            'icon' => 'fa-certificate',
            'command' => 'image license pro registration',
            'command_title' => 'jump_license_settings',
            'dynamic' => false,
            'requires_keyword' => false,
            'target' => 'license'
        )
    ];
}
