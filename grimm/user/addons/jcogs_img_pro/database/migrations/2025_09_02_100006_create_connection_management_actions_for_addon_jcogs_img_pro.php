<?php

use ExpressionEngine\Service\Migration\Migration;

class CreateConnectionManagementActionsForAddonJcogsImgPro extends Migration
{
    /**
     * Execute the migration
     * @return void
     */
    public function up()
    {
        $actions = [
            [
                'class' => 'Jcogs_img_pro',
                'method' => 'new_connection',
                'csrf_exempt' => 0
            ],
            [
                'class' => 'Jcogs_img_pro', 
                'method' => 'edit_connection',
                'csrf_exempt' => 0
            ],
            [
                'class' => 'Jcogs_img_pro',
                'method' => 'delete_connection',
                'csrf_exempt' => 0
            ],
            [
                'class' => 'Jcogs_img_pro',
                'method' => 'save_connection',
                'csrf_exempt' => 0
            ]
        ];

        foreach ($actions as $action) {
            // Check if action already exists
            $existing = ee('Model')->get('Action')
                ->filter('class', $action['class'])
                ->filter('method', $action['method'])
                ->first();

            if (!$existing) {
                ee('Model')->make('Action', $action)->save();
            }
        }
    }

    /**
     * Rollback the migration
     * @return void
     */
    public function down()
    {
        $addon = ee('Addon')->get('jcogs_img_pro');

        $methods = [
            'new_connection',
            'edit_connection', 
            'delete_connection',
            'save_connection'
        ];

        foreach ($methods as $method) {
            ee('Model')->get('Action')
                ->filter('class', $addon->getInstallerClass())
                ->filter('method', $method)
                ->delete();
        }
    }
}
