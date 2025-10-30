<?php

/**
 * JCOGS Image Pro - Template Post Parse Extension
 * ================================================
 * Template post-processing with lazy loading and optimization features
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

namespace JCOGSDesign\JCOGSImagePro\Extensions;

use ExpressionEngine\Service\Addon\Controllers\Extension\AbstractRoute;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

/**
 * Template Post Parse Extension for JCOGS Image Pro
 * 
 * Handles template post-processing including:
 * - Lazy loading JavaScript injection
 * - Image preload link generation  
 * - Background lazy loading optimization
 * - NoScript CSS fallbacks
 * 
 * @package JCOGSImagePro\Extensions
 * @author JCOGS Design
 * @version 2.0.0 (Phase 2A Migration)
 * @since Pro 2.0.0
 */
class TemplatePostParse extends AbstractRoute
{
    protected $addon_name = 'jcogs_img_pro';
    protected $version = '2.0.0';
    
    /**
     * Direct service access for optimal performance
     * Following established pattern from other migrated services
     */
    private $utilities_service;
    
    /**
     * Initialize with direct service access
     */
    public function __construct()
    {
        $this->utilities_service = ServiceCache::utilities();
    }
    
    /**
     * Process final template for lazy loading and preload optimization
     * 
     * Migrated from Legacy ext.jcogs_img.php->template_post_parse()
     * Processes only final templates (not partials/embeds) for:
     * - Lazy loading JavaScript injection when data-ji-src detected
     * - Background lazy loading preload generation from data-bglzy attributes
     * - Image preload link creation from data-ji-preload attributes
     * 
     * @param string $final_template The final parsed template
     * @param bool $is_partial Whether this is a partial/embed template
     * @param int $site_id Current site ID
     * @return string Processed template with lazy loading and preloads
     */
    public function process($final_template, $is_partial, $site_id)
    {
        // Only process final templates (not partials/embeds)
        if ($is_partial !== false) {
            return $final_template;
        }
        
        // Check for other extensions and use their output if available
        if (isset(ee()->extensions->last_call) && ee()->extensions->last_call) {
            $final_template = ee()->extensions->last_call;
        }
        
        try {
            // 1. Handle lazy loading JavaScript injection
            $final_template = $this->_process_lazy_loading_javascript($final_template);
            
            // 2. Handle background lazy loading preloads
            $final_template = $this->_process_background_lazy_preloads($final_template);
            
            // 3. Handle image preload generation
            $final_template = $this->_process_image_preloads($final_template);
            
        } catch (\Exception $e) {
            // Log error but don't break template rendering
            $this->utilities_service->debug_message("Template post-processing error: " . $e->getMessage());
        }
        
        return $final_template;
    }
    
