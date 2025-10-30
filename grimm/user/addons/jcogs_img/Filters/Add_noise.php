<?php

/**
 * JCOGS Image Filter
 * ==================
 * A filter to add random noise to image
 * 
 * Adds a random noise to random selection of pixels image by nudging RGB values by
 * a random amount. Only variable is $level to provide CE-Image backward compatibility
 * Based loosely on https://stackoverflow.com/questions/2727450/how-can-i-make-a-noisy-background-image-using-php 
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
use Imagine\Image\Point;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Filter\Advanced\OnPixelBased;

/**
 * A noise filter using Imagine on-pixel method.
 */
class Add_noise extends OnPixelBased implements FilterInterface

{
    /**
     * @var int
     */
    private $level;

    public function __construct(int $level = 30)
    {
        $this->level = $level;
        parent::__construct(function (ImageInterface $image, Point $point) {
            // Only do the work for a random selection of pixels
            // Use random method from php rand manual page (http://www.php.net/manual/en/function.rand.php)
            if (rand()&1) {
                // Get direction for change
                $direction = rand()&1 ? 1 : -1;
                // Get size of change
                $step = rand()&$this->level;
                // Get random adjustment to colour value
                $adjustment = $step * $direction;

                // Get colour of our pixel
                $color = $image->getColorAt($point);

                // Work out adjusted pixel values
                $r = min(max($color->getValue(ColorInterface::COLOR_RED) + $adjustment, 0),255);
                $g = min(max($color->getValue(ColorInterface::COLOR_GREEN) + $adjustment, 0),255);
                $b = min(max($color->getValue(ColorInterface::COLOR_BLUE) + $adjustment, 0),255);
                $a = min(max($color->getAlpha() + $adjustment, 0),100);

                // Write back new color
                try {
                    $new_color = (new RGB())->color([$r,$g,$b], $a);
                } catch(\Imagine\Exception\RuntimeException $e) {
                    // Creation of colour failed, so skip.
                    ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'),$e->getMessage());
                    return;
                }
                $image->draw()->dot($point, $new_color);
            }
        });
    }
}
