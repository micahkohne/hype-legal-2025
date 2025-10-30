<?php

/**
 * JCOGS Image Pro - Format Capability Detection Service
 * =====================================================
 * Dynamic detection of supported image formats based on server and browser capabilities
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 4 CP Integration Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service;

class FormatCapabilityDetection
{
    /**
     * @var array|null Cached server capabilities
     */
    private static $server_capabilities = null;

    /**
     * @var array|null Cached browser capabilities
     */
    private static $browser_capabilities = null;

    /**
     * @var array Known image formats that we can potentially support
     */
    private static $known_formats = [
        'jpg' => ['mime' => 'image/jpeg', 'extensions' => ['jpg', 'jpeg']],
        'jpeg' => ['mime' => 'image/jpeg', 'extensions' => ['jpg', 'jpeg']], // Alias for jpg
        'png' => ['mime' => 'image/png', 'extensions' => ['png']],
        'gif' => ['mime' => 'image/gif', 'extensions' => ['gif']],
        'webp' => ['mime' => 'image/webp', 'extensions' => ['webp']],
        'avif' => ['mime' => 'image/avif', 'extensions' => ['avif']],
        'bmp' => ['mime' => 'image/bmp', 'extensions' => ['bmp']],
        'tiff' => ['mime' => 'image/tiff', 'extensions' => ['tiff', 'tif']],
        'heic' => ['mime' => 'image/heic', 'extensions' => ['heic']],
        'heif' => ['mime' => 'image/heif', 'extensions' => ['heif']],
    ];

    /**
     * Get server image processing capabilities
     * 
     * @return array Server supported formats
     */
    public function getServerCapabilities(): array
    {
        if (self::$server_capabilities !== null) {
            return self::$server_capabilities;
        }

        $capabilities = [];

        // Check GD extension capabilities
        if (extension_loaded('gd')) {
            $gd_info = gd_info();
            
            // JPEG support
            if (!empty($gd_info['JPEG Support']) || !empty($gd_info['JPG Support'])) {
                $capabilities['jpg'] = true;
                $capabilities['jpeg'] = true; // Alias
            }
            
            // PNG support
            if (!empty($gd_info['PNG Support'])) {
                $capabilities['png'] = true;
            }
            
            // GIF support
            if (!empty($gd_info['GIF Read Support']) && !empty($gd_info['GIF Create Support'])) {
                $capabilities['gif'] = true;
            }
            
            // WebP support (GD 2.0.2+)
            if (!empty($gd_info['WebP Support'])) {
                $capabilities['webp'] = true;
            }
            
            // AVIF support (GD 2.1.0+)
            if (!empty($gd_info['AVIF Support'])) {
                $capabilities['avif'] = true;
            }
            
            // BMP support (available in some GD versions)
            if (!empty($gd_info['BMP Support'])) {
                $capabilities['bmp'] = true;
            }
        }

        // Check ImageMagick/Imagick extension capabilities
        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick();
                $formats = $imagick->queryFormats();
                
                // Convert to lowercase for consistent checking
                $formats = array_map('strtolower', $formats);
                
                // Check for additional formats supported by ImageMagick
                foreach (self::$known_formats as $format => $info) {
                    if (in_array(strtoupper($format), $formats) || in_array($format, $formats)) {
                        $capabilities[$format] = true;
                    }
                    
                    // Check alternative names/extensions
                    foreach ($info['extensions'] as $ext) {
                        if (in_array(strtoupper($ext), $formats) || in_array($ext, $formats)) {
                            $capabilities[$format] = true;
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log error but continue with GD capabilities
                error_log('JCOGS Image Pro: ImageMagick capability detection failed: ' . $e->getMessage());
            }
        }

        // Cache the results
        self::$server_capabilities = $capabilities;
        
        return $capabilities;
    }

    /**
     * Get browser image format support capabilities
     * 
     * @return array Browser supported formats
     */
    public function getBrowserCapabilities(): array
    {
        if (self::$browser_capabilities !== null) {
            return self::$browser_capabilities;
        }

        $capabilities = [];
        
        // Universal browser support (always available)
        $capabilities['jpg'] = true;
        $capabilities['jpeg'] = true; // Alias
        $capabilities['png'] = true;
        $capabilities['gif'] = true;

        // Detect modern format support via User-Agent analysis
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // WebP support detection
        if ($this->browserSupportsWebP($user_agent)) {
            $capabilities['webp'] = true;
        }
        
        // AVIF support detection  
        if ($this->browserSupportsAVIF($user_agent)) {
            $capabilities['avif'] = true;
        }
        
        // HEIC/HEIF support (primarily Safari on macOS/iOS)
        if ($this->browserSupportsHEIC($user_agent)) {
            $capabilities['heic'] = true;
            $capabilities['heif'] = true;
        }

        // Check Accept header if available for more accurate detection
        $accept_header = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (!empty($accept_header)) {
            if (strpos($accept_header, 'image/webp') !== false) {
                $capabilities['webp'] = true;
            }
            if (strpos($accept_header, 'image/avif') !== false) {
                $capabilities['avif'] = true;
            }
            if (strpos($accept_header, 'image/heic') !== false) {
                $capabilities['heic'] = true;
            }
            if (strpos($accept_header, 'image/heif') !== false) {
                $capabilities['heif'] = true;
            }
        }

        // Cache the results
        self::$browser_capabilities = $capabilities;
        
        return $capabilities;
    }

    /**
     * Get intersection of server and browser capabilities
     * 
     * @return array Formats that both server can create AND browser can display
     */
    public function getSupportedFormats(): array
    {
        $server_caps = $this->getServerCapabilities();
        $browser_caps = $this->getBrowserCapabilities();
        
        // Get intersection of capabilities
        $supported = array_intersect_key($server_caps, $browser_caps);
        
        // Always include 'source' as a special option
        $supported['source'] = true;
        
        return $supported;
    }

    /**
     * Get format choices for form dropdowns
     * 
     * @return array Format options suitable for EE form dropdowns
     */
    public function getFormatChoices(): array
    {
        $supported = $this->getSupportedFormats();
        $choices = [];
        
        // Always add 'source' first
        $choices['source'] = lang('jcogs_img_pro_param_control_output_format_source');
        
        // Add supported formats with language keys
        $format_labels = [
            'jpg' => lang('jcogs_img_pro_param_control_output_format_jpg'),
            'jpeg' => lang('jcogs_img_pro_param_control_output_format_jpeg'), 
            'png' => lang('jcogs_img_pro_param_control_output_format_png'),
            'gif' => lang('jcogs_img_pro_param_control_output_format_gif'),
            'webp' => lang('jcogs_img_pro_param_control_output_format_webp'),
            'avif' => lang('jcogs_img_pro_param_control_output_format_avif'),
            'bmp' => lang('jcogs_img_pro_param_control_output_format_bmp'),
            'tiff' => lang('jcogs_img_pro_param_control_output_format_tiff'),
            'heic' => lang('jcogs_img_pro_param_control_output_format_heic'),
            'heif' => lang('jcogs_img_pro_param_control_output_format_heif'),
        ];
        
        foreach ($supported as $format => $supported_flag) {
            if ($format !== 'source' && $supported_flag && isset($format_labels[$format])) {
                $choices[$format] = $format_labels[$format];
            }
        }
        
        return $choices;
    }

    /**
     * Check if browser supports WebP
     * 
     * @param string $user_agent Browser user agent string
     * @return bool True if WebP is likely supported
     */
    private function browserSupportsWebP(string $user_agent): bool
    {
        // Chrome 23+, Firefox 65+, Safari 14+, Edge 18+
        if (preg_match('/Chrome\/(\d+)/', $user_agent, $matches)) {
            return (int)$matches[1] >= 23;
        }
        if (preg_match('/Firefox\/(\d+)/', $user_agent, $matches)) {
            return (int)$matches[1] >= 65;
        }
        if (preg_match('/Safari\//', $user_agent) && preg_match('/Version\/(\d+)/', $user_agent, $matches)) {
            return (int)$matches[1] >= 14;
        }
        if (preg_match('/Edge\/(\d+)/', $user_agent, $matches)) {
            return (int)$matches[1] >= 18;
        }
        
        return false;
    }

    /**
     * Check if browser supports AVIF
     * 
     * @param string $user_agent Browser user agent string  
     * @return bool True if AVIF is likely supported
     */
    private function browserSupportsAVIF(string $user_agent): bool
    {
        // Chrome 85+, Firefox 93+, Safari 16.1+
        if (preg_match('/Chrome\/(\d+)/', $user_agent, $matches)) {
            return (int)$matches[1] >= 85;
        }
        if (preg_match('/Firefox\/(\d+)/', $user_agent, $matches)) {
            return (int)$matches[1] >= 93;
        }
        if (preg_match('/Safari\//', $user_agent) && preg_match('/Version\/(\d+)\.(\d+)/', $user_agent, $matches)) {
            $major = (int)$matches[1];
            $minor = (int)$matches[2];
            return $major > 16 || ($major === 16 && $minor >= 1);
        }
        
        return false;
    }

    /**
     * Check if browser supports HEIC/HEIF
     * 
     * @param string $user_agent Browser user agent string
     * @return bool True if HEIC/HEIF is likely supported
     */
    private function browserSupportsHEIC(string $user_agent): bool
    {
        // Primarily Safari on macOS/iOS
        return preg_match('/Safari\//', $user_agent) && 
               (preg_match('/Macintosh/', $user_agent) || preg_match('/iPhone|iPad/', $user_agent));
    }

    /**
     * Clear cached capabilities (useful for testing)
     * 
     * @return void
     */
    public static function clearCache(): void
    {
        self::$server_capabilities = null;
        self::$browser_capabilities = null;
    }

    /**
     * Get detailed capability information for debugging
     * 
     * @return array Detailed capability information
     */
    public function getDetailedCapabilities(): array
    {
        return [
            'server' => $this->getServerCapabilities(),
            'browser' => $this->getBrowserCapabilities(),
            'supported' => $this->getSupportedFormats(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'accept_header' => $_SERVER['HTTP_ACCEPT'] ?? '',
            'gd_info' => extension_loaded('gd') ? gd_info() : null,
            'imagick_formats' => extension_loaded('imagick') ? (new \Imagick())->queryFormats() : null
        ];
    }
}
