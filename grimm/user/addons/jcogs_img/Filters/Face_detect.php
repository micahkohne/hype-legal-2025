<?php

/**
 * JCOGS Image Filter
 * ==================
 * An Face Detection Filter
 * 
 * @return object $image
 * 
 * CHANGELOG
 * 
 * 23/03/2023: 1.3.6     First release
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
 * @since      File available since Release 1.3.6
 */

namespace JCOGSDesign\Jcogs_img\Filters;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

/**
 * A Face Detection filter.
 */
class Face_detect implements FilterInterface
{
    /**
     * @var int
     */
    private $sensitivity;

    /**
     * @var bool
     */
    private $draw_rectangles;

    /**
     * @var array
     */
    private $faces;

    /**
     * Constructs Face_detect filter.
     *
     */
    public function __construct(int $sensitivity, bool $draw_rectangles, array $faces)
    {
        $this->sensitivity = $sensitivity;
        $this->draw_rectangles = $draw_rectangles;
        $this->faces = $faces;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // What kind of image do we have
        $image_type = get_class($image);

        switch (true) {
            case ($image instanceof \Imagine\Gd\Image) : 
                $image = (new Gd\Face_detect($this->sensitivity, $this->draw_rectangles, $this->faces))->apply($image);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
            case ($image instanceof \Imagine\Gmagick\Image):
            default:
                // Do nothing
        }

        return $image;
    }
}
