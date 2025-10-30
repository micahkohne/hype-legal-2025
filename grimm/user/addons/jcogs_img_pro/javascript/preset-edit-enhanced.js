/**
 * JCOGS Image Pro - Enhanced Preset Edit JavaScript
 * =================================================
 * Advanced parameter management and live preview integration
 */

class JCOGSImageProPresetEditor {
    constructor() {
        this.previewSystem = null;
        this.parameterData = {};
        this.filteredParameters = [];
        this.currentPreviewImage = null;
        
        this.init();
    }
    
    init() {
        this.initializePreviewSystem();
        this.setupParameterManagement();
        this.setupModals();
        this.setupPresetTools();
        this.setupLiveUpdates();
        
        console.log('JCOGS Image Pro Enhanced Preset Editor initialized');
    }
    
    /**
     * Initialize the live preview system
     */
    initializePreviewSystem() {
        // Check if live preview system is available
        if (typeof JCOGSImageProPreview !== 'undefined') {
            this.previewSystem = new JCOGSImageProPreview();
            this.setupPreviewEvents();
        }
    }
    
    /**
     * Setup preview-related event handlers
     */
    setupPreviewEvents() {
        // Auto-refresh toggle
        const autoRefreshCheckbox = document.getElementById('auto-refresh');
        if (autoRefreshCheckbox) {
            autoRefreshCheckbox.addEventListener('change', (e) => {
                if (this.previewSystem) {
                    this.previewSystem.setAutoRefresh(e.target.checked);
                }
            });
        }
        
        // Show metadata toggle
        const showMetadataCheckbox = document.getElementById('show-metadata');
        if (showMetadataCheckbox) {
            showMetadataCheckbox.addEventListener('change', (e) => {
                const metadataPanel = document.querySelector('.preview-metadata');
                if (metadataPanel) {
                    metadataPanel.style.display = e.target.checked ? 'block' : 'none';
                }
            });
        }
        
        // Preview image selection
        const selectImageBtn = document.getElementById('select-preview-image');
        const changeImageBtn = document.getElementById('change-preview-image');
        
        [selectImageBtn, changeImageBtn].forEach(btn => {
            if (btn) {
                btn.addEventListener('click', () => this.selectPreviewImage());
            }
        });
        
        // Refresh preview button
        const refreshBtn = document.getElementById('refresh-preview');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                if (this.previewSystem && this.currentPreviewImage) {
                    this.previewSystem.generatePreview();
                }
            });
        }
    }
    
    /**
     * Setup parameter management functionality
     */
    setupParameterManagement() {
        this.setupParameterSearch();
        this.setupParameterFiltering();
        this.setupQuickEdit();
        this.setupParameterActions();
    }
    
    /**
     * Setup parameter search functionality
     */
    setupParameterSearch() {
        const searchInput = document.getElementById('parameter-search');
        if (!searchInput) return;
        
        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            this.filterParameters(searchTerm);
        });
    }
    
    /**
     * Setup parameter category filtering
     */
    setupParameterFiltering() {
        const categoryFilter = document.getElementById('category-filter');
        if (!categoryFilter) return;
        
        categoryFilter.addEventListener('change', (e) => {
            const category = e.target.value;
            this.filterParametersByCategory(category);
        });
    }
    
    /**
     * Filter parameters by search term
     */
    filterParameters(searchTerm) {
        const parameterItems = document.querySelectorAll('.parameter-item');
        
        parameterItems.forEach(item => {
            const parameterName = item.dataset.name?.toLowerCase() || '';
            const parameterLabel = item.querySelector('.parameter-name')?.textContent.toLowerCase() || '';
            const parameterValue = item.querySelector('.parameter-value-display')?.textContent.toLowerCase() || '';
            
            const matches = parameterName.includes(searchTerm) || 
                          parameterLabel.includes(searchTerm) || 
                          parameterValue.includes(searchTerm);
            
            item.style.display = matches ? 'block' : 'none';
        });
    }
    
    /**
     * Filter parameters by category
     */
    filterParametersByCategory(category) {
        const parameterItems = document.querySelectorAll('.parameter-item');
        
        parameterItems.forEach(item => {
            const itemCategory = item.dataset.category || '';
            const matches = !category || itemCategory === category;
            
            item.style.display = matches ? 'block' : 'none';
        });
    }
    
    /**
     * Setup quick edit functionality
     */
    setupQuickEdit() {
        // Quick edit is handled by global functions for now
        // This could be refactored to be more object-oriented
    }
    
    /**
     * Setup parameter action handlers
     */
    setupParameterActions() {
        // Add parameter button handlers
        const addParameterBtn = document.getElementById('add-parameter-btn');
        const addFirstParameterBtn = document.getElementById('add-first-parameter');
        
        [addParameterBtn, addFirstParameterBtn].forEach(btn => {
            if (btn) {
                btn.addEventListener('click', () => this.showAddParameterModal());
            }
        });
    }
    
    /**
     * Setup modal functionality
     */
    setupModals() {
        this.setupAddParameterModal();
    }
    
    /**
     * Setup add parameter modal
     */
    setupAddParameterModal() {
        const modal = document.getElementById('add-parameter-modal');
        const closeBtn = document.getElementById('close-add-parameter-modal');
        const cancelBtn = document.getElementById('cancel-add-parameter');
        const categorySelect = document.getElementById('parameter-category-select');
        const parameterSelect = document.getElementById('parameter-type-select');
        const confirmBtn = document.getElementById('confirm-add-parameter');
        
        if (!modal) return;
        
        // Close modal handlers
        [closeBtn, cancelBtn].forEach(btn => {
            if (btn) {
                btn.addEventListener('click', () => this.hideAddParameterModal());
            }
        });
        
        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.hideAddParameterModal();
            }
        });
        
        // Category selection handler
        if (categorySelect) {
            categorySelect.addEventListener('change', (e) => {
                this.populateParameterOptions(e.target.value);
            });
        }
        
        // Parameter selection handler
        if (parameterSelect) {
            parameterSelect.addEventListener('change', (e) => {
                this.showParameterPreview(e.target.value);
                if (confirmBtn) {
                    confirmBtn.disabled = !e.target.value;
                }
            });
        }
    }
    
    /**
     * Show add parameter modal
     */
    showAddParameterModal() {
        const modal = document.getElementById('add-parameter-modal');
        if (modal) {
            modal.style.display = 'flex';
            
            // Reset form
            const form = document.getElementById('add-parameter-form');
            if (form) {
                form.reset();
            }
            
            // Reset parameter options
            const parameterSelect = document.getElementById('parameter-type-select');
            if (parameterSelect) {
                parameterSelect.innerHTML = '<option value="">Choose parameter...</option>';
            }
            
            // Hide preview
            const preview = document.getElementById('parameter-preview');
            if (preview) {
                preview.style.display = 'none';
            }
            
            // Disable confirm button
            const confirmBtn = document.getElementById('confirm-add-parameter');
            if (confirmBtn) {
                confirmBtn.disabled = true;
            }
        }
    }
    
    /**
     * Hide add parameter modal
     */
    hideAddParameterModal() {
        const modal = document.getElementById('add-parameter-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }
    
    /**
     * Populate parameter options based on category
     */
    populateParameterOptions(category) {
        const parameterSelect = document.getElementById('parameter-type-select');
        if (!parameterSelect) return;
        
        // Clear existing options
        parameterSelect.innerHTML = '<option value="">Choose parameter...</option>';
        
        // Define parameter options by category
        const parametersByCategory = {
            'control': {
                'quality': 'JPEG Quality',
                'compression': 'PNG Compression',
                'progressive': 'Progressive JPEG',
                'strip_metadata': 'Strip Metadata',
                'optimize': 'Optimize Output'
            },
            'dimensional': {
                'width': 'Width',
                'height': 'Height',
                'max_width': 'Maximum Width',
                'max_height': 'Maximum Height',
                'crop': 'Crop Mode',
                'fit': 'Fit Mode',
                'gravity': 'Crop Gravity'
            },
            'transformational': {
                'rotate': 'Rotation',
                'flip_h': 'Flip Horizontal',
                'flip_v': 'Flip Vertical',
                'brightness': 'Brightness',
                'contrast': 'Contrast',
                'saturation': 'Saturation',
                'blur': 'Blur',
                'sharpen': 'Sharpen'
            }
        };
        
        if (parametersByCategory[category]) {
            Object.entries(parametersByCategory[category]).forEach(([value, label]) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = label;
                parameterSelect.appendChild(option);
            });
        }
    }
    
    /**
     * Show parameter preview information
     */
    showParameterPreview(parameterName) {
        const preview = document.getElementById('parameter-preview');
        if (!preview || !parameterName) {
            if (preview) preview.style.display = 'none';
            return;
        }
        
        // Define parameter information
        const parameterInfo = {
            'quality': {
                description: 'JPEG compression quality (1-100). Higher values produce better quality but larger file sizes.',
                defaultValue: '85',
                type: 'integer',
                range: '1-100'
            },
            'width': {
                description: 'Target width in pixels. Image will be resized to this width while maintaining aspect ratio.',
                defaultValue: 'auto',
                type: 'integer',
                range: '1-9999'
            },
            'height': {
                description: 'Target height in pixels. Image will be resized to this height while maintaining aspect ratio.',
                defaultValue: 'auto',
                type: 'integer',
                range: '1-9999'
            },
            'crop': {
                description: 'How to handle cropping when both width and height are specified.',
                defaultValue: 'crop',
                type: 'string',
                options: ['crop', 'fit', 'stretch']
            },
            'rotate': {
                description: 'Rotation angle in degrees. Positive values rotate clockwise.',
                defaultValue: '0',
                type: 'integer',
                range: '0-360'
            }
            // Add more parameter definitions as needed
        };
        
        const info = parameterInfo[parameterName];
        if (!info) {
            preview.style.display = 'none';
            return;
        }
        
        const previewContent = preview.querySelector('.preview-content');
        if (previewContent) {
            previewContent.innerHTML = `
                <div class="parameter-detail">
                    <p><strong>Description:</strong> ${info.description}</p>
                    <p><strong>Type:</strong> ${info.type}</p>
                    <p><strong>Default Value:</strong> <code>${info.defaultValue}</code></p>
                    ${info.range ? `<p><strong>Range:</strong> ${info.range}</p>` : ''}
                    ${info.options ? `<p><strong>Options:</strong> ${info.options.join(', ')}</p>` : ''}
                </div>
            `;
        }
        
        preview.style.display = 'block';
    }
    
    /**
     * Setup preset tools functionality
     */
    setupPresetTools() {
        // Export preset
        const exportBtn = document.getElementById('export-preset');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportPreset());
        }
        
        // Duplicate preset
        const duplicateBtn = document.getElementById('duplicate-preset');
        if (duplicateBtn) {
            duplicateBtn.addEventListener('click', () => this.duplicatePreset());
        }
        
        // Import preset
        const importBtn = document.getElementById('import-preset');
        if (importBtn) {
            importBtn.addEventListener('click', () => this.importPreset());
        }
        
        // Usage analytics
        const usageBtn = document.getElementById('preset-usage');
        if (usageBtn) {
            usageBtn.addEventListener('click', () => this.showUsageAnalytics());
        }
        
        // Test preset
        const testBtn = document.getElementById('test-preset');
        if (testBtn) {
            testBtn.addEventListener('click', () => this.testPreset());
        }
    }
    
    /**
     * Setup live updates for parameter changes
     */
    setupLiveUpdates() {
        // Monitor parameter value changes for live preview updates
        const parameterForms = document.querySelectorAll('.quick-edit-form form');
        parameterForms.forEach(form => {
            const input = form.querySelector('input[name="parameter_value"]');
            if (input) {
                input.addEventListener('input', () => {
                    if (this.previewSystem && this.previewSystem.autoRefresh) {
                        // Debounce the preview update
                        clearTimeout(this.previewUpdateTimeout);
                        this.previewUpdateTimeout = setTimeout(() => {
                            this.previewSystem.generatePreview();
                        }, 500);
                    }
                });
            }
        });
    }
    
    /**
     * Select preview image
     */
    selectPreviewImage() {
        // Create file input for image selection
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.style.display = 'none';
        
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file && file.type.startsWith('image/')) {
                this.loadPreviewImage(file);
            }
        });
        
        document.body.appendChild(fileInput);
        fileInput.click();
        document.body.removeChild(fileInput);
    }
    
    /**
     * Load preview image
     */
    loadPreviewImage(file) {
        const reader = new FileReader();
        
        reader.onload = (e) => {
            this.currentPreviewImage = e.target.result;
            
            // Update preview area
            const previewArea = document.querySelector('.preview-image-area');
            if (previewArea) {
                previewArea.innerHTML = `
                    <img src="${e.target.result}" alt="Preview Image" style="max-width: 100%; max-height: 300px; object-fit: contain;">
                `;
            }
            
            // Generate initial preview if auto-refresh is enabled
            if (this.previewSystem && this.previewSystem.autoRefresh) {
                this.previewSystem.generatePreview();
            }
        };
        
        reader.readAsDataURL(file);
    }
    
    /**
     * Export preset configuration
     */
    exportPreset() {
        // Check if preset data is available
        if (!window.JCOGSPresetData) {
            console.error('Preset data not available');
            alert('Export failed: Preset data not available');
            return;
        }
        
        // Show loading state
        const exportBtn = document.getElementById('export-preset');
        const originalText = exportBtn.innerHTML;
        exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Exporting...</span>';
        exportBtn.disabled = true;
        
        // Redirect to export URL (which will trigger file download)
        window.location.href = window.JCOGSPresetData.exportUrl;
        
        // Reset button after a short delay
        setTimeout(() => {
            exportBtn.innerHTML = originalText;
            exportBtn.disabled = false;
        }, 2000);
    }
    
    /**
     * Duplicate current preset
     */
    duplicatePreset() {
        // Check if preset data is available
        if (!window.JCOGSPresetData) {
            console.error('Preset data not available');
            alert('Duplicate failed: Preset data not available');
            return;
        }
        
        // Redirect to duplicate page
        window.location.href = window.JCOGSPresetData.duplicateUrl;
    }
    
    /**
     * Import preset configuration from JSON file
     */
    importPreset() {
        // Check if preset data is available
        if (!window.JCOGSPresetData) {
            console.error('Preset data not available');
            alert('Import failed: Preset data not available');
            return;
        }
        
        // Redirect to import page
        window.location.href = window.JCOGSPresetData.importUrl;
    }
    
    /**
     * Show usage analytics
     */
    showUsageAnalytics() {
        // Check if preset data is available
        if (!window.JCOGSPresetData) {
            console.error('Preset data not available');
            alert('Analytics failed: Preset data not available');
            return;
        }
        
        // Redirect to analytics page
        window.location.href = window.JCOGSPresetData.analyticsUrl;
    }
    
    /**
     * Test preset with sample data
     */
    testPreset() {
        if (!this.currentPreviewImage) {
            alert('Please select a preview image first');
            return;
        }
        
        console.log('Testing preset...');
        if (this.previewSystem) {
            this.previewSystem.generatePreview();
        }
    }
}

