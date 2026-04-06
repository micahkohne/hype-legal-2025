<?php

/**
 * JCOGS Image Pro Field - Migration: Register Preview Action
 *==========================================================
 * Registers the ACT endpoint that generates preview output for the publish UI.
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.2
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 * @since      0.1.6
 */

use ExpressionEngine\Service\Migration\Migration;

/**
 * Migration: register Preview ACT.
 */
class RegisterPreviewAction extends Migration
{
    public function up()
    {
        $exists = ee('Model')->get('Action')
            ->filter('class', 'Jcogs_img_pro_field')
            ->filter('method', 'preview')
            ->count();

        if ($exists > 0) {
            return;
        }

        ee('Model')->make('Action', [
            'class' => 'Jcogs_img_pro_field',
            'method' => 'preview',
            'csrf_exempt' => 0,
        ])->save();
    }

    public function down()
    {
        ee('Model')->get('Action')
            ->filter('class', 'Jcogs_img_pro_field')
            ->filter('method', 'preview')
            ->delete();
    }
}
