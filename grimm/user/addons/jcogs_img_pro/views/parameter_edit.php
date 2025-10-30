<?php
/**
 * JCOGS Image Pro - Parameter Edit View
 * =====================================
 * ExpressionEngine 7 Add-on View Template for Individual Parameter Editing
 * 
 * This view provides the interface for editing individual preset parameters,
 * including form controls, validation, and contextual information display.
 * Supports the comprehensive parameter editing workflow with enhanced UX.
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Parameter Edit Interface Implementation
 * 
 * Template Variables:
 * @var array   $preset          Complete preset configuration data
 * @var string  $parameter_key   Unique identifier for the parameter being edited
 * @var array   $parameter_data  Current parameter configuration and metadata
 * @var string  $form_url        Target URL for form submission
 * @var array   $validation      Form validation rules and error messages
 * @var string  $base_url        Base URL for asset loading and navigation
 */
?>

<div class="panel">
    <div class="panel-heading">
        <div class="title-bar title-bar--large">
            <h3 class="title-bar__title"><?= htmlspecialchars($cp_page_title) ?></h3>
            <div class="title-bar__extra-tools">
                <a class="btn btn-default" href="<?= $edit_preset_url ?>">
                    <i class="icon--back"></i> Back to Preset
                </a>
            </div>
        </div>
    </div>
    <div class="panel-body">
        <div class="app-notice-wrap">
            <?=ee('CP/Alert')->getAllInlines()?>
        </div>
        
        <!-- Enhanced Parameter Information Section -->
        <div class="parameter-info-section">
            <h4>Parameter Details</h4>
            <div class="parameter-details">
                <div class="detail-item">
                    <strong>Preset:</strong> 
                    <span class="preset-name"><?= htmlspecialchars($preset['name']) ?></span>
                </div>
                <div class="detail-item">
                    <strong>Parameter:</strong> 
                    <span class="parameter-name"><?= htmlspecialchars($parameter_name) ?></span>
                </div>
                <?php if (!empty($parameter_info['category'])): ?>
                <div class="detail-item">
                    <strong>Category:</strong> 
                    <span class="parameter-category"><?= htmlspecialchars(ucfirst($parameter_info['category'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($current_value)): ?>
                <div class="detail-item">
                    <strong>Current Value:</strong> 
                    <code class="current-value"><?= htmlspecialchars($current_value) ?></code>
                </div>
                <?php endif; ?>
                <?php if (!empty($parameter_info['description'])): ?>
                <div class="detail-item full-width">
                    <strong>Description:</strong> 
                    <p class="parameter-description"><?= htmlspecialchars($parameter_info['description']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <hr class="section-divider">
        
        <!-- Parameter Edit Form -->
        <div class="parameter-form-section">
            <h4>Edit Parameter</h4>
            <?= $parameter_form ?>
        </div>
    </div>
</div>