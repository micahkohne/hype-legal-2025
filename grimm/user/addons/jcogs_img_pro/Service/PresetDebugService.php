<?php

/**
 * JCOGS Image Pro - Preset Debugging Service
 * ===========================================
 * Comprehensive debugging and monitoring for preset operations
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 7 Preset Debugging Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service;

use JCOGSDesign\JCOGSImagePro\Contracts\SettingsInterface;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;
use Exception;

/**
 * Preset Debugging Service
 * 
 * Provides comprehensive debugging and monitoring capabilities for preset operations:
 * - Detailed preset resolution logging
 * - Performance tracking and analysis
 * - Parameter merge tracking
 * - Error tracking and reporting
 * - Debug output formatting for developers
 * 
 * Integrates with Pro's existing Utilities debug system while adding preset-specific
 * debugging capabilities for troubleshooting preset issues.
 */
class PresetDebugService 
{
    /**
     * @var Utilities Pro utilities service for logging
     */
    private Utilities $utilities;
    
    /**
     * @var SettingsInterface Pro settings service
     */
    private SettingsInterface $settings;
    
    /**
     * @var array Active debug sessions for tracking multi-step operations
     */
    private array $debug_sessions = [];
    
    /**
     * @var bool Whether preset debugging is enabled
     */
    private bool $debug_enabled = false;
    
    /**
     * Constructor
     * 
     * Initialize debugging service with utilities and settings
     */
    public function __construct() 
    {
        try {
            $this->utilities = ServiceCache::utilities();
            $this->settings = ServiceCache::settings();
            
            // Check if preset debugging is enabled via settings
            $this->debug_enabled = $this->settings->get('preset_debug_enabled', false);
        } catch (Exception $e) {
            // Fallback if services not available
            $this->debug_enabled = false;
        }
    }
    
    /**
     * Start a debug session for tracking preset resolution
     * 
     * @param string $preset_name Preset being resolved
     * @param array $tag_parameters Original tag parameters
     * @return string Session ID for tracking
     */
    public function startPresetResolution(string $preset_name, array $tag_parameters): string
    {
        if (!$this->debug_enabled) {
            return '';
        }
        
        $session_id = uniqid('preset_debug_', true);
        
        $this->debug_sessions[$session_id] = [
            'preset_name' => $preset_name,
            'start_time' => microtime(true),
            'tag_parameters' => $tag_parameters,
            'steps' => [],
            'errors' => [],
            'performance' => []
        ];
        
        $this->utilities->debug_log(
            'preset_resolution_start',
            $preset_name,
            count($tag_parameters),
            $session_id
        );
        
        return $session_id;
    }
    
    /**
     * Log a step in the preset resolution process
     * 
     * @param string $session_id Debug session ID
     * @param string $step_name Name of the step
     * @param array $data Step data for logging
     */
    public function logResolutionStep(string $session_id, string $step_name, array $data = []): void
    {
        if (!$this->debug_enabled || !isset($this->debug_sessions[$session_id])) {
            return;
        }
        
        $step_data = [
            'step' => $step_name,
            'timestamp' => microtime(true),
            'data' => $data
        ];
        
        $this->debug_sessions[$session_id]['steps'][] = $step_data;
        
        $this->utilities->debug_log(
            'preset_resolution_step',
            $session_id,
            $step_name,
            $data
        );
    }
    
    /**
     * Log parameter merging details
     * 
     * @param string $session_id Debug session ID
     * @param array $preset_parameters Parameters from preset
     * @param array $tag_parameters Parameters from tag
     * @param array $merged_parameters Final merged parameters
     */
    public function logParameterMerge(
        string $session_id, 
        array $preset_parameters, 
        array $tag_parameters, 
        array $merged_parameters
    ): void {
        if (!$this->debug_enabled || !isset($this->debug_sessions[$session_id])) {
            return;
        }
        
        $merge_analysis = [
            'preset_param_count' => count($preset_parameters),
            'tag_param_count' => count($tag_parameters),
            'merged_param_count' => count($merged_parameters),
            'overridden_params' => array_keys(array_intersect_key($preset_parameters, $tag_parameters)),
            'preset_only_params' => array_keys(array_diff_key($preset_parameters, $tag_parameters)),
            'tag_only_params' => array_keys(array_diff_key($tag_parameters, $preset_parameters))
        ];
        
        $this->logResolutionStep($session_id, 'parameter_merge', $merge_analysis);
        
        // Detailed parameter logging for verbose debugging
        if ($this->isVerboseDebugEnabled()) {
            $this->utilities->debug_log('preset_parameters_detail', $session_id, $preset_parameters);
            $this->utilities->debug_log('tag_parameters_detail', $session_id, $tag_parameters);
            $this->utilities->debug_log('merged_parameters_detail', $session_id, $merged_parameters);
        }
    }
    
    /**
     * Log validation results
     * 
     * @param string $session_id Debug session ID
     * @param array $validation_errors Validation errors found
     * @param bool $validation_passed Whether validation passed
     */
    public function logValidationResult(string $session_id, array $validation_errors, bool $validation_passed): void
    {
        if (!$this->debug_enabled || !isset($this->debug_sessions[$session_id])) {
            return;
        }
        
        $validation_data = [
            'passed' => $validation_passed,
            'error_count' => count($validation_errors),
            'errors' => $validation_errors
        ];
        
        $this->logResolutionStep($session_id, 'validation', $validation_data);
        
        if (!$validation_passed) {
            $this->debug_sessions[$session_id]['errors'][] = [
                'type' => 'validation_error',
                'errors' => $validation_errors,
                'timestamp' => microtime(true)
            ];
        }
    }
    
