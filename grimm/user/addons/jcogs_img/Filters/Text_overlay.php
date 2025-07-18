<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Text Overlay filter.
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

/**
 * A Text Overlay filter.
 */
class Text_overlay implements FilterInterface 
{
    /**
     * @var string|null
     */
    private $text_param_string;

    /**
     * Constructs Text Overlay filter.
     *
     * @param int $block_size
     */
    public function __construct(string $text_param_string = null)
    {
        $this->text_param_string = $text_param_string;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // No params? Return
        if (!$this->text_param_string) {
            return $image;
        }

        // No cat? Return
        if (!$this->text_param_string) {
            return $image;
        }

        switch (true) {
            case ($image instanceof \Imagine\Gd\Image) : 
                $image = (new Gd\Text_overlay($this->text_param_string))->apply($image);
                break;
            case ($image instanceof \Imagine\Imagick\Image):
            case ($image instanceof \Imagine\Gmagick\Image):
            default:
                // Do nothing
        }
        return $image;
    }
}
