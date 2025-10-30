<?php

use ExpressionEngine\Service\Migration\Migration;

class CreateExtHookAfterFileDeleteForAddonJcogsImgPro extends Migration
{
    /**
     * Execute the migration
     * @return void
     */
    public function up()
    {
        $addon = ee('Addon')->get('jcogs_img_pro');

        $ext = [
            'class' => $addon->getExtensionClass(),
            'method' => 'after_file_delete',
            'hook' => 'after_file_delete',
            'settings' => serialize([]),
            'priority' => 10,
            'version' => $addon->getVersion(),
            'enabled' => 'y'
        ];

        // If we didnt find a matching Extension, lets just insert it
        ee('Model')->make('Extension', $ext)->save();
    }

    /**
     * Rollback the migration
     * @return void
     */
    public function down()
    {
        $addon = ee('Addon')->get('jcogs_img_pro');

        ee('Model')->get('Extension')
            ->filter('class', $addon->getExtensionClass())
            ->delete();
    }
}
