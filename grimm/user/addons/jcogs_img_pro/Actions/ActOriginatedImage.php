<?php

/**
 * JCOGS Image Pro - ACT Originated Image Action Handler
 * =====================================================
 * Handles direct image serving via EE Action URLs
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

namespace JCOGSDesign\JCOGSImagePro\Actions;

/**
 * ACT Originated Image Action Handler
 * 
 * Handles EE Action URLs that directly serve processed images.
 * These URLs are generated when action_link='yes' parameter is used
 * or when img_cp_action_links setting is enabled globally.
 * 
 * The handler:
 * 1. Decodes base64-encoded parameter packets from ACT URLs
 * 2. Attempts direct cache serving for performance
 * 3. Falls back to full image processing if needed
 * 4. Sets appropriate HTTP headers and serves image data
 */
class ActOriginatedImage extends ImageAbstractAction
{
    /**
     * @var object|null Decoded ACT parameters from the URL packet
     */
    private $act_params = null;

    /**
     * ACT originated image processing method
     * 
     * Handles EE Action URLs that directly serve processed images.
     * This method is called when EE encounters an Action URL like:
     * https://site.com/?ACT=123&act_packet=base64_encoded_params
     * 
     * The action is registered during addon installation and routes
     * to this method for direct image serving with appropriate headers.
     * 
     * @return string Empty string (image data sent via headers) or error message
     */
    public function process(): string
    {
        $action_name = 'ActOriginatedImage';
        
        // Set ACT processing flag first (mirrors Legacy: ee('jcogs_img:ImageUtilities')::$act_based_tag = true)
        $this->validation_service->set_act_processing_flag(true);
        
        $this->start_benchmark($action_name);
        
        // Debug log the ACT call (mirrors Legacy debug message)
        $this->utilities_service->debug_log('JCOGS ACT: ActOriginatedImage process() called');
        
        try {
            // Try to decode ACT parameter packet (mirrors Legacy get_act_param_object())
            $this->act_params = $this->validation_service->get_act_param_object();
            
            if ($this->act_params && property_exists($this->act_params, 'act_path')) {
                // We have valid ACT parameters - try direct image serving (mirrors Legacy _send_act_link_image)
                $this->utilities_service->debug_log('JCOGS ACT: act_path found: ' . $this->act_params->act_path);
                
                if ($this->send_act_link_image($this->act_params->act_path)) {
                    // Image served successfully - execution ends here (like Legacy)
                    return '';
                }
            }
            
            // If we get here, ACT processing failed completely
            $this->utilities_service->debug_log('ACT: Failed to process image request');
            $this->end_benchmark_with_error($action_name, 'ACT processing failed');
            return '';
            
        } catch (\Throwable $e) {
            $this->end_benchmark_with_error($action_name, $e->getMessage());
            return $this->handle_action_error($action_name, $e);
        }
    }

    /**
     * Send ACT link image directly (mirrors Legacy _send_act_link_image)
     * 
     * Attempts to serve cached image directly with proper headers.
     * Returns true if successful (and exits), false if failed.
     * This mirrors Legacy's get_file_from_local() cache checking behavior.
     * 
     * @param string|null $image_path Path to the cached image file
     * @return bool True if image served successfully, false otherwise
     */
    private function send_act_link_image(?string $image_path = null): bool
    {
        // If we don't have a path, bail (mirrors Legacy check)
        if (empty($image_path)) {
            return false;
        }

        $this->utilities_service->debug_log('JCOGS ACT: send_act_link_image called for: ' . $image_path);

        $image_raw = null;
        
        // Try to get image from cache first
        if ($this->cache_service->is_image_in_cache($image_path)) {
            $image_raw = $this->filesystem_service->read($image_path);
        }
        
        // If cache failed or image not in cache, generate via pipeline
        if (empty($image_raw)) {
            $this->utilities_service->debug_log('ACT: Cache miss or read failed, triggering pipeline regeneration');
            $image_raw = $this->trigger_pipeline_for_raw_image();
        }
        
        // If we have image data, serve it
        if (!empty($image_raw)) {
            $this->utilities_service->debug_log('ACT: Serving image data, size: ' . strlen($image_raw) . ' bytes');
            return $this->serve_image_data($image_raw, $image_path);
        }
        
        // Failed to get image data
        $this->utilities_service->debug_log('ACT: Failed to obtain image data from cache or pipeline');
        return false;
    }

