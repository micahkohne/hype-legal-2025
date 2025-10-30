<?php

/**
 * JCOGS Image Pro - Process Image Pipeline Stage
 * ==============================================
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

use JCOGSDesign\JCOGSImagePro\Filters\Blur;
use JCOGSDesign\JCOGSImagePro\Filters\Brightness;
use JCOGSDesign\JCOGSImagePro\Filters\Contrast;
use JCOGSDesign\JCOGSImagePro\Filters\Flip;
use JCOGSDesign\JCOGSImagePro\Filters\Greyscale;
use JCOGSDesign\JCOGSImagePro\Filters\Colorize;
use JCOGSDesign\JCOGSImagePro\Filters\Negate;
use JCOGSDesign\JCOGSImagePro\Filters\Opacity;
use JCOGSDesign\JCOGSImagePro\Filters\Sharpen;
use JCOGSDesign\JCOGSImagePro\Filters\AutoSharpen;
use JCOGSDesign\JCOGSImagePro\Filters\TextOverlay;
use JCOGSDesign\JCOGSImagePro\Filters\Watermark;
use JCOGSDesign\JCOGSImagePro\Filters\RoundedCorners;
use JCOGSDesign\JCOGSImagePro\Filters\Reflection;
use JCOGSDesign\JCOGSImagePro\Filters\Rotate;
use JCOGSDesign\JCOGSImagePro\Filters\Edgedetect;
use JCOGSDesign\JCOGSImagePro\Filters\Emboss;
use JCOGSDesign\JCOGSImagePro\Filters\MeanRemoval;
use JCOGSDesign\JCOGSImagePro\Filters\Noise;
use JCOGSDesign\JCOGSImagePro\Filters\Scatter;
use JCOGSDesign\JCOGSImagePro\Filters\SelectiveBlur;
use JCOGSDesign\JCOGSImagePro\Filters\Sepia;
use JCOGSDesign\JCOGSImagePro\Filters\Smooth;
use JCOGSDesign\JCOGSImagePro\Filters\SobelEdgify;
use JCOGSDesign\JCOGSImagePro\Filters\Pixelate;
use JCOGSDesign\JCOGSImagePro\Filters\DominantColor;
use JCOGSDesign\JCOGSImagePro\Filters\Dot;
use JCOGSDesign\JCOGSImagePro\Filters\FaceDetect;
use JCOGSDesign\JCOGSImagePro\Filters\Lqip;
use JCOGSDesign\JCOGSImagePro\Filters\Mask;
use JCOGSDesign\JCOGSImagePro\Filters\ReplaceColors;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Filter\Transformation;
use JCOGSDesign\JCOGSImagePro\Service\Pipeline\InstrumentedTransformation;
use \Imagine\Filter\Basic\Resize;

/**
 * Process Image Pipeline Stage
 * 
 * Third stage of the processing pipeline. Applies transformations to the
 * source image based on the provided parameters.
 * 
 * Responsibilities:
 * - Calculate target dimensions
 * - Resize or crop image as needed
 * - Apply filters and effects
 * - Handle special processing modes
 * - Prepare processed image for caching
 */
class ProcessImageStage extends AbstractStage 
{
    /**
     * @var Context|null Cached context for performance - eliminates parameter passing overhead
     */
    private ?Context $context = null;
    
    /**
     * Constructor
     */
    public function __construct() 
    {
        parent::__construct('process_image');
    }
    
    /**
     * Process image transformation stage
     * 
     * @param Context $this->context Processing context
     * @throws \Exception If processing fails
     */
    protected function process(Context $context): void 
    {
        $process_start_time = microtime(true);
        
        // Cache context as class property for performance optimization
        $this->context = $context;
        
        try {
            $this->processInternal();
        } finally {
            // Clean up context reference to prevent state pollution
            $this->context = null;
        }
    }
    
