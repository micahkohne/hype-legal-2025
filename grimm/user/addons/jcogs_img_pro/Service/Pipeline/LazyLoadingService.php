<?php declare(strict_types=1);

/**
 * JCOGS Image Pro - Lazy Loading Service
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
use JCOGSDesign\JCOGSImagePro\Service\Pipeline\AbstractService;

/**
 * Lazy Loading Service
 * 
 * Handles lazy loading implementation for JCOGS Image Pro, maintaining
 * full backward compatibility with Legacy lazy loading approaches.
 * 
 * Legacy modes supported:
 * - 'lqip': Low Quality Image Placeholder with CSS background approach
 * - 'dominant_color': Dominant color placeholder with CSS background approach  
 * - 'js_lqip': JavaScript-based LQIP with IntersectionObserver
 * - 'js_dominant_color': JavaScript-based dominant color with IntersectionObserver
 * - 'html5': Native HTML5 loading="lazy" attribute only
 * 
 * @package JCOGSDesign\JCOGSImagePro\Service\Pipeline
 */
class LazyLoadingService extends AbstractService
{
    /**
     * @var array Valid lazy loading modes
     */
    private const VALID_MODES = [
        'lqip', 'dominant_color', 'js_lqip', 'js_dominant_color', 'html5'
    ];
    
    /**
     * @var array CSS background-based modes
     */
    private const CSS_BACKGROUND_MODES = ['lqip', 'dominant_color'];
    
    /**
     * @var array JavaScript-based modes  
     */
    private const JAVASCRIPT_MODES = ['js_lqip', 'js_dominant_color'];
    
    /**
     * Constructor
     * 
     * All common services are now automatically available via parent AbstractService.
     * No need to manually instantiate common services.
     */
    public function __construct()
    {
        parent::__construct('LazyLoadingService');
        // $this->settings_service is now available via parent
        // $this->utilities_service is now available via parent
        // All other common services are also available
    }
    
    /**
     * Get default lazy loading mode from settings
     * 
     * @return string Default mode
     */
    public function get_default_mode(): string
    {
        return $this->settings_service->get('img_cp_lazy_loading_mode', 'lqip');
    }
    
    /**
     * Get JPG fallback URL for noscript compatibility
     * 
     * For JavaScript lazy loading modes, we create JPG versions of non-JPG images
     * to ensure maximum browser compatibility in noscript scenarios.
     * 
     * @param string $src_url Original image URL
     * @param Context $context Processing context
     * @return string JPG fallback URL or original URL if JPG not needed
     */
    private function get_jpg_fallback_url(string $src_url, Context $context): string
    {
        $mode = $this->get_lazy_loading_mode($context);
        $save_as = strtolower($context->get_param('save_as', 'jpg'));
        
        // Only use JPG fallback for JavaScript modes and non-JPG formats
        if (!$this->uses_javascript($mode) || $save_as === 'jpg') {
            return $src_url;
        }
        
        // Convert URL to JPG version
        $path_info = pathinfo($src_url);
        $jpg_url = $path_info['dirname'] . '/' . $path_info['filename'] . '.jpg';
        
        return $jpg_url;
    }
    
    /**
     * Determine the lazy loading mode to use
     * 
     * @param Context $context Processing context
     * @return string|null Lazy loading mode or null if disabled
     */
    public function get_lazy_loading_mode(Context $context): ?string
    {
        if (!$this->is_lazy_loading_enabled($context)) {
            return null;
        }
        
        $lazy_param = $context->get_param('lazy', '');
        
        // If parameter specifies a mode, validate and use it
        if ($lazy_param && in_array($lazy_param, self::VALID_MODES)) {
            $requested_mode = $lazy_param;
        } else {
            // Fall back to default mode from settings
            $requested_mode = $this->settings_service->get('img_cp_lazy_loading_mode', 'lqip');
        }
        
        // OVERRIDE: For srcset images, force JavaScript approach to avoid layout issues
        // CSS background lazy loading sets dimensions based on primary image, but srcset
        // may cause browser to choose different sized image, creating dimension mismatches
        if ($this->has_srcset_param($context) && in_array($requested_mode, ['lqip', 'dominant_color'])) {
            // Convert CSS background modes to JavaScript equivalents
            $js_mode = 'js_' . $requested_mode;
            $this->utilities_service->debug_message("Srcset detected - converting {$requested_mode} to {$js_mode} to avoid dimension conflicts", null, false, 'detailed');
            return $js_mode;
        }
        
        // OVERRIDE: For masked images, use JavaScript approach to avoid edge artifacts
        // When mask filters are applied, CSS background LQIP creates ugly edge bleeding
        // because pixelate/blur filters don't handle transparency edges cleanly
        if ($this->has_mask_filter($context) && in_array($requested_mode, ['lqip', 'dominant_color'])) {
            // Convert CSS background modes to JavaScript equivalents
            $js_mode = 'js_' . $requested_mode;
            $this->utilities_service->debug_message("Mask filter detected - converting {$requested_mode} to {$js_mode} to avoid edge artifacts", null, false, 'detailed');
            return $js_mode;
        }
        
        return $requested_mode;
    }
    
