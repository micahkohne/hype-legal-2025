<?php

/**
 * JCOGS Image Pro - Performance Service
 * Phase 2: EE benchmark integration for performance tracking
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.1-alpha1
 * @link       https://JCOGS.net/
 * @since      Phase 2 Native Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service;

use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

/**
 * Performance Service for JCOGS Image Pro
 * 
 * Provides EE benchmark integration for tracking image processing performance.
 * Tracks instance counts, timing, and context for comprehensive performance reporting
 * in EE's Debug Panel Performance tab.
 * 
 * Uses EE's native elapsed_time() method for precise timing calculations that are
 * consistent with EE's core benchmarking system.
 * 
 * @package JCOGSImagePro\Service
 * @author JCOGS Design
 * @version 2.0.1 (Phase 2A)
 * @since Pro 2.0.0
 */
class PerformanceService
{
    /**
     * Global instance counter across all tag types
     * Maintains compatibility with Legacy counting system
     */
    private static int $instance_count = 0;
    
    /**
     * Track active benchmarks to ensure proper cleanup
     * Format: [instance_id => ['tag_context' => string, 'start_marker' => string]]
     */
    private static array $active_benchmarks = [];
    
    /**
     * EE benchmark naming prefix for Pro addon
     */
    private const BENCHMARK_PREFIX = 'JCOGS_Image_Pro_';
    
    /**
     * Direct service access for utilities
     */
    private $utilities;
    
    /**
     * Initialize Performance Service with ServiceCache
     */
    public function __construct()
    {
        $this->utilities = ServiceCache::utilities();
        
        // Ensure EE benchmark library is loaded
        if (!class_exists('CI_Benchmark')) {
            ee()->load->library('benchmark');
        }
    }
    
    /**
     * Force cleanup of any remaining active benchmarks
     * Called during shutdown or error conditions
     * 
     * @return array List of cleaned up benchmark instance IDs
     */
    public function cleanup_active_benchmarks(): array
    {
        $cleaned_ids = [];
        
        foreach (self::$active_benchmarks as $instance_id => $benchmark_data) {
            $this->end_benchmark_with_error($instance_id, 'cleanup');
            $cleaned_ids[] = $instance_id;
        }
        
        return $cleaned_ids;
    }
    
    /**
     * End benchmark timer for image processing
     * Completes the EE benchmark timing pair using EE's native elapsed_time()
     * 
     * @param int $instance_id Instance ID returned from start_benchmark()
     * @param string|null $tag_context Optional context override
     * @return float|null Duration in seconds, or null if benchmark not found
     */
    public function end_benchmark(int $instance_id, ?string $tag_context = null): ?float
    {
        if (!isset(self::$active_benchmarks[$instance_id])) {
            // Benchmark not found - may have been ended already or never started
            $this->utilities->debug_log("Warning: Attempted to end benchmark for instance #{$instance_id} which was not found");
            return null;
        }
        
        $benchmark_data = self::$active_benchmarks[$instance_id];
        $effective_context = $tag_context ?: $benchmark_data['tag_context'];
        
        // Create EE benchmark end marker
        $end_marker = sprintf('%s(%s)_#%d_end', self::BENCHMARK_PREFIX, $effective_context, $instance_id);
        ee()->benchmark->mark($end_marker);
        
        // Calculate duration using EE's native elapsed_time() method
        $duration = (float) ee()->benchmark->elapsed_time($benchmark_data['start_marker'], $end_marker, 6);
        
        // Clean up tracking
        unset(self::$active_benchmarks[$instance_id]);
        
        return $duration;
    }
    
    /**
     * End benchmark with error context
     * Special handling for failed image processing
     * 
     * @param int $instance_id Instance ID to end
     * @param string $error_context Error description
     * @return float|null Duration in seconds
     */
    public function end_benchmark_with_error(int $instance_id, string $error_context = 'error'): ?float
    {
        if (!isset(self::$active_benchmarks[$instance_id])) {
            return null;
        }
        
        $benchmark_data = self::$active_benchmarks[$instance_id];
        $tag_context = $benchmark_data['tag_context'] . '_ERROR';
        
        return $this->end_benchmark($instance_id, $tag_context);
    }
    
