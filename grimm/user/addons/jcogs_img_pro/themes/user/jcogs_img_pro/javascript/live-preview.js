/**
 * JCOGS Image Pro - Live Preview System
 * ====================================
 * JavaScript for live parameter preview functionality
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-alpha4
 * @since      Phase 5: UI Enhancement
 */

class JCOGSImageProPreview {
    constructor() {
        this.previewContainer = null;
        this.currentPreviewImage = null;
        this.previewSettings = {
            debounceDelay: 1000, // 1 second delay before triggering preview
            maxPreviewWidth: 400,
            maxPreviewHeight: 300,
            sampleImage: '/media/images/sample_preview.jpg' // Default sample image
        };
        this.debounceTimer = null;
        this.isPreviewEnabled = false;
        
        this.init();
    }

    /**
     * Initialize the preview system
     */
    init() {
        this.createPreviewContainer();
        this.bindParameterEvents();
        this.setupPreviewControls();
        
        // Load initial preview if parameter form is present
        if (this.hasParameterForm()) {
            this.schedulePreviewUpdate();
        }
    }

    /**
     * Check if we're on a parameter editing page
     */
    hasParameterForm() {
        return document.querySelector('.parameter-form-section') !== null;
    }

    /**
     * Create the preview container in the UI
     */
    createPreviewContainer() {
        const parameterSection = document.querySelector('.parameter-form-section');
        if (!parameterSection) return;

        const previewHTML = `
            <div class="live-preview-panel">
                <div class="preview-header">
                    <h4>Live Preview</h4>
                    <div class="preview-controls">
                        <button type="button" class="btn btn-sm btn-default" id="toggle-preview">
                            <i class="icon--view"></i> <span class="preview-toggle-text">Enable Preview</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-default" id="change-sample-image">
                            <i class="icon--edit"></i> Change Sample
                        </button>
                        <button type="button" class="btn btn-sm btn-default" id="refresh-preview">
                            <i class="icon--sync"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="preview-content">
                    <div class="preview-placeholder">
                        <i class="icon--image"></i>
                        <p>Click "Enable Preview" to see live parameter changes</p>
                    </div>
                    <div class="preview-image-container" style="display: none;">
                        <img id="live-preview-image" alt="Parameter Preview" />
                        <div class="preview-loading">
                            <i class="icon--sync icon--spin"></i>
                            <span>Generating preview...</span>
                        </div>
                    </div>
                    <div class="preview-error" style="display: none;">
                        <i class="icon--warning"></i>
                        <span class="error-message">Preview generation failed</span>
                    </div>
                </div>
                <div class="preview-metadata">
                    <div class="metadata-grid">
                        <div class="metadata-item">
                            <label>Processing Time:</label>
                            <span id="preview-time">--</span>
                        </div>
                        <div class="metadata-item">
                            <label>Output Format:</label>
                            <span id="preview-format">--</span>
                        </div>
                        <div class="metadata-item">
                            <label>Dimensions:</label>
                            <span id="preview-dimensions">--</span>
                        </div>
                        <div class="metadata-item">
                            <label>File Size:</label>
                            <span id="preview-filesize">--</span>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Insert preview panel after the parameter form
        parameterSection.insertAdjacentHTML('afterend', previewHTML);
        this.previewContainer = document.querySelector('.live-preview-panel');
        
        this.bindPreviewControls();
    }

    /**
     * Bind events to parameter form inputs
     */
    bindParameterEvents() {
        const formInputs = document.querySelectorAll('.parameter-form-section input, .parameter-form-section select, .parameter-form-section textarea');
        
        formInputs.forEach(input => {
            input.addEventListener('input', () => this.schedulePreviewUpdate());
            input.addEventListener('change', () => this.schedulePreviewUpdate());
        });
    }

    /**
     * Bind events to preview control buttons
     */
    bindPreviewControls() {
        const toggleBtn = document.getElementById('toggle-preview');
        const refreshBtn = document.getElementById('refresh-preview');
        const changeSampleBtn = document.getElementById('change-sample-image');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this.togglePreview());
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.forcePreviewUpdate());
        }

        if (changeSampleBtn) {
            changeSampleBtn.addEventListener('click', () => this.showSampleImageDialog());
        }
    }

    /**
     * Setup additional preview controls
     */
    setupPreviewControls() {
        // Add keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'p':
                        e.preventDefault();
                        this.togglePreview();
                        break;
                    case 'r':
                        if (this.isPreviewEnabled) {
                            e.preventDefault();
                            this.forcePreviewUpdate();
                        }
                        break;
                }
            }
        });
    }

    /**
     * Toggle preview on/off
     */
    togglePreview() {
        this.isPreviewEnabled = !this.isPreviewEnabled;
        const toggleBtn = document.getElementById('toggle-preview');
        const toggleText = document.querySelector('.preview-toggle-text');
        
        if (this.isPreviewEnabled) {
            toggleBtn.classList.add('btn-primary');
            toggleBtn.classList.remove('btn-default');
            toggleText.textContent = 'Disable Preview';
            this.schedulePreviewUpdate();
        } else {
            toggleBtn.classList.add('btn-default');
            toggleBtn.classList.remove('btn-primary');
            toggleText.textContent = 'Enable Preview';
            this.showPreviewPlaceholder();
        }
    }

    /**
     * Schedule a preview update with debouncing
     */
    schedulePreviewUpdate() {
        if (!this.isPreviewEnabled) return;

        // Clear existing timer
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        // Schedule new update
        this.debounceTimer = setTimeout(() => {
            this.updatePreview();
        }, this.previewSettings.debounceDelay);
    }

    /**
     * Force immediate preview update
     */
    forcePreviewUpdate() {
        if (!this.isPreviewEnabled) return;

        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        this.updatePreview();
    }

    /**
     * Update the live preview
     */
    async updatePreview() {
        if (!this.isPreviewEnabled) return;

        this.showPreviewLoading();

        try {
            const formData = this.collectFormData();
            const previewData = await this.generatePreview(formData);
            this.displayPreview(previewData);
        } catch (error) {
            console.error('Preview update failed:', error);
            this.showPreviewError(error.message);
        }
    }

    /**
     * Collect current form data for preview generation
     */
    collectFormData() {
        const formData = new FormData();
        const form = document.querySelector('.parameter-form-section form');
        
        if (form) {
            new FormData(form).forEach((value, key) => {
                formData.append(key, value);
            });
        }

        // Add sample image for preview
        formData.append('preview_sample_image', this.previewSettings.sampleImage);
        formData.append('preview_max_width', this.previewSettings.maxPreviewWidth);
        formData.append('preview_max_height', this.previewSettings.maxPreviewHeight);
        
        return formData;
    }

    /**
     * Generate preview via AJAX request
     */
    async generatePreview(formData) {
        const response = await fetch('/admin.php?/cp/addons/settings/jcogs_img_pro/preview/generate', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`Preview generation failed: ${response.status}`);
        }

        return await response.json();
    }

    /**
     * Display the generated preview
     */
    displayPreview(previewData) {
        this.hidePreviewPlaceholder();
        this.hidePreviewLoading();
        this.hidePreviewError();

        const imageContainer = document.querySelector('.preview-image-container');
        const previewImage = document.getElementById('live-preview-image');
        
        if (previewData.success && previewData.preview_url) {
            previewImage.src = previewData.preview_url + '?' + Date.now(); // Cache busting
            imageContainer.style.display = 'block';
            
            // Update metadata
            this.updatePreviewMetadata(previewData.metadata || {});
        } else {
            this.showPreviewError(previewData.error || 'Unknown preview error');
        }
    }

    /**
     * Update preview metadata display
     */
    updatePreviewMetadata(metadata) {
        document.getElementById('preview-time').textContent = metadata.processing_time || '--';
        document.getElementById('preview-format').textContent = metadata.format || '--';
        document.getElementById('preview-dimensions').textContent = 
            metadata.width && metadata.height ? `${metadata.width}×${metadata.height}` : '--';
        document.getElementById('preview-filesize').textContent = metadata.file_size || '--';
    }

    /**
     * Show preview loading state
     */
    showPreviewLoading() {
        document.querySelector('.preview-loading').style.display = 'flex';
        document.querySelector('.preview-image-container').style.display = 'block';
        this.hidePreviewPlaceholder();
        this.hidePreviewError();
    }

    /**
     * Hide preview loading state
     */
    hidePreviewLoading() {
        document.querySelector('.preview-loading').style.display = 'none';
    }

    /**
     * Show preview placeholder
     */
    showPreviewPlaceholder() {
        document.querySelector('.preview-placeholder').style.display = 'block';
        document.querySelector('.preview-image-container').style.display = 'none';
        this.hidePreviewError();
    }

    /**
     * Hide preview placeholder
     */
    hidePreviewPlaceholder() {
        document.querySelector('.preview-placeholder').style.display = 'none';
    }

    /**
     * Show preview error
     */
    showPreviewError(message) {
        const errorElement = document.querySelector('.preview-error');
        const errorMessage = document.querySelector('.error-message');
        
        errorMessage.textContent = message;
        errorElement.style.display = 'block';
        
        this.hidePreviewLoading();
        document.querySelector('.preview-image-container').style.display = 'none';
        this.hidePreviewPlaceholder();
    }

    /**
     * Hide preview error
     */
    hidePreviewError() {
        document.querySelector('.preview-error').style.display = 'none';
    }

    /**
     * Show sample image selection dialog
     */
    showSampleImageDialog() {
        // Implementation for sample image selection
        // This could open a modal with common sample images or allow URL input
        const newSample = prompt('Enter sample image URL:', this.previewSettings.sampleImage);
        if (newSample && newSample.trim()) {
            this.previewSettings.sampleImage = newSample.trim();
            if (this.isPreviewEnabled) {
                this.forcePreviewUpdate();
            }
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.jcogsImageProPreview = new JCOGSImageProPreview();
});
