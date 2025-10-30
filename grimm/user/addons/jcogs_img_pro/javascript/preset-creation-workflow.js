/**
 * JCOGS Image Pro - Preset Creation Workflow
 * ===========================================
 * JavaScript for enhanced preset creation workflow with guided steps
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @since      Phase 5: Enhanced Preset UI
 */

class JCOGSImageProPresetWorkflow {
    constructor() {
        this.currentStep = 1;
        this.maxSteps = 4;
        this.selectedTemplate = null;
        this.presetData = {
            name: '',
            description: '',
            template: null,
            parameters: {}
        };
        
        // Template definitions with parameter presets
        this.templates = {
            blank: {
                name: 'Blank Preset',
                description: 'Start with an empty preset',
                parameters: {},
                parameterCount: 0
            },
            thumbnail: {
                name: 'Thumbnail Template',
                description: 'Square thumbnails with consistent sizing',
                parameters: {
                    'width': '150',
                    'height': '150',
                    'crop': 'y|center,center|0,0|y|3',
                    'quality': '80'
                },
                parameterCount: 4
            },
            responsive: {
                name: 'Responsive Template',
                description: 'Responsive images with multiple breakpoints',
                parameters: {
                    'width': '800',
                    'height': '600',
                    'responsive': 'y',
                    'breakpoints': '480,768,1024',
                    'sizes': '(max-width: 480px) 100vw, (max-width: 768px) 50vw, 33vw',
                    'lazy': 'y'
                },
                parameterCount: 6
            },
            hero: {
                name: 'Hero Banner Template',
                description: 'Large banner images for hero sections',
                parameters: {
                    'width': '1200',
                    'height': '600',
                    'crop': 'y|center,center|0,0|y|3',
                    'quality': '90',
                    'auto_sharpen': 'y'
                },
                parameterCount: 5
            },
            gallery: {
                name: 'Gallery Template',
                description: 'Gallery images with lightbox optimization',
                parameters: {
                    'width': '400',
                    'height': '300',
                    'crop': 'y|center,center|0,0|y|3',
                    'quality': '85',
                    'add_dimensions': 'y',
                    'lazy': 'y',
                    'class': 'gallery-image'
                },
                parameterCount: 7
            },
            watermark: {
                name: 'Watermark Template',
                description: 'Images with watermark protection',
                parameters: {
                    'width': '600',
                    'height': '400',
                    'quality': '85',
                    'watermark': '/media/watermarks/logo.png|100,100|0.7|bottom-right|10,10|0',
                    'copyright': 'y',
                    'disable_right_click': 'y',
                    'add_dimensions': 'y',
                    'class': 'protected-image'
                },
                parameterCount: 8
            }
        };
        
        this.init();
    }

    /**
     * Initialize the workflow
     */
    init() {
        this.bindEvents();
        this.updateProgress();
        this.loadTemplateCards();
    }

    /**
     * Bind event handlers
     */
    bindEvents() {
        // Step navigation buttons
        document.getElementById('continue-to-templates')?.addEventListener('click', () => {
            if (this.validateStep1()) {
                this.goToStep(2);
            }
        });

        document.getElementById('back-to-basic')?.addEventListener('click', () => {
            this.goToStep(1);
        });

        document.getElementById('continue-to-parameters')?.addEventListener('click', () => {
            this.goToStep(3);
        });

        document.getElementById('back-to-templates')?.addEventListener('click', () => {
            this.goToStep(2);
        });

        document.getElementById('continue-to-preview')?.addEventListener('click', () => {
            this.goToStep(4);
        });

        document.getElementById('back-to-parameters')?.addEventListener('click', () => {
            this.goToStep(3);
        });

        document.getElementById('create-preset')?.addEventListener('click', () => {
            this.createPreset();
        });

        // Template selection
        document.addEventListener('click', (e) => {
            if (e.target.closest('.template-card')) {
                this.selectTemplate(e.target.closest('.template-card'));
            }
        });

        // Form validation
        document.addEventListener('input', (e) => {
            if (e.target.name === 'name' || e.target.name === 'description') {
                this.validateStep1();
            }
        });
    }

    /**
     * Navigate to specific step
     */
    goToStep(stepNumber) {
        if (stepNumber < 1 || stepNumber > this.maxSteps) return;

        // Hide all steps
        document.querySelectorAll('.workflow-step').forEach(step => {
            step.classList.remove('active');
        });

        // Show target step
        const targetStep = document.querySelector(`.workflow-step[data-step="${stepNumber}"]`);
        if (targetStep) {
            targetStep.classList.add('active');
            this.currentStep = stepNumber;
            this.updateProgress();

            // Load step-specific content
            switch (stepNumber) {
                case 3:
                    this.loadParameterEditor();
                    break;
                case 4:
                    this.loadPresetSummary();
                    this.generatePreview();
                    break;
            }
        }
    }

    /**
     * Update progress indicator
     */
    updateProgress() {
        document.querySelectorAll('.step').forEach((step, index) => {
            const stepNumber = index + 1;
            step.classList.remove('active', 'completed');
            
            if (stepNumber === this.currentStep) {
                step.classList.add('active');
            } else if (stepNumber < this.currentStep) {
                step.classList.add('completed');
            }
        });
    }

