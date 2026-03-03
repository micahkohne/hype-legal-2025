<?php

/**
 * JCOGS Image Pro Field - Migration: Register after_channel_entry_delete
 *======================================================================
 * Registers the after_channel_entry_delete hygiene hook.
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
 * Migration: register after_channel_entry_delete extension.
 */
class RegisterAfterChannelEntryDeleteExtension extends Migration
{
    public function up()
    {
        $addon = ee('Addon')->get('jcogs_img_pro_field');

        $exists = ee('Model')->get('Extension')
            ->filter('class', $addon->getExtensionClass())
            ->filter('method', 'after_channel_entry_delete')
            ->count();

        if ($exists > 0) {
            return;
        }

        $ext = [
            'class' => $addon->getExtensionClass(),
            'method' => 'after_channel_entry_delete',
            'hook' => 'after_channel_entry_delete',
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
            ->filter('method', 'after_channel_entry_delete')
            ->delete();
    }
}
