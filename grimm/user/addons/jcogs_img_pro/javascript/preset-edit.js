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
        const nameElement = item.querySelector('.parameter-name');
        const valueElement = item.querySelector('.parameter-value-display');
        
        if (nameElement && valueElement) {
            const name = nameElement.textContent.trim();
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
        
        // Prepare request data
        const requestData = {
            parameters: presetData,
            preview_file_id: previewFileId
        };
        
        // Make AJAX request to preview endpoint
        fetch(window.jcogsImageProConfig.previewUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(data => {
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
    const loadingIndicator = document.getElementById('preview-loading');
    const statusIndicator = document.getElementById('processing-status');
    const metadataPanel = document.getElementById('preview-metadata');
    
    // Hide loading, show processed image
    if (loadingIndicator) loadingIndicator.style.display = 'none';
    if (processedImg) {
        processedImg.src = result.processedImage;
        processedImg.style.display = 'block';
    }
    
    // Update status
    if (statusIndicator) {
        statusIndicator.textContent = 'Complete';
        statusIndicator.className = 'status-indicator complete';
    }
    
    // Show and update metadata
    if (metadataPanel) {
        metadataPanel.style.display = 'block';
        updateMetadata('processed-size', `${result.metadata.width} × ${result.metadata.height}`);
        updateMetadata('file-size', result.metadata.fileSize);
        updateMetadata('processing-time', `${(processingTime / 1000).toFixed(2)}s`);
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
    
    // Find the parameter item
    const paramItems = document.querySelectorAll('.parameter-item');
    let targetItem = null;
    
    paramItems.forEach(item => {
        const nameElement = item.querySelector('.parameter-name');
        if (nameElement && nameElement.textContent.trim() === parameterName) {
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
        existingForm.style.display = existingForm.style.display === 'none' ? 'block' : 'none';
    } else {
        // Create quick edit form
        const valueDisplay = targetItem.querySelector('.parameter-value-display');
        const currentValue = valueDisplay ? valueDisplay.textContent.trim() : '';
        
        const formHtml = `
            <div class="quick-edit-form" style="margin-top: 10px;">
                <div class="input-group">
                    <input type="text" class="form-control" name="parameter_value" value="${currentValue}" placeholder="Enter value...">
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-primary" onclick="saveQuickEdit('${parameterName}', this)">
                            <i class="fas fa-check"></i>
                        </button>
                        <button type="button" class="btn btn-default" onclick="cancelQuickEdit(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </span>
                </div>
            </div>
        `;
        
        targetItem.insertAdjacentHTML('beforeend', formHtml);
    }
}

function saveQuickEdit(parameterName, buttonElement) {
    const form = buttonElement.closest('.quick-edit-form');
    const input = form.querySelector('input[name="parameter_value"]');
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
    
    // Trigger preview update
    setTimeout(updatePreview, 300);
}

function cancelQuickEdit(buttonElement) {
    const form = buttonElement.closest('.quick-edit-form');
    form.style.display = 'none';
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
    
    // Auto-trigger initial preview if we have parameters
    const parameterItems = document.querySelectorAll('.parameter-item');
    if (parameterItems.length > 0) {
        console.log('JCOGS Image Pro: Found ' + parameterItems.length + ' parameters, triggering initial preview...');
        setTimeout(updatePreview, 2000); // Give everything time to load
    }
    
    console.log('JCOGS Image Pro: Preset editor initialization complete.');
});