    /**
     * Complete a debug session and generate summary
     * 
     * @param string $session_id Debug session ID
     * @param bool $success Whether resolution was successful
     * @param array $final_parameters Final resolved parameters
     */
    public function completePresetResolution(string $session_id, bool $success, array $final_parameters = []): void
    {
        if (!$this->debug_enabled || !isset($this->debug_sessions[$session_id])) {
            return;
        }
        
        $session = &$this->debug_sessions[$session_id];
        $session['end_time'] = microtime(true);
        $session['duration'] = $session['end_time'] - $session['start_time'];
        $session['success'] = $success;
        $session['final_parameters'] = $final_parameters;
        
        // Log completion
        $this->utilities->debug_log(
            'preset_resolution_complete',
            $session_id,
            $session['preset_name'],
            $success,
            $session['duration'],
            count($session['steps']),
            count($session['errors'])
        );
        
        // Generate detailed summary if verbose debugging enabled
        if ($this->isVerboseDebugEnabled()) {
            $this->generateDebugSummary($session_id);
        }
        
        // Clean up session after a delay to allow for summary generation
        unset($this->debug_sessions[$session_id]);
    }
    
    /**
     * Log cache key generation for preset
     * 
     * @param string $preset_name Preset name
     * @param string $cache_key Generated cache key
     * @param array $parameters Parameters used in cache key
     */
    public function logCacheKeyGeneration(string $preset_name, string $cache_key, array $parameters): void
    {
        if (!$this->debug_enabled) {
            return;
        }
        
        $this->utilities->debug_log(
            'preset_cache_key_generated',
            $preset_name,
            $cache_key,
            array_keys($parameters)
        );
    }
    
    /**
     * Log preset performance metrics
     * 
     * @param array $performance_stats Performance statistics from PresetResolver
     */
    public function logPerformanceMetrics(array $performance_stats): void
    {
        if (!$this->debug_enabled) {
            return;
        }
        
        $this->utilities->debug_log('preset_performance_metrics', $performance_stats);
        
        // Calculate derived metrics
        if ($performance_stats['resolutions'] > 0) {
            $avg_time = $performance_stats['total_time'] / $performance_stats['resolutions'];
            
            // Calculate cache hit rate, avoiding division by zero
            $total_cache_operations = $performance_stats['cache_hits'] + $performance_stats['cache_misses'];
            $cache_hit_rate = $total_cache_operations > 0 
                ? $performance_stats['cache_hits'] / $total_cache_operations
                : 0.0;
            
            $this->utilities->debug_log(
                'preset_performance_analysis',
                [
                    'average_resolution_time' => $avg_time,
                    'cache_hit_rate' => $cache_hit_rate,
                    'total_operations' => $performance_stats['resolutions'],
                    'total_cache_operations' => $total_cache_operations
                ]
            );
        }
    }
    
    /**
     * Log preset error for debugging
     * 
     * @param string $error_type Type of error
     * @param string $preset_name Preset name involved
     * @param Exception|string $error Error details
     * @param array $context Additional context
     */
    public function logPresetError(string $error_type, string $preset_name, $error, array $context = []): void
    {
        if (!$this->debug_enabled) {
            return;
        }
        
        $error_details = [
            'type' => $error_type,
            'preset' => $preset_name,
            'context' => $context
        ];
        
        if ($error instanceof Exception) {
            $error_details['message'] = $error->getMessage();
            $error_details['file'] = $error->getFile();
            $error_details['line'] = $error->getLine();
        } else {
            $error_details['message'] = (string) $error;
        }
        
        $this->utilities->debug_log('preset_error', $error_details);
    }
    
    /**
     * Generate comprehensive debug summary for a session
     * 
     * @param string $session_id Debug session ID
     */
    private function generateDebugSummary(string $session_id): void
    {
        if (!isset($this->debug_sessions[$session_id])) {
            return;
        }
        
        $session = $this->debug_sessions[$session_id];
        
        $summary = [
            'session_id' => $session_id,
            'preset_name' => $session['preset_name'],
            'duration_ms' => round($session['duration'] * 1000, 2),
            'success' => $session['success'],
            'step_count' => count($session['steps']),
            'error_count' => count($session['errors']),
            'original_params' => count($session['tag_parameters']),
            'final_params' => count($session['final_parameters'] ?? [])
        ];
        
        // Add step timeline
        $summary['timeline'] = [];
        foreach ($session['steps'] as $step) {
            $step_time = round(($step['timestamp'] - $session['start_time']) * 1000, 2);
            $summary['timeline'][] = [
                'step' => $step['step'],
                'time_ms' => $step_time
            ];
        }
        
        $this->utilities->debug_log('preset_resolution_summary', $summary);
    }
    
    /**
     * Check if verbose debugging is enabled
     * 
     * @return bool True if verbose debugging enabled
     */
    private function isVerboseDebugEnabled(): bool
    {
        return $this->settings->get('preset_debug_verbose', false);
    }
    
    /**
     * Get current debug statistics
     * 
     * @return array Debug statistics
     */
    public function getDebugStatistics(): array
    {
        return [
            'debug_enabled' => $this->debug_enabled,
            'verbose_enabled' => $this->isVerboseDebugEnabled(),
            'active_sessions' => count($this->debug_sessions),
            'session_ids' => array_keys($this->debug_sessions)
        ];
    }
}