    /**
     * Generate lazy loading attributes for HTML output
     * 
     * @param Context $context Processing context
     * @param string $src_url Primary image URL
     * @param string $placeholder_url Placeholder image URL (if applicable)
     * @param string|null $srcset_value Srcset attribute value (if applicable)
     * @param string|null $sizes_value Sizes attribute value (if applicable)
     * @return array Associative array of HTML attributes
     */
    public function generate_lazy_attributes(
        Context $context, 
        string $src_url, 
        string $placeholder_url = '', 
        ?string $srcset_value = null,
        ?string $sizes_value = null
    ): array {
        $mode = $this->get_lazy_loading_mode($context);
        
        if (!$mode) {
            // No lazy loading - return standard attributes
            $attributes = ['src' => $src_url];
            if ($srcset_value) {
                $attributes['srcset'] = $srcset_value;
            }
            if ($sizes_value) {
                $attributes['sizes'] = $sizes_value;
            }
            return $attributes;
        }
        
        $attributes = [];
        
        // All modes get loading="lazy" attribute
        $attributes['loading'] = 'lazy';
        
        if ($mode === 'html5') {
            // HTML5-only mode - just add native lazy loading
            $attributes['src'] = $src_url;
            if ($srcset_value) {
                $attributes['srcset'] = $srcset_value;
            }
            if ($sizes_value) {
                $attributes['sizes'] = $sizes_value;
            }
        } elseif ($this->uses_css_background($mode)) {
            // CSS background approach (Legacy compatibility)
            $attributes['src'] = $src_url;
            
            // Add both background-image style and data-bglzy for template post-processing
            if (!empty($placeholder_url)) {
                // Add data-bglzy attribute for TemplatePostParse extension
                $attributes['data-bglzy'] = $placeholder_url;
                
                // Add background-image style for immediate placeholder display
                $existing_style = $context->get_param('style', '');
                $background_style = "background-image: url({$placeholder_url});";
                
                if (!empty($existing_style)) {
                    // Ensure existing style ends with semicolon
                    $existing_style = rtrim($existing_style, ';') . '; ';
                    $attributes['style'] = $existing_style . $background_style;
                } else {
                    $attributes['style'] = $background_style;
                }
            }
            
            if ($srcset_value) {
                $attributes['srcset'] = $srcset_value;
            }
            if ($sizes_value) {
                $attributes['sizes'] = $sizes_value;
            }
        } elseif ($this->uses_javascript($mode)) {
            // JavaScript approach with data attributes (Pro-specific to avoid Legacy conflicts)
            $attributes['src'] = $placeholder_url;
            $attributes['data-ji-pro-src'] = $src_url;
            
            // Add Pro-specific class to avoid conflicts with Legacy addon JavaScript
            $existing_class = $context->get_param('class', '');
            $pro_class = 'jcogs-img-pro-lazy';
            
            if (!empty($existing_class)) {
                $attributes['class'] = $existing_class . ' ' . $pro_class;
            } else {
                $attributes['class'] = $pro_class;
            }
            
            if ($srcset_value) {
                $attributes['data-ji-pro-srcset'] = $srcset_value;
            }
            if ($sizes_value) {
                $attributes['sizes'] = $sizes_value;
            }
        }
        
        return $attributes;
    }
    
    /**
     * Generate noscript fallback HTML for progressive enhancement
     * 
     * @param Context $context Processing context
     * @param string $src_url Primary image URL
     * @param array $additional_attributes Additional HTML attributes
     * @return string Noscript HTML element
     */
    public function generate_noscript_fallback(
        Context $context, 
        string $src_url, 
        array $additional_attributes = []
    ): string {
        if (!$this->needs_progressive_enhancement($context)) {
            return '';
        }
        
        // For noscript fallback, use JPG version for maximum browser compatibility
        $noscript_src = $this->get_jpg_fallback_url($src_url, $context);
        
        $attributes = array_merge($additional_attributes, ['src' => $noscript_src]);
        
        $attr_string = '';
        foreach ($attributes as $name => $value) {
            $attr_string .= sprintf(' %s="%s"', 
                htmlspecialchars($name, ENT_QUOTES), 
                htmlspecialchars($value, ENT_QUOTES)
            );
        }
        
        return sprintf('<noscript class="ji__progenhlazyns"><img%s></noscript>', $attr_string);
    }
    
