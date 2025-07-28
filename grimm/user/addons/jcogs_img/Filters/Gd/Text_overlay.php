<?php

/**
 * JCOGS Image Filter
 * ==================
 * A Text Overlay filter.
 * Add a text-overlay to an image
 * Approach is to create a new image of the size specified (or size of current image)
 * Add text to this image with main text options specified
 * Create a second image with shadow text options specified
 * Insert shadow text into current image
 * Insert main text into current image
 * 
 * @return object $image
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

namespace JCOGSDesign\Jcogs_img\Filters\Gd;

use Imagine\Filter\FilterInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette\RGB;
use Imagine\Gd\Imagine;
use Imagine\Image\Point;
use Imagine\Image\PointSigned;
use Imagine\Image\Box;

/**
 * A Text Overlay filter.
 */
class Text_overlay implements FilterInterface
{
    /**
     * @var string
     */
    private $text_param_string;

    /**
     * @var string
     */
    private $text_params;

    /**
     * Constructs Text Overlay filter.
     *
     * @param array $text_overlay_parameter_array
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
        // Don't do anything if we have no parameters

        if(!$this->text_param_string) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_no_parameters_for_add_text_overlay'));
            return $image;
        }

        // 1) Unpack parameters
        // ====================

        $this->text_params = explode('|',$this->text_param_string);

        // Get the size of the image we are processing
        $image_size = $image->getSize();
        $image_width = $image_size->getWidth();
        $image_height = $image_size->getHeight();
        
        // CE Image reference for params - https://docs.causingeffect.com/expressionengine/ce-image/user-guide/parameters.html#text
            // Parameter list:
            // param # | name | value type
            // 0 | the_text string
            // 1 | ​minimum_dimensions | x,y
            // 2​ | font_size int
            // 3​ | line_height float or %
            // 4​ | font_color colour
            // 5​ | font_src path
            // 6​ | text_align left|center|right
            // 7​ | width_adjustment dimension
            // 8​ | position left|center|right, top|center|bottom
            // 9​ | offset x,y
            // 10| ​opacity 0 < int < 100
            // ​11| shadow_color colour
            // ​12| shadow_offset x,y
            // ​13| shadow_opacity 0 < int < 100
            // 14| new - text_box bg_color colour
            // 15| new - text bg_color colour
            // 16| new - text block rotation


        // Don't do anything if we have no text to add

        if(!strlen(trim($this->text_params[0]))) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_no_parameters_for_add_text_overlay'));
            return $image;
        }

        // Should we be abandoning this due to image size limitation?
        $minimum_dimensions = explode(',',$this->text_params[1]);
        $minimum_width = isset($minimum_dimensions[0]) ? ee('jcogs_img:ImageUtilities')->validate_dimension($minimum_dimensions[0],(int) $image_width) : 0;
        $minimum_height = isset($minimum_dimensions[1]) ? ee('jcogs_img:ImageUtilities')->validate_dimension($minimum_dimensions[1],(int) $image_height) : 0;

        // Don't do anything if image is smaller than minimum size threshold
        if($minimum_width > $image_width || $minimum_height > $image_height) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_too_small_to_add_text_overlay'));
            return $image;
        }

        // Still here... so prepare the text for insertion by...
        // 1) Fixing the EOL characters
        $content = str_replace('\n',PHP_EOL,$this->text_params[0]);
        $content = str_replace('<br />',PHP_EOL,$content);
        $content = str_replace('<br>',PHP_EOL,$content);
        $content = str_replace('</p>',PHP_EOL,$content);

        // 2) Dumping any other HTML or php tags
        $content = strip_tags($content);

        // 3) Converting html entities back to ASCII
        ee()->load->helper('text'); 
        $content = entities_to_ascii($content);

        // 4) Clearing out any &nbsp; patterns
        $content = str_replace('&nbsp;',' ',$content);

        // 5) Trim any junk spaces
        $content = preg_replace('/^\s*(.*?)\s*$/m','$1',$content);

        // If we don't have any text any more bale ... 
        if (strlen($content) == 0) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_no_parameters_for_add_text_overlay'));
            return $image;
        }

        // 6) Work out some of the parameters of box to drop our text into
        // Text width adjustments:
        // if value positive, use as text width (max 100%)
        // if value negative, subtract value from width of processed image (max -100%)
        if(isset($this->text_params[7])) {
            if(($width_adjustment = ee('jcogs_img:ImageUtilities')->validate_dimension($this->text_params[7],(int) $image_width)) > 0) {
                // positive value - use as text width
                $width_adjustment =  min($width_adjustment,$image_width);
            } else {
                // negative value - width is image width - adjustment
                $width_adjustment =  min(max($image_width + $width_adjustment,0),$image_width);
            }
        }
        $box_bg_color = isset($this->text_params[14]) && is_string($this->text_params[14]) && strlen($this->text_params[14]) ? ee('jcogs_img:ImageUtilities')->validate_colour_string($this->text_params[14]) :  ee('jcogs_img:ImageUtilities')->validate_colour_string('rgba(0,0,0,0)');

        $bg_color = isset($this->text_params[15]) && is_string($this->text_params[15]) && strlen($this->text_params[15]) ? ee('jcogs_img:ImageUtilities')->validate_colour_string($this->text_params[15]) :  ee('jcogs_img:ImageUtilities')->validate_colour_string('rgba(0,0,0,0)');
        
        $rotation = isset($this->text_params[16]) && is_string($this->text_params[16]) && strlen($this->text_params[16]) ? intval($this->text_params[16]) : 0;

        // 7) Get some details of the text format 
        // (font-size / line-height / font-colour / font / alignment / opacity)
        $font_size = isset($this->text_params[2]) && $this->text_params[2] != '' ? ee('jcogs_img:Utilities')->validate_font_size($this->text_params[2]) : 12;
        $line_height = isset($this->text_params[3]) && strlen($this->text_params[3]) ? round(ee('jcogs_img:ImageUtilities')->validate_dimension($this->text_params[3], (int) $font_size)/$font_size,2) : round(15/12,2);
        $font_colour = isset($this->text_params[4]) && is_string($this->text_params[4]) && strlen($this->text_params[4]) ? ee('jcogs_img:ImageUtilities')->validate_colour_string($this->text_params[4]) : ee('jcogs_img:ImageUtilities')->validate_colour_string('rgba(0,0,0,1)');
        $align = isset($this->text_params[6]) && strlen($this->text_params[6]) ? $this->text_params[6] : 'center';
        $opacity = isset($this->text_params[10]) && strlen($this->text_params[10]) ? round(abs(intval($this->text_params[10]))/100,2) : 1;
        $opacity = max(min($opacity,1),0);
        // We not only need to check that the font source if specified exists, but also that it is a valid font file... 
        // Uses method from https://stackoverflow.com/a/15587541/6475781
        // Get the alternative font file if it exists
        if (isset($this->text_params[5]) && strlen($this->text_params[5]) > 5 && function_exists('finfo_open')) {
            $font_src = rtrim(ee('jcogs_img:Utilities')->path($this->text_params[5]),'/');
            // Valid font formats
            $mimeTypes = array('font/ttf','font/truetype','font/sfnt');
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            // Get mime type of font file
            $mime = finfo_file($finfo, $font_src);
            // if it is not valid, substitute our default 
            if(!in_array($mime, $mimeTypes)){
                $font_src = PATH_THIRD.'jcogs_img/fonts/Voces-Regular.ttf';
            }
            finfo_close($finfo);
        } else {
            $font_src = PATH_THIRD.'jcogs_img/fonts/Voces-Regular.ttf';
        }

        // 8) Fit text into the box
        list($content,$row_count) = ee('jcogs_img:ImageUtilities')->pixel_word_wrap($content, $width_adjustment, $font_size, $font_src, $line_height*$font_size);

        // 9) Get information about text box position in image space
        $position = isset($this->text_params[8]) && strlen($this->text_params[8]) ? explode(',',$this->text_params[8]) : null;
        $position_horizontal = isset($position[0]) ? $position[0] : 'center';
        $position_vertical = isset($position[1]) ? $position[1] : 'center';

        $offset = isset($this->text_params[9]) && strlen($this->text_params[9]) ? explode(',',$this->text_params[9]) : null;
        $offset_horizontal = isset($offset[0]) ? ee('jcogs_img:ImageUtilities')->validate_dimension($offset[0],(int) $image_width) : 0;
        $offset_vertical = isset($offset[1]) ? ee('jcogs_img:ImageUtilities')->validate_dimension($offset[1],(int) $image_height) : 0;

        // 10) Get information about text shadow
        $shadow_colour = isset($this->text_params[11]) && strlen($this->text_params[11]) ? ee('jcogs_img:ImageUtilities')->validate_colour_string($this->text_params[11]) : null;
        $shadow_opacity = isset($this->text_params[13]) && strlen($this->text_params[13]) ? round(abs(intval($this->text_params[13]))/100,2) : false;
        $shadow_opacity = min($shadow_opacity,1);
            
        // Only set the $shadow_offset if we have a $shadow_colour
        if($shadow_colour) {
            $shadow_offset =  isset($this->text_params[12]) && strlen($this->text_params[12]) ? explode(',',$this->text_params[12]) : null;
            $shadow_offset_horizontal = isset($shadow_offset[0]) ? ee('jcogs_img:ImageUtilities')->validate_dimension($shadow_offset[0],(int) $image_width) : 1;
            $shadow_offset_vertical = isset($shadow_offset[1]) ? ee('jcogs_img:ImageUtilities')->validate_dimension($shadow_offset[1],(int) $image_width) : 1;
        } else {
            $shadow_offset = null;
        }

        // 11) Update text font colours to reflect opacity value given for text
        $font_colour =  ee('jcogs_img:ImageUtilities')->validate_colour_string('rgba('.$font_colour->getRed().','.$font_colour->getGreen().','.$font_colour->getBlue().','.$opacity.')');
        $shadow_colour = $shadow_colour ? ee('jcogs_img:ImageUtilities')->validate_colour_string($shadow_colour,$shadow_opacity) : null;

        // Preparation done, so now create the text insertion
        // ==================================================

        // No point doing this if box width is zero ... 
        if ($width_adjustment == 0) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_box_width_adjusted_to_zero'));
            return $image;
        }

        // Make an image size / shape of text box we want
        // Work out likely dimensions of text box
        $text_box_size = new Box($width_adjustment, $row_count*$line_height*$font_size);

        try {
            $empty_box = (new Imagine())->create($text_box_size, $box_bg_color);
        } catch(\Imagine\Exception\RuntimeException $e) {
            // Creation of image failed.
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'),$e->getMessage());
            return $image;
        }

        // 1) Create a temporary empty image using dimensions for text
        // Set image bg_colour according to parameter
        $canvas = imagecreatefromstring($empty_box->__toString());
        
        // 2) Create a box for text
        $box = new \GDText\Box($canvas);
        $box->setBackgroundColor(new \GDText\Color($bg_color->getRed(), $bg_color->getGreen(), $bg_color->getBlue(), 127-$bg_color->getAlpha()));
        $box->setFontFace($font_src);
        $box->setFontColor(new \GDText\Color($font_colour->getRed(), $font_colour->getGreen(), $font_colour->getBlue(), 127-$font_colour->getAlpha()));
        if ($shadow_colour) {
            $box->setTextShadow(new \GDText\Color($shadow_colour->getRed(), $shadow_colour->getGreen(), $shadow_colour->getBlue(), 127-$shadow_colour->getAlpha()),$shadow_offset_horizontal,$shadow_offset_vertical);
        }
        $box->setFontSize($font_size);
        $box->setLineHeight($line_height);
        //$box->enableDebug();
        $box->setBox(0,0,$width_adjustment, $row_count*$line_height*$font_size);
        // in setTextAlign vAlign always 'top' - as box is same height as text doesn't matter what this value is.
        $box->setTextAlign($align, 'top'); 
        
        // 3) Add text to the box
        $box->draw($content);

        // 4) Rotate text if specified
        $canvas = imagerotate($canvas, $rotation, imageColorAllocateAlpha($canvas, 0, 0, 0, 127));

        // Convert back to Imagine object
        $text_box = ee('jcogs_img:ImageUtilities')->convert_GDImage_object_to_image($canvas);

        // Update text-box dimensions based on what happened...
        $text_box_size = $text_box->getSize();

        // Overlay text box onto image

        // First work out where it should go... 

        // Co-ordinates of top-left corner of new image against original image
        $x_dimension['left'] = 0;
        $x_dimension['center'] = (int) round(($image_width-$text_box_size->getWidth()) / 2,0);
        $x_dimension['right'] = $image_width-$text_box_size->getWidth();
        $y_dimension['top'] = 0;
        $y_dimension['center'] = (int) round(($image_height-$text_box_size->getHeight()) / 2,0);
        $y_dimension['bottom'] = $image_height-$text_box_size->getHeight();
        
        // Work out the offset (if any)
        $x_position = $x_dimension[$position_horizontal] + $offset_horizontal;
        $y_position = $y_dimension[$position_vertical] + $offset_vertical;

        // Check that position is within image
        // $x_position = max(min($x_position, $x_dimension['right']),0);
        // $y_position = max(min($y_position, $y_dimension['bottom']),0);

        // Paste it in
        $image->paste($text_box, new PointSigned($x_position,$y_position));

        // 5) End
        // ======
        unset($empty_box);
        unset($canvas);
        unset($text_box);

        ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_adding_text_overlay_complete'));                    

        return $image;
    }
}