    /**
     * Internal processing method using cached context
     * 
     * @throws \Exception If processing fails
     */
    private function processInternal(): void 
    {
        $process_start_time = microtime(true);
        
        // Performance timing instrumentation
        $step_times = [];
        $current_step_start = $process_start_time;
        
        // Skip detailed start message to reduce overhead
        // (Removed internal debug message)
        
        // 1. Check if this is a palette tag operation
        if ($this->handle_palette_processing()) {
            return;
        }

        $step_times['palette_check'] = microtime(true) - $current_step_start;
        $current_step_start = microtime(true);

        // 2. Calculate target dimensions
        $target_size = $this->calculate_target_size_for_context(true); // true = throw on failure
        
        $step_times['target_size_calculation'] = microtime(true) - $current_step_start;
        $current_step_start = microtime(true);

        // 3. Get source image - at this point we must have a physical image
        $image = $this->context->get_source_image();
        if (!$image) {
            throw new \Exception('No source image available for processing');
        }

        $step_times['source_image_retrieval'] = microtime(true) - $current_step_start;
        $current_step_start = microtime(true);

        // 3.5. Capture original metadata before any modifications
        // This enables complete copy elimination while preserving base64_orig functionality
        $this->capture_original_metadata($image);

        // 4. Working directly with source image
        // All original metadata captured above, so we can work directly on source

        // 4.25. Apply smart-scale resize before face detection (Legacy order)
        // This ensures face detection runs on appropriately sized images for performance
        $this->apply_preliminary_smart_scale_if_needed($image);

        $step_times['smart_scale'] = microtime(true) - $current_step_start;
        $current_step_start = microtime(true);

        // 4.5. Pre-analyze for face detection requirements and run once if needed
        // Face detection now runs on smart-scaled image (if applicable) for optimal performance
        $this->analyze_and_run_face_detection_if_needed($image);

        $step_times['face_detection'] = microtime(true) - $current_step_start;
        $current_step_start = microtime(true);

        // Create transformation queue (using instrumented transformation for filter-level timing)
        $transformation_queue = new InstrumentedTransformation(null, $this->utilities_service);
        $transformation_count = 0;
        
        // 5. Queue transformations following Legacy order and logic
        
        // 5a. Queue resize/crop operations
        if ($this->context->get_flag('its_a_crop')) {
            // Queue crop operation (with optional smart-scale first)
            $crop_param = $this->context->get_param('crop');
            if ($this->validate_crop_params($crop_param)) {
                $this->queue_crop_operation($transformation_queue, $transformation_count, $target_size);
            } else {
                // Invalid crop params, fall back to resize
                $this->queue_resize_operation($transformation_queue, $transformation_count, $target_size);
            }
        } else {
            // Queue resize operation (following Legacy's simple approach)
            $this->queue_resize_operation($transformation_queue, $transformation_count, $target_size);
        }
        
        // 5b. Queue other transformations in Legacy order
        $this->queue_flip_operation($transformation_queue, $transformation_count);
        $this->queue_filter_operations($transformation_queue, $transformation_count);
        $this->queue_text_overlay_operation($transformation_queue, $transformation_count);
        $this->queue_watermark_operation($transformation_queue, $transformation_count);
        $this->queue_rounded_corners_operation($transformation_queue, $transformation_count);
        $this->queue_border_operation($transformation_queue, $transformation_count);
        $this->queue_reflection_operation($transformation_queue, $transformation_count);
        $this->queue_rotate_operation($transformation_queue, $transformation_count);
        
        $step_times['transformation_queue_building'] = microtime(true) - $current_step_start;
        $current_step_start = microtime(true);

        // 6. Execute ALL transformations in one batch (like Legacy)
        
        if ($transformation_count > 0) {
            // Original metadata already captured in capture_original_metadata()            
            // Apply transformations directly to source/smart-scaled image
            $image = $transformation_queue->apply($image);
            if ($image) {
                if ($transformation_count > 1) {
                    $this->utilities_service->debug_message("{$transformation_count} transformations to be applied");
                } else {
                    $this->utilities_service->debug_message("{$transformation_count} transformation to be applied");
                }
            } else {
                throw new \Exception('Transformation queue execution failed');
            }
        } else {
            $this->utilities_service->debug_message("No transformations to apply");
        }
        
        $step_times['transformation_execution'] = microtime(true) - $current_step_start;
        $current_step_start = microtime(true);

        // 7. Store processed image
        $this->context->set_processed_image($image);
        
        // 8. Update metadata with actual final dimensions
        $final_size = $image->getSize();
        $this->context->set_metadata('final_width', $final_size->getWidth());
        $this->context->set_metadata('final_height', $final_size->getHeight());

        $step_times['final_metadata'] = microtime(true) - $current_step_start;
        
        // Performance analysis output - ensure this appears in template debug log
        $total_time = microtime(true) - $process_start_time;
        $this->utilities_service->debug_message("=== ProcessImageStage Performance Breakdown ===", null, false, 'standard');
        foreach ($step_times as $step => $time) {
            $percentage = ($time / $total_time) * 100;
            $this->utilities_service->debug_message(sprintf("  %s: %.4fs (%.1f%%)", str_pad($step, 30), $time, $percentage), null, false, 'standard');
        }
        $this->utilities_service->debug_message(sprintf("  %s: %.4fs", str_pad("TOTAL", 30), $total_time), null, false, 'standard');
        
        $this->utilities_service->debug_message(sprintf(
            'Final dimensions stored in metadata: %dx%d',
            $final_size->getWidth(),
            $final_size->getHeight()
        ), null, false, 'detailed');
        
        // Combined completion message with timing - matching legacy format
        $processing_time = microtime(true) - $process_start_time;
        $this->utilities_service->debug_message(lang('jcogs_img_debug_processing_complete'), [number_format($processing_time, 3)]);
    }
    
    
    /**
     * Apply min/max constraints to dimensions following legacy logic exactly
     * 
     * @param int $width Current width
     * @param int $height Current height  
     * @param Context $this->context Processing context containing constraint parameters
     * @return array Constrained dimensions ['width' => int, 'height' => int]
     */
    private function apply_min_max_constraints(int $width, int $height): array
    {
        // Get all constraint parameters
        $max_width = $this->context->get_param('max_width');
        $min_width = $this->context->get_param('min_width');
        $max_height = $this->context->get_param('max_height');
        $min_height = $this->context->get_param('min_height');
        $max = $this->context->get_param('max');
        $min = $this->context->get_param('min');
        
        $this->utilities_service->debug_log(sprintf(
            'Min/Max constraints - max_width: %s, min_width: %s, max_height: %s, min_height: %s, max: %s, min: %s',
            $max_width ?? 'null', $min_width ?? 'null', $max_height ?? 'null', 
            $min_height ?? 'null', $max ?? 'null', $min ?? 'null'
        ));
        
        // Convert constraint values to integers (following legacy logic) and handle validation failures
        $original_width = $this->context->get_metadata_value('original_width', 0);
        $original_height = $this->context->get_metadata_value('original_height', 0);
        
        // Validate dimensions and convert false values to null for constraint processing
        $max_width_validated = $max_width ? $this->validate_dimension_value($max_width, $original_width) : null;
        $max_width = ($max_width_validated === false) ? null : $max_width_validated;
        
        $min_width_validated = $min_width ? $this->validate_dimension_value($min_width, $original_width) : null;
        $min_width = ($min_width_validated === false) ? null : $min_width_validated;
        
        $max_height_validated = $max_height ? $this->validate_dimension_value($max_height, $original_height) : null;
        $max_height = ($max_height_validated === false) ? null : $max_height_validated;
        
        $min_height_validated = $min_height ? $this->validate_dimension_value($min_height, $original_height) : null;
        $min_height = ($min_height_validated === false) ? null : $min_height_validated;
        
        $max_validated = $max ? $this->validate_dimension_value($max, max($original_width, $original_height)) : null;
        $max = ($max_validated === false) ? null : $max_validated;
        
        $min_validated = $min ? $this->validate_dimension_value($min, max($original_width, $original_height)) : null;
        $min = ($min_validated === false) ? null : $min_validated;
        
        $constrained_width = $width;
        $constrained_height = $height;
        
        $this->utilities_service->debug_log("CONSTRAINTS INPUT: {$width}x{$height}");
        $this->utilities_service->debug_log("Original dimensions: {$original_width}x{$original_height}");
        
        // Apply constraints in legacy order with specific overriding general
        
        // 1. Apply general max constraint (applies to larger dimension)
        if ($max) {
            $larger_dim = max($constrained_width, $constrained_height);
            if ($larger_dim > $max) {
                $scale = $max / $larger_dim;
                $constrained_width = (int)round($constrained_width * $scale);
                $constrained_height = (int)round($constrained_height * $scale);
                $this->utilities_service->debug_message("Applied general max constraint: {$max} -> {$constrained_width}x{$constrained_height}");
            }
        }
        
        // 2. Apply general min constraint (applies to smaller dimension)
        if ($min) {
            $smaller_dim = min($constrained_width, $constrained_height);
            if ($smaller_dim < $min) {
                $scale = $min / $smaller_dim;
                $constrained_width = (int)round($constrained_width * $scale);
                $constrained_height = (int)round($constrained_height * $scale);
                $this->utilities_service->debug_message("Applied general min constraint: {$min} -> {$constrained_width}x{$constrained_height}");
            }
        }
        
        // 3. Apply specific max constraints (override general)
        if ($max_width && $constrained_width > $max_width) {
            $scale = $max_width / $constrained_width;
            $constrained_width = $max_width;
            $constrained_height = (int)round($constrained_height * $scale);
            $this->utilities_service->debug_message("Applied max_width constraint: {$max_width} -> {$constrained_width}x{$constrained_height}");
        }
        
        if ($max_height && $constrained_height > $max_height) {
            $scale = $max_height / $constrained_height;
            $constrained_height = $max_height;
            $constrained_width = (int)round($constrained_width * $scale);
            $this->utilities_service->debug_message("Applied max_height constraint: {$max_height} -> {$constrained_width}x{$constrained_height}");
        }
        
        // 4. Apply specific min constraints (override general)
        if ($min_width && $constrained_width < $min_width) {
            $scale = $min_width / $constrained_width;
            $constrained_width = $min_width;
            $constrained_height = (int)round($constrained_height * $scale);
            $this->utilities_service->debug_message("Applied min_width constraint: {$min_width} -> {$constrained_width}x{$constrained_height}");
        }
        
        if ($min_height && $constrained_height < $min_height) {
            $scale = $min_height / $constrained_height;
            $constrained_height = $min_height;
            $constrained_width = (int)round($constrained_width * $scale);
            $this->utilities_service->debug_message("Applied min_height constraint: {$min_height} -> {$constrained_width}x{$constrained_height}");
        }
        
        // Ensure minimum 1x1 dimensions
        $constrained_width = max(1, $constrained_width);
        $constrained_height = max(1, $constrained_height);
        
        // Apply allow_scale_larger constraint if set to 'n' (disable scaling larger)
        $allow_scale_larger = $this->context->get_param('allow_scale_larger');
        if (!$allow_scale_larger) {
            // Get default setting if parameter not provided
            $settings = new \JCOGSDesign\JCOGSImagePro\Service\Settings();
            $allow_scale_larger = $settings->get('img_cp_allow_scale_larger_default', 'n');
        }
        
        // If allow_scale_larger is disabled ('n'), cap dimensions to original size
        if ($allow_scale_larger === 'n') {
            if ($original_width > 0 && $constrained_width > $original_width) {
                $scale = $original_width / $constrained_width;
                $constrained_width = $original_width;
                $constrained_height = (int)round($constrained_height * $scale);
                $this->utilities_service->debug_message("Applied allow_scale_larger constraint (width): capped to original {$original_width} -> {$constrained_width}x{$constrained_height}");
            }
            
            if ($original_height > 0 && $constrained_height > $original_height) {
                $scale = $original_height / $constrained_height;
                $constrained_height = $original_height;
                $constrained_width = (int)round($constrained_width * $scale);
                $this->utilities_service->debug_message("Applied allow_scale_larger constraint (height): capped to original {$original_height} -> {$constrained_width}x{$constrained_height}");
            }
        }
        
        $this->utilities_service->debug_log("CONSTRAINTS OUTPUT: {$constrained_width}x{$constrained_height}");
        
        return [
            'width' => $constrained_width,
            'height' => $constrained_height
        ];
    }
    
    /**
     * Calculate and store display dimensions for special cases (like animated GIFs)
     * This method performs the same dimension calculations as normal processing
     * but without actually processing the image
     */
    private function calculate_and_store_display_dimensions(): void 
    {
        // Use the shared dimension calculation logic
        $target_size = $this->calculate_target_size_for_context(false); // false = don't throw on failure
        
        // Store final dimensions for animated GIFs
        $this->context->set_metadata('final_width', $target_size->getWidth());
        $this->context->set_metadata('final_height', $target_size->getHeight());
        
        $this->utilities_service->debug_message(sprintf(
            'Display dimensions calculated for animated GIF: %dx%d',
            $target_size->getWidth(),
            $target_size->getHeight()
        ));
    }
    
