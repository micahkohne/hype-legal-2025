<?php

use ExpressionEngine\Service\Migration\Migration;

class CreateactionactoriginatedimageforaddonjcogsImgPro extends Migration
{
    /**
     * Execute the migration
     * @return void
     */
    public function up()
    {
        ee('Model')->make('Action', [
            'class' => 'Jcogs_img_pro',
            'method' => 'ActOriginatedImage',
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
            ->filter('class', 'Jcogs_img_pro')
            ->filter('method', 'ActOriginatedImage')
            ->delete();
    }
}
