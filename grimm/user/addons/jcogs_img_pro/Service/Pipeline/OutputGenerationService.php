<?php declare(strict_types=1);

/**
 * JCOGS Image Pro - Output Generation Service
 * Phase 2: Native EE7 implementation pipeline architecture
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 2 Native Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service\Pipeline;

use JCOGSDesign\JCOGSImagePro\Service\Pipeline\Context;
use JCOGSDesign\JCOGSImagePro\Service\Pipeline\ResponsiveImageService;
use JCOGSDesign\JCOGSImagePro\Service\Pipeline\AbstractService;

/**
 * Output Generation Service
 * 
 * Handles HTML output generation with custom attributes, maintaining full 
 * backward compatibility with Legacy attribute handling and output formatting.
 * 
 * Legacy features supported:
 * - Custom HTML attributes via 'attributes' parameter
 * - Class/style consolidation to prevent duplicates
 * - Variable expansion within attribute values
 * - Automatic dimension addition for lazy loading
 * - SVG role attribute injection
 * - HTML5 decoding attribute management
 * 
 * @package JCOGSDesign\JCOGSImagePro\Service\Pipeline
 */
class OutputGenerationService extends AbstractService
{
    /**
     * @var ResponsiveImageService Responsive image service
     */
    private ResponsiveImageService $responsive_service;
    
    /**
     * Constructor
     * 
     * All common services are now automatically available via parent AbstractService.
     * No need to manually instantiate common services.
     */
    public function __construct()
    {
        parent::__construct('OutputGenerationService');
        // $this->utilities_service is now available via parent
        // $this->cache_service is now available via parent
        // Note: lazy_loading_service accessed via helper method to prevent circular dependency
        // All other common services are also available
        
        // Initialize specialized services
        $this->responsive_service = ee('jcogs_img_pro:ResponsiveImageService');
    }
    
    /**
     * Get lazy loading service (lazy initialization to prevent circular dependency)
     * 
     * @return mixed LazyLoadingService instance
     */
    private function getLazyLoadingService()
    {
        return \JCOGSDesign\JCOGSImagePro\Service\ServiceCache::lazy_loading();
    }
    
    /**
     * Generate complete HTML output for the image
     * 
     * @param Context $context Processing context
     * @param string $image_url Primary image URL
     * @param array $image_dimensions Array with 'width' and 'height' keys
     * @param array $responsive_data Array with srcset/sizes information
     * @param string $placeholder_url Placeholder URL for lazy loading (if applicable)
     * @return string Complete HTML img tag
     */
    public function generate_html_output(
        Context $context,
        string $image_url,
        array $image_dimensions,
        array $responsive_data = [],
        string $placeholder_url = ''
    ): string {
        // Start building the img tag with proper attribute collection
        $attributes = [];
        
        // Apply action link generation BEFORE lazy loading processing
        // This ensures action links work correctly with lazy loading
        $final_image_url = $this->apply_action_link_if_enabled($context, $image_url);
        
        // 1. Core image attributes with lazy loading and responsive support
        $lazy_attributes = $this->getLazyLoadingService()->generate_lazy_attributes(
            $context,
            $final_image_url,
            $placeholder_url,
            $responsive_data['srcset'] ?? null,
            $responsive_data['sizes'] ?? null
        );
        
        $attributes = $this->collect_attributes($attributes, $lazy_attributes);
        
        // 2. Add basic parameter attributes (class, alt, etc.)
        $basic_attributes = $this->generate_basic_attributes($context);
        $attributes = $this->collect_attributes($attributes, $basic_attributes);
        
        // 3. Add custom attributes from parameters
        $custom_attributes = $this->process_custom_attributes($context);
        $attributes = $this->collect_attributes($attributes, $custom_attributes);
        
        // 4. Add performance attributes
        $performance_attributes = $this->generate_performance_attributes($context);
        $attributes = $this->collect_attributes($attributes, $performance_attributes);
        
        // 5. Add dimension attributes if required
        $dimension_attributes = $this->generate_dimension_attributes($context, $image_dimensions);
        $attributes = $this->collect_attributes($attributes, $dimension_attributes);
        
        // 6. Add special attributes for specific image types
        $special_attributes = $this->generate_special_attributes($context);
        $attributes = $this->collect_attributes($attributes, $special_attributes);
        
        // 7. Add preload attribute if specified
        $preload_attributes = $this->generate_preload_attributes($context, $image_url);
        $attributes = $this->collect_attributes($attributes, $preload_attributes);
        
        // 8. Final consolidation of class and style attributes (Legacy-style)
        $attributes = $this->consolidate_class_style_attributes($context, $attributes);
        
        // 9. Process tagdata content (Legacy compatibility)
        $tagdata_content = $this->process_tagdata($context);
        
        // 10. Build final HTML
        $html = $this->build_html_tag($attributes, $tagdata_content);
        
        // 11. Add noscript fallback if needed
        $noscript = $this->getLazyLoadingService()->generate_noscript_fallback(
            $context, 
            $image_url, 
            $dimension_attributes
        );
        
        return $html . $noscript;
    }
    