    /**
     * Validate and parse crop parameters (following legacy _validate_crop_params logic)
     * 
     * @param string|null $crop_params
     * @param Context $this->context
     * @return bool
     */
    private function validate_crop_params($crop_params): bool
    {
        // If we get null/empty, no crop
        if (empty($crop_params)) {
            return false;
        }
        
        // crop='no' or crop='none' means no crop
        if (substr(strtolower($crop_params), 0, 1) === 'n') {
            return false;
        }
        
        // Parse crop parameters following legacy format: 'yes|position|offset|smart_scale|sensitivity'
        $parsed_params = $this->parse_crop_parameters($crop_params);
        if ($parsed_params) {
            // Store parsed parameters in context for use by crop filters
            $this->context->set_metadata('crop_parsed_params', $parsed_params);
            
            // Alternative: Let's also try parsing directly in calculate_crop_position if this is the issue
            return true;
        }
        
        return false; // Invalid crop parameters
    }
    
    /**
     * Calculate target dimensions specifically for crop operations
     * Unlike regular calculate_target_dimensions, this preserves user-specified dimensions
     * without applying fit mode logic that could reduce the target size
     * 
     * @return Box Target dimensions for cropping
     */
    private function calculate_crop_target_dimensions(): Box 
    {
        // Get the key dimensions from context
        $width = $this->context->get_metadata_value('target_width', null);
        $height = $this->context->get_metadata_value('target_height', null);
        $original_width = $this->context->get_metadata_value('original_width', 0);
        $original_height = $this->context->get_metadata_value('original_height', 0);
        
        // If no dimensions specified, use original
        if (empty($width) && empty($height)) {
            if ($original_width > 0 && $original_height > 0) {
                return new Box($original_width, $original_height);
            }
            return new Box(100, 100); // Fallback
        }
        
        // Convert string dimensions to integers
        $width = $width ? (int)$width : null;
        $height = $height ? (int)$height : null;
        
        // Get aspect ratio - check for parameter first, then fall back to original image aspect ratio
        $aspect_ratio_param = $this->context->get_param('aspect_ratio');
        $aspect_ratio_orig = $original_width && $original_height ? $original_height / $original_width : 1.0;
        $aspect_ratio_target = $aspect_ratio_orig; // Default to original aspect ratio
        
        // If we have an aspect ratio parameter, try to parse it
        $parsed_aspect_ratio = $this->parse_aspect_ratio_parameter($aspect_ratio_param);
        if ($parsed_aspect_ratio !== null) {
            $aspect_ratio_target = $parsed_aspect_ratio;
        }
        
        // Calculate missing dimension while preserving aspect ratio
        // For crop operations, we want to use the user's specified dimensions directly
        if ($width && !$height) {
            // aspect_ratio is height/width ratio, so height = width * aspect_ratio
            $height = (int)round($width * $aspect_ratio_target);
        } elseif ($height && !$width) {
            // aspect_ratio is height/width ratio, so width = height / aspect_ratio
            $width = (int)round($height / $aspect_ratio_target);
        } elseif ($width && $height) {
            // Both dimensions specified - use them directly for crop operations
        } else {
            // Neither specified - use original dimensions
            $width = $original_width;
            $height = $original_height;
        }
        
        // Apply min/max constraints
        $constrained_dimensions = $this->apply_min_max_constraints($width, $height);
        $final_width = $constrained_dimensions['width'];
        $final_height = $constrained_dimensions['height'];
        
        return new Box($final_width, $final_height);
    }

    /**
     * Calculate dimensions for fit modes following legacy _get_fit_dimensions logic
     * 
     * @param Box $current_size Current image size
     * @param Box $target_size Target bounding box
     * @param string $fit_mode 'contain', 'cover', or 'distort'
     * @return Box Calculated dimensions
     */
    private function calculate_fit_dimensions(Box $current_size, Box $target_size, string $fit_mode): Box 
    {
        $orig_width = $current_size->getWidth();
        $orig_height = $current_size->getHeight();
        $new_width = $target_size->getWidth();
        $new_height = $target_size->getHeight();
        
        // Calculate aspect ratios like legacy
        $aspect_ratio_orig = $orig_height / $orig_width; // height/width ratio
        $aspect_ratio_target = $new_height / $new_width;
        
        // If aspect ratios match (within tolerance), use target dimensions
        if (abs($aspect_ratio_target - $aspect_ratio_orig) <= 0.0001) {
            return $target_size;
        }
        
        // Aspect ratios differ - calculate fit dimensions
        if ($fit_mode === 'contain') {
            // Contain: image fits within bounding box
            if ($new_width * $aspect_ratio_orig > $new_height) {
                // Y is constraining axis - use target height, calculate width
                $new_width = round($new_height / $aspect_ratio_orig);
            } else {
                // X is constraining axis - use target width, calculate height  
                $new_height = round($new_width * $aspect_ratio_orig);
            }
        } elseif ($fit_mode === 'cover') {
            // Cover: image fills bounding box (may exceed bounds)
            if ($new_width * $aspect_ratio_orig > $new_height) {
                // Keep new_width, calculate height to fill
                $new_height = round($new_width * $aspect_ratio_orig);
            } else {
                // Keep new_height, calculate width to fill
                $new_width = round($new_height / $aspect_ratio_orig);
            }
        }
        // For 'distort' mode, we would return target_size unchanged (handled in apply_resize)
        
        return new Box(max(1, $new_width), max(1, $new_height));
    }

    /**
     * Calculate target dimensions based on parameters
     * 
     * @return Box|null Target dimensions
     */
    private function calculate_target_dimensions(): ?Box 
    {
        // Get the key dimensions from context
        $width = $this->context->get_metadata_value('target_width', null);
        $height = $this->context->get_metadata_value('target_height', null);
        $original_width = $this->context->get_metadata_value('original_width', 0);
        $original_height = $this->context->get_metadata_value('original_height', 0);

        // Get the fit mode we are using
        $fit_mode = $this->context->get_param('fit', 'contain'); // Default to 'contain' per documentation
        
        // If no dimensions specified, use original
        if (empty($width) && empty($height)) {
            if ($original_width > 0 && $original_height > 0) {
                return new Box($original_width, $original_height);
            }
            return new Box(100, 100); // Fallback
        }
        
        // Convert string dimensions to integers
        $width = $width ? (int)$width : null;
        $height = $height ? (int)$height : null;
        
        // Get aspect ratio - check for parameter first, then fall back to original image aspect ratio
        $aspect_ratio_param = $this->context->get_param('aspect_ratio');
        $aspect_ratio_orig = $original_width && $original_height ? $original_height / $original_width : 1.0; // Default to 1.0 if no original size
        $aspect_ratio_target = $aspect_ratio_orig; // Default to original aspect ratio
        
        // If we have an aspect ratio parameter, try to parse it
        $parsed_aspect_ratio = $this->parse_aspect_ratio_parameter($aspect_ratio_param);
        if ($parsed_aspect_ratio !== null) {
            $aspect_ratio_target = $parsed_aspect_ratio;
        }
        
        // Calculate missing raw dimension while preserving aspect ratio
        if ($width && !$height) {
            // aspect_ratio is height/width ratio, so height = width * aspect_ratio
            $height = (int)round($width * $aspect_ratio_target);
        } elseif ($height && !$width) {
            // aspect_ratio is height/width ratio, so width = height / aspect_ratio
            $width = (int)round($height / $aspect_ratio_target);
        }
        

        // Now process the dimensions based on fit mode
        
        // For fit mode logic, compare the target bounding box aspect ratio with original image aspect ratio
        $target_box_aspect_ratio = $height > 0 ? $height / $width : $aspect_ratio_orig;
        
        // If aspect ratios match (within tolerance), use target dimensions
        if (abs($target_box_aspect_ratio - $aspect_ratio_orig) <= 0.0001) {
            $final_width = $width;
            $final_height = $height;
        } else {
            // Aspect ratios differ - calculate fit dimensions using ORIGINAL aspect ratio (Legacy behavior)
            if ($fit_mode === 'contain') {
                // Contain: image fits within bounding box
                if ($width * $aspect_ratio_orig > $height) {
                    // Y is constraining axis - use target height, calculate width using ORIGINAL aspect ratio
                    $final_width = round($height / $aspect_ratio_orig);
                    $final_height = $height;
                } else {
                    // X is constraining axis - use target width, calculate height using ORIGINAL aspect ratio
                    $final_width = $width;
                    $final_height = round($width * $aspect_ratio_orig);
                }
            } elseif ($fit_mode === 'cover') {
                // Cover: image fills bounding box (may exceed bounds) using ORIGINAL aspect ratio
                if ($width * $aspect_ratio_orig > $height) {
                    // Y is constraining axis for cover - keep width, calculate height using ORIGINAL aspect ratio
                    $final_width = $width;
                    $final_height = round($width * $aspect_ratio_orig);
                } else {
                    // X is constraining axis for cover - keep height, calculate width using ORIGINAL aspect ratio
                    $final_width = round($height / $aspect_ratio_orig);
                    $final_height = $height;
                }
            } else {
                // For 'distort' mode, use target dimensions unchanged
                $final_width = $width;
                $final_height = $height;
            }
        }
        
        
        // Apply min/max constraints following legacy logic exactly
        $constrained_dimensions = $this->apply_min_max_constraints($final_width, $final_height);
        $final_width = $constrained_dimensions['width'];
        $final_height = $constrained_dimensions['height'];
        
        
        return new Box($final_width, $final_height);
    }

