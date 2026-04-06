<?php

/**
 * JCOGS Image Pro Field - CP JS End Extension
 * ===========================================
 * Injects update-status badge scripts into CP pages.
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.2
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 */

namespace JCOGSDesign\JcogsImgProField\Extensions;

use ExpressionEngine\Service\Addon\Controllers\Extension\AbstractRoute;

class CpJsEnd extends AbstractRoute
{
    /**
     * Handle cp_js_end hook.
     */
    public function process(): string
    {
        $scripts = [];
        if (ee()->extensions->last_call !== false) {
            $scripts[] = (string) ee()->extensions->last_call;
        }

        try {
            $updateService = ee('jcogs_img_pro_field:UpdateCheckService');
            $badgeScript = $updateService->getCpBadgeScript();
            if ($badgeScript !== '') {
                $scripts[] = $badgeScript;
            }
        } catch (\Throwable $e) {
            // Soft-fail: never block CP due to update checks.
        }

        return implode('', $scripts);
    }
}