    /**
     * Apply action link generation if enabled
     * 
     * @param Context $context Processing context
     * @param string $image_url Original image URL
     * @return string Action URL or original URL if action links disabled
     */
    private function apply_action_link_if_enabled(Context $context, string $image_url): string
    {
        // Import action link generation logic from OutputStage
        $action_link_param = $context->get_param('action_link', 'auto');
        $global_action_links = ee('jcogs_img_pro:Settings')->get('img_cp_action_links', 'n');
        
        // Logic: 
        // - If action_link="y" → force enable
        // - If action_link="n" → force disable  
        // - If action_link not set (auto) → use global setting
        $action_links_enabled = false;
        if (strtolower(substr($action_link_param, 0, 1)) === 'y') {
            $action_links_enabled = true;
        } elseif (strtolower(substr($action_link_param, 0, 1)) === 'n') {
            $action_links_enabled = false;
        } else {
            // Use global setting when parameter is not explicitly set
            $action_links_enabled = (strtolower(substr($global_action_links, 0, 1)) === 'y');
        }
        
        if (!$action_links_enabled) {
            // Only show simple message when disabled (reduce debug noise)
            $this->utilities_service->debug_message('Action links disabled', null, false, 'detailed');
            return $image_url;
        }
        
        // Action links are enabled - show detailed debugging
        $this->utilities_service->debug_message('Action link generation called for: ' . $image_url);
        
        // Get ORIGINAL context parameters for ACT packet (not current parameters which may have been modified during processing)
        // This ensures consistent cache keys between regular tag processing and ACT regeneration
        $all_params = $context->get_original_tag_params();
        
        // Set specific ACT parameters
        $all_params['action_link'] = 'no'; // Prevent recursive action link generation
        $all_params['act_what'] = 'img';
        $all_params['act_path'] = $image_url;
        $all_params['url_only'] = 'yes'; // Force URL only mode for ACT processing
        
        // Build ACT packet
        $act_packet = base64_encode(json_encode($all_params));
        
        if (empty($act_packet)) {
            $this->utilities_service->debug_message('Action link generation failed: JSON encoding error');
            return $image_url;
        }
        
        // Get action ID for act_originated_image
        $act_id = ee('jcogs_img_pro:Utilities')->get_action_id('ActOriginatedImage');
        if (!$act_id) {
            $this->utilities_service->debug_message('Action link generation failed: No action ID found for act_originated_image');
            return $image_url;
        }
        
        // Debug: Log successful action ID lookup
        $this->utilities_service->debug_message('Action ID found: ' . $act_id);
        
        // Build action URL
        $action_url = sprintf(
            '%s?ACT=%s&act_packet=%s',
            ee()->config->item('site_url'),
            $act_id,
            $act_packet
        );
        
        $this->utilities_service->debug_message('Generated action link: ' . $action_url);
        return $action_url;
    }
    
