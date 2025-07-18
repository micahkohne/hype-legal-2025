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
 * @version    1.4.16.1
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.3.6
 */

namespace JCOGSDesign\Jcogs_img\Filters\Gd;

use Imagine\Gd\Imagine;
use Imagine\Filter;
use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\Box;
use Imagine\Image\Point;
use JCOGSDesign\Jcogs_img\Filters as Filters;

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
        // Get the GDImage object
        $gd_image = imagecreatefromstring($image->__toString());

        // Get face crop data
        $this->faces = count($this->faces) > 0 ? $this->faces : ee('jcogs_img:ImageUtilities')->face_detection($gd_image, $this->sensitivity);

        // If something found ... 
        if ($this->faces && count($this->faces) > 1) {
            // We have some faces, so let's do something with them!
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_face_detect'), count($this->faces) - 1), $this->faces);

            if ($this->draw_rectangles) {
                // Add face rectangles
                // for each entry in $faces draw a simple bounding rectangle
                // need to add a new 'draw a shape' filter
                // First create our own transformation object for these additions
                $transformation = new Filter\Transformation(new Imagine());
                $outline_colour = ee('jcogs_img:ImageUtilities')->validate_colour_string('#01bf42');
                $face_colour = ee('jcogs_img:ImageUtilities')->validate_colour_string('#eded03');
                $rectangle_colour = $outline_colour;
                $transformation_count = 1;
                foreach ($this->faces as $face) {
                    $transformation->add(new Filters\Draw_rectangle(new Point($face['x'], $face['y']), new Box(max($face['width'], 20), max($face['height'], 20)), $rectangle_colour, 2), $transformation_count++);
                    $rectangle_colour = $face_colour;
                }
                // Apply the filters 
                $image = $transformation->apply($image);   
            }
        } else {
            // No faces found so do nothing
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_face_detect_none'));
        }

        unset($gd_image);
        return $image;
    }
}