    /**
     * Shared logic for calculating target dimensions
     * Used by both normal processing and special cases (like animated GIFs)
     * 
     * @param bool $throw_on_failure Whether to throw exception on calculation failure
     * @return Box Target dimensions
     */
    private function calculate_target_size_for_context(bool $throw_on_failure = true): Box
    {
        // Set metadata for target dimensions
        $target_width = $this->context->get_param('width');
        $target_height = $this->context->get_param('height');
        $this->context->set_metadata('target_width', $target_width);
        $this->context->set_metadata('target_height', $target_height);

        // Calculate target dimensions using the same logic as normal processing
        $crop_param = $this->context->get_param('crop');
        $its_a_crop = $this->context->get_flag('its_a_crop');
        $is_face_detect_crop_mode = false;
        
        // Check if this is face_detect crop mode (first parameter is face_detect)
        if ($crop_param) {
            $crop_parts = explode('|', $crop_param);
            $crop_mode = isset($crop_parts[0]) ? strtolower($crop_parts[0]) : '';
            $is_face_detect_crop_mode = ($crop_mode === 'face_detect');
        }
        
        if ($is_face_detect_crop_mode) {
            // For face_detect crop mode, defer dimension calculation
            $target_size = new Box(100, 100); // Placeholder
        } else if ($its_a_crop) {
            // For regular crop modes, use crop target dimensions
            $target_size = $this->calculate_crop_target_dimensions();
        } else {
            $target_size = $this->calculate_target_dimensions();
            if (!$target_size) {
                if ($throw_on_failure) {
                    throw new \Exception('Failed to calculate target dimensions');
                } else {
                    // Fall back to original dimensions if calculation fails
                    $original_width = $this->context->get_metadata_value('original_width', 0);
                    $original_height = $this->context->get_metadata_value('original_height', 0);
                    $target_size = new Box($original_width ?: 100, $original_height ?: 100);
                }
            }
            
            // Update context metadata with calculated dimensions
            $this->context->set_metadata('target_width', $target_size->getWidth());
            $this->context->set_metadata('target_height', $target_size->getHeight());
        }
        
        return $target_size;
    }
    
    /**
     * Generate color fill image
     * 
     * @return mixed Color image object
     */
    private function generate_color_fill_image() 
    {
        $color = $this->context->get_param('color', '#cccccc');
        $width = (int)$this->context->get_param('width', 100);
        $height = (int)$this->context->get_param('height', 100);
        
        $this->utilities_service->debug_message("Generating {$width}x{$height} color fill image with color: {$color}", null, false, 'detailed');
        
        // For now, return a basic colored image
        // In a full implementation, this would create an actual colored image
        $imagine = new \Imagine\Gd\Imagine();
        
        // Create a simple colored canvas
        $image = $imagine->create(new Box($width, $height));
        
        return $image;
    }
    
    /**
     * Generate SVG output
     * 
     * @param Context $this->context
     * @return string SVG HTML output
     */
    private function generate_svg_output(): string 
    {
        $src = $this->context->get_param('src');
        $width = $this->context->get_param('width', '');
        $height = $this->context->get_param('height', '');
        
        $attributes = [];
        if ($width) $attributes[] = "width=\"{$width}\"";
        if ($height) $attributes[] = "height=\"{$height}\"";
        
        $attr_string = !empty($attributes) ? ' ' . implode(' ', $attributes) : '';
        
        return "<img src=\"{$src}\"{$attr_string} />";
    }
    
    /**
     * Handle palette processing for palette tags
     * 
     * @param Context $this->context
     * @return bool True if palette processing was handled
     */
    private function handle_palette_processing(): bool 
    {
        // Check if this is a palette tag
        $called_by = $this->context->get_flag('called_by') ?: '';
        if ($called_by !== 'Palette_Tag') {
            return false;
        }
        
        $this->utilities_service->debug_message('Palette tag detected - processing color palette');
        
        try {
            // Get source image
            $source_image = $this->context->get_source_image();
            if (!$source_image) {
                throw new \Exception('No source image available for palette processing');
            }
            
            // Get palette size parameter (default to 5 like Legacy)
            $palette_size = (int) $this->context->get_param('palette_size', 5);
            $palette_size = max(1, min(20, $palette_size)); // Limit to reasonable range
            
            // Get GD resource for ColorThief
            $gd_resource = $source_image->getGdResource();
            if (!$gd_resource) {
                throw new \Exception('Failed to get GD resource for palette processing');
            }
            
            // Use ColorThief to extract palette and dominant color
            $palette = \ColorThief\ColorThief::getPalette($gd_resource, $palette_size, 10);
            $dominant_color = \ColorThief\ColorThief::getColor($gd_resource, 10);
            
            // Format colors as RGB strings
            $formatted_palette = [];
            $rank = 1;
            foreach ($palette as $color) {
                $formatted_palette[] = [
                    'color' => sprintf('rgb(%d,%d,%d)', $color[0], $color[1], $color[2]),
                    'rank' => $rank++
                ];
            }
            
            $formatted_dominant = sprintf('rgb(%d,%d,%d)', $dominant_color[0], $dominant_color[1], $dominant_color[2]);
            
            // Build output variables for template parsing
            $output_vars = [
                'colors' => $formatted_palette,
                'dominant_color' => $formatted_dominant
            ];
            
            // Store palette data for OutputStage
            $this->context->set_metadata('palette_data', $output_vars);
            $this->context->set_flag('is_palette_processing', true);
            
            $this->utilities_service->debug_message(sprintf(
                'Palette extracted: %d colors, dominant: %s',
                count($formatted_palette),
                $formatted_dominant
            ));
            
            return true;
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Palette processing failed: " . $e->getMessage());
            // Set empty palette data to prevent errors
            $this->context->set_metadata('palette_data', [
                'colors' => [],
                'dominant_color' => 'rgb(128,128,128)'
            ]);
            $this->context->set_flag('is_palette_processing', true);
            return true;
        }
    }

    /**
     * Handle special processing cases
     * 
     * @param Context $this->context
     * @return bool True if handled as special case
     */
    private function handle_special_cases(): bool 
    {
        // Handle SVG passthrough
        if ($this->context->get_flag('svg')) {
            $this->utilities_service->debug_message('SVG passthrough - no processing needed');
            $this->context->set_output($this->generate_svg_output());
            $this->context->set_exit_early(true, 'SVG passthrough');
            return true;
        }
        
        // Handle animated GIF passthrough (but calculate display dimensions)
        if ($this->context->get_flag('animated_gif')) {
            $this->utilities_service->debug_message('Animated GIF detected - calculating display dimensions without processing');
            
            // Calculate and store display dimensions for animated GIFs
            $this->calculate_and_store_display_dimensions();
            
            // The raw image data is already stored in metadata from LoadSourceStage
            // No processing needed - will be handled in CacheStage as direct copy
            return true;
        }
        
        // Handle color fill mode
        if ($this->context->get_flag('use_colour_fill')) {
            $this->utilities_service->debug_message('Color fill mode - generating color image', null, false, 'detailed');
            $processed_image = $this->generate_color_fill_image();
            $this->context->set_processed_image($processed_image);
            $this->utilities_service->debug_message('Color fill image generated and stored in context', null, false, 'detailed');
            return true; // Exit early - color fill processing is complete
        }
        
        return false;
    }
    
