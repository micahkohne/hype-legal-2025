<?php

/**
 * JCOGS Image Filter
 * ==================
 * A simple Sharpen filter.
 * Uses a laplacian filter (similar to but more refined than the one used in CE Image's auto_sharpen.
 * Algorithm etc. from https://iq.opengenus.org/sharpening-filters/
 * 
 * The amount of sharpening varies from none (if image not reduced in size) to 
 * 30 if reduction is more than 80%.
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
 * @version    1.4.16.1
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.3
 */

namespace JCOGSDesign\Jcogs_img\Filters;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Utils\Matrix;

/**
 * A Sharpen filter.
 */
class Sharpen_universal implements FilterInterface
{
    /**
     * @var int
     */
    private $sharpening_value;

    /**
     * Constructs Sharpen filter.
     *
     * @param int $sharpening_value
     */
     public function __construct(float $sharpening_value)
    {
        $this->sharpening_value = $sharpening_value;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        $this->sharpening_value = max(0,min(500,$this->sharpening_value));

        // build matrix
        $min = $this->sharpening_value >= 10 ? $this->sharpening_value * -0.01 : 0;
        $max = $this->sharpening_value * -0.025;
        $abs = ((4 * $min + 4 * $max) * -1) + 1;
        $div = 1;

        $matrix = [
            [$min, $max, $min,$max, $abs, $max,$min, $max, $min]
        ];

        // apply the matrix
        $image->effects()->convolve(new Matrix(3,3,$matrix));

        return $image;
    }
}
