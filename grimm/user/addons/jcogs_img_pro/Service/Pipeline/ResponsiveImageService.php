<?php declare(strict_types=1);

/**
 * JCOGS Image Pro - Responsive Image Service
 * ==========================================
 * Comprehensive responsive image processing with HTML5 srcset/sizes support
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 3 Legacy Independence
 */

namespace JCOGSDesign\JCOGSImagePro\Service\Pipeline;

use JCOGSDesign\JCOGSImagePro\Service\Pipeline\Context;
use JCOGSDesign\JCOGSImagePro\Service\Pipeline\AbstractService;

/**
 * Responsive Image Service for JCOGS Image Pro
 * 
 * Provides comprehensive responsive image functionality including:
 * - Legacy srcset parameter parsing and validation
 * - Responsive image variant generation and management
 * - HTML5 srcset and sizes attribute generation
 * - Performance optimization for responsive image workflows
 * - Integration with lazy loading and caching systems
 * 
 * Legacy format support:
 * - srcset parameter: '250|50%|300px|400' (pipe-separated width values)
 * - sizes parameter: Custom sizes attribute or auto-generated from breakpoints
 * - Width descriptors: Standard HTML5 format (e.g., "250w", "300w") 
 * - Integration: Works with lazy loading via data-ji-srcset attributes
 * 
 * Features full backward compatibility with Legacy srcset implementation while
 * providing enhanced performance optimization and modern responsive image best practices.
 * Handles percentage-based widths, explicit pixel values, and optimal breakpoint calculation.
 */
class ResponsiveImageService extends AbstractService
{
    /**
     * Constructor
     * 
     * All services are now automatically available via parent AbstractService.
     * No need to manually instantiate common services.
     */
    public function __construct()
    {
        parent::__construct('ResponsiveImageService');
        // $this->utilities_service is now available via parent
        // $this->settings_service is now available via parent
        // All other common services are also available
    }
    
    /**
     * Calculate optimal breakpoints from content analysis
     * 
     * This is a placeholder for more sophisticated breakpoint calculation
     * that could analyze content width patterns or use standard responsive breakpoints.
     * 
     * @param int $max_width Maximum image width
     * @return array Array of breakpoint widths
     */
    public function calculate_optimal_breakpoints(int $max_width): array
    {
        // Standard responsive breakpoints commonly used in web design
        $standard_breakpoints = [320, 480, 768, 1024, 1280, 1600];
        
        // Filter to only include breakpoints smaller than max width
        return array_filter($standard_breakpoints, function($width) use ($max_width) {
            return $width <= $max_width;
        });
    }
    
    /**
     * Generate responsive image variants information
     * 
     * @param Context $context Processing context
     * @param int $base_width Primary image width
     * @param int $max_width Maximum allowed width (for scale limiting)
     * @param bool $allow_scale_larger Whether to allow scaling larger than original
     * @return array Array of variant information with widths and file paths
     */
    public function generate_variant_info(
        Context $context, 
        int $base_width, 
        int $max_width, 
        bool $allow_scale_larger = false
    ): array {
        $srcset_param = $context->get_param('srcset', '');
        
        if (empty($srcset_param)) {
            return [];
        }
        
        $requested_widths = $this->parse_srcset_parameter($srcset_param, $base_width);
        $variants = [];
        
        foreach ($requested_widths as $width) {
            // Skip if width exceeds limits (unless scale larger is allowed)
            $effective_max = $allow_scale_larger ? PHP_INT_MAX : $max_width;
            if ($width > $effective_max) {
                $this->utilities_service->debug_message(
                    sprintf('Skipping srcset width %dpx - exceeds maximum allowed width of %dpx', 
                        $width, $effective_max)
                );
                continue;
            }
            
            $variants[] = [
                'width' => $width,
                'descriptor' => $width . 'w',
                'cache_suffix' => '_' . $width . 'w',
                'needs_generation' => true, // Will be checked against cache later
            ];
        }
        
        return $variants;
    }
    