    /**
     * Parse aspect ratio parameter using legacy logic
     * 
     * @param string $input Aspect ratio string (e.g., '16_9', '16:9', '4/3')
     * @return float|null Parsed aspect ratio as height/width ratio, or null if invalid
     */
    private function parse_aspect_ratio_parameter(?string $input): ?float 
    {
        // Handle null input
        if ($input === null || $input === '') {
            return null;
        }
        
        // Validate input format - must contain separator
        if (!(stripos($input, '_', 1) || stripos($input, '/', 1) || stripos($input, ':', 1))) {
            $this->utilities_service->debug_message("Invalid aspect ratio format: {$input}");
            return null;
        }

        // Extract ratio values using regex pattern from legacy code
        preg_match('/^(\d*)(?:_|\/|\:)(\d*)/', $input, $matches, PREG_UNMATCHED_AS_NULL);
        
        if (isset($matches[1]) && isset($matches[2]) && 
            is_numeric($matches[1]) && is_numeric($matches[2]) &&
            intval($matches[1]) > 0 && intval($matches[2]) > 0) {
            
            // Calculate aspect ratio as height/width ratio (matches[2] / matches[1])
            $aspect_ratio = floatval($matches[2]) / floatval($matches[1]);
            $this->utilities_service->debug_message("Parsed aspect ratio: {$input} => {$matches[2]}/{$matches[1]} = {$aspect_ratio}");
            
            return $aspect_ratio;
        }
        
        $this->utilities_service->debug_message("Failed to parse aspect ratio: {$input}");
        return null;
    }
    
    /**
     * Parse crop parameters following legacy _validate_crop_params logic
     * 
     * @param string $crop_params Raw crop parameter string
     * @param Context $this->context Processing context for dimension validation
     * @return array|null Parsed parameters or null if invalid
     */
    private function parse_crop_parameters(string $crop_params): ?array
    {
        // Split crop params by pipe character
        $crop_parts = explode('|', $crop_params);
        
        // Legacy defaults: 'y|center,center|0,0|y|50'
        $defaults = [
            'mode' => 'y',            // yes/no/face_detect
            'position' => ['center', 'center'], // [horizontal, vertical]
            'offset' => [0, 0],       // [x_offset, y_offset] in pixels
            'smart_scale' => 'y',     // yes/no
            'sensitivity' => 50       // face detection sensitivity
        ];
        
        // 1. Validate crop mode (yes/no/face_detect)
        $mode = isset($crop_parts[0]) ? strtolower($crop_parts[0]) : $defaults['mode'];
        if ($mode === 'face_detect') {
            $mode = 'f'; // Convert to single character for consistency
        } else {
            $mode = strtolower(substr($mode, 0, 1));
        }
        if (!in_array($mode, ['y', 'n', 'f'])) {
            $mode = $defaults['mode'];
        }
        
        // 2. Parse position parameters (horizontal,vertical)
        $position = $defaults['position'];
        if (isset($crop_parts[1]) && !empty($crop_parts[1])) {
            $pos_parts = explode(',', $crop_parts[1]);
            
            $horizontal = isset($pos_parts[0]) ? strtolower($pos_parts[0]) : '';
            $vertical = isset($pos_parts[1]) ? strtolower($pos_parts[1]) : '';
            
            $position[0] = in_array($horizontal, ['left', 'center', 'right', 'face_detect']) 
                ? $horizontal : $defaults['position'][0];
            $position[1] = in_array($vertical, ['top', 'center', 'bottom', 'face_detect']) 
                ? $vertical : $defaults['position'][1];
        }
        
        // 3. Parse offset parameters (x_offset,y_offset)
        $offset = $defaults['offset'];
        if (isset($crop_parts[2]) && !empty($crop_parts[2])) {
            $offset_parts = explode(',', $crop_parts[2]);
            if (count($offset_parts) == 2) {
                // Use validate_dimension-like logic for offset values
                $original_width = $this->context->get_metadata_value('original_width', 0);
                $original_height = $this->context->get_metadata_value('original_height', 0);
                
                $offset_x = $this->validate_dimension_value($offset_parts[0], $original_width);
                $offset_y = $this->validate_dimension_value($offset_parts[1], $original_height);
                
                // Handle validation failures
                $offset[0] = ($offset_x === false) ? 0 : $offset_x;
                $offset[1] = ($offset_y === false) ? 0 : $offset_y;
            }
        }
        
        // 4. Parse smart scale flag (yes/no)
        $smart_scale = isset($crop_parts[3]) ? strtolower(substr($crop_parts[3], 0, 1)) : $defaults['smart_scale'];
        if (!in_array($smart_scale, ['y', 'n'])) {
            $smart_scale = $defaults['smart_scale'];
        }
        
        // 5. Parse sensitivity (optional)
        $sensitivity = isset($crop_parts[4]) && is_numeric($crop_parts[4]) ? (int)$crop_parts[4] : $defaults['sensitivity'];
        
        return [
            'mode' => $mode,
            'position' => $position,
            'offset' => $offset,
            'smart_scale' => $smart_scale,
            'sensitivity' => $sensitivity
        ];
    }
    
    /**
     * Queue border operation
     */
    private function queue_border_operation($transformation_queue, &$transformation_count): void
    {
        $border_param = $this->context->get_param('border');
        if ($border_param !== null && $border_param !== '') {
            $this->utilities_service->debug_message("Queuing border operation: {$border_param}", null, false, 'detailed');
            
            // Create a custom filter that applies the border using the comprehensive logic from apply_border
            $border_filter = new class($border_param, $this->context, $this, $this->utilities_service) implements \Imagine\Filter\FilterInterface {
                private $border_param;
                private $context;
                private $stage;
                private $utilities_service;

                public function __construct($border_param, $context, $stage, $utilities_service) {
                    $this->border_param = $border_param;
                    $this->context = $context;
                    $this->stage = $stage;
                    $this->utilities_service = $utilities_service;
                }
                
                public function apply($image, array $params = []) {
                    try {
                        // Parse border parameter using legacy format: "width|color"
                        $border_parts = explode('|', $this->border_param);
                        if (count($border_parts) < 1) {
                            $this->utilities_service->debug_message("Invalid border parameter format: {$this->border_param}");
                            return $image;
                        }
                        
                        // Use target width for percentage calculations (matching legacy usage of new_width)
                        $target_width = $this->context->get_metadata_value('target_width', 0);
                        if ($target_width <= 0) {
                            // Fallback to current image width if no target width available
                            $current_size = $image->getSize();
                            $target_width = $current_size->getWidth();
                        }
                        
                        // Extract and validate width using legacy-compatible method
                        $width_str = trim($border_parts[0]);
                        if (empty($width_str)) {
                            $this->utilities_service->debug_message("Missing border width in parameter: {$this->border_param}");
                            return $image;
                        }
                        
                        // Use reflection to call the private validate_dimension_value method
                        $reflection = new \ReflectionClass($this->stage);
                        $validate_method = $reflection->getMethod('validate_dimension_value');
                        $validate_method->setAccessible(true);
                        $width = $validate_method->invoke($this->stage, $width_str, $target_width);
                        
                        if ($width === false || $width <= 0) {
                            $this->utilities_service->debug_message("Invalid border width: {$width_str} -> " . ($width === false ? 'false' : $width));
                            return $image;
                        }
                        
                        // Extract color (optional, defaults to #FFFFFF)
                        $color = '#FFFFFF';
                        if (count($border_parts) >= 2 && !empty(trim($border_parts[1]))) {
                            $color = trim($border_parts[1]);
                            
                            // Basic color format validation
                            if (!str_starts_with($color, '#') && !str_starts_with($color, 'rgb')) {
                                // Add # prefix for hex colors without it
                                if (ctype_xdigit($color)) {
                                    $color = '#' . $color;
                                }
                            }
                        }
                        
                        // Determine if image has been masked (non-rectangular shape)
                        $is_masked = $this->context->get_flag('masked_image', false);
                        
                        // Also check for rounded corners parameter - these create shaped images requiring masked borders
                        $rounded_corners_param = $this->context->get_param('rounded_corners');
                        $has_rounded_corners = !empty($rounded_corners_param);
                        
                        // Check for mask filters in the filter parameter that will create shaped images
                        $filter_param = $this->context->get_param('filter');
                        $has_mask_filter = !empty($filter_param) && str_contains($filter_param, 'mask');
                        
                        // Check if output format supports transparency (needed for masked borders)
                        $save_type = $this->context->get_param('save_type', 'jpg');
                        $supports_transparency = in_array(strtolower($save_type), ['png', 'webp', 'gif']);
                        
                        // Determine if we need shaped border handling
                        // Only use masked borders if format supports transparency - otherwise use box border like Legacy
                        $needs_masked_border = ($is_masked || $has_rounded_corners || $has_mask_filter) && $supports_transparency;
                        
                        // For masked images or images with rounded corners, use masked border filter
                        if ($needs_masked_border) {
                            // Use masked border filter for shaped images with constructor parameters
                            $border_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Border\MaskedBorder($width, $color, $rounded_corners_param);
                            $image = $border_filter->apply($image);
                            $this->utilities_service->debug_message("Using masked border filter for shaped image - width: {$width}, color: {$color}, rounded_corners: {$rounded_corners_param}, is_masked: {$is_masked}");
                        } else {
                            // Use box border filter for rectangular images
                            $border_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Border\BoxBorder($this->border_param);
                            $image = $border_filter->apply($image);
                            $this->utilities_service->debug_message("Using box border filter for rectangular image - width: {$width}, color: {$color}");
                        }
                        $this->utilities_service->debug_message("Applied border transformation: width={$width}, color={$color}, masked={$needs_masked_border}");
                        return $image;
                    } catch (\Exception $e) {
                        $this->utilities_service->debug_message("Error applying border: " . $e->getMessage());
                        return $image;
                    }
                }
            };
            
            $transformation_queue->add($border_filter, $transformation_count++);
            $this->utilities_service->debug_message("Queued border operation with comprehensive logic");
        }
    }
    
