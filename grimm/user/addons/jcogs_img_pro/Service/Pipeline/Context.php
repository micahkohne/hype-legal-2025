<?php

/**
 * JCOGS Image Pro - Pipeline Processing Context
 * =============================================
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

use JCOGSDesign\JCOGSImagePro\Service\CacheKeyGenerator;

/**
 * Pipeline Processing Context Class
 * 
 * Maintains the state and data throughout the image processing pipeline.
 * Acts as a shared data container that stages can read from and update
 * as processing progresses.
 * 
 * Contains:
 * - Input parameters and data
 * - Processing state and flags
 * - Image objects and metadata
 * - Cache information
 * - Performance metrics
 * - Error handling
 */
class Context 
{
    /**
     * @var array Original template tag parameters (immutable)
     */
    private array $original_tag_params;
    
    /**
     * @var array Current working template tag parameters (can be modified during processing)
     */
    private array $tag_params;
    
    /**
     * @var string|null Original template tag data
     */
    private ?string $tag_data;
    
    /**
     * @var string Variable prefix for S4-F3 Tag-based Variable Prefixing
     */
    private string $variable_prefix = '';
    
    /**
     * @var string|null Target connection name from connection parameter
     */
    private ?string $save_to_connection = null;
    
    /**
     * @var CacheKeyGenerator Cache key service
     */
    private CacheKeyGenerator $cache_key_generator;
    
    /**
     * @var string|null Generated cache key
     */
    private ?string $cache_key = null;
    
    /**
     * @var mixed Source image object
     */
    private $source_image = null;
    
    /**
     * @var mixed Processed image object
     */
    private $processed_image = null;
    
    /**
     * @var string Final output for template
     */
    private string $output = '';
    
    /**
     * @var array Processing metadata
     */
    private array $metadata = [];
    
    /**
     * @var array Performance metrics
     */
    private array $performance_metrics = [];
    
    /**
     * @var array Processing flags
     */
    private array $flags = [];
    
    /**
     * @var array Error messages
     */
    private array $errors = [];
    
    /**
     * @var bool Whether pipeline should exit early
     */
    private bool $exit_early = false;
    
    /**
     * Constructor
     * 
     * @param array $tag_params Template tag parameters
     * @param string|null $tag_data Template tag data
     * @param CacheKeyGenerator $cache_key_generator
     */
    public function __construct(array $tag_params, ?string $tag_data, CacheKeyGenerator $cache_key_generator) 
    {
        // Store original tag parameters (immutable for ACT packet generation)
        $this->original_tag_params = [];
        foreach ($tag_params as $key => $value) {
            // Force string conversion for critical parameters to prevent reference sharing
            if ($key === 'cache_dir' || $key === 'src') {
                $this->original_tag_params[$key] = (string) $value;
            } else {
                $this->original_tag_params[$key] = $value;
            }
        }
        
        // Create working copy of parameters for pipeline processing (can be modified)
        $this->tag_params = [];
        foreach ($tag_params as $key => $value) {
            // Force string conversion for critical parameters to prevent reference sharing
            if ($key === 'cache_dir' || $key === 'src') {
                $this->tag_params[$key] = (string) $value;
            } else {
                $this->tag_params[$key] = $value;
            }
        }
        
        // Isolate tag data as well
        $this->tag_data = $tag_data ? (string) $tag_data : null;
        $this->cache_key_generator = $cache_key_generator;
        
        // Process connection parameter for named connections
        $this->save_to_connection = $this->tag_params['connection'] ?? null;
        
        // Initialize variable prefix from tag parts (following Legacy approach)
        $this->_initialize_variable_prefix();
        
        // Initialize processing flags
        $this->flags = [
            'using_cache_copy' => false,
            'svg' => false,
            'animated_gif' => false,
            'its_a_crop' => false,
            'use_colour_fill' => false
        ];
    }
    
    // =============================================================================
    // Parameter Access Methods
    // =============================================================================
    
    /**
     * Get template tag parameters
     * 
     * @return array
     */
    public function get_tag_params(): array 
    {
        return $this->tag_params;
    }
    
    /**
     * Get original template tag parameters (immutable, for ACT packet generation)
     * 
     * @return array
     */
    public function get_original_tag_params(): array 
    {
        return $this->original_tag_params;
    }
    
    /**
     * Get template tag data
     * 
     * @return string|null
     */
    public function get_tag_data(): ?string 
    {
        return $this->tag_data;
    }
    
    /**
     * Get specific parameter value
     * 
     * @param string $key Parameter name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public function get_param(string $key, $default = null) 
    {
        return $this->tag_params[$key] ?? $default;
    }
    
    /**
     * Set parameter value
     * 
     * @param string $key Parameter name
     * @param mixed $value Parameter value
     */
    public function set_param(string $key, $value): void 
    {
        $this->tag_params[$key] = $value;
    }
    
