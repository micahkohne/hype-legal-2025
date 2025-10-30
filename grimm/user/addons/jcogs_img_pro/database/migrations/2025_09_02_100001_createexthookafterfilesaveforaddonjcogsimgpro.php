<?php

use ExpressionEngine\Service\Migration\Migration;

class CreateExtHookAfterFileSaveForAddonJcogsImgPro extends Migration
{
    /**
     * Execute the migration
     * @return void
     */
    public function up()
    {
        $addon = ee('Addon')->get('jcogs_img_pro');

        $ext = [
            'class' => 'Jcogs_img_pro_ext',
            'method' => 'after_file_save',
            'hook' => 'after_file_save',
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
        ee('Model')->get('Extension')
            ->filter('class', 'Jcogs_img_pro_ext')
            ->delete();
    }
}
