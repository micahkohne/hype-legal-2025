<?php

/**
 * JCOGS Image Pro - CP Custom Menu Extension
 * ===========================================
 * EE7 extension that adds custom menu items to the control panel
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Control Panel Menu Extension Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Extensions;

use ExpressionEngine\Service\Addon\Controllers\Extension\AbstractRoute;

/**
 * CP Custom Menu Extension
 * 
 * Adds JCOGS Image Pro submenu items to the ExpressionEngine control panel.
 * This extension hooks into the cp_custom_menu hook to provide quick access
 * to various add-on settings pages.
 * 
 * Only works with EE6 and later versions.
 */
class CpCustomMenu extends AbstractRoute
{
    /**
     * Handle cp_custom_menu hook
     * 
     * Adds JCOGS Image Pro submenu items to the control panel navigation.
     * EE7 extensions use the process() method regardless of hook name.
     * 
     * @param object $menu The menu object from ExpressionEngine
     * @return void
     */
    public function process($menu)
    {
        // Only works for EE6 and later
        if (version_compare(APP_VER, '6.0.0', '>=')) {
            // Load the language file first
            ee()->lang->loadfile('jcogs_img_pro', 'jcogs_img_pro');
            
            $sub = $menu->addSubmenu(lang('jcogs_img_pro_module_name'));
            $sub->addItem(lang('jcogs_img_pro_fly_system_settings'), ee('CP/URL')->make('addons/settings/jcogs_img_pro'));
            $sub->addItem(lang('jcogs_img_pro_fly_presets_settings'), ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets'));
            $sub->addItem(lang('jcogs_img_pro_fly_cache_settings'), ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching'));
            $sub->addItem(lang('jcogs_img_pro_fly_image_settings'), ee('CP/URL')->make('addons/settings/jcogs_img_pro/image_defaults'));
            $sub->addItem(lang('jcogs_img_pro_fly_advanced_settings'), ee('CP/URL')->make('addons/settings/jcogs_img_pro/advanced_settings'));
        }
    }
}
