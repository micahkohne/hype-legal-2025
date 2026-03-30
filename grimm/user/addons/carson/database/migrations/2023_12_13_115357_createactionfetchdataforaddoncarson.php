<?php

use ExpressionEngine\Service\Migration\Migration;

class Createactionfetchdataforaddoncarson extends Migration
{
    /**
     * Execute the migration
     * @return void
     */
    public function up()
    {
        ee('Model')->make('Action', [
            'class' => 'Carson',
            'method' => 'FetchData',
            'csrf_exempt' => false,
        ])->save();
    }

    /**
     * Rollback the migration
     * @return void
     */
    public function down()
    {
        ee('Model')->get('Action')
            ->filter('class', 'Carson')
            ->filter('method', 'FetchData')
            ->delete();
    }
}
