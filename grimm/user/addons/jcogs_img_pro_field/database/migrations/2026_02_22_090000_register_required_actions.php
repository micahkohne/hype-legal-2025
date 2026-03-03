<?php

/**
 * JCOGS Image Pro Field - Migration: Register Required Actions
 *=============================================================
 * Defensive migration to ensure core ACT endpoints are registered.
 */

use ExpressionEngine\Service\Migration\Migration;

class RegisterRequiredActions extends Migration
{
    public function up()
    {
        $this->ensureAction('usage');
        $this->ensureAction('preview');
        $this->ensureAction('face_detect');
    }

    public function down()
    {
        // No-op: do not remove actions in a reconciliation migration.
    }

    private function ensureAction(string $method): void
    {
        $exists = ee('Model')->get('Action')
            ->filter('class', 'Jcogs_img_pro_field')
            ->filter('method', $method)
            ->count();

        if ((int) $exists > 0) {
            return;
        }

        ee('Model')->make('Action', [
            'class' => 'Jcogs_img_pro_field',
            'method' => $method,
            'csrf_exempt' => 0,
        ])->save();
    }
}