    /**
     * Get connection name for named connections
     * 
     * @return string|null Connection name or null if not specified
     */
    public function get_save_to_connection(): ?string 
    {
        return $this->save_to_connection;
    }
    
    /**
     * Set connection name for named connections
     * 
     * @param string|null $connection_name Connection name to save to
     */
    public function set_save_to_connection(?string $connection_name): void 
    {
        $this->save_to_connection = $connection_name;
    }
    
    // =============================================================================
    // Cache Key Methods
    // =============================================================================
    
    /**
     * Generate and get cache key
     * 
     * @return string
     */
    public function get_cache_key(): string 
    {
        if ($this->cache_key === null) {
            // Need filename from src parameter to generate cache key
            $filename = $this->get_param('src', 'unknown');
            $this->cache_key = $this->cache_key_generator->generate_cache_key($filename, $this->tag_params);
        }
        return $this->cache_key;
    }
    
    /**
     * Set cache key manually
     * 
     * @param string $cache_key
     */
    public function set_cache_key(string $cache_key): void 
    {
        $this->cache_key = $cache_key;
    }
    
    // =============================================================================
    // Image Object Methods
    // =============================================================================
    
    /**
     * Get source image object
     * 
     * @return mixed
     */
    public function get_source_image() 
    {
        return $this->source_image;
    }
    
    /**
     * Set source image object
     * 
     * @param mixed $image
     */
    public function set_source_image($image): void 
    {
        $this->source_image = $image;
    }
    
    /**
     * Get processed image object
     * 
     * @return mixed
     */
    public function get_processed_image() 
    {
        return $this->processed_image;
    }
    
    /**
     * Set processed image object
     * 
     * @param mixed $image
     */
    public function set_processed_image($image): void 
    {
        $this->processed_image = $image;
    }
    
    // =============================================================================
    // Output Methods
    // =============================================================================
    
    /**
     * Get final output
     * 
     * @return string
     */
    public function get_output(): string 
    {
        return $this->output;
    }
    
    /**
     * Set final output
     * 
     * @param string $output
     */
    public function set_output(string $output): void 
    {
        $this->output = $output;
    }
    
    /**
     * Append to output
     * 
     * @param string $content
     */
    public function append_output(string $content): void 
    {
        $this->output .= $content;
    }
    
    // =============================================================================
    // Metadata Methods
    // =============================================================================
    
    /**
     * Get all metadata
     * 
     * @return array
     */
    public function get_metadata(): array 
    {
        return $this->metadata;
    }
    
    /**
     * Get specific metadata value
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get_metadata_value(string $key, $default = null) 
    {
        return $this->metadata[$key] ?? $default;
    }
    
    /**
     * Set metadata value
     * 
     * @param string $key
     * @param mixed $value
     */
    public function set_metadata(string $key, $value): void 
    {
        $this->metadata[$key] = $value;
    }
    
    // =============================================================================
    // Cache Result Caching Methods
    // =============================================================================
    
    /**
     * Get cached cache check result to avoid redundant database/filesystem operations
     * 
     * @param string $cache_key Unique identifier for the cache check (e.g., image_path + duration)
     * @return mixed|null Cached result or null if not cached
     */
    public function get_cached_cache_result(string $cache_key)
    {
        return $this->get_metadata_value("cache_result_{$cache_key}", null);
    }
    
    /**
     * Store cache check result to avoid redundant operations
     * 
     * @param string $cache_key Unique identifier for the cache check
     * @param mixed $result Result to cache (boolean, array, etc.)
     */
    public function set_cached_cache_result(string $cache_key, $result): void
    {
        $this->set_metadata("cache_result_{$cache_key}", $result);
    }
    
    /**
     * Get cached freshness validation result
     * 
     * @param string $freshness_key Unique identifier for freshness check
     * @return array|null Cached freshness result or null if not cached
     */
    public function get_cached_freshness_result(string $freshness_key): ?array
    {
        return $this->get_metadata_value("freshness_result_{$freshness_key}", null);
    }
    
    /**
     * Store freshness validation result to avoid redundant operations
     * 
     * @param string $freshness_key Unique identifier for freshness check
     * @param array $result Freshness validation result (expired, inception_time, etc.)
     */
    public function set_cached_freshness_result(string $freshness_key, array $result): void
    {
        $this->set_metadata("freshness_result_{$freshness_key}", $result);
    }
    
    // =============================================================================
    // Performance Methods
    // =============================================================================
    
    /**
     * Add performance metric
     * 
     * @param string $metric_name
     * @param float $time_seconds
     */
    public function add_performance_metric(string $metric_name, float $time_seconds): void 
    {
        $this->performance_metrics[$metric_name] = $time_seconds;
    }
    
    /**
     * Get performance metrics
     * 
     * @return array
     */
    public function get_performance_metrics(): array 
    {
        return $this->performance_metrics;
    }
    
