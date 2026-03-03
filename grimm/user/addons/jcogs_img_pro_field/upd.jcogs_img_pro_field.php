<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

use ExpressionEngine\Service\Addon\Installer;

/**
 * JCOGS Image Pro Field - Updater
 *===============================
 * Installer/update entry-point.
 *
 * Delegates schema and registration work to EE migrations.
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
class Jcogs_img_pro_field_upd extends Installer
{
    public $has_cp_backend = 'y';
    public $has_publish_fields = 'y';

    public function install()
    {
        parent::install();
        $this->ensureRequiredActionsRegistered();
        return true;
    }

    public function update($current = '')
    {
        parent::update($current);
        $this->ensureRequiredActionsRegistered();
        return true;
    }

    public function uninstall()
    {
        parent::uninstall();
        return true;
    }

    private function ensureRequiredActionsRegistered(): void
    {
        $requiredMethods = ['usage', 'preview', 'face_detect'];

        foreach ($requiredMethods as $method) {
            try {
                $exists = ee('Model')->get('Action')
                    ->filter('class', 'Jcogs_img_pro_field')
                    ->filter('method', $method)
                    ->count();

                if ((int) $exists > 0) {
                    continue;
                }

                ee('Model')->make('Action', [
                    'class' => 'Jcogs_img_pro_field',
                    'method' => $method,
                    'csrf_exempt' => 0,
                ])->save();
            } catch (\Throwable $e) {
                // Fail-safe: do not block install/update if action self-heal fails.
            }
        }
    }
}