    /**
     * Build the complete HTML img tag
     * 
     * @param array $attributes Attributes array
     * @param string $tagdata_content Additional content
     * @return string Complete HTML tag
     */
    private function build_html_tag(array $attributes, string $tagdata_content = ''): string
    {
        $attr_string = '';
        
        foreach ($attributes as $name => $value) {
            if ($value !== null && $value !== '') {
                $attr_string .= sprintf(' %s="%s"', 
                    htmlspecialchars($name, ENT_QUOTES), 
                    htmlspecialchars($value, ENT_QUOTES)
                );
            }
        }
        
        // Add tagdata content if present
        if (!empty($tagdata_content)) {
            $attr_string .= ' ' . $tagdata_content;
        }
        
        return '<img' . $attr_string . '>';
    }
    
    /**
     * Collect attributes properly, accumulating class/style/alt values instead of overwriting
     * 
     * This ensures that multiple sources of class/style/alt attributes are preserved
     * for later consolidation, following Legacy's approach
     * 
     * @param array $existing_attributes Current attributes collection
     * @param array $new_attributes New attributes to add
     * @return array Updated attributes with proper accumulation
     */
    private function collect_attributes(array $existing_attributes, array $new_attributes): array
    {
        foreach ($new_attributes as $attr_name => $attr_value) {
            if (empty($attr_value)) {
                continue;
            }
            
            // For class, style, and alt attributes, accumulate values in arrays for later consolidation
            if (in_array($attr_name, ['class', 'style', 'alt'])) {
                if (!isset($existing_attributes[$attr_name])) {
                    $existing_attributes[$attr_name] = [];
                }
                
                // Ensure we have an array to work with
                if (!is_array($existing_attributes[$attr_name])) {
                    $existing_attributes[$attr_name] = [$existing_attributes[$attr_name]];
                }
                
                // Add the new value to the array
                $existing_attributes[$attr_name][] = $attr_value;
            } else {
                // For other attributes, simply overwrite (last wins)
                $existing_attributes[$attr_name] = $attr_value;
            }
        }
        
        return $existing_attributes;
    }
    
