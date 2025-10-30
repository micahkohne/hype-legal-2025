<?php

/**
 * JCOGS Image Pro - Srcset Parameter Package
 * ===========================================
 * Parameter package for responsive image srcset functionality
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Parameter Package Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service\ParameterPackages;

use JCOGSDesign\JCOGSImagePro\Service\ParameterPackages\AbstractParameterPackage;

/**
 * Srcset Parameter Package
 * 
 * Handles the complex srcset parameter for responsive image variant specification
 * with width descriptors. Supports pipe-separated width values with optional units
 * (px, %) and validates ordering for optimal browser selection.
 */
class SrcsetParameterPackage extends AbstractParameterPackage
{
    /**
     * Get the priority for this package
     * 
     * Higher numbers indicate higher priority. Set to 15 to have higher 
     * priority than ControlParameterPackage (10) for specialized handling.
     * 
     * @return int Priority level (higher = more priority)
     */
    public function getPriority(): int
    {
        return 15;
    }

    /**
     * Get the parameters this package handles
     * 
     * @return array List of parameter names handled by this package
     */
    public function getHandledParameters(): array
    {
        return ['srcset'];
    }

    /**
     * Get the form fields for this package
     * 
     * @param array $existingValues Current parameter values for form population
     * @return array Form field definitions for EE control panel
     */
    public function getPackageFormFields(array $existingValues = []): array
    {
        return [
            'srcset' => [
                'type' => 'text',
                'label' => 'Responsive Image Variants (srcset)',
                'description' => 'Pipe-separated width values for responsive image variants (e.g., "350|450|700" or "280px|50%|771|800px")',
                'placeholder' => '350|450|700',
                'value' => $existingValues['srcset'] ?? '',
                'validation' => [
                    'pattern' => '^[0-9]+(%|px)?(\|[0-9]+(%|px)?)*$',
                    'title' => 'Enter pipe-separated width values (numbers with optional px or % units)'
                ]
            ]
        ];
    }

    /**
     * Validate the srcset parameter
     * 
     * Validates srcset parameter format, width specifications, and ordering.
     * Ensures width values are positive, formats are correct, and ordering
     * is optimized for browser selection.
     * 
     * @param string $param_name Parameter name being validated
     * @param mixed $value Parameter value to validate
     * @return bool|string True if valid, error message if invalid
     */
    public function validateParameter(string $param_name, $value)
    {
        if ($param_name !== 'srcset') {
            return true;
        }

        if (empty($value)) {
            return true; // Empty is valid, parameter is optional
        }

        // Split by pipe to get individual width specifications
        $widthSpecs = explode('|', $value);
        
        if (empty($widthSpecs)) {
            return 'At least one width specification is required';
        }

        $previousWidth = 0;

        foreach ($widthSpecs as $index => $spec) {
            $spec = trim($spec);
            
            if (empty($spec)) {
                return "Empty width specification found at position " . ($index + 1);
            }

            // Validate width format (number with optional px or % suffix)
            if (!preg_match('/^(\d+)(px|%)?$/', $spec, $matches)) {
                return "Invalid width format '$spec' at position " . ($index + 1) . ". Expected number with optional 'px' or '%' suffix";
            }

            $width = (int)$matches[1];
            $unit = $matches[2] ?? '';

            // Validate width value
            if ($width <= 0) {
                return "Width value must be greater than 0 at position " . ($index + 1) . ". Found: $width";
            }

            // For percentage values, ensure they're reasonable
            if ($unit === '%') {
                if ($width > 100) {
                    return "Percentage width should not exceed 100% at position " . ($index + 1) . ". Found: {$width}%";
                }
            }

            // Check ordering (values should be in increasing order for optimal processing)
            // Only check pixel values for ordering to avoid unit conversion complexity
            if ($unit !== '%' && $width <= $previousWidth) {
                return "Width values should be in increasing order for optimal processing. Value '$spec' at position " . ($index + 1) . " is not larger than previous value";
            }

            // Update previous width for ordering check (only for pixel values)
            if ($unit !== '%') {
                $previousWidth = $width;
            }
        }

        return true;
    }

    /**
     * Get the description for this package
     * 
     * @return string Package description for display purposes
     */
    public function getPackageDescription(): string
    {
        return 'Responsive Image Variants (srcset) - Create multiple width variants of images for responsive design';
    }

    /**
     * Get additional help text for this package
     * 
     * @return string Detailed help text explaining usage and examples
     */
    public function getPackageHelp(): string
    {
        return 'The srcset parameter creates responsive image variants with different widths. ' .
               'Specify pipe-separated width values in increasing order (e.g., "350|450|700"). ' .
               'Values can be numbers (pixels assumed), numbers with "px" suffix, or percentages with "%" suffix. ' .
               'The browser automatically selects the best variant based on viewport size and pixel density. ' .
               'Works with the sizes parameter to control how variants are selected.';
    }

    /**
     * Get documentation URL for this package
     * 
     * @return string URL to parameter documentation
     */
    public function getDocumentationUrl(): string
    {
        return 'https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-srcset';
    }
}
