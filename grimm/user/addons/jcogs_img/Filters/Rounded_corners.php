<?php

/**
 * JCOGS Image Filter
 * ==================
 * Add rounded corners to image
 * Approach is build a mask based on four corner circles and fill in
 * space between, and then mask the image with what results.
 * 1) unpack parameters
 * 2) calculate dimensions / origins of corners and fill-in rectangles
 * 3) build mask with shapes
 * 4) apply mask to image
 * 5) move masked image back to image
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

class Rounded_corners implements FilterInterface
{
    /**
     * @var array
     */
    private $rounded_corner_working;

    /**
     * Constructs Rounded Corners filter.
     *
     * @param int $rounded_corner_working
     */
    public function __construct(array $rounded_corner_working)
    {
        $this->rounded_corner_working = $rounded_corner_working;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Get the dimensions of image
        $image_size = $image->getSize();
        
        // 2) Calculate mask elements
        $need_to_do_corners = false;
        foreach ($this->rounded_corner_working as $corner => $data) {
            if ($data['radius'] == 0) {
                // nothing to do
                continue;
            }
            // build a mask for this corner
            switch($corner) {
                case 'tl':
                    $this->rounded_corner_working[$corner]['x'] = $data['x'] + $data['radius'];
                    $this->rounded_corner_working[$corner]['y'] = $data['y'] + $data['radius'];
                    $need_to_do_corners = true;
                    break;
                case 'tr':
                    $this->rounded_corner_working[$corner]['x'] = $data['x'] - $data['radius'];
                    $this->rounded_corner_working[$corner]['y'] = $data['y'] + $data['radius'];
                    $need_to_do_corners = true;
                    break;
                case 'bl':
                    $this->rounded_corner_working[$corner]['x'] = $data['x'] + $data['radius'];
                    $this->rounded_corner_working[$corner]['y'] = $data['y'] - $data['radius'];
                    $need_to_do_corners = true;
                    break;
                case 'br':
                    $this->rounded_corner_working[$corner]['x'] = $data['x'] - $data['radius'];
                    $this->rounded_corner_working[$corner]['y'] = $data['y'] - $data['radius'];
                    $need_to_do_corners = true;
                    break;
            }
        }

        // do we have to do anything?
        if (! $need_to_do_corners) {
            return $image;
        }

        // calculate infill masks
        $infill_masks = array(
            'top' => array(
                'x'      => 0 + $this->rounded_corner_working['tl']['radius'],
                'y'      => 0,
                'width'  => $image_size->getWidth() - $this->rounded_corner_working['tl']['radius'] - $this->rounded_corner_working['tr']['radius'],
                'height' => $image_size->getHeight() - max($this->rounded_corner_working['bl']['radius'],$this->rounded_corner_working['br']['radius'])
            ),
            'bottom' => array(
                'x'      => 0 + $this->rounded_corner_working['bl']['radius'],
                'y'      => max($this->rounded_corner_working['tl']['radius'],$this->rounded_corner_working['tr']['radius']),
                'width'  => $image_size->getWidth() - $this->rounded_corner_working['br']['radius'] - $this->rounded_corner_working['bl']['radius'],
                'height' => $image_size->getHeight() - max($this->rounded_corner_working['tl']['radius'],$this->rounded_corner_working['tr']['radius'])
            ),
            'left' => array(
                'x'      => 0,
                'y'      => 0 + $this->rounded_corner_working['tl']['radius'],
                'width'  => $image_size->getWidth() - max($this->rounded_corner_working['tr']['radius'], $this->rounded_corner_working['br']['radius']),
                'height' => $image_size->getHeight() - $this->rounded_corner_working['tl']['radius'] - $this->rounded_corner_working['bl']['radius']
            ),
            'right' => array(
                'x'      => max($this->rounded_corner_working['tl']['radius'], $this->rounded_corner_working['bl']['radius']),
                'y'      => 0 + $this->rounded_corner_working['tr']['radius'],
                'width'  => $image_size->getWidth() - max($this->rounded_corner_working['tl']['radius'], $this->rounded_corner_working['bl']['radius']),
                'height' => $image_size->getHeight() - $this->rounded_corner_working['tr']['radius'] - $this->rounded_corner_working['br']['radius']
            )
        );

        switch (true) {
            case ($image instanceof \Imagine\Gd\Image) : 
                $image = (new \JCOGSDesign\Jcogs_img\Filters\Gd\Rounded_corners_fast($this->rounded_corner_working, $infill_masks))->apply($image);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
            case ($image instanceof \Imagine\Gmagick\Image):
            default:
                break;
    }
        return $image;
    }
}
