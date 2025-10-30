<?php
/**
 * JCOGS Image Pro - Delete Parameter View
 * =======================================
 * ExpressionEngine 7 Add-on View Template for Parameter Deletion Confirmation
 * 
 * This view provides a confirmation interface for deleting parameters from presets,
 * including warning notices, parameter information display, and confirmation forms.
 * Features clear warning messaging and destructive action protection.
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Parameter Deletion Interface Implementation
 * 
 * Template Variables:
 * @var string  $cp_page_title   Control panel page title for the deletion interface
 * @var string  $cancel_url      URL to return to when canceling the deletion
 * @var array   $preset          Complete preset data containing the parameter
 * @var mixed   $form            EE7 form object for deletion confirmation
 * @var string  $parameter_name  Name of the parameter being deleted
 * @var string  $base_url        Base URL for asset loading and navigation
 */
?>

<div class="panel">
    <div class="panel-heading">
        <div class="title-bar title-bar--large">
            <h3 class="title-bar__title"><?= $cp_page_title ?></h3>
            <div class="title-bar__extra-tools">
                <a class="btn btn-default" href="<?= $cancel_url ?>">
                    <i class="icon--cancel"></i> Cancel
                </a>
            </div>
        </div>
    </div>
    <div class="panel-body">
        <div class="app-notice-wrap"><?=ee('CP/Alert')->getAllInlines()?></div>
        
        <div class="preset-context">
            <p class="form-standard">
                Deleting parameter from preset: <strong><?= htmlspecialchars($preset['name']) ?></strong>
            </p>
        </div>
        
        <div class="warning-notice">
            <div class="warning-icon">⚠️</div>
            <div class="warning-content">
                <h4>Warning: This action cannot be undone</h4>
                <p>Deleting this parameter will permanently remove it from the preset. Any templates using this preset will lose this parameter configuration.</p>
            </div>
        </div>
        
        <?php if (isset($form) && !empty($form)): ?>
        <?= $form->render() ?>
        <?php endif; ?>
    </div>
</div>
