<?php

/**
 * JCOGS Image Pro Field - Migration: Register FaceDetect Action
 *=============================================================
 * Registers the ACT endpoint for face detection requests.
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
 * Migration: register FaceDetect ACT.
 */
class RegisterFaceDetectAction extends Migration
{
    public function up()
    {
        $exists = ee('Model')->get('Action')
            ->filter('class', 'Jcogs_img_pro_field')
            ->filter('method', 'face_detect')
            ->count();

        if ($exists > 0) {
            return;
        }

        ee('Model')->make('Action', [
            'class' => 'Jcogs_img_pro_field',
            'method' => 'face_detect',
            'csrf_exempt' => 0,
        ])->save();
    }

    public function down()
    {
        ee('Model')->get('Action')
            ->filter('class', 'Jcogs_img_pro_field')
            ->filter('method', 'face_detect')
            ->delete();
    }
}
