<?php

/**
 * JCOGS Image Pro - Cache Management View
 * =======================================
 * Control panel view for cache management interface with statistics and controls
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Cache Management Implementation
 */

/**
 * Cache Management View Template
 * 
 * Renders the cache management interface using EE7 layout conventions.
 * The sidebar is handled by ImageAbstractRoute - this template only renders 
 * the main content area with cache statistics, controls, and settings.
 * 
 * Available Variables:
 * - $cp_page_title: Page title for the cache management interface
 * - $main_form: EE7 form object containing all cache management controls
 * 
 * Features:
 * - Cache statistics display
 * - Cache clearing controls
 * - Cache duration settings
 * - Storage location management
 * - Performance monitoring
 */
?>

<div class="panel">
    <div class="panel-heading">
        <div class="title-bar title-bar--large">
            <h3 class="title-bar__title"><?= $cp_page_title ?></h3>
        </div>
    </div>
    <div class="panel-body">
        <div class="app-notice-wrap"><?=ee('CP/Alert')->getAllInlines()?></div>
        
        <?php if (isset($main_form) && !empty($main_form)): ?>
        <!-- Single comprehensive form with all groups (CP/Form handles its own form tags) -->
        <?= $main_form->render() ?>
        <?php endif; ?>
    </div>
</div>



