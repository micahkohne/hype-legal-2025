<?php

/**
 * JCOGS Image Pro Variable Modifier for EE7
 * ==========================================
 * 
 * Provides variable modifier functionality for applying image presets
 * and processing parameters to image URLs and paths in EE templates.
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 6 Variable Modifier Implementation
 */

/**
 * Namespace is required: add-on namespace + '\Modifiers'
 */
namespace JCOGSDesign\JCOGSImagePro\Modifiers;

use ExpressionEngine\Service\Template\Variables\ModifierInterface;
use JCOGSDesign\JCOGSImagePro\Service\ModifierService;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

/**
 * JCOGS Image Pro Variable Modifier
 * 
 * Implements EE7 ModifierInterface to provide image processing capabilities
 * through variable modifiers. Supports preset application and parameter overrides.
 * 
 * Usage Examples:
 * - {image_field:jcogs_img jip_preset='thumbnail'}
 * - {custom_field:jcogs_img jip_preset='hero' jip_quality='90'}
 * - {gallery_image:resize:jcogs_img width='800' jip_preset='effects'}
 */
class Jcogs_img implements ModifierInterface
{
    /**
     * EE7 modifier entry point
     * 
     * Processes variable content through JCOGS Image Pro pipeline using
     * jip_ prefixed parameters to apply presets and image processing.
     * 
     * @param mixed $data Variable content (URL/path/filedir tag)
     * @param array $params Modifier parameters from template
     * @param mixed $tagdata Template tag data (unused for variable modifiers)
     * @return string Processed image URL or original data on failure
     */
    public function modify($data, $params = array(), $tagdata = false)
    {
        try {
            // Extract URL from data - handle both string URLs and EE file arrays
            $source_url = $this->_extract_source_url($data);
            
            if (!$source_url) {
                // Could not extract valid URL, return original data
                return is_string($data) ? $data : '';
            }

            // Filter for jip_ prefixed parameters only
            $jip_params = $this->_extract_jip_parameters($params);
            
            // No jip_ parameters means no processing needed
            if (empty($jip_params)) {
                return $source_url;
            }

            // Validate parameters before processing
            if (!$this->_validate_modifier_parameters($jip_params)) {
                return $source_url;
            }

            // Delegate to ModifierService for processing
            $modifierService = new ModifierService();
            return $modifierService->processModifier($source_url, $jip_params);
            
        } catch (\Exception $e) {
            // Log error and return safe fallback
            if (function_exists('ee')) {
                ee()->logger->developer('JCOGS Image Pro modifier error: ' . $e->getMessage(), [
                    'data' => $data,
                    'params' => $params,
                    'exception' => $e->getTraceAsString()
                ]);
            }
            
            // Try to extract URL for fallback, or return empty string to avoid array conversion
            $fallback_url = $this->_extract_source_url($data);
            return $fallback_url ?: (is_string($data) ? $data : '');
        }
    }
    
    /**
     * Check for potentially malicious content in parameter values
     * 
     * @param string $value Parameter value to check
     * @return bool True if content appears malicious
     */
    private function _contains_malicious_content($value)
    {
        return ServiceCache::security()->containsMaliciousContent($value);
    }
    
    /**
     * Decode and normalize input for comprehensive security checking
     * 
     * @param string $value Input value to decode
     * @return string Decoded and normalized value
     */

    /**
     * Extract jip_ prefixed parameters
     * 
     * Filters modifier parameters to only include those with the jip_ prefix,
     * removing the prefix for internal processing. This ensures we only process
     * parameters intended for JCOGS Image Pro and avoid conflicts with other
     * modifiers in the chain.
     * 
     * @param array $params All modifier parameters from template
     * @return array Filtered parameters with jip_ prefix removed
     */
    private function _extract_jip_parameters($params)
    {
        $jip_params = [];
        
        if (!is_array($params)) {
            return $jip_params;
        }
        
        foreach ($params as $key => $value) {
            // Check for jip_ prefix (case-sensitive)
            if (strpos($key, 'jip_') === 0) {
                // Remove jip_ prefix for internal processing
                $clean_key = substr($key, 4);
                $jip_params[$clean_key] = $value;
            }
        }
        
        return $jip_params;
    }
    
    /**
     * Validate modifier parameters for basic safety and structure
     * 
     * @param array $params JIP parameters to validate
     * @return bool True if parameters are valid for processing
     */
    private function _validate_modifier_parameters($params)
    {
        // Check for dangerous/malicious parameter values
        foreach ($params as $key => $value) {
            // Ensure string values (EE passes most params as strings)
            if (!is_string($value) && !is_numeric($value)) {
                return false;
            }
            
            // Check for potentially dangerous content
            $value = (string) $value;
            if ($this->_contains_malicious_content($value)) {
                return false;
            }
            
            // Check parameter key format (after jip_ prefix removed)
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Extract source URL from variable data
     * 
     * Handles multiple data types that can be passed to modifiers:
     * - String URLs (direct usage)
     * - EE file arrays (from {file:id:url} tags)
     * - Other variable content
     * 
     * @param mixed $data Variable data from EE
     * @return string|false Source URL or false if cannot extract
     */
    private function _extract_source_url($data)
    {
        // Case 1: Simple string URL
        if (is_string($data) && !empty($data)) {
            return trim($data);
        }
        
        // Case 2: EE File array (from {file:id:url} tags)
        if (is_array($data)) {
            // Try 'url' key first (most common for file URLs)
            if (isset($data['url']) && is_string($data['url']) && !empty($data['url'])) {
                return trim($data['url']);
            }
            
            // Try 'path' key as fallback
            if (isset($data['path']) && is_string($data['path']) && !empty($data['path'])) {
                // If path looks like a directory, combine with filename
                if (isset($data['file_name']) && is_string($data['file_name'])) {
                    $path = rtrim($data['path'], '/') . '/' . $data['file_name'];
                    return trim($path);
                }
                return trim($data['path']);
            }
            
            // Try 'src' key as another fallback
            if (isset($data['src']) && is_string($data['src']) && !empty($data['src'])) {
                return trim($data['src']);
            }
        }
        
        return false;
    }
}
