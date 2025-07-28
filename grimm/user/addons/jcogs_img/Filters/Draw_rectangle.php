<?php

/**
 * JCOGS Image Filter
 * ==================
 * A draw rectangle filter
 * Overlays a rectangle on an image
 * 
 * @return object $image
 * 
 * CHANGELOG
 * 
 * 15/03/2023: 1.3.6    First release
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
use Imagine\Image\Palette\Color\RGB;
use Imagine\Image\Point;
use Imagine\Image\Box;

/**
 * A filter to overlay a rectangle on an image.
 */
class Draw_rectangle implements FilterInterface
{
    /**
     * @var RGB
     */
    private $colour;

    /**
     * @var Box
     */
    private $rectangle;

    /**
     * @var Point
     */
    private $position;

    /**
     * @var int
     */
    private $thickness;

    /**
     * Constructs Draw_rectangle filter.
     *
     * @param RGB $colour
     * @param Point $position
     * @param Box $rectangle
     */
    public function __construct(Point $position, Box $rectangle, RGB $colour, int $thickness = 1)
    {
        $this->position = $position;
        $this->rectangle = $rectangle;
        $this->colour = $colour;
        $this->thickness = $thickness;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Create drawing layer for $mask_image
        $image_draw = $image->draw();

        // Draw the rectangle onto the image
        $image_draw->rectangle($this->position, new Point($this->position->getX() + $this->rectangle->getWidth(), $this->position->getY() + $this->rectangle->getHeight()), $this->colour, false, $this->thickness);

        return $image;
    }
}