    /**
     * Get current instance count without incrementing
     * 
     * @return int Current instance count
     */
    public static function get_current_instance_count(): int
    {
        return self::$instance_count;
    }
    
    /**
     * Get elapsed time since benchmark started (without ending it)
     * Uses EE's native elapsed_time() method with temporary marker
     * 
     * @param int $instance_id Instance ID from start_benchmark()
     * @return float|null Elapsed time in seconds, or null if benchmark not found
     */
    public function get_elapsed_time(int $instance_id): ?float
    {
        if (!isset(self::$active_benchmarks[$instance_id])) {
            return null;
        }
        
        // Create temporary marker to calculate elapsed time without ending benchmark
        $temp_marker = "temp_elapsed_check_{$instance_id}_" . uniqid();
        ee()->benchmark->mark($temp_marker);
        
        $benchmark_data = self::$active_benchmarks[$instance_id];
        return (float) ee()->benchmark->elapsed_time($benchmark_data['start_marker'], $temp_marker, 6);
    }
    
    /**
     * Get formatted elapsed time report with color coding
     * Based on Legacy _get_elapsed_time_report() functionality
     * 
     * @param float $elapsed_time Elapsed time in seconds
     * @return string Formatted HTML string with CSS color coding
     */
    public function get_elapsed_time_report(float $elapsed_time): string
    {
        if ($elapsed_time > 2) {
            return sprintf('<span style="color:var(--ee-error-dark);font-weight:bold">Processing time: %.4f seconds</span>', $elapsed_time);
        } elseif ($elapsed_time > 1) {
            return sprintf('<span style="color:var(--ee-warning-dark);font-weight:bold">Processing time: %.4f seconds</span>', $elapsed_time);
        } else {
            return sprintf('<span style="color:var(--ee-button-success-hover-bg);font-weight:bold">Processing time: %.4f seconds</span>', $elapsed_time);
        }
    }
    
    /**
     * Get next instance ID and increment counter
     * Thread-safe increment for accurate instance tracking
     * 
     * @return int Next instance ID
     */
    public static function get_next_instance_id(): int
    {
        return ++self::$instance_count;
    }
    
    /**
     * Get performance statistics
     * 
     * @return array Performance metrics
     */
    public function get_performance_stats(): array
    {
        return [
            'total_instances' => self::$instance_count,
            'active_benchmarks' => count(self::$active_benchmarks),
            'active_instance_ids' => array_keys(self::$active_benchmarks)
        ];
    }
    
    /**
     * Reset instance counter (for testing purposes)
     * 
     * @return void
     */
    public static function reset_instance_counter(): void
    {
        self::$instance_count = 0;
        self::$active_benchmarks = [];
    }
    
    /**
     * Start benchmark timer for image processing
     * Creates EE benchmark marker that will appear in Debug Panel
     * 
     * @param string $tag_context Context identifier (Image_Tag, Single_Tag, etc.)
     * @param int|null $instance_id Optional instance ID, will generate if not provided
     * @return int Instance ID for ending the benchmark
     */
    public function start_benchmark(string $tag_context, ?int $instance_id = null): int
    {
        $instance_id = $instance_id ?: self::get_next_instance_id();
        
        // Create EE benchmark marker
        $benchmark_name = sprintf('%s(%s)_#%d_start', self::BENCHMARK_PREFIX, $tag_context, $instance_id);
        ee()->benchmark->mark($benchmark_name);
        
        // Track active benchmark for cleanup
        self::$active_benchmarks[$instance_id] = [
            'tag_context' => $tag_context,
            'start_marker' => $benchmark_name
        ];
        
        return $instance_id;
    }
    
    /**
     * Destructor ensures cleanup of any remaining benchmarks
     */
    public function __destruct()
    {
        if (!empty(self::$active_benchmarks)) {
            $this->cleanup_active_benchmarks();
        }
    }
}
