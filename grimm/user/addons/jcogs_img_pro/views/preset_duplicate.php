<?php
/**
 * JCOGS Image Pro - Duplicate Preset View
 * ========================================
 * Form for duplicating existing presets with validation
 */
?>

<div class="main">
    <div class="box">
        <h1><?=$cp_page_title?></h1>
        
        <div class="app-notice-wrap">
            <div class="app-notice app-notice--inline">
                <div class="app-notice__tag">
                    <span class="app-notice__icon">
                        <i class="fal fa-copy"></i>
                    </span>
                </div>
                <div class="app-notice__content">
                    <p>Create a copy of "<?=ee('Format')->make('Text', $preset['name'])->convertToEntities()?>" with a new name. All parameters will be copied to the new preset.</p>
                </div>
            </div>
        </div>

        <?= form_open($duplicate_url, array('class' => 'settings')) ?>
        
        <fieldset class="col-group">
            <div class="setting-txt col w-8">
                <h3>Original Preset</h3>
                <em>This preset will be duplicated</em>
            </div>
            <div class="setting-field col w-8 last">
                <div class="preset-info">
                    <strong><?=ee('Format')->make('Text', $preset['name'])->convertToEntities()?></strong>
                    <?php if (!empty($preset['description'])): ?>
                        <br><span class="meta-info"><?=ee('Format')->make('Text', $preset['description'])->convertToEntities()?></span>
                    <?php endif; ?>
                    <br><span class="meta-info">Created: <?=date('M j, Y', $preset['created_date'])?></span>
                </div>
            </div>
        </fieldset>

        <fieldset class="col-group required">
            <div class="setting-txt col w-8">
                <h3>New Preset Name <span class="required">*</span></h3>
                <em>Enter a unique name for the duplicated preset</em>
            </div>
            <div class="setting-field col w-8 last">
                <input type="text" name="preset_name" value="<?=ee('Format')->make('Text', $suggested_name)->convertToEntities()?>" maxlength="100" required>
            </div>
        </fieldset>

        <fieldset class="col-group">
            <div class="setting-txt col w-8">
                <h3>New Description</h3>
                <em>Optional description for the new preset</em>
            </div>
            <div class="setting-field col w-8 last">
                <textarea name="preset_description" rows="3" placeholder="Enter description for the duplicated preset..."><?=ee('Format')->make('Text', $preset['description'])->convertToEntities()?></textarea>
            </div>
        </fieldset>

        <fieldset class="form-ctrls">
            <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
            <input class="btn action" type="submit" value="Create Duplicate">
            <a href="<?=$presets_url?>" class="btn">Cancel</a>
        </fieldset>

        <?=form_close()?>
    </div>
</div>
