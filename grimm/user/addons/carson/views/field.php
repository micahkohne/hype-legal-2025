<div class="carson-container">
    <?php if ($fieldName && $isSelfTarget): ?>
        <div class="field-control">
            <textarea name="<?php echo $fieldName ?>" rows="10"><?php echo $value ?></textarea>
        </div>
    <?php endif; ?>
    <?php if ($showPrompt): ?>
        <div class="field-control">
            <div class="field-instruct"><em><?php echo $description ?></em></div>
            <input type="hidden" name="carson_prompt" value="<?php echo $prompt ?>" placeholder="<?php echo lang('carson_default_prompt_placeholder') ?>" />
        </div>
    <?php else: ?>
        <input type="hidden" name="carson_prompt" value="<?php echo $prompt ?>" placeholder="<?php echo lang('carson_default_prompt_placeholder') ?>" />
    <?php endif; ?>
    <?php if ($showContext): ?>
        <div class="field-control">
            <div class="field-instruct">
                <em>Use this entry's content to supply additional context to the prompt?</em>
            </div>
            <?php
            echo $this->embed('ee:_shared/form/fields/toggle', [
                'yes_no' => true,
                'value' => 'y',
                'disabled' => false,
                'field_name' => 'carson_context'
            ]); ?>
        </div>
    <?php endif; ?>
    <div class="carson-container__footer">
        <a href="#"
           class="button button--small button--action carson"
           data-action-url="<?php echo $actionUrl ?>"
           data-force-context="<?php echo $forceContext ? 'y' : 'n' ?>"
           data-target="<?php echo $targetFields ?>"
           data-type="<?php echo $type ?>"
           data-work-text="<?php echo $workText ?>"
        >
            <span><?php echo $buttonLabel ?></span> <i class="fas fa-wand-magic-sparkles"></i>
        </a>
    </div>
</div>