    /**
     * Consolidate class, style and alt attributes following Legacy approach
     * 
     * Replicates Legacy class consolidation logic exactly:
     * - Collects class values from all attribute sources (class param + attributes param)
     * - Eliminates duplicates using Legacy's str_contains approach
     * - Consolidates style attributes from multiple sources
     * - Handles multiple occurrences of same attribute type
     * - Respects exclude_class and exclude_style parameters (Legacy lines 766-769)
     * 
     * @param Context $context Processing context for exclude parameter checks
     * @param array $attributes Attributes array from all sources
     * @return array Consolidated attributes with class/style deduplication
     */
    private function consolidate_class_style_attributes(Context $context, array $attributes): array
    {
        // === CLASS CONSOLIDATION (following Legacy lines 710-722) ===
        $new_class = '';
        
        // Collect all class values from attributes array (could be multiple sources)
        foreach ($attributes as $attr_name => $attr_value) {
            if ($attr_name === 'class' && !empty($attr_value)) {
                // Handle array of class values (from multiple sources) or single value
                $class_values = is_array($attr_value) ? $attr_value : [$attr_value];
                
                foreach ($class_values as $class_item) {
                    if (!empty($class_item)) {
                        // Legacy approach: check if class not already contained, then add with space
                        $new_class = !str_contains($new_class, $class_item) ? $new_class . ' ' . $class_item : $new_class;
                    }
                }
            }
        }
        $new_class = trim($new_class);
        
        // === STYLE CONSOLIDATION (following Legacy lines 724-736) ===
        $new_style = '';
        
        // Collect all style values from attributes array
        foreach ($attributes as $attr_name => $attr_value) {
            if ($attr_name === 'style' && !empty($attr_value)) {
                // Handle array of style values (from multiple sources) or single value
                $style_values = is_array($attr_value) ? $attr_value : [$attr_value];
                
                foreach ($style_values as $style_item) {
                    if (!empty($style_item)) {
                        $new_style .= ' ' . $style_item;
                    }
                }
            }
        }
        $new_style = trim($new_style);
        
        // === ALT CONSOLIDATION (following Legacy lines 738-750) ===
        $new_alt = '';
        
        // Collect all alt values from attributes array
        foreach ($attributes as $attr_name => $attr_value) {
            if ($attr_name === 'alt' && !empty($attr_value)) {
                // Handle array of alt values (from multiple sources) or single value
                $alt_values = is_array($attr_value) ? $attr_value : [$attr_value];
                
                foreach ($alt_values as $alt_item) {
                    if (!empty($alt_item)) {
                        // Legacy approach: check if alt not already contained, then add with space
                        $new_alt = !str_contains($new_alt, $alt_item) ? $new_alt . ' ' . $alt_item : $new_alt;
                    }
                }
            }
        }
        $new_alt = trim($new_alt);
        
        // Remove all class, style, alt entries from attributes array
        $consolidated_attributes = [];
        foreach ($attributes as $attr_name => $attr_value) {
            if (!in_array($attr_name, ['class', 'style', 'alt'])) {
                $consolidated_attributes[$attr_name] = $attr_value;
            }
        }
        
        // Add back the consolidated values (following Legacy lines 760-769)
        // Check exclude_class and exclude_style parameters as per Legacy lines 766-769
        if (!empty($new_alt)) {
            $consolidated_attributes['alt'] = $new_alt;
        }
        
        // Only add class if exclude_class is not enabled (Legacy line 766)
        if (!empty($new_class) && substr(strtolower($context->get_param('exclude_class', 'n')), 0, 1) != 'y') {
            $consolidated_attributes['class'] = $new_class;
        }
        
        // Only add style if exclude_style is not enabled (Legacy line 769)
        if (!empty($new_style) && substr(strtolower($context->get_param('exclude_style', 'n')), 0, 1) != 'y') {
            $consolidated_attributes['style'] = $new_style;
        }
        
        return $consolidated_attributes;
    }
    
    /**
     * Generate basic attributes from individual parameters
     * 
     * @param Context $context Processing context
     * @return array Basic attributes from individual parameters
     */
    private function generate_basic_attributes(Context $context): array
    {
        $basic_attributes = [];

        // Add class parameter if provided
        $class = $context->get_param('class', '');
        if (!empty($class)) {
            $basic_attributes['class'] = $class;
        }

        // Add style parameter if provided
        $style = $context->get_param('style', '');
        if (!empty($style)) {
            $basic_attributes['style'] = $style;
        }

        // Add alt parameter if provided
        $alt = $context->get_param('alt', '');
        if (!empty($alt)) {
            $basic_attributes['alt'] = $alt;
        }

        // Add title parameter if provided
        $title = $context->get_param('title', '');
        if (!empty($title)) {
            $basic_attributes['title'] = $title;
        }

        // Add id parameter if provided
        $id = $context->get_param('id', '');
        if (!empty($id)) {
            $basic_attributes['id'] = $id;
        }

        return $basic_attributes;
    }
    
    /**
     * Generate dimension attributes if required
     * 
     * @param Context $context Processing context
     * @param array $image_dimensions Image dimensions
     * @return array Dimension attributes
     */
    private function generate_dimension_attributes(Context $context, array $image_dimensions): array
    {
        $attributes = [];
        
        if (!$this->utilities_service->should_add_dimensions($context)) {
            return $attributes;
        }
        
        // Don't add width if srcset is present (responsive images)
        if (!$this->responsive_service->is_responsive_enabled($context)) {
            if (isset($image_dimensions['width']) && $image_dimensions['width'] > 0) {
                $attributes['width'] = (string) $image_dimensions['width'];
            }
        }
        
        // Always add height if available
        if (isset($image_dimensions['height']) && $image_dimensions['height'] > 0) {
            $attributes['height'] = (string) $image_dimensions['height'];
        }
        
        return $attributes;
    }
    
