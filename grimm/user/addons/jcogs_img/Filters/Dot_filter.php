<?php

/**
 * JCOGS Image Filter
 * ==================
 * A half-tone filter
 * Uses basic principles of Floyd-Steinberg Dithering, adapted for 
 * speed and compatibility with CE Image's Dot filter options.
 * More here https://en.wikipedia.org/wiki/Floyd%E2%80%93Steinberg_dithering
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
use Imagine\Image\Palette\Color\RGB;
use Imagine\Gd\Imagine;
use Imagine\Image\Point;
use Imagine\Image\Palette\Color\ColorInterface;

/**
 * A half-tone filter.
 */
class Dot_filter implements FilterInterface
{
    /**
     * @var string
     */
    private $block;

    /**
     * @var RGB
     */
    private $color;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $multiplier;

    public function __construct(string $block = '6', RGB $color = null, string $type = 'circle', string $multiplier = '1')
    {
        $this->block = $block == '' ? 6 : intval($block);
        $this->color = $color;
        $this->type = $type;
        $this->multiplier = $multiplier == '' ? 1 : floatval($multiplier);
    }
    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        $image_size = $image->getSize();

        // First we cheat and shrink image by factor given as $block - using the resize function
        // to give us the average colour for each pixel rather than sum / average of individual pixels

        $reduced_image = $image->resize($image_size->widen(round($image_size->getWidth()/$this->block,0)));

        // Now we make a working image to hold the half-tone filtered image
        try {
            $working_image = (new Imagine())->create($image_size);
        } catch(\Imagine\Exception\RuntimeException $e) {
            // Creation of image failed.
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'),$e->getMessage());
            return $image;
        }

        // Now we scan the reduced image to determine the colour and size of the 'dot' to add to 
        // the working image

        $size = $reduced_image->getSize();
        $w = $size->getWidth();
        $h = $size->getHeight();
        $pixel_count = $w*$h;
        for ($i = 0; $i < $pixel_count; $i++) {
            $x = $i % $w; // counts up to each row limit and then starts again ... 
            $y = (int) ($i / $w); // (int) rounds down - so this gives number of completed rows ... 
            $this->callback($reduced_image, $working_image, new Point($x, $y), $this->block, $this->color, $this->type, $this->multiplier);
        }

        $image = $working_image->copy();
        unset($reduced_image);
        unset($working_image);
        return $image;
    }

    /**
     * Callback function for image processing.
     *
     * @param ImageInterface $reduced_image The reduced image being processed.
     * @param ImageInterface $working_image The working image being processed.
     * @param Point $point The point at which the callback is applied.
     * @param int $block The block size for the callback.
     * @param RGB|null $color The color used in the callback, if applicable.
     * @param string $type The type of processing being applied.
     * @param float $multiplier The multiplier used in the processing.
     *
     * @return void
     */
    private function callback(ImageInterface $reduced_image, ImageInterface $working_image, Point $point, int $block, RGB $color = null, string $type, float $multiplier) {

        // Set the fudge factor ... 
        $fudge_factor = 1.2;

        // Set the circle size fudge factor ... 
        $circle_factor = 1.2;

        // Get colour of our pixel
        $pixel_color = $reduced_image->getColorAt($point);

        // Get average intensity of pixel
        $intensity = (255-($pixel_color->grayscale())->getValue(ColorInterface::COLOR_RED))/255 * $multiplier * $fudge_factor;

        // Work out what colour to use for the dot to be written
        // If not specified, use the colour from pixel
        $colour_for_point = $color ?: $pixel_color;

        // Work out where to write the dot
        $new_x = min(max($point->getX()*$block, 0),($working_image->getSize())->getWidth());
        $new_y = min(max($point->getY()*$block, 0),($working_image->getSize())->getHeight());

        // Draw the dot if it is not zero sized
        $nudge = round($block/2,0);
        $radius = round($nudge*$intensity,0);
        if($radius && strtolower(substr($type,0,1)) == 's') {
            $working_image->draw()->rectangle(
                new Point($new_x + $radius + $nudge, $new_y + $radius + $nudge), 
                new Point($new_x + $radius*2 + $nudge, $new_y + $radius*2 + $nudge),
                $colour_for_point, 
                true);
        } elseif ($radius) {
            $radius = round(($block/2)*$intensity*$circle_factor,0);
            $working_image->draw()->circle(new Point($new_x + $radius + $nudge, $new_y + $radius + $nudge), $radius, $colour_for_point, true);
        }
    }
}
