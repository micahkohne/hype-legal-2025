<?php

/**
 * JCOGS Image Filter
 * ==================
 * Apply a generic GD filter
 * 
 * @return object $image
 * 
 * CHANGELOG
 * 
 * 25/03/2023: 1.3.6      First release
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

 use Imagine\Filter\FilterInterface;
 use Imagine\Image\ImageInterface;
 
 
/**
 * Apply a generic GD filter
 */
class Apply_Gd_Filter implements FilterInterface
{
    /**
     * @var string 
     */
    private $gd_filter;

    /**
     * @var array 
     */
    private $gd_filter_settings;

    /**
     * Apply a generic GD filter
     *
     * @param string $gd_filter
     */
    public function __construct(string $gd_filter, array $gd_filter_settings = [])
    {
        $this->gd_filter = $gd_filter;
        $this->gd_filter_settings = $gd_filter_settings;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Convert Imagine image to GD image
        $workingImage = imagecreatefromstring($image->__toString());

        // Check if the GD image was created successfully
        if ($workingImage === false) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_image_creation_failed'));
            return $image;
        }

        // Apply the GD filter to the image
        if (imagefilter($workingImage, constant($this->gd_filter), ...$this->gd_filter_settings)) {
            // Convert the GD image back to Imagine image
            $image = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($workingImage);
        } else {
            // Log the failure message
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagefilter_failed'), [$this->gd_filter => $this->gd_filter_settings]);
        }

        // Free up memory
        imagedestroy($workingImage);

        return $image;
    }
}