    /**
     * Process custom attributes parameter
     * 
     * Legacy format: attributes='class="my-class" data-test="value"'
     * Supports variable expansion and class/style consolidation
     * 
     * @param Context $context Processing context
     * @return array Processed attributes array
     */
    private function process_custom_attributes(Context $context): array
    {
        $attributes_param = $context->get_param('attributes', '');
        
        if (empty($attributes_param)) {
            return [];
        }
        
        // Parse attribute string into array
        $attributes = $this->parse_attribute_string($attributes_param);
        
        // Apply variable expansion if enabled
        if ($this->should_expand_variables($context)) {
            $attributes = $this->expand_attribute_variables($context, $attributes);
        }
        
        // Apply class/style consolidation if enabled
        if ($this->should_consolidate_classes($context)) {
            $attributes = $this->consolidate_class_style_attributes($context, $attributes);
        }
        
        return $attributes;
    }
    
    /**
     * Parse attribute string into associative array
     * 
     * @param string $attribute_string Raw attribute string
     * @return array Parsed attributes
     */
    private function parse_attribute_string(string $attribute_string): array
    {
        $attributes = [];
        
        // Simple regex to match attribute="value" patterns
        if (preg_match_all('/(\w+(?:-\w+)*)=["\']([^"\']*)["\']/', $attribute_string, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attributes[$match[1]] = $this->sanitize_attribute_value($match[2]);
            }
        }

        return $attributes;
    }
    
    /**
     * Expand template variables within attribute values
     * 
     * @param Context $context Processing context
     * @param array $attributes Attributes array
     * @return array Attributes with expanded variables
     */
    private function expand_attribute_variables(Context $context, array $attributes): array
    {
        // Get template variables from context for expansion
        $template_variables = $context->get_metadata_value('computed_template_variables', []);
        
        if (empty($template_variables)) {
            return $attributes;
        }
        
        // Expand EE variables in attribute values
        foreach ($attributes as $attr_name => $attr_value) {
            if (is_string($attr_value) && !empty($attr_value)) {
                // Use EE's template parser for variable expansion
                $template_vars = [$template_variables]; // EE expects nested array
                
                if (!empty(ee()->TMPL)) {
                    // Standard template processing
                    $attributes[$attr_name] = ee()->TMPL->parse_variables($attr_value, $template_vars);
                } else {
                    // ACT/direct processing fallback
                    $attributes[$attr_name] = ee('Template')->parse_variables($attr_value, $template_vars);
                }
            }
        }
        
        return $attributes;
    }
    
    /**
     * Format attributes array as HTML attribute string
     * 
     * @param array $attributes Attributes array
     * @return string Formatted attribute string
     */
    private function format_attributes_string(array $attributes): string
    {
        $attr_parts = [];
        foreach ($attributes as $name => $value) {
            if ($value === true) {
                $attr_parts[] = $name;
            } elseif ($value !== false && $value !== null && $value !== '') {
                $attr_parts[] = $name . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
            }
        }
        return implode(' ', $attr_parts);
    }
    
    /**
     * Generate special attributes for specific image types
     * 
     * @param Context $context Processing context
     * @return array Special attributes
     */
    private function generate_special_attributes(Context $context): array
    {
        $attributes = [];
        
        // Add role="img" for SVG images (accessibility)
        if ($context->get_metadata_value('is_svg', false)) {
            $attributes['role'] = 'img';
        }
        
        return $attributes;
    }
    
