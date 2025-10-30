<?php

/**
 * JCOGS Image Filter
 * ==================
 * Reflect image
 * Method
 * Loosely from p24 of https://www.slideshare.net/avalanche123/introduction-toimagine
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
use Imagine\Image\Palette;
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Image\Fill\Gradient;
use Imagine\Image\Point;
use Imagine\Image\Box;

/**
 * A Box Border filter.
 */
class Reflection_slow implements FilterInterface
{
    /**
     * @var ColorInterface
     */
    private $color;

    /**
     * @var int
     */
    private $gap;

    /**
     * @var int
     */
    private $starting_opacity;

    /**
     * @var int
     */
    private $ending_opacity;

    /**
     * @var int
     */
    private $height;

    /**
     * Constructs Reflection filter.
     *
     * @param ColorInterface $color
     * @param int $gap
     * @param int $starting_opacity
     * @param int $ending_opacity
     * @param int $height
     */
    public function __construct(ColorInterface $color, int $height, int $gap = 0, int $starting_opacity = 80, int $ending_opacity = 0)
    {
        $this->color = $color;
        $this->gap = $gap;
        $this->starting_opacity = $starting_opacity;
        $this->ending_opacity = $ending_opacity;
        $this->height = $height;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Create image into which build reflection
        // Work out dimensions
        $size = $image->getSize();
        $reflection_size = new Box($size->getWidth(), $size->getHeight() + $this->gap + $this->height);

        try {
            $canvas = (new Imagine())->create($reflection_size, $this->color);
        } catch(\Imagine\Exception\RuntimeException $e) {
            // Creation of image failed.
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'),$e->getMessage());
            return $image;
        }

        // Create box for cropped image dimensions for reflection
        $reflection_crop = new Box($size->getWidth(), $this->height);
        
        // Generate reflection image
        $reflection = $image->copy()
            ->flipVertically()
            ->crop(new Point(0,0),$reflection_crop)
            ->applyMask(
                (new Imagine())->create($reflection_crop)
                    ->fill(
                        new Gradient\Vertical(
                            $this->height,
                            (new Palette\RGB())->color([127,127,127], $this->starting_opacity),
                            (new Palette\RGB())->color('fff',$this->ending_opacity
                            )
                        )
                    )
            );

        // Paste in original image
        $canvas
            ->paste($image, new Point(0,0))
            ->paste($reflection, new Point(0, $size->getHeight() + $this->gap));

        // Update main image with reflection
        $image = $canvas->copy();

        unset($size);
        unset($reflection_crop);
        unset($canvas);
        unset($reflection);

        return $image;
    }
}