    /**
     * Queue crop operation (Legacy-style)
     */
    private function queue_crop_operation($transformation_queue, &$transformation_count, $target_size): void
    {
        $crop_param = $this->context->get_param('crop');
        
        // If no crop parameter or crop is disabled, fall back to resize
        if (empty($crop_param) || substr(strtolower($crop_param), 0, 1) === 'n') {
            $this->queue_resize_operation($transformation_queue, $transformation_count, $target_size);
            return;
        }
        
        // Validate and parse crop parameters
        if (!$this->validate_crop_params($crop_param)) {
            $this->utilities_service->debug_message("Invalid crop parameters, falling back to resize: {$crop_param}", null, false, 'detailed');
            $this->queue_resize_operation($transformation_queue, $transformation_count, $target_size);
            return;
        }
        
        // Queue crop transformation with parsed parameters
        $this->utilities_service->debug_message("Queuing crop operation with target size: {$target_size->getWidth()}x{$target_size->getHeight()}", null, false, 'detailed');
        $this->utilities_service->debug_message("Queuing crop transformation: {$crop_param}");
        
        // Use dedicated Crop filter for maintainability
        $crop_filter = new \JCOGSDesign\JCOGSImagePro\Filters\Crop($target_size, $this->context);
        $transformation_queue->add($crop_filter, $transformation_count++);
    }
    
    /**
     * Queue filter operations using Pro filter wrapper
     */
    private function queue_filter_operations($transformation_queue, &$transformation_count): void
    {
        $filter_param = $this->context->get_param('filter');
        if ($filter_param) {
            $this->utilities_service->debug_message("Queuing filter operations (Imagine-compatible): {$filter_param}", null, false, 'detailed');
            
            // Parse multiple filters separated by pipes
            $filters = explode('|', $filter_param);
            
            foreach ($filters as $filter) {
                $filter = trim($filter);
                if (empty($filter)) continue;
                
                // Parse filter name and parameters
                $filter_parts = explode(',', $filter);
                $filter_name = strtolower(trim($filter_parts[0]));
                
                switch ($filter_name) {
                    case 'grayscale':
                    case 'greyscale':
                        // Use Pro's Greyscale filter directly
                        $transformation_queue->add(new Greyscale(), $transformation_count++);
                        break;
                        
                    case 'blur':
                    case 'gaussian_blur':
                        $blur_radius = isset($filter_parts[1]) ? (int)$filter_parts[1] : 1;
                        $transformation_queue->add(new Blur($blur_radius), $transformation_count++);
                        break;
                        
                    case 'negate':
                    case 'invert':
                        $transformation_queue->add(new Negate(), $transformation_count++);
                        break;
                        
                    case 'brightness':
                        $brightness_amount = isset($filter_parts[1]) ? (int)$filter_parts[1] : 0;
                        // Scale brightness value to lie within 100/-100 range like Legacy
                        $brightness = (int) round($brightness_amount / 255 * 100, 0);
                        $transformation_queue->add(new Brightness($brightness), $transformation_count++);
                        break;
                        
                    case 'contrast':
                        $contrast_level = isset($filter_parts[1]) ? (int)$filter_parts[1] : 0;
                        // Legacy inverts the contrast value
                        $contrast_level = -$contrast_level;
                        $transformation_queue->add(new Contrast($contrast_level), $transformation_count++);
                        break;
                        
                    case 'colorize':
                        $red = isset($filter_parts[1]) ? (int)$filter_parts[1] : 0;
                        $green = isset($filter_parts[2]) ? (int)$filter_parts[2] : 0;
                        $blue = isset($filter_parts[3]) ? (int)$filter_parts[3] : 0;
                        $alpha = isset($filter_parts[4]) ? (int)$filter_parts[4] : 0;
                        $transformation_queue->add(new Colorize($red, $green, $blue, $alpha), $transformation_count++);
                        break;
                        
                    case 'opacity':
                        $opacity_level = isset($filter_parts[1]) ? (int)$filter_parts[1] : 100;
                        $transformation_queue->add(new Opacity($opacity_level), $transformation_count++);
                        break;
                        
                    case 'sharpen':
                        $amount = isset($filter_parts[1]) ? (int)$filter_parts[1] : 80;
                        $radius = isset($filter_parts[2]) ? (float)$filter_parts[2] : 0.5;
                        $threshold = isset($filter_parts[3]) ? (int)$filter_parts[3] : 3;
                        $transformation_queue->add(new Sharpen($amount, $radius, $threshold), $transformation_count++);
                        break;
                        
                    case 'auto_sharpen':
                        // AutoSharpen filter with no parameters (uses automatic detection)
                        $this->utilities_service->debug_message("Queuing auto_sharpen filter transformation");
                        $transformation_queue->add(new AutoSharpen(), $transformation_count++);
                        break;
                        
                    case 'flip':
                        $flip_direction = isset($filter_parts[1]) ? strtolower(trim($filter_parts[1])) : 'h';
                        $this->utilities_service->debug_message("Queuing flip filter transformation: {$flip_direction}");
                        $transformation_queue->add(new Flip($flip_direction), $transformation_count++);
                        break;
                        
                    case 'edgedetect':
                        $this->utilities_service->debug_message("Queuing edgedetect filter transformation");
                        $transformation_queue->add(new Edgedetect(), $transformation_count++);
                        break;
                        
                    case 'emboss':
                        $this->utilities_service->debug_message("Queuing emboss filter transformation");
                        $transformation_queue->add(new Emboss(), $transformation_count++);
                        break;
                        
                    case 'mean_removal':
                        $this->utilities_service->debug_message("Queuing mean_removal filter transformation");
                        $transformation_queue->add(new MeanRemoval(), $transformation_count++);
                        break;
                        
                    case 'noise':
                        $noise_level = isset($filter_parts[1]) ? (int)$filter_parts[1] : 5;
                        $this->utilities_service->debug_message("Queuing noise filter transformation: {$noise_level}");
                        $transformation_queue->add(new Noise($noise_level), $transformation_count++);
                        break;
                        
                    case 'scatter':
                        $effect_x = isset($filter_parts[1]) ? (int)$filter_parts[1] : 3;
                        $effect_y = isset($filter_parts[2]) ? (int)$filter_parts[2] : 3;
                        $this->utilities_service->debug_message("Queuing scatter filter transformation: {$effect_x},{$effect_y}");
                        $transformation_queue->add(new Scatter($effect_x, $effect_y), $transformation_count++);
                        break;
                        
                    case 'selective_blur':
                        $blur_intensity = isset($filter_parts[1]) ? (int)$filter_parts[1] : 3;
                        $this->utilities_service->debug_message("Queuing selective_blur filter transformation: {$blur_intensity}");
                        $transformation_queue->add(new SelectiveBlur($blur_intensity), $transformation_count++);
                        break;
                        
                    case 'sepia':
                        $sepia_method = isset($filter_parts[1]) ? strtolower(trim($filter_parts[1])) : 'fast';
                        $this->utilities_service->debug_message("Queuing sepia filter transformation: {$sepia_method}");
                        $transformation_queue->add(new Sepia($sepia_method), $transformation_count++);
                        break;
                        
                    case 'smooth':
                        $smoothness = isset($filter_parts[1]) ? (int)$filter_parts[1] : 6;
                        $this->utilities_service->debug_message("Queuing smooth filter transformation: {$smoothness}");
                        $transformation_queue->add(new Smooth($smoothness), $transformation_count++);
                        break;
                        
                    case 'sobel_edgify':
                        $this->utilities_service->debug_message("Queuing sobel_edgify filter transformation");
                        $transformation_queue->add(new SobelEdgify(), $transformation_count++);
                        break;
                        
                    case 'pixelate':
                        $pixel_size = isset($filter_parts[1]) ? (int)$filter_parts[1] : 12;
                        $advanced = isset($filter_parts[2]) ? filter_var($filter_parts[2], FILTER_VALIDATE_BOOLEAN) : true;
                        $this->utilities_service->debug_message("Queuing pixelate filter transformation: size={$pixel_size}, advanced={$advanced}");
                        $transformation_queue->add(new Pixelate($pixel_size, $advanced), $transformation_count++);
                        break;
                        
                    case 'dominant_color':
                        $this->utilities_service->debug_message("Queuing dominant_color filter transformation");
                        $transformation_queue->add(new DominantColor(), $transformation_count++);
                        break;
                        
                    case 'dot':
                        $dot_size = isset($filter_parts[1]) ? (int)$filter_parts[1] : 2;
                        $dot_color = isset($filter_parts[2]) ? trim($filter_parts[2]) : '';
                        $dot_shape = isset($filter_parts[3]) ? strtolower(trim($filter_parts[3])) : 'circle';
                        $this->utilities_service->debug_message("Queuing dot filter transformation: size={$dot_size}, color={$dot_color}, shape={$dot_shape}");
                        $transformation_queue->add(new Dot($dot_size, $dot_color, $dot_shape), $transformation_count++);
                        break;
                        
                    case 'face_detect':
                        $this->utilities_service->debug_message("Queuing face_detect filter transformation");
                        
                        // Face detection should already be done by pipeline analysis - get cached results
                        $cached_face_regions = $this->context->get_metadata_value('original_face_regions', null);
                        if ($cached_face_regions !== null) {
                            $this->utilities_service->debug_message("Using pre-analyzed face regions for face_detect filter: " . count($cached_face_regions) . " faces");
                        } else {
                            $this->utilities_service->debug_message("No pre-analyzed face regions found - face_detect filter will run fresh detection");
                        }
                        
                        $transformation_queue->add(new FaceDetect('highlight', 50, $cached_face_regions), $transformation_count++);
                        break;
                        
                    case 'lqip':
                        $this->utilities_service->debug_message("Queuing lqip filter transformation");
                        $transformation_queue->add(new Lqip(), $transformation_count++);
                        break;
                        
                    case 'mask':
                        // Parse mask parameters: shape, x, y, width, height, etc.
                        $mask_params = array_slice($filter_parts, 1); // Remove filter name
                        // Trim all parameters to remove leading/trailing whitespace (critical for shape detection)
                        $mask_params = array_map('trim', $mask_params);
                        $this->utilities_service->debug_message("Queuing mask filter transformation with " . count($mask_params) . " parameters");
                        $transformation_queue->add(new Mask($mask_params), $transformation_count++);
                        break;
                        
                    case 'replace_colors':
                    case 'replace_colour':
                        $from_color = isset($filter_parts[1]) ? trim($filter_parts[1]) : '';
                        $to_color = isset($filter_parts[2]) ? trim($filter_parts[2]) : '';
                        $fuzziness = isset($filter_parts[3]) ? (int)$filter_parts[3] : 0;
                        $this->utilities_service->debug_message("Queuing replace_colors filter transformation: {$from_color} -> {$to_color}, fuzziness={$fuzziness}");
                        $transformation_queue->add(new ReplaceColors($from_color, $to_color, $fuzziness), $transformation_count++);
                        break;
                        
                    // Add more filters as needed...
                    default:
                        $this->utilities_service->debug_message("Unknown filter: {$filter_name}", null, false, 'detailed');
                        break;
                }
            }
        }
    }
    