    /**
     * Generate preload attributes if specified
     * 
     * Sprint 4 Enhanced: Now supports fetchpriority and advanced preload options
     * 
     * @param Context $context Processing context
     * @param string $image_url Image URL
     * @return array Preload attributes
     */
    private function generate_preload_attributes(Context $context, string $image_url): array
    {
        $attributes = [];
        
        $preload_param = $context->get_param('preload', '');
        
        // Check for explicit preload parameter or default setting
        $enable_preload = false;
        if ($preload_param) {
            // Parameter provided - check value
            $enable_preload = substr(strtolower($preload_param), 0, 1) === 'y';
        } else {
            // No parameter - check for default setting
            $settings = new \JCOGSDesign\JCOGSImagePro\Service\Settings();
            $enable_preload = $settings->get('img_cp_default_preload_critical', 'n') === 'y';
        }
        
        if ($enable_preload) {
            // Use Pro-specific data attribute to avoid conflicts with Legacy
            $attributes['data-jip-preload'] = $image_url;
            
            // Sprint 4: Add fetchpriority support for critical images
            $fetchpriority = $context->get_param('fetchpriority', 'auto'); // Default to 'auto'
            if (in_array($fetchpriority, ['high', 'low', 'auto'])) {
                $attributes['data-jip-fetchpriority'] = $fetchpriority;
            }
            
            // Sprint 4: Add responsive preload support
            $responsive_data = $context->get_metadata_value('responsive_data', []);
            if (!empty($responsive_data['srcset'])) {
                $attributes['data-jip-preload-srcset'] = $responsive_data['srcset'];
            }
            if (!empty($responsive_data['sizes'])) {
                $attributes['data-jip-preload-sizes'] = $responsive_data['sizes'];
            }
        }
        
        return $attributes;
    }

    /**
     * Generate performance-related attributes
     * 
     * @param Context $context Processing context
     * @return array Performance attributes
     */
    private function generate_performance_attributes(Context $context): array
    {
        $attributes = [];
        
        // Add decoding attribute if enabled and not already present
        if ($this->should_add_decoding_attribute($context)) {
            $attributes['decoding'] = 'async';
        }
        
        return $attributes;
    }
    