    /**
     * Check if image has mask filter applied
     * 
     * Mask filters create transparency that causes edge artifacts when 
     * pixelate/blur filters are applied for LQIP generation.
     * 
     * @param Context $context Processing context
     * @return bool True if mask filter is detected
     */
    private function has_mask_filter(Context $context): bool
    {
        $filter_param = $context->get_param('filter', '');
        
        if (empty($filter_param)) {
            return false;
        }
        
        // Check if filter parameter contains mask filter
        // Filter parameter format: "mask,type,parameters" or "filter1|mask,type,parameters|filter2"
        $filters = explode('|', $filter_param);
        
        foreach ($filters as $filter) {
            $filter_parts = explode(',', trim($filter));
            if (!empty($filter_parts[0]) && strtolower(trim($filter_parts[0])) === 'mask') {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if srcset parameter is active
     * 
     * When srcset is active, CSS background lazy loading can cause layout issues
     * because it sets dimensions based on the primary image, but srcset may cause
     * the browser to choose a different sized image.
     * 
     * @param Context $context Processing context
     * @return bool True if srcset parameter is present and not empty
     */
    private function has_srcset_param(Context $context): bool
    {
        $srcset_param = $context->get_param('srcset', '');
        
        // Check if srcset parameter is present and not explicitly disabled
        return !empty($srcset_param) && !in_array(strtolower($srcset_param), ['no', 'n', 'false', '0']);
    }
    
    /**
     * Check if lazy loading is enabled for the context
     * 
     * @param Context $context Processing context
     * @return bool True if lazy loading should be applied
     */
    public function is_lazy_loading_enabled(Context $context): bool
    {
        $lazy_param = $context->get_param('lazy', '');
        
        // Check explicit parameter setting first
        if ($lazy_param) {
            // If explicitly set to 'no', 'n', 'false', '0', disable lazy loading
            return !in_array(strtolower($lazy_param), ['no', 'n', 'false', '0']);
        }
        
        // Check default setting from Pro/Legacy settings
        $default_enabled = $this->settings_service->get('img_cp_enable_lazy_loading', 'n');
        return $default_enabled === 'y';
    }
    
    /**
     * Validate lazy loading mode parameter
     * 
     * @param string $mode Mode to validate
     * @return bool True if mode is valid
     */
    public function is_valid_mode(string $mode): bool
    {
        return in_array($mode, self::VALID_MODES);
    }
    
    /**
     * Check if progressive enhancement noscript tag is needed
     * 
     * @param Context $context Processing context
     * @return bool True if noscript fallback should be generated
     */
    public function needs_progressive_enhancement(Context $context): bool
    {
        $mode = $this->get_lazy_loading_mode($context);
        
        if (!$mode || !$this->uses_javascript($mode)) {
            return false;
        }
        
        // Check Pro setting for progressive enhancement
        $progressive_setting = $this->settings_service->get('img_cp_lazy_progressive_enhancement', 'y');
        
        return $progressive_setting === '1' || $progressive_setting === 'y';
    }
    
    /**
     * Check if the mode requires placeholder generation
     * 
     * @param string $mode Lazy loading mode
     * @return bool True if placeholder image is needed
     */
    public function requires_placeholder(string $mode): bool
    {
        return in_array($mode, [...self::CSS_BACKGROUND_MODES, ...self::JAVASCRIPT_MODES]);
    }
    
    /**
     * Check if lazy loading should force dimension attributes
     * 
     * @param Context $context Processing context
     * @return bool True if dimensions should be added
     */
    public function should_force_dimensions(Context $context): bool
    {
        $mode = $this->get_lazy_loading_mode($context);
        
        if (!$mode) {
            return false;
        }
        
        // CSS background and JavaScript modes need dimensions for proper layout
        return $this->uses_css_background($mode) || $this->uses_javascript($mode);
    }
    
    /**
     * Check if the mode uses CSS background approach
     * 
     * @param string $mode Lazy loading mode
     * @return bool True if CSS background approach is used
     */
    public function uses_css_background(string $mode): bool
    {
        return in_array($mode, self::CSS_BACKGROUND_MODES);
    }
    
    /**
     * Check if the mode uses JavaScript approach
     * 
     * @param string $mode Lazy loading mode
     * @return bool True if JavaScript approach is used
     */
    public function uses_javascript(string $mode): bool
    {
        return in_array($mode, self::JAVASCRIPT_MODES);
    }
}