    /**
     * Generate Pro-specific lazy loading JavaScript
     * 
     * Loads JavaScript from minified file following Legacy pattern
     * Falls back to inline version if file is missing (development safety)
     * 
     * @return string JavaScript code for Pro lazy loading
     */
    private function _generate_pro_lazy_loading_javascript(): string
    {
        // Follow Legacy pattern: read from minified JavaScript file
        $js_file = __DIR__ . '/../javascript/lazy_load_pro.min.js';
        
        if (file_exists($js_file)) {
            $javascript = file_get_contents($js_file);
            if ($javascript !== false) {
                return $javascript;
            }
        }
        
        // Fallback to inline minified version if file is missing (development safety)
        // This ensures the extension still works even if files are missing
        return 'function jcogs_pro_lazyload(){let e;const t=document.querySelectorAll("[data-ji-pro-src]");e&&clearTimeout(e),e=setTimeout(()=>{const e=window.scrollY;t.forEach(t=>{t.offsetTop<window.innerHeight+e&&(t.src=t.dataset.jiProSrc,t.removeAttribute("data-ji-pro-src"),void 0!==t.dataset.jiProSrcset&&(t.srcset=t.dataset.jiProSrcset,t.removeAttribute("data-ji-pro-srcset")))}),0===t.length&&removeProLazyLoadListeners()},20)}function removeProLazyLoadListeners(){document.removeEventListener("scroll",jcogs_pro_lazyload),window.removeEventListener("resize",jcogs_pro_lazyload),window.removeEventListener("orientationChange",jcogs_pro_lazyload)}document.addEventListener("DOMContentLoaded",()=>{document.body.classList.add("jcogs_img_pro_lazy_loaded"),document.querySelectorAll("noscript.ji__progenhlazyns").forEach(e=>e.parentNode.removeChild(e));const e=document.querySelectorAll("[data-ji-pro-src]");if("IntersectionObserver"in window){const t=new IntersectionObserver(e=>{e.forEach(e=>{if(e.isIntersecting){const r=e.target;r.src=r.dataset.jiProSrc,r.removeAttribute("data-ji-pro-src"),void 0!==r.dataset.jiProSrcset&&(r.srcset=r.dataset.jiProSrcset,r.removeAttribute("data-ji-pro-srcset")),t.unobserve(r)}})});e.forEach(e=>t.observe(e))}else document.addEventListener("scroll",jcogs_pro_lazyload),window.addEventListener("resize",jcogs_pro_lazyload),window.addEventListener("orientationChange",jcogs_pro_lazyload)});';
    }
    
    /**
     * Process background lazy loading preload generation
     * 
     * Migrated from Legacy ext.jcogs_img.php template_post_parse()
     * Finds data-bglzy attributes and creates preload links in head
     * 
     * @param string $template Template content to process  
     * @return string Template with background preload links injected
     */
    private function _process_background_lazy_preloads(string $template): string
    {
        // Look for background lazy loading markers
        preg_match_all('/data-bglzy=\"(.*?)\"/', $template, $matches, PREG_UNMATCHED_AS_NULL);
        
        if ($matches && !empty($matches[1])) {
            $head_insert = '';
            
            foreach($matches[1] as $i => $match) {
                // Insert as preload if not already present and URL is valid
                if (!empty($match) && strpos($head_insert, $match) === false) {
                    // Create preload entry for high-priority background images
                    $head_insert .= "<link rel=\"preload\" as=\"image\" href=\"{$match}\" fetchpriority=\"high\">\n";
                    
                    // Remove the data attribute as it's no longer needed
                    $template = str_replace($matches[0][$i], '', $template);
                }
            }
            
            // Insert preload links into head section
            if ($head_insert !== '') {
                $count = 1; // Replace only first occurrence
                $template = str_ireplace('</head>', $head_insert . '</head>', $template, $count);
                
                $this->utilities_service->debug_message("Background lazy loading preloads injected: " . count($matches[1]));
            }
        }
        
        return $template;
    }
    
