<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Fast method to add symmetric border around an image.
 * 
 * Method
 * Make a new image bigger than original by 2x width of border filled with border colour
 * Paste original image into new image offset by 1x width of border
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
use Imagine\Gd\Imagine;
use Imagine\Image\Palette\Color\RGB;
use Imagine\Image\Point;
use Imagine\Image\Box;

/**
 * A Box Border filter.
 */
class Box_border implements FilterInterface
{
    /**
     * @var int
     */
    private $border_width;

    /**
     * @var RGB
     */
    private $color;

    /**
     * Constructs Box Border filter.
     *
     * @param int $border_width
     * @param RGB $color
     */
    public function __construct(int $border_width = 0, RGB $color = null)
    {
        $this->border_width = $border_width;
        $this->color = $color;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Did we get some parameters?
        if($this->border_width == 0 || !$this->color) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_adding_border_failed'),['width' => $this->border_width, 'color' => $this->color]);
            return $image;
        }

        // Get the dimensions of image
        $image_size = $image->getSize();
        $image_width = $image_size->getWidth();
        $image_height = $image_size->getHeight();

        // Get the dimensions of the image after border added;
        $new_image_width = $image_width + (2 * $this->border_width);
        $new_image_height = $image_height + (2 * $this->border_width);
        $new_image_size = new Box($new_image_width, $new_image_height);
        
        // Create a larger image with appropriate background colour
        try {
            $temp_image = (new Imagine())->create($new_image_size, $this->color);
        } catch(\Imagine\Exception\RuntimeException $e) {
            // Creation of image failed.
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'),$e->getMessage());
            return $image;
        }

        // Paste original image onto temp image
        $temp_image->paste($image, new Point($this->border_width, $this->border_width));

        // Replace original image with updated image
        $image = $temp_image->copy();
        unset($temp_image);

        return $image;
    }
}
