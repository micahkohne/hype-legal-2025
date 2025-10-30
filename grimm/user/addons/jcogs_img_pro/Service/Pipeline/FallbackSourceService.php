<?php declare(strict_types=1);

/**
 * JCOGS Image Pro - Fallback Source Resolution Service
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
 * Fallback Source Resolution Service
 * 
 * Handles the complex fallback source resolution system that mirrors
 * the Legacy addon's fallback handling logic.
 * 
 * Priority order:
 * 1. Primary src parameter
 * 2. fallback_src parameter  
 * 3. System default fallback (based on img_cp_enable_default_fallback_image)
 *    - 'yc': Color fill mode
 *    - 'yl': Local image fallback
 *    - 'yr': Remote image fallback
 */
class FallbackSourceService extends AbstractService
{
    private $image_utilities;
    
    public function __construct()
    {
        parent::__construct('FallbackSourceService');
        // $this->utilities_service is now available via parent
        // $this->settings_service is now available via parent  
        // All other common services are also available
        $this->image_utilities = ee('jcogs_img_pro:ImageUtilities');
    }
    
    /**
     * Resolve local fallback image path
     * 
     * @param string $path Local image path
     * @return string|null Resolved path or null if not found
     */
    public function resolve_local_fallback(string $path): ?string
    {
        if (empty($path)) {
            $this->utilities_service->debug_message('Local fallback path is empty', null, false, 'detailed');
            return null;
        }
        
        $this->utilities_service->debug_message('Resolving local fallback path', $path, false, 'detailed');
        
        // Parse EE file directory syntax (e.g., {filedir_2}image.jpg) - mirrors Legacy parseFiledir()
        try {
            $resolved_path = $this->image_utilities->parseFiledir($path);
            $this->utilities_service->debug_log('EE file syntax parsed', $path . ' -> ' . $resolved_path, false, 'detailed');
        } catch (\Exception $e) {
            $this->utilities_service->debug_log('EE file syntax parsing failed', $path . ' - ' . $e->getMessage(), false, 'detailed');
            $resolved_path = $path; // Return original path as fallback
        }
        
        $this->utilities_service->debug_message('Parsed path result', $resolved_path, false, 'detailed');
        
        // Mirror Legacy approach: don't pre-validate paths, let loading system handle validation
        // Legacy assigns parseFiledir() result directly to $this->params->src without validation
        if ($resolved_path) {
            $this->utilities_service->debug_log('Local fallback resolved (validation deferred to loading)', $resolved_path, false, 'detailed');
            return $resolved_path;
        }
        
        $this->utilities_service->debug_log('Local fallback parsing failed', $resolved_path ?: 'null', false, 'detailed');
        return null;
    }
    
