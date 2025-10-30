<?php

/**
 * JCOGS Image Pro - Contrast Filter
 * ==================================
 * Contrast adjustment filter with library detection
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Filter Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Filters;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

/**
 * Contrast Filter
 * 
 * Adjusts image contrast with library detection and delegation.
 * Automatically detects the image library in use and delegates to
 * the appropriate optimized implementation for best performance.
 */
class Contrast implements FilterInterface
{
    /**
     * @var int Contrast level (-100 to 100)
     */
    private $level;

    /**
     * Constructs Contrast filter
     * 
     * @param int $level Contrast adjustment level (-100 to 100)
     */
    public function __construct(int $level = 0)
    {
        $this->level = $level;
        // No parameters in constructor - use runtime parameters
    }

    /**
     * Apply contrast filter to image
     * 
     * @param ImageInterface $image The image to adjust contrast for
     * @return ImageInterface The contrast-adjusted image
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Use contrast level from constructor
        $level = $this->process_contrast_parameter($this->level);
        
        // Detect image library and delegate to appropriate implementation
        switch (true) {
            case ($image instanceof \Imagine\Gd\Image): 
                $image = (new Gd\Contrast())->apply($image, [$level]);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
                // Future: add Imagick implementation
                // $image = (new Imagick\Contrast())->apply($image, [$level]);
                break;
            case ($image instanceof \Imagine\Gmagick\Image):
                // Future: add Gmagick implementation
                // $image = (new Gmagick\Contrast())->apply($image, [$level]);
                break;
            default:
                // No suitable implementation available
                break;
        }
        
        return $image;
    }
    
    /**
     * Process contrast parameter to match legacy behavior exactly
     *
     * @param mixed $value Raw parameter value
     * @return int Processed contrast value
     */
    private function process_contrast_parameter($value): int
    {
        // Convert to integer
        $value = (int) $value;
        
        // Clamp to range -100 to 100 (legacy validation)
        $value = max(-100, min(100, $value));
        
        // Remove double negation - the GD implementation handles inversion internally
        // Legacy processes this correctly, Pro was doing double negation
        return $value;
    }
}
