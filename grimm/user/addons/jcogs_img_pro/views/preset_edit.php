<?php
/**
 * JCOGS Image Pro - Enhanced Preset Edit View
 * ============================================
 * Advanced preset parameter management interface with modern UI
 */
?>

<div class="enhanced-preset-editor">
    <!-- Header Section -->
    <!-- Header Section -->
    <div class="preset-header">
        <div class="header-content">
            <div class="preset-title">
                <h2>Preset: <?= htmlspecialchars($preset['name']) ?></h2>
            </div>
            <div class="header-actions">
                <a class="btn btn-default" href="<?= ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets') ?>">
                    <i class="fas fa-arrow-left"></i> Back to Presets
                </a>
            </div>
        </div>
        
        <!-- Description Editor Row -->
        <div class="preset-description-row">
            <div class="preset-description-editor">
                <form method="post" action="<?= ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit/' . $preset['id']) ?>" class="inline-description-form">
                    <input type="hidden" name="csrf_token" value="<?= CSRF_TOKEN ?>">
                    <input type="hidden" name="name_display" value="<?= htmlspecialchars($preset['name']) ?>">
                    <div class="description-field-group">
                        <textarea name="description" 
                                  class="description-input" 
                                  placeholder="Add a description for this preset..." 
                                  rows="2"><?= htmlspecialchars($preset['description'] ?? '') ?></textarea>
                        <div class="description-actions">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fas fa-save"></i> Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="preset-stats">
            <div class="stat-item">
                <span class="stat-label">Parameters</span>
                <span class="stat-value"><?= count($preset['parameters'] ?? []) ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Categories</span>
                <span class="stat-value"><?php 
                    $categories = [];
                    if (!empty($preset['parameters'])) {
                        foreach ($preset['parameters'] as $param_name => $param_value) {
                            if (in_array($param_name, ['width', 'height', 'max_width', 'max_height'])) {
                                $categories['dimensional'] = true;
                            } elseif (in_array($param_name, ['rotate', 'flip', 'crop'])) {
                                $categories['transformational'] = true;
                            } else {
                                $categories['control'] = true;
                            }
                        }
                    }
                    echo count($categories);
                ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Usage (30d)</span>
                <span class="stat-value" title="Images processed in last 30 days"><?= htmlspecialchars($analytics['usage_count'] ?? '--') ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Last Modified</span>
                <span class="stat-value"><?= isset($preset['modified_date']) ? date('M j, Y', $preset['modified_date']) : 'Unknown' ?></span>
            </div>
        </div>
    </div>    <!-- Main Content Grid -->
    <div class="preset-content-grid">
        <!-- Left Panel: Parameter Management -->
        <div class="parameters-panel">
            <div class="panel">
                <div class="panel-heading">
                    <div class="title-bar">
                        <h4 class="title-bar__title">Parameter Management</h4>
                        <div class="title-bar__extra-tools">
                            <button type="button" class="btn btn-sm btn-primary" id="add-parameter-btn">
                                <i class="fas fa-plus"></i> Add Parameter
                            </button>
                        </div>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="app-notice-wrap">
                        <?=ee('CP/Alert')->getAllInlines()?>
                        
                        <?php 
                        // Check for flash messages as backup
                        $quick_edit_error = ee()->session->flashdata('quick_edit_error');
                        if ($quick_edit_error): ?>
                            <div class="app-notice app-notice---error">
                                <div class="app-notice__tag">
                                    <span class="app-notice__icon"></span>
                                </div>
                                <div class="app-notice__content">
                                    <p><strong>Quick Edit Failed</strong></p>
                                    <p><?= htmlspecialchars($quick_edit_error) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Search and Filter -->
                    <div class="parameter-controls">
                        <div class="search-box">
                            <input type="text" id="parameter-search" placeholder="Search parameters..." class="form-control">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="filter-controls">
                            <select id="category-filter" class="form-control">
                                <option value="">All Categories</option>
                                <option value="control">Control</option>
                                <option value="dimensional">Dimensional</option>
                                <option value="transformational">Transformational</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Parameter List -->
                    <div class="parameter-list">
                        <?php 
                        $parameters = $preset['parameters'] ?? [];
                        if (empty($parameters)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <h5>No Parameters Yet</h5>
                            <p>Add your first parameter to start configuring this preset.</p>
                            <button type="button" class="btn btn-primary" id="add-first-parameter">
                                <i class="fas fa-plus"></i> Add First Parameter
                            </button>
                        </div>
                        <?php else: ?>
                        <?php foreach ($parameters as $param_name => $param_value): ?>
                        <?php 
                        // Use ParameterRegistry for accurate category assignment
                        $category = \JCOGSDesign\JCOGSImagePro\Service\ParameterRegistry::getParameterCategory($param_name);
                        
                        // Create parameter data structure
                        $param_data = [
                            'value' => $param_value,
                            'category' => $category,
                            'label' => ucfirst(str_replace('_', ' ', $param_name))
                        ];
                        ?>
                        <div class="parameter-item" data-category="<?= htmlspecialchars($param_data['category']) ?>" data-name="<?= htmlspecialchars($param_name) ?>">
                            <div class="parameter-header">
                                <div class="parameter-info">
                                    <h5 class="parameter-name"><?= htmlspecialchars($param_data['label']) ?></h5>
                                    <span class="parameter-category"><?= htmlspecialchars(ucfirst($param_data['category'])) ?></span>
                                </div>
                                <div class="parameter-actions">
                                    <button type="button" class="btn btn-sm btn-default" title="Quick Edit" onclick="quickEditParameter('<?= htmlspecialchars($param_name) ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="<?= ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/edit_parameter/' . $preset['id'] . '/' . urlencode($param_name)) ?>" class="btn btn-sm btn-primary" title="Full Edit">
                                        <i class="fas fa-cog"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" title="Delete" onclick="deleteParameter('<?= htmlspecialchars($param_name) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="parameter-content">
                                <div class="parameter-value">
                                    <strong>Value:</strong>
                                    <code class="parameter-value-display"><?= htmlspecialchars($param_data['value'] ?? '') ?></code>
                                </div>
                                <?php if (!empty($param_data['description'])): ?>
                                <div class="parameter-description">
                                    <p><?= htmlspecialchars($param_data['description']) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <!-- Quick Edit Form (hidden by default) -->
                            <div class="quick-edit-form" id="quick-edit-<?= htmlspecialchars($param_name) ?>" style="display: none;">
                                <form method="post" action="<?= ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/update_parameter/' . $preset['id']) ?>">
                                    <input type="hidden" name="parameter_name" value="<?= htmlspecialchars($param_name) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                    <div class="form-group">
                                        <input type="text" name="parameter_value" value="<?= htmlspecialchars($param_value ?? '') ?>" class="form-control" placeholder="Parameter value...">
                                    </div>
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="fas fa-check"></i> Save
                                        </button>
                                        <button type="button" class="btn btn-sm btn-default" onclick="cancelQuickEdit('<?= htmlspecialchars($param_name) ?>')">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Preview Panel -->
        <div class="preview-main-panel">
            <div class="panel">
                <div class="panel-heading">
                    <div class="title-bar">
                        <h4 class="title-bar__title">Preview Preset Effects</h4>
                        <div class="title-bar__extra-tools">
                            <button type="button" class="btn btn-sm btn-primary" id="refresh-preview" onclick="refreshPreview()">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="preview-container">
                        <div class="preview-image-area" id="preview-image-area">
                            <div class="preview-comparison">
                                <div class="preview-section">
                                    <h6>Original</h6>
                                    <div class="preview-image-container">
                                        <?php if (!empty($preview_image['preview_url'])): ?>
                                            <img id="original-preview" src="<?= htmlspecialchars($preview_image['preview_url']) ?>" alt="<?= htmlspecialchars($preview_image['preview_alt']) ?>" style="display: block;">
                                        <?php else: ?>
                                            <div class="preview-placeholder active" id="original-placeholder">
                                                <i class="fas fa-image"></i>
                                                <p>No preview image selected</p>
                                                <p><small>Set a default preview image in <a href="<?= ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets') ?>" class="text-primary">Preset Management</a></small></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="preview-source-info">
                                        <?php if (!empty($preview_image['preview_url'])): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle"></i>
                                                Source: <?= ucfirst($preview_image['preview_source']) ?> image
                                                <?php if ($preview_image['preview_source'] === 'default'): ?>
                                                    <a href="<?= ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets') ?>" class="text-primary">(change default)</a>
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="preview-section">
                                    <h6>Processed <span id="processing-status" class="status-indicator"></span></h6>
                                    <div class="preview-image-container">
                                        <img id="processed-preview" src="" alt="Processed image" style="display: none;">
                                        <div class="preview-placeholder active" id="processed-placeholder">
                                            <i class="fas fa-cog"></i>
                                            <p>Processing will appear here</p>
                                        </div>
                                        <div class="preview-loading" id="preview-loading" style="display: none;">
                                            <i class="fas fa-spinner fa-spin"></i>
                                            <p>Processing image...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Processing Summary - Positioned below images -->
                        <div class="preview-metadata" id="preview-metadata" style="display: none;">
                            <div class="preview-summary-header">
                                <h6><i class="fas fa-chart-bar"></i> Processing Summary</h6>
                            </div>
                            <div class="metadata-grid">
                                <div class="metadata-row">
                                    <span class="metadata-label">Original Dimensions:</span>
                                    <span class="metadata-value" id="original-size">--</span>
                                </div>
                                <div class="metadata-row">
                                    <span class="metadata-label">Processed Dimensions:</span>
                                    <span class="metadata-value" id="processed-size">--</span>
                                </div>
                                <div class="metadata-row">
                                    <span class="metadata-label">Size Change:</span>
                                    <span class="metadata-value" id="size-change">--</span>
                                </div>
                                <div class="metadata-row">
                                    <span class="metadata-label">Processing Time:</span>
                                    <span class="metadata-value" id="processing-time">--</span>
                                </div>
                                <div class="metadata-row">
                                    <span class="metadata-label">Applied Effects:</span>
                                    <span class="metadata-value" id="applied-effects">--</span>
                                </div>
                                <div class="metadata-row">
                                    <span class="metadata-label">Preview URL:</span>
                                    <span class="metadata-value" id="preview-url"><a href="#" target="_blank" rel="noopener">View Direct Link</a></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Size Warning Message -->
                        <div class="preview-size-warning" id="preview-size-warning">
                            <i class="fas fa-info-circle"></i>
                            <span>Preview is scaled down for display. <a href="#" id="size-warning-link" target="_blank" rel="noopener">Click here to see actual size</a></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom Preview File Management -->
        <div class="panel preview-file-management">
            <div class="panel-heading">
                <h4>Preview Image Settings</h4>
            </div>
            <div class="panel-body">
                <form method="post" action="<?= $update_preview_url ?>" class="preview-file-form">
                    <input type="hidden" name="csrf_token" value="<?= CSRF_TOKEN ?>">
                    
                    <div class="fieldset-frag">
                        <div class="field-instruct">
                            <label>Test Image for Preview</label>
                            <em>Choose an image to test your preset effects on, or leave blank to use the global default test image.</em>
                        </div>
                        <div class="field-control">
                            <?= $preview_file_picker ?>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Test Image
                        </button>
                        <?php if (!empty($preset['parameters']['preview_file_id'])): ?>
                            <button type="submit" name="preview_file_id" value="0" class="btn btn-default">
                                <i class="fas fa-times"></i> Use Global Default
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
                
                <div class="preview-file-info">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        Preset-specific test images override the global default. Leave blank to use the global default test image set in 
                        <a href="<?= ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets') ?>" class="text-primary">Preset Management</a>.
                    </small>
                </div>
            </div>
        </div>

            <!-- Preset Tools -->
            <div class="panel preset-tools-panel">
                <div class="panel-heading">
                    <h4>Preset Tools</h4>
                    <small class="text-muted">Advanced preset management features</small>
                </div>
                <div class="panel-body">
                    <div class="tool-grid">
                        <button type="button" class="tool-button" id="export-preset" title="Export preset as JSON file">
                            <i class="fas fa-download"></i>
                            <span>Export Preset</span>
                            <small>Download JSON</small>
                        </button>
                        <button type="button" class="tool-button" id="duplicate-preset" title="Create a copy of this preset">
                            <i class="fas fa-copy"></i>
                            <span>Duplicate Preset</span>
                            <small>Create copy</small>
                        </button>
                        <button type="button" class="tool-button" id="import-preset" title="Import parameters from JSON file">
                            <i class="fas fa-upload"></i>
                            <span>Import Preset</span>
                            <small>Load from JSON</small>
                        </button>
                        <button type="button" class="tool-button" id="preset-usage" title="View usage analytics for this preset">
                            <i class="fas fa-chart-bar"></i>
                            <span>Usage Analytics</span>
                            <small>View stats</small>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Add Parameter Modal -->
<div class="jip_modal" id="add-parameter-modal" style="display: none;">
    <div class="jip_modal-dialog">
        <div class="jip_modal-header">
            <h4>Add New Parameter</h4>
            <button type="button" class="jip_modal-close" id="close-add-parameter-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="jip_modal-body">
            <form method="post" action="<?= $add_parameter_url ?>" id="add-parameter-form">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="form-group">
                    <label for="parameter-select">Choose Parameter</label>
                    <select name="parameter" id="parameter-select" class="form-control">
                        <option value="">Choose parameter...</option>
                        <?php foreach ($available_parameters as $category => $parameters): ?>
                            <optgroup label="<?= htmlspecialchars($category) ?> Parameters">
                                <?php foreach ($parameters as $param_name => $param_label): ?>
                                    <option value="<?= htmlspecialchars($param_name) ?>">
                                        <?= htmlspecialchars($param_label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="parameter-preview" id="parameter-preview" style="display: none;">
                    <h5>Parameter Information</h5>
                    <div class="preview-content">
                        <!-- Populated by JavaScript -->
                    </div>
                </div>
            </form>
        </div>
        <div class="jip_modal-footer">
            <button type="button" class="btn btn-default" id="cancel-add-parameter">Cancel</button>
            <button type="submit" form="add-parameter-form" class="btn btn-primary" id="confirm-add-parameter" disabled>
                <i class="fas fa-plus"></i> Add Parameter
            </button>
        </div>
    </div>
</div>

<!-- Enhanced Styling and JavaScript now loaded via EE CP methods -->
<script>
// Preset data for JavaScript
window.JCOGSPresetData = {
    presetId: <?= (int)$preset['id'] ?>,
    presetName: <?= json_encode($preset['name']) ?>,
    exportUrl: <?= json_encode(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/export/' . $preset['id'])->compile()) ?>,
    duplicateUrl: <?= json_encode(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/duplicate/' . $preset['id'])->compile()) ?>,
    importUrl: <?= json_encode(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/import')->compile()) ?>,
    analyticsUrl: <?= json_encode(ee('CP/URL')->make('addons/settings/jcogs_img_pro/presets/analytics/' . $preset['id'])->compile()) ?>
};
</script>
