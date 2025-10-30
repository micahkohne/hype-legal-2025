<?php
/**
 * JCOGS Image Pro - Add Parameter View
 * ====================================
 * ExpressionEngine 7 Add-on View Template for Adding Parameters to Presets
 * 
 * This view provides the interface for adding new parameters to existing presets,
 * including form controls, validation, parameter examples, and contextual help.
 * Supports the parameter addition workflow with enhanced user guidance.
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Parameter Addition Interface Implementation
 * 
 * Template Variables:
 * @var string  $cp_page_title   Control panel page title for the interface
 * @var string  $cancel_url      URL to return to when canceling the operation
 * @var array   $preset          Complete preset configuration data being modified
 * @var mixed   $form            EE7 form object for parameter input
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
                Adding parameter to preset: <strong><?= htmlspecialchars($preset['name']) ?></strong>
            </p>
        </div>
        
        <?php if (isset($form) && !empty($form)): ?>
        <?= $form->render() ?>
        <?php endif; ?>
        
        <div class="form-btns">
            <div class="parameter-help">
                <h5>Parameter Value Examples:</h5>
                <ul class="parameter-examples">
                    <li><strong>width/height:</strong> 400, 100%, auto</li>
                    <li><strong>quality:</strong> 85, 100, auto</li>
                    <li><strong>crop:</strong> center, face_detect|||no, 50,50,100,100</li>
                    <li><strong>cache:</strong> 0, 1, 2592000 (seconds)</li>
                    <li><strong>format:</strong> jpg, png, webp, auto</li>
                </ul>
                <p class="form-standard">
                    Use the same format you would use in template tags. You can refine these values later using the parameter package forms.
                </p>
            </div>
        </div>
    </div>
</div>