/**
 * Global functions for backward compatibility and PHP integration
 */
function quickEditParameter(parameterName) {
    const quickEditForm = document.getElementById(`quick-edit-${parameterName}`);
    const parameterItem = quickEditForm?.closest('.parameter-item');
    
    if (quickEditForm && parameterItem) {
        // Hide parameter content and show quick edit form
        const parameterContent = parameterItem.querySelector('.parameter-content');
        if (parameterContent) {
            parameterContent.style.display = 'none';
        }
        
        quickEditForm.style.display = 'block';
        
        // Focus on input
        const input = quickEditForm.querySelector('input[name="parameter_value"]');
        if (input) {
            input.focus();
            input.select();
        }
    }
}

function cancelQuickEdit(parameterName) {
    const quickEditForm = document.getElementById(`quick-edit-${parameterName}`);
    const parameterItem = quickEditForm?.closest('.parameter-item');
    
    if (quickEditForm && parameterItem) {
        // Show parameter content and hide quick edit form
        const parameterContent = parameterItem.querySelector('.parameter-content');
        if (parameterContent) {
            parameterContent.style.display = 'block';
        }
        
        quickEditForm.style.display = 'none';
    }
}

function deleteParameter(parameterName) {
    if (confirm(`Are you sure you want to delete the "${parameterName}" parameter?`)) {
        // Create and submit a delete form
        const form = document.createElement('form');
        form.method = 'post';
        form.action = window.jcogsImageProConfig?.removeParameterUrl || '';
        
        // Add CSRF token
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = window.jcogsImageProConfig?.csrfToken || '';
        
        // Add parameter name
        const paramInput = document.createElement('input');
        paramInput.type = 'hidden';
        paramInput.name = 'parameter_name';
        paramInput.value = parameterName;
        
        form.appendChild(csrfInput);
        form.appendChild(paramInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.jcogsImageProPresetEditor = new JCOGSImageProPresetEditor();
});
