/**
 * JCOGS Image Pro - Preset Analytics JavaScript
 * ==============================================
 * JavaScript functionality for preset analytics interface
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta4
 * @link       https://JCOGS.net/
 * @since      Preset Analytics Interface Implementation
 */

document.addEventListener('DOMContentLoaded', function() {
    const resetBtn = document.getElementById('reset-statistics-btn');
    
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            const presetId = this.getAttribute('data-preset-id');
            const presetName = this.getAttribute('data-preset-name');
            
            // Show confirmation dialog
            const message = `Are you sure you want to reset all statistics for "${presetName}"?\n\nThis will clear:\n- Usage count\n- Error count\n- Performance data\n- Usage history\n\nThis action cannot be undone.`;
            
            if (confirm(message)) {
                // Show loading state
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';
                this.disabled = true;
                
                // Create form to submit reset request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.href; // Current analytics page
                
                // Add CSRF token - will be injected by PHP when loading
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = window.jcogsAnalyticsConfig ? window.jcogsAnalyticsConfig.csrfToken : '';
                form.appendChild(csrfInput);
                
                // Add reset action
                const resetInput = document.createElement('input');
                resetInput.type = 'hidden';
                resetInput.name = 'reset_statistics';
                resetInput.value = '1';
                form.appendChild(resetInput);
                
                // Submit form
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
});
