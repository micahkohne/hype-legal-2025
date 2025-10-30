<?php
/**
 * JCOGS Image Pro - Preset Management View
 * 
 * This view renders the preset management interface using EE7 layout conventions.
 * The sidebar is handled by ImageAbstractRoute - this template only renders the main content.
 */
?>

<div class="panel">
    <div class="panel-heading">
        <div class="title-bar title-bar--large">
            <h3 class="title-bar__title"><?= $cp_page_title ?></h3>
            <div class="title-bar__extra-tools">
                <div class="button-toolbar">
                    <a class="btn btn-default" href="<?= ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/import') ?>">
                        <i class="icon--upload"></i> Import Preset
                    </a>
                    <a class="btn btn-primary" href="<?= $create_url ?>">
                        <i class="icon--add"></i> Create New Preset
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="panel-body">
        <div class="app-notice-wrap"><?=ee('CP/Alert')->getAllInlines()?></div>
        
        <!-- Existing Presets Table -->
        <div class="preset-table-section">
            <h4>Existing Presets</h4>
            <?php $this->embed('ee:_shared/table', $preset_table); ?>
        </div>
        
        <hr class="section-divider">
        
        <!-- Global Settings Form -->
        <div class="global-settings-section">
            <h4>Global Settings</h4>
            <p class="form-standard">Configure default settings that apply to all presets.</p>
            
            <?php if ($has_presets): ?>
                <?= $settings_form->render() ?>
            <?php else: ?>
                <div class="empty-state">
                    <p>Create your first preset to access global settings.</p>
                    <a href="<?= $create_url ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create First Preset
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