    /**
     * Queue flip operation
     */
    private function queue_flip_operation($transformation_queue, &$transformation_count): void
    {
        $flip_param = $this->context->get_param('flip');
        if ($flip_param !== null && $flip_param !== '') {
            $this->utilities_service->debug_log("Queuing flip operation: {$flip_param}");
            $this->utilities_service->debug_message("Queuing flip transformation: {$flip_param}");
            
            $transformation_queue->add(new Flip($flip_param), $transformation_count++);
        }
    }
    
    /**
     * Queue reflection operation
     */
    private function queue_reflection_operation($transformation_queue, &$transformation_count): void
    {
        $reflection_param = $this->context->get_param('reflection');
        if ($reflection_param !== null && $reflection_param !== '') {
            $this->utilities_service->debug_message("Queuing reflection operation: {$reflection_param}");
            
            $transformation_queue->add(new Reflection($reflection_param), $transformation_count++);
        }
    }
   
    /**
     * Queue resize operation
     */
    private function queue_resize_operation($transformation_queue, &$transformation_count, $target_size): void
    {
        // Use Legacy's simple approach: just queue a basic resize
        $this->utilities_service->debug_message("Queuing resize operation");
        $this->utilities_service->debug_log("Queuing resize transformation: {$target_size->getWidth()}x{$target_size->getHeight()}");
        $transformation_queue->add(new Resize($target_size), $transformation_count++);
    }
    
    /**
     * Queue rotate operation
     */
    private function queue_rotate_operation($transformation_queue, &$transformation_count): void
    {
        $rotate_param = $this->context->get_param('rotate');
        if ($rotate_param !== null && $rotate_param !== '') {
            $this->utilities_service->debug_message("Queuing rotate operation: {$rotate_param}");
            
            $transformation_queue->add(new Rotate($rotate_param), $transformation_count++);
        }
    }
    
    /**
     * Queue rounded corners operation
     */
    private function queue_rounded_corners_operation($transformation_queue, &$transformation_count): void
    {
        $rounded_corners_param = $this->context->get_param('rounded_corners');
        if ($rounded_corners_param !== null && $rounded_corners_param !== '') {
            $this->utilities_service->debug_message("Queuing rounded corners operation: {$rounded_corners_param}");
            
            $transformation_queue->add(new RoundedCorners($rounded_corners_param), $transformation_count++);
        }
    }

    /**
     * Queue text overlay operation
     */
    private function queue_text_overlay_operation($transformation_queue, &$transformation_count): void
    {
        $text_param = $this->context->get_param('text');
        if ($text_param !== null && $text_param !== '') {
            $this->utilities_service->debug_message("Queuing text overlay operation: {$text_param}");
            
            $transformation_queue->add(new TextOverlay($text_param), $transformation_count++);
        }
    }
    
    /**
     * Queue watermark operation
     */
    private function queue_watermark_operation($transformation_queue, &$transformation_count): void
    {
        $watermark_param = $this->context->get_param('watermark');
        if ($watermark_param !== null && $watermark_param !== '') {
            $this->utilities_service->debug_message("Queuing watermark operation: {$watermark_param}");
            
            $transformation_queue->add(new Watermark($watermark_param), $transformation_count++);
        }
    }
    
    /**
     * Check if stage should be skipped
     * 
     * @param Context $this->context
     * @return bool
     */
    public function should_skip(Context $context): bool 
    {
        // Skip if there are critical errors or early exit is requested
        if ($context->has_critical_error() || $context->should_exit_early()) {
            return true;
        }
        
        // For should_skip method, we need to temporarily cache context to use handle_special_cases
        $original_context = $this->context;
        $this->context = $context;
        
        try {
            // Handle special cases that require dimension calculations but no image processing
            if ($this->handle_special_cases()) {
                return true;
            }
        } finally {
            // Restore original context state
            $this->context = $original_context;
        }
        
        return false;
    }
    
