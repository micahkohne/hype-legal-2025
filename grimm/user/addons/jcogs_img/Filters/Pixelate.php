<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Pixelate filter.
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

/**
 * A Pixelate filter.
 */
class Pixelate implements FilterInterface
{
    /**
     * @var int
     */
    private $block_size;

    /**
     * @var bool
     */
    private $mode;

    /**
     * Constructs Pixelate filter.
     *
     * @param int $block_size
     */
    public function __construct(int $block_size, bool $mode = false)
    {
        $this->block_size = $block_size;
        $this->mode = $mode;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        switch (true) {
            case ($image instanceof \Imagine\Gd\Image) : 
                $image = (new \JCOGSDesign\Jcogs_img\Filters\Gd\Pixelate($this->block_size, $this->mode))->apply($image);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
            case ($image instanceof \Imagine\Gmagick\Image):
            default:
                // Do nothing
        }
        return $image;
    }
}
