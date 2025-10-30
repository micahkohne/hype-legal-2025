<?php

/**
 * JCOGS Image Filter
 * ==================
 * An Unsharp Mask filter.
 * Applies an unsharp mask to an image
 * Uses algorithm / code derived from: 
 * http://phpthumb.sourceforge.net/index.php?source=phpthumb.unsharp.php
 * 
 * Unsharp mask algorithm by Torstein Hønsi 2003-07. thoensi_at_netcom_dot_no.
 * Please leave this notice.
 * 
 * This method has been modified by JCOGS Design to preserve transparency.
 * 
 * Unsharp masking is a traditional darkroom technique that has proven very suitable for
 * digital imaging. The principle of unsharp masking is to create a blurred copy of the image
 * and compare it to the underlying original. The difference in colour values between the two 
 * images is greatest for the pixels near sharp edges. When this difference is subtracted from 
 * the original image, the edges will be accentuated.
 * 
 * The Amount parameter simply says how much of the effect you want. 100 is 'normal'.
 * Radius is the radius of the blurring circle of the mask. 'Threshold' is 
 * 
 * @param int $sharpening_value - how much of the effect you want - default 80 - typical range 50->200
 * @param float $radius - radius of the blurring circle of the mask - default 0.5 - typical range 0.5-1
 * @param int $threshold - the least difference in colour values that is allowed between the original
 * and the mask. In practice this means that low-contrast areas of the picture are left unrendered
 * whereas edges are treated normally. This is good for pictures of e.g. skin or blue skies. 
 * - default 3, typical range 0-5
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

namespace JCOGSDesign\Jcogs_img\Filters\Gd;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;

/**
 * An Unsharp Mask filter.
 */
class Unsharp_mask implements FilterInterface
{
    /**
     * @var int
     */
    private $sharpening_value;

    /**
     * @var float
     */
    private $radius;

    /**
     * @var int
     */
    private $threshold;

    /**
     * Constructs Sharpen filter.
     *
     * @param int $sharpening_value
     * @param float $radius
     * @param int $threshold
     */
    public function __construct(int $sharpening_value = 80, float $radius = 0.5, int $threshold = 3)
    {
        $this->sharpening_value = $sharpening_value;
        $this->radius = $radius;
        $this->threshold = $threshold;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Imagine\Filter\FilterInterface::apply()
     */
    public function apply(ImageInterface $image): ImageInterface
    {
        // Run unsharp mask filter
        // Get the GDImage object
        $img = imagecreatefromstring($image->__toString());

        // Attempt to calibrate the parameters to Photoshop:
        $this->sharpening_value = min($this->sharpening_value, 500) * 0.016;
        $this->radius = abs(round(min(50, $this->radius) * 2)); // Only integers make sense.
        $this->threshold = min(255, $this->threshold);
        if ($this->radius == 0) {
            return $image;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $imgCanvas = imagecreatetruecolor($w, $h);
        $imgBlur   = imagecreatetruecolor($w, $h);

        // Gaussian blur matrix:
        $matrix = array(
            array(1, 2, 1),
            array(2, 4, 2),
            array(1, 2, 1)
        );
        imagecopy($imgBlur, $img, 0, 0, 0, 0, $w, $h);
        imageconvolution($imgBlur, $matrix, 16, 0);

        if ($this->threshold > 0) {
            // Calculate the difference between the blurred pixels and the original
            // and set the pixels
            for ($row = 0; $row < $h - 1; $row++) { // each row
                for ($col = 0; $col < $w; $col++) { // each pixel

                    $rgbOrig = imagecolorat($img, $col, $row);
                    $rOrig = round((($rgbOrig >> 16) & 0xFF),0);
                    $gOrig = round((($rgbOrig >>  8) & 0xFF),0);
                    $bOrig = round( ($rgbOrig        & 0xFF),0);
                    $aOrig = round( ($rgbOrig & 0x7F000000) >> 24,0);
                    
                    $rgbBlur = imagecolorat($imgBlur, $col, $row);
                    
                    $rBlur = round((($rgbBlur >> 16) & 0xFF),0);
                    $gBlur = round((($rgbBlur >>  8) & 0xFF),0);
                    $bBlur = round( ($rgbBlur        & 0xFF),0);
                    $aBlur = round( ($rgbBlur & 0x7F000000) >> 24,0);

                    // When the masked pixels differ less from the original
                    // than the threshold specifies, they are set to their original value.
                    $rNew = ((abs($rOrig - $rBlur) >= $this->threshold) ? max(0, min(255, round($this->sharpening_value * ($rOrig - $rBlur),0) + $rOrig)) : $rOrig);
                    $gNew = ((abs($gOrig - $gBlur) >= $this->threshold) ? max(0, min(255, round($this->sharpening_value * ($gOrig - $gBlur)) + $gOrig),0) : $gOrig);
                    $bNew = ((abs($bOrig - $bBlur) >= $this->threshold) ? max(0, min(255, round($this->sharpening_value * ($bOrig - $bBlur),0) + $bOrig)) : $bOrig);
                    $aNew = $aOrig;

                    if (($rOrig != $rNew) || ($gOrig != $gNew) || ($bOrig != $bNew)) {
                        $pixCol = imagecolorallocatealpha($img, $rNew, $gNew, $bNew, $aNew);
                        imagesetpixel($img, $col, $row, $pixCol);
                    }
                }
            }
        } else {
            for ($row = 0; $row < $h - 1; $row++) { // each row
                for ($col = 0; $col < $w; $col++) { // each pixel
                    $rgbOrig = imagecolorat($img, $col, $row);
                    $rOrig = round((($rgbOrig >> 16) & 0xFF),0);
                    $gOrig = round((($rgbOrig >>  8) & 0xFF),0);
                    $bOrig = round( ($rgbOrig        & 0xFF),0);
                    $aOrig = round( ($rgbOrig & 0x7F000000) >> 24,0);

                    $rgbBlur = imagecolorat($imgBlur, $col, $row);

                    $rBlur = round((($rgbBlur >> 16) & 0xFF),0);
                    $gBlur = round((($rgbBlur >>  8) & 0xFF),0);
                    $bBlur = round( ($rgbBlur        & 0xFF),0);
                    $aBlur = round( ($rgbBlur & 0x7F000000) >> 24,0);

                    $rNew = min(255, max(0, ($this->sharpening_value * ($rOrig - $rBlur)) + $rOrig));
                    $gNew = min(255, max(0, ($this->sharpening_value * ($gOrig - $gBlur)) + $gOrig));
                    $bNew = min(255, max(0, ($this->sharpening_value * ($bOrig - $bBlur)) + $bOrig));
                    $aNew = $aOrig;
                    $pixCol = imagecolorallocatealpha($img, $rNew, $gNew, $bNew, $aNew);
                    imagesetpixel($img, $col, $row, $pixCol);
                }
            }
        }

        $image = (ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($img))->copy();

        unset($imgCanvas);
        unset($imgBlur);
        unset($img);

        return $image;

    }
}
