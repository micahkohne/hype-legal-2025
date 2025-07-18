<style>
    ul.list-group {
        margin-top: 1rem;
    }
    li.jcogs_img_list {
        flex-direction: column;
    }
    .list-item__content-left, .list-item__content-right {
        justify-content: flex-start;
    }
    .jcogs_img_button-toolbar.toolbar {
        display: flex;
        justify-content: flex-end;
        flex-wrap: wrap;
    }
    .jcogs_img_button-group.button-group {
        display: flex;
        flex-wrap: wrap;
    }
</style>
<?php if (isset($cache_intro_text)): ?>
    <div class="field-instruct">
        <label for="smth"><?= $cache_location_title ?></label>
        <em>
            <p>
                <?= $cache_intro_text ?>
            </p>
        </em>
    </div>
<?php endif; ?>
<div class="js-list-group-wrap">
    <ul class="list-group">
        <?php foreach ($cache_locations as $row): ?>
            <li class="list-item list-item--action<?php if (isset($row['selected']) && $row['selected']):?> list-item--selected<?php endif ?> jcogs_img_list" style="position: relative;">
                <a href="<?=$row['href']?>" class="list-item__content">
                    <div class="list-item__title">
                        <?=(isset($row['htmlLabel']) && $row['htmlLabel']) ? $row['label'] : ee('Format')->make('Text', $row['label'])->convertToEntities()?>
                        <?php if (isset($row['faded'])): ?>
                            <span class="faded"<?php echo isset($row['faded-href']) ? ' data-href="' . $row['faded-href'] . '"' : ''; ?>><?=$row['faded']?></span>
                        <?php endif ?>
                    </div>
                    <div class="list-item__secondary"><?=$row['status_info']?></div>
                </a>

                <?php if (isset($row['toolbar_items'])) : ?>
                <div class="list-item__content-right">
                    <?=$this->embed('jcogs_img:toolbar', ['toolbar_items' => $row['toolbar_items']])?>
                </div>
                <?php endif ?>
            </li>
        <?php endforeach; ?>
        <?php if (empty($cache_locations) && isset($no_results)): ?>
            <li>
                <div class="tbl-row no-results">
                    <div class="none">
                        <p><?=$no_results['text']?><?php if (isset($no_results['href'])): ?> <a href="<?=$no_results['href']?>"><?=lang('add_new')?></a><?php endif ?></p>
                    </div>
                </div>
            </li>
        <?php endif ?>
    </ul>
</div>
