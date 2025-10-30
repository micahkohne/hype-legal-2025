<?php

/**
 * JCOGS Image Pro - Parameter Package Interface
 * =============================================
 * Defines the contract for parameter packages in the presets system
 * 
 * Parameter packages organize related parameters into logical groups and provide:
 * - Form field generation for Control Panel interfaces
 * - Parameter validation and sanitization
 * - Documentation and help text
 * - Value transformation between CP forms and tag parameters
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Presets Feature Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service\ParameterPackages;

interface ParameterPackageInterface
{
    /**
     * Get the package name/identifier
     * Used for package discovery and configuration
     * 
     * @return string Package identifier (e.g., 'core', 'transformational', 'effects')
     */
    public function getName(): string;

    /**
     * Get the package display label for UI
     * 
     * @return string Human-readable package name for Control Panel
     */
    public function getLabel(): string;

    /**
     * Get the package description
     * 
     * @return string Brief description of what this package handles
     */
    public function getDescription(): string;

    /**
     * Get the Legacy parameter category this package belongs to
     * Must be one of: 'control', 'dimensional', 'transformational'
     * 
     * @return string Legacy category for ParameterRegistry integration
     */
    public function getCategory(): string;

    /**
     * Get all parameters handled by this package
     * Returns array of parameter names this package manages
     * 
     * @return array List of parameter names (e.g., ['width', 'height', 'quality'])
     */
    public function getParameters(): array;

    /**
     * Generate form fields for Control Panel interface
     * Returns array of EE form field definitions for all parameters in this package
     * 
     * @param array $current_values Current parameter values for populating forms
     * @return array EE-compatible form field definitions
     */
    public function generateFormFields(array $current_values = []): array;

    /**
     * Validate parameter values for this package
     * Validates all parameters managed by this package
     * 
     * @param array $parameters Parameter values to validate
     * @return array Array of validation errors (empty if valid)
     */
    public function validateParameters(array $parameters): array;

    /**
     * Transform values from Control Panel form to tag parameters
     * Converts form data into tag-compatible parameter format
     * 
     * @param array $form_data Raw form data from Control Panel
     * @return array Tag-compatible parameter array
     */
    public function transformFormToParameters(array $form_data): array;

    /**
     * Transform tag parameters to Control Panel form values
     * Converts tag parameters back to form-compatible format
     * 
     * @param array $parameters Tag parameter values
     * @return array Form-compatible values for populating interface
     */
    public function transformParametersToForm(array $parameters): array;

    /**
     * Get parameter documentation/help text
     * Returns help text for all parameters in this package
     * 
     * @return array Parameter name => help text mapping
     */
    public function getParameterDocumentation(): array;

    /**
     * Get JavaScript files needed for this package
     * Returns array of JS file paths for dynamic form behavior
     * 
     * @return array JavaScript file paths relative to addon directory
     */
    public function getJavaScriptFiles(): array;

    /**
     * Get CSS files needed for this package
     * Returns array of CSS file paths for package-specific styling
     * 
     * @return array CSS file paths relative to addon directory
     */
    public function getCssFiles(): array;

    /**
     * Check if this package is enabled in addon settings
     * Allows packages to be enabled/disabled per site
     * 
     * @return bool True if package is enabled for current site
     */
    public function isEnabled(): bool;

    /**
     * Get package priority for ordering in interface
     * Lower numbers appear first in Control Panel
     * 
     * @return int Priority order (0-100, where 0 is highest priority)
     */
    public function getPriority(): int;
}