    /**
     * Generate template variables for S4-F3 Tag-based Variable Prefixing
     * 
     * Creates an array of template variables that can be parsed by EE's template parser.
     * Applies variable prefix if specified in the tag (e.g., {exp:jcogs_img_pro:image:cats})
     * 
     * @param Context $context Processing context
     * @param string $image_url Final processed image URL
     * @param array $image_dimensions Array with 'width' and 'height' keys
     * @param array $responsive_data Optional responsive image data
     * @return array Template variables for EE parsing
     */
    public function generate_template_variables(Context $context, string $image_url, array $image_dimensions, array $responsive_data = []): array
    {
        $prefix = $context->get_variable_prefix();
        $variables = [];
        
        // Core image variables
        $variables[$context->apply_variable_prefix('made')] = $image_url;
        $variables[$context->apply_variable_prefix('made_url')] = $image_url; // For full URL compatibility
        $variables[$context->apply_variable_prefix('width')] = $image_dimensions['width'] ?? '';
        $variables[$context->apply_variable_prefix('height')] = $image_dimensions['height'] ?? '';
        
        // Source image variables
        $source_url = $context->get_param('src', '');
        $variables[$context->apply_variable_prefix('orig')] = $source_url;
        $variables[$context->apply_variable_prefix('orig_url')] = $source_url;
        
        // Image metadata
        if ($source_url) {
            $path_info = pathinfo($source_url);
            $variables[$context->apply_variable_prefix('name')] = $path_info['filename'] ?? '';
            $variables[$context->apply_variable_prefix('extension')] = $path_info['extension'] ?? '';
            $variables[$context->apply_variable_prefix('basename')] = $path_info['basename'] ?? '';
        }
        
        // Format and quality
        $variables[$context->apply_variable_prefix('type')] = $context->get_param('save_as', 'jpg');
        $variables[$context->apply_variable_prefix('quality')] = $context->get_param('quality', '85');
        
        // Responsive image variables
        if (!empty($responsive_data)) {
            $variables[$context->apply_variable_prefix('srcset')] = $responsive_data['srcset'] ?? '';
            $variables[$context->apply_variable_prefix('sizes')] = $responsive_data['sizes'] ?? '';
            $variables[$context->apply_variable_prefix('srcset_param')] = $responsive_data['srcset'] ?? '';
            $variables[$context->apply_variable_prefix('sizes_param')] = $responsive_data['sizes'] ?? '';
        }
        
        // Aspect ratio calculation
        $width = (int) ($image_dimensions['width'] ?? 0);
        $height = (int) ($image_dimensions['height'] ?? 0);
        if ($width > 0 && $height > 0) {
            $variables[$context->apply_variable_prefix('aspect_ratio')] = round($height / $width, 6);
        }
        
        // Cache information for debugging
        if ($context->get_param('debug', '') === 'yes') {
            $cache_info = $this->get_cache_info($context, $source_url);
            $variables[$context->apply_variable_prefix('cache_exists')] = $cache_info['cache_exists'] ? 'yes' : 'no';
            $variables[$context->apply_variable_prefix('cache_path')] = $cache_info['cache_path'] ?? '';
            $variables[$context->apply_variable_prefix('cache_age')] = $cache_info['cache_age'] ?? '';
        }
        
        // Preload attributes for S4-F1 integration
        $preload_param = $context->get_param('preload', '');
        $enable_preload_for_template = false;
        
        if ($preload_param) {
            // Parameter provided - check value
            $enable_preload_for_template = substr(strtolower($preload_param), 0, 1) === 'y';
        } else {
            // No parameter - check for default setting
            $settings = new \JCOGSDesign\JCOGSImagePro\Service\Settings();
            $enable_preload_for_template = $settings->get('img_cp_default_preload_critical', 'n') === 'y';
        }
        
        if ($enable_preload_for_template) {
            $variables[$context->apply_variable_prefix('preload')] = 'data-jip-preload="' . $image_url . '"';
            
            // Add fetchpriority (default to 'auto' if not specified)
            $fetchpriority = $context->get_param('fetchpriority', 'auto');
            if (in_array(strtolower($fetchpriority), ['high', 'low', 'auto'])) {
                $variables[$context->apply_variable_prefix('preload')] .= ' data-jip-fetchpriority="' . $fetchpriority . '"';
            }
            
            // Add responsive preload attributes
            if (!empty($responsive_data['srcset'])) {
                $variables[$context->apply_variable_prefix('preload')] .= ' data-jip-preload-srcset="' . $responsive_data['srcset'] . '"';
            }
            if (!empty($responsive_data['sizes'])) {
                $variables[$context->apply_variable_prefix('preload')] .= ' data-jip-preload-sizes="' . $responsive_data['sizes'] . '"';
            }
        }
        
        // Consolidate class and style attributes with prefix support
        $attributes = $this->generate_basic_attributes($context);
        if (!empty($attributes)) {
            $variables[$context->apply_variable_prefix('attributes')] = $this->format_attributes_string($attributes);
        }
        
        return $variables;
    }
    
    /**
     * Check if cache should be overwritten based on S4-F2 parameters
     * 
     * @param Context $context Processing context
     * @param string $source_path Original image path  
     * @return array Cache information array with 'should_overwrite' and 'cache_path'
     */
    public function get_cache_info(Context $context, string $source_path): array
    {
        $cache_path = $this->cache_service->generate_cache_path($context, $source_path);
        $should_overwrite = $this->cache_service->should_overwrite_cache($context, $cache_path);
        
        return [
            'should_overwrite' => $should_overwrite,
            'cache_path' => $cache_path,
            'cache_exists' => file_exists($cache_path),
            'cache_age' => file_exists($cache_path) ? time() - filemtime($cache_path) : null
        ];
    }