    /**
     * Generate srcset attribute value
     * 
     * @param array $variants Array of variant information 
     * @param string $base_url Base URL for cache directory
     * @param string $main_image_url URL of the main processed image
     * @param int $main_image_width Width of the main image in pixels
     * @return string Formatted srcset attribute value
     */
    public function generate_srcset_attribute(
        array $variants, 
        string $base_url, 
        string $main_image_url,
        int $main_image_width
    ): string {
        if (empty($variants)) {
            return '';
        }
        
        $srcset_entries = [];
        
        // Extract base filename and extension from main image URL
        $main_path_info = pathinfo($main_image_url);
        $base_filename = $main_path_info['filename']; // Without extension
        $extension = $main_path_info['extension'];
        
        // Add variant entries with proper filenames
        foreach ($variants as $variant) {
            $variant_filename = $base_filename . $variant['cache_suffix'] . '.' . $extension;
            $variant_url = $base_url . $variant_filename;
            $srcset_entries[] = $variant_url . ' ' . $variant['descriptor'];
        }
        
        // Add main image as final entry with its actual width
        $srcset_entries[] = $main_image_url . ' ' . $main_image_width . 'w';
        
        return implode(', ', $srcset_entries);
    }    /**
     * Generate sizes attribute value
     * 
     * @param Context $context Processing context
     * @param array $variants Array of variant information
     * @param int $main_width Main image width
     * @return string Formatted sizes attribute value
     */
    public function generate_sizes_attribute(
        Context $context, 
        array $variants, 
        int $main_width
    ): string {
        // Check for custom sizes parameter first
        $custom_sizes = $context->get_param('sizes', '');
        if (!empty($custom_sizes)) {
            // Ensure trailing comma for concatenation with auto-generated sizes
            return rtrim($custom_sizes, ',') . ', ';
        }
        
        if (empty($variants)) {
            return '';
        }
        
        $sizes_entries = [];
        
        // Generate responsive breakpoints from variants
        foreach ($variants as $variant) {
            $width = $variant['width'];
            $sizes_entries[] = "(max-width: {$width}px) {$width}px";
        }
        
        // Add default size (main image width)
        $sizes_entries[] = "{$main_width}px";
        
        return implode(', ', $sizes_entries);
    }
    
    /**
     * Debug information for srcset processing
     * 
     * @param Context $context Processing context
     * @param array $variants Generated variants
     * @return string Debug information
     */
    public function get_debug_info(Context $context, array $variants): string
    {
        $srcset_param = $context->get_param('srcset', '');
        $info = [
            "Srcset parameter: {$srcset_param}",
            "Generated variants: " . count($variants),
        ];
        
        foreach ($variants as $variant) {
            $info[] = "  - {$variant['width']}px ({$variant['descriptor']})";
        }
        
        return implode("\n", $info);
    }
    
    /**
     * Get performance impact of srcset generation
     * 
     * @param array $variants Array of variants to be generated
     * @return array Performance metrics prediction
     */
    public function get_performance_metrics(array $variants): array
    {
        return [
            'variant_count' => count($variants),
            'estimated_generation_time' => count($variants) * 0.1, // Rough estimate
            'cache_storage_multiplier' => count($variants) + 1, // +1 for main image
            'bandwidth_saving_potential' => min(50, count($variants) * 10), // Percentage
        ];
    }
    
    /**
     * Check if responsive images are enabled for the context
     * 
     * @param Context $context Processing context
     * @return bool True if srcset should be generated
     */
    public function is_responsive_enabled(Context $context): bool
    {
        $srcset_param = $context->get_param('srcset', '');
        return !empty($srcset_param);
    }
    
    /**
     * Validate srcset parameter format
     * 
     * @param string $srcset_param Raw srcset parameter
     * @return bool True if parameter format is valid
     */
    public function is_valid_srcset_parameter(string $srcset_param): bool
    {
        if (empty($srcset_param)) {
            return false;
        }
        
        $values = explode('|', $srcset_param);
        
        foreach ($values as $value) {
            $value = trim($value);
            
            // Check if value matches expected patterns
            if (!preg_match('/^(\d+(%|px)?|\d*\.\d+%)$/', $value)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Parse srcset parameter from Legacy format
     * 
     * Legacy format: '250|50%|300px|400'
     * Supports: numeric pixels, percentages, and explicit px values
     * 
     * @param string $srcset_param Raw srcset parameter value
     * @param int $base_width Base image width for percentage calculations
     * @return array Array of width values in pixels
     */
    public function parse_srcset_parameter(string $srcset_param, int $base_width): array
    {
        if (empty($srcset_param)) {
            return [];
        }
        
        $widths = [];
        $srcset_values = explode('|', $srcset_param);
        
        foreach ($srcset_values as $value) {
            $value = trim($value);
            
            if (empty($value)) {
                continue;
            }
            
            // Handle percentage values (e.g., "50%")
            if (str_ends_with($value, '%')) {
                $percentage = (float) str_replace('%', '', $value);
                $width = (int) round(($percentage / 100) * $base_width);
            }
            // Handle explicit pixel values (e.g., "300px")
            elseif (str_ends_with($value, 'px')) {
                $width = (int) str_replace('px', '', $value);
            }
            // Handle numeric values (assumed to be pixels)
            else {
                $width = (int) $value;
            }
            
            // Only include valid positive widths
            if ($width > 0) {
                $widths[] = $width;
            }
        }
        
        // Remove duplicates and sort ascending
        $widths = array_unique($widths);
        sort($widths);
        
        return $widths;
    }
    
    /**
     * Check if image format supports responsive images
     * 
     * @param Context $context Processing context
     * @return bool True if format supports srcset
     */
    public function supports_responsive_images(Context $context): bool
    {
        // SVG and animated GIF should not have srcset generated
        $is_svg = $context->get_metadata_value('is_svg', false);
        $is_animated_gif = $context->get_metadata_value('is_animated_gif', false);
        
        return !$is_svg && !$is_animated_gif;
    }
}