    /**
     * Serve image data with proper headers and exit
     * 
     * @param string $image_raw Raw image binary data
     * @param string $image_path Path for Content-Type detection
     * @return bool Always returns true (exits before return)
     */
    private function serve_image_data(string $image_raw, string $image_path): bool
    {
        // Get the image size (mirrors Legacy)
        $image_size = strlen($image_raw);
        
        // Set the appropriate Content-Type header (mirrors Legacy switch statement exactly)
        $this->set_content_type_header($image_path);

        // Clear parameters (mirrors Legacy clear_params call)
        $this->validation_service->clear_params();

        // Send headers and image data (mirrors Legacy exactly)
        header('Content-Length: ' . $image_size);
        echo $image_raw;
        exit();
    }

    /**
     * Set appropriate Content-Type header (mirrors Legacy switch statement exactly)
     * 
     * @param string $image_path Path to the image file
     * @return void
     */
    private function set_content_type_header(string $image_path): void
    {
        $extension = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
        
        // Mirror Legacy switch statement exactly
        switch ($extension) {
            case 'avif':
                header('Content-Type: image/avif');
                break;
            case 'bmp':
                header('Content-Type: image/bmp');
                break;
            case 'gif':
                header('Content-Type: image/gif');
                break;
            case 'jpg':
            case 'jpeg':
                header('Content-Type: image/jpeg');
                break;
            case 'png':
                header('Content-Type: image/png');
                break;
            case 'svg':
                header('Content-Type: image/svg+xml');
                break;
            case 'tiff':
                header('Content-Type: image/tiff');
                break;
            case 'webp':
                header('Content-Type: image/webp');
                break;
            default:
                header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
                echo "Unsupported file type.";
                exit;
        }
    }

    /**
     * Trigger image pipeline to generate raw image data for ACT serving
     * 
     * @return string|false Raw image binary data or false on failure
     */
    private function trigger_pipeline_for_raw_image(): string|false
    {
        try {
            // Use the class-level ACT parameters
            if (!$this->act_params) {
                return false;
            }
            
            // Convert ACT parameters object to array for pipeline processing
            $all_params = (array) $this->act_params;
            
            // Separate ACT-specific parameters from image processing parameters
            // These parameters should NOT be included in the filename hash calculation
            $act_specific_params = [
                'act_what',      // ACT request type
                'act_path',      // ACT target path
                'act_packet',    // ACT encoded packet
                'url_only',      // ACT URL generation flag
                'action_link'    // Action link flag
            ];
            
            // Create clean pipeline parameters without ACT-specific values
            $pipeline_params = [];
            foreach ($all_params as $key => $value) {
                if (!in_array($key, $act_specific_params)) {
                    $pipeline_params[$key] = $value;
                }
            }
            
            // Add processing context flags (these are for pipeline behavior, not filename hashing)
            $pipeline_params['_tag_type'] = 'single';
            $pipeline_params['_called_by'] = 'Image_Tag';  // Use Image_Tag instead of ACT_Pipeline to match original hash
           
            // Process through pipeline with clean parameters (ACT flag already set in validation service)
            $result = $this->pipeline_service->process($pipeline_params, null);
            
            // Check if pipeline succeeded and returned raw binary data
            if (isset($result['success']) && $result['success']) {
                $output = $result['output'] ?? '';
                
                // With ACT flag set, OutputStage should return raw binary data instead of HTML
                if (is_string($output) && !empty($output) && !str_contains($output, '<')) {
                    return $output;
                }
            }
            
            return false;
            
        } catch (\Throwable $e) {
            $this->utilities_service->debug_log('ACT: Pipeline execution failed: ' . $e->getMessage());
            return false;
        }
    }
}
