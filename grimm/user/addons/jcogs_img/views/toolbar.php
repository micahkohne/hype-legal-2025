<?php if (! empty($toolbar_items)): ?>
    <div class="jcogs_img_button-toolbar button-toolbar toolbar">
        <div class="jcogs_img_button-group button-group button-group-xsmall">
            <?php foreach ($toolbar_items as $type => $attributes):
                if (isset($attributes['type'])) {
                    $type = $attributes['type'];
                }
                $class = $type ?: '';
                $attr = '';
                $content = '';
                foreach ($attributes as $key => $val) {
                    if ($key == 'content') {
                        $content = $val;

                        continue;
                    }
                    if ($key == 'class') {
                        $class .= ' ' . $val;

                        continue;
                    }
                    $attr .= ' ' . $key . '="' . $val . '"';
                }
                if (isset($attributes['title'])) {
                    $content .= '<span class="hidden">' . $attributes['title'] . '</span>';
                }
                ?>
                <?php if($attributes): ?>
                    <a class="<?=$class?> button button--default" <?=$attr?>><?=$content?></a>
                <?php endif; ?>
            <?php endforeach ?>
        </div>
    </div>
<?php endif; ?>
