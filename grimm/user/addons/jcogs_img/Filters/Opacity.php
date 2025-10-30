<?php

/**
 * JCOGS Image Filter
 * ==================
 * An Opacity filter.
 * Adjusts the opacity of an image
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

class Opacity implements FilterInterface
{
    /**
     * @var int
     */
    private $opacity;

    /**
     * Constructs Opacity filter.
     *
     * @param int $opacity
     */
    public function __construct(int $opacity = 100)
    {
        $this->opacity = $opacity;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Get the GDImage object
        $img = imagecreatefromstring($image->__toString());

        // Get image size
        $size = $image->getSize();

        // 1) Get a value for opacity
        // Imagecopymerge uses pct where 0 = transparent and 100 = opaque.
        $given_opacity = max(min($this->opacity,100),0);

        // 2) Create a temporary empty image using dimensions for processed image
        // Set image bg_colour to transparent
        $opacity_image = imagecreatetruecolor($size->getWidth(),$size->getHeight());
        $backgroundColor = imagecolorallocatealpha($opacity_image,0,0,0,127);
        imagefill($opacity_image, 0, 0, $backgroundColor);
          
        // 3) Adjust opacity during copy operation
        $opacity_image = ee('jcogs_img:ImageUtilities')->imagecopymerge_alpha($opacity_image, $img, 0, 0, 0, 0, $size->getWidth(), $size->getHeight(), $this->opacity);

        // 4) As we are working with opacity, set savealpha true
        imagesavealpha($opacity_image, true);

        // 5) Replace original image with updated image
        $image = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($opacity_image);
        unset($img);
        unset($opacity_image);
        unset($given_opacity);
        unset($backgroundColor);

        return $image;
    }
}
