<?php

use BoldMinded\Carson\Dependency\Litzinger\Basee\Setting;
use ExpressionEngine\Service\Migration\Migration;

class InstallCarson extends Migration
{
    /**
     * Execute the migration
     * @return void
     */
    public function up()
    {
        $addon = ee('Addon')->get('carson');

        $ext = [
            'class' => $addon->getExtensionClass(),
            'method' => 'cp_js_end',
            'hook' => 'cp_js_end',
            'settings' => serialize([]),
            'priority' => 10,
            'version' => $addon->getVersion(),
            'enabled' => 'y'
        ];

        ee('Model')->make('Extension', $ext)->save();

        /** @var Setting $setting */
        $setting = ee('carson:Setting');
        $setting->createTable();
        $setting->save(array_merge([
            'installed_date' => time(),
            'installed_version' => CARSON_VERSION,
            'installed_build' => CARSON_BUILD_VERSION,
        ], ee('carson:Config')));
    }

    /**
     * Rollback the migration
     * @return void
     */
    public function down()
    {
        $addon = ee('Addon')->get('carson');

        ee('Model')->get('Extension')
            ->filter('class', $addon->getExtensionClass())
            ->delete();

        ee('db')->where('class', CARSON_NAME_SHORT)
            ->delete('actions');

        ee()->load->dbforge();
        ee()->dbforge->drop_table('carson_settings');
    }
}
