<?php

/**
 * JCOGS Image Pro - Live Preview Generator Route
 * ==============================================
 * Route for generating live previews during parameter editing
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 5: UI Enhancement
 */

namespace JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\Preview;

use JCOGSDesign\JCOGSImagePro\ControlPanel\Routes\ImageAbstractRoute;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;
use Exception;

class Generate extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'preview/generate';

    /**
     * @var array Default sample images for preview
     */
    private $default_samples = [
        '/media/images/sample_landscape.jpg',
        '/media/images/sample_portrait.jpg',
        '/media/images/sample_square.jpg'
    ];

    /**
     * Generate live preview for parameter testing
     * 
     * @param mixed $id Not used for preview generation
     * @return mixed JSON response or redirect
     */
    public function process($id = false)
    {
        // Only allow POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->jsonResponse(['error' => 'Method not allowed'], 405);
        }

        // Only allow AJAX requests
        if (!$this->isAjaxRequest()) {
            return $this->jsonResponse(['error' => 'AJAX requests only'], 400);
        }

        try {
            $preview_data = $this->generatePreview();
            return $this->jsonResponse($preview_data);
        } catch (Exception $e) {
            $this->utilities_service->debug_log('preview_generation_error', $e->getMessage());
            return $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate the actual preview image
     * 
     * @return array Preview generation result
     */
    private function generatePreview(): array
    {
        $start_time = microtime(true);

        // Get preview parameters from POST
        $parameters = $this->extractPreviewParameters();
        $sample_image = $this->getSampleImagePath();

        // Validate sample image exists
        if (!$this->validateSampleImage($sample_image)) {
            throw new Exception('Sample image not found or not accessible');
        }

        // Generate preview using the pipeline system
        $pipeline_service = ServiceCache::pipeline();
        
        // Create preview parameters with source image
        $preview_params = array_merge($parameters, [
            'src' => $sample_image,
            '_preview_mode' => true,
            '_called_by' => 'Preview_Generator'
        ]);
        
        $preview_result = $pipeline_service->process($preview_params);

        $processing_time = microtime(true) - $start_time;

        if (!$preview_result['success']) {
            throw new Exception($preview_result['error'] ?? 'Preview generation failed');
        }

        // Get preview metadata from pipeline result
        $metadata = $this->extractPreviewMetadata($preview_result, $processing_time);

        return [
            'success' => true,
            'preview_url' => $preview_result['url'] ?? $preview_result['output'],
            'metadata' => $metadata,
            'parameters_used' => $parameters,
            'sample_image' => $sample_image
        ];
    }

    /**
     * Extract preview parameters from POST data
     * 
     * @return array Cleaned preview parameters
     */
    private function extractPreviewParameters(): array
    {
        $parameters = [];
        
        // Get all form parameters except system ones
        $excluded_keys = [
            'preview_sample_image',
            'preview_max_width', 
            'preview_max_height',
            'csrf_token',
            'XID'
        ];

        foreach ($_POST as $key => $value) {
            if (!in_array($key, $excluded_keys) && !empty($value)) {
                // Sanitize parameter value
                $parameters[$key] = $this->sanitizeParameterValue($value);
            }
        }

        // Add preview-specific constraints
        $max_width = (int) ($_POST['preview_max_width'] ?? 400);
        $max_height = (int) ($_POST['preview_max_height'] ?? 300);

        // Ensure preview constraints are reasonable
        $parameters['max_width'] = min($max_width, 800);
        $parameters['max_height'] = min($max_height, 600);
        
        // Force specific settings for preview
        $parameters['quality'] = $parameters['quality'] ?? 85;
        $parameters['format'] = $parameters['format'] ?? 'webp';
        $parameters['cache'] = '0'; // Disable caching for previews

        return $parameters;
    }

    /**
     * Get sample image path from POST or use default
     * 
     * @return string Sample image path
     */
    private function getSampleImagePath(): string
    {
        $sample_image = $_POST['preview_sample_image'] ?? '';
        
        if (empty($sample_image)) {
            return $this->getDefaultSampleImage();
        }

        // Validate and sanitize the provided sample image path
        return $this->sanitizeSampleImagePath($sample_image);
    }

    /**
     * Get default sample image path
     * 
     * @return string Default sample image path
     */
    private function getDefaultSampleImage(): string
    {
        // Try to find an available default sample
        foreach ($this->default_samples as $sample) {
            if ($this->validateSampleImage($sample)) {
                return $sample;
            }
        }

        // If no default samples exist, try to find any image in media folder
        $media_path = FCPATH . 'media/images/';
        if (is_dir($media_path)) {
            $image_files = glob($media_path . '*.{jpg,jpeg,png,webp}', GLOB_BRACE);
            if (!empty($image_files)) {
                return str_replace(FCPATH, '/', $image_files[0]);
            }
        }

        throw new Exception('No suitable sample image found for preview');
    }

    /**
     * Validate sample image exists and is accessible
     * 
     * @param string $image_path Image path to validate
     * @return bool True if valid
     */
    private function validateSampleImage(string $image_path): bool
    {
        // Convert relative path to absolute
        $full_path = $this->resolveImagePath($image_path);
        
        if (!file_exists($full_path)) {
            return false;
        }

        // Check if it's a valid image file
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $mime_type = mime_content_type($full_path);
        
        return in_array($mime_type, $allowed_types);
    }

    /**
     * Resolve image path to full system path
     * 
     * @param string $image_path Relative or absolute image path
     * @return string Full system path
     */
    private function resolveImagePath(string $image_path): string
    {
        // If already absolute path, return as-is
        if (strpos($image_path, '/') === 0) {
            return FCPATH . ltrim($image_path, '/');
        }

        // If URL, try to convert to local path
        if (strpos($image_path, 'http') === 0) {
            $base_url = ee()->config->item('base_url');
            if (strpos($image_path, $base_url) === 0) {
                $relative_path = str_replace($base_url, '', $image_path);
                return FCPATH . ltrim($relative_path, '/');
            }
        }

        // Default to media folder
        return FCPATH . 'media/images/' . $image_path;
    }

    /**
     * Sanitize parameter value for preview
     * 
     * @param mixed $value Parameter value to sanitize
     * @return string Sanitized value
     */
    private function sanitizeParameterValue($value): string
    {
        if (is_array($value)) {
            return implode(',', array_map('trim', $value));
        }
        
        return trim((string) $value);
    }

    /**
     * Sanitize sample image path
     * 
     * @param string $image_path Image path to sanitize
     * @return string Sanitized path
     */
    private function sanitizeSampleImagePath(string $image_path): string
    {
        // Remove any dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9\/_.-]/', '', $image_path);
        
        // Prevent directory traversal
        $sanitized = str_replace('..', '', $sanitized);
        
        return $sanitized;
    }

    /**
     * Extract metadata from preview result
     * 
     * @param array $preview_result Processing result
     * @param float $processing_time Time taken to process
     * @return array Metadata for display
     */
    private function extractPreviewMetadata(array $preview_result, float $processing_time): array
    {
        $metadata = [
            'processing_time' => number_format($processing_time * 1000, 2) . 'ms',
            'format' => $preview_result['format'] ?? 'unknown',
            'width' => $preview_result['width'] ?? null,
            'height' => $preview_result['height'] ?? null,
            'file_size' => $this->formatFileSize($preview_result['file_size'] ?? 0)
        ];

        return $metadata;
    }

    /**
     * Format file size for display
     * 
     * @param int $bytes File size in bytes
     * @return string Formatted file size
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        
        $size = $bytes / pow(1024, $power);
        
        return number_format($size, 1) . ' ' . $units[$power];
    }

    /**
     * Check if request is AJAX
     * 
     * @return bool True if AJAX request
     */
    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Return JSON response
     * 
     * @param array $data Response data
     * @param int $status_code HTTP status code
     * @return mixed JSON response
     */
    private function jsonResponse(array $data, int $status_code = 200)
    {
        // Set appropriate headers
        header('Content-Type: application/json');
        http_response_code($status_code);
        
        echo json_encode($data);
        exit;
    }
}
