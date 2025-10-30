<?php
/**
 * JCOGS Image Pro - Import Preset View
 * ====================================
 * Form for importing preset configurations from JSON files
 */
?>

<div class="main">
    <div class="box">
        <h1><?=$cp_page_title?></h1>
        
        <div class="app-notice-wrap">
            <div class="app-notice app-notice--inline">
                <div class="app-notice__tag">
                    <span class="app-notice__icon">
                        <i class="fal fa-upload"></i>
                    </span>
                </div>
                <div class="app-notice__content">
                    <p>Upload a JSON preset file to import settings. The file should be exported from JCOGS Image Pro.</p>
                </div>
            </div>
        </div>

        <?= form_open_multipart($import_url, array('class' => 'settings')) ?>
        
        <fieldset class="col-group">
            <div class="setting-txt col w-8">
                <h3>Select Preset File</h3>
                <em>Choose a .json file exported from JCOGS Image Pro</em>
            </div>
            <div class="setting-field col w-8 last">
                <input type="file" name="import_file" accept=".json,application/json" required>
            </div>
        </fieldset>

        <fieldset class="col-group">
            <div class="setting-txt col w-8">
                <h3>Import Options</h3>
                <em>How to handle conflicts if preset name already exists</em>
            </div>
            <div class="setting-field col w-8 last">
                <label class="choice block">
                    <input type="radio" name="overwrite" value="no" checked>
                    Auto-rename if conflict (recommended)
                </label>
                <label class="choice block">
                    <input type="radio" name="overwrite" value="yes">
                    Overwrite existing preset
                </label>
                <label class="choice block">
                    <input type="radio" name="auto_rename" value="no">
                    Fail if name exists
                </label>
            </div>
        </fieldset>

        <fieldset class="form-ctrls">
            <input type="hidden" name="csrf_token" value="<?=$csrf_token?>">
            <input class="btn action" type="submit" value="Import Preset">
            <a href="<?=$presets_url?>" class="btn">Cancel</a>
        </fieldset>

        <?=form_close()?>
    </div>
</div>
