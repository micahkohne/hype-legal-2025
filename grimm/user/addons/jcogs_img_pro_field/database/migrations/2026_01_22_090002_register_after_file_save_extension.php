<?php

/**
 * JCOGS Image Pro Field - Migration: Register after_file_save
 *===========================================================
 * Registers the after_file_save extension hook handler.
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.0
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 * @since      0.1.6
 */

use ExpressionEngine\Service\Migration\Migration;

/**
 * Migration: register after_file_save extension.
 */
class RegisterAfterFileSaveExtension extends Migration
{
    public function up()
    {
        $addon = ee('Addon')->get('jcogs_img_pro_field');

        $ext = [
            'class' => $addon->getExtensionClass(),
            'method' => 'after_file_save',
            'hook' => 'after_file_save',
            'settings' => serialize([]),
            'priority' => 5,
            'version' => $addon->getVersion(),
            'enabled' => 'y',
        ];

        ee('Model')->make('Extension', $ext)->save();
    }

    public function down()
    {
        $addon = ee('Addon')->get('jcogs_img_pro_field');

        ee('Model')->get('Extension')
            ->filter('class', $addon->getExtensionClass())
            ->filter('method', 'after_file_save')
            ->delete();
    }
}
