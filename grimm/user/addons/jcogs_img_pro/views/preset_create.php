<?php
/**
 * JCOGS Image Pro - Enhanced Create Preset View
 * ==============================================
 * ExpressionEngine 7 Add-on View Template for Enhanced Preset Creation
 * 
 * This view provides a comprehensive workflow-driven interface for creating new presets,
 * including guided steps, template selection, parameter configuration, and live preview.
 * Features a modern multi-step UI with progressive disclosure and validation.
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Enhanced Preset Creation Workflow Implementation
 * 
 * Template Variables:
 * @var string  $cp_page_title     Control panel page title for the creation interface
 * @var string  $cancel_url        URL to return to when canceling the creation process
 * @var mixed   $form              EE7 form object for basic preset information
 * @var array   $preset_templates  Available preset templates for quick start
 * @var string  $base_url          Base URL for asset loading and navigation
 * @var string  $csrf_token        CSRF protection token for form submissions
 */
?>

<div class="preset-creation-form">
    <!-- Main Content Panel -->
    <div class="panel">
        <div class="panel-heading">
            <div class="title-bar title-bar--large">
                <h3 class="title-bar__title">
                    <i class="fas fa-plus"></i> <?= $cp_page_title ?>
                </h3>
                <div class="title-bar__extra-tools">
                    <a class="btn btn-default" href="<?= $cancel_url ?>">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </div>
        </div>
        <div class="panel-body">
            <div class="app-notice-wrap"><?=ee('CP/Alert')->getAllInlines()?></div>
            
            <!-- Preset Creation Form -->
            <div class="preset-creation-content">
                <div class="creation-header">
                    <h4>Create New Preset</h4>
                    <p>Enter the basic details for your new preset. After creation, you'll be able to add parameters and configure settings on the edit page.</p>
                </div>
                
                <div class="creation-form">
                    <?php if (isset($form) && !empty($form)): ?>
                    <?= $form->render() ?>
                    <?php endif; ?>
                </div>
                
                <!-- Additional preset info -->
                <div class="preset-info-cards">
                    <div class="info-card">
                        <div class="card-icon">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <div class="card-content">
                            <h5>What are Presets?</h5>
                            <p>Presets are reusable collections of image processing parameters that can be applied to any image tag. They help maintain consistency across your site.</p>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="card-icon">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <div class="card-content">
                            <h5>Naming Best Practices</h5>
                            <p>Use descriptive names like "thumbnail_square", "hero_banner", or "product_gallery" to easily identify preset purposes.</p>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="card-icon">
                            <i class="fas fa-sort"></i>
                        </div>
                        <div class="card-content">
                            <h5>Parameter Priority</h5>
                            <p>Preset parameters can be overridden by tag parameters, giving you flexibility when needed.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>            <!-- Step 2: Template Selection -->
            <div class="workflow-step" data-step="2">
                <div class="step-header">
                    <h4>Step 2: Choose Starting Template</h4>
                    <p>Select a template to pre-populate your preset with common parameter combinations, or start from scratch.</p>
                </div>
                
                <div class="step-content">
                    <div class="template-grid">
                        <div class="template-card" data-template="blank">
                            <div class="template-preview">
                                <i class="icon--file-o"></i>
                            </div>
                            <div class="template-info">
                                <h5>Blank Preset</h5>
                                <p>Start with an empty preset and add parameters manually</p>
                                <span class="parameter-count">0 parameters</span>
                            </div>
                        </div>
                        
                        <div class="template-card" data-template="thumbnail">
                            <div class="template-preview">
                                <i class="icon--crop"></i>
                            </div>
                            <div class="template-info">
                                <h5>Thumbnail Template</h5>
                                <p>Square thumbnails with consistent sizing and cropping</p>
                                <span class="parameter-count">4 parameters</span>
                            </div>
                        </div>
                        
                        <div class="template-card" data-template="responsive">
                            <div class="template-preview">
                                <i class="icon--mobile"></i>
                            </div>
                            <div class="template-info">
                                <h5>Responsive Template</h5>
                                <p>Responsive images with multiple breakpoints</p>
                                <span class="parameter-count">6 parameters</span>
                            </div>
                        </div>
                        
                        <div class="template-card" data-template="hero">
                            <div class="template-preview">
                                <i class="icon--image"></i>
                            </div>
                            <div class="template-info">
                                <h5>Hero Banner Template</h5>
                                <p>Large banner images with optimization for hero sections</p>
                                <span class="parameter-count">5 parameters</span>
                            </div>
                        </div>
                        
                        <div class="template-card" data-template="gallery">
                            <div class="template-preview">
                                <i class="icon--grid"></i>
                            </div>
                            <div class="template-info">
                                <h5>Gallery Template</h5>
                                <p>Gallery images with lightbox optimization</p>
                                <span class="parameter-count">7 parameters</span>
                            </div>
                        </div>
                        
                        <div class="template-card" data-template="watermark">
                            <div class="template-preview">
                                <i class="icon--shield"></i>
                            </div>
                            <div class="template-info">
                                <h5>Watermark Template</h5>
                                <p>Images with watermark protection</p>
                                <span class="parameter-count">8 parameters</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Template Preview -->
                    <div class="template-preview-section" style="display: none;">
                        <h5>Template Parameters Preview</h5>
                        <div class="parameter-preview-list">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <div class="step-actions">
                    <button type="button" class="btn btn-default" id="back-to-basic">
                        <i class="icon--arrow-left"></i> Back
                    </button>
                    <button type="button" class="btn btn-primary" id="continue-to-parameters" disabled>
                        Continue to Parameters <i class="icon--arrow-right"></i>
                    </button>
                </div>
            </div>
            
            <!-- Step 3: Initial Parameters (populated by JavaScript) -->
            <div class="workflow-step" data-step="3">
                <div class="step-header">
                    <h4>Step 3: Configure Initial Parameters</h4>
                    <p>Customize the template parameters or add new ones to match your specific needs.</p>
                </div>
                
                <div class="step-content">
                    <div class="parameters-editor">
                        <!-- Populated by JavaScript based on template selection -->
                    </div>
                </div>
                
                <div class="step-actions">
                    <button type="button" class="btn btn-default" id="back-to-templates">
                        <i class="icon--arrow-left"></i> Back
                    </button>
                    <button type="button" class="btn btn-primary" id="continue-to-preview">
                        Preview & Finalize <i class="icon--arrow-right"></i>
                    </button>
                </div>
            </div>
            
            <!-- Step 4: Preview & Finalize -->
            <div class="workflow-step" data-step="4">
                <div class="step-header">
                    <h4>Step 4: Preview & Finalize</h4>
                    <p>Review your preset configuration and see how it processes a sample image.</p>
                </div>
                
                <div class="step-content">
                    <div class="preset-summary">
                        <h5>Preset Summary</h5>
                        <div class="summary-details">
                            <!-- Populated by JavaScript -->
                        </div>
                    </div>
                    
                    <div class="preset-preview">
                        <h5>Sample Preview</h5>
                        <div class="preview-container">
                            <!-- Preview will be generated here -->
                        </div>
                    </div>
                </div>
                
                <div class="step-actions">
                    <button type="button" class="btn btn-default" id="back-to-parameters">
                        <i class="icon--arrow-left"></i> Back
                    </button>
                    <button type="button" class="btn btn-primary" id="create-preset">
                        <i class="icon--check"></i> Create Preset
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
