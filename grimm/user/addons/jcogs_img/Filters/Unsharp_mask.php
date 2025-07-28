<?php

/**
 * JCOGS Image Filter
 * ==================
 * An Unsharp Mask filter.
 * Applies an unsharp mask to an image
 * Uses algorithm / code derived from: 
 * http://phpthumb.sourceforge.net/index.php?source=phpthumb.unsharp.php
 * 
 * Unsharp mask algorithm by Torstein Hønsi 2003-07. thoensi_at_netcom_dot_no.
 * Please leave this notice.
 * 
 * This method has been modified by JCOGS Design to preserve transparency.
 * 
 * Unsharp masking is a traditional darkroom technique that has proven very suitable for
 * digital imaging. The principle of unsharp masking is to create a blurred copy of the image
 * and compare it to the underlying original. The difference in colour values between the two 
 * images is greatest for the pixels near sharp edges. When this difference is subtracted from 
 * the original image, the edges will be accentuated.
 * 
 * The Amount parameter simply says how much of the effect you want. 100 is 'normal'.
 * Radius is the radius of the blurring circle of the mask. 'Threshold' is 
 * 
 * @param int $sharpening_value - how much of the effect you want - default 80 - typical range 50->200
 * @param float $radius - radius of the blurring circle of the mask - default 0.5 - typical range 0.5-1
 * @param int $threshold - the least difference in colour values that is allowed between the original
 * and the mask. In practice this means that low-contrast areas of the picture are left unrendered
 * whereas edges are treated normally. This is good for pictures of e.g. skin or blue skies. 
 * - default 3, typical range 0-5
 * 
 * @return object $image
 * 
 * CHANGELOG
 * 
 * 12/12/2022: 1.3      First release
 * 
 * =====================================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.4.16.2
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.3
 */

namespace JCOGSDesign\Jcogs_img\Filters;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface; 

class Unsharp_mask implements FilterInterface
{
    /**
     * @var int
     */
    private $sharpening_value;

    /**
     * @var float
     */
    private $radius;

    /**
     * @var int
     */
    private $threshold;

    /**
     * Constructs Sharpen filter.
     *
     * @param int $sharpening_value
     * @param float $radius
     * @param int $threshold
     */
    public function __construct(int $sharpening_value = 80, float $radius = 0.5, int $threshold = 3)
    {
        $this->sharpening_value = $sharpening_value;
        $this->radius = $radius;
        $this->threshold = $threshold;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // What kind of image do we have
        switch (true) {
            case ($image instanceof \Imagine\Gd\Image) : 
                $image = (new Gd\Unsharp_mask($this->sharpening_value, $this->radius, $this->threshold))->apply($image);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
            case ($image instanceof \Imagine\Gmagick\Image):
            default:
            // Do nothing
                break;
    }

        return $image;
    }
}
