/**
 * JCOGS Image Pro - Duration Form Field JavaScript
 * ================================================
 * Client-side enhancements for duration input fields with natural language support.
 * Provides real-time validation feedback and help text for duration inputs.
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta4
 * @link       https://JCOGS.net/
 * @since      Duration Enhancement Implementation
 */

document.addEventListener('DOMContentLoaded', function() {
    // Find all duration input fields
    const durationFields = document.querySelectorAll('.duration-input');
    
    durationFields.forEach(field => {
        const currentSeconds = parseInt(field.dataset.currentSeconds);
        const currentHuman = field.dataset.currentHuman;
        const context = field.dataset.durationContext;
        
        // Add help text below the field
        const helpDiv = document.createElement('div');
        helpDiv.className = 'duration-help';
        helpDiv.innerHTML = '<small class="text-muted">Currently: <strong>' + currentHuman + '</strong> (' + currentSeconds + ' seconds)</small>';
        field.parentNode.appendChild(helpDiv);
        
        // Add real-time validation feedback
        field.addEventListener('input', function() {
            clearTimeout(field.validationTimeout);
            field.validationTimeout = setTimeout(() => {
                validateDurationInput(field, context);
            }, 500);
        });
    });
    
    // Handle cache duration custom field toggle in parameter packages
    const cacheDurationSelects = document.querySelectorAll('.cache-duration-select');
    cacheDurationSelects.forEach(select => {
        const toggleField = select.dataset.toggleField;
        if (toggleField) {
            const customField = document.querySelector('[data-parameter="' + toggleField + '"]');
            if (customField) {
                // Show/hide custom field based on selection
                select.addEventListener('change', function() {
                    if (this.value === 'custom') {
                        customField.style.display = 'block';
                        customField.closest('.fieldset').style.display = 'block';
                    } else {
                        customField.style.display = 'none';
                        customField.closest('.fieldset').style.display = 'none';
                        customField.value = ''; // Clear custom value when hiding
                    }
                });
                
                // Initialize state
                if (select.value === 'custom') {
                    customField.style.display = 'block';
                    customField.closest('.fieldset').style.display = 'block';
                } else {
                    customField.style.display = 'none';
                    customField.closest('.fieldset').style.display = 'none';
                }
            }
        }
    });
    
    /**
     * Validate duration input field and provide feedback
     * @param {HTMLElement} field The duration input field
     * @param {string} context The validation context
     */
    function validateDurationInput(field, context) {
        const value = field.value.trim();
        if (!value) return;
        
        // This would ideally make an AJAX call to validate on the server
        // For now, we'll just provide basic client-side feedback
        const helpDiv = field.parentNode.querySelector('.duration-help');
        if (helpDiv) {
            if (isNaN(value) && !isSpecialKeyword(value)) {
                helpDiv.innerHTML = '<small class="text-muted">Examples: ' + field.dataset.examples.split('|').join(', ') + '</small>';
            }
        }
    }
    
    /**
     * Check if value contains special duration keywords
     * @param {string} value Input value to check
     * @returns {boolean}
     */
    function isSpecialKeyword(value) {
        const keywords = ['forever', 'never', 'disabled', 'daily', 'weekly', 'monthly'];
        return keywords.some(keyword => value.toLowerCase().includes(keyword));
    }
});