    /**
     * Process image preload generation
     * 
     * Enhanced: Now supports fetchpriority, responsive preloads, and advanced options
     * Migrated from Legacy ext.jcogs_img.php template_post_parse()
     * Finds data-jip-preload attributes and creates preload links in head
     * Uses Pro-specific data attribute to avoid conflicts with Legacy
     * 
     * @param string $template Template content to process
     * @return string Template with image preload links injected  
     */
    private function _process_image_preloads(string $template): string
    {
        // Look for Pro-specific image preload markers
        preg_match_all('/data-jip-preload=\"(.*?)\"/', $template, $preload_matches, PREG_UNMATCHED_AS_NULL);
        
        if ($preload_matches && !empty($preload_matches[1])) {
            $head_insert = '';
            
            foreach($preload_matches[1] as $i => $image_url) {
                // Skip if URL is empty or already processed
                if (empty($image_url) || strpos($head_insert, $image_url) !== false) {
                    continue;
                }
                
                // Start building preload link
                $preload_attributes = [
                    'rel' => 'preload',
                    'as' => 'image',
                    'href' => $image_url
                ];
                
                // Sprint 4: Check for fetchpriority attribute
                $fetchpriority_pattern = '/data-jip-fetchpriority=\"(high|low|auto)\"/';
                if (preg_match($fetchpriority_pattern, $preload_matches[0][$i], $fetchpriority_match)) {
                    $preload_attributes['fetchpriority'] = $fetchpriority_match[1];
                    // Remove the fetchpriority data attribute
                    $template = str_replace($fetchpriority_match[0], '', $template);
                }
                
                // Sprint 4: Check for responsive preload attributes
                $srcset_pattern = '/data-jip-preload-srcset=\"([^\"]*)\"/';
                if (preg_match($srcset_pattern, $preload_matches[0][$i], $srcset_match)) {
                    $preload_attributes['imagesrcset'] = $srcset_match[1];
                    // Remove the srcset data attribute
                    $template = str_replace($srcset_match[0], '', $template);
                }
                
                $sizes_pattern = '/data-jip-preload-sizes=\"([^\"]*)\"/';
                if (preg_match($sizes_pattern, $preload_matches[0][$i], $sizes_match)) {
                    $preload_attributes['imagesizes'] = $sizes_match[1];
                    // Remove the sizes data attribute
                    $template = str_replace($sizes_match[0], '', $template);
                }
                
                // Build the preload link tag
                $link_attributes = [];
                foreach ($preload_attributes as $attr => $value) {
                    $link_attributes[] = $attr . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
                }
                $head_insert .= "<link " . implode(' ', $link_attributes) . ">\n";
                
                // Remove the main preload data attribute
                $template = str_replace($preload_matches[0][$i], '', $template);
            }
            
            // Insert preload links into head section
            if ($head_insert !== '') {
                $count = 1; // Replace only first occurrence  
                $template = str_ireplace('</head>', $head_insert . '</head>', $template, $count);
                
                $preload_count = count($preload_matches[1]);
                $this->utilities_service->debug_message("Sprint 4 Pro image preloads injected: {$preload_count}");
            }
        }
        
        return $template;
    }
    
    /**
     * Process lazy loading JavaScript injection
     * 
     * Migrated from Legacy ext.jcogs_img.php template_post_parse() 
     * Detects data-ji-src attributes and injects lazy loading JavaScript + CSS
     * 
     * @param string $template Template content to process
     * @return string Template with lazy loading JavaScript injected
     */
    private function _process_lazy_loading_javascript(string $template): string
    {
        // Look for JCOGS Image Pro Lazy Loading data attributes (Pro-specific)
        preg_match('/data-ji-pro-src/', $template, $matches, PREG_UNMATCHED_AS_NULL);
        
        if ($matches) {
            // Check if Pro JavaScript is already injected to avoid duplicates
            if (strpos($template, 'jcogs_img_pro_lazy_loaded') !== false) {
                return $template;
            }
            
            // Create Pro-specific JavaScript that uses different data attributes
            $javascript = $this->_generate_pro_lazy_loading_javascript();
            $css = '<noscript><style>[data-ji-pro-src]{display:none;}</style></noscript>';
            
            // Split template at head and body closing tags
            $parts = explode('</head>', $template);
            if (count($parts) >= 2) {
                $start = $parts[0];
                $rest = implode('</head>', array_slice($parts, 1));
                
                $body_parts = explode('</body>', $rest);
                if (count($body_parts) >= 2) {
                    $middle = $body_parts[0];
                    $end = implode('</body>', array_slice($body_parts, 1));
                    
                    // Inject CSS in head and JavaScript before closing body
                    $template = $start . PHP_EOL . $css . PHP_EOL . '</head>' . PHP_EOL . 
                               $middle . PHP_EOL. '<script>' . PHP_EOL . $javascript . PHP_EOL . 
                               '</script>' . PHP_EOL . '</body>' . PHP_EOL . $end;
                               
                    $this->utilities_service->debug_message("Pro lazy loading JavaScript injected into template");
                }
            }
        }
        
        return $template;
    }

}
