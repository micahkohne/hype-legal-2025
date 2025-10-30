// JCOGS Image Pro - Enhanced Preset Editor JavaScript
// Configuration will be set by the controller
window.jcogsImageProConfig = window.jcogsImageProConfig || {};

// Live Preview System
let currentPreviewImage = null;
let previewProcessingTimer = null;

console.log('JCOGS Image Pro: preset-edit.js file loaded successfully');

// DEBUG: Check what functions exist globally
console.log('JCOGS Image Pro: Checking window object for existing functions...');
console.log('window.refreshPreview exists:', typeof window.refreshPreview);
console.log('window.quickEditParameter exists:', typeof window.quickEditParameter);

// Parameter search and filtering
function filterParameters() {
    const searchInput = document.getElementById('parameter-search');
    const categoryFilter = document.getElementById('category-filter');
    const parameterItems = document.querySelectorAll('.parameter-item');
    
    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    const selectedCategory = categoryFilter ? categoryFilter.value : '';
    
    parameterItems.forEach(item => {
        const paramName = item.dataset.name ? item.dataset.name.toLowerCase() : '';
        const paramCategory = item.dataset.category || '';
        
        const matchesSearch = paramName.includes(searchTerm);
        const matchesCategory = !selectedCategory || paramCategory === selectedCategory;
        
        if (matchesSearch && matchesCategory) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

// Live Preview Functions
function initializePreviewImage() {
    const originalImg = document.getElementById('original-preview');
    if (originalImg && originalImg.src && originalImg.style.display !== 'none') {
        // Image is already loaded from server, set as current preview
        currentPreviewImage = originalImg.src;
        // Auto-trigger preview update if we have parameters
        const parameterItems = document.querySelectorAll('.parameter-item');
        if (parameterItems.length > 0) {
            setTimeout(updatePreview, 1000); // Small delay to let everything load
        }
    }
}

function updatePreview() {
    if (!currentPreviewImage) return;
    
    const processedImg = document.getElementById('processed-preview');
    const processedPlaceholder = document.getElementById('processed-placeholder');
    const loadingIndicator = document.getElementById('preview-loading');
    const statusIndicator = document.getElementById('processing-status');
    
    // Show loading state
    if (processedImg) processedImg.style.display = 'none';
    if (processedPlaceholder) processedPlaceholder.style.display = 'none';
    if (loadingIndicator) loadingIndicator.style.display = 'flex';
    if (statusIndicator) {
        statusIndicator.textContent = 'Processing...';
        statusIndicator.className = 'status-indicator processing';
    }
    
    // Collect current preset parameters
    const presetData = collectPresetParameters();
    
    // Simulate processing time
    const startTime = Date.now();
    
    // Send to backend for processing
    processPreviewImage(presetData)
        .then(result => {
            const processingTime = Date.now() - startTime;
            displayProcessedImage(result, processingTime);
        })
        .catch(error => {
            console.error('Preview processing error:', error);
            showPreviewError();
        });
}

function collectPresetParameters() {
    const parameters = {};
    
    // Collect all parameter values from the UI
    document.querySelectorAll('.parameter-item').forEach(item => {
        // Use data-name attribute for actual parameter name instead of display text
        const name = item.dataset.name;
        const valueElement = item.querySelector('.parameter-value-display');
        
        if (name && valueElement) {
            let value = valueElement.textContent.trim();
            
            // Handle empty values
            if (!value || value === '(not set)' || value === '') {
                // Skip empty parameters or use a default based on parameter type
                console.log('JCOGS Preview: Skipping empty parameter:', name);
                return;
            }
            
            parameters[name] = value;
            console.log('JCOGS Preview: Collected parameter:', name, '=', value);
        }
    });
    
    console.log('JCOGS Preview: Total parameters collected:', Object.keys(parameters).length);
    console.log('JCOGS Preview: Full parameter set:', parameters);
    
    return parameters;
}

function processPreviewImage(presetData) {
    // Real AJAX implementation using the Preview endpoint
    return new Promise((resolve, reject) => {
        // Get current preview image file ID - try multiple sources
        let previewFileId = null;
        
        // First, try the file picker input
        const previewImagePicker = document.querySelector('input[name="preview_file_id"]');
        if (previewImagePicker && previewImagePicker.value) {
            previewFileId = parseInt(previewImagePicker.value);
        }
        
        // Fallback to configuration value
        if (!previewFileId && window.jcogsImageProConfig && window.jcogsImageProConfig.currentPreviewFileId) {
            previewFileId = window.jcogsImageProConfig.currentPreviewFileId;
        }
        
        // If still no preview file ID, let the backend handle default fallback
        console.log('JCOGS Preview: Using preview file ID:', previewFileId);
        console.log('JCOGS Preview: Preview URL:', window.jcogsImageProConfig.previewUrl);
        
        // Prepare request data
        const requestData = {
            parameters: presetData,
            preview_file_id: previewFileId,
            csrf_token: window.jcogsImageProConfig.csrfToken
        };
        
        console.log('JCOGS Preview: Request data:', requestData);
        
        // Make AJAX request to preview endpoint
        fetch(window.jcogsImageProConfig.previewUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': window.jcogsImageProConfig.csrfToken
            },
            body: JSON.stringify(requestData)
        })
        .then(response => {
            console.log('JCOGS Preview: Response status:', response.status);
            console.log('JCOGS Preview: Response headers:', response.headers);
            return response.json();
        })
        .then(data => {
            console.log('JCOGS Preview: Response data:', data);
            if (data.status === 'success') {
                console.log('JCOGS Preview: Successfully generated preview URL:', data.data.preview_url);
                resolve({
                    processedImage: data.data.preview_url + '&cb=' + data.data.cache_buster,
                    metadata: {
                        width: 'Processing...',
                        height: 'Processing...',
                        fileSize: 'Processing...',
                        processingTime: 'Live'
                    }
                });
            } else {
                console.error('JCOGS Preview: Backend error:', data.message);
                reject(new Error(data.message || 'Preview generation failed'));
            }
        })
        .catch(error => {
            console.error('JCOGS Preview: AJAX error:', error);
            reject(error);
        });
    });
}

function displayProcessedImage(result, processingTime) {
    const processedImg = document.getElementById('processed-preview');
    const originalImg = document.getElementById('original-preview');
    const loadingIndicator = document.getElementById('preview-loading');
    const statusIndicator = document.getElementById('processing-status');
    const metadataPanel = document.getElementById('preview-metadata');
    
    // Hide loading, show processed image
    if (loadingIndicator) loadingIndicator.style.display = 'none';
    if (processedImg) {
        processedImg.src = result.processedImage;
        processedImg.style.display = 'block';
        
        // When the processed image loads, get its dimensions
        processedImg.onload = function() {
            updateEnhancedMetadata(originalImg, processedImg, result.processedImage, processingTime);
        };
    }
    
    // Update status
    if (statusIndicator) {
        statusIndicator.textContent = 'Complete';
        statusIndicator.className = 'status-indicator complete';
    }
    
    // Show metadata panel
    if (metadataPanel) {
        metadataPanel.style.display = 'block';
    }
}

function showPreviewError() {
    const loadingIndicator = document.getElementById('preview-loading');
    const statusIndicator = document.getElementById('processing-status');
    const processedPlaceholder = document.getElementById('processed-placeholder');
    
    if (loadingIndicator) loadingIndicator.style.display = 'none';
    if (processedPlaceholder) {
        processedPlaceholder.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            <p>Processing failed</p>
            <button type="button" class="btn btn-sm btn-primary" onclick="updatePreview()">
                <i class="fas fa-redo"></i> Retry
            </button>
        `;
        processedPlaceholder.style.display = 'flex';
    }
    if (statusIndicator) {
        statusIndicator.textContent = 'Error';
        statusIndicator.className = 'status-indicator error';
    }
}

function updateMetadata(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = value;
    }
}

function updateEnhancedMetadata(originalImg, processedImg, processedUrl, processingTime) {
    // Get original image dimensions
    const originalWidth = originalImg ? originalImg.naturalWidth : 0;
    const originalHeight = originalImg ? originalImg.naturalHeight : 0;
    
    // Get processed image dimensions
    const processedWidth = processedImg ? processedImg.naturalWidth : 0;
    const processedHeight = processedImg ? processedImg.naturalHeight : 0;
    
    // Calculate size change
    let sizeChange = '--';
    if (originalWidth && originalHeight && processedWidth && processedHeight) {
        const originalPixels = originalWidth * originalHeight;
        const processedPixels = processedWidth * processedHeight;
        const changePercent = ((processedPixels - originalPixels) / originalPixels * 100).toFixed(1);
        const changeIcon = changePercent > 0 ? '↗' : changePercent < 0 ? '↘' : '→';
        sizeChange = `${changeIcon} ${Math.abs(changePercent)}%`;
    }
    
    // Collect applied effects from current parameters
    const appliedEffects = getAppliedEffects();
    
    // Update all metadata fields
    updateMetadata('original-size', originalWidth && originalHeight ? `${originalWidth} × ${originalHeight}` : '--');
    updateMetadata('processed-size', processedWidth && processedHeight ? `${processedWidth} × ${processedHeight}` : '--');
    updateMetadata('size-change', sizeChange);
    updateMetadata('processing-time', `${(processingTime / 1000).toFixed(2)}s`);
    updateMetadata('applied-effects', appliedEffects);
    
    // Update preview URL link
    const previewUrlElement = document.getElementById('preview-url');
    if (previewUrlElement && previewUrlElement.querySelector('a')) {
        previewUrlElement.querySelector('a').href = processedUrl;
        previewUrlElement.querySelector('a').textContent = 'Open Processed Image';
    }
    
    // Check if preview image is scaled down and show size warning
    checkAndShowSizeWarning(processedImg, processedUrl, processedWidth, processedHeight);
}

function checkAndShowSizeWarning(processedImg, processedUrl, actualWidth, actualHeight) {
    const sizeWarning = document.getElementById('preview-size-warning');
    const sizeWarningLink = document.getElementById('size-warning-link');
    
    if (!sizeWarning || !processedImg || !actualWidth || !actualHeight) {
        return;
    }
    
    // Get the displayed size of the preview image
    const displayedWidth = processedImg.offsetWidth;
    const displayedHeight = processedImg.offsetHeight;
    
    // Check if the actual image is significantly larger than what's displayed
    // Using a 10% threshold to avoid showing warning for small differences
    const isScaledDown = (actualWidth > displayedWidth * 1.1) || (actualHeight > displayedHeight * 1.1);
    
    if (isScaledDown) {
        // Update the warning link
        if (sizeWarningLink) {
            sizeWarningLink.href = processedUrl;
        }
        // Show the warning
        sizeWarning.classList.add('show');
    } else {
        // Hide the warning
        sizeWarning.classList.remove('show');
    }
}

function getAppliedEffects() {
    const effects = [];
    const parameters = collectPresetParameters();
    
    // Check for various effects based on parameters (using lowercase names to match collectPresetParameters)
    if (parameters.width && parameters.width !== '0') {
        effects.push(`Resize (${parameters.width}px)`);
    }
    
    if (parameters.text && parameters.text.trim() && !parameters.text.startsWith('|')) {
        effects.push('Text Overlay');
    }
    
    if (parameters.rounded_corners && parameters.rounded_corners !== 'none') {
        effects.push('Rounded Corners');
    }
    
    if (parameters.watermark && parameters.watermark.trim() && !parameters.watermark.startsWith('|')) {
        effects.push('Watermark');
    }
    
    if (parameters.filter && parameters.filter !== 'none') {
        const filterParts = parameters.filter.split('|');
        const activeFilters = [];
        
        if (filterParts[0] && filterParts[0].split(':')[1] !== '0') activeFilters.push('Blur');
        if (filterParts[1] && filterParts[1].split(':')[1] !== '0') activeFilters.push('Brightness');
        if (filterParts[2] && filterParts[2].split(':')[1] !== '1') activeFilters.push('Smooth');
        
        if (activeFilters.length > 0) {
            effects.push(`Filters (${activeFilters.join(', ')})`);
        }
    }
    
    if (parameters.crop && parameters.crop.startsWith('yes')) {
        effects.push('Crop');
    }
    
    if (parameters.border && parameters.border !== '0' && parameters.border !== '0px') {
        effects.push(`Border (${parameters.border})`);
    }
    
    return effects.length > 0 ? effects.join(', ') : 'None';
}

// Event Listeners for Parameter Changes
function initializeParameterChangeListeners() {
    // Listen for parameter changes to trigger preview updates
    document.addEventListener('change', function(e) {
        if (e.target.closest('.quick-edit-form') || e.target.name === 'parameter_value') {
            // Debounce preview updates
            clearTimeout(previewProcessingTimer);
            previewProcessingTimer = setTimeout(updatePreview, 500);
        }
    });
    
    // Listen for parameter form submissions
    document.addEventListener('submit', function(e) {
        if (e.target.closest('.quick-edit-form')) {
            // Allow the form to submit normally, then update preview after page reload
            setTimeout(function() {
                if (window.location.pathname.includes('/presets/edit/')) {
                    updatePreview();
                }
            }, 1000);
        }
    });
}

function initializeParameterSelection() {
    // Any parameter selection initialization code would go here
    console.log('JCOGS Image Pro: Parameter selection initialized');
}

// Functions called from HTML onclick handlers
function quickEditParameter(parameterName) {
    console.log('JCOGS Image Pro: Quick edit requested for parameter:', parameterName);
    
    // Find the parameter item by data-name attribute
    const paramItems = document.querySelectorAll('.parameter-item');
    let targetItem = null;
    
    paramItems.forEach(item => {
        const dataName = item.getAttribute('data-name');
        if (dataName === parameterName) {
            targetItem = item;
        }
    });
    
    if (!targetItem) {
        console.error('JCOGS Image Pro: Parameter not found:', parameterName);
        return;
    }
    
    // Toggle or show quick edit form
    const existingForm = targetItem.querySelector('.quick-edit-form');
    if (existingForm) {
        // Remove existing form and recreate it to ensure proper event listeners
        console.log('JCOGS Image Pro: Removing existing form to recreate with proper handlers');
        existingForm.remove();
    }
    
    // Create quick edit form
    const valueDisplay = targetItem.querySelector('.parameter-value-display');
    const currentValue = valueDisplay ? valueDisplay.textContent.trim() : '';
    
    console.log('JCOGS Image Pro: Creating new quick edit form for', parameterName, 'with value:', currentValue);
        
        const formHtml = `
            <div class="quick-edit-form" style="margin-top: 10px;" data-parameter="${parameterName}">
                <div class="input-group">
                    <input type="text" class="form-control" name="parameter_value" value="${currentValue}" placeholder="Enter value...">
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-primary quick-edit-save">
                            <i class="fas fa-check"></i>
                        </button>
                        <button type="button" class="btn btn-default quick-edit-cancel">
                            <i class="fas fa-times"></i>
                        </button>
                    </span>
                </div>
            </div>
        `;
        
        targetItem.insertAdjacentHTML('beforeend', formHtml);
        
        // Add event listeners to the newly created buttons
        const newForm = targetItem.querySelector('.quick-edit-form[data-parameter="' + parameterName + '"]');
        const saveBtn = newForm.querySelector('.quick-edit-save');
        const cancelBtn = newForm.querySelector('.quick-edit-cancel');
        
        saveBtn.addEventListener('click', function() {
            saveQuickEdit(parameterName, this);
        });
        
        cancelBtn.addEventListener('click', function() {
            cancelQuickEdit(this);
        });
}

function saveQuickEdit(parameterName, buttonElement) {
    console.log('JCOGS Image Pro: Save quick edit called', parameterName, buttonElement);
    
    // Ensure we have a valid DOM element
    if (!buttonElement || typeof buttonElement.closest !== 'function') {
        console.error('JCOGS Image Pro: Invalid button element passed to saveQuickEdit', buttonElement);
        return;
    }
    
    const form = buttonElement.closest('.quick-edit-form');
    if (!form) {
        console.error('JCOGS Image Pro: Could not find quick-edit-form');
        return;
    }
    
    const input = form.querySelector('input[name="parameter_value"]');
    if (!input) {
        console.error('JCOGS Image Pro: Could not find parameter_value input');
        return;
    }
    
    const newValue = input.value.trim();
    
    console.log('JCOGS Image Pro: Saving quick edit for', parameterName, '=', newValue);
    
    // Update the display
    const paramItem = form.closest('.parameter-item');
    const valueDisplay = paramItem.querySelector('.parameter-value-display');
    if (valueDisplay) {
        valueDisplay.textContent = newValue || '(not set)';
    }
    
    // Hide the form
    form.style.display = 'none';
    
    // Get values for debugging
    const presetId = getPresetId();
    const csrfToken = getCSRFToken();
    
    console.log('JCOGS Image Pro: Debug info:');
    console.log('  - Preset ID:', presetId);
    console.log('  - CSRF Token:', csrfToken);
    console.log('  - Parameter Name:', parameterName);
    console.log('  - Parameter Value:', newValue);
    
    // Create a temporary form to submit the parameter update
    const tempForm = document.createElement('form');
    tempForm.method = 'POST';
    tempForm.action = 'admin.php?/cp/addons/settings/jcogs_img_pro/presets/edit/' + presetId;
    tempForm.style.display = 'none';
    
    console.log('JCOGS Image Pro: Form action:', tempForm.action);
    
    // Add CSRF token
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = csrfToken;
    tempForm.appendChild(csrfInput);
    
    // Add parameter name
    const paramNameInput = document.createElement('input');
    paramNameInput.type = 'hidden';
    paramNameInput.name = 'quick_edit_parameter';
    paramNameInput.value = parameterName;
    tempForm.appendChild(paramNameInput);
    
    // Add parameter value
    const paramValueInput = document.createElement('input');
    paramValueInput.type = 'hidden';
    paramValueInput.name = 'quick_edit_value';
    paramValueInput.value = newValue;
    tempForm.appendChild(paramValueInput);
    
    console.log('JCOGS Image Pro: Form data being submitted:');
    console.log('  - csrf_token:', csrfToken);
    console.log('  - quick_edit_parameter:', parameterName);
    console.log('  - quick_edit_value:', newValue);
    
    // Use AJAX instead of form submission for better error handling
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('quick_edit_parameter', parameterName);
    formData.append('quick_edit_value', newValue);
    
    fetch('admin.php?/cp/addons/settings/jcogs_img_pro/presets/edit/' + presetId, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => {
        if (response.ok) {
            // Success - reload the page to show updated data and any success messages
            window.location.reload();
        } else {
            // Error - try to get detailed error message
            return response.json().then(errorData => {
                const errorMessage = errorData.error || 'Failed to save parameter. Please check the value and try again.';
                showQuickEditError(paramItem, errorMessage);
            }).catch(() => {
                // Fallback if JSON parsing fails
                showQuickEditError(paramItem, 'Failed to save parameter. Please check the value and try again.');
            });
        }
    })
    .catch(error => {
        console.error('JCOGS Image Pro: AJAX error:', error);
        showQuickEditError(paramItem, 'Network error occurred. Please try again.');
    });
}

// Show quick edit error message
function showQuickEditError(paramItem, message) {
    // Remove any existing error messages
    const existingError = paramItem.querySelector('.quick-edit-error');
    if (existingError) {
        existingError.remove();
    }
    
    // Create error message element
    const errorDiv = document.createElement('div');
    errorDiv.className = 'quick-edit-error';
    errorDiv.style.cssText = `
        background: #ff6b6b;
        color: white;
        padding: 8px 12px;
        border-radius: 4px;
        margin: 8px 0;
        font-size: 14px;
        border: 1px solid #ff5252;
    `;
    errorDiv.textContent = message;
    
    // Insert error message after the quick edit form
    const quickEditForm = paramItem.querySelector('.quick-edit-form');
    if (quickEditForm) {
        quickEditForm.parentNode.insertBefore(errorDiv, quickEditForm.nextSibling);
        
        // Auto-remove the error after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.remove();
            }
        }, 5000);
    }
}

// Helper functions
function getPresetId() {
    // Extract preset ID from URL or data attribute
    const url = window.location.href;
    const match = url.match(/\/presets\/edit\/(\d+)/);
    return match ? match[1] : null;
}

function getCSRFToken() {
    // Get CSRF token from meta tag or existing form
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (metaTag) {
        return metaTag.getAttribute('content');
    }
    
    // Fallback: get from existing form
    const existingForm = document.querySelector('input[name="csrf_token"]');
    if (existingForm) {
        return existingForm.value;
    }
    
    return '';
}

function cancelQuickEdit(buttonElement) {
    console.log('JCOGS Image Pro: Cancel quick edit called', buttonElement);
    
    // Ensure we have a valid DOM element
    if (!buttonElement || typeof buttonElement.closest !== 'function') {
        console.error('JCOGS Image Pro: Invalid button element passed to cancelQuickEdit', buttonElement);
        return;
    }
    
    const form = buttonElement.closest('.quick-edit-form');
    if (form) {
        form.style.display = 'none';
        console.log('JCOGS Image Pro: Quick edit form hidden');
    } else {
        console.error('JCOGS Image Pro: Could not find quick-edit-form');
    }
}

function refreshPreview() {
    console.log('JCOGS Image Pro: Manual preview refresh requested');
    debugger; // BREAKPOINT: Set browser breakpoint here to debug refresh function
    updatePreview();
}

// Make sure the function is globally accessible
window.refreshPreview = refreshPreview;
window.quickEditParameter = quickEditParameter;

// DEBUG: Confirm functions are assigned to window
console.log('JCOGS Image Pro: Functions assigned to window object');
console.log('window.refreshPreview after assignment:', typeof window.refreshPreview);
console.log('window.quickEditParameter after assignment:', typeof window.quickEditParameter);

// Search and Filter Event Listeners
function initializeSearchAndFilter() {
    const searchInput = document.getElementById('parameter-search');
    const categoryFilter = document.getElementById('category-filter');
    
    if (searchInput) {
        searchInput.addEventListener('input', filterParameters);
    }
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', filterParameters);
    }
}

// Add Parameter Modal Initialization
function initializeAddParameterModal() {
    const parameterSelect = document.getElementById('parameter-select');
    const confirmBtn = document.getElementById('confirm-add-parameter');
    
    if (parameterSelect && confirmBtn) {
        // Handle parameter selection to enable/disable Add Parameter button
        parameterSelect.addEventListener('change', function(e) {
            console.log('JCOGS Image Pro: Parameter selected:', e.target.value);
            
            if (e.target.value && e.target.value !== '') {
                confirmBtn.disabled = false;
                console.log('JCOGS Image Pro: Add Parameter button enabled');
            } else {
                confirmBtn.disabled = true;
                console.log('JCOGS Image Pro: Add Parameter button disabled');
            }
        });
        
        console.log('JCOGS Image Pro: Add Parameter modal initialized');
    } else {
        console.log('JCOGS Image Pro: Add Parameter modal elements not found');
    }
}

// Main Initialization
document.addEventListener('DOMContentLoaded', function() {
    console.log('JCOGS Image Pro: Initializing preset editor...');
    
    // DEBUG: Check if our functions still exist after other scripts load
    console.log('JCOGS Image Pro: Checking functions at DOMContentLoaded...');
    console.log('window.refreshPreview at init:', typeof window.refreshPreview);
    console.log('window.quickEditParameter at init:', typeof window.quickEditParameter);
    
    // Set a delayed check to see if functions get overridden
    setTimeout(function() {
        console.log('JCOGS Image Pro: Delayed check (3 seconds) for function existence...');
        console.log('window.refreshPreview after delay:', typeof window.refreshPreview);
        console.log('window.quickEditParameter after delay:', typeof window.quickEditParameter);
        
        // BREAKPOINT: Set browser breakpoint here to check final state
        // debugger;
    }, 3000);
    
    // Initialize with default preview image if available
    initializePreviewImage();
    
    // Initialize all component systems
    initializeParameterSelection();
    initializeParameterChangeListeners();
    initializeSearchAndFilter();
    initializeAddParameterModal();
    
    // Auto-trigger initial preview if we have parameters
    const parameterItems = document.querySelectorAll('.parameter-item');
    if (parameterItems.length > 0) {
        console.log('JCOGS Image Pro: Found ' + parameterItems.length + ' parameters, triggering initial preview...');
        setTimeout(updatePreview, 2000); // Give everything time to load
    }
    
    console.log('JCOGS Image Pro: Preset editor initialization complete.');
});