    /**
     * Validate step 1 form
     */
    validateStep1() {
        const nameField = document.querySelector('input[name="name"]');
        const name = nameField?.value?.trim();
        
        if (!name) {
            this.showValidationError(nameField, 'Preset name is required');
            return false;
        }

        if (name.length < 3) {
            this.showValidationError(nameField, 'Preset name must be at least 3 characters');
            return false;
        }

        // Store data
        this.presetData.name = name;
        this.presetData.description = document.querySelector('input[name="description"]')?.value?.trim() || '';

        this.clearValidationError(nameField);
        return true;
    }

    /**
     * Load template cards and bind selection
     */
    loadTemplateCards() {
        // Template cards are already in HTML, just bind selection behavior
        document.querySelectorAll('.template-card').forEach(card => {
            card.addEventListener('click', () => {
                this.selectTemplate(card);
            });
        });
    }

    /**
     * Select a template
     */
    selectTemplate(templateCard) {
        // Remove selection from all templates
        document.querySelectorAll('.template-card').forEach(card => {
            card.classList.remove('selected');
        });

        // Select clicked template
        templateCard.classList.add('selected');
        
        const templateKey = templateCard.dataset.template;
        this.selectedTemplate = templateKey;
        this.presetData.template = templateKey;

        // Show template preview
        this.showTemplatePreview(templateKey);

        // Enable continue button
        const continueBtn = document.getElementById('continue-to-parameters');
        if (continueBtn) {
            continueBtn.disabled = false;
        }
    }

    /**
     * Show template parameter preview
     */
    showTemplatePreview(templateKey) {
        const template = this.templates[templateKey];
        if (!template) return;

        const previewSection = document.querySelector('.template-preview-section');
        const parameterList = document.querySelector('.parameter-preview-list');
        
        if (!previewSection || !parameterList) return;

        // Clear existing preview
        parameterList.innerHTML = '';

        // Show parameters
        Object.entries(template.parameters).forEach(([key, value]) => {
            const parameterItem = document.createElement('div');
            parameterItem.className = 'parameter-preview-item';
            parameterItem.innerHTML = `
                <div class="parameter-name">${this.formatParameterName(key)}</div>
                <div class="parameter-value"><code>${value}</code></div>
            `;
            parameterList.appendChild(parameterItem);
        });

        previewSection.style.display = 'block';
    }

    /**
     * Load parameter editor for step 3
     */
    loadParameterEditor() {
        const container = document.querySelector('.parameters-editor');
        if (!container) return;

        const template = this.templates[this.selectedTemplate];
        if (!template) return;

        container.innerHTML = `
            <div class="parameters-form">
                <h5>Template Parameters</h5>
                <p>Customize these parameters or add new ones:</p>
                <div class="parameter-fields">
                    ${this.generateParameterFields(template.parameters)}
                </div>
                <div class="add-parameter-section">
                    <h6>Add New Parameter</h6>
                    <div class="add-parameter-controls">
                        <select id="new-parameter-type">
                            <option value="">Choose parameter type...</option>
                            <option value="width">Width</option>
                            <option value="height">Height</option>
                            <option value="quality">Quality</option>
                            <option value="crop">Crop</option>
                            <option value="filter">Filter</option>
                            <option value="lazy">Lazy Loading</option>
                            <option value="class">CSS Class</option>
                        </select>
                        <button type="button" class="btn btn-secondary" id="add-parameter">
                            <i class="icon--add"></i> Add Parameter
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Bind add parameter functionality
        document.getElementById('add-parameter')?.addEventListener('click', () => {
            this.addNewParameter();
        });
    }

    /**
     * Generate parameter field HTML
     */
    generateParameterFields(parameters) {
        return Object.entries(parameters).map(([key, value]) => `
            <div class="parameter-field">
                <label for="param_${key}">${this.formatParameterName(key)}</label>
                <input type="text" id="param_${key}" name="param_${key}" value="${value}" class="form-control">
                <button type="button" class="btn btn-sm btn-danger remove-parameter" data-parameter="${key}">
                    <i class="icon--remove"></i>
                </button>
            </div>
        `).join('');
    }

    /**
     * Add new parameter field
     */
    addNewParameter() {
        const select = document.getElementById('new-parameter-type');
        const parameterType = select?.value;
        
        if (!parameterType) return;

        const container = document.querySelector('.parameter-fields');
        if (!container) return;

        const fieldHtml = `
            <div class="parameter-field">
                <label for="param_${parameterType}">${this.formatParameterName(parameterType)}</label>
                <input type="text" id="param_${parameterType}" name="param_${parameterType}" value="" class="form-control" placeholder="Enter ${parameterType} value...">
                <button type="button" class="btn btn-sm btn-danger remove-parameter" data-parameter="${parameterType}">
                    <i class="icon--remove"></i>
                </button>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', fieldHtml);
        select.value = '';

        // Bind remove functionality
        container.querySelector(`.remove-parameter[data-parameter="${parameterType}"]`)?.addEventListener('click', (e) => {
            e.target.closest('.parameter-field').remove();
        });
    }

