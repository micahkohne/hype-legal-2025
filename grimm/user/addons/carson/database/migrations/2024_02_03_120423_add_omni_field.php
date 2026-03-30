<?php

use ExpressionEngine\Service\Migration\Migration;

class AddOmniField extends Migration
{
    /**
     * Execute the migration
     * @return void
     */
    public function up()
    {
        $addon = ee('Addon')->get('carson');

        $installed = ee('Model')->get('Fieldtype')
            ->filter('name', 'carson_omni')
            ->first();

        if (! $installed) {
            ee('Model')->make('Fieldtype', [
                'name' => 'carson_omni',
                'version' => $addon->getVersion(),
                'settings' => [],
                'has_global_settings' => 'n',
            ])->save();
        }
    }

    /**
     * Rollback the migration
     * @return void
     */
    public function down()
    {
        ee('Model')->get('Fieldtype')
            ->filter('name', 'carson_omni')
            ->delete();
    }
}
