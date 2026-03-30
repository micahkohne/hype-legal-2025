<?php

use ExpressionEngine\Service\Migration\Migration;

class CreateExtHookCustomFieldModifyDataForAddonCarson extends Migration
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
            'method' => 'custom_field_modify_data',
            'hook' => 'custom_field_modify_data',
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
        $addon = ee('Addon')->get('carson');

        ee('Model')->get('Extension')
            ->filter('class', $addon->getExtensionClass())
            ->delete();
    }
}