    /**
     * Validate dimension value
     * 
     * @param string $value Dimension value to validate
     * @param int $base_dimension Base dimension for percentage calculations
     * @return int|false Validated dimension in pixels or false if invalid
     */
    private function validate_dimension_value(string $value, int $base_dimension)
    {
        $value = trim($value);
        
        // Handle empty values
        if (empty($value)) {
            return false;
        }
        
        // Handle percentage values
        if (str_ends_with($value, '%')) {
            $percentage = (float)substr($value, 0, -1);
            return $base_dimension ? (int)round($base_dimension * $percentage / 100) : false;
        }
        
        // Handle pixel values (remove 'px' suffix if present)
        if (str_ends_with($value, 'px')) {
            return (int)substr($value, 0, -2);
        }
        
        // Handle zero
        if ($value === '0' || $value === 0) {
            return 0;
        }
        
        // Cast to integer - if not valid integer it will give 0
        $int_value = (int)$value;
        if ($int_value !== 0) {
            return $int_value;
        }
        
        // Invalid value
        return false;
    }
    
    /**
     * Analyze pipeline requirements and run face detection once if needed
     * 
     * This method examines all parameters to determine if face detection will be needed
     * anywhere in the pipeline (crop positioning, face_detect filters, etc.) and if so,
     * runs face detection once on the source image and stores the results in context
     * for reuse by all operations that need it.
     * 
     * @param \Imagine\Image\ImageInterface $source_image Source image to analyze
     * @param Context $this->context Processing context
     */
    private function analyze_and_run_face_detection_if_needed($source_image): void
    {
        // Simple check: if 'face_detect' appears in key parameters, we need face detection
        $crop_param = $this->context->get_param('crop', '');
        $filter_param = $this->context->get_param('filter', '');
        
        if(!$needs_face_detection = str_contains($crop_param, 'face_detect') || str_contains($filter_param, 'face_detect')) {
            return;
        }

        // Determine appropriate sensitivity based on which operations need face detection
        $face_detect_sensitivity = 5; // Default sensitivity
        
        // If crop uses face detection, prefer crop sensitivity (usually 3)
        if (str_contains($crop_param, 'face_detect')) {
            $face_detect_sensitivity = $this->context->get_param('face_detect_sensitivity', 3);
        } else {
            // Filter operations typically use sensitivity 5
            $face_detect_sensitivity = $this->context->get_param('face_detect_sensitivity', 5);
        }
        
        $this->utilities_service->debug_message("Pipeline analysis: Face detection required, running once with sensitivity: {$face_detect_sensitivity}");
        
        try {
            // Get face detection service
            $face_detection_service = \JCOGSDesign\JCOGSImagePro\Service\ServiceCache::face_detection();
            
            // Convert Imagine image to GD resource for face detection
            $gd_resource = imagecreatefromstring($source_image->__toString());
            if (!$gd_resource) {
                $this->utilities_service->debug_message("Failed to convert image to GD resource for face detection");
                return;
            }
            
            // Run face detection
            $face_regions = $face_detection_service->detect_faces($gd_resource, $face_detect_sensitivity);
            
            // Store face regions and source image dimensions in context for reuse
            $source_size = $source_image->getSize();
            $this->context->set_metadata('original_face_regions', $face_regions);
            $this->context->set_metadata('original_image_size', [
                'width' => $source_size->getWidth(),
                'height' => $source_size->getHeight()
            ]);
            
            // Also store in current context for immediate use
            $this->context->set_metadata('face_regions', $face_regions);
            $this->context->set_metadata('face_regions_image_size', [
                'width' => $source_size->getWidth(),
                'height' => $source_size->getHeight()
            ]);
            
            $this->utilities_service->debug_message("Pipeline face detection complete: Found {" . count($face_regions) . "} faces, cached for reuse");
            
            // Clean up GD resource
            imagedestroy($gd_resource);
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Pipeline face detection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Apply preliminary smart-scale processing before face detection
     * 
     * Following Legacy behavior: if smart-scale is enabled for crop operations,
     * apply the resize transformation BEFORE face detection to improve performance.
     * This ensures face detection runs on appropriately sized images.
     * 
     * @param mixed $processed_image Working copy of image (passed by reference)
     * @param Context $this->context Processing context
     */
    private function apply_preliminary_smart_scale_if_needed(&$processed_image): void
    {
        // Only apply smart-scale for crop operations
        if (!$this->context->get_flag('its_a_crop')) {
            return;
        }
        
        // Check if smart-scale is enabled in crop parameters
        $crop_param = $this->context->get_param('crop', '');
        if (empty($crop_param) || substr(strtolower($crop_param), 0, 1) === 'n') {
            return;
        }
        
        // Parse crop parameters to check smart-scale setting
        $parsed_params = $this->parse_crop_parameters($crop_param);
        if (!$parsed_params || !isset($parsed_params['smart_scale']) || $parsed_params['smart_scale'] !== 'y') {
            return;
        }
        
        $this->utilities_service->debug_message("Legacy-style preliminary smart-scale processing enabled");
        
        try {
            // Calculate target dimensions for smart-scale resize
            $target_size = $this->calculate_target_size_for_context(true);
            
            // Apply smart-scale resize using 'cover' mode (matching Legacy behavior)
            $current_size = $processed_image->getSize();
            $cover_dimensions = $this->calculate_fit_dimensions($current_size, $target_size, 'cover');
            
            // User-friendly debug message matching Legacy format
            $this->utilities_service->debug_message(
                lang('jcogs_img_debug_smart_scale_resize'),
                [$target_size->getWidth(), $target_size->getHeight()]
            );
            
            $this->utilities_service->debug_message(sprintf(
                'Preliminary smart-scale resize: %dx%d -> %dx%d (before face detection)',
                $current_size->getWidth(),
                $current_size->getHeight(),
                $cover_dimensions->getWidth(),
                $cover_dimensions->getHeight()
            ));
            
            // Apply the resize transformation
            $processed_image = $processed_image->resize($cover_dimensions);
            
            // Set flag indicating smart-scale has been applied
            $this->context->set_flag('preliminary_smart_scale_applied', true);
            
            // Store the smart-scaled image dimensions for coordinate scaling
            $this->context->set_metadata('smart_scale_dimensions', [
                'width' => $cover_dimensions->getWidth(),
                'height' => $cover_dimensions->getHeight()
            ]);
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Preliminary smart-scale failed: " . $e->getMessage());
            // Continue with original image if smart-scale fails
        }
    }
    
    /**
     * Capture original image metadata before any modifications (Phase 2 optimization)
     * 
     * This method captures all the original metadata needed for template variables
     * and base64_orig generation, enabling complete copy elimination while maintaining
     * full functionality.
     * 
     * @param \Imagine\Image\ImageInterface $source_image Original source image
     */
    private function capture_original_metadata(\Imagine\Image\ImageInterface $source_image): void
    {
        try {
            // Capture original dimensions
            $original_size = $source_image->getSize();
            $this->context->set_metadata('original_width', $original_size->getWidth());
            $this->context->set_metadata('original_height', $original_size->getHeight());
            
            // Capture original file information
            $src_path = $this->context->get_param('src', '');
            if ($src_path) {
                $this->context->set_metadata('extension_orig', pathinfo($src_path, PATHINFO_EXTENSION));
                $this->context->set_metadata('name_orig', pathinfo($src_path, PATHINFO_FILENAME));
                $this->context->set_metadata('path_orig', $src_path);
            }
            
            // Capture raw image data for base64_orig generation (only if not using cache)
            // This is the critical piece that enables base64_orig without keeping source copy
            if (!$this->context->get_flag('using_cache_copy')) {
                try {
                    // Get raw image data by converting image object back to string
                    $save_as = pathinfo($src_path, PATHINFO_EXTENSION) ?: 'jpg';
                    $raw_image_data = $source_image->get($save_as);
                    
                    if ($raw_image_data) {
                        $this->context->set_metadata('image_source_raw', $raw_image_data);
                        $this->context->set_metadata('original_filesize_bytes', strlen($raw_image_data));
                        
                        $this->utilities_service->debug_log(sprintf(
                            'Captured original metadata - %dx%d, %s bytes raw data',
                            $original_size->getWidth(),
                            $original_size->getHeight(),
                            number_format(strlen($raw_image_data))
                        ));
                    }
                } catch (\Exception $e) {
                    $this->utilities_service->debug_log('Could not capture raw image data: ' . $e->getMessage());
                }
            } else {
                $this->utilities_service->debug_log('Skipped raw data capture for cached image');
            }
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_log('Error capturing original metadata: ' . $e->getMessage());
        }
    }
}