    /**
     * Get debug information for output generation
     * 
     * @param Context $context Processing context
     * @param array $final_attributes Final attributes used
     * @return string Debug information
     */
    public function get_debug_info(Context $context, array $final_attributes): string
    {
        $info = [
            "Output generation debug:",
            "Lazy loading mode: " . ($this->getLazyLoadingService()->get_lazy_loading_mode($context) ?? 'disabled'),
            "Responsive images: " . ($this->responsive_service->is_responsive_enabled($context) ? 'enabled' : 'disabled'),
            "Dimension attributes: " . ($this->utilities_service->should_add_dimensions($context) ? 'enabled' : 'disabled'),
            "Attribute count: " . count($final_attributes),
        ];
        
        if (!empty($final_attributes)) {
            $info[] = "Attributes:";
            foreach ($final_attributes as $name => $value) {
                $info[] = "  {$name}=\"{$value}\"";
            }
        }
        
        return implode("\n", $info);
    }
    
    /**
     * Get cache statistics for debugging and reporting
     * 
     * @return array Cache statistics
     */
    public function get_cache_statistics(): array
    {
        return $this->cache_service->get_cache_statistics();
    }
    
    /**
     * Process tagdata content for inclusion in output
     * 
     * @param Context $context Processing context
     * @return string Processed tagdata content
     */
    private function process_tagdata(Context $context): string
    {
        $tagdata = $context->get_tag_data();
        
        if (empty($tagdata)) {
            return '';
        }
        
        // Check if bulk tag processing should exclude tagdata
        $bulk_tag = $context->get_param('bulk_tag', '');
        if (substr(strtolower($bulk_tag), 0, 1) === 'y') {
            return '';
        }
        
        // Remove template variables from tagdata (Legacy compatibility)
        $processed_tagdata = preg_replace('/\{.*\}/', '', $tagdata);
        
        return trim($processed_tagdata);
    }
    
    /**
     * Sanitize attribute values for security
     * 
     * @param string $value Attribute value
     * @return string Sanitized value
     */
    private function sanitize_attribute_value(string $value): string
    {
        // Basic XSS prevention
        $value = strip_tags($value);
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $value;
    }
    
    /**
     * Check if decoding attribute should be added
     * 
     * @param Context $context Processing context
     * @return bool True if decoding attribute should be added
     */
    private function should_add_decoding_attribute(Context $context): bool
    {
        $settings = \JCOGSDesign\JCOGSImagePro\Service\ServiceCache::settings();
        return $settings->get('img_cp_html_decoding_enabled', 'y') === 'y';
    }
    
    /**
     * Check if class/style consolidation should be applied
     * 
     * @param Context $context Processing context
     * @return bool True if consolidation should be applied
     */
    private function should_consolidate_classes(Context $context): bool
    {
        // Check consolidate_class_style parameter first
        $consolidate_param = $context->get_param('consolidate_class_style', '');
        if (!empty($consolidate_param)) {
            return substr(strtolower($consolidate_param), 0, 1) !== 'n';
        }
        
        // Use ServiceCache for efficient settings access
        $settings = \JCOGSDesign\JCOGSImagePro\Service\ServiceCache::settings();
        return $settings->get('img_cp_class_consolidation_default', 'y') === 'y';
    }
    
    /**
     * Check if variable expansion should be applied
     * 
     * @param Context $context Processing context
     * @return bool True if variables should be expanded
     */
    private function should_expand_variables(Context $context): bool
    {
        // Use ServiceCache for efficient settings access
        $settings = \JCOGSDesign\JCOGSImagePro\Service\ServiceCache::settings();
        return $settings->get('img_cp_attribute_variable_expansion_default', 'y') === 'y';
    }
}
