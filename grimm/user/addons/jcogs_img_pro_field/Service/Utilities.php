<?php

/**
 * JCOGS Image Pro Field - Utilities
 *=================================
 * Lightweight utilities for this add-on.
 *
 * Currently provides template log debugging to assist local development.
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

namespace JCOGSDesign\JcogsImgProField\Service;

/**
 * Utility helpers.
 */
class Utilities
{
    /**
     * Emit a message to EE's template log (visible in CP template debugger).
     */
    public function debug_message(string $key, array $context = []): void
    {
        if (function_exists('ee') && isset(ee()->TMPL) && is_object(ee()->TMPL) && method_exists(ee()->TMPL, 'log_item')) {
            ee()->TMPL->log_item('[jcogs_img_pro_field] ' . $key . (!empty($context) ? ' ' . json_encode($context) : ''));
        }
    }
}
