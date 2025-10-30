<?php

/**
 * JCOGS Image Pro - Variable Modifier Service
 * ============================================
 * 
 * Processes variable modifier requests by integrating with the existing
 * preset system and image processing pipeline.
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

namespace JCOGSDesign\JCOGSImagePro\Service;

use JCOGSDesign\JCOGSImagePro\Service\ImageProcessingPipeline;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;
use JCOGSDesign\JCOGSImagePro\Service\PresetResolver;
use JCOGSDesign\JCOGSImagePro\Service\ParameterPackageDiscovery;

/**
 * Variable Modifier Service
 * 
 * Handles processing of variable modifier requests by:
 * 1. Validating and normalizing source data (URLs, paths, filedir tags)
 * 2. Creating processing context from modifier parameters
 * 3. Integrating with existing preset and processing pipeline
 * 4. Returning processed URLs with comprehensive error handling
 */
class ModifierService 
{
    /**
     * @var \JCOGSDesign\JCOGSImagePro\Service\Utilities Utilities service
     */
    private $utilities_service;
    
    /**
     * @var \JCOGSDesign\JCOGSImagePro\Service\PresetResolver Preset resolver service
     */
    private $preset_resolver;
    
    /**
     * @var \JCOGSDesign\JCOGSImagePro\Service\ParameterPackageDiscovery Parameter validation service
     */
    private $parameter_validator;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        // Use ServiceCache for optimal performance (consistent with other services)
        $this->utilities_service = ServiceCache::utilities();
        // PresetResolver uses ServiceCache internally for its dependencies
        $this->preset_resolver = new PresetResolver();
        // ParameterPackageDiscovery for comprehensive parameter validation
        $this->parameter_validator = new ParameterPackageDiscovery();
    }
    /**
     * Process variable modifier request
     * 
     * Main entry point for variable modifier processing. Takes source data
     * and jip_ parameters, processes through the existing image pipeline,
     * and returns a processed image URL.
     * 
     * @param string $source_data Variable content (URL/path/filedir tag)
     * @param array $jip_params Filtered jip_ parameters (prefix removed)
     * @return string Processed image URL or original data on failure
     */
    public function processModifier(string $source_data, array $jip_params): string
    {
        // Start processing with debug tracking
        $processing_start = microtime(true);
        $this->utilities_service->debug_message('Variable modifier processing started', [
            'source_data' => $source_data,
            'parameter_count' => count($jip_params)
        ]);

        try {
            // 1. Validate and normalize source data
            $normalized_source = $this->validateAndNormalizeSource($source_data);
            if (!$normalized_source) {
                $this->logProcessingError('source_validation_failed', 'Invalid source data', [
                    'source_data' => $source_data
                ]);
                return $source_data; // Return original on invalid source
            }

            // 2. Create processing context from variable data
            $processing_context = [
                'src' => $normalized_source,
                // Add jip_ parameters (already have prefix removed)
                ...$jip_params
            ];

            // 3. Validate parameters before processing
            $validation_result = $this->validateProcessingParameters($processing_context);
            if (!$validation_result['valid']) {
                $this->logProcessingError('parameter_validation_failed', 'Parameter validation failed', [
                    'validation_errors' => $validation_result['errors'],
                    'processing_context' => $processing_context
                ]);
                
                // Apply parameter sanitization and continue processing
                $processing_context = $this->sanitizeParameters($processing_context, $validation_result['errors']);
            }

            // 4. Handle preset resolution if preset parameter exists
            $resolved_context = $this->resolvePresetParameters($processing_context);

            // 5. Process through existing pipeline (inherits all caching/optimization)
            $pipeline = new ImageProcessingPipeline(null, $this->utilities_service);
            $result = $pipeline->process($resolved_context, null, false);
            
            // 6. Validate pipeline result and extract output
            $output = $this->extractProcessingOutput($result, $source_data);
            
            // Log successful completion
            $processing_time = (microtime(true) - $processing_start) * 1000;
            $this->utilities_service->debug_message('Variable modifier processing completed', [
                'processing_time_ms' => round($processing_time, 2),
                'output_length' => strlen($output)
            ]);
            
            return $output;
            
        } catch (\Exception $e) {
            // Comprehensive error logging and fallback
            $this->logProcessingError('processing_exception', $e->getMessage(), [
                'source_data' => $source_data,
                'jip_params' => $jip_params,
                'exception_type' => get_class($e),
                'stack_trace' => $e->getTraceAsString()
            ]);
            
            return $source_data; // Always return original data on any failure
        }
    }

    /**
     * Resolve preset parameters and merge with modifier parameters
     * 
     * Handles the preset parameter (converted from jip_preset) by integrating 
     * with the existing PresetResolver system for parameter inheritance.
     * 
     * @param array $processing_context Parameters with source and processed jip_ params
     * @return array Resolved parameters with preset inheritance applied
     */
    private function resolvePresetParameters(array $processing_context): array
    {
        // Check if preset parameter exists (jip_preset gets converted to 'preset' by Modifier class)
        if (!isset($processing_context['preset']) || empty($processing_context['preset'])) {
            // No preset specified, return parameters unchanged
            return $processing_context;
        }

        // Use existing PresetResolver to handle parameter resolution and merging
        try {
            $resolved_parameters = $this->preset_resolver->resolveParameters($processing_context);
            
            // Log preset resolution for debugging
            $this->utilities_service->debug_message(
                'Variable modifier preset resolved: ' . $processing_context['preset'],
                ['parameter_count' => count($resolved_parameters)]
            );
            
            return $resolved_parameters;
            
        } catch (\Exception $e) {
            // If preset resolution fails, log error and return original parameters
            $this->utilities_service->debug_message(
                'Variable modifier preset resolution failed: ' . $e->getMessage()
            );
            
            // Remove failed preset parameter and continue with other parameters
            unset($processing_context['preset']);
            return $processing_context;
        }
    }

    /**
     * Validate and normalize source data
     * 
     * Handles various input formats including direct URLs, local paths,
     * and EE filedir tags. Uses existing ImageUtilities for comprehensive
     * EE tag resolution.
     * 
     * @param string $source_data Raw variable content
     * @return string|false Normalized URL/path or false if invalid
     */
    private function validateAndNormalizeSource(string $source_data): string|false
    {
        // Use existing ImageUtilities parseFiledir method for comprehensive EE tag handling
        $imageUtilities = ee('jcogs_img_pro:ImageUtilities');
        $normalized_path = $imageUtilities->parseFiledir($source_data);
        
        // If parseFiledir returns a different value (not empty), use the parsed result
        // If it returns empty string or same value, use the original (mirrors Legacy behavior)
        if ($normalized_path !== '' && $normalized_path !== $source_data) {
            // EE directive was successfully resolved
            $final_source = $normalized_path;
        } else {
            // Not an EE directive or couldn't be resolved, use original value
            $final_source = $source_data;
        }
        
        // Validate as URL or image path
        if (filter_var($final_source, FILTER_VALIDATE_URL) || 
            $this->isValidImagePath($final_source)) {
            return $final_source;
        }

        return false; // Invalid source data
    }

    /**
     * Validate processing parameters using the parameter package system
     * 
     * @param array $parameters Parameters to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    private function validateProcessingParameters(array $parameters): array
    {
        try {
            // Remove src parameter from validation as it's not a processing parameter
            $processing_params = $parameters;
            unset($processing_params['src']);
            
            // Skip validation if no processing parameters
            if (empty($processing_params)) {
                return ['valid' => true, 'errors' => []];
            }
            
            // Use existing parameter validation system
            $validation_errors = $this->parameter_validator->validateAllParameters($processing_params);
            
            return [
                'valid' => empty($validation_errors),
                'errors' => $validation_errors
            ];
            
        } catch (\Exception $e) {
            // If validation system fails, log but don't block processing
            $this->utilities_service->debug_message(
                'Parameter validation service error: ' . $e->getMessage()
            );
            
            return ['valid' => true, 'errors' => []]; // Allow processing to continue
        }
    }

    /**
     * Sanitize parameters by removing invalid ones
     * 
     * @param array $parameters Original parameters
     * @param array $validation_errors Validation errors
     * @return array Sanitized parameters
     */
    private function sanitizeParameters(array $parameters, array $validation_errors): array
    {
        $sanitized = $parameters;
        
        foreach ($validation_errors as $param_name => $error) {
            // Remove parameters that failed validation
            if (isset($sanitized[$param_name])) {
                unset($sanitized[$param_name]);
                
                $this->utilities_service->debug_message(
                    'Removed invalid parameter during sanitization',
                    ['parameter' => $param_name, 'error' => $error]
                );
            }
        }
        
        return $sanitized;
    }

    /**
     * Extract and validate processing output
     * 
     * @param array $pipeline_result Result from image processing pipeline
     * @param string $fallback_data Original source data for fallback
     * @return string Processed output or fallback data
     */
    private function extractProcessingOutput(array $pipeline_result, string $fallback_data): string
    {
        // Check for output in the expected key
        if (isset($pipeline_result['output']) && !empty($pipeline_result['output'])) {
            return $pipeline_result['output'];
        }
        
        // Check for alternative output keys
        $output_keys = ['output', 'url', 'result', 'processed_url'];
        foreach ($output_keys as $key) {
            if (isset($pipeline_result[$key]) && !empty($pipeline_result[$key])) {
                $this->utilities_service->debug_message(
                    'Using alternative output key: ' . $key,
                    ['value' => $pipeline_result[$key]]
                );
                return $pipeline_result[$key];
            }
        }
        
        // No valid output found, use fallback
        $this->utilities_service->debug_message(
            'No valid output found in pipeline result, using fallback',
            ['pipeline_result_keys' => array_keys($pipeline_result)]
        );
        
        return $fallback_data;
    }

    /**
     * Log processing errors with comprehensive context
     * 
     * @param string $error_type Error type identifier
     * @param string $message Error message
     * @param array $context Additional context data
     */
    private function logProcessingError(string $error_type, string $message, array $context = []): void
    {
        // Enhanced debug logging
        $this->utilities_service->debug_message(
            'Variable modifier error [' . $error_type . ']: ' . $message,
            $context
        );
        
        // Also log to EE debug log for persistent tracking
        if (function_exists('ee')) {
            $this->utilities_service->debug_log('JCOGS Image Pro ModifierService [' . $error_type . ']: ' . $message, $context);
        }
    }

    /**
     * Check if path looks like a valid image file
     * 
     * Performs basic extension validation to ensure we're dealing with
     * image files that can be processed by the image pipeline.
     * 
     * @param string $path File path to validate
     * @return bool True if appears to be valid image path
     */
    private function isValidImagePath(string $path): bool
    {
        // Check for valid image extensions
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff'];
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        return in_array($extension, $allowed_extensions);
    }
}