    /**
     * Load preset summary for step 4
     */
    loadPresetSummary() {
        const container = document.querySelector('.summary-details');
        if (!container) return;

        // Collect current parameter values
        const parameters = this.collectParameterValues();
        
        container.innerHTML = `
            <div class="summary-grid">
                <div class="summary-item">
                    <label>Preset Name:</label>
                    <span>${this.presetData.name}</span>
                </div>
                <div class="summary-item">
                    <label>Description:</label>
                    <span>${this.presetData.description || 'No description'}</span>
                </div>
                <div class="summary-item">
                    <label>Template Used:</label>
                    <span>${this.templates[this.selectedTemplate]?.name || 'None'}</span>
                </div>
                <div class="summary-item">
                    <label>Parameter Count:</label>
                    <span>${Object.keys(parameters).length}</span>
                </div>
            </div>
            <div class="parameter-summary">
                <h6>Parameters:</h6>
                <div class="parameter-list">
                    ${Object.entries(parameters).map(([key, value]) => `
                        <div class="parameter-summary-item">
                            <strong>${this.formatParameterName(key)}:</strong>
                            <code>${value}</code>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    /**
     * Generate preview in step 4
     */
    async generatePreview() {
        const container = document.querySelector('.preview-container');
        if (!container) return;

        container.innerHTML = `
            <div class="preview-loading">
                <i class="icon--sync icon--spin"></i>
                <span>Generating preset preview...</span>
            </div>
        `;

        try {
            const parameters = this.collectParameterValues();
            const previewData = await this.requestPreview(parameters);
            
            if (previewData.success) {
                container.innerHTML = `
                    <div class="preview-result">
                        <img src="${previewData.preview_url}" alt="Preset Preview" class="preset-preview-image">
                        <div class="preview-metadata">
                            <div class="metadata-item">
                                <label>Processing Time:</label>
                                <span>${previewData.metadata.processing_time}</span>
                            </div>
                            <div class="metadata-item">
                                <label>Dimensions:</label>
                                <span>${previewData.metadata.width}×${previewData.metadata.height}</span>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                throw new Error(previewData.error || 'Preview generation failed');
            }
        } catch (error) {
            container.innerHTML = `
                <div class="preview-error">
                    <i class="icon--warning"></i>
                    <span>Preview generation failed: ${error.message}</span>
                    <button type="button" class="btn btn-sm btn-secondary retry-preview">
                        <i class="icon--sync"></i> Retry
                    </button>
                </div>
            `;

            container.querySelector('.retry-preview')?.addEventListener('click', () => {
                this.generatePreview();
            });
        }
    }

    /**
     * Collect parameter values from form
     */
    collectParameterValues() {
        const parameters = {};
        const parameterFields = document.querySelectorAll('input[name^="param_"]');
        
        parameterFields.forEach(field => {
            const parameterName = field.name.replace('param_', '');
            const value = field.value.trim();
            if (value) {
                parameters[parameterName] = value;
            }
        });

        return parameters;
    }

    /**
     * Request preview from server
     */
    async requestPreview(parameters) {
        const formData = new FormData();
        Object.entries(parameters).forEach(([key, value]) => {
            formData.append(key, value);
        });
        
        formData.append('preview_sample_image', '/media/images/sample_preview.jpg');
        formData.append('preview_max_width', '400');
        formData.append('preview_max_height', '300');

        const response = await fetch('/admin.php?/cp/addons/settings/jcogs_img_pro/preview/generate', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        return await response.json();
    }

    /**
     * Create the preset
     */
    async createPreset() {
        const createBtn = document.getElementById('create-preset');
        if (!createBtn) return;

        createBtn.disabled = true;
        createBtn.innerHTML = '<i class="icon--sync icon--spin"></i> Creating Preset...';

        try {
            const parameters = this.collectParameterValues();
            
            // Submit via existing form
            const form = document.querySelector('form[method="post"]');
            if (form) {
                // Add parameters to form
                Object.entries(parameters).forEach(([key, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `preset_parameter_${key}`;
                    input.value = value;
                    form.appendChild(input);
                });

                // Add template info
                const templateInput = document.createElement('input');
                templateInput.type = 'hidden';
                templateInput.name = 'preset_template';
                templateInput.value = this.selectedTemplate || 'blank';
                form.appendChild(templateInput);

                form.submit();
            }
        } catch (error) {
            this.showError('Failed to create preset: ' + error.message);
            createBtn.disabled = false;
            createBtn.innerHTML = '<i class="icon--check"></i> Create Preset';
        }
    }

    /**
     * Utility functions
     */
    formatParameterName(name) {
        return name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    showValidationError(field, message) {
        // Implementation for showing validation errors
    }

    clearValidationError(field) {
        // Implementation for clearing validation errors
    }

    showError(message) {
        // Implementation for showing error messages
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.preset-creation-workflow')) {
        window.jcogsImageProPresetWorkflow = new JCOGSImageProPresetWorkflow();
    }
});
