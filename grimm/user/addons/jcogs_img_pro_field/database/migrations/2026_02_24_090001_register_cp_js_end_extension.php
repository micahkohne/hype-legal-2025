<?php

use ExpressionEngine\Service\Migration\Migration;

class RegisterCpJsEndExtension extends Migration
{
    public function up()
    {
        $addon = ee('Addon')->get('jcogs_img_pro_field');
        if (!$addon) {
            return;
        }

        $exists = ee('Model')->get('Extension')
            ->filter('class', $addon->getExtensionClass())
            ->filter('method', 'cp_js_end')
            ->filter('hook', 'cp_js_end')
            ->count();

        if ($exists > 0) {
            return;
        }

        $ext = [
            'class' => $addon->getExtensionClass(),
            'method' => 'cp_js_end',
            'hook' => 'cp_js_end',
            'settings' => serialize([]),
            'priority' => 10,
            'version' => $addon->getVersion(),
            'enabled' => 'y',
        ];

        ee('Model')->make('Extension', $ext)->save();
    }

    public function down()
    {
        $addon = ee('Addon')->get('jcogs_img_pro_field');
        if (!$addon) {
            return;
        }

        ee('Model')->get('Extension')
            ->filter('class', $addon->getExtensionClass())
            ->filter('method', 'cp_js_end')
            ->filter('hook', 'cp_js_end')
            ->delete();
    }
}
