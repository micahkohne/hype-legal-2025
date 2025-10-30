<?php

/**
 * JCOGS Image Pro - Instrumented Transformation
 * ============================================
 * Wrapper around Imagine\Filter\Transformation that adds performance timing for individual filters
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-alpha-3
 * @link       https://JCOGS.net/
 * @since      File available since Release 2.0.0-alpha-3
 */

namespace JCOGSDesign\JCOGSImagePro\Service\Pipeline;

use Imagine\Filter\Transformation;
use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;

/**
 * Performance-instrumented transformation wrapper class
 */
class InstrumentedTransformation implements FilterInterface
{
    /**
     * @var \Imagine\Filter\Transformation
     */
    private $transformation;
    
    /**
     * @var mixed UtilitiesService instance
     */
    private $utilities_service;
    
    /**
     * @var array Filter timing data
     */
    private $filter_timings = [];
    
    /**
     * @var int Filter execution counter
     */
    private $filter_counter = 0;

    /**
     * Class constructor
     *
     * @param \Imagine\Image\ImagineInterface|null $imagine An ImagineInterface instance
     * @param mixed $utilities_service
     */
    public function __construct(?ImagineInterface $imagine = null, $utilities_service = null)
    {
        $this->transformation = new Transformation($imagine);
        $this->utilities_service = $utilities_service;
    }

    /**
     * Add a filter to the transformation
     *
     * @param \Imagine\Filter\FilterInterface $filter
     * @param int $priority
     * @return $this
     */
    public function add(FilterInterface $filter, $priority = 0)
    {
        $this->transformation->add($filter, $priority);
        return $this;
    }

    /**
     * Get filters from the underlying transformation
     *
     * @return array
     */
    public function getFilters()
    {
        return $this->transformation->getFilters();
    }

    /**
     * Apply filter with timing instrumentation
     *
     * @param \Imagine\Image\ImageInterface $image
     * @param \Imagine\Filter\FilterInterface $filter
     *
     * @return \Imagine\Image\ImageInterface
     */
    public function applyFilter(ImageInterface $image, FilterInterface $filter)
    {
        $filter_start_time = microtime(true);
        $this->filter_counter++;
        
        // Get filter class name for identification
        $filter_class = get_class($filter);
        $filter_name = $this->getFilterDisplayName($filter_class);
        
        // Debug message for filter start
        if ($this->utilities_service) {
            $this->utilities_service->debug_log("Filter #{$this->filter_counter} starting: {$filter_name}");
        }
        
        // Execute the filter using transformation's applyFilter method
        $result = $this->transformation->applyFilter($image, $filter);
        
        // Calculate timing
        $filter_execution_time = microtime(true) - $filter_start_time;
        
        // Store timing data
        $this->filter_timings[] = [
            'filter_number' => $this->filter_counter,
            'filter_name' => $filter_name,
            'filter_class' => $filter_class,
            'execution_time' => $filter_execution_time
        ];
        
        // Debug message for filter completion
        if ($this->utilities_service) {
            $this->utilities_service->debug_log(sprintf(
                "Filter #%d completed: %s (%.4fs)",
                $this->filter_counter,
                $filter_name,
                $filter_execution_time
            ));
        }
        
        return $result;
    }
    
    /**
     * Apply transformation with performance instrumentation
     *
     * @param \Imagine\Image\ImageInterface $image
     *
     * @return \Imagine\Image\ImageInterface
     */
    public function apply(ImageInterface $image)
    {
        $transformation_start_time = microtime(true);
        $this->filter_timings = []; // Reset timing data
        $this->filter_counter = 0;   // Reset counter
        
        if ($this->utilities_service) {
            $filter_count = count($this->getFilters());
            $this->utilities_service->debug_log("=== Starting Transformation Sequence: {$filter_count} filters ===");
        }
        
        // Execute transformation manually with timing for each filter
        $result = array_reduce(
            $this->getFilters(),
            array($this, 'applyFilter'),
            $image
        );
        
        // Calculate total transformation time
        $total_transformation_time = microtime(true) - $transformation_start_time;
        
        // Generate performance summary
        $this->generatePerformanceSummary($total_transformation_time);
        
        return $result;
    }
    
    /**
     * Generate and log performance summary
     *
     * @param float $total_time Total transformation time
     */
    private function generatePerformanceSummary($total_time)
    {
        if (!$this->utilities_service || empty($this->filter_timings)) {
            return;
        }

        $this->utilities_service->debug_log("=== Filter Performance Summary ===");

        // Sort filters by execution time (descending)
        $sorted_timings = $this->filter_timings;
        usort($sorted_timings, function($a, $b) {
            return $b['execution_time'] <=> $a['execution_time'];
        });
        
        // Display individual filter timings
        foreach ($sorted_timings as $timing) {
            $percentage = ($timing['execution_time'] / $total_time) * 100;
            $this->utilities_service->debug_log(sprintf(
                "  Filter #%d: %s - %.4fs (%.1f%%)",
                $timing['filter_number'],
                str_pad($timing['filter_name'], 25),
                $timing['execution_time'],
                $percentage
            ));
        }
        
        // Display total
        $this->utilities_service->debug_log(sprintf(
            "  %s: %.4fs",
            str_pad("TOTAL FILTERS", 35),
            $total_time
        ));
        
        // Identify slowest filter
        if (!empty($sorted_timings)) {
            $slowest = $sorted_timings[0];
            $slowest_percentage = ($slowest['execution_time'] / $total_time) * 100;
            $this->utilities_service->debug_log(sprintf(
                "Slowest filter: %s (%.1f%% of transformation time)",
                $slowest['filter_name'],
                $slowest_percentage
            ));
        }

        $this->utilities_service->debug_log("=== End Filter Performance Summary ===");
    }
    
    /**
     * Convert filter class name to display name
     *
     * @param string $filter_class
     * @return string
     */
    private function getFilterDisplayName($filter_class)
    {
        // Remove namespace and get base class name
        $base_name = basename(str_replace('\\', '/', $filter_class));
        
        // Special handling for JCOGS filters
        if (strpos($filter_class, 'JCOGSDesign\\') !== false) {
            if (strpos($base_name, 'Reflection_') !== false) {
                return $base_name; // Keep reflection_fast/reflection_slow distinction
            }
            return "JCOGS_{$base_name}";
        }
        
        // Special handling for Imagine built-in filters
        if (strpos($filter_class, 'Imagine\\Filter\\') !== false) {
            return "Imagine_{$base_name}";
        }
        
        return $base_name;
    }
    
    /**
     * Get filter timing data for analysis
     *
     * @return array
     */
    public function getFilterTimings()
    {
        return $this->filter_timings;
    }
}