    /**
     * Resolve remote fallback image URL
     * 
     * @param string $url Remote image URL
     * @return string|null Validated URL or null if invalid
     */
    public function resolve_remote_fallback(string $url): ?string
    {
        if (empty($url)) {
            return null;
        }
        
        // Basic URL validation and ensure it's HTTP/HTTPS for image loading
        if (filter_var($url, FILTER_VALIDATE_URL) && 
            (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) {
            $this->utilities_service->debug_message('Remote fallback validated', $url, false, 'detailed');
            return $url;
        }
        
        $this->utilities_service->debug_message('Remote fallback URL invalid', $url, false, 'detailed');
        return null;
    }
    
    /**
     * Resolve source with complete fallback chain
     * 
     * @param Context $context Processing context
     * @return array|null ['source' => string, 'is_fallback' => bool, 'fallback_type' => string]
     */
    public function resolve_source_with_fallbacks(Context $context): ?array
    {
        $primary_src = $context->get_param('src', '');
        
        // Try primary source first - Legacy behavior: always return primary if provided
        // The actual validation happens during image loading, not here
        if (!empty($primary_src)) {
            $this->utilities_service->debug_message('Using primary source (validation deferred)', $primary_src, false, 'detailed');
            return [
                'source' => $primary_src,
                'is_fallback' => false,
                'fallback_type' => 'primary'
            ];
        }
        
        // Primary src is empty, try fallback_src parameter
        $fallback_src = $context->get_param('fallback_src', '');
        if (!empty($fallback_src)) {
            $this->utilities_service->debug_message('Primary src empty, using fallback_src parameter', $fallback_src);
            return [
                'source' => $fallback_src,
                'is_fallback' => true,
                'fallback_type' => 'parameter'
            ];
        }
        
        // Try system default fallback options
        return $this->resolve_system_default_fallback($context);
    }
    
    /**
     * Resolve system default fallback based on settings
     * 
     * @param Context $context Processing context
     * @return array|null Fallback source info or null
     */
    public function resolve_system_default_fallback(Context $context): ?array
    {
        $settings = $this->settings_service->get_all();
        $fallback_setting = $settings['img_cp_enable_default_fallback_image'] ?? 'n';
        
        $this->utilities_service->debug_message('Checking system default fallback', $fallback_setting, false, 'detailed');
        
        switch ($fallback_setting) {
            case 'yc':
                // Color fill - will be handled by setup_color_fill_mode()
                $this->utilities_service->debug_message('System fallback: Color fill mode');
                return [
                    'source' => '',
                    'is_fallback' => true,
                    'fallback_type' => 'color_fill'
                ];
                
            case 'yl':
                // Local image fallback
                $local_path = $settings['img_cp_fallback_image_local'] ?? '';
                $this->utilities_service->debug_message('Local fallback path from settings', $local_path, false, 'detailed');
                $resolved_path = $this->resolve_local_fallback($local_path);
                if ($resolved_path) {
                    $this->utilities_service->debug_message('System fallback: Local image', $resolved_path);
                    return [
                        'source' => $resolved_path,
                        'is_fallback' => true,
                        'fallback_type' => 'local'
                    ];
                }
                break;
                
            case 'yr':
                // Remote image fallback
                $remote_url = $settings['img_cp_fallback_image_remote'] ?? '';
                $this->utilities_service->debug_message('Remote fallback URL from settings', $remote_url, false, 'detailed');
                $resolved_url = $this->resolve_remote_fallback($remote_url);
                if ($resolved_url) {
                    $this->utilities_service->debug_message('System fallback: Remote image', $resolved_url);
                    return [
                        'source' => $resolved_url,
                        'is_fallback' => true,
                        'fallback_type' => 'remote'
                    ];
                }
                break;
        }
        
        $this->utilities_service->debug_message('No system fallback available');
        return null;
    }
    
    /**
     * Setup color fill mode when no valid source found
     * 
     * @param Context $context Processing context
     * @return bool True if color fill mode was set up successfully
     */
    public function setup_color_fill_mode(Context $context): bool
    {
        // Mirror legacy logic from JcogsImage::initialise() Stage 3
        $settings = $this->settings_service->get_all();
        
        // Setup color fill if img_cp_enable_default_fallback_image is specifically set to 'yc'
        // This should fire when source fails to resolve, not just when src is empty
        if (($settings["img_cp_enable_default_fallback_image"] ?? 'n') === 'yc') {
            
            $this->utilities_service->debug_message('Color fill fallback mode activated - yc setting enabled');
            
            $context->set_flag('use_colour_fill', true);
            $context->set_flag('valid_image', true);
            
            // Set up color fill metadata
            $context->set_metadata('orig_filename', 'color_fill_');
            $context->set_metadata('image_type', 'color_fill');
            
            return true;
        }
        
        $fallback_setting = $settings["img_cp_enable_default_fallback_image"] ?? 'NOT SET';
        $this->utilities_service->debug_message('Color fill mode NOT activated - fallback setting: ' . $fallback_setting, null, false, 'detailed');
        
        return false;
    }
}
