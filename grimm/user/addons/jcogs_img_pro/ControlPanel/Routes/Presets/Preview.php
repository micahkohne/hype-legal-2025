<?php

/**
 * JCOGS Image Pro - Preview Route
 * ===============================
 * AJAX endpoint for live preview generation using Action Link system
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 5 Live Preview Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\Presets;

use Exception;
use JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\ImageAbstractRoute;
use JCOGSDesign\JCOGSImagePro\Service\ParameterRegistry;

class Preview extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'presets/preview';

    /**
     * Process AJAX preview request
     * 
     * Generates action link URL for real-time preview using existing
     * _serve_act_image system for efficient image delivery.
     * 
     * @param mixed $preset_id Preset ID (optional for validation)
     * @return $this Fluent interface for EE7 routing
     */
    public function process($preset_id = false)
    {
        // DEBUG: Log that the preview endpoint was reached
        $this->utilities_service->debug_message('Preview endpoint called - Method: ' . $_SERVER['REQUEST_METHOD'] . ', Preset ID: ' . $preset_id);
        
        // Ensure this is an AJAX POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->utilities_service->debug_message('Preview endpoint: Invalid request method');
            $this->_return_json_error('Invalid request method');
            return $this;
        }

        // Check for AJAX headers
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            $this->utilities_service->debug_message('Preview endpoint: Missing AJAX headers');
            $this->_return_json_error('AJAX request required');
            return $this;
        }

        try {
            // Get JSON input data
            $json_input = file_get_contents('php://input');
            $this->utilities_service->debug_message('Preview endpoint: JSON input: ' . $json_input);
            
            $request_data = json_decode($json_input, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->utilities_service->debug_message('Preview endpoint: JSON decode error: ' . json_last_error_msg());
                $this->_return_json_error('Invalid JSON data');
                return $this;
            }

            // Validate CSRF token
            $csrf_token = $request_data['csrf_token'] ?? '';
            if (empty($csrf_token) || $csrf_token !== CSRF_TOKEN) {
                $this->utilities_service->debug_message('Preview endpoint: CSRF token validation failed. Expected: ' . CSRF_TOKEN . ', Got: ' . $csrf_token);
                $this->_return_json_error('Invalid CSRF token');
                return $this;
            }

            // Extract parameters and preview image
            $parameters = $request_data['parameters'] ?? [];
            $preview_file_id = $request_data['preview_file_id'] ?? null;
            
            $this->utilities_service->debug_message('Preview endpoint: Parameters count: ' . count($parameters));
            $this->utilities_service->debug_message('Preview endpoint: Preview file ID: ' . var_export($preview_file_id, true));
            
            if (empty($parameters)) {
                $this->utilities_service->debug_message('Preview endpoint: No parameters provided');
                $this->_return_json_error('No parameters provided');
                return $this;
            }

            // Validate preview image
            $preview_image_url = $this->_get_preview_image_url($preview_file_id);
            $this->utilities_service->debug_message('Preview endpoint: Preview image URL result: ' . var_export($preview_image_url, true));
            
            if (!$preview_image_url) {
                $this->utilities_service->debug_message('Preview endpoint: Preview image URL is empty or invalid');
                $this->_return_json_error('Invalid preview image');
                return $this;
            }

            // Build action link for preview
            $preview_url = $this->_generate_preview_action_link($parameters, $preview_image_url);
            $this->utilities_service->debug_message('Preview endpoint: Generated preview URL: ' . var_export($preview_url, true));
            
            if (!$preview_url) {
                $this->_return_json_error('Failed to generate preview');
                return $this;
            }

            // Return success response
            $this->_return_json_success([
                'preview_url' => $preview_url,
                'timestamp' => time(),
                'cache_buster' => uniqid()
            ]);

        } catch (Exception $e) {
            $this->utilities_service->debug_log('Preview generation error: ' . $e->getMessage());
            $this->_return_json_error('Preview generation failed: ' . $e->getMessage());
        }

        return $this;
    }

    /**
     * Get preview image URL from file ID
     * 
     * @param int|null $preview_file_id EE Files ID
     * @return string|null Image URL or null if invalid
     */
    private function _get_preview_image_url($preview_file_id): ?string
    {
        if (empty($preview_file_id)) {
            // Try to get global default preview image
            $default_preview_setting = $this->settings_service->get('preset_default_preview_file_id', null);
            $this->utilities_service->debug_log('Preview URL: No file ID provided, checking default setting: ' . var_export($default_preview_setting, true));
            
            if (empty($default_preview_setting)) {
                $this->utilities_service->debug_log('Preview URL: No default preview setting found');
                return null;
            }
            
            // Parse EE Files format: {file:97:url} -> extract file ID
            if (preg_match('/\{file:(\d+):url\}/', $default_preview_setting, $matches)) {
                $preview_file_id = (int)$matches[1];
                $this->utilities_service->debug_log('Preview URL: Extracted file ID from setting: ' . $preview_file_id);
            } elseif (is_numeric($default_preview_setting)) {
                $preview_file_id = (int)$default_preview_setting;
                $this->utilities_service->debug_log('Preview URL: Using numeric setting as file ID: ' . $preview_file_id);
            } else {
                $this->utilities_service->debug_log('Preview URL: Could not parse default preview setting: ' . $default_preview_setting);
                return null;
            }
        }

        $this->utilities_service->debug_log('Preview URL: Using file ID: ' . $preview_file_id);

        // Get file info from EE Files
        $file_info = $this->_get_file_info((int)$preview_file_id);
        
        if (!$file_info) {
            $this->utilities_service->debug_log('Preview URL: File not found for ID: ' . $preview_file_id);
            return null;
        }
        
        if (!$this->_is_image_file($file_info)) {
            $this->utilities_service->debug_log('Preview URL: File is not an image: ' . json_encode($file_info));
            return null;
        }

        $this->utilities_service->debug_log('Preview URL: Found valid image URL: ' . $file_info['url']);
        return $file_info['url'];
    }

    /**
     * Generate action link URL for preview processing
     * 
     * Leverages existing action link system from OutputStage for consistent
     * image processing and delivery.
     * 
     * @param array $parameters Processing parameters
     * @param string $image_url Source image URL
     * @return string|null Action link URL or null on failure
     */
    private function _generate_preview_action_link(array $parameters, string $image_url): ?string
    {
        try {
            // DEBUG: Log incoming parameters
            $this->utilities_service->debug_log('Preview action link - Incoming parameters: ' . json_encode($parameters));
            $this->utilities_service->debug_log('Preview action link - Image URL: ' . $image_url);
            
            // Build parameters for action link generation
            $action_params = $parameters;
            
            // Set required parameters for action link
            $action_params['src'] = $image_url;
            $action_params['action_link'] = 'yes'; // Force action link generation
            $action_params['cache'] = '0'; // Disable caching for previews
            $action_params['url_only'] = 'yes'; // Force URL only mode
            
            // Add preview-specific parameters
            $action_params['act_what'] = 'preview';
            $action_params['act_path'] = $image_url;
            
            // DEBUG: Log action parameters before validation
            $this->utilities_service->debug_log('Preview action link - Before validation: ' . json_encode($action_params));
            
            // Validate critical parameters using ParameterRegistry
            $action_params = $this->_validate_preview_parameters($action_params);
            
            // DEBUG: Log action parameters after validation
            $this->utilities_service->debug_log('Preview action link - After validation: ' . json_encode($action_params));
            
            // Build ACT packet (base64 encoded JSON)
            $act_packet = base64_encode(json_encode($action_params));
            
            if (empty($act_packet)) {
                $this->utilities_service->debug_log('Preview action link: JSON encoding failed');
                return null;
            }

            // Get action ID for act_originated_image
            $act_id = $this->utilities_service->get_action_id('ActOriginatedImage');
            if (!$act_id) {
                $this->utilities_service->debug_log('Preview action link: No action ID found');
                return null;
            }

            // Build action URL
            $action_url = sprintf(
                '%s?ACT=%s&act_packet=%s&preview=1&t=%s',
                ee()->config->item('site_url'),
                $act_id,
                urlencode($act_packet),
                time() // Cache buster
            );

            $this->utilities_service->debug_log('Generated preview action link: ' . $action_url);
            return $action_url;

        } catch (Exception $e) {
            $this->utilities_service->debug_log('Preview action link generation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate and sanitize preview parameters
     * 
     * Uses ParameterRegistry for consistent validation with main processing pipeline.
     * 
     * @param array $parameters Raw parameters
     * @return array Validated parameters
     */
    private function _validate_preview_parameters(array $parameters): array
    {
        try {
            $validated = [];

            foreach ($parameters as $param_name => $param_value) {
                // Skip non-processing parameters
                if (in_array($param_name, ['src', 'action_link', 'url_only', 'act_what', 'act_path', 'cache'])) {
                    $validated[$param_name] = $param_value;
                    continue;
                }

                // Convert parameter name to lowercase for registry check
                $param_name_lower = strtolower($param_name);
                
                // Validate using registry if parameter is known
                if (ParameterRegistry::parameterExists($param_name_lower)) {
                    // Sanitize dimensional parameters (width, height, max, etc.)
                    if (in_array($param_name_lower, ['width', 'height', 'max', 'max_width', 'max_height', 'min', 'min_width', 'min_height'])) {
                        $sanitized_value = $this->validation_service->validate_dimension($param_value);
                        if ($sanitized_value !== false && $sanitized_value !== null) {
                            $validated[$param_name_lower] = (string) $sanitized_value;
                        }
                    } else {
                        // For non-dimensional parameters, just use the value as-is
                        $validated[$param_name_lower] = $param_value;
                    }
                }
            }

            return $validated;

        } catch (Exception $e) {
            $this->utilities_service->debug_log('Parameter validation error: ' . $e->getMessage());
            return $parameters; // Return original parameters as fallback
        }
    }

    /**
     * Get file info from EE Files
     * 
     * @param int $file_id EE Files ID
     * @return array|null File information or null if not found
     */
    private function _get_file_info(int $file_id): ?array
    {
        try {
            $file = ee('Model')->get('File', $file_id)->first();
            
            if (!$file) {
                return null;
            }

            return [
                'id' => $file->file_id,
                'url' => $file->getAbsoluteURL(),
                'filename' => $file->file_name,
                'mime_type' => $file->mime_type
            ];

        } catch (Exception $e) {
            $this->utilities_service->debug_log('File lookup error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if file is a valid image
     * 
     * @param array $file_info File information array
     * @return bool True if valid image file
     */
    private function _is_image_file(array $file_info): bool
    {
        $image_mime_types = [
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/gif',
            'image/webp',
            'image/avif',
            'image/bmp'
        ];

        return in_array($file_info['mime_type'], $image_mime_types);
    }

    /**
     * Return JSON success response
     * 
     * @param array $data Response data
     * @return void
     */
    private function _return_json_success(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
        exit;
    }

    /**
     * Return JSON error response
     * 
     * @param string $message Error message
     * @return void
     */
    private function _return_json_error(string $message): void
    {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $message
        ]);
        exit;
    }
}
