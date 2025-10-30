<?php

/**
 * JCOGS Image Pro Filter
 * ======================
 * A scatter filter for Pro addon with library detection.
 * 
 * @return object $image
 * 
 * CHANGELOG
 * 
 * 14/07/2025: 2.0      Pro addon implementation
 * 
 * =====================================================
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

namespace JCOGSDesign\JCOGSImagePro\Filters;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

/**
 * A Scatter filter with library detection.
 */
class Scatter implements FilterInterface
{
    private int $effect_x;
    private int $effect_y;

    /**
     * Constructs Scatter filter.
     * 
     * @param int $effect_x Horizontal scatter effect (default: 3)
     * @param int $effect_y Vertical scatter effect (default: 3)
     */
    public function __construct(int $effect_x = 3, int $effect_y = 3)
    {
        $this->effect_x = $effect_x;
        $this->effect_y = $effect_y;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(ImageInterface $image, array $params = []): ImageInterface
    {
        // Use constructor parameters, with fallback to apply params for backward compatibility
        $subtraction = $params[0] ?? $this->effect_x;
        $addition = $params[1] ?? $this->effect_y;
        $processed_params = $this->process_scatter_parameters($subtraction, $addition);
        
        // Detect image library and delegate to appropriate implementation
        switch (true) {
            case ($image instanceof \Imagine\Gd\Image): 
                $image = (new Gd\Scatter())->apply($image, $processed_params);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
                // Future: add Imagick implementation
                // $image = (new Imagick\Scatter())->apply($image, $processed_params);
                break;
            case ($image instanceof \Imagine\Gmagick\Image):
                // Future: add Gmagick implementation
                // $image = (new Gmagick\Scatter())->apply($image, $processed_params);
                break;
            default:
                // No suitable implementation available
                break;
        }
        
        return $image;
    }
    
    /**
     * Process scatter parameters to match legacy behavior exactly
     *
     * @param mixed $subtraction Subtraction level
     * @param mixed $addition Addition level
     * @return array Processed scatter parameters (streamlined for performance)
     */
    private function process_scatter_parameters($subtraction, $addition): array
    {
        // Streamlined parameter processing - Legacy doesn't apply range limits
        return [(int) $subtraction, (int) $addition];
    }
}