    // =============================================================================
    // Flag Methods
    // =============================================================================
    
    /**
     * Get flag value
     * 
     * @param string $flag
     * @param bool $default
     * @return bool
     */
    public function get_flag(string $flag, bool $default = false): bool 
    {
        return $this->flags[$flag] ?? $default;
    }
    
    /**
     * Set flag value
     * 
     * @param string $flag
     * @param bool $value
     */
    public function set_flag(string $flag, bool $value): void 
    {
        $this->flags[$flag] = $value;
    }
    
    /**
     * Get all flags
     * 
     * @return array
     */
    public function get_flags(): array 
    {
        return $this->flags;
    }
    
    // =============================================================================
    // Error Handling Methods
    // =============================================================================
    
    /**
     * Add error message
     * 
     * @param string $message
     * @param bool $critical Whether this is a critical error
     */
    public function add_error(string $message, bool $critical = false): void 
    {
        $this->errors[] = [
            'message' => $message,
            'critical' => $critical,
            'timestamp' => microtime(true)
        ];
    }
    
    /**
     * Check if context has any errors
     * 
     * @return bool
     */
    public function has_errors(): bool 
    {
        return !empty($this->errors);
    }
    
    /**
     * Check if context has critical errors
     * 
     * @return bool
     */
    public function has_critical_error(): bool 
    {
        foreach ($this->errors as $error) {
            if ($error['critical']) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get error messages
     * 
     * @param bool $critical_only Only return critical errors
     * @return array
     */
    public function get_errors(bool $critical_only = false): array 
    {
        if (!$critical_only) {
            return $this->errors;
        }
        
        return array_filter($this->errors, fn($error) => $error['critical']);
    }
    
    /**
     * Get first error message
     * 
     * @return string
     */
    public function get_error_message(): string 
    {
        if (empty($this->errors)) {
            return '';
        }
        
        return $this->errors[0]['message'];
    }
    
    // =============================================================================
    // Flow Control Methods
    // =============================================================================
    
    /**
     * Check if pipeline should exit early
     * 
     * @return bool
     */
    public function should_exit_early(): bool 
    {
        return $this->exit_early;
    }
    
    /**
     * Set early exit flag
     * 
     * @param bool $exit
     * @param string $reason Optional reason for logging
     */
    public function set_exit_early(bool $exit = true, string $reason = ''): void 
    {
        $this->exit_early = $exit;
        if ($exit && $reason) {
            $this->set_metadata('exit_reason', $reason);
        }
    }
    
    /**
     * Get variable prefix for S4-F3 Tag-based Variable Prefixing
     * 
     * @return string Variable prefix (e.g., 'cats:' for {exp:jcogs_img_pro:image:cats})
     */
    public function get_variable_prefix(): string
    {
        return $this->variable_prefix;
    }
    
    /**
     * Set variable prefix manually (for testing or special cases)
     * 
     * @param string $prefix Variable prefix
     */
    public function set_variable_prefix(string $prefix): void
    {
        $this->variable_prefix = $prefix;
    }
    
    /**
     * Apply variable prefix to a variable name
     * 
     * @param string $variable_name Base variable name (e.g., 'made', 'width')
     * @return string Prefixed variable name (e.g., 'cats:made', 'cats:width')
     */
    public function apply_variable_prefix(string $variable_name): string
    {
        if (empty($this->variable_prefix)) {
            return $variable_name;
        }
        
        return $this->variable_prefix . $variable_name;
    }
    
    /**
     * Initialize variable prefix from EE template tag parts
     * Following Legacy approach from JcogsImage.php lines 128-139
     * 
     * For tag: {exp:jcogs_img_pro:image:cats}
     * The prefix becomes 'cats:'
     */
    private function _initialize_variable_prefix(): void
    {
        // Check for explicit var_prefix parameter first
        if (isset($this->tag_params['var_prefix'])) {
            $trimmed_prefix = trim($this->tag_params['var_prefix']);
            if (!empty($trimmed_prefix)) {
                $this->variable_prefix = $trimmed_prefix . ':';
            } else {
                $this->variable_prefix = ':';
            }
            return;
        }
        
        // Try to get prefix from EE template tag parts (following Legacy pattern)
        if (!defined('JCOGS_IMG_PRO_TESTING') && !empty(ee()) && isset(ee()->TMPL) && !empty(ee()->TMPL)) {
            try {
                $tag_parts = ee()->TMPL->tagparts ?? null;
                if (is_array($tag_parts) && isset($tag_parts[2]) && !empty($tag_parts[2])) {
                    $this->variable_prefix = $tag_parts[2] . ':';
                }
            } catch (\Exception $e) {
                // Silently fail - variable prefix is optional
                $this->variable_prefix = '';
            }
        }
        
        // Default to empty prefix if nothing found
        if (!isset($this->variable_prefix)) {
            $this->variable_prefix = '';
        }
    }
}
