/**
 * JCOGS Image Pro - Edit Connection JavaScript
 * ===========================================
 * Handles group visibility for adapter type forms when editing connections.
 * Since adapter type is disabled during editing, we manually show the correct
 * form group based on the connection's adapter type.
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta4
 * @link       https://JCOGS.net/
 * @since      Phase 3 Legacy Independence
 */

$(document).ready(function() {
    // Get the active adapter type from the hidden field
    var activeAdapterType = $('input[name="active_adapter_type"]').val() || 'local';
    
    // Map adapter types to their corresponding form groups
    var groupMap = {
        'local': 'jcogs_img_cp_flysystem_local_adapter',
        's3': 'jcogs_img_cp_flysystem_s3_adapter',
        'r2': 'jcogs_img_cp_flysystem_r2_adapter',
        'dospaces': 'jcogs_img_cp_flysystem_dospaces_adapter'
    };
    
    // Hide all adapter configuration groups first
    Object.values(groupMap).forEach(function(groupName) {
        $('[data-group="' + groupName + '"]').hide();
    });
    
    // Show only the active adapter's configuration group
    if (groupMap[activeAdapterType]) {
        $('[data-group="' + groupMap[activeAdapterType] + '"]').show();
    }
    
    // Debug logging (only in development)
    if (window.console && console.log) {
        console.log('Edit Connection: Showing group for adapter type:', activeAdapterType);
        console.log('Edit Connection: Target group:', groupMap[activeAdapterType]);
    }
});
